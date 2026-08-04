<?php
/**
 * Contract every Entity Scanner detector must implement.
 *
 * Small on purpose: orchestrator hands the detector the post and the shared
 * signal collector; the detector returns a Detection_Result. Nothing else.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AEOMATIC_Entity_Detector_Interface {

	/**
	 * Detect whether the post covers this entity type.
	 *
	 * @param WP_Post                              $post      Post being analysed.
	 * @param AEOMATIC_Entity_Signal_Collector     $signals   Shared signal collector.
	 * @return AEOMATIC_Entity_Detection_Result
	 */
	public function detect( WP_Post $post, AEOMATIC_Entity_Signal_Collector $signals ): AEOMATIC_Entity_Detection_Result;
}
