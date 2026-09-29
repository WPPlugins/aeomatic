/**
 * AEOmatic — page-builder mount shell.
 *
 * Page builders (Elementor, Beaver Builder, Bricks, Divi, Oxygen, WPBakery)
 * render their own full-screen editor, so a metabox or Gutenberg sidebar never
 * appears. This script pins a floating launcher button to the builder UI.
 * Clicking it opens a modal that mounts the same TabbedEditor from
 * aeomatic-panels.js — identical component tree as the Gutenberg sidebar and
 * Classic Editor metabox.
 *
 * The button is a plain DOM node on document.body, not a builder panel, so it
 * does not depend on any builder's own UI lifecycle. Configuration comes from
 * the localized `aeomaticBuilder` global (AEOMATIC_Builder_Integration).
 */
( function ( wp ) {
	'use strict';
	if ( ! wp?.element || ! window.AeomaticPanels ) return;

	// Builders load the page again in a preview iframe; only the top window
	// gets a launcher, or it would appear twice.
	try {
		if ( window.self !== window.top ) return;
	} catch ( _ ) {
		return;
	}

	const cfg = window.aeomaticBuilder;
	if ( ! cfg || ! cfg.postId ) return;

	const { createElement: el } = wp.element;
	const { TabbedEditor, mountInto } = window.AeomaticPanels;
	const i18n = cfg.i18n || {};

	// Builders stack their own panels up to around 100000; stay above them.
	const BUTTON_Z = 2147483000;
	const MODAL_Z  = 2147483001;
	const FONT     = '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif';

	// ── Launcher button ───────────────────────────────────────────────────
	function mountLauncher() {
		if ( document.getElementById( 'aeomatic-builder-launcher' ) ) return;

		const btn = document.createElement( 'button' );
		btn.id          = 'aeomatic-builder-launcher';
		btn.type        = 'button';
		btn.title       = i18n.launcher || 'AEOmatic';
		btn.textContent = i18n.launcher || 'AEOmatic';

		const pos = cfg.position && typeof cfg.position === 'object' ? cfg.position : { right: '20px', bottom: '20px' };
		const offsets = [ 'top', 'right', 'bottom', 'left' ]
			.filter( side => pos[ side ] )
			.map( side => `${ side }:${ pos[ side ] }` );

		btn.style.cssText = [
			'position:fixed',
			...offsets,
			'z-index:' + BUTTON_Z,
			'padding:10px 16px',
			'margin:0',
			'background:#5B3FFF',
			'color:#fff',
			'border:0',
			'border-radius:999px',
			`font:600 13px/1 ${ FONT }`,
			'letter-spacing:normal',
			'text-transform:none',
			'box-shadow:0 6px 20px rgba(0,0,0,0.25)',
			'cursor:pointer',
		].join( ';' );

		btn.addEventListener( 'mouseenter', () => btn.style.background = '#4a32d6' );
		btn.addEventListener( 'mouseleave', () => btn.style.background = '#5B3FFF' );
		btn.addEventListener( 'click',      openModal );

		document.body.appendChild( btn );
	}

	// ── Modal container + React root ──────────────────────────────────────
	let modalHost  = null;
	let unmount    = null;
	let keyHandler = null;

	function openModal() {
		if ( modalHost ) return;

		modalHost = document.createElement( 'div' );
		modalHost.id            = 'aeomatic-builder-modal';
		modalHost.style.cssText = [
			'position:fixed',
			'inset:0',
			'z-index:' + MODAL_Z,
			'background:rgba(0,0,0,0.5)',
			'display:flex',
			'align-items:center',
			'justify-content:center',
			'padding:24px',
			'box-sizing:border-box',
		].join( ';' );
		// Backdrop dismiss.
		modalHost.addEventListener( 'click', e => {
			if ( e.target === modalHost ) closeModal();
		} );

		const card = document.createElement( 'div' );
		card.className     = 'aeob-builder-modal';
		card.setAttribute( 'role', 'dialog' );
		card.setAttribute( 'aria-modal', 'true' );
		card.setAttribute( 'aria-label', i18n.title || 'AEOmatic' );
		card.style.cssText = [
			'background:#fff',
			'color:#1d2327',
			`font:13px/1.4 ${ FONT }`,
			'border-radius:8px',
			'box-shadow:0 20px 60px rgba(0,0,0,0.3)',
			'width:100%',
			'max-width:760px',
			'max-height:90vh',
			'display:flex',
			'flex-direction:column',
			'overflow:hidden',
			'text-align:left',
		].join( ';' );

		const header = document.createElement( 'div' );
		header.style.cssText = [
			'display:flex',
			'align-items:center',
			'justify-content:space-between',
			'padding:12px 16px',
			'border-bottom:1px solid #e0e0e0',
			'background:#fafafa',
		].join( ';' );

		const title = document.createElement( 'div' );
		title.textContent   = i18n.title || 'AEOmatic';
		title.style.cssText = `font:600 14px/1.2 ${ FONT };color:#1d2327`;

		const closeBtn = document.createElement( 'button' );
		closeBtn.type          = 'button';
		closeBtn.title         = i18n.close || 'Close';
		closeBtn.setAttribute( 'aria-label', i18n.close || 'Close' );
		closeBtn.textContent   = '✕';
		closeBtn.style.cssText = [
			'background:transparent',
			'border:0',
			'color:#666',
			'font-size:18px',
			'line-height:1',
			'padding:4px 8px',
			'margin:0',
			'cursor:pointer',
			'border-radius:4px',
			'box-shadow:none',
		].join( ';' );
		closeBtn.addEventListener( 'mouseenter', () => closeBtn.style.background = '#eee' );
		closeBtn.addEventListener( 'mouseleave', () => closeBtn.style.background = 'transparent' );
		closeBtn.addEventListener( 'click',      closeModal );

		header.appendChild( title );
		header.appendChild( closeBtn );

		const body = document.createElement( 'div' );
		body.style.cssText = 'flex:1;overflow:auto;padding:12px 16px';

		card.appendChild( header );
		card.appendChild( body );
		modalHost.appendChild( card );
		document.body.appendChild( modalHost );

		// ESC dismiss. Capture phase so the builder's own ESC handler (which
		// often closes its panels) does not act on it first.
		keyHandler = e => {
			if ( 'Escape' === e.key ) {
				e.stopPropagation();
				closeModal();
			}
		};
		document.addEventListener( 'keydown', keyHandler, true );

		// Builders have no core/editor store, so pass `undefined` for
		// isSavingPost — each panel's Refresh / Re-run button is the manual
		// refetch.
		unmount = mountInto( body, el( TabbedEditor, {
			postId:       cfg.postId,
			isSavingPost: undefined,
			initialTitle: cfg.postTitle || '',
		} ) );
	}

	function closeModal() {
		if ( ! modalHost ) return;
		if ( unmount ) {
			try { unmount(); } catch ( _ ) {}
			unmount = null;
		}
		if ( keyHandler ) {
			document.removeEventListener( 'keydown', keyHandler, true );
			keyHandler = null;
		}
		modalHost.remove();
		modalHost = null;
	}

	// ── Boot ──────────────────────────────────────────────────────────────
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', mountLauncher );
	} else {
		mountLauncher();
	}

	// Some builders rebuild <body> content after boot; put the button back
	// if it gets removed.
	new MutationObserver( () => {
		if ( ! document.getElementById( 'aeomatic-builder-launcher' ) ) mountLauncher();
	} ).observe( document.body || document.documentElement, { childList: true } );

} )( window.wp );
