<?php
/**
 * Site-wide AI Citation Gaps view — "AEOmatic → Citation Gaps".
 *
 * Renders the client-side table shell. Rows load from
 * GET /aeomatic/v1/citation-gaps/site so filtering and paging can be interactive
 * without a full page reload.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
$type_labels = array();
foreach ( $post_types as $pt ) {
	$obj = get_post_type_object( $pt );
	$type_labels[ $pt ] = $obj ? $obj->labels->singular_name : $pt;
}

// Authors with at least one post in enabled post types.
$authors = get_users( array(
	'capability'          => array( 'edit_posts' ),
	'has_published_posts' => $post_types,
	'fields'              => array( 'ID', 'display_name' ),
	'orderby'             => 'display_name',
) );

$rest_root  = esc_url_raw( rest_url( AEOMATIC_Citation_Gaps::REST_NS . '/citation-gaps/site' ) );
$rest_base  = esc_url_raw( rest_url( AEOMATIC_Citation_Gaps::REST_NS ) );
$rest_nonce = wp_create_nonce( 'wp_rest' );

wp_enqueue_script( 'wp-api-fetch' );
?>
<div class="wrap aeob-wrap aeob-gaps">
	<h1><?php esc_html_e( 'AI Citation Gaps', 'aeomatic' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Local, rules-based analysis of every published page and post. Lower score = more gaps. Fully offline — no external APIs, no data leaves your site.', 'aeomatic' ); ?>
	</p>

	<div class="aeob-gaps__summary" id="aeob-gaps-summary">
		<div class="aeob-gaps__tile"><div class="aeob-gaps__tile-num" id="aeob-gaps-tile-scanned">—</div><div class="aeob-gaps__tile-label"><?php esc_html_e( 'Pages scanned', 'aeomatic' ); ?></div></div>
		<div class="aeob-gaps__tile aeob-gaps__tile--warn"><div class="aeob-gaps__tile-num" id="aeob-gaps-tile-withgaps">—</div><div class="aeob-gaps__tile-label"><?php esc_html_e( 'With gaps', 'aeomatic' ); ?></div></div>
		<div class="aeob-gaps__tile aeob-gaps__tile--good"><div class="aeob-gaps__tile-num" id="aeob-gaps-tile-clean">—</div><div class="aeob-gaps__tile-label"><?php esc_html_e( 'Clean (100/100)', 'aeomatic' ); ?></div></div>
		<div class="aeob-gaps__tile"><div class="aeob-gaps__tile-num" id="aeob-gaps-tile-avg">—</div><div class="aeob-gaps__tile-label"><?php esc_html_e( 'Avg score', 'aeomatic' ); ?></div></div>
		<div class="aeob-gaps__tile aeob-gaps__tile--muted"><div class="aeob-gaps__tile-num" id="aeob-gaps-tile-notscanned">—</div><div class="aeob-gaps__tile-label"><?php esc_html_e( 'Not scanned yet', 'aeomatic' ); ?></div></div>
	</div>

	<div class="aeob-gaps__filters">
		<div class="aeob-gaps__filter-row">
			<label class="aeob-gaps__field aeob-gaps__field--grow">
				<span><?php esc_html_e( 'Search title', 'aeomatic' ); ?></span>
				<input type="search" id="aeob-gaps-filter-search" placeholder="<?php esc_attr_e( 'e.g. checkout, pricing…', 'aeomatic' ); ?>" />
			</label>
			<label class="aeob-gaps__field">
				<span><?php esc_html_e( 'Post type', 'aeomatic' ); ?></span>
				<select id="aeob-gaps-filter-type">
					<option value=""><?php esc_html_e( 'All', 'aeomatic' ); ?></option>
					<?php foreach ( $type_labels as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="aeob-gaps__field">
				<span><?php esc_html_e( 'Author', 'aeomatic' ); ?></span>
				<select id="aeob-gaps-filter-author">
					<option value="0"><?php esc_html_e( 'Any', 'aeomatic' ); ?></option>
					<?php foreach ( $authors as $u ) : ?>
						<option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html( $u->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<div class="aeob-gaps__filter-row">
			<label class="aeob-gaps__field">
				<span><?php esc_html_e( 'Severity', 'aeomatic' ); ?></span>
				<select id="aeob-gaps-filter-severity">
					<option value=""><?php esc_html_e( 'Any', 'aeomatic' ); ?></option>
					<option value="high"><?php esc_html_e( 'High', 'aeomatic' ); ?></option>
					<option value="medium"><?php esc_html_e( 'Medium', 'aeomatic' ); ?></option>
					<option value="low"><?php esc_html_e( 'Low', 'aeomatic' ); ?></option>
				</select>
			</label>
			<label class="aeob-gaps__field">
				<span><?php esc_html_e( 'Gap type', 'aeomatic' ); ?></span>
				<select id="aeob-gaps-filter-code">
					<option value=""><?php esc_html_e( 'Any', 'aeomatic' ); ?></option>
					<option value="orphan_page">Orphan page</option>
					<option value="low_outbound_links">Low internal links</option>
					<option value="sibling_topic_gap">Sibling topic gap</option>
					<option value="thin_body">Thin body</option>
					<option value="no_related_links">No related links</option>
					<option value="no_opening_answer">No opening answer</option>
					<option value="no_question_heading">No question heading</option>
					<option value="no_faq_block">No FAQ block</option>
					<option value="schema_type_mismatch">Schema mismatch</option>
					<option value="low_alt_coverage">Low alt coverage</option>
					<option value="no_citation_hook">No citation hook</option>
					<option value="missing_author_signal">Missing author signal</option>
					<option value="missing_meta_description">Missing meta description</option>
					<option value="faq_answers_not_snippet_ready">Question headings without a snippet-length answer</option>
					<option value="steps_not_in_ordered_list">Steps not in a numbered list</option>
					<option value="missing_from_llms_txt">Missing from /llms.txt</option>
				</select>
			</label>
			<label class="aeob-gaps__field aeob-gaps__field--score">
				<span><?php esc_html_e( 'Score range', 'aeomatic' ); ?></span>
				<span class="aeob-gaps__range">
					<input type="number" id="aeob-gaps-filter-score-min" min="0" max="100" step="1" value="0" aria-label="<?php esc_attr_e( 'Minimum score', 'aeomatic' ); ?>" />
					<span aria-hidden="true">–</span>
					<input type="number" id="aeob-gaps-filter-score-max" min="0" max="100" step="1" value="100" aria-label="<?php esc_attr_e( 'Maximum score', 'aeomatic' ); ?>" />
				</span>
			</label>
			<label class="aeob-gaps__field aeob-gaps__field--date">
				<span><?php esc_html_e( 'Modified between', 'aeomatic' ); ?></span>
				<span class="aeob-gaps__range">
					<input type="date" id="aeob-gaps-filter-after" aria-label="<?php esc_attr_e( 'Modified after', 'aeomatic' ); ?>" />
					<span aria-hidden="true">→</span>
					<input type="date" id="aeob-gaps-filter-before" aria-label="<?php esc_attr_e( 'Modified before', 'aeomatic' ); ?>" />
				</span>
			</label>
			<div class="aeob-gaps__filter-actions">
				<button type="button" class="button" id="aeob-gaps-reset"><?php esc_html_e( 'Reset', 'aeomatic' ); ?></button>
				<button type="button" class="button button-primary" id="aeob-gaps-refresh"><?php esc_html_e( 'Apply', 'aeomatic' ); ?></button>
			</div>
		</div>
	</div>

	<div id="aeob-gaps-status" class="notice notice-info" style="padding:8px 12px;">
		<?php esc_html_e( 'Loading…', 'aeomatic' ); ?>
	</div>

	<table class="widefat striped aeob-gaps__table" style="margin-top:8px;">
		<thead>
			<tr>
				<th style="width:80px;"><?php esc_html_e( 'Score', 'aeomatic' ); ?></th>
				<th><?php esc_html_e( 'Post', 'aeomatic' ); ?></th>
				<th style="width:110px;"><?php esc_html_e( 'Type', 'aeomatic' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Gaps', 'aeomatic' ); ?></th>
				<th><?php esc_html_e( 'Top issues', 'aeomatic' ); ?></th>
			</tr>
		</thead>
		<tbody id="aeob-gaps-rows"></tbody>
	</table>

	<div id="aeob-gaps-pager" style="margin-top:12px;display:flex;gap:8px;align-items:center;">
		<button type="button" class="button" id="aeob-gaps-prev" disabled>&laquo;</button>
		<span id="aeob-gaps-pageinfo" style="color:#6e6e73;font-size:13px;"></span>
		<button type="button" class="button" id="aeob-gaps-next" disabled>&raquo;</button>
	</div>
</div>

<style>
	.aeob-gaps__summary { display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:16px 0 0; }
	@media (max-width:900px) { .aeob-gaps__summary { grid-template-columns:repeat(2,1fr); } }
	.aeob-gaps__tile { background:#fff;border:1px solid #e5e5e7;border-radius:8px;padding:14px 16px; }
	.aeob-gaps__tile-num { font-size:26px;font-weight:700;letter-spacing:-0.02em;color:#1d1d1f; }
	.aeob-gaps__tile-label { font-size:11px;color:#6e6e73;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px; }
	.aeob-gaps__tile--warn .aeob-gaps__tile-num { color:#ba7517; }
	.aeob-gaps__tile--good .aeob-gaps__tile-num { color:#087443; }
	.aeob-gaps__tile--muted .aeob-gaps__tile-num { color:#6e6e73; }
	.aeob-gaps__filters { background:#fff;border:1px solid #e5e5e7;border-radius:8px;padding:14px 16px;margin:16px 0 12px; }
	.aeob-gaps__filter-row { display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;margin-bottom:8px; }
	.aeob-gaps__filter-row:last-child { margin-bottom:0; }
	.aeob-gaps__field { display:flex;flex-direction:column;gap:4px;font-size:12px;color:#50575e; }
	.aeob-gaps__field > span { font-weight:600;letter-spacing:0.02em; }
	.aeob-gaps__field input[type=search],
	.aeob-gaps__field input[type=number],
	.aeob-gaps__field input[type=date],
	.aeob-gaps__field select { min-width:140px; }
	.aeob-gaps__field--grow { flex:1 1 220px; }
	.aeob-gaps__field--grow input[type=search] { width:100%; }
	.aeob-gaps__field--score input[type=number] { width:70px; }
	.aeob-gaps__field--date input[type=date] { width:145px; }
	.aeob-gaps__range { display:inline-flex;gap:6px;align-items:center; }
	.aeob-gaps__range > span { color:#a7a7ac;font-size:12px; }
	.aeob-gaps__filter-actions { display:flex;gap:8px;margin-left:auto; }
	.aeob-gaps__score { display:inline-block;min-width:36px;text-align:center;font-weight:600;padding:3px 8px;border-radius:999px;font-size:12px; }
	.aeob-gaps__score--good { background:#e6f7ee;color:#087443; }
	.aeob-gaps__score--warn { background:#fdf3e2;color:#7a4b06; }
	.aeob-gaps__score--bad  { background:#fde8e8;color:#8a1c1c; }
	.aeob-gaps__sev { display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;vertical-align:middle; }
	.aeob-gaps__sev--high { background:#a32d2d; }
	.aeob-gaps__sev--medium { background:#ba7517; }
	.aeob-gaps__sev--low { background:#6e6e73; }
	.aeob-gaps__issue { display:block;font-size:12px;color:#333;margin:2px 0; }
</style>

<script>
( function () {
	'use strict';
	var restRoot = <?php echo wp_json_encode( $rest_root ); ?>;
	var restBase = <?php echo wp_json_encode( rtrim( $rest_base, '/' ) ); ?>;
	var nonce    = <?php echo wp_json_encode( $rest_nonce ); ?>;
	var page     = 1;
	var lastResp = null;

	var $ = function ( sel ) { return document.querySelector( sel ); };

	function scoreClass( s ) {
		if ( s >= 70 ) return 'aeob-gaps__score--good';
		if ( s >= 40 ) return 'aeob-gaps__score--warn';
		return 'aeob-gaps__score--bad';
	}

	function esc( str ) {
		var d = document.createElement( 'div' );
		d.textContent = String( str == null ? '' : str );
		return d.innerHTML;
	}

	function renderSummary( s ) {
		if ( ! s ) return;
		var byId = function ( id ) { var n = document.getElementById( id ); if ( n ) n.textContent = esc( arguments[1] ); };
		byId( 'aeob-gaps-tile-scanned', s.scanned );
		byId( 'aeob-gaps-tile-withgaps', s.with_gaps );
		byId( 'aeob-gaps-tile-clean', s.clean );
		byId( 'aeob-gaps-tile-avg', s.avg_score + ( s.scanned ? ' / 100' : '' ) );
		byId( 'aeob-gaps-tile-notscanned', s.not_scanned );
	}

	function render( data ) {
		lastResp = data;
		renderSummary( data.summary );
		var tbody = $( '#aeob-gaps-rows' );
		tbody.innerHTML = '';
		if ( ! data.rows || ! data.rows.length ) {
			tbody.innerHTML = '<tr><td colspan="5" style="padding:16px;color:#6e6e73;">No matching posts. Save a post to trigger analysis, or clear filters.</td></tr>';
		} else {
			data.rows.forEach( function ( r ) {
				var issues = ( r.top_gaps || [] ).map( function ( g ) {
					var btn = g.fix_action
						? ' <button type="button" class="button button-small aeob-gaps__fix" data-post="' + esc( r.id ) + '" data-code="' + esc( g.code ) + '" data-fix="' + esc( JSON.stringify( g.fix_action ) ) + '">' + esc( g.fix_action.label || 'Quick fix' ) + '</button>'
						: '';
					return '<span class="aeob-gaps__issue"><span class="aeob-gaps__sev aeob-gaps__sev--' + esc( g.severity ) + '"></span>' + esc( g.title ) + btn + '</span>';
				} ).join( '' );
				var tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td><span class="aeob-gaps__score ' + scoreClass( r.score ) + '">' + esc( r.score ) + '</span></td>' +
					'<td><a href="' + esc( r.edit_url ) + '"><strong>' + esc( r.title || '(untitled)' ) + '</strong></a><br><a href="' + esc( r.view_url ) + '" target="_blank" style="font-size:12px;color:#6e6e73;">View →</a></td>' +
					'<td>' + esc( r.post_type ) + '</td>' +
					'<td>' + esc( r.gap_count ) + '</td>' +
					'<td>' + issues + '</td>';
				tbody.appendChild( tr );
			} );
			// Wire Quick Fix buttons that were just rendered.
			tbody.querySelectorAll( '.aeob-gaps__fix' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var postId = btn.getAttribute( 'data-post' );
					var fix    = JSON.parse( btn.getAttribute( 'data-fix' ) || '{}' );
					if ( ! postId || ! fix.code ) return;
					btn.disabled    = true;
					btn.textContent = 'Applying…';
					fetch( restBase + '/citation-gaps/fix/' + postId, {
						method:  'POST',
						headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
						body:    JSON.stringify( { code: fix.code, params: fix.params || {} } )
					} )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							btn.disabled    = false;
							btn.textContent = ( res && res.ok ) ? 'Applied ✓' : ( res && res.message ? res.message : 'Failed' );
							if ( res && res.ok ) { setTimeout( load, 700 ); }
						} )
						.catch( function ( e ) {
							btn.disabled    = false;
							btn.textContent = 'Failed';
						} );
				} );
			} );
		}

		$( '#aeob-gaps-status' ).textContent = data.total + ' post(s) analysed. Showing page ' + data.paged + ' of ' + Math.max( 1, data.total_pages ) + '.';
		$( '#aeob-gaps-pageinfo' ).textContent = 'Page ' + data.paged + ' of ' + Math.max( 1, data.total_pages );
		$( '#aeob-gaps-prev' ).disabled = data.paged <= 1;
		$( '#aeob-gaps-next' ).disabled = data.paged >= data.total_pages;
	}

	function val( sel ) { var n = $( sel ); return n ? String( n.value || '' ) : ''; }

	function load() {
		var params = new URLSearchParams( {
			paged:     page,
			per_page:  20,
			post_type: val( '#aeob-gaps-filter-type' ),
			severity:  val( '#aeob-gaps-filter-severity' ),
			gap_code:  val( '#aeob-gaps-filter-code' ),
			score_min: val( '#aeob-gaps-filter-score-min' ) || '0',
			score_max: val( '#aeob-gaps-filter-score-max' ) || '100',
			search:    val( '#aeob-gaps-filter-search' ),
			after:     val( '#aeob-gaps-filter-after' ),
			before:    val( '#aeob-gaps-filter-before' ),
			author:    val( '#aeob-gaps-filter-author' ) || '0'
		} );
		$( '#aeob-gaps-status' ).textContent = 'Loading…';
		fetch( restRoot + '?' + params.toString(), {
			headers: { 'X-WP-Nonce': nonce }
		} )
			.then( function ( r ) { return r.json(); } )
			.then( render )
			.catch( function ( e ) {
				$( '#aeob-gaps-status' ).textContent = 'Error: ' + ( e && e.message ? e.message : 'unknown' );
			} );
	}

	function resetFilters() {
		[ '#aeob-gaps-filter-type', '#aeob-gaps-filter-severity', '#aeob-gaps-filter-code' ].forEach( function ( s ) {
			var n = $( s ); if ( n ) n.value = '';
		} );
		var au = $( '#aeob-gaps-filter-author' ); if ( au ) au.value = '0';
		var sr = $( '#aeob-gaps-filter-search' ); if ( sr ) sr.value = '';
		var mn = $( '#aeob-gaps-filter-score-min' ); if ( mn ) mn.value = 0;
		var mx = $( '#aeob-gaps-filter-score-max' ); if ( mx ) mx.value = 100;
		var af = $( '#aeob-gaps-filter-after' ); if ( af ) af.value = '';
		var bf = $( '#aeob-gaps-filter-before' ); if ( bf ) bf.value = '';
	}

	// Debounce so the search box doesn't fire on every keystroke.
	var searchTimer = null;
	function debouncedLoad() {
		clearTimeout( searchTimer );
		searchTimer = setTimeout( function () { page = 1; load(); }, 350 );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		// Change-driven filters (immediate).
		[ '#aeob-gaps-filter-type', '#aeob-gaps-filter-severity', '#aeob-gaps-filter-code',
		  '#aeob-gaps-filter-author', '#aeob-gaps-filter-after', '#aeob-gaps-filter-before'
		].forEach( function ( s ) {
			var n = $( s ); if ( n ) n.addEventListener( 'change', function () { page = 1; load(); } );
		} );
		// Text + number filters (debounced).
		[ '#aeob-gaps-filter-search', '#aeob-gaps-filter-score-min', '#aeob-gaps-filter-score-max'
		].forEach( function ( s ) {
			var n = $( s ); if ( n ) n.addEventListener( 'input', debouncedLoad );
		} );
		$( '#aeob-gaps-refresh' ).addEventListener( 'click', function () { page = 1; load(); } );
		$( '#aeob-gaps-reset' ).addEventListener( 'click', function () {
			resetFilters(); page = 1; load();
		} );
		$( '#aeob-gaps-prev' ).addEventListener( 'click', function () { if ( page > 1 ) { page--; load(); } } );
		$( '#aeob-gaps-next' ).addEventListener( 'click', function () { if ( lastResp && page < lastResp.total_pages ) { page++; load(); } } );
		load();
	} );
} )();
</script>
