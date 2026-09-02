<?php
/**
 * JSON-LD schema injector.
 *
 * Outputs Article, FAQ, HowTo, Author, Organization and Breadcrumb schema
 * into <head>. Detects the active SEO plugin and avoids duplicate schema
 * by only emitting types that other plugins typically don't cover well.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Schema {

	public function register(): void {
		add_action( 'wp_head', array( $this, 'output' ), 5 );

		// Invalidate the schema cache whenever content changes.
		add_action( 'save_post', array( $this, 'on_post_changed' ), 30, 2 );
		add_action( 'edited_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'on_meta_changed' ), 10, 4 );
		add_action( 'set_object_terms', array( $this, 'on_terms_changed' ), 10, 4 );
	}

	public function on_post_changed( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		AEOMATIC_Schema_Builder::invalidate( $post_id );
		AEOMATIC_Schema_Analyzer::invalidate_cache( $post_id );
		// Rebuild the ACF text cache in a safe context so the frontend never
		// has to call get_field_objects() during wp_head.
		AEOMATIC_Content_Extractor::build_acf_cache( $post );
	}

	/**
	 * Invalidate when ACF or other meta updates — but skip our own cache key
	 * to avoid infinite loops, and skip the audit score meta.
	 */
	public function on_meta_changed( $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		// Don't invalidate on our own bookkeeping writes.
		$skip_keys = array(
			AEOMATIC_Schema_Builder::CACHE_META,
			AEOMATIC_Content_Extractor::ACF_CACHE_META,
			// Our own analysis output. None of these feed schema generation, so
			// writing them must not drop the built schema.
			'_aeomatic_score',
			'_aeomatic_score_breakdown',
			'_aeomatic_ai_readiness',
			'_aeomatic_ai_readiness_fingerprint',
			'_aeomatic_ai_readiness_score',
			'_aeomatic_citation_gaps',
			'_aeomatic_citation_gaps_fingerprint',
			'_aeomatic_citation_gaps_score',
			'_aeomatic_entity_scan',
			'_aeomatic_entity_scan_fingerprint',
			'_aeomatic_entity_coverage',
			'_edit_lock',
			'_edit_last',
			'_encloseme',
		);
		if ( in_array( $meta_key, $skip_keys, true ) ) {
			return;
		}
		if ( ! get_post( $object_id ) ) {
			return;
		}
		AEOMATIC_Schema_Builder::invalidate( $object_id );
	}

	public function on_terms_changed( int $object_id, $terms, $tt_ids, string $taxonomy ): void {
		if ( ! get_post( $object_id ) ) {
			return;
		}
		AEOMATIC_Schema_Builder::invalidate( $object_id );
	}

	/**
	 * Render JSON-LD blocks for the current request.
	 */
	public function output(): void {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		$settings = AEOMATIC_Settings::get( 'schema' );
		if ( empty( $settings ) ) {
			return;
		}

		$blocks = array();

		if ( ! empty( $settings['organization'] ) ) {
			$org = $this->organization_schema( $settings );
			if ( $org ) {
				$blocks[] = $org;
			}
		}

		// WebSite schema with SearchAction — homepage only (sitelinks search box).
		if ( is_front_page() ) {
			$blocks[] = $this->website_schema();
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post && AEOMATIC_Post_Types::is_enabled( $post->post_type ) ) {
				// Use the analyzer to pick the most accurate schema type.
				$analysis    = AEOMATIC_Schema_Analyzer::analyze( $post );
				$schema_type = $analysis['type'];

				// 1. Primary schema via Generator — merges auto-extracted values
				//    with any user-edited field overrides from the visual editor.
				if ( 'Product' === $schema_type && class_exists( 'WooCommerce' ) ) {
					// Use the generator for WooCommerce too — user overrides win.
					$built = AEOMATIC_Schema_Generator::generate( $post, 'Product', $settings );
					if ( $built ) {
						$blocks[] = $built;
					}
				} elseif ( ! empty( $settings['article'] ) ) {
					$built = AEOMATIC_Schema_Generator::generate( $post, $schema_type, $settings );
					if ( $built ) {
						$blocks[] = $built;
					}
				}

				// 2. FAQ schema — only emit as a separate block if main schema isn't already FAQPage.
				if ( ! empty( $settings['faq'] ) && 'FAQPage' !== $schema_type && 'QAPage' !== $schema_type ) {
					$faq = $this->faq_schema_from_post( $post );
					if ( $faq ) {
						$blocks[] = $faq;
					}
				}

				// 3. HowTo — only emit if main schema isn't already step-based.
				if ( ! empty( $settings['howto'] ) && ! in_array( $schema_type, array( 'HowTo', 'Recipe' ), true ) ) {
					$howto = $this->maybe_howto_schema( $post );
					if ( $howto ) {
						$blocks[] = $howto;
					}
				}

				// 4. Author schema.
				if ( ! empty( $settings['author'] ) ) {
					$author = $this->author_schema( (int) $post->post_author );
					if ( $author ) {
						$blocks[] = $author;
					}
				}

				// 5. VideoObject — auto-detected YouTube / Vimeo embeds.
				$video = $this->video_schema( $post );
				if ( $video ) {
					$blocks[] = $video;
				}

				// 6. Speakable — voice-AI discovery signal for singular pages.
				$blocks[] = $this->speakable_schema( $post );
			}
		}

		if ( ! empty( $settings['breadcrumb'] ) && ! is_front_page() ) {
			$crumbs = $this->breadcrumb_schema();
			if ( $crumbs ) {
				$blocks[] = $crumbs;
			}
		}

		foreach ( $blocks as $b ) {
			wp_print_inline_script_tag(
				wp_json_encode( $b, JSON_HEX_TAG | JSON_HEX_AMP ),
				array( 'type' => 'application/ld+json' )
			);
		}

		if ( ! empty( $blocks ) ) {
			AEOMATIC_Activity::bump( 'schema_renders', count( $blocks ) );
		}
	}

	/**
	 * Organization schema — site-wide.
	 */
	private function organization_schema( array $s ): ?array {
		$name = ! empty( $s['org_name'] ) ? $s['org_name'] : get_bloginfo( 'name' );
		if ( empty( $name ) ) {
			return null;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'@id'      => home_url( '/#organization' ),
			'name'     => $name,
			'url'      => home_url( '/' ),
		);
		if ( ! empty( $s['org_logo'] ) ) {
			$schema['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $s['org_logo'],
			);
		}
		if ( ! empty( $s['org_sameas'] ) ) {
			$urls = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $s['org_sameas'] ) ) );
			if ( $urls ) {
				$schema['sameAs'] = array_values( $urls );
			}
		}
		return $schema;
	}

	/**
	 * Build FAQPage schema by extracting FAQ blocks from anywhere in the post.
	 * Uses the content extractor which handles all builders.
	 */
	private function faq_schema_from_post( WP_Post $post ): ?array {
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$qa        = $extracted['faqs'] ?? array();
		if ( empty( $qa ) ) {
			return null;
		}
		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map(
				static function ( $pair ) {
					return array(
						'@type'          => 'Question',
						'name'           => $pair['q'],
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $pair['a'],
						),
					);
				},
				$qa
			),
		);
	}

	/**
	 * Detect if the post looks like a how-to: H2/H3 headings starting with "Step".
	 * Uses the content extractor so Elementor/Divi/ACF content is included.
	 */
	private function maybe_howto_schema( WP_Post $post ): ?array {
		$extracted = AEOMATIC_Content_Extractor::extract( $post );
		$html      = $extracted['html'];
		if ( ! $html ) {
			return null;
		}

		preg_match_all( '/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $matches );
		if ( empty( $matches[1] ) ) {
			return null;
		}

		$step_pattern = '/^\s*(step\s+\d+|[\d]+[\.\)])/i';
		$steps        = array();
		foreach ( $matches[1] as $heading ) {
			$plain = trim( wp_strip_all_tags( $heading ) );
			if ( $plain && preg_match( $step_pattern, $plain ) ) {
				$steps[] = $plain;
			}
		}

		if ( count( $steps ) < 2 ) {
			return null;
		}

		return array(
			'@context' => 'https://schema.org',
			'@type'    => 'HowTo',
			'name'     => wp_strip_all_tags( get_the_title( $post ) ),
			'step'     => array_map(
				static function ( $name, $idx ) {
					return array(
						'@type'    => 'HowToStep',
						'position' => $idx + 1,
						'name'     => $name,
					);
				},
				$steps,
				array_keys( $steps )
			),
		);
	}

	/**
	 * Author / Person schema.
	 */
	private function author_schema( int $user_id ): ?array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'@id'      => get_author_posts_url( $user_id ) . '#person',
			'name'     => $user->display_name,
			'url'      => get_author_posts_url( $user_id ),
		);

		$bio = get_user_meta( $user_id, 'description', true );
		if ( $bio ) {
			$schema['description'] = wp_strip_all_tags( $bio );
		}

		// Build sameAs from common social meta keys if present.
		$same = array();
		foreach ( array( 'twitter', 'facebook', 'linkedin', 'github', 'url' ) as $key ) {
			$val = get_user_meta( $user_id, $key, true );
			if ( $val && filter_var( $val, FILTER_VALIDATE_URL ) ) {
				$same[] = $val;
			}
		}
		if ( $user->user_url && filter_var( $user->user_url, FILTER_VALIDATE_URL ) ) {
			$same[] = $user->user_url;
		}
		$same = array_values( array_unique( $same ) );
		if ( $same ) {
			$schema['sameAs'] = $same;
		}

		// GEO author authority enrichment — read optional user_meta keys that
		// site admins / theme builders can populate to strengthen E-E-A-T.
		// All values support newline-separated lists.
		$schema = self::add_author_authority( $schema, $user_id );

		return $schema;
	}

	/**
	 * Attach Person authority properties from user_meta. The site admin (or
	 * a theme) is expected to add these custom user fields; AEOmatic just
	 * reads and emits them. Newline-separated values become arrays.
	 *
	 *   aeomatic_knows_about     → knowsAbout (string[])
	 *   aeomatic_has_credential  → hasCredential (string[])
	 *   aeomatic_works_for       → worksFor (Organization name)
	 *   aeomatic_alumni_of       → alumniOf (string[])
	 *   aeomatic_award           → award (string[])
	 *   aeomatic_job_title       → jobTitle
	 */
	private function add_author_authority( array $schema, int $user_id ): array {
		$list_keys = array(
			'knows_about'    => 'knowsAbout',
			'has_credential' => 'hasCredential',
			'alumni_of'      => 'alumniOf',
			'award'          => 'award',
		);
		foreach ( $list_keys as $meta_key => $schema_key ) {
			$raw = (string) get_user_meta( $user_id, 'aeomatic_' . $meta_key, true );
			if ( '' === trim( $raw ) ) {
				continue;
			}
			$items = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) ) );
			if ( ! empty( $items ) ) {
				$schema[ $schema_key ] = count( $items ) === 1 ? $items[0] : $items;
			}
		}

		$job_title = (string) get_user_meta( $user_id, 'aeomatic_job_title', true );
		if ( '' !== trim( $job_title ) ) {
			$schema['jobTitle'] = $job_title;
		}

		$works_for = (string) get_user_meta( $user_id, 'aeomatic_works_for', true );
		if ( '' !== trim( $works_for ) ) {
			$schema['worksFor'] = array(
				'@type' => 'Organization',
				'name'  => $works_for,
			);
		}

		return $schema;
	}

	/**
	 * Breadcrumb list — Home → Section → Page.
	 */
	private function breadcrumb_schema(): ?array {
		$items   = array();
		$items[] = array( 'name' => __( 'Home', 'aeomatic' ), 'url' => home_url( '/' ) );

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post ) {
				return null;
			}
			$cats = get_the_category( $post->ID );
			if ( $cats ) {
				$items[] = array(
					'name' => $cats[0]->name,
					'url'  => get_category_link( $cats[0]->term_id ),
				);
			}
			$items[] = array(
				'name' => wp_strip_all_tags( get_the_title( $post ) ),
				'url'  => get_permalink( $post ),
			);
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->name, $term->term_id ) ) {
				$items[] = array(
					'name' => $term->name,
					'url'  => get_term_link( $term ),
				);
			}
		} elseif ( is_archive() ) {
			$items[] = array(
				'name' => wp_strip_all_tags( get_the_archive_title() ),
				'url'  => '',
			);
		} else {
			return null;
		}

		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array_map(
				static function ( $item, $i ) {
					$entry = array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => $item['name'],
					);
					if ( ! empty( $item['url'] ) ) {
						$entry['item'] = $item['url'];
					}
					return $entry;
				},
				$items,
				array_keys( $items )
			),
		);
	}

	/**
	 * WebSite schema with SearchAction — enables sitelinks search box in Google.
	 */
	private function website_schema(): array {
		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'description'     => get_bloginfo( 'description' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * VideoObject schema — auto-detected from YouTube / Vimeo embeds in post content.
	 */
	private function video_schema( WP_Post $post ): ?array {
		$content = $post->post_content;
		$embed   = '';
		$thumb   = '';

		if ( preg_match( '#(?:youtube\.com/(?:embed/|watch\?v=)|youtu\.be/)([a-zA-Z0-9_-]{11})#', $content, $m ) ) {
			$vid   = $m[1];
			$embed = 'https://www.youtube.com/embed/' . $vid;
			$thumb = 'https://img.youtube.com/vi/' . $vid . '/maxresdefault.jpg';
		} elseif ( preg_match( '#vimeo\.com/(?:video/)?(\d+)#', $content, $m ) ) {
			$embed = 'https://player.vimeo.com/video/' . $m[1];
		}

		if ( ! $embed ) {
			return null;
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'VideoObject',
			'name'       => wp_strip_all_tags( get_the_title( $post ) ),
			'embedUrl'   => $embed,
			'uploadDate' => get_the_date( 'c', $post ),
		);

		$desc = wp_strip_all_tags( get_the_excerpt( $post ) );
		if ( $desc ) {
			$schema['description'] = $desc;
		}

		if ( $thumb ) {
			$schema['thumbnailUrl'] = $thumb;
		} else {
			$img = get_the_post_thumbnail_url( $post, 'large' );
			if ( $img ) {
				$schema['thumbnailUrl'] = $img;
			}
		}

		return $schema;
	}

	/**
	 * WebPage + SpeakableSpecification schema — signals to voice-AI engines what
	 * section of the page to read aloud when citing this content.
	 */
	private function speakable_schema( WP_Post $post ): array {
		return array(
			'@context'  => 'https://schema.org',
			'@type'     => 'WebPage',
			'@id'       => get_permalink( $post ) . '#webpage',
			'url'       => get_permalink( $post ),
			'speakable' => array(
				'@type'       => 'SpeakableSpecification',
				'cssSelector' => array( 'h1', '.entry-title', '.entry-content p:first-of-type' ),
			),
		);
	}
}
