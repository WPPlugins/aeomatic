<?php
/**
 * Immutable value object holding one entity type's detection result.
 *
 * Mirrors AEOMATIC_Gap_Result / AEOMATIC_Readiness_Result. `signals`
 * and `missing_signals` are human-readable strings so the UI can render
 * them without further lookup.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AEOMATIC_Entity_Detection_Result {

	/** @var string */
	public $entity_type;

	/** @var bool */
	public $detected;

	/** @var int 0-100 */
	public $confidence;

	/** @var string[] */
	public $signals;

	/** @var string[] */
	public $missing_signals;

	/** @var string[] */
	public $recommendations;

	/**
	 * @param string   $entity_type    Slug (organization|person|service|product|local_business|faq).
	 * @param bool     $detected       True when the confidence threshold is met.
	 * @param int      $confidence     0-100. Clamped.
	 * @param string[] $signals        Detected evidence strings.
	 * @param string[] $missing_signals Evidence NOT found.
	 * @param string[] $recommendations Advice to raise confidence.
	 */
	public function __construct(
		string $entity_type,
		bool $detected,
		int $confidence,
		array $signals = array(),
		array $missing_signals = array(),
		array $recommendations = array()
	) {
		$this->entity_type     = $entity_type;
		$this->detected        = $detected;
		$this->confidence      = max( 0, min( 100, $confidence ) );
		$this->signals         = array_values( array_map( 'strval', $signals ) );
		$this->missing_signals = array_values( array_map( 'strval', $missing_signals ) );
		$this->recommendations = array_values( array_map( 'strval', $recommendations ) );
	}

	public function to_array(): array {
		return array(
			'entity_type'     => $this->entity_type,
			'detected'        => $this->detected,
			'confidence'      => $this->confidence,
			'signals'         => $this->signals,
			'missing_signals' => $this->missing_signals,
			'recommendations' => $this->recommendations,
		);
	}
}
