<?php
/**
 * Block: Call to Action
 */

$data = get_query_var('block_data');

if ( ! $data ) return;

$eyebrow    = $data['eyebrow'];
$heading    = $data['heading'];
$subheading = $data['subheading'];
$content    = $data['content'];
$buttons    = $data['buttons'];
$block      = $data['block'];

$has_button = false;
foreach ( (array) $buttons as $row ){
	$url   = $row['link']['url'];
	$title = $row['link']['title'];

	if ( $url && $title ){
		$has_button = true;
		break;
	}
} ?>


<?php if ( $heading !== '' || $eyebrow !== '' || $subheading !== '' || $content !== '' || $has_button ): ?>
	<section
		id="<?= esc_attr(cs__get_block_id($block)); ?>"
		class="block-cta <?= cs__get_block_classes($block, $data, []); ?>"
		<?= cs__get_block_styles($block); ?>
	>
		<div class="block-cta__container">
			<?php if ( $eyebrow !== '' ){ ?>
				<p class="block-cta__eyebrow h6"><?= esc_html($eyebrow); ?></p>
			<?php } ?>

			<?php if ( $heading !== '' ){ ?>
				<h2 class="block-cta__heading"><?= esc_html($heading); ?></h2>
			<?php } ?>

			<?php if ( $subheading !== '' ){ ?>
				<h3 class="block-cta__subheading"><?= esc_html($subheading); ?></h3>
			<?php } ?>

			<?php if ( $content !== '' ){ ?>
				<div class="block-cta__content"><?= wp_kses_post($content); ?></div>
			<?php } ?>

			<?php cs__render_link_group($buttons, 'block-cta__button-wrapper'); ?>
		</div>
	</section>
<?php endif; ?>