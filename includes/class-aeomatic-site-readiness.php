<?php
/**
 * Site-level AI readiness summary.
 *
 * The plugin already scores individual posts two ways — AEOMATIC_Audit (a
 * single 0-100 content rubric) and AEOMATIC_AI_Readiness (five per-post
 * categories). Neither answers the question the dashboard needs to answer:
 * "how ready is this *site* for AI systems, and what should I fix first?"
 *
 * This class answers that by aggregating what already exists. It invents no
 * new measurements and makes no claim about observed AI visibility:
 *
 *   Technical readiness  site configuration we can verify locally
 *                        (llms.txt live, schema on, AI headers, permalinks,
 *                        duplicate-schema conflicts, XML sitemap present)
 *   Content readiness    mean of the per-post Content + Entities readiness
 *                        categories across a sample of published posts
 *   AI crawler access    share of known AI crawlers the site's own robots.txt
 *                        settings currently allow
 *
 * Every category reports how it was derived (`method`) and whether it is a
 * direct check or a heuristic (`kind`), so the UI can label them honestly.
 *
 * What this is NOT: a measure of whether AI systems actually cite the site.
 * That requires querying the engines themselves and is not implemented — see
 * the roadmap in README.md.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Site_Readiness {

	const CACHE_KEY = 'aeomatic_site_readiness_cache';
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * How many recent published posts feed the content sample.
	 *
	 * Bounded because each post runs the five readiness analyzers. The results
	 * are cached per post by AEOMATIC_AI_Readiness, so a warm site pays almost
	 * nothing; this cap bounds the cold case.
	 */
	const SAMPLE_SIZE = 20;

	const REST_NS = 'aeomatic/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Any content or settings change can move the score.
		add_action( 'save_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'update_option_' . AEOMATIC_OPTION_KEY, array( __CLASS__, 'flush_cache' ) );
	}

	public function register_routes(): void {
		register_rest_route( self::REST_NS, '/readiness/site', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_site_readiness' ),
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'force' => array( 'required' => false, 'type' => 'boolean', 'default' => false ),
			),
		) );
	}

	public function rest_site_readiness( WP_REST_Request $request ) {
		return rest_ensure_response( self::summary( (bool) $request->get_param( 'force' ) ) );
	}

	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Build (or read) the site readiness summary.
	 *
	 * @return array{
	 *   overall:int,
	 *   categories:array<int,array{key:string,label:string,score:int,kind:string,method:string,checks:array}>,
	 *   issue_count:int,
	 *   recommendations:array<int,array{title:string,detail:string,url:string}>,
	 *   sample_size:int,
	 *   generated_at:int,
	 *   cached:bool
	 * }
	 */
	public static function summary( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				$cached['cached'] = true;
				return $cached;
			}
		}

		$technical = self::technical_category();
		$content   = self::content_category();
		$access    = self::crawler_access_category();

		$categories = array( $technical, $content, $access );

		// Extension filter: extensions may append categories (e.g. a GSC-data
		// category from AEOmatic Pro). Same shape as the core categories.
		$categories = apply_filters( 'aeomatic_site_readiness_categories', $categories );

		// Unweighted mean. A weighted blend would imply we know the relative
		// citation impact of each axis; no public evidence supports specific
		// weights, so the honest default is equal treatment.
		$scored  = array_filter( $categories, static function ( $c ) { return null !== $c['score']; } );
		$overall = $scored
			? (int) round( array_sum( array_column( $scored, 'score' ) ) / count( $scored ) )
			: 0;

		$issues = 0;
		foreach ( $categories as $category ) {
			foreach ( $category['checks'] as $check ) {
				if ( empty( $check['passed'] ) ) {
					++$issues;
				}
			}
		}

		$payload = array(
			'overall'         => $overall,
			'categories'      => $categories,
			'issue_count'     => $issues,
			'recommendations' => self::top_recommendations( $categories ),
			'sample_size'     => $content['sample_size'] ?? 0,
			'generated_at'    => time(),
			'cached'          => false,
		);

		// Extension filter: last chance to mutate the summary (e.g. reorder
		// recommendations or inject Pro-only ones).
		$payload = apply_filters( 'aeomatic_site_readiness_summary', $payload );

		set_transient( self::CACHE_KEY, $payload, self::CACHE_TTL );

		// Extension action: fires whenever a fresh audit is computed. Consumers
		// like scheduled-audits and trend-charts persist the snapshot from here.
		do_action( 'aeomatic_site_readiness_computed', $payload );

		return $payload;
	}

	// ── Categories ────────────────────────────────────────────────────────

	/**
	 * Technical readiness — site-level configuration we can verify directly.
	 *
	 * Every check here is a fact about this install, not an estimate: either
	 * the setting is on or it is not.
	 */
	private static function technical_category(): array {
		$checks = array();

		$llms_on = (bool) AEOMATIC_Settings::get( 'llms_txt', 'enabled' );
		$checks[] = self::check(
			'llms_txt',
			__( 'llms.txt endpoint is serving', 'aeomatic' ),
			$llms_on,
			__( 'Enable llms.txt in Settings so AI clients can fetch a clean index of your site.', 'aeomatic' ),
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS )
		);

		$schema_on = (bool) AEOMATIC_Settings::get( 'schema', 'article' );
		$checks[] = self::check(
			'schema',
			__( 'Page-level schema is enabled', 'aeomatic' ),
			$schema_on,
			__( 'Turn on Article schema in Settings, or confirm your SEO plugin is emitting it instead.', 'aeomatic' ),
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS )
		);

		$org_named = '' !== trim( (string) AEOMATIC_Settings::get( 'schema', 'org_name' ) );
		$checks[] = self::check(
			'organization',
			__( 'Organisation identity is filled in', 'aeomatic' ),
			$org_named,
			__( 'Set your organisation name and logo so schema can name the entity behind the site.', 'aeomatic' ),
			admin_url( 'admin.php?page=' . AEOMATIC_Wizard::SLUG . '&step=org' )
		);

		// Pretty permalinks are required for the /llms.txt rewrite to resolve.
		$pretty = '' !== (string) get_option( 'permalink_structure' );
		$checks[] = self::check(
			'permalinks',
			__( 'Pretty permalinks are on', 'aeomatic' ),
			$pretty,
			__( 'Plain permalinks prevent /llms.txt from resolving. Pick any other structure under Settings → Permalinks.', 'aeomatic' ),
			admin_url( 'options-permalink.php' )
		);

		$headers_on = class_exists( 'AEOMATIC_AI_Headers' ) && AEOMATIC_AI_Headers::is_enabled();
		$checks[] = self::check(
			'ai_headers',
			__( 'AI discovery headers are sent', 'aeomatic' ),
			$headers_on,
			__( 'Optional. Enable the X-AI-* headers in Settings to advertise citation terms.', 'aeomatic' ),
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS )
		);

		// A live duplicate-schema conflict is a real technical defect: two
		// plugins emitting the same JSON-LD type on one page.
		$no_conflict = ! ( class_exists( 'AEOMATIC_Conflict_Detector' ) && AEOMATIC_Conflict_Detector::has_active_conflicts() );
		$checks[] = self::check(
			'no_duplicate_schema',
			__( 'No duplicate schema with your SEO plugin', 'aeomatic' ),
			$no_conflict,
			__( 'Another SEO plugin emits the same schema types. Resolve the overlap in Settings so pages carry one copy.', 'aeomatic' ),
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS )
		);

		return array(
			'key'    => 'technical',
			'label'  => __( 'Technical readiness', 'aeomatic' ),
			'score'  => self::score_from_checks( $checks ),
			'kind'   => 'direct',
			'method' => __( 'Direct checks against this site\'s configuration.', 'aeomatic' ),
			'checks' => $checks,
		);
	}

	/**
	 * Content readiness — mean of the per-post Content and Entities readiness
	 * categories over a sample of recent published posts.
	 *
	 * Heuristic: the underlying analyzers use pattern matching (opening-answer
	 * detection, question headings, capitalised-token entity spotting), so this
	 * is an estimate of answer-shaped-ness, not a measured outcome.
	 */
	private static function content_category(): array {
		$posts = self::sample_posts();

		if ( empty( $posts ) ) {
			return array(
				'key'         => 'content',
				'label'       => __( 'Content readiness', 'aeomatic' ),
				'score'       => null,
				'kind'        => 'heuristic',
				'method'      => __( 'No published posts to sample yet.', 'aeomatic' ),
				'checks'      => array(),
				'sample_size' => 0,
			);
		}

		$readiness = new AEOMATIC_AI_Readiness();
		$totals    = array();
		$weakest   = array();

		foreach ( $posts as $post ) {
			$report = $readiness->analyze( $post );
			foreach ( (array) ( $report['categories'] ?? array() ) as $category ) {
				$key = (string) ( $category['category'] ?? '' );
				if ( ! in_array( $key, array( 'content', 'entities' ), true ) ) {
					continue;
				}
				$totals[ $key ][] = (int) ( $category['score'] ?? 0 );

				// Keep the first recommendation from the lowest-scoring post so
				// the dashboard can name something concrete to fix.
				$score = (int) ( $category['score'] ?? 0 );
				if ( ! empty( $category['recommendations'] ) &&
					( ! isset( $weakest[ $key ] ) || $score < $weakest[ $key ]['score'] ) ) {
					$weakest[ $key ] = array(
						'score' => $score,
						'text'  => (string) $category['recommendations'][0],
						'post'  => $post,
					);
				}
			}
		}

		$checks = array();
		$labels = array(
			'content'  => __( 'Posts open with an answer and are structured for extraction', 'aeomatic' ),
			'entities' => __( 'Posts name concrete entities and carry a schema type', 'aeomatic' ),
		);

		$means = array();
		foreach ( $totals as $key => $scores ) {
			$mean         = (int) round( array_sum( $scores ) / count( $scores ) );
			$means[]      = $mean;
			$hint         = $weakest[ $key ]['text'] ?? '';
			$weakest_post = $weakest[ $key ]['post'] ?? null;

			$checks[] = self::check(
				'content_' . $key,
				sprintf(
					/* translators: 1: category description, 2: mean score */
					__( '%1$s (avg %2$d/100)', 'aeomatic' ),
					$labels[ $key ] ?? $key,
					$mean
				),
				$mean >= 70,
				$hint,
				$weakest_post instanceof WP_Post ? (string) get_edit_post_link( $weakest_post->ID, 'raw' ) : ''
			);
		}

		return array(
			'key'         => 'content',
			'label'       => __( 'Content readiness', 'aeomatic' ),
			'score'       => $means ? (int) round( array_sum( $means ) / count( $means ) ) : null,
			'kind'        => 'heuristic',
			'method'      => sprintf(
				/* translators: %d: number of posts sampled */
				__( 'Heuristic. Mean of the Content and Entities readiness categories across your %d most recent published items.', 'aeomatic' ),
				count( $posts )
			),
			'checks'      => $checks,
			'sample_size' => count( $posts ),
		);
	}

	/**
	 * AI crawler access — what this site's own robots.txt settings allow.
	 *
	 * Direct check: it reports the configuration AEOmatic writes, not whether
	 * any crawler obeyed it. A low score here is not automatically bad — some
	 * sites deliberately block AI training crawlers.
	 */
	private static function crawler_access_category(): array {
		$crawlers = (array) AEOMATIC_Settings::get( 'crawlers' );
		$total    = count( $crawlers );
		$allowed  = count( array_filter( $crawlers ) );

		$checks = array();

		// The crawlers that fetch pages to answer a live user question are the
		// ones that gate appearing in an AI answer at all. Training-corpus
		// crawlers are a separate, legitimately optional decision.
		$retrieval = array( 'OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User' );
		$blocked_retrieval = array();
		foreach ( $retrieval as $bot ) {
			if ( array_key_exists( $bot, $crawlers ) && ! $crawlers[ $bot ] ) {
				$blocked_retrieval[] = $bot;
			}
		}

		$checks[] = self::check(
			'retrieval_bots_allowed',
			__( 'Answer-time retrieval crawlers are allowed', 'aeomatic' ),
			empty( $blocked_retrieval ),
			$blocked_retrieval
				? sprintf(
					/* translators: %s: comma-separated crawler names */
					__( 'Blocked: %s. These fetch pages to answer a live question — blocking them removes the site from those answers.', 'aeomatic' ),
					implode( ', ', $blocked_retrieval )
				)
				: '',
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS )
		);

		$public = '1' === (string) get_option( 'blog_public' );
		$checks[] = self::check(
			'search_engines_allowed',
			__( 'Site is not discouraging search engines', 'aeomatic' ),
			$public,
			__( 'Reading Settings has "Discourage search engines" enabled, which tells every crawler to stay away.', 'aeomatic' ),
			admin_url( 'options-reading.php' )
		);

		// Score on the share of configured crawlers allowed, so the number
		// reflects the user's own choices rather than a target we impose.
		$score = $total > 0 ? (int) round( ( $allowed / $total ) * 100 ) : 100;

		// A blocked retrieval crawler matters more than a blocked training
		// crawler, so failing that check caps the category.
		if ( ! empty( $blocked_retrieval ) ) {
			$score = min( $score, 60 );
		}
		if ( ! $public ) {
			$score = min( $score, 30 );
		}

		return array(
			'key'    => 'crawler_access',
			'label'  => __( 'AI crawler access', 'aeomatic' ),
			'score'  => $score,
			'kind'   => 'direct',
			'method' => sprintf(
				/* translators: 1: allowed crawler count, 2: total crawler count */
				__( 'Direct check. %1$d of %2$d known AI crawlers are allowed by your robots.txt settings.', 'aeomatic' ),
				$allowed,
				$total
			),
			'checks' => $checks,
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	/**
	 * Recent published items across enabled post types.
	 *
	 * @return WP_Post[]
	 */
	private static function sample_posts(): array {
		$post_types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		$query = new WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => self::SAMPLE_SIZE,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'has_password'   => false,
		) );

		return $query->posts;
	}

	/**
	 * Build one check row.
	 */
	private static function check( string $key, string $label, bool $passed, string $hint = '', string $url = '' ): array {
		return array(
			'key'    => $key,
			'label'  => $label,
			'passed' => $passed,
			'hint'   => $hint,
			'url'    => $url,
		);
	}

	private static function score_from_checks( array $checks ): int {
		if ( empty( $checks ) ) {
			return 0;
		}
		$passed = count( array_filter( $checks, static function ( $c ) { return ! empty( $c['passed'] ); } ) );
		return (int) round( ( $passed / count( $checks ) ) * 100 );
	}

	/**
	 * The three failing checks worth showing first.
	 *
	 * Ordered by the category with the lowest score, so the dashboard points at
	 * the weakest axis instead of an arbitrary list.
	 *
	 * @return array<int,array{title:string,detail:string,url:string}>
	 */
	private static function top_recommendations( array $categories ): array {
		$ranked = array_filter( $categories, static function ( $c ) { return null !== $c['score']; } );
		usort( $ranked, static function ( $a, $b ) { return $a['score'] <=> $b['score']; } );

		$out = array();
		foreach ( $ranked as $category ) {
			foreach ( $category['checks'] as $check ) {
				if ( ! empty( $check['passed'] ) || '' === trim( (string) $check['hint'] ) ) {
					continue;
				}
				$out[] = array(
					'title'  => (string) $check['label'],
					'detail' => (string) $check['hint'],
					'url'    => (string) $check['url'],
				);
				if ( count( $out ) >= 3 ) {
					return $out;
				}
			}
		}

		return $out;
	}
}
