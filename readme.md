# cs_w_000_starter-v4

A hybrid WordPress starter theme: classic PHP templates plus server-rendered
ACF Pro blocks. It is **not** a block theme (no Full Site Editing, no
`theme.json`-driven templates) and **not** a page builder. Page structure comes
from PHP template files; reusable sections come from custom Gutenberg blocks
whose markup is rendered by PHP, not JavaScript.

---

## 1. What this is

- **PHP templates** for every base view: `front-page.php`, `home.php`,
  `index.php`, `archive.php`, `category.php`, `tag.php`, `date.php`,
  `author.php`, `search.php`, `404.php`, `page.php`, `single.php`, plus
  `header.php` and `footer.php`.
- **Server-rendered blocks** under `parts/block/<slug>/`. Each block is a
  standard `block.json` block whose `renderCallback` is a PHP function, so the
  front end and the editor preview run the same PHP. No React block code is
  written by hand.
- **One settings surface** for site-wide options (Customizer), one source of
  truth for design tokens (`theme.json`), and one build pipeline (Gulp + Sass).
- **A commented toolbox** in `functions.php`: optional features ship as real
  files with their include commented out, so a project starts lean and switches
  on only what it needs (see §8).

Deliberately out of scope: FSE/block-theme conversion, page builders, Tailwind,
PHP frameworks (Sage/Timber), a Composer/PSR-4 rewrite, a React block editor,
and a US map component (see §11).

## 2. Requirements

- **WordPress** 6.5 or newer
- **PHP** 8.1 or newer
- **Advanced Custom Fields (ACF) Pro** — a hard dependency, not optional. CPTs,
  taxonomies and field groups are registered through it (see §7).
- **Node.js** 18 or newer (for the build pipeline and the block generator)
- **A local WordPress environment.** The reference setup is
  [Local by Flywheel](https://localwp.com/), which is what this theme is
  developed against; any local environment that provides PHP, WordPress and a
  MySQL database works.

## 3. Install

```bash
# 1. Put the theme in the site's themes directory
cd wp-content/themes
git clone <repository-url> cs_w_000_starter-v4

# 2. Install the build toolchain
cd cs_w_000_starter-v4
npm install

# 3. Activate the theme in wp-admin → Appearance → Themes
```

`npm install` is required before the first build: the compiled CSS is not in
git (see `.gitignore`), so the theme has no `main.min.css` until you build.

## 4. Commands

Every script in `package.json`. There are **six**:

| Command | Runs | What it does | When to run it |
|---|---|---|---|
| `npm start` | `gulp` | Generates tokens, compiles all Sass, then starts BrowserSync against `https://starter-theme.local` and watches SCSS/PHP for changes. | During active development. This is the default working command. |
| `npm run build` | `gulp build` | Generates tokens and compiles all Sass (global + per-block) with sourcemaps, then exits. No watcher. | Before every commit and before delivery. |
| `npm run watch` | `gulp watch` | Starts BrowserSync and the file watchers only — it does **not** run tokens or compile first. | When the CSS is already built and you only want live reload while editing templates. |
| `npm run tokens` | `node scripts/build-tokens.mjs` | Regenerates `assets/scss/abstracts/_tokens.scss` from `theme.json`. The file is generated and gitignored — never edit it by hand. | After changing any token in `theme.json`. `start` and `build` run it for you. |
| `npm run make:block <slug> "<Title>"` | `node scripts/make-block.mjs` | Scaffolds `parts/block/<slug>/` from the `_skeleton` template. | When adding a new block (see §5). |
| `npm run stand` | `python scripts/check-theme-stand.py` | Runs the five pre-delivery checks (see §9). | Before every commit and as the final gate before delivery. |

> **`npm run build` compiles CSS only.** No task compiles JavaScript — see the
> first item in §11.

## 5. Adding a block

```bash
npm run make:block my-block "My Block"
```

The slug must be lowercase letters, digits and hyphens, starting with a letter.
This copies `parts/block/_skeleton/` to `parts/block/my-block/`, replacing
`{{SLUG}}`, `{{TITLE}}` and `{{FUNC}}`, and prints the generated block name
(`cs/my-block`) and render callback (`cs__render_my_block_block`). Then edit:

| File | What to change |
|---|---|
| `block.json` | `title`, `description`, `icon`, `keywords`, `supports`, `attributes`, and which `postTypes` the block appears on. `name` is already `cs/<slug>`. |
| `callback.php` | The render callback. It reads ACF fields (via `cs__get_block_field()`) into an array and includes `render.php`. Add or rename the fields you need. |
| `render.php` | The block's markup. Escape output (`esc_html`, `esc_attr`, `wp_kses_post`) and build the wrapper with `cs__get_block_id()`, `cs__get_block_classes()` and `cs__get_block_styles()`. |
| `style.scss` | Front-end styles for the block (see §10). |
| `editor.scss` | Editor-only styles. |

Finally:

```bash
npm run build   # compiles style.min.css and editor.min.css for the new block
```

A folder whose name starts with `_` (`_skeleton`, `_base-block`) is a template
and is excluded from block registration by `cs__get_blocks()` — do not name a
real block that way.

## 6. Adding a page template

1. Copy `templates/_skeleton.php` to a new file in the theme root (or in
   `templates/`) that does **not** start with an underscore.
2. Set the header comment's `Template Name:` line to the name shown in the
   editor's template dropdown.
3. Edit the markup as needed, then select the template on a page in wp-admin.

Files beginning with `_` are excluded from the template registry by
`cs__exclude_generator_templates()`, so `_skeleton.php` never appears in the
dropdown. The skeleton is a starting point, not a live template.

## 7. Where content definitions live

Custom post types, taxonomies and field groups are **registered through the
ACF Pro UI**, not in theme code. The theme deliberately does not disable ACF's
post-type feature (that line lives in the commented toolbox).

- The definitions are stored as **posts in the WordPress database** — that is
  where ACF reads them from at runtime.
- `acf-json/` inside the theme is a **mirror for version control**, not the
  source of truth. It carries definitions into a fresh environment and into git.
  Block-owned field groups can instead live beside their block in
  `parts/block/<slug>/` (loaded through `acf/settings/load_json`).

Two consequences worth knowing before you touch anything:

- **Switching the theme does not lose CPT, taxonomy or field definitions.**
  They stay in the database and ACF keeps registering them. Theme-switch safety
  comes from ACF itself, not from where the JSON file sits.
- **Deactivating ACF Pro does lose them.** Nothing registers the post types, so
  every CPT post becomes "Invalid post type" until ACF is reactivated. ACF Pro
  is a permanent, paid dependency on every project; keep it active.

For cases ACF's UI cannot express (custom capabilities, `register_post_meta()`,
a post type owned by a plugin), use the declarative registrar in the commented
toolbox — `inc/post-types.php` with the `DEFAULT_CPT_ARGS` constants (see §8).

## 8. The commented toolbox

The commented includes at the bottom of `functions.php` are a **deliberate
toolbox, not dead code**. Every one of them has a real, working file in `inc/`;
uncomment the line to switch the feature on. (The v3 theme died because a
commented include's file was *missing* — a commented include is a promise that
the file exists, and here it does.)

| Commented include | Provides | Notes / dependencies |
|---|---|---|
| `inc/breadcrumbs.php` | `cs__the_breadcrumbs()` — the breadcrumb trail. | Already called, behind `function_exists()` guards, by the archive, author, category, date, front-page, home, search and tag templates. Uncommenting lights them up. |
| `inc/pagination.php` | `cs__the_pagination()` — Previous/Next bookended pagination. | Guarded the same way by the same archive-style templates. |
| `inc/shortcodes.php` | The `[cs-year]` shortcode (current year in the site timezone). | None. |
| `inc/widgets.php` | Registers the `cs-sidebar` sidebar and `cs__the_sidebar()`. | None. |
| `inc/post-types.php` | `cs__register_post_types()` / `cs__register_taxonomies()` — a declarative registrar for CPTs and taxonomies. | **Depends on `inc/constants.php`**, which always loads: it reads `DEFAULT_CPT_ARGS`, `DEFAULT_CPT_LABELS` and `DEFAULT_TAXONOMY_ARGS`. Use it only for what ACF's UI cannot express (§7). |
| `inc/cpt-post.php` | Removes the built-in Posts post type from the admin. | None. |
| `inc/admin.php` | Reorders the wp-admin menu. | None. |
| `inc/customize.php` | Customizer settings: header button and social links. | This is the **primary** settings surface. |
| `inc/plugin-acf.php` | An ACF options page ("Theme Settings") as a settings surface. | The **fallback** alternative to `inc/customize.php` — the two are competing sources of truth, so enable one, not both. Its body is commented too; uncomment the include *and* the `acf_add_options_page()` block inside. |

**Not in the toolbox:** `inc/menu-walker.php` is always active. `header.php`
calls `new cs__primary_menu_walker()` in both of its `wp_nav_menu` calls, so the
class must load with the theme — that is why its include is not commented.

## 9. The stand check

```bash
npm run stand
```

This runs `scripts/check-theme-stand.py`, five static checks that answer "is
this theme deliverable?":

1. **PHP syntax** — every parseable `.php` file (`_skeleton` templates are
   excluded; they carry placeholders) lints clean.
2. **`block.json` validity** — every real block folder has valid JSON with
   `name`, `title`, `category`, `apiVersion: 3`, and no `file:` asset reference
   pointing at a missing file.
3. **Symbol resolution** — every `cs__` function or class a template uses is
   defined somewhere in the theme (this is the check that would have caught the
   v3 fatal).
4. **Output escaping** — no `<?= $var ?>` printed without an `esc_*`/`wp_kses`
   call on the same line.
5. **Build artifacts not tracked** — no `*.min.css`, `*.min.js` or `*.map`
   files are committed.

**Exit 0** means all checks passed. **Non-zero** means at least one check
failed; each finding is printed as `file: line: what`, and it must be fixed
before delivery. A `SKIP` line means the check could not run in this
environment (e.g. no git) and does not fail the gate. See the third item in §11
for what the symbol check does **not** cover.

## 10. CSS conventions

Adapted BEM, used consistently across every block and partial:

```scss
.block-x {
  $b: &;                          // root alias, so nested rules can refer back

  &__container { }                // elements in the order they appear in the block
  &__heading {
    margin: 0 0 calc($layout_padding * 4);

    @include mediaMaxWidth( md ){ // media query PER ELEMENT, never one at the end
      margin: 0 0 calc($layout_padding * 3);
    }
  }
  & &__list { }                   // contextual descendant
  &__item { }

  // Modifiers                     // section headers are comments; the rules are live
  &.alignfull { }
  &.has-background { }

  // States
  &.is-playing { }

  // Frontend only styles
  body:not(.wp-admin) & { }
}

.card-x { $c: &; }                // a card partial gets its own root in the same file
```

Rules:

1. **Declaration order within a block** follows its architecture: container →
   elements → modifiers → states → frontend-only.
2. **Property order within a rule is alphabetical** (`align-items`, `display`,
   `flex-flow`, `gap`, `margin`) — for fast lookup when editing.
3. **Modifiers (`is-*`) and states (`has-*`) go at the end of the block**, under
   the `// Modifiers` / `// States` headers. The code is live; only the header is
   a comment.
4. **Media queries live inside the element they modify**, never collected at the
   bottom.
5. Block SCSS imports the shared abstracts:
   `@import '../../../assets/scss/abstracts/{functions,variables,mixins}';`
6. Each block's SCSS is self-contained and compiles in place to
   `style.min.css` / `editor.min.css`.
7. Spacing and colour per instance come from Gutenberg native supports, emitted
   by `CS_Block_Styles` as an inline `style` attribute — not per-instance
   `<style>` tags.
8. **Card partials** live one per file in
   `assets/scss/parts/content/_<type>-card.scss` and are imported by **exactly
   one entry point** — the narrowest one covering every consumer (the block's
   `style.scss` if only one block uses it, or `main.scss` if a PHP
   template/archive renders it too). Never import the same partial from both
   `main.scss` and a block file: that emits duplicate CSS on pages carrying the
   block.

SCSS never hardcodes a colour, font size or radius; it references
`var(--wp--preset--…)` / `var(--wp--custom--…)` through
`assets/scss/abstracts/_variables.scss`. Editing a token means editing
`theme.json` and re-running the build.

## 11. Known limitations

Accepted, documented limits — not defects to fix in passing:

- **The `card-list` contract.** `card-list` (and the ten project-specific block
  names it replaces) resolves a card partial by post type and calls it with a
  fixed `$args` contract: `post_id` and `modifier`. Every card partial must
  share that contract. It covers roughly 80% of cases; lists with bespoke
  filtering stay as project-specific blocks. The contract is small, so removing
  the block is cheap if it does not fit.
- **ACF Pro is a hard dependency.** CPTs, taxonomies and field groups are
  registered through ACF Pro; deactivating it makes every CPT post "Invalid post
  type". See §7.
- **The US map component is absent by design.** The large inlined US-map SVG
  used by earlier themes was considered for reuse and rejected — it is not
  needed on most projects. Add it per project when required.
- **`npm run build` compiles no JS.** `gulpfile.js` declares `paths.scripts` but
  no task consumes it, and `package.json` carries no bundler, so
  `assets/js/dist/` holds only `.gitkeep`. This is harmless today:
  `inc/enqueue.php:19` guards its enqueue with `file_exists()`, so no
  `<script src>` renders and no 404 is reachable. The JS pipeline must be built
  before the first real JS source is added.
- **The `h6` eyebrow filter is indiscriminate.** `inc/a11y-block-fixes.php`
  rewrites **every** `core/heading` level-6 block to a `<p>`, so a genuine H6
  heading an author meant as a heading is rewritten too. An H6 block carries
  nothing that distinguishes an eyebrow from a real heading, so the filter
  cannot tell them apart — this is a limitation of the approach, not a bug.
- **The stand check does not trace `require_once`.** It resolves a symbol by
  "defined somewhere in the theme", so a symbol behind a **commented-out**
  include passes while the page would fatal at runtime — the exact v3 failure.
  It catches the definition file being *absent*, not the include being *off*
  (§9, check 3). The failure it misses is loud and immediate: a fatal naming the
  class the moment the include is toggled, on the developer's own machine.