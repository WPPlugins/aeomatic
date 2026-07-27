<?php
/**
 * Answer Shape gap analyzer — rules that inspect the post's own content
 * for the structural signals AI engines look for when deciding what to cite.
 *
 * Runs entirely on the extracted content array from AEOMATIC_Content_Extractor.
 * No external calls.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Gap_Answer_Shape {

	private const OPENING_WINDOW_CHARS      = 300;
	private const CITATION_HOOK_WORDS       = 500;
	private const ALT_COVERAGE_THRESHOLD    = 0.6;
	private const SCHEMA_CONFIDENCE_CUTOFF  = 50;

	/**
	 * @return AEOMATIC_Gap_Result[]
	 */
	public function analyze( WP_Post $post, array $extract ): array {
		$gaps = array();

		if ( $r = $this->rule_no_opening_answer( $extract ) )                    { $gaps[] = $r; }
		if ( $r = $this->rule_no_question_heading( $extract ) )                  { $gaps[] = $r; }
		if ( $r = $this->rule_no_faq_block( $post, $extract ) )                  { $gaps[] = $r; }
		if ( $r = $this->rule_schema_type_mismatch( $post ) )                    { $gaps[] = $r; }
		if ( $r = $this->rule_low_alt_coverage( $extract ) )                     { $gaps[] = $r; }
		if ( $r = $this->rule_no_citation_hook( $extract ) )                     { $gaps[] = $r; }
		if ( $r = $this->rule_missing_author_signal( $post ) )                   { $gaps[] = $r; }
		if ( $r = $this->rule_missing_meta_description( $post ) )                { $gaps[] = $r; }
		if ( $r = $this->rule_faq_content_without_schema( $post, $extract ) )    { $gaps[] = $r; }
		if ( $r = $this->rule_howto_content_without_schema( $post, $extract ) )  { $gaps[] = $r; }
		if ( $r = $this->rule_missing_from_llms_txt( $post ) )                   { $gaps[] = $r; }

		return $gaps;
	}

	private function rule_no_opening_answer( array $extract ): ?AEOMATIC_Gap_Result {
		$text = trim( (string) ( $extract['text'] ?? '' ) );
		if ( '' === $text ) {
			return null;
		}
		$window = mb_substr( $text, 0, self::OPENING_WINDOW_CHARS );

		// A declarative opening usually contains a linking verb (is/are/means/refers/allows/provides/etc.)
		// and ends its first sentence within the window.
		$has_verb = (bool) preg_match(
			'/\b(is|are|means|refers|refers to|allows|provides|helps|lets|works by|works when|includes|represents|equals|becomes|counts as|stands for|describes)\b/i',
			$window
		);
		$has_sentence_end = (bool) preg_match( '/[.!?]/', $window );

		if ( $has_verb && $has_sentence_end ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'no_opening_answer',
			AEOMATIC_Gap_Result::SEVERITY_HIGH,
			__( 'No direct opening answer in the first 300 characters', 'aeomatic' ),
			__( 'Lead with a single declarative sentence that answers the implied question. AI engines pull citations from opening definitions far more often than from paragraphs of preamble.', 'aeomatic' ),
			'opening-answer',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
		);
	}

	private function rule_no_question_heading( array $extract ): ?AEOMATIC_Gap_Result {
		$html = (string) ( $extract['html'] ?? '' );
		if ( '' === $html ) {
			return null;
		}

		if ( preg_match( '/<h[2-4][^>]*>([^<]*\?)\s*<\/h[2-4]>/i', $html ) ) {
			return null;
		}
		if ( preg_match( '/<h[2-4][^>]*>\s*(how|what|why|when|where|which|who|can|does|is|are|do)\b/i', $html ) ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'no_question_heading',
			AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
			__( 'No question-style headings', 'aeomatic' ),
			__( 'Reframe at least one H2 or H3 as a natural question ("What is X?", "How does Y work?"). Question headings map directly to user prompts and get pulled into AI answers.', 'aeomatic' ),
			'question-headings',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
		);
	}

	private function rule_no_faq_block( WP_Post $post, array $extract ): ?AEOMATIC_Gap_Result {
		$faqs = $extract['faqs'] ?? array();
		if ( is_array( $faqs ) && count( $faqs ) >= 2 ) {
			return null;
		}

		$html = (string) ( $extract['html'] ?? '' );
		if ( '' !== $html && preg_match( '/<details[\s>]/i', $html ) ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'no_faq_block',
			AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
			__( 'No FAQ block on this page', 'aeomatic' ),
			__( 'Add an AEOmatic FAQ block with 3–5 real user questions and short direct answers. FAQPage schema is one of the most-cited structured data types in AI answers.', 'aeomatic' ),
			'faq-block',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
		);
	}

	private function rule_schema_type_mismatch( WP_Post $post ): ?AEOMATIC_Gap_Result {
		if ( ! class_exists( 'AEOMATIC_Schema_Analyzer' ) ) {
			return null;
		}
		$analysis   = AEOMATIC_Schema_Analyzer::analyze( $post );
		$confidence = (int) ( $analysis['confidence'] ?? 100 );
		$type       = (string) ( $analysis['type'] ?? '' );

		if ( $confidence >= self::SCHEMA_CONFIDENCE_CUTOFF ) {
			return null;
		}

		$alternatives = (array) ( $analysis['alternatives'] ?? array() );
		$suggest      = ! empty( $alternatives ) ? (string) $alternatives[0] : '';

		$fix_action = $suggest ? array(
			'code'   => 'set_schema_type',
			'label'  => sprintf( /* translators: %s: suggested schema type */ __( 'Set schema type to %s', 'aeomatic' ), $suggest ),
			'params' => array( 'type' => $suggest ),
		) : null;

		return new AEOMATIC_Gap_Result(
			'schema_type_mismatch',
			AEOMATIC_Gap_Result::SEVERITY_HIGH,
			sprintf(
				/* translators: 1: current schema type, 2: confidence percent */
				__( 'Schema type "%1$s" is a low-confidence match (%2$d%%)', 'aeomatic' ),
				$type ?: '—',
				$confidence
			),
			$suggest
				? sprintf(
					/* translators: %s: suggested alternative schema type */
					__( 'Content signals suggest "%s" would fit better. Override the schema type from the AEOmatic sidebar, or restructure the page to match "%s".', 'aeomatic' ),
					$suggest, $type
				)
				: __( 'Restructure the page to more clearly match its schema type, or override the type from the AEOmatic sidebar.', 'aeomatic' ),
			'schema-mismatch',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE,
			$fix_action
		);
	}

	private function rule_low_alt_coverage( array $extract ): ?AEOMATIC_Gap_Result {
		$html = (string) ( $extract['html'] ?? '' );
		if ( '' === $html ) {
			return null;
		}
		if ( ! preg_match_all( '/<img\b[^>]*>/i', $html, $imgs ) ) {
			return null;
		}
		$total = count( $imgs[0] );
		if ( $total < 2 ) {
			return null;
		}

		$with_alt = 0;
		foreach ( $imgs[0] as $img ) {
			if ( preg_match( '/\balt=(["\'])([^"\']*)\1/i', $img, $m ) && trim( $m[2] ) !== '' ) {
				$with_alt++;
			}
		}
		$coverage = $with_alt / $total;
		if ( $coverage >= self::ALT_COVERAGE_THRESHOLD ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'low_alt_coverage',
			AEOMATIC_Gap_Result::SEVERITY_LOW,
			sprintf(
				/* translators: 1: images with alt, 2: total images, 3: percentage */
				__( 'Only %1$d of %2$d images have alt text (%3$d%%)', 'aeomatic' ),
				$with_alt,
				$total,
				(int) round( $coverage * 100 )
			),
			__( 'Add descriptive alt text to every content image. Multimodal AI engines read alt attributes when deciding whether an image (and the page hosting it) is citation-worthy.', 'aeomatic' ),
			'alt-coverage',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
		);
	}

	private function rule_no_citation_hook( array $extract ): ?AEOMATIC_Gap_Result {
		$html = (string) ( $extract['html'] ?? '' );
		if ( '' === $html ) {
			return null;
		}

		// Take the first ~CITATION_HOOK_WORDS worth of HTML for the check.
		$sample = mb_substr( $html, 0, 6000 );

		$patterns = array(
			'/<ul[\s>]/i',
			'/<ol[\s>]/i',
			'/<table[\s>]/i',
			'/<blockquote[\s>]/i',
			'/<strong[^>]*>[^<]{40,}<\/strong>/i', // long bold callout — likely a summary line
		);
		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $sample ) ) {
				return null;
			}
		}

		return new AEOMATIC_Gap_Result(
			'no_citation_hook',
			AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
			__( 'No lists, tables, or bold summaries near the top', 'aeomatic' ),
			__( 'Add a bullet list, numbered list, comparison table, or bold summary line within the first 500 words. AI engines lift these structural chunks directly into answer snippets.', 'aeomatic' ),
			'citation-hook',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
		);
	}

	private function rule_missing_author_signal( WP_Post $post ): ?AEOMATIC_Gap_Result {
		if ( ! (int) $post->post_author ) {
			// A truly authorless CPT (e.g., product) — skip rather than false-flag.
			return null;
		}

		$author = get_userdata( (int) $post->post_author );
		if ( ! $author ) {
			return null;
		}

		$bio = trim( (string) get_the_author_meta( 'description', (int) $post->post_author ) );
		if ( '' !== $bio && strlen( $bio ) >= 40 ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'missing_author_signal',
			AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
			__( 'Author bio is empty or too short', 'aeomatic' ),
			__( 'Fill in a real author bio (Users → Profile → Biographical Info) with credentials and a link to the author\'s About page. Author authority is a first-order signal for E-E-A-T and AI citation.', 'aeomatic' ),
			'author-signal',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
		);
	}

	private function rule_missing_meta_description( WP_Post $post ): ?AEOMATIC_Gap_Result {
		// Check the four common SEO plugins first. Any populated value wins.
		$candidates = array(
			'_yoast_wpseo_metadesc',   // Yoast SEO
			'rank_math_description',   // Rank Math
			'_aioseo_description',     // AIOSEO
			'_seopress_titles_desc',   // SEOPress
			'_genesis_description',    // Genesis
		);
		foreach ( $candidates as $key ) {
			$val = trim( (string) get_post_meta( $post->ID, $key, true ) );
			if ( '' !== $val && strlen( $val ) >= 40 ) {
				return null;
			}
		}

		// Fall back to a manual excerpt — long enough to serve as one.
		$excerpt = trim( (string) $post->post_excerpt );
		if ( '' !== $excerpt && strlen( $excerpt ) >= 80 ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'missing_meta_description',
			AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
			__( 'No meta description set', 'aeomatic' ),
			__( 'Add a 120–160 character summary in your SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress) or write a post excerpt of at least 80 characters. AI engines quote meta descriptions verbatim when generating answer snippets.', 'aeomatic' ),
			'meta-description',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE,
			array(
				'code'   => 'generate_meta_description',
				'label'  => __( 'Generate from first paragraph', 'aeomatic' ),
				'params' => new stdClass(), // no params — dispatcher derives from post content
			)
		);
	}

	private function rule_faq_content_without_schema( WP_Post $post, array $extract ): ?AEOMATIC_Gap_Result {
		$html = (string) ( $extract['html'] ?? '' );
		if ( '' === $html ) {
			return null;
		}

		// Skip if we already emit FAQPage schema (the base plugin does this
		// when the FAQ block is present or when the schema type is FAQPage).
		$schema_type = 'Article';
		if ( class_exists( 'AEOMATIC_Schema_Analyzer' ) ) {
			$analysis    = AEOMATIC_Schema_Analyzer::analyze( $post );
			$schema_type = (string) ( $analysis['type'] ?? 'Article' );
		}
		if ( 'FAQPage' === $schema_type || 'QAPage' === $schema_type ) {
			return null;
		}
		$faqs = $extract['faqs'] ?? array();
		if ( is_array( $faqs ) && count( $faqs ) >= 2 ) {
			return null; // The FAQ block itself emits FAQPage schema.
		}

		// Look for FAQ-shaped content that isn't a FAQ block:
		// - 3+ headings ending in "?"
		// - or a <details>/<summary> pattern with 2+ items
		$question_headings = 0;
		if ( preg_match_all( '/<h[2-4][^>]*>[^<]*\?\s*<\/h[2-4]>/i', $html, $m ) ) {
			$question_headings = count( $m[0] );
		}
		$details_count = 0;
		if ( preg_match_all( '/<details[\s>]/i', $html, $m ) ) {
			$details_count = count( $m[0] );
		}

		if ( $question_headings < 3 && $details_count < 2 ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'faq_content_without_schema',
			AEOMATIC_Gap_Result::SEVERITY_HIGH,
			__( 'FAQ-shaped content without FAQPage schema', 'aeomatic' ),
			__( 'This page has multiple question-style headings but no FAQPage schema. Convert the Q&A section to an AEOmatic FAQ block, or override the schema type to FAQPage from the AEOmatic sidebar. FAQPage is one of the most-cited schema types in AI answers.', 'aeomatic' ),
			'faq-without-schema',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE,
			array(
				'code'   => 'set_schema_type',
				'label'  => __( 'Set schema type to FAQPage', 'aeomatic' ),
				'params' => array( 'type' => 'FAQPage' ),
			)
		);
	}

	private function rule_howto_content_without_schema( WP_Post $post, array $extract ): ?AEOMATIC_Gap_Result {
		$html = (string) ( $extract['html'] ?? '' );
		$text = (string) ( $extract['text'] ?? '' );
		if ( '' === $html ) {
			return null;
		}

		$schema_type = 'Article';
		if ( class_exists( 'AEOMATIC_Schema_Analyzer' ) ) {
			$analysis    = AEOMATIC_Schema_Analyzer::analyze( $post );
			$schema_type = (string) ( $analysis['type'] ?? 'Article' );
		}
		if ( 'HowTo' === $schema_type || 'Recipe' === $schema_type ) {
			return null;
		}

		// HowTo signals: an <ol> with 3+ <li>, or step-language in the text.
		$has_numbered_list = false;
		if ( preg_match_all( '/<ol[\s>][\s\S]*?<\/ol>/i', $html, $m ) ) {
			foreach ( $m[0] as $ol ) {
				$li_count = preg_match_all( '/<li[\s>]/i', $ol );
				if ( $li_count >= 3 ) {
					$has_numbered_list = true;
					break;
				}
			}
		}
		$has_step_language = (bool) preg_match(
			'/\b(step\s*\d+|step\s+one|step\s+two|first[,:]|next[,:]|then[,:]|finally[,:])\b/i',
			$text
		);
		$title_signals = (bool) preg_match( '/\b(how to|how do i|guide|tutorial|walkthrough)\b/i', (string) $post->post_title );

		// Need at least two of three signals to flag with confidence.
		$score = ( $has_numbered_list ? 1 : 0 ) + ( $has_step_language ? 1 : 0 ) + ( $title_signals ? 1 : 0 );
		if ( $score < 2 ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'howto_content_without_schema',
			AEOMATIC_Gap_Result::SEVERITY_HIGH,
			__( 'HowTo-shaped content without HowTo schema', 'aeomatic' ),
			__( 'This page reads like a step-by-step guide but does not emit HowTo schema. Override the schema type to HowTo from the AEOmatic sidebar. HowTo schema surfaces your steps directly in AI answers and Google rich results.', 'aeomatic' ),
			'howto-without-schema',
			AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE,
			array(
				'code'   => 'set_schema_type',
				'label'  => __( 'Set schema type to HowTo', 'aeomatic' ),
				'params' => array( 'type' => 'HowTo' ),
			)
		);
	}

	private function rule_missing_from_llms_txt( WP_Post $post ): ?AEOMATIC_Gap_Result {
		if ( 'publish' !== $post->post_status ) {
			return null;
		}
		if ( ! class_exists( 'AEOMATIC_Settings' ) ) {
			return null;
		}
		// Only posts and pages ship in the llms.txt index by default. Other
		// public CPTs (WooCommerce products, EDD downloads, custom types)
		// are excluded from llms.txt by design, so flagging them would be noise.
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return null;
		}

		// llms.txt disabled entirely at the plugin level → nothing is listed.
		$llms_enabled      = (bool) AEOMATIC_Settings::get( 'llms_txt', 'enabled' );
		$llms_auto_include = (bool) AEOMATIC_Settings::get( 'llms_txt', 'auto_include' );

		if ( ! $llms_enabled ) {
			return new AEOMATIC_Gap_Result(
				'missing_from_llms_txt',
				AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
				__( '/llms.txt is disabled — this page is not discoverable by AI crawlers via it', 'aeomatic' ),
				__( 'Enable /llms.txt in Settings → llms.txt so AI crawlers can find this and every other published post/page in one index.', 'aeomatic' ),
				'llms-txt-missing',
				AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
			);
		}

		if ( ! $llms_auto_include ) {
			return new AEOMATIC_Gap_Result(
				'missing_from_llms_txt',
				AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
				__( 'Auto-include is off — this page is not listed in /llms.txt', 'aeomatic' ),
				__( 'Turn on "Automatically include all published posts and pages" in Settings → llms.txt, or add this URL to the Extra URLs list.', 'aeomatic' ),
				'llms-txt-missing',
				AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
			);
		}

		// Password-protected posts also don't ship in llms.txt.
		if ( '' !== $post->post_password ) {
			return new AEOMATIC_Gap_Result(
				'missing_from_llms_txt',
				AEOMATIC_Gap_Result::SEVERITY_LOW,
				__( 'Password-protected — not listed in /llms.txt', 'aeomatic' ),
				__( 'Password-protected posts are excluded from the /llms.txt index by design. Remove the password if you want AI crawlers to discover this page.', 'aeomatic' ),
				'llms-txt-missing',
				AEOMATIC_Gap_Result::SOURCE_ANSWER_SHAPE
			);
		}

		return null;
	}
}
