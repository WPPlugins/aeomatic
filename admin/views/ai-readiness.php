<?php
/**
 * AI Readiness — admin submenu view.
 *
 * Server-rendered shell. Client-side JS talks to
 * GET /aeomatic/v1/ai-readiness/{id} using wp.apiFetch, exactly like the
 * existing Citation Gaps admin view.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
if ( empty( $post_types ) ) {
	$post_types = array( 'post' );
}

$recent_posts = get_posts( array(
	'post_type'      => $post_types,
	'post_status'    => 'publish',
	'posts_per_page' => 100,
	'orderby'        => 'modified',
	'order'          => 'DESC',
) );

$default_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
if ( ! $default_id && ! empty( $recent_posts ) ) {
	$default_id = (int) $recent_posts[0]->ID;
}

$rest_base  = esc_url_raw( rest_url( AEOMATIC_AI_Readiness::REST_NS . '/ai-readiness' ) );
$rest_nonce = wp_create_nonce( 'wp_rest' );

wp_enqueue_script( 'wp-api-fetch' );
?>
<div class="wrap aeob-wrap aeob-readiness">
	<h1><?php esc_html_e( 'AI Readiness', 'aeomatic' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Score any post or page across five categories — Content, Trust, Authority, Entities, Technical — and see the exact issues plus fixes to raise the score.', 'aeomatic' ); ?>
	</p>

	<p>
		<label for="aeob-readiness-post"><?php esc_html_e( 'Post to analyze', 'aeomatic' ); ?></label>
		<select id="aeob-readiness-post" style="min-width:340px;">
			<?php foreach ( $recent_posts as $rp ) : ?>
				<option value="<?php echo esc_attr( (string) $rp->ID ); ?>"
				        <?php selected( $default_id, (int) $rp->ID ); ?>>
					<?php echo esc_html( get_the_title( $rp ) . ' (#' . $rp->ID . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button button-primary" id="aeob-readiness-analyze">
			<?php esc_html_e( 'Analyze', 'aeomatic' ); ?>
		</button>
		<button type="button" class="button" id="aeob-readiness-force">
			<?php esc_html_e( 'Recompute (skip cache)', 'aeomatic' ); ?>
		</button>
	</p>

	<div id="aeob-readiness-root"
	     data-rest="<?php echo esc_attr( $rest_base ); ?>"
	     data-nonce="<?php echo esc_attr( $rest_nonce ); ?>">
		<p class="aeob-readiness-loading" style="display:none;"><?php esc_html_e( 'Analyzing…', 'aeomatic' ); ?></p>
	</div>
</div>

<script>
(function () {
	var root = document.getElementById( 'aeob-readiness-root' );
	if ( ! root ) { return; }

	var rest    = root.dataset.rest;
	var nonce   = root.dataset.nonce;
	var select  = document.getElementById( 'aeob-readiness-post' );
	var btn     = document.getElementById( 'aeob-readiness-analyze' );
	var btnForce = document.getElementById( 'aeob-readiness-force' );

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[ c ];
		} );
	}

	function scoreColor( n ) {
		return n >= 70 ? '#1d9e75' : ( n >= 40 ? '#ba7517' : '#a32d2d' );
	}

	function renderCategory( c ) {
		var issues = ( c.issues || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		var recs   = ( c.recommendations || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		return '<div class="aeob-readiness-card" style="border:1px solid #dcdcde;background:#fff;padding:14px;border-radius:6px;margin-bottom:12px;">' +
			'<div style="display:flex;align-items:center;gap:12px;">' +
				'<div style="font-size:24px;font-weight:700;color:' + scoreColor( c.score ) + ';min-width:60px;">' + c.score + '/100</div>' +
				'<div style="text-transform:uppercase;font-weight:600;letter-spacing:0.5px;">' + esc( c.category ) + '</div>' +
			'</div>' +
			( issues ? '<h4 style="margin:12px 0 4px;">Issues</h4><ul style="margin:0 0 0 20px;">' + issues + '</ul>' : '<p style="color:#1d9e75;margin-top:12px;">No issues in this category.</p>' ) +
			( recs   ? '<h4 style="margin:12px 0 4px;">Recommendations</h4><ul style="margin:0 0 0 20px;">' + recs + '</ul>' : '' ) +
		'</div>';
	}

	function render( data ) {
		var overall = data.overall_score || 0;
		var cats    = data.categories || [];
		var cached  = data.cached ? ' <span style="color:#8c8f94;font-size:12px;">(cached)</span>' : '';

		var html = '<div class="aeob-kpi" style="background:#fff;border:1px solid #dcdcde;padding:16px;border-radius:6px;margin:16px 0;display:flex;align-items:center;gap:16px;">' +
			'<div style="font-size:44px;font-weight:700;color:' + scoreColor( overall ) + ';">' + overall + '</div>' +
			'<div><div style="font-size:12px;text-transform:uppercase;color:#50575e;">Overall AI Readiness</div>' +
				'<div style="font-size:14px;">' + esc( overall >= 70 ? 'Great' : ( overall >= 40 ? 'Needs work' : 'At risk' ) ) + cached + '</div></div>' +
			'</div>';

		html += cats.map( renderCategory ).join( '' );
		root.innerHTML = html;
	}

	function load( force ) {
		var id = parseInt( select.value, 10 );
		if ( ! id ) { return; }
		root.innerHTML = '<p>' + '<?php echo esc_js( __( 'Analyzing…', 'aeomatic' ) ); ?>' + '</p>';
		var url = rest + '/' + id + ( force ? ( rest.indexOf( '?' ) > -1 ? '&' : '?' ) + 'force=true' : '' ); // rest_url() already has a '?' with plain permalinks (?rest_route=…).
		fetch( url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( data && data.error ) {
					root.innerHTML = '<p style="color:#a32d2d;">' + esc( data.error ) + '</p>';
					return;
				}
				render( data );
			} )
			.catch( function ( e ) {
				root.innerHTML = '<p style="color:#a32d2d;">' + esc( e.message || 'Request failed' ) + '</p>';
			} );
	}

	btn.addEventListener( 'click', function () { load( false ); } );
	btnForce.addEventListener( 'click', function () { load( true ); } );
	select.addEventListener( 'change', function () { load( false ); } );

	// Auto-run for the pre-selected post.
	if ( select.value ) { load( false ); }
})();
</script>
