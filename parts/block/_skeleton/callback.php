<?php
/**
 * Block: {{TITLE}}
 * Render callback
 */

function cs__render_{{FUNC}}_block( $block, $content = '', $is_preview = false, $post_id = 0 ){
	$block_data = array(
		'eyebrow'    => get_field('eyebrow'),
		'heading'    => get_field('heading'),
		'subheading' => get_field('subheading'),
		'content'    => get_field('content'),
		'buttons'    => get_field('buttons'),
		'block'      => $block,
	);

	set_query_var('block_data', $block_data);

	$template = __DIR__ .'/render.php';

	if ( file_exists($template) ){
		include $template;
	}
}