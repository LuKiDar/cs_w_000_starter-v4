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
		if ( cs__block_matches_name($block, $block_name) ){
			return true;
		}
	}

	return false;
}

/* --- Check block name including inner blocks --- */
function cs__block_matches_name( $block, $block_name ){
	if ( empty($block_name) ){
		return false;
	}

	if ( !empty($block['blockName']) && $block['blockName']===$block_name ){
		return true;
	}

	if ( !empty($block['innerBlocks']) ){
		foreach ( $block['innerBlocks'] as $inner_block ){
			if ( cs__block_matches_name($inner_block, $block_name) ){
				return true;
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
	return trim($handle, '-');
}


/* --- Get block ID --- */
function cs__get_block_id( $block ){
	return ! empty($block['anchor']) ? $block['anchor'] : $block['id'];
}

/* --- Determine if a color is dark --- */
function cs__is_dark_color( $color ){
	if ( !is_string($color) || $color==='' ){
		return false;
	}

	$color = trim($color);
	$r = $g = $b = null;

	if ( preg_match('/^#([a-f0-9]{3})$/i', $color, $matches) ){
		$hex = $matches[1];
		$r = hexdec(str_repeat($hex[0], 2));
		$g = hexdec(str_repeat($hex[1], 2));
		$b = hexdec(str_repeat($hex[2], 2));
	} elseif ( preg_match('/^#([a-f0-9]{6})$/i', $color, $matches) ){
		$hex = $matches[1];
		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));
	} elseif ( preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i', $color, $matches) ){
		$r = intval($matches[1]);
		$g = intval($matches[2]);
		$b = intval($matches[3]);
	}

	if ( $r===null || $g===null || $b===null ){
		return false;
	}

	$r = max(0, min(255, $r));
	$g = max(0, min(255, $g));
	$b = max(0, min(255, $b));

	$luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
	return $luminance < 0.5;
}

/* --- Determine if a background slug is dark --- */
function cs__is_dark_background_slug( $slug ){
	if ( !is_string($slug) || $slug==='' ){
		return false;
	}

	$dark_slugs = array(
		'primary',
		'black',
		'gray-900',
	);

	return in_array($slug, $dark_slugs, true);
}

/* --- Get block data value with fallback --- */
function cs__get_block_value( $data, $block, $key ){
	if ( !empty($data[$key]) ){
		return $data[$key];
	}

	if ( !empty($block[$key]) ){
		return $block[$key];
	}

	return '';
}

/* --- Get background color value from block styles --- */
function cs__get_block_background_value( $data, $block ){
	if ( !empty($data['style']['color']['background']) ){
		return $data['style']['color']['background'];
	}

	if ( !empty($block['style']['color']['background']) ){
		return $block['style']['color']['background'];
	}

	return '';
}

/* --- Check if background is dark --- */
function cs__has_dark_background( $background_color, $background_value ){
	if ( $background_color && cs__is_dark_background_slug($background_color) ){
		return true;
	}

	if ( $background_value && cs__is_dark_color($background_value) ){
		return true;
	}

	return false;
}

/* --- Build block class list --- */
function cs__get_block_classes( $block, $data=array(), $extra_classes=array() ){
	$classes = array();

	if ( !empty($block['className']) ){
		$classes[] = $block['className'];
	}

	if ( !empty($block['align']) ){
		$classes[] = 'align'. $block['align'];
	}

	$align_text = cs__get_block_value($data, $block, 'alignText');
	if ( $align_text ){
		$classes[] = 'has-text-align-'. $align_text;
	}

	$align_content = cs__get_block_value($data, $block, 'alignContent');
	if ( $align_content ){
		$classes[] = 'is-vertically-aligned-' . $align_content;
	}

	$background_color = cs__get_block_value($data, $block, 'backgroundColor');
	if ( $background_color ){
		$classes[] = 'has-'. $background_color .'-background-color';
		$classes[] = 'has-background';
	}

	$text_color = cs__get_block_value($data, $block, 'textColor');
	if ( $text_color ){
		$classes[] = 'has-'. $text_color .'-color';
		$classes[] = 'has-text-color';
	}

	$gradient = cs__get_block_value($data, $block, 'gradient');
	if ( $gradient ){
		$classes[] = 'has-'. $gradient .'-gradient-background';
		$classes[] = 'has-background';
		$classes[] = 'has-gradient';
	}

	$background_value = cs__get_block_background_value($data, $block);
	if ( cs__has_dark_background($background_color, $background_value) ){
		$classes[] = 'has-dark-background';
	}

	if ( is_string($extra_classes) && $extra_classes!=='' ){
		$extra_classes = preg_split('/\s+/', trim($extra_classes));
	}

	if ( is_array($extra_classes) && !empty($extra_classes) ){
		$classes = array_merge($classes, array_filter($extra_classes));
	}

	$classes = array_unique(array_filter(array_map('trim', $classes)));

	return implode(' ', $classes);
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