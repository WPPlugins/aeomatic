<?php
/**
 * SEO plugin conflict detector.
 *
 * Detects Yoast SEO, Rank Math, AIOSEO, SEOPress and All in One SEO.
 * For each detected plugin, checks which schema types it already emits
 * and advises AEOmatic to skip those to avoid duplicates.
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
	 * Detect which known SEO plugins are active AND currently emitting
	 * schema types that would collide with AEOmatic.
	 *
	 * A plugin being installed/active is not enough on its own — if the
	 * user has turned off the plugin's schema module (Rank Math) or set
	 * every post type's schema to "None" (Yoast), we drop the conflict
	 * entry so the warning banner stops nagging them.
	 *
	 * @return array<string, array{name:string, conflicts:string[], doc_url:string}>
	 */
	public static function detect(): array {
		$found = array();

		// ── Yoast SEO / Yoast SEO Premium ────────────────────────────────
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Frontend' ) ) {
			$conflicts = self::detect_yoast_conflicts();
			if ( ! empty( $conflicts ) ) {
				$found['yoast'] = array(
					'name'      => 'Yoast SEO',
					'conflicts' => $conflicts,
					'doc_url'   => 'https://yoast.com/features/schema/',
				);
			}
		}

		// ── Rank Math ─────────────────────────────────────────────────────
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
			$conflicts = self::detect_rankmath_conflicts();
			if ( ! empty( $conflicts ) ) {
				$found['rankmath'] = array(
					'name'      => 'Rank Math',
					'conflicts' => $conflicts,
					'doc_url'   => 'https://rankmath.com/kb/schema-markup/',
				);
			}
		}

		// ── All in One SEO ────────────────────────────────────────────────
		if ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\Plugin\AIOSEO' ) ) {
			$found['aioseo'] = array(
				'name'      => 'All in One SEO',
				'conflicts' => array( 'article', 'breadcrumb', 'organization', 'author' ),
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

		/**
		 * Filter the detected SEO plugin conflicts.
		 *
		 * Allows sites with unusual setups to drop or adjust entries when
		 * the detector's heuristics don't match their reality (e.g., a
		 * custom filter that suppresses Yoast's schema output).
		 *
		 * Return an empty array (or unset a plugin key) to suppress its
		 * conflict entirely.
		 *
		 * @param array<string, array{name:string, conflicts:string[], doc_url:string}> $found
		 */
		$found = (array) apply_filters( 'aeomatic_seo_conflicts_detected', $found );

		// Drop plugin entries whose 'conflicts' list was emptied by the filter.
		foreach ( $found as $slug => $entry ) {
			if ( empty( $entry['conflicts'] ) || ! is_array( $entry['conflicts'] ) ) {
				unset( $found[ $slug ] );
			}
		}

		return $found;
	}

	/**
	 * Build the Yoast conflict list based on its actual settings.
	 *
	 * Yoast has no global schema off switch, but per-CPT you can set the
	 * page-type / article-type to "None", and breadcrumbs have their own
	 * toggle. We treat each schema key independently:
	 *   • breadcrumb    — only if breadcrumbs-enable is truthy
	 *   • article       — only if any schema-article-type-* is set to a real type
	 *   • organization  — only if company_or_person resolves to company/person
	 *                     AND at least one schema-page-type-* is a real type
	 *   • author        — same trigger as article
	 *
	 * @return string[]
	 */
	protected static function detect_yoast_conflicts(): array {
		// Yoast's master schema toggle (introduced in the Schema Framework
		// feature). When explicitly false, Yoast emits no JSON-LD regardless
		// of the per-CPT schema-*-type-* settings below.
		$main = (array) get_option( 'wpseo', array() );
		if ( array_key_exists( 'enable_schema', $main ) && false === (bool) $main['enable_schema'] ) {
			return array();
		}

		$titles = (array) get_option( 'wpseo_titles', array() );
		$conflicts = array();

		$has_real_type = static function ( array $opts, string $prefix ): bool {
			foreach ( $opts as $key => $val ) {
				if ( 0 !== strpos( (string) $key, $prefix ) ) {
					continue;
				}
				$val = is_string( $val ) ? trim( $val ) : '';
				if ( '' !== $val && 'None' !== $val && 'none' !== $val ) {
					return true;
				}
			}
			return false;
		};

		$article_on = $has_real_type( $titles, 'schema-article-type-' );
		$page_on    = $has_real_type( $titles, 'schema-page-type-' );

		if ( $article_on ) {
			$conflicts[] = 'article';
			$conflicts[] = 'author';
		}

		if ( $page_on ) {
			$org_type = isset( $titles['company_or_person'] ) ? (string) $titles['company_or_person'] : '';
			if ( 'company' === $org_type || 'person' === $org_type ) {
				$conflicts[] = 'organization';
			}
		}

		if ( ! empty( $titles['breadcrumbs-enable'] ) ) {
			$conflicts[] = 'breadcrumb';
		}

		return array_values( array_unique( $conflicts ) );
	}

	/**
	 * Build the Rank Math conflict list based on which modules are active.
	 *
	 * Rank Math ships a "Schema (Structured Data)" module with ID
	 * `rich-snippet` that users can toggle from Dashboard → Modules. When
	 * it's off Rank Math emits no schema, so we drop the entire conflict
	 * list for that plugin.
	 *
	 * @return string[]
	 */
	protected static function detect_rankmath_conflicts(): array {
		$modules      = (array) get_option( 'rank_math_modules', array() );
		$schema_on    = in_array( 'rich-snippet', $modules, true );
		if ( ! $schema_on ) {
			return array();
		}
		return array( 'article', 'breadcrumb', 'organization', 'author' );
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
	 * Whether AEOmatic currently has any conflicting schema types enabled.
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
	 * Show the conflict notice in the admin — only on AEOmatic pages,
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
		);
		$conflict_labels = array_filter(
			array_map( static fn( $k ) => $labels[ $k ] ?? null, $conflicted )
		);

		?>
		<div class="notice notice-warning aeob-conflict-notice" style="border-left-color:#A23FCC;padding:14px 18px;">
			<h3 style="margin:0 0 6px;font-size:15px;color:#1d2327;">
				⚠️ <?php esc_html_e( 'AEOmatic — Schema conflict detected', 'aeomatic' ); ?>
			</h3>
			<p style="margin:0 0 10px;font-size:14px;">
				<?php
				printf(
					/* translators: 1: plugin name(s), 2: schema type list */
					esc_html__( '%1$s is active and already outputs %2$s schema. If AEOmatic also outputs the same types, search engines see duplicate JSON-LD — this can hurt your rankings.', 'aeomatic' ),
					'<strong>' . esc_html( $names ) . '</strong>',
					'<strong>' . esc_html( implode( ', ', $conflict_labels ) ) . '</strong>'
				);
				?>
			</p>
			<p style="margin:0;font-size:13px;color:#50575e;">
				<?php esc_html_e( 'AEOmatic\'s unique features (llms.txt, AI crawler control, AEO audit, FAQ blocks, WooCommerce Product schema) don\'t conflict — only the general schema types listed above do.', 'aeomatic' ); ?>
			</p>
			<?php if ( isset( $plugins['yoast'] ) ) : ?>
			<p style="margin:6px 0 0;font-size:12px;color:#666;">
				<?php esc_html_e( 'Note: turning off Yoast schema requires setting BOTH "Default Page type" and "Default Article type" to None in Yoast → Search Appearance → Content Types → Posts (repeat for Pages), plus disabling breadcrumbs and clearing "Company or person" in Yoast → Search Appearance. Yoast has no single "disable schema" switch.', 'aeomatic' ); ?>
			</p>
			<?php endif; ?>
			<div style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
				<a href="<?php echo esc_url( $fix_url ); ?>" class="button button-primary">
					<?php esc_html_e( '✓ Fix it for me — disable conflicting types', 'aeomatic' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button">
					<?php esc_html_e( 'Keep both (I know what I\'m doing)', 'aeomatic' ); ?>
				</a>
				<span style="font-size:12px;color:#888;">
					<?php esc_html_e( '"Fix it" only disables the duplicate types. All other AEOmatic features stay active.', 'aeomatic' ); ?>
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
			AEOMATIC_Settings::put( $settings );
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
