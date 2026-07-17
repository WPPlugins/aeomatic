<?php
/**
 * Type-aware schema builder.
 *
 * For each detected schema type, builds a complete JSON-LD object with
 * type-specific properties (Recipe with ingredients, JobPosting with
 * salary, Event with dates, etc.) — not just generic Article fields.
 *
 * Output is cached per-post and invalidated on save_post, ensuring
 * schema is always in sync with the latest content.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Schema_Builder {

	const CACHE_META = '_aeomatic_schema_cache';
	const CACHE_VERSION = 2;

	/**
	 * Build the primary schema for a post. Returns null if nothing meaningful
	 * can be built for this type/content combination.
	 *
	 * @param WP_Post   $post
	 * @param string    $type     Chosen schema @type from the analyzer.
	 * @param array     $settings Schema settings array.
	 * @param bool      $force    Bypass cache.
	 * @return array|null
	 */
	public static function build( WP_Post $post, string $type, array $settings, bool $force = false ): ?array {
		// Try cache first unless forced.
		if ( ! $force ) {
			$cached = self::read_cache( $post, $type );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		// Pre-extract content once.
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$text      = $extracted['text'];
		$html      = $extracted['html'];

		// Build the base envelope (common fields used by most types).
		$base = self::base_envelope( $post, $type, $settings );

		// Dispatch to the type-specific builder.
		$method = 'build_' . self::method_slug( $type );
		if ( method_exists( __CLASS__, $method ) ) {
			$schema = self::$method( $post, $base, $settings, $text, $html );
		} else {
			// Fallback — emit the base envelope only.
			$schema = $base;
		}

		// Cache the result with content fingerprint for invalidation.
		self::write_cache( $post, $type, $schema );

		return $schema;
	}

	/**
	 * Common fields included on most schema types.
	 */
	private static function base_envelope( WP_Post $post, string $type, array $settings ): array {
		$author = get_userdata( (int) $post->post_author );

		$envelope = array(
			'@context'      => 'https://schema.org',
			'@type'         => $type,
			'@id'           => get_permalink( $post ) . '#' . strtolower( $type ),
			'name'          => wp_strip_all_tags( get_the_title( $post ) ),
			'url'           => get_permalink( $post ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
		);

		// Description: post excerpt, or extracted text snippet.
		$excerpt = wp_strip_all_tags( get_the_excerpt( $post ) );
		if ( ! $excerpt ) {
			$extracted = AEOMATIC_Content_Extractor::extract( $post );
			$excerpt   = self::truncate( $extracted['text'], 280 );
		}
		if ( $excerpt ) {
			$envelope['description'] = $excerpt;
		}

		// Image.
		$thumb_id = get_post_thumbnail_id( $post );
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'full' );
			if ( $src ) {
				$envelope['image'] = $src[0];
			}
		}

		// Author (Person).
		if ( $author ) {
			$envelope['author'] = array(
				'@type' => 'Person',
				'name'  => $author->display_name,
				'url'   => get_author_posts_url( $author->ID ),
			);
		}

		// Publisher (Organization).
		$envelope['publisher'] = array(
			'@type' => 'Organization',
			'name'  => ! empty( $settings['org_name'] ) ? $settings['org_name'] : get_bloginfo( 'name' ),
		);
		if ( ! empty( $settings['org_logo'] ) ) {
			$envelope['publisher']['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $settings['org_logo'],
			);
		}

		return $envelope;
	}

	/* ===============================================================
	 * Type-specific builders
	 * =============================================================== */

	private static function build_article( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		// Article needs headline.
		$base['headline'] = $base['name'];
		$base['mainEntityOfPage'] = array(
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post ),
		);
		// Word count is a plus.
		$wc = str_word_count( $text );
		if ( $wc ) {
			$base['wordCount'] = $wc;
		}

		// GEO enrichment: abstract, about, mentions, citation.
		// AI engines use these to map the article into their knowledge graph
		// and to surface short quotable summaries in generative answers.
		$enriched = self::geo_enrich_article( $post, $text, $html );
		foreach ( $enriched as $k => $v ) {
			if ( ! empty( $v ) ) {
				$base[ $k ] = $v;
			}
		}
		return $base;
	}

	/**
	 * Auto-derive GEO-friendly schema properties from existing post data.
	 *
	 * - abstract: WP excerpt (or first ~300 chars of text if no excerpt).
	 * - about: categories as Thing[] with optional sameAs (Wikipedia URL hint).
	 * - mentions: tags as Thing[].
	 * - citation: outbound external links from the post content, treated as
	 *   `CreativeWork` references.
	 *
	 * @return array<string,mixed>
	 */
	private static function geo_enrich_article( WP_Post $post, string $text, string $html ): array {
		$out = array();

		// abstract — short summary AI engines can quote directly.
		$excerpt = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
		if ( '' === $excerpt && $text ) {
			$excerpt = mb_substr( trim( $text ), 0, 300 );
		}
		if ( '' !== $excerpt ) {
			$out['abstract'] = $excerpt;
		}

		// about — primary topics, from categories.
		$cats = get_the_category( $post->ID );
		if ( ! empty( $cats ) ) {
			$about = array();
			foreach ( $cats as $c ) {
				$about[] = array(
					'@type' => 'Thing',
					'name'  => $c->name,
				);
			}
			$out['about'] = $about;
		}

		// mentions — secondary entities, from tags.
		$tags = get_the_tags( $post->ID );
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			$mentions = array();
			foreach ( $tags as $t ) {
				$mentions[] = array(
					'@type' => 'Thing',
					'name'  => $t->name,
				);
			}
			$out['mentions'] = $mentions;
		}

		// citation — outbound external links treated as referenced works.
		$citations = self::extract_citations( $html );
		if ( ! empty( $citations ) ) {
			$out['citation'] = $citations;
		}

		return $out;
	}

	/**
	 * Pull external links from post HTML and shape them as schema.org
	 * CreativeWork references. Capped at 20 to keep JSON-LD payload sane.
	 *
	 * @return array<int,array{@type:string,url:string,name:string}>
	 */
	private static function extract_citations( string $html ): array {
		if ( '' === $html ) {
			return array();
		}
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! preg_match_all( '/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$seen = array();
		$out  = array();
		foreach ( $matches as $m ) {
			$href = (string) $m[1];
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $host || $host === $home || str_ends_with( $host, '.' . $home ) ) {
				continue;
			}
			if ( ! preg_match( '#^https?://#i', $href ) ) {
				continue;
			}
			if ( isset( $seen[ $href ] ) ) {
				continue;
			}
			$seen[ $href ] = true;
			$anchor = trim( wp_strip_all_tags( $m[2] ?? '' ) );
			$out[]  = array(
				'@type' => 'CreativeWork',
				'url'   => esc_url_raw( $href ),
				'name'  => $anchor !== '' ? $anchor : $host,
			);
			if ( count( $out ) >= 20 ) {
				break;
			}
		}
		return $out;
	}

	private static function build_blogposting( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		return self::build_article( $post, $base, $settings, $text, $html );
	}

	private static function build_newsarticle( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$schema = self::build_article( $post, $base, $settings, $text, $html );
		// NewsArticle benefits from articleSection (category).
		$cats = get_the_category( $post->ID );
		if ( $cats ) {
			$schema['articleSection'] = $cats[0]->name;
		}
		return $schema;
	}

	private static function build_techarticle( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$schema = self::build_article( $post, $base, $settings, $text, $html );
		$schema['proficiencyLevel'] = 'Beginner';
		return $schema;
	}

	private static function build_recipe( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$ingredients = AEOMATIC_Property_Extractors::ingredients( $post, $text, $html );
		$instructions = AEOMATIC_Property_Extractors::instructions( $post, $text, $html );
		$prep_time    = AEOMATIC_Property_Extractors::duration( $text, 'prep' );
		$cook_time    = AEOMATIC_Property_Extractors::duration( $text, 'cook' );
		$total_time   = AEOMATIC_Property_Extractors::duration( $text, 'total' );
		$yield        = AEOMATIC_Property_Extractors::recipe_yield( $post, $text );

		if ( $ingredients ) {
			$base['recipeIngredient'] = $ingredients;
		}
		if ( $instructions ) {
			$base['recipeInstructions'] = $instructions;
		}
		if ( $prep_time )  $base['prepTime']  = $prep_time;
		if ( $cook_time )  $base['cookTime']  = $cook_time;
		if ( $total_time ) $base['totalTime'] = $total_time;
		if ( $yield )      $base['recipeYield'] = $yield;

		// Try to detect category and cuisine from taxonomies.
		$cats = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$base['recipeCategory'] = $cats[0];
		}

		return $base;
	}

	private static function build_howto( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$instructions = AEOMATIC_Property_Extractors::instructions( $post, $text, $html );
		if ( $instructions ) {
			$base['step'] = $instructions;
		}
		$total = AEOMATIC_Property_Extractors::duration( $text, 'total' );
		if ( $total ) $base['totalTime'] = $total;
		return $base;
	}

	private static function build_faqpage( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$qa        = $extracted['faqs'] ?? array();

		// If no AEO FAQ blocks, derive from question-style headings.
		if ( empty( $qa ) ) {
			$qa = self::derive_faqs_from_headings( $html );
		}

		$base['mainEntity'] = array_map( static function ( $pair ) {
			return array(
				'@type'          => 'Question',
				'name'           => $pair['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $pair['a'],
				),
			);
		}, $qa );

		return $base;
	}

	private static function build_qapage( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$qa = $extracted['faqs'] ?? self::derive_faqs_from_headings( $html );

		if ( ! empty( $qa ) ) {
			$first = $qa[0];
			$base['mainEntity'] = array(
				'@type'        => 'Question',
				'name'         => $first['q'],
				'text'         => $first['q'],
				'answerCount'  => count( $qa ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $first['a'],
				),
			);
		}
		return $base;
	}

	private static function build_review( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		// Review schema needs an itemReviewed and a reviewRating.
		$rating = AEOMATIC_Property_Extractors::rating( $post, $text );
		if ( $rating ) {
			$base['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => $rating['value'],
				'bestRating'  => $rating['best'],
				'worstRating' => 1,
			);
		}

		// itemReviewed: try to detect product name from title.
		$item_name = self::extract_review_subject( get_the_title( $post ) );
		if ( $item_name ) {
			$base['itemReviewed'] = array(
				'@type' => 'Thing',
				'name'  => $item_name,
			);
		}

		// Pros/cons go in reviewBody as structured text.
		$pc = AEOMATIC_Property_Extractors::pros_and_cons( $post, $html );
		if ( $pc ) {
			if ( ! empty( $pc['pros'] ) ) {
				$base['positiveNotes'] = array(
					'@type'           => 'ItemList',
					'itemListElement' => array_map( static function ( $p, $i ) {
						return array(
							'@type'    => 'ListItem',
							'position' => $i + 1,
							'name'     => $p,
						);
					}, $pc['pros'], array_keys( $pc['pros'] ) ),
				);
			}
			if ( ! empty( $pc['cons'] ) ) {
				$base['negativeNotes'] = array(
					'@type'           => 'ItemList',
					'itemListElement' => array_map( static function ( $p, $i ) {
						return array(
							'@type'    => 'ListItem',
							'position' => $i + 1,
							'name'     => $p,
						);
					}, $pc['cons'], array_keys( $pc['cons'] ) ),
				);
			}
		}

		return $base;
	}

	private static function build_service( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$base['provider'] = $base['publisher']; // service is provided by the org
		$base['serviceType'] = $base['name'];
		// areaServed could come from ACF.
		$area = AEOMATIC_Property_Extractors::meta_value( $post->ID, array( 'area_served', 'service_area', 'location' ) );
		if ( $area ) {
			$base['areaServed'] = is_string( $area ) ? $area : (string) $area;
		}
		return $base;
	}

	private static function build_localbusiness( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$addr = AEOMATIC_Property_Extractors::address( $post, $text );
		if ( $addr ) {
			$base['address'] = $addr;
		}
		$phone = AEOMATIC_Property_Extractors::telephone( $post, $text );
		if ( $phone ) {
			$base['telephone'] = $phone;
		}
		$hours = AEOMATIC_Property_Extractors::opening_hours( $post, $text );
		if ( $hours ) {
			$base['openingHours'] = $hours;
		}

		$rating = AEOMATIC_Property_Extractors::rating( $post, $text );
		if ( $rating ) {
			$base['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating['value'],
				'bestRating'  => $rating['best'],
				'worstRating' => 1,
				'reviewCount' => 1,
			);
		}

		return $base;
	}

	private static function build_event( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$start = AEOMATIC_Property_Extractors::event_start( $post, $text );
		if ( $start ) $base['startDate'] = $start;
		$end = AEOMATIC_Property_Extractors::event_end( $post );
		if ( $end ) $base['endDate'] = $end;

		$loc = AEOMATIC_Property_Extractors::event_location( $post );
		if ( $loc ) {
			$base['location'] = $loc;
		}
		$base['organizer'] = $base['publisher'];
		$base['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
		$base['eventStatus'] = 'https://schema.org/EventScheduled';

		return $base;
	}

	private static function build_jobposting( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$base['title'] = $base['name'];
		$base['datePosted'] = get_the_date( 'c', $post );

		$base['hiringOrganization'] = $base['publisher'];

		$emp_type = AEOMATIC_Property_Extractors::employment_type( $post, $text );
		if ( $emp_type ) {
			$base['employmentType'] = $emp_type;
		}
		$salary = AEOMATIC_Property_Extractors::job_salary( $post, $text );
		if ( $salary ) {
			$base['baseSalary'] = $salary;
		}
		$loc = AEOMATIC_Property_Extractors::address( $post, $text );
		if ( $loc ) {
			$base['jobLocation'] = array(
				'@type'   => 'Place',
				'address' => $loc,
			);
		}
		// validThrough — application deadline.
		$valid = AEOMATIC_Property_Extractors::meta_value( $post->ID, array( 'application_deadline', 'valid_through', 'closing_date' ) );
		if ( $valid ) {
			$ts = is_numeric( $valid ) ? (int) $valid : strtotime( (string) $valid );
			if ( $ts ) {
				$base['validThrough'] = gmdate( 'c', $ts );
			}
		}

		return $base;
	}

	private static function build_course( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$base['provider'] = AEOMATIC_Property_Extractors::course_provider( $post );
		// Free vs paid.
		$price = AEOMATIC_Property_Extractors::meta_value( $post->ID, array( 'price', 'course_price' ) );
		if ( null !== $price ) {
			$base['offers'] = array(
				'@type'         => 'Offer',
				'category'      => 'paid',
				'price'         => is_numeric( $price ) ? (string) $price : '0',
				'priceCurrency' => 'USD',
			);
		}
		return $base;
	}

	private static function build_aboutpage( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$base['mainEntity'] = $base['publisher']; // The org this page is about.
		return $base;
	}

	private static function build_contactpage( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$phone = AEOMATIC_Property_Extractors::telephone( $post, $text );
		if ( $phone ) {
			$base['telephone'] = $phone;
		}
		$addr = AEOMATIC_Property_Extractors::address( $post, $text );
		if ( $addr ) {
			$base['address'] = $addr;
		}
		return $base;
	}

	private static function build_webpage( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		// Generic — just the envelope.
		return $base;
	}

	private static function build_collectionpage( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		return $base;
	}

	private static function build_profilepage( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		// Profile pages describe the person who is the author/subject.
		$base['mainEntity'] = $base['author'] ?? null;
		return $base;
	}

	private static function build_book( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		$isbn = AEOMATIC_Property_Extractors::meta_value( $post->ID, array( 'isbn' ) );
		if ( $isbn ) $base['isbn'] = $isbn;
		return $base;
	}

	private static function build_movie( WP_Post $post, array $base, array $settings, string $text, string $html ): array {
		return $base;
	}

	/* ===============================================================
	 * Caching with content fingerprint
	 * =============================================================== */

	/**
	 * Generate a fingerprint of the post that captures everything that
	 * would change the schema. If the fingerprint matches the cached one,
	 * return the cached schema; otherwise rebuild.
	 */
	private static function content_fingerprint( WP_Post $post ): string {
		$parts = array(
			$post->post_title,
			$post->post_excerpt,
			$post->post_modified_gmt,
			get_post_thumbnail_id( $post ),
			get_post_meta( $post->ID, AEOMATIC_Schema_Analyzer::META_OVERRIDE, true ),
			// Content extractor result captures all builder content + ACF.
			AEOMATIC_Content_Extractor::extract( $post )['text'] ?? '',
		);
		return md5( implode( '|', array_map( static function ( $v ) {
			return is_scalar( $v ) ? (string) $v : wp_json_encode( $v );
		}, $parts ) ) );
	}

	private static function read_cache( WP_Post $post, string $type ): ?array {
		$cached = get_post_meta( $post->ID, self::CACHE_META, true );
		if ( ! is_array( $cached ) ) {
			return null;
		}
		if ( ( $cached['v'] ?? 0 ) !== self::CACHE_VERSION ) {
			return null;
		}
		if ( ( $cached['type'] ?? '' ) !== $type ) {
			return null;
		}
		if ( ( $cached['fp'] ?? '' ) !== self::content_fingerprint( $post ) ) {
			return null;
		}
		return is_array( $cached['schema'] ?? null ) ? $cached['schema'] : null;
	}

	private static function write_cache( WP_Post $post, string $type, array $schema ): void {
		update_post_meta( $post->ID, self::CACHE_META, array(
			'v'      => self::CACHE_VERSION,
			'type'   => $type,
			'fp'     => self::content_fingerprint( $post ),
			'schema' => $schema,
			'built'  => time(),
		) );
	}

	/**
	 * Public method: invalidate cache for a post (called from save_post).
	 */
	public static function invalidate( int $post_id ): void {
		delete_post_meta( $post_id, self::CACHE_META );
	}

	/* ===============================================================
	 * Helpers
	 * =============================================================== */

	private static function method_slug( string $type ): string {
		return strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', $type ) );
	}

	private static function truncate( string $text, int $limit ): string {
		if ( strlen( $text ) <= $limit ) return $text;
		$cut = substr( $text, 0, $limit );
		$last = strrpos( $cut, ' ' );
		if ( false !== $last ) $cut = substr( $cut, 0, $last );
		return $cut . '…';
	}

	/**
	 * Try to extract the subject of a review from its title.
	 * "Apple iPhone 16 Pro review" → "Apple iPhone 16 Pro"
	 */
	private static function extract_review_subject( string $title ): ?string {
		$cleaned = preg_replace( '/\b(review(s)?|hands[\s-]on|first impressions?|verdict|tested)\b/i', '', $title );
		$cleaned = trim( preg_replace( '/\s+/', ' ', $cleaned ?: '' ) );
		$cleaned = trim( $cleaned, " \t\n\r:-—–" );
		return $cleaned ?: null;
	}

	/**
	 * Derive Q&A pairs from H2/H3 headings ending with "?" + the paragraph
	 * that follows. Used as a fallback for FAQPage when no AEO FAQ blocks
	 * are present.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	private static function derive_faqs_from_headings( string $html ): array {
		$out = array();
		// Split on H2/H3 headings, capturing the heading and the content after it.
		if ( ! preg_match_all( '/<h[2-3][^>]*>(.*?)<\/h[2-3]>(.*?)(?=<h[2-3]|$)/is', $html, $matches, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $matches as $m ) {
			$q = trim( wp_strip_all_tags( $m[1] ) );
			if ( ! str_ends_with( $q, '?' ) ) continue;
			$a = trim( wp_strip_all_tags( $m[2] ) );
			$a = preg_replace( '/\s+/', ' ', $a ?: '' );
			if ( $a && strlen( $a ) >= 30 ) {
				$out[] = array( 'q' => $q, 'a' => self::truncate( $a, 500 ) );
			}
		}
		return $out;
	}
}
