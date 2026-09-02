<?php
/**
 * AI Citation Gaps orchestrator.
 *
 * Runs the internal-coverage and answer-shape analyzers against a post,
 * caches the result in post meta keyed by content fingerprint, exposes
 * REST endpoints for the editor sidebar and admin subpage, and rebuilds
 * the cache on save_post.
 *
 * Free-tier feature: all analysis is local. No external calls.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once AEOMATIC_DIR . 'includes/gaps/class-gap-result.php';
require_once AEOMATIC_DIR . 'includes/gaps/class-gap-internal-coverage.php';
require_once AEOMATIC_DIR . 'includes/gaps/class-gap-answer-shape.php';

class AEOMATIC_Citation_Gaps {

	public const META_GAPS        = '_aeomatic_citation_gaps';
	public const META_FINGERPRINT = '_aeomatic_citation_gaps_fingerprint';
	public const META_SCORE       = '_aeomatic_citation_gaps_score';
	public const REST_NS          = 'aeomatic/v1';

	public function register(): void {
		add_action( 'init',         array( $this, 'register_post_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post',     array( $this, 'on_save_post' ), 25, 2 );
	}

	public function register_post_meta(): void {
		$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}
		$auth = static function () { return current_user_can( 'edit_posts' ); };

		foreach ( $post_types as $pt ) {
			register_post_meta( $pt, self::META_GAPS, array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			) );
			register_post_meta( $pt, self::META_FINGERPRINT, array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
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
		register_rest_route( self::REST_NS, '/citation-gaps/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'route_gaps_for_post' ),
			'permission_callback' => static function ( WP_REST_Request $r ) {
				// Per-post analysis — gate on this post, not on the generic
				// edit_posts capability, so a contributor cannot read the gap
				// report for someone else's draft.
				return current_user_can( 'edit_post', (int) $r->get_param( 'id' ) );
			},
			'args'                => array(
				'id'    => array( 'required' => true, 'type' => 'integer' ),
				'force' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			),
		) );

		register_rest_route( self::REST_NS, '/citation-gaps/fix/(?P<id>\d+)', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'route_gap_fix' ),
			'permission_callback' => static function ( WP_REST_Request $r ) {
				return current_user_can( 'edit_post', (int) $r->get_param( 'id' ) );
			},
			'args'                => array(
				'id'     => array( 'required' => true,  'type' => 'integer' ),
				'code'   => array( 'required' => true,  'type' => 'string' ),
				'params' => array( 'required' => false, 'type' => 'object', 'default' => array() ),
			),
		) );

		register_rest_route( self::REST_NS, '/citation-gaps/site', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'route_gaps_site' ),
			'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
			'args'                => array(
				'paged'     => array( 'required' => false, 'type' => 'integer', 'default' => 1 ),
				'per_page'  => array( 'required' => false, 'type' => 'integer', 'default' => 20 ),
				'post_type' => array( 'required' => false, 'type' => 'string',  'default' => '' ),
				'severity'  => array( 'required' => false, 'type' => 'string',  'default' => '' ),
				'gap_code'  => array( 'required' => false, 'type' => 'string',  'default' => '' ),
				'score_min' => array( 'required' => false, 'type' => 'integer', 'default' => 0 ),
				'score_max' => array( 'required' => false, 'type' => 'integer', 'default' => 100 ),
				'search'    => array( 'required' => false, 'type' => 'string',  'default' => '' ),
				'after'     => array( 'required' => false, 'type' => 'string',  'default' => '' ),
				'before'    => array( 'required' => false, 'type' => 'string',  'default' => '' ),
				'author'    => array( 'required' => false, 'type' => 'integer', 'default' => 0 ),
			),
		) );
	}

	public function route_gaps_for_post( WP_REST_Request $req ) {
		$id = (int) $req->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}
		$force = (bool) $req->get_param( 'force' );
		$data  = $this->get_or_build( $post, $force );
		return rest_ensure_response( $data );
	}

	/**
	 * Quick Fix dispatcher. Each fix code is a small, reversible mutation
	 * on a single post — no bulk actions, no destructive changes.
	 */
	public function route_gap_fix( WP_REST_Request $req ) {
		$id   = (int) $req->get_param( 'id' );
		$code = sanitize_key( (string) $req->get_param( 'code' ) );
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}

		$raw    = $req->get_param( 'params' );
		$params = is_array( $raw ) ? $raw : ( is_object( $raw ) ? (array) $raw : array() );

		switch ( $code ) {
			case 'set_schema_type':
				$type = isset( $params['type'] ) ? (string) $params['type'] : '';
				if ( '' === $type || ! class_exists( 'AEOMATIC_Schema_Analyzer' ) ) {
					return new WP_Error( 'bad_request', __( 'Missing or unsupported schema type.', 'aeomatic' ), array( 'status' => 400 ) );
				}
				$known = (array) AEOMATIC_Schema_Analyzer::known_types();
				if ( ! in_array( $type, $known, true ) ) {
					return new WP_Error( 'bad_request', __( 'Unknown schema type.', 'aeomatic' ), array( 'status' => 400 ) );
				}
				AEOMATIC_Schema_Analyzer::set_override( $id, $type );
				AEOMATIC_Schema_Analyzer::invalidate_cache( $id );
				$message = sprintf(
					/* translators: %s: schema type */
					__( 'Schema type set to %s.', 'aeomatic' ),
					$type
				);
				break;

			case 'generate_meta_description':
				$excerpt = trim( (string) $post->post_excerpt );
				// Extracted text, not post_content — otherwise this quick-fix reports
				// "no content to summarise" on every page-builder page.
				$plain   = trim( (string) ( AEOMATIC_Content_Extractor::extract( $post )['text'] ?? '' ) );
				// Pick the first sentence-ish chunk of plain content, cap at 155 chars.
				$candidate = '';
				if ( '' !== $plain ) {
					// Find the first sentence break within 200 chars, else hard-cap.
					$window = mb_substr( $plain, 0, 200 );
					if ( preg_match( '/^(.{40,155}?[.!?])\s/u', $window, $m ) ) {
						$candidate = trim( $m[1] );
					} else {
						$candidate = trim( mb_substr( $plain, 0, 155 ) );
					}
				}
				if ( '' === $candidate ) {
					return new WP_Error( 'no_content', __( 'Post has no content to summarise.', 'aeomatic' ), array( 'status' => 400 ) );
				}

				// Write to the active SEO plugin's meta key if one is present; always
				// also update post_excerpt so the value is discoverable regardless.
				$targets = array(
					'yoast'      => '_yoast_wpseo_metadesc',
					'rankmath'   => 'rank_math_description',
					'aioseo'     => '_aioseo_description',
					'seopress'   => '_seopress_titles_desc',
				);
				$written_to = array();
				foreach ( $targets as $slug => $key ) {
					// Only write if the plugin already stores meta for this post
					// (avoid writing keys for plugins the user doesn't have).
					if ( '' !== (string) get_post_meta( $id, $key, true ) || $this->is_seo_plugin_active( $slug ) ) {
						update_post_meta( $id, $key, $candidate );
						$written_to[] = $slug;
					}
				}
				if ( '' === $excerpt ) {
					wp_update_post( array( 'ID' => $id, 'post_excerpt' => $candidate ) );
					$written_to[] = 'excerpt';
				}
				$message = sprintf(
					/* translators: %s: comma-separated list of targets updated */
					__( 'Meta description generated (%s).', 'aeomatic' ),
					implode( ', ', $written_to ?: array( 'excerpt' ) )
				);
				break;

			default:
				return new WP_Error( 'unknown_fix', __( 'Unknown fix code.', 'aeomatic' ), array( 'status' => 400 ) );
		}

		// Rebuild gaps so the caller sees the update reflected immediately.
		$refreshed = $this->get_or_build( $post, true );

		return rest_ensure_response( array(
			'ok'      => true,
			'message' => $message,
			'gaps'    => $refreshed,
		) );
	}

	private function is_seo_plugin_active( string $slug ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$map = array(
			'yoast'    => 'wordpress-seo/wp-seo.php',
			'rankmath' => 'seo-by-rank-math/rank-math.php',
			'aioseo'   => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
			'seopress' => 'wp-seopress/seopress.php',
		);
		return isset( $map[ $slug ] ) && is_plugin_active( $map[ $slug ] );
	}

	public function route_gaps_site( WP_REST_Request $req ) {
		$paged     = max( 1, (int) $req->get_param( 'paged' ) );
		$per_page  = max( 1, min( 100, (int) $req->get_param( 'per_page' ) ) );
		$post_type = sanitize_key( (string) $req->get_param( 'post_type' ) );
		$severity  = sanitize_key( (string) $req->get_param( 'severity' ) );
		$gap_code  = sanitize_key( (string) $req->get_param( 'gap_code' ) );
		$score_min = max( 0, min( 100, (int) $req->get_param( 'score_min' ) ) );
		$score_max = max( 0, min( 100, (int) $req->get_param( 'score_max' ) ) );
		if ( $score_min > $score_max ) {
			[ $score_min, $score_max ] = array( $score_max, $score_min );
		}
		$search = trim( (string) $req->get_param( 'search' ) );
		$after  = trim( (string) $req->get_param( 'after' ) );   // YYYY-MM-DD
		$before = trim( (string) $req->get_param( 'before' ) );  // YYYY-MM-DD
		$author = max( 0, (int) $req->get_param( 'author' ) );

		$enabled = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( '' !== $post_type && in_array( $post_type, $enabled, true ) ) {
			$types = array( $post_type );
		} else {
			$types = $enabled;
		}

		$query_args = array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'meta_value_num',
			'meta_key'       => self::META_SCORE,
			'order'          => 'ASC',
			'no_found_rows'  => false,
			'meta_query'     => array(
				array(
					'key'     => self::META_SCORE,
					'value'   => array( $score_min, $score_max ),
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				),
			),
		);

		if ( $author > 0 ) {
			$query_args['author'] = $author;
		}

		$date_query = array();
		if ( '' !== $after  && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $after ) )  { $date_query['after']  = $after; }
		if ( '' !== $before && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $before ) ) { $date_query['before'] = $before; }
		if ( ! empty( $date_query ) ) {
			$date_query['column']    = 'post_modified';
			$date_query['inclusive'] = true;
			$query_args['date_query'] = array( $date_query );
		}

		// Title-only search — WP_Query's default `s` also matches content.
		$title_filter = null;
		if ( '' !== $search ) {
			$title_filter = static function ( $where, $wp_q ) use ( $search ) {
				global $wpdb;
				$like = '%' . $wpdb->esc_like( $search ) . '%';
				return $where . $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $like );
			};
			add_filter( 'posts_where', $title_filter, 10, 2 );
		}

		$q = new WP_Query( $query_args );

		if ( $title_filter ) {
			remove_filter( 'posts_where', $title_filter, 10 );
		}

		$rows = array();
		foreach ( $q->posts as $post ) {
			$gaps  = $this->read_cached_gaps( $post->ID );
			$score = (int) get_post_meta( $post->ID, self::META_SCORE, true );

			if ( '' !== $severity ) {
				$gaps = array_values( array_filter( $gaps, static fn( $g ) => ( $g['severity'] ?? '' ) === $severity ) );
			}
			if ( '' !== $gap_code ) {
				$gaps = array_values( array_filter( $gaps, static fn( $g ) => ( $g['code'] ?? '' ) === $gap_code ) );
			}
			if ( ( '' !== $severity || '' !== $gap_code ) && empty( $gaps ) ) {
				continue;
			}

			$rows[] = array(
				'id'         => (int) $post->ID,
				'title'      => (string) get_the_title( $post ),
				'edit_url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
				'view_url'   => (string) get_permalink( $post ),
				'post_type'  => (string) $post->post_type,
				'score'      => $score,
				'gap_count'  => count( $gaps ),
				'top_gaps'   => array_slice( $gaps, 0, 3 ),
			);
		}

		return rest_ensure_response( array(
			'rows'        => $rows,
			'paged'       => $paged,
			'per_page'    => $per_page,
			'total'       => (int) $q->found_posts,
			'total_pages' => (int) $q->max_num_pages,
			'summary'     => $this->compute_summary( $types ),
		) );
	}

	/**
	 * Site-wide summary for the dashboard widget (resolves enabled post types
	 * automatically so external callers don't need to pass them).
	 *
	 * @return array{scanned:int,with_gaps:int,clean:int,avg_score:int,not_scanned:int}
	 */
	public function site_summary(): array {
		$types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $types ) ) {
			$types = array( 'post' );
		}
		return $this->compute_summary( $types );
	}

	/**
	 * Compute the scanner-summary numbers shown at the top of AEOmatic → Citation Gaps.
	 * Runs against cached meta only — no live analysis.
	 *
	 * @return array{scanned:int,with_gaps:int,clean:int,avg_score:int,not_scanned:int}
	 */
	public function compute_summary( array $types ): array {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$publish      = 'publish';

		// Total published posts across enabled types.
		$total_published = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ($placeholders)",
			array_merge( array( $publish ), $types )
		) );

		// Posts that have been scanned (have a score meta row).
		$scanned = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
			 WHERE p.post_status = %s AND p.post_type IN ($placeholders)",
			array_merge( array( self::META_SCORE, $publish ), $types )
		) );

		// Clean = score of 100. With gaps = anything below.
		$clean = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
			 WHERE p.post_status = %s AND p.post_type IN ($placeholders) AND CAST(m.meta_value AS UNSIGNED) = 100",
			array_merge( array( self::META_SCORE, $publish ), $types )
		) );

		// Average score across scanned posts.
		$avg = $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(CAST(m.meta_value AS UNSIGNED)) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
			 WHERE p.post_status = %s AND p.post_type IN ($placeholders)",
			array_merge( array( self::META_SCORE, $publish ), $types )
		) );

		return array(
			'scanned'     => $scanned,
			'with_gaps'   => max( 0, $scanned - $clean ),
			'clean'       => $clean,
			'avg_score'   => null === $avg ? 0 : (int) round( (float) $avg ),
			'not_scanned' => max( 0, $total_published - $scanned ),
		);
	}

	public function on_save_post( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status && 'draft' !== $post->post_status && 'private' !== $post->post_status ) {
			return;
		}
		if ( ! in_array( $post->post_type, AEOMATIC_Post_Types::get_enabled_slugs(), true ) ) {
			return;
		}
		$this->get_or_build( $post, true );
	}

	/**
	 * Public entry point for bulk operations — rebuild the gap payload for a
	 * post regardless of fingerprint. Returns the freshly-computed result.
	 *
	 * @return array{gaps:array,score:int,cached:bool,generated_at:int}|null Null if the post cannot be resolved.
	 */
	public function rebuild( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		return $this->get_or_build( $post, true );
	}

	/**
	 * Return the cached gap payload for a post, rebuilding if the fingerprint
	 * has changed or $force is true.
	 *
	 * @return array{gaps:array,score:int,cached:bool,generated_at:int}
	 */
	private function get_or_build( WP_Post $post, bool $force ): array {
		$fingerprint = $this->fingerprint_for( $post );
		$stored_fp   = (string) get_post_meta( $post->ID, self::META_FINGERPRINT, true );

		if ( ! $force && $fingerprint === $stored_fp ) {
			$cached = $this->read_cached_gaps( $post->ID );
			$score  = (int) get_post_meta( $post->ID, self::META_SCORE, true );
			return array(
				'gaps'         => $cached,
				'score'        => $score,
				'cached'       => true,
				'generated_at' => (int) get_post_meta( $post->ID, self::META_FINGERPRINT . '_at', true ),
			);
		}

		$extract = class_exists( 'AEOMATIC_Content_Extractor' )
			? AEOMATIC_Content_Extractor::extract( $post )
			: array( 'html' => (string) $post->post_content, 'text' => wp_strip_all_tags( (string) $post->post_content ), 'faqs' => array(), 'word_count' => str_word_count( wp_strip_all_tags( (string) $post->post_content ) ) );

		$gaps = array();
		$gaps = array_merge( $gaps, ( new AEOMATIC_Gap_Internal_Coverage() )->analyze( $post, $extract ) );
		$gaps = array_merge( $gaps, ( new AEOMATIC_Gap_Answer_Shape() )->analyze( $post, $extract ) );

		/**
		 * Allow third parties to add their own gap results (or drop existing ones).
		 *
		 * @param AEOMATIC_Gap_Result[] $gaps
		 * @param WP_Post               $post
		 * @param array                 $extract
		 */
		$gaps = apply_filters( 'aeomatic_citation_gap_results', $gaps, $post, $extract );

		$score = AEOMATIC_Gap_Result::score_for( $gaps );

		$as_arrays = array_map( static fn( $g ) => $g instanceof AEOMATIC_Gap_Result ? $g->to_array() : (array) $g, $gaps );
		// Order by severity high → low, then by code for stability.
		$severity_order = array( 'high' => 0, 'medium' => 1, 'low' => 2 );
		usort( $as_arrays, static function ( $a, $b ) use ( $severity_order ) {
			$sa = $severity_order[ $a['severity'] ?? 'low' ] ?? 3;
			$sb = $severity_order[ $b['severity'] ?? 'low' ] ?? 3;
			if ( $sa !== $sb ) { return $sa - $sb; }
			return strcmp( (string) ( $a['code'] ?? '' ), (string) ( $b['code'] ?? '' ) );
		} );

		update_post_meta( $post->ID, self::META_GAPS, wp_json_encode( $as_arrays ) );
		update_post_meta( $post->ID, self::META_FINGERPRINT, $fingerprint );
		update_post_meta( $post->ID, self::META_FINGERPRINT . '_at', time() );
		update_post_meta( $post->ID, self::META_SCORE, $score );

		return array(
			'gaps'         => $as_arrays,
			'score'        => $score,
			'cached'       => false,
			'generated_at' => time(),
		);
	}

	private function read_cached_gaps( int $post_id ): array {
		$raw = (string) get_post_meta( $post_id, self::META_GAPS, true );
		if ( '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private function fingerprint_for( WP_Post $post ): string {
		$schema_type = 'Article';
		if ( class_exists( 'AEOMATIC_Schema_Analyzer' ) ) {
			$analysis    = AEOMATIC_Schema_Analyzer::analyze( $post );
			$schema_type = (string) ( $analysis['type'] ?? 'Article' );
		}
		return sha1(
			(string) $post->post_content . '|' .
			(string) $post->post_title   . '|' .
			$schema_type                 . '|' .
			(string) $post->post_modified_gmt
		);
	}
}
