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
	 * Drop the in-request cache for one post (or all posts), e.g. after
	 * AEOmatic itself edits the post content or after a live page scan.
	 */
	public static function forget( int $post_id = 0 ): void {
		if ( $post_id ) {
			unset( self::$cache[ $post_id ] );
			return;
		}
		self::$cache = array();
	}

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
			// Classic-editor FAQ shortcodes ([aeo_qa] inside [aeo_faq]).
			if ( class_exists( 'AEOMATIC_Shortcodes' ) ) {
				$faqs = array_merge( $faqs, AEOMATIC_Shortcodes::extract_pairs( $post->post_content ) );
			}
		}

		// 2. Page-builder-specific content extraction.
		switch ( $builder ) {
			case 'elementor':
				$elementor_html = self::extract_elementor( $post );
				if ( $elementor_html ) {
					$html_parts[] = $elementor_html;
				}
				$faqs = array_merge( $faqs, self::extract_elementor_faqs( $post ) );
				break;
			case 'divi':
				$divi_html = self::extract_divi( $post );
				if ( $divi_html ) {
					$html_parts[] = $divi_html;
				}
				$faqs = array_merge( $faqs, self::extract_divi_faqs( (string) $post->post_content ) );
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
				$faqs = array_merge( $faqs, self::extract_wpbakery_faqs( (string) $post->post_content ) );
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

		// 6. Universal HTML FAQ detection — works for every builder once their
		// content is in $html. Catches question headings, <details>/<summary>,
		// definition lists, and pre-existing Question microdata.
		if ( apply_filters( 'aeomatic_detect_faqs', true, $post ) ) {
			$faqs = array_merge( $faqs, self::extract_universal_faqs( $html ) );
			$faqs = array_merge( $faqs, self::extract_acf_faqs( $post ) );
		}

		// 7. FAQs found on the live page by the last scan — covers the theme,
		// templates and widgets that the saved post data does not show.
		if ( class_exists( 'AEOMATIC_Page_Crawler' ) ) {
			$faqs = array_merge( $faqs, AEOMATIC_Page_Crawler::cached_faqs( $post ) );
		}

		// Dedup by trimmed question — multiple sources often catch the same Q&A.
		$faqs = self::dedup_faqs( $faqs );

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
	 * Universal FAQ detection across any HTML source. Catches:
	 *   1. <details>/<summary>   — semantic disclosure widget
	 *   2. <dl>/<dt>/<dd>        — definition lists
	 *   3. Existing schema.org Question microdata in the HTML
	 *   4. FAQ blocks and accordions built from divs (Yoast, Rank Math,
	 *      Spectra, Kadence, Elementor, ARIA accordions)
	 *   5. Question-style <h2>/<h3>/<h4> headings followed by paragraphs
	 *
	 * A "question-style" heading either ends with `?` or starts with a
	 * recognised interrogative (how/what/why/when/where/who/which/is/are/can/do).
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function extract_universal_faqs( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$pairs = array();

		// 1. <details><summary>Q</summary>A</details>
		if ( preg_match_all( '/<details[^>]*>\s*<summary[^>]*>(.*?)<\/summary>(.*?)<\/details>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				$q = self::clean_qa_text( $row[1] );
				$a = self::clean_qa_text( $row[2] );
				if ( self::is_valid_qa( $q, $a ) ) {
					$pairs[] = array( 'q' => $q, 'a' => $a );
				}
			}
		}

		// 2. <dt>Q</dt><dd>A</dd>
		if ( preg_match_all( '/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				$q = self::clean_qa_text( $row[1] );
				$a = self::clean_qa_text( $row[2] );
				if ( self::is_valid_qa( $q, $a ) ) {
					$pairs[] = array( 'q' => $q, 'a' => $a );
				}
			}
		}

		// 3. Existing schema.org Question microdata (don't re-extract if author
		// already shipped the schema, but capture Q/A so we can re-emit it
		// alongside others into one consolidated FAQPage block).
		if ( preg_match_all(
			'/<[^>]+itemtype=["\']https?:\/\/schema\.org\/Question["\'][^>]*>(.*?)(?=<[^>]+itemtype=["\']https?:\/\/schema\.org\/Question|<\/(?:article|section|main|body)|$)/is',
			$html,
			$m,
			PREG_SET_ORDER
		) ) {
			foreach ( $m as $row ) {
				$chunk = $row[1];
				$q = '';
				$a = '';
				if ( preg_match( '/itemprop=["\']name["\'][^>]*>([^<]+)</is', $chunk, $qm ) ) {
					$q = self::clean_qa_text( $qm[1] );
				}
				if ( preg_match( '/itemprop=["\']text["\'][^>]*>(.*?)<\//is', $chunk, $am ) ) {
					$a = self::clean_qa_text( $am[1] );
				}
				if ( self::is_valid_qa( $q, $a ) ) {
					$pairs[] = array( 'q' => $q, 'a' => $a );
				}
			}
		}

		// 4. Div-based FAQ and accordion widgets.
		$pairs = array_merge( $pairs, self::extract_accordion_faqs( $html ) );

		// 5. Question-style heading + following non-heading content. We split on
		// headings so each pair is a heading and the next sibling chunk before
		// the next heading. Headings without a question mark must lead with an
		// interrogative to be considered a question.
		if ( preg_match_all( '/<h([2-4])[^>]*>(.*?)<\/h\1>(.*?)(?=<h[2-4][^>]*>|$)/is', $html, $m, PREG_SET_ORDER ) ) {
			$interrogative = '/^(how|what|why|when|where|who|which|is|are|can|should|does|do)\b/i';
			foreach ( $m as $row ) {
				$q = self::clean_qa_text( $row[2] );
				$a = self::clean_qa_text( $row[3] );
				if ( '' === $q || '' === $a ) {
					continue;
				}
				$looks_like_question = str_ends_with( $q, '?' ) || (bool) preg_match( $interrogative, $q );
				if ( ! $looks_like_question ) {
					continue;
				}
				if ( ! self::is_valid_qa( $q, $a ) ) {
					continue;
				}
				$pairs[] = array( 'q' => $q, 'a' => $a );
			}
		}

		return $pairs;
	}

	/**
	 * FAQ and accordion widgets that render as divs rather than <details>:
	 * the FAQ blocks of Yoast, Rank Math, Spectra and Kadence, Elementor's
	 * accordion and toggle, and any accordion built on the ARIA pattern
	 * (a button with aria-expanded and aria-controls pointing at its panel),
	 * which covers most theme and builder accordions.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function extract_accordion_faqs( string $html ): array {
		$pairs = array();

		// Question class => answer class, for widgets whose answers follow
		// their question in the markup.
		$known = array(
			'schema-faq-question'      => 'schema-faq-answer',        // Yoast FAQ block
			'rank-math-question'       => 'rank-math-answer',         // Rank Math FAQ block
			'uagb-question'            => 'uagb-faq-content',         // Spectra FAQ
			'kt-blocks-accordion-title' => 'kt-accordion-panel-inner', // Kadence accordion
			'elementor-tab-title'      => 'elementor-tab-content',    // Elementor accordion / toggle
		);
		foreach ( $known as $q_class => $a_class ) {
			$q_re = '/<([a-z][a-z0-9]*)\b[^>]*\bclass=("|\')[^"\']*(?<![\w-])' . preg_quote( $q_class, '/' ) . '(?![\w-])[^>]*>/i';
			if ( ! preg_match_all( $q_re, $html, $qs, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
				continue;
			}
			$a_re = '/<[a-z][a-z0-9]*\b[^>]*\bclass=("|\')[^"\']*(?<![\w-])' . preg_quote( $a_class, '/' ) . '(?![\w-])[^>]*>/i';
			foreach ( $qs as $i => $q_match ) {
				$start = $q_match[0][1];
				$next  = isset( $qs[ $i + 1 ] ) ? $qs[ $i + 1 ][0][1] : min( strlen( $html ), $start + 6000 );
				$chunk = substr( $html, $start, $next - $start );
				// Question: the text of the question element itself.
				$tag = $q_match[1][0];
				if ( ! preg_match( '/^<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', $chunk, $qm ) ) {
					continue;
				}
				if ( ! preg_match( $a_re, $chunk, $am, PREG_OFFSET_CAPTURE ) ) {
					continue;
				}
				$q = self::clean_qa_text( $qm[1] );
				$a = self::clean_qa_text( substr( $chunk, $am[0][1] ) );
				if ( self::is_valid_qa( $q, $a ) ) {
					$pairs[] = array( 'q' => $q, 'a' => mb_substr( $a, 0, 1000 ) );
				}
			}
		}

		// ARIA accordions. Only question-like titles count, so tabs and
		// menu toggles that use the same pattern are not mistaken for FAQs.
		if ( preg_match_all( '/<(button|a|div|h[2-6]|span)\b([^>]*\baria-controls=("|\')([\w-]+)\3[^>]*)>(.*?)<\/\1>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				if ( false === stripos( $row[2], 'aria-expanded' ) ) {
					continue;
				}
				$q = self::clean_qa_text( $row[5] );
				if ( ! str_ends_with( $q, '?' ) && ! preg_match( '/^(how|what|why|when|where|who|which|is|are|can|should|does|do|will)\b/i', $q ) ) {
					continue;
				}
				$id = preg_quote( $row[4], '/' );
				if ( ! preg_match( '/<([a-z][a-z0-9]*)\b[^>]*\bid=("|\')' . $id . '\2[^>]*>(.*?)(?=<[a-z][a-z0-9]*\b[^>]*\baria-controls=|<\/section>|<\/article>|$)/is', $html, $panel ) ) {
					continue;
				}
				$a = self::clean_qa_text( $panel[3] );
				if ( self::is_valid_qa( $q, $a ) ) {
					$pairs[] = array( 'q' => $q, 'a' => mb_substr( $a, 0, 1000 ) );
				}
			}
		}

		return $pairs;
	}

	/**
	 * Pull Q&A pairs from Divi toggle and accordion modules.
	 *   [et_pb_toggle title="Q?"]A[/et_pb_toggle]
	 *   [et_pb_accordion_item title="Q?"]A[/et_pb_accordion_item]
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function extract_divi_faqs( string $content ): array {
		if ( false === strpos( $content, '[et_pb_' ) ) {
			return array();
		}
		$pairs = array();
		$patterns = array(
			'/\[et_pb_toggle\b([^\]]*)\](.*?)\[\/et_pb_toggle\]/is',
			'/\[et_pb_accordion_item\b([^\]]*)\](.*?)\[\/et_pb_accordion_item\]/is',
		);
		foreach ( $patterns as $pat ) {
			if ( ! preg_match_all( $pat, $content, $m, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $m as $row ) {
				$attrs = shortcode_parse_atts( $row[1] ) ?: array();
				$q = isset( $attrs['title'] ) ? self::clean_qa_text( $attrs['title'] ) : '';
				$a = self::clean_qa_text( $row[2] );
				if ( self::is_valid_qa( $q, $a ) ) {
					$pairs[] = array( 'q' => $q, 'a' => $a );
				}
			}
		}
		return $pairs;
	}

	/**
	 * Pull Q&A pairs from WPBakery toggle shortcodes.
	 *   [vc_toggle title="Q?"]A[/vc_toggle]
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function extract_wpbakery_faqs( string $content ): array {
		if ( false === strpos( $content, '[vc_toggle' ) ) {
			return array();
		}
		$pairs = array();
		if ( preg_match_all( '/\[vc_toggle\b([^\]]*)\](.*?)\[\/vc_toggle\]/is', $content, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				$attrs = shortcode_parse_atts( $row[1] ) ?: array();
				$q = isset( $attrs['title'] ) ? self::clean_qa_text( $attrs['title'] ) : '';
				$a = self::clean_qa_text( $row[2] );
				if ( self::is_valid_qa( $q, $a ) ) {
					$pairs[] = array( 'q' => $q, 'a' => $a );
				}
			}
		}
		return $pairs;
	}

	/**
	 * Pull Q&A pairs from Elementor accordion/toggle widgets. Walks the
	 * `_elementor_data` JSON tree and recognises widgets whose settings
	 * contain a `tabs` array of `{tab_title, tab_content}` rows, or a
	 * single `accordion_title` + `accordion_content` pair.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function extract_elementor_faqs( WP_Post $post ): array {
		$data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( empty( $data ) ) {
			return array();
		}
		$decoded = json_decode( is_string( $data ) ? $data : wp_json_encode( $data ), true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$pairs = array();
		self::walk_elementor_for_faqs( $decoded, $pairs );
		return $pairs;
	}

	/** All text-bearing settings in an Elementor node and its children. */
	private static function elementor_node_text( array $node ): string {
		$text     = '';
		$settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();
		foreach ( array( 'title', 'editor', 'text', 'description', 'content', 'heading_text' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				$text .= ' ' . $settings[ $key ];
			}
		}
		foreach ( (array) ( $node['elements'] ?? array() ) as $child ) {
			if ( is_array( $child ) ) {
				$text .= ' ' . self::elementor_node_text( $child );
			}
		}
		return $text;
	}

	private static function walk_elementor_for_faqs( array $nodes, array &$pairs ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$settings = $node['settings'] ?? array();
			if ( is_array( $settings ) ) {
				// Multi-item accordion/toggle: settings.tabs[].{tab_title, tab_content}
				if ( ! empty( $settings['tabs'] ) && is_array( $settings['tabs'] ) ) {
					foreach ( $settings['tabs'] as $tab ) {
						if ( ! is_array( $tab ) ) {
							continue;
						}
						$q = self::clean_qa_text( $tab['tab_title'] ?? '' );
						$a = self::clean_qa_text( $tab['tab_content'] ?? '' );
						if ( self::is_valid_qa( $q, $a ) ) {
							$pairs[] = array( 'q' => $q, 'a' => $a );
						}
					}
				}
				// Nested accordion (Elementor 3.15+ default): settings.items[]
				// holds the titles and each answer is the matching child
				// container, so its text is gathered from the child's widgets.
				if ( ! empty( $settings['items'] ) && is_array( $settings['items'] ) && ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
					foreach ( array_values( $settings['items'] ) as $i => $item ) {
						$child = $node['elements'][ $i ] ?? null;
						if ( ! is_array( $item ) || ! is_array( $child ) ) {
							continue;
						}
						$q = self::clean_qa_text( $item['item_title'] ?? '' );
						$a = self::clean_qa_text( self::elementor_node_text( $child ) );
						if ( self::is_valid_qa( $q, $a ) ) {
							$pairs[] = array( 'q' => $q, 'a' => $a );
						}
					}
				}
				// Single accordion item.
				$q = self::clean_qa_text( $settings['accordion_title'] ?? '' );
				$a = self::clean_qa_text( $settings['accordion_content'] ?? '' );
				if ( self::is_valid_qa( $q, $a ) ) {
					$pairs[] = array( 'q' => $q, 'a' => $a );
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_elementor_for_faqs( $node['elements'], $pairs );
			}
		}
	}

	/**
	 * Detect Q&A patterns in ACF data. Walks the full field tree so Q&As are
	 * found whether they live in a top-level repeater, a nested repeater inside
	 * a group, a flexible_content layout with question/answer sub-fields, or a
	 * repeater nested inside a flexible_content layout.
	 *
	 * Recognises any container whose sub-fields include one field with
	 * "question" in its name and another with "answer" in its name.
	 *
	 * Uses the safe-context guard from `extract_acf_fields()` — never invokes
	 * ACF's getter API during a live frontend render.
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function extract_acf_faqs( WP_Post $post ): array {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return array();
		}
		if ( ! self::is_safe_acf_context() ) {
			return array();
		}
		$fields = get_field_objects( $post->ID );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return array();
		}
		$pairs = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$parent = $field['parent'] ?? '';
			if ( $parent && str_starts_with( (string) $parent, 'field_' ) ) {
				continue; // sub-field instance — handled via its parent container
			}
			self::walk_acf_field_for_faqs( $field, $pairs );
		}
		return $pairs;
	}

	/**
	 * Recursively probe a single ACF field object for Q&A rows. Handles
	 * repeater, group, and flexible_content containers to any depth.
	 */
	private static function walk_acf_field_for_faqs( array $field, array &$pairs ): void {
		$type  = $field['type']  ?? '';
		$value = $field['value'] ?? null;
		if ( ! is_array( $value ) ) {
			return;
		}

		if ( 'repeater' === $type ) {
			$sub_fields = (array) ( $field['sub_fields'] ?? array() );
			self::extract_qa_from_rows( $value, $sub_fields, $pairs );
			self::walk_acf_rows_for_faqs( $value, $sub_fields, $pairs );
			return;
		}

		if ( 'group' === $type ) {
			$sub_fields = (array) ( $field['sub_fields'] ?? array() );
			$rows       = array( $value ); // group value is a single row
			self::extract_qa_from_rows( $rows, $sub_fields, $pairs );
			self::walk_acf_rows_for_faqs( $rows, $sub_fields, $pairs );
			return;
		}

		if ( 'flexible_content' === $type ) {
			$layouts = (array) ( $field['layouts'] ?? array() );
			foreach ( $value as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$layout_name       = (string) ( $row['acf_fc_layout'] ?? '' );
				$layout_sub_fields = self::find_flex_layout_sub_fields( $layouts, $layout_name );
				// Each flexible_content row is one layout instance — treat it as a single-row
				// container so a layout with question/answer sub-fields counts as one Q&A.
				self::extract_qa_from_rows( array( $row ), $layout_sub_fields, $pairs );
				self::walk_acf_rows_for_faqs( array( $row ), $layout_sub_fields, $pairs );
			}
		}
	}

	/**
	 * Locate a flexible_content layout definition by its `name` (falls back to key match).
	 */
	private static function find_flex_layout_sub_fields( array $layouts, string $layout_name ): array {
		if ( '' === $layout_name || empty( $layouts ) ) {
			return array();
		}
		foreach ( $layouts as $key => $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}
			if ( ( $layout['name'] ?? '' ) === $layout_name || $key === $layout_name ) {
				return (array) ( $layout['sub_fields'] ?? array() );
			}
		}
		return array();
	}

	/**
	 * If the given sub_fields include a "question" field and an "answer" field,
	 * extract Q&A pairs from each row.
	 */
	private static function extract_qa_from_rows( array $rows, array $sub_fields, array &$pairs ): void {
		$q_key = $a_key = '';
		foreach ( $sub_fields as $sf ) {
			if ( ! is_array( $sf ) ) {
				continue;
			}
			$name = strtolower( (string) ( $sf['name'] ?? '' ) );
			if ( '' === $q_key && false !== strpos( $name, 'question' ) ) {
				$q_key = (string) $sf['name'];
			} elseif ( '' === $a_key && false !== strpos( $name, 'answer' ) ) {
				$a_key = (string) $sf['name'];
			}
		}
		if ( '' === $q_key || '' === $a_key ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$q = self::clean_qa_text( $row[ $q_key ] ?? '' );
			$a = self::clean_qa_text( $row[ $a_key ] ?? '' );
			if ( self::is_valid_qa( $q, $a ) ) {
				$pairs[] = array( 'q' => $q, 'a' => $a );
			}
		}
	}

	/**
	 * Descend into nested containers within a set of rows and continue probing
	 * for Q&A patterns. Complements extract_qa_from_rows: that method looks at
	 * the current level; this one recurses one level deeper into any nested
	 * repeater / group / flexible_content sub-field.
	 */
	private static function walk_acf_rows_for_faqs( array $rows, array $sub_fields, array &$pairs ): void {
		$defs = array();
		foreach ( $sub_fields as $sf ) {
			if ( is_array( $sf ) && isset( $sf['name'] ) ) {
				$defs[ (string) $sf['name'] ] = $sf;
			}
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( $row as $key => $val ) {
				if ( 'acf_fc_layout' === $key ) {
					continue;
				}
				$def     = $defs[ $key ] ?? null;
				$sf_type = is_array( $def ) ? (string) ( $def['type'] ?? '' ) : '';
				if ( '' === $sf_type || ! is_array( $val ) ) {
					continue;
				}
				// Synthesise a field-like array and recurse via the same walker.
				$synth = array( 'type' => $sf_type, 'value' => $val );
				if ( isset( $def['sub_fields'] ) ) {
					$synth['sub_fields'] = $def['sub_fields'];
				}
				if ( isset( $def['layouts'] ) ) {
					$synth['layouts'] = $def['layouts'];
				}
				self::walk_acf_field_for_faqs( $synth, $pairs );
			}
		}
	}

	/**
	 * Normalise question/answer text — strip tags, collapse whitespace.
	 */
	private static function clean_qa_text( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$txt = wp_strip_all_tags( $raw );
		$txt = preg_replace( '/\s+/', ' ', $txt );
		return trim( (string) $txt );
	}

	/**
	 * Heuristic gate so we only emit Q&As that look real: substantive answer,
	 * non-empty question, neither suspiciously short nor enormous.
	 */
	private static function is_valid_qa( string $q, string $a ): bool {
		if ( '' === $q || '' === $a ) {
			return false;
		}
		if ( strlen( $q ) < 8 || strlen( $q ) > 300 ) {
			return false;
		}
		if ( strlen( $a ) < 20 || strlen( $a ) > 5000 ) {
			return false;
		}
		return true;
	}

	/**
	 * Deduplicate FAQ pairs by lower-cased trimmed question text. Keeps the
	 * first occurrence (which is also the highest-confidence source given the
	 * extractor ordering). Public so the live page check can reuse it.
	 */
	public static function dedup_faqs( array $pairs ): array {
		$seen = array();
		$out  = array();
		foreach ( $pairs as $p ) {
			$key = strtolower( trim( $p['q'] ?? '' ) );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[] = $p;
		}
		return $out;
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
		// Note: 'header_size' is deliberately NOT in this list. It holds a tag
		// name ('h2'), not content — collecting it injected the literal string
		// "h2" into the extracted text. It is consumed by elementor_heading()
		// instead, to rebuild the real tag.
		$text_keys = array(
			'title', 'heading_text', 'editor', 'text', 'content', 'description',
			'subtitle', 'caption', 'cta_text', 'button_text', 'tab_title',
			'tab_content', 'item_title', 'item_description', 'testimonial_content',
			'price_subscript', 'price_currency_symbol', 'alt',
			'image_alt',
		);
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( ! empty( $node['settings'] ) && is_array( $node['settings'] ) ) {
				$settings = $node['settings'];

				// Rebuild heading widgets as real <hN> markup. Without this the
				// heading level is lost and downstream heading-hierarchy and
				// question-heading checks see only a bare line of text.
				$heading = self::elementor_heading( $settings );
				if ( '' !== $heading ) {
					$buf[] = $heading;
				}

				// Widget links live in settings.link.url rather than as <a href>,
				// so internal/outbound link checks could not see them. Built before
				// the text loop so the key that supplied the label can be skipped.
				$anchor       = self::elementor_link( $settings );
				$anchor_label = self::elementor_link_label_key( $settings );

				foreach ( $text_keys as $k ) {
					// Skip anything already emitted inside a tag above — otherwise a
					// heading title or a button label is counted twice in word and
					// entity counts.
					if ( 'title' === $k && '' !== $heading ) {
						continue;
					}
					if ( '' !== $anchor && $k === $anchor_label ) {
						continue;
					}
					if ( ! empty( $settings[ $k ] ) && is_string( $settings[ $k ] ) ) {
						$buf[] = $settings[ $k ];
					}
				}

				if ( '' !== $anchor ) {
					$buf[] = $anchor;
				}

				// Repeater fields.
				foreach ( $settings as $key => $val ) {
					if ( is_array( $val ) ) {
						foreach ( $val as $row ) {
							if ( is_array( $row ) ) {
								$row_anchor = self::elementor_link( $row );
								$row_label  = self::elementor_link_label_key( $row );
								foreach ( $text_keys as $tk ) {
									if ( '' !== $row_anchor && $tk === $row_label ) {
										continue;
									}
									if ( ! empty( $row[ $tk ] ) && is_string( $row[ $tk ] ) ) {
										$buf[] = $row[ $tk ];
									}
								}
								if ( '' !== $row_anchor ) {
									$buf[] = $row_anchor;
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
	 * Rebuild an Elementor heading widget as <hN>text</hN>.
	 *
	 * Returns '' when the node is not a heading (no valid header_size), which
	 * is the common case — most widgets carry a 'title' with no level.
	 *
	 * @param array $settings Elementor widget settings.
	 */
	private static function elementor_heading( array $settings ): string {
		if ( empty( $settings['title'] ) || ! is_string( $settings['title'] ) ) {
			return '';
		}
		$tag = isset( $settings['header_size'] ) && is_string( $settings['header_size'] )
			? strtolower( trim( $settings['header_size'] ) )
			: '';
		if ( ! preg_match( '/^h[1-6]$/', $tag ) ) {
			return '';
		}
		return sprintf( '<%1$s>%2$s</%1$s>', $tag, wp_strip_all_tags( $settings['title'] ) );
	}

	/**
	 * Rebuild an Elementor widget link as an <a href> anchor.
	 *
	 * Returns '' when the node carries no URL.
	 *
	 * @param array $settings Elementor widget settings or repeater row.
	 */
	private static function elementor_link( array $settings ): string {
		$url = '';
		if ( isset( $settings['link'] ) && is_array( $settings['link'] ) && ! empty( $settings['link']['url'] ) && is_string( $settings['link']['url'] ) ) {
			$url = $settings['link']['url'];
		} elseif ( ! empty( $settings['url'] ) && is_string( $settings['url'] ) ) {
			$url = $settings['url'];
		}
		if ( '' === trim( $url ) ) {
			return '';
		}

		$label_key = self::elementor_link_label_key( $settings );
		$label     = '' !== $label_key ? wp_strip_all_tags( (string) $settings[ $label_key ] ) : '';

		return sprintf( '<a href="%s">%s</a>', esc_url_raw( $url ), $label );
	}

	/**
	 * Which settings key supplies an anchor's visible label, if any.
	 *
	 * Shared by elementor_link() and the text-collection loop so the label is
	 * emitted exactly once — inside the anchor, not also as a bare line.
	 *
	 * @param array $settings Elementor widget settings or repeater row.
	 * @return string Key name, or '' when none applies.
	 */
	private static function elementor_link_label_key( array $settings ): string {
		foreach ( array( 'text', 'button_text', 'title' ) as $k ) {
			if ( ! empty( $settings[ $k ] ) && is_string( $settings[ $k ] ) ) {
				return $k;
			}
		}
		return '';
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

	const ACF_CACHE_META = '_aeomatic_acf_text';

	/**
	 * Extract text from ACF fields.
	 *
	 * Important: `get_field_objects()` seeds ACF's internal value store with
	 * repeater sub-field instances (e.g. repeater_0_text). If this runs during
	 * `wp_head` on a frontend render, the theme's later `have_rows()` call sees
	 * the polluted state and renders duplicate repeater rows. To avoid that,
	 * we cache the extracted text in post meta and only ever build the cache
	 * outside of a live frontend render (save_post, admin, REST, CLI, cron).
	 */
	private static function extract_acf_fields( WP_Post $post ): string {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return '';
		}

		$cached = get_post_meta( $post->ID, self::ACF_CACHE_META, true );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		// Cache empty — only build it in a safe context. A live frontend
		// render (wp_head) is not safe; return empty and let save_post fill it.
		if ( ! self::is_safe_acf_context() ) {
			return '';
		}

		return self::build_acf_cache( $post );
	}

	/**
	 * Run get_field_objects() and persist the extracted text to post meta.
	 * Returns the freshly built string.
	 */
	public static function build_acf_cache( WP_Post $post ): string {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return '';
		}
		$fields = get_field_objects( $post->ID );
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			update_post_meta( $post->ID, self::ACF_CACHE_META, '' );
			return '';
		}
		$buf = array();
		self::walk_acf_fields( $fields, $buf );
		$text = implode( "\n", $buf );
		update_post_meta( $post->ID, self::ACF_CACHE_META, $text );
		return $text;
	}

	/**
	 * Contexts where it's safe to call get_field_objects() — i.e. not during a
	 * live frontend page render where the theme will iterate ACF data after us.
	 */
	private static function is_safe_acf_context(): bool {
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

	/**
	 * Plain-text-only convenience wrapper around extract().
	 *
	 * The readiness analyzers only need the normalised text, and calling this
	 * instead of wp_strip_all_tags( post_content ) means page-builder and ACF
	 * content is scored on what is actually rendered rather than on an empty
	 * or shortcode-only post_content.
	 *
	 * @param WP_Post|int $post_or_id
	 */
	public static function extract_text( $post_or_id ): string {
		$extracted = self::extract( $post_or_id );
		return trim( (string) ( $extracted['text'] ?? '' ) );
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
