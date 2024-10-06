<?php

namespace ApiMaker\Functionality;

use ApiMaker\Components\PhpValidator;
use PluboRoutes\RoutesProcessor;
use PluboRoutes\Endpoint\GetEndpoint;
use PluboRoutes\Endpoint\PostEndpoint;
use PluboRoutes\Endpoint\PutEndpoint;
use PluboRoutes\Endpoint\DeleteEndpoint;
use PluboRoutes\Middleware\CacheMiddleware;
use PluboRoutes\Middleware\JsonTokenValidationMiddleware;
use PluboRoutes\Middleware\SchemaValidator;
use PluboRoutes\Middleware\RateLimitMiddleware;
use PluboRoutes\Middleware\CorsMiddleware;

class ApiEndpoints
{

	protected $plugin_name;
	protected $plugin_version;

	public function __construct($plugin_name, $plugin_version)
	{
		$this->plugin_name = $plugin_name;
		$this->plugin_version = $plugin_version;

		add_filter('after_setup_theme', [$this, 'load_plubo_routes']);
		add_filter('plubo/endpoints', [$this, 'add_endpoints']);
	}

	public function load_plubo_routes()
	{
		RoutesProcessor::init();
	}

	private function get_endpoint_class($type)
	{
		switch ($type) {
			case 'GET':
				return GetEndpoint::class;
			case 'POST':
				return PostEndpoint::class;
			case 'PUT':
				return PutEndpoint::class;
			case 'DELETE':
				return DeleteEndpoint::class;
			default:
				return null;
		}
	}

	private function add_middlewares($endpoint, $post_id, $type)
	{
		$json_schema = carbon_get_post_meta($post_id, 'json_schema');
		$jwt_protection = carbon_get_post_meta($post_id, 'jwt_protection');
		$jwt_secret = carbon_get_post_meta($post_id, 'jwt_secret');
		$save_transient = carbon_get_post_meta($post_id, 'save_transient');
		$transient_seconds = carbon_get_post_meta($post_id, 'transient_seconds');
		$rate_limit = carbon_get_post_meta($post_id, 'rate_limit');
		$rate_limit_max_calls = carbon_get_post_meta($post_id, 'rate_limit_max_calls');
		$rate_limit_every = carbon_get_post_meta($post_id, 'rate_limit_every');
		$rate_limit_by = carbon_get_post_meta($post_id, 'rate_limit_by');

		if ($json_schema) {
			$endpoint->useMiddleware(new SchemaValidator(json_decode($json_schema, true)));
		}

		if ($jwt_protection) {
			$endpoint->useMiddleware(new JsonTokenValidationMiddleware($jwt_secret));
		}

		if ($save_transient) {
			$endpoint->useMiddleware(new CacheMiddleware((int)$transient_seconds));
		}

		if ($rate_limit) {
			$endpoint->useMiddleware(new RateLimitMiddleware((int)$rate_limit_max_calls, (int)$rate_limit_every), $rate_limit_by ?: 'int');
		}

		$endpoint->useMiddleware(new CorsMiddleware('*', [$type], ['Content-Type', 'Authorization']));
	}

	public function add_endpoints($endpoints)
	{
		$ep = get_transient('api_maker_endpoints');
		if ($ep === false) {
			$query = new \WP_Query([
				'post_type'   => 'api_endpoint',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'meta_query'  => [
					'relation' => 'AND',
					[
						'key'     => 'is_safe',
						'value'   => true,
						'compare' => '=',
						'type'    => 'BOOLEAN'
					],
					[
						'key'     => 'status',
						'value'   => 'active',
						'compare' => '=',
					]
				],
				'no_found_rows' => true,
			]);

			$ep = $query->posts; // Get the posts array
			set_transient('api_maker_endpoints', $ep, 5 * DAY_IN_SECONDS);
		}

		$forbidden_functions = PhpValidator::get_dangerous_functions();

		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		foreach ($ep as $post) {
			$post_id = $post->ID;

			$namespace = wp_get_post_terms($post_id, 'namespace');
			$route = carbon_get_post_meta($post_id, 'route');
			$version = carbon_get_post_meta($post_id, 'version');
			$type = carbon_get_post_meta($post_id, 'type');
			$callback_type = carbon_get_post_meta($post_id, 'callback_type');
			$function_code = carbon_get_post_meta($post_id, 'function_code');
			$function_name = carbon_get_post_meta($post_id, 'function_name');
			$status = carbon_get_post_meta($post_id, 'status');

			if ($status !== 'active') {
				continue;
			}

			if ($callback_type === 'code') {
				if (!defined('ALLOW_API_MAKER_CODE_EDITOR') || !ALLOW_API_MAKER_CODE_EDITOR || empty($function_code)) {
					continue;
				}
			} else if (!$function_name) {
				continue;
			}

			$is_safe = get_post_meta($post_id, 'is_safe', true);
			if (!$is_safe) {
				continue;
			}

			$namespace = isset($namespace[0]) ? sanitize_title($namespace[0]->slug) : '';
			$version = sanitize_title($version);
			$route = trim($route, '/');

			//CHECK ALL DATA IS PRESENT
			if (empty($namespace) || empty($route) || empty($type)) {
				continue;
			}

			//CHECK ENDPOINT TYPE IS CORRECT
			$endpoint_class = $this->get_endpoint_class($type);
			if (!$endpoint_class) {
				continue;
			}

			if ($callback_type === 'code') {
				// Validate the user-provided function code using PhpValidator
				$validation_result = PhpValidator::validate_php($function_code);

				if (is_wp_error($validation_result)) {
					error_log('Validation Error in endpoint ID ' . $post_id . ': ' . $validation_result->get_error_message());
					continue; // Skip this endpoint due to validation error
				}

				// Create a temporary file for executing user code
				$temp_file = wp_tempnam('api_maker', get_temp_dir()) . '.php';

				// Write the user-provided function code to the temporary file
				$wrapped_code = "<?php\nfunction api_maker_user_function_{$post_id}(\$request) {\n" . $function_code . "\n}";
				$wp_filesystem->put_contents($temp_file, $wrapped_code);

				// Define the callback function to include the temporary file
				$callback = function ($request) use ($temp_file, $post_id) {
					try {
						set_time_limit(10);
						ini_set('memory_limit', '128M');
						ini_set('allow_url_fopen', '0');
						ini_set('allow_url_include', '0');

						if (file_exists($temp_file)) {
							include $temp_file;
							$function_name = "api_maker_user_function_{$post_id}";

							if (function_exists($function_name)) {
								return $function_name($request);
							} else {
								return esc_html__('Function not defined properly.', 'api-maker');
							}
						} else {
							return esc_html__('Temporary file not found.', 'api-maker');
						}
					} catch (\Exception $e) {
						error_log($e->getMessage());
						return esc_html__('Error executing endpoint.', 'api-maker');
					} finally {
						// Delete the temporary file after execution
						wp_delete_file($temp_file);
					}
				};
			} else {
				// Sanitize the function name and ensure it is a callable function
				$function_name = sanitize_text_field($function_name);
				if (!function_exists($function_name) || in_array($function_name, $forbidden_functions, true)) {
					/* translators: %1$s: Function name %2$d: ID */
					error_log(sprintf(esc_html__('Forbidden or undefined function "%1$s" used in endpoint ID: %2$d', 'api-maker'), $function_name, $post_id));
					continue;
				}
				$callback = function ($request) use ($function_name) {
					if (function_exists($function_name)) {
						return $function_name($request);
					} else {
						return esc_html__('Function not defined properly.', 'api-maker');
					}
				};
			}

			$endpoint = new $endpoint_class("$namespace/$version", $route, $callback);
			$this->add_middlewares($endpoint, $post_id, $type);

			$endpoints[] = $endpoint;
		}

		return $endpoints;
	}
}
