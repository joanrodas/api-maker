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

	public function add_endpoints($endpoints)
	{
		$ep = get_posts([
			'post_type' => 'api_endpoint',
			'post_status' => 'publish',
			'numberposts' => -1,
		]);

		$ep = get_transient('api_maker_endpoints');
        if ($ep === false) {
            $ep = get_posts([
                'post_type' => 'api_endpoint',
                'post_status' => 'publish',
                'numberposts' => -1,
            ]);
            set_transient('api_maker_endpoints', $ep, 5 * DAY_IN_SECONDS);
        }

		foreach ($ep as $post) {
			$namespace = wp_get_post_terms($post->ID, 'namespace');
            $route = carbon_get_post_meta($post->ID, 'route');
            $version = carbon_get_post_meta($post->ID, 'version');
            $type = carbon_get_post_meta($post->ID, 'type');
            $function_code = carbon_get_post_meta($post->ID, 'function_code');
            $json_schema = carbon_get_post_meta($post->ID, 'json_schema');
            $jwt_protection = carbon_get_post_meta($post->ID, 'jwt_protection');
            $jwt_secret = carbon_get_post_meta($post->ID, 'jwt_secret');
            $save_transient = carbon_get_post_meta($post->ID, 'save_transient');
            $transient_seconds = carbon_get_post_meta($post->ID, 'transient_seconds');
            $rate_limit = carbon_get_post_meta($post->ID, 'rate_limit');
            $rate_limit_max_calls = carbon_get_post_meta($post->ID, 'rate_limit_max_calls');
            $rate_limit_every = carbon_get_post_meta($post->ID, 'rate_limit_every');
			

			if (empty($namespace) || empty($route) || empty($type)) {
				continue;
			}

			$namespace = sanitize_title($namespace[0]->slug);
			$version = sanitize_title($version);
			$route = trim($route, '/');
			error_log($route);

			error_log("$namespace/$version");
			error_log($type);

			switch ($type) {
				case 'GET':
					$endpoint = new GetEndpoint(
						"$namespace/$version",
						$route,
						function ($request) use ($function_code) {
							ob_start();
							eval($function_code);
							return ob_get_clean();
						}
					);
					break;
				case 'POST':
					$endpoint = new PostEndpoint(
						"$namespace/$version",
						$route,
						function ($request) use ($function_code) {
							ob_start();
							eval($function_code);
							return ob_get_clean();
						}
					);
					break;
				case 'PUT':
					$endpoint = new PutEndpoint(
						"$namespace/$version",
						$route,
						function ($request) use ($function_code) {
							ob_start();
							eval($function_code);
							return ob_get_clean();
						}
					);
					break;
				case 'DELETE':
					$endpoint = new DeleteEndpoint(
						"$namespace/$version",
						$route,
						function ($request) use ($function_code) {
							ob_start();
							eval($function_code);
							return ob_get_clean();
						}
					);
					break;
				default:
					continue 2;
			}

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

			$endpoints[] = $endpoint;
		}

		return $endpoints;
	}
}
