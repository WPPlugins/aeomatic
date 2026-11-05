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
		// Late, so the "User-agent: *" rules other plugins add are already
		// there to be copied into the allowed crawlers' groups.
		add_filter( 'robots_txt', array( $this, 'append_directives' ), 99, 2 );
	}

	/**
	 * Append AI bot directives to robots.txt output.
	 *
	 * @param string $output  Existing robots.txt body.
	 * @param string $public  '1' if site is public.
	 */
	public function append_directives( $output, $public ): string {
		$output = (string) $output;

		// "Discourage search engines" is on: core already blocks every crawler.
		// Adding Allow groups here would let AI crawlers into a private site.
		if ( '0' === (string) $public ) {
			return $output . "\n# AEOmatic: no AI crawler rules added. Settings > Reading discourages search engines, so all crawlers stay blocked.\n";
		}

		$crawlers   = AEOMATIC_Settings::get( 'crawlers' );
		$block_pdfs = (bool) AEOMATIC_Settings::get( 'robots', 'block_pdfs' );

		if ( empty( $crawlers ) && ! $block_pdfs ) {
			return $output;
		}

		$lines = array();

		if ( $block_pdfs ) {
			$lines[] = '';
			$lines[] = '# Block PDF files — managed by AEOmatic';
			$lines[] = 'User-agent: *';
			$lines[] = 'Disallow: /*.pdf$';
			$lines[] = '';
		}

		if ( ! empty( $crawlers ) ) {
			// A crawler named in its own group ignores every "User-agent: *"
			// rule, so each allowed crawler's group repeats them (wp-admin,
			// other plugins' rules, the PDF block).
			$shared = self::star_rules( $output );
			if ( $block_pdfs ) {
				$shared[] = 'Disallow: /*.pdf$';
			}
			$shared = array_values( array_unique( $shared ) );

			$lines[] = '';
			$lines[] = '# AI crawler directives — managed by AEOmatic';

			foreach ( $crawlers as $bot => $allowed ) {
				$lines[] = 'User-agent: ' . $bot;
				if ( $allowed ) {
					$lines[] = 'Allow: /';
					foreach ( $shared as $rule ) {
						$lines[] = $rule;
					}
				} else {
					$lines[] = 'Disallow: /';
				}
				$lines[] = '';
			}
		}

		// Hint to llms.txt for compatible AI crawlers.
		//
		// Deliberately a comment, not a `Sitemap:` directive: Sitemap expects an
		// XML sitemap or a plain list of URLs, and llms.txt is Markdown. Pointing
		// Sitemap at it makes conforming crawlers report a malformed sitemap and
		// can suppress the site's real sitemap. The canonical discovery routes for
		// llms.txt are the well-known path itself and the Link: rel="ai-context"
		// response header AEOMATIC_AI_Headers emits.
		$llms_enabled = AEOMATIC_Settings::get( 'llms_txt', 'enabled' );
		if ( $llms_enabled ) {
			$lines[] = '# AI context file (llms.txt spec: https://llmstxt.org/)';
			$lines[] = '# ' . home_url( '/llms.txt' );
			$lines[] = '';
		}

		return $output . "\n" . implode( "\n", $lines );
	}

	/**
	 * The Allow / Disallow lines of every "User-agent: *" group in $robots.
	 *
	 * @return string[]
	 */
	private static function star_rules( string $robots ): array {
		if ( ! class_exists( 'AEOMATIC_AI_Access' ) ) {
			return array();
		}
		$out = array();
		foreach ( AEOMATIC_AI_Access::parse_robots( $robots ) as $group ) {
			if ( ! in_array( '*', (array) $group['agents'], true ) ) {
				continue;
			}
			foreach ( (array) $group['rules'] as $rule ) {
				$path = (string) $rule['path'];
				if ( '' === $path ) {
					continue; // An empty Disallow matches nothing.
				}
				$out[] = ( $rule['allow'] ? 'Allow: ' : 'Disallow: ' ) . $path;
			}
		}
		return $out;
	}
}
