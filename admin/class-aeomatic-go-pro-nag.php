<?php
/**
 * Soft "Go Pro" notice shown on AEOmatic admin screens only.
 *
 * Suppressed as soon as any Pro extension registers itself via
 * AEOMATIC_Extensions::register_module() with a non-free tier. Per-user
 * dismissible so multiple admins on one site can each decide independently.
 *
 * Kept in its own file so removing the entire nag is one require line.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Go_Pro_Nag {

	const USER_META_KEY = 'aeomatic_go_pro_dismissed';
	const PRICING_URL   = 'https://getaeomatic.com/pricing';
	const MIN_AGE_DAYS  = 14;

	public function register(): void {
		add_action( 'admin_init',    array( $this, 'maybe_dismiss' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
	}

	public function maybe_dismiss(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['aeomatic_dismiss_go_pro'] ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, 'aeomatic_dismiss_go_pro' ) ) {
			return;
		}
		update_user_meta( get_current_user_id(), self::USER_META_KEY, time() );
		wp_safe_redirect( remove_query_arg( array( 'aeomatic_dismiss_go_pro', '_wpnonce' ) ) );
		exit;
	}

	public function maybe_render(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$dismiss_url = add_query_arg( array( 'aeomatic_dismiss_go_pro' => '1', '_wpnonce' => wp_create_nonce( 'aeomatic_dismiss_go_pro' ) ) );
		?>
		<div class="notice notice-info aeob-go-pro-notice" style="border-left-color:#00d4ff; padding:14px 16px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
			<div style="flex:1; min-width:260px;">
				<strong style="font-size:14px;">
					<?php esc_html_e( 'Ready for more? AEOmatic Pro is here.', 'aeomatic' ); ?>
				</strong>
				<p style="margin:4px 0 0; color:#50575e;">
					<?php esc_html_e( 'Search Console + GA4 integration, an automated fix engine, scheduled audits with email alerts, and 90-day trend charts. From $49/yr.', 'aeomatic' ); ?>
				</p>
			</div>
			<div style="display:flex; gap:10px; align-items:center; flex-shrink:0;">
				<a href="<?php echo esc_url( self::PRICING_URL ); ?>"
				   target="_blank"
				   rel="noopener noreferrer"
				   class="button button-primary">
					<?php esc_html_e( 'See Pro plans', 'aeomatic' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"
				   class="button button-secondary">
					<?php esc_html_e( 'Not now', 'aeomatic' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Every gate together, so the render function stays tidy.
	 */
	private function should_show(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Any Pro extension registered → no nag.
		if ( class_exists( 'AEOMATIC_Extensions' ) && AEOMATIC_Extensions::has_pro() ) {
			return false;
		}

		// This user has already dismissed.
		if ( (int) get_user_meta( get_current_user_id(), self::USER_META_KEY, true ) > 0 ) {
			return false;
		}

		// Only on AEOmatic admin screens — avoid becoming site-wide spam.
		if ( ! $this->on_aeomatic_screen() ) {
			return false;
		}

		// Give free-plugin users time to see value first.
		$installed_at = (int) AEOMATIC_Settings::get( '_meta', 'installed_at' );
		if ( $installed_at && ( time() - $installed_at ) < ( self::MIN_AGE_DAYS * DAY_IN_SECONDS ) ) {
			return false;
		}

		return true;
	}

	private function on_aeomatic_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return false !== strpos( (string) $screen->id, 'aeomatic' );
	}
}
