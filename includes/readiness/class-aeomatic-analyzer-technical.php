<?php
/**
 * Technical category for AI Readiness — infrastructure signals.
 *
 * Signals (weights sum to 100 deducted possible):
 *   - Post marked noindex                         (weight 30)
 *   - No canonical URL derivable                  (weight 15)
 *   - Not included in the site's llms.txt         (weight 20)
 *   - Empty body / JS-only content (short body)   (weight 20)
 *   - No AI HTTP discovery headers enabled        (weight 15)
 *
 * Reads AEOMATIC_LLMs_Txt to check inclusion — no separate scan.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Analyzer_Technical implements AEOMATIC_Readiness_Analyzer_Interface {

	public function analyze( WP_Post $post ): AEOMATIC_Readiness_Result {
		$score           = 100;
		$issues          = array();
		$recommendations = array();

		if ( $this->is_noindex( $post ) ) {
			$score -= 30;
			$issues[]          = __( 'This post is marked noindex by an SEO plugin — AI crawlers may skip it.', 'aeomatic' );
			$recommendations[] = __( 'Remove the noindex directive if this content is meant to be discoverable.', 'aeomatic' );
		}

		if ( get_permalink( $post ) === false ) {
			$score -= 15;
			$issues[]          = __( 'No canonical / permalink derivable — permalink structure may be broken.', 'aeomatic' );
			$recommendations[] = __( 'Re-save permalink settings and confirm this post opens at a stable URL.', 'aeomatic' );
		}

		if ( ! $this->included_in_llms_txt( $post ) ) {
			$score -= 20;
			$issues[]          = __( 'This post is not included in the site\'s llms.txt index.', 'aeomatic' );
			$recommendations[] = __( 'Open AEOmatic → Settings → llms.txt and enable this post type for inclusion.', 'aeomatic' );
		}

		// Read through the extractor, which merges post_content, page-builder
		// data and ACF fields. Reading post_content alone fired this check on
		// every Elementor or Divi page — telling the user their content was
		// unparseable precisely because they used a page builder.
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$text      = trim( (string) ( $extracted['text'] ?? '' ) );
		if ( str_word_count( $text ) < 20 ) {
			$score -= 20;
			$issues[]          = __( 'Under 20 words of extractable text found in the post body, page-builder data, or ACF fields — AI crawlers may find little to read here.', 'aeomatic' );
			$recommendations[] = __( 'Add body copy, or check that the content renders in the initial HTML response rather than being injected by JavaScript.', 'aeomatic' );
		}

		if ( ! $this->ai_headers_enabled() ) {
			$score -= 15;
			$issues[]          = __( 'AI HTTP discovery headers (X-AI-Crawl, X-AI-Citeable, etc.) are disabled.', 'aeomatic' );
			$recommendations[] = __( 'Enable AI HTTP headers in AEOmatic → Settings for automatic AI-crawler discovery signals.', 'aeomatic' );
		}

		return new AEOMATIC_Readiness_Result( 'technical', $score, $issues, $recommendations );
	}

	private function is_noindex( WP_Post $post ): bool {
		$keys = array(
			'_yoast_wpseo_meta-robots-noindex' => static fn( $v ) => (string) $v === '1',
			'rank_math_robots'                 => static fn( $v ) => is_array( $v ) ? in_array( 'noindex', $v, true ) : false,
			'_aioseo_robots_noindex'           => static fn( $v ) => (string) $v === '1' || $v === true,
			'_seopress_robots_index'           => static fn( $v ) => (string) $v === 'yes',
		);
		foreach ( $keys as $meta_key => $check ) {
			$val = get_post_meta( $post->ID, $meta_key, true );
			if ( $val !== '' && $val !== null && $check( $val ) ) {
				return true;
			}
		}
		return false;
	}

	private function included_in_llms_txt( WP_Post $post ): bool {
		if ( ! class_exists( 'AEOMATIC_LLMs_Txt' ) ) {
			return true; // Nothing to check against; don't penalise.
		}
		if ( method_exists( 'AEOMATIC_LLMs_Txt', 'is_post_included' ) ) {
			return (bool) AEOMATIC_LLMs_Txt::is_post_included( $post );
		}
		if ( class_exists( 'AEOMATIC_Post_Types' ) ) {
			$enabled = AEOMATIC_Post_Types::get_enabled_slugs();
			return in_array( $post->post_type, (array) $enabled, true );
		}
		return true;
	}

	private function ai_headers_enabled(): bool {
		if ( ! class_exists( 'AEOMATIC_AI_Headers' ) ) {
			return true; // Nothing to check against; don't penalise.
		}
		return AEOMATIC_AI_Headers::is_enabled();
	}
}
