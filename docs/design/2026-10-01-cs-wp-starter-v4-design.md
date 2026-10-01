# CStheme v4 — WordPress Starter Theme: Design Specification

- **Date:** 2026-10-01
- **Repo:** https://github.com/LuKiDar/cs_w_000_starter-v4
- **Working copy:** `D:\Local\starter-theme\app\public\wp-content\themes\cs_w_000_starter-v4`
- **Author:** Dariia Lukiianchuk (Crazy Seeker)
- **Predecessor:** `cs_w_000_starter-v3` (frozen, not touched — see §2)
- **Status:** approved for implementation

---

## 1. Goal

Turn the starter theme from a *scaffold* into a *library*.

The measured problem: across the four most recent client projects (arosa, millburn,
nucleux, corazon) there are **62 distinct block slugs across 75 block instances**, of
which only **9 (14%) appear in two or more projects** and only `features` appears in all
four. 53 blocks (85%) are one-offs — the same primitives rebuilt under a new name in every
project. v3's own "custom blocks" (`hero`, `example`) are identical wrappers around a
single ACF `content` field and expose no field contract at all.

v4 must ship a small, opinionated core: a proven block contract, a reusable block library,
one design-token source, correct per-block asset loading, a ready-to-uncomment toolbox,
and an automated delivery check.

**Success criteria**

1. A new client project starts from v4 and reaches a working local site in one sitting.
2. Blocks from the core library are reused as-is; only project-specific "meat" is added.
3. Switching the active theme does not break the site's content (CPT/taxonomy definitions
   survive a theme change).
4. One command answers "is this theme deliverable?" with pass/fail.
5. A developer who has never seen the theme can build it and extend it from the README alone.

## 2. Non-goals

- **v3 is not modified, fixed, or migrated.** It is frozen history. Its broken state
  (see §12.1) is a known, accepted fact. `cs_w_000_starter`, `-v2`, `-v3` and all four
  client themes are read-only references for this work.
- No page builder support.
- No conversion to a full-site-editing / block theme. v4 stays a **hybrid** theme:
  PHP template hierarchy + server-rendered ACF blocks.
- No Tailwind, no CSS-in-JS, no front-end JS framework.
- No PHP framework (Sage/Timber), no Composer, no PSR-4 OOP rewrite.
- No React `edit.js` for blocks. Blocks are ACF `mode: auto` + `render.php`.
- No generic "flexible content" mega-block.
- The US map SVG component is **explicitly excluded** — it is not reusable (see §12.2).

## 3. What is carried over from v3 (keep)

These are established, working conventions. v4 reproduces them.

| Convention | Detail |
|---|---|
| Function prefix | `cs__` for functions, `cs__` for classes used as helpers |
| Text domain | `define('CSWP', 'cswp')` in `functions.php`, `'cswp'` in `style.css` |
| CSS naming | Adapted BEM — see §7 |
| PHP layout | `inc/` one file per concern; `parts/{block,content,section}/`; `templates/` |
| Asset versioning | `filemtime()` as the `$ver` argument on every enqueue |
| Token flow | `theme.json` → CSS custom properties → SCSS variables (one direction only) |
| CPT/taxonomy registration | **Not the primary path** — see §6. The declarative registrar (`DEFAULT_CPT_ARGS` / `DEFAULT_CPT_LABELS` / `DEFAULT_TAXONOMY_ARGS` constants plus `cs__register_post_types()`) ships in the commented toolbox, for the cases ACF's UI cannot express: custom capabilities, `register_post_meta()`, or a post type owned by a plugin |
| Nav menus | Custom `Walker_Nav_Menu` subclass + `nav_menu_css_class` cleanup |
| Editor curation | `assets/js/block-styles.js` unregisters unused core blocks; core patterns and the block directory removed |
| Cleanup layer | `inc/wordpress-cleanup.php` — clean head, whitelisted body/nav classes, emoji and comments off |
| Theme features | The `add_theme_support()` set from v3 `functions.php` |
| SVG uploads | `upload_mimes` filter allowing `image/svg+xml` |

## 4. Block contract

Taken from `arosa`, which represents the format to hold to. One folder per block:

```
parts/block/<slug>/
├── block.json        # name cs/<slug>, category "cs-blocks", apiVersion 3,
│                     # acf.mode "auto", acf.renderCallback "cs__render_<slug>_block",
│                     # style  "file:./style.min.css"
│                     # script "file:./script.js"        (optional)
│                     # editorStyle "file:./editor.min.css"
├── callback.php      # cs__render_<slug>_block(): get_field() each field,
│                     # pack into $block_data, set_query_var('block_data', $block_data),
│                     # include render.php
├── render.php        # $data = get_query_var('block_data'); early-return if empty;
│                     # semantic markup only
├── style.scss        # front-end styles (compiled in place to style.min.css)
├── editor.scss       # editor-only styles (compiled in place to editor.min.css)
└── script.js         # optional, front-end behaviour only
```

`_skeleton/` is the same tree with placeholders and is **excluded from registration**
(the existing `scandir` exclusion list already names `_base-block`; v4 uses `_skeleton`
and excludes both).

### 4.1 Why callback.php + render.php are split

`render.php` must be includable from two contexts: the block render callback, and
`render_block()` called from a PHP template. The split keeps field access (which needs
ACF's block context) separate from markup (which does not). This is what makes the hybrid
approach work — arosa uses `render_block()` in 10 template locations.

### 4.2 Improvements over arosa

| Change | Reason |
|---|---|
| `apiVersion: 3` | arosa uses 2; 3 is current and enables iframe editor / block API v3 |
| `"style": "file:./style.min.css"` everywhere | arosa is consistent, but v3 mixed `file:` and a bare handle. Make it a rule |
| `renderCallback` mandatory | makes the contract uniform; `renderTemplate` alone is the older v3 shape |
| Block category title derived from the theme | arosa hardcodes `'Arosa blocks'`; v4 uses the theme name |
| `CS_Block_Styles` used for every block | replaces the old per-instance `<style scoped>` from nucleux/corazon |
| `example.data` populated for every core block | blocks otherwise show an empty ACF preview in the inserter |
| No `helpers.php` / `block-functions.php` dead paths | arosa's loader probes for files that mostly do not exist; keep only `block-functions.php` and only where genuinely needed |

### 4.3 Field groups

- **Spacing and colour come from Gutenberg natively.** `supports.spacing.margin/padding`
  plus `attributes.style.default` using `var:preset|spacing|40` etc. This is what the
  arosa `cta` block does. No custom "Style" tab.
- `Part: Block Content` (`eyebrow`, `heading`, `subheading`, `content`) and
  `Part: Button Group` (`buttons`) are shipped as **optional clone sources**
  (`location: widget:all`) for blocks whose design happens to fit them. They are not
  forced onto every block.
- Block field groups live with their block. CPT and options field groups live outside the
  theme (§6).

## 5. Core block library

Twelve blocks plus the skeleton. Deliberately thin: content + minimum variants, no styling
variants. "Meat" is added per project.

| Block | Purpose | Replaces |
|---|---|---|
| `hero` | Page-opening hero | arosa/millburn/nucleux `hero` |
| `page-header` | Interior page / archive header | arosa/millburn `page-header` |
| `content-media` | Image + text, alignment variants | `content-media`, `media-text-2-col`, `spotlight-image` |
| `features` | Grid of icon/image + title + text | `features` (4/4 projects) |
| `item-list` | Repeater of icon/logo/link + label | `logo-list`, `icon-list`, `icon-slider-content`, `highlighted-list`, `link-arrow`, `quick-links` |
| `cta` | Call to action with button group | `cta`, `contact-info` |
| `accordion` | Disclosure list | `accordion`, `accordion-text` |
| `tabs` | Tabbed panels | `tabs` |
| `card-list` | Generic query-driven card grid/list/slider — see §5.1 | `block-blog-posts`, `post-cards`, `services-posts`, `services-overview`, `case-studies-overview`, `positions-cards`, `team-cards`, `team-slider`, `location-offices`, `reviews`, `testimonials` |
| `counters` | Stat counters | `counters`, `stats-timeline` |
| `faq` | FAQ list | `faq` |
| `form` | Form embed wrapper (CF7 / Fluent Forms / Gravity Forms) | `content-form`, `find-form`, `contact-form`, `contact-form-selector`, `form` |
| `_skeleton` | Generator template, not registered | — |

`team` and `testimonials` are **not** separate blocks: they are `card-list` with
`post_type=team|testimonial` and the matching card partial. This is the point of §5.1.

### 5.1 `card-list`

One block replacing ten. Fields:

| Field | Values |
|---|---|
| `post_type` | any registered post type |
| `taxonomy` + `term` | optional filter |
| `count` | number of items |
| `orderby` / `order` | query ordering |
| `layout` | `grid` / `list` / `slider` |
| `heading`, `subheading` | from the optional `Part: Block Content` clone |

The card partial is resolved by post type:
`get_template_part('parts/content/' . $post_type . '-card', '', ['post_id' => $id, 'modifier' => $modifier])`

**Known limitation, accepted:** the card partials must share one `$args` contract
(`post_id`, `modifier`). Today each project invents its own. `card-list` covers roughly
80% of cases; lists with bespoke filtering (e.g. arosa `job-openings` filtered by
location) stay as project-specific blocks. If it turns out not to fit, it is cheap to
remove because the contract is small.

## 6. Custom post types: ACF Pro, not the theme

Verified on ACF Pro 6.8.6:

- CPT + taxonomy UI exists (`includes/post-types/class-acf-post-type.php`, `class-acf-taxonomy.php`)
- Enabled by default (`acf.php:154` → `'enable_post_types' => true`)
- Local JSON for post types and taxonomies is supported since ACF 6.1
  (`local-json.php:324-358`, `include_post_types()` / `include_taxonomies()`)

**Decision:** CPTs and taxonomies are registered through ACF Pro's Post Types and
Taxonomies UI. No custom site plugin is written for this purpose. The theme simply stops
disabling the feature (v3/arosa/millburn/nucleux/corazon all call
`add_filter('acf/settings/enable_post_types', '__return_false')`; in v4 that line sits in
the commented toolbox).

### 6.1 Where the definitions actually live

Verified empirically against the local installs: ACF stores its definitions as
**WordPress posts in the database**. `acf-json/` is a mirror for version control, not the
source of truth.

| Evidence | Value |
|---|---|
| `acf_get_acf_post_types()` | delegates to `acf_get_internal_post_type_posts('acf-post-type', $filter)` — a query over ACF's internal post types |
| arosa `app/sql/local.sql` | 31 `acf-field-group` rows, 168 `acf-field` rows |
| corazon `app/sql/local.sql` | 36 `acf-field-group` rows, 389 `acf-field` rows |

Consequences:

- **Changing the theme does not lose CPT, taxonomy or field definitions.** They stay in
  the database and ACF keeps registering them. Theme-switch safety comes from ACF itself,
  not from where the JSON file sits.
- **Deactivating ACF Pro does lose them** — nothing registers and every CPT post becomes
  "Invalid post type". See §6.2.
- JSON's real job is version control and carrying definitions into a fresh environment.

**Decision: keep a single `acf-json/` inside the theme**, exactly as the existing themes
do. Splitting the load/save paths to a location outside the theme is **not needed** — it
adds a moving part without buying theme independence that the database already provides.

| Lives where | What | Why |
|---|---|---|
| `themes/<theme>/acf-json/` | field groups; CPT and taxonomy definitions if created through the ACF UI | versioned with the theme that owns them; the database holds the runtime copy |
| `parts/block/<slug>/` | block field groups, where a block owns them | ship with the block |
| A dedicated site plugin | job boards, reviews integrations, external services | business logic, not presentation (as done for arosa `workstream-jobs` and `google-reviews-places`) |

### 6.2 Residual risk, stated honestly

CPT registration now depends on ACF Pro being active. Deactivating ACF Pro makes every
CPT post "Invalid post type". This is accepted: ACF Pro is a stable, permanent, paid
dependency present on every project, whereas the theme is the thing that gets swapped.
For job-board-grade logic the plugin route remains correct.

### 6.3 Theme dependency on foreign post types

Arosa's theme queries a `job` CPT and a `job_location` taxonomy registered elsewhere, with
no declaration anywhere. Rule for v4: any theme reference to a post type or taxonomy the
theme does not itself guarantee must be either

- guarded (`post_type_exists()`, `taxonomy_exists()`, `class_exists()`), or
- declared as an explicit dependency in `readme.md`.

## 7. CSS conventions

Adapted BEM, reproduced exactly as used in arosa.

```scss
.block-x {
  $b: &;                          // root alias

  &__container { }                // elements in the order they appear in the block
  &__heading {
    margin: 0 0 calc($layout_padding * 4);

    @include mediaMaxWidth( md ){ // media query PER ELEMENT, never one block at the end
      margin: 0 0 calc($layout_padding * 3);
    }
  }
  & &__list { }                   // contextual descendant
  &__item { }

  // Modifiers                     // section headers are comments; the rules are live
  &.alignfull { }
  &.has-background { }
  &.has-dark-background { }

  // States
  &.is-playing { }
  &.is-vertically-aligned-top { }

  // Frontend only styles
  body:not(.wp-admin) & { }
}

.card-x { $c: &; }                // card partials get their own root in the same file
```

Rules:

1. **Declaration order within a block** follows the block's architecture (container →
   elements → modifiers → states → frontend-only).
2. **Property order within a rule is alphabetical** (`align-items`, `display`,
   `flex-flow`, `gap`, `margin`). This is for fast lookup when making an edit.
3. **Modifiers (`is-*`) and states (`has-*`) go at the end of the block**, under the
   `// Modifiers` and `// States` comment headers. The code is live; only the header is a
   comment. (Confirmed with the owner — this was previously misread as commented-out CSS.)
4. **Media queries live inside the element they modify**, never collected at the bottom.
5. Block SCSS imports the shared abstracts:
   `@import '../../../assets/scss/abstracts/{functions,variables,mixins}';`
6. Each block's SCSS is self-contained and compiles in place to `style.min.css` /
   `editor.min.css`.
7. Spacing/colour per instance comes from Gutenberg native supports, emitted by
   `CS_Block_Styles` as an inline `style` attribute — not from per-instance `<style>` tags.
8. **Card partials** live one per file in `assets/scss/parts/content/_<type>-card.scss` and
   are imported by **exactly one entry point** — the narrowest one that covers every
   consumer:

   | Consumers | Imported by | Cost |
   |---|---|---|
   | one block only | that block's `style.scss` | none |
   | a block **and** PHP templates / archives | `assets/scss/main.scss` (global) | unavoidable — no block owns it, so per-block loading cannot apply |
   | several blocks, no templates | each consuming block's `style.scss` | single source, a few KB duplicated in the compiled output |

   Never import the same partial from both `main.scss` and a block file — that emits
   duplicate CSS on pages carrying the block.

   This is what arosa already does: `post-card`, `location-card`, `team-card` and
   `job-card` are imported in `main.scss` because archives render them from PHP templates,
   while `card-feature` — used only by the `features` block — is defined inside
   `parts/block/features/style.scss`. `card-list` (§5.1) resolves its card by post type,
   so its cards are shared with the archives and therefore belong in the global layer.

## 8. Design tokens

`theme.json` is the single source of truth for palette, font sizes, spacing scale,
radius, layout widths, line heights and element/block styles. Two rules:

1. SCSS never hardcodes a colour, font size or radius — it references
   `var(--wp--preset--…)` / `var(--wp--custom--…)` through `assets/scss/abstracts/_variables.scss`.
2. **No manual duplication.** Today the same palette and font-size scale is maintained in
   four places (theme.json, `inc/tinymce-editor.php`, `assets/scss/editor.scss`,
   `assets/scss/abstracts/_root-variables.scss`). In v4 a build step **generates**
   `_tokens.scss` (and the TinyMCE map) from `theme.json`. Editing a token means editing
   `theme.json` and re-running the build.

`appearanceTools: true` with WP's default presets, custom colours, gradients, duotone,
shadows and custom spacing sizes disabled — so only the project palette is selectable.

## 9. Asset loading

**Rule: a block's CSS and JS load only on pages where that block is rendered.**

The mechanism is already proven in arosa: `inc/gutenberg.php` is 72 lines and performs
**no manual enqueue at all**. Each `block.json` declares `"style": "file:./style.min.css"`,
`"editorStyle": "file:./editor.min.css"`, `"script": "file:./script.js"`, and WordPress
loads them per block.

What v4 must therefore **not** do (this is the v3/nucleux/millburn defect):
`wp_register_style()` + `wp_enqueue_style()` for every block inside `cs__load_blocks()` on
`init`, which pulled every block's CSS and JS onto every page.

Additional rules:

- `add_filter('should_load_separate_core_block_assets', '__return_true')` (v3 already has it).
- Core block patterns and the block directory removed; unused core blocks unregistered in
  `assets/js/block-styles.js`.
- Conditional third-party asset loading (e.g. a slider library) only when the block that
  needs it is present.

## 10. The toolbox

The commented include list in `functions.php` is **deliberate and must be preserved**: a
ready-to-uncomment toolbox so a new project starts lean but every optional feature is one
line away, and stays visible in the code while being cleaned up.

The defect in v3 is not the comments — it is that **the commented files do not exist**.
`header.php` calls `new cs__primary_menu_walker()` while `inc/menu-walker.php` is absent
and its include is commented out, so every page 500s. A commented include is a promise;
v4 must keep it.

**Requirement:** every entry in the commented include list has a real, working file on
disk, and the stand script (§11) verifies that every class or function a template
references is **defined and loaded**.

**What the stand check actually does, measured rather than assumed.** The symbol check
resolves a symbol by asking whether it is **defined anywhere in the theme's PHP files**.
It does not trace `require_once`, so it answers "does this definition exist", not "is it
loaded". Measured on this theme, two states that look alike behave differently:

| state | stand check |
| --- | --- |
| `header.php` calls the walker, its file is **absent** (v3's actual fatal) | `FAIL symbol resolution (2)`, exit 1 |
| `header.php` calls the walker, its file exists but the include is **commented** | `PASS`, exit 0 — and the page fatals at runtime |

So the check catches the defect v3 actually died of — a template calling into a file that
does not exist — and does **not** catch a file that exists but is not included. That
second gap is real and is recorded here rather than closed: closing it means tracing
includes, which is new analyzer surface with its own false-alarm cost, and Task 4 spent
eight rounds learning how expensive that is. The failure it would catch is loud and
immediate — a fatal naming the class, on the developer's own machine, the moment they
toggle an include — not a silent one in production.

An earlier draft of this section said a reference was acceptable if the symbol was
"defined or behind a commented include". That describes the check's behaviour correctly,
but it reads as a licence, and it is not one: a file in the toolbox is a promise that the
file *exists*, never a licence to call into it while it is switched off.

That settles where a feature belongs. A `Walker_Nav_Menu` subclass that the theme's own
`header.php` uses is **not a toolbox item** — its include is always-on, like the other
always-on includes, and the toolbox holds only what a new project may reasonably never
switch on. (An earlier draft of the plan put `menu-walker.php` in the toolbox *and* wired
it into `header.php`. That combination is the second row of the table above: green stand
check, fatal page.)

Toolbox files to ship, commented in `functions.php`:

`breadcrumbs.php`, `pagination.php`, `shortcodes.php`, `widgets.php`, `cpt-post.php`,
`plugin-acf.php` (options-page fallback), `post-types.php`.

**Always-on, not toolbox:** `menu-walker.php` — `header.php` wires it into both
`wp_nav_menu` calls, so it loads with the theme. Arosa already contains a working
`inc/menu-walker.php` (87 lines) to port.

## 11. Stand script

`scripts/check-theme-stand.py` — one command that answers "is this theme deliverable?".
The analogue of the Shopify profile's stand check; for WordPress the pre-delivery surface
is the local/staging site rather than a theme preview, so the checks are static plus
optional live probes.

Static checks:

- `php -l` over every `.php` file
- every `block.json` parses; has `name`, `title`, `category`, `apiVersion`; if it declares
  `acf.renderCallback`, that function is actually defined somewhere in the theme
- every `file:./…` reference in `block.json` resolves to an existing file
- every `wp_enqueue_style/script` source path exists on disk
- every `get_template_part('…')` path resolves
- **every `cs__…()` call and `new cs__…` in the theme has a definition** — this single
  check would have caught the fatal that killed v3
- unescaped output: `<?= $…` / `echo $…` without `esc_*` / `wp_kses`
- token drift: palette and font-size slugs in `theme.json` vs those referenced in SCSS and
  the TinyMCE map
- build artefacts not present in the git index

Optional live probes (flag-gated): Lighthouse / axe run against the local site via the
browser tooling.

Output: pass/fail per check plus a non-zero exit code on failure.

## 12. Reference findings (evidence for the decisions above)

### 12.1 The v3 fatal

```
PHP Fatal error: Uncaught Error: Class "cs__primary_menu_walker" not found
in .../cs_w_000_starter-v3/header.php:42
```

`header.php:42` and `:65` call `new cs__primary_menu_walker()`. No such class exists
anywhere in the theme; `inc/menu-walker.php` is absent and its `require_once` is commented
out in `functions.php`. The active theme on the local site is v3, so `starter-theme.local`
returns 500. Last commit: `d1d44d0 20250428 header updates`.

Also present in the v3 working copy as uncommitted work: modified `header.php`,
`footer.php`, `inc/enqueue.php`, `inc/gutenberg.php`, `theme.json`, several SCSS files,
plus untracked `parts/block/hero/` and `parts/block/example/style.scss`. v3 is not to be
touched, so this stays as-is.

### 12.2 The US map

`arosa/parts/locations-map.php` (107 KB), `millburn/parts/properties-map.php` (108 KB) and
`corazon/parts/block/asc-map/render.php` (109 KB) each inline the full US map SVG, each
with its own copy of the same `cs__set_state_attributes()` helper. ~324 KB of duplicated
markup. It was considered as a reusable component and **rejected by the owner** — the map
is not needed on most projects. Skip it; add it per project when required.

### 12.3 Repo hygiene baseline

- None of the four client themes has a `.git` directory.
- v3's `.gitignore` contains only `node_modules`; compiled `*.min.css`, `*.min.js` and
  `*.map` files are committed alongside their sources.
- No `.editorconfig`, no PHPCS, no stylelint, no CI anywhere.
- `package.json` in v3 and arosa still carries `"name": "millburn"` — copy-paste residue
  from a previous client project.
- `gulpfile.js` in millburn still proxies BrowserSync to `http://starter-theme.local`.

### 12.4 ACF Pro evidence

Version 6.8.6. `includes/post-types/{class-acf-post-type,class-acf-taxonomy}.php` present.
`acf.php:154` sets `'enable_post_types' => true` by default.
`local-json.php:324-358` implements `include_post_types()` and `include_taxonomies()`
since 6.1.

## 13. Build tooling

**Decision: time-boxed spike first.**

v4's directory is empty, so the build pipeline is written once — building Gulp and then
migrating would be double work. But a per-block-entry Vite configuration with
manifest-based enqueueing is fiddly and must not stall the theme.

Plan: a 2–3 hour spike on two blocks (`cta`, `hero`) answering three questions:

1. Does a block's `style.scss` compile in place to `style.min.css`?
2. Does HMR pick up a change to a block's SCSS without a page reload?
3. Does the manifest-based enqueue load a block's CSS/JS **only** on pages where the block
   is rendered?

If all three pass, v4 uses Vite. If not, v4 falls back to the arosa Gulp 5 configuration,
which is already written and proven. Either way the theme's output layout
(`parts/block/<slug>/style.min.css`, `editor.min.css`, `assets/css/`, `assets/js/dist/`)
stays the same, so the choice is reversible.

Assessment for the record: Gulp is in maintenance but stable and not dead. Vite's concrete
wins are CSS HMR instead of full page reload, one tool instead of
gulp + rollup + glob + sourcemaps + autoprefixer + clean-css, and a manifest that maps
cleanly onto `wp_enqueue_*`. Importance: medium, not blocking.

### Spike result

**Decision: `GULP`.**

Spike run 2026-10-01 in a throwaway directory outside the theme (Vite 8.3.2, sass
1.105.1, glob 13.0.6 on Node 24.19.0), against two probe blocks `cta` and `hero` with the
per-block-entry config from the brief. Result: **(a) pass, (c) pass, (b) fail** — and per
the rule above, one failure means Gulp.

**(a) `style.scss` → `style.min.css` in place — PASS.** `npx vite build` exits 0 and lands
the compiled CSS beside its source, unhashed and stable:

```
/parts/block/cta/editor.min.css   0.04 kB
/parts/block/hero/editor.min.css  0.04 kB
/parts/block/hero/style.min.css   0.15 kB
/parts/block/cta/style.min.css    0.17 kB
```

`.vite/manifest.json` records the source→output map (`parts/block/cta/style.scss` →
`parts/block/cta/style.min.css`) that a `wp_enqueue_*` helper would key on. The one blemish
is a warning — `build.outDir must not be the same directory of root or a parent directory
of root` — because `outDir: '.'`; it is a warning, not an error, and `emptyOutDir: false`
is what stops the build deleting the sources.

**(c) manifest-driven per-block enqueue — PASS.** A PHP helper reading the manifest
enqueues only what the page rendered. Simulated page output:

```
blocks=cta        ENQUEUED: /parts/block/cta/style.min.css
blocks=hero       ENQUEUED: /parts/block/hero/style.min.css
blocks=cta,hero   ENQUEUED: /parts/block/cta/style.min.css /parts/block/hero/style.min.css
```

In production this does not even need the manifest for block CSS: §9's `block.json`
`"style": "file:./style.min.css"` mechanism resolves the same files natively.

**(b) SCSS HMR without a page reload — FAIL.** The raw mechanism works and was proven
end-to-end: a PHP 8.4 page that emits the dev markup (`@vite/client` plus one module script
per rendered block's `style.scss`) was loaded in headless Chrome; editing
`parts/block/cta/style.scss` (`#2f6fed` → `#e01b24`) changed the computed background from
`rgb(47, 111, 237)` to `rgb(224, 27, 36)` **while `window.__loadedAt` stayed identical** —
no navigation. A websocket probe against Vite's HMR channel returned `fullReloads=0`.

It fails on the machinery clause. Making it work requires a *second, dev-only asset-loading
path in the theme*, and that path contradicts §9 and §4:

1. In dev the built `.min.css` files do not exist (sources-only-in-git, §14), so
   `block.json`'s `"style"` and `"editorStyle"` are inert — core resolves them through
   `realpath()` (`wp-includes/blocks.php:407`), which returns `false` for a missing file,
   registering a style with no source. The dev branch must therefore suppress block.json's
   styles and enqueue SCSS modules instead, maintained in parallel with the manifest path.
2. Vite's default `server.cors` only echoes `Access-Control-Allow-Origin` for localhost
   origins. Measured: `Origin: http://localhost:8080` is echoed, `Origin:
   http://starter-theme.local` — the host the Local site actually serves on — is not. The
   module fetch would be blocked until `server.cors` is widened and `server.origin` set.
3. §4 mandates `editorStyle` on every block and §4.2 sets `apiVersion: 3` (iframed editor).
   In dev that file is absent too, so the editor's SCSS needs module injection into the
   Gutenberg iframe — untested here and a further unknown.
4. Without this path Vite keeps none of its advantage: `vite build --watch` writes real
   `.min.css` files and keeps `block.json` working, but has no HMR at all, so a live-reload
   tool would have to be bolted back on.

With Gulp, dev behaves like production: `gulp watch` writes the same `.min.css` files
`block.json` already points at, BrowserSync injects the CSS change, and **no PHP changes
between dev and production** — no dev branch, no CORS widening, no editor-iframe work. The
arosa Gulp 5 configuration is already written and proven, and the output layout is
identical, so the choice stays reversible.

Notes for the record: `outDir: '.'` will warn permanently with this layout; the manifest
`name` field uses backslashes on Windows, so only `file` / `names[0]` are safe to key on;
and the compiled CSS is minified and re-ordered, so §7's alphabetical property order
survives in the SCSS source but not in the emitted CSS.

## 14. Repository hygiene

`.gitignore` — **in the starter theme only** (client projects have their own `.gitignore`
higher up the WordPress instance tree, per the owner):

```
node_modules/
assets/css/*.min.css
assets/css/*.min.css.map
assets/js/dist/*.min.js
assets/js/dist/*.min.js.map
parts/block/**/style.min.css
parts/block/**/style.min.css.map
parts/block/**/editor.min.css
parts/block/**/editor.min.css.map
```

Also shipped: `.editorconfig`, PHPCS with WordPress Coding Standards, stylelint.
Sources only in git; build output is generated.

## 15. Accessibility

Two small, generic fixes carried over from arosa. Proportionate, not a framework —
approximately 40 lines total, and tested for relevance before being kept.

1. **`inc/a11y-block-fixes.php`** — a `render_block` filter rewriting
   `<h6 class="wp-block-heading">Eyebrow</h6>` to `<p>Eyebrow</p>`. Designers style an
   eyebrow as an H6 for its look, but an H6 before an H2 is a heading-order violation
   flagged by Lighthouse and axe. The class is preserved, so the visual result is
   unchanged and only the semantic lie is removed.
2. **`assets/js/a11y-runtime.js`** — strips positive `tabindex` values and removes
   focusable elements from `aria-hidden` slider slides (off-screen clones otherwise stay
   focusable, so keyboard users tab into invisible content).

General standard: semantic HTML, real landmarks, keyboard-operable interactive components,
visible focus. No ARIA added purely for a checklist.

## 16. Base templates

v1 had `front-page.php`, `home.php`, `category.php`; v3 dropped them. v4 ships the full
baseline:

`front-page.php`, `home.php`, `index.php`, `archive.php`, `category.php`, `tag.php`,
`date.php`, `author.php`, `search.php`, `searchform.php`, `404.php`, `page.php`,
`single.php`, `header.php`, `footer.php`, plus `templates/_skeleton.php` as the page
template generator.

## 17. Documentation requirement

`readme.md` is a **deliverable, not an afterthought.** The client's team supports projects
without the owner's involvement, so the README must explain, in plain terms:

- what the theme is and what it is not
- prerequisites and how to install dependencies
- every build command and what it does
- how to add a new block (including the generator)
- how to add a page template
- where CPTs, taxonomies and field groups live and why
- how to run the stand check
- how to uncomment a toolbox feature
- known limitations

## 18. Open items

| Item | Owner | Note |
|---|---|---|
| Vite vs Gulp | spike, §13 | decided by the spike result, not by preference |
| Final base-template list | confirmed | §16 |
| `card-list` field set | confirmed | refine during implementation if it does not fit |
| Active theme on the local site | owner | owner switches to v4 once `style.css` and `functions.php` exist; the site currently 500s on v3 |

## 19. Out of scope (deliberately rejected)

Page builders; FSE/block theme conversion; Tailwind; PHP frameworks (Sage, Timber);
Composer/PSR-4 rewrite; a custom settings framework on top of ACF; React block editor
scripts; a "flexible content" mega-block; front-end JS frameworks; the US map component.