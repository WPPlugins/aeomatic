<?php
/**
 * Admin settings view.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aeomatic_settings = AEOMATIC_Settings::all();
settings_errors( 'aeomatic_messages' );
?>
<div class="wrap aeob-wrap">
	<h1><?php esc_html_e( 'AEOmatic — AI based Answer Engine Optimization — Settings', 'aeomatic' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( AEOMATIC_Admin::NONCE_ACTION ); ?>

		<h2 class="title"><?php esc_html_e( 'Schema', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Choose which JSON-LD schema types to inject. Disable any type already provided by another SEO plugin to avoid duplicates.', 'aeomatic' ); ?>
		</p>

		<?php if ( class_exists( 'AEOMATIC_Conflict_Detector' ) ) : ?>
			<p style="margin:8px 0 16px;padding:10px 14px;background:#f8f9fa;border-radius:6px;border-left:3px solid #e0e0e0;font-size:13px;">
				<strong><?php esc_html_e( 'SEO plugin status:', 'aeomatic' ); ?></strong>
				<?php echo wp_kses_post( AEOMATIC_Conflict_Detector::get_status_html() ); ?>
			</p>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<?php
			$aeomatic_schema_options = array(
				'article'      => __( 'Article', 'aeomatic' ),
				'faq'          => __( 'FAQ (from FAQ blocks)', 'aeomatic' ),
				'howto'        => __( 'HowTo (auto-detected)', 'aeomatic' ),
				'author'       => __( 'Author / Person', 'aeomatic' ),
				'organization' => __( 'Organization (site-wide)', 'aeomatic' ),
				'breadcrumb'   => __( 'BreadcrumbList', 'aeomatic' ),
			);
			foreach ( $aeomatic_schema_options as $aeomatic_key => $aeomatic_label ) :
				$aeomatic_checked = ! empty( $aeomatic_settings['schema'][ $aeomatic_key ] );
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $aeomatic_label ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="aeob[schema][<?php echo esc_attr( $aeomatic_key ); ?>]" value="1" <?php checked( $aeomatic_checked ); ?> />
							<?php esc_html_e( 'Enabled', 'aeomatic' ); ?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>

			<tr>
				<th scope="row"><label for="aeob-org-name"><?php esc_html_e( 'Organization name', 'aeomatic' ); ?></label></th>
				<td>
					<input type="text" id="aeob-org-name" name="aeob[schema][org_name]" value="<?php echo esc_attr( $aeomatic_settings['schema']['org_name'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Defaults to your site title if empty.', 'aeomatic' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aeob-org-logo"><?php esc_html_e( 'Logo URL', 'aeomatic' ); ?></label></th>
				<td>
					<input type="url" id="aeob-org-logo" name="aeob[schema][org_logo]" value="<?php echo esc_attr( $aeomatic_settings['schema']['org_logo'] ); ?>" class="regular-text" placeholder="https://" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aeob-org-sameas"><?php esc_html_e( 'Social profile URLs', 'aeomatic' ); ?></label></th>
				<td>
					<textarea id="aeob-org-sameas" name="aeob[schema][org_sameas]" rows="4" class="large-text code" placeholder="https://twitter.com/yourbrand&#10;https://linkedin.com/company/yourbrand"><?php echo esc_textarea( $aeomatic_settings['schema']['org_sameas'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One URL per line. Used in Organization sameAs.', 'aeomatic' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'llms.txt', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: llms.txt URL */
					__( 'Serves an AI-friendly site index at %s.', 'aeomatic' ),
					'<code>' . esc_html( home_url( '/llms.txt' ) ) . '</code>'
				),
				array( 'code' => array() )
			);
			?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable llms.txt', 'aeomatic' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="aeob[llms_txt][enabled]" value="1" <?php checked( ! empty( $aeomatic_settings['llms_txt']['enabled'] ) ); ?> />
						<?php esc_html_e( 'Serve a virtual /llms.txt endpoint', 'aeomatic' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aeob-llms-desc"><?php esc_html_e( 'Site summary', 'aeomatic' ); ?></label></th>
				<td>
					<textarea id="aeob-llms-desc" name="aeob[llms_txt][description]" rows="3" class="large-text" placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"><?php echo esc_textarea( $aeomatic_settings['llms_txt']['description'] ); ?></textarea>
					<p class="description"><?php esc_html_e( '1–3 sentence summary of what your site is about. Defaults to your tagline.', 'aeomatic' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-include content', 'aeomatic' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="aeob[llms_txt][auto_include]" value="1" <?php checked( ! empty( $aeomatic_settings['llms_txt']['auto_include'] ) ); ?> />
						<?php esc_html_e( 'Include all published posts and pages in llms.txt', 'aeomatic' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Lists every published page and post (grouped by type) in llms.txt, and serves their full text at /llms-full.txt.', 'aeomatic' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aeob-llms-extra"><?php esc_html_e( 'Extra URLs', 'aeomatic' ); ?></label></th>
				<td>
					<textarea id="aeob-llms-extra" name="aeob[llms_txt][extra_urls]" rows="4" class="large-text code"><?php echo esc_textarea( $aeomatic_settings['llms_txt']['extra_urls'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One URL per line. Useful for documentation or pillar pages.', 'aeomatic' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'AI discovery headers', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'AEOmatic automatically adds these HTTP response headers to every public page. No configuration needed — they are always active.', 'aeomatic' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active headers', 'aeomatic' ); ?></th>
				<td>
					<ul style="margin:0;padding:0;list-style:none;font-family:monospace;font-size:13px;line-height:2;">
						<li>X-AI-Crawl: allowed</li>
						<li>X-AI-Citeable: true</li>
						<li>X-Content-License: free-to-cite-with-attribution</li>
						<li>X-AI-Content-Type: human-authored</li>
						<li>X-AI-Speakable: true <em style="font-family:sans-serif;font-size:12px;color:#50575e;"><?php esc_html_e( '(singular pages only)', 'aeomatic' ); ?></em></li>
						<?php if ( AEOMATIC_Settings::get( 'llms_txt', 'enabled' ) ) : ?>
						<li>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: llms.txt URL */
									__( 'Link: &lt;%s&gt;; rel="ai-context"; type="text/plain"', 'aeomatic' ),
									esc_html( home_url( '/llms.txt' ) )
								),
								array()
							);
							?>
						</li>
						<?php endif; ?>
					</ul>
					<p class="description" style="margin-top:8px;">
						<?php esc_html_e( 'These are emerging citation signals recognised by AI search engines. They complement robots.txt directives.', 'aeomatic' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'robots.txt rules', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Extra Disallow rules appended to robots.txt by AEOmatic.', 'aeomatic' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Block PDF files', 'aeomatic' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="aeob[robots][block_pdfs]" value="1" <?php checked( ! empty( $aeomatic_settings['robots']['block_pdfs'] ) ); ?> />
						<?php esc_html_e( 'Prevent crawlers from indexing PDF files (adds Disallow: /*.pdf$)', 'aeomatic' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'AI crawler control', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Choose which AI crawlers can access your site. Directives are added to your robots.txt automatically.', 'aeomatic' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php
			$aeomatic_bots = $aeomatic_settings['crawlers'];
			$aeomatic_bot_descriptions = array(
				'GPTBot'             => __( 'OpenAI training crawler', 'aeomatic' ),
				'ChatGPT-User'       => __( 'OpenAI ChatGPT live browsing', 'aeomatic' ),
				'OAI-SearchBot'      => __( 'OpenAI SearchGPT', 'aeomatic' ),
				'ClaudeBot'          => __( 'Anthropic training crawler', 'aeomatic' ),
				'Claude-User'        => __( 'Anthropic Claude live browsing (user-triggered fetch)', 'aeomatic' ),
				'Claude-SearchBot'   => __( 'Anthropic Claude search crawler', 'aeomatic' ),
				'PerplexityBot'      => __( 'Perplexity training crawler', 'aeomatic' ),
				'Perplexity-User'    => __( 'Perplexity live browsing', 'aeomatic' ),
				'Google-Extended'    => __( 'Google Bard / Gemini / AI Overviews opt-in', 'aeomatic' ),
				'CCBot'              => __( 'Common Crawl (used by many AI training sets)', 'aeomatic' ),
				'Bytespider'         => __( 'ByteDance / TikTok crawler', 'aeomatic' ),
				'Amazonbot'          => __( 'Amazon Alexa / AI', 'aeomatic' ),
				'Applebot-Extended'  => __( 'Apple Intelligence opt-in', 'aeomatic' ),
				'meta-externalagent' => __( 'Meta AI crawler', 'aeomatic' ),
				// Legacy entries — kept for backward compatibility with older saved settings.
				'Claude-Web'         => __( 'Anthropic live browsing (deprecated — use Claude-User)', 'aeomatic' ),
				'anthropic-ai'       => __( 'Legacy Anthropic crawler (deprecated)', 'aeomatic' ),
				'DeepSeekBot'        => __( 'DeepSeek AI crawler', 'aeomatic' ),
				'Cohere-AI'          => __( 'Cohere AI crawler (blocked by default)', 'aeomatic' ),
			);
			foreach ( $aeomatic_bots as $aeomatic_bot => $aeomatic_allowed ) :
				$aeomatic_desc = $aeomatic_bot_descriptions[ $aeomatic_bot ] ?? '';
				?>
				<tr>
					<th scope="row"><code><?php echo esc_html( $aeomatic_bot ); ?></code></th>
					<td>
						<label>
							<input type="checkbox" name="aeob[crawlers][<?php echo esc_attr( $aeomatic_bot ); ?>]" value="1" <?php checked( $aeomatic_allowed ); ?> />
							<?php esc_html_e( 'Allow access', 'aeomatic' ); ?>
						</label>
						<?php if ( $aeomatic_desc ) : ?>
							<p class="description"><?php echo esc_html( $aeomatic_desc ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<h2 class="title"><?php esc_html_e( 'Audit', 'aeomatic' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable audit', 'aeomatic' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="aeob[audit][enabled]" value="1" <?php checked( ! empty( $aeomatic_settings['audit']['enabled'] ) ); ?> />
						<?php esc_html_e( 'Score posts and show breakdown in the editor sidebar', 'aeomatic' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aeob-min-words"><?php esc_html_e( 'Minimum word count', 'aeomatic' ); ?></label></th>
				<td>
					<input type="number" id="aeob-min-words" name="aeob[audit][min_words]" value="<?php echo esc_attr( (int) $aeomatic_settings['audit']['min_words'] ); ?>" min="100" max="5000" step="50" class="small-text" />
					<p class="description"><?php esc_html_e( 'Posts shorter than this lose points.', 'aeomatic' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Content coverage', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Choose which post types AEOmatic — AI based Answer Engine Optimization scans, scores, and adds schema to. Custom post types from your themes and plugins are auto-detected.', 'aeomatic' ); ?>
		</p>
		<?php
		$aeomatic_pt_objects = AEOMATIC_Post_Types::get_public_post_types();
		$aeomatic_pt_map     = AEOMATIC_Post_Types::get_enabled_map();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active post types', 'aeomatic' ); ?></th>
				<td>
					<?php foreach ( $aeomatic_pt_objects as $aeomatic_slug => $aeomatic_obj ) :
						$aeomatic_is_enabled = ! empty( $aeomatic_pt_map[ $aeomatic_slug ] );
						$aeomatic_schema_for = AEOMATIC_Post_Types::detect_schema_type_by_slug( $aeomatic_slug );
					?>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="aeob[post_types][<?php echo esc_attr( $aeomatic_slug ); ?>]" value="1" <?php checked( $aeomatic_is_enabled ); ?> />
							<strong><?php echo esc_html( $aeomatic_obj->labels->name ); ?></strong>
							<code style="margin-left:6px;"><?php echo esc_html( $aeomatic_slug ); ?></code>
							<span style="color:#50575e;font-size:12px;margin-left:6px;">
								<?php
								/* translators: %s: schema type */
								printf( esc_html__( '→ %s schema', 'aeomatic' ), esc_html( $aeomatic_schema_for ) );
								?>
							</span>
						</label>
					<?php endforeach; ?>
					<p class="description" style="margin-top:8px;">
						<?php
						/* translators: %d: count of detected post types */
						printf( esc_html__( '%d public post types detected on this site.', 'aeomatic' ), count( $aeomatic_pt_objects ) );
						?>
						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
							<br><span style="color:#0f6e56;">✓ <?php esc_html_e( 'WooCommerce detected — Product schema with price, availability and ratings will be emitted on product pages.', 'aeomatic' ); ?></span>
						<?php endif; ?>
						<?php if ( function_exists( 'get_field_objects' ) ) : ?>
							<br><span style="color:#0f6e56;">✓ <?php esc_html_e( 'ACF detected — text, textarea, wysiwyg, and repeater fields will be scanned.', 'aeomatic' ); ?></span>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Page builders supported', 'aeomatic' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'AEOmatic — AI based Answer Engine Optimization automatically extracts content from these page builders. No configuration needed — just edit your pages as normal.', 'aeomatic' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-detected', 'aeomatic' ); ?></th>
				<td>
					<ul style="margin:0;list-style:none;">
						<li>✓ Gutenberg (block editor)</li>
						<li>✓ Classic editor</li>
						<li>✓ Elementor</li>
						<li>✓ Divi Builder</li>
						<li>✓ Beaver Builder</li>
						<li>✓ Bricks Builder</li>
						<li>✓ Oxygen Builder</li>
						<li>✓ WPBakery Page Builder</li>
						<li>✓ Advanced Custom Fields (ACF)</li>
					</ul>
					<p class="description" style="margin-top:8px;">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: filter name in code tags */
								__( 'Custom builder? Use the %s filter to inject extracted content.', 'aeomatic' ),
								'<code>aeomatic_extract_extra_html</code>'
							),
							array( 'code' => array() )
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="aeomatic_save" value="1" class="button button-primary">
				<?php esc_html_e( 'Save settings', 'aeomatic' ); ?>
			</button>
		</p>
	</form>
</div>
