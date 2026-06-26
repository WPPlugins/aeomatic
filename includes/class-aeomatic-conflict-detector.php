<?php
/**
 * SEO plugin conflict detector.
 *
 * Detects Yoast SEO, Rank Math, AIOSEO, SEOPress and All in One SEO.
 * For each detected plugin, checks which schema types it already emits
 * and advises AEOmatic — AI based Answer Engine Optimization to skip those to avoid duplicates.
 *
 * Also fires admin notices when a conflict is detected, with a one-click
 * "Fix it for me" button that disables the overlapping schema types.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Conflict_Detector {

	const NOTICE_DISMISSED_KEY = 'aeomatic_conflict_notice_dismissed';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybe_show_notice' ) );
		add_action( 'admin_post_aeomatic_fix_conflicts', array( $this, 'handle_fix' ) );
	}

	/**
	 * Detect which known SEO plugins are active.
	 *
	 * @return array<string, array{name:string, conflicts:string[]}>
	 */
	public static function detect(): array {
		$found = array();

		// ── Yoast SEO / Yoast SEO Premium ────────────────────────────────
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Frontend' ) ) {
			$conflicts = array( 'article', 'breadcrumb', 'organization', 'author' );
			// Yoast emits FAQ schema too if its FAQ block is used.
			if ( class_exists( 'WPSEO_Schema_FAQ_Section' ) ) {
				$conflicts[] = 'faq';
			}
			$found['yoast'] = array(
				'name'      => 'Yoast SEO',
				'conflicts' => $conflicts,
				'doc_url'   => 'https://yoast.com/features/schema/',
			);
		}

		// ── Rank Math ─────────────────────────────────────────────────────
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$found['rankmath'] = array(
				'name'      => 'Rank Math',
				'conflicts' => array( 'article', 'breadcrumb', 'organization', 'author', 'faq', 'howto' ),
				'doc_url'   => 'https://rankmath.com/kb/schema-markup/',
			);
		}

		// ── All in One SEO ────────────────────────────────────────────────
		if ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\Plugin\AIOSEO' ) ) {
			$found['aioseo'] = array(
				'name'      => 'All in One SEO',
				'conflicts' => array( 'article', 'breadcrumb', 'organization', 'author', 'faq' ),
				'doc_url'   => 'https://aioseo.com/docs/schema-markup/',
			);
		}

		// ── SEOPress ──────────────────────────────────────────────────────
		if ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_activation' ) ) {
			$found['seopress'] = array(
				'name'      => 'SEOPress',
				'conflicts' => array( 'article', 'breadcrumb', 'organization' ),
				'doc_url'   => 'https://www.seopress.org/support/guides/schema-markup/',
			);
		}

		// ── The SEO Framework ─────────────────────────────────────────────
		if ( function_exists( 'the_seo_framework' ) ) {
			$found['tsf'] = array(
				'name'      => 'The SEO Framework',
				'conflicts' => array( 'article', 'breadcrumb', 'organization', 'author' ),
				'doc_url'   => 'https://theseoframework.com/?p=2582',
			);
		}

		return $found;
	}

	/**
	 * Return a flat list of schema setting keys that are conflicted by
	 * any currently-active SEO plugin.
	 *
	 * @return string[]  e.g. ['article', 'breadcrumb', 'organization']
	 */
	public static function conflicted_keys(): array {
		$keys = array();
		foreach ( self::detect() as $plugin ) {
			$keys = array_merge( $keys, $plugin['conflicts'] );
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Whether AEOmatic — AI based Answer Engine Optimization currently has any conflicting schema types enabled.
	 */
	public static function has_active_conflicts(): bool {
		$settings  = AEOMATIC_Settings::get( 'schema' );
		$conflicts = self::conflicted_keys();
		foreach ( $conflicts as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Show the conflict notice in the admin — only on AEOmatic — AI based Answer Engine Optimization pages,
	 * and only if not already dismissed.
	 */
	public function maybe_show_notice(): void {
		// Only on our admin pages. Reading $_GET['page'] for routing display only — no form processing, no nonce needed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$aeomatic_current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( false === strpos( $aeomatic_current_page, AEOMATIC_Admin::MENU_SLUG ) ) {
			return;
		}
		if ( get_option( self::NOTICE_DISMISSED_KEY ) ) {
			return;
		}

		$plugins = self::detect();
		if ( empty( $plugins ) || ! self::has_active_conflicts() ) {
			return;
		}

		$names     = implode( ' and ', array_column( $plugins, 'name' ) );
		$fix_url   = wp_nonce_url(
			admin_url( 'admin-post.php?action=aeomatic_fix_conflicts' ),
			'aeomatic_fix_conflicts'
		);
		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=aeomatic_fix_conflicts&dismiss=1' ),
			'aeomatic_fix_conflicts'
		);

		$conflicted = self::conflicted_keys();
		$labels     = array(
			'article'      => 'Article',
			'breadcrumb'   => 'Breadcrumb',
			'organization' => 'Organization',
			'author'       => 'Author',
			'faq'          => 'FAQ',
			'howto'        => 'HowTo',
		);
		$conflict_labels = array_filter(
			array_map( static fn( $k ) => $labels[ $k ] ?? null, $conflicted )
		);

		?>
		<div class="notice notice-warning aeob-conflict-notice" style="border-left-color:#A23FCC;padding:14px 18px;">
			<h3 style="margin:0 0 6px;font-size:15px;color:#1d2327;">
				⚠️ <?php esc_html_e( 'AEOmatic — AI based Answer Engine Optimization — Schema conflict detected', 'aeomatic' ); ?>
			</h3>
			<p style="margin:0 0 10px;font-size:14px;">
				<?php
				printf(
					/* translators: 1: plugin name(s), 2: schema type list */
					esc_html__( '%1$s is active and already outputs %2$s schema. If AEOmatic — AI based Answer Engine Optimization also outputs the same types, search engines see duplicate JSON-LD — this can hurt your rankings.', 'aeomatic' ),
					'<strong>' . esc_html( $names ) . '</strong>',
					'<strong>' . esc_html( implode( ', ', $conflict_labels ) ) . '</strong>'
				);
				?>
			</p>
			<p style="margin:0;font-size:13px;color:#50575e;">
				<?php esc_html_e( 'AEOmatic — AI based Answer Engine Optimization\'s unique features (llms.txt, AI crawler control, AEO audit, FAQ blocks, WooCommerce Product schema) don\'t conflict — only the general schema types listed above do.', 'aeomatic' ); ?>
			</p>
			<div style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
				<a href="<?php echo esc_url( $fix_url ); ?>" class="button button-primary">
					<?php esc_html_e( '✓ Fix it for me — disable conflicting types', 'aeomatic' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button">
					<?php esc_html_e( 'Keep both (I know what I\'m doing)', 'aeomatic' ); ?>
				</a>
				<span style="font-size:12px;color:#888;">
					<?php esc_html_e( '"Fix it" only disables the duplicate types. All other AEOmatic — AI based Answer Engine Optimization features stay active.', 'aeomatic' ); ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the "Fix it for me" form action.
	 * Disables the conflicting schema settings and redirects back.
	 */
	public function handle_fix(): void {
		check_admin_referer( 'aeomatic_fix_conflicts' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'aeomatic' ) );
		}

		if ( ! empty( $_GET['dismiss'] ) ) {
			// Just dismiss — keep settings as-is.
			update_option( self::NOTICE_DISMISSED_KEY, true );
		} else {
			// Disable the conflicting schema keys.
			$conflicted = self::conflicted_keys();
			$settings   = AEOMATIC_Settings::all();
			foreach ( $conflicted as $key ) {
				if ( isset( $settings['schema'][ $key ] ) ) {
					$settings['schema'][ $key ] = false;
				}
			}
			update_option( AEOMATIC_OPTION_KEY, $settings );
			update_option( self::NOTICE_DISMISSED_KEY, true );

			AEOMATIC_Activity::log(
				'conflict_resolved',
				sprintf(
					/* translators: %s: comma-separated list of disabled schema types */
					__( 'Conflict resolved: disabled %s schema (already provided by active SEO plugin).', 'aeomatic' ),
					implode( ', ', $conflicted )
				)
			);
		}

		wp_safe_redirect(
			admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS . '&aeomatic_conflict=resolved' )
		);
		exit;
	}

	/**
	 * Short summary string for the Settings page — tells user what's active.
	 * Returns empty string if no conflicts.
	 */
	public static function get_status_html(): string {
		$plugins = self::detect();
		if ( empty( $plugins ) ) {
			return '<span style="color:#1d9e75;">✓ ' . esc_html__( 'No conflicting SEO plugins detected.', 'aeomatic' ) . '</span>';
		}

		$names = array_column( $plugins, 'name' );
		if ( self::has_active_conflicts() ) {
			$fix_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=aeomatic_fix_conflicts' ),
				'aeomatic_fix_conflicts'
			);
			// translators: %s: comma-separated list of detected SEO plugin names.
			$aeomatic_conflict_msg = esc_html__( '%s detected — some schema types overlap.', 'aeomatic' );
			return sprintf(
				'<span style="color:#854f0b;">⚠ ' . $aeomatic_conflict_msg . '</span> <a href="%s" class="button button-small">%s</a>',
				esc_html( implode( ', ', $names ) ),
				esc_url( $fix_url ),
				esc_html__( 'Fix conflicts', 'aeomatic' )
			);
		}

		// translators: %s: comma-separated list of detected SEO plugin names.
		$aeomatic_resolved_msg = esc_html__( '%s detected — conflicts already resolved.', 'aeomatic' );
		return sprintf(
			'<span style="color:#1d9e75;">✓ ' . $aeomatic_resolved_msg . '</span>',
			esc_html( implode( ', ', $names ) )
		);
	}
}
