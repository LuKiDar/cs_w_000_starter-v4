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
	// Underscore-prefixed folders (`_skeleton`, `_base-block`) are templates, not
	// blocks: `_skeleton`'s SCSS still carries {{SLUG}} placeholders, which is not
	// valid SCSS. It sorts first, so its parse error aborts the whole gulp-sass
	// stream and the build emits no block stylesheet at all while exiting 0.
	// `cs__get_blocks()` excludes the same folders from registration. This is glob
	// 11: negated patterns ('!parts/block/_*/**') and `ignore: 'parts/block/_*'`
	// were measured and exclude nothing -- only the `ignore` form below works.
	const files = glob.sync('parts/block/**/*.scss', { ignore: 'parts/block/_*/**' });
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