<?php
/**
 * Uninstall handler. Runs when the plugin is deleted from the admin.
 *
 * @package AEOmatic
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aeomatic_settings' );
delete_option( 'aeomatic_activity_log' );
delete_option( 'aeomatic_stats' );
delete_option( 'aeomatic_conflict_notice_dismissed' );
delete_option( 'aeomatic_review_dismissed' );
delete_option( 'aeomatic_review_snoozed_until' );
delete_option( 'aeomatic_crawler_log_entries' );
delete_transient( 'aeomatic_verification_cache' );
delete_transient( 'aeomatic_site_audit_cache' );

// Clean up post meta.
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aeomatic_score' ) ); // phpcs:ignore WordPress.DB
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aeomatic_score_breakdown' ) ); // phpcs:ignore WordPress.DB
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aeomatic_schema_type' ) ); // phpcs:ignore WordPress.DB
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aeomatic_schema_analysis' ) ); // phpcs:ignore WordPress.DB
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aeomatic_schema_cache' ) ); // phpcs:ignore WordPress.DB
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aeomatic_schema_overrides' ) ); // phpcs:ignore WordPress.DB
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_aeomatic_acf_text' ) ); // phpcs:ignore WordPress.DB
