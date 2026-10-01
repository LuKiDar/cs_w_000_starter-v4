<?php
/**
 * Customize theme
 *
 * Two v3 defects are corrected here:
 *  1. The social options are registered under the slugs the social template
 *     actually reads. v3 registered a `twitter` slug while the template read
 *     `cs_social_x`, so the field saved to an option nothing consumed.
 *  2. The Footer section is not registered. The ACF options page
 *     ("Options: Theme Settings", `theme-settings`) already owns the footer
 *     settings; shipping the same fields in both the Customizer and the options
 *     page left two competing sources of truth. The Customizer is the primary
 *     settings surface in v4, and the options page is the fallback
 *     (inc/plugin-acf.php) for projects that want it instead.
 */

function cs__customize_register( $wp_customize ){
	// --- 1. Header Section ---
	$wp_customize->add_section('cs_header_section', array(
		'title'				=> __('Header', CSWP),
		'priority'			=> 101,
	));

	$wp_customize->add_setting('cs_header_button_text', array(
		'default'			=> '',
		'type'				=> 'option',
		'sanitize_callback'	=> 'sanitize_text_field',
	));
	$wp_customize->add_control('cs_header_button_text', array(
		'label'				=> __('Button Text', CSWP),
		'type'				=> 'text',
		'section'			=> 'cs_header_section',
	));

	$wp_customize->add_setting('cs_header_button_url', array(
		'default'			=> '',
		'type'				=> 'option',
		'sanitize_callback'	=> 'esc_url_raw',
	));
	$wp_customize->add_control('cs_header_button_url', array(
		'label'				=> __('Button URL', CSWP),
		'type'				=> 'url',
		'section'			=> 'cs_header_section',
	));

	$wp_customize->add_setting('cs_header_button_new_tab', array(
		'default'			=> false,
		'type'				=> 'option',
		'sanitize_callback'	=> 'wp_validate_boolean',
	));
	$wp_customize->add_control('cs_header_button_new_tab', array(
		'label'				=> __('Open link in a new window', CSWP),
		'type'				=> 'checkbox',
		'section'			=> 'cs_header_section',
	));

	// --- 2. Social Networks Section ---
	$wp_customize->add_section('cs_social_section', array(
		'title'				=> __('Social Networks', CSWP),
		'priority'			=> 102,
	));

	// The key is the slug, not the label: the social template
	// (parts/social-networks-menu.php) reads `cs_social_{slug}` options, and it
	// reads `cs_social_x` for Twitter/X -- v3 registered `cs_social_twitter`,
	// which the template never looked at.
	$social_networks = array(
		'email'		=> 'Email',
		'facebook'	=> 'Facebook',
		'instagram'	=> 'Instagram',
		'linkedin'	=> 'LinkedIn',
		'x'			=> 'X (Twitter)',
		'youtube'	=> 'YouTube',
	);
	foreach ( $social_networks as $slug => $label ){
		$wp_customize->add_setting("cs_social_{$slug}", array(
			'default'			=> '',
			'type'				=> 'option',
			'sanitize_callback'	=> 'esc_url_raw',
		));
		$wp_customize->add_control("cs_social_{$slug}", array(
			'label'				=> $label,
			'type'				=> ( $slug=='email' ) ? 'text' : 'url',
			'section'			=> 'cs_social_section',
		));
	}
}
add_action('customize_register', 'cs__customize_register');
