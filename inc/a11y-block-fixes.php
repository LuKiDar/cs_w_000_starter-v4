<?php
/**
 * Accessibility: block render fixes (semantic only).
 *
 * Goal: keep styling/layout unchanged while avoiding Lighthouse heading-order flags
 * caused by H6 "heading" blocks used as eyebrow text.
 */

add_filter( 'render_block', function ( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || $block['blockName'] !== 'core/heading' ) {
		return $block_content;
	}

	// Only rewrite actual <h6 class="wp-block-heading ...">...</h6> to <p ...>...</p>.
	if ( ! is_string( $block_content ) || stripos( $block_content, '<h6' ) === false ) {
		return $block_content;
	}

	$block_content = preg_replace(
		'~<h6([^>]*\bclass=("|\')[^"\']*\bwp-block-heading\b[^"\']*\2[^>]*)>(.*?)</h6>~is',
		'<p$1>$3</p>',
		$block_content
	);

	return $block_content;
}, 10, 2 );