<?php
/**
 * AI crawler visit log.
 *
 * Logs recognised AI bot visits to an options-based ring buffer. Retention is
 * hybrid: a daily cron purges entries older than the configured retention
 * window (default 90 days), and a hard cap (500 entries) acts as a safety
 * ceiling against bot storms. Exposes a small stats surface so the admin
 * dashboard widget can show retention health without recomputing.
 *
 * Privacy model — what is recorded per visit:
 *   bot     matched crawler name from self::BOTS
 *   url     request path on this site
 *   ua      raw user-agent string, truncated to UA_MAX_LENGTH
 *   status  HTTP response status this site returned
 *   time    Unix timestamp
 *
 * Deliberately NOT recorded: IP addresses, cookies, referrers, headers, or
 * anything tied to a human visitor. Only requests whose user agent matches a
 * known AI crawler are logged at all, and nothing is sent off-site.
 *
 * A crawler visit means a bot fetched a URL. It does NOT mean the page was
 * read, retained, or cited in an AI answer — no AI vendor exposes citation
 * data through crawler traffic, so this log cannot report citations.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Crawler_Log {

	const OPTION_KEY             = 'aeomatic_crawler_log_entries';
	const STATS_OPTION_KEY       = 'aeomatic_crawler_log_stats';
	const CRON_HOOK              = 'aeomatic_crawler_log_purge';
	const MAX                    = 500;
	const DEFAULT_RETENTION_DAYS = 90;

	/** User-agent strings are truncated to this many characters before storage. */
	const UA_MAX_LENGTH = 255;

	/** Stored URL length cap: the user agent is trivially spoofed, so a fake bot must not be able to grow the log with huge URLs. */
	const URL_MAX_LENGTH = 255;

	/**
	 * All recognised AI bot strings (case-insensitive substring match).
	 */
	const BOTS = array(
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		'ClaudeBot',
		'Claude-User',
		'Claude-SearchBot',
		// Legacy Anthropic UAs — retained for detection only (no longer used by Anthropic per
		// https://support.claude.com/en/articles/8896518). Removed from default allow-list.
		'Claude-Web',
		'anthropic-ai',
		'PerplexityBot',
		'Perplexity-User',
		'Google-Extended',
		'Applebot-Extended',
		'CCBot',
		'Bytespider',
		'Amazonbot',
		'meta-externalagent',
		// Meta's per-user fetcher — distinct from meta-externalagent (the training UA).
		'Meta-ExternalFetcher',
		'DeepSeekBot',
		'Cohere-AI',
		// DuckDuckGo's AI-answers fetcher.
		'DuckAssistBot',
	);

	/**
	 * Entry staged by maybe_log() on `wp`, written on `shutdown`.
	 *
	 * @var array|null
	 */
	private static $pending = null;

	public function register(): void {
		add_action( 'wp', array( $this, 'maybe_log' ) );
		// The response status is not settled at `wp`, so the row is written at
		// shutdown once http_response_code() reflects what was actually sent.
		add_action( 'shutdown', array( __CLASS__, 'flush_pending' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_purge' ) );

		// Self-heal: if activation ran before this build (or the schedule was
		// dropped), make sure the daily purge is queued.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public function maybe_log(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( ! $ua ) {
			return;
		}

		$bot = $this->identify_bot( $ua );
		if ( ! $bot ) {
			return;
		}

		// AEOmatic's own AI Access check sends crawler user agents from this
		// server. Those are tests, not visits.
		if ( class_exists( 'AEOMATIC_AI_Access' ) && AEOMATIC_AI_Access::is_test_request() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		// Staged, not written: the status code is only final at shutdown.
		self::$pending = array(
			'bot'  => $bot,
			'url'  => substr( $uri, 0, self::URL_MAX_LENGTH ),
			'ua'   => self::truncate_ua( $ua ),
			'time' => time(),
		);
	}

	/**
	 * Write the staged entry, stamping the response status the site returned.
	 *
	 * Runs on `shutdown`. A 404 or 403 for GPTBot is far more actionable than a
	 * bare visit count, which is why the write is deferred this late.
	 */
	public static function flush_pending(): void {
		if ( null === self::$pending ) {
			return;
		}

		$entry         = self::$pending;
		self::$pending = null;

		$status          = function_exists( 'http_response_code' ) ? http_response_code() : 0;
		$entry['status'] = ( is_int( $status ) && $status > 0 ) ? $status : 200;

		$log   = (array) get_option( self::OPTION_KEY, array() );
		$log[] = $entry;

		if ( count( $log ) > self::MAX ) {
			$log = array_slice( $log, -self::MAX );
		}

		// autoload = false — log data does not need to load on every request.
		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * Trim a user-agent string to a storable length.
	 */
	private static function truncate_ua( string $ua ): string {
		if ( strlen( $ua ) <= self::UA_MAX_LENGTH ) {
			return $ua;
		}
		return substr( $ua, 0, self::UA_MAX_LENGTH );
	}

	private function identify_bot( string $ua ): string {
		foreach ( self::BOTS as $bot ) {
			if ( false !== stripos( $ua, $bot ) ) {
				return $bot;
			}
		}
		return '';
	}

	/**
	 * Return the most recent log entries, newest first.
	 *
	 * @param int    $limit       Maximum entries to return.
	 * @param bool   $errors_only Only entries answered with an HTTP status of 400 or above.
	 * @param string $bot         Only entries for this crawler ('' for all).
	 */
	public static function get_recent( int $limit = 50, bool $errors_only = false, string $bot = '' ): array {
		$log = array_reverse( (array) get_option( self::OPTION_KEY, array() ) );
		if ( $errors_only || '' !== $bot ) {
			$log = array_filter(
				$log,
				static function ( $entry ) use ( $errors_only, $bot ) {
					if ( '' !== $bot && ( $entry['bot'] ?? '' ) !== $bot ) {
						return false;
					}
					return ! $errors_only || self::is_error( (array) $entry );
				}
			);
		}
		return array_slice( array_values( $log ), 0, $limit );
	}

	/**
	 * Whether an entry was answered with an error status. Entries logged
	 * before status codes were recorded have none and are not errors.
	 */
	public static function is_error( array $entry ): bool {
		return isset( $entry['status'] ) && (int) $entry['status'] >= 400;
	}

	/**
	 * The recognised crawler name matching $name, or '' if it is not one.
	 */
	public static function known_bot( string $name ): string {
		foreach ( self::BOTS as $bot ) {
			if ( 0 === strcasecmp( $bot, $name ) ) {
				return $bot;
			}
		}
		return '';
	}

	/**
	 * Return a bot_name => visit_count summary, sorted by count descending.
	 */
	public static function get_summary(): array {
		$log    = (array) get_option( self::OPTION_KEY, array() );
		$counts = array();
		foreach ( $log as $entry ) {
			$bot            = $entry['bot'] ?? 'unknown';
			$counts[ $bot ] = ( $counts[ $bot ] ?? 0 ) + 1;
		}
		arsort( $counts );
		return $counts;
	}

	/**
	 * Per-bot breakdown including how many responses were errors.
	 *
	 * Sorted by visit count descending. Entries logged before status codes were
	 * recorded have no 'status' key and count as neither ok nor error.
	 *
	 * @return array<int,array{bot:string,visits:int,ok:int,errors:int,last_seen:int,last_url:string}>
	 */
	public static function get_breakdown(): array {
		$log  = (array) get_option( self::OPTION_KEY, array() );
		$rows = array();

		foreach ( $log as $entry ) {
			$bot = (string) ( $entry['bot'] ?? 'unknown' );
			if ( ! isset( $rows[ $bot ] ) ) {
				$rows[ $bot ] = array(
					'bot'       => $bot,
					'visits'    => 0,
					'ok'        => 0,
					'errors'    => 0,
					'last_seen' => 0,
					'last_url'  => '',
				);
			}

			++$rows[ $bot ]['visits'];

			if ( isset( $entry['status'] ) ) {
				if ( self::is_error( (array) $entry ) ) {
					++$rows[ $bot ]['errors'];
				} else {
					++$rows[ $bot ]['ok'];
				}
			}

			$time = (int) ( $entry['time'] ?? 0 );
			if ( $time >= $rows[ $bot ]['last_seen'] ) {
				$rows[ $bot ]['last_seen'] = $time;
				$rows[ $bot ]['last_url']  = (string) ( $entry['url'] ?? '' );
			}
		}

		usort( $rows, static function ( $a, $b ) { return $b['visits'] <=> $a['visits']; } );

		return array_values( $rows );
	}

	/**
	 * Retention health snapshot for the dashboard widget.
	 * kept          — current row count
	 * oldest_age    — age in seconds of the oldest kept entry (0 if empty)
	 * retention_days — configured retention window
	 * purged_last   — rows removed on the last cron pass (0 if none yet)
	 * purged_at     — timestamp of the last cron pass
	 */
	public static function get_retention_summary(): array {
		$log   = (array) get_option( self::OPTION_KEY, array() );
		$stats = (array) get_option( self::STATS_OPTION_KEY, array() );

		$oldest_age = 0;
		if ( ! empty( $log ) ) {
			$oldest_entry = $log[0];
			$oldest_ts    = (int) ( $oldest_entry['time'] ?? 0 );
			$oldest_age   = $oldest_ts ? max( 0, time() - $oldest_ts ) : 0;
		}

		return array(
			'kept'           => count( $log ),
			'oldest_age'     => $oldest_age,
			'retention_days' => self::get_retention_days(),
			'purged_last'    => (int) ( $stats['purged_last'] ?? 0 ),
			'purged_at'      => (int) ( $stats['purged_at'] ?? 0 ),
			'cap'            => self::MAX,
		);
	}

	/**
	 * Read the configured retention window (falls back to default).
	 */
	public static function get_retention_days(): int {
		$days = (int) AEOMATIC_Settings::get( 'log', 'retention_days' );
		if ( $days < 7 || $days > 365 ) {
			return self::DEFAULT_RETENTION_DAYS;
		}
		return $days;
	}

	/**
	 * Cron target: drop entries older than the retention window and record
	 * how many were removed so the widget can show it.
	 */
	public static function run_purge(): void {
		$log = (array) get_option( self::OPTION_KEY, array() );
		if ( empty( $log ) ) {
			update_option(
				self::STATS_OPTION_KEY,
				array( 'purged_last' => 0, 'purged_at' => time() ),
				false
			);
			return;
		}

		$cutoff = time() - ( self::get_retention_days() * DAY_IN_SECONDS );
		$kept   = array();
		foreach ( $log as $entry ) {
			if ( (int) ( $entry['time'] ?? 0 ) >= $cutoff ) {
				$kept[] = $entry;
			}
		}

		$purged = count( $log ) - count( $kept );
		update_option( self::OPTION_KEY, $kept, false );
		update_option(
			self::STATS_OPTION_KEY,
			array( 'purged_last' => $purged, 'purged_at' => time() ),
			false
		);
	}

	/**
	 * Erase all log entries (used by uninstall and admin reset).
	 */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
		delete_option( self::STATS_OPTION_KEY );
	}

	/**
	 * Called from the activation hook to guarantee the purge cron is scheduled.
	 */
	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Called from the deactivation hook to remove the scheduled cron.
	 */
	public static function unschedule_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
