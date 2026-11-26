<?php
/**
 * URL redirect manager.
 *
 * Simple admin-managed 301/302 redirects by exact path match. Deliberately
 * scoped small: no regex, no wildcards, no import/export. Intended as a
 * daily-sized shipment that solves the most common case (one old path, one
 * new path) without duplicating the Redirection plugin's full feature set.
 *
 * Storage: a single site option `aeomatic_redirects` containing an array of
 * rows. Capped at 500 entries to keep the option payload bounded.
 *
 * Matching: fires on `template_redirect` priority 1. Incoming REQUEST_URI is
 * stripped of its query string and trailing slash before comparison; stored
 * sources are normalised the same way at save time.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Redirects {

	const OPTION_KEY = 'aeomatic_redirects';
	const MAX_ROWS   = 500;

	public function register(): void {
		add_action( 'template_redirect',                       array( $this, 'maybe_redirect' ), 1 );
		add_action( 'admin_post_aeomatic_add_redirect',        array( $this, 'handle_add' ) );
		add_action( 'admin_post_aeomatic_delete_redirect',     array( $this, 'handle_delete' ) );
	}

	/**
	 * Read all stored redirects, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$rows = get_option( self::OPTION_KEY, array() );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count_rows(): int {
		return count( self::all() );
	}

	/**
	 * Normalise a URL path for comparison. Keeps the leading slash, drops the
	 * query string and any trailing slash (so `/foo`, `/foo/` and `/foo?a=b`
	 * all collapse to `/foo`).
	 */
	public static function normalise_path( string $path ): string {
		$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		if ( '' === $path ) {
			return '/';
		}
		if ( '/' !== $path[0] ) {
			$path = '/' . $path;
		}
		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' );
		}
		return $path;
	}

	/**
	 * Redirect the current request if its path matches a stored rule. Skips
	 * admin, login, cron and REST / admin-ajax so no infrastructure URL is
	 * ever accidentally redirected.
	 */
	public function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $request ) {
			return;
		}
		$path = self::normalise_path( $request );

		$rows = self::all();
		foreach ( $rows as $i => $row ) {
			if ( empty( $row['source'] ) || empty( $row['target'] ) ) {
				continue;
			}
			if ( self::normalise_path( (string) $row['source'] ) !== $path ) {
				continue;
			}

			$target = (string) $row['target'];
			// Normalise path-style targets so /foo and /foo/ loop-detect correctly.
			$normalised_target = ( '' !== $target && 0 === strpos( $target, '/' ) && 0 !== strpos( $target, '//' ) )
				? self::normalise_path( $target )
				: $target;
			if ( $normalised_target === $path ) {
				// Refuse to redirect a URL to itself — would loop.
				continue;
			}

			$status = (int) ( $row['status'] ?? 301 );
			if ( 301 !== $status && 302 !== $status ) {
				$status = 301;
			}

			// Debounce the hit counter: at most one option write per rule per 60s.
			$now      = time();
			$last_hit = (int) ( $row['last_hit_at'] ?? 0 );
			if ( ( $now - $last_hit ) >= 60 ) {
				$rows[ $i ]['hits']        = (int) ( $row['hits'] ?? 0 ) + 1;
				$rows[ $i ]['last_hit_at'] = $now;
				update_option( self::OPTION_KEY, $rows, true );
			}

			if ( 0 === strpos( $target, '/' ) ) {
				wp_safe_redirect( home_url( $target ), $status );
			} else {
				wp_redirect( esc_url_raw( $target ), $status ); // phpcs:ignore WordPress.Security.SafeRedirect
			}
			exit;
		}
	}

	/**
	 * Admin form handler: add a new redirect. Nonce-protected, admin-only.
	 */
	public function handle_add(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage redirects.', 'aeomatic' ), 403 );
		}
		check_admin_referer( 'aeomatic_add_redirect' );

		$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$target = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
		$status = isset( $_POST['status'] ) ? (int) $_POST['status'] : 301;
		$status = ( 302 === $status ) ? 302 : 301;

		$notice = $this->validate_pair( $source, $target );
		if ( null === $notice ) {
			$rows = self::all();
			if ( count( $rows ) >= self::MAX_ROWS ) {
				$notice = 'max';
			} else {
				$rows[] = array(
					'id'          => wp_generate_uuid4(),
					'source'      => self::normalise_path( $source ),
					'target'      => $target,
					'status'      => $status,
					'hits'        => 0,
					'last_hit_at' => 0,
					'created_at'  => time(),
				);
				update_option( self::OPTION_KEY, $rows, true );
				$notice = 'added';
			}
		}

		$this->redirect_back( $notice );
	}

	/**
	 * Admin form handler: delete a redirect by id.
	 */
	public function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage redirects.', 'aeomatic' ), 403 );
		}
		check_admin_referer( 'aeomatic_delete_redirect' );

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( '' === $id ) {
			$this->redirect_back( 'missing' );
		}

		$rows = self::all();
		$next = array();
		$hit  = false;
		foreach ( $rows as $row ) {
			if ( ( $row['id'] ?? '' ) === $id ) {
				$hit = true;
				continue;
			}
			$next[] = $row;
		}
		if ( $hit ) {
			update_option( self::OPTION_KEY, $next, true );
			$this->redirect_back( 'deleted' );
		}
		$this->redirect_back( 'missing' );
	}

	/**
	 * Return a notice slug for an invalid add-form submission, or null when
	 * the source/target pair is acceptable.
	 */
	private function validate_pair( string $source, string $target ): ?string {
		if ( '' === $source || '' === $target ) {
			return 'empty';
		}
		if ( '/' !== $source[0] ) {
			return 'source_path';
		}
		$is_absolute_target = (bool) preg_match( '#^https?://#i', $target );
		if ( ! $is_absolute_target && '/' !== $target[0] ) {
			return 'target_shape';
		}
		// For absolute URL targets, make sure the string is a parseable URL with
		// a real host — `https://` or `http:// ` would otherwise pass the shape
		// check above and reach wp_redirect() as nonsense.
		if ( $is_absolute_target && null === wp_http_validate_url( $target ) ) {
			return 'target_shape';
		}
		if ( self::normalise_path( $source ) === self::normalise_path( $target ) ) {
			return 'loop';
		}
		return null;
	}

	private function redirect_back( string $notice ): void {
		$url = add_query_arg(
			array( 'aeomatic_redirects_notice' => $notice ),
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_REDIRECTS )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
