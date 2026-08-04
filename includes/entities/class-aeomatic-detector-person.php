<?php
/**
 * Detects Person entity coverage.
 *
 * Signals (weights sum to 100):
 *   - Schema type is Person / ProfilePage        (30)
 *   - Post author has non-empty display name     (15)
 *   - Post author bio ≥ 40 words                 (25)
 *   - Post author has profile URL / social       (15)
 *   - Author name appears in body or headings    (15)
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Detector_Person implements AEOMATIC_Entity_Detector_Interface {

	public function detect( WP_Post $post, AEOMATIC_Entity_Signal_Collector $signals ): AEOMATIC_Entity_Detection_Result {
		$score          = 0;
		$found          = array();
		$missing        = array();
		$recommendations = array();

		if ( $signals->has_schema_type( 'Person' ) || $signals->has_schema_type( 'ProfilePage' ) ) {
			$score += 30;
			$found[] = __( 'Schema type is Person or ProfilePage.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No Person / ProfilePage schema.', 'aeomatic' );
			$recommendations[] = __( 'For author-focused or bio pages, set schema type to Person or ProfilePage.', 'aeomatic' );
		}

		$author = $signals->post_author_display_name();
		if ( $author !== '' ) {
			$score += 15;
			$found[] = sprintf( __( 'Author byline: "%s".', 'aeomatic' ), $author );
		} else {
			$missing[]         = __( 'No author display name.', 'aeomatic' );
			$recommendations[] = __( 'Assign the post to a user with a filled display name.', 'aeomatic' );
		}

		$bio_words = $signals->post_author_bio_words();
		if ( $bio_words >= 40 ) {
			$score += 25;
			$found[] = sprintf( __( 'Author bio has %d words.', 'aeomatic' ), $bio_words );
		} elseif ( $bio_words > 0 ) {
			$score += 10;
			$missing[]         = sprintf( __( 'Author bio has only %d words (target 40+).', 'aeomatic' ), $bio_words );
			$recommendations[] = __( 'Expand the author bio: role, credentials, and a link to a professional profile.', 'aeomatic' );
		} else {
			$missing[]         = __( 'Author has no bio.', 'aeomatic' );
			$recommendations[] = __( 'Add an author bio in Users → Profile → Biographical Info.', 'aeomatic' );
		}

		if ( $signals->post_author_has_url() ) {
			$score += 15;
			$found[] = __( 'Author has a profile URL (website / LinkedIn / GitHub).', 'aeomatic' );
		} else {
			$missing[]         = __( 'Author has no profile URL.', 'aeomatic' );
			$recommendations[] = __( 'Add a website or LinkedIn URL for the author so AI engines can verify identity.', 'aeomatic' );
		}

		if ( $author !== '' && ( $signals->has_content_keyword( array( $author ), 1 ) || $signals->has_heading_keyword( array( $author ) ) ) ) {
			$score += 15;
			$found[] = __( 'Author name appears in body or headings.', 'aeomatic' );
		} else {
			$missing[]         = __( 'Author name not mentioned in body or headings.', 'aeomatic' );
			$recommendations[] = __( 'Mention the author\'s name explicitly at least once in the body ("Written by …").', 'aeomatic' );
		}

		$detected = $score >= AEOMATIC_Entity_Scanner::detected_threshold();
		return new AEOMATIC_Entity_Detection_Result( 'person', $detected, $score, $found, $missing, $recommendations );
	}
}
