<?php
/**
 * Content: Post card
 *
 * @param array $args { post_id: int, modifier: string }
 */

$post_id    = (int) ($args['post_id'] ?? get_the_ID());
$date       = get_the_date('', $post_id);
$author     = get_the_author_meta('display_name', (int) get_post_field('post_author', $post_id));
$categories = get_the_category($post_id);

$modifier   = $args['modifier'] ?? ''; ?>

<article id="post-<?= esc_attr($post_id); ?>"
	       <?php post_class(trim('post-card '. $modifier), $post_id); ?>
	>
	<?php if ( has_post_thumbnail($post_id) ){ ?>
		<figure class="post-card__media" role="none">
			<?= get_the_post_thumbnail($post_id, 'medium_large', array('class' => 'post-card__image')); ?>

			<a class="post-card__media-link" href="<?= esc_url(get_permalink($post_id)); ?>" tabindex="-1" aria-label="<?= esc_attr(sprintf(
				/* translators: %s: post title */
				__('Read more: %s', CSWP),
				get_the_title($post_id)
			)); ?>"></a>
		</figure>
	<?php } ?>

	<div class="post-card__body">

		<?php if ( $categories ){ ?>
			<ul class="post-card__tags">
				<?php foreach ( $categories as $category ){ ?>
					<li>
						<a class="builtin" href="<?= esc_url(get_category_link($category)); ?>"><?= esc_html($category->name); ?></a>
					</li>
				<?php } ?>
			</ul>
		<?php } ?>

		<h4 class="post-card__title">
			<a class="post-card__link builtin" href="<?= esc_url(get_permalink($post_id)); ?>"><?= esc_html(get_the_title($post_id)); ?></a>
		</h4>

		<div class="post-card__excerpt"><?= wp_kses_post(get_the_excerpt($post_id)); ?></div>

		<div class="post-card__navigation">
			<a class="post-card__more link-arrow builtin" href="<?= esc_url(get_permalink($post_id)); ?>"><?= esc_html__('Read more', CSWP); ?></a>
		</div>

		<?php if ( $date || $author ){ ?>
			<ul class="post-card__meta">
				<?php if ( $date ){ ?>
					<li>
						<time datetime="<?= esc_attr(get_the_date('c', $post_id)); ?>"><?= esc_html($date); ?></time>
					</li>
				<?php } ?>

				<?php if ( $author ){ ?>
					<li>
						<?php printf(
							/* translators: %s: author name */
							esc_html__('by %s', CSWP),
							esc_html($author)
						); ?>
					</li>
				<?php } ?>
			</ul>
		<?php } ?>
	</div>
</article>