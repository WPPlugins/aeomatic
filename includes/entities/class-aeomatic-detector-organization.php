<?php
/**
 * Detects Organization entity coverage.
 *
 * Signals (weights sum to 100):
 *   - Schema type = Organization or subtype           (35)
 *   - Organization name configured in settings         (25)
 *   - Organization logo configured in settings         (15)
 *   - "founded/established/since YYYY" pattern in body (10)
 *   - Organization mentioned in headings                (15)
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Detector_Organization implements AEOMATIC_Entity_Detector_Interface {

	public function detect( WP_Post $post, AEOMATIC_Entity_Signal_Collector $signals ): AEOMATIC_Entity_Detection_Result {
		$score          = 0;
		$found          = array();
		$missing        = array();
		$recommendations = array();

		if ( $signals->schema_type_is_subtype_of( 'Organization' ) || $signals->schema_type_is_subtype_of( 'Corporation' ) ) {
			$score += 35;
			$found[] = __( 'Schema type is Organization (or subtype).', 'aeomatic' );
		} else {
			$missing[]         = __( 'No Organization schema on this post.', 'aeomatic' );
			$recommendations[] = __( 'Set the schema type to Organization for pages describing your company.', 'aeomatic' );
		}

		$org_name = $signals->organization_setting( 'name' );
		if ( $org_name !== '' ) {
			$score += 25;
			$found[] = sprintf( __( 'Organisation name configured: "%s".', 'aeomatic' ), $org_name );
		} else {
			$missing[]         = __( 'Organisation name is not set.', 'aeomatic' );
			$recommendations[] = __( 'Open AEOmatic → Settings and fill the Organisation name.', 'aeomatic' );
		}

		$org_logo = $signals->organization_setting( 'logo' );
		if ( $org_logo !== '' ) {
			$score += 15;
			$found[] = __( 'Organisation logo configured.', 'aeomatic' );
		} else {
			$missing[]         = __( 'Organisation logo not configured.', 'aeomatic' );
			$recommendations[] = __( 'Add an Organisation logo URL in Settings — required for Article schema publisher property.', 'aeomatic' );
		}

		if ( $signals->has_founded_pattern() ) {
			$score += 10;
			$found[] = __( 'Founding date pattern detected in body.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No founding date signal in body.', 'aeomatic' );
			$recommendations[] = __( 'Mention when the organisation was founded ("Founded in 2015…") to strengthen the entity signal.', 'aeomatic' );
		}

		if ( $org_name !== '' && $signals->has_heading_keyword( array( $org_name, 'about us', 'about the company', 'our team' ) ) ) {
			$score += 15;
			$found[] = __( 'Organisation referenced in headings.', 'aeomatic' );
		} elseif ( $signals->has_heading_keyword( array( 'about us', 'about the company', 'our team' ) ) ) {
			$score += 10;
			$found[] = __( 'Organisation-context heading detected.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No organisation-context heading.', 'aeomatic' );
			$recommendations[] = __( 'On About/Team pages, use an H2 that names the organisation.', 'aeomatic' );
		}

		$detected = $score >= AEOMATIC_Entity_Scanner::detected_threshold();
		return new AEOMATIC_Entity_Detection_Result( 'organization', $detected, $score, $found, $missing, $recommendations );
	}
}
