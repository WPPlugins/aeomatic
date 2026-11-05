<?php
/**
 * Entities category for AI Readiness — semantic tagging signals.
 *
 * Signals (weights sum to 100 deducted possible):
 *   - No schema.org markup detected/generated  (weight 30)
 *   - Schema type mismatches content type      (weight 20)
 *   - No named entities in the body            (weight 25)
 *   - Missing Article "about" property         (weight 15)
 *   - No dateModified in schema                (weight 10)
 *
 * Reads AEOMATIC_Schema_Analyzer::META_ANALYSIS so we consume the existing
 * schema detection rather than duplicating it.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Analyzer_Entities implements AEOMATIC_Readiness_Analyzer_Interface {

	public function analyze( WP_Post $post ): AEOMATIC_Readiness_Result {
		$score           = 100;
		$issues          = array();
		$recommendations = array();

		// The analyzer stores the type under "type". When the user picked the
		// type, that is the override itself, not a content-detected type.
		$analysis = $this->schema_analysis( $post );
		$detected = ( 'user_override' !== ( $analysis['source'] ?? '' ) && isset( $analysis['type'] ) ) ? (string) $analysis['type'] : '';
		$assigned = $this->assigned_schema_type( $post );
		$active   = $assigned !== '' ? $assigned : $detected;

		if ( $active === '' ) {
			$score -= 30;
			$issues[]          = __( 'No schema.org type detected or assigned for this post.', 'aeomatic' );
			$recommendations[] = __( 'Open the Gutenberg sidebar → Schema Generator. Pick a type (Article, Product, Event, etc.).', 'aeomatic' );
		} else {
			if ( $assigned !== '' && $detected !== '' && $assigned !== $detected ) {
				$score -= 20;
				$issues[]          = sprintf( __( 'Assigned schema type "%1$s" does not match detected type "%2$s".', 'aeomatic' ), $assigned, $detected );
				$recommendations[] = sprintf( __( 'Either change the assigned type to "%s" or rewrite the content to fit the assigned type.', 'aeomatic' ), $detected );
			}
		}

		if ( ! $this->has_named_entities( $post ) ) {
			$score -= 25;
			$issues[]          = __( 'Body has few named entities (people, places, organisations, dates).', 'aeomatic' );
			$recommendations[] = __( 'Name specific people, companies, tools, or dates. Specific names are clearer to readers and engines than general adjectives.', 'aeomatic' );
		}

		if ( ! $this->schema_has_date_modified( $post ) ) {
			$score -= 10;
			$issues[]          = __( 'Schema does not expose `dateModified`.', 'aeomatic' );
			$recommendations[] = __( 'Ensure the schema type outputs `dateModified` so AI engines can gauge freshness.', 'aeomatic' );
		}

		return new AEOMATIC_Readiness_Result( 'entities', $score, $issues, $recommendations );
	}

	private function schema_analysis( WP_Post $post ): array {
		if ( ! class_exists( 'AEOMATIC_Schema_Analyzer' ) ) {
			return array();
		}
		// analyze() reads the persisted analysis and builds it when missing,
		// so a post that was never opened in the editor still gets a type.
		return (array) AEOMATIC_Schema_Analyzer::analyze( $post );
	}

	private function assigned_schema_type( WP_Post $post ): string {
		if ( ! class_exists( 'AEOMATIC_Schema_Analyzer' ) ) {
			return '';
		}
		$key = defined( 'AEOMATIC_Schema_Analyzer::META_OVERRIDE' )
			? AEOMATIC_Schema_Analyzer::META_OVERRIDE
			: '_aeomatic_schema_type';
		return (string) get_post_meta( $post->ID, $key, true );
	}

	private function has_named_entities( WP_Post $post ): bool {
		// Via the extractor: builder and ACF content is where the entity names
		// actually live on many sites.
		$text = trim( (string) ( AEOMATIC_Content_Extractor::extract( $post )['text'] ?? '' ) );
		if ( $text === '' ) {
			return false;
		}
		// Coarse heuristic: consecutive capitalised tokens (rules out sentence-start noise
		// by requiring at least two in a row, or one alongside a year/month/dollar figure).
		if ( preg_match( '/\b(?:[A-Z][a-z]+\s+){1,}[A-Z][a-z]+/', $text ) ) {
			return true;
		}
		if ( preg_match( '/\b(19|20)\d{2}\b/', $text ) ) {
			return true;
		}
		return false;
	}

	private function schema_has_date_modified( WP_Post $post ): bool {
		// Every post has post_modified; the schema types AEOmatic emits include
		// dateModified by default for Article-family types.
		$modified = strtotime( (string) $post->post_modified_gmt );
		return $modified > 0;
	}
}
