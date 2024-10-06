<?php

namespace ApiMaker\Functionality;

use Carbon_Fields\Container;
use Carbon_Fields\Field;
use ApiMaker\Components\PhpValidator;

class CustomFields
{

	protected $plugin_name;
	protected $plugin_version;

	public function __construct($plugin_name, $plugin_version)
	{
		$this->plugin_name = $plugin_name;
		$this->plugin_version = $plugin_version;

		add_action('after_setup_theme', [$this, 'load_cf']);
		add_action('carbon_fields_register_fields', [$this, 'register_custom_fields']);
		add_filter('carbon_fields_before_field_save', [$this, 'sanitize_php']);
		add_action('admin_footer', [$this, 'display_errors']);
	}

	public function load_cf()
	{
		\Carbon_Fields\Carbon_Fields::boot();
	}

	public function display_errors()
	{
		global $post;
		$screen = get_current_screen();

		// Check if we're on the edit screen for the 'api_endpoint' post type
		if ($screen && $screen->base === 'post' && $screen->post_type === 'api_endpoint') {
			$errors = get_post_meta($post->ID, 'code_validation_errors', true);
			if (!empty($errors)) {
				echo '<div class="notice notice-error"><strong>' . esc_html__('Code Validation Errors:', 'api-maker') . '</strong><pre>';
				echo esc_html($errors);
				echo '</pre></div>';
			}
		}
	}

	function sanitize_php($field)
	{
		if (!isset($_POST['post_ID'])) {
			return $field;
		}

		if (!check_admin_referer('update-post_' . intval($_POST['post_ID']))) {
			return $field;
		}

		if ($field->get_type() == 'code_editor' && $field->get_base_name() == 'function_code') {

			$post_id = intval($_POST['post_ID']);
			$function_code = $field->get_value();
			if (!$function_code) return $field;
			$validation_result = PhpValidator::validate_php($function_code);

			if (is_wp_error($validation_result)) {
				// Mark the post as unsafe and store the errors in post meta
				update_post_meta($post_id, 'is_safe', false); // Mark as unsafe
				update_post_meta($post_id, 'code_validation_errors', $validation_result->get_error_message()); // Save errors
				return $field;
			}

			// Mark the post as safe and clear any previous errors
			update_post_meta($post_id, 'is_safe', true); // Mark as safe
			delete_post_meta($post_id, 'code_validation_errors'); // Clear previous errors
		} else if ($field->get_type() == 'text' && $field->get_base_name() == 'function_name') {

			$post_id = intval($_POST['post_ID']);
			$function_name = sanitize_text_field($field->get_value());
			if (!$function_name) return $field;

			if (!function_exists($function_name)) {
				update_post_meta($post_id, 'is_safe', false); // Mark as unsafe
				/* translators: %s: Function name */
				update_post_meta($post_id, 'code_validation_errors', sprintf(esc_html__('Undefined function "%s"', 'api-maker'), $function_name)); // Save errors
				return $field;
			}

			$forbidden_functions = PhpValidator::get_dangerous_functions();
			if (in_array($function_name, $forbidden_functions, true)) {
				update_post_meta($post_id, 'is_safe', false); // Mark as unsafe
				/* translators: %s: Function name */
				update_post_meta($post_id, 'code_validation_errors', sprintf(esc_html__('Forbidden function "%s"', 'api-maker'), $function_name)); // Save errors
				return $field;
			}

			// Mark the post as safe and clear any previous errors
			update_post_meta($post_id, 'is_safe', true); // Mark as safe
			delete_post_meta($post_id, 'code_validation_errors'); // Clear previous errors
		}

		return $field;
	}

	public function register_custom_fields()
	{

		$fields = [
			Field::make('text', 'route', esc_html__('Route', 'api-maker'))
				->set_help_text(esc_html__('Define the endpoint route, e.g., "custom-route"', 'api-maker')),

			Field::make('select', 'status', esc_html__('Status', 'api-maker'))
				->set_options([
					'active' => 'Active',
					'inactive' => 'Inactive'
				])
				->set_help_text(esc_html__('Specify the status of the endpoint.', 'api-maker')),

			Field::make('select', 'callback_type', esc_html__('Callback Type', 'api-maker'))
				->set_options((defined('ALLOW_API_MAKER_CODE_EDITOR') && ALLOW_API_MAKER_CODE_EDITOR) ? [
					'name' => 'Name',
					'code' => 'Code'
				] : ['name' => 'Name'])
				->set_help_text(esc_html__('Select whether to use a function name or code for the callback. You must define ALLOW_API_MAKER_CODE_EDITOR to true to enable code edition. Do not enable this option if you are not an experienced developer. Copy-pasting code without knowledge can be extremely dangerous.', 'api-maker')),

			Field::make('text', 'function_name', esc_html__('Function Name', 'api-maker'))
				->set_help_text(esc_html__('Provide the function name to execute when this endpoint is accessed.', 'api-maker'))
				->set_conditional_logic([
					[
						'field' => 'callback_type',
						'value' => 'name'
					]
				]),

			Field::make('text', 'version', esc_html__('Version', 'api-maker'))
				->set_help_text(esc_html__('Version of this endpoint, e.g., "v1"', 'api-maker')),

			Field::make('select', 'type', esc_html__('Type', 'api-maker'))
				->set_options([
					'GET' => 'GET',
					'POST' => 'POST',
					'PUT' => 'PUT',
					'DELETE' => 'DELETE'
				])
				->set_help_text(esc_html__('Select the type of this endpoint.', 'api-maker')),

			Field::make('textarea', 'json_schema', esc_html__('JSON Schema'))
				// ->set_language('json')
				// ->set_indent_unit(2)
				// ->set_tab_size(2)
				->set_help_text(esc_html__('Define the JSON schema for validating the input.', 'api-maker'))
				->set_conditional_logic([
					[
						'field' => 'type',
						'value' => ['POST', 'PUT'],
						'compare' => 'IN'
					]
				]),

			Field::make('checkbox', 'jwt_protection', esc_html__('Enable JWT Protection', 'api-maker')),
			Field::make('text', 'jwt_secret', esc_html__('JWT Secret', 'api-maker'))
				->set_help_text(esc_html__('Provide the secret key for JWT protection.', 'api-maker'))
				->set_conditional_logic([
					[
						'field' => 'jwt_protection',
						'value' => true,
					]
				]),
			Field::make('checkbox', 'save_transient', esc_html__('Save Response in Transient', 'api-maker')),
			Field::make('text', 'transient_seconds', esc_html__('Transient Expiry Time (seconds)', 'api-maker'))
				->set_attribute('type', 'number')
				->set_help_text(esc_html__('Define how long the response should be cached in seconds.', 'api-maker'))
				->set_conditional_logic([
					[
						'field' => 'save_transient',
						'value' => true,
					]
				]),
			Field::make('checkbox', 'rate_limit', esc_html__('Enable Rate Limit', 'api-maker')),
			Field::make('text', 'rate_limit_max_calls', esc_html__('Max Calls', 'api-maker'))
				->set_attribute('type', 'number')
				->set_help_text(esc_html__('Set the maximum number of calls.', 'api-maker'))
				->set_conditional_logic([
					[
						'field' => 'rate_limit',
						'value' => true,
					]
				]),
			Field::make('text', 'rate_limit_every', esc_html__('Rate Limit Interval (seconds)', 'api-maker'))
				->set_attribute('type', 'number')
				->set_help_text(esc_html__('Specify the rate limit interval in seconds.', 'api-maker'))
				->set_conditional_logic([
					[
						'field' => 'rate_limit',
						'value' => true,
					]
				]),
			Field::make('select', 'rate_limit_by', esc_html__('Rate Limit By', 'api-maker'))
				->set_options([
					'ip' => 'IP',
					'user' => 'User',
					'endpoint' => 'Endpoint'
				])
				->set_help_text(esc_html__('Specify the rate type.', 'api-maker'))
				->set_conditional_logic([
					[
						'field' => 'rate_limit',
						'value' => true,
					]
				])
		];

		if (defined('ALLOW_API_MAKER_CODE_EDITOR') && ALLOW_API_MAKER_CODE_EDITOR) {
			array_splice($fields, 4, 0, [
				Field::make('code_editor', 'function_code', esc_html__('Code Editor'))
					->set_language('php')
					->set_indent_unit(2)
					->set_tab_size(2)
					->set_help_text(esc_html__('Write the PHP code to execute when this endpoint is accessed ($request available). Do not create a function and proceed with caution.', 'api-maker'))
					->set_conditional_logic([
						[
							'field' => 'callback_type',
							'value' => 'code',
						]
					])
			]);
		}

		Container::make('post_meta', esc_html__('Endpoint Details', 'api-maker'))
			->where('post_type', '=', 'api_endpoint')
			->add_fields($fields);
	}
}
