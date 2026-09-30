<?php
/**
 * Entity Scanner — orchestrator.
 *
 * Detects six entity types (Organization, Person, Service, Product,
 * LocalBusiness, FAQ) across four sources (content, schema, headings,
 * metadata) and returns a coverage score, missing entities, and
 * recommendations.
 *
 * Follows the same architectural pattern as AEOMATIC_AI_Readiness:
 *   - one shared signal collector consumed by every detector
 *   - detector registry via filter (aeomatic_entity_detectors)
 *   - REST route under the existing aeomatic/v1 namespace
 *   - fingerprint-cached in post meta, invalidated on save_post
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once AEOMATIC_DIR . 'includes/entities/interface-aeomatic-entity-detector.php';
require_once AEOMATIC_DIR . 'includes/entities/class-aeomatic-entity-detection-result.php';
require_once AEOMATIC_DIR . 'includes/entities/class-aeomatic-entity-signal-collector.php';
require_once AEOMATIC_DIR . 'includes/entities/class-aeomatic-detector-organization.php';
require_once AEOMATIC_DIR . 'includes/entities/class-aeomatic-detector-person.php';
require_once AEOMATIC_DIR . 'includes/entities/class-aeomatic-detector-service.php';
require_once AEOMATIC_DIR . 'includes/entities/class-aeomatic-detector-product.php';
require_once AEOMATIC_DIR . 'includes/entities/class-aeomatic-detector-local-business.php';
require_once AEOMATIC_DIR . 'includes/entities/class-aeomatic-detector-faq.php';

class AEOMATIC_Entity_Scanner {

	public const META_RESULT      = '_aeomatic_entity_scan';
	public const META_FINGERPRINT = '_aeomatic_entity_scan_fingerprint';
	public const META_COVERAGE    = '_aeomatic_entity_coverage';
	public const REST_NS          = 'aeomatic/v1';

	private const DETECTED_MIN_CONFIDENCE = 60;
	private const MISSING_MAX_CONFIDENCE  = 40;

	public function register(): void {
		add_action( 'init',          array( $this, 'register_post_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post',     array( $this, 'invalidate_on_save' ), 30, 2 );
	}

	public function register_post_meta(): void {
		$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $post_types ) ) { $post_types = array( 'post' ); }
		$auth = static function () { return current_user_can( 'edit_posts' ); };
		foreach ( $post_types as $pt ) {
			register_post_meta( $pt, self::META_RESULT, array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			) );
			register_post_meta( $pt, self::META_COVERAGE, array(
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			) );
		}
	}

	public function register_routes(): void {
		register_rest_route( self::REST_NS, '/entity-scan/(?P<id>\d+)', array(
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

	public function route_for_post( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$force   = (bool) $request->get_param( 'force' );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}
		return new WP_REST_Response( $this->scan( $post, $force ), 200 );
	}

	/**
	 * Run every registered detector against the post and aggregate.
	 *
	 * @return array{coverage_score:int, entities:array, missing_entities:string[], recommendations:array, fingerprint:string, cached:bool}
	 */
	public function scan( WP_Post $post, bool $force = false ): array {
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

		$signals = new AEOMATIC_Entity_Signal_Collector( $post );
		$results = array();
		foreach ( $this->detectors() as $detector ) {
			if ( ! $detector instanceof AEOMATIC_Entity_Detector_Interface ) { continue; }
			$results[] = $detector->detect( $post, $signals )->to_array();
		}

		$coverage         = $this->coverage_score( $results );
		$missing_entities = $this->missing_entities( $results );
		$recommendations  = $this->flatten_recommendations( $results );

		$payload = array(
			'coverage_score'   => $coverage,
			'entities'         => $results,
			'missing_entities' => $missing_entities,
			'recommendations'  => $recommendations,
			'fingerprint'      => $fingerprint,
			'cached'           => false,
		);

		// wp_slash: update_post_meta() unslashes, which would corrupt the JSON's escapes.
		update_post_meta( $post->ID, self::META_RESULT, wp_slash( wp_json_encode( $payload ) ) );
		update_post_meta( $post->ID, self::META_FINGERPRINT, $fingerprint );
		update_post_meta( $post->ID, self::META_COVERAGE, $coverage );

		return $payload;
	}

	/**
	 * @return AEOMATIC_Entity_Detector_Interface[]
	 */
	private function detectors(): array {
		$default = array(
			new AEOMATIC_Detector_Organization(),
			new AEOMATIC_Detector_Person(),
			new AEOMATIC_Detector_Service(),
			new AEOMATIC_Detector_Product(),
			new AEOMATIC_Detector_Local_Business(),
			new AEOMATIC_Detector_Faq(),
		);
		$filtered = apply_filters( 'aeomatic_entity_detectors', $default );
		return is_array( $filtered ) ? $filtered : $default;
	}

	private function coverage_score( array $results ): int {
		if ( empty( $results ) ) { return 0; }
		$sum = 0;
		foreach ( $results as $r ) {
			$sum += isset( $r['confidence'] ) ? (int) $r['confidence'] : 0;
		}
		return (int) round( $sum / count( $results ) );
	}

	private function missing_entities( array $results ): array {
		$out = array();
		foreach ( $results as $r ) {
			$detected   = ! empty( $r['detected'] );
			$confidence = isset( $r['confidence'] ) ? (int) $r['confidence'] : 0;
			if ( ! $detected && $confidence < self::MISSING_MAX_CONFIDENCE ) {
				$out[] = (string) ( $r['entity_type'] ?? '' );
			}
		}
		return array_values( array_filter( $out ) );
	}

	private function flatten_recommendations( array $results ): array {
		$out = array();
		foreach ( $results as $r ) {
			$entity = (string) ( $r['entity_type'] ?? '' );
			foreach ( (array) ( $r['recommendations'] ?? array() ) as $rec ) {
				$out[] = array( 'entity_type' => $entity, 'text' => (string) $rec );
			}
		}
		return $out;
	}

	private function fingerprint_for( WP_Post $post ): string {
		return md5( $post->post_content . '|' . $post->post_title . '|' . $post->post_modified_gmt );
	}

	public function invalidate_on_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
		delete_post_meta( $post_id, self::META_FINGERPRINT );
	}

	public static function detected_threshold(): int { return self::DETECTED_MIN_CONFIDENCE; }
}
