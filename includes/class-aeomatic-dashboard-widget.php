<?php
/**
 * WordPress Dashboard widget — AEO Health at a glance.
 *
 * A single compact tile shown on the native wp-admin dashboard so site
 * owners see AEO status without clicking into the plugin. Differs from the
 * competitor's score-only widget by exposing four independent signals side
 * by side: audit score, high-severity gap count, recent AI-bot activity
 * with a mini bar, and log retention health.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Dashboard_Widget {

	const WIDGET_ID = 'aeomatic_health_widget';

	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ) );
	}

	public function add_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			self::WIDGET_ID,
			esc_html__( 'AEOmatic — AI Answer Engine Readiness', 'aeomatic' ),
			array( $this, 'render' )
		);
	}

	public function render(): void {
		$audit = ( new AEOMATIC_Audit() )->site_summary();
		$gaps  = ( new AEOMATIC_Citation_Gaps() )->site_summary();

		$bot_summary = AEOMATIC_Crawler_Log::get_summary();
		$retention   = AEOMATIC_Crawler_Log::get_retention_summary();

		$score          = (int) ( $audit['average'] ?? 0 );
		$scanned_count  = (int) ( $audit['count'] ?? 0 );
		$posts_with_gap = (int) ( $gaps['with_gaps'] ?? 0 );
		$clean_count    = (int) ( $gaps['clean'] ?? 0 );

		$dashboard_url = admin_url( 'admin.php?page=' . AEOMATIC_Admin::MENU_SLUG );
		$gaps_url      = admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_GAPS );
		$activity_url  = admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_ACTIVITY );
		$settings_url  = admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS );

		$score_color = $score >= 70 ? '#1d9e75' : ( $score >= 40 ? '#ba7517' : '#a32d2d' );
		$score_label = $score >= 70 ? __( 'Great', 'aeomatic' )
			: ( $score >= 40 ? __( 'Needs work', 'aeomatic' ) : __( 'At risk', 'aeomatic' ) );

		// Top 3 bots — small horizontal bars, scaled to the busiest bot so
		// the shape reads at a glance regardless of absolute traffic.
		$top_bots = array_slice( $bot_summary, 0, 3, true );
		$max_bot  = $top_bots ? max( $top_bots ) : 0;

		$total_visits  = array_sum( $bot_summary );
		$kept_entries  = (int) ( $retention['kept'] ?? 0 );
		$purged_last   = (int) ( $retention['purged_last'] ?? 0 );
		$retention_days = (int) ( $retention['retention_days'] ?? 90 );

		?>
		<style>
			.aeomatic-widget { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:-4px 0 8px; }
			.aeomatic-widget .aeom-tile { background:#f6f7f7; border:1px solid #e0e0e0; border-radius:6px; padding:10px 12px; }
			.aeomatic-widget .aeom-tile__label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#646970; margin:0 0 4px; font-weight:600; }
			.aeomatic-widget .aeom-tile__value { font-size:22px; font-weight:600; line-height:1.1; }
			.aeomatic-widget .aeom-tile__meta { font-size:11px; color:#646970; margin-top:2px; }
			.aeomatic-widget .aeom-tile--wide { grid-column:1 / -1; }
			.aeomatic-widget .aeom-bar-row { display:flex; align-items:center; gap:8px; margin:3px 0; font-size:12px; }
			.aeomatic-widget .aeom-bar-row__name { flex:0 0 130px; color:#1d2327; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
			.aeomatic-widget .aeom-bar-row__bar { flex:1; height:8px; background:#e0e0e0; border-radius:4px; overflow:hidden; }
			.aeomatic-widget .aeom-bar-row__fill { display:block; height:100%; background:linear-gradient(90deg,#5B3FFF,#7A5FFF); }
			.aeomatic-widget .aeom-bar-row__n { flex:0 0 auto; color:#50575e; font-variant-numeric:tabular-nums; }
			.aeomatic-widget .aeom-footer { display:flex; gap:12px; margin-top:6px; padding-top:8px; border-top:1px solid #e0e0e0; font-size:11px; color:#646970; }
			.aeomatic-widget .aeom-footer a { text-decoration:none; }
			.aeomatic-widget .aeom-empty { font-size:12px; color:#8c8f94; font-style:italic; }
		</style>

		<div class="aeomatic-widget">
			<div class="aeom-tile">
				<p class="aeom-tile__label"><?php esc_html_e( 'Avg AEO Score', 'aeomatic' ); ?></p>
				<div class="aeom-tile__value" style="color:<?php echo esc_attr( $score_color ); ?>;">
					<?php echo esc_html( $score ); ?><span style="font-size:14px; color:#8c8f94; font-weight:400;"> / 100</span>
				</div>
				<p class="aeom-tile__meta">
					<?php
					if ( $scanned_count ) {
						printf(
							/* translators: 1: verbal label (Great/Needs work/At risk), 2: post count */
							esc_html__( '%1$s — across %2$d recent posts', 'aeomatic' ),
							esc_html( $score_label ),
							(int) $scanned_count
						);
					} else {
						esc_html_e( 'No posts scored yet.', 'aeomatic' );
					}
					?>
				</p>
			</div>

			<div class="aeom-tile">
				<p class="aeom-tile__label"><?php esc_html_e( 'Citation Gaps', 'aeomatic' ); ?></p>
				<div class="aeom-tile__value" style="color:<?php echo esc_attr( $posts_with_gap > 0 ? '#ba7517' : '#1d9e75' ); ?>;">
					<?php echo esc_html( $posts_with_gap ); ?><span style="font-size:14px; color:#8c8f94; font-weight:400;"> <?php esc_html_e( 'posts', 'aeomatic' ); ?></span>
				</div>
				<p class="aeom-tile__meta">
					<?php
					if ( $posts_with_gap > 0 ) {
						printf(
							'<a href="%s">%s</a> — %s',
							esc_url( $gaps_url ),
							esc_html__( 'Fix now', 'aeomatic' ),
							sprintf( esc_html__( '%d clean', 'aeomatic' ), $clean_count )
						);
					} else {
						esc_html_e( 'No gaps found — nice.', 'aeomatic' );
					}
					?>
				</p>
			</div>

			<div class="aeom-tile aeom-tile--wide">
				<p class="aeom-tile__label">
					<?php esc_html_e( 'AI Bot Visits (recent)', 'aeomatic' ); ?>
					<span style="float:right; text-transform:none; letter-spacing:0; font-weight:400;">
						<?php printf( esc_html__( 'Total: %d', 'aeomatic' ), (int) $total_visits ); ?>
					</span>
				</p>

				<?php if ( empty( $top_bots ) ) : ?>
					<p class="aeom-empty">
						<?php esc_html_e( 'No AI bot visits logged yet. Once ChatGPT, Claude, Perplexity, or Google-Extended crawls your site, they will appear here.', 'aeomatic' ); ?>
					</p>
				<?php else : ?>
					<?php foreach ( $top_bots as $bot => $count ) :
						$pct = $max_bot ? max( 4, (int) round( ( $count / $max_bot ) * 100 ) ) : 0;
					?>
						<div class="aeom-bar-row">
							<span class="aeom-bar-row__name"><?php echo esc_html( $bot ); ?></span>
							<span class="aeom-bar-row__bar"><span class="aeom-bar-row__fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></span></span>
							<span class="aeom-bar-row__n"><?php echo esc_html( number_format_i18n( (int) $count ) ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="aeomatic-widget">
			<div class="aeom-tile aeom-tile--wide" style="background:#fff;">
				<div class="aeom-footer">
					<span>
						<?php
						printf(
							/* translators: 1: number of retention days, 2: entries kept */
							esc_html__( 'Log retention: %1$d days • %2$d entries kept', 'aeomatic' ),
							(int) $retention_days,
							(int) $kept_entries
						);
						if ( $purged_last > 0 ) {
							echo ' • ';
							printf(
								/* translators: %d: entries purged in last cron pass */
								esc_html__( '%d purged today', 'aeomatic' ),
								(int) $purged_last
							);
						}
						?>
					</span>
					<span style="margin-left:auto;">
						<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Dashboard', 'aeomatic' ); ?></a>
						&nbsp;·&nbsp;
						<a href="<?php echo esc_url( $activity_url ); ?>"><?php esc_html_e( 'Bot log', 'aeomatic' ); ?></a>
						&nbsp;·&nbsp;
						<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Settings', 'aeomatic' ); ?></a>
					</span>
				</div>
			</div>
		</div>
		<?php
	}
}
