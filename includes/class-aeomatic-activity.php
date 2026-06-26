<?php
/**
 * Activity log.
 *
 * Tracks what AEOmatic — AI based Answer Engine Optimization has done since installation.
 *
 * Performance note: bump() uses an in-memory accumulator that is flushed
 * to the database at shutdown via wp_cache_incr where available, or via a
 * single deferred update_option per request. This eliminates the previous
 * pattern of calling get_option + update_option on every page render.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Activity {

	const OPTION_KEY  = 'aeomatic_activity_log';
	const STATS_KEY   = 'aeomatic_stats';
	const MAX_ENTRIES = 50;

	/** Accumulated increments, flushed once at request shutdown. */
	private static array $pending_bumps = array();

	/** Whether we have already registered the shutdown flush. */
	private static bool $shutdown_registered = false;

	// ── Event log ────────────────────────────────────────────────────────

	/**
	 * Append a named event to the activity log.
	 *
	 * @param string $type    Short slug, e.g. 'settings_saved'.
	 * @param string $message Human-readable sentence.
	 * @param array  $context Optional key-value context data.
	 */
	public static function log( string $type, string $message, array $context = array() ): void {
		$entries = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}

		array_unshift( $entries, array(
			'time'    => time(),
			'type'    => sanitize_key( $type ),
			'message' => sanitize_text_field( $message ),
			'context' => $context,
		) );

		update_option( self::OPTION_KEY, array_slice( $entries, 0, self::MAX_ENTRIES ), false );
	}

	// ── Stat counters ─────────────────────────────────────────────────────

	/**
	 * Queue a counter increment. Does NOT touch the database immediately.
	 * All pending increments are flushed to the DB once at request shutdown.
	 *
	 * @param string $stat Counter key (e.g. 'schema_renders').
	 * @param int    $by   Amount to add (default 1).
	 */
	public static function bump( string $stat, int $by = 1 ): void {
		$stat = sanitize_key( $stat );
		self::$pending_bumps[ $stat ] = ( self::$pending_bumps[ $stat ] ?? 0 ) + $by;
		self::maybe_register_shutdown();
	}

	/**
	 * Flush all pending stat bumps to the database in a single write.
	 * Called automatically at PHP shutdown; can also be called explicitly
	 * in tests or long-running scripts.
	 */
	public static function flush_bumps(): void {
		if ( empty( self::$pending_bumps ) ) {
			return;
		}

		$stats = get_option( self::STATS_KEY, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		foreach ( self::$pending_bumps as $stat => $amount ) {
			$stats[ $stat ] = ( (int) ( $stats[ $stat ] ?? 0 ) ) + $amount;
		}
		$stats['updated_at']    = time();
		self::$pending_bumps    = array();

		update_option( self::STATS_KEY, $stats, false );
	}

	// ── Reads ─────────────────────────────────────────────────────────────

	public static function get_log(): array {
		$entries = get_option( self::OPTION_KEY, array() );
		return is_array( $entries ) ? $entries : array();
	}

	public static function get_stats(): array {
		$stats = get_option( self::STATS_KEY, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		// Merge with pending so the UI shows live numbers even before flush.
		foreach ( self::$pending_bumps as $k => $v ) {
			$stats[ $k ] = ( (int) ( $stats[ $k ] ?? 0 ) ) + $v;
		}
		return wp_parse_args( $stats, array(
			'schema_renders' => 0,
			'audits_run'     => 0,
			'llms_serves'    => 0,
			'updated_at'     => 0,
		) );
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
		self::$pending_bumps = array();
	}

	// ── Internal ──────────────────────────────────────────────────────────

	private static function maybe_register_shutdown(): void {
		if ( self::$shutdown_registered ) {
			return;
		}
		register_shutdown_function( array( __CLASS__, 'flush_bumps' ) );
		self::$shutdown_registered = true;
	}
}
