<?php
/**
 * Immutable value object holding one category's AI Readiness result.
 *
 * Shape mirrors the existing AEOMATIC_Gap_Result value-object pattern so the
 * REST payload style is consistent across modules. Scores are clamped 0-100
 * at construction; issues and recommendations are plain strings.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AEOMATIC_Readiness_Result {

	/** @var string */
	public $category;

	/** @var int 0-100 */
	public $score;

	/** @var string[] */
	public $issues;

	/** @var string[] */
	public $recommendations;

	/**
	 * @param string   $category        Category slug (content|trust|authority|entities|technical).
	 * @param int      $score           0-100. Clamped.
	 * @param string[] $issues          Human-readable issue strings.
	 * @param string[] $recommendations Human-readable recommendation strings.
	 */
	public function __construct( string $category, int $score, array $issues = array(), array $recommendations = array() ) {
		$this->category        = $category;
		$this->score           = max( 0, min( 100, $score ) );
		$this->issues          = array_values( array_map( 'strval', $issues ) );
		$this->recommendations = array_values( array_map( 'strval', $recommendations ) );
	}

	/**
	 * Serialize to the REST-response shape.
	 *
	 * @return array{category:string, score:int, issues:string[], recommendations:string[]}
	 */
	public function to_array(): array {
		return array(
			'category'        => $this->category,
			'score'           => $this->score,
			'issues'          => $this->issues,
			'recommendations' => $this->recommendations,
		);
	}
}
