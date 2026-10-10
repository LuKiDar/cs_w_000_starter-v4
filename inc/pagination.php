<?php
/**
 * Pagination
 *
 * Ported from arosa. The 1% edge cases WordPress itself passes through
 * (a single page, no links) are already handled by paginate_links(); this only
 * adds the disabled Previous/Next bookends arosa's markup styles.
 */

function cs__the_pagination( $page_url='', $max_pages='' ){
	$args = array(
		'prev_text' => 'Previous',
		'next_text' => 'Next',
		'type' => 'array'
	);
	if ( $page_url!='' && $max_pages!='' ){
		$args['base'] = $page_url .'%_%';
		$args['format'] = '?page=%#%';
		$args['current'] = ( get_query_var('page') ) ? absint(get_query_var('page')) : 1;
		$args['total'] = $max_pages;
	}
	$links = paginate_links($args);

	if ( !empty($links) ){ ?>
		<nav class="pagination">
			<div class="nav-links">
				<?php if ( !strpos($links[0], 'Previous') ){ ?>
					<span class="prev page-numbers disabled">Previous</span>
				<?php } ?>

				<?php foreach ( $links as $item ){ ?>
					<?php echo wp_kses_post($item); ?>
				<?php } ?>

				<?php if ( !strpos($links[count($links)-1], 'Next') ){ ?>
					<span class="next page-numbers disabled">Next</span>
				<?php } ?>
			</div>
		</nav>
	<?php }
}