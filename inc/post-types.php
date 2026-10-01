<?php
/**
 * Post types
 */

add_action('init', function() {
	// cs__register_post_types(array(
	// 	array(
	// 		'slug'					=> 'example_cpt',
	// 		'single'				=> 'Example',
	// 		'plural'				=> 'Examples',
	// 		'menu_icon'				=> 'dashicons-open-folder',
	// 		'taxonomies'			=> array('example_tax')
	// 	)
	// ));

	// cs__register_taxonomies(array(
	// 	array(
	// 		'cpt'			=> 'example_cpt',
	// 		'slug'			=> 'example_tax',
	// 		'single'		=> 'Example Category',
	// 		'plural'		=> 'Example Categories',
	// 		'hierarchical'	=> true,
	// 		'rewrite'		=> array('slug' => 'category'),
	// 	)
	// ));
});


/* --- Register custom post types --- */
function cs__register_post_types( $types=array() ){
	if ( empty($types) ) return;

	foreach ( $types as $type ){
		$slug				= $type['slug'];
		$single				= $type['single'];
		$plural				= $type['plural'];
		$public				= $type['public'] ?? constant('DEFAULT_CPT_ARGS')['public'];
		$hierarchical		= $type['hierarchical'] ?? constant('DEFAULT_CPT_ARGS')['hierarchical'];
		$publicly_queryable	= $type['publicly_queryable'] ?? constant('DEFAULT_CPT_ARGS')['publicly_queryable'];
		$menu_position		= $type['menu_position'] ?? constant('DEFAULT_CPT_ARGS')['menu_position'];
		$menu_icon			= $type['menu_icon'] ?? constant('DEFAULT_CPT_ARGS')['menu_icon'];
		$supports			= $type['supports'] ?? constant('DEFAULT_CPT_ARGS')['supports'];
		$taxonomies			= $type['taxonomies'] ?? constant('DEFAULT_CPT_ARGS')['taxonomies'];
		$has_archive		= $type['has_archive'] ?? constant('DEFAULT_CPT_ARGS')['has_archive'];
		$rewrite			= $type['rewrite'] ?? array('slug' => str_replace('_', '-', $slug));

		$labels = array_merge(DEFAULT_CPT_LABELS, array(
			'name'					=> _x($plural, 'Post type general name', CSWP),
			'singular_name'			=> _x($single, 'Post type singular name', CSWP),
			'all_items'				=> __('All ' . $plural, CSWP),
			'archives'				=> __($single . ' archives', CSWP),
			'menu_name'				=> _x($plural, 'Admin Menu text', CSWP),
			'name_admin_bar'		=> _x($single, 'Add New on Toolbar', CSWP)
		));

		$args = array_merge(DEFAULT_CPT_ARGS, array(
			'labels'				=> $labels,
			'public'				=> $public,
			'hierarchical'			=> $hierarchical,
			'publicly_queryable'	=> $publicly_queryable,
			'menu_position'			=> $menu_position,
			'menu_icon'				=> $menu_icon,
			'supports'				=> $supports,
			'taxonomies'			=> $taxonomies,
			'has_archive'			=> $has_archive,
			'rewrite'				=> $rewrite,
		));

		register_post_type($slug, $args);
	}
}


/* --- Register custom taxonomies --- */
function cs__register_taxonomies( $taxonomies=array() ){
	if ( empty($taxonomies) ) return;

	foreach ( $taxonomies as $taxonomy ){
		$cpt			= $taxonomy['cpt'];
		$slug			= $taxonomy['slug'];
		$single			= $taxonomy['single'];
		$plural			= $taxonomy['plural'];
		$public			= $taxonomy['public'] ?? constant('DEFAULT_TAXONOMY_ARGS')['public'];
		$hierarchical	= $taxonomy['hierarchical'] ?? constant('DEFAULT_TAXONOMY_ARGS')['hierarchical'];
		$rewrite		= $taxonomy['rewrite'] ?? array('slug' => str_replace('_', '-', $slug));

		$labels = array_merge(DEFAULT_TAXONOMY_LABELS, array(
			'name'				=> _x($plural, 'General name for the taxonomy, usually plural', CSWP),
			'singular_name'		=> _x($single, 'Name for one object of this taxonomy', CSWP),
		));

		$args = array_merge(DEFAULT_TAXONOMY_ARGS, array(
			'labels'			=> $labels,
			'public'			=> $public,
			'hierarchical'		=> $hierarchical,
			'rewrite'			=> $rewrite,
		));

		register_taxonomy($slug, array($cpt), $args);
	}
}
