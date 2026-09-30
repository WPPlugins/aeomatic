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
			array( 'key' => 'image',         'label' => 'Image URL',          'type' => 'url',      'required' => false, 'help' => 'Defaults to featured image. Use at least 50,000 pixels in total (e.g. 1200×675) at 16:9, 4:3 or 1:1.' ),
			array( 'key' => 'datePublished', 'label' => 'Date published',     'type' => 'date',     'required' => false, 'help' => 'ISO 8601 format. Defaults to post publish date.' ),
			array( 'key' => 'dateModified',  'label' => 'Date modified',      'type' => 'date',     'required' => false, 'help' => 'ISO 8601. Defaults to post modified date.' ),
		);
	}

	/**
	 * Properties Google treats as required for each type — missing one makes
	 * Search Console report the item invalid. Types not listed require only
	 * a name. Article has no required properties at all, so validation will
	 * not protect it; the recommended fields are the real specification.
	 *
	 * @return array<string,string[]>
	 */
	private static function required_map(): array {
		return array(
			'Article'             => array(),
			'BlogPosting'         => array(),
			'NewsArticle'         => array(),
			'TechArticle'         => array(),
			'WebPage'             => array(),
			'AboutPage'           => array(),
			'ContactPage'         => array(),
			'CollectionPage'      => array(),
			'ProfilePage'         => array( 'mainEntity_name' ),
			'Recipe'              => array( 'name', 'image' ),
			'Movie'               => array( 'name', 'image' ),
			'Product'             => array( 'name', 'image' ),
			'Review'              => array( 'author_name', 'itemReviewed_name', 'reviewRating_value' ),
			'Event'               => array( 'name', 'startDate', 'location_name', 'location_address' ),
			'JobPosting'          => array( 'title', 'description', 'datePosted', 'hiringOrg_name', 'jobLocation_address' ),
			'Course'              => array( 'name', 'description' ),
			'LocalBusiness'       => array( 'name', 'address_street' ),
			'SoftwareApplication' => array( 'name', 'offer_price' ),
			'Dataset'             => array( 'name', 'description' ),
		);
	}

	/**
	 * Return field definitions for the given schema @type.
	 * Returns common fields + type-specific fields, with each field's
	 * `required` flag set from Google's required-property tier.
	 *
	 * @param string $type Schema @type, e.g. 'Recipe'.
	 * @return array<int,array>
	 */
	public static function for_type( string $type ): array {
		$fields   = array_merge( self::common(), self::specific_fields( $type ) );
		$map      = self::required_map();
		$required = $map[ $type ] ?? array( 'name' );
		foreach ( $fields as &$field ) {
			$field['required'] = in_array( $field['key'], $required, true );

			// Google caps the dataset description at 50–5,000 characters and
			// needs the full job description, not a summary.
			if ( 'description' === $field['key'] && 'Dataset' === $type ) {
				$field['help'] = 'Required: 50 to 5,000 characters.';
			} elseif ( 'description' === $field['key'] && 'JobPosting' === $type ) {
				$field['help'] = 'Required: the full job description, not a summary. Defaults to the post content.';
			}
		}
		unset( $field );

		return $fields;
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
					array( 'key' => 'headline',        'label' => 'Headline',          'type' => 'text',  'required' => false, 'help' => 'Max 110 chars. Defaults to post title.' ),
					array( 'key' => 'author_name',     'label' => 'Author name',       'type' => 'text',  'required' => false, 'help' => 'The person\'s name only — no job title or publisher name. Defaults to post author.' ),
					array( 'key' => 'author_url',      'label' => 'Author URL',        'type' => 'url',   'required' => false, 'help' => 'Link to author bio or LinkedIn profile.' ),
					array( 'key' => 'publisher_name',  'label' => 'Publisher name',    'type' => 'text',  'required' => false, 'help' => 'Defaults to site name from Settings.' ),
					array( 'key' => 'publisher_logo',  'label' => 'Publisher logo URL','type' => 'url',   'required' => false, 'help' => 'Defaults to logo from AEOmatic Settings.' ),
					array( 'key' => 'articleSection',  'label' => 'Article section',   'type' => 'text',  'required' => false, 'help' => 'e.g. "Technology", "Health". Defaults to primary category.' ),
					array( 'key' => 'wordCount',       'label' => 'Word count',        'type' => 'number','required' => false, 'help' => 'Auto-counted from content.' ),
					array( 'key' => 'isAccessibleForFree', 'label' => 'Paywalled / gated', 'type' => 'select', 'required' => false, 'help' => 'Declares gated content honestly instead of as cloaking. The gated text must still be in the HTML Googlebot receives.',
						'options' => array( '' => 'Free to read', 'false' => 'Gated — part of the page is behind a paywall or form' ) ),
					array( 'key' => 'paywall_selector', 'label' => 'Gated section CSS class', 'type' => 'text', 'required' => false, 'help' => 'A single class selector such as .paywall, on the element that wraps the gated text. Only used when the page is gated and that class is in the content.' ),
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

			// HowTo (removed by Google in 2023) and FAQPage (discontinued in
			// Search in May 2026) are no longer offered: neither produces
			// anything. The FAQ block still renders visible Q&A HTML, which is
			// what featured snippets read.

			// ── Review ───────────────────────────────────────────────────
			case 'Review':
				return array(
					array( 'key' => 'itemReviewed_name',   'label' => 'Item reviewed',    'type' => 'text',   'required' => true,  'help' => 'Name of the product or service being reviewed. Auto-detected from title. Stars are not shown for a business reviewing itself.' ),
					array( 'key' => 'itemReviewed_type',   'label' => 'Item type',        'type' => 'select', 'required' => false, 'help' => 'What kind of thing is being reviewed.',
						'options' => array( 'Product' => 'Product', 'SoftwareApplication' => 'Software', 'LocalBusiness' => 'Local business', 'Organization' => 'Organization', 'Book' => 'Book', 'Movie' => 'Movie', 'Course' => 'Course', 'Event' => 'Event', 'Recipe' => 'Recipe', 'Thing' => 'Thing (generic — not eligible for stars)' ) ),
					array( 'key' => 'reviewRating_value',  'label' => 'Rating (out of 5)','type' => 'number', 'required' => true,  'help' => 'e.g. 4.5. Auto-detected from content.' ),
					array( 'key' => 'reviewRating_best',   'label' => 'Best rating',      'type' => 'number', 'required' => false, 'help' => 'Max score (default 5).' ),
					array( 'key' => 'author_name',         'label' => 'Reviewer name',    'type' => 'text',   'required' => true,  'help' => 'Defaults to post author. Under 100 characters.' ),
					array( 'key' => 'reviewBody',          'label' => 'Review body',      'type' => 'textarea','required' => false, 'help' => 'Summary of the review.' ),
				);

			// ── Product ──────────────────────────────────────────────────
			case 'Product':
				return array(
					array( 'key' => 'brand_name',    'label' => 'Brand',              'type' => 'text',   'required' => false, 'help' => 'Product brand name.' ),
					array( 'key' => 'sku',           'label' => 'SKU',                'type' => 'text',   'required' => false, 'help' => 'Stock keeping unit. Auto-filled from WooCommerce.' ),
					array( 'key' => 'gtin',          'label' => 'GTIN / EAN / UPC',   'type' => 'text',   'required' => false, 'help' => 'Global trade item number, 8 to 14 digits.' ),
					array( 'key' => 'mpn',           'label' => 'MPN',                'type' => 'text',   'required' => false, 'help' => 'Manufacturer part number.' ),
					array( 'key' => 'category',      'label' => 'Category',           'type' => 'text',   'required' => false, 'help' => 'e.g. "Apparel > Shoes". Defaults to the product category.' ),
					array( 'key' => 'offer_price',   'label' => 'Price',              'type' => 'text',   'required' => false, 'help' => 'e.g. 29.99. Auto-filled from WooCommerce.' ),
					array( 'key' => 'offer_currency','label' => 'Currency',           'type' => 'text',   'required' => false, 'help' => 'ISO 4217 code, e.g. USD, INR, EUR.' ),
					array( 'key' => 'offer_availability','label' => 'Availability',   'type' => 'select', 'required' => false, 'help' => 'Stock status.',
						'options' => array( 'InStock' => 'In stock', 'OutOfStock' => 'Out of stock', 'PreOrder' => 'Pre-order', 'BackOrder' => 'Back order', 'Discontinued' => 'Discontinued' ) ),
					array( 'key' => 'offer_itemCondition','label' => 'Condition',     'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( '' => 'Not stated', 'NewCondition' => 'New', 'UsedCondition' => 'Used', 'RefurbishedCondition' => 'Refurbished', 'DamagedCondition' => 'Damaged' ) ),
					array( 'key' => 'offer_url',     'label' => 'Offer URL',          'type' => 'url',    'required' => false, 'help' => 'Where the product can be bought. Defaults to this page.' ),
					array( 'key' => 'offer_priceValidUntil','label' => 'Price valid until','type' => 'date','required' => false, 'help' => 'ISO 8601 date, e.g. 2026-12-31. Leave empty for no end date.' ),
					array( 'key' => 'aggregateRating_value','label' => 'Average rating','type' => 'number','required' => false, 'help' => 'e.g. 4.2. Auto-filled from WooCommerce customer reviews.' ),
					array( 'key' => 'aggregateRating_count','label' => 'Review count', 'type' => 'number','required' => false, 'help' => 'Number of reviews. Auto-filled from WooCommerce.' ),
				);

			// ── SoftwareApplication ──────────────────────────────────────
			case 'SoftwareApplication':
				return array(
					array( 'key' => 'applicationCategory', 'label' => 'Category',        'type' => 'select', 'required' => false, 'help' => '',
						'options' => array(
							'BusinessApplication' => 'Business', 'DeveloperApplication' => 'Developer tools', 'DesignApplication' => 'Design',
							'EducationalApplication' => 'Education', 'FinanceApplication' => 'Finance', 'HealthApplication' => 'Health',
							'LifestyleApplication' => 'Lifestyle', 'MultimediaApplication' => 'Multimedia', 'CommunicationApplication' => 'Communication',
							'SecurityApplication' => 'Security', 'UtilitiesApplication' => 'Utilities', 'ReferenceApplication' => 'Reference',
							'ShoppingApplication' => 'Shopping', 'SocialNetworkingApplication' => 'Social', 'TravelApplication' => 'Travel',
							'GameApplication' => 'Game', 'EntertainmentApplication' => 'Entertainment', 'HomeApplication' => 'Home',
							'BrowserApplication' => 'Browser', 'DesktopEnhancementApplication' => 'Desktop enhancement', 'DriverApplication' => 'Driver',
						) ),
					array( 'key' => 'operatingSystem',     'label' => 'Operating system', 'type' => 'text',     'required' => false, 'help' => 'e.g. "Windows 11, macOS 14" or "Web".' ),
					array( 'key' => 'softwareVersion',     'label' => 'Version',          'type' => 'text',     'required' => false, 'help' => '' ),
					array( 'key' => 'offer_price',         'label' => 'Price',            'type' => 'text',     'required' => true,  'help' => 'Required. Enter 0 for a free application — 0 is a real value, not an absence.' ),
					array( 'key' => 'offer_currency',      'label' => 'Currency',         'type' => 'text',     'required' => false, 'help' => 'ISO 4217, e.g. USD.' ),
					array( 'key' => 'featureList',         'label' => 'Features',         'type' => 'repeater', 'required' => false, 'help' => 'One feature per line.' ),
					array( 'key' => 'downloadUrl',         'label' => 'Download URL',     'type' => 'url',      'required' => false, 'help' => '' ),
					array( 'key' => 'aggregateRating_value','label' => 'Average rating',  'type' => 'number',   'required' => false, 'help' => 'Google needs a rating or review for the app card, but a vendor rating its own product is not eligible — only fill this from independent user reviews hosted on this page.' ),
					array( 'key' => 'aggregateRating_count','label' => 'Rating count',    'type' => 'number',   'required' => false, 'help' => '' ),
				);

			// ── Event ────────────────────────────────────────────────────
			case 'Event':
				return array(
					array( 'key' => 'startDate',           'label' => 'Start date & time', 'type' => 'date',   'required' => true,  'help' => 'ISO 8601 with a timezone, e.g. 2026-06-15T18:00+01:00. Keep it even if the event is cancelled or moved.' ),
					array( 'key' => 'endDate',             'label' => 'End date & time',   'type' => 'date',   'required' => false, 'help' => 'ISO 8601.' ),
					array( 'key' => 'previousStartDate',   'label' => 'Previous start date','type' => 'date',  'required' => false, 'help' => 'Only when the event was rescheduled or postponed.' ),
					array( 'key' => 'location_name',       'label' => 'Venue name',        'type' => 'text',   'required' => true,  'help' => 'Auto-detected from content. Events must be at a physical location and open to the public to get the listing.' ),
					array( 'key' => 'location_address',    'label' => 'Venue address',     'type' => 'text',   'required' => true,  'help' => 'Full street address.' ),
					array( 'key' => 'location_url',        'label' => 'Online event URL',  'type' => 'url',    'required' => false, 'help' => 'For online or hybrid events. Online-only events are not eligible for the Google event listing.' ),
					array( 'key' => 'organizer_name',      'label' => 'Organizer',         'type' => 'text',   'required' => false, 'help' => 'Defaults to site name.' ),
					array( 'key' => 'organizer_url',       'label' => 'Organizer URL',     'type' => 'url',    'required' => false, 'help' => 'Defaults to your home page.' ),
					array( 'key' => 'performer_name',      'label' => 'Performer',         'type' => 'text',   'required' => false, 'help' => 'Speaker, band or host.' ),
					array( 'key' => 'eventAttendanceMode', 'label' => 'Attendance mode',   'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( 'OfflineEventAttendanceMode' => 'In-person', 'OnlineEventAttendanceMode' => 'Online', 'MixedEventAttendanceMode' => 'Hybrid' ) ),
					array( 'key' => 'eventStatus',         'label' => 'Event status',      'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( 'EventScheduled' => 'Scheduled', 'EventCancelled' => 'Cancelled', 'EventPostponed' => 'Postponed', 'EventRescheduled' => 'Rescheduled', 'EventMovedOnline' => 'Moved online' ) ),
					array( 'key' => 'offer_price',         'label' => 'Ticket price',      'type' => 'text',   'required' => false, 'help' => 'e.g. 0 for free events, 25 for paid.' ),
					array( 'key' => 'offer_currency',      'label' => 'Currency',          'type' => 'text',   'required' => false, 'help' => 'ISO 4217, e.g. USD.' ),
					array( 'key' => 'offer_url',           'label' => 'Ticket URL',        'type' => 'url',    'required' => false, 'help' => 'Where to book. Defaults to this page.' ),
					array( 'key' => 'offer_availability',  'label' => 'Ticket availability','type' => 'select','required' => false, 'help' => '',
						'options' => array( 'InStock' => 'On sale', 'SoldOut' => 'Sold out', 'PreOrder' => 'Not yet on sale' ) ),
				);

			// ── JobPosting ───────────────────────────────────────────────
			case 'JobPosting':
				return array(
					array( 'key' => 'title',               'label' => 'Job title',         'type' => 'text',   'required' => true,  'help' => 'Defaults to post title.' ),
					array( 'key' => 'hiringOrg_name',      'label' => 'Hiring company',    'type' => 'text',   'required' => true,  'help' => 'Defaults to site name.' ),
					array( 'key' => 'hiringOrg_url',       'label' => 'Company website',   'type' => 'url',    'required' => false, 'help' => '' ),
					array( 'key' => 'jobLocationType',     'label' => 'Remote role',       'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( '' => 'No — at a workplace', 'TELECOMMUTE' => 'Yes — fully remote' ) ),
					array( 'key' => 'jobLocation_address', 'label' => 'Job location',      'type' => 'text',   'required' => true,  'help' => 'e.g. "Mumbai, India". For a fully remote role leave empty and fill the countries below.' ),
					array( 'key' => 'applicantLocation',   'label' => 'Remote: allowed countries', 'type' => 'text', 'required' => false, 'help' => 'Comma-separated, e.g. "United States, Canada". Replaces the job location for remote roles.' ),
					array( 'key' => 'employmentType',      'label' => 'Employment type',   'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( 'FULL_TIME' => 'Full-time', 'PART_TIME' => 'Part-time', 'CONTRACTOR' => 'Contractor', 'TEMPORARY' => 'Temporary', 'INTERN' => 'Intern', 'VOLUNTEER' => 'Volunteer', 'PER_DIEM' => 'Per diem', 'OTHER' => 'Other' ) ),
					array( 'key' => 'salary_min',          'label' => 'Min salary',        'type' => 'number', 'required' => false, 'help' => 'Auto-detected from content.' ),
					array( 'key' => 'salary_max',          'label' => 'Max salary',        'type' => 'number', 'required' => false, 'help' => '' ),
					array( 'key' => 'salary_currency',     'label' => 'Salary currency',   'type' => 'text',   'required' => false, 'help' => 'ISO 4217, e.g. INR, USD.' ),
					array( 'key' => 'salary_unit',         'label' => 'Pay period',        'type' => 'select', 'required' => false, 'help' => '',
						'options' => array( 'YEAR' => 'Per year', 'MONTH' => 'Per month', 'HOUR' => 'Per hour' ) ),
					array( 'key' => 'datePosted',          'label' => 'Date posted',       'type' => 'date',   'required' => true,  'help' => 'Defaults to publish date.' ),
					array( 'key' => 'validThrough',        'label' => 'Application deadline','type' => 'date', 'required' => false, 'help' => 'Remove the page or set a past date once the role is filled.' ),
					array( 'key' => 'directApply',         'label' => 'Apply on this page', 'type' => 'select', 'required' => false, 'help' => 'Applicants must be able to apply without creating an account.',
						'options' => array( '' => 'Not stated', 'true' => 'Yes', 'false' => 'No — applying happens elsewhere' ) ),
					array( 'key' => 'identifier_value',    'label' => 'Job ID',            'type' => 'text',   'required' => false, 'help' => 'Your internal requisition number.' ),
				);

			// ── Course ───────────────────────────────────────────────────
			case 'Course':
				return array(
					array( 'key' => 'provider_name',      'label' => 'Provider / Instructor','type' => 'text', 'required' => false, 'help' => 'Defaults to site name. Google only shows course carousels (3+ courses, English) — single course pages no longer get a rich result.' ),
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
					array( 'key' => 'business_subtype',    'label' => 'Business type',    'type' => 'select',   'required' => false, 'help' => 'Pick the most specific type that fits.',
						'options' => self::local_business_subtypes() ),
					array( 'key' => 'telephone',           'label' => 'Phone number',     'type' => 'text',     'required' => false, 'help' => 'Include country code, e.g. +91 98765 43210.' ),
					array( 'key' => 'address_street',      'label' => 'Street address',   'type' => 'text',     'required' => true,  'help' => 'Auto-detected from content.' ),
					array( 'key' => 'address_city',        'label' => 'City',             'type' => 'text',     'required' => false, 'help' => '' ),
					array( 'key' => 'address_region',      'label' => 'State / Region',   'type' => 'text',     'required' => false, 'help' => '' ),
					array( 'key' => 'address_postal',      'label' => 'Postcode / ZIP',   'type' => 'text',     'required' => false, 'help' => '' ),
					array( 'key' => 'address_country',     'label' => 'Country',          'type' => 'text',     'required' => false, 'help' => 'ISO 3166, e.g. IN, US, GB.' ),
					array( 'key' => 'geo_latitude',        'label' => 'Latitude',         'type' => 'text',     'required' => false, 'help' => 'At least 5 decimal places, e.g. 19.07598.' ),
					array( 'key' => 'geo_longitude',       'label' => 'Longitude',        'type' => 'text',     'required' => false, 'help' => 'At least 5 decimal places, e.g. 72.87766.' ),
					array( 'key' => 'openingHours',        'label' => 'Opening hours',    'type' => 'repeater', 'required' => false, 'help' => 'One line per day range, e.g. "Mo-Fr 09:00-18:00".' ),
					array( 'key' => 'priceRange',          'label' => 'Price range',      'type' => 'text',     'required' => false, 'help' => 'e.g. "$$", "₹500-₹2000".' ),
					array( 'key' => 'servesCuisine',       'label' => 'Cuisine',          'type' => 'text',     'required' => false, 'help' => 'Restaurants only, e.g. "Italian".' ),
					array( 'key' => 'menu',                'label' => 'Menu URL',         'type' => 'url',      'required' => false, 'help' => 'Restaurants only.' ),
					array( 'key' => 'aggregateRating_value','label' => 'Average rating',  'type' => 'number',   'required' => false, 'help' => 'Only if this site reviews other businesses. A business rating itself is not eligible for stars.' ),
					array( 'key' => 'aggregateRating_count','label' => 'Review count',    'type' => 'number',   'required' => false, 'help' => '' ),
				);

			// ── Dataset ──────────────────────────────────────────────────
			case 'Dataset':
				return array(
					array( 'key' => 'creator_name',        'label' => 'Creator',          'type' => 'text',     'required' => false, 'help' => 'Defaults to your organization name.' ),
					array( 'key' => 'license',             'label' => 'Licence URL',      'type' => 'url',      'required' => false, 'help' => 'Use a version-specific URL, e.g. https://creativecommons.org/licenses/by/4.0/.' ),
					array( 'key' => 'identifier',          'label' => 'Identifier',       'type' => 'text',     'required' => false, 'help' => 'DOI or other persistent ID.' ),
					array( 'key' => 'keywords',            'label' => 'Keywords',         'type' => 'text',     'required' => false, 'help' => 'Comma-separated.' ),
					array( 'key' => 'version',             'label' => 'Version',          'type' => 'text',     'required' => false, 'help' => '' ),
					array( 'key' => 'isAccessibleForFree', 'label' => 'Free to access',   'type' => 'select',   'required' => false, 'help' => '',
						'options' => array( 'true' => 'Yes', 'false' => 'No' ) ),
					array( 'key' => 'temporalCoverage',    'label' => 'Time period',      'type' => 'text',     'required' => false, 'help' => 'ISO 8601 interval, e.g. 2020-01-01/2025-12-31.' ),
					array( 'key' => 'spatialCoverage',     'label' => 'Place covered',    'type' => 'text',     'required' => false, 'help' => 'e.g. "India".' ),
					array( 'key' => 'variableMeasured',    'label' => 'Variables measured','type' => 'repeater','required' => false, 'help' => 'One per line.' ),
					array( 'key' => 'measurementTechnique','label' => 'Method',           'type' => 'text',     'required' => false, 'help' => 'How the data was collected.' ),
					array( 'key' => 'citation',            'label' => 'Cite as',          'type' => 'text',     'required' => false, 'help' => 'A citation or DOI for the dataset.' ),
					array( 'key' => 'distribution_url',    'label' => 'Download URL',     'type' => 'url',      'required' => false, 'help' => 'Direct link to the data file.' ),
					array( 'key' => 'distribution_format', 'label' => 'File format',      'type' => 'text',     'required' => false, 'help' => 'MIME type, e.g. text/csv.' ),
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

			// ── ProfilePage ──────────────────────────────────────────────
			case 'ProfilePage':
				return array(
					array( 'key' => 'mainEntity_type',     'label' => 'Profile of',       'type' => 'select',   'required' => false, 'help' => 'Must be affiliated with this website.',
						'options' => array( 'Person' => 'A person', 'Organization' => 'An organization' ) ),
					array( 'key' => 'mainEntity_name',     'label' => 'Name',             'type' => 'text',     'required' => true,  'help' => 'Defaults to the page title on team/staff pages, otherwise the post author.' ),
					array( 'key' => 'mainEntity_jobTitle', 'label' => 'Job title',        'type' => 'text',     'required' => false, 'help' => 'People only.' ),
					array( 'key' => 'mainEntity_description','label' => 'Bio',            'type' => 'textarea', 'required' => false, 'help' => '' ),
					array( 'key' => 'mainEntity_image',    'label' => 'Photo URL',        'type' => 'url',      'required' => false, 'help' => 'A real photograph only — never a placeholder avatar.' ),
					array( 'key' => 'mainEntity_sameAs',   'label' => 'Profiles elsewhere','type' => 'repeater','required' => false, 'help' => 'One URL per line, e.g. LinkedIn.' ),
				);

			// ── AboutPage / ContactPage / WebPage ────────────────────────
			case 'AboutPage':
			case 'ContactPage':
			case 'WebPage':
			case 'CollectionPage':
				return array(
					array( 'key' => 'keywords',      'label' => 'Keywords',    'type' => 'text', 'required' => false, 'help' => 'Comma-separated.' ),
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
	 * LocalBusiness subtypes offered in the editor. Google asks for the most
	 * specific type and does not support additionalType.
	 *
	 * @return array<string,string>
	 */
	public static function local_business_subtypes(): array {
		return array(
			'LocalBusiness'              => 'Local business (generic)',
			'ProfessionalService'        => 'Professional service',
			'LegalService'               => 'Legal service',
			'AccountingService'          => 'Accounting',
			'FinancialService'           => 'Financial service',
			'RealEstateAgent'            => 'Real estate agent',
			'MedicalBusiness'            => 'Medical practice',
			'Dentist'                    => 'Dentist',
			'HealthAndBeautyBusiness'    => 'Health & beauty',
			'Restaurant'                 => 'Restaurant',
			'CafeOrCoffeeShop'           => 'Café',
			'Bakery'                     => 'Bakery',
			'Store'                      => 'Store',
			'AutoRepair'                 => 'Auto repair',
			'HomeAndConstructionBusiness'=> 'Home & construction',
			'Plumber'                    => 'Plumber',
			'Electrician'                => 'Electrician',
			'LodgingBusiness'            => 'Lodging',
			'Hotel'                      => 'Hotel',
			'ChildCare'                  => 'Child care',
			'EntertainmentBusiness'      => 'Entertainment',
			'ExerciseGym'                => 'Gym',
		);
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
				$defaults['organizer_name']      = $defaults['publisher_name'];
				$defaults['organizer_url']       = home_url( '/' );
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
				$defaults['title']               = wp_strip_all_tags( get_the_title( $post ) );
				$defaults['hiringOrg_name']      = $defaults['publisher_name'];
				$defaults['hiringOrg_url']       = home_url( '/' );
				$defaults['datePosted']          = get_the_date( 'c', $post );
				// Google wants the whole description, with basic HTML allowed.
				$full = trim( wp_kses( $html, array( 'p' => array(), 'br' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(), 'strong' => array(), 'em' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array() ) ) );
				if ( '' !== $full ) {
					$defaults['description'] = $full;
				} elseif ( '' !== trim( $text ) ) {
					$defaults['description'] = trim( $text );
				}
				break;

			case 'ProfilePage':
				// On team/staff pages the page is about the person it is titled
				// after; elsewhere it is the author's own profile.
				$is_person_cpt = (bool) preg_match( '/team|staff|person|people|member/i', $post->post_type );
				$defaults['mainEntity_type']        = 'Person';
				$defaults['mainEntity_name']        = $is_person_cpt ? $defaults['name'] : ( $author ? $author->display_name : $defaults['name'] );
				$defaults['mainEntity_description'] = $is_person_cpt ? $excerpt : ( $author ? wp_strip_all_tags( (string) get_user_meta( $author->ID, 'description', true ) ) : '' );
				if ( $is_person_cpt && ! empty( $defaults['image'] ) ) {
					$defaults['mainEntity_image'] = $defaults['image'];
				}
				if ( $author && ! $is_person_cpt ) {
					$defaults['mainEntity_jobTitle'] = (string) get_user_meta( $author->ID, 'aeomatic_job_title', true );
				}
				break;

			case 'SoftwareApplication':
				$price = AEOMATIC_Property_Extractors::meta_value( $post->ID, array( 'edd_price', '_price', 'price' ) );
				if ( null !== $price && is_numeric( $price ) ) {
					$defaults['offer_price'] = (string) $price;
				}
				$defaults['offer_currency'] = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
				$defaults['applicationCategory'] = 'BusinessApplication';
				break;

			case 'Dataset':
				$defaults['creator_name']        = $defaults['publisher_name'];
				$defaults['isAccessibleForFree'] = 'true';
				// Dataset descriptions must run 50–5,000 characters.
				if ( mb_strlen( (string) $defaults['description'] ) < 50 && '' !== trim( $text ) ) {
					$defaults['description'] = self::truncate( trim( $text ), 5000 );
				}
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
						if ( method_exists( $product, 'get_global_unique_id' ) && $product->get_global_unique_id() ) {
							$defaults['gtin'] = $product->get_global_unique_id();
						}
						$terms = get_the_terms( $post->ID, 'product_cat' );
						if ( is_array( $terms ) && $terms ) {
							$defaults['category'] = $terms[0]->name;
						}
					}
				}
				$defaults['offer_url'] = get_permalink( $post );
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
