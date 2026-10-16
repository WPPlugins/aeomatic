<?php
/**
 * AEO Audit engine.
 *
 * Scores a post on a 0–100 scale based on the AEO readiness rubric.
 * Exposes a REST endpoint that the Gutenberg sidebar consumes,
 * and persists the score in post meta for dashboard reporting.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Audit {

	const META_SCORE = '_aeomatic_score';
	const META_BREAKDOWN = '_aeomatic_score_breakdown';
	const REST_NS    = 'aeomatic/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
		add_action( 'init', array( $this, 'register_post_meta' ) );
	}

	public function register_post_meta(): void {
		$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		foreach ( $post_types as $pt ) {
			register_post_meta( $pt, self::META_SCORE, array(
				'type'         => 'integer',
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
			register_post_meta( $pt, self::META_BREAKDOWN, array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
			register_post_meta( $pt, AEOMATIC_Schema_Analyzer::META_OVERRIDE, array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
			) );
			register_post_meta( $pt, AEOMATIC_Schema_Generator::META_OVERRIDES, array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
			) );
		}
	}

	public function register_routes(): void {
		register_rest_route( self::REST_NS, '/audit/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_audit' ),
			'permission_callback' => function ( $request ) {
				$post_id = (int) $request['id'];
				return current_user_can( 'edit_post', $post_id );
			},
			'args'                => array(
				'id' => array(
					'validate_callback' => static function ( $v ) { return is_numeric( $v ); },
				),
			),
		) );

		register_rest_route( self::REST_NS, '/audit/site', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_site_audit' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		// Schema analysis — read.
		register_rest_route( self::REST_NS, '/schema/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_schema_analysis' ),
			'permission_callback' => function ( $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
		) );

		// Schema override — write type only.
		register_rest_route( self::REST_NS, '/schema/(?P<id>\d+)', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_schema_override' ),
			'permission_callback' => function ( $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
			'args'                => array(
				'type' => array( 'type' => 'string', 'required' => true ),
			),
		) );

		// Visual field editor — read current values (auto-extracted + overrides).
		register_rest_route( self::REST_NS, '/schema/(?P<id>\d+)/fields', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_schema_fields_get' ),
			'permission_callback' => function ( $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
		) );

		// Visual field editor — save per-property overrides.
		register_rest_route( self::REST_NS, '/schema/(?P<id>\d+)/fields', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_schema_fields_save' ),
			'permission_callback' => function ( $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
		) );

		// Reset one field or all overrides to auto-extracted values.
		register_rest_route( self::REST_NS, '/schema/(?P<id>\d+)/fields/reset', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_schema_fields_reset' ),
			'permission_callback' => function ( $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
		) );

		// Live schema preview — returns the actual JSON-LD that will be emitted.
		// GET returns the saved-state preview; POST accepts an in-flight `values`
		// map from the editor so the panel reflects unsaved edits in real time.
		register_rest_route( self::REST_NS, '/schema/(?P<id>\d+)/preview', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( $this, 'rest_schema_preview' ),
			'permission_callback' => function ( $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
		) );
	}

	/**
	 * Return the schema analysis for a single post.
	 */
	public function rest_schema_analysis( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}

		$analysis = AEOMATIC_Schema_Analyzer::analyze( $post );
		$known    = AEOMATIC_Schema_Analyzer::known_types();

		// Pretty-print the alternatives for the UI.
		$alts = array();
		foreach ( $analysis['alternatives'] as $type => $score ) {
			$alts[] = array(
				'type'       => $type,
				'label'      => $known[ $type ]['label'] ?? $type,
				'confidence' => min( 95, $score ),
			);
		}

		return rest_ensure_response( array(
			'type'         => $analysis['type'],
			'type_label'   => $known[ $analysis['type'] ]['label'] ?? $analysis['type'],
			'confidence'   => $analysis['confidence'],
			'reasons'      => $analysis['reasons'],
			'alternatives' => $alts,
			'source'       => $analysis['source'],
			'all_types'    => array_map(
				static function ( $info, $key ) { return array( 'type' => $key, 'label' => $info['label'], 'description' => $info['description'] ); },
				$known,
				array_keys( $known )
			),
		) );
	}

	/**
	 * Save a manual schema override.
	 */
	public function rest_schema_override( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$type    = sanitize_text_field( (string) $request['type'] );
		$ok      = AEOMATIC_Schema_Analyzer::set_override( $post_id, $type );
		return rest_ensure_response( array( 'ok' => (bool) $ok, 'type' => $type ) );
	}

	/**
	 * Return the field definitions + merged values for the visual editor.
	 */
	public function rest_schema_fields_get( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}

		$analysis      = AEOMATIC_Schema_Analyzer::analyze( $post );
		$type          = $analysis['type'];
		$settings      = (array) AEOMATIC_Settings::get( 'schema' );
		$field_defs    = AEOMATIC_Schema_Fields::for_type( $type );
		$values        = AEOMATIC_Schema_Generator::get_editor_values( $post, $type, $settings );
		$overridden    = AEOMATIC_Schema_Generator::get_overridden_keys( $post->ID, $type );
		$known         = AEOMATIC_Schema_Analyzer::known_types();

		return rest_ensure_response( array(
			'post_id'       => $post->ID,
			// For the editor's "Test this post with Google" link. Only a
			// published post has a URL Google can fetch.
			'permalink'     => 'publish' === $post->post_status ? (string) get_permalink( $post ) : '',
			'type'          => $type,
			'type_label'    => $known[ $type ]['label'] ?? $type,
			'confidence'    => $analysis['confidence'],
			'source'        => $analysis['source'],
			'reasons'       => $analysis['reasons'],
			'all_types'     => array_map(
				static fn( $key, $info ) => array( 'type' => $key, 'label' => $info['label'], 'description' => $info['description'] ),
				array_keys( $known ), array_values( $known )
			),
			'fields'        => $field_defs,
			'values'        => $values,
			'overridden'    => $overridden,
		) );
	}

	/**
	 * Save per-property field overrides from the visual editor.
	 */
	public function rest_schema_fields_save( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'bad_request', __( 'Expected JSON body', 'aeomatic' ), array( 'status' => 400 ) );
		}

		$fields = $body['fields'] ?? array();
		if ( ! is_array( $fields ) ) {
			return new WP_Error( 'bad_request', __( 'fields must be an object', 'aeomatic' ), array( 'status' => 400 ) );
		}

		// Scope the save to the post's current schema type so overrides kept
		// for other types survive, and cleared fields fall back to auto values.
		$type = AEOMATIC_Schema_Analyzer::analyze( $post )['type'];
		$ok   = AEOMATIC_Schema_Generator::save_overrides( $post->ID, $fields, $type );
		return rest_ensure_response( array( 'ok' => $ok, 'post_id' => $post->ID ) );
	}

	/**
	 * Reset one field key or all overrides for a post.
	 */
	public function rest_schema_fields_reset( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}

		$body = $request->get_json_params();
		// Case-preserving whitelist — matches how sanitise_overrides() now
		// stores the key, so resetting an aggregateRating_value actually
		// unsets aggregateRating_value rather than aggregaterating_value.
		$key  = isset( $body['key'] )
			? (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $body['key'] )
			: null;
		if ( '' === $key ) {
			$key = null;
		}

		AEOMATIC_Schema_Generator::reset_overrides( $post->ID, $key );

		return rest_ensure_response( array(
			'ok'      => true,
			'reset'   => $key ?? 'all',
			'post_id' => $post->ID,
		) );
	}

	/**
	 * Live schema preview — returns the JSON-LD blocks the front-end would emit.
	 *
	 * On POST, `values` in the body are treated as in-flight editor overrides
	 * so the preview reflects unsaved edits in real time. On GET, the currently
	 * saved overrides are used. Both paths go through Schema_Generator so the
	 * preview and the actual front-end render come from the same code — the
	 * previous Schema_Builder call path bypassed user overrides entirely, which
	 * is why the preview did not reflect edits even after save.
	 */
	public function rest_schema_preview( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}

		// Invalidate cache so downstream callers see fresh content.
		AEOMATIC_Schema_Builder::invalidate( $post->ID );

		// Optional in-flight overrides from the editor. When null, generate()
		// falls back to reading saved post meta.
		$in_flight = null;
		if ( 'POST' === $request->get_method() ) {
			$body = $request->get_json_params();
			if ( is_array( $body ) && isset( $body['values'] ) && is_array( $body['values'] ) ) {
				$in_flight = $body['values'];
			}
		}

		$analysis = AEOMATIC_Schema_Analyzer::analyze( $post );
		$type     = $analysis['type'];
		$settings = (array) AEOMATIC_Settings::get( 'schema' );

		$blocks = array();

		if ( 'Product' === $type ) {
			$built = AEOMATIC_Schema_Generator::generate( $post, 'Product', $settings, $in_flight );
			if ( $built ) {
				$blocks[] = $built;
			}
		} elseif ( ! empty( $settings['article'] ) ) {
			$built = AEOMATIC_Schema_Generator::generate( $post, $type, $settings, $in_flight );
			if ( $built ) {
				$blocks[] = $built;
			}
		}

		return rest_ensure_response( array(
			'type'       => $type,
			'type_label' => AEOMATIC_Schema_Analyzer::known_types()[ $type ]['label'] ?? $type,
			'confidence' => $analysis['confidence'],
			'source'     => $analysis['source'] ?? 'analyzed',
			'reasons'    => $analysis['reasons'] ?? array(),
			'blocks'     => $blocks,
			'count'      => count( $blocks ),
		) );
	}

	/**
	 * Score one post and return JSON.
	 */
	public function rest_audit( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->score_post( $post ) );
	}

	/**
	 * Site-wide summary for the admin overview (raw data, shared by REST and
	 * the WP dashboard widget). Cached for 5 minutes; the on_save_post handler
	 * clears the transient so scores stay fresh.
	 */
	public function site_summary(): array {
		$cache_key = 'aeomatic_site_audit_cache';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		$query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		$scores = array();
		$worst  = array();
		$by_pt  = array();

		foreach ( $query->posts as $p ) {
			$score = (int) get_post_meta( $p->ID, self::META_SCORE, true );
			if ( ! $score ) {
				$result = $this->score_post( $p );
				$score  = $result['score'];
			}
			$scores[] = $score;

			$pt_obj   = get_post_type_object( $p->post_type );
			$pt_label = $pt_obj ? $pt_obj->labels->singular_name : $p->post_type;

			$worst[] = array(
				'id'        => $p->ID,
				'title'     => get_the_title( $p ),
				'url'       => get_edit_post_link( $p->ID, 'raw' ),
				'score'     => $score,
				'post_type' => $pt_label,
			);
			$by_pt[ $pt_label ] = ( $by_pt[ $pt_label ] ?? 0 ) + 1;
		}

		usort( $worst, static function ( $a, $b ) { return $a['score'] <=> $b['score']; } );

		$result = array(
			'count'        => count( $scores ),
			'average'      => $scores ? (int) round( array_sum( $scores ) / count( $scores ) ) : 0,
			'distribution' => array(
				'good'  => count( array_filter( $scores, static function ( $s ) { return $s >= 70; } ) ),
				'okay'  => count( array_filter( $scores, static function ( $s ) { return $s >= 40 && $s < 70; } ) ),
				'poor'  => count( array_filter( $scores, static function ( $s ) { return $s < 40; } ) ),
			),
			'worst'        => array_slice( $worst, 0, 10 ),
			'by_post_type' => $by_pt,
		);

		set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * REST wrapper for the site summary.
	 */
	public function rest_site_audit() {
		return rest_ensure_response( $this->site_summary() );
	}

	/**
	 * Re-score on save.
	 */
	public function on_save_post( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status && 'draft' !== $post->post_status ) {
			return;
		}
		if ( ! AEOMATIC_Post_Types::is_enabled( $post->post_type ) ) {
			return;
		}
		// Bust the dashboard cache so it reflects the newly saved post.
		delete_transient( 'aeomatic_site_audit_cache' );
		$this->score_post( $post );
	}

	/**
	 * Count the distinct pages on this site that a list of hrefs points to.
	 *
	 * Relative links count, "www." and letter case are ignored, and links to
	 * the post itself, in-page anchors, wp-admin, uploaded files and
	 * mailto/tel/javascript are skipped. Each page counts once.
	 *
	 * @param string[] $hrefs Raw href values.
	 */
	public static function count_internal_links( array $hrefs, WP_Post $post ): int {
		$bare      = static function ( $host ): string {
			return (string) preg_replace( '/^www\./', '', strtolower( (string) $host ) );
		};
		$path_key  = static function ( string $url ): string {
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$path = '/' . trim( $path, '/' );
			return '/' === $path ? '/' : $path . '/';
		};
		$home_host = $bare( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$self      = $path_key( (string) get_permalink( $post ) );

		$pages = array();
		foreach ( $hrefs as $href ) {
			$href = trim( html_entity_decode( (string) $href, ENT_QUOTES ) );
			if ( '' === $href || '#' === $href[0] || preg_match( '#^(mailto|tel|javascript|data):#i', $href ) ) {
				continue;
			}
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( $host ) {
				if ( $bare( $host ) !== $home_host ) {
					continue; // External link.
				}
				$url = $href;
			} elseif ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $href ) ) {
				continue; // Another scheme without a host.
			} else {
				$url = home_url( '/' . ltrim( $href, '/' ) ); // Relative link.
			}
			$key = $path_key( $url );
			if ( $key === $self || preg_match( '#^/(wp-admin|wp-login\.php|wp-content/uploads)(/|$)#', $key ) ) {
				continue;
			}
			$pages[ $key ] = true;
		}
		return count( $pages );
	}

	/**
	 * Compute the AEO score and persist to meta.
	 *
	 * @return array{score:int, breakdown:array<int,array{key:string,label:string,points:int,max:int,passed:bool,hint:string}>}
	 */
	public function score_post( WP_Post $post ): array {
		$extracted    = AEOMATIC_Content_Extractor::extract( $post );
		$content_html = $extracted['html'];
		$content_text = $extracted['text'];
		$word_count   = $extracted['word_count'];
		$builder      = $extracted['builder'];

		$min_words    = (int) AEOMATIC_Settings::get( 'audit', 'min_words' );
		$min_words    = $min_words > 0 ? $min_words : 800;

		$checks = array();

		// 1. Direct answer in first 50 words (15 pts).
		$first_50 = implode( ' ', array_slice( preg_split( '/\s+/', trim( $content_text ) ), 0, 50 ) );
		$has_direct_answer = $word_count >= 50 && strlen( $first_50 ) > 100;
		$checks[] = array(
			'key'    => 'direct_answer',
			'label'  => __( 'Direct answer in opening', 'aeomatic' ),
			'points' => $has_direct_answer ? 15 : 0,
			'max'    => 15,
			'passed' => $has_direct_answer,
			'hint'   => __( 'Open with a clear, complete answer in the first 1–2 sentences, so the answer does not depend on reading the whole page.', 'aeomatic' ),
		);

		// 2. Question-style headings (10 pts).
		preg_match_all( '/<h[2-3][^>]*>(.*?)<\/h[2-3]>/is', $content_html, $hm );
		$headings        = $hm[1] ?? array();
		$question_count  = 0;
		foreach ( $headings as $h ) {
			$text = trim( wp_strip_all_tags( $h ) );
			if ( $text === '' ) {
				continue;
			}
			if ( str_ends_with( $text, '?' ) ) {
				$question_count++;
				continue;
			}
			if ( preg_match( '/^(how|what|why|when|where|who|which|is|are|can|should|do|does)\b/i', $text ) ) {
				$question_count++;
			}
		}
		$question_pts = $question_count >= 2 ? 10 : ( $question_count === 1 ? 5 : 0 );
		$checks[] = array(
			'key'    => 'question_headings',
			'label'  => __( 'Question-style H2/H3 headings', 'aeomatic' ),
			'points' => $question_pts,
			'max'    => 10,
			'passed' => $question_pts === 10,
			'hint'   => __( 'Use natural questions as headings ("How does X work?"). They match how people phrase questions to AI assistants.', 'aeomatic' ),
		);

		// 3. FAQ block present (10 pts).
		$has_faq = ! empty( $extracted['faqs'] ) || $this->content_has_faq_block( $content_html );
		$checks[] = array(
			'key'    => 'faq_block',
			'label'  => __( 'FAQ on the page', 'aeomatic' ),
			'points' => $has_faq ? 10 : 0,
			'max'    => 10,
			'passed' => $has_faq,
			'hint'   => __( 'Add a few questions with short, direct answers — the AEO FAQ block, the [aeo_faq] shortcode, or an accordion or toggle from your builder all count. AEOmatic also checks the live page, so FAQs from your theme or other plugins are found too.', 'aeomatic' ),
		);

		// 4. Schema enabled (proxy) — checks settings (15 pts).
		$schema_enabled = (bool) AEOMATIC_Settings::get( 'schema', 'article' );
		$checks[] = array(
			'key'    => 'article_schema',
			'label'  => __( 'Article schema enabled', 'aeomatic' ),
			'points' => $schema_enabled ? 15 : 0,
			'max'    => 15,
			'passed' => $schema_enabled,
			'hint'   => __( 'Enable Article schema in AEOmatic settings.', 'aeomatic' ),
		);

		// 5. Author bio + display name complete (10 pts).
		$user      = get_userdata( (int) $post->post_author );
		$bio       = $user ? trim( (string) get_user_meta( $user->ID, 'description', true ) ) : '';
		$has_author = $user && $user->display_name && strlen( $bio ) > 30;
		$checks[] = array(
			'key'    => 'author_authority',
			'label'  => __( 'Author bio present', 'aeomatic' ),
			'points' => $has_author ? 10 : 0,
			'max'    => 10,
			'passed' => $has_author,
			'hint'   => __( 'Add a 30+ character author bio. A named author with real credentials makes the content easier to trust and attribute.', 'aeomatic' ),
		);

		// 6. Internal links (10 pts). Content, page-builder and ACF fields
		//    (including relationship fields) all arrive in $content_html.
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		preg_match_all( '/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $content_html, $am );
		$internal     = self::count_internal_links( $am[1] ?? array(), $post );
		$internal_pts = $internal >= 3 ? 10 : ( $internal >= 1 ? 5 : 0 );
		$checks[] = array(
			'key'    => 'internal_links',
			'label'  => __( 'Internal links to related content', 'aeomatic' ),
			'points' => $internal_pts,
			'max'    => 10,
			'passed' => $internal_pts === 10,
			'hint'   => sprintf(
				/* translators: %d: number of distinct internal pages linked */
				__( 'Link to at least 3 other pages on your site from this post (found %d). Links in the content and in ACF fields, including relationship fields, count.', 'aeomatic' ),
				$internal
			),
		);

		// 7. Tables / lists (10 pts).
		$has_table = (bool) preg_match( '/<table[^>]*>/i', $content_html );
		$has_list  = (bool) preg_match( '/<(ul|ol)[^>]*>/i', $content_html );
		$structured_pts = ( $has_table && $has_list ) ? 10 : ( ( $has_table || $has_list ) ? 5 : 0 );
		$checks[] = array(
			'key'    => 'structured_content',
			'label'  => __( 'Structured content (tables/lists)', 'aeomatic' ),
			'points' => $structured_pts,
			'max'    => 10,
			'passed' => $structured_pts === 10,
			'hint'   => __( 'Add at least one table and one list. Structured chunks are easier to extract than long paragraphs.', 'aeomatic' ),
		);

		// 8. Image alt text (5 pts).
		preg_match_all( '/<img\b[^>]*>/i', $content_html, $im );
		$total_imgs = count( $im[0] ?? array() );
		$alt_imgs   = 0;
		foreach ( ( $im[0] ?? array() ) as $tag ) {
			if ( preg_match( '/alt=["\'][^"\']+["\']/i', $tag ) ) {
				$alt_imgs++;
			}
		}
		$alt_passed = ( 0 === $total_imgs ) || ( $alt_imgs === $total_imgs );
		$checks[] = array(
			'key'    => 'image_alt',
			'label'  => __( 'Image alt text complete', 'aeomatic' ),
			'points' => $alt_passed ? 5 : 0,
			'max'    => 5,
			'passed' => $alt_passed,
			'hint'   => __( 'Add descriptive alt text to every image.', 'aeomatic' ),
		);

		// 9. Page load performance (10 pts) — best-effort, content-size proxy.
		$content_size_kb = strlen( $content_html ) / 1024;
		$perf_passed = $content_size_kb < 200;
		$checks[] = array(
			'key'    => 'performance',
			'label'  => __( 'Lean page weight', 'aeomatic' ),
			'points' => $perf_passed ? 10 : 0,
			'max'    => 10,
			'passed' => $perf_passed,
			'hint'   => __( 'Keep post HTML under 200KB. Heavy pages are slower for any crawler to fetch and parse. Note: this check measures HTML size only, not real page load.', 'aeomatic' ),
		);

		// 10. Word count (5 pts).
		$wc_passed = $word_count >= $min_words;
		$checks[] = array(
			'key'    => 'word_count',
			/* translators: %d: minimum word count threshold. */
			'label'  => sprintf( __( 'Word count ≥ %d', 'aeomatic' ), $min_words ),
			'points' => $wc_passed ? 5 : 0,
			'max'    => 5,
			'passed' => $wc_passed,
			/* translators: %d: minimum word count threshold. */
			'hint'   => sprintf( __( 'Aim for at least %d words. Short pages give an AI answer little to extract.', 'aeomatic' ), $min_words ),
		);

		// 11. Statistics & data citations (10 pts). Rationale: a quantified claim
		// with a source link is a self-contained, verifiable fragment, which is the
		// shape AI answers quote. Deliberately not stated as a percentage uplift —
		// no public dataset supports a specific number.
		$stat_matches = 0;
		// Numbers with % or quantifiers like "X% / X percent / $X / Xk users / X million".
		if ( preg_match_all( '/\b\d{1,3}(?:[\.,]\d+)?\s*(?:%|percent|million|billion|thousand|k\b|users|customers|companies|sites|studies)\b/i', $content_text, $sm ) ) {
			$stat_matches = count( $sm[0] );
		}
		// External citation links — outbound <a href> to non-self domains.
		$citation_count = 0;
		foreach ( ( $am[1] ?? array() ) as $href ) {
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( $host && $host !== $home && ! str_starts_with( $host, 'www.' . $home ) ) {
				$citation_count++;
			}
		}
		$stats_pts = 0;
		if ( $stat_matches >= 2 && $citation_count >= 1 ) {
			$stats_pts = 10;
		} elseif ( $stat_matches >= 2 || $citation_count >= 1 ) {
			$stats_pts = 5;
		}
		$checks[] = array(
			'key'    => 'stats_citations',
			'label'  => __( 'Statistics & external citations', 'aeomatic' ),
			'points' => $stats_pts,
			'max'    => 10,
			'passed' => 10 === $stats_pts,
			'hint'   => __( 'Cite at least 2 numerical statistics and link to at least 1 external authoritative source. A quantified, sourced claim is a self-contained fragment an AI answer can quote and attribute.', 'aeomatic' ),
		);

		// 12. First-hand experience markers (5 pts) — Google E-E-A-T signal.
		$firsthand_patterns = '/\b(i (?:tested|tried|used|measured|reviewed|found|noticed|built|wrote)|we (?:tested|tried|used|measured|reviewed|found|noticed|built|ran)|in (?:my|our) (?:experience|tests?|review|setup)|after (?:weeks|months|years) of)\b/i';
		$has_firsthand = (bool) preg_match( $firsthand_patterns, $content_text );
		$checks[] = array(
			'key'    => 'firsthand_experience',
			'label'  => __( 'First-hand experience markers', 'aeomatic' ),
			'points' => $has_firsthand ? 5 : 0,
			'max'    => 5,
			'passed' => $has_firsthand,
			'hint'   => __( 'Mention what you tested, built, used, or measured yourself ("I tested…", "In our review…"). Google describes first-hand experience as part of E-E-A-T.', 'aeomatic' ),
		);

		// 13. Content freshness (5 pts) — AI engines favour recently updated pages.
		$modified_ts   = (int) get_post_modified_time( 'U', true, $post );
		$age_in_days   = $modified_ts ? ( time() - $modified_ts ) / DAY_IN_SECONDS : PHP_INT_MAX;
		if ( $age_in_days <= 180 ) {
			$fresh_pts = 5;
		} elseif ( $age_in_days <= 365 ) {
			$fresh_pts = 3;
		} else {
			$fresh_pts = 0;
		}
		$checks[] = array(
			'key'    => 'freshness',
			'label'  => __( 'Content updated recently', 'aeomatic' ),
			'points' => $fresh_pts,
			'max'    => 5,
			'passed' => 5 === $fresh_pts,
			'hint'   => __( 'Review the post at least every 6 months and update anything out of date. Fresh, accurate facts matter to readers and engines alike.', 'aeomatic' ),
		);

		// Normalise to 0–100 so existing dashboard thresholds (≥70 good, ≥40 okay) keep working
		// as criteria are added or removed over time.
		$earned    = 0;
		$max_total = 0;
		foreach ( $checks as $c ) {
			$earned    += (int) $c['points'];
			$max_total += (int) ( $c['max'] ?? 0 );
		}
		$score = $max_total > 0 ? (int) round( ( $earned / $max_total ) * 100 ) : 0;

		update_post_meta( $post->ID, self::META_SCORE, $score );
		// wp_slash: update_post_meta() unslashes, which would corrupt the JSON's escapes.
		update_post_meta( $post->ID, self::META_BREAKDOWN, wp_slash( wp_json_encode( $checks ) ) );

		AEOMATIC_Activity::bump( 'audits_run' );

		return array(
			'post_id'    => $post->ID,
			'post_type'  => $post->post_type,
			'score'      => $score,
			'breakdown'  => $checks,
			'word_count' => $word_count,
			'builder'    => $builder,
			'builder_label' => AEOMATIC_Content_Extractor::builder_label( $builder ),
		);
	}

	private function content_has_faq_block( string $content ): bool {
		if ( false !== strpos( $content, '<!-- wp:aeomatic/faq' ) ) {
			return true;
		}
		// Also accept core list-style FAQ patterns with <details>.
		if ( false !== strpos( $content, '<details' ) ) {
			return true;
		}
		return false;
	}
}
