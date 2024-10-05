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

	public static function register_taxonomies()
	{
		register_taxonomy('namespace', 'api_endpoint', [
			'labels' => [
				'name' => __('Namespaces', 'api-maker'),
				'singular_name' => __('Namespace', 'api-maker'),
			],
			'public' => false,
			'hierarchical' => false,
			'show_ui' => true,
			'meta_box_cb' => function ($post, $meta_box_properties) {
				$tax_name = $meta_box_properties['args']['taxonomy'];
				$taxonomy = get_taxonomy($tax_name);
				$terms = get_terms(['taxonomy' => $tax_name, 'hide_empty' => false]);
				$post_terms = wp_get_object_terms($post->ID, $tax_name, ['fields' => 'ids']);
				$current = ($post_terms && !is_wp_error($post_terms)) ? $post_terms[0] : 0;
?>
			<div id="taxonomy-<?php echo esc_attr($tax_name); ?>" class="categorydiv">
				<input type="hidden" name="tax_input[<?php echo esc_attr($tax_name); ?>]" value="" />
				<ul class="category-tabs">
					<li class="tabs"><a href="#">Select Namespace</a></li>
				</ul>
				<div id="<?php echo esc_attr($tax_name); ?>-all" class="tabs-panel">
					<ul id="<?php echo esc_attr($tax_name); ?>checklist" class="categorychecklist form-no-clear">
						<?php foreach ($terms as $term) : ?>
							<li id="<?php echo esc_attr($tax_name . '-' . $term->term_id); ?>">
								<label class="selectit">
									<input type="radio" name="tax_input[<?php echo esc_attr($tax_name); ?>]" value="<?php echo esc_attr($term->name); ?>" <?php checked($current, $term->term_id); ?> />
									<?php echo esc_html($term->name); ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<p>
						<a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=' . $tax_name . '&post_type=api_endpoint')); ?>">
							<?php _e('Add New Namespace', 'api-maker'); ?>
						</a>
					</p>
				</div>
			</div>
<?php
			},
		]);
	}
}
