<?php
/**
 * Activity & verification view.
 *
 * Shows the user concrete proof of what AEOmatic has done since installation:
 * live verification of llms.txt and schema, counters of work done,
 * and a chronological activity log.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aeomatic_stats         = AEOMATIC_Activity::get_stats();
$aeomatic_log           = AEOMATIC_Activity::get_log();
$aeomatic_installed_at  = (int) AEOMATIC_Settings::get( '_meta', 'installed_at' );
$aeomatic_days_active   = $aeomatic_installed_at ? max( 1, (int) floor( ( time() - $aeomatic_installed_at ) / DAY_IN_SECONDS ) ) : 1;
?>
<div class="wrap aeob-wrap">
	<h1><?php esc_html_e( 'What did AEOmatic do?', 'aeomatic' ); ?></h1>
	<p class="aeob-tagline">
		<?php esc_html_e( 'Live proof of every change AEOmatic has made to your site. Every link below opens the actual file or page so you can verify it yourself.', 'aeomatic' ); ?>
	</p>

	<div class="aeob-grid aeob-grid--4">
		<div class="aeob-stat">
			<div class="aeob-stat__num"><?php echo esc_html( number_format_i18n( $aeomatic_stats['schema_renders'] ) ); ?></div>
			<div class="aeob-stat__label"><?php esc_html_e( 'Schema blocks rendered', 'aeomatic' ); ?></div>
			<div class="aeob-stat__hint">
				<?php esc_html_e( 'Each visit to a post triggers schema injection. AI engines see this structured data when they crawl.', 'aeomatic' ); ?>
			</div>
		</div>
		<div class="aeob-stat">
			<div class="aeob-stat__num"><?php echo esc_html( number_format_i18n( $aeomatic_stats['audits_run'] ) ); ?></div>
			<div class="aeob-stat__label"><?php esc_html_e( 'Posts audited', 'aeomatic' ); ?></div>
			<div class="aeob-stat__hint">
				<?php esc_html_e( 'Every saved post is scored automatically. Open any post in the editor to see its breakdown.', 'aeomatic' ); ?>
			</div>
		</div>
		<div class="aeob-stat">
			<div class="aeob-stat__num"><?php echo esc_html( number_format_i18n( $aeomatic_stats['llms_serves'] ) ); ?></div>
			<div class="aeob-stat__label"><?php esc_html_e( 'llms.txt requests served', 'aeomatic' ); ?></div>
			<div class="aeob-stat__hint">
				<?php
				/* translators: %d: days active */
				printf( esc_html__( 'Active for %d day(s). Counters reflect requests since last reset.', 'aeomatic' ), (int) $aeomatic_days_active );
				?>
			</div>
		</div>
		<div class="aeob-stat">
			<div class="aeob-stat__num"><?php echo esc_html( number_format_i18n( $aeomatic_stats['llms_full_serves'] ) ); ?></div>
			<div class="aeob-stat__label"><?php esc_html_e( 'llms-full.txt requests served', 'aeomatic' ); ?></div>
			<div class="aeob-stat__hint">
				<?php esc_html_e( 'Full-content file requests. AI engines fetch this for the complete text of every published page and post.', 'aeomatic' ); ?>
			</div>
		</div>
	</div>

	<div class="aeob-card">
		<div class="aeob-card-header">
			<h2><?php esc_html_e( 'Live verification', 'aeomatic' ); ?></h2>
			<button type="button" class="button" id="aeob-rerun-verify">
				<?php esc_html_e( 'Re-run checks', 'aeomatic' ); ?>
			</button>
		</div>
		<p class="description">
			<?php esc_html_e( 'AEOmatic just made live HTTP requests to your own site to verify everything is working. Click any "View" link to see the actual output yourself.', 'aeomatic' ); ?>
		</p>
		<div id="aeob-verify-results" class="aeob-verify-list">
			<div class="aeob-verify-loading"><?php esc_html_e( 'Running checks…', 'aeomatic' ); ?></div>
		</div>
	</div>

	<div class="aeob-card">
		<h2><?php esc_html_e( 'How to confirm AEOmatic is working — manual checks', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'For complete peace of mind, run these manual checks. They take about 2 minutes total.', 'aeomatic' ); ?>
		</p>
		<ol class="aeob-manual-checks">
			<li>
				<strong><?php esc_html_e( 'View your llms.txt', 'aeomatic' ); ?></strong>
				—
				<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( home_url( '/llms.txt' ) ); ?>
				</a>
				<p><?php esc_html_e( 'You should see a Markdown file listing your site name, key pages, a Pages section, and your posts grouped by type. The header includes a "Full content" link.', 'aeomatic' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'View your llms-full.txt', 'aeomatic' ); ?></strong>
				—
				<a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( home_url( '/llms-full.txt' ) ); ?>
				</a>
				<p><?php esc_html_e( 'You should see one Markdown ## block per published page and post, each with its URL and full extracted text.', 'aeomatic' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'View your robots.txt', 'aeomatic' ); ?></strong>
				—
				<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( home_url( '/robots.txt' ) ); ?>
				</a>
				<p><?php esc_html_e( 'Look for the "AI crawler directives — managed by AEOmatic" section listing each AI bot.', 'aeomatic' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'View page source on any post', 'aeomatic' ); ?></strong>
				<p>
					<?php esc_html_e( 'Right-click any published post → "View Page Source" → search for "application/ld+json". You should see one or more JSON-LD schema blocks.', 'aeomatic' ); ?>
				</p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Validate with Google Rich Results Test', 'aeomatic' ); ?></strong>
				—
				<a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">
					https://search.google.com/test/rich-results
				</a>
				<p><?php esc_html_e( 'Paste any post URL to check that its structured data is valid.', 'aeomatic' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Open a post in the editor', 'aeomatic' ); ?></strong>
				<p><?php esc_html_e( 'Click the chart-line icon in the top-right of the editor. The AEOmatic sidebar opens with a 0–100 score and breakdown.', 'aeomatic' ); ?></p>
			</li>
		</ol>
	</div>

	<div class="aeob-card">
		<div class="aeob-card-header">
			<h2><?php esc_html_e( 'Activity log', 'aeomatic' ); ?></h2>
			<span class="description">
				<?php
				/* translators: %d: log entry count */
				printf( esc_html__( '%d entries shown — most recent first', 'aeomatic' ), count( $aeomatic_log ) );
				?>
			</span>
		</div>
		<?php if ( empty( $aeomatic_log ) ) : ?>
			<p class="description"><?php esc_html_e( 'No activity recorded yet.', 'aeomatic' ); ?></p>
		<?php else : ?>
			<table class="widefat striped aeob-log-table">
				<thead>
					<tr>
						<th style="width:160px;"><?php esc_html_e( 'When', 'aeomatic' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Event', 'aeomatic' ); ?></th>
						<th><?php esc_html_e( 'Description', 'aeomatic' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $aeomatic_log as $aeomatic_entry ) : ?>
						<tr>
							<td>
								<?php
								echo esc_html( human_time_diff( $aeomatic_entry['time'], time() ) );
								echo ' ' . esc_html__( 'ago', 'aeomatic' );
								?>
								<br>
								<small style="color:#888;">
									<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i', $aeomatic_entry['time'] ) ); ?>
								</small>
							</td>
							<td><code><?php echo esc_html( $aeomatic_entry['type'] ); ?></code></td>
							<td><?php echo esc_html( $aeomatic_entry['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="aeob-card">
		<div class="aeob-card-header">
			<h2><?php esc_html_e( 'AI crawler visits', 'aeomatic' ); ?></h2>
			<span class="description">
				<?php esc_html_e( 'Most recent 20 recognised AI bot visits to your site', 'aeomatic' ); ?>
			</span>
		</div>
		<?php
		$aeomatic_bot_summary = AEOMATIC_Crawler_Log::get_summary();
		$aeomatic_bot_recent  = AEOMATIC_Crawler_Log::get_recent( 20 );
		if ( empty( $aeomatic_bot_summary ) ) :
		?>
			<p class="description">
				<?php esc_html_e( 'No AI bot visits recorded yet. Visits are logged the first time a recognised AI crawler hits your site after activation.', 'aeomatic' ); ?>
			</p>
		<?php else : ?>
			<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
				<?php foreach ( $aeomatic_bot_summary as $aeomatic_bot_name => $aeomatic_bot_count ) : ?>
					<span style="background:#f0f0f1;border-radius:4px;padding:4px 10px;font-size:13px;">
						<code><?php echo esc_html( $aeomatic_bot_name ); ?></code>
						&nbsp;<strong><?php echo esc_html( number_format_i18n( $aeomatic_bot_count ) ); ?></strong>
						<?php esc_html_e( 'visit(s)', 'aeomatic' ); ?>
					</span>
				<?php endforeach; ?>
			</div>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:140px;"><?php esc_html_e( 'Bot', 'aeomatic' ); ?></th>
						<th><?php esc_html_e( 'URL visited', 'aeomatic' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'When', 'aeomatic' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $aeomatic_bot_recent as $aeomatic_visit ) : ?>
						<tr>
							<td><code><?php echo esc_html( $aeomatic_visit['bot'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $aeomatic_visit['url'] ?? '' ); ?></td>
							<td>
								<?php
								$aeomatic_visit_time = (int) ( $aeomatic_visit['time'] ?? 0 );
								if ( $aeomatic_visit_time ) {
									echo esc_html( human_time_diff( $aeomatic_visit_time, time() ) ) . ' ';
									esc_html_e( 'ago', 'aeomatic' );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="aeob-card aeob-card--tips">
		<h2><?php esc_html_e( 'What happens next?', 'aeomatic' ); ?></h2>
		<p>
			<?php esc_html_e( 'The changes AEOmatic makes are live on your site straight away. What AI engines do with them happens on their own schedule, outside your site:', 'aeomatic' ); ?>
		</p>
		<ul class="aeob-timeline">
			<li><strong><?php esc_html_e( 'Now:', 'aeomatic' ); ?></strong> <?php esc_html_e( 'Schema, llms.txt and robots.txt directives are live. Verify them above.', 'aeomatic' ); ?></li>
			<li><strong><?php esc_html_e( 'As crawlers return:', 'aeomatic' ); ?></strong> <?php esc_html_e( 'AI crawlers pick up your changes when they next visit. The AI crawler log above shows each visit and the status your site returned.', 'aeomatic' ); ?></li>
			<li><strong><?php esc_html_e( 'Citations:', 'aeomatic' ); ?></strong> <?php esc_html_e( 'Whether an AI engine quotes your pages is decided by that engine, and no plugin can promise it or predict when. Use the Ask AI tab in the editor to check answers yourself.', 'aeomatic' ); ?></li>
		</ul>
	</div>
</div>
