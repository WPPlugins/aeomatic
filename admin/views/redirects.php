<?php
/**
 * Admin view: URL redirects.
 *
 * Exact-match 301/302 redirects managed as a flat list. No regex, no
 * wildcards, no import — deliberately scoped small.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aeomatic_rows   = AEOMATIC_Redirects::all();
$aeomatic_count  = count( $aeomatic_rows );
$aeomatic_max    = AEOMATIC_Redirects::MAX_ROWS;
$aeomatic_notice = isset( $_GET['aeomatic_redirects_notice'] ) ? sanitize_key( wp_unslash( $_GET['aeomatic_redirects_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$aeomatic_notice_map = array(
	'added'       => array( 'success', __( 'Redirect added.', 'aeomatic' ) ),
	'deleted'     => array( 'success', __( 'Redirect removed.', 'aeomatic' ) ),
	'empty'       => array( 'error',   __( 'Both the source path and the target URL are required.', 'aeomatic' ) ),
	'source_path' => array( 'error',   __( 'The source must be a path on this site starting with /, for example /old-slug.', 'aeomatic' ) ),
	'target_shape'=> array( 'error',   __( 'The target must be either a full URL (https://...) or a path starting with /.', 'aeomatic' ) ),
	'loop'        => array( 'error',   __( 'The target cannot match the source — that would redirect to itself.', 'aeomatic' ) ),
	'max'         => array( 'error',   sprintf( /* translators: %d: row cap */ __( 'The redirect list is full (max %d entries). Remove one before adding another.', 'aeomatic' ), $aeomatic_max ) ),
	'missing'     => array( 'error',   __( 'That redirect no longer exists.', 'aeomatic' ) ),
);
?>
<div class="wrap aeob-wrap">
	<h1><?php esc_html_e( 'URL redirects', 'aeomatic' ); ?></h1>

	<p class="description" style="max-width:720px;">
		<?php esc_html_e( 'When you rename a slug or retire a page, AI crawlers and search engines keep hitting the old URL and get a 404. Add a redirect here and that old path sends visitors — and AI crawlers — to the right place with a 301 or 302 response.', 'aeomatic' ); ?>
	</p>
	<p class="description" style="max-width:720px;">
		<?php esc_html_e( 'If your site uses a full-page cache (W3 Total Cache, LiteSpeed, host-level caching, etc.), flush the cache after adding a redirect to a URL that is already cached — a cached HTML response can be served before WordPress applies the redirect.', 'aeomatic' ); ?>
	</p>

	<?php if ( isset( $aeomatic_notice_map[ $aeomatic_notice ] ) ) :
		list( $aeomatic_kind, $aeomatic_msg ) = $aeomatic_notice_map[ $aeomatic_notice ]; ?>
		<div class="notice notice-<?php echo esc_attr( $aeomatic_kind ); ?> is-dismissible">
			<p><?php echo esc_html( $aeomatic_msg ); ?></p>
		</div>
	<?php endif; ?>

	<div class="aeob-card">
		<h2><?php esc_html_e( 'Add a redirect', 'aeomatic' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'aeomatic_add_redirect' ); ?>
			<input type="hidden" name="action" value="aeomatic_add_redirect">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="aeomatic-redirect-source"><?php esc_html_e( 'Source path', 'aeomatic' ); ?></label>
						</th>
						<td>
							<input type="text" id="aeomatic-redirect-source" name="source" value=""
							       class="regular-text code" placeholder="/old-slug" required>
							<p class="description">
								<?php esc_html_e( 'The path visitors arrive at. Must start with / and be on this site. Trailing slash and query string are ignored at match time.', 'aeomatic' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="aeomatic-redirect-target"><?php esc_html_e( 'Target URL', 'aeomatic' ); ?></label>
						</th>
						<td>
							<input type="text" id="aeomatic-redirect-target" name="target" value=""
							       class="regular-text code" placeholder="/new-slug or https://example.com/new" required>
							<p class="description">
								<?php esc_html_e( 'Where to send them. Either a path on this site starting with /, or a full https:// URL.', 'aeomatic' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="aeomatic-redirect-status"><?php esc_html_e( 'HTTP status', 'aeomatic' ); ?></label>
						</th>
						<td>
							<select id="aeomatic-redirect-status" name="status">
								<option value="301"><?php esc_html_e( '301 — Permanent (recommended for renamed pages)', 'aeomatic' ); ?></option>
								<option value="302"><?php esc_html_e( '302 — Temporary (use for short-lived campaigns)', 'aeomatic' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<p>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Add redirect', 'aeomatic' ); ?>
				</button>
			</p>
		</form>
	</div>

	<div class="aeob-card">
		<h2>
			<?php esc_html_e( 'Active redirects', 'aeomatic' ); ?>
			<span class="aeob-pill aeob-pill--muted" style="font-size:11px;vertical-align:middle;">
				<?php
				printf(
					/* translators: 1: current count, 2: max */
					esc_html__( '%1$d of %2$d used', 'aeomatic' ),
					(int) $aeomatic_count,
					(int) $aeomatic_max
				);
				?>
			</span>
		</h2>

		<?php if ( empty( $aeomatic_rows ) ) : ?>
			<p><?php esc_html_e( 'No redirects yet. Add one above and it takes effect immediately.', 'aeomatic' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'aeomatic' ); ?></th>
						<th><?php esc_html_e( 'Target', 'aeomatic' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Status', 'aeomatic' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Hits', 'aeomatic' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Last hit', 'aeomatic' ); ?></th>
						<th style="width:90px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php
					// Newest first.
					$aeomatic_sorted = $aeomatic_rows;
					usort( $aeomatic_sorted, static function ( $a, $b ) {
						return (int) ( $b['created_at'] ?? 0 ) <=> (int) ( $a['created_at'] ?? 0 );
					} );
					foreach ( $aeomatic_sorted as $aeomatic_row ) :
						$aeomatic_id      = (string) ( $aeomatic_row['id'] ?? '' );
						$aeomatic_src     = (string) ( $aeomatic_row['source'] ?? '' );
						$aeomatic_tgt     = (string) ( $aeomatic_row['target'] ?? '' );
						$aeomatic_status  = (int) ( $aeomatic_row['status'] ?? 301 );
						$aeomatic_hits    = (int) ( $aeomatic_row['hits'] ?? 0 );
						$aeomatic_lasthit = (int) ( $aeomatic_row['last_hit_at'] ?? 0 );
					?>
						<tr>
							<td><code><?php echo esc_html( $aeomatic_src ); ?></code></td>
							<td><code><?php echo esc_html( $aeomatic_tgt ); ?></code></td>
							<td><?php echo (int) $aeomatic_status; ?></td>
							<td><?php echo (int) $aeomatic_hits; ?></td>
							<td>
								<?php
								echo $aeomatic_lasthit
									? esc_html(
										sprintf(
											/* translators: %s: human-readable time difference */
											__( '%s ago', 'aeomatic' ),
											human_time_diff( $aeomatic_lasthit )
										)
									)
									: '&mdash;';
								?>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
								      onsubmit="return confirm('<?php echo esc_js( __( 'Remove this redirect?', 'aeomatic' ) ); ?>');">
									<?php wp_nonce_field( 'aeomatic_delete_redirect' ); ?>
									<input type="hidden" name="action" value="aeomatic_delete_redirect">
									<input type="hidden" name="id" value="<?php echo esc_attr( $aeomatic_id ); ?>">
									<button type="submit" class="button button-link-delete">
										<?php esc_html_e( 'Delete', 'aeomatic' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="margin-top:8px;">
				<?php esc_html_e( 'Matching is by exact path (query string and trailing slash ignored). No regex or wildcards — add one row per path you want to redirect.', 'aeomatic' ); ?>
			</p>
		<?php endif; ?>
	</div>
</div>
