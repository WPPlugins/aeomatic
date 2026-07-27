<?php
/**
 * Internal Coverage gap analyzer — rules that require whole-site context.
 *
 * All rules run against WordPress data only. No external calls.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Gap_Internal_Coverage {

	private const THIN_BODY_MIN_WORDS   = 300;
	private const THIN_BODY_HEAVY_TYPES = array( 'Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'HowTo', 'Recipe', 'Review' );

	private const SIBLING_LOOKUP_LIMIT = 15;
	private const SIBLING_TOPIC_MIN    = 3;

	private const STOPWORDS = array(
		'the','a','an','and','or','but','if','so','of','to','in','on','for','with','by','at','from',
		'is','are','was','were','be','been','being','has','have','had','do','does','did','it','this',
		'that','these','those','you','your','we','our','they','their','not','no','how','what','why',
		'when','where','which','who','can','will','just','use','using','used','get','make','made',
		'best','top','vs','via','into','about','more','some','all','any','each','only','than',
	);

	/**
	 * @return AEOMATIC_Gap_Result[]
	 */
	public function analyze( WP_Post $post, array $extract ): array {
		$gaps = array();

		if ( $r = $this->rule_orphan_page( $post ) )        { $gaps[] = $r; }
		if ( $r = $this->rule_low_outbound_links( $post, $extract ) ) { $gaps[] = $r; }
		if ( $r = $this->rule_sibling_topic_gap( $post, $extract ) )  { $gaps[] = $r; }
		if ( $r = $this->rule_thin_body( $post, $extract ) )          { $gaps[] = $r; }
		if ( $r = $this->rule_no_related_links( $post, $extract ) )   { $gaps[] = $r; }

		return $gaps;
	}

	private function rule_orphan_page( WP_Post $post ): ?AEOMATIC_Gap_Result {
		$permalink = get_permalink( $post );
		if ( ! $permalink ) {
			return null;
		}
		$path = wp_parse_url( $permalink, PHP_URL_PATH );
		if ( ! $path ) {
			return null;
		}

		global $wpdb;
		$like_full = '%' . $wpdb->esc_like( $permalink ) . '%';
		$like_path = '%href="' . $wpdb->esc_like( $path ) . '%';

		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE ID != %d
			   AND post_status = 'publish'
			   AND post_type IN ('post','page')
			   AND ( post_content LIKE %s OR post_content LIKE %s )",
			$post->ID,
			$like_full,
			$like_path
		) );

		if ( $count > 0 ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'orphan_page',
			AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
			__( 'Orphan page — no other published content links here', 'aeomatic' ),
			__( 'Add at least two contextual links to this page from related posts. Orphaned URLs are cited less by AI engines because they lack topical connection signals.', 'aeomatic' ),
			'orphan-page',
			AEOMATIC_Gap_Result::SOURCE_INTERNAL_COVERAGE
		);
	}

	private function rule_low_outbound_links( WP_Post $post, array $extract ): ?AEOMATIC_Gap_Result {
		$home = home_url( '/' );
		$home_host = (string) wp_parse_url( $home, PHP_URL_HOST );
		if ( '' === $home_host ) {
			return null;
		}

		$html = (string) ( $extract['html'] ?? '' );
		if ( '' === $html ) {
			return null;
		}

		if ( ! preg_match_all( '/<a[^>]+href=(["\'])([^"\']+)\1/i', $html, $m ) ) {
			return new AEOMATIC_Gap_Result(
				'low_outbound_links',
				AEOMATIC_Gap_Result::SEVERITY_LOW,
				__( 'No internal links in body', 'aeomatic' ),
				__( 'Add 2–3 links to related posts on your own site. Internal links tell AI engines which pages cluster around a topic.', 'aeomatic' ),
				'internal-links',
				AEOMATIC_Gap_Result::SOURCE_INTERNAL_COVERAGE
			);
		}

		$internal = 0;
		foreach ( $m[2] as $href ) {
			$host = (string) wp_parse_url( $href, PHP_URL_HOST );
			if ( '' === $host || 0 === strcasecmp( $host, $home_host ) || str_starts_with( $href, '/' ) ) {
				$internal++;
			}
		}

		if ( $internal >= 2 ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'low_outbound_links',
			AEOMATIC_Gap_Result::SEVERITY_LOW,
			sprintf(
				/* translators: %d: number of internal links currently in body */
				__( 'Only %d internal link(s) in body', 'aeomatic' ),
				$internal
			),
			__( 'Aim for at least 2 links to related posts on your own site. Internal linking builds the topic cluster signal AI engines use.', 'aeomatic' ),
			'internal-links',
			AEOMATIC_Gap_Result::SOURCE_INTERNAL_COVERAGE
		);
	}

	private function rule_sibling_topic_gap( WP_Post $post, array $extract ): ?AEOMATIC_Gap_Result {
		$categories = get_the_category( $post->ID );
		if ( empty( $categories ) ) {
			return null;
		}
		$cat_ids = array_map( static fn( $c ) => (int) $c->term_id, $categories );

		$siblings = get_posts( array(
			'post_type'      => $post->post_type,
			'post_status'    => 'publish',
			'exclude'        => array( $post->ID ),
			'category__in'   => $cat_ids,
			'posts_per_page' => self::SIBLING_LOOKUP_LIMIT,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		) );

		if ( count( $siblings ) < 2 ) {
			return null;
		}

		$body_terms = $this->tokenize( strtolower( (string) ( $extract['text'] ?? '' ) . ' ' . $post->post_title ) );
		if ( empty( $body_terms ) ) {
			return null;
		}
		$body_index = array_flip( $body_terms );

		$missing = array();
		foreach ( $siblings as $sid ) {
			$title_tokens = $this->tokenize( strtolower( (string) get_the_title( $sid ) ) );
			foreach ( $title_tokens as $t ) {
				if ( strlen( $t ) < 4 ) {
					continue;
				}
				if ( ! isset( $body_index[ $t ] ) && ! isset( $missing[ $t ] ) ) {
					$missing[ $t ] = 1;
				}
			}
		}

		if ( count( $missing ) < self::SIBLING_TOPIC_MIN ) {
			return null;
		}

		$top = array_slice( array_keys( $missing ), 0, 5 );

		return new AEOMATIC_Gap_Result(
			'sibling_topic_gap',
			AEOMATIC_Gap_Result::SEVERITY_MEDIUM,
			__( 'Missing topics that sibling posts cover', 'aeomatic' ),
			sprintf(
				/* translators: %s: comma-separated list of missing terms */
				__( 'Sibling posts in the same category discuss terms this page does not: %s. Consider adding a section that references these topics or linking out to the posts that do.', 'aeomatic' ),
				implode( ', ', $top )
			),
			'sibling-topic-gap',
			AEOMATIC_Gap_Result::SOURCE_INTERNAL_COVERAGE
		);
	}

	private function rule_thin_body( WP_Post $post, array $extract ): ?AEOMATIC_Gap_Result {
		$word_count = (int) ( $extract['word_count'] ?? 0 );
		if ( $word_count >= self::THIN_BODY_MIN_WORDS ) {
			return null;
		}

		$schema_type = 'Article';
		if ( class_exists( 'AEOMATIC_Schema_Analyzer' ) ) {
			$analysis = AEOMATIC_Schema_Analyzer::analyze( $post );
			$schema_type = (string) ( $analysis['type'] ?? 'Article' );
		}

		if ( ! in_array( $schema_type, self::THIN_BODY_HEAVY_TYPES, true ) ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'thin_body',
			AEOMATIC_Gap_Result::SEVERITY_HIGH,
			sprintf(
				/* translators: 1: word count, 2: schema type */
				__( 'Thin body (%1$d words) for %2$s content', 'aeomatic' ),
				$word_count,
				$schema_type
			),
			sprintf(
				/* translators: %d: minimum recommended words */
				__( 'AI engines skip thin pages. Expand to at least %d words with concrete detail, examples, and answers to sub-questions.', 'aeomatic' ),
				self::THIN_BODY_MIN_WORDS
			),
			'thin-body',
			AEOMATIC_Gap_Result::SOURCE_INTERNAL_COVERAGE
		);
	}

	private function rule_no_related_links( WP_Post $post, array $extract ): ?AEOMATIC_Gap_Result {
		$html = (string) ( $extract['html'] ?? '' );
		if ( '' === $html ) {
			return null;
		}

		if ( preg_match( '/\b(related|see also|further reading|read more|you might (also )?like)\b/i', $html ) ) {
			return null;
		}

		return new AEOMATIC_Gap_Result(
			'no_related_links',
			AEOMATIC_Gap_Result::SEVERITY_LOW,
			__( 'No "related" or "see also" section', 'aeomatic' ),
			__( 'Add a short "Related reading" or "See also" section near the bottom with 2–3 links to relevant posts. AI engines use these hints to map topic clusters.', 'aeomatic' ),
			'related-links',
			AEOMATIC_Gap_Result::SOURCE_INTERNAL_COVERAGE
		);
	}

	private function tokenize( string $text ): array {
		$text = preg_replace( '/[^a-z0-9\-\s]+/', ' ', $text );
		$parts = preg_split( '/\s+/', trim( (string) $text ) );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		return array_values( array_filter(
			$parts,
			static fn( $t ) => $t !== '' && strlen( $t ) > 2 && ! in_array( $t, self::STOPWORDS, true )
		) );
	}
}
