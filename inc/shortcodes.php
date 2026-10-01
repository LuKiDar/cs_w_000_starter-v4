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
