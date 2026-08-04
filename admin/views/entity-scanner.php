<?php
/**
 * Entity Scanner — admin submenu view.
 *
 * Server-rendered shell. Client-side JS calls
 * GET /aeomatic/v1/entity-scan/{id} using wp.apiFetch. Same pattern as
 * the AI Readiness and Citation Gaps admin views.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
if ( empty( $post_types ) ) { $post_types = array( 'post' ); }

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

$rest_base  = esc_url_raw( rest_url( AEOMATIC_Entity_Scanner::REST_NS . '/entity-scan' ) );
$rest_nonce = wp_create_nonce( 'wp_rest' );

wp_enqueue_script( 'wp-api-fetch' );
?>
<div class="wrap aeob-wrap aeob-entity-scanner">
	<h1><?php esc_html_e( 'Entity Scanner', 'aeomatic' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Scans content, schema, headings, and metadata for six entity types (Organization, Person, Service, Product, LocalBusiness, FAQ). Returns a coverage score, missing entities, and recommendations to raise confidence.', 'aeomatic' ); ?>
	</p>

	<p>
		<label for="aeob-entity-post"><?php esc_html_e( 'Post to scan', 'aeomatic' ); ?></label>
		<select id="aeob-entity-post" style="min-width:340px;">
			<?php foreach ( $recent_posts as $rp ) : ?>
				<option value="<?php echo esc_attr( (string) $rp->ID ); ?>" <?php selected( $default_id, (int) $rp->ID ); ?>>
					<?php echo esc_html( get_the_title( $rp ) . ' (#' . $rp->ID . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button button-primary" id="aeob-entity-scan"><?php esc_html_e( 'Scan', 'aeomatic' ); ?></button>
		<button type="button" class="button" id="aeob-entity-force"><?php esc_html_e( 'Rescan (skip cache)', 'aeomatic' ); ?></button>
	</p>

	<div id="aeob-entity-root"
	     data-rest="<?php echo esc_attr( $rest_base ); ?>"
	     data-nonce="<?php echo esc_attr( $rest_nonce ); ?>">
		<p><?php esc_html_e( 'Select a post and click Scan.', 'aeomatic' ); ?></p>
	</div>
</div>

<script>
(function () {
	var root = document.getElementById( 'aeob-entity-root' );
	if ( ! root ) { return; }
	var rest = root.dataset.rest, nonce = root.dataset.nonce;
	var sel = document.getElementById( 'aeob-entity-post' );
	var btn = document.getElementById( 'aeob-entity-scan' );
	var btnForce = document.getElementById( 'aeob-entity-force' );

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[ c ];
		} );
	}
	function color( n ) { return n >= 70 ? '#1d9e75' : ( n >= 40 ? '#ba7517' : '#a32d2d' ); }
	function title( s ) { return s.replace( /_/g, ' ' ).replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } ); }

	function renderEntity( e ) {
		var signals = ( e.signals || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		var missing = ( e.missing_signals || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		var recs = ( e.recommendations || [] ).map( function ( x ) { return '<li>' + esc( x ) + '</li>'; } ).join( '' );
		var badge = e.detected
			? '<span style="background:#1d9e75;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;">Detected</span>'
			: '<span style="background:#e0e0e0;color:#3c434a;padding:2px 8px;border-radius:999px;font-size:11px;">Missing</span>';
		return '<div style="border:1px solid #dcdcde;background:#fff;padding:14px;border-radius:6px;margin-bottom:12px;">' +
			'<div style="display:flex;align-items:center;gap:12px;">' +
				'<div style="font-size:22px;font-weight:700;color:' + color( e.confidence ) + ';min-width:60px;">' + e.confidence + '/100</div>' +
				'<div style="text-transform:uppercase;font-weight:600;letter-spacing:0.5px;">' + esc( title( e.entity_type ) ) + '</div>' +
				'<div style="margin-left:auto;">' + badge + '</div>' +
			'</div>' +
			( signals ? '<h4 style="margin:12px 0 4px;">Signals found</h4><ul style="margin:0 0 0 20px;color:#1d5e40;">' + signals + '</ul>' : '' ) +
			( missing ? '<h4 style="margin:12px 0 4px;">Missing signals</h4><ul style="margin:0 0 0 20px;color:#a32d2d;">' + missing + '</ul>' : '' ) +
			( recs ? '<h4 style="margin:12px 0 4px;">Recommendations</h4><ul style="margin:0 0 0 20px;">' + recs + '</ul>' : '' ) +
		'</div>';
	}

	function render( data ) {
		var cov = data.coverage_score || 0;
		var entities = data.entities || [];
		var cached = data.cached ? ' <span style="color:#8c8f94;font-size:12px;">(cached)</span>' : '';

		var missing = ( data.missing_entities || [] ).map( function ( m ) { return esc( title( m ) ); } ).join( ', ' );

		var html = '<div style="background:#fff;border:1px solid #dcdcde;padding:16px;border-radius:6px;margin:16px 0;display:flex;align-items:center;gap:16px;">' +
			'<div style="font-size:44px;font-weight:700;color:' + color( cov ) + ';">' + cov + '</div>' +
			'<div><div style="font-size:12px;text-transform:uppercase;color:#50575e;">Entity Coverage</div>' +
				'<div style="font-size:14px;">' + esc( cov >= 70 ? 'Strong coverage' : ( cov >= 40 ? 'Partial coverage' : 'Weak coverage' ) ) + cached + '</div></div>' +
			'</div>';

		if ( missing ) {
			html += '<div style="background:#fef7e8;border-left:4px solid #ba7517;padding:10px;margin-bottom:16px;">' +
				'<strong>Missing entities:</strong> ' + missing +
			'</div>';
		}

		html += entities.map( renderEntity ).join( '' );
		root.innerHTML = html;
	}

	function load( force ) {
		var id = parseInt( sel.value, 10 );
		if ( ! id ) { return; }
		root.innerHTML = '<p>' + '<?php echo esc_js( __( 'Scanning…', 'aeomatic' ) ); ?>' + '</p>';
		var url = rest + '/' + id + ( force ? '?force=true' : '' );
		fetch( url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) {
				if ( d && d.error ) { root.innerHTML = '<p style="color:#a32d2d;">' + esc( d.error ) + '</p>'; return; }
				render( d );
			} )
			.catch( function ( e ) { root.innerHTML = '<p style="color:#a32d2d;">' + esc( e.message || 'Request failed' ) + '</p>'; } );
	}

	btn.addEventListener( 'click', function () { load( false ); } );
	btnForce.addEventListener( 'click', function () { load( true ); } );
	sel.addEventListener( 'change', function () { load( false ); } );

	if ( sel.value ) { load( false ); }
})();
</script>
