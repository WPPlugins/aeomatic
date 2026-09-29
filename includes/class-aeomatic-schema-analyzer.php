<?php
/**
 * Schema type analyzer.
 *
 * Inspects a post's actual content (title, body, taxonomy, custom fields,
 * post type) and decides the most accurate schema.org @type to emit.
 *
 * Returns a result with:
 *  - type: chosen schema @type
 *  - confidence: 0–100
 *  - reasons: array of human-readable signals that influenced the decision
 *  - alternatives: array of other plausible types (for the editor UI)
 *
 * The user can override this in the editor sidebar; their override
 * is stored in post meta and respected on every render.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Schema_Analyzer {

	const META_OVERRIDE = '_aeomatic_schema_type';
	const META_ANALYSIS = '_aeomatic_schema_analysis';

	/** Confidence threshold: below this, fall back to the post-type default. */
	const CONFIDENCE_THRESHOLD = 50;

	/**
	 * Request-level cache: post_id → analysis result array.
	 * Avoids re-running the full scoring algorithm on the same post
	 * in a single request (e.g. schema render + audit both call analyze()).
	 *
	 * @var array<int,array>
	 */
	private static array $request_cache = array();

	/**
	 * Recognized schema types this analyzer can pick.
	 *
	 * @var array<string,array{label:string,description:string}>
	 */
	private static $types = array();

	public static function known_types(): array {
		if ( empty( self::$types ) ) {
			self::$types = array(
				'Article'           => array( 'label' => 'Article',          'description' => 'General article or blog post' ),
				'BlogPosting'       => array( 'label' => 'Blog post',        'description' => 'Personal/casual blog post' ),
				'NewsArticle'       => array( 'label' => 'News article',     'description' => 'Time-sensitive news content' ),
				'TechArticle'       => array( 'label' => 'Technical article', 'description' => 'How-to, documentation, technical guide' ),
				'Recipe'            => array( 'label' => 'Recipe',           'description' => 'Cooking recipe with ingredients and steps' ),
				'HowTo'             => array( 'label' => 'How-to guide',     'description' => 'Step-by-step instructional content' ),
				'FAQPage'           => array( 'label' => 'FAQ page',         'description' => 'Page primarily of Q&A pairs' ),
				'QAPage'            => array( 'label' => 'Q&A page',         'description' => 'Single-question discussion' ),
				'Review'            => array( 'label' => 'Review',           'description' => 'Review of a product or service' ),
				'Product'           => array( 'label' => 'Product',          'description' => 'Product listing' ),
				'Service'           => array( 'label' => 'Service',          'description' => 'Service offering page' ),
				'LocalBusiness'     => array( 'label' => 'Local business',   'description' => 'Physical business location' ),
				'Event'             => array( 'label' => 'Event',            'description' => 'Scheduled event' ),
				'Course'            => array( 'label' => 'Course',           'description' => 'Educational course' ),
				'JobPosting'        => array( 'label' => 'Job posting',      'description' => 'Job vacancy' ),
				'Book'              => array( 'label' => 'Book',             'description' => 'Book entry' ),
				'Movie'             => array( 'label' => 'Movie',            'description' => 'Film entry' ),
				'WebPage'           => array( 'label' => 'Web page',         'description' => 'Generic page' ),
				'AboutPage'         => array( 'label' => 'About page',       'description' => 'About/company info page' ),
				'ContactPage'       => array( 'label' => 'Contact page',     'description' => 'Contact info page' ),
				'CollectionPage'    => array( 'label' => 'Collection page',  'description' => 'List or category index' ),
				'ProfilePage'       => array( 'label' => 'Profile page',     'description' => 'Person or author profile' ),
			);
		}
		return self::$types;
	}

	/**
	 * Run the analysis.
	 *
	 * Results are cached per-post for the duration of the HTTP request so
	 * multiple callers (schema render, editor sidebar REST call, audit)
	 * don't re-run the full scoring algorithm on the same post.
	 *
	 * @return array{type:string,confidence:int,reasons:string[],alternatives:array<string,int>}
	 */
	public static function analyze( WP_Post $post ): array {
		if ( isset( self::$request_cache[ $post->ID ] ) ) {
			return self::$request_cache[ $post->ID ];
		}

		// Read persisted analysis from post meta. This avoids re-running the
		// full scoring (which calls ACF's get_field_objects) during wp_head
		// on every frontend render — that side-effect was poisoning the
		// theme's ACF row store and causing repeater rows to render as
		// duplicates of row 0.
		$persisted = get_post_meta( $post->ID, self::META_ANALYSIS, true );
		if ( is_string( $persisted ) && '' !== $persisted ) {
			$decoded = json_decode( $persisted, true );
			if ( is_array( $decoded ) && isset( $decoded['type'] ) ) {
				self::$request_cache[ $post->ID ] = $decoded;
				return $decoded;
			}
		}

		// No cached analysis. Only run the full algorithm in a safe context
		// (admin, REST, cron, CLI) — never during a live frontend render.
		if ( ! self::is_safe_analysis_context() ) {
			// A user-chosen type is a plain meta read, safe anywhere. Without
			// this, every save (which clears the cache) made the front end emit
			// the post-type default instead of the chosen type until the post
			// was next opened in the editor.
			$override = get_post_meta( $post->ID, self::META_OVERRIDE, true );
			if ( $override && isset( self::known_types()[ $override ] ) ) {
				$result = array(
					'type'         => $override,
					'confidence'   => 100,
					'reasons'      => array( __( 'Manually set by user.', 'aeomatic' ) ),
					'alternatives' => array(),
					'source'       => 'user_override',
				);
				self::$request_cache[ $post->ID ] = $result;
				return $result;
			}
			$fallback = array(
				'type'         => self::default_for_post_type( $post->post_type ),
				'confidence'   => 20,
				'reasons'      => array( __( 'Default — analysis cache empty; will be built on next save.', 'aeomatic' ) ),
				'alternatives' => array(),
				'source'       => 'default_pending',
			);
			self::$request_cache[ $post->ID ] = $fallback;
			return $fallback;
		}

		$result = self::run_analysis( $post );

		// Persist for request cache.
		self::$request_cache[ $post->ID ] = $result;

		// Persist to post meta so subsequent frontend renders read this cache.
		update_post_meta( $post->ID, self::META_ANALYSIS, wp_json_encode( $result ) );

		return $result;
	}

	/**
	 * Invalidate the request cache AND the persisted meta cache so the next
	 * safe-context call rebuilds.
	 */
	public static function invalidate_cache( int $post_id ): void {
		unset( self::$request_cache[ $post_id ] );
		delete_post_meta( $post_id, self::META_ANALYSIS );
	}

	/**
	 * Contexts where it's safe to call get_field_objects() — i.e. not during a
	 * live frontend page render where the theme will iterate ACF data after us.
	 */
	private static function is_safe_analysis_context(): bool {
		if ( is_admin() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return false;
	}

	/**
	 * Internal: run the full scoring algorithm without any caching.
	 */
	private static function run_analysis( WP_Post $post ): array {
		// 1. User override always wins.
		$override = get_post_meta( $post->ID, self::META_OVERRIDE, true );
		if ( $override && isset( self::known_types()[ $override ] ) ) {
			return array(
				'type'         => $override,
				'confidence'   => 100,
				'reasons'      => array( __( 'Manually set by user.', 'aeomatic' ) ),
				'alternatives' => array(),
				'source'       => 'user_override',
			);
		}

		// 2. WooCommerce shortcut.
		if ( 'product' === $post->post_type && class_exists( 'WooCommerce' ) ) {
			return array(
				'type'         => 'Product',
				'confidence'   => 100,
				'reasons'      => array( __( 'WooCommerce product post type.', 'aeomatic' ) ),
				'alternatives' => array(),
				'source'       => 'post_type',
			);
		}

		// 3. Pull content for analysis.
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$title     = wp_strip_all_tags( get_the_title( $post ) );
		$slug      = $post->post_name;
		$text      = $extracted['text'];
		$html      = $extracted['html'];

		// 4. Score every candidate type.
		$scores = array_fill_keys( array_keys( self::known_types() ), 0 );
		$reasons_by_type = array_fill_keys( array_keys( self::known_types() ), array() );

		// Apply each rule.
		self::score_post_type( $post, $scores, $reasons_by_type );
		self::score_slug_and_title( $slug, $title, $scores, $reasons_by_type );
		self::score_content_signals( $text, $html, $scores, $reasons_by_type );
		self::score_taxonomy( $post, $scores, $reasons_by_type );
		self::score_acf_hints( $post, $scores, $reasons_by_type );
		self::score_template( $post, $scores, $reasons_by_type );

		// 5. Pick the winner.
		arsort( $scores );
		$top_types = array_keys( $scores );
		$winner    = $top_types[0];
		$top_score = $scores[ $winner ];

		// 6. Confidence calibration.
		// If multiple types scored 0, treat as low-confidence fallback.
		// If winner scored less than threshold, fall back to safe default.
		$default = self::default_for_post_type( $post->post_type );

		if ( $top_score < self::CONFIDENCE_THRESHOLD ) {
			$winner = $default;
			$reasons = array( sprintf(
				/* translators: %s: schema type */
				__( 'Confidence too low — using safe default (%s) for this post type.', 'aeomatic' ),
				$winner
			) );
			$confidence = 30;
		} else {
			$reasons = $reasons_by_type[ $winner ];
			// Cap confidence at 95 unless override.
			$confidence = min( 95, $top_score );
		}

		// 7. Build alternatives list (top 3 non-winners with non-zero scores).
		$alternatives = array();
		foreach ( $scores as $type => $score ) {
			if ( $type === $winner || $score <= 0 ) {
				continue;
			}
			$alternatives[ $type ] = $score;
			if ( count( $alternatives ) >= 3 ) {
				break;
			}
		}

		$result = array(
			'type'         => $winner,
			'confidence'   => $confidence,
			'reasons'      => $reasons,
			'alternatives' => $alternatives,
			'source'       => 'analyzed',
		);

		return $result;
	}

	/**
	 * Get the safe default for a post type (used when confidence is low).
	 */
	public static function default_for_post_type( string $pt ): string {
		// Reuse the existing post type slug → schema mapping.
		if ( class_exists( 'AEOMATIC_Post_Types' ) ) {
			return AEOMATIC_Post_Types::detect_schema_type_by_slug( $pt );
		}
		return 'page' === $pt ? 'WebPage' : 'Article';
	}

	/* ---------------------------------------------------------------
	 * Scoring rules
	 * --------------------------------------------------------------- */

	private static function add_score( array &$scores, array &$reasons, string $type, int $points, string $reason ): void {
		if ( ! isset( $scores[ $type ] ) ) {
			return;
		}
		$scores[ $type ] += $points;
		$reasons[ $type ][] = $reason;
	}

	/**
	 * Post type itself is the strongest signal — but never the only one.
	 */
	private static function score_post_type( WP_Post $post, array &$scores, array &$reasons ): void {
		$pt = $post->post_type;

		$mappings = array(
			'post'         => array( 'Article' => 60, 'BlogPosting' => 30 ),
			'page'         => array( 'WebPage' => 50 ),
			'event'        => array( 'Event' => 90 ),
			'tribe_events' => array( 'Event' => 90 ),
			'course'       => array( 'Course' => 90 ),
			'sfwd-courses' => array( 'Course' => 90 ),
			'job'          => array( 'JobPosting' => 90 ),
			'jobpost'      => array( 'JobPosting' => 90 ),
			'recipe'       => array( 'Recipe' => 90 ),
			'book'         => array( 'Book' => 90 ),
			'review'       => array( 'Review' => 70 ),
			'service'      => array( 'Service' => 80 ),
			'team'         => array( 'ProfilePage' => 70 ),
			'staff'        => array( 'ProfilePage' => 70 ),
			'location'     => array( 'LocalBusiness' => 80 ),
			'docs'         => array( 'TechArticle' => 80 ),
			'documentation'=> array( 'TechArticle' => 80 ),
			'kb'           => array( 'TechArticle' => 70 ),
			'help-center'  => array( 'TechArticle' => 70 ),
			'tutorial'     => array( 'HowTo' => 70 ),
			'faq'          => array( 'FAQPage' => 90 ),
		);

		if ( isset( $mappings[ $pt ] ) ) {
			foreach ( $mappings[ $pt ] as $type => $pts ) {
				self::add_score( $scores, $reasons, $type, $pts,
					/* translators: 1: post type, 2: type points */
					sprintf( __( 'Post type "%1$s" → +%2$d', 'aeomatic' ), $pt, $pts )
				);
			}
		}

		// Pattern-match unknown post types.
		if ( ! isset( $mappings[ $pt ] ) ) {
			if ( false !== stripos( $pt, 'service' ) ) {
				self::add_score( $scores, $reasons, 'Service', 70, sprintf( __( 'Post type slug contains "service" → +70', 'aeomatic' ) ) );
			}
			if ( false !== stripos( $pt, 'product' ) ) {
				self::add_score( $scores, $reasons, 'Product', 70, sprintf( __( 'Post type slug contains "product" → +70', 'aeomatic' ) ) );
			}
			if ( false !== stripos( $pt, 'event' ) ) {
				self::add_score( $scores, $reasons, 'Event', 70, sprintf( __( 'Post type slug contains "event" → +70', 'aeomatic' ) ) );
			}
			if ( false !== stripos( $pt, 'recipe' ) ) {
				self::add_score( $scores, $reasons, 'Recipe', 70, sprintf( __( 'Post type slug contains "recipe" → +70', 'aeomatic' ) ) );
			}
			if ( false !== stripos( $pt, 'review' ) ) {
				self::add_score( $scores, $reasons, 'Review', 60, sprintf( __( 'Post type slug contains "review" → +60', 'aeomatic' ) ) );
			}
		}
	}

	/**
	 * URL slug and title patterns. "/about", "/contact", "how-to-*", etc.
	 */
	private static function score_slug_and_title( string $slug, string $title, array &$scores, array &$reasons ): void {
		$slug_lc  = strtolower( $slug );
		$title_lc = strtolower( $title );

		// AboutPage / ContactPage by slug.
		if ( 'about' === $slug_lc || 'about-us' === $slug_lc || false !== strpos( $slug_lc, 'about' ) ) {
			self::add_score( $scores, $reasons, 'AboutPage', 70, __( 'Slug indicates About page → +70', 'aeomatic' ) );
		}
		if ( 'contact' === $slug_lc || 'contact-us' === $slug_lc || false !== strpos( $slug_lc, 'contact' ) ) {
			self::add_score( $scores, $reasons, 'ContactPage', 80, __( 'Slug indicates Contact page → +80', 'aeomatic' ) );
		}

		// HowTo / TechArticle from title.
		if ( preg_match( '/^how[\s-]+to\s+/i', $title ) ) {
			self::add_score( $scores, $reasons, 'HowTo', 50, __( 'Title starts with "How to" → +50', 'aeomatic' ) );
			self::add_score( $scores, $reasons, 'TechArticle', 30, __( 'Title starts with "How to" → +30', 'aeomatic' ) );
		}

		// FAQ patterns.
		if ( false !== strpos( $title_lc, 'faq' ) || false !== strpos( $title_lc, 'frequently asked' ) ) {
			self::add_score( $scores, $reasons, 'FAQPage', 60, __( 'Title contains "FAQ" → +60', 'aeomatic' ) );
		}

		// Recipe patterns.
		if ( preg_match( '/\b(recipe|cook|bake|grill|roast)\b/i', $title ) ) {
			self::add_score( $scores, $reasons, 'Recipe', 30, __( 'Title contains cooking term → +30', 'aeomatic' ) );
		}

		// News patterns.
		if ( preg_match( '/\b(announces|launches|breaking|update|press release)\b/i', $title ) ) {
			self::add_score( $scores, $reasons, 'NewsArticle', 40, __( 'Title contains news verb → +40', 'aeomatic' ) );
		}

		// Review patterns.
		if ( preg_match( '/\breview(s|ed|ing)?\b/i', $title ) ) {
			self::add_score( $scores, $reasons, 'Review', 50, __( 'Title contains "review" → +50', 'aeomatic' ) );
		}

		// Best-of / vs / comparison signals → Review.
		if ( preg_match( '/\b(best|top \d+|vs\.?|versus|comparison)\b/i', $title ) ) {
			self::add_score( $scores, $reasons, 'Review', 30, __( 'Title looks like a comparison → +30 (Review)', 'aeomatic' ) );
		}

		// Service slug patterns.
		if ( preg_match( '#/services?/#', '/' . $slug_lc . '/' ) ) {
			self::add_score( $scores, $reasons, 'Service', 50, __( 'Slug under /services/ → +50', 'aeomatic' ) );
		}
	}

	/**
	 * Inspect actual content for structural signals: ingredient lists,
	 * step headings, ratings, schema-suggesting keywords, FAQ density.
	 */
	private static function score_content_signals( string $text, string $html, array &$scores, array &$reasons ): void {
		if ( '' === $text ) {
			return;
		}

		$text_lc = strtolower( $text );

		// Recipe signals: ingredients list + cooking time + servings.
		$recipe_signals = 0;
		if ( preg_match( '/\bingredients?\b/i', $text ) ) {
			$recipe_signals++;
		}
		if ( preg_match( '/\b(prep|cook|bake|total)\s+time\b/i', $text ) ) {
			$recipe_signals++;
		}
		if ( preg_match( '/\b(serves|servings|yield)\b/i', $text ) ) {
			$recipe_signals++;
		}
		if ( preg_match( '/\b\d+\s*(cup|tbsp|tsp|tablespoon|teaspoon|gram|grams|g|kg|oz|ounce|pound|lb|ml|liter)\b/i', $text ) ) {
			$recipe_signals++;
		}
		if ( $recipe_signals >= 3 ) {
			// translators: %d: number of recipe-related signals found in content.
			$aeomatic_reason = sprintf( __( 'Recipe signals detected (%d) → +70', 'aeomatic' ), $recipe_signals );
			self::add_score( $scores, $reasons, 'Recipe', 70, $aeomatic_reason );
		} elseif ( $recipe_signals >= 2 ) {
			// translators: %d: number of recipe-related signals found in content.
			$aeomatic_reason = sprintf( __( 'Some recipe signals (%d) → +30', 'aeomatic' ), $recipe_signals );
			self::add_score( $scores, $reasons, 'Recipe', 30, $aeomatic_reason );
		}

		// HowTo signals: numbered/step headings.
		preg_match_all( '/<h[2-3][^>]*>(.*?)<\/h[2-3]>/is', $html, $hm );
		$step_headings = 0;
		foreach ( ( $hm[1] ?? array() ) as $h ) {
			$plain = trim( wp_strip_all_tags( $h ) );
			if ( preg_match( '/^\s*(step\s+\d+|[\d]+[\.\)])/i', $plain ) ) {
				$step_headings++;
			}
		}
		if ( $step_headings >= 3 ) {
			// translators: %d: number of step-style headings found in the post.
			$aeomatic_reason = sprintf( __( '%d step-style headings detected → +60', 'aeomatic' ), $step_headings );
			self::add_score( $scores, $reasons, 'HowTo', 60, $aeomatic_reason );
		}

		// FAQ density: count question-style headings vs total headings.
		$total_h = count( $hm[1] ?? array() );
		$question_h = 0;
		foreach ( ( $hm[1] ?? array() ) as $h ) {
			$plain = trim( wp_strip_all_tags( $h ) );
			if ( str_ends_with( $plain, '?' ) ) {
				$question_h++;
			}
		}
		if ( $total_h > 0 && $question_h / $total_h > 0.6 && $question_h >= 3 ) {
			// translators: 1: number of question headings, 2: total heading count.
			$aeomatic_reason = sprintf( __( '%1$d/%2$d headings are questions → +70', 'aeomatic' ), $question_h, $total_h );
			self::add_score( $scores, $reasons, 'FAQPage', 70, $aeomatic_reason );
		}

		// Review signals: explicit rating mentions.
		if ( preg_match( '/\b(\d(\.\d)?)\s*(out of|\/)\s*[5|10]\b/i', $text ) ||
		     preg_match( '/\b(rating|rated|score|stars)\b/i', $text_lc ) ) {
			self::add_score( $scores, $reasons, 'Review', 30, __( 'Rating/score language detected → +30', 'aeomatic' ) );
		}
		if ( preg_match( '/\b(pros and cons|verdict|our take|bottom line)\b/i', $text_lc ) ) {
			self::add_score( $scores, $reasons, 'Review', 30, __( 'Review structure language detected → +30', 'aeomatic' ) );
		}

		// LocalBusiness signals: address, phone number, opening hours.
		$lb_signals = 0;
		if ( preg_match( '/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b.*\d/i', $text ) ) {
			$lb_signals++;
		}
		if ( preg_match( '/\b\d{3}[\s-]\d{3}[\s-]\d{4}\b/', $text ) ||
		     preg_match( '/\+\d{1,3}[\s-]?\d{2,4}[\s-]?\d{3,4}[\s-]?\d{3,4}/', $text ) ) {
			$lb_signals++;
		}
		if ( preg_match( '/\b\d+\s+[A-Z][a-z]+\s+(street|st\.?|road|rd\.?|avenue|ave\.?|lane|ln\.?|drive|dr\.?)\b/i', $text ) ) {
			$lb_signals++;
		}
		if ( $lb_signals >= 2 ) {
			// translators: %d: number of local business signals found in content.
			$aeomatic_reason = sprintf( __( 'Local business signals (%d) → +60', 'aeomatic' ), $lb_signals );
			self::add_score( $scores, $reasons, 'LocalBusiness', 60, $aeomatic_reason );
		}

		// Event signals: date + venue + RSVP.
		$event_signals = 0;
		if ( preg_match( '/\b(register|rsvp|tickets?|book your spot)\b/i', $text_lc ) ) {
			$event_signals++;
		}
		if ( preg_match( '/\b(venue|location):/i', $text_lc ) ) {
			$event_signals++;
		}
		if ( preg_match( '/\b\d{1,2}:\d{2}\s*(am|pm)\b/i', $text ) ) {
			$event_signals++;
		}
		if ( $event_signals >= 2 ) {
			// translators: %d: number of event-related signals found in content.
			$aeomatic_reason = sprintf( __( 'Event signals (%d) → +50', 'aeomatic' ), $event_signals );
			self::add_score( $scores, $reasons, 'Event', 50, $aeomatic_reason );
		}

		// JobPosting signals.
		$job_signals = 0;
		if ( preg_match( '/\b(salary|compensation|benefits|requirements|qualifications)\b/i', $text_lc ) ) {
			$job_signals++;
		}
		if ( preg_match( '/\b(apply now|how to apply|submit your resume)\b/i', $text_lc ) ) {
			$job_signals++;
		}
		if ( preg_match( '/\b(full[\s-]?time|part[\s-]?time|contract|remote|hybrid)\b/i', $text_lc ) ) {
			$job_signals++;
		}
		if ( $job_signals >= 2 ) {
			// translators: %d: number of job posting signals found in content.
			$aeomatic_reason = sprintf( __( 'Job posting signals (%d) → +60', 'aeomatic' ), $job_signals );
			self::add_score( $scores, $reasons, 'JobPosting', 60, $aeomatic_reason );
		}

		// Course signals.
		$course_signals = 0;
		if ( preg_match( '/\b(curriculum|syllabus|modules?|lessons?|learn(ing)? outcomes?|prerequisites?)\b/i', $text_lc ) ) {
			$course_signals++;
		}
		if ( preg_match( '/\b(enroll|enrol|sign up for the course)\b/i', $text_lc ) ) {
			$course_signals++;
		}
		if ( preg_match( '/\b(instructor|teacher|professor)\b/i', $text_lc ) ) {
			$course_signals++;
		}
		if ( $course_signals >= 2 ) {
			// translators: %d: number of course-related signals found in content.
			$aeomatic_reason = sprintf( __( 'Course signals (%d) → +50', 'aeomatic' ), $course_signals );
			self::add_score( $scores, $reasons, 'Course', 50, $aeomatic_reason );
		}
	}

	/**
	 * Use category/tag taxonomies as hints.
	 */
	private static function score_taxonomy( WP_Post $post, array &$scores, array &$reasons ): void {
		$cat_names = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			$terms = wp_get_object_terms( $post->ID, $tax, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) ) {
				$cat_names = array_merge( $cat_names, $terms );
			}
		}
		$joined = strtolower( implode( ' ', $cat_names ) );
		if ( '' === $joined ) {
			return;
		}

		$mappings = array(
			'recipe'   => 'Recipe',
			'cooking'  => 'Recipe',
			'food'     => 'Recipe',
			'tutorial' => 'HowTo',
			'how-to'   => 'HowTo',
			'guide'    => 'HowTo',
			'news'     => 'NewsArticle',
			'press'    => 'NewsArticle',
			'announcement' => 'NewsArticle',
			'review'   => 'Review',
			'reviews'  => 'Review',
			'event'    => 'Event',
			'service'  => 'Service',
			'job'      => 'JobPosting',
			'career'   => 'JobPosting',
			'course'   => 'Course',
			'class'    => 'Course',
			'faq'      => 'FAQPage',
		);

		foreach ( $mappings as $needle => $type ) {
			if ( false !== strpos( $joined, $needle ) ) {
				self::add_score( $scores, $reasons, $type, 25,
					/* translators: 1: taxonomy term, 2: schema type */
					sprintf( __( 'Category/tag "%1$s" suggests %2$s → +25', 'aeomatic' ), $needle, $type )
				);
			}
		}
	}

	/**
	 * If ACF is present, look for fields whose names imply schema type.
	 */
	private static function score_acf_hints( WP_Post $post, array &$scores, array &$reasons ): void {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return;
		}
		$fields = get_field_objects( $post->ID );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return;
		}

		$names = strtolower( implode( ' ', array_map( static function ( $f ) {
			return $f['name'] ?? '';
		}, $fields ) ) );

		// Recipe fields.
		if ( false !== strpos( $names, 'ingredient' ) || false !== strpos( $names, 'cook_time' ) ) {
			self::add_score( $scores, $reasons, 'Recipe', 50, __( 'ACF recipe fields detected → +50', 'aeomatic' ) );
		}
		// Job fields.
		if ( false !== strpos( $names, 'salary' ) || false !== strpos( $names, 'job_type' ) ) {
			self::add_score( $scores, $reasons, 'JobPosting', 50, __( 'ACF job fields detected → +50', 'aeomatic' ) );
		}
		// Event fields.
		if ( false !== strpos( $names, 'event_date' ) || false !== strpos( $names, 'venue' ) ) {
			self::add_score( $scores, $reasons, 'Event', 50, __( 'ACF event fields detected → +50', 'aeomatic' ) );
		}
		// Address/location fields.
		if ( false !== strpos( $names, 'address' ) || false !== strpos( $names, 'opening_hour' ) ) {
			self::add_score( $scores, $reasons, 'LocalBusiness', 50, __( 'ACF location fields detected → +50', 'aeomatic' ) );
		}
		// Rating fields.
		if ( false !== strpos( $names, 'rating' ) || false !== strpos( $names, 'pros' ) || false !== strpos( $names, 'cons' ) ) {
			self::add_score( $scores, $reasons, 'Review', 40, __( 'ACF review fields detected → +40', 'aeomatic' ) );
		}
	}

	/**
	 * Check the page template — themes commonly use page-about.php etc.
	 */
	private static function score_template( WP_Post $post, array &$scores, array &$reasons ): void {
		$tpl = get_page_template_slug( $post );
		if ( ! $tpl ) {
			return;
		}
		$tpl_lc = strtolower( $tpl );

		$mappings = array(
			'about'   => array( 'AboutPage' => 50 ),
			'contact' => array( 'ContactPage' => 60 ),
			'service' => array( 'Service' => 50 ),
			'team'    => array( 'ProfilePage' => 50 ),
			'faq'     => array( 'FAQPage' => 60 ),
		);

		foreach ( $mappings as $needle => $types ) {
			if ( false !== strpos( $tpl_lc, $needle ) ) {
				foreach ( $types as $type => $pts ) {
					self::add_score( $scores, $reasons, $type, $pts,
						/* translators: 1: template slug, 2: schema type, 3: points */
						sprintf( __( 'Template "%1$s" → %2$s +%3$d', 'aeomatic' ), $tpl, $type, $pts )
					);
				}
			}
		}
	}

	/**
	 * Save user override.
	 *
	 * Clears both the analyzer's persisted analysis cache and the schema
	 * builder cache. Without the analyzer invalidation the next analyze()
	 * call returns the pre-override result from META_ANALYSIS, which is
	 * why the editor's Schema-type dropdown appeared not to accept the
	 * user's selection — the refetched value was the stale one.
	 */
	public static function set_override( int $post_id, string $type ): bool {
		self::invalidate_cache( $post_id );
		if ( class_exists( 'AEOMATIC_Schema_Builder' ) ) {
			AEOMATIC_Schema_Builder::invalidate( $post_id );
		}
		if ( '' === $type || 'auto' === $type ) {
			return delete_post_meta( $post_id, self::META_OVERRIDE );
		}
		if ( ! isset( self::known_types()[ $type ] ) ) {
			return false;
		}
		return (bool) update_post_meta( $post_id, self::META_OVERRIDE, $type );
	}
}
