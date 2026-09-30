<?php
/**
 * AI Access — admin view with two tabs: Crawler check and What AI sees.
 *
 * Server-rendered shell; assets/js/ai-access.js talks to the
 * /aeomatic/v1/ai-access/* endpoints.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
$aeomatic_tab = isset( $_GET['tab'] ) && 'view' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'view' : 'crawlers';
$aeomatic_base = admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_ACCESS );

$aeomatic_types = AEOMATIC_Post_Types::get_enabled_slugs();
if ( empty( $aeomatic_types ) ) {
	$aeomatic_types = array( 'post', 'page' );
}
$aeomatic_posts = get_posts( array(
	'post_type'      => $aeomatic_types,
	'post_status'    => 'publish',
	'posts_per_page' => 100,
	'orderby'        => 'modified',
	'order'          => 'DESC',
	'has_password'   => false,
) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preselect.
$aeomatic_selected = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
?>
<div class="wrap aeob-wrap aeob-access">
	<h1><?php esc_html_e( 'AI Access', 'aeomatic' ); ?></h1>
	<p class="aeob-tagline">
		<?php esc_html_e( 'Can AI crawlers actually reach your pages, and what text do they get? These checks request your own pages the way each crawler does.', 'aeomatic' ); ?>
	</p>

	<nav class="nav-tab-wrapper aeob-access-tabs">
		<a href="<?php echo esc_url( $aeomatic_base ); ?>" class="nav-tab <?php echo 'crawlers' === $aeomatic_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Crawler check', 'aeomatic' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'view', $aeomatic_base ) ); ?>" class="nav-tab <?php echo 'view' === $aeomatic_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'What AI sees', 'aeomatic' ); ?></a>
	</nav>

	<?php if ( 'crawlers' === $aeomatic_tab ) : ?>

		<div class="aeob-card aeob-access-intro">
			<p>
				<?php esc_html_e( 'For each crawler this check reads the robots.txt your site is really serving, then requests your home page and two recent pages with that crawler\'s user agent and compares the answer with a normal browser. A firewall, CDN or security plugin that treats AI crawlers differently shows up here.', 'aeomatic' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'The requests come from your own server. A firewall that checks a crawler\'s real IP address can block this test while letting the real crawler in; where the AI crawler log shows successful real visits, the result says so.', 'aeomatic' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="aeob-access-run"><?php esc_html_e( 'Run crawler check', 'aeomatic' ); ?></button>
				<span class="aeob-access-progress" id="aeob-access-progress" aria-live="polite"></span>
			</p>
		</div>

		<div id="aeob-access-notices"></div>
		<div id="aeob-access-summary"></div>
		<div id="aeob-access-results"><p class="description"><?php esc_html_e( 'Loading the last results…', 'aeomatic' ); ?></p></div>
		<div id="aeob-access-robots"></div>

	<?php else : ?>

		<div class="aeob-card aeob-access-intro">
			<p>
				<?php esc_html_e( 'ChatGPT, Claude and Perplexity crawlers read the HTML your server sends and do not run JavaScript. This shows the text they get for a page and flags content that only appears after JavaScript runs.', 'aeomatic' ); ?>
			</p>
			<p>
				<label for="aeob-view-post" class="screen-reader-text"><?php esc_html_e( 'Page to check', 'aeomatic' ); ?></label>
				<select id="aeob-view-post" class="aeob-access-select">
					<?php foreach ( $aeomatic_posts as $aeomatic_p ) : ?>
						<option value="<?php echo esc_attr( (string) $aeomatic_p->ID ); ?>" <?php selected( $aeomatic_selected, (int) $aeomatic_p->ID ); ?>>
							<?php echo esc_html( get_the_title( $aeomatic_p ) . ' — ' . get_post_type_object( $aeomatic_p->post_type )->labels->singular_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button button-primary" id="aeob-view-run"><?php esc_html_e( 'Show what AI sees', 'aeomatic' ); ?></button>
			</p>
			<?php if ( empty( $aeomatic_posts ) ) : ?>
				<p class="description"><?php esc_html_e( 'Publish a post or page first. Crawlers only see published content.', 'aeomatic' ); ?></p>
			<?php endif; ?>
		</div>

		<div id="aeob-view-results"></div>

	<?php endif; ?>
</div>
