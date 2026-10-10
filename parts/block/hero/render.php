<?php
/**
 * Block: Hero
 */

$data = get_query_var('block_data');

if ( ! $data ){
	return;
}

$eyebrow       = $data['eyebrow'] ?? '';
$heading       = $data['heading'] ?? '';
$subheading    = $data['subheading'] ?? '';
$content       = $data['content'] ?? '';
$buttons       = $data['buttons'] ?? array();
$image         = $data['image'] ?? '';
$image_overlay = ! empty($data['image_overlay']);
$block         = $data['block'] ?? array();

// Resolve the image to an attachment ID. The field returns an array; a bare ID
// is tolerated too rather than fatal.
$image_id = 0;
if ( is_array($image) ){
	$image_id = (int) ($image['ID'] ?? 0);
} elseif ( is_numeric($image) ){
	$image_id = (int) $image;
}

// Count only the buttons that will actually render. cs__render_link_group()
// skips a row whose link was left blank, so a non-empty $buttons array is not
// the same as a block with a button -- the wrapper would still be emitted, with
// the block's own padding, as an empty band. Mirror the renderer's own row test
// EXACTLY (see parts/block/cta/render.php for why `! empty()` would disagree).
$has_button = false;
foreach ( (array) $buttons as $row ){
	$url   = $row['link']['url'] ?? '';
	$title = $row['link']['title'] ?? '';
	if ( $url !== '' && $title !== '' ){
		$has_button = true;
		break;
	}
}

$has_text = ( $eyebrow !== '' || $heading !== '' || $subheading !== '' || $content !== '' );

// A hero with neither text nor an image is an empty band: emit nothing at all.
if ( ! $has_text && ! $has_button && ! $image_id ){
	return;
}

$modifier = cs__get_block_classes($block, 'block-hero');
if ( $image_id ){
	$modifier .= ' has-background-image';
} ?>

<section
	id="<?= esc_attr(cs__get_block_id($block)); ?>"
	class="block-hero <?= cs__get_block_classes($block, $data, $modifier); ?>"
	<?= cs__get_block_styles($block); ?>
>
	<?php if ( $image_id ): ?>
		<figure class="block-hero__background" role="none">
			<?= wp_get_attachment_image($image_id, 'full', false, array('class' => 'block-hero__image', 'alt' => '')); ?>

			<?php if ( $image_overlay ): ?>
				<span class="block-hero__overlay" aria-hidden="true"></span>
			<?php endif; ?>
		</figure>
	<?php endif; ?>

	<div class="block-hero__container container">
		<div class="block-hero__inner">
			<?php if ( $eyebrow !== '' ): ?>
				<p class="block-hero__eyebrow"><?= esc_html($eyebrow); ?></p>
			<?php endif; ?>

			<?php if ( $heading !== '' ): ?>
				<h1 class="block-hero__heading"><?= esc_html($heading); ?></h1>
			<?php endif; ?>

			<?php if ( $subheading !== '' ): ?>
				<p class="block-hero__subheading"><?= esc_html($subheading); ?></p>
			<?php endif; ?>

			<?php if ( $content !== '' ): ?>
				<div class="block-hero__content"><?= wp_kses_post($content); ?></div>
			<?php endif; ?>

			<?php cs__render_link_group($buttons, 'block-hero__button-wrapper'); ?>
		</div>
	</div>
</section>