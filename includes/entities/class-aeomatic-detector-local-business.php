<?php
/**
 * Detects LocalBusiness entity coverage.
 *
 * Signals (weights sum to 100):
 *   - Schema type is LocalBusiness or subtype    (35)
 *   - Organization address in settings           (20)
 *   - NAP pattern in body (address + phone)      (20)
 *   - Geo coordinates in schema                  (15)
 *   - Opening hours in schema                    (10)
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Detector_Local_Business implements AEOMATIC_Entity_Detector_Interface {

	public function detect( WP_Post $post, AEOMATIC_Entity_Signal_Collector $signals ): AEOMATIC_Entity_Detection_Result {
		$score          = 0;
		$found          = array();
		$missing        = array();
		$recommendations = array();

		if ( $signals->schema_type_is_subtype_of( 'LocalBusiness' ) ) {
			$score += 35;
			$found[] = __( 'Schema type is LocalBusiness (or subtype).', 'aeomatic' );
		} else {
			$missing[]         = __( 'No LocalBusiness schema.', 'aeomatic' );
			$recommendations[] = __( 'For brick-and-mortar or service-area businesses, use LocalBusiness (or a subtype like Restaurant, Store).', 'aeomatic' );
		}

		$has_address_setting = $signals->organization_setting( 'street' ) !== ''
			|| $signals->organization_setting( 'address' ) !== ''
			|| $signals->organization_setting( 'city' ) !== '';
		// The Schema Generator sidebar exposes address on the LocalBusiness
		// schema itself — accept either source so users who configure per-page
		// aren't punished for the Settings UI not having address fields yet.
		$has_address_schema = (bool) $signals->schema_override( 'address' )
			|| (bool) $signals->schema_override( 'streetAddress' )
			|| (bool) $signals->schema_override( 'addressLocality' );
		if ( $has_address_setting || $has_address_schema ) {
			$score += 20;
			$found[] = $has_address_schema && ! $has_address_setting
				? __( 'Address set on the LocalBusiness schema for this page.', 'aeomatic' )
				: __( 'Organisation address configured in settings.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No organisation address in settings or on the page schema.', 'aeomatic' );
			$recommendations[] = $signals->is_block_editor()
				? __( 'Set the address on this page\'s LocalBusiness schema (Schema Generator sidebar), or add it site-wide in AEOmatic → Settings.', 'aeomatic' )
				: __( 'Add the business address to this page\'s LocalBusiness schema — edit the post in the block editor to use the Schema Generator, or use the aeomatic_schema_overrides filter. Alternatively add it site-wide in AEOmatic → Settings.', 'aeomatic' );
		}

		if ( $signals->has_nap_pattern() ) {
			$score += 20;
			$found[] = __( 'NAP pattern (address + phone) detected in body.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No NAP pattern (address + phone) in body.', 'aeomatic' );
			$recommendations[] = __( 'Publish Name / Address / Phone consistently on location pages — AI local answers rely on this.', 'aeomatic' );
		}

		$geo = $signals->schema_override( 'geo' );
		if ( $geo ) {
			$score += 15;
			$found[] = __( 'Geo coordinates set in schema.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No geo coordinates in schema.', 'aeomatic' );
			$recommendations[] = __( 'Add latitude/longitude to the schema so map-aware AI engines can position the business.', 'aeomatic' );
		}

		if ( $signals->schema_override( 'openingHours' ) || $signals->schema_override( 'openingHoursSpecification' ) ) {
			$score += 10;
			$found[] = __( 'Opening hours in schema.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No opening hours in schema.', 'aeomatic' );
			$recommendations[] = $signals->is_block_editor()
				? __( 'Set openingHours in the Schema Generator so AI answers can quote your hours.', 'aeomatic' )
				: __( 'Set openingHours on this page\'s LocalBusiness schema — edit the post in the block editor to use the Schema Generator, or set it via the aeomatic_schema_overrides filter.', 'aeomatic' );
		}

		$detected = $score >= AEOMATIC_Entity_Scanner::detected_threshold();
		return new AEOMATIC_Entity_Detection_Result( 'local_business', $detected, $score, $found, $missing, $recommendations );
	}
}
