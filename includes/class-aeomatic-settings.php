<?php
/**
 * Settings manager.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps the single options array used by the plugin.
 */
class AEOMATIC_Settings {

	/**
	 * Request-level cache. Populated on first call to all(), reused for the
	 * lifetime of the request. Cleared on save() so settings changes are
	 * visible immediately in the same request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'schema'           => array(
				'article'      => true,
				'author'       => true,
				'organization' => true,
				'breadcrumb'   => true,
				'org_name'     => '',
				'org_logo'     => '',
				'org_sameas'   => '', // newline-separated URLs
				// Organization details. The identifiers are what entity
				// resolution trusts most; all are optional.
				'org_legal_name'     => '',
				'org_alternate_name' => '',
				'org_description'    => '',
				'org_email'          => '',
				'org_telephone'      => '',
				'org_founding_date'  => '',
				'org_street'         => '',
				'org_city'           => '',
				'org_region'         => '',
				'org_postal'         => '',
				'org_country'        => '',
				'org_duns'           => '',
				'org_naics'          => '',
				'org_lei'            => '',
				'org_vat'            => '',
				'org_tax_id'         => '',
			),
			'llms_txt'         => array(
				'enabled'      => true,
				'description'  => '',
				'auto_include' => true, // include all published posts and pages automatically
				'extra_urls'   => '',   // newline-separated extra URLs
			),
			'crawlers'         => array(
				// true = allowed, false = blocked.
				'GPTBot'           => true,
				'ChatGPT-User'     => true,
				'OAI-SearchBot'    => true,
				'ClaudeBot'        => true,
				'Claude-User'      => true,
				'Claude-SearchBot' => true,
				'PerplexityBot'    => true,
				'Perplexity-User'  => true,
				'Google-Extended'  => true,
				'CCBot'                => false,
				'Bytespider'           => false,
				'Amazonbot'            => true,
				'Applebot-Extended'    => true,
				'meta-externalagent'   => true,
				'Meta-ExternalFetcher' => true,
				'DeepSeekBot'          => true,
				'Cohere-AI'            => false,
				'DuckAssistBot'        => true,
			),
			'robots'           => array(
				'block_pdfs' => false,
			),
			'ai_headers'       => array(
				// Emit the X-AI-* discovery headers and the Link: rel="ai-context"
				// header on public requests. Read by AEOMATIC_AI_Headers and scored
				// by AEOMATIC_Analyzer_Technical.
				'enabled'         => true,
				// X-Content-License + X-AI-Content-Type: opt-in, see AEOMATIC_AI_Headers.
				'declare_content' => false,
			),
			'log'              => array(
				// Days to retain AI crawler visit rows. Purged daily via WP-Cron.
				'retention_days' => 90,
			),
			'audit'            => array(
				'enabled'   => true,
				'min_words' => 800,
			),
			'post_types'       => array(
				// Map of post_type slug => bool. Empty by default — all public types
				// auto-enable. User explicitly opts out per-CPT in settings.
			),
			'_meta'            => array(
				'installed_version' => AEOMATIC_VERSION,
				'installed_at'      => time(),
			),
		);
	}

	/**
	 * Get full settings array, merged with defaults.
	 * Result is cached for the duration of the HTTP request.
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$saved       = get_option( AEOMATIC_OPTION_KEY, array() );
		self::$cache = self::recursive_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		return self::$cache;
	}

	/**
	 * Get a specific section (e.g. 'schema', 'llms_txt').
	 */
	public static function get( string $section, ?string $key = null ) {
		$all = self::all();
		if ( ! isset( $all[ $section ] ) ) {
			return null;
		}
		if ( null === $key ) {
			return $all[ $section ];
		}
		return $all[ $section ][ $key ] ?? null;
	}

	/**
	 * Save a sanitised settings payload and bust the request-level cache.
	 */
	public static function save( array $input ): bool {
		$current = self::all();
		$clean   = self::sanitise( $input, $current );
		return self::put( $clean );
	}

	/**
	 * Persist a complete settings array and keep every cache coherent.
	 *
	 * Every writer must go through this rather than calling update_option()
	 * directly. A direct write leaves two caches stale for the rest of the
	 * request — Settings::$cache and AEOMATIC_Post_Types::$supported_cache —
	 * so a later reader in the same request sees pre-write values. That is
	 * how the settings save could write back a stale _meta.installed_version
	 * after maybe_upgrade() had just bumped it, leaving the upgrade routine
	 * re-firing (and re-flushing rewrite rules) on every admin page load.
	 *
	 * @param array $all Complete settings array.
	 */
	public static function put( array $all ): bool {
		$ok = update_option( AEOMATIC_OPTION_KEY, $all );

		// Defeat every downstream cache that could return stale data on the
		// remainder of this request:
		//   1. Settings::$cache is repopulated with the freshly-written array
		//      (nulling it works too, but this guarantees no reader ever sees
		//      an intermediate state).
		//   2. WP's own options cache is invalidated in case a persistent
		//      object cache (Redis, Memcached) held the previous value.
		//   3. Post_Types static map is flushed so get_enabled_map() re-reads
		//      the fresh post_types section on next call.
		self::$cache = $all;
		wp_cache_delete( AEOMATIC_OPTION_KEY, 'options' );
		if ( class_exists( 'AEOMATIC_Post_Types' ) ) {
			AEOMATIC_Post_Types::flush_cache();
		}
		return $ok;
	}

	/**
	 * Set defaults if no option exists yet.
	 */
	public static function set_defaults(): void {
		if ( false === get_option( AEOMATIC_OPTION_KEY ) ) {
			add_option( AEOMATIC_OPTION_KEY, self::defaults() );
		}
	}

	/**
	 * Sanitise input. Booleans, text, URLs, and structured values handled per-key.
	 */
	private static function sanitise( array $input, array $current ): array {
		$out = $current;

		if ( isset( $input['schema'] ) && is_array( $input['schema'] ) ) {
			$s = $input['schema'];
			// Checkboxes are absent from POST when unchecked, so don't guard with
			// isset() — always write the value so unchecked boxes save as false.
			foreach ( array( 'article', 'author', 'organization', 'breadcrumb' ) as $bool ) {
				$out['schema'][ $bool ] = ! empty( $s[ $bool ] );
			}
			foreach ( array(
				'org_legal_name', 'org_alternate_name', 'org_telephone', 'org_founding_date',
				'org_street', 'org_city', 'org_region', 'org_postal', 'org_country',
				'org_duns', 'org_naics', 'org_lei', 'org_vat', 'org_tax_id',
			) as $text ) {
				if ( isset( $s[ $text ] ) ) {
					$out['schema'][ $text ] = sanitize_text_field( $s[ $text ] );
				}
			}
			if ( isset( $s['org_description'] ) ) {
				$out['schema']['org_description'] = sanitize_textarea_field( $s['org_description'] );
			}
			if ( isset( $s['org_email'] ) ) {
				$out['schema']['org_email'] = sanitize_email( $s['org_email'] );
			}
			if ( isset( $s['org_name'] ) ) {
				$out['schema']['org_name'] = sanitize_text_field( $s['org_name'] );
			}
			if ( isset( $s['org_logo'] ) ) {
				$out['schema']['org_logo'] = esc_url_raw( $s['org_logo'] );
			}
			if ( isset( $s['org_sameas'] ) ) {
				$urls = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $s['org_sameas'] ) ) );
				$urls = array_map( 'esc_url_raw', $urls );
				$out['schema']['org_sameas'] = implode( "\n", $urls );
			}
		}

		if ( isset( $input['llms_txt'] ) && is_array( $input['llms_txt'] ) ) {
			$l = $input['llms_txt'];
			$out['llms_txt']['enabled']      = ! empty( $l['enabled'] );
			$out['llms_txt']['auto_include'] = ! empty( $l['auto_include'] );
			if ( isset( $l['description'] ) ) {
				$out['llms_txt']['description'] = sanitize_textarea_field( $l['description'] );
			}
			if ( isset( $l['extra_urls'] ) ) {
				$urls = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $l['extra_urls'] ) ) );
				$urls = array_map( 'esc_url_raw', $urls );
				$out['llms_txt']['extra_urls'] = implode( "\n", $urls );
			}
		}

		if ( isset( $input['crawlers'] ) && is_array( $input['crawlers'] ) ) {
			foreach ( $out['crawlers'] as $bot => $_ ) {
				$out['crawlers'][ $bot ] = ! empty( $input['crawlers'][ $bot ] );
			}
		}

		if ( isset( $input['robots'] ) && is_array( $input['robots'] ) ) {
			$out['robots']['block_pdfs'] = ! empty( $input['robots']['block_pdfs'] );
		}

		if ( isset( $input['ai_headers'] ) && is_array( $input['ai_headers'] ) ) {
			$out['ai_headers']['enabled']         = ! empty( $input['ai_headers']['enabled'] );
			$out['ai_headers']['declare_content'] = ! empty( $input['ai_headers']['declare_content'] );
		}

		if ( isset( $input['log'] ) && is_array( $input['log'] ) ) {
			$days = isset( $input['log']['retention_days'] ) ? absint( $input['log']['retention_days'] ) : 90;
			// Clamp to a sane window: at least a week, at most a year.
			$out['log']['retention_days'] = max( 7, min( 365, $days ) );
		}

		if ( isset( $input['audit'] ) && is_array( $input['audit'] ) ) {
			$a = $input['audit'];
			$out['audit']['enabled']   = ! empty( $a['enabled'] );
			if ( isset( $a['min_words'] ) ) {
				$mw = absint( $a['min_words'] );
				$out['audit']['min_words'] = max( 100, min( 5000, $mw ) );
			}
		}

		if ( array_key_exists( 'post_types', $input ) && is_array( $input['post_types'] ) ) {
			// The settings view pairs every checkbox with a hidden "0" companion,
			// so $_POST always enumerates every rendered slug with the user's
			// explicit intent. We trust POST here and merge over the current
			// saved map — that way slugs not on the page keep their prior state
			// instead of being dropped.
			//
			// Slugs are resolved against the post types actually registered
			// rather than passed through sanitize_key(). sanitize_key()
			// lowercases and strips anything outside [a-z0-9_-], so a CPT
			// registered as "MyCPT" was saved under "mycpt" while the render
			// path looked it up by "MyCPT" — the lookup missed, fell through to
			// the default-ON branch, and the checkbox could never be turned off.
			$registered = array();
			if ( class_exists( 'AEOMATIC_Post_Types' ) ) {
				$registered = array_keys( AEOMATIC_Post_Types::get_public_post_types() );
			}
			$by_sanitised = array();
			foreach ( $registered as $known_slug ) {
				$by_sanitised[ sanitize_key( $known_slug ) ] = $known_slug;
			}

			foreach ( $input['post_types'] as $raw_slug => $val ) {
				$raw_slug = (string) $raw_slug;

				if ( in_array( $raw_slug, $registered, true ) ) {
					// Exact match on a live post type — store under its real slug.
					$slug = $raw_slug;
				} elseif ( isset( $by_sanitised[ sanitize_key( $raw_slug ) ] ) ) {
					// Submitted key differs only by sanitisation; map it back.
					$slug = $by_sanitised[ sanitize_key( $raw_slug ) ];
				} else {
					// Not currently registered (plugin deactivated, say). Keep a
					// sanitised key so the preference survives reactivation.
					$slug = sanitize_key( $raw_slug );
				}

				if ( '' === $slug || 'attachment' === $slug ) {
					continue;
				}
				$out['post_types'][ $slug ] = ! empty( $val );
			}
		}

		return $out;
	}

	/**
	 * Merge defaults with saved settings, preserving nested structure.
	 */
	private static function recursive_merge( array $defaults, array $saved ): array {
		$out = $defaults;
		foreach ( $saved as $k => $v ) {
			if ( isset( $defaults[ $k ] ) && is_array( $defaults[ $k ] ) && is_array( $v ) ) {
				$out[ $k ] = self::recursive_merge( $defaults[ $k ], $v );
			} else {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}
}
