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

	// Block styles are queued before this callback. Move them after the theme
	// stylesheets so a block rule wins when specificity is equal.
	cs__enqueue_block_styles_after_theme();

	// JS
	wp_enqueue_script('theme-main', get_template_directory_uri() .'/assets/js/dist/main.min.js', array(), filemtime(get_template_directory() .'/assets/js/dist/main.min.js'), true);
}
add_action('wp_enqueue_scripts', 'cs__enqueue_assets');


/* --- Block styles after the theme --- */
function cs__enqueue_block_styles_after_theme(){
	// On-demand block CSS is hoisted into this placeholder. Re-queue it so the
	// hoist lands after theme-main, not before it.
	if ( wp_style_is('wp-block-styles-placeholder', 'enqueued') ){
		wp_dequeue_style('wp-block-styles-placeholder');
		wp_enqueue_style('wp-block-styles-placeholder');
	}

	if ( ! class_exists('WP_Block_Type_Registry') ){
		return;
	}

	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $block_type ){
		if ( str_starts_with($block_type->name, 'core/') ){
			continue;
		}
		foreach ( $block_type->style_handles as $handle ){
			if ( wp_style_is($handle, 'enqueued') ){
				wp_dequeue_style($handle);
				wp_enqueue_style($handle);
			}
		}
	}
}


/* --- Gutenberg styles and scripts --- */
function cs__enqueue_gutenberg_assets(){
	// CSS
	add_editor_style('assets/css/editor.min.css');

	// JS
	wp_enqueue_script('theme-editor', get_theme_file_uri('/assets/js/block-styles.js'), array('wp-blocks', 'wp-dom'), filemtime(get_theme_file_path('/assets/js/block-styles.js')), true);
}
add_action('enqueue_block_editor_assets', 'cs__enqueue_gutenberg_assets');


/* --- Block styles after theme styles in the editor --- */
function cs__editor_block_styles_last( $settings ){
	if ( ! function_exists('cs__get_blocks') ){
		return $settings;
	}

	$css = '';
	foreach ( cs__get_blocks() as $block ){
		$dir = get_stylesheet_directory() ."/parts/block/{$block}";
		foreach ( array('style.min.css', 'editor.min.css') as $file ){
			$path = "{$dir}/{$file}";
			if ( is_file($path) ){
				$css .= file_get_contents($path) ."\n";
			}
		}
	}

	if ( $css === '' ){
		return $settings;
	}

	// Theme editor CSS is appended after the iframe's block styles and scoped to
	// .editor-styles-wrapper, which raises its specificity. Append the block CSS
	// the same way so it comes last.
	$settings['styles'][] = array(
		'css'            => $css,
		'__unstableType' => 'theme',
		'isGlobalStyles' => false,
	);

	return $settings;
}
add_filter('block_editor_settings_all', 'cs__editor_block_styles_last');


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