<?php
/**
 * AI crawler visit log.
 *
 * Logs recognised AI bot visits to a capped options-based ring buffer (max 200
 * entries). No custom DB table is needed — entries are trimmed on write.
 * Provides summary and recent-visit accessors for the admin dashboard.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Crawler_Log {

	const OPTION_KEY = 'aeomatic_crawler_log_entries';
	const MAX        = 200;

	/**
	 * All recognised AI bot strings (case-insensitive substring match).
	 */
	const BOTS = array(
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		'ClaudeBot',
		'Claude-SearchBot',
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
		'DeepSeekBot',
		'Cohere-AI',
	);

	public function register(): void {
		// Run after WP is fully loaded so is_admin() and similar are reliable.
		add_action( 'wp', array( $this, 'maybe_log' ) );
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
	 * Erase all log entries (used by uninstall and admin reset).
	 */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
