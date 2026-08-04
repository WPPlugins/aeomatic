<?php
/**
 * Admin UI.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Admin {

	const MENU_SLUG    = 'aeomatic';
	const NONCE_ACTION = 'aeomatic_save_settings';
	const SLUG_ACTIVITY   = 'aeomatic-activity';
	const SLUG_GAPS       = 'aeomatic-citation-gaps';
	const SLUG_READINESS  = 'aeomatic-ai-readiness';
	const SLUG_ENTITY_SCAN = 'aeomatic-entity-scanner';
	const SLUG_SETTINGS   = 'aeomatic-settings';

	public function register(): void {
		add_action( 'admin_menu',             array( $this, 'add_menu' ) );
		add_action( 'admin_init',             array( $this, 'maybe_save' ) );
		add_action( 'admin_init',             array( $this, 'maybe_dismiss_review' ) );
		add_action( 'admin_init',             array( $this, 'maybe_snooze_review' ) );
		add_action( 'admin_notices',          array( $this, 'maybe_show_review_notice' ) );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . AEOMATIC_BASENAME, array( $this, 'plugin_links' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'AEOmatic — AI based Answer Engine Optimization', 'aeomatic' ),
			__( 'AEOmatic', 'aeomatic' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-chart-line',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard — AEOmatic', 'aeomatic' ),
			__( 'Dashboard', 'aeomatic' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Activity — AEOmatic', 'aeomatic' ),
			__( 'What did we do?', 'aeomatic' ),
			'manage_options',
			self::SLUG_ACTIVITY,
			array( $this, 'render_activity' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'AI Citation Gaps — AEOmatic', 'aeomatic' ),
			__( 'Citation Gaps', 'aeomatic' ),
			'manage_options',
			self::SLUG_GAPS,
			array( $this, 'render_gaps' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'AI Readiness — AEOmatic', 'aeomatic' ),
			__( 'AI Readiness', 'aeomatic' ),
			'manage_options',
			self::SLUG_READINESS,
			array( $this, 'render_readiness' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Entity Scanner — AEOmatic', 'aeomatic' ),
			__( 'Entity Scanner', 'aeomatic' ),
			'manage_options',
			self::SLUG_ENTITY_SCAN,
			array( $this, 'render_entity_scanner' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings — AEOmatic', 'aeomatic' ),
			__( 'Settings', 'aeomatic' ),
			'manage_options',
			self::SLUG_SETTINGS,
			array( $this, 'render_settings' )
		);
	}

	public function plugin_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=' . self::SLUG_SETTINGS );
		$wizard_url   = admin_url( 'admin.php?page=' . AEOMATIC_Wizard::SLUG );
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $wizard_url ), esc_html__( 'Setup wizard', 'aeomatic' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Settings', 'aeomatic' ) )
		);
		return $links;
	}

	public function enqueue_assets( string $hook ): void {
		$is_aeob = false !== strpos( $hook, 'aeomatic' ) ||
		           false !== strpos( $hook, 'aeo_booster' );
		if ( ! $is_aeob ) {
			return;
		}

		wp_enqueue_style(
			'aeob-admin',
			AEOMATIC_URL . 'assets/css/admin.css',
			array(),
			AEOMATIC_VERSION
		);
		wp_enqueue_script(
			'aeob-admin',
			AEOMATIC_URL . 'assets/js/admin.js',
			array( 'wp-api-fetch', 'wp-i18n' ),
			AEOMATIC_VERSION,
			true
		);
		wp_localize_script( 'aeob-admin', 'aeomaticAdmin', array(
			'restUrl' => esc_url_raw( rest_url( 'aeomatic/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		) );

		if ( false !== strpos( $hook, self::SLUG_ACTIVITY ) ||
		     false !== strpos( $hook, str_replace( '-', '_', self::SLUG_ACTIVITY ) ) ) {
			wp_enqueue_script(
				'aeob-verify',
				AEOMATIC_URL . 'assets/js/verify.js',
				array( 'wp-api-fetch', 'wp-i18n' ),
				AEOMATIC_VERSION,
				true
			);
		}
	}

	public function maybe_save(): void {
		if ( empty( $_POST['aeomatic_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aeomatic' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		// Sanitisation of individual fields is handled recursively inside AEOMATIC_Settings::save().
		// The raw array must be passed unslashed so nested sanitisation functions work correctly.
		$input = isset( $_POST['aeob'] ) ? wp_unslash( $_POST['aeob'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		AEOMATIC_Settings::save( $input );
		flush_rewrite_rules();
		AEOMATIC_Activity::log( 'settings_saved', __( 'Settings updated.', 'aeomatic' ) );
		add_settings_error( 'aeomatic_messages', 'aeomatic_saved', __( 'Settings saved.', 'aeomatic' ), 'updated' );
	}

	public function render_dashboard(): void {
		require AEOMATIC_DIR . 'admin/views/dashboard.php';
	}

	public function render_activity(): void {
		require AEOMATIC_DIR . 'admin/views/activity.php';
	}

	public function render_gaps(): void {
		require AEOMATIC_DIR . 'admin/views/gaps.php';
	}

	public function render_readiness(): void {
		require AEOMATIC_DIR . 'admin/views/ai-readiness.php';
	}

	public function render_entity_scanner(): void {
		require AEOMATIC_DIR . 'admin/views/entity-scanner.php';
	}

	public function render_settings(): void {
		require AEOMATIC_DIR . 'admin/views/settings.php';
	}

	/**
	 * Dismiss the review notice permanently when the user clicks "Already did" or "×".
	 */
	public function maybe_dismiss_review(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['aeomatic_dismiss_review'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( 'aeomatic_review_dismissed', true, false );
		// Redirect back cleanly without the query param.
		$redirect = remove_query_arg( 'aeomatic_dismiss_review' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Snooze the review notice for 14 days when the user clicks "Remind me later".
	 */
	public function maybe_snooze_review(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['aeomatic_snooze_review'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( 'aeomatic_review_snoozed_until', time() + ( 14 * DAY_IN_SECONDS ), false );
		$redirect = remove_query_arg( 'aeomatic_snooze_review' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Show a review request notice once the user has had time and engagement with
	 * the plugin:
	 *   - 7 days since install, AND
	 *   - at least 5 schema renders (proves the plugin is actually doing work).
	 *
	 * The notice can be snoozed for 14 days ("Remind me later") or dismissed
	 * permanently ("Already did" / "Leave a review" / "×"). Snooze gives genuinely
	 * interested users a second chance instead of forcing a hard yes/no.
	 */
	public function maybe_show_review_notice(): void {
		// Already dismissed permanently.
		if ( get_option( 'aeomatic_review_dismissed' ) ) {
			return;
		}

		// Snoozed — not yet time to re-show.
		$snoozed_until = (int) get_option( 'aeomatic_review_snoozed_until', 0 );
		if ( $snoozed_until && $snoozed_until > time() ) {
			return;
		}

		// Only admins see this.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Time gate: 7 days after install.
		$installed_at = (int) AEOMATIC_Settings::get( '_meta', 'installed_at' );
		if ( ! $installed_at || ( time() - $installed_at ) < 7 * DAY_IN_SECONDS ) {
			return;
		}

		// Engagement gate: must have rendered schema at least 5 times. Avoids
		// nagging users who installed but haven't actually benefited yet.
		if ( class_exists( 'AEOMATIC_Activity' ) ) {
			$stats   = AEOMATIC_Activity::get_stats();
			$renders = (int) ( $stats['schema_renders'] ?? 0 );
			if ( $renders < 5 ) {
				return;
			}
		}

		$review_url  = 'https://wordpress.org/support/plugin/aeomatic/reviews/#new-post';
		$dismiss_url = add_query_arg( 'aeomatic_dismiss_review', '1' );
		$snooze_url  = add_query_arg( 'aeomatic_snooze_review', '1' );

		// Background-dismiss when the user clicks "Leave a review" — the link
		// opens wp.org in a new tab via target="_blank"; this fetch silently
		// records the dismiss without navigating the current admin page away.
		$review_onclick = sprintf(
			"fetch(%s,{credentials:'same-origin'}).catch(function(){});return true;",
			wp_json_encode( esc_url_raw( $dismiss_url ) )
		);
		?>
		<div class="notice notice-info aiaeo-review-notice" style="border-left-color:#5B3FFF; padding: 14px 16px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
			<img src="<?php echo esc_url( AEOMATIC_URL . 'assets/images/icon.svg' ); ?>" alt="" width="40" height="40" style="flex-shrink:0;">
			<div style="flex:1; min-width:200px;">
				<strong style="font-size:14px;">
					<?php esc_html_e( 'Enjoying AEOmatic?', 'aeomatic' ); ?>
				</strong>
				<p style="margin:4px 0 0; color:#50575e;">
					<?php esc_html_e( 'If AEOmatic is helping your site get found by ChatGPT, Claude and Perplexity — a quick review on WordPress.org makes a huge difference. It takes less than a minute.', 'aeomatic' ); ?>
				</p>
			</div>
			<div style="display:flex; gap:10px; align-items:center; flex-shrink:0;">
				<a href="<?php echo esc_url( $review_url ); ?>"
				   target="_blank"
				   rel="noopener noreferrer"
				   class="button button-primary"
				   style="background:#5B3FFF; border-color:#5B3FFF; display:flex; align-items:center; gap:5px;"
				   onclick="<?php echo esc_attr( $review_onclick ); ?>">
					⭐ <?php esc_html_e( 'Leave a review', 'aeomatic' ); ?>
				</a>
				<a href="<?php echo esc_url( $snooze_url ); ?>"
				   class="button button-secondary">
					<?php esc_html_e( 'Remind me later', 'aeomatic' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"
				   class="button button-secondary">
					<?php esc_html_e( 'Already did ✓', 'aeomatic' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"
				   style="color:#aaa; text-decoration:none; font-size:18px; line-height:1;"
				   title="<?php esc_attr_e( 'Dismiss', 'aeomatic' ); ?>">×</a>
			</div>
		</div>
		<?php
	}
}
