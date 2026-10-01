<?php
/**
 * 404 Template
 */

get_header();
?>

<div class="container">
	<article class="error-404 not-found">
		<h1 class="page-title"><?php esc_html_e('Page not found', CSWP); ?></h1>
		<div class="page-content">
			<p><?php esc_html_e('The page you are looking for could not be found.', CSWP); ?></p>
			<?php get_search_form(); ?>
		</div>
	</article>
</div>

<?php get_footer(); ?>