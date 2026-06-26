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
		// Initialise modules.
		( new AEOMATIC_Schema() )->register();
		( new AEOMATIC_LLMs_Txt() )->register();
		( new AEOMATIC_Crawler_Control() )->register();
		( new AEOMATIC_Audit() )->register();
		( new AEOMATIC_Blocks() )->register();
		( new AEOMATIC_Verification() )->register();
		( new AEOMATIC_Conflict_Detector() )->register();
		( new AEOMATIC_AI_Headers() )->register();
		( new AEOMATIC_Crawler_Log() )->register();

		if ( is_admin() ) {
			( new AEOMATIC_Admin() )->register();
		}
	}
}
