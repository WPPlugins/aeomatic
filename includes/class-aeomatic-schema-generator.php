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

		// wp_slash: update_post_meta() unslashes, which would corrupt the JSON's escapes.
		$ok = update_post_meta( $post_id, self::META_OVERRIDES, wp_slash( wp_json_encode( array_merge( $kept, $sanitised ) ) ) );

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
			update_post_meta( $post_id, self::META_OVERRIDES, wp_slash( wp_json_encode( $overrides ) ) );
		}
		AEOMATIC_Schema_Builder::invalidate( $post_id );
		AEOMATIC_Schema_Analyzer::invalidate_cache( $post_id );
	}

	// ── Graph identifiers ─────────────────────────────────────────────────
	//
	// Every node carries an @id so the other entities on the page can point
	// at it. That is what turns separate JSON-LD blocks into one statement
	// about one business: the Article's publisher, the WebSite's publisher
	// and the Organization node all resolve to the same #organization.

	public static function org_id(): string {
		return home_url( '/#organization' );
	}

	public static function website_id(): string {
		return home_url( '/#website' );
	}

	public static function webpage_id( WP_Post $post ): string {
		return get_permalink( $post ) . '#webpage';
	}

	public static function person_id( int $user_id ): string {
		return get_author_posts_url( $user_id ) . '#person';
	}

	/** Organization name from Settings, falling back to the site title. */
	public static function org_name( array $settings ): string {
		return ! empty( $settings['org_name'] ) ? (string) $settings['org_name'] : (string) get_bloginfo( 'name' );
	}

	/**
	 * Inline Organization reference. Carries the site-wide @id only when it
	 * is the site's own organization, so a user-typed third party (another
	 * hiring company, another publisher) never merges into it.
	 */
	private static function org_ref( array $settings, string $name, ?string $logo = null ): array {
		$ref = array( '@type' => 'Organization', 'name' => $name );
		if ( ! empty( $settings['organization'] ) && $name === self::org_name( $settings ) ) {
			$ref['@id'] = self::org_id();
		}
		$logo = $logo ?: ( $name === self::org_name( $settings ) ? (string) ( $settings['org_logo'] ?? '' ) : '' );
		if ( $logo ) {
			$ref['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
		}
		return $ref;
	}

	/** Types that are schema.org CreativeWorks and so take author/publisher/dates. */
	private const CREATIVE_WORKS = array(
		'Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'Recipe', 'Review',
		'Course', 'Book', 'Movie', 'SoftwareApplication', 'Dataset',
		'WebPage', 'AboutPage', 'ContactPage', 'CollectionPage', 'ProfilePage',
	);

	/** WebPage subtypes: when primary, they are the page node itself. */
	public const PAGE_TYPES = array( 'WebPage', 'AboutPage', 'ContactPage', 'CollectionPage', 'ProfilePage' );

	// ── Private: assemble flat merged values into a JSON-LD tree ──────────

	private static function assemble( WP_Post $post, string $type, array $settings, array $v ): array {
		$permalink = get_permalink( $post );
		$is_work   = in_array( $type, self::CREATIVE_WORKS, true );
		$is_page   = in_array( $type, self::PAGE_TYPES, true );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'@id'      => $is_page ? self::webpage_id( $post ) : $permalink . '#' . strtolower( $type ),
			'name'     => self::val( $v, 'name', wp_strip_all_tags( get_the_title( $post ) ) ),
			'url'      => self::val( $v, 'url', $permalink ),
		);

		$desc = self::val( $v, 'description' );
		if ( $desc ) {
			$schema['description'] = $desc;
		}

		$image = self::val( $v, 'image' );
		if ( $image ) {
			$schema['image'] = $image;
		}

		if ( $is_work ) {
			$schema['datePublished'] = self::val( $v, 'datePublished', get_the_date( 'c', $post ) );
			$schema['dateModified']  = self::val( $v, 'dateModified', get_the_modified_date( 'c', $post ) );
			$schema['inLanguage']    = get_bloginfo( 'language' );

			// Author — linked by @id to the page's Person node when it is the
			// post's own WordPress author and not a name the user typed in.
			$author_name = self::val( $v, 'author_name' );
			if ( $author_name ) {
				$author    = array( '@type' => 'Person', 'name' => mb_substr( (string) $author_name, 0, 100 ) );
				$wp_author = get_userdata( (int) $post->post_author );
				if ( $wp_author && ! empty( $settings['author'] ) && $wp_author->display_name === $author_name ) {
					$author['@id'] = self::person_id( $wp_author->ID );
				}
				$author_url = self::val( $v, 'author_url' );
				if ( $author_url ) {
					$author['url'] = $author_url;
				}
				$schema['author'] = $author;
			}

			$schema['publisher'] = self::org_ref(
				$settings,
				(string) self::val( $v, 'publisher_name', self::org_name( $settings ) ),
				self::val( $v, 'publisher_logo' )
			);
		}

		if ( $is_page ) {
			$schema['isPartOf'] = array( '@id' => self::website_id() );
			$keywords = self::val( $v, 'keywords' );
			if ( $keywords ) {
				$schema['keywords'] = $keywords;
			}
		}

		// Type-specific assembly.
		switch ( $type ) {

			case 'Article':
			case 'BlogPosting':
			case 'NewsArticle':
			case 'TechArticle':
				$schema['headline']         = mb_substr( (string) self::val( $v, 'headline', $schema['name'] ), 0, 110 );
				$schema['mainEntityOfPage'] = array( '@id' => self::webpage_id( $post ) );
				if ( self::val( $v, 'articleSection' ) )  $schema['articleSection'] = self::val( $v, 'articleSection' );
				if ( self::val( $v, 'wordCount' ) )        $schema['wordCount']      = (int) self::val( $v, 'wordCount' );

				// Gated content: a real boolean, never the string "false",
				// which parsers read as truthy.
				if ( false === self::bool_val( $v, 'isAccessibleForFree' ) ) {
					$schema['isAccessibleForFree'] = false;
					$selector = trim( (string) self::val( $v, 'paywall_selector', '' ) );
					// Only when an element with that class is really in the
					// content: validators flag a selector that matches nothing.
					$html = (string) ( AEOMATIC_Content_Extractor::extract( $post )['html'] ?? '' );
					if ( preg_match( '/^\.[A-Za-z_][A-Za-z0-9_-]*$/', $selector )
						&& preg_match( '/\bclass=("|\')[^"\']*(?<![\w-])' . preg_quote( substr( $selector, 1 ), '/' ) . '(?![\w-])/i', $html ) ) {
						$schema['hasPart'] = array(
							'@type'               => 'WebPageElement',
							'isAccessibleForFree' => false,
							'cssSelector'         => $selector,
						);
					}
				}
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

			case 'Review':
				$item_name = self::val( $v, 'itemReviewed_name' );
				$item_type = self::val( $v, 'itemReviewed_type', 'Product' );
				if ( $item_name ) {
					$schema['itemReviewed'] = array( '@type' => $item_type, 'name' => $item_name );
				}
				// Self-review rule: a business rating itself is ineligible, so
				// no rating is claimed when the reviewed item is this site's
				// own organization.
				$is_self = $item_name && in_array( $item_type, array( 'Organization', 'LocalBusiness' ), true )
					&& 0 === strcasecmp( trim( (string) $item_name ), self::org_name( $settings ) );
				$rating_val = self::val( $v, 'reviewRating_value' );
				if ( ! $is_self && $rating_val !== '' && $rating_val !== null ) {
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
				foreach ( array( 'sku', 'gtin', 'mpn', 'category' ) as $prop ) {
					$val = self::val( $v, $prop );
					if ( $val ) {
						$schema[ $prop ] = $val;
					}
				}
				$price = self::val( $v, 'offer_price' );
				if ( $price !== '' && $price !== null ) {
					$offer = array(
						'@type'         => 'Offer',
						'price'         => (string) $price,
						'priceCurrency' => self::val( $v, 'offer_currency', 'USD' ),
						'availability'  => 'https://schema.org/' . self::val( $v, 'offer_availability', 'InStock' ),
						'url'           => self::val( $v, 'offer_url', $permalink ),
					);
					$condition = self::val( $v, 'offer_itemCondition' );
					if ( $condition ) {
						$offer['itemCondition'] = 'https://schema.org/' . $condition;
					}
					$valid_until = self::val( $v, 'offer_priceValidUntil' );
					if ( $valid_until ) {
						$offer['priceValidUntil'] = $valid_until;
					}
					$schema['offers'] = $offer;
				}
				$rating = self::aggregate_rating( $v );
				if ( $rating ) {
					$schema['aggregateRating'] = $rating;
				}
				break;

			case 'SoftwareApplication':
				foreach ( array( 'applicationCategory', 'operatingSystem', 'softwareVersion', 'downloadUrl' ) as $prop ) {
					$val = self::val( $v, $prop );
					if ( $val ) {
						$schema[ $prop ] = $val;
					}
				}
				$features = self::as_list( $v, 'featureList' );
				if ( $features ) {
					$schema['featureList'] = $features;
				}
				// Required. "0" is a real price for a free app, so only an
				// empty value is treated as missing.
				$price = self::val( $v, 'offer_price' );
				if ( $price !== '' && $price !== null ) {
					$schema['offers'] = array(
						'@type'         => 'Offer',
						'price'         => (string) $price,
						'priceCurrency' => self::val( $v, 'offer_currency', 'USD' ),
					);
				}
				$rating = self::aggregate_rating( $v );
				if ( $rating ) {
					$schema['aggregateRating'] = $rating;
				}
				break;

			case 'Event':
				foreach ( array( 'startDate', 'endDate', 'previousStartDate' ) as $prop ) {
					$val = self::val( $v, $prop );
					if ( $val ) {
						$schema[ $prop ] = $val;
					}
				}
				$mode = self::val( $v, 'eventAttendanceMode', 'OfflineEventAttendanceMode' );
				$schema['eventAttendanceMode'] = 'https://schema.org/' . $mode;

				$place    = null;
				$loc_name = self::val( $v, 'location_name' );
				$loc_addr = self::val( $v, 'location_address' );
				if ( $loc_name || $loc_addr ) {
					$place = array( '@type' => 'Place' );
					if ( $loc_name ) $place['name'] = $loc_name;
					if ( $loc_addr ) $place['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => $loc_addr );
				}
				$loc_url = self::val( $v, 'location_url' );
				$virtual = $loc_url ? array( '@type' => 'VirtualLocation', 'url' => $loc_url ) : null;
				if ( 'OnlineEventAttendanceMode' === $mode ) {
					$location = $virtual ?? $place;
				} elseif ( 'MixedEventAttendanceMode' === $mode ) {
					$location = array_values( array_filter( array( $place, $virtual ) ) );
					$location = count( $location ) === 1 ? $location[0] : ( $location ?: null );
				} else {
					$location = $place;
				}
				if ( $location ) {
					$schema['location'] = $location;
				}

				$org_name = self::val( $v, 'organizer_name' );
				if ( $org_name ) {
					$schema['organizer'] = self::org_ref( $settings, (string) $org_name );
					$org_url = self::val( $v, 'organizer_url' );
					if ( $org_url ) {
						$schema['organizer']['url'] = $org_url;
					}
				}
				$performer = self::val( $v, 'performer_name' );
				if ( $performer ) {
					$schema['performer'] = array( '@type' => 'PerformingGroup', 'name' => $performer );
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
						'availability'  => 'https://schema.org/' . self::val( $v, 'offer_availability', 'InStock' ),
						'url'           => self::val( $v, 'offer_url', $permalink ),
					);
				}
				break;

			case 'JobPosting':
				$schema['title']      = self::val( $v, 'title', $schema['name'] );
				$schema['datePosted'] = self::val( $v, 'datePosted', get_the_date( 'c', $post ) );
				$schema['hiringOrganization'] = self::org_ref( $settings, (string) self::val( $v, 'hiringOrg_name', self::org_name( $settings ) ) );
				$org_url = self::val( $v, 'hiringOrg_url' );
				if ( $org_url ) {
					$schema['hiringOrganization']['sameAs'] = $org_url;
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
				// A fully remote role replaces jobLocation with the countries
				// applicants may live in.
				$job_location = self::val( $v, 'jobLocation_address' );
				if ( 'TELECOMMUTE' === self::val( $v, 'jobLocationType' ) ) {
					$schema['jobLocationType'] = 'TELECOMMUTE';
					$countries = array_filter( array_map( 'trim', explode( ',', (string) self::val( $v, 'applicantLocation', '' ) ) ) );
					if ( $countries ) {
						$schema['applicantLocationRequirements'] = array_values( array_map(
							static fn( $c ) => array( '@type' => 'Country', 'name' => $c ),
							$countries
						) );
					}
				}
				if ( $job_location ) {
					$schema['jobLocation'] = array(
						'@type'   => 'Place',
						'address' => array( '@type' => 'PostalAddress', 'streetAddress' => $job_location ),
					);
				}
				$valid_through = self::val( $v, 'validThrough' );
				if ( $valid_through ) {
					$schema['validThrough'] = $valid_through;
				}
				$direct = self::bool_val( $v, 'directApply' );
				if ( null !== $direct ) {
					$schema['directApply'] = $direct;
				}
				$job_id = self::val( $v, 'identifier_value' );
				if ( $job_id ) {
					$schema['identifier'] = array(
						'@type' => 'PropertyValue',
						'name'  => $schema['hiringOrganization']['name'],
						'value' => $job_id,
					);
				}
				break;

			case 'Course':
				$schema['provider'] = self::org_ref( $settings, (string) self::val( $v, 'provider_name', self::org_name( $settings ) ) );
				$prov_url = self::val( $v, 'provider_url', home_url( '/' ) );
				if ( $prov_url ) {
					$schema['provider']['sameAs'] = $prov_url;
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
				// The most specific subtype is the @type itself; Google does
				// not support additionalType.
				$subtype = (string) self::val( $v, 'business_subtype', 'LocalBusiness' );
				if ( isset( AEOMATIC_Schema_Fields::local_business_subtypes()[ $subtype ] ) ) {
					$schema['@type'] = $subtype;
				}
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
				$lat = self::val( $v, 'geo_latitude' );
				$lng = self::val( $v, 'geo_longitude' );
				if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
					$schema['geo'] = array(
						'@type'     => 'GeoCoordinates',
						'latitude'  => (float) $lat,
						'longitude' => (float) $lng,
					);
				}
				$phone = self::val( $v, 'telephone' );
				if ( $phone ) {
					$schema['telephone'] = $phone;
				}
				$hours = self::as_list( $v, 'openingHours' );
				if ( $hours ) {
					$spec = self::opening_hours_spec( $hours );
					if ( $spec ) {
						$schema['openingHoursSpecification'] = $spec;
					} else {
						$schema['openingHours'] = $hours;
					}
				}
				foreach ( array( 'priceRange', 'servesCuisine', 'menu' ) as $prop ) {
					$val = self::val( $v, $prop );
					if ( $val ) {
						$schema[ $prop ] = $val;
					}
				}
				$rating = self::aggregate_rating( $v );
				if ( $rating ) {
					$schema['aggregateRating'] = $rating;
				}
				break;

			case 'Service':
				$service_type = self::val( $v, 'serviceType' );
				if ( $service_type ) {
					$schema['serviceType'] = $service_type;
				}
				$schema['provider'] = self::org_ref( $settings, (string) self::val( $v, 'provider_name', self::org_name( $settings ) ) );
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

			case 'Dataset':
				if ( isset( $schema['description'] ) ) {
					$schema['description'] = mb_substr( (string) $schema['description'], 0, 5000 );
				}
				$creator = self::val( $v, 'creator_name' );
				if ( $creator ) {
					$schema['creator'] = self::org_ref( $settings, (string) $creator );
				}
				foreach ( array( 'license', 'identifier', 'keywords', 'version', 'temporalCoverage', 'measurementTechnique', 'citation' ) as $prop ) {
					$val = self::val( $v, $prop );
					if ( $val ) {
						$schema[ $prop ] = $val;
					}
				}
				$free = self::bool_val( $v, 'isAccessibleForFree' );
				if ( null !== $free ) {
					$schema['isAccessibleForFree'] = $free;
				}
				$spatial = self::val( $v, 'spatialCoverage' );
				if ( $spatial ) {
					$schema['spatialCoverage'] = array( '@type' => 'Place', 'name' => $spatial );
				}
				$variables = self::as_list( $v, 'variableMeasured' );
				if ( $variables ) {
					$schema['variableMeasured'] = $variables;
				}
				$download = self::val( $v, 'distribution_url' );
				if ( $download ) {
					$dist = array( '@type' => 'DataDownload', 'contentUrl' => $download );
					$format = self::val( $v, 'distribution_format' );
					if ( $format ) {
						$dist['encodingFormat'] = $format;
					}
					$schema['distribution'] = array( $dist );
				}
				break;

			case 'AboutPage':
				// The organization this page is about. The full Organization
				// node is printed on this page, so a reference is enough.
				$schema['mainEntity'] = ! empty( $settings['organization'] )
					? array( '@id' => self::org_id() )
					: self::org_ref( $settings, self::org_name( $settings ) );
				break;

			case 'ProfilePage':
				$entity_name = self::val( $v, 'mainEntity_name' );
				if ( $entity_name ) {
					$entity_type = 'Organization' === self::val( $v, 'mainEntity_type' ) ? 'Organization' : 'Person';
					$entity      = array( '@type' => $entity_type, 'name' => $entity_name );
					$wp_author   = get_userdata( (int) $post->post_author );
					if ( 'Person' === $entity_type && $wp_author && ! empty( $settings['author'] ) && $wp_author->display_name === $entity_name ) {
						$entity['@id'] = self::person_id( $wp_author->ID );
					}
					if ( 'Person' === $entity_type && self::val( $v, 'mainEntity_jobTitle' ) ) {
						$entity['jobTitle'] = self::val( $v, 'mainEntity_jobTitle' );
					}
					foreach ( array( 'description', 'image' ) as $prop ) {
						$val = self::val( $v, 'mainEntity_' . $prop );
						if ( $val ) {
							$entity[ $prop ] = $val;
						}
					}
					$same = self::as_list( $v, 'mainEntity_sameAs' );
					if ( $same ) {
						$entity['sameAs'] = $same;
					}
					$schema['mainEntity'] = $entity;
				}
				$schema['dateCreated'] = $schema['datePublished'];
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
					$schema['publisher'] = self::org_ref( $settings, (string) $pub_name_book );
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

	/**
	 * AggregateRating from the rating value/count fields, or null. Both are
	 * required — inventing a count would misreport the reviews.
	 */
	private static function aggregate_rating( array $v ): ?array {
		$rating_val = self::val( $v, 'aggregateRating_value' );
		$rating_cnt = self::val( $v, 'aggregateRating_count' );
		if ( ! $rating_val || ! $rating_cnt ) {
			return null;
		}
		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (float) $rating_val,
			'reviewCount' => (int) $rating_cnt,
			'bestRating'  => 5,
			'worstRating' => 1,
		);
	}

	/**
	 * Turn "Mo-Fr 09:00-18:00" lines into OpeningHoursSpecification, which is
	 * the form Google documents. Returns null if any line does not parse, so
	 * the caller keeps the plain openingHours text instead of dropping hours.
	 *
	 * @param string[] $lines
	 * @return array<int,array>|null
	 */
	private static function opening_hours_spec( array $lines ): ?array {
		$days = array( 'Mo' => 'Monday', 'Tu' => 'Tuesday', 'We' => 'Wednesday', 'Th' => 'Thursday', 'Fr' => 'Friday', 'Sa' => 'Saturday', 'Su' => 'Sunday' );
		$keys = array_keys( $days );
		$out  = array();
		foreach ( $lines as $line ) {
			if ( ! preg_match( '/^(Mo|Tu|We|Th|Fr|Sa|Su)(?:\s*-\s*(Mo|Tu|We|Th|Fr|Sa|Su))?\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/i', trim( $line ), $m ) ) {
				return null;
			}
			$from = array_search( ucfirst( strtolower( $m[1] ) ), $keys, true );
			$to   = '' !== $m[2] ? array_search( ucfirst( strtolower( $m[2] ) ), $keys, true ) : $from;
			if ( false === $from || false === $to || $to < $from ) {
				return null;
			}
			$out[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array_map( static fn( $k ) => $days[ $k ], array_slice( $keys, $from, $to - $from + 1 ) ),
				'opens'     => str_pad( $m[3], 5, '0', STR_PAD_LEFT ),
				'closes'    => str_pad( $m[4], 5, '0', STR_PAD_LEFT ),
			);
		}
		return $out ?: null;
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

	/**
	 * Read a yes/no select as a real boolean. The editor stores "true" /
	 * "false" strings; JSON-LD needs true / false, since the string "false"
	 * is truthy to a parser. Returns null when the field is unset.
	 */
	private static function bool_val( array $v, string $key ): ?bool {
		$val = $v[ $key ] ?? null;
		if ( is_bool( $val ) ) {
			return $val;
		}
		if ( 'true' === $val || '1' === $val ) {
			return true;
		}
		if ( 'false' === $val || '0' === $val ) {
			return false;
		}
		return null;
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
