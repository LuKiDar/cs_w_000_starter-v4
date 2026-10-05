/**
 * Global front-end behaviour.
 *
 * Mobile menu toggle. `header.php:43` emits `.nav-toggle` with
 * `aria-expanded="false"` and `header.php:50` emits `.mobile-navigation` with the
 * `hidden` attribute. Until this file existed **nothing toggled either**, so the
 * mobile menu could not be opened (recorded by Task 17 and deferred to Task 20
 * because there was no JS pipeline). The CSS contract is already in place:
 * `assets/scss/layout/_header.scss:130` animates `.nav-toggle[aria-expanded="true"]`
 * and `:170` reveals `.mobile-navigation:not([hidden])` below `md`.
 *
 * This is the only behaviour the theme ships globally. Per-block behaviour belongs
 * in the block's own `parts/block/<slug>/script.js`.
 */

const TOGGLE_SELECTOR = '.nav-toggle';
const PANEL_SELECTOR = '.mobile-navigation';

function initMobileMenu(){
	const toggle = document.querySelector(TOGGLE_SELECTOR);

	// The header only emits the toggle when a primary menu exists, so a missing
	// one is a real, non-error state (e.g. a site with no menu assigned).
	if ( ! toggle ){
		return;
	}

	// Prefer the relationship the markup declares — `aria-controls="mobile-menu"`
	// (header.php:43) against `<nav id="mobile-menu">` (header.php:50) — and fall
	// back to the theme's class so the two cannot silently drift apart.
	const controls = toggle.getAttribute('aria-controls');
	const panel =
		( controls && document.getElementById(controls) ) ||
		document.querySelector(PANEL_SELECTOR);

	if ( ! panel ){
		return;
	}

	const isOpen = () => toggle.getAttribute('aria-expanded') === 'true';

	function open(){
		toggle.setAttribute('aria-expanded', 'true');
		panel.removeAttribute('hidden');
	}

	function close(){
		toggle.setAttribute('aria-expanded', 'false');
		panel.setAttribute('hidden', '');
	}

	function setOpen( next ){
		if ( next === isOpen() ){
			return;
		}
		if ( next ){
			open();
		} else {
			close();
		}
	}

	// A <button> fires `click` for Enter and Space as well as the pointer, so this
	// is the whole keyboard path — no separate keydown handling for activation.
	toggle.addEventListener('click', () => {
		setOpen( ! isOpen() );
	});

	// Escape closes the panel and returns focus to the control that opened it.
	document.addEventListener('keydown', ( event ) => {
		if ( event.key === 'Escape' && isOpen() ){
			close();
			toggle.focus();
		}
	});

	// The panel reuses the primary menu markup, so a link may be an in-page
	// anchor that does not navigate. Close on any link activation so the panel is
	// never left open behind the page.
	panel.addEventListener('click', ( event ) => {
		if ( event.target instanceof Element && event.target.closest('a') ){
			close();
		}
	});
}

if ( document.readyState === 'loading' ){
	document.addEventListener('DOMContentLoaded', initMobileMenu);
} else {
	initMobileMenu();
}
