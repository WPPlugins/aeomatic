<?php
/**
 * AI-discovery HTTP response headers.
 *
 * Emits X-AI-* headers and an ai-context Link header so AI crawlers can
 * identify this site as citeable and locate the llms.txt context file.
 * These are emerging citation signals that complement robots.txt directives.
 *
 * Nothing here is a standard: no AI vendor has committed to reading these
 * headers. They are cheap, additive hints, and the whole block can be turned
 * off in Settings.
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

	/**
	 * Are the discovery headers enabled?
	 *
	 * Defaults to true so installs that predate the ai_headers setting keep
	 * their existing behaviour after an update.
	 */
	public static function is_enabled(): bool {
		$enabled = AEOMATIC_Settings::get( 'ai_headers', 'enabled' );
		if ( null === $enabled || '' === $enabled ) {
			return true;
		}
		return (bool) $enabled;
	}

	public function send(): void {
		if ( is_admin() || ! self::is_enabled() ) {
			return;
		}

		// Reflect the user's actual robots.txt configuration rather than
		// hard-coding "allowed". Announcing X-AI-Crawl: allowed while
		// robots.txt disallows every AI bot is a contradictory signal, and the
		// robots.txt directives are the ones crawlers actually obey.
		header( 'X-AI-Crawl: ' . ( self::any_crawler_allowed() ? 'allowed' : 'restricted' ) );
		header( 'X-AI-Citeable: true' );

		// These two state a licence and an authorship claim on the owner's
		// behalf, so they are only sent when the owner opts in (1.2.6+).
		if ( self::declares_content() ) {
			header( 'X-Content-License: free-to-cite-with-attribution' );
			header( 'X-AI-Content-Type: human-authored' );
		}

		if ( is_singular() ) {
			header( 'X-AI-Speakable: true' );
		}

		if ( AEOMATIC_Settings::get( 'llms_txt', 'enabled' ) ) {
			// false = allow multiple Link headers without overwriting.
			header( 'Link: <' . esc_url_raw( home_url( '/llms.txt' ) ) . '>; rel="ai-context"; type="text/plain"', false );
		}
	}

	/**
	 * Has the owner opted in to the licence and authorship headers?
	 * Off unless explicitly enabled, including on installs from before 1.2.6.
	 */
	public static function declares_content(): bool {
		return (bool) AEOMATIC_Settings::get( 'ai_headers', 'declare_content' );
	}

	/**
	 * Is at least one AI crawler allowed in the crawler settings?
	 */
	private static function any_crawler_allowed(): bool {
		$crawlers = (array) AEOMATIC_Settings::get( 'crawlers' );
		if ( empty( $crawlers ) ) {
			return true;
		}
		foreach ( $crawlers as $allowed ) {
			if ( $allowed ) {
				return true;
			}
		}
		return false;
	}
}
