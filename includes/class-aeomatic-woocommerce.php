<?php
/**
 * WooCommerce integration.
 *
 * Emits Product JSON-LD on product pages with price, availability,
 * SKU, brand, ratings, and review aggregate.
 *
 * Activates only when WooCommerce is active.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_WooCommerce {

	public function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
	}

	/**
	 * Build Product schema for a WC_Product.
	 *
	 * Returns null if the product is not retrievable.
	 */
	public static function product_schema( WP_Post $post ): ?array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return null;
		}

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'@id'      => get_permalink( $post ) . '#product',
			'name'     => wp_strip_all_tags( $product->get_name() ),
			'url'      => get_permalink( $post ),
		);

		$desc = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
		if ( $desc ) {
			$schema['description'] = self::trim_text( $desc, 2500 );
		}

		$sku = $product->get_sku();
		if ( $sku ) {
			$schema['sku'] = $sku;
		}

		// Image.
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$src = wp_get_attachment_image_src( $image_id, 'full' );
			if ( $src ) {
				$schema['image'] = $src[0];
			}
		}

		// Brand — try product attribute "brand", fall back to site name.
		$brand = $product->get_attribute( 'brand' ) ?: $product->get_attribute( 'pa_brand' );
		if ( $brand ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		// Categories.
		$cats = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$schema['category'] = implode( ' > ', $cats );
		}

		// Offer / pricing.
		$offer = self::build_offer( $product, $currency );
		if ( $offer ) {
			$schema['offers'] = $offer;
		}

		// Reviews / rating aggregate.
		$rating_count = (int) $product->get_rating_count();
		$avg_rating   = (float) $product->get_average_rating();
		if ( $rating_count > 0 && $avg_rating > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $avg_rating, 2 ),
				'reviewCount' => $rating_count,
				'bestRating'  => 5,
				'worstRating' => 1,
			);
		}

		return $schema;
	}

	/**
	 * Build the Offer or AggregateOffer block.
	 */
	private static function build_offer( $product, string $currency ): ?array {
		// Variable products → AggregateOffer.
		if ( $product->is_type( 'variable' ) ) {
			$min = $product->get_variation_price( 'min', true );
			$max = $product->get_variation_price( 'max', true );
			if ( ! is_numeric( $min ) || ! is_numeric( $max ) ) {
				return null;
			}
			return array(
				'@type'         => 'AggregateOffer',
				'priceCurrency' => $currency,
				'lowPrice'      => self::format_price( $min ),
				'highPrice'     => self::format_price( $max ),
				'offerCount'    => count( $product->get_children() ),
				'availability'  => $product->is_in_stock()
					? 'https://schema.org/InStock'
					: 'https://schema.org/OutOfStock',
				'url'           => get_permalink( $product->get_id() ),
			);
		}

		$price = $product->get_price();
		if ( ! is_numeric( $price ) ) {
			return null;
		}

		$offer = array(
			'@type'         => 'Offer',
			'priceCurrency' => $currency,
			'price'         => self::format_price( $price ),
			'availability'  => $product->is_in_stock()
				? 'https://schema.org/InStock'
				: 'https://schema.org/OutOfStock',
			'url'           => get_permalink( $product->get_id() ),
		);

		$sale_to = $product->get_date_on_sale_to();
		if ( $sale_to ) {
			$offer['priceValidUntil'] = $sale_to->date( 'Y-m-d' );
		}

		return $offer;
	}

	private static function format_price( $value ): string {
		// WooCommerce can return a string with comma decimal separators.
		$num = (float) wc_format_decimal( $value, wc_get_price_decimals() );
		return number_format( $num, wc_get_price_decimals(), '.', '' );
	}

	private static function trim_text( string $text, int $limit ): string {
		if ( strlen( $text ) <= $limit ) {
			return $text;
		}
		return rtrim( substr( $text, 0, $limit ), " \t\n\r," ) . '…';
	}
}
