<?php
/**
 * Card: Post
 *
 * @param array $args { post_id: int, modifier: string }
 */

$post_id  = $args['post_id'] ?? get_the_ID();
$modifier = $args['modifier'] ?? '';

$classes = 'card-post';
if ( $modifier !== '' ){
	$classes .= ' '. $modifier;
}
?>

<article class="<?= esc_attr($classes); ?>">
	<?php if ( has_post_thumbnail($post_id) ): ?>
		<a class="card-post__media" href="<?= esc_url(get_permalink($post_id)); ?>" tabindex="-1" aria-hidden="true">
			<?= get_the_post_thumbnail($post_id, 'medium_large', array('class' => 'card-post__image')); ?>
		</a>
	<?php endif; ?>

	<div class="card-post__body">
		<h2 class="card-post__title">
			<a class="card-post__link" href="<?= esc_url(get_permalink($post_id)); ?>"><?= esc_html(get_the_title($post_id)); ?></a>
		</h2>

		<div class="card-post__excerpt"><?= wp_kses_post(get_the_excerpt($post_id)); ?></div>

		<a class="card-post__more link-arrow" href="<?= esc_url(get_permalink($post_id)); ?>">
			<?= esc_html__('Read more', CSWP); ?>
			<span class="screen-reader-text"><?= esc_html(get_the_title($post_id)); ?></span>
		</a>
	</div>
</article>