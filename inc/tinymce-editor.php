<?php
/**
 * TinyMCE editor
 *
 * Styles the classic editor used by ACF WYSIWYG fields.
 * Colors and font sizes come from theme.json.
 */

/* --- Row 1: justify after align right, button shortcodes after the link button --- */
function cs__mce_add_more_buttons( $buttons ){
	$align = array_search('alignright', $buttons, true);

	if ( $align!==false ){
		array_splice($buttons, $align + 1, 0, 'alignjustify');
	} else {
		$buttons[] = 'alignjustify';
	}

	$link = array_search('link', $buttons, true);
	if ( $link!==false ){
		array_splice($buttons, $link + 1, 0, 'cs_buttons');
	} else {
		$buttons[] = 'cs_buttons';
	}

	return $buttons;
}
add_filter('mce_buttons', 'cs__mce_add_more_buttons');


/* --- Row 2: underline, subscript, superscript and code, then the Formats menu --- */
function cs__mce_add_more_buttons_2( $buttons ){
	$extra = array('underline', 'subscript', 'superscript', 'wp_code');
	$strike = array_search('strikethrough', $buttons, true);

	if ( $strike!==false ){
		array_splice($buttons, $strike + 1, 0, $extra);
	} else {
		$buttons = array_merge($extra, $buttons);
	}

	$buttons[] = 'styleselect';

	return $buttons;
}
add_filter('mce_buttons_2', 'cs__mce_add_more_buttons_2');


/* --- Button menu. assets/js/tinymce-buttons.js inserts [cs-button]. --- */
function cs__mce_button_plugin( $plugins ){
	$plugins['cs_buttons'] = add_query_arg(
		'ver',
		(string) filemtime(get_template_directory() .'/assets/js/tinymce-buttons.js'),
		get_template_directory_uri() .'/assets/js/tinymce-buttons.js'
	);

	return $plugins;
}
add_filter('mce_external_plugins', 'cs__mce_button_plugin');


/* --- Theme palette as a TinyMCE color map. CSS-variable colors are skipped. --- */
function cs__mce_color_map(){
	$palette = function_exists('wp_get_global_settings')
		? wp_get_global_settings(array('color', 'palette', 'theme'))
		: array();
	$map = array();

	if ( !is_array($palette) ){
		return $map;
	}

	foreach ( $palette as $color ){
		if ( empty($color['name']) || empty($color['color']) || !is_string($color['color']) ){
			continue;
		}
		if ( !preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color['color'], $matches) ){
			continue;
		}

		$hex = strtoupper($matches[1]);
		if ( strlen($hex)===3 ){
			$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
		}

		$map[] = $hex;
		$map[] = $color['name'];
	}

	return $map;
}


/* --- Font-size formats from the theme.json font size presets --- */
function cs__mce_font_size_formats(){
	$sizes = function_exists('wp_get_global_settings')
		? wp_get_global_settings(array('typography', 'fontSizes', 'theme'))
		: array();
	$items = array();

	if ( !is_array($sizes) ){
		return $items;
	}

	foreach ( $sizes as $size ){
		if ( empty($size['name']) || empty($size['slug']) ){
			continue;
		}

		$items[] = array(
			'title'    => $size['name'],
			'selector' => 'p,h1,h2,h3,h4,h5,h6',
			'classes'  => 'has-'. $size['slug'] .'-font-size',
		);
	}

	return $items;
}


/* --- Pass the theme palette and font-size formats into TinyMCE --- */
function cs__mce_before_init( $settings ){
	$color_map = cs__mce_color_map();
	$font_sizes = cs__mce_font_size_formats();
	$style_formats = array();

	if ( $font_sizes ){
		$style_formats[] = array(
			'title' => 'Font sizes',
			'items' => $font_sizes,
		);
	}

	if ( $color_map ){
		$settings['textcolor_map'] = json_encode($color_map);
		$settings['textcolor_cols'] = (int) min(8, count($color_map) / 2);
	}

	if ( $style_formats ){
		$settings['style_formats'] = json_encode($style_formats);
	}

	return $settings;
}
add_filter('tiny_mce_before_init', 'cs__mce_before_init');


/* --- Theme.json styles for the TinyMCE iframe. content_style is not used: --- */
/* --- WordPress prints init settings without escaping quotes in that string. --- */
function cs__mce_css( $stylesheets ){
	$url = add_query_arg(
		'ver',
		(string) filemtime(get_template_directory() .'/theme.json'),
		admin_url('admin-ajax.php?action=cs_mce_styles')
	);

	if ( $stylesheets ){
		return $stylesheets .','. $url;
	}

	return $url;
}
add_filter('mce_css', 'cs__mce_css');


/* --- Stylesheet printed for the editor iframe by the mce_css URL above --- */
function cs__mce_styles(){
	header('Content-Type: text/css; charset=UTF-8');

	if ( function_exists('wp_get_global_stylesheet') ){
		echo wp_get_global_stylesheet(array('variables', 'presets', 'styles'));
	}

	exit;
}
add_action('wp_ajax_cs_mce_styles', 'cs__mce_styles');


/* --- TinyMCE: remove the custom color picker so only the theme palette is offered --- */
function cs__mce_remove_custom_colors( $plugins ){
	if ( !is_array($plugins) ){
		return $plugins;
	}

	foreach ( $plugins as $key => $plugin_name ){
		if ( $plugin_name==='colorpicker' ){
			unset($plugins[$key]);
		}
	}

	return $plugins;
}
add_filter('tiny_mce_plugins', 'cs__mce_remove_custom_colors');