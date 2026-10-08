<?php
/**
 * Index Template
 * Fallback template for displaying posts
 */

get_header(); ?>

<div class="container">
	<?php if ( function_exists('cs__the_breadcrumbs') ) cs__the_breadcrumbs(); ?>
</div>

<div class="container">
	<?php if ( have_posts() ): ?>
		<?php while ( have_posts() ): the_post(); ?>
			<?php get_template_part('parts/content/post-entry'); ?>
		<?php endwhile; ?>
		
		<?php if ( function_exists('cs__the_pagination') ) cs__the_pagination(); ?>

	<?php else: ?>
		<p class="no-results"><?php esc_html_e('Nothing found.', CSWP); ?></p>

	<?php endif; ?>
</div>

<?php get_footer(); ?>