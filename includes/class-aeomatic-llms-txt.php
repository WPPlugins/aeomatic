<?php
/**
 * llms.txt module.
 *
 * Serves /llms.txt as a virtual endpoint with a Markdown-formatted summary
 * of the site, including key pages and the most recent or popular posts.
 * Spec reference: https://llmstxt.org/
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_LLMs_Txt {

	const QUERY_VAR      = 'aeomatic_llms';
	const QUERY_VAR_FULL = 'aeomatic_llms_full';

	/** Transient keys for the rendered bodies. */
	const CACHE_KEY      = 'aeomatic_llms_txt_cache';
	const CACHE_KEY_FULL = 'aeomatic_llms_full_txt_cache';

	/** How long a rendered body is reused before it is rebuilt. */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Default per-post-type ceiling for /llms-full.txt.
	 *
	 * llms-full.txt inlines the extracted body of every item, so an unbounded
	 * build is O(published posts) of page-builder extraction inside a single
	 * anonymous request. 200 per type keeps the endpoint useful on real sites
	 * while bounding worst-case memory and time. Raise it (or pass -1 for no
	 * cap) via the aeomatic_llms_full_max_items filter.
	 */
	const FULL_MAX_ITEMS_DEFAULT = 200;

	public function register(): void {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		// Priority 0 so the endpoint is served before redirect_canonical (priority 10)
		// can append a trailing slash and 301 /llms.txt to /llms.txt/.
		add_action( 'template_redirect', array( $this, 'maybe_serve' ), 0 );
		add_filter( 'redirect_canonical', array( $this, 'skip_canonical_redirect' ) );

		// Both rendered bodies list every published item, so a publish, update,
		// delete, or menu change can invalidate them. Drop the cache on those
		// events rather than serving a stale index for up to CACHE_TTL.
		add_action( 'save_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'wp_update_nav_menu', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Drop both rendered bodies.
	 */
	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::CACHE_KEY_FULL );
	}

	/**
	 * Return a cached rendered body, building it via $builder on a miss.
	 *
	 * Building either body walks every published item, so an uncached endpoint
	 * turns each anonymous GET into a full-site scan. The transient bounds that
	 * to one build per CACHE_TTL, or per content change, whichever comes first.
	 *
	 * @param string   $key     Transient key.
	 * @param callable $builder Returns the body as a string.
	 */
	private static function cached_body( string $key, callable $builder ): string {
		$cached = get_transient( $key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$body = (string) $builder();
		set_transient( $key, $body, self::CACHE_TTL );
		return $body;
	}

	/**
	 * Register the /llms.txt and /llms-full.txt rewrite rules.
	 * Static so the activation hook can call it.
	 */
	public static function register_rewrites(): void {
		// Match /llms-full.txt before /llms.txt so the longer pattern wins.
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?' . self::QUERY_VAR_FULL . '=1', 'top' );
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::QUERY_VAR_FULL;
		return $vars;
	}

	/**
	 * Never canonical-redirect the llms endpoints (e.g. /llms.txt → /llms.txt/).
	 *
	 * @param string|false $redirect_url Proposed redirect.
	 * @return string|false
	 */
	public function skip_canonical_redirect( $redirect_url ) {
		if ( 1 === (int) get_query_var( self::QUERY_VAR ) || 1 === (int) get_query_var( self::QUERY_VAR_FULL ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Convert a rendered body to clean plain text: no tags, no HTML entities.
	 *
	 * The endpoints are served as text/plain with nosniff, so HTML escaping is
	 * not needed for safety and would corrupt the Markdown (a "> summary" line
	 * became "&gt; summary", apostrophes became "&#8217;").
	 */
	public static function to_plain_text( string $body ): string {
		$body = wp_strip_all_tags( $body, false );
		return html_entity_decode( $body, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	public function maybe_serve(): void {
		$is_full = (int) get_query_var( self::QUERY_VAR_FULL ) === 1;
		$is_txt  = (int) get_query_var( self::QUERY_VAR ) === 1;

		if ( ! $is_full && ! $is_txt ) {
			return;
		}

		$settings = AEOMATIC_Settings::get( 'llms_txt' );
		if ( empty( $settings['enabled'] ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: public, max-age=' . (int) self::CACHE_TTL );
		// The endpoint is intentionally world-readable. Online validators and
		// AI clients fetch it via browser fetch(), which requires CORS.
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, HEAD, OPTIONS' );

		if ( $is_full ) {
			AEOMATIC_Activity::bump( 'llms_full_serves' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain + nosniff; tags stripped in to_plain_text().
			echo self::to_plain_text( self::cached_body( self::CACHE_KEY_FULL, fn(): string => $this->build_full_content( $settings ) ) );
			exit;
		}

		AEOMATIC_Activity::bump( 'llms_serves' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain + nosniff; tags stripped in to_plain_text().
		echo self::to_plain_text( self::cached_body( self::CACHE_KEY, fn(): string => $this->build_content( $settings ) ) );
		exit;
	}

	/**
	 * Build the llms.txt body.
	 */
	private function build_content( array $settings ): string {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$tagline   = get_bloginfo( 'description' );

		$out  = '# ' . $site_name . "\n\n";
		$summary = trim( (string) ( ! empty( $settings['description'] ) ? $settings['description'] : $tagline ), " \t\n\r\0\x0B" );
		if ( '' !== $summary ) {
			$out .= '> ' . $summary . "\n\n";
		}
		$out .= 'Site URL: ' . $site_url . "\n";
		$out .= 'Full content: ' . home_url( '/llms-full.txt' ) . "\n\n";

		// Key pages (those marked in primary nav menu).
		$key_pages = $this->get_key_pages();
		if ( $key_pages ) {
			$out .= "## Key pages\n\n";
			foreach ( $key_pages as $page ) {
				$out .= sprintf( "- [%s](%s)", $page['title'], $page['url'] );
				if ( ! empty( $page['desc'] ) ) {
					$out .= ': ' . $page['desc'];
				}
				$out .= "\n";
			}
			$out .= "\n";
		}

		// Recent / featured content from every enabled post type.
		if ( ! empty( $settings['auto_include'] ) ) {
			$grouped = $this->get_recent_content_by_type();
			foreach ( $grouped as $section ) {
				if ( empty( $section['posts'] ) ) {
					continue;
				}
				$out .= '## ' . $section['heading'] . "\n\n";
				foreach ( $section['posts'] as $p ) {
					$out .= sprintf( "- [%s](%s)", $p['title'], $p['url'] );
					if ( ! empty( $p['desc'] ) ) {
						$out .= ': ' . $p['desc'];
					}
					$out .= "\n";
				}
				$out .= "\n";
			}
		}

		// Extra URLs from settings.
		if ( ! empty( $settings['extra_urls'] ) ) {
			$urls = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $settings['extra_urls'] ) ) );
			if ( $urls ) {
				$out .= "## Additional resources\n\n";
				foreach ( $urls as $url ) {
					$out .= '- ' . esc_url( $url ) . "\n";
				}
				$out .= "\n";
			}
		}

		$out .= "## Ignore\n\n";
		$out .= "- /wp-admin/\n";
		$out .= "- /wp-login.php\n";
		$out .= "- /wp-json/\n";
		$out .= "- /cart/\n";
		$out .= "- /checkout/\n";
		$out .= "- /my-account/\n";
		$out .= "- /feed/\n";
		$out .= "- /xmlrpc.php\n\n";
		$out .= '---' . "\n";
		$out .= 'Generated by AEOmatic v' . AEOMATIC_VERSION . ' on ' . gmdate( 'Y-m-d' ) . "\n";

		return $out;
	}

	/**
	 * Build the llms-full.txt body — the full extracted text of every
	 * published page and post (across enabled post types), one Markdown
	 * `##` block per item. This is the AI-readable full-content companion
	 * to the llms.txt index.
	 */
	private function build_full_content( array $settings ): string {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$tagline   = get_bloginfo( 'description' );

		$out  = '# ' . $site_name . ' — ' . __( 'Full content', 'aeomatic' ) . "\n\n";
		$out .= '> ' . ( ! empty( $settings['description'] ) ? $settings['description'] : $tagline ) . "\n\n";
		$out .= 'Site URL: ' . $site_url . "\n";
		$out .= 'Index: ' . home_url( '/llms.txt' ) . "\n\n";

		$enabled = AEOMATIC_Post_Types::get_enabled_slugs();
		$objects = AEOMATIC_Post_Types::get_public_post_types();

		// Bounded separately from the llms.txt index: this body inlines every
		// item's extracted text, so it costs far more per item.
		$max_items = (int) apply_filters( 'aeomatic_llms_full_max_items', self::FULL_MAX_ITEMS_DEFAULT );
		$truncated = array();

		foreach ( $enabled as $slug ) {
			if ( ! isset( $objects[ $slug ] ) ) {
				continue;
			}

			$query = new WP_Query( array(
				'post_type'      => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => $max_items,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			) );

			if ( $max_items > 0 && count( $query->posts ) >= $max_items ) {
				$truncated[] = $objects[ $slug ]->labels->name ?? $slug;
			}

			foreach ( $query->posts as $post ) {
				$out .= '## ' . wp_strip_all_tags( get_the_title( $post ) ) . "\n\n";
				$out .= 'URL: ' . get_permalink( $post ) . "\n\n";

				$extracted = AEOMATIC_Content_Extractor::extract( $post );
				$text      = trim( (string) ( $extracted['text'] ?? '' ) );
				if ( '' === $text ) {
					$text = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
				}

				$out .= ( '' !== $text ? $text : __( '(No extractable text content.)', 'aeomatic' ) ) . "\n\n";
			}
		}

		$out .= '---' . "\n";
		if ( ! empty( $truncated ) ) {
			$out .= sprintf(
				/* translators: 1: per-type item limit, 2: comma-separated post type labels */
				__( 'Note: capped at %1$d items per type. Truncated: %2$s.', 'aeomatic' ),
				$max_items,
				implode( ', ', $truncated )
			) . PHP_EOL;
		}
		$out .= 'Generated by AEOmatic v' . AEOMATIC_VERSION . ' on ' . gmdate( 'Y-m-d' ) . "\n";

		return $out;
	}

	/**
	 * Is this post listed in the llms.txt index?
	 *
	 * Read by AEOMATIC_Analyzer_Technical so the readiness score reflects the
	 * inclusion rules the endpoint actually applies: the feature must be on,
	 * auto-include must be on, the post type enabled, and the post itself
	 * published and not password-protected.
	 */
	public static function is_post_included( WP_Post $post ): bool {
		$settings = (array) AEOMATIC_Settings::get( 'llms_txt' );
		if ( empty( $settings['enabled'] ) || empty( $settings['auto_include'] ) ) {
			return false;
		}
		if ( 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return false;
		}
		return AEOMATIC_Post_Types::is_enabled( $post->post_type );
	}

	/**
	 * Get pages from the primary nav menu (best heuristic for "important pages").
	 */
	private function get_key_pages(): array {
		$pages    = array();
		$locations = get_nav_menu_locations();
		$menu_id  = $locations['primary'] ?? ( reset( $locations ) ?: 0 );

		if ( $menu_id ) {
			$items = wp_get_nav_menu_items( $menu_id );
			if ( $items ) {
				foreach ( $items as $item ) {
					if ( '0' !== (string) $item->menu_item_parent ) {
						continue; // top-level only
					}
					$pages[] = array(
						'title' => $item->title,
						'url'   => $item->url,
						'desc'  => $item->description,
					);
					if ( count( $pages ) >= 10 ) {
						break;
					}
				}
			}
		}

		// Fallback: top-level published pages by menu_order.
		if ( empty( $pages ) ) {
			$wp_pages = get_pages( array(
				'parent'      => 0,
				'sort_column' => 'menu_order,post_title',
				'number'      => 10,
			) );
			foreach ( (array) $wp_pages as $p ) {
				$pages[] = array(
					'title' => get_the_title( $p ),
					'url'   => get_permalink( $p ),
					'desc'  => wp_strip_all_tags( get_the_excerpt( $p ) ),
				);
			}
		}

		return $pages;
	}

	/**
	 * Get published items grouped by post type. Skips post types the user
	 * has disabled in settings. Pages are included as their own "Pages"
	 * section (in addition to the curated nav-menu key-pages list above).
	 *
	 * Lists every published item — there is no fixed cap. Sites with very
	 * large archives can bound the list per type via the
	 * `aeomatic_llms_max_items` filter (default -1 = unlimited).
	 *
	 * @return array<int,array{heading:string,posts:array}>
	 */
	private function get_recent_content_by_type(): array {
		$enabled = AEOMATIC_Post_Types::get_enabled_slugs();
		$objects = AEOMATIC_Post_Types::get_public_post_types();
		$out     = array();

		/** Per-type item limit. -1 = all published items (no cap). */
		$max_items = (int) apply_filters( 'aeomatic_llms_max_items', -1 );

		foreach ( $enabled as $slug ) {
			$obj = $objects[ $slug ] ?? null;
			if ( ! $obj ) {
				continue;
			}

			$query = new WP_Query( array(
				'post_type'      => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => $max_items,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			) );

			if ( empty( $query->posts ) ) {
				continue;
			}

			$posts = array();
			foreach ( $query->posts as $post ) {
				$posts[] = array(
					'title' => get_the_title( $post ),
					'url'   => get_permalink( $post ),
					'desc'  => wp_strip_all_tags( get_the_excerpt( $post ) ),
				);
			}

			$out[] = array(
				'heading' => $obj->labels->name ?? ucfirst( $slug ),
				'posts'   => $posts,
			);
		}

		return $out;
	}

	/**
	 * Legacy helper kept for backward compatibility.
	 */
	private function get_recent_posts(): array {
		$query = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 25,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		$out = array();
		foreach ( $query->posts as $post ) {
			$out[] = array(
				'title' => get_the_title( $post ),
				'url'   => get_permalink( $post ),
				'desc'  => wp_strip_all_tags( get_the_excerpt( $post ) ),
			);
		}
		return $out;
	}
}
