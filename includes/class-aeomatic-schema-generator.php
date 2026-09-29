<?php
/**
 * Schema generator.
 *
 * Builds the final JSON-LD output for a post by merging:
 *   1. Auto-extracted values (from content, ACF, WooCommerce etc.)
 *   2. User overrides saved in _aeomatic_schema_overrides post meta
 *
 * User overrides always win over auto-extraction. This is the Webflow
 * pattern: auto-populate every field, let the user edit any field.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Schema_Generator {

	const META_OVERRIDES = '_aeomatic_schema_overrides';

	/**
	 * Build the complete JSON-LD array for a post.
	 *
	 * When $in_flight_overrides is null (default), overrides are read from the
	 * saved post-meta record — this is the front-end render path. When it is
	 * an array, those values are used INSTEAD of the saved ones — this is the
	 * editor live-preview path, so the panel reflects unsaved edits.
	 *
	 * @param WP_Post           $post
	 * @param string            $type                 Schema @type chosen by analyzer or user.
	 * @param array             $settings             Plugin schema settings.
	 * @param array<string,mixed>|null $in_flight_overrides  Preview-only override map.
	 * @return array  JSON-LD object ready for wp_json_encode().
	 */
	public static function generate( WP_Post $post, string $type, array $settings, ?array $in_flight_overrides = null ): array {
		// 1. Get auto-extracted defaults.
		$defaults = AEOMATIC_Schema_Fields::extract_defaults( $post, $type, $settings );

		// 2. Choose the override source: in-flight (preview) or saved (front-end).
		if ( null !== $in_flight_overrides ) {
			$overrides = $in_flight_overrides;
		} else {
			$overrides = self::read_overrides( $post->ID, $type );
		}

		// Key-level merge: override only keys the user has explicitly set.
		$merged = array_merge( $defaults, array_filter( $overrides, static function ( $v ) {
			return $v !== null && $v !== '';
		} ) );

		// 3. Assemble into a schema.org JSON-LD object.
		return self::assemble( $post, $type, $settings, $merged );
	}

	/**
	 * Editor live preview: build JSON-LD using unsaved in-flight values.
	 *
	 * Thin wrapper around generate() with a clearer name so the preview REST
	 * handler expresses its intent without a magic null argument.
	 */
	public static function preview( WP_Post $post, string $type, array $settings, array $in_flight_values ): array {
		return self::generate( $post, $type, $settings, $in_flight_values );
	}

	/**
	 * Save user-supplied field values as overrides for a post.
	 *
	 * $fields holds only the keys the user edited. When $type is given, the
	 * saved overrides for that type's fields are replaced wholesale (so a key
	 * the user reset or cleared is dropped) while overrides for fields of other
	 * types are kept, so switching type and back does not lose earlier edits.
	 * An empty value means "use the auto-detected value" and is not stored.
	 *
	 * @param int         $post_id
	 * @param array       $fields   Flat key→value map from the editor form.
	 * @param string|null $type     Schema @type the fields belong to.
	 * @return bool
	 */
	public static function save_overrides( int $post_id, array $fields, ?string $type = null ): bool {
		$sanitised = array_filter( self::sanitise_overrides( $fields ), static function ( $v ) {
			return $v !== null && $v !== '' && $v !== array();
		} );

		$kept = array();
		if ( null !== $type ) {
			$kept = array_diff_key( self::read_overrides( $post_id, $type ), array_flip( self::field_keys( $type ) ) );
		}

		$ok = update_post_meta( $post_id, self::META_OVERRIDES, wp_json_encode( array_merge( $kept, $sanitised ) ) );

		// Bust all schema caches for this post so next render uses new values.
		AEOMATIC_Schema_Builder::invalidate( $post_id );
		AEOMATIC_Schema_Analyzer::invalidate_cache( $post_id );

		return (bool) $ok;
	}

	/**
	 * Get the merged field values (defaults + overrides) for the editor.
	 * This is what the editor form is pre-populated with.
	 *
	 * @param WP_Post $post
	 * @param string  $type
	 * @param array   $settings
	 * @return array<string,mixed>
	 */
	public static function get_editor_values( WP_Post $post, string $type, array $settings ): array {
		$defaults = AEOMATIC_Schema_Fields::extract_defaults( $post, $type, $settings );
		// Only merge override keys that exist in this type's field set, and
		// never let an empty override blank out an auto-detected value —
		// generate() ignores empty overrides, so the editor must too.
		$relevant = array_filter(
			array_intersect_key( self::read_overrides( $post->ID, $type ), array_flip( self::field_keys( $type ) ) ),
			static fn( $v ) => $v !== null && $v !== '' && $v !== array()
		);
		return array_merge( $defaults, $relevant );
	}

	/**
	 * Which field values are currently overridden (user-edited) vs auto-extracted?
	 * Used to show the "Edited" badge in the UI.
	 *
	 * @param int         $post_id
	 * @param string|null $type  When given, only keys belonging to this type's fields.
	 * @return string[]  Keys that have been user-overridden.
	 */
	public static function get_overridden_keys( int $post_id, ?string $type = null ): array {
		$overrides = array_filter(
			self::read_overrides( $post_id, $type ),
			static fn( $v ) => $v !== null && $v !== '' && $v !== array()
		);
		if ( null !== $type ) {
			$overrides = array_intersect_key( $overrides, array_flip( self::field_keys( $type ) ) );
		}
		return array_keys( $overrides );
	}

	/**
	 * Read the saved override map for a post.
	 *
	 * Before 1.2.3 override keys went through sanitize_key(), which lowercased
	 * them (aggregateRating_value was stored as aggregaterating_value), so the
	 * editor never found them again. When $type is given, such legacy keys are
	 * mapped back onto that type's field keys so values saved by older
	 * versions reappear instead of reading as blank.
	 *
	 * @return array<string,mixed>
	 */
	private static function read_overrides( int $post_id, ?string $type = null ): array {
		$raw       = get_post_meta( $post_id, self::META_OVERRIDES, true );
		$overrides = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $overrides ) ) {
			return array();
		}
		if ( null === $type ) {
			return $overrides;
		}

		$canonical = array();
		foreach ( self::field_keys( $type ) as $field_key ) {
			$canonical[ strtolower( $field_key ) ] = $field_key;
		}
		foreach ( $overrides as $key => $value ) {
			$key   = (string) $key;
			$fixed = $canonical[ strtolower( $key ) ] ?? null;
			// Rename only when the correctly-cased key is not already present,
			// so a newer save always wins over a stale legacy one.
			if ( null !== $fixed && $fixed !== $key ) {
				unset( $overrides[ $key ] );
				if ( ! array_key_exists( $fixed, $overrides ) ) {
					$overrides[ $fixed ] = $value;
				}
			}
		}
		return $overrides;
	}

	/** @return string[] Field keys defined for a schema @type. */
	private static function field_keys( string $type ): array {
		return array_column( AEOMATIC_Schema_Fields::for_type( $type ), 'key' );
	}

	/**
	 * Reset one or all overrides for a post.
	 *
	 * @param int         $post_id
	 * @param string|null $key  Pass a key to reset one field, null to reset all.
	 */
	public static function reset_overrides( int $post_id, ?string $key = null ): void {
		if ( null === $key ) {
			delete_post_meta( $post_id, self::META_OVERRIDES );
		} else {
			$overrides = self::read_overrides( $post_id );
			// Case-insensitive, so a legacy lowercased copy of the key goes too.
			foreach ( array_keys( $overrides ) as $saved_key ) {
				if ( 0 === strcasecmp( (string) $saved_key, $key ) ) {
					unset( $overrides[ $saved_key ] );
				}
			}
			update_post_meta( $post_id, self::META_OVERRIDES, wp_json_encode( $overrides ) );
		}
		AEOMATIC_Schema_Builder::invalidate( $post_id );
		AEOMATIC_Schema_Analyzer::invalidate_cache( $post_id );
	}

	// ── Private: assemble flat merged values into a JSON-LD tree ──────────

	private static function assemble( WP_Post $post, string $type, array $settings, array $v ): array {
		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => $type,
			'@id'           => get_permalink( $post ) . '#' . strtolower( $type ),
			'name'          => self::val( $v, 'name', get_the_title( $post ) ),
			'url'           => self::val( $v, 'url', get_permalink( $post ) ),
			'datePublished' => self::val( $v, 'datePublished', get_the_date( 'c', $post ) ),
			'dateModified'  => self::val( $v, 'dateModified', get_the_modified_date( 'c', $post ) ),
		);

		$desc = self::val( $v, 'description' );
		if ( $desc ) {
			$schema['description'] = $desc;
		}

		$image = self::val( $v, 'image' );
		if ( $image ) {
			$schema['image'] = $image;
		}

		// Author.
		$author_name = self::val( $v, 'author_name' );
		$author_url  = self::val( $v, 'author_url' );
		if ( $author_name ) {
			$author = array( '@type' => 'Person', 'name' => $author_name );
			if ( $author_url ) {
				$author['url'] = $author_url;
			}
			$schema['author'] = $author;
		}

		// Publisher.
		$pub_name = self::val( $v, 'publisher_name', get_bloginfo( 'name' ) );
		$schema['publisher'] = array( '@type' => 'Organization', 'name' => $pub_name );
		$pub_logo = self::val( $v, 'publisher_logo' );
		if ( $pub_logo ) {
			$schema['publisher']['logo'] = array( '@type' => 'ImageObject', 'url' => $pub_logo );
		}

		// Type-specific assembly.
		switch ( $type ) {

			case 'Article':
			case 'BlogPosting':
			case 'NewsArticle':
			case 'TechArticle':
				$schema['headline']      = self::val( $v, 'headline', self::val( $v, 'name' ) );
				$schema['mainEntityOfPage'] = array( '@type' => 'WebPage', '@id' => self::val( $v, 'url' ) );
				if ( self::val( $v, 'articleSection' ) )  $schema['articleSection'] = self::val( $v, 'articleSection' );
				if ( self::val( $v, 'wordCount' ) )        $schema['wordCount']      = (int) self::val( $v, 'wordCount' );
				break;

			case 'Recipe':
				if ( self::val( $v, 'prepTime' ) )           $schema['prepTime']          = self::val( $v, 'prepTime' );
				if ( self::val( $v, 'cookTime' ) )           $schema['cookTime']          = self::val( $v, 'cookTime' );
				if ( self::val( $v, 'totalTime' ) )          $schema['totalTime']         = self::val( $v, 'totalTime' );
				if ( self::val( $v, 'recipeYield' ) )        $schema['recipeYield']       = self::val( $v, 'recipeYield' );
				if ( self::val( $v, 'recipeCategory' ) )     $schema['recipeCategory']    = self::val( $v, 'recipeCategory' );
				if ( self::val( $v, 'recipeCuisine' ) )      $schema['recipeCuisine']     = self::val( $v, 'recipeCuisine' );
				if ( self::val( $v, 'keywords' ) )           $schema['keywords']          = self::val( $v, 'keywords' );
				$ingredients = self::as_list( $v, 'recipeIngredient' );
				if ( $ingredients )                          $schema['recipeIngredient']  = $ingredients;
				$steps = self::as_list( $v, 'recipeInstructions' );
				if ( $steps ) {
					$schema['recipeInstructions'] = array_map( static fn( $s, $i ) => array(
						'@type' => 'HowToStep', 'position' => $i + 1, 'text' => $s,
					), $steps, array_keys( $steps ) );
				}
				$calories = self::val( $v, 'nutrition_calories' );
				if ( $calories ) {
					$schema['nutrition'] = array( '@type' => 'NutritionInformation', 'calories' => $calories );
				}
				break;

			case 'HowTo':
				if ( self::val( $v, 'totalTime' ) )    $schema['totalTime'] = self::val( $v, 'totalTime' );
				if ( self::val( $v, 'estimatedCost' ) ) $schema['estimatedCost'] = self::val( $v, 'estimatedCost' );
				$steps = self::as_list( $v, 'step' );
				if ( $steps ) {
					$schema['step'] = array_map( static fn( $s, $i ) => array(
						'@type' => 'HowToStep', 'position' => $i + 1, 'name' => $s, 'text' => $s,
					), $steps, array_keys( $steps ) );
				}
				$tools = self::as_list( $v, 'tool' );
				if ( $tools ) {
					$schema['tool'] = array_map( static fn( $t ) => array( '@type' => 'HowToTool', 'name' => $t ), $tools );
				}
				$supplies = self::as_list( $v, 'supply' );
				if ( $supplies ) {
					$schema['supply'] = array_map( static fn( $s ) => array( '@type' => 'HowToSupply', 'name' => $s ), $supplies );
				}
				break;

			case 'FAQPage':
				$faq_pairs = self::val( $v, 'faq_pairs', array() );
				if ( is_array( $faq_pairs ) && ! empty( $faq_pairs ) ) {
					$schema['mainEntity'] = array_map( static function ( $pair ) {
						$q = is_array( $pair ) ? ( $pair['q'] ?? '' ) : $pair;
						$a = is_array( $pair ) ? ( $pair['a'] ?? '' ) : '';
						return array(
							'@type'          => 'Question',
							'name'           => wp_strip_all_tags( $q ),
							'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $a ) ),
						);
					}, $faq_pairs );
				}
				break;

			case 'Review':
				$item_name = self::val( $v, 'itemReviewed_name' );
				$item_type = self::val( $v, 'itemReviewed_type', 'Product' );
				if ( $item_name ) {
					$schema['itemReviewed'] = array( '@type' => $item_type, 'name' => $item_name );
				}
				$rating_val = self::val( $v, 'reviewRating_value' );
				if ( $rating_val !== '' && $rating_val !== null ) {
					$schema['reviewRating'] = array(
						'@type'       => 'Rating',
						'ratingValue' => (float) $rating_val,
						'bestRating'  => (int) self::val( $v, 'reviewRating_best', 5 ),
						'worstRating' => 1,
					);
				}
				$body = self::val( $v, 'reviewBody' );
				if ( $body ) {
					$schema['reviewBody'] = $body;
				}
				break;

			case 'Product':
				$brand = self::val( $v, 'brand_name' );
				if ( $brand ) {
					$schema['brand'] = array( '@type' => 'Brand', 'name' => $brand );
				}
				$sku = self::val( $v, 'sku' );
				if ( $sku ) {
					$schema['sku'] = $sku;
				}
				$price = self::val( $v, 'offer_price' );
				if ( $price !== '' && $price !== null ) {
					$schema['offers'] = array(
						'@type'         => 'Offer',
						'price'         => (string) $price,
						'priceCurrency' => self::val( $v, 'offer_currency', 'USD' ),
						'availability'  => 'https://schema.org/' . self::val( $v, 'offer_availability', 'InStock' ),
					);
				}
				$rating_val = self::val( $v, 'aggregateRating_value' );
				$rating_cnt = self::val( $v, 'aggregateRating_count' );
				if ( $rating_val && $rating_cnt ) {
					$schema['aggregateRating'] = array(
						'@type'       => 'AggregateRating',
						'ratingValue' => (float) $rating_val,
						'reviewCount' => (int) $rating_cnt,
						'bestRating'  => 5,
						'worstRating' => 1,
					);
				}
				break;

			case 'Event':
				$start = self::val( $v, 'startDate' );
				if ( $start ) {
					$schema['startDate'] = $start;
				}
				$end = self::val( $v, 'endDate' );
				if ( $end ) {
					$schema['endDate'] = $end;
				}
				$loc_name = self::val( $v, 'location_name' );
				$loc_addr = self::val( $v, 'location_address' );
				if ( $loc_name || $loc_addr ) {
					$loc = array( '@type' => 'Place' );
					if ( $loc_name ) $loc['name'] = $loc_name;
					if ( $loc_addr ) $loc['address'] = $loc_addr;
					$schema['location'] = $loc;
				}
				$org_name = self::val( $v, 'organizer_name' );
				if ( $org_name ) {
					$schema['organizer'] = array( '@type' => 'Organization', 'name' => $org_name );
				}
				$mode = self::val( $v, 'eventAttendanceMode' );
				if ( $mode ) {
					$schema['eventAttendanceMode'] = 'https://schema.org/' . $mode;
				}
				$status = self::val( $v, 'eventStatus' );
				if ( $status ) {
					$schema['eventStatus'] = 'https://schema.org/' . $status;
				}
				$ticket_price = self::val( $v, 'offer_price' );
				if ( $ticket_price !== '' && $ticket_price !== null ) {
					$schema['offers'] = array(
						'@type'         => 'Offer',
						'price'         => (string) $ticket_price,
						'priceCurrency' => self::val( $v, 'offer_currency', 'USD' ),
					);
				}
				break;

			case 'JobPosting':
				$schema['title']      = self::val( $v, 'title', self::val( $v, 'name' ) );
				$schema['datePosted'] = self::val( $v, 'datePosted', get_the_date( 'c', $post ) );
				$org_name = self::val( $v, 'hiringOrg_name', get_bloginfo( 'name' ) );
				$schema['hiringOrganization'] = array( '@type' => 'Organization', 'name' => $org_name );
				$org_url = self::val( $v, 'hiringOrg_url' );
				if ( $org_url ) {
					$schema['hiringOrganization']['url'] = $org_url;
				}
				$emp_type = self::val( $v, 'employmentType' );
				if ( $emp_type ) {
					$schema['employmentType'] = $emp_type;
				}
				$salary_min = self::val( $v, 'salary_min' );
				if ( $salary_min !== '' && $salary_min !== null ) {
					$schema['baseSalary'] = array(
						'@type'    => 'MonetaryAmount',
						'currency' => self::val( $v, 'salary_currency', 'USD' ),
						'value'    => array(
							'@type'    => 'QuantitativeValue',
							'minValue' => (float) $salary_min,
							'maxValue' => (float) self::val( $v, 'salary_max', $salary_min ),
							'unitText' => self::val( $v, 'salary_unit', 'YEAR' ),
						),
					);
				}
				$job_location = self::val( $v, 'jobLocation_address' );
				if ( $job_location ) {
					$schema['jobLocation'] = array( '@type' => 'Place', 'address' => $job_location );
				}
				$valid_through = self::val( $v, 'validThrough' );
				if ( $valid_through ) {
					$schema['validThrough'] = $valid_through;
				}
				break;

			case 'Course':
				$provider_name = self::val( $v, 'provider_name', get_bloginfo( 'name' ) );
				$schema['provider'] = array( '@type' => 'Organization', 'name' => $provider_name );
				$prov_url = self::val( $v, 'provider_url', home_url( '/' ) );
				if ( $prov_url ) {
					$schema['provider']['url'] = $prov_url;
				}
				$course_price = self::val( $v, 'offer_price' );
				if ( $course_price !== '' && $course_price !== null ) {
					$schema['offers'] = array(
						'@type'         => 'Offer',
						'price'         => (string) $course_price,
						'priceCurrency' => self::val( $v, 'offer_currency', 'USD' ),
						'category'      => self::val( $v, 'offer_category', 'paid' ),
					);
				}
				$course_code = self::val( $v, 'courseCode' );
				if ( $course_code ) {
					$schema['courseCode'] = $course_code;
				}
				$edu_level = self::val( $v, 'educationalLevel' );
				if ( $edu_level ) {
					$schema['educationalLevel'] = $edu_level;
				}
				break;

			case 'LocalBusiness':
				$street  = self::val( $v, 'address_street' );
				$city    = self::val( $v, 'address_city' );
				$region  = self::val( $v, 'address_region' );
				$postal  = self::val( $v, 'address_postal' );
				$country = self::val( $v, 'address_country' );
				if ( $street || $city ) {
					$addr = array( '@type' => 'PostalAddress' );
					if ( $street )  $addr['streetAddress']   = $street;
					if ( $city )    $addr['addressLocality'] = $city;
					if ( $region )  $addr['addressRegion']   = $region;
					if ( $postal )  $addr['postalCode']      = $postal;
					if ( $country ) $addr['addressCountry']  = $country;
					$schema['address'] = $addr;
				}
				$phone = self::val( $v, 'telephone' );
				if ( $phone ) {
					$schema['telephone'] = $phone;
				}
				$hours = self::as_list( $v, 'openingHours' );
				if ( $hours ) {
					$schema['openingHours'] = $hours;
				}
				$price_range = self::val( $v, 'priceRange' );
				if ( $price_range ) {
					$schema['priceRange'] = $price_range;
				}
				$rating_val = self::val( $v, 'aggregateRating_value' );
				$rating_cnt = self::val( $v, 'aggregateRating_count' );
				// Both are required — Google rejects an AggregateRating with no
				// count, and inventing a count of 1 would misreport the reviews.
				if ( $rating_val && $rating_cnt ) {
					$schema['aggregateRating'] = array(
						'@type'       => 'AggregateRating',
						'ratingValue' => (float) $rating_val,
						'reviewCount' => (int) $rating_cnt,
						'bestRating'  => 5,
						'worstRating' => 1,
					);
				}
				break;

			case 'Service':
				$service_type = self::val( $v, 'serviceType' );
				if ( $service_type ) {
					$schema['serviceType'] = $service_type;
				}
				$prov_name = self::val( $v, 'provider_name', get_bloginfo( 'name' ) );
				$schema['provider'] = array( '@type' => 'Organization', 'name' => $prov_name );
				$area = self::val( $v, 'areaServed' );
				if ( $area ) {
					$schema['areaServed'] = $area;
				}
				$svc_price = self::val( $v, 'offer_price' );
				if ( $svc_price ) {
					$schema['offers'] = array(
						'@type'         => 'Offer',
						'price'         => (string) $svc_price,
						'priceCurrency' => self::val( $v, 'offer_currency', 'USD' ),
					);
				}
				break;

			case 'Book':
				$isbn = self::val( $v, 'isbn' );
				if ( $isbn ) {
					$schema['isbn'] = $isbn;
				}
				$pages = self::val( $v, 'numberOfPages' );
				if ( $pages ) {
					$schema['numberOfPages'] = (int) $pages;
				}
				$pub_name_book = self::val( $v, 'publisher_name' );
				if ( $pub_name_book ) {
					$schema['publisher'] = array( '@type' => 'Organization', 'name' => $pub_name_book );
				}
				$fmt = self::val( $v, 'bookFormat' );
				if ( $fmt ) {
					$schema['bookFormat'] = 'https://schema.org/' . $fmt;
				}
				break;
		}

		/**
		 * Allow other plugins to modify the assembled schema before it is emitted.
		 *
		 * @param array   $schema  Assembled JSON-LD array.
		 * @param WP_Post $post
		 * @param string  $type   Schema @type.
		 * @param array   $merged Merged field values used to build the schema.
		 */
		return (array) apply_filters( 'aeomatic_schema_output', $schema, $post, $type, $v );
	}

	// ── Sanitisation ──────────────────────────────────────────────────────

	private static function sanitise_overrides( array $fields ): array {
		$clean = array();
		foreach ( $fields as $key => $value ) {
			// Whitelist [A-Za-z0-9_-] but preserve case. sanitize_key() would
			// lowercase camelCase keys (aggregateRating_value → aggregaterating_value)
			// so 41 of the 93 field keys were being saved under a name the
			// editor never asks for on reload — every camelCase field
			// (aggregateRating, itemReviewed, datePublished, recipeYield, …)
			// went blank after save. Casing is significant here because it
			// matches the field definitions in AEOMATIC_Schema_Fields.
			$key = (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				// Repeater fields: array of strings. FAQ pairs are an array of
				// {q, a} objects — strval() on those stored the word "Array".
				$items = array();
				foreach ( $value as $item ) {
					if ( is_array( $item ) ) {
						$q = sanitize_textarea_field( (string) ( $item['q'] ?? '' ) );
						$a = sanitize_textarea_field( (string) ( $item['a'] ?? '' ) );
						if ( '' !== $q || '' !== $a ) {
							$items[] = array( 'q' => $q, 'a' => $a );
						}
					} elseif ( is_scalar( $item ) ) {
						$item = sanitize_textarea_field( (string) $item );
						if ( '' !== $item ) {
							$items[] = $item;
						}
					}
				}
				$clean[ $key ] = $items;
			} elseif ( is_bool( $value ) ) {
				$clean[ $key ] = $value;
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				// Real JSON numbers only. Numeric *strings* stay strings: casting
				// them dropped leading zeros and "+" from postcodes, SKUs, ISBNs
				// and phone numbers. assemble() casts the numeric properties.
				$clean[ $key ] = $value;
			} else {
				// String — sanitise based on key name hints.
				$v = (string) $value;
				if ( str_ends_with( $key, '_url' ) || 'url' === $key || 'image' === $key ) {
					$clean[ $key ] = esc_url_raw( $v );
				} else {
					$clean[ $key ] = sanitize_textarea_field( $v );
				}
			}
		}
		return $clean;
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private static function val( array $v, string $key, $default = null ) {
		$val = $v[ $key ] ?? null;
		if ( $val === null || $val === '' ) {
			return $default;
		}
		return $val;
	}

	/** Return a field as a flat list of strings, accepting array or newline string. */
	private static function as_list( array $v, string $key ): array {
		$val = $v[ $key ] ?? null;
		if ( is_array( $val ) ) {
			return array_values( array_filter( array_map( 'strval', $val ) ) );
		}
		if ( is_string( $val ) && '' !== $val ) {
			return array_values( array_filter(
				array_map( 'trim', preg_split( '/\r\n|\r|\n/', $val ) )
			) );
		}
		return array();
	}
}
