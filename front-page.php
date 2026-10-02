<?php
/**
 * Front Page Template
 *
 * Covers two WordPress settings and must tell them apart with is_home():
 * - is_home() true  -> the front page shows the latest posts: render the card list.
 * - is_home() false -> the front page is a static page: render that page, as page.php does.
 */

get_header();

if ( function_exists('cs__the_breadcrumbs') ){
	cs__the_breadcrumbs();
}

if ( is_home() ):
	// The front page shows the latest posts. get_the_title() of the queried object
	// is used rather than the_archive_title(), which would print the literal
	// "Archives" here (wp-includes/general-template.php:1957) — not empty, just wrong.
	?>
	<header class="archive-header">
		<div class="container">
			<h1 class="archive-header__title"><?= esc_html(get_the_title(get_queried_object_id())); ?></h1>
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
<?php else: ?>
	<div class="container">
		<?php while ( have_posts() ): the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<h1 class="page-title"><?php the_title(); ?></h1>
				<div class="page-content"><?php the_content(); ?></div>
				<?php wp_link_pages(); ?>
			</article>
		<?php endwhile; ?>
	</div>
<?php endif; ?>

<?php get_footer(); ?>