<?php
/**
 * Bulk actions on the wp-admin Posts/Pages/CPT list screens.
 *
 * Adds one action per enabled post type: "AEOmatic — refresh audit & gaps".
 * Runs the existing per-post audit and citation-gaps engines against the
 * selected rows, updates cached meta, and shows an admin notice with a
 * compact per-post score table so users can see the outcome without
 * clicking into each post.
 *
 * Everything runs on the user's server — no external calls.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOMATIC_Bulk_Actions {

	const ACTION_SLUG   = 'aeomatic_refresh';
	const QUERY_ARG     = 'aeomatic_bulk_result';
	const TRANSIENT_TTL = 5 * MINUTE_IN_SECONDS;
	// Hard cap so a bulk run never takes down a shared host if the user selects
	// many rows. Anything above this is truncated and reported in the notice.
	const MAX_PER_BATCH = 50;

	public function register(): void {
		$types = AEOMATIC_Post_Types::get_enabled_slugs();
		if ( empty( $types ) ) {
			$types = array( 'post' );
		}

		foreach ( $types as $post_type ) {
			add_filter( 'bulk_actions-edit-' . $post_type, array( $this, 'add_bulk_action' ) );
			add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'handle_bulk_action' ), 10, 3 );
		}

		add_action( 'admin_notices', array( $this, 'maybe_show_notice' ) );
	}

	public function add_bulk_action( array $actions ): array {
		$actions[ self::ACTION_SLUG ] = __( 'AEOmatic — refresh audit & gaps', 'aeomatic' );
		return $actions;
	}

	/**
	 * Process the bulk action. Redirects back to the list with a transient
	 * key that maybe_show_notice() reads on the next request.
	 */
	public function handle_bulk_action( string $redirect_to, string $doaction, array $post_ids ): string {
		if ( self::ACTION_SLUG !== $doaction ) {
			return $redirect_to;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect_to;
		}

		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_values( array_filter( $post_ids ) );

		$truncated = false;
		if ( count( $post_ids ) > self::MAX_PER_BATCH ) {
			$post_ids  = array_slice( $post_ids, 0, self::MAX_PER_BATCH );
			$truncated = true;
		}

		$audit = new AEOMATIC_Audit();
		$gaps  = new AEOMATIC_Citation_Gaps();

		$rows = array();
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$audit_result = $audit->score_post( $post );
			$gaps_result  = $gaps->rebuild( $post_id );

			$rows[] = array(
				'id'         => (int) $post_id,
				'title'      => (string) get_the_title( $post ),
				'edit_url'   => (string) get_edit_post_link( $post_id, 'raw' ),
				'audit'      => (int) ( $audit_result['score'] ?? 0 ),
				'gap_score'  => (int) ( $gaps_result['score'] ?? 0 ),
				'gap_count'  => is_array( $gaps_result['gaps'] ?? null ) ? count( $gaps_result['gaps'] ) : 0,
			);
		}

		// Bust the site-wide audit summary transient so the widget sees fresh numbers.
		delete_transient( 'aeomatic_site_audit_cache' );

		// Stash results in a short-lived transient keyed to the user; the
		// notice on the next request reads and clears it.
		$transient_key = self::transient_key();
		set_transient(
			$transient_key,
			array(
				'rows'      => $rows,
				'truncated' => $truncated,
				'ts'        => time(),
			),
			self::TRANSIENT_TTL
		);

		AEOMATIC_Activity::log(
			'bulk_refresh',
			sprintf(
				/* translators: %d: post count */
				_n( 'Bulk refresh: %d post rescored.', 'Bulk refresh: %d posts rescored.', count( $rows ), 'aeomatic' ),
				count( $rows )
			)
		);

		return add_query_arg( self::QUERY_ARG, '1', $redirect_to );
	}

	/**
	 * Render the results notice after a bulk run.
	 */
	public function maybe_show_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ self::QUERY_ARG ] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$key    = self::transient_key();
		$result = get_transient( $key );
		if ( ! is_array( $result ) || empty( $result['rows'] ) ) {
			return;
		}
		delete_transient( $key );

		$rows      = $result['rows'];
		$truncated = ! empty( $result['truncated'] );
		$count     = count( $rows );

		$avg_audit = $count ? (int) round( array_sum( array_column( $rows, 'audit' ) ) / $count ) : 0;
		$avg_gaps  = $count ? (int) round( array_sum( array_column( $rows, 'gap_score' ) ) / $count ) : 0;
		$total_gap = array_sum( array_column( $rows, 'gap_count' ) );

		$dashboard_url = admin_url( 'admin.php?page=' . AEOMATIC_Admin::MENU_SLUG );
		$gaps_url      = admin_url( 'admin.php?page=' . AEOMATIC_Admin::SLUG_GAPS );

		?>
		<div class="notice notice-success is-dismissible aeomatic-bulk-notice" style="border-left-color:#5B3FFF;">
			<style>
				.aeomatic-bulk-notice details { margin:6px 0 0; }
				.aeomatic-bulk-notice summary { cursor:pointer; color:#5B3FFF; font-weight:500; }
				.aeomatic-bulk-notice table { border-collapse:collapse; margin-top:8px; font-size:12px; width:100%; max-width:720px; }
				.aeomatic-bulk-notice th, .aeomatic-bulk-notice td { padding:5px 8px; border-bottom:1px solid #eee; text-align:left; }
				.aeomatic-bulk-notice th { background:#f6f7f7; font-weight:600; color:#50575e; }
				.aeomatic-bulk-notice .aeom-chip { display:inline-block; padding:1px 8px; border-radius:999px; font-weight:600; font-variant-numeric:tabular-nums; }
				.aeomatic-bulk-notice .aeom-chip--good { background:#e4f5ec; color:#1d9e75; }
				.aeomatic-bulk-notice .aeom-chip--okay { background:#fdf1de; color:#ba7517; }
				.aeomatic-bulk-notice .aeom-chip--poor { background:#fde3e3; color:#a32d2d; }
				.aeomatic-bulk-notice .aeom-stats { display:flex; gap:20px; flex-wrap:wrap; margin:4px 0 2px; }
				.aeomatic-bulk-notice .aeom-stats span { font-size:12px; color:#50575e; }
				.aeomatic-bulk-notice .aeom-stats b { color:#1d2327; }
			</style>

			<p style="margin-bottom:4px;">
				<strong>
					<?php
					printf(
						/* translators: %d: post count */
						esc_html( _n( 'AEOmatic: refreshed %d post.', 'AEOmatic: refreshed %d posts.', $count, 'aeomatic' ) ),
						(int) $count
					);
					?>
				</strong>
			</p>

			<div class="aeom-stats">
				<span><?php esc_html_e( 'Avg audit score:', 'aeomatic' ); ?> <b><?php echo esc_html( $avg_audit ); ?>/100</b></span>
				<span><?php esc_html_e( 'Avg citation-gap score:', 'aeomatic' ); ?> <b><?php echo esc_html( $avg_gaps ); ?>/100</b></span>
				<span><?php esc_html_e( 'Total gaps flagged:', 'aeomatic' ); ?> <b><?php echo esc_html( $total_gap ); ?></b></span>
				<span>
					<a href="<?php echo esc_url( $gaps_url ); ?>"><?php esc_html_e( 'Open Citation Gaps', 'aeomatic' ); ?></a>
					·
					<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Open dashboard', 'aeomatic' ); ?></a>
				</span>
			</div>

			<?php if ( $truncated ) : ?>
				<p style="color:#a32d2d; font-size:12px; margin:6px 0 0;">
					<?php
					printf(
						/* translators: %d: cap size */
						esc_html__( 'Only the first %d posts in this batch were processed (safety cap). Select the remaining rows and run the bulk action again.', 'aeomatic' ),
						(int) self::MAX_PER_BATCH
					);
					?>
				</p>
			<?php endif; ?>

			<details>
				<summary><?php esc_html_e( 'Show per-post scores', 'aeomatic' ); ?></summary>
				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post', 'aeomatic' ); ?></th>
							<th style="width:100px;"><?php esc_html_e( 'Audit', 'aeomatic' ); ?></th>
							<th style="width:100px;"><?php esc_html_e( 'Gaps score', 'aeomatic' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Gaps', 'aeomatic' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) :
						$audit_class = $row['audit'] >= 70 ? 'aeom-chip--good' : ( $row['audit'] >= 40 ? 'aeom-chip--okay' : 'aeom-chip--poor' );
						$gap_class   = $row['gap_score'] >= 70 ? 'aeom-chip--good' : ( $row['gap_score'] >= 40 ? 'aeom-chip--okay' : 'aeom-chip--poor' );
					?>
						<tr>
							<td>
								<?php if ( $row['edit_url'] ) : ?>
									<a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $row['title'] ); ?>
								<?php endif; ?>
							</td>
							<td><span class="aeom-chip <?php echo esc_attr( $audit_class ); ?>"><?php echo esc_html( $row['audit'] ); ?></span></td>
							<td><span class="aeom-chip <?php echo esc_attr( $gap_class ); ?>"><?php echo esc_html( $row['gap_score'] ); ?></span></td>
							<td><?php echo esc_html( $row['gap_count'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		</div>
		<?php
	}

	/**
	 * Per-user transient key so two admins running bulk actions at once don't
	 * clobber each other's results.
	 */
	private static function transient_key(): string {
		return 'aeomatic_bulk_result_' . get_current_user_id();
	}
}
