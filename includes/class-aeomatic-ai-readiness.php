<?php
/**
 * AI Readiness — per-post score across five categories.
 *
 * Aggregates content-quality, trust, authority, entities, and technical signals
 * into a single 0-100 readiness score with actionable issues + recommendations.
 * Depends on the analyzer interface, not on concrete analyzers (DIP): each
 * category is registered via the `aeomatic_ai_readiness_analyzers` filter,
 * so new categories can be added without touching this class (OCP).
 *
 * REST routes live under the existing `aeomatic/v1` namespace for consistency
 * with AEOMATIC_Audit and AEOMATIC_Citation_Gaps.
 *
 * Cached in post meta keyed by a content fingerprint (same pattern as
 * AEOMATIC_Citation_Gaps) to avoid recomputing on every read.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once AEOMATIC_DIR . 'includes/readiness/interface-aeomatic-analyzer.php';
require_once AEOMATIC_DIR . 'includes/readiness/class-aeomatic-readiness-result.php';
require_once AEOMATIC_DIR . 'includes/readiness/class-aeomatic-analyzer-content.php';
require_once AEOMATIC_DIR . 'includes/readiness/class-aeomatic-analyzer-trust.php';
require_once AEOMATIC_DIR . 'includes/readiness/class-aeomatic-analyzer-authority.php';
require_once AEOMATIC_DIR . 'includes/readiness/class-aeomatic-analyzer-entities.php';
require_once AEOMATIC_DIR . 'includes/readiness/class-aeomatic-analyzer-technical.php';

class AEOMATIC_AI_Readiness {

	public const META_RESULT      = '_aeomatic_ai_readiness';
	public const META_FINGERPRINT = '_aeomatic_ai_readiness_fingerprint';
	public const META_SCORE       = '_aeomatic_ai_readiness_score';
	public const REST_NS          = 'aeomatic/v1';

	public function register(): void {
		add_action( 'init',          array( $this, 'register_post_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post',     array( $this, 'invalidate_on_save' ), 30, 2 );
	}

	public function register_post_meta(): void {
		$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}
		// Computed by the plugin: readable over REST, never writable there.
		$auth = '__return_false';

		foreach ( $post_types as $pt ) {
			register_post_meta( $pt, self::META_RESULT, array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			) );
			register_post_meta( $pt, self::META_SCORE, array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			) );
		}
	}

	public function register_routes(): void {
		register_rest_route( self::REST_NS, '/ai-readiness/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'route_for_post' ),
			'permission_callback' => static function ( WP_REST_Request $r ) {
				return current_user_can( 'edit_post', (int) $r->get_param( 'id' ) );
			},
			'args'                => array(
				'id'    => array( 'required' => true, 'type' => 'integer' ),
				'force' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			),
		) );
	}

	/**
	 * REST GET handler. Returns cached result or recomputes on `force=true`
	 * or fingerprint mismatch.
	 */
	public function route_for_post( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$force   = (bool) $request->get_param( 'force' );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}
		return new WP_REST_Response( $this->analyze( $post, $force ), 200 );
	}

	/**
	 * Analyze a post across every registered category. Returns the aggregated
	 * report as an array (for direct REST serialization). Reads from cache
	 * unless `$force` or the fingerprint has changed.
	 *
	 * @return array{overall_score:int, categories:array<int,array>, fingerprint:string, cached:bool}
	 */
	public function analyze( WP_Post $post, bool $force = false ): array {
		$fingerprint = $this->fingerprint_for( $post );

		if ( ! $force ) {
			$cached_fp = get_post_meta( $post->ID, self::META_FINGERPRINT, true );
			if ( $cached_fp === $fingerprint ) {
				$cached_json = get_post_meta( $post->ID, self::META_RESULT, true );
				if ( is_string( $cached_json ) && $cached_json !== '' ) {
					$cached = json_decode( $cached_json, true );
					if ( is_array( $cached ) ) {
						$cached['cached'] = true;
						return $cached;
					}
				}
			}
		}

		$results = array();
		foreach ( $this->analyzers() as $analyzer ) {
			if ( ! $analyzer instanceof AEOMATIC_Readiness_Analyzer_Interface ) {
				continue;
			}
			$results[] = $analyzer->analyze( $post )->to_array();
		}

		$overall = $this->overall_score( $results );
		$payload = array(
			'overall_score' => $overall,
			'categories'    => $results,
			'fingerprint'   => $fingerprint,
			'cached'        => false,
		);

		// wp_slash: update_post_meta() unslashes, which would corrupt the JSON's escapes.
		update_post_meta( $post->ID, self::META_RESULT, wp_slash( wp_json_encode( $payload ) ) );
		update_post_meta( $post->ID, self::META_FINGERPRINT, $fingerprint );
		update_post_meta( $post->ID, self::META_SCORE, $overall );

		return $payload;
	}

	/**
	 * Registry of category analyzers. Filter `aeomatic_ai_readiness_analyzers`
	 * lets third parties add or replace categories without editing this class.
	 *
	 * @return AEOMATIC_Readiness_Analyzer_Interface[]
	 */
	private function analyzers(): array {
		$default = array(
			new AEOMATIC_Analyzer_Content(),
			new AEOMATIC_Analyzer_Trust(),
			new AEOMATIC_Analyzer_Authority(),
			new AEOMATIC_Analyzer_Entities(),
			new AEOMATIC_Analyzer_Technical(),
		);
		$filtered = apply_filters( 'aeomatic_ai_readiness_analyzers', $default );
		return is_array( $filtered ) ? $filtered : $default;
	}

	private function overall_score( array $categories ): int {
		if ( empty( $categories ) ) {
			return 0;
		}
		$sum = 0;
		foreach ( $categories as $c ) {
			$sum += isset( $c['score'] ) ? (int) $c['score'] : 0;
		}
		return (int) round( $sum / count( $categories ) );
	}

	private function fingerprint_for( WP_Post $post ): string {
		// Scores also depend on the plugin settings, the schema type and field
		// overrides (saved without touching post_modified) and the author bio,
		// so a change to any of them must re-run the analysis.
		return md5( implode( '|', array(
			$post->post_content,
			$post->post_title,
			$post->post_modified_gmt,
			(string) get_post_meta( $post->ID, AEOMATIC_Schema_Analyzer::META_OVERRIDE, true ),
			(string) get_post_meta( $post->ID, AEOMATIC_Schema_Generator::META_OVERRIDES, true ),
			(string) get_the_author_meta( 'description', (int) $post->post_author ),
			md5( (string) wp_json_encode( AEOMATIC_Settings::all() ) ),
		) ) );
	}

	public function invalidate_on_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		delete_post_meta( $post_id, self::META_FINGERPRINT );
	}
}
