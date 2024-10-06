<?php

namespace ApiMaker\Functionality;

class Taxonomies
{

	protected $plugin_name;
	protected $plugin_version;

	public function __construct($plugin_name, $plugin_version)
	{
		$this->plugin_name = $plugin_name;
		$this->plugin_version = $plugin_version;

		add_action('init', [$this, 'register_taxonomies']);
	}

	public function register_taxonomies()
	{
		$args = [
			'labels' => $this->get_taxonomy_labels(),
			'public' => false,
			'hierarchical' => false,
			'show_ui' => true,
			'meta_box_cb' => [$this, 'render_meta_box'],
			'show_in_rest' => true,
		];

		$registered = register_taxonomy('namespace', 'api_endpoint', $args);

		if (is_wp_error($registered)) {
			error_log('Failed to register taxonomy: ' . $registered->get_error_message());
		}
	}

	private function get_taxonomy_labels()
	{
		return [
			'name' => esc_html__('Namespaces', 'api-maker'),
			'singular_name' => esc_html__('Namespace', 'api-maker'),
			'search_items' => esc_html__('Search Namespaces', 'api-maker'),
			'all_items' => esc_html__('All Namespaces', 'api-maker'),
			'edit_item' => esc_html__('Edit Namespace', 'api-maker'),
			'update_item' => esc_html__('Update Namespace', 'api-maker'),
			'add_new_item' => esc_html__('Add New Namespace', 'api-maker'),
			'new_item_name' => esc_html__('New Namespace Name', 'api-maker'),
			'menu_name' => esc_html__('Namespaces', 'api-maker'),
		];
	}

	public function render_meta_box($post, $meta_box_properties)
	{
		$tax_name = $meta_box_properties['args']['taxonomy'];
		$terms = get_terms([
			'taxonomy' => $tax_name,
			'hide_empty' => false,
		]);

		if (is_wp_error($terms)) {
			wp_die(esc_html($terms->get_error_message()));
		}

		$post_terms = wp_get_object_terms($post->ID, $tax_name, ['fields' => 'ids']);
		$current = ($post_terms && !is_wp_error($post_terms)) ? $post_terms[0] : 0;

		wp_nonce_field('namespace_meta_box', 'namespace_meta_box_nonce');
?>
		<div id="taxonomy-<?php echo esc_attr($tax_name); ?>" class="categorydiv">
			<input type="hidden" name="tax_input[<?php echo esc_attr($tax_name); ?>]" value="" />
			<ul class="category-tabs">
				<li class="tabs"><a href="#"><?php esc_html_e('Select Namespace', 'api-maker'); ?></a></li>
			</ul>
			<div id="<?php echo esc_attr($tax_name); ?>-all" class="tabs-panel">
				<ul id="<?php echo esc_attr($tax_name); ?>checklist" class="categorychecklist form-no-clear">
					<?php foreach ($terms as $term): ?>
						<li id="<?php echo esc_attr($tax_name . '-' . $term->term_id); ?>">
							<label class="selectit">
								<input type="radio" name="tax_input[<?php echo esc_attr($tax_name); ?>]"
									value="<?php echo esc_attr($term->name); ?>"
									<?php checked($current, $term->term_id); ?> />
								<?php echo esc_html($term->name); ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=' . $tax_name . '&post_type=api_endpoint')); ?>">
						<?php esc_html_e('Add New Namespace', 'api-maker'); ?>
					</a>
				</p>
			</div>
		</div>
<?php
	}
}
