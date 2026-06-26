<?php
/**
 * Content extractor.
 *
 * Pulls plain text from any post regardless of how it was authored:
 * Gutenberg blocks, classic editor, Elementor, Divi, Beaver Builder,
 * Bricks, Oxygen, WPBakery, plus any ACF text-type fields and arbitrary
 * post meta. The extracted text is what the audit engine and llms.txt
 * actually scan.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Content_Extractor {

	/**
	 * Cache of extractions per post during a single request.
	 *
	 * @var array<int,array{html:string,text:string,builder:string,word_count:int,faqs:array}>
	 */
	private static $cache = array();

	/**
	 * Extract a normalized representation of the post's content.
	 *
	 * @param WP_Post|int $post_or_id
	 * @return array{html:string,text:string,builder:string,word_count:int,faqs:array}
	 */
	public static function extract( $post_or_id ): array {
		$post = $post_or_id instanceof WP_Post ? $post_or_id : get_post( (int) $post_or_id );
		if ( ! $post instanceof WP_Post ) {
			return self::empty_result();
		}

		if ( isset( self::$cache[ $post->ID ] ) ) {
			return self::$cache[ $post->ID ];
		}

		$builder = self::detect_builder( $post );
		$html_parts = array();
		$faqs       = array();

		// 1. Standard post_content (Gutenberg, Classic).
		if ( ! empty( $post->post_content ) ) {
			$html_parts[] = (string) $post->post_content;
			$faqs = array_merge( $faqs, self::extract_gutenberg_faqs( $post->post_content ) );
		}

		// 2. Page-builder-specific content extraction.
		switch ( $builder ) {
			case 'elementor':
				$elementor_html = self::extract_elementor( $post );
				if ( $elementor_html ) {
					$html_parts[] = $elementor_html;
				}
				break;
			case 'divi':
				$divi_html = self::extract_divi( $post );
				if ( $divi_html ) {
					$html_parts[] = $divi_html;
				}
				break;
			case 'beaver':
				$bb_html = self::extract_beaver_builder( $post );
				if ( $bb_html ) {
					$html_parts[] = $bb_html;
				}
				break;
			case 'bricks':
				$bricks_html = self::extract_bricks( $post );
				if ( $bricks_html ) {
					$html_parts[] = $bricks_html;
				}
				break;
			case 'oxygen':
				$oxygen_html = self::extract_oxygen( $post );
				if ( $oxygen_html ) {
					$html_parts[] = $oxygen_html;
				}
				break;
			case 'wpbakery':
				$wpb_html = self::extract_wpbakery( $post );
				if ( $wpb_html ) {
					$html_parts[] = $wpb_html;
				}
				break;
		}

		// 3. ACF fields (any text-type fields).
		$acf_html = self::extract_acf_fields( $post );
		if ( $acf_html ) {
			$html_parts[] = $acf_html;
		}

		// 4. Generic post meta (developer-extensible).
		$meta_keys = (array) apply_filters( 'aeomatic_extract_meta_keys', array(), $post );
		foreach ( $meta_keys as $key ) {
			$val = get_post_meta( $post->ID, $key, true );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				$html_parts[] = $val;
			}
		}

		// 5. Allow other plugins to inject content (e.g. custom builders).
		$extra = apply_filters( 'aeomatic_extract_extra_html', '', $post, $builder );
		if ( is_string( $extra ) && $extra ) {
			$html_parts[] = $extra;
		}

		$html = implode( "\n\n", array_filter( $html_parts ) );
		$text = trim( wp_strip_all_tags( $html ) );
		$text = preg_replace( '/\s+/', ' ', $text );

		$result = array(
			'html'       => $html,
			'text'       => $text,
			'builder'    => $builder,
			'word_count' => $text ? str_word_count( $text ) : 0,
			'faqs'       => $faqs,
		);

		self::$cache[ $post->ID ] = $result;
		return $result;
	}

	/**
	 * Detect which builder authored the post.
	 *
	 * @return string Builder slug: gutenberg|classic|elementor|divi|beaver|bricks|oxygen|wpbakery|unknown
	 */
	public static function detect_builder( WP_Post $post ): string {
		// Elementor.
		if ( get_post_meta( $post->ID, '_elementor_edit_mode', true ) === 'builder' ) {
			return 'elementor';
		}
		// Divi.
		if ( get_post_meta( $post->ID, '_et_pb_use_builder', true ) === 'on' ) {
			return 'divi';
		}
		// Beaver Builder.
		if ( get_post_meta( $post->ID, '_fl_builder_enabled', true ) ) {
			return 'beaver';
		}
		// Bricks.
		if ( get_post_meta( $post->ID, '_bricks_page_content_2', true ) ) {
			return 'bricks';
		}
		// Oxygen.
		if ( get_post_meta( $post->ID, 'ct_builder_shortcodes', true ) ) {
			return 'oxygen';
		}
		// WPBakery — content includes [vc_row] shortcodes.
		if ( $post->post_content && false !== strpos( $post->post_content, '[vc_row' ) ) {
			return 'wpbakery';
		}
		// Gutenberg.
		if ( $post->post_content && has_blocks( $post->post_content ) ) {
			return 'gutenberg';
		}
		// Classic.
		if ( $post->post_content ) {
			return 'classic';
		}
		return 'unknown';
	}

	/**
	 * Extract FAQ pairs from Gutenberg blocks (aeomatic/faq-item).
	 */
	public static function extract_gutenberg_faqs( string $content ): array {
		if ( ! function_exists( 'parse_blocks' ) || empty( $content ) ) {
			return array();
		}
		$blocks = parse_blocks( $content );
		return self::walk_blocks_for_faqs( $blocks );
	}

	private static function walk_blocks_for_faqs( array $blocks ): array {
		$pairs = array();
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === 'aeomatic/faq-item' ) {
				$q = trim( wp_strip_all_tags( $block['attrs']['question'] ?? '' ) );
				$a = trim( wp_strip_all_tags( $block['attrs']['answer'] ?? '' ) );
				if ( $q && $a ) {
					$pairs[] = array( 'q' => $q, 'a' => $a );
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$pairs = array_merge( $pairs, self::walk_blocks_for_faqs( $block['innerBlocks'] ) );
			}
		}
		return $pairs;
	}

	/**
	 * Extract Elementor content from _elementor_data.
	 *
	 * Elementor stores a JSON tree of widgets. We walk it and pull
	 * settings keys that contain text (title, editor, text, content,
	 * heading_text, description, etc.).
	 */
	private static function extract_elementor( WP_Post $post ): string {
		$data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( empty( $data ) ) {
			return '';
		}
		// Elementor stores it as a JSON string (already escaped).
		$decoded = json_decode( is_string( $data ) ? $data : wp_json_encode( $data ), true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		$buf = array();
		self::walk_elementor( $decoded, $buf );
		return implode( "\n", $buf );
	}

	private static function walk_elementor( array $nodes, array &$buf ): void {
		$text_keys = array(
			'title', 'heading_text', 'editor', 'text', 'content', 'description',
			'subtitle', 'caption', 'cta_text', 'button_text', 'tab_title',
			'tab_content', 'item_title', 'item_description', 'testimonial_content',
			'price_subscript', 'price_currency_symbol', 'header_size', 'alt',
			'image_alt',
		);
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( ! empty( $node['settings'] ) && is_array( $node['settings'] ) ) {
				foreach ( $text_keys as $k ) {
					if ( ! empty( $node['settings'][ $k ] ) && is_string( $node['settings'][ $k ] ) ) {
						$buf[] = $node['settings'][ $k ];
					}
				}
				// Repeater fields.
				foreach ( $node['settings'] as $key => $val ) {
					if ( is_array( $val ) ) {
						foreach ( $val as $row ) {
							if ( is_array( $row ) ) {
								foreach ( $text_keys as $tk ) {
									if ( ! empty( $row[ $tk ] ) && is_string( $row[ $tk ] ) ) {
										$buf[] = $row[ $tk ];
									}
								}
							}
						}
					}
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_elementor( $node['elements'], $buf );
			}
		}
	}

	/**
	 * Divi stores content as shortcodes inside post_content. Strip the
	 * shortcode wrappers and keep the inner text.
	 */
	private static function extract_divi( WP_Post $post ): string {
		$content = (string) $post->post_content;
		if ( ! $content ) {
			return '';
		}
		// Remove all [et_pb_...] shortcode tags but keep their inner content.
		$cleaned = preg_replace( '/\[\/?et_pb_[^\]]*\]/i', ' ', $content );
		return is_string( $cleaned ) ? $cleaned : '';
	}

	/**
	 * Beaver Builder stores layout data in _fl_builder_data post meta.
	 */
	private static function extract_beaver_builder( WP_Post $post ): string {
		$data = get_post_meta( $post->ID, '_fl_builder_data', true );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return '';
		}
		$buf = array();
		foreach ( $data as $node ) {
			if ( ! is_object( $node ) && ! is_array( $node ) ) {
				continue;
			}
			$node = (array) $node;
			$settings = isset( $node['settings'] ) ? (array) $node['settings'] : array();
			foreach ( $settings as $key => $val ) {
				if ( is_string( $val ) && '' !== trim( $val ) ) {
					$buf[] = $val;
				}
			}
		}
		return implode( "\n", $buf );
	}

	/**
	 * Bricks Builder stores content in _bricks_page_content_2.
	 */
	private static function extract_bricks( WP_Post $post ): string {
		$data = get_post_meta( $post->ID, '_bricks_page_content_2', true );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return '';
		}
		$buf = array();
		self::walk_generic_settings( $data, $buf );
		return implode( "\n", $buf );
	}

	/**
	 * Oxygen Builder stores shortcode JSON in ct_builder_shortcodes.
	 * Best-effort: strip shortcodes and keep inner text.
	 */
	private static function extract_oxygen( WP_Post $post ): string {
		$data = (string) get_post_meta( $post->ID, 'ct_builder_shortcodes', true );
		if ( ! $data ) {
			return '';
		}
		$cleaned = preg_replace( '/\[\/?ct_[^\]]*\]/i', ' ', $data );
		return is_string( $cleaned ) ? $cleaned : '';
	}

	/**
	 * WPBakery uses [vc_*] shortcodes inline in post_content.
	 */
	private static function extract_wpbakery( WP_Post $post ): string {
		$content = (string) $post->post_content;
		$cleaned = preg_replace( '/\[\/?(vc|kc)_[^\]]*\]/i', ' ', $content );
		return is_string( $cleaned ) ? $cleaned : '';
	}

	/**
	 * Walk an arbitrary settings array and collect all string values.
	 */
	private static function walk_generic_settings( array $arr, array &$buf ): void {
		foreach ( $arr as $val ) {
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				$buf[] = $val;
			} elseif ( is_array( $val ) ) {
				self::walk_generic_settings( $val, $buf );
			} elseif ( is_object( $val ) ) {
				self::walk_generic_settings( (array) $val, $buf );
			}
		}
	}

	/**
	 * Extract text from ACF fields. Walks all ACF field groups attached
	 * to the post and collects values from text-type fields.
	 */
	private static function extract_acf_fields( WP_Post $post ): string {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return '';
		}
		$fields = get_field_objects( $post->ID );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return '';
		}
		$buf = array();
		self::walk_acf_fields( $fields, $buf );
		return implode( "\n", $buf );
	}

	private static function walk_acf_fields( array $fields, array &$buf ): void {
		$text_types   = array( 'text', 'textarea', 'wysiwyg', 'email', 'url' );
		$nested_types = array( 'repeater', 'group', 'flexible_content' );

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			// get_field_objects() can return repeater sub-field instances at the
			// top level (e.g. repeater_0_sub_text) alongside the parent repeater.
			// Their 'parent' is a field key ('field_xxx'), not a group key ('group_xxx').
			// Skip them here so we don't count each sub-field value twice.
			$parent = $field['parent'] ?? '';
			if ( $parent && str_starts_with( (string) $parent, 'field_' ) ) {
				continue;
			}

			$type  = $field['type'] ?? '';
			$value = $field['value'] ?? null;

			if ( in_array( $type, $text_types, true ) && is_string( $value ) && '' !== trim( $value ) ) {
				$buf[] = $value;
				continue;
			}

			if ( in_array( $type, $nested_types, true ) && is_array( $value ) ) {
				$sub_fields = $field['sub_fields'] ?? array();
				// Group value is a flat assoc array; wrap it so walk_acf_rows
				// always receives an array-of-rows.
				$rows = ( 'group' === $type ) ? array( $value ) : $value;
				self::walk_acf_rows( $rows, $sub_fields, $buf );
			}
		}
	}

	/**
	 * Walk an array of repeater/flexible-content rows, extracting only
	 * text-type sub-field values using the sub_fields type definitions.
	 *
	 * @param array $rows       Array of row arrays.
	 * @param array $sub_fields Sub-field definitions from the parent field object.
	 * @param array $buf        Accumulator passed by reference.
	 */
	private static function walk_acf_rows( array $rows, array $sub_fields, array &$buf ): void {
		$text_types   = array( 'text', 'textarea', 'wysiwyg', 'email', 'url' );
		$nested_types = array( 'repeater', 'group', 'flexible_content' );

		// Build name → field-def map so we know each sub-field's type.
		$defs = array();
		foreach ( $sub_fields as $sf ) {
			if ( is_array( $sf ) && isset( $sf['name'] ) ) {
				$defs[ $sf['name'] ] = $sf;
			}
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $row as $key => $val ) {
				// Skip the flexible-content layout identifier.
				if ( 'acf_fc_layout' === $key ) {
					continue;
				}
				$sf_type = $defs[ $key ]['type'] ?? '';
				if ( in_array( $sf_type, $text_types, true ) && is_string( $val ) && '' !== trim( $val ) ) {
					$buf[] = $val;
				} elseif ( in_array( $sf_type, $nested_types, true ) && is_array( $val ) ) {
					$child_sub_fields = $defs[ $key ]['sub_fields'] ?? array();
					$child_rows       = ( 'group' === $sf_type ) ? array( $val ) : $val;
					self::walk_acf_rows( $child_rows, $child_sub_fields, $buf );
				} elseif ( '' === $sf_type && is_string( $val ) && '' !== trim( $val ) ) {
					// No type info available (unusual) — include the string as a
					// safe fallback so no content is silently dropped.
					$buf[] = $val;
				}
			}
		}
	}

	private static function empty_result(): array {
		return array(
			'html'       => '',
			'text'       => '',
			'builder'    => 'unknown',
			'word_count' => 0,
			'faqs'       => array(),
		);
	}

	/**
	 * Pretty label for builder slugs.
	 */
	public static function builder_label( string $slug ): string {
		$labels = array(
			'gutenberg' => 'Gutenberg',
			'classic'   => 'Classic editor',
			'elementor' => 'Elementor',
			'divi'      => 'Divi',
			'beaver'    => 'Beaver Builder',
			'bricks'    => 'Bricks',
			'oxygen'    => 'Oxygen',
			'wpbakery'  => 'WPBakery',
			'unknown'   => 'Unknown',
		);
		return $labels[ $slug ] ?? ucfirst( $slug );
	}
}
