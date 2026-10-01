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