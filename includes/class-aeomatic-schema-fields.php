<?php
/**
 * Schema field definitions.
 *
 * For every supported schema @type, defines the editable fields that
 * appear in the visual schema editor. Each field has:
 *
 *  - key      The JSON-LD property name (e.g. 'cookTime')
 *  - label    Human-readable label shown in the editor
 *  - type     Input widget: text | textarea | url | date | number |
 *             repeater | select | toggle
 *  - help     One-line hint shown below the field
 *  - required Whether Google marks this property as required
 *  - options  For select fields: [ 'value' => 'Label' ]
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Schema_Fields {

	/**
	 * Fields shared across nearly all schema types.
	 * Merged into every type definition.
	 */
	private static function common(): array {
		return array(
			array( 'key' => 'name',          'label' => 'Title / Name',       'type' => 'text',     'required' => true,  'help' => 'Defaults to post title.' ),
			array( 'key' => 'description',   'label' => 'Description',        'type' => 'textarea', 'required' => false, 'help' => 'Defaults to post excerpt. Max 300 chars.' ),
			array( 'key' => 'url',           'label' => 'Canonical URL',      'type' => 'url',      'required' => false, 'help' => 'Defaults to permalink.' ),
			array( 'key' => 'image',         'label' => 'Image URL',          'type' => 'url',      'required' => false, 'help' => 'Defaults to featured image. Google requires 1200×630px minimum for rich results.' ),
			array( 'key' => 'datePublished', 'label' => 'Date published',     'type' => 'date',     'required' => false, 'help' => 'ISO 8601 format. Defaults to post publish date.' ),
			array( 'key' => 'dateModified',  'label' => 'Date modified',      'type' => 'date',     'required' => false, 'help' => 'ISO 8601. Defaults to post modified date.' ),
		);
	}

	/**
	 * Return field definitions for the given schema @type.
	 * Returns common fields + type-specific fields.
	 *
	 * @param string $type Schema @type, e.g. 'Recipe'.
	 * @return array<int,array>
	 */
	public static function for_type( string $type ): array {
		$specific = self::specific_fields( $type );
		return array_merge( self::common(), $specific );
	}

	/**
	 * Type-specific field definitions.
	 */
	private static function specific_fields( string $type ): array {
		switch ( $type ) {

			// ── Article family ───────────────────────────────────────────
			case 'Article':
			case 'BlogPosting':
			case 'NewsArticle':
			case 'TechArticle':
				return array(
					array( 'key' => 'headline',        'label' => 'Headline',          'type' => 'text',  'required' => true,  'help' => 'Max 110 chars. Defaults to post title.' ),
					array( 'key' => 'author_name',     'label' => 'Author name',       'type' => 'text',  'required' => true,  'help' => 'Defaults to post author display name.' ),
					array( 'key' => 'author_url',      'label' => 'Author URL',        'type' => 'url',   'required' => false, 'help' => 'Link to author bio or LinkedIn profile.' ),
					array( 'key' => 'publisher_name',  'label' => 'Publisher name',    'type' => 'text',  'required' => true,  'help' => 'Defaults to site name from Settings.' ),
					array( 'key' => 'publisher_logo',  'label' => 'Publisher logo URL','type' => 'url',   'required' => false, 'help' => 'Defaults to logo from AEOmatic Settings.' ),
					array( 'key' => 'articleSection',  'label' => 'Article section',   'type' => 'text',  'required' => false, 'help' => 'e.g. "Technology", "Health". Defaults to primary category.' ),
					array( 'key' => 'wordCount',       'label' => 'Word count',        'type' => 'number','required' => false, 'help' => 'Auto-counted from content.' ),
				);

			// ── Recipe ───────────────────────────────────────────────────
			case 'Recipe':
				return array(
					array( 'key' => 'author_name',       'label' => 'Recipe author',    'type' => 'text',     'required' => true,  'help' => 'Defaults to post author.' ),
					array( 'key' => 'prepTime',          'label' => 'Prep time',        'type' => 'text',     'required' => false, 'help' => 'ISO 8601 duration, e.g. PT15M (15 min).' ),
					array( 'key' => 'cookTime',          'label' => 'Cook time',        'type' => 'text',     'required' => false, 'help' => 'ISO 8601, e.g. PT30M.' ),
					array( 'key' => 'totalTime',         'label' => 'Total time',       'type' => 'text',     'required' => false, 'help' => 'ISO 8601, e.g. PT45M.' ),
					array( 'key' => 'recipeYield',       'label' => 'Yield / Servings', 'type' => 'text',     'required' => false, 'help' => 'e.g. "4 servings" or "12 cookies".' ),
					array( 'key' => 'recipeCategory',    'label' => 'Recipe category',  'type' => 'text',     'required' => false, 'help' => 'e.g. "Dessert", "Main course".' ),
					array( 'key' => 'recipeCuisine',     'label' => 'Cuisine',          'type' => 'text',     'required' => false, 'help' => 'e.g. "Italian", "Indian".' ),
					array( 'key' => 'recipeIngredient',  'label' => 'Ingredients',      'type' => 'repeater', 'required' => true,  'help' => 'One ingredient per line. Auto-detected from content.' ),
					array( 'key' => 'recipeInstructions','label' => 'Instructions',     'type' => 'repeater', 'required' => true,  'help' => 'One step per line. Auto-detected from content.' ),
					array( 'key' => 'keywords',          'label' => 'Keywords',         'type' => 'text',     'required' => false, 'help' => 'Comma-separated keywords.' ),
					array( 'key' => 'nutrition_calories','label' => 'Calories',         'type' => 'text',     'required' => false, 'help' => 'e.g. "250 calories".' ),
				);

			// ── HowTo ────────────────────────────────────────────────────
			case 'HowTo':
				return array(
					array( 'key' => 'step',       'label' => 'Steps',        'type' => 'repeater', 'required' => true,  'help' => 'One step per line. Auto-detected from numbered headings.' ),
					array( 'key' => 'totalTime',  'label' => 'Total time',   'type' => 'text',     'required' => false, 'help' => 'ISO 8601, e.g. PT1H.' ),
					array( 'key' => 'tool',       'label' => 'Tools needed', 'type' => 'repeater', 'required' => false, 'help' => 'One tool per line.' ),
					array( 'key' => 'supply',     'label' => 'Supplies',     'type' => 'repeater', 'required' => false, 'help' => 'One supply per line.' ),
					array( 'key' => 'estimatedCost', 'label' => 'Estimated cost', 'type' => 'text', 'required' => false, 'help' => 'e.g. "$50".' ),
				);

			// ── FAQPage ──────────────────────────────────────────────────
			case 'FAQPage':
				return array(
					array( 'key' => 'faq_pairs', 'label' => 'Q&A pairs', 'type' => 'faq_repeater', 'required' => true, 'help' => 'Auto-detected from AEO FAQ blocks and question-style headings. Add or edit manually.' ),
				);

			// ── Review ───────────────────────────────────────────────────
			case 'Review':
				return array(
					array( 'key' => 'itemReviewed_name',   'label' => 'Item reviewed',    'type' => 'text',   'required' => true,  'help' => 'Name of the product or service being reviewed. Auto-detected from title.' ),
					array( 'key' => 'itemReviewed_type',   'label' => 'Item type',        'type' => 'select', 'required' => false, 'help' => 'What kind of thing is being reviewed.',
						'options' => array( 'Thing' => 'Thing (generic)', 'Product' => 'Product', 'SoftwareApplication' => 'Software', 'LocalBusiness' => 'Local business', 'Book' => 'Book', 'Movie' => 'Movie' ) ),
					array( 'key' => 'reviewRating_value',  'label' => 'Rating (out of 5)','type' => 'number', 'required' => true,  'help' => 'e.g. 4.5. Auto-detected from content.' ),
					array( 'key' => 'reviewRating_best',   'label' => 'Best rating',      'type' => 'number', 'required' => false, 'help' => 'Max score (default 5).' ),
					array( 'key' => 'author_name',         'label' => 'Reviewer name',    'type' => 'text',   'required' => true,  'help' => 'Defaults to post author.' ),
					array( 'key' => 'reviewBody',          'label' => 'Review body',      'type' => 'textarea','required' => false, 'help' => 'Summary of the review.' ),
				);

			// ── Product ──────────────────────────────────────────────────
			case 'Product':
				return array(
					array( 'key' => 'brand_name',    'label' => 'Brand',              'type' => 'text',   'required' => false, 'help' => 'Product brand name.' ),
					array( 'key' => 'sku',           'label' => 'SKU',                'type' => 'text',   'required' => false, 'help' => 'Stock keeping unit. Auto-filled from WooCommerce.' ),
					array( 'key' => 'offer_price',   'label' => 'Price',              'type' => 'text',   'required' => false, 'help' => 'e.g. 29.99. Auto-filled from WooCommerce.' ),
					array( 'key' => 'offer_currency','label' => 'Currency',           'type' => 'text',   'required' => false, 'help' => 'ISO 4217 code, e.g. USD, INR, EUR.' ),
					array( 'key' => 'offer_availability','label' => 'Availability',   'type' => 'select', 'required' => false, 'help' => 'Stock status.',
						'options' => array( 'InStock' => 'In stock', 'OutOfStock' => 'Out of stock', 'PreOrder' => 'Pre-order', 'Discontinued' => 'Discontinued' ) ),
					array( 'key' => 'aggregateRating_value','label' => 'Average rating','type' => 'number','required' => false, 'help' => 'e.g. 4.2. Auto-filled from WooCommerce.' ),
					array( 'key' => 'aggregateRating_count','label' => 'Review count', 'type' => 'number','required' => false, 'help' => 'Number of reviews. Auto-filled from WooCommerce.' ),
				);

			// ── Event ────────────────────────────────────────────────────
			case 'Event':
				return array(
					array( 'key' => 'startDate',           'label' => 'Start date & time', 'type' => 'date',   'required' => true,  'help' => 'ISO 8601, e.g. 2026-06-15T18:00. Auto-detected from content.' ),
					array( 'key' => 'endDate',             'label' => 'End date & time',   'type' => 'date',   'required' => false, 'help' => 'ISO 8601.' ),
					array( 'key' => 'location_name',       'label' => 'Venue name',        'type' => 'text',   'required' => false, 'help' => 'Auto-detected from content.' ),
					array( 'key' => 'location_address',    'label' => 'Venue address',     'type' => 'text',   'required' => false, 'help' => 'Full street address.' ),
					array( 'key' => 'organizer_name',      'label' => 'Organizer',         'type' => 'text',   'required' => false, 'help' => 'Defaults to site name.' ),
					array( 'key' => 'eventAttendanceMode', 'label' => 'Attendance mode',   'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( 'OfflineEventAttendanceMode' => 'In-person', 'OnlineEventAttendanceMode' => 'Online', 'MixedEventAttendanceMode' => 'Hybrid' ) ),
					array( 'key' => 'eventStatus',         'label' => 'Event status',      'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( 'EventScheduled' => 'Scheduled', 'EventCancelled' => 'Cancelled', 'EventPostponed' => 'Postponed', 'EventRescheduled' => 'Rescheduled' ) ),
					array( 'key' => 'offer_price',         'label' => 'Ticket price',      'type' => 'text',   'required' => false, 'help' => 'e.g. 0 for free events, 25 for paid.' ),
					array( 'key' => 'offer_currency',      'label' => 'Currency',          'type' => 'text',   'required' => false, 'help' => 'ISO 4217, e.g. USD.' ),
				);

			// ── JobPosting ───────────────────────────────────────────────
			case 'JobPosting':
				return array(
					array( 'key' => 'title',               'label' => 'Job title',         'type' => 'text',   'required' => true,  'help' => 'Defaults to post title.' ),
					array( 'key' => 'hiringOrg_name',      'label' => 'Hiring company',    'type' => 'text',   'required' => true,  'help' => 'Defaults to site name.' ),
					array( 'key' => 'hiringOrg_url',       'label' => 'Company website',   'type' => 'url',    'required' => false, 'help' => '' ),
					array( 'key' => 'jobLocation_address', 'label' => 'Job location',      'type' => 'text',   'required' => false, 'help' => 'e.g. "Mumbai, India" or "Remote".' ),
					array( 'key' => 'employmentType',      'label' => 'Employment type',   'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( 'FULL_TIME' => 'Full-time', 'PART_TIME' => 'Part-time', 'CONTRACTOR' => 'Contractor', 'TEMPORARY' => 'Temporary', 'INTERN' => 'Intern', 'VOLUNTEER' => 'Volunteer', 'PER_DIEM' => 'Per diem', 'OTHER' => 'Other' ) ),
					array( 'key' => 'salary_min',          'label' => 'Min salary',        'type' => 'number', 'required' => false, 'help' => 'Auto-detected from content.' ),
					array( 'key' => 'salary_max',          'label' => 'Max salary',        'type' => 'number', 'required' => false, 'help' => '' ),
					array( 'key' => 'salary_currency',     'label' => 'Salary currency',   'type' => 'text',   'required' => false, 'help' => 'ISO 4217, e.g. INR, USD.' ),
					array( 'key' => 'salary_unit',         'label' => 'Pay period',        'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( 'YEAR' => 'Per year', 'MONTH' => 'Per month', 'HOUR' => 'Per hour' ) ),
					array( 'key' => 'datePosted',          'label' => 'Date posted',       'type' => 'date',   'required' => true,  'help' => 'Defaults to publish date.' ),
					array( 'key' => 'validThrough',        'label' => 'Application deadline','type' => 'date', 'required' => false, 'help' => '' ),
				);

			// ── Course ───────────────────────────────────────────────────
			case 'Course':
				return array(
					array( 'key' => 'provider_name',      'label' => 'Provider / Instructor','type' => 'text', 'required' => true,  'help' => 'Defaults to site name.' ),
					array( 'key' => 'provider_url',       'label' => 'Provider URL',        'type' => 'url',  'required' => false, 'help' => '' ),
					array( 'key' => 'offer_price',        'label' => 'Course price',        'type' => 'text', 'required' => false, 'help' => 'e.g. 0 for free, 49 for paid.' ),
					array( 'key' => 'offer_currency',     'label' => 'Currency',            'type' => 'text', 'required' => false, 'help' => 'ISO 4217.' ),
					array( 'key' => 'offer_category',     'label' => 'Price category',      'type' => 'select','required' => false, 'help' => '',
						'options' => array( 'free' => 'Free', 'paid' => 'Paid', 'freemium' => 'Freemium' ) ),
					array( 'key' => 'courseCode',         'label' => 'Course code',         'type' => 'text', 'required' => false, 'help' => '' ),
					array( 'key' => 'educationalLevel',   'label' => 'Level',               'type' => 'select','required' => false, 'help' => '',
						'options' => array( 'Beginner' => 'Beginner', 'Intermediate' => 'Intermediate', 'Advanced' => 'Advanced' ) ),
				);

			// ── LocalBusiness ────────────────────────────────────────────
			case 'LocalBusiness':
				return array(
					array( 'key' => 'telephone',           'label' => 'Phone number',     'type' => 'text',     'required' => false, 'help' => 'Include country code, e.g. +91 98765 43210.' ),
					array( 'key' => 'address_street',      'label' => 'Street address',   'type' => 'text',     'required' => false, 'help' => 'Auto-detected from content.' ),
					array( 'key' => 'address_city',        'label' => 'City',             'type' => 'text',     'required' => false, 'help' => '' ),
					array( 'key' => 'address_region',      'label' => 'State / Region',   'type' => 'text',     'required' => false, 'help' => '' ),
					array( 'key' => 'address_postal',      'label' => 'Postcode / ZIP',   'type' => 'text',     'required' => false, 'help' => '' ),
					array( 'key' => 'address_country',     'label' => 'Country',          'type' => 'text',     'required' => false, 'help' => 'ISO 3166, e.g. IN, US, GB.' ),
					array( 'key' => 'openingHours',        'label' => 'Opening hours',    'type' => 'repeater', 'required' => false, 'help' => 'One line per day, e.g. "Mo-Fr 09:00-18:00".' ),
					array( 'key' => 'priceRange',          'label' => 'Price range',      'type' => 'text',     'required' => false, 'help' => 'e.g. "$$", "₹500-₹2000".' ),
					array( 'key' => 'aggregateRating_value','label' => 'Average rating',  'type' => 'number',   'required' => false, 'help' => 'e.g. 4.3.' ),
					array( 'key' => 'aggregateRating_count','label' => 'Review count',    'type' => 'number',   'required' => false, 'help' => '' ),
				);

			// ── Service ──────────────────────────────────────────────────
			case 'Service':
				return array(
					array( 'key' => 'serviceType',      'label' => 'Service type',    'type' => 'text',   'required' => false, 'help' => 'e.g. "Web Design", "Plumbing".' ),
					array( 'key' => 'provider_name',    'label' => 'Provider name',   'type' => 'text',   'required' => false, 'help' => 'Defaults to site name.' ),
					array( 'key' => 'areaServed',       'label' => 'Area served',     'type' => 'text',   'required' => false, 'help' => 'e.g. "Mumbai", "India".' ),
					array( 'key' => 'offer_price',      'label' => 'Starting price',  'type' => 'text',   'required' => false, 'help' => 'e.g. "From $99".' ),
					array( 'key' => 'offer_currency',   'label' => 'Currency',        'type' => 'text',   'required' => false, 'help' => '' ),
				);

			// ── AboutPage / ContactPage / WebPage ────────────────────────
			case 'AboutPage':
			case 'ContactPage':
			case 'WebPage':
			case 'CollectionPage':
			case 'ProfilePage':
				return array(
					array( 'key' => 'author_name',   'label' => 'Author',      'type' => 'text', 'required' => false, 'help' => 'Defaults to post author.' ),
					array( 'key' => 'keywords',      'label' => 'Keywords',    'type' => 'text', 'required' => false, 'help' => 'Comma-separated.' ),
					array( 'key' => 'breadcrumb',    'label' => 'Breadcrumb',  'type' => 'text', 'required' => false, 'help' => 'e.g. "Home > Services > Web Design".' ),
				);

			// ── Book ─────────────────────────────────────────────────────
			case 'Book':
				return array(
					array( 'key' => 'author_name', 'label' => 'Author name',  'type' => 'text', 'required' => true,  'help' => '' ),
					array( 'key' => 'isbn',        'label' => 'ISBN',         'type' => 'text', 'required' => false, 'help' => '13-digit ISBN.' ),
					array( 'key' => 'numberOfPages','label' => 'Page count',  'type' => 'number','required' => false, 'help' => '' ),
					array( 'key' => 'publisher_name','label' => 'Publisher',  'type' => 'text', 'required' => false, 'help' => '' ),
					array( 'key' => 'bookFormat',  'label' => 'Format',       'type' => 'select','required' => false, 'help' => '',
						'options' => array( 'Paperback' => 'Paperback', 'Hardcover' => 'Hardcover', 'EBook' => 'E-book', 'AudioBook' => 'Audiobook' ) ),
				);

			default:
				return array();
		}
	}

	/**
	 * Return an associative map of key → default value for the given type,
	 * populated from the auto-extracted post content.
	 *
	 * @param WP_Post $post
	 * @param string  $type
	 * @param array   $settings  Plugin schema settings.
	 * @return array<string,mixed>
	 */
	public static function extract_defaults( WP_Post $post, string $type, array $settings ): array {
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$text      = $extracted['text'];
		$html      = $extracted['html'];
		$author    = get_userdata( (int) $post->post_author );

		$defaults  = array();

		// Common fields.
		$defaults['name']          = wp_strip_all_tags( get_the_title( $post ) );
		$defaults['url']           = get_permalink( $post );
		$defaults['datePublished'] = get_the_date( 'c', $post );
		$defaults['dateModified']  = get_the_modified_date( 'c', $post );

		$excerpt = wp_strip_all_tags( get_the_excerpt( $post ) );
		if ( ! $excerpt && $extracted['text'] ) {
			$excerpt = self::truncate( $extracted['text'], 300 );
		}
		$defaults['description'] = $excerpt;

		$thumb_id = get_post_thumbnail_id( $post );
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'full' );
			$defaults['image'] = $src ? $src[0] : '';
		}

		if ( $author ) {
			$defaults['author_name'] = $author->display_name;
			$defaults['author_url']  = get_author_posts_url( $author->ID );
		}

		$defaults['publisher_name'] = ! empty( $settings['org_name'] ) ? $settings['org_name'] : get_bloginfo( 'name' );
		$defaults['publisher_logo'] = $settings['org_logo'] ?? '';

		// Type-specific.
		switch ( $type ) {

			case 'Article':
			case 'BlogPosting':
			case 'NewsArticle':
			case 'TechArticle':
				$defaults['headline'] = wp_strip_all_tags( get_the_title( $post ) );
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$defaults['articleSection'] = $cats[0]->name;
				}
				$defaults['wordCount'] = $extracted['word_count'] ?? 0;
				break;

			case 'Recipe':
				$ingredients = AEOMATIC_Property_Extractors::ingredients( $post, $text, $html );
				$defaults['recipeIngredient']   = $ingredients ?: array();
				$instructions = AEOMATIC_Property_Extractors::instructions( $post, $text, $html );
				$defaults['recipeInstructions'] = $instructions
					? array_map( static fn( $s ) => $s['text'] ?? '', $instructions )
					: array();
				$defaults['prepTime']    = AEOMATIC_Property_Extractors::duration( $text, 'prep' ) ?? '';
				$defaults['cookTime']    = AEOMATIC_Property_Extractors::duration( $text, 'cook' ) ?? '';
				$defaults['totalTime']   = AEOMATIC_Property_Extractors::duration( $text, 'total' ) ?? '';
				$defaults['recipeYield'] = AEOMATIC_Property_Extractors::recipe_yield( $post, $text ) ?? '';
				break;

			case 'HowTo':
				$instructions = AEOMATIC_Property_Extractors::instructions( $post, $text, $html );
				$defaults['step'] = $instructions
					? array_map( static fn( $s ) => $s['text'] ?? '', $instructions )
					: array();
				$defaults['totalTime'] = AEOMATIC_Property_Extractors::duration( $text, 'total' ) ?? '';
				break;

			case 'FAQPage':
				$defaults['faq_pairs'] = $extracted['faqs'] ?? array();
				break;

			case 'Review':
				$rating = AEOMATIC_Property_Extractors::rating( $post, $text );
				$defaults['reviewRating_value'] = $rating ? $rating['value'] : '';
				$defaults['reviewRating_best']  = $rating ? $rating['best']  : 5;
				$defaults['itemReviewed_name']  = self::extract_review_subject( get_the_title( $post ) );
				$defaults['itemReviewed_type']  = 'Product';
				break;

			case 'Event':
				$defaults['startDate']           = AEOMATIC_Property_Extractors::event_start( $post, $text ) ?? '';
				$defaults['endDate']             = AEOMATIC_Property_Extractors::event_end( $post ) ?? '';
				$loc = AEOMATIC_Property_Extractors::event_location( $post );
				$defaults['location_name']       = $loc['name'] ?? '';
				$defaults['location_address']    = $loc['address'] ?? '';
				$defaults['organizer_name']      = get_bloginfo( 'name' );
				$defaults['eventAttendanceMode'] = 'OfflineEventAttendanceMode';
				$defaults['eventStatus']         = 'EventScheduled';
				break;

			case 'JobPosting':
				$salary = AEOMATIC_Property_Extractors::job_salary( $post, $text );
				if ( $salary ) {
					$val = $salary['value'] ?? null;
					if ( is_array( $val ) ) {
						$defaults['salary_min']      = $val['minValue'] ?? '';
						$defaults['salary_max']      = $val['maxValue'] ?? '';
						$defaults['salary_unit']     = $val['unitText'] ?? 'YEAR';
					}
					$defaults['salary_currency'] = $salary['currency'] ?? 'USD';
				}
				$defaults['employmentType']      = AEOMATIC_Property_Extractors::employment_type( $post, $text ) ?? '';
				$defaults['title']               = get_the_title( $post );
				$defaults['hiringOrg_name']      = get_bloginfo( 'name' );
				$defaults['hiringOrg_url']       = home_url( '/' );
				$defaults['datePosted']          = get_the_date( 'c', $post );
				break;

			case 'LocalBusiness':
				$addr = AEOMATIC_Property_Extractors::address( $post, $text );
				if ( $addr ) {
					$defaults['address_street']  = $addr['streetAddress']   ?? '';
					$defaults['address_city']    = $addr['addressLocality'] ?? '';
					$defaults['address_region']  = $addr['addressRegion']   ?? '';
					$defaults['address_postal']  = $addr['postalCode']      ?? '';
					$defaults['address_country'] = $addr['addressCountry']  ?? '';
				}
				$defaults['telephone']    = AEOMATIC_Property_Extractors::telephone( $post, $text ) ?? '';
				$hours = AEOMATIC_Property_Extractors::opening_hours( $post, $text );
				$defaults['openingHours'] = $hours ?? array();
				break;

			case 'Course':
				$defaults['provider_name'] = get_bloginfo( 'name' );
				$defaults['provider_url']  = home_url( '/' );
				$defaults['offer_category']= 'paid';
				break;

			case 'Product':
				if ( function_exists( 'wc_get_product' ) ) {
					$product = wc_get_product( $post->ID );
					if ( $product ) {
						$defaults['sku']                    = $product->get_sku();
						$defaults['offer_price']            = $product->get_price();
						$defaults['offer_currency']         = get_woocommerce_currency();
						$defaults['offer_availability']     = $product->is_in_stock() ? 'InStock' : 'OutOfStock';
						$defaults['aggregateRating_value']  = $product->get_average_rating();
						$defaults['aggregateRating_count']  = $product->get_rating_count();
						$defaults['brand_name']             = $product->get_attribute( 'brand' ) ?: $product->get_attribute( 'pa_brand' );
					}
				}
				break;
		}

		return $defaults;
	}

	private static function truncate( string $text, int $limit ): string {
		if ( strlen( $text ) <= $limit ) {
			return $text;
		}
		$cut = substr( $text, 0, $limit );
		$pos = strrpos( $cut, ' ' );
		return ( false !== $pos ? substr( $cut, 0, $pos ) : $cut ) . '…';
	}

	private static function extract_review_subject( string $title ): string {
		$cleaned = preg_replace( '/\b(review(s)?|hands[\s-]on|first impression|verdict|tested)\b/i', '', $title );
		return trim( preg_replace( '/\s+/', ' ', $cleaned ?? '' ), " \t\n\r:-—–" );
	}
}
