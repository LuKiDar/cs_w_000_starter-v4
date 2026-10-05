# CStheme v4 — Phase 2 plan: theme styles and the core block library

**Date:** 2026-10-05
**Theme:** `cs_w_000_starter-v4` (`CStheme`), repo `https://github.com/LuKiDar/cs_w_000_starter-v4`
**Prerequisite:** Phase 1 (Tasks 1–11 of `2026-10-01-cs-wp-starter-v4-foundation.md`) is closed; Task 12
(ACF field groups) and Task 13 (template and toolbox removals) were dispatched from the owner's
Phase 1 review. **Task numbering continues from there, so this plan starts at Task 14.**

**Scope of this phase:** the visual layer of the theme, and the eleven core blocks that Phase 1
defined but did not build.

**The Phase 1 plan's boundary explicitly excluded both.** That was a planning failure, corrected by
the owner on 2026-10-05; see the review section of the SDD ledger.

---

## What this plan is built on

Every source reference below is **measured**, not guessed. The inventory lives in
`.superpowers/sdd/2026-10-01-cs-wp-starter-v4-foundation/phase-2-styles-recon.md` (read-only recon,
368 lines, file+line evidence). Read it before starting any task here.

**v3 is the base, and it is thinner than it looks.** Measured: `components/_modal.scss`,
`components/_tabs.scss`, `layout/_sidebar.scss`, `pages/_404.scss` and `pages/_blog.scss` are **0
bytes**; `base/_typography.scss` is 8 lines; `pages/_page.scss` is a 6-line demo. **Blog listing,
breadcrumbs and pagination have no v3 styling at all** — three of the six areas the owner named.

**The references, in her stated order:** `arosa` (first), then `millburn`, `nucleux`, `corazon` —
all under `D:/Local/<name>/app/public/wp-content/themes/<name>/assets/scss`.

**Class names are a contract read from v4's own PHP, never invented.**
- Breadcrumbs: `.breadcrumbs` + `__delimiter`, `__current`, `__paged`, plus a raw modifier suffix —
  `inc/breadcrumbs.php:26–30,37,165`.
- Pagination: `.pagination .nav-links .page-numbers` with `.prev`/`.next`/`.current`/`.disabled` —
  `inc/pagination.php:25–37`. WordPress's own `the_posts_pagination()` emits the same classes, which
  is why pagination styles work even where the include is off.
- Post card: v4 emits **`.card-post`, `.card-post__media`, `__image`, `__body`, `__title`, `__link`,
  `__excerpt`, `__more`** and `.link-arrow` (`parts/content/post-card.php`). v3 styles
  `.post-card__media-wrapper`, `__content-wrapper`, `__navigation` — **not one name matches.**
  v3's file is a structural skeleton only, and `.card-list` (the wrapper every listing template uses)
  is styled nowhere.
- Menu walker: `.menu-item-trigger`, `.caret`, `.menu-link.main-menu-link` / `.sub-menu-link`,
  `.sub-menu`, `.main-menu-item` / `.sub-menu-item`, `.menu-item-depth-*` —
  `inc/menu-walker.php:40–44,60,67`. **Arosa's `layout/_navigation.scss:1–380` targets exactly
  `.menu-item-trigger`/`.caret`/`.sub-menu`**; v3's targets the older markup with no trigger or caret.

## The owner's rulings for this phase (2026-10-05)

1. **Gulp stays.** The Vite spike was measured and rejected (design doc §13); no migration.
2. **A field group per block.** The two `Part: …` groups stay exactly as they are — inactive,
   widget-located, reusable parts — and every ACF block gets its own **separate, self-contained,
   active** group bound to `block == <namespace>/<slug>`.
3. **ACF local-JSON files are named after the group key** (`<group-key>.json`), never a readable
   title, because ACF saves to `<key>.json` regardless of the existing filename and a readable name
   becomes a stale duplicate the first time the group is edited in wp-admin. The block generator must
   do the same.
4. **Breadcrumbs and pagination: style both AND enable both includes.** Her words: "вони структурні,
   кожен архів потребує пагінації". `functions.php:66–67` are uncommented in Task 16.
5. **The staged-section rule.** Styles for a section the site does not use yet must exist in the
   theme, ready to switch on, and must **not** reach the compiled output. In v4 the mechanism is
   already the import graph: a partial whose `@use` line in `main.scss` is commented out is never
   parsed by the compiler and contributes **0 bytes** to `main.min.css`, while stylelint still lints
   it (its glob is `assets/scss/**/*.scss`). **Switching a section on means uncommenting one `@use`
   line.** Task 19 makes this a measured requirement.
6. **Superseded variants are not ported.** `layout/_header.scss:142–279` is a second, commented-out
   implementation of the *same* header. That is not a staged section — it is a dead alternate of a
   live one — so only the live rules (L5–137) travel.

## Controller rulings (delegated by the owner, 2026-10-05)

- **Global blocks layer: keep, trimmed.** `base/_blocks-base.scss` keeps the `.wp-block-button__link`
  → `.button-base` extension; `base/_blocks-styles.scss` keeps the first/last-child margin reset.
  ~30 lines covering core blocks that ship no custom SCSS. Not Arosa's 334+51.
- **No `!important` without a comment** naming the rule it has to beat. Dead commented code is never
  ported (see ruling 6).
- **Canonical token names, no alias block.** Ported partials are rewritten to v4's generated names
  (`$color_black`, `$layout_block_gap`, `$fontSize_x_large`). A starter theme that lives for years
  should not carry two vocabularies. Missing non-token constants (`$lineHeight_*`, `$header_height`)
  are added to `abstracts/_variables.scss` — **never** to the generated `_tokens.scss`.
- **Grid:** `$layout_breakout` and `$layout_columns` are added to `abstracts/_variables.scss` (they
  are semantic layout constants, not design tokens), and the `.grid`/`.col` flex half is refactored
  toward Arosa's closure mixins (`base/_grid.scss:27–122`) rather than copied from v3.

---

## Part A — Theme styles

### Task 14: Extend the abstracts layer

**Files:** Modify `assets/scss/abstracts/_variables.scss`, `abstracts/_mixins.scss`,
`abstracts/_functions.scss`.

**Why first:** every later task compiles against these. v4's `_tokens.scss` is generated from
`theme.json` and must not be edited; anything missing has to be declared here.

- [ ] **Step 1: Add the layout constants v4 lacks.** From v3 `abstracts/_variables.scss:6–11`:
  `$layout_columns`, `$layout_breakout`, `$layout_padding`, `$layout_content`, `$layout_wide`,
  `$layout_blockGap` — the last already exists in v4 as **`$layout_block_gap`**; do not add a second.
  Add `$lineHeight_*` and a `--header--height` custom property: `base/_base.scss:72–74` and
  `layout/_header.scss:44–56` both need it, and v4 has neither. Derive values from processing the
  `:root` block at v3 `abstracts/_variables.scss:61–82`, not from memory.
- [ ] **Step 2: Port the mixins the ported partials call.** From v3 `abstracts/_mixins.scss`:
  `placeholder` (L61–67), `blockCover` (L71–80), `iconMask` (L85–109), and — only if a ported file
  uses them — `getStyles` (L39–43) and `getStylesBreakpoints` (L45–57). Keep v4's existing
  `mediaMinWidth`/`mediaMaxWidth`/`mediaBetween` and do not duplicate them.
- [ ] **Step 3: `encodecolor`** from v3 `abstracts/_functions.scss:12–20` — needed by `iconMask`.
- [ ] **Step 4: Verify.** `npm run build` exits 0 and `npm run lint:css` exits 0. Then prove the new
  variables resolved rather than silently compiling to nothing: compile a probe partial that emits
  each new variable as a custom property, inspect `assets/css/main.min.css`, and delete the probe.

### Task 15: The base layer

**Files:** Create `assets/scss/base/_typography.scss`, `_content-formats.scss`, `_grid.scss`,
`_button-base.scss`, `_blocks-base.scss`, `_blocks-styles.scss`; rewrite `base/_base.scss`;
uncomment the matching `@use` lines in `assets/scss/main.scss`.

- [ ] **Step 1: `_base.scss`** from v3 `base/_base.scss:5–92` — `::selection`, `#wpadminbar *`, `html`
  box-sizing, `body` overflow/word-wrap, `.is-menu-active`/`.is-modal-active`, `.site-container`
  (v4's `header.php:17` uses it), skip link, `.site-overlay`. Rewrite `$gray_500`/`$white`/`$black`
  and `$header_height` to the names Task 14 established.
- [ ] **Step 2: `_grid.scss`** from v3 `base/_grid.scss:1–162`, with the flex half refactored toward
  Arosa `base/_grid.scss:27–122` (closure mixins, `gap: $layout_blockGap` renamed to
  `$layout_block_gap`). `.container` with `full`/`wide`/`content` line names is what every v4
  template wraps in — it must survive the refactor, so verify it on a real page rather than by reading.
- [ ] **Step 3: `_typography.scss`** — **write new.** v3's is 8 lines (`mark`), so Arosa
  `base/_typography.scss` (84 lines) is the only substantive reference. Element-level defaults for
  headings, paragraphs, links and lists, expressed in v4's tokens.
- [ ] **Step 4: `_content-formats.scss`** from v3 `base/_content-formats.scss:5–26` (list spacing) —
  directly reusable.
- [ ] **Step 5: `_button-base.scss`** from v3 `base/_button-base.scss:5–51` (`.button-base`,
  `.button-default`, `.button-outlined`). `searchform.php:20` emits `.button`.
- [ ] **Step 6: the trimmed blocks layer** (controller ruling): `_blocks-base.scss` keeps only the
  `.wp-block-button__link` → `.button-base` extension and the `.is-style-outlined` mapping;
  `_blocks-styles.scss` keeps only the first/last-child margin reset.
- [ ] **Step 7: Verify** — `npm run build` exit 0; `.container`, `.grid` and `.col` behave on a real
  page at three widths (screenshot or computed-style read, not a visual opinion); `npm run stand`
  5/5; `npm run lint:css` exit 0.

### Task 16: Components — buttons, breadcrumbs, pagination, search form

**Files:** Create `assets/scss/components/_buttons.scss`, `_breadcrumbs.scss`, `_pagination.scss`,
`_searchform.scss`; uncomment their `@use` lines in `main.scss`; **modify `functions.php` to
uncomment `inc/breadcrumbs.php` and `inc/pagination.php` (lines 66–67).**

- [ ] **Step 1: `_buttons.scss`** from v3 `components/_buttons.scss:5–10`, extended with the variants
  Arosa `components/_buttons.scss:115` carries if v4's markup supports them (check first; do not add
  classes nothing emits).
- [ ] **Step 2: `_breadcrumbs.scss`** from **nucleux `components/_breadcrumbs.scss:1–57`** — the
  closer of the two available sources (corazon `:1–63` is `inline-block`, nucleux is flex). Target
  **exactly** `.breadcrumbs` + `__delimiter`/`__current`/`__paged`. Keep the `--align-*` modifiers
  only if `inc/breadcrumbs.php` appends them verbatim (it appends the modifier raw, so they work).
- [ ] **Step 3: `_pagination.scss`** from **Arosa `components/_pagination.scss:1–67`** — it targets
  `.pagination`, `.nav-links`, `.page-numbers`, `&.prev/&.next`, `&.disabled`, `&.current` exactly.
  It uses `iconMask` for the prev/next arrows; that mixin now exists from Task 14.
- [ ] **Step 4: `_searchform.scss`** — **write new.** v4's `searchform.php` emits `.search-form`,
  `__label`, `__input`, `__submit`; corazon's `_searchform.scss` targets `.searchform` (no hyphen)
  and is 9 lines, so it is a shape reference only.
- [ ] **Step 5: enable the two includes.** Uncomment `functions.php:66–67`. This is the owner's
  ruling: both are structural.
- [ ] **Step 6: Verify — and this one is behaviour, not just build.** Breadcrumbs must now actually
  render on the site (they were off, so a passing build proves nothing): load a single post and a
  category archive and confirm the trail markup is present and matches the styled classes. Confirm
  pagination renders on a listing with more than one page — create enough posts to paginate if the
  site does not already have them, then **remove that test content**. Check the disabled bookends
  (`.prev.page-numbers.disabled`) render as spans, not links.

### Task 17: Layout — header, navigation, footer

**Files:** Create `assets/scss/layout/_header.scss`, `_navigation.scss`, `_footer.scss`; uncomment
their `@use` lines.

- [ ] **Step 1: `_header.scss`** from v3 `layout/_header.scss:5–137` **only** (ruling 6 — L142–279 is
  a superseded variant and is not ported). Two markup mismatches must be handled:
  (a) v3 draws the toggle from `.nav-toggle__icon` with pseudo-elements, while v4 emits
  **`.nav-toggle__bar`** (`header.php:44`) — Arosa `layout/_header.scss:137–195` already targets both
  `.nav-toggle::before/::after` and `.nav-toggle__bar`, the exact three-bar markup v4 uses, so take
  the toggle from Arosa;
  (b) v3 shows/hides `.mobile-navigation` via `[aria-hidden="false"]` (L134) while v4 uses the
  **`hidden` attribute** (`header.php:50`) — target `[hidden]` / `:not([hidden])` instead.
- [ ] **Step 2: `_navigation.scss`** — desktop dropdown from **Arosa `layout/_navigation.scss:1–380`**,
  which targets the walker's `.menu-item-trigger`/`.caret`/`.sub-menu`; the flat `.footer-menu` block
  from v3 `layout/_navigation.scss:99–127`. The v3 flat-list rule uses `display:none !important` on
  the sub-menu — carry the `!important` only if it is still needed to beat the dropdown rules in the
  same file, and comment it either way.
- [ ] **Step 3: `_footer.scss`** — `.site-footer` shell from v3 `layout/_footer.scss:5–11`, plus the
  `.footer-menu` rules from Step 2. **`.site-footer__inner`, `__navigation` and `__copyright`
  (`footer.php:9,11,21`) are styled in no reference at all — write them new.**
- [ ] **Step 4: Verify** the header toggle at mobile width: open and close it, confirm the bars
  render and the menu becomes visible — the `hidden`-vs-`aria-hidden` mismatch is exactly the kind of
  thing that compiles cleanly and does nothing.

### Task 18: Pages and the post card

**Files:** Create `assets/scss/pages/_archive.scss`, `_single.scss`, `_page.scss`, `_404.scss`,
`assets/scss/parts/content/_post-card.scss`; uncomment their `@use` lines.

- [ ] **Step 1: `_archive.scss`** — **write new.** No v3 file (`pages/_blog.scss` is 0 bytes) and no
  reference matches: Arosa styles `.blog-page…`, corazon `.news-feed…`, neither targets
  `.archive-header`. The contract is `.archive-header` with `__title`/`__description` (and, in
  `search.php:20,25`, `__query`/`__count`), the `.card-list` wrapper, and `.no-results`.
  **`.card-list` is styled nowhere in any reference.**
- [ ] **Step 2: `_post-card.scss`** — **write new** for `.card-post*`, using v3
  `parts/content/_post-card.scss:5–106` as the structural skeleton only (its class names do not
  apply — see the contract note at the top). Arosa `parts/content/_post-card.scss` is the closest
  *visual* reference.
- [ ] **Step 3: `_single.scss`** — write new for `.entry-title`/`.entry-content`; Arosa
  `pages/_post-single.scss:1–144` is the pattern to adapt (its own class is `.post-single`, which no
  v4 template emits).
- [ ] **Step 4: `_page.scss`** — write new for `.page-title`/`.page-content`. v3's file is a 6-line
  demo.
- [ ] **Step 5: `_404.scss`** — adapt Arosa `pages/_404.scss:1–52`; **v4's markup is
  `.error-404.not-found` + `.page-title`/`.page-content`, not Arosa's `.not-found-page`.**
- [ ] **Step 6: Verify** by loading every listing template with content: `/` (front page), `/blog/`,
  a category archive, a tag archive (served by `archive.php` after Task 13), an author archive, a
  search result page, a single post, a static page, and a 404. Confirm each renders styled and that
  `.card-list` lays out its cards.

### Task 19: The staged layer, and the build-verification that keeps it inert

**Files:** Modify `assets/scss/main.scss` (comments only, unless a new staged partial is needed).

This task exists because of the owner's staged-section rule (ruling 5). It is a verification task
first: **the mechanism must be proved, not assumed.**

- [ ] **Step 1: Prove the mechanism on a real file.** Temporarily comment an `@use` line for a
  partial that emits CSS (e.g. `base/content-formats`), rebuild, and record the byte size of
  `assets/css/main.min.css` before and after. The size must **drop**. Uncomment it again and confirm
  the size returns. **Quote both numbers in the report.** If the size does not change, the mechanism
  is not what this plan claims and the staged-layer design has to be rethought — say so rather than
  working around it.
- [ ] **Step 2: Make the staged set explicit.** `main.scss` keeps a commented `@use` block for
  sections not yet in use, each with a one-line note saying what would switch it on. The comment must
  name the **include** as well as the stylesheet where the two go together (that coupling is the
  point of the rule).
- [ ] **Step 3: Record the convention in `readme.md`.** One short subsection: a section is *ready*
  when its partial exists and its `@use` is commented; enabling it means uncommenting the `@use` and,
  where the section renders through a toolbox include, that include too. Name `main.scss` as the
  single place the switch lives.
- [ ] **Step 4: Verify** the final state compiles clean: `npm run build` exit 0, `npm run lint:css`
  exit 0, `npm run stand` 5/5, site 200. Report the final `main.min.css` size and list every partial
  that is staged (present but not in the build).

---

## Part B — The core block library

### Task 20: The JS pipeline (prerequisite — it does not exist)

**Files:** Modify `gulpfile.js`, `package.json`, `inc/enqueue.php`.

Phase 1 recorded this gap: `gulpfile.js:25` **declares** `paths.scripts` and **no gulp task consumes
it**; `package.json` carries no bundler, so `npm run build` emits no JS. `inc/enqueue.php:19` guards
the enqueue with `file_exists()`, so the absence is currently harmless — and that is exactly why it
was deferred. **Two blocks in Task 21 need JS (`accordion`, `tabs`) and the per-block script
convention (`parts/block/<slug>/script.js`) is already in the block contract**, so this must land
first.

- [ ] **Step 1:** Choose the bundler with the same discipline Task 1 used: a time-boxed spike with
  pre-registered pass criteria, written down **before** running it, so the decision cannot be
  reverse-fitted. The output layout is already fixed by the block contract and by
  `.gitignore:12–15` — one JS entry per block plus a global entry.
- [ ] **Step 2:** Wire `paths.scripts` to a real task; drop the declaration if the chosen bundler does
  not use it.
- [ ] **Step 3:** Verify with the guard the Phase 1 stand already has (`build artifacts not tracked`)
  plus `npm run stand` 5/5, and prove the emitted file is actually enqueued on a page that uses a
  block with a script.

### Task 21: The eleven blocks

**Files:** Create `parts/block/<slug>/` for each of: `hero`, `page-header`, `content-media`,
`features`, `item-list`, `accordion`, `tabs`, `card-list`, `counters`, `faq`, `form`. Each block gets
its own ACF field group (ruling 2), generated with the block — the generator now emits it
(Task 12's sibling change) with the file named after the group key (ruling 3).

**Why one task and not eleven:** after Task 12 the generator produces the five block files *and* the
ACF group in one command, so the blocks differ only in their fields, their markup and whether they
need JS. Eleven separate tasks would be eleven copies of the same steps.

- [ ] **Step 1: Generate, then edit.** For each slug: `npm run make:block <slug> "<Title>"`, then fill
  in `render.php` and the field group, then `style.scss`. **Never leave a generated block
  half-filled** — a block with the skeleton's placeholder markup is worse than no block, because it
  renders.
- [ ] **Step 2: The reference contract (`cta`) applies to all of them:** `block.json` with
  `apiVersion: 3` and an `acf.renderCallback`; `callback.php` reading fields through
  `cs__get_block_field()`; `render.php` tolerating every field being empty; escaping per value, not
  per line. **Read `parts/block/cta/render.php` before writing any of them** — it is the worked
  example, including the empty-field handling.
- [ ] **Step 3: `card-list` is the one with a documented external contract.** Phase 1 defined it: it
  "resolves a card partial by post type and calls it with a fixed `$args` contract: `post_id` and
  `modifier`" — the same contract `parts/content/post-card.php` already implements. An earlier README
  claimed it "ships" in the foundation; it does not, and Task 21 is where it becomes true.
- [ ] **Step 4: Fields, per block.** A block whose editor shows "This block contains no editable
  fields" is not finished — that was Phase 1's defect. **The per-block verification is: the block's
  own ACF group resolves and its fields appear.** Verify through ACF's API
  (`acf_get_field_groups()`, `acf_get_fields()`) and by rendering with real values; the editor check
  is the owner's.
- [ ] **Step 5: Per-block checks.** Every block: renders on a page, contributes its
  `style.min.css`/`editor.min.css` only where used, passes `npm run stand` and `npm run lint:css`.
  `accordion`/`tabs` additionally need Task 20's pipeline and a keyboard pass (they are interactive).
- [ ] **Step 6: Commit per block or per small group, never one giant commit** — a block that renders
  wrong is far easier to find in a small diff.

---

## Phase boundary

**Phase 2 delivers:** a theme with a real visual layer — base, grid, typography, buttons, header,
navigation, footer, blog listing, single, page, 404, post card, breadcrumbs and pagination, all
running on v4's own token names, with unstaged-in-use sections ready but inert — plus the eleven core
blocks, each with its own active ACF field group, generating their CSS on demand.

**Phase 3 (unchanged, separate plan):** live Lighthouse/axe probes in the stand script, a block
pattern library, PHPUnit against the WordPress test suite, and a second look at Vite now that a JS
pipeline exists and its HMR question can be asked about real block scripts rather than a probe.

**Deliberately not in Phase 2:** anything project-specific from the reference themes — Arosa's
find-form / Gravity Forms / campaign modal / locations / jobs / resources / map, millburn's property
and team work, corazon's case-study / counter / library / position / testimonial cards, its
library feed and its header brand bar and search. Those are client work; the recon lists them so a
later reader does not mistake them for starter material.
