<?php
/**
 * Uninstall handler. Runs when the plugin is deleted from the admin.
 *
 * Removes every option, transient and post-meta key AEOmatic writes, so a
 * delete leaves no orphaned rows behind. Keep this list in sync with the
 * META_* / OPTION_KEY constants declared across includes/.
 *
 * @package AEOmatic
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Options. Mirrors:
 *   AEOMATIC_OPTION_KEY                        (aeomatic_settings)
 *   AEOMATIC_Activity::OPTION_KEY / STATS_KEY
 *   AEOMATIC_Crawler_Log::OPTION_KEY / STATS_OPTION_KEY
 *   AEOMATIC_Conflict_Detector::NOTICE_DISMISSED_KEY
 *   AEOMATIC_Wizard::COMPLETED_KEY
 *   AEOMATIC_Admin review-notice flags
 */
$aeomatic_options = array(
	'aeomatic_settings',
	'aeomatic_activity_log',
	'aeomatic_stats',
	'aeomatic_conflict_notice_dismissed',
	'aeomatic_review_dismissed',
	'aeomatic_review_snoozed_until',
	'aeomatic_crawler_log_entries',
	'aeomatic_crawler_log_stats',
	'aeomatic_wizard_completed',
);
foreach ( $aeomatic_options as $aeomatic_option ) {
	delete_option( $aeomatic_option );
}

// Transients (including the wizard's first-run redirect flag).
$aeomatic_transients = array(
	'aeomatic_verification_cache',
	'aeomatic_site_audit_cache',
	'aeomatic_site_readiness_cache',
	'aeomatic_llms_txt_cache',
	'aeomatic_llms_full_txt_cache',
	'aeomatic_wizard_redirect',
);
foreach ( $aeomatic_transients as $aeomatic_transient ) {
	delete_transient( $aeomatic_transient );
}

// Scheduled events. Deactivation normally clears this, but a direct delete of
// an already-deactivated install can leave the hook queued.
wp_clear_scheduled_hook( 'aeomatic_crawler_log_purge' );

/*
 * Post meta. Mirrors every META_* constant in includes/:
 *   AEOMATIC_Audit             — score, score_breakdown
 *   AEOMATIC_Schema_Analyzer   — schema_type, schema_analysis
 *   AEOMATIC_Schema_Builder    — schema_cache
 *   AEOMATIC_Schema_Generator  — schema_overrides
 *   AEOMATIC_Content_Extractor — acf_text
 *   AEOMATIC_AI_Readiness      — ai_readiness{,_fingerprint,_score}
 *   AEOMATIC_Citation_Gaps     — citation_gaps{,_fingerprint,_score}
 *   AEOMATIC_Entity_Scanner    — entity_scan{,_fingerprint}, entity_coverage
 */
$aeomatic_meta_keys = array(
	'_aeomatic_score',
	'_aeomatic_score_breakdown',
	'_aeomatic_schema_type',
	'_aeomatic_schema_analysis',
	'_aeomatic_schema_cache',
	'_aeomatic_schema_overrides',
	'_aeomatic_acf_text',
	'_aeomatic_ai_readiness',
	'_aeomatic_ai_readiness_fingerprint',
	'_aeomatic_ai_readiness_score',
	'_aeomatic_citation_gaps',
	'_aeomatic_citation_gaps_fingerprint',
	'_aeomatic_citation_gaps_score',
	'_aeomatic_entity_scan',
	'_aeomatic_entity_scan_fingerprint',
	'_aeomatic_entity_coverage',
);

global $wpdb;
foreach ( $aeomatic_meta_keys as $aeomatic_meta_key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $aeomatic_meta_key ) ); // phpcs:ignore WordPress.DB
}
