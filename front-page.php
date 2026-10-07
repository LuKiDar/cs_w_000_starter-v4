<?php
/**
 * Front Page Template
 *
 * Covers two WordPress settings and must tell them apart with is_home():
 * - is_home() true  -> the front page shows the latest posts: render the card list.
 * - is_home() false -> the front page is a static page: render that page, as page.php does.
 */

get_header();


if ( is_home() ): ?>
	<?php if ( function_exists('cs__the_breadcrumbs') ) cs__the_breadcrumbs(); ?>

	<header class="archive-header">
		<h1 class="archive-header__title"><?= esc_html(get_the_title(get_queried_object_id())); ?></h1>
	</header>

	<?php if ( have_posts() ): ?>
		<div class="card-list">
			<?php while ( have_posts() ): the_post(); ?>
				<?php get_template_part('parts/content/post-card', '', ['post_id' => get_the_ID()]); ?>
			<?php endwhile; ?>
		</div>

		<?php if ( function_exists('cs__the_pagination') ) cs__the_pagination(); ?>

	<?php else: ?>
		<p class="no-results"><?php esc_html_e('Nothing found.', CSWP); ?></p>

	<?php endif; ?>

<?php else: ?>
	<?php if ( have_posts() ): ?>
		<?php while ( have_posts() ): the_post(); ?>
			<?php get_template_part('parts/content/post-entry'); ?>
		<?php endwhile; ?>
		
		<?php if ( function_exists('cs__the_pagination') ) cs__the_pagination(); ?>

	<?php else: ?>
		<p class="no-results"><?php esc_html_e('Nothing found.', CSWP); ?></p>

	<?php endif; ?>

<?php endif; ?>

<?php get_footer(); ?>