<?php
/**
 * Template Name: {{TITLE}}
 *
 * @package CStheme
 */

get_header(); ?>

<?php while ( have_posts() ): the_post(); ?>
	<?php get_template_part('parts/content/page-entry'); ?>
<?php endwhile; ?>

<?php get_footer(); ?>