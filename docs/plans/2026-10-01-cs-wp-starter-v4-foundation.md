# CStheme v4 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up `cs_w_000_starter-v4` as a booting, buildable, checkable WordPress theme with the block system, the design-token pipeline, the ready-to-uncomment toolbox, the base template set and one reference block implemented end to end.

**Architecture:** A hybrid WordPress theme — PHP template hierarchy plus server-rendered ACF Pro blocks. Each block is a self-contained folder (`block.json` + `callback.php` + `render.php` + `style.scss` + `editor.scss` + optional `script.js`) auto-discovered from `parts/block/`. `theme.json` is the single source of truth for design tokens and a build step generates the SCSS token mirror. Block CSS/JS is declared in `block.json` with `file:` references so WordPress loads it only on pages that render the block.

**Tech Stack:** PHP 8.1 (Local by Flywheel, `php-8.1.23+0`), WordPress 6.x, ACF Pro 6.8.6, `theme.json` v3, SCSS (Dart Sass), Node 24.19.0 / npm 10.9.0, Python 3.14.3 for the stand script. Build tool decided by Task 1 (Vite or Gulp 5).

**Spec:** `docs/design/2026-10-01-cs-wp-starter-v4-design.md`

## Tasks

1. **Vite feasibility spike** — decide the build tool, recorded in the spec.
2. **The theme boots and renders** — `style.css`, `functions.php`, the `inc/` spine, the block loader, `header.php`/`footer.php`, the loop templates, `searchform.php`.
3. **Build pipeline and design tokens** — wire the chosen build; `theme.json` → generated `_tokens.scss`; the editor curation script.
4. **The stand script** — `scripts/check-theme-stand.py`, validated against v3 (must fail) and v4 (must pass).
5. **Block system** — `parts/block/_skeleton/`, the generator (`npm run make:block`), the field-group convention.
6. **Reference block `cta`** — proves the whole chain: registration, per-page asset loading, escaping, guards.
7. **The toolbox** — `inc/menu-walker.php`, breadcrumbs, pagination, shortcodes, widgets, `cpt-post.php`, `post-types.php`, `plugin-acf.php`, plus the commented-include map.
8. **Base templates** — archives, search, `templates/_skeleton.php`, `parts/content/` cards.
9. **Accessibility layer** — the two a11y files and their enqueue.
10. **README and PHP 8.4 compatibility** — the document the client's team works from.
11. **Lint and editor configuration** — `.editorconfig`, stylelint, PHPCS with WordPress standards.

## Global Constraints

- Working copy: `D:/Local/starter-theme/app/public/wp-content/themes/cs_w_000_starter-v4`. Nothing outside it may be modified — in particular `cs_w_000_starter`, `-v2`, `-v3` and the four client themes (arosa, millburn, nucleux, corazon) are read-only references.
- Repository: `https://github.com/LuKiDar/cs_w_000_starter-v4`, branch `main`. Commit after every task.
- All code, comments, docs and commit messages in **English**. Conversation with the owner is Ukrainian.
- PHP prefix `cs__` for functions, `CSWP` for the text domain, `'cswp'` in `style.css`.
- PHP binary for linting: `"C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe"` — not on `PATH`, always quote it.
- Local site URL: `https://starter-theme.local` (self-signed cert — always `curl -k`).
- `package.json` `"name"` must be `cs_w_000_starter-v4`, never a client project name.
- No `apiVersion: 2` blocks — `apiVersion: 3` only.
- Block assets are declared with `file:` references in `block.json`. **Never** `wp_enqueue_style`/`wp_enqueue_script` a block's assets from a loop in `inc/gutenberg.php`.
- SCSS declaration order inside a block: container → elements in architectural order → `// Modifiers` → `// States` → `// Frontend only styles`. Properties alphabetical within a rule. Media queries inside the element they modify, never collected at the bottom. `// Modifiers` / `// States` are comment headers over live code.
- Sources only in git. Compiled `*.min.css`, `*.min.js` and `*.map` are gitignored.
- PHP 8.1 is the target; the theme must also run clean on PHP 8.4 (`php-8.4.10+0` is installed and available for a compatibility lint pass).

## Review Focus

Failure modes the spec implies but no task's own tests exercise. Each is pinned to a test in the task that owns the code.

1. **A block folder without a `block.json`** (a half-created block, or `_skeleton` itself) — the loader must skip it silently, not fatal or emit a warning. → Task 5.
2. **A block whose ACF field group has not been synced yet** — `get_field()` returns `false`; `render.php` must early-return with no PHP notice, and the block must not print an empty wrapper. → Task 6.
3. **ACF Pro deactivated entirely** — the theme must not fatal: every ACF call site is guarded. → Task 6.
4. **The same block twice on one page** — its stylesheet enqueues once, and each instance gets a unique `id` (`$block['anchor'] ?: $block['id']`). → Task 6.
5. **PHP 8.4 running the theme** — no deprecation or notice may reach the error log on a rendered page. → Task 10.

---

## Task 1: Vite feasibility spike

A throwaway probe answering three questions. Output is a decision, not code — nothing built here is kept.

**Files:**
- Create (throwaway, outside the theme): `$TMPDIR/vite-spike/`
- Modify: `docs/design/2026-10-01-cs-wp-starter-v4-design.md` (§13, record the outcome)

**Interfaces:**
- Produces: a recorded decision — `VITE` or `GULP` — that Task 3 implements. The theme's output layout is fixed either way: `parts/block/<slug>/style.min.css`, `parts/block/<slug>/editor.min.css`, `assets/css/{main,editor,admin}.min.css`, `assets/js/dist/main.min.js`.

- [ ] **Step 1: State the question**

Can Vite (a) compile a block's `style.scss` in place to `style.min.css` with a stable, unhashed filename, (b) update a block's SCSS in the browser without a full page reload, and (c) drive a manifest-based `wp_enqueue_*` that loads a block's CSS/JS **only** on pages rendering that block?

- [ ] **Step 2: Build the probe**

In `$TMPDIR/vite-spike/` create two throwaway block folders with a `style.scss` each, plus:

```js
// vite.config.mjs
import { defineConfig } from 'vite';
import { glob } from 'glob';
import path from 'node:path';

const blockStyles = glob.sync('parts/block/*/style.scss');
const blockEditors = glob.sync('parts/block/*/editor.scss');

export default defineConfig({
  build: {
    manifest: true,
    outDir: '.',
    emptyOutDir: false,
    rollupOptions: {
      input: Object.fromEntries(
        [...blockStyles, ...blockEditors].map(f => [f.replace(/\.scss$/, ''), f])
      ),
      output: {
        assetFileNames: (info) => {
          // keep the compiled CSS beside its source, unhashed
          return info.names?.[0]?.replace(/\.css$/, '.min.css') ?? '[name].min.css';
        },
      },
    },
  },
});
```

Run `npx vite build` and inspect where the CSS actually lands.

- [ ] **Step 3: Record pass/fail against the three criteria**

For each of (a), (b), (c) write pass or fail with the command output that proves it. Criterion (b) is the one most likely to fail: Vite's HMR client must be injected into a PHP-rendered page, which requires an extra `wp_enqueue_script` of `@vite/client` on localhost.

- [ ] **Step 4: Decide and record**

Append the outcome to the design doc §13 under "Spike result": the decision (`VITE` or `GULP`), the evidence, and the reasoning. If any criterion failed, the decision is `GULP`.

- [ ] **Step 5: Commit**

```bash
cd "D:/Local/starter-theme/app/public/wp-content/themes/cs_w_000_starter-v4"
git add docs/design/2026-10-01-cs-wp-starter-v4-design.md
git commit -m "docs: record build tool spike result"
```

---

## Task 2: The theme boots and renders

The skeleton: identity, the `inc/` spine, the block loader, and enough templates that the local site renders a page.

**Files:**
- Create: `style.css`, `functions.php`, `package.json`, `theme.json`
- Create: `inc/constants.php`, `inc/enqueue.php`, `inc/helper-functions.php`, `inc/wordpress-cleanup.php`, `inc/gutenberg.php`
- Create: `header.php`, `footer.php`, `index.php`, `page.php`, `single.php`, `404.php`, `searchform.php`
- Create: `assets/scss/main.scss`, `assets/scss/abstracts/_functions.scss`, `assets/scss/abstracts/_variables.scss`, `assets/scss/abstracts/_mixins.scss`, `assets/scss/base/_base.scss`
- Create: `parts/block/.gitkeep`, `assets/css/.gitkeep`, `assets/js/dist/.gitkeep`

**Interfaces:**
- Produces: `CSWP` constant; `cs__enqueue_assets()`; `cs__load_blocks()`; `cs__get_blocks()`; `cs__register_block_categories()`; `cs__theme_setup()`. Tasks 3–10 all hang off these names.

- [ ] **Step 1: Write the failing test**

```bash
cd "D:/Local/starter-theme/app/public/wp-content/themes/cs_w_000_starter-v4"
PHP="C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe"
"$PHP" -l functions.php
```

Expected: FAIL — `Could not open input file: functions.php`

- [ ] **Step 2: Write `style.css`**

```css
/*
	Theme Name: CStheme
	Author: Dariia Lukiianchuk
	Author URI: https://github.com/LuKiDar
	Description: A custom WordPress starter theme built with a hybrid approach and native Gutenberg blocks.
	Version: 4.0
	Requires at least: 6.5
	Tested up to: 6.8
	Requires PHP: 8.1
	License: GNU General Public License v3
	License URI: https://www.gnu.org/licenses/gpl-2.0.html
	Text Domain: cswp
	Tags: custom-theme, gutenberg, hybrid, responsive
*/
```

- [ ] **Step 3: Write `functions.php` with the commented toolbox**

```php
<?php
/**
 * Theme Functions
 */

/* --- Constants --- */
define('CSWP', 'cswp');
include 'inc/constants.php';


/* --- Theme setup --- */
function cs__theme_setup(){
	load_theme_textdomain(CSWP, get_template_directory() .'/languages');

	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('automatic-feed-links');
	add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
	add_theme_support('custom-logo');
	add_theme_support('menus');
	add_theme_support('responsive-embeds');
	add_theme_support('wp-block-styles');
	add_theme_support('editor-styles');
	add_theme_support('align-wide');

	register_nav_menus(array(
		'primary' => __('Primary Menu', CSWP),
	));

	add_filter('should_load_separate_core_block_assets', '__return_true');
	remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
	remove_theme_support('core-block-patterns');
	define('CORE_UPGRADE_SKIP_NEW_BUNDLED', true);
}
add_action('after_setup_theme', 'cs__theme_setup');


/* --- Enable SVG uploads --- */
function cs__mime_types( $mimes ){
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}
add_filter('upload_mimes', 'cs__mime_types');


/* --- Custom logo classes --- */
add_filter('get_custom_logo', function( $html ){
	$html = str_replace('custom-logo-link', 'logo', $html);
	$html = str_replace('custom-logo', 'logo__image', $html);
	return $html;
});


/* --- Includes --- */
// Theme (always on)
require_once 'inc/enqueue.php';
require_once 'inc/wordpress-cleanup.php';
require_once 'inc/helper-functions.php';
require_once 'inc/gutenberg.php';

// Toolbox — uncomment what the project needs. Every file below exists in inc/.
// require_once 'inc/menu-walker.php';
// require_once 'inc/breadcrumbs.php';
// require_once 'inc/pagination.php';
// require_once 'inc/shortcodes.php';
// require_once 'inc/widgets.php';
// require_once 'inc/post-types.php';
// require_once 'inc/cpt-post.php';
// require_once 'inc/admin.php';
// require_once 'inc/customize.php';

// Plugin support
// require_once 'inc/plugin-acf.php';       // ACF options page fallback (Customizer is the primary settings surface)
```

- [ ] **Step 4: Write `inc/constants.php`**

Port verbatim from `cs_w_000_starter-v3/inc/constants.php` — `DEFAULT_CPT_LABELS`, `DEFAULT_CPT_ARGS`, `DEFAULT_TAXONOMY_LABELS`, `DEFAULT_TAXONOMY_ARGS`. These are read-only reference; copy the file content as-is.

- [ ] **Step 5: Write `inc/enqueue.php`**

```php
<?php
/**
 * Enqueue Assets
 */

/* --- Theme styles and scripts --- */
function cs__enqueue_assets(){
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style('theme-style', get_stylesheet_uri());

	$main_css = '/assets/css/main.min.css';
	if ( file_exists($dir . $main_css) ){
		wp_enqueue_style('theme-main', $uri . $main_css, array(), filemtime($dir . $main_css), 'all');
	}

	$main_js = '/assets/js/dist/main.min.js';
	if ( file_exists($dir . $main_js) ){
		wp_enqueue_script('theme-main', $uri . $main_js, array(), filemtime($dir . $main_js), true);
	}
}
add_action('wp_enqueue_scripts', 'cs__enqueue_assets');


/* --- Editor styles --- */
function cs__enqueue_editor_assets(){
	$editor_css = get_template_directory() .'/assets/css/editor.min.css';
	if ( file_exists($editor_css) ){
		add_editor_style('assets/css/editor.min.css');
	}
}
add_action('enqueue_block_editor_assets', 'cs__enqueue_editor_assets');
```

Note the `file_exists()` guards: they exist so the theme renders before the first build has ever run.

- [ ] **Step 6: Write `inc/gutenberg.php` — the block loader**

```php
<?php
/**
 * Gutenberg
 */

/* --- Block category --- */
function cs__register_block_categories( $categories ){
	$theme = wp_get_theme();

	return array_merge(
		array(
			array(
				'slug'  => 'cs-blocks',
				'title' => sprintf(__('%s blocks', CSWP), $theme->get('Name')),
			),
		),
		$categories
	);
}
add_filter('block_categories_all', 'cs__register_block_categories', 10, 1);


/* --- Discover block folders --- */
function cs__get_blocks(){
	$directory = get_stylesheet_directory() .'/parts/block/';

	if ( ! is_dir($directory) ){
		return array();
	}

	$entries = scandir($directory);
	$exclude = array('..', '.', '.DS_Store', '_skeleton', '_base-block');

	return array_values(array_diff($entries, $exclude));
}


/* --- Register every folder that carries a block.json --- */
function cs__load_blocks(){
	foreach ( cs__get_blocks() as $block ){
		$block_dir  = get_stylesheet_directory() ."/parts/block/{$block}";
		$block_json = "{$block_dir}/block.json";

		// Skip anything that is not a complete block. No warning: a folder may be
		// mid-creation, and _skeleton is excluded above for the same reason.
		if ( ! file_exists($block_json) ){
			continue;
		}

		$functions_file = "{$block_dir}/block-functions.php";
		if ( file_exists($functions_file) ){
			require_once $functions_file;
		}

		$callback_file = "{$block_dir}/callback.php";
		if ( file_exists($callback_file) ){
			require_once $callback_file;
		}

		// Assets are declared in block.json with file: references, so WordPress
		// loads them only on pages that render the block. Never enqueue here.
		register_block_type($block_json);
	}
}
add_action('init', 'cs__load_blocks', 5);


/* --- ACF field groups stored beside their block --- */
add_filter('acf/settings/load_json', function( $paths ){
	foreach ( cs__get_blocks() as $block ){
		$paths[] = get_stylesheet_directory() ."/parts/block/{$block}";
	}
	return $paths;
});
```

- [ ] **Step 7: Write `inc/helper-functions.php`**

Port `cs__get_template_page_ID()`, `cs__has_block()` and `cs__generate_url_handle()` from v3 unchanged, plus the two additions the block contract needs:

```php
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
	if ( ! empty($block['textAlign']) ){
		$classes[] = 'has-text-align-'. $block['textAlign'];
	}

	return implode(' ', array_unique($classes));
}


/* --- Read a block field, surviving a deactivated ACF Pro --- */
function cs__get_block_field( $name ){
	return function_exists('get_field') ? get_field($name) : null;
}
```

- [ ] **Step 8: Write `inc/wordpress-cleanup.php`**

Port verbatim from `cs_w_000_starter-v3/inc/wordpress-cleanup.php` (172 lines) — head cleanup, excerpt filters, extra body classes, body/nav class whitelisting, emoji removal, comments disabled.

- [ ] **Step 9: Write `header.php`**

```php
<?php
/**
 * Header Template
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class('is-header-fixed'); ?>>
	<?php wp_body_open(); ?>

	<div id="page" class="site-container">
		<a class="screen-reader-shortcut" href="#main" aria-label="<?php esc_attr_e('Skip to main content', CSWP); ?>"><?php esc_html_e('Skip to main content', CSWP); ?></a>
		<a class="screen-reader-shortcut" href="#footer" aria-label="<?php esc_attr_e('Skip to footer content', CSWP); ?>"><?php esc_html_e('Skip to footer content', CSWP); ?></a>

		<header id="masthead" class="site-header container" role="banner">
			<div class="site-header__inner alignwide">
				<div class="site-logo">
					<?php if ( has_custom_logo() ): ?>
						<?php the_custom_logo(); ?>
					<?php else: ?>
						<a href="<?= esc_url(home_url('/')); ?>" class="site-title"><?php bloginfo('name'); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( has_nav_menu('primary') ): ?>
					<nav class="site-header__navigation" role="navigation" aria-label="<?php esc_attr_e('Main Menu', CSWP); ?>">
						<?php wp_nav_menu(array(
							'theme_location' => 'primary',
							'menu_class'     => 'primary-menu',
							'container'      => false,
							'depth'          => 2,
						)); ?>
					</nav>
				<?php endif; ?>

				<button class="nav-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="<?php esc_attr_e('Toggle Navigation', CSWP); ?>">
					<span class="nav-toggle__bar" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e('Menu', CSWP); ?></span>
				</button>
			</div>

			<?php if ( has_nav_menu('primary') ): ?>
				<nav id="mobile-menu" class="mobile-navigation" role="navigation" aria-label="<?php esc_attr_e('Mobile Menu', CSWP); ?>" hidden>
					<?php wp_nav_menu(array(
						'theme_location' => 'primary',
						'menu_class'     => 'primary-menu',
						'container'      => false,
						'depth'          => 2,
					)); ?>
				</nav>
			<?php endif; ?>
		</header>

		<main id="main" class="site-main">
```

**Do not** pass a `'walker' => new cs__primary_menu_walker()` argument here. That class lives in `inc/menu-walker.php`, which is a commented include in v4 — referencing it unconditionally is exactly what produced the v3 fatal (`Class "cs__primary_menu_walker" not found`, every page 500). Task 7 wires the walker once the file exists and the include is documented as required.

- [ ] **Step 10: Write `footer.php`**

```php
<?php
/**
 * Footer Template
 */
?>
		</main>

		<footer id="footer" class="site-footer container" role="contentinfo">
			<div class="site-footer__inner alignwide">
				<?php if ( has_nav_menu('footer') ): ?>
					<nav class="site-footer__navigation" role="navigation" aria-label="<?php esc_attr_e('Footer Menu', CSWP); ?>">
						<?php wp_nav_menu(array(
							'theme_location' => 'footer',
							'menu_class'     => 'footer-menu',
							'container'      => false,
							'depth'          => 1,
						)); ?>
					</nav>
				<?php endif; ?>

				<p class="site-footer__copyright">
					<?php
					printf(
						/* translators: %s: site name */
						esc_html__('© %1$s %2$s', CSWP),
						esc_html(date_i18n('Y')),
						esc_html(get_bloginfo('name'))
					);
					?>
				</p>
			</div>
		</footer>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
```

Register the footer menu location alongside `primary` in `functions.php`:

```php
	register_nav_menus(array(
		'primary' => __('Primary Menu', CSWP),
		'footer'  => __('Footer Menu', CSWP),
	));
```

- [ ] **Step 11: Write the loop templates**

`index.php`, `page.php`, `single.php`, `404.php` share this shape — `page.php` shown, the others differ only in the heading level and the not-found copy:

```php
<?php
/**
 * Page Template
 */

get_header();
?>

<div class="container">
	<?php while ( have_posts() ): the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h1 class="page-title"><?php the_title(); ?></h1>
			<div class="page-content"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 12: Write `searchform.php`**

```php
<?php
/**
 * Search Form
 */

$search_id = 'search-form-' . wp_unique_id();
?>
<form role="search" method="get" class="search-form" action="<?= esc_url(home_url('/')); ?>">
	<label class="search-form__label" for="<?= esc_attr($search_id); ?>">
		<?php esc_html_e('Search for:', CSWP); ?>
	</label>
	<input
		type="search"
		id="<?= esc_attr($search_id); ?>"
		class="search-form__input"
		value="<?= esc_attr(get_search_query()); ?>"
		name="s"
		placeholder="<?php esc_attr_e('Search…', CSWP); ?>"
	/>
	<button type="submit" class="search-form__submit button">
		<?php esc_html_e('Search', CSWP); ?>
	</button>
</form>
```

**`wp_unique_id()` must be called exactly once and stored.** It is a static counter — `static $id_counter = 0; return $prefix . (string) ++$id_counter;` (`wp-includes/functions.php:8156`) — so two calls return two different values. Calling it separately in the `for` and the `id` produces a label bound to nothing on every render, and the input loses its accessible name.

- [ ] **Step 13: Write a minimal `theme.json`**

```json
{
	"$schema": "https://schemas.wp.org/wp/6.8/theme.json",
	"version": 3,
	"settings": {
		"appearanceTools": true,
		"color": {
			"custom": false,
			"defaultPalette": false,
			"defaultGradients": false,
			"palette": [
				{"color": "#000000", "name": "Black", "slug": "black"},
				{"color": "#FFFFFF", "name": "White", "slug": "white"}
			]
		},
		"layout": {"contentSize": "75rem", "wideSize": "87.5rem"},
		"typography": {
			"customFontSize": false,
			"defaultFontSizes": false,
			"fluid": false
		},
		"useRootPaddingAwareAlignments": true
	}
}
```

Task 3 replaces this with the full token set.

- [ ] **Step 14: Write `package.json`**

```json
{
	"name": "cs_w_000_starter-v4",
	"version": "4.0.0",
	"description": "A WordPress starter theme built with a hybrid approach and native Gutenberg blocks.",
	"author": "Crazy Seeker",
	"license": "GPL-3.0",
	"private": true
}
```

- [ ] **Step 15: Run the lint test**

```bash
PHP="C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe"
find . -name '*.php' -not -path './node_modules/*' -print0 | xargs -0 -n1 "$PHP" -l
```

Expected: PASS — `No syntax errors detected` for every file.

- [ ] **Step 16: Activate the theme (owner action)**

The active theme is still `cs_w_000_starter-v3`, so the site returns 500. The owner switches Appearance → Themes to `CStheme` (v4). No `wp-cli` is installed, so this is manual.

- [ ] **Step 17: Verify the site renders**

```bash
curl -k -s -o /dev/null -w "%{http_code}\n" https://starter-theme.local
curl -k -s https://starter-theme.local | grep -c 'id="masthead"'
curl -k -s https://starter-theme.local | grep -c 'id="main"'
```

Expected: `200`, `1`, `1`

- [ ] **Step 18: Commit**

```bash
git add -A
git commit -m "feat: theme skeleton with block loader and commented toolbox"
```

---

## Task 3: Build pipeline and design tokens

Implements the Task 1 decision and wires the token pipeline.

**Files:**
- Create (if `GULP`): `gulpfile.js`
- Create (if `VITE`): `vite.config.mjs`, `inc/vite.php`
- Create: `scripts/build-tokens.mjs`
- Modify: `package.json` (scripts + devDependencies)
- Modify: `theme.json` (full token set)
- Create: `assets/scss/abstracts/_tokens.scss` (generated — gitignored, regenerated by the build)
- Modify: `assets/scss/abstracts/_variables.scss`
- Modify: `assets/scss/main.scss`
- Create: `assets/scss/editor.scss`, `assets/scss/admin.scss`

**Interfaces:**
- Consumes: `theme.json`.
- Produces: `npm run build`, `npm run watch`, `npm run tokens`. `_tokens.scss` defines `$color_*`, `$fontSize_*`, `$spacing_*`, `$borderRadius_*`, `$layout_*` as `var(--wp--preset--…)` / `var(--wp--custom--…)` references.

- [ ] **Step 1: Write the failing test**

```bash
npm run tokens
```

Expected: FAIL — `npm ERR! Missing script: "tokens"`

- [ ] **Step 2: Write `scripts/build-tokens.mjs`**

```js
import fs from 'node:fs';
import path from 'node:path';

const root      = process.cwd();
const themeJson = JSON.parse(fs.readFileSync(path.join(root, 'theme.json'), 'utf8'));

const s = themeJson.settings ?? {};
const lines = [
	'// GENERATED FILE — do not edit. Source: theme.json. Run: npm run tokens',
	'',
];

for ( const { slug } of s.color?.palette ?? [] ){
	lines.push(`$color_${slug.replace(/-/g, '_')}: var(--wp--preset--color--${slug});`);
}
lines.push('');
for ( const { slug } of s.typography?.fontSizes ?? [] ){
	lines.push(`$fontSize_${slug.replace(/-/g, '_')}: var(--wp--preset--font-size--${slug});`);
}
lines.push('');
for ( const { slug } of s.spacing?.spacingSizes ?? [] ){
	lines.push(`$spacing_${slug}: var(--wp--preset--spacing--${slug});`);
}
lines.push('');
for ( const [key, value] of Object.entries(s.custom ?? {}) ){
	if ( typeof value === 'object' ){
		for ( const [sub, subValue] of Object.entries(value) ){
			if ( typeof subValue === 'string' ){
				lines.push(`$custom_${key.replace(/-/g, '_')}_${sub.replace(/-/g, '_')}: var(--wp--custom--${key}--${sub});`);
			}
		}
	}
}
lines.push('');

const out = path.join(root, 'assets/scss/abstracts/_tokens.scss');
fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, lines.join('\n'), 'utf8');

console.log(`Wrote ${lines.length} lines to assets/scss/abstracts/_tokens.scss`);
```

- [ ] **Step 3: Write the full `theme.json`**

Carry over the token structure from `cs_w_000_starter-v3/theme.json` (516 lines) — `appearanceTools`, the locked-down preset flags, `palette`, `typography.fontFamilies` / `fontSizes`, `spacing.spacingSizes`, `custom.{border-radius,box-shadow,functionalColor,layout,line-height}`, `layout`, `useRootPaddingAwareAlignments`, and the `styles` layer for elements (`button`, `heading`, `h1`–`h6`, `link`, `caption`, `cite`) and core blocks. Change two things:

1. Use a neutral starter palette — one brand colour plus a grey ramp — since a starter must not carry a client's colours.
2. Point `settings.layout.contentSize` / `wideSize` at `var(--wp--custom--layout--content)` / `--wide`, as v3 does.

- [ ] **Step 4: Wire the build — Gulp branch**

Only if Task 1 decided `GULP`. Port the arosa `gulpfile.js`, changing the BrowserSync proxy to `https://starter-theme.local` and adding the token generation as the first step of the default task:

```js
const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer');
const cleanCSS = require('gulp-clean-css');
const rename = require('gulp-rename');
const browserSync = require('browser-sync').create();
const sourcemaps = require('gulp-sourcemaps');
const glob = require('glob');
const path = require('path');
const { execSync } = require('child_process');

const paths = {
	styles:  { src: 'assets/scss/*.scss', dest: 'assets/css' },
	scripts: { src: 'assets/js/src/**/*.js', dest: 'assets/js/dist' },
};

function tokens(done){
	execSync('node scripts/build-tokens.mjs', { stdio: 'inherit' });
	done();
}

function compileSass(){
	return gulp.src(paths.styles.src)
		.pipe(sourcemaps.init())
		.pipe(sass({ silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import'] }).on('error', sass.logError))
		.pipe(autoprefixer())
		.pipe(cleanCSS())
		.pipe(rename({ suffix: '.min' }))
		.pipe(sourcemaps.write('.'))
		.pipe(gulp.dest(paths.styles.dest))
		.pipe(browserSync.stream());
}

function compileBlockSass(){
	const files = glob.sync('parts/block/**/*.scss');
	if ( ! files.length ){ return Promise.resolve(); }

	return gulp.src(files)
		.pipe(sourcemaps.init())
		.pipe(sass({ silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import'] }).on('error', sass.logError))
		.pipe(autoprefixer())
		.pipe(cleanCSS())
		.pipe(rename({ suffix: '.min' }))
		.pipe(sourcemaps.write('.'))
		.pipe(gulp.dest(file => path.dirname(file.path)))
		.pipe(browserSync.stream());
}

function watchFiles(done){
	browserSync.init({
		proxy: 'https://starter-theme.local',
		open: false,
		notify: false,
		https: { rejectUnauthorized: false },
	});

	gulp.watch('assets/scss/**/*.scss', compileSass);
	gulp.watch('parts/block/**/*.scss', compileBlockSass);
	gulp.watch('**/*.php').on('change', browserSync.reload);
	done();
}

exports.tokens = tokens;
exports.compileSass = compileSass;
exports.compileBlockSass = compileBlockSass;
exports.watch = watchFiles;
exports.build = gulp.series(tokens, gulp.parallel(compileSass, compileBlockSass));
exports.default = gulp.series(tokens, gulp.parallel(compileSass, compileBlockSass), watchFiles);
```

Add to `package.json`: `"start": "gulp"`, `"build": "gulp build"`, `"watch": "gulp watch"`, `"tokens": "node scripts/build-tokens.mjs"`, and devDependencies `gulp ^5.0.0`, `gulp-sass ^6.0.0`, `sass ^1.85.1`, `gulp-autoprefixer ^8.0.0`, `gulp-clean-css ^4.3.0`, `gulp-rename ^2.0.0`, `gulp-sourcemaps ^3.0.0`, `browser-sync ^3.0.3`, `glob ^11.0.1`.

`"watch"` binds to `exports.watch` in the gulpfile below. It is easy to omit because this step's prose names only three scripts while the task's Interfaces line promises four — keep the two in agreement.

- [ ] **Step 5: Wire the build — Vite branch**

Only if Task 1 decided `VITE`. Use the config validated in Task 1, plus `inc/vite.php` reading the manifest:

```php
<?php
/**
 * Vite manifest reader (development + production)
 */

function cs__vite_manifest(){
	static $manifest = null;

	if ( $manifest !== null ){
		return $manifest;
	}

	$path = get_template_directory() .'/build/.vite/manifest.json';
	$manifest = file_exists($path) ? json_decode(file_get_contents($path), true) : array();

	return $manifest;
}
```

and in `inc/enqueue.php`, resolve each handle's file through the manifest with a `file_exists()` fallback to the unhashed path.

- [ ] **Step 6: Write `assets/scss/abstracts/_variables.scss` and the entry points**

```scss
// assets/scss/abstracts/_variables.scss
@import 'tokens';
```

`assets/scss/main.scss` imports abstracts, then `base/*`, `components/*`, `layout/*`, `pages/*`, `parts/*` in that order. `editor.scss` and `admin.scss` each import the abstracts they need.

Then add the editor curation script, `assets/js/block-styles.js` — this is what keeps the block inserter to the theme's own blocks:

```js
/**
 * Editor curation: unregister the core blocks and block styles this theme does not use.
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
```

Enqueue it on `enqueue_block_editor_assets` from `inc/enqueue.php`, alongside `add_editor_style()`:

```php
	$block_styles_js = '/assets/js/block-styles.js';
	if ( file_exists(get_template_directory() . $block_styles_js) ){
		wp_enqueue_script(
			'theme-block-styles',
			get_template_directory_uri() . $block_styles_js,
			array('wp-blocks', 'wp-dom-ready', 'wp-edit-post'),
			filemtime(get_template_directory() . $block_styles_js),
			true
		);
	}
```

This file is not part of the SCSS/JS build — it is plain browser JS depending on `wp.*` globals, loaded as-is. Do not add it to a bundle.

The list above is a starting point carried over from the client themes; trim it per project.

- [ ] **Step 7: Run the test**

```bash
npm install
npm run tokens
npm run build
ls -1 assets/css/
head -12 assets/scss/abstracts/_tokens.scss
```

Expected: PASS — `assets/css/` contains `main.min.css`, `editor.min.css`, `admin.min.css`; `_tokens.scss` starts with the GENERATED header and lists the palette slugs.

- [ ] **Step 8: Verify the generated file is gitignored**

```bash
git status --short
```

Expected: `_tokens.scss` and the `*.min.css` outputs do **not** appear. If they do, add `assets/scss/abstracts/_tokens.scss` to `.gitignore`.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: build pipeline and generated design-token mirror"
```

---

## Task 4: The stand script

`scripts/check-theme-stand.py`. Its oracle is the frozen v3 theme: the script **must** fail on v3 because of the missing `cs__primary_menu_walker` class, and must pass on v4.

**Files:**
- Create: `scripts/check-theme-stand.py`
- Create: `scripts/check-symbols.php`

**Interfaces:**
- Consumes: nothing from earlier tasks beyond the theme layout.
- Produces: `python scripts/check-theme-stand.py <theme-dir>` → exit 0 on pass, 1 on failure, one `PASS`/`FAIL` line per check. Task 10 and every later phase run this.

- [ ] **Step 1: Write the failing test**

```bash
python scripts/check-theme-stand.py "D:/Local/starter-theme/app/public/wp-content/themes/cs_w_000_starter-v3"; echo "exit=$?"
```

Expected: FAIL — `python: can't open file ... No such file or directory`, exit 2.

- [ ] **Step 2: Write the two scripts**

**`scripts/check-symbols.php`** — the symbol analyser, and the reason this task ships two files rather than one.

`check_symbols` used to be a regex pass in Python. It is a tokenizer now, because PHP's block structure is neither indentation-based nor line-based: a text scan cannot tell a call site from a mention inside a comment, a string or a heredoc, and tokens can.

It reports every `cs__`/`CS_` call or `new` whose symbol is defined nowhere in the theme, and exits 0 whenever it ran at all — the caller decides what the output means. It ships inside the theme, so it must work on the client's machines: the theme directory is its only argument and the PHP standard library its only dependency.

**It deliberately does not honour `function_exists()` guards.** Six review rounds tried to prove statically that a guarded call is safe, and every attempt leaked somewhere new — always as a *missed fatal* in code that reads as ordinary in a WordPress template. The rule is flat on purpose. The reasoning is in Step 3b and in the file's own header; it should not be re-litigated without reading both.

```php
<?php
/**
 * Report `cs__` symbols that are called but defined nowhere in the theme.
 *
 * Usage:  php scripts/check-symbols.php <theme-dir>
 * Output: one JSON object per line:
 *           {"file":"<path relative to theme>","line":<int>,"symbol":"<name>","kind":"call"|"new"}
 * Exit:   0 when the analysis ran, 2 when it could not (bad argument). The caller
 *          decides what the output means; a non-zero exit would be indistinguishable
 *          from the caller's own failure.
 *
 * Why a tokenizer and not a regex: PHP's block structure is neither indentation-based
 * nor line-based, so a text scan cannot tell a call site from a mention inside a
 * comment, a string or a heredoc. Tokens can, and they do not guess.
 *
 * What counts as a call site. Three forms, and every one of them is lexical -- none
 * needs flow analysis, which is the whole point: six rounds of trying to reason about
 * `function_exists()` guards statically each leaked, and every leak was a missed fatal.
 *   - a plain call, `cs__x(`, including a fully qualified `\cs__x(`
 *   - a class construction, `new cs__Widget`
 *   - a string callback: a string literal whose entire content is a `cs__`/`CS_` name,
 *     as in `add_action('init', 'cs__foo')`. This is the dominant form in a WordPress
 *     theme -- the starter theme passes thirteen of them to add_action/add_filter --
 *     and WordPress fatals at runtime if the named function does not exist.
 * A method (`$o->cs__m()`), a static call (`Foo::cs__m()`), a docblock, a heredoc, and a
 * string that merely mentions the name are not call sites.
 *
 * Why a `function_exists()` test is NOT an exemption. Six rounds of trying to prove
 * statically that a `cs__` call is guarded each produced a replacement that leaked
 * somewhere new: an over-indented closer, a tab/space mix, `<?php } ?>`, a closer
 * behind a comment, a one-line guard, a brace-less body, `&&` versus `||`, a ternary
 * consequent, a guard in a `for` header, an alt-form `else:` branch, a test nested in
 * an enclosing group, and five more in the final round alone. Every one of those leaks
 * was a *missed fatal* in code that reads as ordinary in a WordPress template -- and a
 * missed fatal is the failure this check exists for: `Class "cs__primary_menu_walker"
 * not found` is what took the v3 theme down.
 *
 * So the rule is flat: report every `cs__` call whose symbol is defined nowhere in the
 * theme, guard or no guard. A false alarm costs one line of noise; a missed fatal costs
 * the site. If a call really does target another theme or plugin, say so where the call
 * is made -- `class_exists()` / `function_exists()` at the call site, or a stub -- rather
 * than asking a scanner to prove it on your behalf.
 */

declare(strict_types=1);

const SKIP_DIRS = ['node_modules', '.git', 'vendor', 'build'];

/**
 * Collect the theme's PHP files as [absolute path, path relative to the theme].
 */
function theme_files(string $root): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        foreach (SKIP_DIRS as $skip) {
            if (str_starts_with($rel, $skip . '/') || str_contains($rel, '/' . $skip . '/')) {
                continue 2;
            }
        }
        $files[] = [$f->getPathname(), $rel];
    }
    sort($files);
    return $files;
}

/**
 * The next significant token at or after $i, skipping whitespace and comments.
 */
function next_significant(array $tokens, int $i): array|string|null
{
    $n = count($tokens);
    for ($j = $i + 1; $j < $n; $j++) {
        $t = $tokens[$j];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $t;
    }
    return null;
}

function is_skippable(mixed $t): bool
{
    return is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function is_cs_symbol(string $name): bool
{
    return str_starts_with($name, 'cs__') || str_starts_with($name, 'CS_');
}

/**
 * One file: what it defines, and which of its `cs__` uses resolve nowhere.
 *
 * @return array{defined: list<string>, reported: list<array{int, string, string}>}
 */
function analyze(string $src): array
{
    $tokens = token_get_all($src);
    $n = count($tokens);

    $defined = [];
    $reported = [];
    $prevSig = null;

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (is_skippable($t)) {
            continue;
        }

        if (is_array($t) && ($t[0] === T_FUNCTION || $t[0] === T_CLASS
            || $t[0] === T_INTERFACE || $t[0] === T_TRAIT)) {
            // `function &cs__x()` returns by reference: the `&` sits between the
            // keyword and the name, and stepping past it by token index rather than
            // by "next significant" is what makes it land on the name.
            $j = $i + 1;
            while ($j < $n && is_skippable($tokens[$j])) {
                $j++;
            }
            // PHP 8.1 lexes the `&` of `function &cs__x()` as T_AMPERSAND_* rather than
            // as the single character '&', so match on the token's text.
            $isAmp = $j < $n
                && ($tokens[$j] === '&'
                    || (is_array($tokens[$j]) && $tokens[$j][1] === '&'));
            if ($isAmp) {
                $j++;
                while ($j < $n && is_skippable($tokens[$j])) {
                    $j++;
                }
            }
            if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING
                && is_cs_symbol($tokens[$j][1])) {
                $defined[$tokens[$j][1]] = true;
            }
        } elseif (is_array($t) && $t[0] === T_NEW) {
            $nx = next_significant($tokens, $i);
            if (is_array($nx) && $nx[0] === T_STRING && is_cs_symbol($nx[1])) {
                $reported[] = [$nx[2], $nx[1], 'new'];
            }
        } elseif (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
            // `add_action('init', 'cs__foo')` is a call site: WordPress calls
            // cs__foo() at runtime and fatals if it does not exist. This is lexical,
            // not flow analysis -- a string literal either names a cs__ symbol or it
            // does not -- so it cannot leak the way guard tracking did.
            $inner = trim($t[1], "'\"");
            // Only `cs__`, not `CS_`. The spec names `cs__` as the prefix for
            // functions and classes; `CS_` appears nowhere in it, and an
            // uppercase-underscore string is a CONSTANT name far more often than a
            // callback -- `define('CS_VERSION', ...)` would report as a callback the
            // moment anyone adds a version constant to a starter theme. Calls and
            // `new` still check both prefixes: an undefined `CS_foo()` is a fatal
            // whichever way you read it.
            if (preg_match('/^cs__\w+$/', $inner)) {
                $reported[] = [$t[2], $inner, 'callback'];
            }
        } elseif (is_array($t) && ($t[0] === T_STRING || $t[0] === T_NAME_FULLY_QUALIFIED)) {
            // PHP 8 lexes `\cs__x` as a single T_NAME_FULLY_QUALIFIED token rather than
            // T_NS_SEPARATOR + T_STRING, so the leading separator has to be stripped or
            // a fully qualified global call goes unseen.
            $name = ltrim($t[1], '\\');

            if (is_cs_symbol($name)
                && next_significant($tokens, $i) === '('
                && !(is_array($prevSig) && in_array(
                    $prevSig[0],
                    [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON],
                    true
                ))) {
                // A call, not a definition or a method. `\cs__x()` is included on
                // purpose: a leading separator means the global function, which is
                // exactly what this check is about.
                $reported[] = [$t[2], $name, 'call'];
            }
        }

        $prevSig = $t;
    }

    return ['defined' => array_keys($defined), 'reported' => $reported];
}

// --- driver ------------------------------------------------------------------

$root = rtrim($argv[1] ?? '.', "/\\");
if (!is_dir($root)) {
    fwrite(STDERR, "not a directory: $root\n");
    exit(2);
}

$files = theme_files($root);

$defined = [];
$perFile = [];
foreach ($files as [$abs, $rel]) {
    $result = analyze((string) file_get_contents($abs));
    $perFile[$rel] = $result['reported'];
    foreach ($result['defined'] as $name) {
        $defined[$name] = true;
    }
}

foreach ($perFile as $rel => $reported) {
    foreach ($reported as [$line, $symbol, $kind]) {
        if (isset($defined[$symbol])) {
            continue;
        }
        echo json_encode(
            ['file' => $rel, 'line' => $line, 'symbol' => $symbol, 'kind' => $kind],
            JSON_UNESCAPED_SLASHES
        ), "\n";
    }
}
```

**`scripts/check-theme-stand.py`** — the gate itself. It orchestrates the five checks and owns the exit code.

```python
#!/usr/bin/env python3
"""Theme stand check: is this theme deliverable?

Usage: python scripts/check-theme-stand.py [theme-dir]
Exit code 0 = all checks passed, 1 = at least one failed.
"""

import json
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path


def find_php() -> str:
    """Locate the PHP CLI, in order: $CSWP_PHP, PATH, then this dev machine's Local install.

    This script ships inside the theme and the client's team is expected to run it,
    so a hard-coded path to one developer's machine must not be the only way to
    find PHP. The Local path stays as the last fallback so the owner's own
    environment keeps working without any setup.
    """
    candidates = [
        os.environ.get("CSWP_PHP"),
        shutil.which("php"),
        r"C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe",
    ]
    for candidate in candidates:
        if candidate and Path(candidate).exists():
            return candidate
    return "php"  # nothing found: let the syntax check fail loudly rather than silently


PHP = find_php()

SKIP_DIRS = {"node_modules", ".git", "vendor", "build"}

# A check returns this when it could not run at all -- no git on the machine, or the
# directory is not a working tree. It is reported as SKIP and does not fail the gate:
# claiming a pass would be a lie, and failing would be a false alarm on a legitimate
# use such as a delivered copy that is not a git checkout.
SKIP = object()


def php_files(theme: Path):
    for p in theme.rglob("*.php"):
        if any(part in SKIP_DIRS for part in p.parts):
            continue
        yield p


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="replace")


def _display(where: Path, theme: Path) -> str:
    """Show a finding's file relative to the theme when it lives there.

    The basename alone is ambiguous: a theme with two `render.php` files reports two
    findings that read identically, and whoever triages them cannot tell which file
    to open. A finding about the theme itself prints the theme's name rather than `.`.
    """
    try:
        rel = where.relative_to(theme)
    except ValueError:
        return str(where)
    return theme.name if str(rel) == "." else str(rel)


# --- checks -----------------------------------------------------------------

def check_php_syntax(theme: Path):
    bad = []
    for f in php_files(theme):
        r = subprocess.run([PHP, "-l", str(f)], capture_output=True, text=True)
        if r.returncode != 0:
            bad.append((f, r.stdout.strip() or r.stderr.strip()))
    return bad


def check_block_json(theme: Path):
    problems = []
    for bj in sorted(theme.glob("parts/block/*/block.json")):
        try:
            data = json.loads(read(bj))
        except json.JSONDecodeError as e:
            problems.append((bj, f"invalid JSON: {e}"))
            continue
        for key in ("name", "title", "category", "apiVersion"):
            if key not in data:
                problems.append((bj, f"missing {key!r}"))
        if data.get("apiVersion") != 3:
            problems.append((bj, f"apiVersion is {data.get('apiVersion')!r}, expected 3"))
        for key in ("style", "script", "editorStyle"):
            ref = data.get(key)
            if isinstance(ref, str) and ref.startswith("file:"):
                target = bj.parent / ref[len("file:"):].lstrip("./")
                if not target.exists():
                    problems.append((bj, f"{key} points at missing {target.name}"))
    return problems


def check_symbols(theme: Path):
    """Every cs__ symbol used by a template must be defined in the theme.

    The analysis is delegated to `scripts/check-symbols.php`, which tokenizes rather
    than pattern-matches. PHP's block structure is neither indentation-based nor
    line-based, so whether a call sits inside a `function_exists()` guard cannot be
    decided by scanning text: three rounds of regex heuristics each missed new
    spellings (an over-indented closer, a tab/space mix, `<?php } ?>`, a closer behind
    a comment, a one-line guard) and introduced false alarms of their own. Tokens do
    not guess. See that file for the reasoning.

    This is the check that would have caught the v3 fatal:
    Class "cs__primary_menu_walker" not found.
    """
    helper = Path(__file__).resolve().parent / "check-symbols.php"
    if not helper.exists():
        return [(theme, f"symbol analysis helper is missing: {helper}")]
    try:
        r = subprocess.run(
            [PHP, str(helper), str(theme)], capture_output=True, text=True
        )
    except FileNotFoundError:
        return SKIP  # no PHP on this machine; the syntax check reports the same
    if r.returncode != 0:
        return [(theme, f"symbol analysis failed: {r.stderr.strip() or r.returncode}")]
    problems = []
    for line in r.stdout.splitlines():
        if not line.strip():
            continue
        try:
            found = json.loads(line)
        except json.JSONDecodeError:
            problems.append((theme, f"symbol analysis emitted junk: {line.strip()}"))
            continue
        symbol = found.get("symbol")
        shown = f"new {symbol}()" if found.get("kind") == "new" else f"{symbol}()"
        problems.append((theme / found["file"], f"line {found['line']}: {shown}"))
    return problems


def check_escaping(theme: Path):
    problems = []
    pattern = re.compile(r"<\?=\s*\$[A-Za-z_]")
    for f in php_files(theme):
        for i, line in enumerate(read(f).splitlines(), 1):
            if pattern.search(line) and "esc_" not in line and "wp_kses" not in line:
                problems.append((f, f"line {i}: unescaped output"))
    return problems


def check_build_artifacts(theme: Path):
    try:
        r = subprocess.run(
            ["git", "-C", str(theme), "ls-files"], capture_output=True, text=True
        )
    except FileNotFoundError:
        return SKIP  # no git on this machine
    if r.returncode != 0:
        return SKIP  # not a git working tree
    bad = [
        line for line in r.stdout.splitlines()
        if line.endswith((".min.css", ".min.js", ".map"))
    ]
    return [(theme, f"tracked build artifact: {p}") for p in bad]


CHECKS = [
    ("PHP syntax", check_php_syntax),
    ("block.json validity", check_block_json),
    ("symbol resolution", check_symbols),
    ("output escaping", check_escaping),
    ("build artifacts not tracked", check_build_artifacts),
]


def main():
    theme = Path(sys.argv[1] if len(sys.argv) > 1 else os.getcwd()).resolve()
    print(f"Stand check: {theme}\n")

    # Without this guard a typo, a wrong cwd or a failed checkout iterates an empty
    # tree, every check finds nothing wrong, and the gate reports success over nothing.
    if not theme.is_dir():
        print(f"FAIL  theme directory not found: {theme}")
        return 1

    failed = 0
    for name, fn in CHECKS:
        try:
            problems = fn(theme)
        except Exception as e:  # a crashing check is a failing check
            problems = [(theme, f"check raised {type(e).__name__}: {e}")]
        if problems is SKIP:
            print(f"SKIP  {name}  (could not run here)")
            continue
        if problems:
            failed += 1
            print(f"FAIL  {name}  ({len(problems)})")
            for where, what in problems[:20]:
                print(f"        {_display(where, theme)}: {what}")
            if len(problems) > 20:
                print(f"        … and {len(problems) - 20} more")
        else:
            print(f"PASS  {name}")

    print()
    if failed:
        print(f"{failed} check(s) failed.")
        return 1
    print("All checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
```

- [ ] **Step 3: Run it against v3 — it must fail**

```bash
python scripts/check-theme-stand.py "D:/Local/starter-theme/app/public/wp-content/themes/cs_w_000_starter-v3"; echo "exit=$?"
```

Expected: FAIL with exit 1, and the `symbol resolution` check reporting `header.php: line 42: new cs__primary_menu_walker()`.

If the symbol check does **not** report it, the check is wrong — fix the check, not the expectation. `php -l` passing on that same file (verified) is what makes this check the load-bearing one.

**Assert the specific line, not the exit code.** v3 fails four of the five checks, not one (measured: `block.json validity` 4 problems, `symbol resolution` 2 — both call sites, `output escaping` 10, `build artifacts not tracked` 8). So `exit=1` proves almost nothing on its own — a stand script that had simply been broken in a way that always exits 1 would satisfy it. The oracle is only meaningful when `new cs__primary_menu_walker()` appears in the output, because that is the check `php -l` provably cannot make.

- [ ] **Step 3b: Prove the gate cannot pass over nothing**

A quality gate that reports success when it has checked nothing is worse than no gate. Both of these caught a real false-pass path during review and must hold:

```bash
# (a) a nonexistent directory must FAIL, not pass over an empty tree
python scripts/check-theme-stand.py "D:/no-such-theme-xyz"; echo "exit=$?"     # expect 1

# (b) a directory that is not a git working tree must say so, not silently pass
git -C "<non-repo-dir>" ls-files; echo "git exit=$?"                           # expect 128
python scripts/check-theme-stand.py "<non-repo-dir>"; echo "exit=$?"           # expect SKIP, not PASS
```

For (b) the expected line is `SKIP  build artifacts not tracked  (could not run here)`. Skipping rather than failing is deliberate: claiming a pass would be a lie, but failing would raise a false alarm on a legitimate use — a copy of the theme that is not a git checkout.

**And the symbol check must be right in both directions.** Build each as a one-file scratch theme outside the repo and run the checker against it:

```bash
# --- must be REPORTED: the symbol is defined nowhere in the theme ---
#   cs__x();                                    a plain call
#   new cs__Widget();                           a class no file defines
#   \cs__x();                                   fully qualified -- the global function
#   if ( function_exists('cs__x') ) {           guarded, and still reported -- see below
#       cs__x();
#   }
#   if ( function_exists('cs__x') ) cs__x();    the brace-less form, same rule
#   <?php if ( function_exists('cs__x') ) : ?>  the alt-template form, same rule
#   <?php cs__x(); ?><?php endif; ?>
#   add_action('init', 'cs__x');                a string callback -- the dominant form
#   call_user_func('cs__x');                    in a WordPress theme, and a real call
#   $s = 'cs__x';                               a bare string that is only a cs__ name

# --- must stay SILENT: the symbol resolves, or it is not a call at all ---
#   function cs__x() {}    cs__x();             defined in the same file
#   function &cs__x() {}   cs__x();             defined by reference
#   class cs__Widget {}    new cs__Widget();    the class exists
#   a.php: cs__x();        b.php: function cs__x() {}   defined in a different file
#   $o->cs__m();                                a method is not a global function
#   Foo::cs__m();                               nor is a static method
#   /** @see cs__x() for the old API */         a docblock is not a call site
#   $s = <<<TXT ... cs__x() ... TXT;            nor is a heredoc
#   $s = 'cs__x()';                             parens: a mention, not a bare name
#   $s = 'cs__Class::method';                   a method callback, not a bare name
#   $s = 'notcs__x';                            the prefix has to start the string
#   define('CS_VERSION', '4.0.0');               CS_ is the constant shape, not a
#   $s = 'CS_SETTINGS';                         callback shape: only a cs__ string is
#                                               treated as a callback. Calls and `new`
#                                               still check both prefixes, so an
#                                               undefined CS_foo() is still reported.
```

**A call site has exactly three forms, and all three are lexical.** This matters, because the one thing this task learned the hard way is that *flow* analysis leaks and *lexical* rules do not. There is no flow question in any of them — a token either is one of these or it is not:

1. a plain call, `cs__x(`, including a fully qualified `\cs__x(`
2. a class construction, `new cs__Widget`
3. a **string callback** — a string literal whose entire content is a `cs__`/`CS_` name, as in `add_action('init', 'cs__foo')`

Form 3 is not a nicety. It is the *dominant* form in a WordPress theme: the v4 theme already passes **thirteen** such strings to `add_action`/`add_filter`, and v3 passes thirty. WordPress calls the named function at runtime and fatals if it does not exist, so leaving this form out would leave the most common call site in the codebase unchecked — which is precisely the missed-fatal failure this check exists to prevent. It costs nothing in noise today: all thirteen in v4 resolve.

**A `function_exists()` test is deliberately not an exemption.** This is the one rule in this task that was arrived at by failing, and it is worth stating plainly so that nobody re-adds it.

Six review rounds tried to prove statically that a guarded call is safe. Each replacement leaked somewhere new: an over-indented closer, a tab/space mix, `<?php } ?>`, a closer behind a comment, a one-line guard, a brace-less body, `&&` versus `||`, a ternary consequent, a guard in a `for` header, an alt-form `else:` branch, a test nested inside an enclosing group. **Every one of those leaks was a missed fatal**, in code that reads as ordinary in a WordPress template — and a missed fatal is the failure this check exists for: `Class "cs__primary_menu_walker" not found` is what took the v3 theme down. The final round alone found five new miss paths, which is what settled it.

So the rule is flat: **report every `cs__` call whose symbol is defined nowhere in the theme, guard or no guard.** The asymmetry decides it — a false alarm costs one line of noise, a missed fatal costs the site. A theme that genuinely calls into another theme or plugin should say so at the call site (`class_exists()` / `function_exists()`) or ship a stub, rather than ask a scanner to prove it on its behalf.
- [ ] **Step 4: Run it against v4 — it must pass**

```bash
python scripts/check-theme-stand.py; echo "exit=$?"
```

Expected: PASS, exit 0.

- [ ] **Step 5: Add the npm script**

Add `"stand": "python scripts/check-theme-stand.py"` to `package.json` scripts.

- [ ] **Step 6: Commit**

```bash
git add scripts/check-theme-stand.py scripts/check-symbols.php package.json
git commit -m "feat: stand check script with v3 as its failing oracle"
```

---

## Task 5: Block system — skeleton and generator

**Files:**
- Create: `parts/block/_skeleton/block.json`, `callback.php`, `render.php`, `style.scss`, `editor.scss`
- Create: `scripts/make-block.mjs`
- Modify: `package.json` (`make:block` script)
- Modify: `gulpfile.js` — exclude `_skeleton` from the block SCSS glob (Step 6b)
- Modify: `scripts/check-theme-stand.py` — the same exclusion in its two globs (Step 6b)

**Interfaces:**
- Consumes: `cs__get_block_id()`, `cs__get_block_classes()`, `CS_Block_Styles` (Task 6).
- Produces: `npm run make:block <slug> "<Title>"` → creates `parts/block/<slug>/` with all five files, placeholders replaced, and a `cs__render_<slug>_block` callback name. Later phases generate every block this way.

- [ ] **Step 1: Write the failing test**

```bash
npm run make:block demo "Demo Block"
```

Expected: FAIL — `npm ERR! Missing script: "make:block"`

- [ ] **Step 2: Write `parts/block/_skeleton/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "cs/{{SLUG}}",
	"title": "{{TITLE}}",
	"description": "",
	"category": "cs-blocks",
	"icon": "block-default",
	"keywords": [],
	"acf": {
		"mode": "auto",
		"postTypes": ["page"],
		"renderCallback": "cs__render_{{FUNC}}_block"
	},
	"style": "file:./style.min.css",
	"script": "",
	"editorStyle": "file:./editor.min.css",
	"supports": {
		"align": ["none", "wide", "full"],
		"alignContent": false,
		"alignText": true,
		"anchor": true,
		"html": false,
		"multiple": true,
		"color": {"background": true, "link": false, "text": false},
		"spacing": {"blockGap": false, "margin": ["top", "bottom"], "padding": ["top", "bottom"]}
	},
	"attributes": {
		"align": {"type": "string", "default": "none"},
		"alignText": {"type": "string", "default": "none"}
	},
	"example": {
		"attributes": {
			"mode": "preview",
			"data": {
				"heading": "{{TITLE}}",
				"content": "Block preview content."
			}
		}
	}
}
```

- [ ] **Step 3: Write `parts/block/_skeleton/callback.php`**

```php
<?php
/**
 * Block: {{TITLE}}
 * Render callback
 */

function cs__render_{{FUNC}}_block( $block, $content = '', $is_preview = false, $post_id = 0 ){
	$block_data = array(
		'heading' => cs__get_block_field('heading'),
		'content' => cs__get_block_field('content'),
		'block'   => $block,
	);

	set_query_var('block_data', $block_data);

	$template = __DIR__ .'/render.php';

	if ( file_exists($template) ){
		include $template;
	}
}
```

- [ ] **Step 4: Write `parts/block/_skeleton/render.php`**

```php
<?php
/**
 * Block: {{TITLE}}
 */

$data = get_query_var('block_data');

if ( ! $data ){
	return;
}

$heading = $data['heading'] ?? '';
$content = $data['content'] ?? '';
$block   = $data['block'] ?? array();

if ( $heading === '' && $content === '' ){
	return;
}
?>

<section
	id="<?= esc_attr(cs__get_block_id($block)); ?>"
	class="<?= esc_attr(cs__get_block_classes($block, 'block-{{SLUG}}')); ?>"
>
	<div class="block-{{SLUG}}__container container">
		<?php if ( $heading !== '' ): ?>
			<h2 class="block-{{SLUG}}__heading"><?= esc_html($heading); ?></h2>
		<?php endif; ?>

		<?php if ( $content !== '' ): ?>
			<div class="block-{{SLUG}}__content"><?= wp_kses_post($content); ?></div>
		<?php endif; ?>
	</div>
</section>
```

- [ ] **Step 5: Write `parts/block/_skeleton/style.scss`**

```scss
/**
 * Block: {{TITLE}}
 */

@import '../../../assets/scss/abstracts/functions';
@import '../../../assets/scss/abstracts/variables';
@import '../../../assets/scss/abstracts/mixins';

.block-{{SLUG}} {
	$b: &;

	&__container {
		position: relative;
		z-index: 1;
	}
	&__heading {
		margin: 0 0 $layout_block_gap;
	}
	&__content {
		margin: 0;
	}

	// Modifiers
	&.alignfull {
		padding-inline: $layout_block_gap;

		#{$b}__container {
			margin-inline: auto;
			max-width: $layout_content;
		}
	}
	&.has-text-align-center {
		text-align: center;
	}

	// States

	// Frontend only styles
	body:not(.wp-admin) & {
	}
}
```

**Two traps in that stylesheet, both measured.** The `$layout_*` names come from `theme.json` through `scripts/build-tokens.mjs`, which normalises a camelCase theme.json key into a snake_case SCSS variable: `block-gap` becomes **`$layout_block_gap`**, not `$layout_blockGap`. The Arosa reference theme — where this convention was copied from — uses `$layout_blockGap`, and that spelling does not exist here: `npm run build` reports `Undefined variable. $layout_blockGap` and emits no stylesheet. It stays invisible until the `{{SLUG}}` parse error is out of the way, because that error aborts the stream before variable resolution happens at all — so fixing one defect uncovers the next.

Run `npm run tokens` and read `assets/scss/abstracts/_tokens.scss` before inventing a token name. It is generated, gitignored, and the only authority on what exists; the same trap was already sitting in Task 6's `cta` stylesheet, five occurrences of it.

- [ ] **Step 6: Write `parts/block/_skeleton/editor.scss`**

```scss
/**
 * Block: {{TITLE}} — editor only
 */

.block-{{SLUG}} {
}
```

- [ ] **Step 6b: Stop `_skeleton` from breaking the two globs it lands in**

The skeleton's SCSS carries `{{SLUG}}`, which is not valid SCSS, and two things glob `parts/block/**` without excluding it. Both failures were measured, not reasoned about:

```bash
npx sass parts/block/_skeleton/style.scss NUL
# Error: expected end of rule.  ->  .block-{{SLUG}} {
```

`compileBlockSass()` in `gulpfile.js` globs `parts/block/**/*.scss` and `_skeleton` sorts first, so that single parse error **aborts the whole gulp-sass stream**. `npm run build` then exits **0** — `.on('error', sass.logError)` logs without failing the task — while producing **no block stylesheet at all**. That is a silent failure of the worst kind: every block ships without CSS and the build reports success. Confirm it on the current state: after `npm run build`, `parts/block/<slug>/` holds the five source files and no `.min.css`.

Fix the glob so it skips underscore-prefixed folders, which is exactly what `cs__get_blocks()` already does for registration (`_skeleton`, `_base-block`):

```js
const files = glob.sync('parts/block/**/*.scss', { ignore: 'parts/block/_*/**' });
```

**Use the `ignore` option, not a negated pattern.** This glob is version 11, and every negated form was tried and measured against it — `['parts/block/**/*.scss', '!parts/block/_*/**']`, `'!parts/block/_*/style.scss'`, `['parts/block/*/*.scss', '!parts/block/_*/*.scss']` — and **none of them excludes anything**; all four return `_skeleton`'s files alongside the real blocks. `{ ignore: 'parts/block/_*/**' }` does exclude them, while `{ ignore: 'parts/block/_*' }` does not. A fix that looks right and silently does nothing is the exact failure this step exists to undo, so check the returned list, not the exit code.

Then `scripts/check-theme-stand.py` needs the same exclusion in its two globs. `php_files()` lints `_skeleton/callback.php` and hits a parse error from `{{FUNC}}`; `check_block_json()` matches `_skeleton/block.json`, which is a template rather than a block:

```python
def php_files(theme: Path):
    for p in theme.rglob("*.php"):
        if any(part in SKIP_DIRS for part in p.parts):
            continue
        # An underscore-prefixed folder is a template or a scratch block: `_skeleton`
        # carries {{FUNC}} placeholders and is not parseable PHP. `cs__get_blocks()`
        # excludes the same folders from registration.
        if any(part.startswith("_") for part in p.relative_to(theme).parts):
            continue
        yield p
```

```python
    for bj in sorted(theme.glob("parts/block/*/block.json")):
        # `_skeleton` is the template every block is generated from, not a block: it
        # holds {{SLUG}} placeholders and has no compiled assets to point at.
        if bj.parent.name.startswith("_"):
            continue
```

```bash
npm run build; echo "build exit=$?"
ls -1 parts/block/<slug>/          # must now include style.min.css and editor.min.css
npm run stand; echo "stand exit=$?"
```

Expected: the generated block's folder gains `style.min.css`, `style.min.css.map`, `editor.min.css` and `editor.min.css.map`; `_skeleton` gains none; the compiled CSS contains the block's own selectors (`block-<slug>`, `block-<slug>__container`, …) rather than the placeholders; and `npm run stand` is **0**.

Before this step `npm run stand` is **1** — `FAIL PHP syntax (1)` and `FAIL block.json validity (4)` — while `npm run build` exits 0 and produces no block stylesheet at all. That pair is the whole point: **the build's exit code is not evidence the build worked.** Read the file list.

The full chain was verified end to end before this step was written, with all three fixes applied and then reverted: build exit 0; `tmpblk/` holding all four compiled files; `_skeleton/` holding its five source files and nothing else; `block-tmpblk__heading` present in the compiled CSS; `npm run stand` at **5 PASS, exit 0**. The live site answered **200** and added exactly one line to the log — the pre-existing `WP_DEBUG` warning — with zero lines mentioning the theme.

- [ ] **Step 7: Write `scripts/make-block.mjs`**

```js
import fs from 'node:fs';
import path from 'node:path';

const [slug, title] = process.argv.slice(2);

if ( ! slug || ! /^[a-z][a-z0-9-]*$/.test(slug) ){
	console.error('Usage: npm run make:block <slug> "<Title>"');
	console.error('  slug: lowercase letters, digits and hyphens, starting with a letter');
	process.exit(1);
}

const root   = process.cwd();
const source = path.join(root, 'parts/block/_skeleton');
const target = path.join(root, 'parts/block', slug);

if ( ! fs.existsSync(source) ){
	console.error(`Missing skeleton at ${source}`);
	process.exit(1);
}
if ( fs.existsSync(target) ){
	console.error(`parts/block/${slug} already exists`);
	process.exit(1);
}

const displayTitle = title || slug.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
const func = slug.replace(/-/g, '_');

const replacements = {
	'{{SLUG}}':  slug,
	'{{TITLE}}': displayTitle,
	'{{FUNC}}':  func,
};

fs.mkdirSync(target, { recursive: true });

// Copy sources only. A build leaves style.min.css, editor.min.css and their
// source maps inside _skeleton, and copying those would hand every new block a
// stale compiled stylesheet built from the placeholder SCSS. This is reachable
// from the second block onwards, not hypothetically.
const COMPILED = /\.min\.(css|js)$|\.min\.(css|js)\.map$/;

for ( const name of fs.readdirSync(source) ){
	if ( COMPILED.test(name) ){
		continue;
	}
	const src = fs.readFileSync(path.join(source, name), 'utf8');
	const out = Object.entries(replacements)
		.reduce((acc, [from, to]) => acc.split(from).join(to), src);
	fs.writeFileSync(path.join(target, name), out, 'utf8');
}

console.log(`Created parts/block/${slug}/`);
console.log(`  block name:      cs/${slug}`);
console.log(`  render callback: cs__render_${func}_block`);
console.log('');
console.log('Next: npm run build   (compiles style.min.css and editor.min.css)');
```

- [ ] **Step 8: Add the npm script and run it**

```json
"make:block": "node scripts/make-block.mjs"
```

```bash
npm run make:block demo "Demo Block"
ls -1 parts/block/demo/
grep -n "cs/demo\|cs__render_demo_block\|block-demo" parts/block/demo/block.json parts/block/demo/callback.php parts/block/demo/render.php | head
```

Expected: PASS — five files created, no `{{…}}` placeholder left anywhere:

```bash
grep -rn "{{" parts/block/demo/ || echo "no placeholders left"
```

- [ ] **Step 9: The loader must skip an incomplete folder without a fatal**

**This step was unreachable during Task 2 and is reachable now:** the active theme is v4 and the site serves it. The owner switches the active theme by hand, and did.

```bash
# (a) the build must survive an incomplete folder
mkdir -p parts/block/half-made
npm run build; echo "build exit=$?"

# (b) the status code alone proves nothing -- WordPress serves cached and partial
#     pages happily with warnings in the log -- so the log is the oracle. It DOES
#     grow by one line per request, from a pre-existing wp-config.php bug that has
#     nothing to do with this theme: WP_DEBUG is defined guarded at line 90 and
#     again unguarded at line 97, so every request warns "Constant WP_DEBUG already
#     defined". It had logged 1987 of those before this task began. That is not
#     this task's to fix; the assertion is that the growth is exactly that one known
#     line, and that nothing added mentions the theme.
LOG="/d/Local/starter-theme/logs/php/error.log"
before=$(wc -l < "$LOG")
code=$(curl -k -s -o NUL -w "%{http_code}" https://starter-theme.local)
after=$(wc -l < "$LOG")
rmdir parts/block/half-made
echo "code=$code  log grew by $((after - before))"
tail -n $((after - before)) "$LOG" | grep -i "cs_w_000_starter-v4" \
  && echo "THEME-RELATED LINE ADDED" || echo "no theme-related line"

# (c) a second generation must still produce five source files. Plant the artifacts
#     first: this is the regression test for the generator's compiled-artifact filter,
#     and the filter has to hold whether or not a build happens to leave them there.
touch parts/block/_skeleton/style.min.css parts/block/_skeleton/style.min.css.map
touch parts/block/_skeleton/editor.min.css parts/block/_skeleton/editor.min.css.map
npm run make:block second "Second Block"
ls -1 parts/block/second/
rm -rf parts/block/second
rm -f parts/block/_skeleton/*.min.css parts/block/_skeleton/*.map
```

Expected: `build exit=0`; `code=200` with the log growing by exactly **1** line, that line being the pre-existing `WP_DEBUG` warning and not one mentioning the theme; and `parts/block/second/` holding **exactly five** entries — `block.json`, `callback.php`, `editor.scss`, `render.php`, `style.scss`.

(c) guards the generator's compiled-artifact filter, and what changed here is worth stating plainly: the filter was written against a premise that turned out to be **false**. The build never leaves output in `_skeleton` — `_skeleton`'s own SCSS fails to parse and aborts the stream, which is Step 6b's defect and worse than assumed. Once Step 6b excludes `_skeleton` from the glob, the folder still never accumulates compiled output, so the filter guards nothing today. (c) therefore plants the artifacts itself rather than waiting for a build to create them.

The filter stays anyway, and not as "defence in depth": the glob lives in `gulpfile.js` and the generator in `scripts/make-block.mjs`, and a generator that copies whatever it happens to find in a directory is wrong on its own terms regardless of what is currently there. That is a judgement call, recorded as one — not a defect found, and not a reason to keep piling on.

Note what is deliberately *not* asserted here: that WordPress registered the demo block. The REST route for block types requires `edit_posts` and answers `401` unauthenticated, and Local's PHP CLI cannot bootstrap WordPress without `mysqli` wired up by hand. End-to-end registration is Task 6's job, where a real page renders a real block; Task 5's offline oracle is `npm run stand`, which validates every `block.json` it finds — including the generated one.

- [ ] **Step 10: Remove the demo block and commit**

```bash
rm -rf parts/block/demo
git add parts/block/_skeleton scripts/make-block.mjs package.json
git status --short          # read it: the staged set must be exactly those paths
git commit -m "feat: block skeleton and generator"
```

**Never `git add -A` in this repository.** It has already dragged `.hermes-tmp.*/` and `.superpowers/` into a commit here. Stage by path, then read `git status --short` before committing. Note that `package-lock.json` is not in the staged set: adding an npm script does not touch the lock file, so if it shows up as modified, something else changed it — stop and report that rather than staging it.

---

## Task 6: Reference block `cta`, end to end

Proves the whole chain: contract, field access, `CS_Block_Styles`, per-page asset loading, and the four Review Focus failure modes that touch block rendering.

**Files:**
- Create: `parts/block/cta/block.json`, `callback.php`, `render.php`, `style.scss`, `editor.scss`
- Create: `inc/class-block-styles.php`
- Create: `acf-json/group_part_block_content.json`, `acf-json/group_part_button_group.json`
- Modify: `inc/helper-functions.php` (add `cs__render_link_group()`)

**Interfaces:**
- Consumes: `cs__get_block_id()`, `cs__get_block_classes()`, `cs__render_link_group()`, `CS_Block_Styles::get_styles()`.
- Produces: `CS_Block_Styles::get_styles( $block )` → `'style="…"'` or `''`; `cs__render_link_group( $buttons, $modifier = '' )` → button markup. Phases 2–3 use both.

- [ ] **Step 1: Write the failing test**

```bash
curl -k -s https://starter-theme.local/ | grep -c "block-cta"
```

Expected: `0` — the block does not exist yet.

- [ ] **Step 2: Write `inc/class-block-styles.php`**

Port `CS_Block_Styles` from `arosa/inc/class-block-styles.php` (260 lines) unchanged, including the `cs__get_block_styles( $block )` wrapper. It converts the standard Gutenberg `style` attribute array (spacing, typography, colour, dimensions, border) into an inline `style="…"` string, translating `var:preset|color|slug` into `var(--wp--preset--color--slug)`.

Add it to the always-on includes in `functions.php`:

```php
require_once 'inc/class-block-styles.php';
```

- [ ] **Step 3: Add `cs__render_link_group()` to `inc/helper-functions.php`**

```php
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
				href="<?= esc_url($url); ?>"
				<?= $target ? 'target="'. esc_attr($target) .'" rel="noopener noreferrer"' : ''; ?>
			><?= esc_html($title); ?></a>
		<?php endforeach; ?>
	</div>
	<?php
}
```

- [ ] **Step 4: Write `parts/block/cta/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "cs/cta",
	"title": "Call to Action",
	"description": "A heading, optional text and a group of buttons.",
	"category": "cs-blocks",
	"icon": "megaphone",
	"keywords": ["cta", "call to action", "button"],
	"acf": {
		"mode": "auto",
		"postTypes": ["page"],
		"renderCallback": "cs__render_cta_block"
	},
	"style": "file:./style.min.css",
	"script": "",
	"editorStyle": "file:./editor.min.css",
	"supports": {
		"align": ["none", "full"],
		"alignContent": false,
		"alignText": true,
		"anchor": true,
		"html": false,
		"multiple": true,
		"color": {"background": true, "link": false, "text": false},
		"spacing": {"blockGap": false, "margin": ["top", "bottom"], "padding": ["top", "bottom"]}
	},
	"attributes": {
		"align": {"type": "string", "default": "full"},
		"alignText": {"type": "string", "default": "center"},
		"style": {
			"type": "object",
			"default": {
				"spacing": {
					"padding": {"top": "var:preset|spacing|40", "bottom": "var:preset|spacing|40"}
				}
			}
		}
	},
	"example": {
		"attributes": {
			"mode": "preview",
			"data": {
				"heading": "Ready to get started?",
				"content": "Get in touch to learn more.",
				"buttons": [{"link": {"title": "Contact us", "url": "#"}, "link_type": "button"}]
			}
		}
	}
}
```

- [ ] **Step 5: Write `parts/block/cta/callback.php`**

```php
<?php
/**
 * Block: Call to Action
 * Render callback
 */

function cs__render_cta_block( $block, $content = '', $is_preview = false, $post_id = 0 ){
	$block_data = array(
		'eyebrow'    => cs__get_block_field('eyebrow'),
		'heading'    => cs__get_block_field('heading'),
		'subheading' => cs__get_block_field('subheading'),
		'content'    => cs__get_block_field('content'),
		'buttons'    => cs__get_block_field('buttons'),
		'block'      => $block,
	);

	set_query_var('block_data', $block_data);

	$template = __DIR__ .'/render.php';

	if ( file_exists($template) ){
		include $template;
	}
}
```

- [ ] **Step 6: Write `parts/block/cta/render.php`**

```php
<?php
/**
 * Block: Call to Action
 */

$data = get_query_var('block_data');

if ( ! $data ){
	return;
}

$eyebrow    = $data['eyebrow'] ?? '';
$heading    = $data['heading'] ?? '';
$subheading = $data['subheading'] ?? '';
$content    = $data['content'] ?? '';
$buttons    = $data['buttons'] ?? array();
$block      = $data['block'] ?? array();

if ( $heading === '' && $content === '' && empty($buttons) ){
	return;
}

$styles = function_exists('cs__get_block_styles') ? cs__get_block_styles($block) : '';
?>

<section
	id="<?= esc_attr(cs__get_block_id($block)); ?>"
	class="<?= esc_attr(cs__get_block_classes($block, 'block-cta')); ?>"
	<?= $styles; ?>
>
	<div class="block-cta__container container">
		<?php if ( $eyebrow !== '' ): ?>
			<p class="block-cta__eyebrow"><?= esc_html($eyebrow); ?></p>
		<?php endif; ?>

		<?php if ( $heading !== '' ): ?>
			<h2 class="block-cta__heading"><?= esc_html($heading); ?></h2>
		<?php endif; ?>

		<?php if ( $subheading !== '' ): ?>
			<p class="block-cta__subheading"><?= esc_html($subheading); ?></p>
		<?php endif; ?>

		<?php if ( $content !== '' ): ?>
			<div class="block-cta__content"><?= wp_kses_post($content); ?></div>
		<?php endif; ?>

		<?php cs__render_link_group($buttons, 'block-cta__button-wrapper'); ?>
	</div>
</section>
```

The `function_exists` guard on `cs__get_block_styles` is what makes the block survive ACF Pro being deactivated and the class not loading.

- [ ] **Step 7: Write `parts/block/cta/style.scss`**

Follow the conventions exactly — elements in architectural order, `// Modifiers` then `// States` then `// Frontend only styles`, alphabetical properties, media queries inside the element.

```scss
/**
 * Block: Call to Action
 */

@import '../../../assets/scss/abstracts/functions';
@import '../../../assets/scss/abstracts/variables';
@import '../../../assets/scss/abstracts/mixins';

.block-cta {
	$b: &;

	&__container {
		position: relative;
		z-index: 1;
	}
	&__eyebrow {
		margin: 0 0 $layout_block_gap;
	}
	&__heading {
		margin: 0 0 calc($layout_padding * 1.5);

		@include mediaMaxWidth( md ){
			margin: 0 0 $layout_padding;
		}
	}
	&__subheading {
		margin: 0 0 $layout_block_gap;
	}
	&__subheading,
	&__content {
		max-width: remc(736);
	}
	&__content {
		margin: 0;
	}
	&__button-wrapper {
		align-items: center;
		display: flex;
		flex-flow: row wrap;
		gap: $layout_block_gap;
		margin: calc($layout_padding * 3) 0 0;
	}

	// Modifiers
	&.alignfull {
		padding-inline: $layout_block_gap;

		#{$b}__container {
			margin-inline: auto;
			max-width: $layout_content;
		}
	}
	&.has-background {
		&:not(.alignwide):not(.alignfull) {
			border-radius: $borderRadius_medium;
			padding: calc($layout_padding * 3) $layout_block_gap;
		}
	}
	&.has-text-align-center {
		#{$b}__subheading,
		#{$b}__content {
			margin-left: auto;
			margin-right: auto;
		}
		#{$b}__button-wrapper {
			justify-content: center;
		}
	}
	&.has-text-align-right {
		#{$b}__subheading,
		#{$b}__content {
			margin-left: auto;
		}
		#{$b}__button-wrapper {
			justify-content: flex-end;
		}
	}

	// States

	// Frontend only styles
	body:not(.wp-admin) & {
	}
}
```

- [ ] **Step 8: Write `parts/block/cta/editor.scss`**

```scss
/**
 * Block: Call to Action — editor only
 */

.block-cta {
	&__button-wrapper {
		// buttons are not clickable in the editor
		pointer-events: none;
	}
}
```

- [ ] **Step 9: Write the two optional clone field groups**

`acf-json/group_part_block_content.json` — title `Part: Block Content`, fields `eyebrow` (text), `heading` (text), `subheading` (text), `content` (wysiwyg), `location: [[{"param":"widget","operator":"==","value":"all"}]]`.

`acf-json/group_part_button_group.json` — title `Part: Button Group`, field `buttons` (repeater → `link` link, `link_type` select with `button` / `button-outlined` / `link-arrow`), same `widget:all` location.

Use the key format from `arosa/acf-json/group_*.json` so the shapes stay identical to what the ACF UI writes.

- [ ] **Step 10: Build and verify the block renders**

```bash
npm run build
ls -1 parts/block/cta/
curl -k -s "https://starter-theme.local/sample-page/" | grep -c "block-cta"
```

Add the block to a page in the editor, then:

Expected: `style.min.css` and `editor.min.css` exist; the page HTML contains `class="block-cta`.

- [ ] **Step 11: Verify assets load only on pages with the block**

```bash
# a page WITHOUT the block
curl -k -s "https://starter-theme.local/" | grep -c "parts/block/cta/style.min.css"
# a page WITH the block
curl -k -s "https://starter-theme.local/sample-page/" | grep -c "parts/block/cta/style.min.css"
```

Expected: `0` then `1`. If the first is non-zero, something is enqueuing globally — the loop in `cs__load_blocks()` is the usual culprit.

- [ ] **Step 12: Test the four failure modes**

```bash
# (1) unsynced field group: delete the local JSON, reload the page
mv acf-json/group_part_block_content.json /tmp/ && curl -k -s -o /dev/null -w "%{http_code}\n" "https://starter-theme.local/sample-page/" && mv /tmp/group_part_block_content.json acf-json/
```

Expected: `200`, and the block prints nothing (early return) rather than an empty `<section>` or a PHP notice.

```bash
# (2) block twice on one page: add a second cs/cta to the page, then
curl -k -s "https://starter-theme.local/sample-page/" | grep -o 'parts/block/cta/style.min.css' | wc -l
curl -k -s "https://starter-theme.local/sample-page/" | grep -o 'id="block-[a-z0-9]*"' | sort | uniq -d
```

Expected: `1` stylesheet reference, and no duplicate `id` (the second command prints nothing).

```bash
# (3) ACF deactivated
# deactivate ACF Pro in wp-admin, then
curl -k -s -o /dev/null -w "%{http_code}\n" "https://starter-theme.local/sample-page/"
tail -5 "/d/Local/starter-theme/logs/php/error.log"
# reactivate ACF Pro
```

Expected: `200` and no new fatal. The callbacks already route every field read through `cs__get_block_field()` (Task 2, Step 7), which returns `null` when ACF is inactive — so `render.php` sees empty values and early-returns without printing an empty wrapper. If this step produces a fatal, some callback is still calling `get_field()` directly; fix that call site.

- [ ] **Step 13: Commit**

```bash
git add -A
git commit -m "feat: cta reference block proving the full block contract"
```

---

## Task 7: The toolbox

Every commented include in `functions.php` gets a real, working file.

**Files:**
- Create: `inc/menu-walker.php`, `inc/breadcrumbs.php`, `inc/pagination.php`, `inc/shortcodes.php`, `inc/widgets.php`, `inc/post-types.php`, `inc/cpt-post.php`, `inc/admin.php`, `inc/customize.php`, `inc/plugin-acf.php`

**Interfaces:**
- Consumes: `DEFAULT_CPT_ARGS` and friends from `inc/constants.php`; `cs__get_template_page_ID()` from `inc/helper-functions.php`.
- Produces: `cs__primary_menu_walker`, `cs__footer_menu_walker`, `cs__the_breadcrumbs()`, `cs__the_pagination()`, `cs__register_post_types()`, `cs__register_taxonomies()`.

- [ ] **Step 1: Write the failing test**

```bash
grep -c "require_once 'inc/" functions.php
ls -1 inc/ | wc -l
```

Expected: the commented list names 10 files; `inc/` holds 6 (the always-on ones). The difference is the gap to close.

- [ ] **Step 2: Port `inc/menu-walker.php`**

Port `arosa/inc/menu-walker.php` (87 lines) — a `Walker_Nav_Menu` subclass emitting `.sub-menu`, `.menu-depth-N`, `.menu-item-trigger` and caret markup, plus a `cs__footer_menu_walker` variant if the arosa file has one. This is the file whose absence killed v3.

- [ ] **Step 3: Port `inc/breadcrumbs.php`, `inc/pagination.php`, `inc/post-types.php`, `inc/cpt-post.php`**

From `cs_w_000_starter-v3/inc/` and `arosa/inc/` — breadcrumbs (159 lines in v3), pagination, the declarative CPT/taxonomy registrar, and the Posts-CPT tweaks. Keep the `cs__` prefix and the `DEFAULT_*` constants.

- [ ] **Step 4: Write `inc/shortcodes.php` and `inc/widgets.php`**

Small, working, generic files: a `[cs-year]` shortcode returning the current year, and one registered sidebar `cs-sidebar` with `is_active_sidebar()` guards. Both must be genuinely usable, not stubs — a commented include is a promise.

- [ ] **Step 5: Port `inc/admin.php` and `inc/customize.php`**

`admin.php` from v3 (menu ordering, admin bar trimming). In `customize.php`, fix the two v3 defects: register the Twitter/X option under the key the social template actually reads, and drop the Customizer section that duplicates the ACF options page. Customizer is the primary settings surface in v4.

- [ ] **Step 6: Write `inc/plugin-acf.php`**

```php
<?php
/**
 * Plugin: Advanced Custom Fields
 */

/* --- ACF options page fallback. The Customizer is the primary settings surface. --- */
// if ( function_exists('acf_add_options_page') ):
// 	acf_add_options_page(array(
// 		'page_title' => __('Theme Settings', CSWP),
// 		'menu_title' => __('Theme Settings', CSWP),
// 		'menu_slug'  => 'theme-settings',
// 		'capability' => 'manage_options',
// 		'position'   => '59',
// 		'redirect'   => true,
// 	));
// endif;
```

`enable_post_types` is deliberately **not** disabled here — CPTs and taxonomies are registered through the ACF Pro UI (spec §6).

- [ ] **Step 7: Test every include uncomments cleanly**

```bash
PHP="C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe"
for f in menu-walker breadcrumbs pagination shortcodes widgets post-types cpt-post admin customize plugin-acf; do
  sed -i "s|// require_once 'inc/$f.php';|require_once 'inc/$f.php';|" functions.php
  "$PHP" -l functions.php >/dev/null || echo "SYNTAX FAIL: $f"
  curl -k -s -o /dev/null -w "$f: %{http_code}\n" https://starter-theme.local
  sed -i "s|require_once 'inc/$f.php';|// require_once 'inc/$f.php';|" functions.php
done
```

Expected: every line reports `200`, no `SYNTAX FAIL`. Then leave all of them commented again.

- [ ] **Step 8: Test the walker is actually wired**

```bash
grep -n "primary_menu_walker" header.php
```

Expected: no match — `header.php` uses the plain `wp_nav_menu` until this task. Add the walker to `header.php`'s two `wp_nav_menu` calls now that `inc/menu-walker.php` exists and the include is documented as required for it. Add a comment in `header.php` naming the include:

```php
// Requires: inc/menu-walker.php (uncomment its include in functions.php)
```

Then re-run the stand check:

```bash
python scripts/check-theme-stand.py; echo "exit=$?"
```

Expected: exit 0 — with the walker uncommented it resolves; the check only fails when a symbol is used but undefined **and** unguarded.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: toolbox files behind commented includes"
```

---

## Task 8: Base templates

**Files:**
- Create: `front-page.php`, `home.php`, `archive.php`, `category.php`, `tag.php`, `date.php`, `author.php`, `search.php`
- Create: `templates/_skeleton.php`
- Create: `parts/content/post-card.php`

**Interfaces:**
- Consumes: `cs__the_pagination()` (Task 7), `cs__the_breadcrumbs()`.
- Produces: the `parts/content/<type>-card.php` contract — each card accepts `$args = ['post_id' => int, 'modifier' => string]`. Phase 2's `card-list` depends on exactly this.

- [ ] **Step 1: Write the failing test**

```bash
for t in front-page home archive category tag date author search; do
  [ -f "$t.php" ] && echo "$t: present" || echo "$t: MISSING"
done
```

Expected: all `MISSING` except those created in Task 2.

- [ ] **Step 2: Write `parts/content/post-card.php`**

```php
<?php
/**
 * Card: Post
 *
 * @param array $args { post_id: int, modifier: string }
 */

$post_id  = $args['post_id'] ?? get_the_ID();
$modifier = $args['modifier'] ?? '';

$classes = 'card-post';
if ( $modifier !== '' ){
	$classes .= ' '. $modifier;
}
?>

<article class="<?= esc_attr($classes); ?>">
	<?php if ( has_post_thumbnail($post_id) ): ?>
		<a class="card-post__media" href="<?= esc_url(get_permalink($post_id)); ?>" tabindex="-1" aria-hidden="true">
			<?= get_the_post_thumbnail($post_id, 'medium_large', array('class' => 'card-post__image')); ?>
		</a>
	<?php endif; ?>

	<div class="card-post__body">
		<h3 class="card-post__title">
			<a class="card-post__link" href="<?= esc_url(get_permalink($post_id)); ?>"><?= esc_html(get_the_title($post_id)); ?></a>
		</h3>

		<div class="card-post__excerpt"><?= wp_kses_post(get_the_excerpt($post_id)); ?></div>

		<a class="card-post__more link-arrow" href="<?= esc_url(get_permalink($post_id)); ?>">
			<?= esc_html__('Read more', CSWP); ?>
			<span class="screen-reader-text"><?= esc_html(get_the_title($post_id)); ?></span>
		</a>
	</div>
</article>
```

- [ ] **Step 3: Write the archive templates**

`archive.php`, `category.php`, `tag.php`, `date.php`, `author.php` share one shape — an archive header, `if ( have_posts() )` loop rendering `get_template_part('parts/content/post-card', '', ['post_id' => get_the_ID()])`, then `cs__the_pagination()`. `home.php` and `front-page.php` do the same without the pagination difference. `search.php` adds the result count.

Each must be guarded so the theme never fatals when a toolbox include is commented out:

```php
<?php if ( function_exists('cs__the_pagination') ): ?>
	<?php cs__the_pagination(); ?>
<?php else: ?>
	<?php the_posts_pagination(); ?>
<?php endif; ?>
```

- [ ] **Step 4: Write `templates/_skeleton.php`**

```php
<?php
/**
 * Template Name: {{TITLE}}
 *
 * @package CStheme
 */

get_header();
?>

<main id="main" class="site-main">
	<div class="container">
		<?php while ( have_posts() ): the_post(); ?>
			<article <?php post_class(); ?>>
				<h1 class="page-title"><?php the_title(); ?></h1>
				<div class="page-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>
```

- [ ] **Step 5: Verify every template renders**

```bash
npm run build
for u in "/" "/sample-page/" "/blog/" "/?s=test" "/?p=1" "/category/uncategorized/" "/nonexistent-xyz/"; do
  printf "%-28s %s\n" "$u" "$(curl -k -s -o /dev/null -w '%{http_code}' "https://starter-theme.local$u")"
done
```

Expected: `200` for every existing route, `404` for `/nonexistent-xyz/`.

- [ ] **Step 6: Verify no notices reach the log**

```bash
: > "/d/Local/starter-theme/logs/php/error.log"
curl -k -s -o /dev/null "https://starter-theme.local/" && curl -k -s -o /dev/null "https://starter-theme.local/blog/"
cat "/d/Local/starter-theme/logs/php/error.log"
```

Expected: empty output.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: base template hierarchy and the post card contract"
```

---

## Task 9: Accessibility layer

**Files:**
- Create: `inc/a11y-block-fixes.php`, `assets/js/src/a11y-runtime.js`
- Modify: `functions.php` (uncomment the include), `inc/enqueue.php` (enqueue the runtime)

**Interfaces:**
- Consumes: the build pipeline (Task 3) for the JS bundle.
- Produces: nothing other tasks import; it is a filter plus a runtime script.

- [ ] **Step 1: Write the failing test**

```bash
grep -c 'wp-block-heading' inc/a11y-block-fixes.php 2>/dev/null || echo "MISSING"
```

Expected: `MISSING`

- [ ] **Step 2: Write `inc/a11y-block-fixes.php`**

Port from `arosa/inc/a11y-block-fixes.php` (27 lines) — the `render_block` filter rewriting `<h6 class="wp-block-heading">…</h6>` to `<p>…</p>`. Keep the class so the visual result is unchanged.

- [ ] **Step 3: Write `assets/js/src/a11y-runtime.js`**

```js
/**
 * Accessibility runtime fixes.
 *
 * 1. Remove positive tabindex values — they break the natural focus order.
 * 2. Remove focusability from content hidden inside aria-hidden containers
 *    (slider clones stay in the DOM and would otherwise be tabbable).
 */

export function initA11yRuntime(){
	document.querySelectorAll('[tabindex]').forEach((el) => {
		if (parseInt(el.getAttribute('tabindex'), 10) > 0) {
			el.removeAttribute('tabindex');
		}
	});

	document.querySelectorAll('[aria-hidden="true"]').forEach((container) => {
		container.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')
			.forEach((el) => {
				el.setAttribute('tabindex', '-1');
			});
	});
}
```

- [ ] **Step 4: Wire it**

Uncomment `require_once 'inc/a11y-block-fixes.php';` in `functions.php`, and enqueue the runtime from `inc/enqueue.php` once `main.js` imports and calls `initA11yRuntime()`.

- [ ] **Step 5: Test it on a real page**

```bash
npm run build
# add an H6 heading block as an "eyebrow" to the sample page, then:
curl -k -s "https://starter-theme.local/sample-page/" | grep -o '<h6 class="wp-block-heading"' | wc -l
curl -k -s "https://starter-theme.local/sample-page/" | grep -o '<p class="wp-block-heading"' | wc -l
```

Expected: `0` then `1` — the H6 was rewritten, its class preserved.

- [ ] **Step 6: Test relevance — if it does nothing, remove it**

```bash
curl -k -s "https://starter-theme.local/sample-page/" | grep -c "a11y-runtime\|a11y"
```

If the runtime produces no observable change on any template in the theme, delete it and record why in the design doc. Shipping a file that never does anything is the "checkbox accessibility" the spec rejects.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: accessibility layer (eyebrow heading fix, focus runtime)"
```

---

## Task 10: README and PHP 8.4 compatibility

**Files:**
- Create: `readme.md`
- Modify: any file that trips a PHP 8.4 deprecation

**Interfaces:**
- Consumes: everything above.
- Produces: the document the client's team supports projects from.

- [ ] **Step 1: Write the failing test**

```bash
ls readme.md 2>/dev/null || echo "MISSING"
```

Expected: `MISSING`

- [ ] **Step 2: Write `readme.md`**

Required sections, in plain English, for a developer who has never seen the theme:

1. **What this is** — a hybrid starter theme: PHP templates plus server-rendered ACF Pro blocks. Not a block theme, not a page builder.
2. **Requirements** — WordPress 6.5+, PHP 8.1+, ACF Pro, Node 18+, and a local environment (Local by Flywheel).
3. **Install** — clone, `npm install`, activate.
4. **Commands** — every script in `package.json`, what it does, and when to run it (`start`, `build`, `tokens`, `make:block`, `stand`).
5. **Adding a block** — `npm run make:block <slug> "<Title>"`, then what to edit in each generated file, then `npm run build`.
6. **Adding a page template** — copy `templates/_skeleton.php`, rename, set the `Template Name` header.
7. **Where content definitions live** — CPTs and taxonomies through the ACF Pro UI; definitions are stored in the database and mirrored to `acf-json/` for version control. Explain the theme-switch and ACF-deactivation consequences.
8. **The commented toolbox** — list every commented include in `functions.php`, what it provides, and any dependency between them.
9. **The stand check** — what it verifies, how to run it, what a failure means.
10. **CSS conventions** — the adapted BEM rules, with a short annotated example.
11. **Known limitations** — the `card-list` contract limit, the ACF Pro dependency, the map component being absent by design.

- [ ] **Step 3: Run the PHP 8.4 compatibility pass**

```bash
PHP84="C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.4.10+0/bin/win64/php.exe"
find . -name '*.php' -not -path './node_modules/*' -print0 | xargs -0 -n1 "$PHP84" -l
```

Expected: PASS. `-l` will not surface deprecations, so also switch the local site's PHP version to 8.4 in Local, load the templates, and read the log:

```bash
: > "/d/Local/starter-theme/logs/php/error.log"
curl -k -s -o /dev/null "https://starter-theme.local/" && curl -k -s -o /dev/null "https://starter-theme.local/sample-page/"
cat "/d/Local/starter-theme/logs/php/error.log"
```

Expected: empty. Fix any `Deprecated:` line it prints. Switch the site back to 8.1.23 afterwards.

- [ ] **Step 4: Run the stand check one last time**

```bash
npm run stand; echo "exit=$?"
```

Expected: exit 0.

- [ ] **Step 5: Commit and push**

```bash
git add -A
git commit -m "docs: README for the client support team, PHP 8.4 compatibility"
git push
git ls-remote origin refs/heads/main | cut -f1
git rev-parse HEAD
```

Expected: the two hashes match.

---

## Task 11: Lint and editor configuration

**Files:**
- Create: `.editorconfig`, `.stylelintrc.json`, `phpcs.xml`
- Modify: `.gitignore` (add `vendor/`, `tools/`), `package.json` (lint scripts)

**Interfaces:**
- Consumes: the built CSS from Task 3.
- Produces: `npm run lint:css`, `npm run lint:php`. Phase 2 and 3 run these.

- [ ] **Step 1: Write the failing test**

```bash
npm run lint:css
```

Expected: FAIL — `npm ERR! Missing script: "lint:css"`

- [ ] **Step 2: Write `.editorconfig`**

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = tab
indent_size = 4

[*.{json,yml,yaml,md,scss,css,js,mjs}]
indent_style = tab

[*.md]
trim_trailing_whitespace = false
```

Note: the theme's PHP and SCSS both use tabs. `.editorconfig` enforces that rather than leaving it to each editor.

- [ ] **Step 3: Install and configure stylelint**

```bash
npm install --save-dev stylelint stylelint-config-standard-scss
```

`.stylelintrc.json`:

```json
{
	"extends": "stylelint-config-standard-scss",
	"rules": {
		"at-rule-no-unknown": null,
		"selector-class-pattern": null,
		"scss/at-rule-no-unknown": true,
		"scss/at-import-partial-extension": null,
		"no-descending-specificity": null,
		"declaration-block-no-redundant-longhand-properties": null
	}
}
```

`selector-class-pattern` is off because the theme's adapted BEM (`.block-x__el`, `.is-state`, `.has-state`) is deliberate and not standard BEM. `at-rule-no-unknown` is off for the same reason in reverse — `@import` is intentionally used over `@use`.

Add the script: `"lint:css": "stylelint \"assets/scss/**/*.scss\" \"parts/block/**/*.scss\""`.

- [ ] **Step 4: Run stylelint**

```bash
npm run lint:css
```

Expected: no errors. Fix any real errors it reports; if a rule fights the theme's conventions, disable that rule in `.stylelintrc.json` with a comment in the commit message explaining why.

- [ ] **Step 5: Install PHPCS with WordPress Coding Standards**

`php` is not on `PATH`, and Composer is not installed — so both are bootstrapped locally with the Local PHP binary:

```bash
PHP="C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe"

curl -sS https://getcomposer.org/installer -o "$TMPDIR/composer-setup.php"
"$PHP" "$TMPDIR/composer-setup.php" --install-dir=. --filename=composer.phar
"$PHP" composer.phar require --dev \
	squizlabs/php_codesniffer \
	wp-coding-standards/wpcs \
	dealerdirect/phpcodesniffer-composer-installer
```

Verify the standard registered:

```bash
"$PHP" vendor/bin/phpcs -i
```

Expected: the list includes `WordPress`, `WordPress-Core`, `WordPress-Extra`.

- [ ] **Step 6: Write `phpcs.xml`**

```xml
<?xml version="1.0"?>
<ruleset name="CStheme">
	<description>WordPress Coding Standards for the CStheme starter theme.</description>

	<file>.</file>

	<exclude-pattern>/node_modules/*</exclude-pattern>
	<exclude-pattern>/vendor/*</exclude-pattern>
	<exclude-pattern>/assets/css/*</exclude-pattern>
	<exclude-pattern>/assets/js/dist/*</exclude-pattern>

	<arg name="extensions" value="php"/>
	<arg name="colors"/>
	<arg name="basepath" value="."/>

	<rule ref="WordPress">
		<!-- Theme templates deliberately mix PHP and HTML heavily. -->
		<exclude name="WordPress.Files.FileName"/>
		<!-- The theme's own escaping helpers are used where the sniff cannot see through them. -->
		<exclude name="WordPress.Security.EscapeOutput.OutputNotEscaped"/>
		<!-- Adapted BEM and the cs__ prefix are project conventions. -->
		<exclude name="WordPress.NamingConventions.PrefixAllGlobals"/>
	</rule>

	<config name="minimum_supported_wp_version" value="6.5"/>
	<config name="testVersion" value="8.1-"/>
</ruleset>
```

The `EscapeOutput` exclusion is deliberate and carries a cost: it means the stand check's own escaping pass (Task 4) is the guard for that class of bug. Do not also silence the stand check.

- [ ] **Step 7: Run PHPCS**

```bash
PHP="C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe"
"$PHP" vendor/bin/phpcs
```

Expected: it runs and reports a finite list. Fix the errors that are real; leave warnings. Do **not** add blanket exclusions to silence a category without recording the reason in the commit message.

- [ ] **Step 8: Add the scripts and gitignore entries**

`package.json`:

```json
"lint:php": "php vendor/bin/phpcs",
"lint": "npm run lint:css && npm run stand"
```

`php` is **not** on this machine's `PATH`, so `npm run lint:php` will not work as written until PHP is added to it. Until then, run PHPCS directly:

```bash
"C:/Users/Admin/AppData/Roaming/Local/lightning-services/php-8.1.23+0/bin/win64/php.exe" vendor/bin/phpcs
```

Do **not** commit a machine-specific absolute PHP path into `package.json` — the repo is handed to the client's team, whose machines resolve `php` differently. Add PHP to `PATH` locally instead, and leave the script portable.

`.gitignore` additions:

```
vendor/
composer.phar
tools/
```

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "chore: editorconfig, stylelint and PHPCS with WordPress standards"
```

---

## Phase boundary

This plan delivers a booting, buildable, checked theme with the block system, one proven reference block, the toolbox, the template hierarchy, the a11y layer and the README.

**Phase 2 — Core block library** (separate plan): the remaining eleven blocks from spec §5 — `hero`, `page-header`, `content-media`, `features`, `item-list`, `accordion`, `tabs`, `card-list`, `counters`, `faq`, `form` — each generated with `npm run make:block` and each following the `cta` contract. Written after this phase lands, so it can reference real interfaces instead of predicting them.

**Phase 3 — Hardening** (separate plan, only if wanted): live Lighthouse/axe probes in the stand script, a block-pattern library, PHPUnit against the WordPress test suite, and an optional second look at Vite if Task 1 chose Gulp.