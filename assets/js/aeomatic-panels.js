/**
 * AEOmatic — shared editor panels.
 *
 * All 5 panels (Schema Generator, Audit, Gaps, Readiness, Entity Scanner, Ask AI)
 * live here so both the Gutenberg sidebar (editor-sidebar.js) and the Classic
 * Editor metabox (classic-editor-metabox.js) can compose them. Every panel takes
 * `postId` (and where relevant `isSavingPost`) as a prop — no direct wp.data
 * calls — so the same components work in both editor contexts.
 *
 * Exposed on `window.AeomaticPanels`.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp?.element || ! wp?.components ) return;

	const { createElement: el, useState, useEffect, useCallback, useRef, Fragment } = wp.element;
	const { PanelBody, Spinner, Button, SelectControl, TabPanel,
	        TextControl, TextareaControl, Notice } = wp.components;
	const { useSelect }   = wp.data || {};
	const { __, sprintf } = wp.i18n;
	const apiFetch        = wp.apiFetch;

	const REST = '/aeomatic/v1';

	// ── Colour helpers ────────────────────────────────────────────────────
	const confColor  = c => c >= 75 ? '#1d9e75' : c >= 50 ? '#ba7517' : '#a32d2d';
	const scoreColor = s => s >= 70 ? '#1d9e75' : s >= 40 ? '#ba7517' : '#a32d2d';
	const sevColor   = s => s === 'high' ? '#a32d2d' : s === 'medium' ? '#ba7517' : '#6e6e73';

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
			const listVal = Array.isArray( value ) ? value.join( '\n' ) : stringVal;
			input = el( TextareaControl, {
				id, value: listVal, rows: 4,
				onChange: v => onChange( v.split( '\n' ).filter( Boolean ) ),
				hideLabelFromVision: true, label: field.label,
			} );
		} else if ( field.type === 'faq_repeater' ) {
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
		const [ loading, setLoading ]     = useState( false );
		const [ saving,  setSaving  ]     = useState( false );
		const [ error,   setError   ]     = useState( null );
		const [ saved,   setSaved   ]     = useState( false );
		const [ data,    setData    ]     = useState( null );
		const [ values,  setValues  ]     = useState( {} );
		const [ overridden, setOverridden ] = useState( [] );
		const [ showJson, setShowJson ]   = useState( false );
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

		useEffect( () => {
			if ( ! showJson || ! postId ) return;
			clearTimeout( previewTimeout.current );
			previewTimeout.current = setTimeout( () => buildPreview(), 350 );
			return () => clearTimeout( previewTimeout.current );
		}, [ values, showJson ] );

		const buildPreview = () => {
			if ( ! postId ) return;
			setJsonLoading( true );
			apiFetch( {
				path:   `${REST}/schema/${postId}/preview`,
				method: 'POST',
				data:   { values },
			} )
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
					load();
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

	// ── Score bar (shared by Audit and Gaps) ─────────────────────────────
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

	// ── Audit panel ───────────────────────────────────────────────────────
	const AuditPanel = ( { postId, isSavingPost } ) => {
		const [ data, setData ]       = useState( null );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ]     = useState( null );

		const fetch = () => {
			if ( ! postId ) return;
			setLoading( true );
			apiFetch( { path: `${REST}/audit/${postId}` } )
				.then( res => { setData( res ); setLoading( false ); } )
				.catch( e => { setError( e.message ); setLoading( false ); } );
		};

		useEffect( () => { fetch(); }, [ postId ] );
		useEffect( () => { if ( isSavingPost === false ) fetch(); }, [ isSavingPost ] );

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

	// ── Gaps panel ────────────────────────────────────────────────────────
	const GapsPanel = ( { postId, isSavingPost } ) => {
		const [ data, setData ]       = useState( null );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ]     = useState( null );
		const [ fixing, setFixing ]   = useState( null );
		const [ toast, setToast ]     = useState( null );

		const fetchGaps = ( force = false ) => {
			if ( ! postId ) return;
			setLoading( true );
			const path = `${REST}/citation-gaps/${postId}${force ? '?force=1' : ''}`;
			apiFetch( { path } )
				.then( res => { setData( res ); setLoading( false ); setError( null ); } )
				.catch( e => { setError( e.message ); setLoading( false ); } );
		};

		const applyFix = ( gapCode, fixAction ) => {
			if ( ! postId || ! fixAction ) return;
			setFixing( gapCode );
			apiFetch( {
				path:   `${REST}/citation-gaps/fix/${postId}`,
				method: 'POST',
				data:   { code: fixAction.code, params: fixAction.params || {} },
			} )
				.then( res => {
					setFixing( null );
					setToast( res && res.message ? res.message : __( 'Fix applied.', 'aeomatic' ) );
					if ( res && res.gaps ) { setData( res.gaps ); }
					setTimeout( () => setToast( null ), 3500 );
				} )
				.catch( e => {
					setFixing( null );
					setToast( __( 'Fix failed: ', 'aeomatic' ) + ( e && e.message ? e.message : 'unknown' ) );
					setTimeout( () => setToast( null ), 5000 );
				} );
		};

		useEffect( () => { fetchGaps(); }, [ postId ] );
		useEffect( () => { if ( isSavingPost === false ) fetchGaps(); }, [ isSavingPost ] );

		if ( loading ) return el( Spinner );
		if ( error ) return el( 'p', { style: { color: '#a32d2d', fontSize: '12px' } }, error );
		if ( ! data ) return null;

		const gaps = ( data.gaps || [] ).filter( g => g.severity !== 'low' ).slice( 0, 5 );

		return el( 'div', null,
			el( ScoreBar, { score: data.score } ),
			el( 'p', { style: { fontSize: '11px', color: '#888', margin: '0 0 8px' } },
				gaps.length === 0
					? __( 'No high or medium gaps found. Nice.', 'aeomatic' )
					: sprintf( __( 'Showing top %d issue(s). Full list on the Citation Gaps page.', 'aeomatic' ), gaps.length )
			),
			el( Button, { variant: 'secondary', isSmall: true, onClick: () => fetchGaps( true ), style: { marginBottom: '10px' } },
				__( 'Re-analyse', 'aeomatic' )
			),
			toast && el( 'div', {
				style: {
					background: '#eef7f0', border: '1px solid #a3d9b5', color: '#1a5c33',
					padding: '6px 10px', borderRadius: '4px', fontSize: '11px', marginBottom: '8px'
				}
			}, toast ),
			gaps.map( g => (
				el( 'div', { key: g.code, style: { padding: '7px 0', borderBottom: '1px solid #f0f0f1' } },
					el( 'div', { style: { display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', fontWeight: 600, color: sevColor( g.severity ) } },
						el( 'span', { style: { display: 'inline-block', width: '8px', height: '8px', borderRadius: '50%', background: sevColor( g.severity ) } } ),
						g.title
					),
					el( 'div', { style: { fontSize: '11px', color: '#50575e', marginTop: '2px' } }, g.fix_hint ),
					g.fix_action && el( Button, {
						variant: 'primary',
						isSmall: true,
						disabled: fixing === g.code,
						onClick: () => applyFix( g.code, g.fix_action ),
						style: { marginTop: '6px' }
					}, fixing === g.code
						? __( 'Applying…', 'aeomatic' )
						: ( g.fix_action.label || __( 'Quick fix', 'aeomatic' ) )
					)
				)
			) )
		);
	};

	// ── AI Readiness panel ────────────────────────────────────────────────
	const ReadinessPanel = ( { postId, isSavingPost } ) => {
		const [ data,    setData    ] = useState( null );
		const [ loading, setLoading ] = useState( true );
		const [ error,   setError   ] = useState( '' );

		const load = useCallback( ( force ) => {
			if ( ! postId ) return;
			setLoading( true );
			setError( '' );
			apiFetch( { path: `${REST}/ai-readiness/${postId}${ force ? '?force=true' : '' }` } )
				.then( setData )
				.catch( e => setError( ( e && e.message ) || __( 'Request failed', 'aeomatic' ) ) )
				.finally( () => setLoading( false ) );
		}, [ postId ] );

		useEffect( () => { load( false ); }, [ load ] );
		useEffect( () => { if ( isSavingPost === false ) load( false ); }, [ isSavingPost, load ] );

		if ( loading ) return el( Spinner, null );
		if ( error   ) return el( Notice, { status: 'error', isDismissible: false }, error );
		if ( ! data  ) return null;

		const overall = data.overall_score || 0;
		const cats    = data.categories || [];

		const scorePill = ( n ) => el( 'span', {
			style: {
				display: 'inline-block', minWidth: 42, textAlign: 'center',
				padding: '2px 8px', borderRadius: 999, background: scoreColor( n ),
				color: '#fff', fontWeight: 700,
			},
		}, `${n}/100` );

		return el( Fragment, null,
			el( 'div', { style: { display: 'flex', alignItems: 'center', gap: 12, marginBottom: 10 } },
				el( 'div', { style: { fontSize: 32, fontWeight: 700, color: scoreColor( overall ) } }, overall ),
				el( 'div', null,
					el( 'div', { style: { fontSize: 11, textTransform: 'uppercase', color: '#50575e' } }, __( 'Overall AI Readiness', 'aeomatic' ) ),
					el( 'div', null, overall >= 70 ? __( 'Great', 'aeomatic' ) : ( overall >= 40 ? __( 'Needs work', 'aeomatic' ) : __( 'At risk', 'aeomatic' ) ) )
				)
			),
			cats.map( ( c ) => el( 'div', {
				key: c.category,
				style: { border: '1px solid #dcdcde', borderRadius: 4, padding: 10, marginBottom: 8 },
			},
				el( 'div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
					el( 'strong', { style: { textTransform: 'capitalize' } }, c.category ),
					scorePill( c.score )
				),
				( c.issues && c.issues.length )
					? el( 'ul', { style: { margin: '8px 0 0 18px', color: '#a32d2d' } },
						c.issues.map( ( i, k ) => el( 'li', { key: 'i' + k }, i ) )
					)
					: null,
				( c.recommendations && c.recommendations.length )
					? el( 'ul', { style: { margin: '6px 0 0 18px', color: '#1d5e40' } },
						c.recommendations.map( ( r, k ) => el( 'li', { key: 'r' + k }, r ) )
					)
					: null
			) ),
			el( Button, { isSecondary: true, onClick: () => load( true ), style: { marginTop: 8 } },
				__( 'Recompute (skip cache)', 'aeomatic' )
			)
		);
	};

	// ── Entity Scanner panel ──────────────────────────────────────────────
	const EntityScannerPanel = ( { postId, isSavingPost } ) => {
		const [ data,    setData    ] = useState( null );
		const [ loading, setLoading ] = useState( true );
		const [ error,   setError   ] = useState( '' );

		const load = useCallback( ( force ) => {
			if ( ! postId ) return;
			setLoading( true );
			setError( '' );
			apiFetch( { path: `${REST}/entity-scan/${postId}${ force ? '?force=true' : '' }` } )
				.then( setData )
				.catch( e => setError( ( e && e.message ) || __( 'Request failed', 'aeomatic' ) ) )
				.finally( () => setLoading( false ) );
		}, [ postId ] );

		useEffect( () => { load( false ); }, [ load ] );
		useEffect( () => { if ( isSavingPost === false ) load( false ); }, [ isSavingPost, load ] );

		if ( loading ) return el( Spinner, null );
		if ( error   ) return el( Notice, { status: 'error', isDismissible: false }, error );
		if ( ! data  ) return null;

		const cov = data.coverage_score || 0;
		const entities = data.entities || [];
		const title = ( s ) => s.replace( /_/g, ' ' ).replace( /\b\w/g, c => c.toUpperCase() );

		return el( Fragment, null,
			el( 'div', { style: { display: 'flex', alignItems: 'center', gap: 12, marginBottom: 10 } },
				el( 'div', { style: { fontSize: 32, fontWeight: 700, color: scoreColor( cov ) } }, cov ),
				el( 'div', null,
					el( 'div', { style: { fontSize: 11, textTransform: 'uppercase', color: '#50575e' } }, __( 'Entity Coverage', 'aeomatic' ) ),
					el( 'div', null, cov >= 70 ? __( 'Strong', 'aeomatic' ) : ( cov >= 40 ? __( 'Partial', 'aeomatic' ) : __( 'Weak', 'aeomatic' ) ) )
				)
			),
			entities.map( ( e ) => el( 'div', {
				key: e.entity_type,
				style: { border: '1px solid #dcdcde', borderRadius: 4, padding: 8, marginBottom: 6 },
			},
				el( 'div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
					el( 'strong', null, title( e.entity_type ) ),
					el( 'span', {
						style: {
							minWidth: 42, textAlign: 'center', padding: '2px 8px', borderRadius: 999,
							background: scoreColor( e.confidence ), color: '#fff', fontWeight: 700, fontSize: 12,
						},
					}, e.confidence )
				),
				( e.missing_signals && e.missing_signals.length )
					? el( 'ul', { style: { margin: '6px 0 0 18px', color: '#a32d2d', fontSize: 12 } },
						e.missing_signals.slice( 0, 3 ).map( ( m, k ) => el( 'li', { key: 'm' + k }, m ) )
					)
					: null,
				( e.recommendations && e.recommendations.length )
					? el( 'ul', { style: { margin: '4px 0 0 18px', color: '#1d5e40', fontSize: 12 } },
						e.recommendations.slice( 0, 2 ).map( ( r, k ) => el( 'li', { key: 'r' + k }, r ) )
					)
					: null
			) ),
			el( Button, { isSecondary: true, onClick: () => load( true ), style: { marginTop: 8 } },
				__( 'Rescan (skip cache)', 'aeomatic' )
			)
		);
	};

	// ── Ask AI panel ──────────────────────────────────────────────────────
	// Reads the post title from wp.data when available (Gutenberg); Classic
	// callers pass `initialTitle` explicitly so this component stays reusable.
	const AskAiPanel = ( { initialTitle } ) => {
		const gutenbergTitle = useSelect && wp.data
			? useSelect( s => s( 'core/editor' )?.getEditedPostAttribute( 'title' ) || '', [] )
			: null;
		const seedTitle = gutenbergTitle !== null ? gutenbergTitle : ( initialTitle || '' );

		const [ query, setQuery ]   = useState( '' );
		const [ copied, setCopied ] = useState( '' );

		useEffect( () => { setQuery( seedTitle ); }, [ seedTitle ] );

		const engines = [
			{ name: 'ChatGPT',    color: '#10a37f',
			  build: q => `https://chatgpt.com/?q=${ encodeURIComponent( q ) }&hints=search` },
			{ name: 'Perplexity', color: '#20808d',
			  build: q => `https://www.perplexity.ai/search?q=${ encodeURIComponent( q ) }` },
			{ name: 'Google AI',  color: '#4285f4',
			  build: q => `https://www.google.com/search?q=${ encodeURIComponent( q ) }` },
			{ name: 'Claude',     color: '#c88a53',
			  build: () => 'https://claude.ai/new', copy: true },
		];

		const askEngine = engine => {
			const q = query.trim();
			if ( ! q ) return;
			if ( engine.copy && navigator.clipboard ) {
				navigator.clipboard.writeText( q ).then( () => {
					setCopied( engine.name );
					setTimeout( () => setCopied( '' ), 2500 );
				} );
			}
			window.open( engine.build( q ), '_blank', 'noopener,noreferrer' );
		};

		return el( 'div', { style: { padding: '4px 0' } },
			el( 'p', {
				style: { fontSize: '12px', color: '#50575e', margin: '0 0 8px', lineHeight: 1.4 },
			}, __( 'Type your question, then click an AI engine. It opens in a new tab — see for yourself whether your site gets cited.', 'aeomatic' ) ),

			el( TextControl, {
				label: __( 'Question to ask', 'aeomatic' ),
				value: query,
				onChange: setQuery,
				placeholder: __( 'e.g. Best espresso machines under $500', 'aeomatic' ),
			} ),

			el( 'div', {
				style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '6px', marginTop: '8px' },
			},
				engines.map( engine => el( Button, {
					key: engine.name,
					variant: 'secondary',
					onClick: () => askEngine( engine ),
					disabled: ! query.trim(),
					style: {
						justifyContent: 'center',
						borderColor: engine.color,
						color: engine.color,
						fontWeight: 600,
					},
				}, sprintf( __( 'Ask %s', 'aeomatic' ), engine.name ) ) )
			),

			copied && el( 'div', {
				style: { marginTop: '8px' },
			}, el( Notice, {
				status: 'info',
				isDismissible: false,
			}, sprintf( __( 'Question copied to clipboard — paste it in the %s tab.', 'aeomatic' ), copied ) ) ),

			el( 'p', {
				style: { fontSize: '10px', color: '#888', margin: '10px 0 0', lineHeight: 1.4 },
			}, __( 'If your site is quoted in the AI answer, your AEO signals are working. If not, check the AI Citation Gaps panel above.', 'aeomatic' ) )
		);
	};

	// ── Tabbed editor — composes the six panels into a tab layout ────────
	// Shared by both the Gutenberg sidebar and the Classic Editor metabox so
	// tab order and labels stay in sync between contexts. Renders the active
	// panel only, so hidden panels do not run their `useEffect` fetches
	// until the user opens the tab.
	const TabbedEditor = ( { postId, isSavingPost, initialTitle } ) => {
		const tabs = [
			{ name: 'schema',    title: __( 'Schema', 'aeomatic' ),    className: 'aeob-tab-schema' },
			{ name: 'audit',     title: __( 'Audit', 'aeomatic' ),     className: 'aeob-tab-audit' },
			{ name: 'gaps',      title: __( 'Gaps', 'aeomatic' ),      className: 'aeob-tab-gaps' },
			{ name: 'readiness', title: __( 'Readiness', 'aeomatic' ), className: 'aeob-tab-readiness' },
			{ name: 'entities',  title: __( 'Entities', 'aeomatic' ),  className: 'aeob-tab-entities' },
			{ name: 'ask',       title: __( 'Ask AI', 'aeomatic' ),    className: 'aeob-tab-ask' },
		];

		const renderTab = ( tab ) => {
			// Wrap each panel's body in a padded container so it does not
			// hug the tab bar's bottom border.
			const wrap = ( child ) => el( 'div', { style: { padding: '14px 4px 4px' } }, child );
			switch ( tab.name ) {
				case 'schema':    return wrap( el( SchemaGeneratorPanel, { postId } ) );
				case 'audit':     return wrap( el( AuditPanel,           { postId, isSavingPost } ) );
				case 'gaps':      return wrap( el( GapsPanel,            { postId, isSavingPost } ) );
				case 'readiness': return wrap( el( ReadinessPanel,       { postId, isSavingPost } ) );
				case 'entities':  return wrap( el( EntityScannerPanel,   { postId, isSavingPost } ) );
				case 'ask':       return wrap( el( AskAiPanel,           { initialTitle } ) );
			}
			return null;
		};

		return el( TabPanel, {
			className: 'aeob-tabs',
			activeClass: 'is-active',
			tabs,
		}, renderTab );
	};

	window.AeomaticPanels = {
		SchemaGeneratorPanel,
		AuditPanel,
		GapsPanel,
		ReadinessPanel,
		EntityScannerPanel,
		AskAiPanel,
		TabbedEditor,
	};

} )( window.wp );
