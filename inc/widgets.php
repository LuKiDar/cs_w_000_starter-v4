<?php
/**
 * Widgets
 */

/* --- Register the one sidebar the theme ships --- */
function cs__register_sidebars(){
	register_sidebar(array(
		'name'          => __('Sidebar', CSWP),
		'id'            => 'cs-sidebar',
		'description'   => __('Shown beside content on templates that opt into a sidebar.', CSWP),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget__title">',
		'after_title'   => '</h2>',
	));
}
add_action('widgets_init', 'cs__register_sidebars');


/* --- Render the sidebar, but only when it actually holds widgets --- */
function cs__the_sidebar(){
	if ( ! is_active_sidebar('cs-sidebar') ){
		return;
	}
	?>
	<aside class="sidebar" role="complementary">
		<?php dynamic_sidebar('cs-sidebar'); ?>
	</aside>
	<?php
}