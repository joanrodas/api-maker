<?php

namespace ApiMaker\Functionality;

use ApiMaker\Components\PhpValidator;
use PluboRoutes\RoutesProcessor;
use PluboRoutes\Endpoint\GetEndpoint;
use PluboRoutes\Endpoint\PostEndpoint;
use PluboRoutes\Endpoint\PutEndpoint;
use PluboRoutes\Endpoint\DeleteEndpoint;
use PluboRoutes\Middleware\Cache;
use PluboRoutes\Middleware\JwtValidation;
use PluboRoutes\Middleware\SchemaValidator;
use PluboRoutes\Middleware\RateLimit;
use PluboRoutes\Middleware\Cors;

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
				return apply_filters('api-maker/custom_endpoint_class', null, $type);
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
			$endpoint->useMiddleware(new JwtValidation($jwt_secret));
		}

		if ($save_transient) {
			$endpoint->useMiddleware(new Cache((int)$transient_seconds));
		}

		if ($rate_limit) {
			$endpoint->useMiddleware(new RateLimit((int)$rate_limit_max_calls, (int)$rate_limit_every), $rate_limit_by ?: 'int');
		}

		$add_cors = apply_filters('api-maker/add_cors', true, $endpoint, $post_id, $type);

		if ($add_cors) {
			$endpoint->useMiddleware(new Cors('*', [$type], ['Content-Type', 'Authorization']));
		}
	}

	public function add_endpoints($endpoints)
	{
		$ep = get_transient('api_maker_endpoints');
		if ($ep === false) {
			$query = new \WP_Query([
				'post_type'   => 'api_endpoint',
				'post_status' => 'publish',
				'posts_per_page' => 200,
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
			$function_name = carbon_get_post_meta($post_id, 'function_name');
			$status = carbon_get_post_meta($post_id, 'status');

			if ($status !== 'active') {
				continue;
			}

			if (!$function_name) {
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

			// Sanitize the function name and ensure it is a callable function
			$function_name = sanitize_text_field($function_name);
			$forbidden_functions = PhpValidator::get_forbidden_functions();
			if (!function_exists($function_name)) {
				update_post_meta($post_id, 'is_safe', false); // Mark as unsafe
				/* translators: %s: Function name */
				update_post_meta($post_id, 'code_validation_errors', sprintf(esc_html__('Undefined function "%s"', 'api-maker'), $function_name));
				/* translators: %1$s: Function name %2$d: ID */
				error_log(sprintf(esc_html__('Undefined function "%1$s" used in endpoint ID: %2$d', 'api-maker'), $function_name, $post_id));
				continue;
			}

			if (in_array($function_name, $forbidden_functions, true)) {
				update_post_meta($post_id, 'is_safe', false); // Mark as unsafe
				/* translators: %s: Function name */
				update_post_meta($post_id, 'code_validation_errors', sprintf(esc_html__('Forbidden function "%s"', 'api-maker'), $function_name)); // Save errors
				/* translators: %1$s: Function name %2$d: ID */
				error_log(sprintf(esc_html__('Forbidden function "%1$s" used in endpoint ID: %2$d', 'api-maker'), $function_name, $post_id));
				continue;
			}


			$callback = function ($request) use ($function_name) {
				try {
					if (function_exists($function_name)) {
						return $function_name($request);
					} else {
						return esc_html__('Function not defined properly.', 'api-maker');
					}
				} catch (\Throwable $th) {
					return esc_html__('Error in function:', 'api-maker') . $th->getMessage();
				}
			};

			$endpoint = new $endpoint_class("$namespace/$version", $route, $callback);
			$this->add_middlewares($endpoint, $post_id, $type);
			do_action('api-maker/after_add_middlewares', $endpoint, $post_id, $type);

			$endpoints[] = $endpoint;
		}

		return $endpoints;
	}
}
