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
const { execSync } = require('child_process');

const paths = {
	styles:  { src: 'assets/scss/*.scss', dest: 'assets/css' },
	// No task consumes `scripts` yet: nothing compiles assets/js/src/**, and package.json
	// carries no bundler. Declared for Phase 2, which adds the first JS source. Until then
	// `npm run build` emits no JS and assets/js/dist/ holds only .gitkeep — harmless,
	// because inc/enqueue.php:19 guards its enqueue with file_exists().
	scripts: { src: 'assets/js/src/**/*.js', dest: 'assets/js/dist' },
};

const sassOptions = {
	silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import'],
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
	gulp.watch('**/*.php').on('change', browserSync.reload);
	done();
}

exports.tokens = tokens;
exports.compileSass = compileSass;
exports.compileBlockSass = compileBlockSass;
exports.watch = watchFiles;
exports.build = gulp.series(tokens, gulp.parallel(compileSass, compileBlockSass));
exports.default = gulp.series(tokens, gulp.parallel(compileSass, compileBlockSass), watchFiles);
