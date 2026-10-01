<?php
/**
 * Block: Call to Action
 */

$data = get_query_var('block_data');

if ( ! $data ){
	return;
}

$eyebrow    = $data['eyebrow'] ?? '';
$heading    = $data['heading'] ?? '';
$subheading = $data['subheading'] ?? '';
$content    = $data['content'] ?? '';
$buttons    = $data['buttons'] ?? array();
$block      = $data['block'] ?? array();

// Count only the buttons that will actually render. A repeater row whose link was
// left blank is skipped by cs__render_link_group(), so a non-empty $buttons array is
// not the same as a block with a button -- and the wrapper would still be emitted,
// with the block's own padding, as an empty band on the page.
$has_button = false;
foreach ( (array) $buttons as $row ){
	// Mirror cs__render_link_group()'s own row test EXACTLY. It skips a row when url or
	// title is '' after a `?? ''` default, so `! empty()` here would disagree with it on
	// "0", 0 and false -- and a button whose visible label is literally "0" would be
	// dropped while the renderer would happily emit it.
	$url   = $row['link']['url'] ?? '';
	$title = $row['link']['title'] ?? '';
	if ( $url !== '' && $title !== '' ){
		$has_button = true;
		break;
	}
}

if ( $heading === '' && $eyebrow === '' && $subheading === '' && $content === '' && ! $has_button ){
	return;
}
?>

<section
	id="<?= esc_attr(cs__get_block_id($block)); ?>"
	class="<?= esc_attr(cs__get_block_classes($block, 'block-cta')); ?>"
	<?= cs__get_block_styles($block); ?>
>
	<div class="block-cta__container container">
		<?php if ( $eyebrow !== '' ): ?>
			<p class="block-cta__eyebrow"><?= esc_html($eyebrow); ?></p>
		<?php endif; ?>

		<?php if ( $heading !== '' ): ?>
			<h2 class="block-cta__heading"><?= esc_html($heading); ?></h2>
		<?php endif; ?>

		<?php if ( $subheading !== '' ): ?>
			<p class="block-cta__subheading"><?= esc_html($subheading); ?></p>
		<?php endif; ?>

		<?php if ( $content !== '' ): ?>
			<div class="block-cta__content"><?= wp_kses_post($content); ?></div>
		<?php endif; ?>

		<?php cs__render_link_group($buttons, 'block-cta__button-wrapper'); ?>
	</div>
</section>