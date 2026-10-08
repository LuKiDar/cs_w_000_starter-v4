<?php
/**
 * Blog Index Template
 */

get_header();

$page_for_posts = (int) get_option('page_for_posts'); ?>

<div class="archive-page container">
	<?php if ( function_exists('cs__the_breadcrumbs') ) cs__the_breadcrumbs(); ?>

	<header class="archive-header">
		<h1 class="archive-header__title"><?= esc_html(get_the_title($page_for_posts)); ?></h1>
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
</div>

<?php get_footer(); ?>