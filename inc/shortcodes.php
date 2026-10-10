<?php
/**
 * Shortcodes
 */

/* --- [cs-year] : the current year, in the site's timezone --- */
function cs__shortcode_year( $atts = array() ){
	$atts = shortcode_atts(array(
		'format' => 'Y',
	), $atts, 'cs-year');

	return esc_html( date_i18n( $atts['format'] ) );
}
add_shortcode('cs-year', 'cs__shortcode_year');


/* --- [cs-button] : a theme button. style="outline", color="white", target="_blank" --- */
function cs__shortcode_button( $atts, $content = '' ){
	$atts = shortcode_atts(array(
		'url'    => '',
		'style'  => '',
		'color'  => '',
		'target' => '',
	), $atts, 'cs-button');

	$classes = array('button');
	if ( $atts['style']==='outline' ){
		$classes[] = 'is-outline';
	}
	if ( $atts['color']==='white' ){
		$classes[] = 'has-color-white';
	}

	$label = trim(wp_strip_all_tags($content));
	if ( $label==='' ){
		return '';
	}

	$html = '<a class="'. esc_attr(implode(' ', $classes)) .'" href="'. esc_url($atts['url']) .'"';
	if ( $atts['target']==='_blank' ){
		$html .= ' target="_blank" rel="noopener noreferrer"';
	}
	$html .= '>'. esc_html($label) .'</a>';

	return $html;
}
add_shortcode('cs-button', 'cs__shortcode_button');