<?php
/**
 * Theme Functions
 */

/* --- Constants --- */
define('CSWP', 'cswp');
include 'inc/constants.php';


/* --- Theme setup --- */
function cs__theme_setup(){
	load_theme_textdomain(CSWP, get_template_directory() .'/languages');

	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('automatic-feed-links');
	add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
	add_theme_support('custom-logo');
	add_theme_support('menus');
	add_theme_support('responsive-embeds');
	add_theme_support('wp-block-styles');
	add_theme_support('editor-styles');
	add_theme_support('align-wide');

	register_nav_menus(array(
		'primary' => __('Primary Menu', CSWP),
		'footer'  => __('Footer Menu', CSWP),
	));

	add_filter('should_load_separate_core_block_assets', '__return_true');
	remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
	remove_theme_support('core-block-patterns');
	define('CORE_UPGRADE_SKIP_NEW_BUNDLED', true);
}
add_action('after_setup_theme', 'cs__theme_setup');


/* --- Enable SVG uploads --- */
function cs__mime_types( $mimes ){
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}
add_filter('upload_mimes', 'cs__mime_types');


/* --- Custom logo classes --- */
add_filter('get_custom_logo', function( $html ){
	$html = str_replace('custom-logo-link', 'logo', $html);
	$html = str_replace('custom-logo', 'logo__image', $html);
	return $html;
});


/* --- Includes --- */
// Theme (always on)
require_once 'inc/enqueue.php';
require_once 'inc/wordpress-cleanup.php';
require_once 'inc/customize.php';
require_once 'inc/helper-functions.php';
require_once 'inc/class-block-styles.php';
require_once 'inc/gutenberg.php';
require_once 'inc/tinymce-editor.php';
require_once 'inc/menu-walker.php';
require_once 'inc/a11y-block-fixes.php';

// Toolbox — uncomment what the project needs. Every file below exists in inc/.
require_once 'inc/breadcrumbs.php';
require_once 'inc/pagination.php';
require_once 'inc/shortcodes.php';
// require_once 'inc/widgets.php';
// require_once 'inc/cpt-post.php';
// require_once 'inc/admin.php';

// Plugin support
// require_once 'inc/plugin-acf.php';       // ACF options page fallback (Customizer is the primary settings surface)