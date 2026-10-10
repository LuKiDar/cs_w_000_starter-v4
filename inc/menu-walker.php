<?php
/**
 * Menu Walker
 *
 * Always-on: header.php wires cs__primary_menu_walker into both of its
 * wp_nav_menu calls, so the class must load with the theme. A class that is used
 * by the theme's own template and left behind a commented include is what took
 * v3 down.
 */

/*--- Primary Menu Walker ---*/
class cs__primary_menu_walker extends Walker_Nav_Menu {
	private $curItem;

	// Add classes to ul sub-menus
	function start_lvl( &$output, $depth = 0, $args = array() ){
		// Depth dependent classes
		$indent         = ( $depth > 0 ? str_repeat( "\t", $depth ) : '' ); // Code indent
		$display_depth  = ( $depth + 1 ); // Because it counts the first submenu as 0
		$classes        = array(
			'sub-menu',
			( $display_depth % 2 ? 'menu-odd' : 'menu-even' ),
			( $display_depth >= 2 ? 'sub-sub-menu' : '' ),
			'menu-depth-' . $display_depth,
		);
		$class_names    = implode( ' ', $classes );

		// Build html
		$output .= "\n" . $indent . '<ul class="' . $class_names . '">' . "\n";
	}

	// Add main/sub classes to li's and links
	function start_el( &$output, $item, $depth = 0, $args = array(), $current_object_id = 0 ){
		global $wp_query;
		$this->curItem = $item;

		$indent = ( $depth > 0 ? str_repeat( "\t", $depth ) : '' ); // Code indent

		// Depth dependent classes
		$depth_classes = array(
			( $depth==0 ? 'main-menu-item' : 'sub-menu-item' ),
			( $depth>=2 ? 'sub-sub-menu-item' : '' ),
			( $depth%2 ? 'menu-item-odd' : 'menu-item-even' ),
			'menu-item-depth-' . $depth,
		);
		$depth_class_names = esc_attr( implode( ' ', $depth_classes ) );

		// Passed classes
		$classes     = empty( $item->classes ) ? array() : (array) $item->classes;
		$class_names = esc_attr( implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) ) );

		// Build html
		$output .= $indent . '<li id="menu-item-' . $item->ID . '" class="' . $class_names . ' ' . $depth_class_names . '" data-content="' . esc_attr( $item->title ) . '">';

		// Link attributes
		$attributes  = !empty( $item->attr_title ) ? ' title="' . esc_attr( $item->attr_title ) . '"' : '';
		$attributes .= !empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '"' : '';
		$attributes .= !empty( $item->xfn ) ? ' rel="' . esc_attr( $item->xfn ) . '"' : '';
		$attributes .= !empty( $item->url ) ? ' href="' . esc_attr( $item->url ) . '"' : '';
		$attributes .= ' class="menu-link ' . ( $depth > 0 ? 'sub-menu-link' : 'main-menu-link' ) . '"';

		$item_title = apply_filters( 'the_title', $item->title, $item->ID );
		$has_children = in_array( 'menu-item-has-children', $classes );

		if ( $has_children ){
			$item_output = sprintf(
				'%1$s<div class="menu-item-trigger"><a%2$s>%3$s%4$s%5$s</a><span class="caret"></span></div>%6$s',
				$args->before,
				$attributes,
				$args->link_before,
				$item_title,
				$args->link_after,
				$args->after
			);
		} else {
			$item_output = sprintf(
				'%1$s<a%2$s>%3$s%4$s%5$s</a>%6$s',
				$args->before,
				$attributes,
				$args->link_before,
				$item_title,
				$args->link_after,
				$args->after
			);
		}

		// Build html
		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}
}


/*--- Footer Menu Walker ---*/
class cs__footer_menu_walker extends Walker_Nav_Menu {
	// Sub-menus are not rendered in the footer: the footer menu is a flat list by
	// design, and a nested list would not match its styles. Returning here instead
	// of emitting markup keeps the same output whether the menu is set to depth 1
	// (as footer.php uses) or a deeper value set by mistake.
	function start_lvl( &$output, $depth = 0, $args = array() ){
		return;
	}

	function start_el( &$output, $item, $depth = 0, $args = array(), $current_object_id = 0 ){
		$indent = ( $depth > 0 ? str_repeat( "\t", $depth ) : '' );

		$classes     = empty( $item->classes ) ? array() : (array) $item->classes;
		$class_names = esc_attr( implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) ) );

		$attributes  = !empty( $item->attr_title ) ? ' title="' . esc_attr( $item->attr_title ) . '"' : '';
		$attributes .= !empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '"' : '';
		$attributes .= !empty( $item->xfn ) ? ' rel="' . esc_attr( $item->xfn ) . '"' : '';
		$attributes .= !empty( $item->url ) ? ' href="' . esc_attr( $item->url ) . '"' : '';
		$attributes .= ' class="menu-link main-menu-link"';

		$item_title = apply_filters( 'the_title', $item->title, $item->ID );

		$item_output = sprintf(
			'%1$s<a%2$s>%3$s%4$s%5$s</a>%6$s',
			$args->before,
			$attributes,
			$args->link_before,
			$item_title,
			$args->link_after,
			$args->after
		);

		$output .= $indent . '<li id="menu-item-' . $item->ID . '" class="' . $class_names . '">';
		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}
}