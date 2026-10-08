<?php
/**
 * Content: Page entry
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('page-entry container'); ?>>
	<h1 class="page-entry__title"><?php the_title(); ?></h1>

	<div class="page-entry__content alignfull container"><?php the_content(); ?></div>
</article>