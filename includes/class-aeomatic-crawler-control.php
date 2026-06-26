<?php
/**
 * AI crawler control.
 *
 * Injects per-bot Allow/Disallow directives into the virtual robots.txt
 * served by WordPress. Supports the major AI crawlers as of 2026.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Crawler_Control {

	public function register(): void {
		// Modern WP (5.3+) uses robots_txt filter.
		add_filter( 'robots_txt', array( $this, 'append_directives' ), 10, 2 );
	}

	/**
	 * Append AI bot directives to robots.txt output.
	 *
	 * @param string $output  Existing robots.txt body.
	 * @param string $public  '1' if site is public.
	 */
	public function append_directives( string $output, string $public ): string {
		$crawlers   = AEOMATIC_Settings::get( 'crawlers' );
		$block_pdfs = (bool) AEOMATIC_Settings::get( 'robots', 'block_pdfs' );

		if ( empty( $crawlers ) && ! $block_pdfs ) {
			return $output;
		}

		$lines = array();

		if ( $block_pdfs ) {
			$lines[] = '';
			$lines[] = '# Block PDF files — managed by AEOmatic — AI based Answer Engine Optimization';
			$lines[] = 'User-agent: *';
			$lines[] = 'Disallow: /*.pdf$';
			$lines[] = '';
		}

		if ( ! empty( $crawlers ) ) {
			$lines[] = '';
			$lines[] = '# AI crawler directives — managed by AEOmatic — AI based Answer Engine Optimization';

			foreach ( $crawlers as $bot => $allowed ) {
				$lines[] = 'User-agent: ' . $bot;
				$lines[] = $allowed ? 'Allow: /' : 'Disallow: /';
				$lines[] = '';
			}
		}

		// Hint to llms.txt for compatible AI crawlers.
		$llms_enabled = AEOMATIC_Settings::get( 'llms_txt', 'enabled' );
		if ( $llms_enabled ) {
			$lines[] = '# AEO discovery';
			$lines[] = 'Sitemap: ' . home_url( '/llms.txt' );
			$lines[] = '';
		}

		return $output . "\n" . implode( "\n", $lines );
	}
}
