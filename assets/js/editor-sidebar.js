/**
 * AEOmatic — AI based Answer Engine Optimization — Gutenberg editor sidebar.
 *
 * Three collapsible panels:
 *  1. Schema Generator  — type detector + visual field editor + live JSON preview
 *  2. Audit             — 0-100 AEO score with actionable breakdown
 *
 * Works like Webflow's schema generator:
 *  - Reads page content and auto-populates every field
 *  - User can edit any field individually
 *  - Edited fields show an "Edited" badge
 *  - Individual fields can be reset to auto-detected value
 *  - JSON preview updates live as fields change
 *  - On save, overrides persist to post meta and affect front-end output
 */
( function ( wp ) {
	'use strict';
	if ( ! wp?.plugins || ! wp?.editPost ) return;

	const { registerPlugin }                          = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { createElement: el, useState, useEffect, useCallback, useRef, Fragment } = wp.element;
	const { PanelBody, Spinner, Button, SelectControl,
	        TextControl, TextareaControl, Notice, Tooltip } = wp.components;
	const { useSelect }  = wp.data;
	const { __, sprintf } = wp.i18n;
	const apiFetch       = wp.apiFetch;

	const REST = '/aeomatic/v1';

	// ── Colour helpers ────────────────────────────────────────────────────
	const confColor = c => c >= 75 ? '#1d9e75' : c >= 50 ? '#ba7517' : '#a32d2d';
	const scoreColor = s => s >= 70 ? '#1d9e75' : s >= 40 ? '#ba7517' : '#a32d2d';

	// ── Simple syntax highlighter for the JSON preview ───────────────────
	function highlight( json ) {
		return json
			.replace( /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?)/g, m =>
				/:$/.test( m )
					? `<span style="color:#7dd3fc">${m}</span>`
					: `<span style="color:#fbbf24">${m}</span>` )
			.replace( /\b(true|false)\b/g, '<span style="color:#34d399">$1</span>' )
			.replace( /\bnull\b/g,         '<span style="color:#f87171">null</span>' )
			.replace( /\b(-?\d+(?:\.\d+)?)\b/g, '<span style="color:#a78bfa">$1</span>' );
	}

	// ── Field component — renders the right input widget per field type ──
	const Field = ( { field, value, isOverridden, onChange, onReset } ) => {
		const id = `aeob-field-${field.key}`;

		const badge = isOverridden
			? el( 'span', {
				style: { fontSize: '10px', background: '#5B3FFF', color: '#fff',
				         padding: '1px 6px', borderRadius: '999px', marginLeft: '6px' }
			  }, __( 'Edited', 'aeomatic' ) )
			: null;

		const resetBtn = isOverridden
			? el( Button, {
				isSmall: true, variant: 'tertiary',
				style:   { fontSize: '11px', color: '#888', padding: '0 4px', minHeight: 'unset' },
				onClick: () => onReset( field.key ),
			  }, __( '↺ Reset', 'aeomatic' ) )
			: null;

		const labelRow = el( 'div', {
			style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '3px' },
		},
			el( 'label', {
				htmlFor: id,
				style: { fontSize: '11px', fontWeight: 600,
				         color: field.required ? '#1d2327' : '#50575e', display: 'flex', alignItems: 'center' },
			},
				field.label,
				field.required && el( 'span', { style: { color: '#a32d2d', marginLeft: '3px' } }, '*' ),
				badge
			),
			resetBtn
		);

		const helpText = field.help
			? el( 'p', { style: { fontSize: '10px', color: '#888', margin: '2px 0 0' } }, field.help )
			: null;

		let input;
		const stringVal = value === null || value === undefined ? '' : String( value );

		if ( field.type === 'textarea' ) {
			input = el( TextareaControl, {
				id, value: stringVal, rows: 3,
				onChange, hideLabelFromVision: true, label: field.label,
			} );
		} else if ( field.type === 'select' && field.options ) {
			const options = Object.entries( field.options ).map( ( [v, l] ) => ( { value: v, label: l } ) );
			input = el( SelectControl, {
				id, value: stringVal, options, onChange,
				hideLabelFromVision: true, label: field.label,
			} );
		} else if ( field.type === 'repeater' ) {
			// Array of strings editable as a textarea, one item per line.
			const listVal = Array.isArray( value ) ? value.join( '\n' ) : stringVal;
			input = el( TextareaControl, {
				id, value: listVal, rows: 4,
				onChange: v => onChange( v.split( '\n' ).filter( Boolean ) ),
				hideLabelFromVision: true, label: field.label,
			} );
		} else if ( field.type === 'faq_repeater' ) {
			// FAQ pairs — array of {q, a} objects.
			const pairs = Array.isArray( value ) ? value : [];
			input = el( 'div', { style: { fontSize: '12px' } },
				pairs.map( ( pair, i ) =>
					el( 'div', { key: i, style: { marginBottom: '10px', padding: '10px', background: '#f8f9fa', borderRadius: '6px', border: '1px solid #e0e0e0' } },
						el( TextControl, {
							label: `Q${i + 1}`, value: pair.q || '',
							onChange: v => {
								const next = [ ...pairs ];
								next[i] = { ...next[i], q: v };
								onChange( next );
							},
						} ),
						el( TextareaControl, {
							label: `A${i + 1}`, value: pair.a || '', rows: 2,
							onChange: v => {
								const next = [ ...pairs ];
								next[i] = { ...next[i], a: v };
								onChange( next );
							},
						} ),
						el( Button, {
							isSmall: true, variant: 'tertiary', isDestructive: true,
							onClick: () => onChange( pairs.filter( ( _, j ) => j !== i ) ),
						}, __( 'Remove', 'aeomatic' ) )
					)
				),
				el( Button, {
					variant: 'secondary', isSmall: true,
					style: { marginTop: '6px' },
					onClick: () => onChange( [ ...pairs, { q: '', a: '' } ] ),
				}, __( '+ Add Q&A', 'aeomatic' ) )
			);
		} else {
			// text, url, date, number
			const inputType = field.type === 'url' ? 'url' : field.type === 'date' ? 'text' : field.type === 'number' ? 'number' : 'text';
			input = el( TextControl, {
				id, value: stringVal, type: inputType,
				onChange, hideLabelFromVision: true, label: field.label,
			} );
		}

		return el( 'div', { style: { marginBottom: '14px' } }, labelRow, input, helpText );
	};

	// ── Schema Generator panel ────────────────────────────────────────────
	const SchemaGeneratorPanel = ( { postId } ) => {
		const [ loading, setLoading ] = useState( false );
		const [ saving,  setSaving  ] = useState( false );
		const [ error,   setError   ] = useState( null );
		const [ saved,   setSaved   ] = useState( false );
		const [ data,    setData    ] = useState( null );   // field definitions + values
		const [ values,  setValues  ] = useState( {} );     // live edited values
		const [ overridden, setOverridden ] = useState( [] );
		const [ showJson, setShowJson ] = useState( false );
		const [ jsonBlocks, setJsonBlocks ] = useState( null );
		const [ jsonLoading, setJsonLoading ] = useState( false );
		const previewTimeout = useRef( null );

		const load = useCallback( () => {
			if ( ! postId ) return;
			setLoading( true ); setError( null );
			apiFetch( { path: `${REST}/schema/${postId}/fields` } )
				.then( res => {
					setData( res );
					setValues( res.values || {} );
					setOverridden( res.overridden || [] );
					setLoading( false );
				} )
				.catch( e => { setError( e.message ); setLoading( false ); } );
		}, [ postId ] );

		useEffect( () => { load(); }, [ load ] );

		// Debounced live JSON preview whenever values change.
		useEffect( () => {
			if ( ! showJson || ! postId ) return;
			clearTimeout( previewTimeout.current );
			previewTimeout.current = setTimeout( () => buildPreview(), 600 );
			return () => clearTimeout( previewTimeout.current );
		}, [ values, showJson ] );

		const buildPreview = () => {
			if ( ! postId ) return;
			setJsonLoading( true );
			apiFetch( { path: `${REST}/schema/${postId}/preview` } )
				.then( res => { setJsonBlocks( res.blocks ); setJsonLoading( false ); } )
				.catch( () => setJsonLoading( false ) );
		};

		const handleChange = ( key, val ) => {
			setValues( prev => ( { ...prev, [ key ]: val } ) );
			setOverridden( prev => prev.includes( key ) ? prev : [ ...prev, key ] );
			setSaved( false );
		};

		const handleReset = ( key ) => {
			apiFetch( { path: `${REST}/schema/${postId}/fields/reset`, method: 'POST',
				data: { key } } )
				.then( () => {
					setOverridden( prev => prev.filter( k => k !== key ) );
					load(); // reload defaults
				} )
				.catch( e => setError( e.message ) );
		};

		const handleResetAll = () => {
			if ( ! window.confirm( __( 'Reset all fields to auto-detected values?', 'aeomatic' ) ) ) return;
			apiFetch( { path: `${REST}/schema/${postId}/fields/reset`, method: 'POST', data: {} } )
				.then( () => { setOverridden( [] ); load(); } )
				.catch( e => setError( e.message ) );
		};

		const handleSave = () => {
			setSaving( true ); setError( null );
			apiFetch( { path: `${REST}/schema/${postId}/fields`, method: 'POST',
				data: { fields: values } } )
				.then( () => {
					setSaving( false ); setSaved( true );
					setTimeout( () => setSaved( false ), 2000 );
					if ( showJson ) buildPreview();
				} )
				.catch( e => { setSaving( false ); setError( e.message ); } );
		};

		const handleTypeChange = ( newType ) => {
			apiFetch( { path: `${REST}/schema/${postId}`, method: 'POST', data: { type: newType } } )
				.then( () => load() )
				.catch( e => setError( e.message ) );
		};

		if ( loading ) return el( 'div', { style: { padding: '20px', textAlign: 'center' } }, el( Spinner ) );
		if ( error ) return el( Notice, { status: 'error', isDismissible: false },
			sprintf( 'Schema Generator: %s', error ) );
		if ( ! data ) return null;

		const conf = data.confidence || 0;
		const isManual = data.source === 'user_override';

		// Type selector dropdown.
		const typeOptions = ( data.all_types || [] ).map( t => ( { value: t.type, label: t.label } ) );
		typeOptions.unshift( { value: '', label: '', disabled: true } );

		const jsonPreview = jsonBlocks
			? jsonBlocks.map( ( b, i ) =>
				el( 'pre', {
					key: i,
					style: { background: '#1d2327', color: '#a7e0b8', padding: '14px',
					         borderRadius: '6px', fontSize: '10.5px', lineHeight: '1.55',
					         overflow: 'auto', maxHeight: '320px', margin: i > 0 ? '8px 0 0' : 0,
					         whiteSpace: 'pre', fontFamily: 'ui-monospace, Menlo, monospace' },
					dangerouslySetInnerHTML: { __html: highlight( JSON.stringify( b, null, 2 ) ) },
				} )
			)
			: null;

		return el( 'div', { style: { padding: '2px 0' } },

			// ── Type selector ─────────────────────────────────────────
			el( 'div', { style: { padding: '12px 16px 10px', borderBottom: '1px solid #f0f0f1' } },
				el( 'div', { style: { fontSize: '10px', fontWeight: 700, color: '#888', textTransform: 'uppercase', letterSpacing: '.5px', marginBottom: '6px' } },
					__( 'Schema type', 'aeomatic' )
				),
				el( SelectControl, {
					value: data.type,
					options: typeOptions,
					onChange: handleTypeChange,
					hideLabelFromVision: true, label: 'Schema type',
				} ),
				el( 'div', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginTop: '6px' } },
					el( 'span', { style: { fontSize: '11px', fontWeight: 600, color: confColor( conf ) } },
						sprintf( __( 'Confidence: %d%%', 'aeomatic' ), conf )
					),
					isManual && el( 'span', { style: { fontSize: '10px', color: '#5B3FFF', background: '#ede9ff', padding: '1px 7px', borderRadius: '999px' } },
						__( 'Manual', 'aeomatic' )
					),
				),
				data.reasons?.length > 0 && el( 'details', { style: { marginTop: '6px' } },
					el( 'summary', { style: { fontSize: '11px', color: '#888', cursor: 'pointer' } },
						__( 'Why this type?', 'aeomatic' )
					),
					el( 'ul', { style: { margin: '6px 0 0', paddingLeft: '18px', fontSize: '11px', color: '#50575e' } },
						data.reasons.slice( 0, 4 ).map( ( r, i ) => el( 'li', { key: i }, r ) )
					)
				)
			),

			// ── Field editor ──────────────────────────────────────────
			el( 'div', { style: { padding: '14px 16px 0' } },
				( data.fields || [] ).map( field =>
					el( Field, {
						key:          field.key,
						field,
						value:        values[ field.key ] ?? '',
						isOverridden: overridden.includes( field.key ),
						onChange:     v => handleChange( field.key, v ),
						onReset:      handleReset,
					} )
				)
			),

			// ── Actions ───────────────────────────────────────────────
			el( 'div', { style: { padding: '10px 16px 14px', borderTop: '1px solid #f0f0f1', display: 'flex', gap: '8px', flexWrap: 'wrap', alignItems: 'center' } },
				el( Button, {
					variant: 'primary', isBusy: saving, disabled: saving,
					onClick: handleSave,
					style: { background: '#5B3FFF', borderColor: '#5B3FFF' },
				}, saving ? __( 'Saving…', 'aeomatic' ) : __( 'Save schema', 'aeomatic' ) ),
				saved && el( 'span', { style: { fontSize: '12px', color: '#1d9e75' } }, '✓ ' + __( 'Saved', 'aeomatic' ) ),
				overridden.length > 0 && el( Button, {
					variant: 'tertiary', isSmall: true,
					style: { color: '#888' },
					onClick: handleResetAll,
				}, sprintf( __( 'Reset all (%d edited)', 'aeomatic' ), overridden.length ) ),
			),

			// ── JSON preview toggle ───────────────────────────────────
			el( 'div', { style: { padding: '0 16px 14px' } },
				el( Button, {
					variant: 'secondary', isSmall: true,
					onClick: () => {
						const next = ! showJson;
						setShowJson( next );
						if ( next && ! jsonBlocks ) buildPreview();
					},
				}, showJson ? __( '▲ Hide JSON-LD', 'aeomatic' ) : __( '{ } Preview JSON-LD', 'aeomatic' ) ),

				showJson && el( 'div', { style: { marginTop: '10px' } },
					jsonLoading
						? el( Spinner )
						: jsonBlocks
							? el( Fragment, null,
								el( 'div', { style: { display: 'flex', gap: '8px', marginBottom: '6px' } },
									el( Button, {
										isSmall: true, variant: 'secondary',
										onClick: buildPreview,
									}, __( '↻ Refresh', 'aeomatic' ) ),
									el( 'a', {
										href: `https://search.google.com/test/rich-results?url=${encodeURIComponent( window.location.origin )}`,
										target: '_blank', rel: 'noopener',
										style: { fontSize: '11px', color: '#5B3FFF', display: 'flex', alignItems: 'center', gap: '3px' },
									}, __( 'Validate with Google ↗', 'aeomatic' ) ),
								),
								jsonPreview
							)
							: el( 'p', { style: { fontSize: '12px', color: '#888' } },
								__( 'No schema blocks to preview.', 'aeomatic' ) )
				)
			)
		);
	};

	// ── Audit panel ───────────────────────────────────────────────────────
	const ScoreBar = ( { score } ) => {
		const color = scoreColor( score );
		return el( 'div', { style: { margin: '8px 0 14px' } },
			el( 'div', { style: { background: '#eee', borderRadius: '6px', height: '8px', overflow: 'hidden' } },
				el( 'div', { style: { width: `${Math.min( 100, score )}%`, background: color, height: '100%' } } )
			),
			el( 'div', { style: { display: 'flex', justifyContent: 'space-between', marginTop: '5px', fontSize: '11px' } },
				el( 'span', null, __( 'AEO score', 'aeomatic' ) ),
				el( 'strong', { style: { color } }, `${score} / 100` )
			)
		);
	};

	const AuditPanel = ( { postId, isSavingPost } ) => {
		const [ data, setData ] = useState( null );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( null );

		const fetch = () => {
			if ( ! postId ) return;
			setLoading( true );
			apiFetch( { path: `${REST}/audit/${postId}` } )
				.then( res => { setData( res ); setLoading( false ); } )
				.catch( e => { setError( e.message ); setLoading( false ); } );
		};

		useEffect( () => { fetch(); }, [ postId ] );
		useEffect( () => { if ( ! isSavingPost ) fetch(); }, [ isSavingPost ] );

		if ( loading ) return el( Spinner );
		if ( error ) return el( 'p', { style: { color: '#a32d2d', fontSize: '12px' } }, error );
		if ( ! data ) return null;

		return el( 'div', null,
			el( ScoreBar, { score: data.score } ),
			data.builder_label && el( 'p', { style: { fontSize: '11px', color: '#888', margin: '0 0 4px' } },
				`${data.post_type || 'post'} · ${data.builder_label} · ${data.word_count} words`
			),
			el( Button, { variant: 'secondary', isSmall: true, onClick: fetch, style: { marginBottom: '10px' } },
				__( 'Re-run', 'aeomatic' )
			),
			( data.breakdown || [] ).map( c => {
				const icon  = c.passed ? '✓' : c.points > 0 ? '◐' : '✗';
				const color = c.passed ? '#1d9e75' : c.points > 0 ? '#ba7517' : '#a32d2d';
				return el( 'div', { key: c.key, style: { padding: '7px 0', borderBottom: '1px solid #f0f0f1' } },
					el( 'div', { style: { display: 'flex', justifyContent: 'space-between' } },
						el( 'span', { style: { color, fontWeight: 600, fontSize: '12px' } }, `${icon} ${c.label}` ),
						el( 'span', { style: { fontSize: '11px', color: '#888' } }, `${c.points}/${c.max}` )
					),
					! c.passed && el( 'div', { style: { fontSize: '11px', color: '#50575e', marginTop: '2px' } }, c.hint )
				);
			} )
		);
	};

	// ── Sidebar root ──────────────────────────────────────────────────────
	const Sidebar = () => {
		const postId = useSelect( s => s( 'core/editor' ).getCurrentPostId(), [] );
		const isSavingPost = useSelect( s =>
			s( 'core/editor' ).isSavingPost() && ! s( 'core/editor' ).isAutosavingPost(),
		[] );

		return el( Fragment, null,
			el( PluginSidebarMoreMenuItem, { target: 'aeob-sidebar' }, 'AEOmatic — AI based Answer Engine Optimization' ),
			el( PluginSidebar, { name: 'aeob-sidebar', title: 'AEOmatic — AI based Answer Engine Optimization', icon: 'chart-line' },
				el( PanelBody, {
					title: __( '✦ Schema Generator', 'aeomatic' ),
					initialOpen: true,
					style: { paddingBottom: 0 },
				},
					el( SchemaGeneratorPanel, { postId } )
				),
				el( PanelBody, { title: __( 'AEO Audit', 'aeomatic' ), initialOpen: false },
					el( AuditPanel, { postId, isSavingPost } )
				)
			)
		);
	};

	registerPlugin( 'aeomatic-sidebar', { render: Sidebar } );

} )( window.wp );
