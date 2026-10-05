/**
 * Build pipeline.
 *
 *   gulp            tokens -> compile -> browser-sync watch (default)
 *   gulp build      tokens -> compile (no watcher)
 *   gulp tokens     regenerate assets/scss/abstracts/_tokens.scss from theme.json
 *
 * Sources only in git: *.min.css / *.min.js / *.map are build outputs and are
 * gitignored. Sourcemaps are kept on purpose — do not drop them.
 */

const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer');
const cleanCSS = require('gulp-clean-css');
const rename = require('gulp-rename');
const browserSync = require('browser-sync').create();
const sourcemaps = require('gulp-sourcemaps');
const glob = require('glob');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');
const esbuild = require('esbuild');

const paths = {
	styles:  { src: 'assets/scss/*.scss', dest: 'assets/css' },
	// `paths.scripts` was declared here for Phase 2 and no task consumed it (the
	// `src`/`dest` glob pair it described never matched how JS is bundled). Task 20
	// dropped it rather than leave a dead declaration: esbuild takes one entry
	// point per output, and the glob it would need (`assets/js/src/**/*.js`) would
	// treat shared modules as entries. See compileScripts below for the real inputs.
};

// Deprecation silences, each load-bearing during the port:
// - 'global-builtin' covers the legacy global Sass functions the ported abstracts
//   still call (ie-hex-str/str-index/unquote in _functions.scss, map-get/type-of in
//   _mixins.scss).
// - 'color-functions' covers legacy colour functions in the ported reference
//   sources; the port is still underway.
// - 'import' is currently hiding the legacy `@import` in
//   parts/block/cta/style.scss. Remove this silence once that partial is migrated
//   to namespaced `@use` -- the Phase 2 plan asks for that migration.
// 'mixed-decls' was removed: Sass reports that silence as obsolete (the warning no
// longer exists), and keeping it printed five advisory lines on every build that
// masked real warnings.
const sassOptions = {
	silenceDeprecations: ['color-functions', 'global-builtin', 'import'],
};

/**
 * Print a Sass failure and hand the error back so the task can reject.
 *
 * gulp-sass's own `logError` writes the error to stderr and then emits `end`
 * on the stream, which marks the task successful. That is why a broken compile
 * used to print its error and still let `npm run build` exit 0 while the
 * previous stylesheet stayed untouched on disk — observed on 2026-10-05 with a
 * partial that held `body { color: ; }`, `@use`-d from main.scss. This handler
 * keeps the readable message but does NOT swallow the error: the caller rejects
 * the task with it, so the process exits non-zero.
 */
function reportSassError(err){
	const detail = err.messageFormatted || err.messageOriginal || err.message || String(err);
	process.stderr.write(`\nError in plugin "sass"\nMessage:\n    ${detail}\n`);
	return err;
}

function tokens(done){
	execSync('node scripts/build-tokens.mjs', { stdio: 'inherit' });
	done();
}

// A Sass error must fail the task, not just print. The stream resolves the
// returned promise only when it finishes cleanly; any error rejects it.
function compileStream(src, dest){
	return new Promise((resolve, reject) => {
		const stream = gulp.src(src)
			.pipe(sourcemaps.init())
			.pipe(sass(sassOptions).on('error', err => reject(reportSassError(err))))
			.pipe(autoprefixer())
			.pipe(cleanCSS())
			.pipe(rename({ suffix: '.min' }))
			.pipe(sourcemaps.write('.'))
			.pipe(gulp.dest(dest));

		stream.on('finish', resolve);
		stream.on('error', reject);
		stream.pipe(browserSync.stream());
	});
}

function compileSass(){
	return compileStream(paths.styles.src, paths.styles.dest);
}

function compileBlockSass(){
	// Underscore-prefixed folders (`_skeleton`, `_base-block`) are templates, not
	// blocks: `_skeleton`'s SCSS still carries {{SLUG}} placeholders, which is not
	// valid SCSS. It sorts first, so its parse error aborts the whole gulp-sass
	// stream and the build emits no block stylesheet at all while exiting 0.
	// `cs__get_blocks()` excludes the same folders from registration. This is glob
	// 11: negated patterns ('!parts/block/_*/**') and `ignore: 'parts/block/_*'`
	// were measured and exclude nothing -- only the `ignore` form below works.
	const files = glob.sync('parts/block/**/*.scss', { ignore: 'parts/block/_*/**' });
	if ( ! files.length ){ return Promise.resolve(); }

	return compileStream(files, file => path.dirname(file.path));
}

/**
 * Bundle and minify JavaScript with esbuild, inside this gulp graph.
 *
 * Owner's ruling for Task 20: esbuild as a `compileScripts` task — not a second
 * toolchain and not a config file. Measured in docs/plans/2026-10-05-js-pipeline-spike.md:
 * 2 new packages (esbuild + its one native binary) versus rollup's 16, with equal
 * output, and it runs here so `npm run build` and `gulp.watch` stay single commands.
 *
 * Outputs, fixed by the block contract and .gitignore:
 *   assets/js/src/main.js         -> assets/js/dist/main.min.js         (global)
 *   parts/block/<slug>/script.js  -> parts/block/<slug>/script.min.js   (per block)
 *
 * `format: 'iife'` because `block.json`'s `"script"` loads a classic `<script>`,
 * which cannot evaluate a bare `import` — the failure the spike measured for the
 * Gulp-only path.
 *
 * A syntax error must fail the build: esbuild rejects, the rejection propagates to
 * gulp, and `npm run build` exits non-zero. This mirrors the sass tasks fixed in
 * 67afd99, where a broken compile printed its error and still exited 0.
 */
function compileScripts(){
	const jobs = [];

	// Global entry — the theme's always-on behaviour (the mobile menu toggle).
	if ( fs.existsSync('assets/js/src/main.js') ){
		jobs.push({ entry: 'assets/js/src/main.js', outfile: 'assets/js/dist/main.min.js' });
	}

	// Per-block entries. `_skeleton` is a generator template, excluded for the same
	// reason the SCSS glob excludes it: it carries placeholders, not a real script.
	for ( const entry of glob.sync('parts/block/*/script.js', { ignore: 'parts/block/_*/**' }) ){
		jobs.push({ entry, outfile: entry.replace(/script\.js$/, 'script.min.js') });
	}

	// No JS sources yet is a real state (a fresh block, or JS not needed), not an error.
	if ( ! jobs.length ){
		return Promise.resolve();
	}

	return Promise.all(jobs.map(job => esbuild.build({
		entryPoints: [job.entry],
		outfile: job.outfile,
		bundle: true,
		format: 'iife',
		minify: true,
		sourcemap: true,
		target: ['es2020'],
		logLevel: 'warning',
	}))).catch(err => {
		// esbuild's formatted message names the file, line and column. Write it, then
		// re-throw so the task rejects rather than finishing successfully.
		process.stderr.write(`\nError in plugin "esbuild"\n${err.message || err}\n`);
		throw err;
	});
}

function watchFiles(done){
	browserSync.init({
		proxy: 'https://starter-theme.local',
		open: false,
		notify: false,
		https: { rejectUnauthorized: false },
	});

	// gulp.watch reports a rejected task instead of silently finishing it, so a
	// Sass error during `npm run watch` is visible rather than swallowed.
	gulp.watch('assets/scss/**/*.scss', compileSass);
	gulp.watch('parts/block/**/*.scss', compileBlockSass);

	// JS re-emits and reloads the page. The reload is folded onto the compile task so
	// a broken script rejects the watcher task (visible) instead of reloading anyway.
	gulp.watch('assets/js/src/**/*.js', reloadScripts);
	gulp.watch('parts/block/**/script.js', reloadScripts);

	gulp.watch('**/*.php').on('change', browserSync.reload);
	done();
}

function reloadScripts(){
	return compileScripts().then(() => browserSync.reload());
}

exports.tokens = tokens;
exports.compileSass = compileSass;
exports.compileBlockSass = compileBlockSass;
exports.compileScripts = compileScripts;
exports.watch = watchFiles;
exports.build = gulp.series(tokens, gulp.parallel(compileSass, compileBlockSass, compileScripts));
exports.default = gulp.series(tokens, gulp.parallel(compileSass, compileBlockSass, compileScripts), watchFiles);
