/**
 * AEOmatic — AI Access screen (Crawler check + What AI sees).
 *
 * Talks to /aeomatic/v1/ai-access/*. The crawler check runs one crawler per
 * request so no single request can hit a server timeout.
 */
( function ( wp ) {
	if ( ! wp || ! wp.apiFetch ) {
		return;
	}

	var apiFetch = wp.apiFetch;
	var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function ( s ) { return s; };
	var sprintf = wp.i18n && wp.i18n.sprintf ? wp.i18n.sprintf : function ( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d+\$)?[sd]/g, function () { return args[ i++ ]; } );
	};

	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function $( id ) {
		return document.getElementById( id );
	}

	function timeAgo( ts ) {
		if ( ! ts ) {
			return '';
		}
		var secs = Math.max( 0, Math.round( Date.now() / 1000 - ts ) );
		if ( secs < 90 ) {
			return __( 'just now', 'aeomatic' );
		}
		var mins = Math.round( secs / 60 );
		if ( mins < 90 ) {
			return sprintf( __( '%d minutes ago', 'aeomatic' ), mins );
		}
		var hours = Math.round( mins / 60 );
		if ( hours < 36 ) {
			return sprintf( __( '%d hours ago', 'aeomatic' ), hours );
		}
		return sprintf( __( '%d days ago', 'aeomatic' ), Math.round( hours / 24 ) );
	}

	function errorText( err ) {
		return ( err && err.message ) ? err.message : __( 'The request failed. Try again.', 'aeomatic' );
	}

	// ── Crawler check ─────────────────────────────────────────────────────

	var PURPOSES = [
		{ key: 'answer', title: __( 'AI answer engines', 'aeomatic' ), hint: __( 'These fetch pages to answer questions in ChatGPT, Claude, Perplexity and DuckDuckGo. Blocking them removes you from those answers.', 'aeomatic' ) },
		{ key: 'search', title: __( 'Search engines with AI answers', 'aeomatic' ), hint: __( 'Google AI Overviews and AI Mode use Googlebot; Microsoft Copilot answers from Bing.', 'aeomatic' ) },
		{ key: 'training', title: __( 'AI training crawlers', 'aeomatic' ), hint: __( 'These collect content to train models. Blocking them is a valid choice and does not remove you from AI search answers.', 'aeomatic' ) }
	];

	function verdictPill( c ) {
		var map = {
			ok: [ 'aeob-pill--ok', __( 'Can read', 'aeomatic' ) ],
			warn: [ 'aeob-pill--warn', __( 'Check', 'aeomatic' ) ],
			blocked: [ 'aeob-pill--err', __( 'Blocked', 'aeomatic' ) ],
			robots: 'training' === c.purpose
				? [ 'aeob-pill--muted', __( 'Opted out', 'aeomatic' ) ]
				: [ 'aeob-pill--err', __( 'Blocked by robots.txt', 'aeomatic' ) ],
			pending: [ 'aeob-pill--muted', __( 'Checking…', 'aeomatic' ) ],
			failed: [ 'aeob-pill--err', __( 'Check failed', 'aeomatic' ) ]
		};
		var m = map[ c.verdict ] || map.pending;
		return '<span class="aeob-pill ' + m[ 0 ] + '">' + esc( m[ 1 ] ) + '</span>';
	}

	function robotsCell( c ) {
		if ( ! c.robots ) {
			return '—';
		}
		return c.robots.allowed
			? '<span class="aeob-access-yes">' + esc( __( 'Allowed', 'aeomatic' ) ) + '</span>'
			: '<span class="aeob-access-no">' + esc( __( 'Disallowed', 'aeomatic' ) ) + '</span>';
	}

	function accessCell( c ) {
		if ( ! c.pages ) {
			return '—';
		}
		if ( ! c.fetch ) {
			return '<span class="description">' + esc( __( 'robots.txt only', 'aeomatic' ) ) + '</span>';
		}
		var codes = c.pages.map( function ( p ) {
			var cls = 'ok' === p.outcome ? 'aeob-access-yes' : ( 'warn' === p.outcome ? 'aeob-access-warn' : 'aeob-access-no' );
			return '<span class="' + cls + '" title="' + esc( p.label ) + '">' + esc( p.status || '×' ) + '</span>';
		} );
		return codes.join( ' · ' );
	}

	function detailRows( c ) {
		var html = '';
		if ( c.summary ) {
			html += '<p>' + esc( c.summary ) + '</p>';
		}
		if ( c.ip_note ) {
			html += '<p class="description">' + esc( c.ip_note ) + '</p>';
		}
		if ( c.note ) {
			html += '<p class="description">' + esc( c.note ) + '</p>';
		}
		if ( c.last_seen && c.last_seen.time ) {
			html += '<p class="description">' + esc( sprintf(
				__( 'Last real visit in your AI crawler log: %1$s, status %2$d, %3$s', 'aeomatic' ),
				timeAgo( c.last_seen.time ), c.last_seen.status, c.last_seen.url
			) ) + '</p>';
		}
		if ( c.pages && c.pages.length ) {
			html += '<ul class="aeob-access-pages">' + c.pages.map( function ( p ) {
				var bits = [];
				bits.push( p.robots_allowed ? __( 'robots.txt allows', 'aeomatic' ) : __( 'robots.txt disallows', 'aeomatic' ) );
				if ( c.fetch && p.status !== undefined ) {
					bits.push( sprintf( __( 'HTTP %d', 'aeomatic' ), p.status ) );
				}
				if ( p.reason ) {
					bits.push( p.reason );
				}
				return '<li><a href="' + esc( p.url ) + '" target="_blank" rel="noopener">' + esc( p.label ) + '</a> — ' + esc( bits.join( ' · ' ) ) + '</li>';
			} ).join( '' ) + '</ul>';
		}
		return html;
	}

	function renderTable( crawlers ) {
		var html = '';
		PURPOSES.forEach( function ( group ) {
			var rows = crawlers.filter( function ( c ) { return c.purpose === group.key; } );
			if ( ! rows.length ) {
				return;
			}
			html += '<div class="aeob-card"><h2>' + esc( group.title ) + '</h2><p class="description">' + esc( group.hint ) + '</p>';
			html += '<div class="aeob-access-table-wrap"><table class="widefat striped aeob-access-table"><thead><tr>' +
				'<th>' + esc( __( 'Crawler', 'aeomatic' ) ) + '</th>' +
				'<th>' + esc( __( 'Company', 'aeomatic' ) ) + '</th>' +
				'<th>' + esc( __( 'robots.txt', 'aeomatic' ) ) + '</th>' +
				'<th>' + esc( __( 'Page requests', 'aeomatic' ) ) + '</th>' +
				'<th>' + esc( __( 'Result', 'aeomatic' ) ) + '</th>' +
				'</tr></thead><tbody>';
			rows.forEach( function ( c ) {
				html += '<tr class="aeob-access-row" data-token="' + esc( c.token ) + '">' +
					'<td><button type="button" class="button-link aeob-access-toggle" aria-expanded="false"><strong>' + esc( c.token ) + '</strong></button></td>' +
					'<td>' + esc( c.vendor ) + '</td>' +
					'<td>' + robotsCell( c ) + '</td>' +
					'<td class="aeob-access-codes">' + accessCell( c ) + '</td>' +
					'<td>' + verdictPill( c ) + '</td>' +
					'</tr>' +
					'<tr class="aeob-access-detail" hidden><td colspan="5">' + detailRows( c ) + '</td></tr>';
			} );
			html += '</tbody></table></div></div>';
		} );
		$( 'aeob-access-results' ).innerHTML = html || '<p class="description">' + esc( __( 'No results yet. Run the crawler check.', 'aeomatic' ) ) + '</p>';
	}

	function renderSummary( crawlers, checkedAt ) {
		var answer = crawlers.filter( function ( c ) { return 'training' !== c.purpose && c.verdict && 'pending' !== c.verdict; } );
		var ok = answer.filter( function ( c ) { return 'ok' === c.verdict; } ).length;
		var blocked = answer.filter( function ( c ) { return 'blocked' === c.verdict || 'robots' === c.verdict || 'failed' === c.verdict; } );
		var warn = answer.filter( function ( c ) { return 'warn' === c.verdict; } );

		if ( ! answer.length ) {
			$( 'aeob-access-summary' ).innerHTML = '';
			return;
		}

		var level = blocked.length ? 'err' : ( warn.length ? 'warn' : 'ok' );
		var headline = blocked.length
			? sprintf( __( '%1$d of %2$d AI answer and search crawlers are blocked', 'aeomatic' ), blocked.length, answer.length )
			: ( warn.length
				? sprintf( __( '%1$d of %2$d AI answer and search crawlers can read your site; %3$d need a look', 'aeomatic' ), ok, answer.length, warn.length )
				: sprintf( __( 'All %d AI answer and search crawlers can read your site', 'aeomatic' ), answer.length ) );

		var html = '<div class="aeob-card aeob-access-summary aeob-access-summary--' + level + '">' +
			'<div class="aeob-access-summary__head">' + esc( headline ) + '</div>';
		if ( blocked.length ) {
			html += '<p>' + esc( __( 'Blocked:', 'aeomatic' ) ) + ' ' + blocked.map( function ( c ) { return '<strong>' + esc( c.token ) + '</strong>'; } ).join( ', ' ) + '. ' +
				esc( __( 'Open a crawler below to see why.', 'aeomatic' ) ) + '</p>';
		}
		if ( checkedAt ) {
			html += '<p class="description">' + esc( sprintf( __( 'Checked %s.', 'aeomatic' ), timeAgo( checkedAt ) ) ) + '</p>';
		}
		html += '</div>';
		$( 'aeob-access-summary' ).innerHTML = html;
	}

	function renderNotices( list ) {
		var box = $( 'aeob-access-notices' );
		if ( ! list || ! list.length ) {
			box.innerHTML = '';
			return;
		}
		box.innerHTML = list.map( function ( t ) {
			return '<div class="notice notice-warning inline"><p>' + esc( t ) + '</p></div>';
		} ).join( '' );
	}

	function renderRobots( robots ) {
		if ( ! robots ) {
			return;
		}
		var head = robots.fetched
			? sprintf( __( 'robots.txt your site is serving (%s)', 'aeomatic' ), robots.physical_file ? __( 'a file on your server', 'aeomatic' ) : __( 'generated by WordPress', 'aeomatic' ) )
			: sprintf( __( 'Could not read robots.txt (status %s). Crawlers treat an unreachable robots.txt as "stay away".', 'aeomatic' ), robots.error || robots.status );
		$( 'aeob-access-robots' ).innerHTML = '<div class="aeob-card"><details><summary><strong>' + esc( head ) + '</strong></summary>' +
			( robots.body ? '<pre class="aeob-access-pre">' + esc( robots.body ) + '</pre>' : '' ) +
			'<p><a href="' + esc( robots.url ) + '" target="_blank" rel="noopener">' + esc( robots.url ) + '</a></p></details></div>';
	}

	function bindToggles() {
		var root = $( 'aeob-access-results' );
		root.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest ? e.target.closest( '.aeob-access-toggle' ) : null;
			if ( ! btn ) {
				return;
			}
			var row = btn.closest( 'tr' );
			var detail = row && row.nextElementSibling;
			if ( ! detail ) {
				return;
			}
			var open = detail.hidden;
			detail.hidden = ! open;
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	function initCrawlers() {
		var runBtn = $( 'aeob-access-run' );
		if ( ! runBtn ) {
			return;
		}
		bindToggles();

		apiFetch( { path: 'aeomatic/v1/ai-access/results' } ).then( function ( data ) {
			var list = [];
			Object.keys( data.crawlers || {} ).forEach( function ( k ) { list.push( data.crawlers[ k ] ); } );
			if ( list.length ) {
				renderTable( list );
				renderSummary( list, data.checked_at );
			} else {
				$( 'aeob-access-results' ).innerHTML = '<p class="description">' + esc( __( 'No results yet. Run the crawler check — it takes about a minute.', 'aeomatic' ) ) + '</p>';
			}
		} ).catch( function () {
			$( 'aeob-access-results' ).innerHTML = '';
		} );

		runBtn.addEventListener( 'click', function () {
			runBtn.disabled = true;
			var progress = $( 'aeob-access-progress' );
			progress.textContent = __( 'Reading robots.txt and loading your pages…', 'aeomatic' );
			renderNotices( [] );
			$( 'aeob-access-summary' ).innerHTML = '';

			apiFetch( { path: 'aeomatic/v1/ai-access/start' } ).then( function ( start ) {
				renderNotices( start.settings );
				renderRobots( start.robots );
				var list = start.crawlers.map( function ( c ) {
					c.verdict = 'pending';
					return c;
				} );
				renderTable( list );

				var i = 0;
				function next() {
					if ( i >= list.length ) {
						progress.textContent = __( 'Done.', 'aeomatic' );
						runBtn.disabled = false;
						renderSummary( list, Math.round( Date.now() / 1000 ) );
						return;
					}
					var c = list[ i ];
					progress.textContent = sprintf( __( 'Checking %1$s (%2$d of %3$d)…', 'aeomatic' ), c.token, i + 1, list.length );
					apiFetch( { path: 'aeomatic/v1/ai-access/crawler?bot=' + encodeURIComponent( c.token ) } ).then( function ( res ) {
						list[ i ] = res;
					} ).catch( function ( err ) {
						c.verdict = 'failed';
						c.summary = errorText( err );
					} ).then( function () {
						renderTable( list );
						i++;
						next();
					} );
				}
				next();
			} ).catch( function ( err ) {
				progress.textContent = errorText( err );
				runBtn.disabled = false;
			} );
		} );
	}

	// ── What AI sees ──────────────────────────────────────────────────────

	function levelPill( level ) {
		var map = {
			ok: [ 'aeob-pill--ok', __( 'Good', 'aeomatic' ) ],
			warn: [ 'aeob-pill--warn', __( 'Check', 'aeomatic' ) ],
			err: [ 'aeob-pill--err', __( 'Problem', 'aeomatic' ) ]
		};
		var m = map[ level ] || map.warn;
		return '<span class="aeob-pill ' + m[ 0 ] + '">' + esc( m[ 1 ] ) + '</span>';
	}

	function stat( num, label, hint ) {
		return '<div class="aeob-stat"><div class="aeob-stat__num">' + esc( num ) + '</div>' +
			'<div class="aeob-stat__label">' + esc( label ) + '</div>' +
			( hint ? '<div class="aeob-stat__hint">' + esc( hint ) + '</div>' : '' ) + '</div>';
	}

	function renderView( d ) {
		var box = $( 'aeob-view-results' );
		if ( ! d.ok ) {
			box.innerHTML = '<div class="notice notice-error inline"><p>' + esc( d.message ) + '</p></div>';
			return;
		}

		var html = '<div class="aeob-grid aeob-grid--4">' +
			stat( null === d.coverage ? '—' : d.coverage + '%', __( 'Content in the HTML', 'aeomatic' ),
				null === d.coverage ? __( 'No editor text to compare.', 'aeomatic' ) : sprintf( __( '%1$d of %2$d sentences from your editor', 'aeomatic' ), d.found, d.expected ) ) +
			stat( d.main_words, __( 'Words in main content', 'aeomatic' ), __( 'As a crawler reads it', 'aeomatic' ) ) +
			stat( d.html_kb + ' KB', __( 'HTML size', 'aeomatic' ), sprintf( __( '%d script tags', 'aeomatic' ), d.scripts ) ) +
			stat( d.jsonld, __( 'Structured data blocks', 'aeomatic' ), __( 'Chatbots mostly read the visible text, not this', 'aeomatic' ) ) +
			'</div>';

		html += '<div class="aeob-card"><h2>' + esc( __( 'Findings', 'aeomatic' ) ) + '</h2><ul class="aeob-status-list">' +
			d.findings.map( function ( f ) { return '<li>' + levelPill( f.level ) + ' ' + esc( f.text ) + '</li>'; } ).join( '' ) +
			'</ul>' +
			'<p class="description">' + esc( sprintf( __( 'Fetched %1$s as %2$s (HTTP %3$d). Editor: %4$s.', 'aeomatic' ), d.url, d.fetch_as, d.status, d.builder ) ) + '</p></div>';

		if ( d.missing && d.missing.length ) {
			html += '<div class="aeob-card"><h2>' + esc( __( 'Missing from the HTML', 'aeomatic' ) ) + '</h2>' +
				'<p class="description">' + esc( __( 'These sentences are in your editor but not in the page HTML. They only appear after JavaScript runs, or they are hidden.', 'aeomatic' ) ) + '</p>' +
				'<ul class="aeob-access-missing">' + d.missing.map( function ( s ) { return '<li>' + esc( s ) + '</li>'; } ).join( '' ) + '</ul></div>';
		}

		html += '<div class="aeob-grid">';
		html += '<div class="aeob-card"><h2>' + esc( __( 'Page title and description', 'aeomatic' ) ) + '</h2>' +
			'<p><strong>' + esc( d.title || __( '(no title)', 'aeomatic' ) ) + '</strong></p>' +
			'<p>' + esc( d.description || __( 'No meta description. Engines will pick their own summary.', 'aeomatic' ) ) + '</p></div>';
		html += '<div class="aeob-card"><h2>' + esc( __( 'Heading outline', 'aeomatic' ) ) + '</h2>' +
			( d.headings.length
				? '<ul class="aeob-access-outline">' + d.headings.map( function ( h ) {
					return '<li class="aeob-access-h' + h.level + '"><span class="aeob-access-tag">H' + h.level + '</span> ' + esc( h.text ) + '</li>';
				} ).join( '' ) + '</ul>'
				: '<p>' + esc( __( 'No headings found in the HTML.', 'aeomatic' ) ) + '</p>' ) +
			'</div></div>';

		html += '<div class="aeob-card"><h2>' + esc( __( 'Main text as a crawler reads it', 'aeomatic' ) ) + '</h2>' +
			'<p class="description">' + esc( __( 'The main content area of the raw HTML, with scripts removed. Headings are marked with #.', 'aeomatic' ) ) + '</p>' +
			'<pre class="aeob-access-pre aeob-access-text">' + esc( d.main_text || __( '(empty)', 'aeomatic' ) ) + '</pre></div>';

		box.innerHTML = html;
	}

	function initView() {
		var btn = $( 'aeob-view-run' );
		var select = $( 'aeob-view-post' );
		if ( ! btn || ! select ) {
			return;
		}
		function run() {
			var id = parseInt( select.value, 10 );
			if ( ! id ) {
				return;
			}
			btn.disabled = true;
			$( 'aeob-view-results' ).innerHTML = '<p class="description">' + esc( __( 'Fetching the page as a crawler…', 'aeomatic' ) ) + '</p>';
			apiFetch( { path: 'aeomatic/v1/ai-access/view/' + id } ).then( renderView ).catch( function ( err ) {
				$( 'aeob-view-results' ).innerHTML = '<div class="notice notice-error inline"><p>' + esc( errorText( err ) ) + '</p></div>';
			} ).then( function () {
				btn.disabled = false;
			} );
		}
		btn.addEventListener( 'click', run );
		if ( select.value ) {
			run();
		}
	}

	function init() {
		initCrawlers();
		initView();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window.wp ) );
