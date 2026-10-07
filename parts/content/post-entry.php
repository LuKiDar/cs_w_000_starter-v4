<?php
/**
 * Post entry
 *
 * @param array $args { heading: string, link: bool }
 */

$heading = tag_escape($args['heading'] ?? 'h2');
if ( ! in_array($heading, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ) {
	$heading = 'h2';
}

$link = $args['link'] ?? true; ?>


<article id="post-<?php the_ID(); ?>" <?php post_class('post-entry'); ?>>
	<?php if ( $link ): ?>
		<<?= $heading; ?> class="post-entry__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</<?= $heading; ?>>
	<?php else: ?>
		<<?= $heading; ?> class="post-entry__title"><?php the_title(); ?></<?= $heading; ?>>
	<?php endif; ?>

	<div class="post-entry__content"><?php the_content(); ?></div>
</article>