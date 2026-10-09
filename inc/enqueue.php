<?php
/**
 * Enqueue Assets
 */

/* --- Theme styles and scripts --- */
function cs__enqueue_assets(){
	// Remove Contact form 7 styles
	// wp_dequeue_style('contact-form-7');

	// CSS
	wp_enqueue_style('theme-style', get_stylesheet_uri());
	wp_enqueue_style('theme-main', get_template_directory_uri() .'/assets/css/main.min.css', array(), filemtime(get_template_directory() .'/assets/css/main.min.css'), 'all');

	// JS
	wp_enqueue_script('theme-main', get_template_directory_uri() .'/assets/js/dist/main.min.js', array(), filemtime(get_template_directory() .'/assets/js/dist/main.min.js'), true);
}
add_action('wp_enqueue_scripts', 'cs__enqueue_assets');


/* --- Gutenberg styles and scripts --- */
function cs__enqueue_gutenberg_assets(){
	// CSS
	add_editor_style('assets/css/editor.min.css');

	// JS
	wp_enqueue_script('theme-editor', get_theme_file_uri('/assets/js/block-styles.js'), array('wp-blocks', 'wp-dom'), filemtime(get_theme_file_path('/assets/js/block-styles.js')), true);
}
add_action('enqueue_block_editor_assets', 'cs__enqueue_gutenberg_assets');


/* --- Admin styles --- */
function cs__enqueue_admin_styles(){
	wp_enqueue_style('admin-styles', get_template_directory_uri() .'/assets/css/admin.min.css', array(), filemtime(get_template_directory() .'/assets/css/admin.min.css'), 'all');
}
add_action('admin_enqueue_scripts', 'cs__enqueue_admin_styles');


/* --- Login styles --- */
function cs__enqueue_login_styles(){
	wp_enqueue_style('login-styles', get_template_directory_uri() .'/assets/css/login.min.css', array(), filemtime(get_template_directory() .'/assets/css/login.min.css'), 'all');
}
// add_action('login_head', 'cs__enqueue_login_styles');


/* --- Disable default WooCommerce styles --- */
// add_filter('woocommerce_enqueue_styles', '__return_empty_array');