<?php
/**
 * Helper functions
 */

/* --- Get template page ID --- */
function cs__get_template_page_ID( $template, $index=0 ){
	$pages = get_posts(array(
		'post_type' =>'page',
		'meta_key'  =>'_wp_page_template',
		'meta_value'=> 'templates/'. $template .'.php',
		'orderby' => 'ID',
		'order' => 'ASC'
	));

	return $pages[$index]->ID;
}


/* --- Parse content in search of a block --- */
function cs__has_block( $post_content, $block_name='' ){
	$blocks = parse_blocks($post_content);

	foreach ( $blocks as $block ){
		if ( $block['blockName']===$block_name ){
			return true;
		}

		if ( !empty($block['innerBlocks']) ){
			foreach ( $block['innerBlocks'] as $innerBlock ){
				if ( $innerBlock['blockName']===$block_name ){
					return true;
				}
			}
		}
	}

	return false;
}


/* --- Generate URL handle from text line --- */
function cs__generate_url_handle( $text ){
	$handle = strtolower($text);
	$handle = preg_replace('/[^\w\s]/u', '', $handle);
	$handle = preg_replace('/\s+/', '-', $handle);
	$handle = trim($handle, '-');

	return $handle;
}


/* --- Block wrapper id: anchor if set, otherwise the unique block id --- */
function cs__get_block_id( $block ){
	return ! empty($block['anchor']) ? $block['anchor'] : $block['id'];
}


/* --- Block wrapper classes --- */
function cs__get_block_classes( $block, $base = '' ){
	$classes = array();

	if ( $base !== '' ){
		$classes[] = $base;
	}
	if ( ! empty($block['className']) ){
		$classes[] = $block['className'];
	}
	if ( ! empty($block['align']) ){
		$classes[] = 'align'. $block['align'];
	}
	if ( ! empty($block['textAlign']) ){
		$classes[] = 'has-text-align-'. $block['textAlign'];
	}

	return implode(' ', array_unique($classes));
}


/* --- Read a block field, surviving a deactivated ACF Pro --- */
function cs__get_block_field( $name ){
	return function_exists('get_field') ? get_field($name) : null;
}