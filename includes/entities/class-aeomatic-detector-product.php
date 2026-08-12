<?php
/**
 * Detects Product entity coverage.
 *
 * Signals (weights sum to 100):
 *   - Schema type is Product                     (30)
 *   - WooCommerce product detected               (20)
 *   - Price/offer schema property set            (15)
 *   - SKU or model number in body or schema      (15)
 *   - Product-context heading                    (20)
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Detector_Product implements AEOMATIC_Entity_Detector_Interface {

	public function detect( WP_Post $post, AEOMATIC_Entity_Signal_Collector $signals ): AEOMATIC_Entity_Detection_Result {
		$score          = 0;
		$found          = array();
		$missing        = array();
		$recommendations = array();

		if ( $signals->has_schema_type( 'Product' ) ) {
			$score += 30;
			$found[] = __( 'Schema type is Product.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No Product schema.', 'aeomatic' );
			$recommendations[] = __( 'Set schema type to Product for pages describing a physical or digital product.', 'aeomatic' );
		}

		if ( $signals->has_woocommerce_product() ) {
			$score += 20;
			$found[] = __( 'WooCommerce product detected.', 'aeomatic' );
		}

		if ( $signals->schema_override( 'offers' ) || $signals->schema_override( 'price' ) ) {
			$score += 15;
			$found[] = __( 'Price / offers schema property set.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No price / offers property.', 'aeomatic' );
			$recommendations[] = $signals->is_block_editor()
				? __( 'Fill the price or offers property in the Schema Generator sidebar.', 'aeomatic' )
				: __( 'Set the price or offers property on this page\'s Product schema — edit the post in the block editor to use the Schema Generator, or set them via the aeomatic_schema_overrides filter.', 'aeomatic' );
		}

		$has_sku = $signals->schema_override( 'sku' )
			|| $signals->schema_override( 'mpn' )
			|| (bool) preg_match( '/\b(SKU|MPN|Model)[\s:]*[A-Z0-9-]{3,}/i', $signals->content_plain() );
		if ( $has_sku ) {
			$score += 15;
			$found[] = __( 'SKU / MPN / model number detected.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No SKU / MPN / model number.', 'aeomatic' );
			$recommendations[] = __( 'Include SKU or model number — AI shopping engines and rich results rely on these identifiers.', 'aeomatic' );
		}

		if ( $signals->has_heading_keyword( array( 'features', 'specifications', 'specs', 'what\'s in the box', 'included', 'product details' ) ) ) {
			$score += 20;
			$found[] = __( 'Product-context heading detected.', 'aeomatic' );
		} else {
			$missing[]         = __( 'No product-context heading (features / specs / details).', 'aeomatic' );
			$recommendations[] = __( 'Use H2s like "Features" or "Specifications" — AI product answers pull from these.', 'aeomatic' );
		}

		$detected = $score >= AEOMATIC_Entity_Scanner::detected_threshold();
		return new AEOMATIC_Entity_Detection_Result( 'product', $detected, $score, $found, $missing, $recommendations );
	}
}
