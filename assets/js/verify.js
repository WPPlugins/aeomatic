/**
 * AEOmatic — verification page.
 *
 * Fetches the live verification report from /wp-json/aeomatic/v1/verify
 * and renders it as a list with status icons and click-to-verify links.
 */
( function ( wp ) {
	if ( ! wp || ! wp.apiFetch ) return;

	const apiFetch = wp.apiFetch;
	const __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : ( s ) => s;

	function escapeHtml( str ) {
		return String( str == null ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	const STATUS_META = {
		ok:   { icon: '✓', label: 'OK',       cls: 'aeob-verify--ok'   },
		warn: { icon: '!', label: 'Action',   cls: 'aeob-verify--warn' },
		fail: { icon: '✗', label: 'Failed',   cls: 'aeob-verify--fail' },
		off:  { icon: '○', label: 'Disabled', cls: 'aeob-verify--off'  },
	};

	function renderResults( data ) {
		const container = document.getElementById( 'aeob-verify-results' );
		if ( ! container ) return;
		if ( ! data || ! Array.isArray( data.checks ) ) {
			container.innerHTML =
				'<div class="aeob-verify-loading">' +
				escapeHtml( __( 'No checks available.', 'aeomatic' ) ) +
				'</div>';
			return;
		}

		const html = data.checks.map( function ( c ) {
			const meta = STATUS_META[ c.status ] || STATUS_META.warn;
			let action = '';

			if ( c.id === 'schema' && c.verify_url && c.details ) {
				// Schema check: separate links for "view post" and "validate with Google".
				action =
					'<a class="button button-small" target="_blank" rel="noopener" href="' +
					escapeHtml( c.verify_url ) + '">' +
					escapeHtml( __( 'Validate with Google', 'aeomatic' ) ) + '</a> ' +
					'<a class="button button-small" target="_blank" rel="noopener" href="' +
					escapeHtml( c.details ) + '">' +
					escapeHtml( __( 'View post', 'aeomatic' ) ) + '</a>';
			} else if ( c.verify_url ) {
				action =
					'<a class="button button-small" target="_blank" rel="noopener" href="' +
					escapeHtml( c.verify_url ) + '">' +
					escapeHtml( __( 'View', 'aeomatic' ) ) + '</a>';
			}

			return (
				'<div class="aeob-verify-row ' + meta.cls + '">' +
				'<div class="aeob-verify-icon">' + escapeHtml( meta.icon ) + '</div>' +
				'<div class="aeob-verify-body">' +
				'<div class="aeob-verify-label">' + escapeHtml( c.label ) + '</div>' +
				'<div class="aeob-verify-msg">' + escapeHtml( c.message ) + '</div>' +
				'</div>' +
				'<div class="aeob-verify-action">' + action + '</div>' +
				'</div>'
			);
		} ).join( '' );

		const ts = data.checked_at ? new Date( data.checked_at * 1000 ).toLocaleTimeString() : '';
		container.innerHTML = html +
			( ts ? '<p class="description aeob-verify-ts">' +
				escapeHtml( __( 'Last checked at:', 'aeomatic' ) ) + ' ' + escapeHtml( ts ) +
				'</p>' : '' );
	}

	function renderError( msg ) {
		const container = document.getElementById( 'aeob-verify-results' );
		if ( container ) {
			container.innerHTML = '<div class="aeob-verify-loading aeob-verify--fail">' + escapeHtml( msg ) + '</div>';
		}
	}

	function loadVerification( fresh ) {
		const path = '/aeomatic/v1/verify' + ( fresh ? '?fresh=1' : '' );
		const container = document.getElementById( 'aeob-verify-results' );
		if ( container && fresh ) {
			container.innerHTML = '<div class="aeob-verify-loading">' +
				escapeHtml( __( 'Re-running checks…', 'aeomatic' ) ) + '</div>';
		}
		apiFetch( { path: path } )
			.then( renderResults )
			.catch( function ( e ) {
				renderError( e.message || __( 'Failed to load verification.', 'aeomatic' ) );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! document.getElementById( 'aeob-verify-results' ) ) return;
		loadVerification( false );

		const btn = document.getElementById( 'aeob-rerun-verify' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				loadVerification( true );
			} );
		}
	} );
} )( window.wp );
