<?php
/**
 * Plugin: Advanced Custom Fields
 */

/* --- ACF: add Menu Level rule --- */
function cs__acf_location_rules_types( $choices ){
	$choices['Menu']['menu_level'] = 'Menu Level';

	return $choices;
}
add_filter('acf/location/rule_types', 'cs__acf_location_rules_types');

function cs__acf_location_rule_values_level( $choices ){
	$choices[0] = 'First';
	$choices[1] = 'Second';

	return $choices;
}
add_filter('acf/location/rule_values/menu_level', 'cs__acf_location_rule_values_level');

function cs__acf_location_rule_match_level( $match, $rule, $options, $field_group ){
	if ( $rule['operator']=='==' ){
		$match = ( $options['nav_menu_item_depth']==$rule['value'] );
	}

	return $match;
}
add_filter('acf/location/rule_match/menu_level', 'cs__acf_location_rule_match_level', 10, 4);


/* --- ACF options page fallback. The Customizer is the primary settings surface. --- */
// if ( function_exists('acf_add_options_page') ):
// 	acf_add_options_page(array(
// 		'page_title' => __('Theme Settings', CSWP),
// 		'menu_title' => __('Theme Settings', CSWP),
// 		'menu_slug'  => 'theme-settings',
// 		'capability' => 'manage_options',
// 		'position'   => '59',
// 		'redirect'   => true,
// 	));
// endif;


/* --- ACF: Google Map API key from the Customizer --- */
// function cs__acf_google_map_api( $api ){
// 	$api['key'] = get_option('cs_google_api_key');
// 	return $api;
// }
// add_filter('acf/fields/google_map/api', 'cs__acf_google_map_api');