/**
 * AEOmatic — Classic Editor metabox shell.
 *
 * Mounts the shared TabbedEditor from aeomatic-panels.js into the metabox
 * container. Post ID and title come from the localized global
 * `aeomaticClassic` — no wp.data editor store in Classic context.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp?.element || ! window.AeomaticPanels ) return;

	const cfg = window.aeomaticClassic;
	if ( ! cfg || ! cfg.postId ) return;

	const container = document.getElementById( 'aeomatic-classic-metabox-root' );
	if ( ! container ) return;

	const { createElement: el, render } = wp.element;
	const { TabbedEditor } = window.AeomaticPanels;

	// Classic has no reactive "is saving post" store. Passing undefined tells
	// the panels to skip the post-save refetch effect entirely — each panel's
	// own Refresh / Re-run button is the manual equivalent.
	render(
		el( TabbedEditor, {
			postId:       cfg.postId,
			isSavingPost: undefined,
			initialTitle: cfg.postTitle || '',
		} ),
		container
	);

} )( window.wp );
