<?php
/**
 * Detects FAQ entity coverage.
 *
 * Signals (weights sum to 100):
 *   - Schema type is FAQPage / QAPage      (40)
 *   - AEOmatic FAQ block present in body   (30)
 *   - Question-style headings              (20)
 *   - FAQ-context section heading          (10)
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Detector_Faq implements AEOMATIC_Entity_Detector_Interface {

	public function detect( WP_Post $post, AEOMATIC_Entity_Signal_Collector $signals ): AEOMATIC_Entity_Detection_Result {
		$score          = 0;
		$found          = array();
		$missing        = array();
		$recommendations = array();

		if ( $signals->has_schema_type( 'FAQPage' ) || $signals->has_schema_type( 'QAPage' ) ) {
			$score += 40;
			$found[] = __( 'Schema type is FAQPage or QAPage.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No FAQPage / QAPage schema.', 'aeomatic' );
			$recommendations[] = __( 'On pages with Q&A structure, set schema type to FAQPage so AI engines can quote answers.', 'aeomatic' );
		}

		if ( $signals->has_faq_block() ) {
			$score += 30;
			$found[] = __( 'FAQ content detected in body.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No FAQ block, accordion, or Q&A pattern in body.', 'aeomatic' );
			$recommendations[] = $signals->is_block_editor()
				? __( 'Insert the AEOmatic FAQ block — it auto-generates FAQPage schema and improves citation odds.', 'aeomatic' )
				: $this->builder_faq_hint( $signals->builder() );
		}

		if ( $signals->has_question_heading() ) {
			$score += 20;
			$found[] = __( 'Question-style heading(s) detected.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No question-style headings.', 'aeomatic' );
			$recommendations[] = __( 'Convert 3-5 subheadings into questions ("What is X?", "How does Y work?") — AI engines quote them verbatim.', 'aeomatic' );
		}

		if ( $signals->has_heading_keyword( array( 'faq', 'frequently asked', 'questions', 'q&a' ) ) ) {
			$score += 10;
			$found[] = __( 'FAQ-context section heading detected.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No FAQ-context section heading.', 'aeomatic' );
			$recommendations[] = __( 'Group Q&As under an H2 titled "FAQ" or "Frequently Asked Questions".', 'aeomatic' );
		}

		$detected = $score >= AEOMATIC_Entity_Scanner::detected_threshold();
		return new AEOMATIC_Entity_Detection_Result( 'faq', $detected, $score, $found, $missing, $recommendations );
	}

	/**
	 * Build a builder-specific FAQ recommendation so users see wording that
	 * matches their actual editor. The shared content extractor already
	 * recognises FAQ output from every builder listed here — the recommendation
	 * just needs to name the widget the user has.
	 */
	private function builder_faq_hint( string $builder ): string {
		switch ( $builder ) {
			case 'elementor':
				return __( 'Add an Elementor Toggle or Accordion widget with your Q&As — AEOmatic will detect it and emit FAQPage schema.', 'aeomatic' );
			case 'divi':
				return __( 'Add a Divi Toggle or Accordion module with your Q&As — AEOmatic will detect it and emit FAQPage schema.', 'aeomatic' );
			case 'wpbakery':
				return __( 'Add a WPBakery Toggle or Accordion element with your Q&As — AEOmatic will detect it and emit FAQPage schema.', 'aeomatic' );
			case 'beaver':
			case 'bricks':
			case 'oxygen':
				return __( 'Add an accordion / toggle module in your builder with your Q&As. AEOmatic will detect the Q&A structure and emit FAQPage schema.', 'aeomatic' );
			default:
				return __( 'Add an ACF FAQ repeater, an accordion module, or Q&A headings. AEOmatic will detect the structure and emit FAQPage schema.', 'aeomatic' );
		}
	}
}
