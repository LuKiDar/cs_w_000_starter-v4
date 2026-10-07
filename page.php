<?php
/**
 * Page Template
 */

get_header(); ?>

<?php if ( function_exists('cs__the_breadcrumbs') ) cs__the_breadcrumbs(); ?>

<?php while ( have_posts() ): the_post(); ?>
	<?php get_template_part('parts/content/post-entry', '', ['heading' => 'h1', 'link' => false]); ?>
<?php endwhile; ?>

<?php get_footer(); ?>