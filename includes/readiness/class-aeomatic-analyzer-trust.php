<?php
/**
 * Trust category for AI Readiness — E-E-A-T signals.
 *
 * Signals (weights sum to 100 deducted possible):
 *   - Missing meta description                (weight 20)
 *   - No visible author byline / author unset (weight 20)
 *   - Content older than 365 days             (weight 15)
 *   - Fewer than 2 outbound citation links    (weight 25)
 *   - Excessive promotional links (>30%)      (weight 20)
 *
 * Reads the meta description via the post's excerpt fallback, and by peeking
 * at Yoast / Rank Math / AIOSEO / SEOPress keys if present — matches the
 * detection used elsewhere in the plugin.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Analyzer_Trust implements AEOMATIC_Readiness_Analyzer_Interface {

	private const KNOWN_META_DESC_KEYS = array(
		'_yoast_wpseo_metadesc',
		'rank_math_description',
		'_aioseo_description',
		'_seopress_titles_desc',
	);

	private const STALE_DAYS = 365;

	public function analyze( WP_Post $post ): AEOMATIC_Readiness_Result {
		$score           = 100;
		$issues          = array();
		$recommendations = array();

		if ( ! $this->has_meta_description( $post ) ) {
			$score -= 20;
			$issues[]          = __( 'No meta description set (checked Yoast / Rank Math / AIOSEO / SEOPress / excerpt).', 'aeomatic' );
			$recommendations[] = __( 'Add a 120-155 character meta description that answers the primary question.', 'aeomatic' );
		}

		if ( ! $this->has_author_byline( $post ) ) {
			$score -= 20;
			$issues[]          = __( 'No author byline — post shows no attributable expert.', 'aeomatic' );
			$recommendations[] = __( 'Assign a user with a filled-in bio as the author. AI engines cite content with a named expert more often.', 'aeomatic' );
		}

		$age_days = $this->age_in_days( $post );
		if ( $age_days > self::STALE_DAYS ) {
			$score -= 15;
			$issues[]          = sprintf( __( 'Post was last modified %d days ago; AI engines de-prioritise stale content.', 'aeomatic' ), $age_days );
			$recommendations[] = __( 'Update the post — refresh dates, stats, and examples — to signal ongoing accuracy.', 'aeomatic' );
		}

		$outbound_count = $this->outbound_links_count( $post );
		if ( $outbound_count < 2 ) {
			$score -= 25;
			$issues[]          = sprintf( __( 'Only %d outbound source links; AI engines favour content that cites its sources.', 'aeomatic' ), $outbound_count );
			$recommendations[] = __( 'Link to at least 2 authoritative external sources (studies, official docs, standards bodies).', 'aeomatic' );
		}

		if ( $this->promotional_ratio( $post ) > 0.3 ) {
			$score -= 20;
			$issues[]          = __( 'More than 30% of links are promotional (your own pricing, sign-up, or affiliate URLs).', 'aeomatic' );
			$recommendations[] = __( 'Rebalance link mix: for every promo link, add at least two links to independent third-party sources.', 'aeomatic' );
		}

		return new AEOMATIC_Readiness_Result( 'trust', $score, $issues, $recommendations );
	}

	private function has_meta_description( WP_Post $post ): bool {
		foreach ( self::KNOWN_META_DESC_KEYS as $key ) {
			$val = get_post_meta( $post->ID, $key, true );
			if ( is_string( $val ) && trim( $val ) !== '' ) {
				return true;
			}
		}
		return trim( (string) $post->post_excerpt ) !== '';
	}

	private function has_author_byline( WP_Post $post ): bool {
		if ( (int) $post->post_author <= 0 ) {
			return false;
		}
		$user = get_userdata( (int) $post->post_author );
		if ( ! $user ) {
			return false;
		}
		return trim( (string) $user->display_name ) !== '';
	}

	private function age_in_days( WP_Post $post ): int {
		$modified = strtotime( (string) $post->post_modified_gmt );
		if ( ! $modified ) {
			return 0;
		}
		return (int) floor( ( time() - $modified ) / DAY_IN_SECONDS );
	}

	/**
	 * Merged html from the shared extractor — post_content plus page-builder
	 * and ACF content. Builder links are invisible in post_content alone.
	 */
	private function extracted_html( WP_Post $post ): string {
		return (string) ( AEOMATIC_Content_Extractor::extract( $post )['html'] ?? '' );
	}

	private function outbound_links_count( WP_Post $post ): int {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $this->extracted_html( $post ), $m ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $m[1] as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host && $host !== $home && strpos( $url, 'http' ) === 0 ) {
				$count++;
			}
		}
		return $count;
	}

	private function promotional_ratio( WP_Post $post ): float {
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $this->extracted_html( $post ), $m ) ) {
			return 0.0;
		}
		$total = 0;
		$promo = 0;
		foreach ( $m[1] as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( ! $host || strpos( $url, 'http' ) !== 0 ) {
				continue;
			}
			$total++;
			if ( $host === $home ) {
				if ( preg_match( '#/(pricing|signup|sign-up|buy|checkout|cart|subscribe|trial)(/|$|\?)#i', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) {
					$promo++;
				}
			}
			if ( preg_match( '/(\?|&)(ref|aff|affiliate|utm_campaign=affiliate)/i', $url ) ) {
				$promo++;
			}
		}
		return $total > 0 ? $promo / $total : 0.0;
	}
}
