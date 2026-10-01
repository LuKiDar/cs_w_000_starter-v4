<?php
/**
 * Search Form
 */
?>
<form role="search" method="get" class="search-form" action="<?= esc_url(home_url('/')); ?>">
	<label class="search-form__label" for="search-form-<?= esc_attr(wp_unique_id()); ?>">
		<?php esc_html_e('Search for:', CSWP); ?>
	</label>
	<input
		type="search"
		id="search-form-<?= esc_attr(wp_unique_id()); ?>"
		class="search-form__input"
		value="<?= esc_attr(get_search_query()); ?>"
		name="s"
		placeholder="<?php esc_attr_e('Search…', CSWP); ?>"
	/>
	<button type="submit" class="search-form__submit button">
		<?php esc_html_e('Search', CSWP); ?>
	</button>
</form>