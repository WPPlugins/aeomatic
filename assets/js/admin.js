/**
 * AEOmatic — AI based Answer Engine Optimization admin dashboard.
 *
 * Loads site-wide audit data and renders the score, distribution
 * and worst-performing posts table.
 */
( function ( wp ) {
	if ( ! wp || ! wp.apiFetch ) {
		return;
	}

	const apiFetch = wp.apiFetch;
	const __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : ( s ) => s;

	function classifyScore( s ) {
		if ( s >= 70 ) return 'good';
		if ( s >= 40 ) return 'okay';
		return 'poor';
	}

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function renderScore( data ) {
		const container = document.getElementById( 'aeob-site-score' );
		if ( ! container ) return;

		if ( ! data || ! data.count ) {
			container.innerHTML = '<span class="aeob-skeleton">' + __( 'No published posts yet.', 'aeomatic' ) + '</span>';
			return;
		}

		const cls = classifyScore( data.average );
		container.classList.add( 'score-' + cls );
		container.textContent = data.average + ' / 100';

		const dist = document.getElementById( 'aeob-distribution' );
		if ( dist ) {
			dist.innerHTML =
				'<span class="aeob-pill aeob-pill--ok">' +
				escapeHtml( __( 'Good', 'aeomatic' ) ) + ': ' + data.distribution.good +
				'</span>' +
				'<span class="aeob-pill aeob-pill--warn">' +
				escapeHtml( __( 'Okay', 'aeomatic' ) ) + ': ' + data.distribution.okay +
				'</span>' +
				'<span class="aeob-pill aeob-pill--err">' +
				escapeHtml( __( 'Poor', 'aeomatic' ) ) + ': ' + data.distribution.poor +
				'</span>';
		}
	}

	function renderWorst( data ) {
		const tbody = document.querySelector( '#aeob-worst-posts tbody' );
		if ( ! tbody ) return;
		if ( ! data || ! data.worst || ! data.worst.length ) {
			tbody.innerHTML = '<tr><td colspan="3">' + escapeHtml( __( 'Nothing to show yet. Publish or save a few posts and come back.', 'aeomatic' ) ) + '</td></tr>';
			return;
		}
		tbody.innerHTML = data.worst.map( function ( p ) {
			const cls = classifyScore( p.score );
			return (
				'<tr>' +
				'<td>' + escapeHtml( p.title ) + '</td>' +
				'<td><span class="aeob-cell-score score-' + cls + '">' + p.score + ' / 100</span></td>' +
				'<td><a href="' + escapeHtml( p.url ) + '" class="button button-small">' +
				escapeHtml( __( 'Edit', 'aeomatic' ) ) +
				'</a></td>' +
				'</tr>'
			);
		} ).join( '' );
	}

	function renderError( msg ) {
		const container = document.getElementById( 'aeob-site-score' );
		if ( container ) {
			container.innerHTML = '<span class="aeob-skeleton">' + escapeHtml( msg ) + '</span>';
		}
		const tbody = document.querySelector( '#aeob-worst-posts tbody' );
		if ( tbody ) {
			tbody.innerHTML = '<tr><td colspan="3">' + escapeHtml( msg ) + '</td></tr>';
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! document.getElementById( 'aeob-site-score' ) ) return;
		apiFetch( { path: '/aeomatic/v1/audit/site' } )
			.then( function ( res ) {
				renderScore( res );
				renderWorst( res );
			} )
			.catch( function ( e ) {
				renderError( e.message || __( 'Failed to load audit data.', 'aeomatic' ) );
			} );
	} );
} )( window.wp );
