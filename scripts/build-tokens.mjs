/**
 * Design-token generator.
 *
 * theme.json is the single source of truth for the theme's design tokens. This
 * script mirrors the presets/custom values declared there into SCSS variables so
 * that stylesheets can reference them without hard-coding hex values or rem
 * values that already live in theme.json.
 *
 * Output: assets/scss/abstracts/_tokens.scss  (GENERATED — gitignored)
 * Run:    npm run tokens
 *
 * The generator is deterministic: the same theme.json always produces a
 * byte-identical file. Do not add timestamps, dates or run counters.
 */

import fs from 'node:fs';
import path from 'node:path';

const root      = process.cwd();
const themeJson = JSON.parse(fs.readFileSync(path.join(root, 'theme.json'), 'utf8'));

const s = themeJson.settings ?? {};
const lines = [
	'// GENERATED FILE — do not edit. Source: theme.json. Run: npm run tokens',
	'',
];

/* --- Presets ------------------------------------------------------------- */

// $color_primary, $color_gray_900, ...
lines.push('// Colors');
for ( const { slug } of s.color?.palette ?? [] ){
	lines.push(`$color_${slug.replace(/-/g, '_')}: var(--wp--preset--color--${slug});`);
}
lines.push('');

// $fontSize_small, $fontSize_x_large, ...
lines.push('// Font sizes');
for ( const { slug } of s.typography?.fontSizes ?? [] ){
	lines.push(`$fontSize_${slug.replace(/-/g, '_')}: var(--wp--preset--font-size--${slug});`);
}
lines.push('');

// $spacing_10, $spacing_20, ...
lines.push('// Spacing');
for ( const { slug } of s.spacing?.spacingSizes ?? [] ){
	lines.push(`$spacing_${slug}: var(--wp--preset--spacing--${slug});`);
}
lines.push('');

/* --- Named custom groups ------------------------------------------------- */

// $borderRadius_small, $borderRadius_medium, ...
lines.push('// Border radii');
for ( const key of Object.keys(s.custom?.['border-radius'] ?? {}) ){
	lines.push(`$borderRadius_${key.replace(/-/g, '_')}: var(--wp--custom--border-radius--${key});`);
}
lines.push('');

// $layout_padding, $layout_content, $layout_wide, $layout_block_gap, ...
lines.push('// Layout');
for ( const key of Object.keys(s.custom?.layout ?? {}) ){
	lines.push(`$layout_${key.replace(/-/g, '_')}: var(--wp--custom--layout--${key});`);
}
lines.push('');

/* --- Remaining custom tokens (generic passthrough) ----------------------- */

lines.push('// Custom (generic)');
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