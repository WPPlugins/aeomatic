<?php
/**
 * Plugin Name:       AEOmatic — Answer Engine Optimization, GEO, AIO, LLM SEO
 * Plugin URI:        https://wordpress.org/plugins/aeomatic/
 * Description:       Optimise your WordPress site for AI search engines (ChatGPT, Claude, Perplexity, Google AI Overviews). Auto-injects JSON-LD schema, generates llms.txt, controls AI crawlers, audits content for AEO readiness, and provides a FAQ block for answer engine extraction.
 * Version:           1.1.7
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            mmtushar
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aeomatic
 * Domain Path:       /languages
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AEOMATIC_VERSION', '1.1.7' );
define( 'AEOMATIC_FILE', __FILE__ );
define( 'AEOMATIC_DIR', plugin_dir_path( __FILE__ ) );
define( 'AEOMATIC_URL', plugin_dir_url( __FILE__ ) );
define( 'AEOMATIC_BASENAME', plugin_basename( __FILE__ ) );
define( 'AEOMATIC_OPTION_KEY', 'aeomatic_settings' );

// PSR-style includes.
require_once AEOMATIC_DIR . 'includes/class-aeomatic-plugin.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-settings.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-activity.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-content-extractor.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-post-types.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-schema-analyzer.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-property-extractors.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-schema-fields.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-schema-generator.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-schema-builder.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-woocommerce.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-schema.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-llms-txt.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-crawler-control.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-audit.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-citation-gaps.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-ai-readiness.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-entity-scanner.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-blocks.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-verification.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-conflict-detector.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-ai-headers.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-crawler-log.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-author-profile.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-shortcodes.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-dashboard-widget.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-bulk-actions.php';
require_once AEOMATIC_DIR . 'includes/class-aeomatic-seo-importer.php';
require_once AEOMATIC_DIR . 'admin/class-aeomatic-admin.php';
require_once AEOMATIC_DIR . 'admin/class-aeomatic-wizard.php';

/**
 * Activation: set defaults, flush rewrites for /llms.txt endpoint.
 */
function aeomatic_activate() {
	AEOMATIC_Settings::set_defaults();
	AEOMATIC_LLMs_Txt::register_rewrites();
	flush_rewrite_rules();
	AEOMATIC_Crawler_Log::schedule_cron();
	AEOMATIC_Wizard::schedule_redirect();
	AEOMATIC_Activity::log( 'plugin_activated', __( 'AEOmatic — AI based Answer Engine Optimization activated. Default settings applied, /llms.txt endpoint registered.', 'aeomatic' ) );
}
register_activation_hook( __FILE__, 'aeomatic_activate' );

/**
 * Deactivation: clean up rewrites and scheduled events. Settings remain so
 * users don't lose config.
 */
function aeomatic_deactivate() {
	flush_rewrite_rules();
	AEOMATIC_Crawler_Log::unschedule_cron();
}
register_deactivation_hook( __FILE__, 'aeomatic_deactivate' );

/**
 * Bootstrap.
 */
add_action( 'plugins_loaded', static function () {
	AEOMATIC_Plugin::instance()->run();
} );
