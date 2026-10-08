<?php
/**
 * Block: {{TITLE}}
 */

$data = get_query_var('block_data');

if ( ! $data ) return;

$eyebrow    = $data['eyebrow'] ?? '';
$heading    = $data['heading'] ?? '';
$subheading = $data['subheading'] ?? '';
$content    = $data['content'] ?? '';
$buttons    = $data['buttons'] ?? array();
$block      = $data['block'] ?? array();

// Count only the buttons that will actually render. cs__render_link_group()
// skips a row whose link was left blank, so a non-empty $buttons array is not
// the same as a block with a button -- and the wrapper would still be emitted,
// with the block's own padding, as an empty band on the page.
$has_button = false;
foreach ( (array) $buttons as $row ){
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
	class="<?= esc_attr(cs__get_block_classes($block, 'block-{{SLUG}}')); ?>"
>
	<div class="block-{{SLUG}}__container container">
		<?php if ( $eyebrow !== '' ): ?>
			<p class="block-{{SLUG}}__eyebrow"><?= esc_html($eyebrow); ?></p>
		<?php endif; ?>

		<?php if ( $heading !== '' ): ?>
			<h2 class="block-{{SLUG}}__heading"><?= esc_html($heading); ?></h2>
		<?php endif; ?>

		<?php if ( $subheading !== '' ): ?>
			<p class="block-{{SLUG}}__subheading"><?= esc_html($subheading); ?></p>
		<?php endif; ?>

		<?php if ( $content !== '' ): ?>
			<div class="block-{{SLUG}}__content"><?= wp_kses_post($content); ?></div>
		<?php endif; ?>

		<?php cs__render_link_group($buttons, 'block-{{SLUG}}__button-wrapper'); ?>
	</div>
</section>
