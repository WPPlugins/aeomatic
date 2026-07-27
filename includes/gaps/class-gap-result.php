<?php
/**
 * Immutable value object representing a single gap finding for a post.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AEOMATIC_Gap_Result {

	public const SEVERITY_HIGH   = 'high';
	public const SEVERITY_MEDIUM = 'medium';
	public const SEVERITY_LOW    = 'low';

	public const SOURCE_INTERNAL_COVERAGE = 'internal_coverage';
	public const SOURCE_ANSWER_SHAPE      = 'answer_shape';

	private const SEVERITY_WEIGHTS = array(
		self::SEVERITY_HIGH   => 20,
		self::SEVERITY_MEDIUM => 10,
		self::SEVERITY_LOW    => 4,
	);

	public function __construct(
		public string $code,
		public string $severity,
		public string $title,
		public string $fix_hint,
		public string $doc_anchor,
		public string $source,
		public ?array $fix_action = null
	) {}

	public function to_array(): array {
		return array(
			'code'       => $this->code,
			'severity'   => $this->severity,
			'title'      => $this->title,
			'fix_hint'   => $this->fix_hint,
			'doc_anchor' => $this->doc_anchor,
			'source'     => $this->source,
			'fix_action' => $this->fix_action,
		);
	}

	public function weight(): int {
		return self::SEVERITY_WEIGHTS[ $this->severity ] ?? 0;
	}

	/**
	 * 0–100 score from a list of gaps. Perfect (no gaps) = 100.
	 * Each gap deducts by severity weight; floors at 0.
	 */
	public static function score_for( array $gaps ): int {
		$deduction = 0;
		foreach ( $gaps as $g ) {
			if ( $g instanceof self ) {
				$deduction += $g->weight();
			}
		}
		return max( 0, 100 - $deduction );
	}
}
