<?php
/**
 * Block: {{TITLE}}
 */

$data = get_query_var('block_data');

if ( ! $data ){
	return;
}

$heading = $data['heading'] ?? '';
$content = $data['content'] ?? '';
$block   = $data['block'] ?? array();

if ( $heading === '' && $content === '' ){
	return;
}
?>

<section
	id="<?= esc_attr(cs__get_block_id($block)); ?>"
	class="<?= esc_attr(cs__get_block_classes($block, 'block-{{SLUG}}')); ?>"
>
	<div class="block-{{SLUG}}__container container">
		<?php if ( $heading !== '' ): ?>
			<h2 class="block-{{SLUG}}__heading"><?= esc_html($heading); ?></h2>
		<?php endif; ?>

		<?php if ( $content !== '' ): ?>
			<div class="block-{{SLUG}}__content"><?= wp_kses_post($content); ?></div>
		<?php endif; ?>
	</div>
</section>