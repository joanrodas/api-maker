<?php

namespace ApiMaker\Functionality;

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
			$endpoint->useMiddleware(new RateLimitMiddleware((int)$rate_limit_max_calls, (int)$rate_limit_every));
		}

		$endpoint->useMiddleware(new CorsMiddleware('*', [$type], ['Content-Type', 'Authorization']));
	}

	public function add_endpoints($endpoints)
	{
		$ep = get_transient('api_maker_endpoints');
		if ($ep === false) {
			$ep = get_posts([
				'post_type' => 'api_endpoint',
				'post_status' => 'publish',
				'numberposts' => -1,
			]);
			set_transient('api_maker_endpoints', $ep, 5 * DAY_IN_SECONDS);
		}

		$forbidden_functions = ['eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];

		global $wp_filesystem;
		if (empty($wp_filesystem)) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		foreach ($ep as $post) {
			$namespace = wp_get_post_terms($post->ID, 'namespace');
			$route = carbon_get_post_meta($post->ID, 'route');
			$version = carbon_get_post_meta($post->ID, 'version');
			$type = carbon_get_post_meta($post->ID, 'type');
			$function_code = carbon_get_post_meta($post->ID, 'function_code');

			$namespace = isset($namespace[0]) ? sanitize_title($namespace[0]->slug) : '';
			$version = sanitize_title($version);
			$route = trim($route, '/');

			//CHECK ALL DATA IS PRESENT
			if (empty($namespace) || empty($route) || empty($type) || empty($function_code)) {
				continue;
			}

			//CHECK ENDPOINT TYPE IS CORRECT
			$endpoint_class = $this->get_endpoint_class($type);
			if (!$endpoint_class) {
				continue;
			}

			// Validate and sanitize the user-provided function code
			foreach ($forbidden_functions as $forbidden_function) {
				if (stripos($function_code, $forbidden_function) !== false) {
					error_log("Forbidden function '$forbidden_function' found in user code for endpoint ID: {$post->ID}");
					continue 2; // Skip this endpoint
				}
			}

			// Create a temporary file for executing user code
			$temp_file = wp_tempnam('api_maker', get_temp_dir()) . '.php';

			// Write the user-provided function code to the temporary file
			$wrapped_code = "<?php\nfunction api_maker_user_function_{$post->ID}(\$request) {\n" . $function_code . "\n}";
			$wp_filesystem->put_contents($temp_file, $wrapped_code);

			// Define the callback function to include the temporary file
			$callback = function ($request) use ($temp_file, $post) {
				try {
					if (file_exists($temp_file)) {
						include $temp_file;
						$function_name = "api_maker_user_function_{$post->ID}";

						if (function_exists($function_name)) {
							return $function_name($request);
						} else {
							return 'Function not defined properly.';
						}
					} else {
						return 'Temporary file not found.';
					}
				} catch (\Exception $e) {
					error_log($e->getMessage());
					return 'Error executing endpoint.';
				} finally {
					// Delete the temporary file after execution
					wp_delete_file($temp_file);
				}
			};

			$endpoint = new $endpoint_class("$namespace/$version", $route, $callback);
			$this->add_middlewares($endpoint, $post->ID, $type);

			$endpoints[] = $endpoint;
		}

		return $endpoints;
	}
}
