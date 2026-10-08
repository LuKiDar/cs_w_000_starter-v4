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

$has_button = false;
foreach ( (array) $buttons as $row ){
	$url   = $row['link']['url'] ?? '';
	$title = $row['link']['title'] ?? '';
	if ( $url !== '' && $title !== '' ){
		$has_button = true;
		break;
	}
} ?>


<?php if ( $heading !== '' || $eyebrow !== '' || $subheading !== '' || $content !== '' || $has_button ): ?>
	<section
		id="<?= esc_attr(cs__get_block_id($block)); ?>"
		class="block-{{SLUG}} <?= cs__get_block_classes($block, $data, []); ?>"
		<?= cs__get_block_styles($block); ?>
	>
		<div class="block-{{SLUG}}__container container">
			<?php if ( $eyebrow !== '' ){ ?>
				<p class="block-{{SLUG}}__eyebrow"><?= esc_html($eyebrow); ?></p>
			<?php } ?>

			<?php if ( $heading !== '' ){ ?>
				<h2 class="block-{{SLUG}}__heading"><?= esc_html($heading); ?></h2>
			<?php } ?>

			<?php if ( $subheading !== '' ){ ?>
				<p class="block-{{SLUG}}__subheading"><?= esc_html($subheading); ?></p>
			<?php } ?>

			<?php if ( $content !== '' ){ ?>
				<div class="block-{{SLUG}}__content"><?= wp_kses_post($content); ?></div>
			<?php } ?>

			<?php cs__render_link_group($buttons, 'block-{{SLUG}}__button-wrapper'); ?>
		</div>
	</section>
<?php endif; ?>