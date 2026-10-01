<?php
/**
 * Author Archive Template
 */

get_header();

if ( function_exists('cs__the_breadcrumbs') ){
	cs__the_breadcrumbs();
}
?>

<header class="archive-header">
	<div class="container">
		<h1 class="archive-header__title"><?php the_archive_title(); ?></h1>
		<?php the_archive_description('<div class="archive-header__description">', '</div>'); ?>
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