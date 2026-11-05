<?php
/**
 * Authority category for AI Readiness — reputation signals.
 *
 * Signals (weights sum to 100 deducted possible):
 *   - Author bio missing or shallow (<40 words)   (weight 25)
 *   - Author has no linked social/website profile (weight 15)
 *   - Organization/Person schema not configured   (weight 20)
 *   - Fewer than 3 incoming internal links        (weight 20)
 *   - Fewer than 1 outbound authoritative link    (weight 20)
 *
 * "Authoritative" outbound host = .gov / .edu / .org, or a Wikipedia /
 * standards-body domain. Deliberately narrow so the signal stays meaningful.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Analyzer_Authority implements AEOMATIC_Readiness_Analyzer_Interface {

	private const AUTHORITATIVE_TLDS  = array( 'gov', 'edu' );
	private const AUTHORITATIVE_HOSTS = array(
		'wikipedia.org', 'w3.org', 'ietf.org', 'iso.org', 'nist.gov',
		'schema.org', 'developer.mozilla.org', 'oed.com', 'britannica.com',
	);

	public function analyze( WP_Post $post ): AEOMATIC_Readiness_Result {
		$score           = 100;
		$issues          = array();
		$recommendations = array();

		$bio_words = $this->author_bio_words( $post );
		if ( $bio_words < 40 ) {
			$score -= 25;
			$issues[]          = sprintf( __( 'Author bio has only %d words. A fuller bio shows what the author knows.', 'aeomatic' ), $bio_words );
			$recommendations[] = __( 'Expand the author bio to at least 40 words: role, credentials, and one link to a professional profile.', 'aeomatic' );
		}

		if ( ! $this->author_has_profile_link( $post ) ) {
			$score -= 15;
			$issues[]          = __( 'Author has no linked professional URL (website / LinkedIn / GitHub).', 'aeomatic' );
			$recommendations[] = __( 'Add a URL to the author profile so AI engines can verify identity.', 'aeomatic' );
		}

		if ( ! $this->has_organization_schema() ) {
			$score -= 20;
			$issues[]          = __( 'Organization / Person schema is not configured in AEOmatic settings.', 'aeomatic' );
			$recommendations[] = __( 'Open AEOmatic → Settings and fill the Organization name, logo, and social profiles.', 'aeomatic' );
		}

		$incoming = $this->incoming_internal_links( $post );
		if ( $incoming < 3 ) {
			$score -= 20;
			$issues[]          = sprintf( __( 'Only %d other posts on this site link here — thin internal support.', 'aeomatic' ), $incoming );
			$recommendations[] = __( 'Link to this post from at least 3 related posts to build internal-graph authority.', 'aeomatic' );
		}

		if ( ! $this->has_authoritative_outbound( $post ) ) {
			$score -= 20;
			$issues[]          = __( 'No outbound links to authoritative domains (.gov / .edu / Wikipedia / standards bodies).', 'aeomatic' );
			$recommendations[] = __( 'Cite at least one authoritative source when making factual claims.', 'aeomatic' );
		}

		return new AEOMATIC_Readiness_Result( 'authority', $score, $issues, $recommendations );
	}

	private function author_bio_words( WP_Post $post ): int {
		if ( (int) $post->post_author <= 0 ) {
			return 0;
		}
		$bio = get_user_meta( (int) $post->post_author, 'description', true );
		return AEOMATIC_Content_Extractor::count_words( (string) wp_strip_all_tags( (string) $bio ) );
	}

	private function author_has_profile_link( WP_Post $post ): bool {
		if ( (int) $post->post_author <= 0 ) {
			return false;
		}
		$user = get_userdata( (int) $post->post_author );
		if ( ! $user ) {
			return false;
		}
		if ( trim( (string) $user->user_url ) !== '' ) {
			return true;
		}
		// Author profile module (if in use) stores social URLs in user meta.
		foreach ( array( 'aeomatic_linkedin', 'aeomatic_twitter', 'aeomatic_github', 'aeomatic_website' ) as $meta_key ) {
			if ( trim( (string) get_user_meta( $user->ID, $meta_key, true ) ) !== '' ) {
				return true;
			}
		}
		return false;
	}

	private function has_organization_schema(): bool {
		if ( ! class_exists( 'AEOMATIC_Settings' ) ) {
			return false;
		}
		$name = (string) AEOMATIC_Settings::get( 'schema', 'org_name' );
		return trim( $name ) !== '';
	}

	private function incoming_internal_links( WP_Post $post ): int {
		global $wpdb;
		$permalink = get_permalink( $post );
		if ( ! $permalink ) {
			return 0;
		}
		$path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		if ( $path === '' ) {
			return 0;
		}
		$like = '%' . $wpdb->esc_like( $path ) . '%';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				  WHERE post_status = 'publish'
				    AND ID <> %d
				    AND post_content LIKE %s",
				$post->ID,
				$like
			)
		);
	}

	private function has_authoritative_outbound( WP_Post $post ): bool {
		// Extracted html, not post_content: builder links live in post meta.
		$html = (string) ( AEOMATIC_Content_Extractor::extract( $post )['html'] ?? '' );
		if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $m ) ) {
			return false;
		}
		foreach ( $m[1] as $url ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( $host === '' ) {
				continue;
			}
			$parts = explode( '.', $host );
			$tld   = end( $parts );
			if ( in_array( $tld, self::AUTHORITATIVE_TLDS, true ) ) {
				return true;
			}
			foreach ( self::AUTHORITATIVE_HOSTS as $auth ) {
				if ( $host === $auth || str_ends_with( $host, '.' . $auth ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
