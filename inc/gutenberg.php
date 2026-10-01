<?php
/**
 * Gutenberg
 */

/* --- Block category --- */
function cs__register_block_categories( $categories ){
	$theme = wp_get_theme();

	return array_merge(
		array(
			array(
				'slug'  => 'cs-blocks',
				'title' => sprintf(__('%s blocks', CSWP), $theme->get('Name')),
			),
		),
		$categories
	);
}
add_filter('block_categories_all', 'cs__register_block_categories', 10, 1);


/* --- Discover block folders --- */
function cs__get_blocks(){
	$directory = get_stylesheet_directory() .'/parts/block/';

	if ( ! is_dir($directory) ){
		return array();
	}

	$entries = scandir($directory);
	$exclude = array('..', '.', '.DS_Store', '_skeleton', '_base-block');

	return array_values(array_diff($entries, $exclude));
}


/* --- Register every folder that carries a block.json --- */
function cs__load_blocks(){
	foreach ( cs__get_blocks() as $block ){
		$block_dir  = get_stylesheet_directory() ."/parts/block/{$block}";
		$block_json = "{$block_dir}/block.json";

		// Skip anything that is not a complete block. No warning: a folder may be
		// mid-creation, and _skeleton is excluded above for the same reason.
		if ( ! file_exists($block_json) ){
			continue;
		}

		$functions_file = "{$block_dir}/block-functions.php";
		if ( file_exists($functions_file) ){
			require_once $functions_file;
		}

		$callback_file = "{$block_dir}/callback.php";
		if ( file_exists($callback_file) ){
			require_once $callback_file;
		}

		// Assets are declared in block.json with file: references, so WordPress
		// loads them only on pages that render the block. Never enqueue here.
		register_block_type($block_json);
	}
}
add_action('init', 'cs__load_blocks', 5);


/* --- ACF field groups stored beside their block --- */
add_filter('acf/settings/load_json', function( $paths ){
	foreach ( cs__get_blocks() as $block ){
		$paths[] = get_stylesheet_directory() ."/parts/block/{$block}";
	}
	return $paths;
});