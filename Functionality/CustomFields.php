<?php

namespace ApiMaker\Functionality;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

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
	}

	public function load_cf()
	{
		\Carbon_Fields\Carbon_Fields::boot();
	}

	public static function register_custom_fields()
	{
		Container::make('post_meta', __('Endpoint Details', 'api-maker'))
			->where('post_type', '=', 'api_endpoint')
			->add_fields([
				Field::make('text', 'route', __('Route', 'api-maker'))
					->set_help_text(__('Define the endpoint route, e.g., "/custom/route"', 'api-maker')),
				Field::make('textarea', 'function_code', __('Function', 'api-maker'))
					->set_rows(10)
					->set_help_text(__('Write the PHP function code to execute when this endpoint is accessed.', 'api-maker')),
				Field::make('text', 'version', __('Version', 'api-maker'))
					->set_help_text(__('Version of this endpoint, e.g., "v1"', 'api-maker')),
				Field::make('select', 'type', __('Type', 'api-maker'))
					->set_options([
						'GET' => 'GET',
						'POST' => 'POST',
						'PUT' => 'PUT',
					])
					->set_help_text(__('Select the type of this endpoint.', 'api-maker')),
				Field::make('rich_text', 'json_schema', __('JSON Schema', 'api-maker'))
					->set_help_text(__('Define the JSON schema for validating the input.', 'api-maker'))
					->set_conditional_logic([
						[
							'field' => 'type',
							'value' => ['POST', 'PUT'],
							'compare' => 'IN'
						]
					]),
				Field::make('checkbox', 'jwt_protection', __('Enable JWT Protection', 'api-maker')),
				Field::make('text', 'jwt_secret', __('JWT Secret', 'api-maker'))
					->set_help_text(__('Provide the secret key for JWT protection.', 'api-maker'))
					->set_conditional_logic([
						[
							'field' => 'jwt_protection',
							'value' => true,
						]
					]),
				Field::make('checkbox', 'save_transient', __('Save Response in Transient', 'api-maker')),
				Field::make('text', 'transient_seconds', __('Transient Expiry Time (seconds)', 'api-maker'))
					->set_attribute('type', 'number')
					->set_help_text(__('Define how long the response should be cached in seconds.', 'api-maker'))
					->set_conditional_logic([
						[
							'field' => 'save_transient',
							'value' => true,
						]
					]),
				Field::make('checkbox', 'rate_limit', __('Enable Rate Limit', 'api-maker')),
				Field::make('text', 'rate_limit_max_calls', __('Max Calls', 'api-maker'))
					->set_attribute('type', 'number')
					->set_help_text(__('Set the maximum number of calls.', 'api-maker'))
					->set_conditional_logic([
						[
							'field' => 'rate_limit',
							'value' => true,
						]
					]),
				Field::make('text', 'rate_limit_every', __('Rate Limit Interval (seconds)', 'api-maker'))
					->set_attribute('type', 'number')
					->set_help_text(__('Specify the rate limit interval in seconds.', 'api-maker'))
					->set_conditional_logic([
						[
							'field' => 'rate_limit',
							'value' => true,
						]
					]),
			]);
	}
}
