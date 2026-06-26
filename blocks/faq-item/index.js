/**
 * AEO FAQ item block — single Q&A pair.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, RichText } = wp.blockEditor;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;

	registerBlockType( 'aeomatic/faq-item', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( { className: 'aeob-faq-item-editor' } );
			return el(
				'div',
				blockProps,
				el( RichText, {
					tagName: 'div',
					className: 'aeob-faq-item-editor__q',
					value: attributes.question,
					onChange: ( v ) => setAttributes( { question: v } ),
					placeholder: __( 'Question…', 'aeomatic' ),
					allowedFormats: [ 'core/bold', 'core/italic', 'core/code' ],
				} ),
				el( RichText, {
					tagName: 'div',
					className: 'aeob-faq-item-editor__a',
					value: attributes.answer,
					onChange: ( v ) => setAttributes( { answer: v } ),
					placeholder: __( 'Answer…', 'aeomatic' ),
					allowedFormats: [ 'core/bold', 'core/italic', 'core/link', 'core/code' ],
				} )
			);
		},
		save: function () {
			// Server-side rendered.
			return null;
		},
	} );
} )( window.wp );
