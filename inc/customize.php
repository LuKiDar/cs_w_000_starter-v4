<?php
/**
 * Customize theme
 */

/* --- Add section --- */
function cs__customize_add_section( $wp_customize, $id, $title, $priority ){
	$wp_customize->add_section($id, array(
		'title'    => $title,
		'priority' => $priority,
	));
}


/* --- Add setting and control --- */
function cs__customize_add_setting_control( $wp_customize, $id, $section, $label, $type, $default, $sanitize_callback ){
	$wp_customize->add_setting($id, array(
		'default'           => $default,
		'type'              => 'option',
		'sanitize_callback' => $sanitize_callback,
	));
	$wp_customize->add_control($id, array(
		'label'   => $label,
		'type'    => $type,
		'section' => $section,
	));
}


/* --- Add divider --- */
function cs__customize_add_divider( $wp_customize, $id, $section, $title ){
	$wp_customize->add_setting($id, array(
		'default'           => '',
		'type'              => 'option',
		'sanitize_callback' => 'sanitize_text_field',
	));
	$wp_customize->add_control(new WP_Customize_Control($wp_customize, $id, array(
		'label'       => '',
		'description' => '<span class="customize-control-title" style="color: #111; font-size: 120%; font-style: normal; font-weight: 700;">'. $title .'</span><hr />',
		'section'     => $section,
		'type'        => 'hidden',
	)));
}


/* --- Register customize --- */
function cs__customize_register( $wp_customize ){
	// --- 0. General Section ---
	cs__customize_add_section($wp_customize, 'cs_general_section', __('General', CSWP), 100);

	cs__customize_add_setting_control($wp_customize, 'cs_google_api_key', 'cs_general_section', __('Google API Key', CSWP), 'text', '', 'sanitize_text_field');

	// --- 1. Header Section ---
	cs__customize_add_section($wp_customize, 'cs_header_section', __('Header', CSWP), 101);

	$wp_customize->add_setting('cs_header_mobile_logo', array(
		'default'           => '',
		'type'              => 'option',
		'sanitize_callback' => 'absint',
	));
	$wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'cs_header_mobile_logo', array(
		'label'     => __('Mobile Logo', CSWP),
		'section'   => 'cs_header_section',
		'mime_type' => 'image',
	)));
	cs__customize_add_setting_control($wp_customize, 'cs_header_button_text', 'cs_header_section', __('Button Text', CSWP), 'text', '', 'sanitize_text_field');
	cs__customize_add_setting_control($wp_customize, 'cs_header_button_url', 'cs_header_section', __('Button URL', CSWP), 'url', '', 'esc_url_raw');
	cs__customize_add_setting_control($wp_customize, 'cs_header_button_new_tab', 'cs_header_section', __('Open link in a new window', CSWP), 'checkbox', false, 'wp_validate_boolean');

	// --- 2. Social Networks Section ---
	cs__customize_add_section($wp_customize, 'cs_social_section', __('Social Networks', CSWP), 102);

	foreach ( SOCIAL_NETWORKS as $network ){
		$slug         = strtolower(strtok($network, ' '));
		$control_type = ( $slug=='email' ) ? 'text' : 'url';
		$sanitize     = ( $slug=='email' ) ? 'sanitize_email' : 'esc_url_raw';

		cs__customize_add_setting_control($wp_customize, "cs_social_{$slug}", 'cs_social_section', sprintf(__('%s', CSWP), $network), $control_type, '', $sanitize);
	}

	// --- 3. Footer Section ---
	cs__customize_add_section($wp_customize, 'cs_footer_section', __('Footer', CSWP), 131);

	$wp_customize->add_setting('cs_footer_logo', array(
		'default'           => '',
		'type'              => 'option',
		'sanitize_callback' => 'absint',
	));
	$wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'cs_footer_logo', array(
		'label'     => __('Footer Logo', CSWP),
		'section'   => 'cs_footer_section',
		'mime_type' => 'image',
	)));
	cs__customize_add_setting_control($wp_customize, 'cs_footer_copyright', 'cs_footer_section', __('Copyright Text', CSWP), 'text', '', 'wp_kses_post');
}
add_action('customize_register', 'cs__customize_register');