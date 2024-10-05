<?php

namespace ApiMaker\Functionality;

use \ApiMaker\Components\Book;

class CustomPostTypes
{

	protected $plugin_name;
	protected $plugin_version;

	public function __construct($plugin_name, $plugin_version)
	{
		$this->plugin_name = $plugin_name;
		$this->plugin_version = $plugin_version;

		add_action('init', [$this, 'register_post_types']);
		add_filter('manage_api_endpoint_posts_columns', [self::class, 'set_custom_columns']);
		add_action('manage_api_endpoint_posts_custom_column', [self::class, 'custom_column_content'], 10, 2);
		add_action('save_post_api_endpoint', [self::class, 'update_endpoints_transient']);
        add_action('delete_post', [self::class, 'update_endpoints_transient']);
	}

	public function register_post_types()
	{
		register_post_type('api_endpoint', [
			'labels' => [
				'name' => __('Endpoints', 'api-maker'),
				'singular_name' => __('Endpoint', 'api-maker'),
				'add_new' => __('Add New Endpoint', 'api-maker'),
				'add_new_item' => __('Add New Endpoint', 'api-maker'),
				'edit_item' => __('Edit Endpoint', 'api-maker'),
				'new_item' => __('New Endpoint', 'api-maker'),
				'view_item' => __('View Endpoint', 'api-maker'),
				'search_items' => __('Search Endpoints', 'api-maker'),
				'not_found' => __('No Endpoints found', 'api-maker'),
				'not_found_in_trash' => __('No Endpoints found in Trash', 'api-maker'),
			],
			'public' => false,
			'show_ui' => true,
			'menu_icon' => 'dashicons-rest-api',
			'supports' => ['title'],
			'show_in_menu' => true,
			'has_archive' => false,
			'capability_type' => 'post'
		]);
	}

	public static function set_custom_columns($columns)
	{
		$columns['route'] = __('Full Route', 'api-maker');
		$columns['type'] = __('Type', 'api-maker');
		$columns['specifications'] = __('Specifications', 'api-maker');
		return $columns;
	}

	public static function custom_column_content($column, $post_id)
	{
		switch ($column) {
			case 'route':
				$namespace = wp_get_post_terms($post_id, 'namespace', ['fields' => 'slugs']);
				$version = carbon_get_post_meta($post_id, 'version');
				$route = carbon_get_post_meta($post_id, 'route');
				echo esc_html(implode('/', [$namespace[0], $version, $route]));
				break;
			case 'type':
				$type = carbon_get_post_meta($post_id, 'type');
				echo esc_html($type);
				break;
			case 'specifications':
				$specifications = [];
				if (carbon_get_post_meta($post_id, 'jwt_protection')) {
					$specifications[] = 'JWT Enabled';
				}
				if (carbon_get_post_meta($post_id, 'save_transient')) {
					$transient_seconds = carbon_get_post_meta($post_id, 'transient_seconds');
					$specifications[] = 'Cache: ' . intval($transient_seconds) . 's';
				}
				if (carbon_get_post_meta($post_id, 'rate_limit')) {
					$rate_limit_max_calls = carbon_get_post_meta($post_id, 'rate_limit_max_calls');
					$rate_limit_every = carbon_get_post_meta($post_id, 'rate_limit_every');
					$specifications[] = 'Rate Limited: ' . intval($rate_limit_max_calls) . ' calls per ' . intval($rate_limit_every) . 's';
				}
				echo esc_html(implode(', ', $specifications));
				break;
		}
	}

	public static function update_endpoints_transient($post_id) {
		if (wp_is_post_revision($post_id)) {
			return;
		}
	
		// If the post type is not 'api_endpoint', do nothing
		if (get_post_type($post_id) !== 'api_endpoint') {
			return;
		}
	
		delete_transient('api_maker_endpoints');
	}
}
