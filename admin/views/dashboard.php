<?php
/**
 * Admin dashboard view.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aeomatic_llms_enabled  = AEOMATIC_Settings::get( 'llms_txt', 'enabled' );
$aeomatic_llms_url      = home_url( '/llms.txt' );
$aeomatic_schema_count  = count( array_filter( (array) AEOMATIC_Settings::get( 'schema' ), 'is_bool' ) );
$aeomatic_bots_blocked  = count( array_filter( (array) AEOMATIC_Settings::get( 'crawlers' ), static function ( $v ) { return ! $v; } ) );
?>
<div class="wrap aeob-wrap">
	<h1><?php esc_html_e( 'AEOmatic — AI based Answer Engine Optimization — Dashboard', 'aeomatic' ); ?></h1>
	<p class="aeob-tagline">
		<?php esc_html_e( 'Make your site visible in ChatGPT, Claude, Perplexity and Google AI Overviews.', 'aeomatic' ); ?>
	</p>

	<div class="notice notice-info aeob-cta-notice" style="padding:14px 18px;">
		<p style="margin:0;font-size:14px;">
			<strong><?php esc_html_e( 'New here?', 'aeomatic' ); ?></strong>
			<?php esc_html_e( 'See exactly what AEOmatic — AI based Answer Engine Optimization has done to your site, with click-to-verify proof.', 'aeomatic' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_ACTIVITY ) ); ?>" class="button button-primary" style="margin-left:12px;">
				<?php esc_html_e( 'See what we did →', 'aeomatic' ); ?>
			</a>
		</p>
	</div>

	<div class="aeob-grid">
		<div class="aeob-card">
			<h2><?php esc_html_e( 'Site AEO score', 'aeomatic' ); ?></h2>
			<div id="aeob-site-score" class="aeob-score-big">
				<span class="aeob-skeleton"><?php esc_html_e( 'Loading…', 'aeomatic' ); ?></span>
			</div>
			<div id="aeob-distribution" class="aeob-distribution"></div>
			<p class="description">
				<?php esc_html_e( 'Average score across your most recent 50 published posts.', 'aeomatic' ); ?>
			</p>
		</div>

		<div class="aeob-card">
			<h2><?php esc_html_e( 'Status', 'aeomatic' ); ?></h2>
			<ul class="aeob-status-list">
				<li>
					<strong><?php esc_html_e( 'llms.txt', 'aeomatic' ); ?>:</strong>
					<?php if ( $aeomatic_llms_enabled ) : ?>
						<span class="aeob-pill aeob-pill--ok"><?php esc_html_e( 'Active', 'aeomatic' ); ?></span>
						<a href="<?php echo esc_url( $aeomatic_llms_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $aeomatic_llms_url ); ?></a>
					<?php else : ?>
						<span class="aeob-pill aeob-pill--warn"><?php esc_html_e( 'Disabled', 'aeomatic' ); ?></span>
					<?php endif; ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Schema types active', 'aeomatic' ); ?>:</strong>
					<?php
					$aeomatic_active_schemas = array_filter( array(
						'Article'      => AEOMATIC_Settings::get( 'schema', 'article' ),
						'FAQ'          => AEOMATIC_Settings::get( 'schema', 'faq' ),
						'HowTo'        => AEOMATIC_Settings::get( 'schema', 'howto' ),
						'Author'       => AEOMATIC_Settings::get( 'schema', 'author' ),
						'Organization' => AEOMATIC_Settings::get( 'schema', 'organization' ),
						'Breadcrumb'   => AEOMATIC_Settings::get( 'schema', 'breadcrumb' ),
					) );
					echo esc_html( $aeomatic_active_schemas ? implode( ', ', array_keys( $aeomatic_active_schemas ) ) : __( 'None', 'aeomatic' ) );
					?>
				</li>
				<li>
					<strong><?php esc_html_e( 'AI bots blocked', 'aeomatic' ); ?>:</strong>
					<?php echo (int) $aeomatic_bots_blocked; ?>
				</li>
			</ul>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_SETTINGS ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configure settings', 'aeomatic' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="aeob-card">
		<h2><?php esc_html_e( 'Posts that need attention', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Lowest-scoring posts. Open each one to see specific improvements in the AEOmatic — AI based Answer Engine Optimization sidebar.', 'aeomatic' ); ?>
		</p>
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

	<div class="aeob-card aeob-card--tips">
		<h2><?php esc_html_e( 'Quick tips', 'aeomatic' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Open with a clear, direct answer in the first 1–2 sentences.', 'aeomatic' ); ?></li>
			<li><?php esc_html_e( 'Use question-style H2/H3 headings ("How does X work?").', 'aeomatic' ); ?></li>
			<li><?php esc_html_e( 'Add an AEO FAQ block to every long article.', 'aeomatic' ); ?></li>
			<li><?php esc_html_e( 'Fill in your author bio (30+ characters) — author authority is a key citation signal.', 'aeomatic' ); ?></li>
			<li><?php esc_html_e( 'Use tables for comparisons and lists for steps. AI engines extract structured content first.', 'aeomatic' ); ?></li>
		</ol>
	</div>
</div>
