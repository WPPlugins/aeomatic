<?php
/**
 * Admin dashboard view — AI Readiness.
 *
 * Deliberately titled "AI Readiness" and not "AI Visibility". Everything on
 * this page is derived from this WordPress install: settings, content, and the
 * site's own robots.txt configuration. None of it measures whether AI systems
 * actually surface or cite the site — that needs data from the engines
 * themselves, which the plugin does not collect. The copy says so plainly so
 * the number is never mistaken for observed visibility.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aeomatic_readiness = AEOMATIC_Site_Readiness::summary();
$aeomatic_crawlers  = AEOMATIC_Crawler_Log::get_breakdown();
$aeomatic_settings_url = admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS );

/**
 * Bucket a 0-100 score into the plugin's three-band scale.
 */
$aeomatic_band = static function ( $score ): string {
	if ( null === $score ) {
		return 'none';
	}
	if ( $score >= 70 ) {
		return 'good';
	}
	return $score >= 40 ? 'okay' : 'poor';
};
?>
<div class="wrap aeob-wrap">
	<h1><?php esc_html_e( 'AI Readiness', 'aeomatic' ); ?></h1>
	<p class="aeob-tagline">
		<?php esc_html_e( 'What AI crawlers can reach, parse and quote on this site — scored from your own configuration and content, not guessed at.', 'aeomatic' ); ?>
	</p>

	<div class="notice notice-info aeob-cta-notice" style="padding:12px 16px;">
		<p style="margin:0;">
			<strong><?php esc_html_e( 'Readiness is not visibility.', 'aeomatic' ); ?></strong>
			<?php esc_html_e( 'These scores describe technical and content signals on this install. A well-prepared site is still not guaranteed to appear or be cited in ChatGPT, Gemini, Perplexity or Google AI Overviews — measuring that requires querying those engines, which AEOmatic does not do.', 'aeomatic' ); ?>
		</p>
	</div>

	<div class="aeob-card">
		<h2><?php esc_html_e( 'Overall AI readiness', 'aeomatic' ); ?></h2>
		<div class="aeob-score-big score-<?php echo esc_attr( $aeomatic_band( $aeomatic_readiness['overall'] ) ); ?>">
			<?php echo (int) $aeomatic_readiness['overall']; ?> / 100
		</div>
		<div class="aeob-distribution">
			<span class="aeob-pill <?php echo $aeomatic_readiness['issue_count'] > 0 ? 'aeob-pill--warn' : 'aeob-pill--ok'; ?>">
				<?php
				printf(
					/* translators: %d: number of failing checks */
					esc_html( _n( '%d issue', '%d issues', (int) $aeomatic_readiness['issue_count'], 'aeomatic' ) ),
					(int) $aeomatic_readiness['issue_count']
				);
				?>
			</span>
			<span class="aeob-pill aeob-pill--muted">
				<?php
				printf(
					/* translators: %d: number of recommendations */
					esc_html( _n( '%d recommendation', '%d recommendations', count( $aeomatic_readiness['recommendations'] ), 'aeomatic' ) ),
					count( $aeomatic_readiness['recommendations'] )
				);
				?>
			</span>
		</div>
		<p class="description">
			<?php esc_html_e( 'Unweighted mean of the categories below. Categories with no data are excluded rather than counted as zero.', 'aeomatic' ); ?>
		</p>
	</div>

	<?php do_action( 'aeomatic_dashboard_after_score', $aeomatic_readiness ); ?>

	<div class="aeob-grid aeob-grid--3">
		<?php foreach ( $aeomatic_readiness['categories'] as $aeomatic_category ) : ?>
			<div class="aeob-card">
				<h2>
					<?php echo esc_html( $aeomatic_category['label'] ); ?>
					<?php if ( 'heuristic' === $aeomatic_category['kind'] ) : ?>
						<span class="aeob-pill aeob-pill--warn" style="font-size:11px;vertical-align:middle;">
							<?php esc_html_e( 'Heuristic', 'aeomatic' ); ?>
						</span>
					<?php else : ?>
						<span class="aeob-pill aeob-pill--ok" style="font-size:11px;vertical-align:middle;">
							<?php esc_html_e( 'Direct check', 'aeomatic' ); ?>
						</span>
					<?php endif; ?>
				</h2>

				<div class="aeob-score-big score-<?php echo esc_attr( $aeomatic_band( $aeomatic_category['score'] ) ); ?>" style="font-size:34px;">
					<?php if ( null === $aeomatic_category['score'] ) : ?>
						<span class="aeob-skeleton"><?php esc_html_e( 'No data', 'aeomatic' ); ?></span>
					<?php else : ?>
						<?php echo (int) $aeomatic_category['score']; ?> / 100
					<?php endif; ?>
				</div>

				<p class="description"><?php echo esc_html( $aeomatic_category['method'] ); ?></p>

				<?php if ( ! empty( $aeomatic_category['checks'] ) ) : ?>
					<ul class="aeob-status-list">
						<?php foreach ( $aeomatic_category['checks'] as $aeomatic_check ) : ?>
							<li>
								<span class="aeob-pill <?php echo ! empty( $aeomatic_check['passed'] ) ? 'aeob-pill--ok' : 'aeob-pill--err'; ?>">
									<?php echo ! empty( $aeomatic_check['passed'] ) ? '&#10003;' : '!'; ?>
								</span>
								<?php echo esc_html( $aeomatic_check['label'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="aeob-card">
		<h2><?php esc_html_e( 'Top recommendations', 'aeomatic' ); ?></h2>
		<?php if ( empty( $aeomatic_readiness['recommendations'] ) ) : ?>
			<p><?php esc_html_e( 'Nothing outstanding — every check in every category is passing.', 'aeomatic' ); ?></p>
		<?php else : ?>
			<ol class="aeob-recommendations">
				<?php foreach ( $aeomatic_readiness['recommendations'] as $aeomatic_rec ) : ?>
					<li style="margin-bottom:12px;">
						<strong><?php echo esc_html( $aeomatic_rec['title'] ); ?></strong><br />
						<span class="description"><?php echo esc_html( $aeomatic_rec['detail'] ); ?></span>
						<?php if ( ! empty( $aeomatic_rec['url'] ) ) : ?>
							<br />
							<a href="<?php echo esc_url( $aeomatic_rec['url'] ); ?>" class="button button-small" style="margin-top:6px;">
								<?php esc_html_e( 'Fix this', 'aeomatic' ); ?>
							</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_READINESS ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Improve my site', 'aeomatic' ); ?>
			</a>
			<a href="<?php echo esc_url( $aeomatic_settings_url ); ?>" class="button">
				<?php esc_html_e( 'Configure settings', 'aeomatic' ); ?>
			</a>
		</p>
	</div>

	<?php do_action( 'aeomatic_dashboard_after_recommendations', $aeomatic_readiness ); ?>

	<div class="aeob-card">
		<h2><?php esc_html_e( 'AI crawler activity', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Recognised AI crawlers that requested a page on this site, from your own server-side log. A crawler fetching a page does NOT mean the page was cited in an AI answer — no AI engine publishes citation data through crawler traffic.', 'aeomatic' ); ?>
		</p>
		<?php if ( empty( $aeomatic_crawlers ) ) : ?>
			<p><?php esc_html_e( 'No AI crawler visits logged yet. Entries appear here once a recognised crawler requests a page.', 'aeomatic' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Crawler', 'aeomatic' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'Visits', 'aeomatic' ); ?></th>
						<th style="width:120px;"><?php esc_html_e( 'Errors served', 'aeomatic' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Last seen', 'aeomatic' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $aeomatic_crawlers, 0, 10 ) as $aeomatic_row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $aeomatic_row['bot'] ); ?></code></td>
							<td><?php echo (int) $aeomatic_row['visits']; ?></td>
							<td>
								<?php if ( $aeomatic_row['errors'] > 0 ) : ?>
									<span class="aeob-pill aeob-pill--err"><?php echo (int) $aeomatic_row['errors']; ?></span>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<?php
								echo $aeomatic_row['last_seen']
									? esc_html(
										sprintf(
											/* translators: %s: human-readable time difference */
											__( '%s ago', 'aeomatic' ),
											human_time_diff( (int) $aeomatic_row['last_seen'] )
										)
									)
									: '&mdash;';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="margin-top:8px;">
				<?php
				printf(
					/* translators: %d: log retention window in days */
					esc_html__( 'Log keeps up to 500 entries, purged after %d days. No IP addresses are recorded.', 'aeomatic' ),
					(int) AEOMATIC_Crawler_Log::get_retention_days()
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<div class="aeob-card">
		<h2><?php esc_html_e( 'Per-post content scores', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Lowest-scoring published posts on the per-post AEO content rubric. Open one to see specific improvements in the AEOmatic editor sidebar.', 'aeomatic' ); ?>
		</p>
		<div id="aeob-site-score" class="aeob-score-big" style="font-size:24px;">
			<span class="aeob-skeleton"><?php esc_html_e( 'Loading…', 'aeomatic' ); ?></span>
		</div>
		<div id="aeob-distribution" class="aeob-distribution"></div>
		<table id="aeob-worst-posts" class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'aeomatic' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Score', 'aeomatic' ); ?></th>
					<th style="width:120px;"></th>
				</tr>
			</thead>
			<tbody>
				<tr><td colspan="3"><?php esc_html_e( 'Loading…', 'aeomatic' ); ?></td></tr>
			</tbody>
		</table>
	</div>

	<?php do_action( 'aeomatic_dashboard_end', $aeomatic_readiness ); ?>
</div>
