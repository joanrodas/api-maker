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
		add_action('ApiMaker/setup', [$this, 'add_admin_capabilities']);
		add_action('ApiMaker/deactivation', [$this, 'remove_admin_capabilities']);
	}

	public function register_post_types()
	{
		$args = [
			'labels'             => $this->get_post_type_labels(),
			'public'             => false,
			'show_ui'            => true,
			'menu_icon'          => 'dashicons-rest-api',
			'supports'           => ['title'],
			'show_in_menu'       => true,
			'has_archive'        => false,
			'capability_type'    => 'api_endpoint',
			'capabilities'       => $this->get_post_type_capabilities(),
			'map_meta_cap'       => true,
		];

		register_post_type('api_endpoint', $args);
	}

	private function get_post_type_labels()
	{
		return [
			'name' => esc_html__('Endpoints', 'api-maker'),
			'singular_name' => esc_html__('Endpoint', 'api-maker'),
			'add_new' => esc_html__('Add New Endpoint', 'api-maker'),
			'add_new_item' => esc_html__('Add New Endpoint', 'api-maker'),
			'edit_item' => esc_html__('Edit Endpoint', 'api-maker'),
			'new_item' => esc_html__('New Endpoint', 'api-maker'),
			'view_item' => esc_html__('View Endpoint', 'api-maker'),
			'search_items' => esc_html__('Search Endpoints', 'api-maker'),
			'not_found' => esc_html__('No Endpoints found', 'api-maker'),
			'not_found_in_trash' => esc_html__('No Endpoints found in Trash', 'api-maker'),
		];
	}

	private function get_post_type_capabilities()
	{
		return [
			'edit_post'              => 'edit_api_endpoint',
			'read_post'              => 'read_api_endpoint',
			'delete_post'            => 'delete_api_endpoint',
			'edit_posts'             => 'edit_api_endpoints',
			'edit_others_posts'      => 'edit_others_api_endpoints',
			'publish_posts'          => 'publish_api_endpoints',
			'read_private_posts'     => 'read_private_api_endpoints',
			'delete_posts'           => 'delete_api_endpoints',
			'delete_private_posts'   => 'delete_private_api_endpoints',
			'delete_published_posts' => 'delete_published_api_endpoints',
			'delete_others_posts'    => 'delete_others_api_endpoints',
			'edit_private_posts'     => 'edit_private_api_endpoints',
			'edit_published_posts'   => 'edit_published_api_endpoints',
			'create_posts'           => 'create_api_endpoints'
		];
	}

	public function add_admin_capabilities()
	{
		$role = get_role('administrator');

		if ($role) {
			$capabilities = array_values($this->get_post_type_capabilities());

			foreach ($capabilities as $cap) {
				$role->add_cap($cap);
			}
		}
	}

	public function remove_admin_capabilities()
	{
		$role = get_role('administrator');

		if ($role) {
			$capabilities = array_values($this->get_post_type_capabilities());

			foreach ($capabilities as $cap) {
				$role->remove_cap($cap);
			}
		}
	}

	public function set_custom_columns($columns)
	{
		$columns['route'] = esc_html__('Route', 'api-maker');
		$columns['type'] = esc_html__('Type', 'api-maker');
		$columns['status'] = esc_html__('Status', 'api-maker');
		$columns['specifications'] = esc_html__('Specifications', 'api-maker');
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
			case 'status':
				$this->display_status($post_id);
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

	private function display_status($post_id)
	{
		$is_safe = get_post_meta($post_id, 'is_safe', true);
		if (!$is_safe) {
			echo esc_html('Inactive: Unsafe', 'api-maker');
			return;
		}

		$status = carbon_get_post_meta($post_id, 'status');
		echo esc_html($status);
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
				/* translators: %s: Number of seconds in human readable format */
				esc_html__('Cache: %s', 'api-maker'),
				$human_readable_time
			);
		}

		if (carbon_get_post_meta($post_id, 'rate_limit')) {
			$rate_limit_max_calls = absint(carbon_get_post_meta($post_id, 'rate_limit_max_calls'));
			$rate_limit_every = absint(carbon_get_post_meta($post_id, 'rate_limit_every'));
			$rate_limit_by = carbon_get_post_meta($post_id, 'rate_limit_by');
			$human_readable_time = $this->get_human_readable_time($rate_limit_every);
			$specifications[] = sprintf(
				/* translators: %1$s: Limited by type (IP, user, endpoint) %2$d: Number of calls %3$s Number of seconds in human readable format */
				esc_html__('Rate Limited by %1$s: %2$d calls per %3$s', 'api-maker'),
				esc_html($rate_limit_by),
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
