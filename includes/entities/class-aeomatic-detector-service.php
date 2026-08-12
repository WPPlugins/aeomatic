<?php
/**
 * Detects Service entity coverage.
 *
 * Signals (weights sum to 100):
 *   - Schema type is Service                     (35)
 *   - Service-context headings                   (20)
 *   - Service verbs in body                      (15)
 *   - Pricing signal (currency + amount)         (15)
 *   - Provider/areaServed schema property        (15)
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Detector_Service implements AEOMATIC_Entity_Detector_Interface {

	public function detect( WP_Post $post, AEOMATIC_Entity_Signal_Collector $signals ): AEOMATIC_Entity_Detection_Result {
		$score          = 0;
		$found          = array();
		$missing        = array();
		$recommendations = array();

		if ( $signals->has_schema_type( 'Service' ) ) {
			$score += 35;
			$found[] = __( 'Schema type is Service.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No Service schema.', 'aeomatic' );
			$recommendations[] = __( 'Set schema type to Service for pages describing what you offer.', 'aeomatic' );
		}

		if ( $signals->has_heading_keyword( array( 'services', 'our service', 'what we do', 'offerings', 'packages' ) ) ) {
			$score += 20;
			$found[] = __( 'Service-context heading detected.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No service-context heading.', 'aeomatic' );
			$recommendations[] = __( 'Use an H2 like "Our Services" or "What We Offer" so AI engines can locate the service list.', 'aeomatic' );
		}

		if ( $signals->has_content_keyword( array( 'we offer', 'we provide', 'we deliver', 'we help', 'we specialize', 'our team offers' ), 1 ) ) {
			$score += 15;
			$found[] = __( 'Service-offering verbs in body.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No service-offering verbs in body.', 'aeomatic' );
			$recommendations[] = __( 'Use verbs like "we offer / we provide / we deliver" to explicitly signal a service.', 'aeomatic' );
		}

		if ( $this->has_pricing_signal( $signals->content_plain() ) ) {
			$score += 15;
			$found[] = __( 'Pricing signal detected in body.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No pricing signal in body.', 'aeomatic' );
			$recommendations[] = __( 'Include an anchor price ("From $299/month") — AI engines cite content with quantifiable claims.', 'aeomatic' );
		}

		if ( $signals->schema_override( 'provider' ) || $signals->schema_override( 'areaServed' ) ) {
			$score += 15;
			$found[] = __( 'Provider or areaServed schema property set.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No provider / areaServed schema property.', 'aeomatic' );
			$recommendations[] = $signals->is_block_editor()
				? __( 'Set provider (link to your Organization) and areaServed in the Schema Generator sidebar.', 'aeomatic' )
				: __( 'Set provider (link to your Organization) and areaServed on this page\'s Service schema — edit the post in the block editor to use the Schema Generator, or set them via the aeomatic_schema_overrides filter.', 'aeomatic' );
		}

		$detected = $score >= AEOMATIC_Entity_Scanner::detected_threshold();
		return new AEOMATIC_Entity_Detection_Result( 'service', $detected, $score, $found, $missing, $recommendations );
	}

	private function has_pricing_signal( string $text ): bool {
		return (bool) preg_match( '/(?:\$|£|€|USD|GBP|EUR)\s*\d[\d,]*(?:\.\d+)?|(?:from|starting at|only)\s*\$?\d+/i', $text );
	}
}
