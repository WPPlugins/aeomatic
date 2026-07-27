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

	public function register(): void {
		add_action( 'wp', array( $this, 'maybe_log' ) );
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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$entry = array(
			'bot'  => $bot,
			'url'  => $uri,
			'time' => time(),
		);

		$log   = (array) get_option( self::OPTION_KEY, array() );
		$log[] = $entry;

		if ( count( $log ) > self::MAX ) {
			$log = array_slice( $log, -self::MAX );
		}

		// autoload = false — log data does not need to load on every request.
		update_option( self::OPTION_KEY, $log, false );
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
	 */
	public static function get_recent( int $limit = 50 ): array {
		$log = (array) get_option( self::OPTION_KEY, array() );
		return array_slice( array_reverse( $log ), 0, $limit );
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
