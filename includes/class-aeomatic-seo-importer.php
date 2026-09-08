<?php
/**
 * SEO-plugin settings importer.
 *
 * Reads organisation and social-profile settings from the popular SEO
 * plugins (Yoast, Rank Math, SEOPress, AIOSEO, The SEO Framework) so the
 * setup wizard can offer a one-click import into AEOmatic's own settings.
 * Never writes back to the source plugin — this is a read-only inspector.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_SEO_Importer {

	/**
	 * Inspect every detected SEO plugin and return the values available for
	 * import. Keyed by short slug ('yoast', 'rankmath', 'seopress', 'aioseo',
	 * 'tsf'). Each entry looks like:
	 *   [
	 *     'label'      => 'Yoast SEO',
	 *     'org_name'   => 'Acme Widgets',      // '' if unavailable
	 *     'org_logo'   => 'https://…logo.png', // '' if unavailable
	 *     'org_sameas' => [ 'https://twitter.com/acme', … ],
	 *     'description' => '…',                // '' if unavailable
	 *   ]
	 *
	 * @return array<string, array{label:string, org_name:string, org_logo:string, org_sameas:array<int,string>, description:string}>
	 */
	public static function inspect(): array {
		$out = array();

		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Frontend' ) ) {
			$out['yoast'] = self::read_yoast();
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$out['rankmath'] = self::read_rankmath();
		}
		if ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_activation' ) ) {
			$out['seopress'] = self::read_seopress();
		}
		if ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' ) ) {
			$out['aioseo'] = self::read_aioseo();
		}
		if ( function_exists( 'the_seo_framework' ) ) {
			$out['tsf'] = self::read_tsf();
		}

		return array_filter( $out, static function ( $row ) {
			// Drop rows where nothing useful was found.
			return $row && ( $row['org_name'] || $row['org_logo'] || ! empty( $row['org_sameas'] ) );
		} );
	}

	/**
	 * Merge imported values into AEOmatic's settings. Never overwrites an
	 * existing non-empty AEOmatic value — treats the user's own AEOmatic
	 * config as authoritative.
	 *
	 * @param array $import  Row from inspect(), or a normalised array.
	 * @return array<string, bool>  Map of field => imported (true) / skipped (false)
	 */
	public static function apply( array $import ): array {
		$current = AEOMATIC_Settings::all();
		$schema  = $current['schema'] ?? array();
		$llms    = $current['llms_txt'] ?? array();
		$applied = array();

		$org_name = trim( (string) ( $import['org_name'] ?? '' ) );
		if ( $org_name && empty( $schema['org_name'] ) ) {
			$schema['org_name'] = sanitize_text_field( $org_name );
			$applied['org_name'] = true;
		} else {
			$applied['org_name'] = false;
		}

		$org_logo = trim( (string) ( $import['org_logo'] ?? '' ) );
		if ( $org_logo && empty( $schema['org_logo'] ) ) {
			$schema['org_logo']  = esc_url_raw( $org_logo );
			$applied['org_logo'] = true;
		} else {
			$applied['org_logo'] = false;
		}

		$new_sameas = array_filter(
			array_map( 'esc_url_raw', array_map( 'trim', (array) ( $import['org_sameas'] ?? array() ) ) )
		);
		if ( $new_sameas && empty( trim( (string) ( $schema['org_sameas'] ?? '' ) ) ) ) {
			$schema['org_sameas']  = implode( "\n", array_values( array_unique( $new_sameas ) ) );
			$applied['org_sameas'] = true;
		} else {
			$applied['org_sameas'] = false;
		}

		$desc = trim( (string) ( $import['description'] ?? '' ) );
		if ( $desc && empty( $llms['description'] ) ) {
			$llms['description']    = sanitize_textarea_field( $desc );
			$applied['description'] = true;
		} else {
			$applied['description'] = false;
		}

		$current['schema']   = $schema;
		$current['llms_txt'] = $llms;
		AEOMATIC_Settings::put( $current );

		return $applied;
	}

	// ── Per-plugin readers ────────────────────────────────────────────────

	private static function read_yoast(): array {
		$titles = (array) get_option( 'wpseo_titles', array() );
		$social = (array) get_option( 'wpseo_social', array() );

		$logo = '';
		if ( ! empty( $titles['company_logo'] ) ) {
			$logo = (string) $titles['company_logo'];
		} elseif ( ! empty( $titles['company_logo_id'] ) ) {
			$att = wp_get_attachment_image_src( (int) $titles['company_logo_id'], 'full' );
			$logo = $att ? (string) $att[0] : '';
		}

		$sameas = self::filter_urls( array(
			$social['facebook_site']  ?? '',
			$social['twitter_site']   ?? '',
			$social['instagram_url']  ?? '',
			$social['linkedin_url']   ?? '',
			$social['youtube_url']    ?? '',
			$social['pinterest_url']  ?? '',
			$social['wikipedia_url']  ?? '',
			$social['other_social_urls'] ?? '',
		) );

		return array(
			'label'       => 'Yoast SEO',
			'org_name'    => (string) ( $titles['company_name'] ?? '' ),
			'org_logo'    => $logo,
			'org_sameas'  => $sameas,
			'description' => (string) get_bloginfo( 'description' ),
		);
	}

	private static function read_rankmath(): array {
		$titles = (array) get_option( 'rank-math-options-titles', array() );
		$general = (array) get_option( 'rank-math-options-general', array() );

		$logo = '';
		if ( ! empty( $titles['knowledgegraph_logo'] ) ) {
			$val = $titles['knowledgegraph_logo'];
			if ( is_numeric( $val ) ) {
				$att  = wp_get_attachment_image_src( (int) $val, 'full' );
				$logo = $att ? (string) $att[0] : '';
			} else {
				$logo = (string) $val;
			}
		}

		$sameas = self::filter_urls( array(
			$titles['social_url_facebook']  ?? '',
			$titles['social_url_twitter']   ?? '',
			$titles['social_url_instagram'] ?? '',
			$titles['social_url_linkedin']  ?? '',
			$titles['social_url_youtube']   ?? '',
			$titles['social_url_pinterest'] ?? '',
			$general['social_url_facebook'] ?? '',
			$general['social_url_twitter']  ?? '',
		) );

		return array(
			'label'       => 'Rank Math',
			'org_name'    => (string) ( $titles['knowledgegraph_name'] ?? '' ),
			'org_logo'    => $logo,
			'org_sameas'  => $sameas,
			'description' => (string) get_bloginfo( 'description' ),
		);
	}

	private static function read_seopress(): array {
		$social = (array) get_option( 'seopress_social_option_name', array() );

		return array(
			'label'      => 'SEOPress',
			'org_name'   => (string) ( $social['seopress_social_knowledge_name'] ?? '' ),
			'org_logo'   => (string) ( $social['seopress_social_knowledge_img'] ?? '' ),
			'org_sameas' => self::filter_urls( array(
				$social['seopress_social_facebook_link'] ?? '',
				$social['seopress_social_twitter']       ?? '',
				$social['seopress_social_instagram']     ?? '',
				$social['seopress_social_linkedin']      ?? '',
				$social['seopress_social_youtube']       ?? '',
				$social['seopress_social_pinterest']     ?? '',
			) ),
			'description' => (string) get_bloginfo( 'description' ),
		);
	}

	private static function read_aioseo(): array {
		// AIOSEO stores its config as a serialised JSON blob under
		// aioseo_options; recent versions also mirror parts under
		// aioseo_options_dynamic. We try both.
		$raw = get_option( 'aioseo_options', '' );
		$cfg = is_string( $raw ) && $raw !== '' ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		if ( ! is_array( $cfg ) ) {
			$cfg = array();
		}

		$name = '';
		$logo = '';
		$sameas = array();

		$schema = $cfg['searchAppearance']['global']['schema'] ?? array();
		if ( is_array( $schema ) ) {
			$name = (string) ( $schema['organizationName'] ?? '' );
			$logo = (string) ( $schema['organizationLogo'] ?? '' );
		}

		$profiles = $cfg['social']['profiles']['urls'] ?? array();
		if ( is_array( $profiles ) ) {
			$sameas = self::filter_urls( array_values( $profiles ) );
		}

		return array(
			'label'       => 'All in One SEO',
			'org_name'    => $name,
			'org_logo'    => $logo,
			'org_sameas'  => $sameas,
			'description' => (string) get_bloginfo( 'description' ),
		);
	}

	private static function read_tsf(): array {
		$opts = (array) get_option( 'autodescription-site-settings', array() );

		return array(
			'label'      => 'The SEO Framework',
			'org_name'   => (string) ( $opts['knowledge_name'] ?? '' ),
			'org_logo'   => (string) ( $opts['knowledge_logo_url'] ?? '' ),
			'org_sameas' => self::filter_urls( array(
				$opts['knowledge_facebook']  ?? '',
				$opts['knowledge_twitter']   ?? '',
				$opts['knowledge_instagram'] ?? '',
				$opts['knowledge_linkedin']  ?? '',
				$opts['knowledge_youtube']   ?? '',
				$opts['knowledge_pinterest'] ?? '',
			) ),
			'description' => (string) get_bloginfo( 'description' ),
		);
	}

	/**
	 * Flatten, trim, keep only valid URLs, dedupe.
	 *
	 * @param array $items
	 * @return array<int,string>
	 */
	private static function filter_urls( array $items ): array {
		$out = array();
		foreach ( $items as $val ) {
			if ( is_array( $val ) ) {
				foreach ( $val as $sub ) {
					self::maybe_add_url( $out, $sub );
				}
				continue;
			}
			self::maybe_add_url( $out, $val );
		}
		return array_values( array_unique( $out ) );
	}

	private static function maybe_add_url( array &$out, $val ): void {
		if ( ! is_string( $val ) ) {
			return;
		}
		$val = trim( $val );
		if ( '' === $val ) {
			return;
		}
		// Yoast sometimes stores twitter handles without a scheme; normalise.
		if ( 0 !== strpos( $val, 'http' ) ) {
			if ( preg_match( '/^@?[A-Za-z0-9_.\-]+$/', $val ) ) {
				$val = 'https://twitter.com/' . ltrim( $val, '@' );
			} else {
				return;
			}
		}
		if ( filter_var( $val, FILTER_VALIDATE_URL ) ) {
			$out[] = $val;
		}
	}
}
