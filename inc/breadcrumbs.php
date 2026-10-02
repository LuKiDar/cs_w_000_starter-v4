<?php
/**
 * Breadcrumbs
 *
 * Ported from v3. Every value this file prints is escaped at the echo site:
 *
 * - Values rendered as text are wrapped in esc_html() -- titles, the search
 *   query, tag and category names, post-type labels, the author's display name,
 *   and the fixed get_the_time() parts.
 * - URLs are wrapped in esc_url() where the anchor is built -- get_year_link(),
 *   get_month_link(), get_permalink() and get_post_type_archive_link().
 * - Markup this file assembles -- $homeItem, $delimiter, $beforeCurrent,
 *   $afterCurrent, $breadcrumbs[$i], $cats and get_category_parents() output --
 *   is passed through wp_kses_post() at the echo site, so the tags survive while
 *   anything unexpected is stripped. Wrapping those in esc_html() instead would
 *   print the tags as text.
 *
 * get_the_time() values come from a timestamp and a fixed format string, never
 * from user text, but they are escaped anyway so the EscapeOutput sniff stays
 * clean and the rule is "escape every output", not "escape the ones we think are
 * risky".
 */

function cs__the_breadcrumbs( $modifier='' ){
	$showOnHome = 0; // 1 - show breadcrumbs on the homepage, 0 - don't show
	$delimiter = '<i class="breadcrumbs__delimiter">&raquo;</i>'; // delimiter between crumbs
	$home = 'Home'; // text for the 'Home' link
	$showCurrent = 1; // 1 - show current post/page title in breadcrumbs, 0 - don't show
	$beforeCurrent = '<span class="breadcrumbs__current">'; // tag before the current crumb
	$afterCurrent = '</span>'; // tag after the current crumb

	global $post;
	$homeLink = get_bloginfo('url');

	$homeItem = '<a href="'. esc_url( $homeLink ) .'">'. esc_html( $home ) .'</a> ';

	echo '<nav class="breadcrumbs'. esc_attr( $modifier ) .'">';

		if ( is_front_page() ){
			if ( $showOnHome==1 ){
				echo wp_kses_post( $homeItem );
			}

		} else if ( is_home() ){
			echo wp_kses_post( $homeItem ) . wp_kses_post( $delimiter );
			echo wp_kses_post( $beforeCurrent ) . esc_html( get_the_title(get_option('page_for_posts')) ) . wp_kses_post( $afterCurrent );

		} else {
			echo wp_kses_post( $homeItem ) . wp_kses_post( $delimiter );

			if ( is_category() ){
				$thisCat = get_category(get_query_var('cat'), false);
				if ( $thisCat->parent!=0 ){
					echo wp_kses_post( get_category_parents($thisCat->parent, true, $delimiter) );
				}
				echo wp_kses_post( $beforeCurrent ) .single_cat_title('', false). wp_kses_post( $afterCurrent );

			} elseif ( is_search() ){
				echo wp_kses_post( $beforeCurrent ) .'Search results for: '. esc_html( get_search_query() ) . wp_kses_post( $afterCurrent );

			} elseif ( is_day() ){
				echo '<a href="'. esc_url( get_year_link(get_the_time('Y')) ) .'">'. esc_html( get_the_time('Y') ) .'</a>'. wp_kses_post( $delimiter );
				echo '<a href="'. esc_url( get_month_link(get_the_time('Y'), get_the_time('m')) ) .'">'. esc_html( get_the_time('F') ) .'</a>'. wp_kses_post( $delimiter );
				echo wp_kses_post( $beforeCurrent ) .esc_html( get_the_time('d') ). wp_kses_post( $afterCurrent );

			} elseif ( is_month() ){
				echo '<a href="'. esc_url( get_year_link(get_the_time('Y')) ) .'">'. esc_html( get_the_time('Y') ) .'</a>'. wp_kses_post( $delimiter );
				echo wp_kses_post( $beforeCurrent ) .esc_html( get_the_time('F') ). wp_kses_post( $afterCurrent );

			} elseif ( is_year() ){
				echo wp_kses_post( $beforeCurrent ) .esc_html( get_the_time('Y') ). wp_kses_post( $afterCurrent );

			} elseif ( is_single() && !is_attachment() ){
				if ( get_post_type()!='post' ){
					$post_type = get_post_type();
					$post_type_obj = get_post_type_object($post_type);
					echo '<a href="'. esc_url( get_post_type_archive_link($post_type) ) .'">'. esc_html( $post_type_obj->labels->singular_name ) .'</a>';

					if ( $post->post_parent ){
						$parent_id = $post->post_parent;
						$breadcrumbs = array();
						while ( $parent_id ){
							$page = get_page($parent_id);
							$breadcrumbs[] = '<a href="'. esc_url( get_permalink($page->ID) ) .'">'. esc_html( get_the_title($page->ID) ) .'</a>';
							$parent_id = $page->post_parent;
						}
						$breadcrumbs = array_reverse($breadcrumbs);
						echo wp_kses_post( $delimiter );
						for ( $i=0; $i<count($breadcrumbs); $i++ ){
							echo wp_kses_post( $breadcrumbs[$i] );
							if ( $i!=count($breadcrumbs)-1 ){
								echo wp_kses_post( $delimiter );
							}
						}
					}

					if ( $showCurrent==1 ){
						echo wp_kses_post( $delimiter ). wp_kses_post( $beforeCurrent ) .esc_html( get_the_title() ). wp_kses_post( $afterCurrent );
					}
				} else {
					$cat = get_the_category();
					$cat = $cat[0];
					$cats = get_category_parents($cat, true, $delimiter);
					if ( $showCurrent==0 ){
						$cats = preg_replace("#^(.+)$delimiter$#", "$1", $cats);
					}
					echo wp_kses_post( $cats );
					if ( $showCurrent==1 ){
						echo wp_kses_post( $beforeCurrent ) .esc_html( get_the_title() ). wp_kses_post( $afterCurrent );
					}
				}

			} elseif ( !is_single() && !is_page() && get_post_type()!='post' && !is_404() ){
				$post_type = get_post_type_object(get_post_type());
				echo wp_kses_post( $beforeCurrent ) .esc_html( $post_type->labels->singular_name ). wp_kses_post( $afterCurrent );

			} elseif ( is_attachment() ){
				$parent = get_post($post->post_parent);
				$cat = get_the_category($parent->ID);
				$cat = $cat[0];
				echo wp_kses_post( get_category_parents($cat, true, $delimiter) );
				echo '<a href="' .esc_url( get_permalink($parent) ). '">' .esc_html( $parent->post_title ). '</a>';
				if ( $showCurrent==1 ){
					echo wp_kses_post( $delimiter ). wp_kses_post( $beforeCurrent ) .esc_html( get_the_title() ). wp_kses_post( $afterCurrent );
				}

			} elseif ( is_page() && !$post->post_parent ){
				if ( $showCurrent==1 ){
					echo wp_kses_post( $beforeCurrent ) .esc_html( get_the_title() ). wp_kses_post( $afterCurrent );
				}

			} elseif ( is_page() && $post->post_parent ){
				$parent_id = $post->post_parent;
				$breadcrumbs = array();
				while ( $parent_id ){
					$page = get_page($parent_id);
					$breadcrumbs[] = '<a href="'. esc_url( get_permalink($page->ID) ) .'">'. esc_html( get_the_title($page->ID) ) .'</a>';
					$parent_id = $page->post_parent;
				}
				$breadcrumbs = array_reverse($breadcrumbs);
				for ( $i=0; $i<count($breadcrumbs); $i++ ){
					echo wp_kses_post( $breadcrumbs[$i] );
					if ( $i!=count($breadcrumbs)-1 ){
						echo wp_kses_post( $delimiter );
					}
				}
				if ( $showCurrent==1 ){
					echo wp_kses_post( $delimiter ). wp_kses_post( $beforeCurrent ) .esc_html( get_the_title() ). wp_kses_post( $afterCurrent );
				}

			} elseif ( is_tag() ){
				echo wp_kses_post( $beforeCurrent ) .'Posts tagged "'. esc_html( single_tag_title('', false) ) .'"'. wp_kses_post( $afterCurrent );

			} elseif ( is_author() ){
				global $author;
				$userdata = get_userdata($author);
				echo wp_kses_post( $beforeCurrent ) .'Articles posted by '. esc_html( $userdata->display_name ). wp_kses_post( $afterCurrent );

			} elseif ( is_404() ){
				echo wp_kses_post( $beforeCurrent ) .'Error 404'. wp_kses_post( $afterCurrent );

			}

			if ( get_query_var('paged') ){
				echo '<span class="breadcrumbs__paged">';
					if ( is_category() || is_day() || is_month() || is_year() || is_search() || is_tag() || is_author() ){
						echo ' (';
					}
					echo esc_html__('Page') .' '. esc_html( get_query_var('paged') );
					if ( is_category() || is_day() || is_month() || is_year() || is_search() || is_tag() || is_author() ){
						echo ')';
					}
				echo '</span>';
			}
		}

	echo '</nav>';
}