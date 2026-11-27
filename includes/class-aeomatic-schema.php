<?php
/**
 * JSON-LD schema injector.
 *
 * Outputs an @id-linked graph into <head>: Organization and WebSite as the
 * site-wide anchors, a WebPage per page, the primary entity for the post,
 * its author, video and breadcrumb.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Schema {

	public function register(): void {
		add_action( 'wp_head', array( $this, 'output' ), 5 );

		// Speakable: mark the opening paragraph, then describe it after the
		// content has rendered — only when the mark is really on the page.
		add_filter( 'the_content', array( $this, 'mark_speakable' ), 20 );
		add_action( 'wp_footer', array( $this, 'speakable_output' ), 5 );

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
			AEOMATIC_Page_Crawler::META,
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
	 *
	 * The blocks form one graph linked by @id: Organization and WebSite are
	 * the site-wide anchors, WebPage is the per-page spine, and the primary
	 * entity, author and breadcrumb all point back at them.
	 */
	public function output(): void {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		$settings = AEOMATIC_Settings::get( 'schema' );
		if ( empty( $settings ) ) {
			return;
		}

		$post        = null;
		$schema_type = '';
		if ( is_singular() ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post && AEOMATIC_Post_Types::is_enabled( $queried->post_type ) ) {
				$post = $queried;
				// Use the analyzer to pick the most accurate schema type.
				$schema_type = AEOMATIC_Schema_Analyzer::analyze( $post )['type'];
			}
		}

		$blocks = array();

		// 1. Organization — printed in full on the homepage and the About page
		//    only. Every other node references it by @id.
		if ( ! empty( $settings['organization'] ) && ( is_front_page() || 'AboutPage' === $schema_type ) ) {
			$org = $this->organization_schema( $settings );
			if ( $org ) {
				$blocks[] = $org;
			}
		}

		// 2. WebSite — homepage only.
		if ( is_front_page() ) {
			$blocks[] = $this->website_schema( $settings );
		}

		if ( $post ) {
			// 3. Primary schema via Generator — merges auto-extracted values
			//    with any user-edited field overrides from the visual editor.
			// A password-protected post shows only the password form, so
			// nothing derived from its content is published until unlocked.
			$protected = post_password_required( $post );
			$primary   = null;
			if ( ! $protected && ( ( 'Product' === $schema_type && class_exists( 'WooCommerce' ) ) || ! empty( $settings['article'] ) ) ) {
				$primary = AEOMATIC_Schema_Generator::generate( $post, $schema_type, $settings ) ?: null;
			}

			// 4. WebPage — the per-page spine. A WebPage-subtype primary
			//    (AboutPage, ProfilePage…) already is that node.
			if ( $primary && in_array( $schema_type, AEOMATIC_Schema_Generator::PAGE_TYPES, true ) ) {
				$blocks[] = $this->page_extras( $primary, get_permalink( $post ), $settings );
			} else {
				$blocks[] = $this->webpage_schema( $post, $settings );
				if ( $primary ) {
					$blocks[] = $primary;
				}
			}

			// 5. Author schema.
			if ( ! empty( $settings['author'] ) ) {
				$author = self::author_schema( (int) $post->post_author );
				if ( $author ) {
					$blocks[] = $author;
				}
			}

			// 6. VideoObject — auto-detected YouTube / Vimeo embeds.
			$video = $protected ? null : $this->video_schema( $post );
			if ( $video ) {
				$blocks[] = $video;
			}
		} elseif ( is_front_page() || is_home() || is_category() || is_tag() || is_tax() ) {
			$page = $this->archive_page_schema( $settings );
			if ( $page ) {
				$blocks[] = $page;
			}
		}

		if ( ! empty( $settings['breadcrumb'] ) && ! is_front_page() ) {
			$crumbs = $this->breadcrumb_schema();
			if ( $crumbs ) {
				$blocks[] = $crumbs;
			}
		}

		foreach ( $blocks as $b ) {
			$this->print_block( $b );
		}

		if ( ! empty( $blocks ) ) {
			AEOMATIC_Activity::bump( 'schema_renders', count( $blocks ) );
		}
	}

	/**
	 * Print one JSON-LD block as a <script type="application/ld+json">.
	 */
	private function print_block( array $b ): void {
		// JSON-LD is parsed by machines but read by people — in page source,
		// in Google's Rich Results Test, and in support threads. The previous
		// flags (JSON_HEX_TAG | JSON_HEX_AMP) combined with PHP's defaults
		// turned every & into \u0026, every < and > into \u003C / \u003E,
		// every / into \/ and every accented or non-Latin character into
		// \uXXXX — all on one unbroken line. That output is valid and Google
		// accepts it, but it is unreadable, and correct schema looked broken.
		// Worse for non-English sites: a German or Japanese headline became
		// nothing but escape sequences.
		$json = wp_json_encode(
			$b,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);
		if ( false === $json ) {
			return;
		}

		// The one escape still required. A literal "</" inside a string value
		// would close the <script> element early, so it is re-escaped as
		// "<\/" — valid JSON, and it leaves "https://" alone because that
		// is "//" rather than "</".
		$json = str_replace( '</', '<\/', $json );
		// "<!--" can switch the HTML parser into an escaped script state that
		// swallows the rest of the page; < is the same character in JSON.
		$json = str_replace( '<!--', '<!--', $json );

		wp_print_inline_script_tag( $json, array( 'type' => 'application/ld+json', 'class' => AEOMATIC_Page_Crawler::SCRIPT_CLASS ) );
	}

	/**
	 * Organization schema — the site-wide entity anchor.
	 *
	 * The business identifiers (DUNS, LEI, VAT…) are the corroborating facts
	 * entity resolution trusts most, and sameAs is how a retrieval system
	 * checks who you are against independent sources.
	 */
	private function organization_schema( array $s ): ?array {
		$name = AEOMATIC_Schema_Generator::org_name( $s );
		if ( '' === $name ) {
			return null;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'@id'      => AEOMATIC_Schema_Generator::org_id(),
			'name'     => $name,
			'url'      => home_url( '/' ),
		);

		$text_props = array(
			'org_legal_name'     => 'legalName',
			'org_alternate_name' => 'alternateName',
			'org_description'    => 'description',
			'org_email'          => 'email',
			'org_telephone'      => 'telephone',
			'org_founding_date'  => 'foundingDate',
			'org_duns'           => 'duns',
			'org_naics'          => 'naics',
			'org_lei'            => 'leiCode',
			'org_vat'            => 'vatID',
			'org_tax_id'         => 'taxID',
		);
		foreach ( $text_props as $key => $prop ) {
			$val = trim( (string) ( $s[ $key ] ?? '' ) );
			if ( '' !== $val ) {
				$schema[ $prop ] = $val;
			}
		}

		if ( ! empty( $s['org_logo'] ) ) {
			$schema['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $s['org_logo'],
			);
		}

		$address = array();
		foreach ( array(
			'org_street'  => 'streetAddress',
			'org_city'    => 'addressLocality',
			'org_region'  => 'addressRegion',
			'org_postal'  => 'postalCode',
			'org_country' => 'addressCountry',
		) as $key => $prop ) {
			$val = trim( (string) ( $s[ $key ] ?? '' ) );
			if ( '' !== $val ) {
				$address[ $prop ] = $val;
			}
		}
		if ( $address ) {
			$schema['address'] = array( '@type' => 'PostalAddress' ) + $address;
		}

		if ( isset( $schema['email'] ) || isset( $schema['telephone'] ) ) {
			$contact = array( '@type' => 'ContactPoint', 'contactType' => 'customer service' );
			if ( isset( $schema['email'] ) ) {
				$contact['email'] = $schema['email'];
			}
			if ( isset( $schema['telephone'] ) ) {
				$contact['telephone'] = $schema['telephone'];
			}
			$schema['contactPoint'] = $contact;
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
	 * WebSite — names the site and links it to its publisher. No
	 * SearchAction: Google retired the sitelinks search box, so it would be
	 * unused bytes on the page.
	 */
	private function website_schema( array $settings ): array {
		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'WebSite',
			'@id'        => AEOMATIC_Schema_Generator::website_id(),
			'name'       => get_bloginfo( 'name' ),
			'url'        => home_url( '/' ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
		$tagline = get_bloginfo( 'description' );
		if ( $tagline ) {
			$schema['description'] = $tagline;
		}
		if ( ! empty( $settings['organization'] ) ) {
			$schema['publisher'] = array( '@id' => AEOMATIC_Schema_Generator::org_id() );
		}
		return $schema;
	}

	/**
	 * WebPage — the per-page spine the primary entity anchors to through
	 * mainEntityOfPage.
	 */
	private function webpage_schema( WP_Post $post, array $settings ): array {
		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'WebPage',
			'@id'           => AEOMATIC_Schema_Generator::webpage_id( $post ),
			'url'           => get_permalink( $post ),
			'name'          => wp_strip_all_tags( get_the_title( $post ) ),
			'isPartOf'      => array( '@id' => AEOMATIC_Schema_Generator::website_id() ),
			'inLanguage'    => get_bloginfo( 'language' ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
		);
		$desc = wp_strip_all_tags( get_the_excerpt( $post ) );
		if ( $desc ) {
			$schema['description'] = $desc;
		}
		return $this->page_extras( $schema, get_permalink( $post ), $settings );
	}

	/**
	 * WebPage (homepage showing latest posts) or CollectionPage (category,
	 * tag and taxonomy archives).
	 */
	private function archive_page_schema( array $settings ): ?array {
		if ( is_front_page() || is_home() ) {
			$url  = is_front_page() ? home_url( '/' ) : (string) get_permalink( (int) get_option( 'page_for_posts' ) );
			$name = is_front_page() ? get_bloginfo( 'name' ) : wp_strip_all_tags( get_the_title( (int) get_option( 'page_for_posts' ) ) );
			$type = is_front_page() ? 'WebPage' : 'CollectionPage';
		} else {
			$term = get_queried_object();
			if ( ! $term || ! isset( $term->term_id ) ) {
				return null;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				return null;
			}
			$url  = $link;
			$name = $term->name;
			$type = 'CollectionPage';
		}
		if ( '' === $url ) {
			return null;
		}
		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => $type,
			'@id'        => $url . '#webpage',
			'url'        => $url,
			'name'       => $name,
			'isPartOf'   => array( '@id' => AEOMATIC_Schema_Generator::website_id() ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
		if ( ! empty( $settings['organization'] ) && is_front_page() ) {
			$schema['about'] = array( '@id' => AEOMATIC_Schema_Generator::org_id() );
		}
		return $schema;
	}

	/**
	 * Attach the breadcrumb reference to a page node.
	 */
	private function page_extras( array $page, string $url, array $settings ): array {
		if ( ! empty( $settings['breadcrumb'] ) && ! is_front_page() ) {
			$page['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
		}
		return $page;
	}

	/** Class put on the opening paragraph so the speakable selector always matches. */
	const SPEAKABLE_CLASS = 'aeomatic-speakable';

	/** Whether mark_speakable() marked a paragraph in the page body in this request. */
	private bool $speakable_marked = false;

	/**
	 * Speakable is still beta and only used for English-language publishers
	 * on Google Assistant devices.
	 */
	private function speakable_applies(): bool {
		if ( ! is_singular() || 0 !== strpos( get_locale(), 'en' ) ) {
			return false;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post && AEOMATIC_Post_Types::is_enabled( $post->post_type );
	}

	/**
	 * Add SPEAKABLE_CLASS to the first real paragraph of the main post's
	 * content. A fixed theme selector such as ".entry-content p" does not
	 * exist in block themes or page builders, and validators report a
	 * selector that matches nothing on the page as an error.
	 *
	 * the_content also runs for copies that are never printed: the
	 * automatic excerpt (get_the_excerpt → wp_trim_excerpt), which the
	 * WebPage description builds in wp_head, and SEO plugins reading the
	 * content for meta tags. Those calls are skipped, and every other call
	 * for the main post is marked, so whichever copy the theme prints
	 * carries the class.
	 */
	public function mark_speakable( $content ) {
		if ( ! is_string( $content ) || is_admin() || is_feed() || ! $this->speakable_applies() ) {
			return $content;
		}
		if ( doing_action( 'wp_head' ) || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		if ( get_the_ID() !== get_queried_object_id() || ! is_main_query() ) {
			return $content;
		}
		if ( preg_match( '/\bclass=("|\')[^"\']*(?<![\w-])' . self::SPEAKABLE_CLASS . '(?![\w-])/i', $content ) ) {
			$this->speakable_marked = true;
			return $content;
		}
		if ( ! preg_match_all( '/<p(\s[^>]*)?>(.*?)<\/p>/is', $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			return $content;
		}
		foreach ( $matches as $p ) {
			// Real prose only: skip captions, datelines, spacer paragraphs and
			// anything that is just a link or a shortcode.
			$attrs = isset( $p[1] ) && -1 !== $p[1][1] ? $p[1][0] : '';
			$text  = trim( wp_strip_all_tags( $p[2][0] ) );
			if ( mb_strlen( $text ) < 40 || preg_match( '/caption|byline|dateline/i', $attrs ) ) {
				continue;
			}
			$old_open = '<p' . $attrs . '>';
			if ( preg_match( '/\bclass=("|\')/i', $attrs ) ) {
				$new_attrs = preg_replace( '/\bclass=("|\')/i', 'class=$1' . self::SPEAKABLE_CLASS . ' ', $attrs, 1 );
			} else {
				$new_attrs = $attrs . ' class="' . self::SPEAKABLE_CLASS . '"';
			}
			$content = substr_replace( $content, '<p' . $new_attrs . '>', $p[0][1], strlen( $old_open ) );
			$this->speakable_marked = true;
			break;
		}
		return $content;
	}

	/**
	 * Speakable for the page node, printed in the footer and merged into the
	 * WebPage by @id. Skipped when no paragraph was marked, so the selector
	 * never points at nothing.
	 */
	public function speakable_output(): void {
		if ( ! $this->speakable_marked || ! $this->speakable_applies() ) {
			return;
		}
		$post = get_queried_object();
		$this->print_block( array(
			'@context'  => 'https://schema.org',
			'@type'     => 'WebPage',
			'@id'       => AEOMATIC_Schema_Generator::webpage_id( $post ),
			'url'       => get_permalink( $post ),
			'speakable' => array(
				'@type'       => 'SpeakableSpecification',
				'cssSelector' => array( '.' . self::SPEAKABLE_CLASS ),
			),
		) );
	}

	/**
	 * Author / Person schema.
	 *
	 * Public static so the editor-sidebar preview endpoint
	 * (AEOMATIC_Audit::rest_schema_preview) can build the same block the
	 * front-end emits; the preview panel previously only rendered the primary
	 * schema, so a ticked Author / Person checkbox looked like a no-op.
	 */
	public static function author_schema( int $user_id ): ?array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'@id'      => AEOMATIC_Schema_Generator::person_id( $user_id ),
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
	private static function add_author_authority( array $schema, int $user_id ): array {
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
	 * Breadcrumb list — the path a visitor would take, not the folder
	 * structure: Home → parent pages / post-type archive / category → page.
	 * Always two or more entries, each with an item URL.
	 */
	private function breadcrumb_schema(): ?array {
		$items   = array();
		$items[] = array( 'name' => __( 'Home', 'aeomatic' ), 'url' => home_url( '/' ) );
		$self    = '';

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post ) {
				return null;
			}
			if ( is_post_type_hierarchical( $post->post_type ) ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
					$items[] = array(
						'name' => wp_strip_all_tags( get_the_title( $ancestor_id ) ),
						'url'  => get_permalink( $ancestor_id ),
					);
				}
			} elseif ( 'post' === $post->post_type ) {
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					foreach ( array_reverse( get_ancestors( $cats[0]->term_id, 'category', 'taxonomy' ) ) as $cat_id ) {
						$items[] = array(
							'name' => get_cat_name( $cat_id ),
							'url'  => get_category_link( $cat_id ),
						);
					}
					$items[] = array(
						'name' => $cats[0]->name,
						'url'  => get_category_link( $cats[0]->term_id ),
					);
				}
			} else {
				$pto  = get_post_type_object( $post->post_type );
				$link = get_post_type_archive_link( $post->post_type );
				if ( $pto && $pto->has_archive && $link ) {
					$items[] = array(
						'name' => $pto->labels->name,
						'url'  => $link,
					);
				}
			}
			$self    = get_permalink( $post );
			$items[] = array(
				'name' => wp_strip_all_tags( get_the_title( $post ) ),
				'url'  => $self,
			);
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->name, $term->term_id ) ) {
				$link = get_term_link( $term );
				$self = is_wp_error( $link ) ? '' : $link;
				$items[] = array(
					'name' => $term->name,
					'url'  => $self,
				);
			}
		} elseif ( is_archive() ) {
			$self    = $this->archive_url();
			$items[] = array(
				'name' => wp_strip_all_tags( get_the_archive_title() ),
				'url'  => $self,
			);
		} else {
			return null;
		}

		// Every ListItem needs a name and an item URL (Search Console flags
		// any that lack one). Drop intermediate crumbs whose link or label
		// could not be resolved; give the current page fallbacks.
		$last = count( $items ) - 1;
		if ( empty( $items[ $last ]['url'] ) ) {
			$items[ $last ]['url'] = $this->current_url();
		}
		if ( '' === trim( (string) $items[ $last ]['name'] ) ) {
			$items[ $last ]['name'] = wp_strip_all_tags( wp_get_document_title() );
		}
		$items = array_values(
			array_filter(
				$items,
				static function ( $item ) {
					return is_string( $item['url'] ) && '' !== $item['url']
						&& '' !== trim( (string) $item['name'] );
				}
			)
		);

		// One entry is not a hierarchy.
		if ( count( $items ) < 2 ) {
			return null;
		}

		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array_map(
				static function ( $item, $i ) {
					return array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => $item['name'],
						'item'     => $item['url'],
					);
				},
				$items,
				array_keys( $items )
			),
		);
		if ( is_string( $self ) && '' !== $self ) {
			$schema['@id'] = $self . '#breadcrumb';
		}
		return $schema;
	}

	/**
	 * Canonical URL of the current non-term archive (author, date,
	 * post-type), or '' when it cannot be determined.
	 */
	private function archive_url(): string {
		$url = '';
		if ( is_post_type_archive() ) {
			$pt  = get_query_var( 'post_type' );
			$url = get_post_type_archive_link( is_array( $pt ) ? reset( $pt ) : $pt );
		} elseif ( is_author() ) {
			$url = get_author_posts_url( (int) get_queried_object_id() );
		} elseif ( is_day() ) {
			$url = get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
		} elseif ( is_month() ) {
			$url = get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
		} elseif ( is_year() ) {
			$url = get_year_link( get_query_var( 'year' ) );
		}
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Absolute URL of the current request, without the query string.
	 */
	private function current_url(): string {
		global $wp;
		$path = ( $wp instanceof WP && isset( $wp->request ) ) ? $wp->request : '';
		return home_url( user_trailingslashit( '/' . ltrim( (string) $path, '/' ) ) );
	}

	/**
	 * VideoObject schema — auto-detected from YouTube / Vimeo embeds in post
	 * content. name, thumbnailUrl and uploadDate are all required, so a
	 * video with no thumbnail is skipped rather than emitted invalid.
	 */
	private function video_schema( WP_Post $post ): ?array {
		$content = $post->post_content;
		$embed   = '';
		$thumb   = '';

		if ( preg_match( '#(?:youtube\.com/(?:embed/|watch\?v=)|youtu\.be/)([a-zA-Z0-9_-]{11})#', $content, $m ) ) {
			$vid   = $m[1];
			$embed = 'https://www.youtube.com/embed/' . $vid;
			// hqdefault exists for every video; maxresdefault does not.
			$thumb = 'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg';
		} elseif ( preg_match( '#vimeo\.com/(?:video/)?(\d+)#', $content, $m ) ) {
			$embed = 'https://player.vimeo.com/video/' . $m[1];
		}

		if ( ! $embed ) {
			return null;
		}

		if ( ! $thumb ) {
			$thumb = (string) get_the_post_thumbnail_url( $post, 'large' );
		}
		if ( ! $thumb ) {
			return null;
		}

		$schema = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'@id'          => get_permalink( $post ) . '#video',
			'name'         => wp_strip_all_tags( get_the_title( $post ) ),
			'embedUrl'     => $embed,
			'thumbnailUrl' => $thumb,
			// ISO 8601 with the site's timezone offset.
			'uploadDate'   => get_the_date( 'c', $post ),
		);

		$desc = wp_strip_all_tags( get_the_excerpt( $post ) );
		if ( $desc ) {
			$schema['description'] = $desc;
		}

		return $schema;
	}
}
