<?php
/**
 * Single Post Template
 */

get_header(); ?>

<div class="container">
	<?php if ( function_exists('cs__the_breadcrumbs') ) cs__the_breadcrumbs(); ?>
</div>

<?php while ( have_posts() ): the_post(); ?>
	<?php get_template_part('parts/content/page-entry'); ?>
<?php endwhile; ?>

<?php get_footer(); ?>