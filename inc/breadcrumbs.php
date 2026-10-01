<?php
/**
 * Breadcrumbs
 *
 * Ported from v3. Escapes are scoped, and the scoping has one rule:
 *
 * - Values this file renders **as text** are escaped -- titles, the search query,
 *   tag and category names, post-type labels, the author's display name, and the
 *   home link and $modifier.
 * - Markup this file **assembles** is not escaped at the echo site --
 *   get_category_parents() and single_cat_title() return HTML, and $breadcrumbs[$i]
 *   and $homeItem hold anchors built a few lines above. Escaping those would print
 *   tags as text.
 *
 * The rule is about the value, not the line: an anchor assembled here is left raw,
 * while the plain-text title inside it is escaped where the anchor is built. An
 * earlier pass grouped $breadcrumbs[$i] with get_category_parents() as
 * "ready-made HTML", which is not true of it and left the ancestor title unescaped
 * on every child page.
 *
 * get_the_time() values are the one thing kept as-is -- they come from a timestamp
 * and a fixed format string, never from user text.
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
				echo $homeItem;
			}

		} else if ( is_home() ){
			echo $homeItem .''. $delimiter;
			echo $beforeCurrent . esc_html( get_the_title(get_option('page_for_posts')) ) . $afterCurrent;

		} else {
			echo $homeItem .''. $delimiter;

			if ( is_category() ){
				$thisCat = get_category(get_query_var('cat'), false);
				if ( $thisCat->parent!=0 ){
					echo get_category_parents($thisCat->parent, true, $delimiter);
				}
				echo $beforeCurrent .single_cat_title('', false). $afterCurrent;

			} elseif ( is_search() ){
				echo $beforeCurrent .'Search results for: '. esc_html( get_search_query() ) . $afterCurrent;

			} elseif ( is_day() ){
				echo '<a href="'. get_year_link(get_the_time('Y')) .'">'. get_the_time('Y') .'</a>'. $delimiter;
				echo '<a href="'. get_month_link(get_the_time('Y'), get_the_time('m')) .'">'. get_the_time('F') .'</a>'. $delimiter;
				echo $beforeCurrent .get_the_time('d'). $afterCurrent;

			} elseif ( is_month() ){
				echo '<a href="'. get_year_link(get_the_time('Y')) .'">'. get_the_time('Y') .'</a>'. $delimiter;
				echo $beforeCurrent .get_the_time('F'). $afterCurrent;

			} elseif ( is_year() ){
				echo $beforeCurrent .get_the_time('Y'). $afterCurrent;

			} elseif ( is_single() && !is_attachment() ){
				if ( get_post_type()!='post' ){
					$post_type = get_post_type();
					$post_type_obj = get_post_type_object($post_type);
					echo '<a href="'. get_post_type_archive_link($post_type) .'">'. esc_html( $post_type_obj->labels->singular_name ) .'</a>';

					if ( $post->post_parent ){
						$parent_id = $post->post_parent;
						$breadcrumbs = array();
						while ( $parent_id ){
							$page = get_page($parent_id);
							$breadcrumbs[] = '<a href="'. get_permalink($page->ID) .'">'. esc_html( get_the_title($page->ID) ) .'</a>';
							$parent_id = $page->post_parent;
						}
						$breadcrumbs = array_reverse($breadcrumbs);
						echo $delimiter;
						for ( $i=0; $i<count($breadcrumbs); $i++ ){
							echo $breadcrumbs[$i];
							if ( $i!=count($breadcrumbs)-1 ){
								echo $delimiter;
							}
						}
					}

					if ( $showCurrent==1 ){
						echo $delimiter. $beforeCurrent .esc_html( get_the_title() ). $afterCurrent;
					}
				} else {
					$cat = get_the_category();
					$cat = $cat[0];
					$cats = get_category_parents($cat, true, $delimiter);
					if ( $showCurrent==0 ){
						$cats = preg_replace("#^(.+)$delimiter$#", "$1", $cats);
					}
					echo $cats;
					if ( $showCurrent==1 ){
						echo $beforeCurrent .esc_html( get_the_title() ). $afterCurrent;
					}
				}

			} elseif ( !is_single() && !is_page() && get_post_type()!='post' && !is_404() ){
				$post_type = get_post_type_object(get_post_type());
				echo $beforeCurrent .esc_html( $post_type->labels->singular_name ). $afterCurrent;

			} elseif ( is_attachment() ){
				$parent = get_post($post->post_parent);
				$cat = get_the_category($parent->ID);
				$cat = $cat[0];
				echo get_category_parents($cat, true, $delimiter);
				echo '<a href="' .get_permalink($parent). '">' .esc_html( $parent->post_title ). '</a>';
				if ( $showCurrent==1 ){
					echo $delimiter. $beforeCurrent .esc_html( get_the_title() ). $afterCurrent;
				}

			} elseif ( is_page() && !$post->post_parent ){
				if ( $showCurrent==1 ){
					echo $beforeCurrent .esc_html( get_the_title() ). $afterCurrent;
				}

			} elseif ( is_page() && $post->post_parent ){
				$parent_id = $post->post_parent;
				$breadcrumbs = array();
				while ( $parent_id ){
					$page = get_page($parent_id);
					$breadcrumbs[] = '<a href="'. get_permalink($page->ID) .'">'. esc_html( get_the_title($page->ID) ) .'</a>';
					$parent_id = $page->post_parent;
				}
				$breadcrumbs = array_reverse($breadcrumbs);
				for ( $i=0; $i<count($breadcrumbs); $i++ ){
					echo $breadcrumbs[$i];
					if ( $i!=count($breadcrumbs)-1 ){
						echo $delimiter;
					}
				}
				if ( $showCurrent==1 ){
					echo $delimiter. $beforeCurrent .esc_html( get_the_title() ). $afterCurrent;
				}

			} elseif ( is_tag() ){
				echo $beforeCurrent .'Posts tagged "'. esc_html( single_tag_title('', false) ) .'"'. $afterCurrent;

			} elseif ( is_author() ){
				global $author;
				$userdata = get_userdata($author);
				echo $beforeCurrent .'Articles posted by '. esc_html( $userdata->display_name ). $afterCurrent;

			} elseif ( is_404() ){
				echo $beforeCurrent .'Error 404'. $afterCurrent;

			}

			if ( get_query_var('paged') ){
				echo '<span class="breadcrumbs__paged">';
					if ( is_category() || is_day() || is_month() || is_year() || is_search() || is_tag() || is_author() ){
						echo ' (';
					}
					echo __('Page') .' '. get_query_var('paged');
					if ( is_category() || is_day() || is_month() || is_year() || is_search() || is_tag() || is_author() ){
						echo ')';
					}
				echo '</span>';
			}
		}

	echo '</nav>';
}
