<?php
/**
 * Template Name: {{TITLE}}
 *
 * @package CStheme
 */

get_header(); ?>

<?php while ( have_posts() ): the_post(); ?>
	<?php get_template_part('parts/content/post-entry', '', ['heading' => 'h1', 'link' => false]); ?>
<?php endwhile; ?>

<?php get_footer(); ?>