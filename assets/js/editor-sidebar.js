/**
 * AEOmatic — Gutenberg editor sidebar shell.
 *
 * The panels live in aeomatic-panels.js as a shared TabbedEditor so the
 * Classic Editor metabox can compose the same UI. This file wraps that
 * editor in the Gutenberg PluginSidebar and reads postId / isSavingPost
 * from the block-editor store.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp?.plugins || ! wp?.editPost || ! window.AeomaticPanels ) return;

	const { registerPlugin }                           = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { createElement: el, Fragment }              = wp.element;
	const { useSelect }                                = wp.data;

	const { ExpandableEditor } = window.AeomaticPanels;

	const Sidebar = () => {
		const postId       = useSelect( s => s( 'core/editor' ).getCurrentPostId(), [] );
		const isSavingPost = useSelect( s =>
			s( 'core/editor' ).isSavingPost() && ! s( 'core/editor' ).isAutosavingPost(),
		[] );

		return el( Fragment, null,
			el( PluginSidebarMoreMenuItem, { target: 'aeob-sidebar' }, 'AEOmatic' ),
			el( PluginSidebar, { name: 'aeob-sidebar', title: 'AEOmatic', icon: 'chart-line' },
				el( ExpandableEditor, { postId, isSavingPost } )
			)
		);
	};

	registerPlugin( 'aeomatic-sidebar', { render: Sidebar } );

} )( window.wp );
