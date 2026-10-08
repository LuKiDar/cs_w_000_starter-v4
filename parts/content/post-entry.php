<?php
/**
 * Entry: Post
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('post-entry'); ?>>
	<h2 class="post-entry__title">
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>

	<div class="post-entry__content"><?php the_content(); ?></div>
</article>