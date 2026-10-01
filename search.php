<?php
/**
 * Search Results Template
 */

get_header();

if ( function_exists('cs__the_breadcrumbs') ){
	cs__the_breadcrumbs();
}
?>

<header class="archive-header">
	<div class="container">
		<h1 class="archive-header__title">
			<?php
			printf(
				/* translators: %s: search query */
				esc_html__('Search results for: %s', CSWP),
				'<span class="archive-header__query">'. esc_html(get_search_query()) .'</span>'
			);
			?>
		</h1>

		<p class="archive-header__count">
			<?php
			printf(
				/* translators: %d: number of search results */
				esc_html(_n('%d result found.', '%d results found.', (int) $wp_query->found_posts, CSWP)),
				(int) $wp_query->found_posts
			);
			?>
		</p>
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