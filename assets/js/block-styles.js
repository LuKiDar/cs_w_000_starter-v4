/**
 * Editor curation: unregister the core blocks and block styles this theme does not use.
 *
 * Plain browser JS depending on the wp.* globals — it is not part of the
 * SCSS/JS build and must not be bundled.
 */

wp.domReady(() => {
	const UNREGISTER_BLOCKS = [
		'core/archives', 'core/avatar', 'core/calendar', 'core/categories',
		'core/comment-author-name', 'core/comment-content', 'core/comment-date',
		'core/comment-edit-link', 'core/comment-reply-link', 'core/comment-template',
		'core/comments', 'core/comments-pagination', 'core/comments-title',
		'core/latest-comments', 'core/latest-posts', 'core/loginout',
		'core/navigation-submenu', 'core/page-list', 'core/post-author',
		'core/post-comments-form', 'core/post-navigation-link',
		'core/post-terms', 'core/rss', 'core/search', 'core/social-link',
		'core/social-links', 'core/tag-cloud', 'core/term-description',
	];

	const UNREGISTER_STYLES = [
		['core/image', 'rounded'],
		['core/separator', 'dots'],
		['core/separator', 'wide'],
		['core/table', 'stripes'],
	];

	UNREGISTER_BLOCKS.forEach((name) => {
		if (wp.blocks.getBlockType(name)) {
			wp.blocks.unregisterBlockType(name);
		}
	});

	UNREGISTER_STYLES.forEach(([block, style]) => {
		wp.blocks.unregisterBlockStyle(block, style);
	});
});