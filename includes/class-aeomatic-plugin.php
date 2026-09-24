<?php
/**
 * Core plugin class.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin orchestrator. Loads modules and registers hooks.
 */
class AEOMATIC_Plugin {

	/** @var AEOMATIC_Plugin|null */
	private static $instance = null;

	public static function instance(): AEOMATIC_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function run(): void {
		// Run any version-change upgrade routines (e.g. flush rewrites for
		// new virtual endpoints) before modules register.
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );

		// Initialise modules.
		( new AEOMATIC_Schema() )->register();
		( new AEOMATIC_LLMs_Txt() )->register();
		( new AEOMATIC_Crawler_Control() )->register();
		( new AEOMATIC_Audit() )->register();
		( new AEOMATIC_Citation_Gaps() )->register();
		( new AEOMATIC_AI_Readiness() )->register();
		( new AEOMATIC_Site_Readiness() )->register();
		( new AEOMATIC_Entity_Scanner() )->register();
		( new AEOMATIC_Blocks() )->register();
		( new AEOMATIC_Verification() )->register();
		( new AEOMATIC_Conflict_Detector() )->register();
		( new AEOMATIC_AI_Headers() )->register();
		( new AEOMATIC_Crawler_Log() )->register();
		( new AEOMATIC_Shortcodes() )->register();

		if ( is_admin() ) {
			( new AEOMATIC_Admin() )->register();
			( new AEOMATIC_Author_Profile() )->register();
			( new AEOMATIC_Dashboard_Widget() )->register();
			( new AEOMATIC_Bulk_Actions() )->register();
			( new AEOMATIC_Wizard() )->register();
			( new AEOMATIC_Classic_Editor_Metabox() )->register();
			// AEOMATIC_Go_Pro_Nag: registered when the Pro plugin launches.
			// Class file kept in admin/ so the switch-back is one line.
		}

		// Extension boot signal. Fires once, after every core module has
		// registered its hooks, so extensions can attach without racing.
		do_action( 'aeomatic_plugin_modules_loaded' );
	}

	/**
	 * Detect a version change and run one-time upgrade steps.
	 *
	 * On 1.1.0 the /llms-full.txt endpoint was added, which needs a rewrite
	 * flush. Comparing the stored version against the running version lets us
	 * flush automatically on update without the user re-saving permalinks.
	 */
	public function maybe_upgrade(): void {
		$stored = (string) AEOMATIC_Settings::get( '_meta', 'installed_version' );
		if ( $stored === AEOMATIC_VERSION ) {
			return;
		}

		AEOMATIC_LLMs_Txt::register_rewrites();
		flush_rewrite_rules();

		$all                               = AEOMATIC_Settings::all();
		$all['_meta']['installed_version'] = AEOMATIC_VERSION;
		AEOMATIC_Settings::put( $all );

		AEOMATIC_Activity::log(
			'plugin_updated',
			sprintf(
				/* translators: 1: previous version, 2: new version */
				__( 'AEOmatic updated from %1$s to %2$s. Rewrite rules refreshed for the /llms-full.txt endpoint.', 'aeomatic' ),
				$stored ? $stored : '—',
				AEOMATIC_VERSION
			)
		);
	}
}
