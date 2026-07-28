<?php
/**
 * Contract every AI Readiness category analyzer must implement.
 *
 * Kept minimal on purpose: the orchestrator only needs to hand a post to an
 * analyzer and receive a Readiness_Result back. Small interface = easy to
 * extend without breaking existing implementations.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AEOMATIC_Readiness_Analyzer_Interface {

	/**
	 * Analyze a post and return the category's score, issues, recommendations.
	 *
	 * @param WP_Post $post Post to analyze.
	 * @return AEOMATIC_Readiness_Result
	 */
	public function analyze( WP_Post $post ): AEOMATIC_Readiness_Result;
}
