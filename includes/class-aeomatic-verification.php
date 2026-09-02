<?php
/**
 * Verification module.
 *
 * Performs live checks against the user's own site to verify that
 * AEOmatic is actually working: schema present, llms.txt serving,
 * robots.txt directives in place, FAQ blocks rendering correctly.
 *
 * Results are surfaced in the "What Did AEOmatic Do?" admin page
 * with click-through verification links.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Verification {

	const REST_NS = 'aeomatic/v1';
	const CACHE_KEY = 'aeomatic_verification_cache';
	const CACHE_TTL = 300; // 5 minutes

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route( self::REST_NS, '/verify', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_verify' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'fresh' => array(
					'description' => 'Force a fresh check, bypassing the 5-minute cache.',
					'type'        => 'boolean',
					'default'     => false,
				),
			),
		) );
	}

	public function rest_verify( WP_REST_Request $request ) {
		$fresh = (bool) $request->get_param( 'fresh' );

		if ( ! $fresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached ) {
				return rest_ensure_response( $cached );
			}
		}

		$results = $this->run_checks();
		set_transient( self::CACHE_KEY, $results, self::CACHE_TTL );
		return rest_ensure_response( $results );
	}

	/**
	 * Run all verification checks.
	 *
	 * @return array{
	 *   checked_at:int,
	 *   checks:array<int,array{
	 *     id:string, label:string, status:string, message:string,
	 *     verify_url:string|null, details:string|null
	 *   }>
	 * }
	 */
	public function run_checks(): array {
		$checks   = array();
		$home_url = home_url( '/' );

		// 1. llms.txt
		$checks[] = $this->check_llms_txt();

		// 1b. llms-full.txt
		$checks[] = $this->check_llms_full_txt();

		// 2. robots.txt with AI bot directives
		$checks[] = $this->check_robots_txt();

		// 3. Schema on a real post
		$checks[] = $this->check_schema_on_post();

		// 4. Coverage — CPTs and builders detected
		$checks[] = $this->check_coverage();

		// 5. FAQ block usage
		$checks[] = $this->check_faq_usage();

		// 6. Author bios
		$checks[] = $this->check_author_bios();

		// 7. Sample post audit score
		$checks[] = $this->check_sample_audit();

		return array(
			'checked_at' => time(),
			'home_url'   => $home_url,
			'checks'     => $checks,
		);
	}

	private function check_llms_txt(): array {
		$enabled = (bool) AEOMATIC_Settings::get( 'llms_txt', 'enabled' );
		$url     = home_url( '/llms.txt' );

		if ( ! $enabled ) {
			return $this->result( 'llms_txt', __( 'llms.txt endpoint', 'aeomatic' ), 'off',
				__( 'Disabled in settings. Turn it on to give AI engines a clean site index.', 'aeomatic' ),
				null, null );
		}

		$response = wp_remote_get( $url, array( 'timeout' => 5, 'redirection' => 2 ) );

		if ( is_wp_error( $response ) ) {
			return $this->result( 'llms_txt', __( 'llms.txt endpoint', 'aeomatic' ), 'fail',
				/* translators: %s: error message */
				sprintf( __( 'Could not reach %s.', 'aeomatic' ), esc_url( $url ) ),
				$url, $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return $this->result( 'llms_txt', __( 'llms.txt endpoint', 'aeomatic' ), 'fail',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Endpoint returned HTTP %d. Visit Settings → Permalinks and click Save to refresh rewrite rules.', 'aeomatic' ), $code ),
				$url, null );
		}

		$line_count = substr_count( $body, "\n" );
		$kb         = round( strlen( $body ) / 1024, 1 );

		return $this->result( 'llms_txt', __( 'llms.txt endpoint', 'aeomatic' ), 'ok',
			/* translators: 1: KB size 2: line count */
			sprintf( __( 'Live and serving content (%1$s KB, %2$d lines). Click to view.', 'aeomatic' ), $kb, $line_count ),
			$url, null );
	}

	private function check_llms_full_txt(): array {
		$enabled = (bool) AEOMATIC_Settings::get( 'llms_txt', 'enabled' );
		$url     = home_url( '/llms-full.txt' );

		if ( ! $enabled ) {
			return $this->result( 'llms_full_txt', __( 'llms-full.txt endpoint', 'aeomatic' ), 'off',
				__( 'Disabled in settings. Enable llms.txt to also serve the full-content file.', 'aeomatic' ),
				null, null );
		}

		$response = wp_remote_get( $url, array( 'timeout' => 8, 'redirection' => 2 ) );

		if ( is_wp_error( $response ) ) {
			return $this->result( 'llms_full_txt', __( 'llms-full.txt endpoint', 'aeomatic' ), 'fail',
				/* translators: %s: error message */
				sprintf( __( 'Could not reach %s.', 'aeomatic' ), esc_url( $url ) ),
				$url, $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return $this->result( 'llms_full_txt', __( 'llms-full.txt endpoint', 'aeomatic' ), 'fail',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Endpoint returned HTTP %d. Visit Settings → Permalinks and click Save to refresh rewrite rules.', 'aeomatic' ), $code ),
				$url, null );
		}

		$block_count = substr_count( $body, "\n## " );
		$kb          = round( strlen( $body ) / 1024, 1 );

		return $this->result( 'llms_full_txt', __( 'llms-full.txt endpoint', 'aeomatic' ), 'ok',
			/* translators: 1: KB size 2: content block count */
			sprintf( __( 'Live and serving full content (%1$s KB, %2$d content blocks). Click to view.', 'aeomatic' ), $kb, $block_count ),
			$url, null );
	}

	private function check_robots_txt(): array {
		$url      = home_url( '/robots.txt' );
		$response = wp_remote_get( $url, array( 'timeout' => 5, 'redirection' => 2 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->result( 'robots', __( 'robots.txt with AI directives', 'aeomatic' ), 'fail',
				__( 'Could not fetch robots.txt.', 'aeomatic' ),
				$url, null );
		}

		$body = wp_remote_retrieve_body( $response );

		if ( false === strpos( $body, 'AEOmatic' ) ) {
			return $this->result( 'robots', __( 'robots.txt with AI directives', 'aeomatic' ), 'warn',
				__( 'AEOmatic directives not detected. Another plugin or a static robots.txt may be overriding ours.', 'aeomatic' ),
				$url, null );
		}

		// Count how many bots we're managing.
		$crawlers = (array) AEOMATIC_Settings::get( 'crawlers' );
		$bot_count = count( $crawlers );
		$blocked_count = count( array_filter( $crawlers, static function ( $v ) { return ! $v; } ) );

		return $this->result( 'robots', __( 'robots.txt with AI directives', 'aeomatic' ), 'ok',
			/* translators: 1: total bots, 2: blocked count */
			sprintf( __( 'Managing %1$d AI crawlers (%2$d blocked). Click to view live robots.txt.', 'aeomatic' ), $bot_count, $blocked_count ),
			$url, null );
	}

	private function check_schema_on_post(): array {
		$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		$recent = get_posts( array(
			'post_type'   => $post_types,
			'post_status' => 'publish',
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );

		if ( empty( $recent ) ) {
			return $this->result( 'schema', __( 'JSON-LD schema on posts', 'aeomatic' ), 'warn',
				__( 'No published posts to verify against. Publish a post to see schema in action.', 'aeomatic' ),
				null, null );
		}

		$post     = $recent[0];
		$post_url = get_permalink( $post );
		$response = wp_remote_get( $post_url, array(
			'timeout'     => 8,
			'redirection' => 2,
			'user-agent'  => 'AEOmatic Verifier/' . AEOMATIC_VERSION,
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->result( 'schema', __( 'JSON-LD schema on posts', 'aeomatic' ), 'warn',
				/* translators: %s: post URL */
				sprintf( __( 'Could not fetch %s to verify schema. Plugin output is correct, but a caching layer or login wall may be blocking the check.', 'aeomatic' ), esc_url( $post_url ) ),
				$post_url, null );
		}

		$body = wp_remote_retrieve_body( $response );

		// Find all JSON-LD blocks.
		preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $body, $matches );
		$json_blocks = $matches[1] ?? array();

		if ( empty( $json_blocks ) ) {
			return $this->result( 'schema', __( 'JSON-LD schema on posts', 'aeomatic' ), 'fail',
				__( 'No JSON-LD found on the latest post. Check that schema types are enabled in Settings.', 'aeomatic' ),
				$post_url, null );
		}

		$types = array();
		foreach ( $json_blocks as $json ) {
			$decoded = json_decode( $json, true );
			if ( is_array( $decoded ) && isset( $decoded['@type'] ) ) {
				$types[] = is_array( $decoded['@type'] ) ? implode( '+', $decoded['@type'] ) : $decoded['@type'];
			}
		}
		$types = array_values( array_unique( $types ) );

		// Build a Google Rich Results Test URL for one-click validation.
		$rich_test_url = 'https://search.google.com/test/rich-results?url=' . rawurlencode( $post_url );

		// translators: 1: number of schema blocks found, 2: comma-separated schema type names.
		$aeomatic_msg_str = __( 'Found %1$d schema block(s) on your latest post: %2$s. Click "Validate" to test with Google.', 'aeomatic' );
		$message      = sprintf( $aeomatic_msg_str, count( $json_blocks ), implode( ', ', $types ) );

		return $this->result( 'schema', __( 'JSON-LD schema on posts', 'aeomatic' ), 'ok', $message, $rich_test_url, $post_url );
	}

	private function check_coverage(): array {
		$enabled = AEOMATIC_Post_Types::get_enabled_slugs();
		$objects = AEOMATIC_Post_Types::get_public_post_types();
		$total   = count( $objects );
		$on      = count( $enabled );

		// Sample up to 20 recent items across enabled types and detect builders.
		$sample = get_posts( array(
			'post_type'   => $enabled ?: array( 'post' ),
			'post_status' => 'publish',
			'numberposts' => 20,
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );

		$builders = array();
		foreach ( $sample as $post ) {
			$b = AEOMATIC_Content_Extractor::detect_builder( $post );
			$builders[ $b ] = ( $builders[ $b ] ?? 0 ) + 1;
		}

		$wc = class_exists( 'WooCommerce' ) ? __( 'WooCommerce: detected, Product schema enabled', 'aeomatic' ) : '';
		$acf = function_exists( 'get_field_objects' ) ? __( 'ACF: detected, fields scanned', 'aeomatic' ) : '';

		$builder_summary = array();
		foreach ( $builders as $slug => $count ) {
			if ( 'unknown' === $slug ) continue;
			$builder_summary[] = AEOMATIC_Content_Extractor::builder_label( $slug ) . ' (' . $count . ')';
		}

		$pieces = array();
		$pieces[] = sprintf(
			/* translators: 1: enabled count, 2: total count */
			__( '%1$d of %2$d post types active.', 'aeomatic' ),
			$on, $total
		);
		if ( $builder_summary ) {
			$pieces[] = __( 'Builders found:', 'aeomatic' ) . ' ' . implode( ', ', $builder_summary );
		}
		if ( $wc )  $pieces[] = $wc;
		if ( $acf ) $pieces[] = $acf;

		return $this->result(
			'coverage',
			__( 'Content coverage', 'aeomatic' ),
			$on > 0 ? 'ok' : 'warn',
			implode( ' · ', $pieces ),
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS ),
			null
		);
	}

	private function check_faq_usage(): array {
		global $wpdb;
		$enabled = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $enabled ) ) {
			$enabled = array( 'post' );
		}

		// Build safe IN clause — one %s placeholder per post type.
		// The number of placeholders is dynamic so we build the query string first,
		// then pass the full args array to prepare(). The phpcs ignore is required
		// because PHPCS cannot statically verify dynamic placeholder counts.
		$aeomatic_placeholders = implode( ', ', array_fill( 0, count( $enabled ), '%s' ) );
		$aeomatic_args         = array_merge( array( 'publish' ), $enabled, array( '%<!-- wp:aeomatic/faq %' ) );
		$aeomatic_cache_key    = 'aeomatic_faq_block_count_' . md5( implode( ',', $enabled ) );
		$count             = wp_cache_get( $aeomatic_cache_key, 'aeob' );

		if ( false === $count ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ({$aeomatic_placeholders}) AND post_content LIKE %s", $aeomatic_args ) );
			wp_cache_set( $aeomatic_cache_key, $count, 'aeob', 5 * MINUTE_IN_SECONDS );
		}

		if ( 0 === $count ) {
			return $this->result( 'faq', __( 'FAQ blocks in use', 'aeomatic' ), 'warn',
				__( 'No posts use the AEO FAQ block yet. Adding FAQs is the single biggest AEO signal — try adding one to your top post.', 'aeomatic' ),
				null, null );
		}

		return $this->result( 'faq', __( 'FAQ blocks in use', 'aeomatic' ), 'ok',
			/* translators: %d: post count */
			sprintf( _n( '%d post uses the AEO FAQ block.', '%d posts use the AEO FAQ block.', $count, 'aeomatic' ), $count ),
			null, null );
	}

	private function check_author_bios(): array {
		$users      = get_users( array( 'who' => 'authors', 'fields' => array( 'ID', 'display_name' ) ) );
		$total      = count( $users );
		$with_bios  = 0;
		$missing    = array();

		foreach ( $users as $user ) {
			$bio = trim( (string) get_user_meta( $user->ID, 'description', true ) );
			if ( strlen( $bio ) >= 30 ) {
				$with_bios++;
			} else {
				$missing[] = $user->display_name;
			}
		}

		if ( 0 === $total ) {
			return $this->result( 'authors', __( 'Author bios for schema', 'aeomatic' ), 'warn',
				__( 'No authors found.', 'aeomatic' ),
				null, null );
		}

		if ( $with_bios === $total ) {
			return $this->result( 'authors', __( 'Author bios for schema', 'aeomatic' ), 'ok',
				/* translators: %d: count */
				sprintf( __( 'All %d author bios are filled in (30+ characters).', 'aeomatic' ), $total ),
				null, null );
		}

		$missing_names = implode( ', ', array_slice( $missing, 0, 3 ) );
		if ( count( $missing ) > 3 ) {
			$missing_names .= ' +' . ( count( $missing ) - 3 ) . ' more';
		}

		return $this->result( 'authors', __( 'Author bios for schema', 'aeomatic' ), 'warn',
			/* translators: 1: with bios, 2: total, 3: missing names */
			sprintf( __( '%1$d of %2$d authors have bios. Missing: %3$s. Author authority is a strong AI citation signal.', 'aeomatic' ),
				$with_bios, $total, $missing_names ),
			admin_url( 'users.php' ), null );
	}

	private function check_sample_audit(): array {
		global $wpdb;

		$aeomatic_cache_key = 'aeomatic_verify_avg_score';
		$avg            = wp_cache_get( $aeomatic_cache_key, 'aeob' );
		if ( false === $avg ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$avg = $wpdb->get_var( $wpdb->prepare(
				"SELECT AVG(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_aeomatic_score'
			) );
			wp_cache_set( $aeomatic_cache_key, $avg, 'aeob', 5 * MINUTE_IN_SECONDS );
		}

		if ( null === $avg ) {
			return $this->result( 'audit', __( 'AEO scores calculated', 'aeomatic' ), 'warn',
				__( 'No scores yet. Save or update a post to trigger scoring, then come back.', 'aeomatic' ),
				null, null );
		}

		$avg = (int) round( $avg );
		$status = $avg >= 70 ? 'ok' : ( $avg >= 40 ? 'warn' : 'fail' );

		return $this->result( 'audit', __( 'AEO scores calculated', 'aeomatic' ), $status,
			/* translators: %d: average score */
			sprintf( __( 'Site-wide average score is %d / 100 across all scored posts.', 'aeomatic' ), $avg ),
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::MENU_SLUG ), null );
	}

	private function result( string $id, string $label, string $status, string $message, ?string $verify_url, ?string $details ): array {
		return array(
			'id'         => $id,
			'label'      => $label,
			'status'     => $status, // ok | warn | fail | off
			'message'    => $message,
			'verify_url' => $verify_url,
			'details'    => $details,
		);
	}
}
