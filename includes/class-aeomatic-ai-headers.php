<?php
/**
 * AI-discovery HTTP response headers.
 *
 * Emits X-AI-* headers and an ai-context Link header so AI crawlers can
 * identify this site as citeable and locate the llms.txt context file.
 * These are emerging citation signals that complement robots.txt directives.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_AI_Headers {

	public function register(): void {
		add_action( 'send_headers', array( $this, 'send' ) );
	}

	public function send(): void {
		if ( is_admin() ) {
			return;
		}

		header( 'X-AI-Crawl: allowed' );
		header( 'X-AI-Citeable: true' );
		header( 'X-Content-License: free-to-cite-with-attribution' );
		header( 'X-AI-Content-Type: human-authored' );

		if ( is_singular() ) {
			header( 'X-AI-Speakable: true' );
		}

		if ( AEOMATIC_Settings::get( 'llms_txt', 'enabled' ) ) {
			// false = allow multiple Link headers without overwriting.
			header( 'Link: <' . esc_url_raw( home_url( '/llms.txt' ) ) . '>; rel="ai-context"; type="text/plain"', false );
		}
	}
}
