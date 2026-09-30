<?php
/**
 * AI Access — live crawler access check and "What AI sees".
 *
 * Two admin-only tools that answer the question most AEO settings pages skip:
 * can an AI crawler actually read this site, and what text does it get?
 *
 * 1. Crawler access check. For each AI crawler, reads the robots.txt the site
 *    is really serving (which may be a physical file that overrides WordPress),
 *    evaluates it for that crawler's product token (RFC 9309 matching), then
 *    requests sample pages with that crawler's user agent and compares the
 *    response with a normal browser request. A firewall, CDN rule or security
 *    plugin that treats the crawler differently shows up here.
 *
 * 2. What AI sees. Fetches a page the way a crawler that does not run
 *    JavaScript does (OpenAI, Anthropic and Perplexity crawlers among them),
 *    extracts the visible text from the raw HTML, and compares it with the
 *    content AEOmatic reads from the editor. Text that only appears after
 *    JavaScript runs is invisible to those crawlers.
 *
 * Limits, stated in the UI as well: the requests come from this server, not
 * from the crawler's own network. A firewall that verifies a crawler's IP
 * address may block this test while letting the real crawler through, so the
 * results are cross-referenced with real visits from the AI crawler log.
 *
 * Everything runs on this server. The only HTTP requests go to this site.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_AI_Access {

	const REST_NS        = 'aeomatic/v1';
	const RESULTS_OPTION = 'aeomatic_ai_access_results';
	const MAX_SAMPLE     = 3;
	const TIMEOUT        = 12;
	const TEST_HEADER    = 'X-AEOmatic-Access-Test';
	const TOKEN_OPTION   = 'aeomatic_ai_access_token';

	/** Crawler purposes, in display order. */
	const PURPOSE_ANSWER   = 'answer';
	const PURPOSE_SEARCH   = 'search';
	const PURPOSE_TRAINING = 'training';

	/** Per-crawler verdicts. */
	const V_OK       = 'ok';
	const V_WARN     = 'warn';
	const V_BLOCKED  = 'blocked';
	const V_ROBOTS   = 'robots';
	const V_SKIPPED  = 'skipped';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	// ── Crawler profiles ──────────────────────────────────────────────────

	/**
	 * AI and search crawlers checked, keyed by robots.txt product token.
	 *
	 * `fetch` is false for tokens that exist only in robots.txt (Google-Extended,
	 * Applebot-Extended): those vendors fetch with their normal search crawler,
	 * so only the robots.txt rule is meaningful.
	 *
	 * @return array<string,array{vendor:string,purpose:string,fetch:bool,ua:string,note:string}>
	 */
	public static function crawlers(): array {
		$webkit = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; %s)';

		$list = array(
			'OAI-SearchBot'      => array( 'OpenAI', self::PURPOSE_ANSWER, true, sprintf( $webkit, 'OAI-SearchBot/1.0; +https://openai.com/searchbot' ), __( 'Finds pages to show and link in ChatGPT search answers.', 'aeomatic' ) ),
			'ChatGPT-User'       => array( 'OpenAI', self::PURPOSE_ANSWER, true, sprintf( $webkit, 'ChatGPT-User/1.0; +https://openai.com/bot' ), __( 'Fetches a page when a ChatGPT user asks about it.', 'aeomatic' ) ),
			'Claude-SearchBot'   => array( 'Anthropic', self::PURPOSE_ANSWER, true, sprintf( $webkit, 'Claude-SearchBot/1.0; +Claude-SearchBot@anthropic.com' ), __( 'Finds pages for Claude search answers.', 'aeomatic' ) ),
			'Claude-User'        => array( 'Anthropic', self::PURPOSE_ANSWER, true, sprintf( $webkit, 'Claude-User/1.0; +Claude-User@anthropic.com' ), __( 'Fetches a page when a Claude user asks about it.', 'aeomatic' ) ),
			'PerplexityBot'      => array( 'Perplexity', self::PURPOSE_ANSWER, true, sprintf( $webkit, 'PerplexityBot/1.0; +https://perplexity.ai/perplexitybot' ), __( 'Indexes pages for Perplexity answers.', 'aeomatic' ) ),
			'Perplexity-User'    => array( 'Perplexity', self::PURPOSE_ANSWER, true, sprintf( $webkit, 'Perplexity-User/1.0; +https://perplexity.ai/perplexity-user' ), __( 'Fetches a page when a Perplexity user asks about it.', 'aeomatic' ) ),
			'DuckAssistBot'      => array( 'DuckDuckGo', self::PURPOSE_ANSWER, true, sprintf( $webkit, 'DuckAssistBot/1.1; +https://duckduckgo.com/duckassistbot' ), __( 'Fetches pages for DuckDuckGo AI answers.', 'aeomatic' ) ),
			'Googlebot'          => array( 'Google', self::PURPOSE_SEARCH, true, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', __( 'Google Search, including AI Overviews and AI Mode.', 'aeomatic' ) ),
			'bingbot'            => array( 'Microsoft', self::PURPOSE_SEARCH, true, 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', __( 'Bing Search, which Microsoft Copilot answers from.', 'aeomatic' ) ),
			'Applebot'           => array( 'Apple', self::PURPOSE_SEARCH, true, 'Mozilla/5.0 (compatible; Applebot/0.1; +http://www.apple.com/go/applebot)', __( 'Siri and Spotlight suggestions.', 'aeomatic' ) ),
			'GPTBot'             => array( 'OpenAI', self::PURPOSE_TRAINING, true, sprintf( $webkit, 'GPTBot/1.1; +https://openai.com/gptbot' ), __( 'Collects content to train OpenAI models.', 'aeomatic' ) ),
			'ClaudeBot'          => array( 'Anthropic', self::PURPOSE_TRAINING, true, sprintf( $webkit, 'ClaudeBot/1.0; +claudebot@anthropic.com' ), __( 'Collects content to train Anthropic models.', 'aeomatic' ) ),
			'Google-Extended'    => array( 'Google', self::PURPOSE_TRAINING, false, '', __( 'robots.txt token only. Controls use of your content for Gemini training and grounding; does not affect Google Search.', 'aeomatic' ) ),
			'Applebot-Extended'  => array( 'Apple', self::PURPOSE_TRAINING, false, '', __( 'robots.txt token only. Controls use of your content to train Apple models.', 'aeomatic' ) ),
			'meta-externalagent' => array( 'Meta', self::PURPOSE_TRAINING, true, 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)', __( 'Collects content to train Meta AI models.', 'aeomatic' ) ),
			'Amazonbot'          => array( 'Amazon', self::PURPOSE_TRAINING, true, 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot)', __( 'Alexa answers and Amazon AI models.', 'aeomatic' ) ),
			'CCBot'              => array( 'Common Crawl', self::PURPOSE_TRAINING, true, 'CCBot/2.0 (https://commoncrawl.org/faq/)', __( 'Open web archive used to train many AI models.', 'aeomatic' ) ),
			'Bytespider'         => array( 'ByteDance', self::PURPOSE_TRAINING, true, 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)', __( 'Collects content to train ByteDance models.', 'aeomatic' ) ),
		);

		$out = array();
		foreach ( $list as $token => $row ) {
			$out[ $token ] = array(
				'vendor'  => $row[0],
				'purpose' => $row[1],
				'fetch'   => $row[2],
				'ua'      => $row[3],
				'note'    => $row[4],
			);
		}

		/**
		 * Filter the crawlers the AI Access check tests.
		 *
		 * @param array $out Keyed by robots.txt product token.
		 */
		return (array) apply_filters( 'aeomatic_ai_access_crawlers', $out );
	}

	/**
	 * Per-site secret sent with test requests so they are not logged as real
	 * crawler visits. Not a security boundary: at worst a spoofed header hides
	 * one request from the log.
	 */
	public static function test_token(): string {
		$token = (string) get_option( self::TOKEN_OPTION, '' );
		if ( '' === $token ) {
			$token = wp_generate_password( 32, false, false );
			update_option( self::TOKEN_OPTION, $token, false );
		}
		return $token;
	}

	/** Is the current request one of this module's own test requests? */
	public static function is_test_request(): bool {
		$key = 'HTTP_' . strtoupper( str_replace( '-', '_', self::TEST_HEADER ) );
		if ( empty( $_SERVER[ $key ] ) ) {
			return false;
		}
		$sent  = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$token = (string) get_option( self::TOKEN_OPTION, '' );
		return '' !== $token && hash_equals( $token, $sent );
	}

	/** Browser user agent used for the baseline request. */
	private static function browser_ua(): string {
		return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';
	}

	// ── REST ──────────────────────────────────────────────────────────────

	public function register_routes(): void {
		$admin_only = static function () {
			return current_user_can( 'manage_options' );
		};

		// Start a run: robots.txt analysis, sample URLs, browser baseline.
		register_rest_route( self::REST_NS, '/ai-access/start', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_start' ),
			'permission_callback' => $admin_only,
		) );

		// Test one crawler. Called once per crawler so no single request runs
		// long enough to hit a PHP or proxy timeout.
		register_rest_route( self::REST_NS, '/ai-access/crawler', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_crawler' ),
			'permission_callback' => $admin_only,
			'args'                => array(
				'bot' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
		) );

		// Last saved results, for the dashboard and readiness score.
		register_rest_route( self::REST_NS, '/ai-access/results', array(
			'methods'             => 'GET',
			'callback'            => static function () {
				return rest_ensure_response( self::summary() );
			},
			'permission_callback' => $admin_only,
		) );

		// What AI sees, for one post.
		register_rest_route( self::REST_NS, '/ai-access/view/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_view' ),
			'permission_callback' => static function ( WP_REST_Request $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
		) );
	}

	public function rest_start( WP_REST_Request $request ) {
		$robots  = self::fetch_robots();
		$samples = self::sample_urls();

		$baseline = array();
		foreach ( $samples as $sample ) {
			$baseline[ $sample['url'] ] = self::fetch( $sample['url'], self::browser_ua() );
		}

		$state = array(
			'started_at' => time(),
			'robots'     => $robots,
			'samples'    => $samples,
			'baseline'   => $baseline,
			'crawlers'   => array(),
		);
		update_option( self::RESULTS_OPTION, $state, false );

		$crawlers = array();
		foreach ( self::crawlers() as $token => $profile ) {
			$crawlers[] = array(
				'token'   => $token,
				'vendor'  => $profile['vendor'],
				'purpose' => $profile['purpose'],
				'fetch'   => $profile['fetch'],
				'note'    => $profile['note'],
			);
		}

		return rest_ensure_response( array(
			'robots'   => self::public_robots( $robots ),
			'samples'  => $samples,
			'baseline' => array_map( array( __CLASS__, 'public_fetch' ), $baseline ),
			'crawlers' => $crawlers,
			'settings' => self::settings_mismatches( $robots ),
		) );
	}

	public function rest_crawler( WP_REST_Request $request ) {
		$token    = (string) $request->get_param( 'bot' );
		$crawlers = self::crawlers();
		if ( ! isset( $crawlers[ $token ] ) ) {
			return new WP_Error( 'aeomatic_unknown_bot', __( 'Unknown crawler.', 'aeomatic' ), array( 'status' => 400 ) );
		}

		$state = get_option( self::RESULTS_OPTION );
		if ( ! is_array( $state ) || empty( $state['samples'] ) ) {
			return new WP_Error( 'aeomatic_not_started', __( 'Start the check first.', 'aeomatic' ), array( 'status' => 409 ) );
		}

		$result = self::check_crawler( $token, $crawlers[ $token ], $state );

		$state['crawlers'][ $token ] = $result;
		$state['checked_at']         = time();
		update_option( self::RESULTS_OPTION, $state, false );

		return rest_ensure_response( $result );
	}

	public function rest_view( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'aeomatic_not_public', __( 'Only published posts can be checked, because crawlers only see published pages.', 'aeomatic' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( self::what_ai_sees( $post ) );
	}

	// ── Crawler access check ──────────────────────────────────────────────

	/**
	 * Check one crawler against robots.txt and every sample URL.
	 *
	 * @param string $token   Product token.
	 * @param array  $profile Crawler profile.
	 * @param array  $state   Run state from rest_start().
	 */
	public static function check_crawler( string $token, array $profile, array $state ): array {
		$robots_rules = (array) ( $state['robots']['groups'] ?? array() );
		$pages        = array();
		$robots_block = array();

		foreach ( (array) $state['samples'] as $sample ) {
			$path    = (string) wp_parse_url( $sample['url'], PHP_URL_PATH );
			$path    = '' === $path ? '/' : $path;
			$allowed = self::robots_allows( $robots_rules, $token, $path );
			if ( ! $allowed ) {
				$robots_block[] = $path;
			}

			$row = array(
				'url'            => $sample['url'],
				'label'          => $sample['label'],
				'robots_allowed' => $allowed,
			);

			if ( $profile['fetch'] ) {
				$response = self::fetch( $sample['url'], $profile['ua'] );
				$baseline = $state['baseline'][ $sample['url'] ] ?? null;
				$row      = array_merge( $row, self::compare( $response, is_array( $baseline ) ? $baseline : null, $token ) );
			}

			$pages[] = $row;
		}

		$verdict = self::verdict( $profile, $pages, $robots_block );
		$seen    = self::last_real_visit( $token );

		// A blocked test with recent successful real visits usually means a
		// firewall is verifying the crawler's IP address: this test comes from
		// the site's own server, so it fails verification; the real crawler
		// passes. Say so instead of reporting a false alarm.
		$ip_note = '';
		if ( self::V_BLOCKED === $verdict['verdict'] && $seen && $seen['status'] >= 200 && $seen['status'] < 300 ) {
			$ip_note = __( 'Real visits from this crawler are getting through, so your firewall probably checks the crawler\'s IP address. This test comes from your own server, so it cannot pass that check. No action needed if the real visits keep succeeding.', 'aeomatic' );
			$verdict['verdict'] = self::V_WARN;
		}

		return array(
			'token'       => $token,
			'vendor'      => $profile['vendor'],
			'purpose'     => $profile['purpose'],
			'fetch'       => $profile['fetch'],
			'note'        => $profile['note'],
			'verdict'     => $verdict['verdict'],
			'summary'     => $verdict['summary'],
			'robots'      => array(
				'allowed'       => empty( $robots_block ),
				'blocked_paths' => $robots_block,
			),
			'pages'       => $pages,
			'last_seen'   => $seen,
			'ip_note'     => $ip_note,
			'checked_at'  => time(),
		);
	}

	/**
	 * Compare a crawler response with the browser baseline.
	 *
	 * @param array      $r        Crawler response from fetch().
	 * @param array|null $baseline Browser response from fetch().
	 * @param string     $token    Crawler token, for bot-specific noindex rules.
	 */
	private static function compare( array $r, ?array $baseline, string $token ): array {
		$status   = (int) $r['status'];
		$b_status = $baseline ? (int) $baseline['status'] : 0;
		$outcome  = 'ok';
		$reason   = '';

		if ( '' !== $r['error'] ) {
			$outcome = 'error';
			$reason  = sprintf(
				/* translators: %s: transport error message */
				__( 'The request failed: %s', 'aeomatic' ),
				$r['error']
			);
		} elseif ( $r['challenge'] ) {
			$outcome = 'blocked';
			$reason  = __( 'Got a bot challenge or CAPTCHA page instead of your content. A firewall or CDN is blocking this crawler.', 'aeomatic' );
		} elseif ( in_array( $status, array( 401, 403, 406, 429, 451 ), true ) || ( 503 === $status && 503 !== $b_status ) ) {
			$outcome = 'blocked';
			$reason  = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Your server answered %d to this crawler. Browsers get the page, so a firewall, security plugin or CDN rule is blocking it.', 'aeomatic' ),
				$status
			);
			if ( $b_status === $status ) {
				$reason = sprintf(
					/* translators: %d: HTTP status code */
					__( 'Your server answered %d to both this crawler and a normal browser, so the page itself is not public.', 'aeomatic' ),
					$status
				);
			}
		} elseif ( $status >= 500 ) {
			$outcome = 'error';
			$reason  = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Server error %d.', 'aeomatic' ),
				$status
			);
		} elseif ( 404 === $status && 200 === $b_status ) {
			$outcome = 'blocked';
			$reason  = __( 'This crawler got "not found" while browsers get the page. Something is hiding it from crawlers.', 'aeomatic' );
		} elseif ( $status >= 300 && $status < 400 ) {
			$outcome = 'warn';
			$reason  = sprintf(
				/* translators: 1: HTTP status code, 2: redirect target */
				__( 'Redirected (%1$d) to %2$s after 3 hops. Crawlers may give up on long redirect chains.', 'aeomatic' ),
				$status,
				$r['location']
			);
		} elseif ( 200 === $status && $baseline && 200 === $b_status && $baseline['text_len'] > 500 && $r['text_len'] < 0.5 * $baseline['text_len'] ) {
			$outcome = 'warn';
			$reason  = sprintf(
				/* translators: 1: crawler text length, 2: browser text length */
				__( 'This crawler got much less text than a browser (%1$s vs %2$s characters). It may be served a stripped or different page.', 'aeomatic' ),
				number_format_i18n( $r['text_len'] ),
				number_format_i18n( $baseline['text_len'] )
			);
		}

		// noindex applies to search and answer engines even when fetching works.
		$noindex = self::noindex_for( $r, $token );
		if ( 'ok' === $outcome && $noindex ) {
			$outcome = 'warn';
			$reason  = sprintf(
				/* translators: %s: where the noindex rule was found */
				__( 'The page is reachable but marked noindex (%s), so engines should not show it.', 'aeomatic' ),
				$noindex
			);
		}

		return array(
			'status'   => $status,
			'outcome'  => $outcome,
			'reason'   => $reason,
			'text_len' => (int) $r['text_len'],
			'time_ms'  => (int) $r['time_ms'],
		);
	}

	/**
	 * Roll page results up into one verdict for a crawler.
	 */
	private static function verdict( array $profile, array $pages, array $robots_block ): array {
		if ( ! empty( $robots_block ) ) {
			return array(
				'verdict' => self::V_ROBOTS,
				'summary' => self::PURPOSE_TRAINING === $profile['purpose']
					? __( 'Blocked by your robots.txt. Blocking training crawlers is a valid choice; it does not remove you from AI search answers.', 'aeomatic' )
					: __( 'Blocked by your robots.txt, so this engine should not use your pages in answers.', 'aeomatic' ),
			);
		}

		if ( ! $profile['fetch'] ) {
			return array(
				'verdict' => self::V_OK,
				'summary' => __( 'Allowed by your robots.txt.', 'aeomatic' ),
			);
		}

		$outcomes = wp_list_pluck( $pages, 'outcome' );
		if ( in_array( 'blocked', $outcomes, true ) || in_array( 'error', $outcomes, true ) ) {
			$reasons = array();
			foreach ( $pages as $p ) {
				if ( in_array( $p['outcome'] ?? '', array( 'blocked', 'error' ), true ) ) {
					$reasons[] = $p['reason'];
				}
			}
			return array(
				'verdict' => self::V_BLOCKED,
				'summary' => (string) reset( $reasons ),
			);
		}

		if ( in_array( 'warn', $outcomes, true ) ) {
			foreach ( $pages as $p ) {
				if ( 'warn' === ( $p['outcome'] ?? '' ) ) {
					return array(
						'verdict' => self::V_WARN,
						'summary' => $p['reason'],
					);
				}
			}
		}

		return array(
			'verdict' => self::V_OK,
			'summary' => __( 'Allowed by robots.txt and gets the same pages as a browser.', 'aeomatic' ),
		);
	}

	/**
	 * Settings that do not match the robots.txt actually being served.
	 *
	 * The usual cause is a physical robots.txt file, which the web server
	 * serves directly, so WordPress (and AEOmatic's crawler settings) never run.
	 *
	 * @return array<int,string>
	 */
	private static function settings_mismatches( array $robots ): array {
		$out = array();

		if ( ! empty( $robots['physical_file'] ) ) {
			$out[] = __( 'A robots.txt file exists in your WordPress folder. Your web server serves that file directly, so AEOmatic\'s crawler settings are not being applied. Edit or remove that file to use the settings.', 'aeomatic' );
		}

		$crawlers = (array) AEOMATIC_Settings::get( 'crawlers' );
		$groups   = (array) ( $robots['groups'] ?? array() );
		$wrong    = array();
		foreach ( $crawlers as $bot => $allowed ) {
			$live = self::robots_allows( $groups, (string) $bot, '/' );
			if ( (bool) $allowed !== $live ) {
				$wrong[] = sprintf(
					/* translators: 1: crawler name, 2: "allowed" or "blocked" */
					__( '%1$s (setting says %2$s)', 'aeomatic' ),
					$bot,
					$allowed ? __( 'allowed', 'aeomatic' ) : __( 'blocked', 'aeomatic' )
				);
			}
		}
		if ( $wrong && ( ! empty( $robots['fetched'] ) ) ) {
			$out[] = sprintf(
				/* translators: %s: list of crawlers */
				__( 'Your live robots.txt does not match your AEOmatic crawler settings for: %s. Another plugin, a physical file or a server rule is changing it.', 'aeomatic' ),
				implode( ', ', $wrong )
			);
		}

		if ( '1' !== (string) get_option( 'blog_public' ) ) {
			$out[] = __( 'Settings → Reading has "Discourage search engines from indexing this site" turned on.', 'aeomatic' );
		}

		return $out;
	}

	/**
	 * Most recent real visit from a crawler, from the AI crawler log.
	 *
	 * @return array{time:int,status:int,url:string}|null
	 */
	private static function last_real_visit( string $token ): ?array {
		if ( ! class_exists( 'AEOMATIC_Crawler_Log' ) ) {
			return null;
		}
		$entries = (array) AEOMATIC_Crawler_Log::get_recent( 500 );
		$best    = null;
		foreach ( $entries as $e ) {
			$bot = (string) ( $e['bot'] ?? '' );
			if ( 0 !== strcasecmp( $bot, $token ) ) {
				continue;
			}
			$time = (int) ( $e['time'] ?? 0 );
			if ( null === $best || $time > $best['time'] ) {
				$best = array(
					'time'   => $time,
					'status' => (int) ( $e['status'] ?? 0 ),
					'url'    => (string) ( $e['url'] ?? '' ),
				);
			}
		}
		return $best;
	}

	/**
	 * Saved results from the last run, rolled up for the dashboard.
	 *
	 * @return array{checked_at:int,has_results:bool,answer_ok:int,answer_total:int,blocked:array<int,string>,warn:array<int,string>,crawlers:array}
	 */
	public static function summary(): array {
		$state    = get_option( self::RESULTS_OPTION );
		$crawlers = is_array( $state ) ? (array) ( $state['crawlers'] ?? array() ) : array();

		$answer_ok    = 0;
		$answer_total = 0;
		$blocked      = array();
		$warn         = array();

		foreach ( $crawlers as $token => $c ) {
			if ( self::PURPOSE_TRAINING !== ( $c['purpose'] ?? '' ) ) {
				++$answer_total;
				if ( self::V_OK === $c['verdict'] ) {
					++$answer_ok;
				}
			}
			if ( in_array( $c['verdict'], array( self::V_BLOCKED, self::V_ROBOTS ), true ) && self::PURPOSE_TRAINING !== $c['purpose'] ) {
				$blocked[] = (string) $token;
			} elseif ( self::V_WARN === $c['verdict'] ) {
				$warn[] = (string) $token;
			}
		}

		return array(
			'checked_at'   => is_array( $state ) ? (int) ( $state['checked_at'] ?? 0 ) : 0,
			'has_results'  => ! empty( $crawlers ),
			'answer_ok'    => $answer_ok,
			'answer_total' => $answer_total,
			'blocked'      => $blocked,
			'warn'         => $warn,
			'crawlers'     => $crawlers,
		);
	}

	// ── Sample URLs ───────────────────────────────────────────────────────

	/**
	 * Home page plus the most recent published items. Password-protected and
	 * noindexed items are skipped by WP_Query / has_password.
	 *
	 * @return array<int,array{url:string,label:string}>
	 */
	public static function sample_urls(): array {
		$out = array(
			array(
				'url'   => home_url( '/' ),
				'label' => __( 'Home page', 'aeomatic' ),
			),
		);

		$types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $types ) ) {
			$types = array( 'post', 'page' );
		}

		$query = new WP_Query( array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'has_password'   => false,
		) );

		$front = (int) get_option( 'page_on_front' );
		$seen  = array();
		foreach ( $query->posts as $p ) {
			if ( count( $out ) >= self::MAX_SAMPLE ) {
				break;
			}
			if ( $p->ID === $front || isset( $seen[ $p->post_type ] ) ) {
				continue;
			}
			$seen[ $p->post_type ] = true;
			$out[]                 = array(
				'url'   => (string) get_permalink( $p ),
				'label' => get_the_title( $p ),
			);
		}

		/**
		 * Filter the URLs the AI Access check requests. Keep them on this site.
		 *
		 * @param array $out List of { url, label }.
		 */
		$out = (array) apply_filters( 'aeomatic_ai_access_sample_urls', $out );

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return array_values( array_filter( $out, static function ( $s ) use ( $host ) {
			return isset( $s['url'] ) && wp_parse_url( $s['url'], PHP_URL_HOST ) === $host;
		} ) );
	}

	// ── HTTP ──────────────────────────────────────────────────────────────

	/**
	 * GET a URL on this site with a given user agent, following up to three
	 * same-site redirects so each hop's status is visible.
	 *
	 * @return array{status:int,error:string,location:string,body:string,text_len:int,challenge:bool,x_robots:string,time_ms:int}
	 */
	public static function fetch( string $url, string $ua ): array {
		$start    = microtime( true );
		$hops     = 0;
		$location = '';
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );

		do {
			$response = wp_remote_get( $url, array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'user-agent'  => $ua,
				'headers'     => array(
					// Lets the AI crawler log skip this request: it is a test,
					// not a real crawler visit.
					self::TEST_HEADER => self::test_token(),
					'Accept'          => 'text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8',
					'Accept-Language' => 'en',
					'Cache-Control'   => 'no-cache',
				),
				// Same rule WordPress core uses for loopback requests.
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ),
				'cookies'     => array(),
			) );

			if ( is_wp_error( $response ) ) {
				return array(
					'status'    => 0,
					'error'     => $response->get_error_message(),
					'location'  => '',
					'body'      => '',
					'text_len'  => 0,
					'challenge' => false,
					'x_robots'  => '',
					'time_ms'   => (int) round( ( microtime( true ) - $start ) * 1000 ),
				);
			}

			$status   = (int) wp_remote_retrieve_response_code( $response );
			$location = (string) wp_remote_retrieve_header( $response, 'location' );
			if ( $status >= 300 && $status < 400 && '' !== $location ) {
				$next = WP_Http::make_absolute_url( $location, $url );
				if ( wp_parse_url( $next, PHP_URL_HOST ) !== $host ) {
					break;
				}
				$url = $next;
				++$hops;
				continue;
			}
			break;
		} while ( $hops < 3 );

		$body = (string) wp_remote_retrieve_body( $response );
		$body = substr( $body, 0, 3 * MB_IN_BYTES );

		$x_robots = wp_remote_retrieve_header( $response, 'x-robots-tag' );
		if ( is_array( $x_robots ) ) {
			$x_robots = implode( ', ', $x_robots );
		}

		return array(
			'status'    => $status,
			'error'     => '',
			'location'  => $location,
			'body'      => $body,
			'text_len'  => mb_strlen( self::visible_text( $body ) ),
			'challenge' => self::looks_like_challenge( $status, $body, $response ),
			'x_robots'  => (string) $x_robots,
			'time_ms'   => (int) round( ( microtime( true ) - $start ) * 1000 ),
		);
	}

	/**
	 * Does this response look like a bot challenge rather than the page?
	 *
	 * @param array|WP_Error $response Raw response.
	 */
	private static function looks_like_challenge( int $status, string $body, $response ): bool {
		if ( ! is_array( $response ) ) {
			return false;
		}
		if ( 'challenge' === strtolower( (string) wp_remote_retrieve_header( $response, 'cf-mitigated' ) ) ) {
			return true;
		}
		$head = strtolower( substr( $body, 0, 20000 ) );
		$markers = array(
			'cf-browser-verification',
			'challenges.cloudflare.com',
			'cf_chl_opt',
			'<title>just a moment...</title>',
			'<title>attention required! | cloudflare</title>',
			'sucuri website firewall - access denied',
			'wordfence-blocked',
			'generated by wordfence',
			'_incapsula_resource',
			'ddos-guard',
			'g-recaptcha',
			'h-captcha',
			'hcaptcha.com/1/api.js',
		);
		foreach ( $markers as $m ) {
			if ( false !== strpos( $head, $m ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Where a noindex rule applies to this crawler, or '' if none.
	 */
	private static function noindex_for( array $r, string $token ): string {
		$token_l = strtolower( $token );

		foreach ( explode( ',', strtolower( $r['x_robots'] ) ) as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			// "gptbot: noindex" applies to one crawler; "noindex" to all.
			if ( false !== strpos( $part, ':' ) ) {
				list( $who, $what ) = array_map( 'trim', explode( ':', $part, 2 ) );
				if ( $who === $token_l && preg_match( '/\b(noindex|none)\b/', $what ) ) {
					return 'X-Robots-Tag';
				}
				continue;
			}
			if ( preg_match( '/^(noindex|none)$/', $part ) ) {
				return 'X-Robots-Tag';
			}
		}

		if ( '' === $r['body'] ) {
			return '';
		}
		if ( preg_match_all( '/<meta\s[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $r['body'], $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $tag ) {
				$name = strtolower( trim( $tag[1] ) );
				if ( 'robots' !== $name && $name !== $token_l ) {
					continue;
				}
				if ( preg_match( '/content\s*=\s*["\']([^"\']*)["\']/i', $tag[0], $c ) && preg_match( '/\b(noindex|none)\b/i', $c[1] ) ) {
					return 'robots' === $name ? 'meta robots' : 'meta ' . $tag[1];
				}
			}
		}
		return '';
	}

	/** Strip heavy fields before sending a fetch result to the browser. */
	public static function public_fetch( array $r ): array {
		unset( $r['body'] );
		return $r;
	}

	// ── robots.txt ────────────────────────────────────────────────────────

	/**
	 * Fetch and parse the robots.txt this site is really serving.
	 *
	 * @return array{fetched:bool,status:int,url:string,physical_file:bool,groups:array,body:string,error:string}
	 */
	public static function fetch_robots(): array {
		$url      = home_url( '/robots.txt' );
		$response = self::fetch( $url, self::browser_ua() );
		$physical = file_exists( ABSPATH . 'robots.txt' );

		$ok   = '' === $response['error'] && 200 === (int) $response['status'];
		$body = $ok ? $response['body'] : '';

		// RFC 9309: a 4xx robots.txt means "no restrictions"; an unreachable
		// (5xx / network error) one means crawlers should assume full disallow.
		$status = (int) $response['status'];
		$groups = self::parse_robots( $body );
		if ( '' !== $response['error'] || $status >= 500 ) {
			$groups = array(
				array(
					'agents' => array( '*' ),
					'rules'  => array( array( 'allow' => false, 'path' => '/' ) ),
				),
			);
		}

		return array(
			'fetched'       => $ok,
			'status'        => $status,
			'url'           => $url,
			'physical_file' => $physical,
			'groups'        => $groups,
			'body'          => substr( $body, 0, 20000 ),
			'error'         => $response['error'],
		);
	}

	private static function public_robots( array $robots ): array {
		return array(
			'fetched'       => $robots['fetched'],
			'status'        => $robots['status'],
			'url'           => $robots['url'],
			'physical_file' => $robots['physical_file'],
			'error'         => $robots['error'],
			'body'          => $robots['body'],
		);
	}

	/**
	 * Parse robots.txt into groups of { agents[], rules[] } (RFC 9309).
	 *
	 * @return array<int,array{agents:array<int,string>,rules:array<int,array{allow:bool,path:string}>}>
	 */
	public static function parse_robots( string $body ): array {
		$groups  = array();
		$current = null;
		$in_ua   = false;

		foreach ( preg_split( '/\r\n|\r|\n/', $body ) as $line ) {
			$hash = strpos( $line, '#' );
			if ( false !== $hash ) {
				$line = substr( $line, 0, $hash );
			}
			$line = trim( $line, " \t" );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $key, $value ) = explode( ':', $line, 2 );
			$key   = strtolower( trim( $key, " \t" ) );
			$value = trim( $value, " \t" );

			if ( 'user-agent' === $key ) {
				if ( ! $in_ua ) {
					if ( null !== $current ) {
						$groups[] = $current;
					}
					$current = array(
						'agents' => array(),
						'rules'  => array(),
					);
				}
				$current['agents'][] = strtolower( $value );
				$in_ua               = true;
				continue;
			}

			if ( 'allow' === $key || 'disallow' === $key ) {
				$in_ua = false;
				if ( null === $current ) {
					continue; // Rules before any user-agent line are ignored.
				}
				$current['rules'][] = array(
					'allow' => 'allow' === $key,
					'path'  => $value,
				);
				continue;
			}

			// Sitemap, Crawl-delay etc. do not end a group's agent list in
			// practice, but they are not rules either.
			$in_ua = false;
		}

		if ( null !== $current ) {
			$groups[] = $current;
		}
		return $groups;
	}

	/**
	 * Is $path allowed for crawler $token under these robots.txt groups?
	 *
	 * Group selection: every group naming the token (case-insensitive), merged;
	 * otherwise every "*" group, merged; otherwise allowed. Rule selection: the
	 * longest matching path wins, and allow wins a tie. An empty Disallow value
	 * matches nothing.
	 */
	public static function robots_allows( array $groups, string $token, string $path ): bool {
		$token = strtolower( $token );
		$rules = array();
		$star  = array();

		foreach ( $groups as $g ) {
			foreach ( (array) $g['agents'] as $agent ) {
				if ( $agent === $token ) {
					$rules = array_merge( $rules, (array) $g['rules'] );
					continue 2;
				}
				if ( '*' === $agent ) {
					$star = array_merge( $star, (array) $g['rules'] );
				}
			}
		}
		if ( empty( $rules ) ) {
			$named = false;
			foreach ( $groups as $g ) {
				if ( in_array( $token, (array) $g['agents'], true ) ) {
					$named = true;
					break;
				}
			}
			if ( ! $named ) {
				$rules = $star;
			}
		}

		$best_len   = -1;
		$best_allow = true;
		foreach ( $rules as $rule ) {
			$pattern = (string) $rule['path'];
			if ( '' === $pattern ) {
				continue;
			}
			if ( ! self::robots_path_matches( $pattern, $path ) ) {
				continue;
			}
			$len = strlen( $pattern );
			if ( $len > $best_len || ( $len === $best_len && $rule['allow'] ) ) {
				$best_len   = $len;
				$best_allow = (bool) $rule['allow'];
			}
		}
		return $best_allow;
	}

	/** robots.txt path pattern match with * and $ support. */
	public static function robots_path_matches( string $pattern, string $path ): bool {
		$anchored = '$' === substr( $pattern, -1 );
		if ( $anchored ) {
			$pattern = substr( $pattern, 0, -1 );
		}
		$regex = '';
		foreach ( explode( '*', $pattern ) as $i => $part ) {
			$regex .= ( $i > 0 ? '.*' : '' ) . preg_quote( $part, '#' );
		}
		return (bool) preg_match( '#^' . $regex . ( $anchored ? '$' : '' ) . '#', $path );
	}

	// ── What AI sees ──────────────────────────────────────────────────────

	/**
	 * What a crawler that does not run JavaScript gets for a post.
	 *
	 * @return array<string,mixed>
	 */
	public static function what_ai_sees( WP_Post $post ): array {
		$url      = (string) get_permalink( $post );
		$crawlers = self::crawlers();
		$ua       = isset( $crawlers['OAI-SearchBot'] ) ? $crawlers['OAI-SearchBot']['ua'] : self::browser_ua();
		$response = self::fetch( $url, $ua );

		$base = array(
			'post_id'  => $post->ID,
			'url'      => $url,
			'status'   => $response['status'],
			'error'    => $response['error'],
			'fetch_as' => 'OAI-SearchBot',
		);

		if ( '' !== $response['error'] || 200 !== (int) $response['status'] || $response['challenge'] ) {
			return array_merge( $base, array(
				'ok'      => false,
				'message' => $response['challenge']
					? __( 'The crawler got a bot challenge page instead of your content. Run the crawler access check for details.', 'aeomatic' )
					: sprintf(
						/* translators: %s: HTTP status or error */
						__( 'The crawler could not load this page (%s). Run the crawler access check for details.', 'aeomatic' ),
						'' !== $response['error'] ? $response['error'] : (string) $response['status']
					),
			) );
		}

		$html     = $response['body'];
		$parsed   = self::parse_page( $html );
		$raw_norm = self::normalise( $parsed['all_text'] );

		// What the editor holds, read through AEOmatic's page-builder-aware
		// extractor. Sentences from it that are missing from the raw HTML only
		// appear after JavaScript runs (or are hidden).
		$extract  = AEOMATIC_Content_Extractor::extract( $post );
		$expected = self::sentences( (string) ( $extract['text'] ?? '' ) );
		$missing  = array();
		$found    = 0;
		foreach ( $expected as $sentence ) {
			if ( false !== strpos( $raw_norm, self::normalise( $sentence ) ) ) {
				++$found;
			} else {
				$missing[] = $sentence;
			}
		}
		$coverage = $expected ? (int) round( ( $found / count( $expected ) ) * 100 ) : null;

		$findings = array();
		if ( 0 === $coverage ) {
			$findings[] = array(
				'level' => 'err',
				'text'  => __( 'None of your content is in the page HTML. It loads with JavaScript, so ChatGPT, Claude and Perplexity crawlers cannot read it. Ask your developer or page builder support about server-side rendering, or move key text out of JavaScript widgets (tabs, sliders, loaded-on-scroll sections).', 'aeomatic' ),
			);
		} elseif ( null !== $coverage && $coverage < 60 ) {
			$findings[] = array(
				'level' => 'err',
				'text'  => sprintf(
					/* translators: %d: percentage */
					__( 'Only %d%% of your content is in the page HTML. The rest loads with JavaScript, so ChatGPT, Claude and Perplexity crawlers cannot read it. Ask your developer or page builder support about server-side rendering, or move key text out of JavaScript widgets (tabs, sliders, loaded-on-scroll sections).', 'aeomatic' ),
					$coverage
				),
			);
		} elseif ( null !== $coverage && $coverage < 90 ) {
			$findings[] = array(
				'level' => 'warn',
				'text'  => sprintf(
					/* translators: %d: percentage */
					__( '%d%% of your content is in the page HTML. The missing sentences below only appear after JavaScript runs, or are hidden.', 'aeomatic' ),
					$coverage
				),
			);
		}

		// Only call it an app shell when the editor holds real content that
		// the HTML is missing; a short page with a few scripts is not one.
		if ( $parsed['app_shell'] && null !== $coverage && $coverage < 50 ) {
			$findings[] = array(
				'level' => 'err',
				'text'  => __( 'The page body is almost empty until JavaScript runs (an app shell). Crawlers that do not run JavaScript see a blank page.', 'aeomatic' ),
			);
		}
		if ( '' === $parsed['h1'] ) {
			$findings[] = array(
				'level' => 'warn',
				'text'  => __( 'No H1 heading in the HTML. A clear main heading helps engines understand what the page answers.', 'aeomatic' ),
			);
		}
		if ( $parsed['main_words'] > 0 && $parsed['main_words'] < 150 && ( null === $coverage || $coverage >= 60 ) ) {
			$findings[] = array(
				'level' => 'warn',
				'text'  => sprintf(
					/* translators: %d: word count */
					__( 'The main content has only %d words in the HTML. Short pages give AI assistants little to quote.', 'aeomatic' ),
					$parsed['main_words']
				),
			);
		}
		if ( empty( $findings ) ) {
			$findings[] = array(
				'level' => 'ok',
				'text'  => __( 'Your content is in the page HTML, so crawlers that do not run JavaScript can read it.', 'aeomatic' ),
			);
		}

		return array_merge( $base, array(
			'ok'          => true,
			'coverage'    => $coverage,
			'expected'    => count( $expected ),
			'found'       => $found,
			'missing'     => array_slice( $missing, 0, 12 ),
			'title'       => $parsed['title'],
			'description' => $parsed['description'],
			'h1'          => $parsed['h1'],
			'headings'    => array_slice( $parsed['headings'], 0, 30 ),
			'main_text'   => mb_substr( $parsed['main_text'], 0, 6000 ),
			'main_words'  => $parsed['main_words'],
			'html_kb'     => (int) round( strlen( $html ) / 1024 ),
			'scripts'     => $parsed['scripts'],
			'jsonld'      => $parsed['jsonld'],
			'findings'    => $findings,
			'builder'     => AEOMATIC_Content_Extractor::builder_label( (string) ( $extract['builder'] ?? 'unknown' ) ),
		) );
	}

	/**
	 * Pull the parts of a page a text-only crawler can use.
	 *
	 * @return array{title:string,description:string,h1:string,headings:array,main_text:string,main_words:int,all_text:string,scripts:int,jsonld:int,app_shell:bool}
	 */
	public static function parse_page( string $html ): array {
		$out = array(
			'title'       => '',
			'description' => '',
			'h1'          => '',
			'headings'    => array(),
			'main_text'   => '',
			'main_words'  => 0,
			'all_text'    => '',
			'scripts'     => 0,
			'jsonld'      => 0,
			'app_shell'   => false,
		);
		if ( '' === trim( $html ) ) {
			return $out;
		}

		$out['scripts'] = preg_match_all( '/<script\b/i', $html );
		$out['jsonld']  = preg_match_all( '/<script[^>]+type\s*=\s*["\']application\/ld\+json["\']/i', $html );

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$xp = new DOMXPath( $doc );

		$t = $xp->query( '//title' );
		if ( $t && $t->length ) {
			$out['title'] = self::clean( $t->item( 0 )->textContent );
		}
		$d = $xp->query( '//meta[translate(@name,"DESCRIPTION","description")="description"]/@content' );
		if ( $d && $d->length ) {
			$out['description'] = self::clean( $d->item( 0 )->nodeValue );
		}

		// Things a text crawler does not read as page text.
		foreach ( array( '//script', '//style', '//template', '//svg', '//iframe', '//head' ) as $q ) {
			$nodes = $xp->query( $q );
			if ( ! $nodes ) {
				continue;
			}
			for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
				$n = $nodes->item( $i );
				if ( $n && $n->parentNode ) {
					$n->parentNode->removeChild( $n );
				}
			}
		}

		$h = $xp->query( '//h1|//h2|//h3|//h4' );
		if ( $h ) {
			foreach ( $h as $node ) {
				$text = self::clean( $node->textContent );
				if ( '' === $text ) {
					continue;
				}
				$level = (int) substr( $node->nodeName, 1 );
				if ( 1 === $level && '' === $out['h1'] ) {
					$out['h1'] = $text;
				}
				$out['headings'][] = array(
					'level' => $level,
					'text'  => $text,
				);
			}
		}

		$body            = $xp->query( '//body' );
		$body_node       = ( $body && $body->length ) ? $body->item( 0 ) : $doc->documentElement;
		$out['all_text'] = $body_node ? self::clean( $body_node->textContent ) : '';

		// Main content: <main>, then <article>, then the body without its
		// header, nav, footer and sidebars.
		$main = null;
		foreach ( array( '//main', '//article', '//*[@role="main"]' ) as $q ) {
			$m = $xp->query( $q );
			if ( $m && $m->length ) {
				$main = $m->item( 0 );
				break;
			}
		}
		if ( null === $main && $body_node ) {
			foreach ( array( '//header', '//nav', '//footer', '//aside' ) as $q ) {
				$nodes = $xp->query( $q );
				if ( ! $nodes ) {
					continue;
				}
				for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
					$n = $nodes->item( $i );
					if ( $n && $n->parentNode ) {
						$n->parentNode->removeChild( $n );
					}
				}
			}
			$main = $body_node;
		}
		$out['main_text']  = $main ? self::block_text( $main ) : '';
		$out['main_words'] = self::word_count( $out['main_text'] );

		$out['app_shell'] = self::word_count( $out['all_text'] ) < 30 && $out['scripts'] >= 3;

		return $out;
	}

	/**
	 * Text of a node with line breaks between block elements, so the preview
	 * reads like the page instead of one run-on line.
	 */
	private static function block_text( DOMNode $node ): string {
		$blocks = array( 'p', 'div', 'section', 'article', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'tr', 'br', 'blockquote', 'pre', 'dt', 'dd', 'figcaption', 'summary', 'details', 'table', 'ul', 'ol' );
		$out    = '';
		foreach ( $node->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType ) {
				$out .= $child->nodeValue;
			} elseif ( XML_ELEMENT_NODE === $child->nodeType ) {
				$is_block = in_array( strtolower( $child->nodeName ), $blocks, true );
				$inner    = self::block_text( $child );
				if ( $is_block ) {
					$prefix = preg_match( '/^h[1-6]$/i', $child->nodeName ) ? str_repeat( '#', (int) substr( $child->nodeName, 1 ) ) . ' ' : '';
					$prefix = 'li' === strtolower( $child->nodeName ) ? '- ' : $prefix;
					$out   .= "\n" . $prefix . $inner . "\n";
				} else {
					$out .= $inner;
				}
			}
		}
		$out = preg_replace( '/[ \t\x{00A0}]+/u', ' ', (string) $out );
		$out = preg_replace( '/ *\n */', "\n", (string) $out );
		$out = preg_replace( '/\n{3,}/', "\n\n", (string) $out );
		return trim( (string) $out, " \n\t" );
	}

	/**
	 * Visible text of an HTML string (scripts and styles removed).
	 */
	public static function visible_text( string $html ): string {
		if ( '' === $html ) {
			return '';
		}
		$html = preg_replace( '#<(script|style|template|svg|noscript)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = preg_replace( '#<head\b[^>]*>.*?</head>#is', ' ', (string) $html );
		return self::clean( wp_strip_all_tags( (string) $html ) );
	}

	/**
	 * Split text into sentences worth checking (6+ words).
	 *
	 * @return array<int,string>
	 */
	private static function sentences( string $text ): array {
		$text  = self::clean( $text );
		// The extractor joins block elements without a space ("agencies.The"),
		// so split after end punctuation whether or not whitespace follows,
		// as long as the next sentence starts with a capital letter or digit.
		$parts = preg_split( '/(?<=[.!?])\s*(?=[\p{Lu}\p{N}"\x{201C}])/u', $text );
		$out   = array();
		foreach ( (array) $parts as $p ) {
			$p = trim( (string) $p );
			if ( self::word_count( $p ) >= 6 && mb_strlen( $p ) <= 400 ) {
				$out[] = $p;
			}
			if ( count( $out ) >= 200 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function clean( string $s ): string {
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( (string) $s, " \t\n\r\0\x0B" );
	}

	/** Lowercase, straight quotes, letters and digits only, for fuzzy matching. */
	private static function normalise( string $s ): string {
		$s = self::clean( $s );
		$s = str_replace( array( "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}" ), array( "'", "'", '"', '"', '-', '-' ), $s );
		$s = mb_strtolower( $s, 'UTF-8' );
		$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
		return trim( (string) $s );
	}

	private static function word_count( string $s ): int {
		$s = trim( $s );
		return '' === $s ? 0 : count( preg_split( '/\s+/u', $s ) );
	}
}
