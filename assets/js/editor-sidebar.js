/**
 * AEOmatic — Gutenberg editor sidebar shell.
 *
 * The panels live in aeomatic-panels.js as a shared TabbedEditor so the
 * Classic Editor metabox can compose the same UI. This file wraps that
 * editor in the Gutenberg PluginSidebar and reads postId / isSavingPost
 * from the block-editor store.
 *
 * The slot components moved from wp.editPost to wp.editor in WordPress 6.6;
 * both are tried so older supported versions keep working.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp?.plugins || ! window.AeomaticPanels ) return;

	const slots = Object.assign( {}, wp.editPost || {}, wp.editor || {} );
	const { PluginSidebar, PluginSidebarMoreMenuItem } = slots;
	if ( ! PluginSidebar ) return;

	const { registerPlugin }                           = wp.plugins;
	const { createElement: el, Fragment }              = wp.element;
	const { useSelect }                                = wp.data;

	const { ExpandableEditor } = window.AeomaticPanels;

	const Sidebar = () => {
		const postId       = useSelect( s => s( 'core/editor' ).getCurrentPostId(), [] );
		const isSavingPost = useSelect( s =>
			s( 'core/editor' ).isSavingPost() && ! s( 'core/editor' ).isAutosavingPost(),
		[] );

		return el( Fragment, null,
			PluginSidebarMoreMenuItem && el( PluginSidebarMoreMenuItem, { target: 'aeob-sidebar' }, 'AEOmatic' ),
			el( PluginSidebar, { name: 'aeob-sidebar', title: 'AEOmatic', icon: 'chart-line' },
				el( ExpandableEditor, { postId, isSavingPost } )
			)
		);
	};

	registerPlugin( 'aeomatic-sidebar', { render: Sidebar } );

} )( window.wp );
