<?php
/**
 * Block: Call to Action
 * Render callback
 */

function cs__render_cta_block( $block, $content = '', $is_preview = false, $post_id = 0 ){
	$block_data = array(
		'eyebrow'    => cs__get_block_field('eyebrow'),
		'heading'    => cs__get_block_field('heading'),
		'subheading' => cs__get_block_field('subheading'),
		'content'    => cs__get_block_field('content'),
		'buttons'    => cs__get_block_field('buttons'),
		'block'      => $block,
	);

	set_query_var('block_data', $block_data);

	$template = __DIR__ .'/render.php';

	if ( file_exists($template) ){
		include $template;
	}
}