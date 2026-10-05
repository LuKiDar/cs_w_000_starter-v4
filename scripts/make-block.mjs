import crypto from 'node:crypto';
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
const acfDir = path.join(root, 'acf-json');

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

// ACF's own key format: `group_` / `field_` followed by a 13-character hex
// uniqid. ACF names a local field-group file after the group's KEY, not after a
// readable label, so the generated file is acf-json/<group-key>.json -- a
// readable name would never be updated when the owner re-saves the group and
// would linger as a stale duplicate beside it.
const uniqueid = () => crypto.randomBytes(8).toString('hex').slice(0, 13);
const uniqueKey = prefix => {
	let key;
	do {
		key = prefix + uniqueid();
	} while ( prefix === 'group_' && fs.existsSync(path.join(acfDir, `${key}.json`)) );
	return key;
};

const groupKey = uniqueKey('group_');

const replacements = {
	'{{SLUG}}':            slug,
	'{{TITLE}}':           displayTitle,
	'{{FUNC}}':            func,
	'{{GROUP_KEY}}':       groupKey,
	'{{FIELD_EYEBROW}}':   uniqueKey('field_'),
	'{{FIELD_HEADING}}':   uniqueKey('field_'),
	'{{FIELD_SUBHEADING}}':uniqueKey('field_'),
	'{{FIELD_CONTENT}}':   uniqueKey('field_'),
	'{{FIELD_BUTTONS}}':   uniqueKey('field_'),
	'{{FIELD_LINK}}':      uniqueKey('field_'),
	'{{FIELD_LINK_TYPE}}': uniqueKey('field_'),
};

const render = src => Object.entries(replacements)
	.reduce((acc, [from, to]) => acc.split(from).join(to), src);

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
	// group.json is an ACF field-group template, not a block file. ACF loads
	// every *.json inside a registered block folder, so a placeholder-bearing
	// copy left in parts/block/<slug>/ would be registered as a real, broken
	// field group. It is rendered to acf-json/ below instead.
	if ( name === 'group.json' ){
		continue;
	}
	fs.writeFileSync(path.join(target, name), render(fs.readFileSync(path.join(source, name), 'utf8')), 'utf8');
}

// The block's field group, named after its own key. Without it a generated
// block opens with "This block contains no editable fields."
fs.mkdirSync(acfDir, { recursive: true });
fs.writeFileSync(
	path.join(acfDir, `${groupKey}.json`),
	render(fs.readFileSync(path.join(source, 'group.json'), 'utf8')),
	'utf8'
);

console.log(`Created parts/block/${slug}/`);
console.log(`  block name:      cs/${slug}`);
console.log(`  render callback: cs__render_${func}_block`);
console.log(`  acf field group: acf-json/${groupKey}.json`);
console.log('');
console.log('Next: npm run build   (compiles style.min.css and editor.min.css)');