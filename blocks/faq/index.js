/**
 * AEO FAQ container block.
 * Registers the parent that holds aeomatic/faq-item children.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { InnerBlocks, useBlockProps } = wp.blockEditor;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const TEMPLATE = [
		[ 'aeomatic/faq-item', { question: '', answer: '' } ],
		[ 'aeomatic/faq-item', { question: '', answer: '' } ],
	];

	registerBlockType( 'aeomatic/faq', {
		edit: function () {
			const blockProps = useBlockProps( { className: 'aeob-faq-editor' } );
			return el(
				'div',
				blockProps,
				el(
					'div',
					{ className: 'aeob-faq-editor__header' },
					el( 'strong', null, __( 'AEO FAQ', 'aeomatic' ) ),
					el( 'span', { className: 'aeob-faq-editor__hint' },
						__( ' — adds FAQPage schema automatically', 'aeomatic' )
					)
				),
				el( InnerBlocks, {
					allowedBlocks: [ 'aeomatic/faq-item' ],
					template: TEMPLATE,
					templateLock: false,
				} )
			);
		},
		save: function () {
			return el( InnerBlocks.Content );
		},
	} );
} )( window.wp );
