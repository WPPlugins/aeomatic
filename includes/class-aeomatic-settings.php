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
				'faq'          => true,
				'howto'        => true,
				'author'       => true,
				'organization' => true,
				'breadcrumb'   => true,
				'org_name'     => '',
				'org_logo'     => '',
				'org_sameas'   => '', // newline-separated URLs
			),
			'llms_txt'         => array(
				'enabled'      => true,
				'description'  => '',
				'auto_include' => true, // include top posts/pages automatically
				'extra_urls'   => '',   // newline-separated extra URLs
			),
			'crawlers'         => array(
				// true = allowed, false = blocked.
				'GPTBot'           => true,
				'ChatGPT-User'     => true,
				'OAI-SearchBot'    => true,
				'ClaudeBot'        => true,
				'Claude-Web'       => true,
				'PerplexityBot'    => true,
				'Perplexity-User'  => true,
				'Google-Extended'  => true,
				'CCBot'            => false,
				'anthropic-ai'     => true,
				'Bytespider'       => false,
				'Amazonbot'        => true,
				'Applebot-Extended'=> true,
				'meta-externalagent' => true,
				'Claude-SearchBot'   => true,
				'DeepSeekBot'        => true,
				'Cohere-AI'          => false,
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
		$current     = self::all();
		$clean       = self::sanitise( $input, $current );
		self::$cache = null; // invalidate so the next all() re-reads from DB
		return update_option( AEOMATIC_OPTION_KEY, $clean );
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
			foreach ( array( 'article', 'faq', 'howto', 'author', 'organization', 'breadcrumb' ) as $bool ) {
				$out['schema'][ $bool ] = ! empty( $s[ $bool ] );
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

		if ( isset( $input['audit'] ) && is_array( $input['audit'] ) ) {
			$a = $input['audit'];
			$out['audit']['enabled']   = ! empty( $a['enabled'] );
			if ( isset( $a['min_words'] ) ) {
				$mw = absint( $a['min_words'] );
				$out['audit']['min_words'] = max( 100, min( 5000, $mw ) );
			}
		}

		if ( array_key_exists( 'post_types', $input ) ) {
			$pt_input = is_array( $input['post_types'] ) ? $input['post_types'] : array();
			$out['post_types'] = array();
			// Iterate every currently public post type so unchecked boxes are saved as false.
			foreach ( array_keys( get_post_types( array( 'public' => true ), 'names' ) ) as $slug ) {
				if ( 'attachment' === $slug ) {
					continue;
				}
				$out['post_types'][ $slug ] = ! empty( $pt_input[ $slug ] );
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
