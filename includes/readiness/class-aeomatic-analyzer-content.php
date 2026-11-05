<?php
/**
 * Content-quality category for AI Readiness.
 *
 * Signals scored (deducting from 100 for each miss):
 *   - Body length below the AI-citation threshold  (weight 25)
 *   - No opening-answer / TL;DR paragraph          (weight 25)
 *   - No FAQ / Q&A structure                       (weight 15)
 *   - No numeric anchors (stats to quote)          (weight 20)
 *   - Missing heading hierarchy (H1/H2/H3 gaps)    (weight 15)
 *
 * Uses AEOMATIC_Content_Extractor to unify text extraction across Gutenberg,
 * Elementor, Divi etc. — no duplication with other modules.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Analyzer_Content implements AEOMATIC_Readiness_Analyzer_Interface {

	private const MIN_WORDS = 300;

	public function analyze( WP_Post $post ): AEOMATIC_Readiness_Result {
		// Both html and text come from the shared extractor. Reading
		// $post->post_content directly scored page-builder and ACF pages as
		// empty, because their content lives in post meta.
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$text      = (string) ( $extracted['text'] ?? '' );
		$html      = (string) ( $extracted['html'] ?? '' );
		$words     = AEOMATIC_Content_Extractor::count_words( $text );

		$score           = 100;
		$issues          = array();
		$recommendations = array();

		if ( $words < self::MIN_WORDS ) {
			$score -= 25;
			$issues[]          = sprintf( __( 'Body is only %d words. Pages under 300 words give AI assistants little to quote.', 'aeomatic' ), $words );
			$recommendations[] = __( 'Expand the post with a definition, one worked example, and one counter-example.', 'aeomatic' );
		}

		if ( ! $this->has_opening_answer( $text ) ) {
			$score -= 25;
			$issues[]          = __( 'No direct answer / TL;DR in the opening paragraph.', 'aeomatic' );
			$recommendations[] = __( 'Add a 1-2 sentence direct answer at the very top of the post.', 'aeomatic' );
		}

		if ( ! $this->has_faq_structure( $html ) ) {
			$score -= 15;
			$issues[]          = __( 'No FAQ block or question-style headings detected.', 'aeomatic' );
			$recommendations[] = __( 'Add 3-5 Q&A pairs near the end of the post. Short questions with direct answers are easy for AI assistants to quote.', 'aeomatic' );
		}

		if ( ! $this->has_numeric_anchors( $text ) ) {
			$score -= 20;
			$issues[]          = __( 'No citable numeric anchors (percentages, ratios, or dollar figures).', 'aeomatic' );
			$recommendations[] = __( 'Add at least one quantified claim ("X%…", "N in M…", "$X per…") with a source link.', 'aeomatic' );
		}

		if ( ! $this->has_heading_hierarchy( $html ) ) {
			$score -= 15;
			$issues[]          = __( 'Heading hierarchy is missing or shallow — no H2s detected.', 'aeomatic' );
			$recommendations[] = __( 'Structure the body with H2 sections and H3 sub-sections so AI engines can pull answer fragments.', 'aeomatic' );
		}

		return new AEOMATIC_Readiness_Result( 'content', $score, $issues, $recommendations );
	}

	private function has_opening_answer( string $text ): bool {
		$opening = mb_substr( trim( $text ), 0, 500 );
		if ( $opening === '' ) {
			return false;
		}
		if ( preg_match( '/\b(tl;dr|in short|short answer|the answer|quick answer)\b/i', $opening ) ) {
			return true;
		}
		$first_sentence = preg_split( '/(?<=[.!?])\s+/', $opening )[0] ?? '';
		return AEOMATIC_Content_Extractor::count_words( $first_sentence ) >= 8;
	}

	private function has_faq_structure( string $html ): bool {
		if ( stripos( $html, 'wp:aeomatic/faq' ) !== false ) {
			return true;
		}
		// Builder accordions surface as FAQ pairs via the extractor rather than
		// as headings, so a detected pair counts as FAQ structure.
		if ( stripos( $html, '<details' ) !== false ) {
			return true;
		}
		if ( preg_match_all( '/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', $html, $m ) ) {
			foreach ( $m[1] as $heading ) {
				$plain = trim( wp_strip_all_tags( $heading ) );
				if ( $plain === '' ) {
					continue;
				}
				if ( str_ends_with( $plain, '?' ) || preg_match( '/^(what|how|why|when|where|who|is|are|can|should|do|does)\b/i', $plain ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private function has_numeric_anchors( string $text ): bool {
		return (bool) preg_match(
			'/(\b\d{1,3}(?:\.\d+)?%|\b\d+\s*(?:in|out of|of every)\s*\d+\b|\$\d[\d,]*(?:\.\d+)?|\b\d{1,3}(?:,\d{3})+\b)/i',
			$text
		);
	}

	private function has_heading_hierarchy( string $html ): bool {
		return (bool) preg_match( '/<h2[^>]*>/i', $html );
	}
}
