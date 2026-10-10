<?php
/**
 * Class to handle Gutenberg block styles and attributes.
 *
 * This class processes standard Gutenberg block attributes (spacing, typography, color, etc.)
 * and converts them into inline CSS styles. It handles variable presets and custom values.
 *
 * Usage:
 * $styles = CS_Block_Styles::get_styles( $block );
 * <div <?= $styles; ?>>...</div>
 */

class CS_Block_Styles {

    /**
     * Get all block styles as an inline style attribute.
     *
     * @param array $block The block array from ACF or Gutenberg.
     * @return string The style attribute string (e.g., 'style="margin-top: 20px; ..."').
     */
    public static function get_styles( $block ){
        $styles = [];

        // 1. Process 'style' array (standard Gutenberg styles)
        if ( !empty( $block['style'] ) ){
            $styles = array_merge( $styles, self::process_spacing( $block['style'] ) );
            $styles = array_merge( $styles, self::process_typography( $block['style'] ) );
            $styles = array_merge( $styles, self::process_color( $block['style'] ) );
            $styles = array_merge( $styles, self::process_dimensions( $block['style'] ) );
            $styles = array_merge( $styles, self::process_border( $block['style'] ) );
        }

        // 2. Process top-level attributes or other locations if needed
        // Some attributes like text align might be in $block['attrs'] or $block['style']['typography']['textAlign']
        // Check for text alignment in style first, then attributes
        if ( isset( $block['style']['typography']['textAlign'] ) ){
             $styles[] = "text-align: " . $block['style']['typography']['textAlign'];
        } elseif ( !empty( $block['alignText'] ) ){ // canonical key, declared in block.json
             $styles[] = "text-align: " . $block['alignText'];
        } elseif ( !empty( $block['align_text'] ) ){ // ACF back-compat mirror
             $styles[] = "text-align: " . $block['align_text'];
        }

        // Filter empty values
        $styles = array_filter( $styles );

        if ( empty( $styles ) ){
            return '';
        }

        return 'style="' . esc_attr( implode( '; ', $styles ) ) . ';"';
    }

    /**
     * Convert Gutenberg preset values to CSS variables.
     * Example: var:preset|color|vivid-green-cyan -> var(--wp--preset--color--vivid-green-cyan)
     *
     * @param string $value The value to parse.
     * @return string The converted CSS value.
     */
    public static function parse_value( $value ){
        if ( !is_string($value) ){
            return $value;
        }

        if ( strpos($value, 'var:preset|')===0 ){
            $parts = explode( '|', str_replace( 'var:preset|', '', $value ) );
            // $parts[0] is category (e.g., 'color', 'spacing', 'font-size')
            // $parts[1] is slug (e.g., 'primary', '30', 'large')
            if ( count( $parts ) >= 2 ){
                // WordPress presets follow the pattern --wp--preset--{category}--{slug}
                // Note: camelCase categories might need conversion, but usually they are hyphenated in presets
                return "var(--wp--preset--{$parts[0]}--{$parts[1]})";
            }
        }

        return $value;
    }

    /**
     * Process Spacing (Margin, Padding).
     */
    private static function process_spacing( $style ){
        $css = [];
        if ( empty( $style['spacing'] ) ){
            return $css;
        }
        
        $spacing = $style['spacing'];

        // Helper for mapping sides
        $sides = [ 'top', 'right', 'bottom', 'left' ];

        // Margin
        if ( isset( $spacing['margin'] ) ){
            if ( is_array( $spacing['margin'] ) ){
                foreach ( $spacing['margin'] as $side => $value ){
                    if ( in_array( $side, $sides ) ){
                        $css[] = "margin-{$side}: " . self::parse_value( $value );
                    }
                }
            } else {
                 $css[] = "margin: " . self::parse_value( $spacing['margin'] );
            }
        }

        // Padding
        if ( isset( $spacing['padding'] ) ){
             if ( is_array( $spacing['padding'] ) ){
                foreach ( $spacing['padding'] as $side => $value ){
                    if ( in_array( $side, $sides ) ){
                        $css[] = "padding-{$side}: " . self::parse_value( $value );
                    }
                }
             } else {
                 $css[] = "padding: " . self::parse_value( $spacing['padding'] );
             }
        }
        
        // Block Gap (often handled by flow layout, but sometimes useful)
        if ( isset( $spacing['blockGap'] ) ){
             $css[] = "gap: " . self::parse_value( $spacing['blockGap'] );
        }

        return $css;
    }

    /**
     * Process Typography.
     */
    private static function process_typography( $style ){
        $css = [];
        if ( empty( $style['typography'] ) ){
            return $css;
        }
        
        $typo = $style['typography'];

        $map = [
            'fontSize'       => 'font-size',
            'fontFamily'     => 'font-family',
            'fontStyle'      => 'font-style',
            'fontWeight'     => 'font-weight',
            'lineHeight'     => 'line-height',
            'letterSpacing'  => 'letter-spacing',
            'textDecoration' => 'text-decoration',
            'textTransform'  => 'text-transform',
        ];

        foreach ( $map as $key => $prop ){
            if ( isset( $typo[ $key ] ) ){
                $css[] = "{$prop}: " . self::parse_value( $typo[ $key ] );
            }
        }

        return $css;
    }

    /**
     * Process Color.
     */
    private static function process_color( $style ){
        $css = [];
        if ( empty( $style['color'] ) ){
            return $css;
        }
        
        $color = $style['color'];

        if ( isset( $color['text'] ) ){
            $css[] = "color: " . self::parse_value( $color['text'] );
        }

        if ( isset( $color['background'] ) ){
            $css[] = "background-color: " . self::parse_value( $color['background'] );
        }

        if ( isset( $color['gradient'] ) ){
            $css[] = "background: " . self::parse_value( $color['gradient'] );
        }

        return $css;
    }

    /**
     * Process Dimensions (min-height, etc.)
     */
    private static function process_dimensions( $style ){
        $css = [];
        if ( isset( $style['dimensions']['minHeight'] ) ){
            $css[] = "min-height: " . self::parse_value( $style['dimensions']['minHeight'] );
        }
        return $css;
    }

    /**
     * Process Borders.
     */
    private static function process_border( $style ){
        $css = [];
        if ( empty( $style['border'] ) ){
            return $css;
        }

        $border = $style['border'];

        // Radius
        if ( isset( $border['radius'] ) ){
            // Radius can be string or array (topRight, etc - flattened usually in CSS var)
            // But Gutenberg often stores radius as a single value or object
            if ( is_array( $border['radius'] ) ){
                 // Handling specific corners if needed: topLeft, topRight, etc.
                 // This matches simple border-radius property syntax if mapped correctly.
                 // For simplicity, we might iterate if keys exist.
                 foreach(['topLeft', 'topRight', 'bottomRight', 'bottomLeft'] as $corner) {
                     if (isset($border['radius'][$corner])) {
                         $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $corner)); // camel to kebab
                         $css[] = "border-{$kebab}-radius: " . self::parse_value($border['radius'][$corner]);
                     }
                 }
            } else {
                $css[] = "border-radius: " . self::parse_value( $border['radius'] );
            }
        }

        // Color
        if ( isset( $border['color'] ) ){
            $css[] = "border-color: " . self::parse_value( $border['color'] );
        }

        // Style
        if ( isset( $border['style'] ) ){
            $css[] = "border-style: " . self::parse_value( $border['style'] );
        }

        // Width
        if ( isset( $border['width'] ) ){
            $css[] = "border-width: " . self::parse_value( $border['width'] );
        }
        
        // Full border object (top, right, bottom, left)
        // Gutenberg sometimes structures it as border.top.color, etc.
        foreach(['top', 'right', 'bottom', 'left'] as $side) {
            if (isset($border[$side])) {
                if (isset($border[$side]['color'])) $css[] = "border-{$side}-color: " . self::parse_value($border[$side]['color']);
                if (isset($border[$side]['width'])) $css[] = "border-{$side}-width: " . self::parse_value($border[$side]['width']);
                if (isset($border[$side]['style'])) $css[] = "border-{$side}-style: " . self::parse_value($border[$side]['style']);
            }
        }

        return $css;
    }
}

/**
 * Helper function for global access.
 */
function cs__get_block_styles( $block ){
    return CS_Block_Styles::get_styles( $block );
}