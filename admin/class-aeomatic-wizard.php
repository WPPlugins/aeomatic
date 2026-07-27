<?php
/**
 * First-run setup wizard.
 *
 * Five-step guided onboarding: welcome → import from an existing SEO plugin
 * → organisation details → AI crawler defaults → verify. Diverges from
 * competitor wizards on layout (vertical step rail, live-preview column)
 * and messaging (offline-first, no accounts required). Runs once after
 * activation and can be re-launched from the dashboard.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Wizard {

	const SLUG          = 'aeomatic-wizard';
	const NONCE_ACTION  = 'aeomatic_wizard';
	const REDIRECT_FLAG = 'aeomatic_wizard_redirect';
	const COMPLETED_KEY = 'aeomatic_wizard_completed';

	const STEPS = array( 'welcome', 'import', 'org', 'crawlers', 'verify' );

	public function register(): void {
		add_action( 'admin_menu',  array( $this, 'register_page' ), 20 );
		add_action( 'admin_init',  array( $this, 'maybe_redirect' ) );
		add_action( 'admin_init',  array( $this, 'maybe_handle_submit' ) );
	}

	/**
	 * Attach a hidden admin page for the wizard. Not exposed in the sidebar;
	 * accessed via ?page=aeomatic-wizard.
	 */
	public function register_page(): void {
		add_submenu_page(
			null,
			__( 'AEOmatic Setup', 'aeomatic' ),
			__( 'AEOmatic Setup', 'aeomatic' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Called by the activation hook so the wizard opens on the next admin
	 * request. Only sets the redirect flag if the wizard has never run.
	 */
	public static function schedule_redirect(): void {
		if ( ! get_option( self::COMPLETED_KEY ) ) {
			set_transient( self::REDIRECT_FLAG, 1, 60 );
		}
	}

	public function maybe_redirect(): void {
		if ( ! get_transient( self::REDIRECT_FLAG ) ) {
			return;
		}
		delete_transient( self::REDIRECT_FLAG );

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	public function current_step(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( (string) $_GET['step'] ) ) : self::STEPS[0];
		return in_array( $step, self::STEPS, true ) ? $step : self::STEPS[0];
	}

	public function step_index( string $step ): int {
		$idx = array_search( $step, self::STEPS, true );
		return false === $idx ? 0 : (int) $idx;
	}

	public function step_url( string $step ): string {
		return admin_url( 'admin.php?page=' . self::SLUG . '&step=' . $step );
	}

	public function next_step_url( string $current ): string {
		$idx = $this->step_index( $current );
		$next = self::STEPS[ $idx + 1 ] ?? null;
		return $next ? $this->step_url( $next ) : admin_url( 'admin.php?page=' . AEOMATIC_Admin::MENU_SLUG );
	}

	public function prev_step_url( string $current ): string {
		$idx = $this->step_index( $current );
		$prev = self::STEPS[ $idx - 1 ] ?? null;
		return $prev ? $this->step_url( $prev ) : $this->step_url( self::STEPS[0] );
	}

	public function step_label( string $step ): string {
		switch ( $step ) {
			case 'welcome':  return __( 'Welcome', 'aeomatic' );
			case 'import':   return __( 'Import existing settings', 'aeomatic' );
			case 'org':      return __( 'Your organisation', 'aeomatic' );
			case 'crawlers': return __( 'AI crawlers', 'aeomatic' );
			case 'verify':   return __( 'Verify & finish', 'aeomatic' );
		}
		return $step;
	}

	/**
	 * Handle POST submissions for each step (skip and completion also come here).
	 */
	public function maybe_handle_submit(): void {
		if ( empty( $_POST['aeomatic_wizard_submit'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'aeomatic' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( (string) $_POST['step'] ) ) : '';
		if ( ! in_array( $step, self::STEPS, true ) ) {
			return;
		}

		switch ( $step ) {
			case 'import':
				$this->handle_import();
				break;
			case 'org':
				$this->handle_org();
				break;
			case 'crawlers':
				$this->handle_crawlers();
				break;
			case 'verify':
				update_option( self::COMPLETED_KEY, time() );
				AEOMATIC_Activity::log( 'wizard_completed', __( 'Setup wizard completed.', 'aeomatic' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=' . AEOMATIC_Admin::MENU_SLUG . '&aeomatic_wizard=done' ) );
				exit;
		}

		wp_safe_redirect( $this->next_step_url( $step ) );
		exit;
	}

	private function handle_import(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sources = isset( $_POST['import_sources'] ) ? (array) wp_unslash( $_POST['import_sources'] ) : array();
		if ( empty( $sources ) ) {
			return;
		}
		$sources = array_map( 'sanitize_key', $sources );

		$available = AEOMATIC_SEO_Importer::inspect();
		foreach ( $sources as $slug ) {
			if ( isset( $available[ $slug ] ) ) {
				AEOMATIC_SEO_Importer::apply( $available[ $slug ] );
				AEOMATIC_Activity::log(
					'wizard_import',
					sprintf(
						/* translators: %s: source plugin label */
						__( 'Imported organisation settings from %s.', 'aeomatic' ),
						$available[ $slug ]['label']
					)
				);
			}
		}
	}

	private function handle_org(): void {
		$all    = AEOMATIC_Settings::all();
		$schema = $all['schema'];

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$org_name = isset( $_POST['org_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['org_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$org_logo = isset( $_POST['org_logo'] ) ? esc_url_raw( wp_unslash( (string) $_POST['org_logo'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$org_sameas_raw = isset( $_POST['org_sameas'] ) ? (string) wp_unslash( $_POST['org_sameas'] ) : '';

		$urls = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $org_sameas_raw ) ) );
		$urls = array_map( 'esc_url_raw', $urls );

		$schema['org_name']   = $org_name;
		$schema['org_logo']   = $org_logo;
		$schema['org_sameas'] = implode( "\n", array_filter( $urls ) );

		$all['schema'] = $schema;
		update_option( AEOMATIC_OPTION_KEY, $all );
	}

	private function handle_crawlers(): void {
		$all      = AEOMATIC_Settings::all();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$crawlers = isset( $_POST['crawlers'] ) ? (array) wp_unslash( $_POST['crawlers'] ) : array();

		foreach ( $all['crawlers'] as $bot => $_ ) {
			$all['crawlers'][ $bot ] = ! empty( $crawlers[ $bot ] );
		}

		update_option( AEOMATIC_OPTION_KEY, $all );
		flush_rewrite_rules();
	}

	/**
	 * Render the wizard shell. Loads the step body from admin/views/wizard.php.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'aeomatic' ) );
		}

		$wizard  = $this;
		$step    = $this->current_step();
		$sources = AEOMATIC_SEO_Importer::inspect();
		$schema  = AEOMATIC_Settings::get( 'schema' );
		$crawlers = AEOMATIC_Settings::get( 'crawlers' );

		require AEOMATIC_DIR . 'admin/views/wizard.php';
	}
}
