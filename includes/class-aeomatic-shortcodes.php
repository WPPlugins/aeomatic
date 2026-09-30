<?php
/**
 * Classic-editor shortcodes.
 *
 * Mirrors the Gutenberg `aeomatic/faq` block as `[aeo_faq] / [aeo_qa]`
 * shortcodes so Classic Editor users (and any context where Gutenberg
 * isn't in play) can still render accessible Q&A HTML.
 *
 * Usage:
 *   [aeo_faq]
 *     [aeo_qa question="What is X?"]X is the thing that does Y and Z.[/aeo_qa]
 *     [aeo_qa question="Why use Y?"]Because it improves Z by 30%.[/aeo_qa]
 *   [/aeo_faq]
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Shortcodes {

	public function register(): void {
		add_shortcode( 'aeo_faq', array( $this, 'render_faq' ) );
		add_shortcode( 'aeo_qa', array( $this, 'render_qa' ) );
	}

	/**
	 * Render the FAQ wrapper. Inner content is `[aeo_qa]` shortcodes which
	 * `do_shortcode()` resolves recursively.
	 *
	 * @param array       $atts    Shortcode attributes (unused).
	 * @param string|null $content Inner content (Q&A shortcodes).
	 */
	public function render_faq( $atts, $content = null ): string {
		if ( null === $content || '' === trim( (string) $content ) ) {
			return '';
		}
		return sprintf(
			'<div class="aeob-faq" itemscope itemtype="https://schema.org/FAQPage">%s</div>',
			do_shortcode( $content )
		);
	}

	/**
	 * Render a single Q&A pair as `<details>/<summary>` with schema.org
	 * microdata. Matches the markup emitted by the Gutenberg faq-item block.
	 */
	public function render_qa( $atts, $content = null ): string {
		$atts = shortcode_atts(
			array(
				'question' => '',
			),
			$atts,
			'aeo_qa'
		);

		$q = trim( (string) $atts['question'] );
		$a = trim( (string) $content );

		if ( '' === $q || '' === wp_strip_all_tags( $a ) ) {
			return '';
		}

		return sprintf(
			'<details class="aeob-faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">'
			. '<summary itemprop="name">%s</summary>'
			. '<div class="aeob-faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">'
			. '<div itemprop="text">%s</div>'
			. '</div>'
			. '</details>',
			esc_html( $q ),
			wp_kses_post( do_shortcode( $a ) )
		);
	}

	/**
	 * Extract Q&A pairs from raw post content by parsing the shortcode source
	 * (NOT the rendered HTML — this runs during wp_head before the_content).
	 *
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function extract_pairs( string $content ): array {
		if ( '' === $content || false === strpos( $content, '[aeo_qa' ) ) {
			return array();
		}

		$pairs = array();

		if ( preg_match_all( '/\[aeo_qa\b([^\]]*)\](.*?)\[\/aeo_qa\]/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$attrs    = shortcode_parse_atts( $m[1] ) ?: array();
				$question = isset( $attrs['question'] ) ? trim( wp_strip_all_tags( $attrs['question'] ) ) : '';
				$answer   = trim( wp_strip_all_tags( $m[2] ?? '' ) );
				if ( '' !== $question && '' !== $answer ) {
					$pairs[] = array( 'q' => $question, 'a' => $answer );
				}
			}
		}

		return $pairs;
	}
}
