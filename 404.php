<?php
/**
 * 404 Template
 */

get_header(); ?>

<article class="error-404 not-found">
	<h1 class="error-404__title"><?php esc_html_e('Page not found', CSWP); ?></h1>

	<div class="error-404__content">
		<p><?php esc_html_e('The page you are looking for could not be found.', CSWP); ?></p>
		<?php get_search_form(); ?>
	</div>
</article>

<?php get_footer(); ?>