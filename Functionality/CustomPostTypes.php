<?php

namespace ApiMaker\Functionality;

class CustomPostTypes
{

	protected $plugin_name;
	protected $plugin_version;

	public function __construct($plugin_name, $plugin_version)
	{
		$this->plugin_name = $plugin_name;
		$this->plugin_version = $plugin_version;

		add_action('init', [$this, 'register_post_types']);
		add_filter('manage_api_endpoint_posts_columns', [$this, 'set_custom_columns']);
		add_action('manage_api_endpoint_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
		add_action('save_post_api_endpoint', [$this, 'update_endpoints_transient']);
		add_action('delete_post', [$this, 'update_endpoints_transient']);
	}

	public function register_post_types()
	{
		$args = [
			'labels' => $this->get_post_type_labels(),
			'public' => false,
			'show_ui' => true,
			'menu_icon' => 'dashicons-rest-api',
			'supports' => ['title'],
			'show_in_menu' => true,
			'has_archive' => false,
			'capability_type' => 'post',
		];

		$registered = register_post_type('api_endpoint', $args);

		if (is_wp_error($registered)) {
			error_log('Failed to register post type: ' . $registered->get_error_message());
		}
	}

	private function get_post_type_labels()
	{
		return [
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
		];
	}

	public function set_custom_columns($columns)
	{
		$columns['route'] = __('Route', 'api-maker');
		$columns['type'] = __('Type', 'api-maker');
		$columns['specifications'] = __('Specifications', 'api-maker');
		return $columns;
	}

	public function custom_column_content($column, $post_id)
	{
		switch ($column) {
			case 'route':
				$this->display_route($post_id);
				break;
			case 'type':
				$this->display_type($post_id);
				break;
			case 'specifications':
				$this->display_specifications($post_id);
				break;
		}
	}

	private function display_route($post_id)
	{
		$namespace = wp_get_post_terms($post_id, 'namespace', ['fields' => 'slugs']);
		$version = carbon_get_post_meta($post_id, 'version');
		$route = carbon_get_post_meta($post_id, 'route');

		$full_route = implode('/', array_filter([$namespace[0] ?? '', $version, $route]));

		$url = get_rest_url(null, $full_route);
		echo '<a href="' . esc_attr($url) . '" target="_blank">' . esc_html($url) . '</a>';
	}

	private function display_type($post_id)
	{
		$type = carbon_get_post_meta($post_id, 'type');
		echo esc_html($type);
	}

	private function display_specifications($post_id)
	{
		$specifications = [];

		if (carbon_get_post_meta($post_id, 'jwt_protection')) {
			$specifications[] = esc_html__('JWT Enabled', 'api-maker');
		}

		if (carbon_get_post_meta($post_id, 'save_transient')) {
			$transient_seconds = absint(carbon_get_post_meta($post_id, 'transient_seconds'));
			$human_readable_time = $this->get_human_readable_time($transient_seconds);
			$specifications[] = sprintf(
				esc_html__('Cache: %s', 'api-maker'),
				$human_readable_time
			);
		}

		if (carbon_get_post_meta($post_id, 'rate_limit')) {
			$rate_limit_max_calls = absint(carbon_get_post_meta($post_id, 'rate_limit_max_calls'));
			$rate_limit_every = absint(carbon_get_post_meta($post_id, 'rate_limit_every'));
			$human_readable_time = $this->get_human_readable_time($rate_limit_every);
			$specifications[] = sprintf(
				esc_html__('Rate Limited: %d calls per %s', 'api-maker'),
				$rate_limit_max_calls,
				$human_readable_time
			);
		}

		if (!empty($specifications)) {
			echo '<ul>';
			foreach ($specifications as $specification) {
				echo '<li>' . esc_html($specification) . '</li>';
			}
			echo '</ul>';
		}
	}

	private function get_human_readable_time($seconds)
	{
		$seconds = absint($seconds);

		if ($seconds < MINUTE_IN_SECONDS) {
			/* translators: %s: Number of seconds */
			return sprintf(_n('%s second', '%s seconds', $seconds, 'api-maker'), number_format_i18n($seconds));
		} elseif ($seconds < HOUR_IN_SECONDS) {
			$minutes = floor($seconds / MINUTE_IN_SECONDS);
			/* translators: %s: Number of minutes */
			return sprintf(_n('%s minute', '%s minutes', $minutes, 'api-maker'), number_format_i18n($minutes));
		} elseif ($seconds < DAY_IN_SECONDS) {
			$hours = floor($seconds / HOUR_IN_SECONDS);
			/* translators: %s: Number of hours */
			return sprintf(_n('%s hour', '%s hours', $hours, 'api-maker'), number_format_i18n($hours));
		} else {
			$days = floor($seconds / DAY_IN_SECONDS);
			/* translators: %s: Number of days */
			return sprintf(_n('%s day', '%s days', $days, 'api-maker'), number_format_i18n($days));
		}
	}

	public function update_endpoints_transient($post_id)
	{
		if (wp_is_post_revision($post_id) || get_post_type($post_id) !== 'api_endpoint') {
			return;
		}

		delete_transient('api_maker_endpoints');
	}
}
