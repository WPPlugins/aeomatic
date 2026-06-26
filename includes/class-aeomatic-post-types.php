<?php
/**
 * Post type manager.
 *
 * Auto-detects every public custom post type registered on the site.
 * Settings let the user toggle which CPTs are scanned.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Post_Types {

	/** @var array<string,bool>|null */
	private static $supported_cache = null;

	/**
	 * Get all public post types eligible for AEO processing.
	 *
	 * @return array<string,WP_Post_Type>
	 */
	public static function get_public_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'objects' );

		// Exclude attachments — they're not content pages.
		unset( $types['attachment'] );

		/**
		 * Filter the list of post types AEOmatic — AI based Answer Engine Optimization considers.
		 *
		 * @param array<string,WP_Post_Type> $types
		 */
		return apply_filters( 'aeomatic_supported_post_types_list', $types );
	}

	/**
	 * Map of post_type => bool from the user's settings.
	 *
	 * Defaults to all public types enabled. WooCommerce 'product' is
	 * always enabled (we ship Product schema for it).
	 *
	 * @return array<string,bool>
	 */
	public static function get_enabled_map(): array {
		if ( null !== self::$supported_cache ) {
			return self::$supported_cache;
		}

		$saved   = (array) AEOMATIC_Settings::get( 'post_types' );
		$public  = self::get_public_post_types();
		$result  = array();

		foreach ( $public as $slug => $obj ) {
			if ( array_key_exists( $slug, $saved ) ) {
				$result[ $slug ] = (bool) $saved[ $slug ];
			} else {
				// Default ON for everything public.
				$result[ $slug ] = true;
			}
		}

		self::$supported_cache = $result;
		return $result;
	}

	/**
	 * Is this post type currently enabled by the user?
	 */
	public static function is_enabled( string $post_type ): bool {
		$map = self::get_enabled_map();
		return ! empty( $map[ $post_type ] );
	}

	/**
	 * Just the list of enabled post type slugs.
	 *
	 * @return string[]
	 */
	public static function get_enabled_slugs(): array {
		return array_keys( array_filter( self::get_enabled_map() ) );
	}

	/**
	 * Bust the in-memory cache (called after settings save).
	 */
	public static function flush_cache(): void {
		self::$supported_cache = null;
	}

	/**
	 * Pick the most appropriate schema type for a given post type.
	 * Used by the schema injector to decide between Article, Product, etc.
	 */
	public static function detect_schema_type( WP_Post $post ): string {
		return self::detect_schema_type_by_slug( $post->post_type );
	}

	/**
	 * Schema type detection from a post type slug only — useful in admin UI.
	 */
	public static function detect_schema_type_by_slug( string $pt ): string {		// WooCommerce.
		if ( 'product' === $pt && class_exists( 'WC_Product' ) ) {
			return 'Product';
		}

		// Common known patterns.
		$known = array(
			'post'        => 'Article',
			'page'        => 'WebPage',
			'product'     => 'Product',
			'event'       => 'Event',
			'tribe_events'=> 'Event',
			'recipe'      => 'Recipe',
			'wpcm_recipe' => 'Recipe',
			'job'         => 'JobPosting',
			'jobpost'     => 'JobPosting',
			'course'      => 'Course',
			'sfwd-courses'=> 'Course',
			'lesson'      => 'LearningResource',
			'sfwd-lessons'=> 'LearningResource',
			'book'        => 'Book',
			'review'      => 'Review',
			'video'       => 'VideoObject',
			'faq'         => 'FAQPage',
			'help-center' => 'TechArticle',
			'docs'        => 'TechArticle',
			'documentation' => 'TechArticle',
		);

		if ( isset( $known[ $pt ] ) ) {
			return $known[ $pt ];
		}

		// Heuristics by post type slug.
		if ( false !== stripos( $pt, 'product' ) ) {
			return 'Product';
		}
		if ( false !== stripos( $pt, 'event' ) ) {
			return 'Event';
		}
		if ( false !== stripos( $pt, 'course' ) || false !== stripos( $pt, 'class' ) ) {
			return 'Course';
		}
		if ( false !== stripos( $pt, 'job' ) ) {
			return 'JobPosting';
		}
		if ( false !== stripos( $pt, 'recipe' ) ) {
			return 'Recipe';
		}
		if ( false !== stripos( $pt, 'doc' ) || false !== stripos( $pt, 'help' ) || false !== stripos( $pt, 'kb' ) ) {
			return 'TechArticle';
		}
		if ( false !== stripos( $pt, 'case-stud' ) || false !== stripos( $pt, 'case_stud' ) ) {
			return 'Article';
		}

		// Default.
		return 'Article';
	}
}
