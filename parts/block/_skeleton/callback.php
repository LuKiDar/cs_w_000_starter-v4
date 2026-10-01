<?php
/**
 * Block: {{TITLE}}
 * Render callback
 */

function cs__render_{{FUNC}}_block( $block, $content = '', $is_preview = false, $post_id = 0 ){
	$block_data = array(
		'heading' => cs__get_block_field('heading'),
		'content' => cs__get_block_field('content'),
		'block'   => $block,
	);

	set_query_var('block_data', $block_data);

	$template = __DIR__ .'/render.php';

	if ( file_exists($template) ){
		include $template;
	}
}