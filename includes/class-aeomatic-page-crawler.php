<?php
/**
 * Live page check.
 *
 * Fetches a published post the way a visitor or crawler sees it and reads
 * the rendered HTML. The content extractor only sees what is saved with the
 * post, so it misses FAQs that come from the theme, a template, another
 * plugin's shortcode or a builder widget it does not know. The live page
 * has all of them.
 *
 * What a scan records (post meta, keyed to the post's modified time):
 *   - FAQ pairs found in the page body (header, navigation, footer and
 *     sidebars are ignored). These feed the extractor, so the audit,
 *     citation gaps and entity scan stop reporting "no FAQ".
 *   - Every JSON-LD node on the page, marked AEOmatic or other, with types
 *     that more than one source prints flagged as duplicates.
 *   - Every cssSelector in that JSON-LD, checked against the page.
 *
 * Scans run in the background after a save, and on demand from the
 * editor's Schema tab.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Page_Crawler {

	const META    = '_aeomatic_live_scan';
	const CRON    = 'aeomatic_live_scan';
	const REST_NS = 'aeomatic/v1';

	/** Class on AEOmatic's own JSON-LD script tags, so a scan can tell them apart. */
	const SCRIPT_CLASS = 'aeomatic-schema';

	public function register(): void {
		add_action( 'save_post', array( $this, 'on_save_post' ), 40, 2 );
		add_action( self::CRON, array( __CLASS__, 'scan' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$permission = static function ( WP_REST_Request $request ) {
			return current_user_can( 'edit_post', (int) $request['id'] );
		};
		register_rest_route( self::REST_NS, '/live-scan/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get' ),
				'permission_callback' => $permission,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_scan' ),
				'permission_callback' => $permission,
			),
		) );
	}

	public function rest_get( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}
		$result = self::stored( $post );
		return rest_ensure_response( array_merge(
			$result ?? array( 'ok' => null ),
			array(
				'fresh'    => self::is_fresh( $post, $result ),
				'can_scan' => self::can_scan( $post ),
			)
		) );
	}

	public function rest_scan( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'not_found', __( 'Post not found', 'aeomatic' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array_merge(
			self::scan( $post ),
			array( 'fresh' => true, 'can_scan' => self::can_scan( $post ) )
		) );
	}

	/** Re-scan shortly after a published post is saved. */
	public function on_save_post( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! self::can_scan( $post ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON, array( $post_id ) ) ) {
			wp_schedule_single_event( time() + 15, self::CRON, array( $post_id ) );
		}
	}

	/** Only public, published posts have a page to fetch. */
	public static function can_scan( WP_Post $post ): bool {
		return 'publish' === $post->post_status
			&& is_post_type_viewable( $post->post_type )
			&& AEOMATIC_Post_Types::is_enabled( $post->post_type );
	}

	/** @return array<string,mixed>|null */
	public static function stored( WP_Post $post ): ?array {
		$raw = get_post_meta( $post->ID, self::META, true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $data ) ? $data : null;
	}

	/** A scan still describes the post if the post has not changed since. */
	public static function is_fresh( WP_Post $post, ?array $result ): bool {
		return is_array( $result ) && ( $result['modified'] ?? '' ) === $post->post_modified_gmt;
	}

	/**
	 * FAQ pairs from the latest scan, if it still matches the post. Called
	 * from the content extractor — a meta read only, safe during page render.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function cached_faqs( WP_Post $post ): array {
		$result = self::stored( $post );
		if ( ! self::is_fresh( $post, $result ) || empty( $result['ok'] ) ) {
			return array();
		}
		return array_values( array_filter( (array) ( $result['faqs'] ?? array() ), static fn( $p ) => is_array( $p ) && isset( $p['q'], $p['a'] ) ) );
	}

	/**
	 * Fetch and analyse the live page, store the result and return it.
	 *
	 * @param WP_Post|int $post
	 */
	public static function scan( $post ): array {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return array( 'ok' => false, 'error' => 'not_found', 'message' => __( 'Post not found.', 'aeomatic' ) );
		}
		$base = array(
			'url'        => get_permalink( $post ),
			'modified'   => $post->post_modified_gmt,
			'scanned_at' => time(),
		);
		if ( ! self::can_scan( $post ) ) {
			return array_merge( $base, array(
				'ok'      => false,
				'error'   => 'not_public',
				'message' => __( 'Publish the post to check the live page.', 'aeomatic' ),
			) );
		}

		// A throwaway query argument steps around most page caches, so the
		// scan sees the post as it is now.
		$response = wp_remote_get( add_query_arg( 'aeomatic_scan', time(), $base['url'] ), array(
			'timeout'     => 20,
			'redirection' => 3,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'user-agent'  => 'AEOmatic live page check; ' . home_url( '/' ),
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		) );

		if ( is_wp_error( $response ) ) {
			$result = array_merge( $base, array(
				'ok'      => false,
				'error'   => 'fetch_failed',
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Could not load the live page (%s). Your host may block the site from requesting its own pages. FAQ checks use the saved content only.', 'aeomatic' ),
					$response->get_error_message() ?: $response->get_error_code()
				),
			) );
		} else {
			$status = (int) wp_remote_retrieve_response_code( $response );
			$html   = (string) wp_remote_retrieve_body( $response );
			if ( $status < 200 || $status >= 300 || '' === $html ) {
				$result = array_merge( $base, array(
					'ok'      => false,
					'error'   => 'bad_status',
					'status'  => $status,
					'message' => sprintf(
						/* translators: %d: HTTP status code */
						__( 'The live page answered with HTTP %d, so it could not be checked.', 'aeomatic' ),
						$status
					),
				) );
			} else {
				$result = array_merge( $base, array( 'ok' => true, 'status' => $status ), self::analyze_html( $html ) );
			}
		}

		update_post_meta( $post->ID, self::META, wp_slash( wp_json_encode( $result ) ) );

		// Reports cached before the scan did not know about these FAQs.
		AEOMATIC_Content_Extractor::forget( $post->ID );
		foreach ( array( '_aeomatic_citation_gaps_fingerprint', '_aeomatic_entity_scan_fingerprint', '_aeomatic_ai_readiness_fingerprint' ) as $key ) {
			delete_post_meta( $post->ID, $key );
		}

		return $result;
	}

	/**
	 * Read FAQs, JSON-LD and cssSelector matches out of a rendered page.
	 * Public so it can be tested against saved HTML.
	 */
	public static function analyze_html( string $html ): array {
		$nodes = self::jsonld_nodes( $html );

		// Types printed by more than one source, or by two different
		// nodes, are duplicates. Nodes sharing one @id merge into one
		// entity, so they count once.
		$by_type = array();
		foreach ( $nodes as $node ) {
			$key = $node['id'] ?: $node['source'] . ':' . count( $by_type[ $node['type'] ] ?? array() );
			$by_type[ $node['type'] ][ $key ] = $node['source'];
		}
		$duplicates = array();
		foreach ( $by_type as $type => $entities ) {
			if ( count( $entities ) > 1 && ! in_array( $type, array( 'ListItem', 'Question', 'Answer' ), true ) ) {
				$duplicates[] = array( 'type' => $type, 'sources' => array_values( array_unique( $entities ) ) );
			}
		}

		$selectors = array();
		foreach ( self::find_selectors( $nodes ) as $selector ) {
			$selectors[] = array( 'selector' => $selector, 'found' => self::selector_matches( $selector, $html ) );
		}

		$faqs = AEOMATIC_Content_Extractor::dedup_faqs(
			AEOMATIC_Content_Extractor::extract_universal_faqs( self::body_region( $html ) )
		);

		return array(
			'faqs'       => array_slice( $faqs, 0, 50 ),
			'schema'     => array_map( static fn( $n ) => array( 'type' => $n['type'], 'id' => $n['id'], 'source' => $n['source'] ), $nodes ),
			'duplicates' => $duplicates,
			'selectors'  => $selectors,
		);
	}

	/**
	 * The page's own content: scripts, styles and the site header,
	 * navigation, footer and sidebars removed, so a theme's footer FAQ or
	 * menu toggles are not counted for every post.
	 */
	private static function body_region( string $html ): string {
		$html = preg_replace( '/<(script|style|noscript|template)\b[^>]*>.*?<\/\1>/is', ' ', $html ) ?? $html;
		foreach ( array( 'header', 'nav', 'footer', 'aside' ) as $tag ) {
			$html = preg_replace( '/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is', ' ', $html ) ?? $html;
		}
		$html = preg_replace( '/<div[^>]+id=["\']wpadminbar["\'].*?<\/div>\s*<\/div>/is', ' ', $html ) ?? $html;
		if ( preg_match( '/<body\b[^>]*>(.*)<\/body>/is', $html, $m ) ) {
			return $m[1];
		}
		return $html;
	}

	/**
	 * Flattened JSON-LD nodes with their @type, @id and source.
	 *
	 * @return array<int,array{type:string,id:string,source:string,data:array}>
	 */
	private static function jsonld_nodes( string $html ): array {
		$out = array();
		if ( ! preg_match_all( '/<script\b([^>]*)type=["\']application\/ld\+json["\']([^>]*)>(.*?)<\/script>/is', $html, $m, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $m as $script ) {
			$attrs  = $script[1] . ' ' . $script[2];
			$source = false !== strpos( $attrs, self::SCRIPT_CLASS ) ? 'aeomatic' : 'other';
			$data   = json_decode( html_entity_decode( trim( $script[3] ) ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$items = isset( $data['@graph'] ) && is_array( $data['@graph'] ) ? $data['@graph'] : ( isset( $data[0] ) ? $data : array( $data ) );
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || empty( $item['@type'] ) ) {
					continue;
				}
				foreach ( (array) $item['@type'] as $type ) {
					$out[] = array(
						'type'   => (string) $type,
						'id'     => (string) ( $item['@id'] ?? '' ),
						'source' => $source,
						'data'   => $item,
					);
				}
			}
		}
		return $out;
	}

	/** Every cssSelector value anywhere in the nodes. @return string[] */
	private static function find_selectors( array $nodes ): array {
		$found = array();
		$walk  = static function ( $value ) use ( &$walk, &$found ) {
			if ( ! is_array( $value ) ) {
				return;
			}
			foreach ( $value as $key => $child ) {
				if ( 'cssSelector' === $key ) {
					foreach ( (array) $child as $selector ) {
						if ( is_string( $selector ) && '' !== trim( $selector ) ) {
							$found[] = trim( $selector );
						}
					}
				} else {
					$walk( $child );
				}
			}
		};
		foreach ( $nodes as $node ) {
			$walk( $node['data'] );
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Whether a simple selector (tag, .class, #id, optionally compound like
	 * "div.note") names an element in the HTML. Complex selectors are
	 * checked on their last simple part.
	 */
	private static function selector_matches( string $selector, string $html ): bool {
		$parts = preg_split( '/[\s>+~]+/', trim( $selector ) );
		$last  = (string) end( $parts );
		$last  = preg_replace( '/:[\w-]+(\([^)]*\))?/', '', $last ) ?? $last; // drop pseudo-classes
		if ( ! preg_match( '/^([a-z][a-z0-9]*)?((?:[.#][\w-]+)*)$/i', $last, $m ) ) {
			return true; // Not something we can judge; do not report it.
		}
		$tag = $m[1] ?? '';
		preg_match_all( '/([.#])([\w-]+)/', $m[2] ?? '', $bits, PREG_SET_ORDER );
		$pattern = '/<' . ( '' !== $tag ? preg_quote( $tag, '/' ) . '\b' : '[a-z][a-z0-9]*' ) . '[^>]*>/i';
		if ( ! preg_match_all( $pattern, $html, $tags ) ) {
			return false;
		}
		foreach ( $tags[0] as $open ) {
			$ok = true;
			foreach ( $bits as $bit ) {
				$name = preg_quote( $bit[2], '/' );
				$re   = '.' === $bit[1]
					? '/\bclass=("|\')[^"\']*(?<![\w-])' . $name . '(?![\w-])/i'
					: '/\bid=("|\')' . $name . '\1/i';
				if ( ! preg_match( $re, $open ) ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				return true;
			}
		}
		return false;
	}
}
