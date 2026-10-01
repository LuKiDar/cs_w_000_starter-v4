<?php
/**
 * Blog Index Template
 */

get_header();

if ( function_exists('cs__the_breadcrumbs') ){
	cs__the_breadcrumbs();
}

$page_for_posts = (int) get_option('page_for_posts');
?>

<header class="archive-header">
	<div class="container">
		<h1 class="archive-header__title"><?= esc_html(get_the_title($page_for_posts)); ?></h1>
	</div>
</header>

<div class="container">
	<?php if ( have_posts() ): ?>
		<div class="card-list">
			<?php while ( have_posts() ): the_post(); ?>
				<?php get_template_part('parts/content/post-card', '', ['post_id' => get_the_ID()]); ?>
			<?php endwhile; ?>
		</div>

		<?php if ( function_exists('cs__the_pagination') ): ?>
			<?php cs__the_pagination(); ?>
		<?php else: ?>
			<?php the_posts_pagination(); ?>
		<?php endif; ?>
	<?php else: ?>
		<p class="no-results"><?php esc_html_e('Nothing found.', CSWP); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>