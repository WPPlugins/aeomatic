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
	const SLUG_ACCESS     = 'aeomatic-ai-access';
	const SLUG_REDIRECTS  = 'aeomatic-redirects';

	public function register(): void {
		add_action( 'admin_menu',             array( $this, 'add_menu' ) );
		add_action( 'admin_init',             array( $this, 'maybe_save' ) );
		add_action( 'admin_init',             array( $this, 'maybe_dismiss_review' ) );
		add_action( 'admin_init',             array( $this, 'maybe_snooze_review' ) );
		add_action( 'admin_init',             array( $this, 'maybe_dismiss_whats_new' ) );
		add_action( 'admin_notices',          array( $this, 'maybe_show_review_notice' ) );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . AEOMATIC_BASENAME, array( $this, 'plugin_links' ) );
	}

	public function add_menu(): void {
		/**
		 * Brand label filter — AEOmatic Pro's white-label module rewrites the
		 * menu title so an agency can ship the plugin under their own name.
		 * Two-arg to allow the filter to distinguish menu label from page title.
		 */
		$brand_menu = apply_filters( 'aeomatic_brand_label', __( 'AEOmatic', 'aeomatic' ), 'menu' );
		$brand_page = apply_filters( 'aeomatic_brand_label', __( 'AEOmatic — AI SEO & Schema for AI Search', 'aeomatic' ), 'page_title' );
		$brand_icon = apply_filters( 'aeomatic_brand_icon', 'dashicons-chart-line' );

		add_menu_page(
			$brand_page,
			$brand_menu,
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			$brand_icon,
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'AI Readiness Dashboard — AEOmatic', 'aeomatic' ),
			__( 'Dashboard', 'aeomatic' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'AI Access — AEOmatic', 'aeomatic' ),
			__( 'AI Access', 'aeomatic' ),
			'manage_options',
			self::SLUG_ACCESS,
			array( $this, 'render_access' )
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
			__( 'URL redirects — AEOmatic', 'aeomatic' ),
			__( 'Redirects', 'aeomatic' ),
			'manage_options',
			self::SLUG_REDIRECTS,
			array( $this, 'render_redirects' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings — AEOmatic', 'aeomatic' ),
			__( 'Settings', 'aeomatic' ),
			'manage_options',
			self::SLUG_SETTINGS,
			array( $this, 'render_settings' )
		);

		// Extension hook: let AEOmatic Pro register its own submenus (License,
		// scheduled audits, etc.) under the same top-level menu.
		do_action( 'aeomatic_admin_submenus', self::MENU_SLUG );
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

		if ( false !== strpos( $hook, self::SLUG_ACCESS ) ) {
			wp_enqueue_script(
				'aeob-ai-access',
				AEOMATIC_URL . 'assets/js/ai-access.js',
				array( 'wp-api-fetch', 'wp-i18n' ),
				AEOMATIC_VERSION,
				true
			);
			wp_set_script_translations( 'aeob-ai-access', 'aeomatic' );
		}

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

		/**
		 * Fires after core settings are saved.
		 *
		 * @param array $input Unslashed, unsanitised form input.
		 */
		do_action( 'aeomatic_settings_saved', $input );

		add_settings_error( 'aeomatic_messages', 'aeomatic_saved', __( 'Settings saved.', 'aeomatic' ), 'updated' );
	}

	public function render_dashboard(): void {
		require AEOMATIC_DIR . 'admin/views/dashboard.php';
	}

	public function render_access(): void {
		require AEOMATIC_DIR . 'admin/views/ai-access.php';
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

	public function render_redirects(): void {
		require AEOMATIC_DIR . 'admin/views/redirects.php';
	}

	/**
	 * Dismiss the review notice permanently when the user clicks "Already did" or "×".
	 */
	public function maybe_dismiss_review(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['aeomatic_dismiss_review'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! self::review_nonce_ok() ) {
			return;
		}
		update_option( 'aeomatic_review_dismissed', true, false );
		// Redirect back cleanly without the query params.
		$redirect = remove_query_arg( array( 'aeomatic_dismiss_review', '_wpnonce' ) );
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
		if ( ! current_user_can( 'manage_options' ) || ! self::review_nonce_ok() ) {
			return;
		}
		update_option( 'aeomatic_review_snoozed_until', time() + ( 14 * DAY_IN_SECONDS ), false );
		$redirect = remove_query_arg( array( 'aeomatic_snooze_review', '_wpnonce' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Nonce check for the review notice's dismiss / snooze links.
	 */
	private static function review_nonce_ok(): bool {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, 'aeomatic_review_notice' );
	}

	/**
	 * Dismiss the "What's new" dashboard card for the current version only.
	 * Stores the dismissed version, so the next release's card appears
	 * automatically without any extra bookkeeping.
	 */
	public function maybe_dismiss_whats_new(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['aeomatic_dismiss_whats_new'] ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, 'aeomatic_whats_new' ) ) {
			return;
		}
		update_option( 'aeomatic_whats_new_dismissed_version', AEOMATIC_VERSION, false );
		$redirect = remove_query_arg( array( 'aeomatic_dismiss_whats_new', '_wpnonce' ) );
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
		// Not wp_nonce_url(): it HTML-encodes "&", which breaks the raw URL the fetch() below uses.
		$nonce       = wp_create_nonce( 'aeomatic_review_notice' );
		$dismiss_url = add_query_arg( array( 'aeomatic_dismiss_review' => '1', '_wpnonce' => $nonce ) );
		$snooze_url  = add_query_arg( array( 'aeomatic_snooze_review' => '1', '_wpnonce' => $nonce ) );

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
					<?php esc_html_e( 'If AEOmatic has been useful for your site\'s AI readiness, a quick review on WordPress.org makes a real difference. It takes less than a minute.', 'aeomatic' ); ?>
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
