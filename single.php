<?php
/**
 * Single Post Template
 */

get_header();
?>

<div class="container">
	<?php
	if ( function_exists('cs__the_breadcrumbs') ){
		cs__the_breadcrumbs();
	}
	?>

	<?php while ( have_posts() ): the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<div class="entry-content"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>