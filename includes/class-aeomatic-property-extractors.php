<?php
/**
 * Property extractors.
 *
 * Reusable extractors that pull type-specific properties from a post:
 * ingredients, instructions, ratings, dates, addresses, prices, etc.
 *
 * Each extractor is independent and best-effort — returns null if it
 * can't find the data. The schema builders compose these to assemble
 * complete schema objects.
 *
 * Sources checked, in priority order:
 *  1. Explicit ACF fields (most reliable)
 *  2. Custom post meta with conventional keys
 *  3. Pattern matching in extracted content text
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Property_Extractors {

	/**
	 * Read a value from ACF or post meta. Tries multiple key variants.
	 *
	 * @param int      $post_id
	 * @param string[] $keys    Candidate keys (snake_case, kebab-case variants).
	 * @return mixed|null
	 */
	public static function meta_value( int $post_id, array $keys ) {
		foreach ( $keys as $k ) {
			// Try ACF first (returns rendered values).
			if ( function_exists( 'get_field' ) ) {
				$val = get_field( $k, $post_id );
				if ( ! empty( $val ) ) {
					return $val;
				}
			}
			// Fall back to raw post meta.
			$val = get_post_meta( $post_id, $k, true );
			if ( ! empty( $val ) ) {
				return $val;
			}
		}
		return null;
	}

	/* ----- Recipe extractors ----- */

	/**
	 * Extract a list of ingredient strings.
	 *
	 * @return string[]|null
	 */
	public static function ingredients( WP_Post $post, string $text, string $html ): ?array {
		// 1. ACF/meta — common keys.
		$val = self::meta_value( $post->ID, array( 'ingredients', 'recipe_ingredients', 'recipeIngredient' ) );
		if ( $val ) {
			return self::normalise_list( $val );
		}

		// 2. Heading "Ingredients" followed by a <ul>.
		if ( preg_match( '/<h[2-4][^>]*>\s*ingredients?\s*<\/h[2-4]>\s*<(ul|ol)[^>]*>(.*?)<\/\1>/is', $html, $m ) ) {
			$list_html = $m[2];
			preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $list_html, $items );
			if ( ! empty( $items[1] ) ) {
				$cleaned = array_map( static function ( $i ) {
					return trim( wp_strip_all_tags( $i ) );
				}, $items[1] );
				$cleaned = array_values( array_filter( $cleaned ) );
				if ( $cleaned ) {
					return $cleaned;
				}
			}
		}
		return null;
	}

	/**
	 * Extract step-by-step instructions.
	 *
	 * @return array<int,array{name:string,text:string}>|null
	 */
	public static function instructions( WP_Post $post, string $text, string $html ): ?array {
		// ACF / meta.
		$val = self::meta_value( $post->ID, array( 'instructions', 'recipe_instructions', 'directions', 'steps' ) );
		if ( $val ) {
			$items = self::normalise_list( $val );
			$out = array();
			foreach ( $items as $i => $step ) {
				// translators: %d: step number in a how-to or recipe.
				$aeomatic_step_name = sprintf( __( 'Step %d', 'aeomatic' ), $i + 1 );
				$out[] = array(
					'@type' => 'HowToStep',
					'name'  => $aeomatic_step_name,
					'text'  => $step,
				);
			}
			return $out;
		}

		// "Instructions" or "Directions" heading followed by ordered list.
		if ( preg_match( '/<h[2-4][^>]*>\s*(instructions?|directions?|steps?|method)\s*<\/h[2-4]>\s*<(ol|ul)[^>]*>(.*?)<\/\2>/is', $html, $m ) ) {
			preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $m[3], $items );
			if ( ! empty( $items[1] ) ) {
				$out = array();
				foreach ( $items[1] as $i => $step ) {
					$plain = trim( wp_strip_all_tags( $step ) );
					if ( $plain ) {
						// translators: %d: step number in a how-to or recipe.
						$aeomatic_step_name = sprintf( __( 'Step %d', 'aeomatic' ), $i + 1 );
						$out[] = array(
							'@type' => 'HowToStep',
							'name'  => $aeomatic_step_name,
							'text'  => $plain,
						);
					}
				}
				if ( $out ) {
					return $out;
				}
			}
		}

		// Fall back: H2/H3 headings starting with "Step" build the steps.
		preg_match_all( '/<h[2-3][^>]*>(.*?)<\/h[2-3]>/is', $html, $hm );
		$steps = array();
		foreach ( ( $hm[1] ?? array() ) as $h ) {
			$plain = trim( wp_strip_all_tags( $h ) );
			if ( preg_match( '/^\s*(step\s+\d+|[\d]+[\.\)])/i', $plain ) ) {
				$steps[] = array(
					'@type' => 'HowToStep',
					'name'  => $plain,
					'text'  => $plain,
				);
			}
		}
		return $steps ?: null;
	}

	/**
	 * Extract an ISO 8601 duration ("PT30M" etc.) from natural-language phrases.
	 *
	 * @return string|null
	 */
	public static function duration( string $text, string $label ): ?string {
		// Look for "{label} time: 30 minutes" or "{label}: 1 hour 30 minutes".
		$pattern = '/' . preg_quote( $label, '/' ) . '\s*(?:time)?\s*[:\-]?\s*((?:\d+\s*(?:hours?|h|hrs?|minutes?|mins?|m)\s*)+)/i';
		if ( preg_match( $pattern, $text, $m ) ) {
			return self::phrase_to_iso8601_duration( $m[1] );
		}
		return null;
	}

	private static function phrase_to_iso8601_duration( string $phrase ): ?string {
		$h = 0; $min = 0;
		if ( preg_match( '/(\d+)\s*(hours?|h|hrs?)/i', $phrase, $m1 ) ) {
			$h = (int) $m1[1];
		}
		if ( preg_match( '/(\d+)\s*(minutes?|mins?|m)/i', $phrase, $m2 ) ) {
			$min = (int) $m2[1];
		}
		if ( ! $h && ! $min ) {
			return null;
		}
		$out = 'PT';
		if ( $h ) $out .= $h . 'H';
		if ( $min ) $out .= $min . 'M';
		return $out;
	}

	/**
	 * Recipe yield ("Serves 4" / "Yield: 12 cookies").
	 */
	public static function recipe_yield( WP_Post $post, string $text ): ?string {
		$val = self::meta_value( $post->ID, array( 'yield', 'recipe_yield', 'servings' ) );
		if ( $val ) {
			return is_string( $val ) ? trim( $val ) : (string) $val;
		}
		if ( preg_match( '/(?:serves|servings|yield|makes)\s*[:\-]?\s*([\d]+(?:\s*\w+)?)/i', $text, $m ) ) {
			return trim( $m[1] );
		}
		return null;
	}

	/* ----- Review / rating extractors ----- */

	/**
	 * Extract a rating value as a float (0–5 scale normalised).
	 *
	 * @return array{value:float,best:int}|null
	 */
	public static function rating( WP_Post $post, string $text ): ?array {
		// ACF / meta.
		$val = self::meta_value( $post->ID, array( 'rating', 'review_rating', 'star_rating' ) );
		if ( is_numeric( $val ) ) {
			$f = (float) $val;
			if ( $f > 0 && $f <= 5 ) return array( 'value' => $f, 'best' => 5 );
			if ( $f > 5 && $f <= 10 ) return array( 'value' => $f, 'best' => 10 );
		}

		// Pattern: "4.5 out of 5", "4.8/5", "8/10".
		if ( preg_match( '/(\d(?:\.\d)?)\s*(?:out of|\/)\s*(5|10)\b/i', $text, $m ) ) {
			return array( 'value' => (float) $m[1], 'best' => (int) $m[2] );
		}
		// Pattern: "Rating: 4.5".
		if ( preg_match( '/(?:rating|score)\s*[:\-]\s*(\d(?:\.\d)?)/i', $text, $m ) ) {
			return array( 'value' => (float) $m[1], 'best' => 5 );
		}
		return null;
	}

	/**
	 * Pull pros and cons lists.
	 *
	 * @return array{pros:array<string>,cons:array<string>}|null
	 */
	public static function pros_and_cons( WP_Post $post, string $html ): ?array {
		$pros = self::list_after_heading( $html, '(pros|advantages|the good|what we liked)' );
		$cons = self::list_after_heading( $html, '(cons|disadvantages|the bad|what we didn\'t like|drawbacks)' );

		// ACF.
		$pros_meta = self::meta_value( $post->ID, array( 'pros', 'positives', 'advantages' ) );
		$cons_meta = self::meta_value( $post->ID, array( 'cons', 'negatives', 'disadvantages' ) );
		if ( $pros_meta ) {
			$pros = array_merge( (array) $pros, self::normalise_list( $pros_meta ) );
		}
		if ( $cons_meta ) {
			$cons = array_merge( (array) $cons, self::normalise_list( $cons_meta ) );
		}

		if ( empty( $pros ) && empty( $cons ) ) {
			return null;
		}
		return array(
			'pros' => array_values( array_filter( $pros ?: array() ) ),
			'cons' => array_values( array_filter( $cons ?: array() ) ),
		);
	}

	/* ----- Event extractors ----- */

	/**
	 * Extract event start date as ISO 8601.
	 */
	public static function event_start( WP_Post $post, string $text ): ?string {
		$val = self::meta_value( $post->ID, array( 'event_date', 'start_date', 'event_start_date', '_EventStartDate' ) );
		if ( $val ) {
			$ts = is_numeric( $val ) ? (int) $val : strtotime( (string) $val );
			if ( $ts ) {
				return gmdate( 'c', $ts );
			}
		}
		// Pattern: "Date: 2026-04-28" or "Apr 28, 2026 at 7pm".
		if ( preg_match( '/\b(\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2})?)\b/', $text, $m ) ) {
			$ts = strtotime( $m[1] );
			if ( $ts ) return gmdate( 'c', $ts );
		}
		return null;
	}

	public static function event_end( WP_Post $post ): ?string {
		$val = self::meta_value( $post->ID, array( 'event_end_date', 'end_date', '_EventEndDate' ) );
		if ( $val ) {
			$ts = is_numeric( $val ) ? (int) $val : strtotime( (string) $val );
			if ( $ts ) {
				return gmdate( 'c', $ts );
			}
		}
		return null;
	}

	public static function event_location( WP_Post $post ): ?array {
		$venue = self::meta_value( $post->ID, array( 'venue', 'event_venue', 'location_name', '_EventVenue' ) );
		$addr  = self::meta_value( $post->ID, array( 'address', 'event_address', 'location_address', '_EventAddress' ) );
		if ( ! $venue && ! $addr ) {
			return null;
		}
		$loc = array( '@type' => 'Place' );
		if ( $venue ) $loc['name'] = is_string( $venue ) ? $venue : (string) $venue;
		if ( $addr )  $loc['address'] = is_string( $addr ) ? $addr : (string) $addr;
		return $loc;
	}

	/* ----- Job posting extractors ----- */

	public static function job_salary( WP_Post $post, string $text ): ?array {
		$val = self::meta_value( $post->ID, array( 'salary', 'job_salary', 'compensation' ) );
		if ( $val ) {
			if ( is_array( $val ) && isset( $val['min'], $val['max'] ) ) {
				return array(
					'@type'    => 'MonetaryAmount',
					'currency' => $val['currency'] ?? 'USD',
					'value'    => array(
						'@type'    => 'QuantitativeValue',
						'minValue' => (float) $val['min'],
						'maxValue' => (float) $val['max'],
						'unitText' => $val['unit'] ?? 'YEAR',
					),
				);
			}
			if ( is_string( $val ) || is_numeric( $val ) ) {
				return array(
					'@type'    => 'MonetaryAmount',
					'currency' => 'USD',
					'value'    => (string) $val,
				);
			}
		}
		// Pattern: "$80,000 – $120,000 per year".
		if ( preg_match( '/(?:[\$€£₹]|usd|inr|eur|gbp)\s*([\d,]+)\s*[–\-to]+\s*(?:[\$€£₹]|usd|inr|eur|gbp)?\s*([\d,]+)\s*(?:per|\/)?\s*(year|month|hour|annum)?/i', $text, $m ) ) {
			$min = (float) str_replace( ',', '', $m[1] );
			$max = (float) str_replace( ',', '', $m[2] );
			$unit_raw = strtolower( $m[3] ?? 'year' );
			$unit = 'YEAR';
			if ( false !== strpos( $unit_raw, 'month' ) ) $unit = 'MONTH';
			elseif ( false !== strpos( $unit_raw, 'hour' ) ) $unit = 'HOUR';
			return array(
				'@type'    => 'MonetaryAmount',
				'currency' => 'USD',
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => $min,
					'maxValue' => $max,
					'unitText' => $unit,
				),
			);
		}
		return null;
	}

	public static function employment_type( WP_Post $post, string $text ): ?string {
		$val = self::meta_value( $post->ID, array( 'employment_type', 'job_type' ) );
		if ( $val && is_string( $val ) ) {
			return strtoupper( str_replace( array( ' ', '-' ), '_', $val ) );
		}
		if ( preg_match( '/\b(full[\s-]?time)\b/i', $text ) ) return 'FULL_TIME';
		if ( preg_match( '/\b(part[\s-]?time)\b/i', $text ) ) return 'PART_TIME';
		if ( preg_match( '/\bcontract(or)?\b/i', $text ) )    return 'CONTRACTOR';
		if ( preg_match( '/\binternship\b/i', $text ) )       return 'INTERN';
		if ( preg_match( '/\btemporary\b/i', $text ) )        return 'TEMPORARY';
		return null;
	}

	/* ----- Local business extractors ----- */

	public static function address( WP_Post $post, string $text ): ?array {
		$street  = self::meta_value( $post->ID, array( 'street_address', 'address_line_1', 'address' ) );
		$city    = self::meta_value( $post->ID, array( 'city', 'locality' ) );
		$region  = self::meta_value( $post->ID, array( 'state', 'region' ) );
		$postal  = self::meta_value( $post->ID, array( 'postcode', 'zip', 'postal_code' ) );
		$country = self::meta_value( $post->ID, array( 'country' ) );

		if ( $street || $city ) {
			$out = array( '@type' => 'PostalAddress' );
			if ( $street )  $out['streetAddress']   = (string) ( is_array( $street ) ? implode( ', ', $street ) : $street );
			if ( $city )    $out['addressLocality'] = (string) $city;
			if ( $region )  $out['addressRegion']   = (string) $region;
			if ( $postal )  $out['postalCode']      = (string) $postal;
			if ( $country ) $out['addressCountry']  = (string) $country;
			return $out;
		}

		// Best-effort pattern match.
		if ( preg_match( '/(\d+\s+[A-Z][A-Za-z\s]+(?:Street|St\.?|Road|Rd\.?|Avenue|Ave\.?|Lane|Ln\.?|Drive|Dr\.?))/i', $text, $m ) ) {
			return array(
				'@type'         => 'PostalAddress',
				'streetAddress' => trim( $m[1] ),
			);
		}
		return null;
	}

	public static function telephone( WP_Post $post, string $text ): ?string {
		$val = self::meta_value( $post->ID, array( 'phone', 'telephone', 'contact_phone' ) );
		if ( $val && is_string( $val ) ) return $val;
		if ( preg_match( '/(\+\d{1,3}[\s-]?\d{2,4}[\s-]?\d{3,4}[\s-]?\d{3,4})/', $text, $m ) ) {
			return trim( $m[1] );
		}
		if ( preg_match( '/(\d{3}[\s-]\d{3}[\s-]\d{4})/', $text, $m ) ) {
			return trim( $m[1] );
		}
		return null;
	}

	public static function opening_hours( WP_Post $post, string $text ): ?array {
		$val = self::meta_value( $post->ID, array( 'opening_hours', 'hours' ) );
		if ( is_array( $val ) ) {
			return $val;
		}
		if ( is_string( $val ) ) {
			return array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $val ) ) ?: array() );
		}
		return null;
	}

	/* ----- Course extractors ----- */

	public static function course_provider( WP_Post $post ): ?array {
		$provider_name = self::meta_value( $post->ID, array( 'provider', 'course_provider', 'instructor' ) );
		$name = is_string( $provider_name ) ? $provider_name : ( is_array( $provider_name ) ? ( $provider_name['name'] ?? '' ) : '' );
		if ( ! $name ) {
			$name = get_bloginfo( 'name' );
		}
		return array(
			'@type' => 'Organization',
			'name'  => $name,
			'url'   => home_url( '/' ),
		);
	}

	/* ----- Generic helpers ----- */

	/**
	 * Normalise a value into an array of trimmed strings.
	 *
	 * @param mixed $val
	 * @return string[]
	 */
	private static function normalise_list( $val ): array {
		if ( is_array( $val ) ) {
			$out = array();
			foreach ( $val as $row ) {
				if ( is_string( $row ) ) {
					$s = trim( wp_strip_all_tags( $row ) );
					if ( $s ) $out[] = $s;
				} elseif ( is_array( $row ) ) {
					// ACF repeater: pick the first text-ish field.
					foreach ( $row as $sub ) {
						if ( is_string( $sub ) && '' !== trim( $sub ) ) {
							$out[] = trim( wp_strip_all_tags( $sub ) );
							break;
						}
					}
				}
			}
			return $out;
		}
		if ( is_string( $val ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $val );
			$out   = array();
			foreach ( $lines as $line ) {
				$line = trim( wp_strip_all_tags( $line ) );
				if ( $line ) $out[] = $line;
			}
			return $out;
		}
		return array();
	}

	/**
	 * Find a heading matching $heading_pattern, then return the items
	 * of the first <ul> or <ol> that follows it.
	 *
	 * @return string[]|null
	 */
	private static function list_after_heading( string $html, string $heading_pattern ): ?array {
		$re = '/<h[2-4][^>]*>\s*' . $heading_pattern . '\s*<\/h[2-4]>\s*<(ul|ol)[^>]*>(.*?)<\/\2>/is';
		if ( preg_match( $re, $html, $m ) ) {
			$inner = $m[3] ?? $m[2];
			preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $inner, $items );
			if ( ! empty( $items[1] ) ) {
				$out = array_map( static function ( $i ) {
					return trim( wp_strip_all_tags( $i ) );
				}, $items[1] );
				return array_values( array_filter( $out ) );
			}
		}
		return null;
	}
}
