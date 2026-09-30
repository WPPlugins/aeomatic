<?php
/**
 * Reusable signal collector for the Entity Scanner.
 *
 * Extracts once from the four sources requested — content, schema, headings,
 * metadata — then answers detector questions cheaply. Detectors never touch
 * the raw post; they ask the collector. Changing extraction rules once here
 * updates every detector.
 *
 * Consumes existing plugin services:
 *   - AEOMATIC_Content_Extractor  (unified page-builder text extraction)
 *   - AEOMATIC_Schema_Analyzer    (detected & assigned schema type)
 *   - AEOMATIC_Schema_Generator   (schema override properties)
 *   - AEOMATIC_Settings           (organization config)
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AEOMATIC_Entity_Signal_Collector {

	/** @var WP_Post */
	private $post;

	/** @var string cached plain-text body (includes ACF + page builder content) */
	private $plain;

	/** @var string cached HTML body (includes ACF wysiwyg + page builder markup) */
	private $html;

	/** @var array cached FAQ pairs extracted from any source (Gutenberg block, ACF repeater, shortcode, etc.) */
	private $extracted_faqs;

	/** @var string[] cached heading strings (H1-H4) */
	private $headings;

	/** @var array cached schema analysis */
	private $schema_analysis;

	/** @var string cached assigned schema type */
	private $assigned_type;

	/** @var array cached schema property overrides */
	private $schema_overrides;

	/** @var array normalised organisation settings (name, logo, sameas, address, ...) */
	private $org_settings;

	/** @var string detected editor / page builder (gutenberg|classic|elementor|divi|beaver|bricks|oxygen|wpbakery|unknown) */
	private $builder = 'gutenberg';

	public function __construct( WP_Post $post ) {
		$this->post = $post;

		// Prefer the shared extractor — it merges post_content, ACF fields, and
		// every supported page builder into one HTML+text pair. Fall back to
		// post_content only if the extractor is missing.
		if ( class_exists( 'AEOMATIC_Content_Extractor' ) && method_exists( 'AEOMATIC_Content_Extractor', 'extract' ) ) {
			$extracted            = (array) AEOMATIC_Content_Extractor::extract( $post );
			$this->html           = (string) ( $extracted['html'] ?? $post->post_content );
			$this->plain          = (string) ( $extracted['text'] ?? trim( (string) wp_strip_all_tags( $this->html ) ) );
			$this->extracted_faqs = (array)  ( $extracted['faqs'] ?? array() );
			$this->builder        = (string) ( $extracted['builder'] ?? 'gutenberg' );
		} else {
			$this->html           = (string) $post->post_content;
			$this->plain          = trim( (string) wp_strip_all_tags( $this->html ) );
			$this->extracted_faqs = array();
			$this->builder        = 'gutenberg';
		}

		$this->headings = $this->extract_headings( $this->html );

		$this->schema_analysis = $this->load_schema_analysis();
		$this->assigned_type   = $this->load_assigned_schema_type();
		$this->schema_overrides = $this->load_schema_overrides();

		$this->org_settings = $this->load_org_settings();
	}

	// ── Post ──────────────────────────────────────────────────────────────
	public function post(): WP_Post { return $this->post; }

	/**
	 * Detected editor / page builder for this post.
	 * Detectors use this to phrase recommendations in ways the user can actually act on
	 * (e.g. "insert an FAQ accordion" for Elementor vs. "insert the FAQ block" for Gutenberg).
	 */
	public function builder(): string { return $this->builder; }

	/** True when the post is authored in the block editor (or classic + no builder plugin). */
	public function is_block_editor(): bool {
		return in_array( $this->builder, array( 'gutenberg', 'classic', 'unknown' ), true );
	}

	// ── Content signals ───────────────────────────────────────────────────
	public function content_plain(): string { return $this->plain; }
	public function content_word_count(): int { return str_word_count( $this->plain ); }

	public function has_content_keyword( array $keywords, int $min_count = 1 ): bool {
		if ( $this->plain === '' ) { return false; }
		$hits = 0;
		foreach ( $keywords as $k ) {
			$k = trim( (string) $k );
			if ( $k === '' ) { continue; }
			$hits += substr_count( strtolower( $this->plain ), strtolower( $k ) );
			if ( $hits >= $min_count ) { return true; }
		}
		return false;
	}

	/** Detect any 4-digit-year pattern that suggests founded-in / since / established. */
	public function has_founded_pattern(): bool {
		return (bool) preg_match( '/\b(?:founded|established|since|est\.)\s*(?:in\s*)?(?:19|20)\d{2}\b/i', $this->plain );
	}

	/** NAP heuristic: street/city + phone-like number nearby. */
	public function has_nap_pattern(): bool {
		$has_phone   = (bool) preg_match( '/(?:\+?\d[\d\s().-]{7,}\d)/', $this->plain );
		$has_address = (bool) preg_match( '/\b\d{1,5}\s+[A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+)*(?:\s+(?:Street|St|Road|Rd|Avenue|Ave|Boulevard|Blvd|Lane|Ln|Drive|Dr|Way))\b/', $this->plain );
		return $has_phone && $has_address;
	}

	// ── Schema signals ────────────────────────────────────────────────────
	public function schema_analysis(): array { return $this->schema_analysis; }
	public function assigned_schema_type(): string { return $this->assigned_type; }

	public function has_schema_type( string $type ): bool {
		$active = $this->assigned_type !== '' ? $this->assigned_type : (string) ( $this->schema_analysis['detected_type'] ?? '' );
		return strcasecmp( $active, $type ) === 0;
	}

	public function schema_type_is_subtype_of( string $ancestor ): bool {
		// LocalBusiness has many subtypes (Restaurant, Store, MedicalOrganization…).
		// Cheap coarse check: exact match OR name contains ancestor.
		$active = $this->assigned_type !== '' ? $this->assigned_type : (string) ( $this->schema_analysis['detected_type'] ?? '' );
		if ( strcasecmp( $active, $ancestor ) === 0 ) { return true; }
		return stripos( $active, $ancestor ) !== false;
	}

	/**
	 * @return mixed
	 */
	public function schema_override( string $key ) {
		return $this->schema_overrides[ $key ] ?? null;
	}

	// ── Heading signals ───────────────────────────────────────────────────
	public function headings(): array { return $this->headings; }

	public function has_heading_keyword( array $keywords ): bool {
		foreach ( $this->headings as $h ) {
			foreach ( $keywords as $k ) {
				if ( $k !== '' && stripos( $h, (string) $k ) !== false ) {
					return true;
				}
			}
		}
		return false;
	}

	public function has_question_heading(): bool {
		foreach ( $this->headings as $h ) {
			if ( str_ends_with( $h, '?' ) ) { return true; }
			if ( preg_match( '/^(what|how|why|when|where|who|is|are|can|should|do|does)\b/i', $h ) ) {
				return true;
			}
		}
		return false;
	}

	// ── Metadata / author signals ─────────────────────────────────────────
	public function post_author_bio_words(): int {
		$aid = (int) $this->post->post_author;
		if ( $aid <= 0 ) { return 0; }
		$bio = get_user_meta( $aid, 'description', true );
		return str_word_count( (string) wp_strip_all_tags( (string) $bio ) );
	}

	public function post_author_display_name(): string {
		$aid = (int) $this->post->post_author;
		if ( $aid <= 0 ) { return ''; }
		$u = get_userdata( $aid );
		return $u ? (string) $u->display_name : '';
	}

	public function post_author_has_url(): bool {
		$aid = (int) $this->post->post_author;
		if ( $aid <= 0 ) { return false; }
		$u = get_userdata( $aid );
		if ( $u && trim( (string) $u->user_url ) !== '' ) { return true; }
		foreach ( array( 'aeomatic_linkedin', 'aeomatic_twitter', 'aeomatic_github', 'aeomatic_website' ) as $k ) {
			if ( trim( (string) get_user_meta( $aid, $k, true ) ) !== '' ) { return true; }
		}
		return false;
	}

	public function organization_setting( string $key ): string {
		return (string) ( $this->org_settings[ $key ] ?? '' );
	}

	public function meta_description(): string {
		foreach ( array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description', '_seopress_titles_desc' ) as $k ) {
			$v = get_post_meta( $this->post->ID, $k, true );
			if ( is_string( $v ) && trim( $v ) !== '' ) { return $v; }
		}
		return trim( (string) $this->post->post_excerpt );
	}

	public function has_woocommerce_product(): bool {
		if ( ! function_exists( 'wc_get_product' ) ) { return false; }
		if ( $this->post->post_type !== 'product' ) { return false; }
		return (bool) wc_get_product( $this->post->ID );
	}

	// ── FAQ marker ────────────────────────────────────────────────────────
	public function has_faq_block(): bool {
		// Count any FAQ source the content extractor could find — Gutenberg
		// block, ACF repeater, Elementor/Divi/WPBakery accordion, shortcode,
		// or universal HTML Q&A. Restricting to `wp:aeomatic/faq` markers
		// missed every non-Gutenberg builder and every ACF site.
		if ( ! empty( $this->extracted_faqs ) ) {
			return true;
		}
		return stripos( $this->html, 'wp:aeomatic/faq' ) !== false
			|| stripos( $this->html, 'wp:core/block/faq' ) !== false;
	}

	// ── Private loaders ───────────────────────────────────────────────────
	private function extract_headings( string $html ): array {
		$out = array();
		if ( preg_match_all( '/<h[1-4][^>]*>(.*?)<\/h[1-4]>/is', $html, $m ) ) {
			foreach ( $m[1] as $h ) {
				$plain = trim( (string) wp_strip_all_tags( $h ) );
				if ( $plain !== '' ) { $out[] = $plain; }
			}
		}
		return $out;
	}

	private function load_schema_analysis(): array {
		if ( ! class_exists( 'AEOMATIC_Schema_Analyzer' ) ) { return array(); }
		$key = defined( 'AEOMATIC_Schema_Analyzer::META_ANALYSIS' )
			? AEOMATIC_Schema_Analyzer::META_ANALYSIS
			: '_aeomatic_schema_analysis';
		$raw = get_post_meta( $this->post->ID, $key, true );
		if ( is_array( $raw ) ) { return $raw; }
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return array();
	}

	private function load_assigned_schema_type(): string {
		if ( ! class_exists( 'AEOMATIC_Schema_Analyzer' ) ) { return ''; }
		$key = defined( 'AEOMATIC_Schema_Analyzer::META_OVERRIDE' )
			? AEOMATIC_Schema_Analyzer::META_OVERRIDE
			: '_aeomatic_schema_type';
		return (string) get_post_meta( $this->post->ID, $key, true );
	}

	private function load_schema_overrides(): array {
		if ( ! class_exists( 'AEOMATIC_Schema_Generator' ) ) { return array(); }
		$key = defined( 'AEOMATIC_Schema_Generator::META_OVERRIDES' )
			? AEOMATIC_Schema_Generator::META_OVERRIDES
			: '_aeomatic_schema_overrides';
		$raw = get_post_meta( $this->post->ID, $key, true );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Load organisation-scope settings and normalise the key names the
	 * detectors expect.
	 *
	 * The plugin stores org config under the `schema` section as `org_name`,
	 * `org_logo`, `org_sameas`. Detectors ask for `name`, `logo`, `sameas`.
	 * This method bridges the two so callers don't need to know the storage
	 * layout. Address keys are exposed even though Settings has no UI for
	 * them yet — the LocalBusiness detector will still find them once
	 * they're wired up, and returns `''` cleanly today.
	 */
	private function load_org_settings(): array {
		if ( ! class_exists( 'AEOMATIC_Settings' ) ) {
			return array();
		}
		$schema = (array) AEOMATIC_Settings::get( 'schema' );
		$org    = (array) AEOMATIC_Settings::get( 'organization' ); // future-proof if a real section is added
		return array(
			'name'   => (string) ( $org['name']   ?? $schema['org_name']   ?? '' ),
			'logo'   => (string) ( $org['logo']   ?? $schema['org_logo']   ?? '' ),
			'sameas' => (string) ( $org['sameas'] ?? $schema['org_sameas'] ?? '' ),
			'street' => (string) ( $org['street'] ?? $schema['org_street'] ?? '' ),
			'address'=> (string) ( $org['address']?? $schema['org_address']?? '' ),
			'city'   => (string) ( $org['city']   ?? $schema['org_city']   ?? '' ),
			'region' => (string) ( $org['region'] ?? $schema['org_region'] ?? '' ),
			'phone'  => (string) ( $org['phone']  ?? $schema['org_phone']  ?? '' ),
		);
	}
}
