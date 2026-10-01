<?php
/**
 * Helper functions
 */

/* --- Get template page ID --- */
function cs__get_template_page_ID( $template, $index=0 ){
	$pages = get_posts(array(
		'post_type' =>'page',
		'meta_key'  =>'_wp_page_template',
		'meta_value'=> 'templates/'. $template .'.php',
		'orderby' => 'ID',
		'order' => 'ASC'
	));

	return $pages[$index]->ID;
}


/* --- Parse content in search of a block --- */
function cs__has_block( $post_content, $block_name='' ){
	$blocks = parse_blocks($post_content);

	foreach ( $blocks as $block ){
		if ( $block['blockName']===$block_name ){
			return true;
		}

		if ( !empty($block['innerBlocks']) ){
			foreach ( $block['innerBlocks'] as $innerBlock ){
				if ( $innerBlock['blockName']===$block_name ){
					return true;
				}
			}
		}
	}

	return false;
}


/* --- Generate URL handle from text line --- */
function cs__generate_url_handle( $text ){
	$handle = strtolower($text);
	$handle = preg_replace('/[^\w\s]/u', '', $handle);
	$handle = preg_replace('/\s+/', '-', $handle);
	$handle = trim($handle, '-');

	return $handle;
}


/* --- Block wrapper id: anchor if set, otherwise the unique block id --- */
function cs__get_block_id( $block ){
	return ! empty($block['anchor']) ? $block['anchor'] : $block['id'];
}


/* --- Block wrapper classes --- */
function cs__get_block_classes( $block, $base = '' ){
	$classes = array();

	if ( $base !== '' ){
		$classes[] = $base;
	}
	if ( ! empty($block['className']) ){
		$classes[] = $block['className'];
	}
	if ( ! empty($block['align']) ){
		$classes[] = 'align'. $block['align'];
	}
	// ACF hands the render callback `alignText` (the key block.json declares and
	// the editor writes) and mirrors it to `align_text` via
	// acf_add_back_compat_attributes(). It never provides `textAlign`, so reading
	// that key emitted nothing and the alignment styles were unreachable.
	$text_align = $block['alignText'] ?? $block['align_text'] ?? '';
	if ( ! empty($text_align) ){
		$classes[] = 'has-text-align-'. $text_align;
	}
	// Same story for the background: style.scss styles `.has-background`, but the
	// class is only emitted by core, which does not run for an ACF block here.
	if ( ! empty($block['style']['color']['background']) ){
		$classes[] = 'has-background';
	}

	return implode(' ', array_unique($classes));
}


/* --- Read a block field, surviving a deactivated ACF Pro --- */
function cs__get_block_field( $name ){
	return function_exists('get_field') ? get_field($name) : null;
}


/* --- Render a repeater of buttons/links --- */
function cs__render_link_group( $links, $modifier = '' ){
	if ( empty($links) || ! is_array($links) ){
		return;
	}

	$classes = 'block-links';
	if ( $modifier !== '' ){
		$classes .= ' '. $modifier;
	}
	?>
	<div class="<?= esc_attr($classes); ?>">
		<?php foreach ( $links as $link ): ?>
			<?php
			$url    = $link['link']['url'] ?? '';
			$title  = $link['link']['title'] ?? '';
			$target = $link['link']['target'] ?? '';
			$type   = $link['link_type'] ?? 'button';

			if ( $url === '' || $title === '' ){
				continue;
			}

			$link_classes = array('block-links__item');
			if ( $type === 'button' ){
				$link_classes[] = 'button';
			} elseif ( $type === 'button-outlined' ){
				$link_classes[] = 'button';
				$link_classes[] = 'is-outlined';
			} else {
				$link_classes[] = 'link-arrow';
			}
			?>
			<a
				class="<?= esc_attr(implode(' ', $link_classes)); ?>"
				href="<?= esc_url($url); ?>"<?= $target ? ' target="'. esc_attr($target) .'" rel="noopener noreferrer"' : ''; ?>><?= esc_html($title); ?></a>
		<?php endforeach; ?>
	</div>
	<?php
}