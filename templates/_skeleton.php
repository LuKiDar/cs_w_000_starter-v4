<?php
/**
 * Template Name: {{TITLE}}
 *
 * @package CStheme
 */

get_header();
?>

<main id="main" class="site-main">
	<div class="container">
		<?php while ( have_posts() ): the_post(); ?>
			<article <?php post_class(); ?>>
				<h1 class="page-title"><?php the_title(); ?></h1>
				<div class="page-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>