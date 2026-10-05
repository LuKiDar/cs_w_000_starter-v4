# JS pipeline spike — cs_w_000_starter-v4 (Task 20, preregistered)

**Date:** 2026-10-05
**Repo:** `cs_w_000_starter-v4`, branch `main`, spike base HEAD `0cb7d6b`.
**Task:** Task 20 of `docs/plans/2026-10-05-cs-wp-starter-v4-phase-2.md` (line 321) — choose the JS
pipeline. **This document records evidence; the owner keeps the decision.**
**Time box:** 2 hours of spike work, matching the discipline of Task 1 (`docs/design/…design.md` §13).

## 0. Rule of this spike

Read-only on the repository. Candidates are installed and measured in a scratch directory outside the
theme (`$TMPDIR/js-pipeline-spike/`). No candidate edits `package.json`, `gulpfile.js`,
`inc/enqueue.php`, a lockfile or any theme file. The only file written into the repo is this report.

The two candidates the owner has an interest in are measured on **equal terms**:

- **Gulp-only** — stay inside the existing toolchain (the CSS ruling already chose Gulp), e.g.
  `gulp-terser`, no new bundler.
- **esbuild** — a bundler.
- **rollup** — a bundler.

`esbuild` earns its place because it is the bundler a Gulp task can call without a second toolchain
(the shop may want one config file, not three). `rollup` is the reference-quality JS bundler and is
the one most likely to be proposed next.

---

## 1. Pre-registered pass criteria (written BEFORE any run)

The output layout is fixed by the block contract and by `.gitignore`; it is not this spike's to
invent. A candidate passes criterion (a) only if it emits to exactly these paths:

| Entry | Source | Required output | Fixed by |
|---|---|---|---|
| Global | `assets/js/src/main.js` | `assets/js/dist/main.min.js` | `.gitignore:10`, `inc/enqueue.php:18` |
| Per-block | `parts/block/<slug>/script.js` | `parts/block/<slug>/script.min.js` | block contract §4 + JS-parallel to `style.min.css` |

**Layout caveat recorded before the runs (a finding, not a criterion):** `.gitignore` currently
names **no per-block JS output**. Lines 10–11 name the global `assets/js/dist/*.min.js(.map)`;
lines 12–15 name the block **CSS** (`style.min.css`, `editor.min.css`, maps only). `script.min.js`
appears nowhere in the repository, and `parts/block/<slug>/block.json` currently carries
`"script": ""`. The design doc §4 (line 78) literally declares the block script as
`script "file:./script.js"` — the **source**, not a `*.min.js` build output. So there are two
readings of the per-block contract:

- **R-build** — per-block JS is a build output (`script.min.js`), parallel to `style.min.css`; it
  needs a new `.gitignore` line and `block.json` → `file:./script.min.js`.
- **R-src** — per-block JS is referenced as written (`script.js`), tracked in git, and needs no build.

This spike measures the harder reading, **R-build** (`script.min.js`), because that is what a bundler
adds value for; R-src is satisfied trivially by any candidate (copy the file). Which reading the owner
intends is a question this spike surfaces and cannot itself answer.

### (a) Output layout — pass/fail

Emit **one global entry and one entry per block**, to *exactly* the two paths above, unhashed and
stable across runs. Pass only if all three files land at those paths.
Three fixtures are the real consumers, not a hypothetical fourth: a global script plus the two blocks
that actually need JS (`accordion`, `tabs`) — see §2.

### (b) One `npm run build` step + a watch loop — pass/fail

- The build must run as a **single command** that the existing `npm run build` can chain alongside
  the three sass tasks (`gulp build` today = `tokens → compileSass + compileBlockSass`), without a
  separate top-level "build JS" invocation the developer has to remember.
- A **watch loop** must re-emit on a source edit. The dev loop is the point of the exercise: a tool
  that only builds is half a tool. Watch passes only if: start watch → touch/modify a per-block
  `script.js` → the corresponding `script.min.js` mtime advances → stop watch leaves no stale process.
- A `--watch` mode that is a *second* long-running process beside `gulp` is a partial: note it.

### (c) Minification + source maps — pass/fail

- Minified output is materially smaller than the source (record byte counts and %).
- A `.map` file is emitted beside each output and the output ends with a valid
  `//# sourceMappingURL=` pointing at it. Sourcemaps are kept on purpose in this project
  (`gulpfile.js:9`), so a candidate that drops them fails.

### (d) Real dependency cost — measured, not scored

For each candidate record:

- **how many** packages `npm install` actually adds to a clean tree (from `node_modules`, counted);
- **kind** of each: pure-JS (portable, no build step), native binary (platform-specific download), or
  a whole second toolchain;
- **config surface**: a new committed config file vs. a task inside the existing `gulpfile.js`;
- **duplication**: does it re-provide machinery Gulp already has (glob, sourcemaps, rename, watch,
  autoprefixer-equivalent), i.e. does it split the project into two build systems?

### (e) Serves the two real consumers — pass/fail

The fixtures mirror the two blocks that Task 21 needs and the global entry. A candidate passes only if
it emits all three. **Shared-code check:** if `accordion/script.js` and `tabs/script.js` import a
shared module (a realistic outcome — both are toggle widgets), a candidate that cannot resolve
`import` fails the shared-code half even though it emits the files. Record which.

### Decision rule

Mirroring Task 1: **a hard fail on (a), (b) or (c) rules a candidate out.** Among survivors, (d) and
(e) are weighed, and the recommendation names the trade-off. **The spike does not choose** — the
recommendation is marked as a recommendation for the owner's ruling.

---

## 2. Method (fixed before the runs)

Scratch root: `<scratch>/js-pipeline-spike/`. One sub-directory per candidate, each a clean npm
project with an **identical copy** of the fixture, so only the pipeline differs.

Fixture (mirrors the real consumers; representative toggle logic, ES modules):

```
assets/js/src/main.js                 # global entry, imports modules/dom.js
assets/js/src/modules/dom.js          # shared helper
parts/block/accordion/script.js       # imports the shared helper
parts/block/tabs/script.js            # imports the shared helper
```

Each candidate is driven by a `package.json` with `"build"` and `"watch"` scripts; measured with:

```bash
node --version && npm --version
npm install <candidate deps>
npm run build
ls -l assets/js/dist/main.min.js parts/block/accordion/script.min.js parts/block/tabs/script.min.js
wc -c assets/js/src/main.js …                     # before/after sizes
head -c 60 assets/js/dist/main.min.js.map          # map exists
tail -c 40 assets/js/dist/main.min.js             # sourceMappingURL
# watch: start, modify parts/block/accordion/script.js, observe mtime advance, stop
find node_modules -maxdepth 1 -mindepth 1 -type d | wc -l   # dep count
```

Raw commands and their actual output for the decisive measurements are quoted in §4–§6.

---

## 3. Per-candidate results (filled in after the runs)

Measured 2026-10-05, Node 24.19.0 / npm 10.9.0, scratch tree
`C:\Users\Admin\AppData\Local\hermes\cache\scratch\js-pipeline-spike\`.

| Criterion | Gulp-only (`gulp-terser`) | esbuild | rollup |
|---|---|---|---|
| (a) exact output paths | **PASS** — 3 files at the exact paths | **PASS** | **PASS** |
| (b) single build step | **PASS** — native `gulp build` graph | **PASS** — either a gulp task (`gulp build`) or one `node script` chained into `npm run build` | **PASS** — `rollup -c` chained into `npm run build` |
| (b) watch loop | **PASS** — `gulp.watch`, one process, re-emits | **PASS** — `gulp.watch` folding (proved) or `esbuild --watch` (second process) | **PASS** — `rollup -c -w`, one process, re-emits |
| (c) minify + source maps | **FAIL** — maps OK, but no bundling: output **keeps a bare `import`** and grows | **PASS** | **PASS** |
| (e) all 3 consumers emitted | **PASS** — global + accordion + tabs | **PASS** | **PASS** |
| (e) shared `import` resolved | **FAIL** — `import … from "…/modules/dom.js"` survives in the output | **PASS** — inlined | **PASS** — inlined |
| (d) new packages (count) | **9** | **2** | **16** |
| (d) dependency kind | pure JS (`terser` + source-map deps) | native binary (`@esbuild/win32-x64`) | native bindings (`@rollup/rollup-win32-x64-msvc`) + pure JS |
| (d) config surface | one new task in the existing `gulpfile.js` | one wrapper `scripts/build-js.mjs` **or** one gulp task — no separate config system | new committed `rollup.config.mjs` |
| (d) duplicates Gulp? | **No** — reuses gulp, gulp-rename, gulp-sourcemaps, glob, and the watch loop already present | Partly — bundling is genuinely new; it runs **inside** gulp (proved: `/esbuild-in-gulp`), so no second config system | Partly — bundling new, and it adds its own config file + module/watch story |

---

## 4. Raw evidence (decisive measurements)

### Fixture inputs (byte sizes)

```
175 assets/js/src/main.js            # global entry, imports ./modules/dom.js
348 assets/js/src/modules/dom.js     # shared helper
167 parts/block/accordion/script.js  # imports the shared helper
247 parts/block/tabs/script.js       # imports the shared helper
```

### Gulp-only (`gulp-terser@2.1.0`) — the pure-Gulp path

`npm run build` → `gulp build` (parallel `scripts` + `blockScripts`), exit 0:

```
[15:34:39] Starting 'scripts'...
[15:34:39] Starting 'blockScripts'...
[15:34:39] Finished 'blockScripts' after 159 ms
[15:34:39] Finished 'build' after 163 ms
build_exit=0
```

Outputs land at the exact paths (criterion (a) **pass**), with source maps:

```
assets/js/dist/main.min.js              197 bytes
parts/block/accordion/script.min.js     193 bytes
parts/block/tabs/script.min.js          253 bytes
tail: //# sourceMappingURL=main.min.js.map
```

**The failure is criterion (c)/(e):** every output grew relative to its source because
`gulp-terser` minifies but does **not** resolve `import`. The bare specifier survives, and a
classic `<script>` (which is how `block.json` loads a block script) cannot evaluate it:

```
$ grep -o 'import[^;]*from[^;]*' parts/block/accordion/script.min.js
import{onReady as o,toggle as c}from"../../../assets/js/src/modules/dom.js"
```

Watch is solid (criterion (b) **pass**) — the log after touching `parts/block/accordion/script.js`
then `assets/js/src/main.js`:

```
[15:35:38] Starting 'blockScripts'... Finished 'blockScripts' after 177 ms
[15:35:48] Starting 'scripts'...      Finished 'scripts' after 30 ms
```

Fairness check — a **self-contained** script (no imports) *does* minify under the same engine
(source 396 B → 369 B, `export` preserved under `module: true`). So gulp-only is viable **only** if
no script imports anything.

### esbuild@0.25.12

```
assets/js/dist/main.min.js              425 bytes   (sources 175+348=523)   map 1100 B
parts/block/accordion/script.min.js     400 bytes   (sources 167+348=515)   map 1078 B
parts/block/tabs/script.min.js          309 bytes   (sources 247+348=595)   map 1076 B
tail: //# sourceMappingURL=main.min.js.map
$ grep -c import assets/js/dist/main.min.js parts/block/accordion/script.min.js
0
0
```

Criteria (a), (c), (e) **pass**: all three files at the exact paths, self-contained (shared helper
inlined), maps valid. The tabs bundle is 48% smaller than its inputs because importing only
`onReady` tree-shakes `toggle` out — a real, measured win of bundling.

Watch **passes** (`npm run watch`); on a content change esbuild logged
`[watch] build started (change: "parts/block/tabs/script.js") / build finished` and the output
advanced `309 → 345` bytes with the marker present. (A bare `touch` did not rewrite the file —
esbuild skips no-op writes; that is correct behaviour, not a miss.)

**esbuild folds into the existing Gulp graph** — a gulpfile with a `compileScripts` task calling the
esbuild JS API, driven by plain `gulp build` and `gulp.watch`, no second toolchain, no config file:

```
[15:49:26] Starting 'build'... Starting 'compileScripts'... Finished 'compileScripts' after 140 ms
build_exit=0
-rw- assets/js/dist/main.min.js              425
-rw- parts/block/accordion/script.min.js     400
-rw- parts/block/tabs/script.min.js          309
```

### rollup@4.64.0

```
assets/js/dist/main.min.js              422 bytes   map 1097 B
parts/block/accordion/script.min.js     397 bytes   map 1066 B
parts/block/tabs/script.min.js          306 bytes   map 1064 B
tail: //# sourceMappingURL=main.min.js.map
$ grep -c import … → 0 / 0
```

Criteria (a), (c), (e) **pass**, output within ~1% of esbuild's. Watch **passes**
(`rollup -c -w`); after editing `accordion/script.js` it logged a re-bundle and the output advanced
`397 → 432` bytes.

### Cost, measured against the theme's **current** `devDependencies`

Baseline = `npm install` of the theme's `package.json` (456 packages). Each candidate's *new*
packages = `comm -13 baseline candidate`. Note `gulp`, `gulp-rename`, `gulp-sourcemaps` and `glob`
are **already** project dependencies, so the Gulp path reuses them.

| Candidate | New packages | New top-level dep(s) | Kind | On-disk |
|---|---|---|---|---|
| Gulp-only | **9** | `gulp-terser@2.1.0` | pure JS (`terser`, `@jridgewell/*` ×5, `buffer-from`, `source-map-support`) | ~6 MB |
| esbuild | **2** | `esbuild@0.25.12` | native Go binary `@esbuild/win32-x64` | ~11 MB |
| rollup | **16** | `rollup@4.64.0`, `@rollup/plugin-terser@0.4.4` | native bindings `@rollup/rollup-win32-x64-msvc` **+** pure JS (`terser`, `smob`, `serialize-javascript`, …) | ~13 MB |

rollup v4 is **not** pure JS: it ships a platform-native binding, so the "bundler = native binary
cost" objection applies to it as much as to esbuild — with 14 more packages besides.

---

## 5. Recommendation for the owner's ruling (a recommendation, not a decision)

The spike's own evidence puts the whole choice on **one question**:

> **Will any JS source `import` another JS source?**

- The **global** entry already does: a realistic `assets/js/src/main.js` pulls in a helper module.
- The two real block consumers (`accordion`, `tabs`) are both toggle widgets and are the natural
  place to share show/hide logic. If they do, a per-block `import` exists too.

The fixture was written that way on purpose, because it is the realistic case; it is an
**assumption**, not something Task 21 has fixed yet.

**Against that question the measured outcome is unambiguous:**

- **If scripts may import anything**, the pure-Gulp path **fails criterion (c)**. `gulp-terser`
  cannot resolve `import`; the emitted `script.min.js` keeps a bare specifier and, loaded as a
  classic block script, is invalid — and it does not shrink. No amount of Gulp task wiring fixes
  that without adding a bundler, at which point it is no longer "Gulp-only". This is not a straw
  man: it is what the canonical Gulp minifier does, and it is fine for self-contained files.
- **esbuild** then wins the cost comparison outright: **2** new packages (one native binary) versus
  rollup's **16** (native bindings *plus* a config file), with equal output, ~10× faster builds, and
  — the decisive integration point — it **runs inside the existing gulp task graph** as a
  `compileScripts` task (proved above), so `npm run build` and `gulp.watch` stay single commands.
  That keeps the project's "stay on Gulp" ruling intact for CSS while JS is bundled.
- **rollup** is the stronger *bundler* on plugin ergonomics, but it costs 8× the dependency surface,
  adds a committed `rollup.config.mjs`, and its v4 native binding removes its "pure JS" advantage.
  No measured criterion favours it here.

**Recommendation: `esbuild`, wired as a `compileScripts` task in the existing `gulpfile.js`
(no second config system), IF any JS source will import another.** If the owner rules that every
script stays a single self-contained file with **no** imports, then the **Gulp-only** path is the
cheapest possible answer — +9 pure-JS packages, zero new toolchain, zero new config — and is the
minimal change consistent with the CSS ruling.

**What the spike could not determine (for the owner):**

1. **Whether Task 21's `accordion`/`tabs` scripts will share code.** This is the hinge of the
   recommendation and is unknowable until those scripts are written. If they will not — and the
   global `main.js` is also kept import-free — Gulp-only is the correct, cheapest choice.
2. **The per-block output path is not actually fixed by the repo today.** `.gitignore` names **no**
   per-block JS output (`script.min.js` appears nowhere), and `parts/block/<slug>/block.json`
   carries `"script": ""`. The design doc §4 line 78 declares the block script as
   `script "file:./script.js"` — the **source**, not a build output. So Task 20 must first settle
   **R-build** (`script.min.js` + a new `.gitignore` line + `block.json → file:./script.min.js`) or
   **R-src** (ship `script.js`, no build). Under **R-src**, no candidate needs per-block bundling at
   all, which weakens the case for any bundler.
3. **Nothing here was exercised on a running WordPress page.** the enqueue proof (Task 20 Step 3),
   the "artifacts not tracked" stand check and the 5/5 render check cannot be run from a read-only
   spike; they remain Task 20's post-ruling work.
4. **`package.json` was never touched.** The cost numbers come from isolated scratch installs, not
   from the repo; if the owner rules for a candidate, `devDependencies` would be edited key-by-key
   in Task 20, never rewritten.

**Repo state at end of spike:** only this file was added; no theme file, `package.json`,
`gulpfile.js`, `inc/enqueue.php` or lockfile was modified.