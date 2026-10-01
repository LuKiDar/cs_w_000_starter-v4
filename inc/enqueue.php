<?php
/**
 * Enqueue Assets
 */

/* --- Theme styles and scripts --- */
function cs__enqueue_assets(){
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style('theme-style', get_stylesheet_uri());

	$main_css = '/assets/css/main.min.css';
	if ( file_exists($dir . $main_css) ){
		wp_enqueue_style('theme-main', $uri . $main_css, array(), filemtime($dir . $main_css), 'all');
	}

	$main_js = '/assets/js/dist/main.min.js';
	if ( file_exists($dir . $main_js) ){
		wp_enqueue_script('theme-main', $uri . $main_js, array(), filemtime($dir . $main_js), true);
	}
}
add_action('wp_enqueue_scripts', 'cs__enqueue_assets');


/* --- Editor styles --- */
function cs__enqueue_editor_assets(){
	$editor_css = get_template_directory() .'/assets/css/editor.min.css';
	if ( file_exists($editor_css) ){
		add_editor_style('assets/css/editor.min.css');
	}
}
add_action('enqueue_block_editor_assets', 'cs__enqueue_editor_assets');