<?php
/**
 * Setup wizard view.
 *
 * Rendered by AEOMATIC_Wizard::render(). Available in scope:
 *   $wizard   — AEOMATIC_Wizard instance (URL + step helpers)
 *   $step     — current step slug
 *   $sources  — array of detected SEO plugins (AEOMATIC_SEO_Importer::inspect())
 *   $schema   — current AEOmatic schema settings
 *   $crawlers — current AEOmatic crawlers map
 *
 * Layout: fixed vertical step rail on the left, form + live preview on the
 * right. Deliberately different from the horizontal step-bar layout used
 * by competing plugins.
 *
 * @package AEOmatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_index = $wizard->step_index( $step );
$total_steps   = count( AEOMATIC_Wizard::STEPS );
$progress_pct  = (int) round( ( $current_index / max( 1, $total_steps - 1 ) ) * 100 );

// Preview data for the right column — computed once and reused per step.
$preview_org_name   = (string) ( $schema['org_name']   ?? '' );
$preview_org_logo   = (string) ( $schema['org_logo']   ?? '' );
$preview_org_sameas = (string) ( $schema['org_sameas'] ?? '' );

$crawler_groups = array(
	'openai'    => array( 'label' => 'OpenAI',    'color' => '#10a37f', 'bots' => array( 'GPTBot', 'ChatGPT-User', 'OAI-SearchBot' ) ),
	'anthropic' => array( 'label' => 'Anthropic', 'color' => '#c88a53', 'bots' => array( 'ClaudeBot', 'Claude-User', 'Claude-SearchBot' ) ),
	'perplexity'=> array( 'label' => 'Perplexity','color' => '#20808d', 'bots' => array( 'PerplexityBot', 'Perplexity-User' ) ),
	'google'    => array( 'label' => 'Google',    'color' => '#4285f4', 'bots' => array( 'Google-Extended' ) ),
	'meta'      => array( 'label' => 'Meta',      'color' => '#0866ff', 'bots' => array( 'meta-externalagent', 'Meta-ExternalFetcher' ) ),
	'others'    => array( 'label' => 'Others',    'color' => '#7c3aed', 'bots' => array( 'Applebot-Extended', 'Amazonbot', 'DeepSeekBot', 'Cohere-AI', 'DuckAssistBot', 'CCBot', 'Bytespider' ) ),
);
?>
<style>
	/* Full-bleed layout: hide the wp-admin sidebar chrome for a focused wizard. */
	#adminmenumain, #adminmenuback, #wpfooter { display:none !important; }
	#wpcontent { margin-left:0 !important; padding-left:0 !important; }
	#wpbody-content { padding-bottom:40px; }

	.aeom-wiz { max-width:1140px; margin:0 auto; padding:30px 24px; display:grid; grid-template-columns:260px 1fr; gap:28px; align-items:flex-start; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
	.aeom-wiz__brand { display:flex; align-items:center; gap:10px; margin-bottom:20px; }
	.aeom-wiz__brand img { width:36px; height:36px; }
	.aeom-wiz__brand span { font-weight:700; font-size:15px; color:#1d2327; }
	.aeom-wiz__progress { height:4px; background:#eee; border-radius:3px; overflow:hidden; margin-bottom:22px; }
	.aeom-wiz__progress span { display:block; height:100%; background:linear-gradient(90deg,#5B3FFF,#A23FCC); transition:width .3s ease; }
	.aeom-wiz__rail ol { list-style:none; padding:0; margin:0; counter-reset:step; }
	.aeom-wiz__rail li { position:relative; padding:10px 12px 10px 42px; margin:0; font-size:13px; color:#8c8f94; border-radius:6px; counter-increment:step; }
	.aeom-wiz__rail li::before { content:counter(step); position:absolute; left:10px; top:9px; width:22px; height:22px; line-height:22px; text-align:center; border-radius:50%; background:#eee; color:#8c8f94; font-weight:600; font-size:12px; }
	.aeom-wiz__rail li.is-done { color:#1d9e75; }
	.aeom-wiz__rail li.is-done::before { content:"✓"; background:#e4f5ec; color:#1d9e75; }
	.aeom-wiz__rail li.is-current { color:#1d2327; font-weight:600; background:#f6f5ff; }
	.aeom-wiz__rail li.is-current::before { background:#5B3FFF; color:#fff; }

	.aeom-wiz__main { background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:28px 32px; box-shadow:0 1px 3px rgba(0,0,0,.03); }
	.aeom-wiz__head { border-bottom:1px solid #eee; padding-bottom:12px; margin-bottom:20px; }
	.aeom-wiz__head h1 { margin:0 0 4px; font-size:22px; color:#1d2327; }
	.aeom-wiz__head p { margin:0; color:#50575e; font-size:14px; }

	.aeom-wiz__body { display:grid; grid-template-columns:1fr; gap:20px; }
	.aeom-wiz__body.has-preview { grid-template-columns:1.35fr 1fr; }
	.aeom-wiz__form label { display:block; font-size:12px; font-weight:600; color:#1d2327; text-transform:uppercase; letter-spacing:.04em; margin:14px 0 6px; }
	.aeom-wiz__form input[type=text], .aeom-wiz__form input[type=url], .aeom-wiz__form textarea { width:100%; padding:8px 10px; border:1px solid #dcdcde; border-radius:5px; font-size:13px; box-sizing:border-box; font-family:inherit; }
	.aeom-wiz__form textarea { resize:vertical; min-height:80px; font-family:ui-monospace,Menlo,Consolas,monospace; }
	.aeom-wiz__hint { font-size:12px; color:#8c8f94; margin:4px 0 0; }

	.aeom-wiz__preview { background:#0f172a; color:#e2e8f0; border-radius:8px; padding:14px 16px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12px; line-height:1.55; max-height:340px; overflow:auto; }
	.aeom-wiz__preview-title { color:#94a3b8; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; font-size:11px; text-transform:uppercase; letter-spacing:.05em; margin:0 0 8px; }
	.aeom-wiz__preview .pk { color:#7dd3fc; }
	.aeom-wiz__preview .ps { color:#fbbf24; }
	.aeom-wiz__preview .pv { color:#a78bfa; }
	.aeom-wiz__preview .pc { color:#64748b; }

	.aeom-wiz__source-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:12px; margin-top:10px; }
	.aeom-wiz__source { border:1px solid #e0e0e0; border-radius:8px; padding:12px 14px; background:#fafafa; }
	.aeom-wiz__source h3 { margin:0 0 6px; font-size:14px; color:#1d2327; display:flex; align-items:center; justify-content:space-between; }
	.aeom-wiz__source .row { font-size:12px; color:#50575e; margin:2px 0; overflow-wrap:anywhere; }
	.aeom-wiz__source .empty { color:#c00; font-style:italic; }
	.aeom-wiz__source label.check { display:flex; align-items:center; gap:6px; margin-top:10px; padding-top:8px; border-top:1px dashed #ddd; cursor:pointer; font-size:12px; color:#1d2327; text-transform:none; letter-spacing:0; font-weight:500; }
	.aeom-wiz__source label.check input { margin:0; }

	.aeom-wiz__crawler-groups { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px; margin-top:6px; }
	.aeom-wiz__crawler-group { border-radius:8px; padding:12px 14px; border:1px solid #e0e0e0; background:#fff; }
	.aeom-wiz__crawler-group h4 { margin:0 0 8px; font-size:13px; display:flex; align-items:center; gap:8px; }
	.aeom-wiz__crawler-group h4 .dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
	.aeom-wiz__crawler-group label { display:flex; align-items:center; gap:6px; margin:4px 0; font-size:12px; text-transform:none; letter-spacing:0; font-weight:400; color:#1d2327; cursor:pointer; }
	.aeom-wiz__crawler-group input { margin:0; }

	.aeom-wiz__verify { display:grid; gap:8px; margin-top:4px; }
	.aeom-wiz__verify-row { display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px solid #e4f5ec; background:#f2faf5; border-radius:6px; font-size:13px; color:#1d2327; }
	.aeom-wiz__verify-row .status { flex:0 0 18px; color:#1d9e75; font-weight:700; }
	.aeom-wiz__verify-row code { background:#e4f5ec; padding:1px 6px; border-radius:3px; font-size:12px; }
	.aeom-wiz__verify-row a { margin-left:auto; font-size:12px; color:#5B3FFF; text-decoration:none; }
	/* Readiness-step rows carry a score, not a pass/fail, so drop the green. */
	.aeom-wiz__verify-row--neutral { border-color:#e0e0e0; background:#f6f7f7; }
	.aeom-wiz__verify-row--neutral .status { flex:0 0 34px; color:#1f4e79; }
	.aeom-wiz__score { margin:8px 0 18px; }
	.aeom-wiz__score .aeob-score-big { font-size:44px; font-weight:700; line-height:1; color:#1f4e79; margin:0; }
	.aeom-wiz__score .aeob-score-big.score-good { color:#1d9e75; }
	.aeom-wiz__score .aeob-score-big.score-okay { color:#ba7517; }
	.aeom-wiz__score .aeob-score-big.score-poor { color:#a32d2d; }
	.aeom-wiz__score .aeob-score-big.score-none { color:#8c8f94; font-weight:500; }
	.aeob-recommendations { margin:12px 0 16px; padding-left:22px; font-size:13px; }

	.aeom-wiz__foot { margin-top:24px; padding-top:16px; border-top:1px solid #eee; display:flex; align-items:center; justify-content:space-between; }
	.aeom-wiz__foot .aeom-wiz__promise { font-size:11px; color:#8c8f94; max-width:420px; }
	.aeom-wiz__foot .buttons { display:flex; gap:8px; align-items:center; }
	.aeom-wiz__foot .button-primary { background:#5B3FFF; border-color:#5B3FFF; }
	.aeom-wiz__foot .button-primary:hover { background:#4a30d4; border-color:#4a30d4; }

	.aeom-wiz__skip { color:#8c8f94; font-size:12px; text-decoration:none; }
	.aeom-wiz__skip:hover { color:#5B3FFF; }
</style>

<div class="aeom-wiz">
	<aside class="aeom-wiz__rail">
		<div class="aeom-wiz__brand">
			<img src="<?php echo esc_url( AEOMATIC_URL . 'assets/images/logo.png' ); ?>" alt="AEOmatic">
			<span><?php esc_html_e( 'AEOmatic Setup', 'aeomatic' ); ?></span>
		</div>
		<div class="aeom-wiz__progress" aria-hidden="true"><span style="width:<?php echo esc_attr( $progress_pct ); ?>%;"></span></div>
		<ol>
			<?php foreach ( AEOMATIC_Wizard::STEPS as $i => $s ) :
				$state = $i < $current_index ? 'is-done' : ( $i === $current_index ? 'is-current' : '' );
			?>
				<li class="<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $wizard->step_label( $s ) ); ?></li>
			<?php endforeach; ?>
		</ol>
		<p style="margin-top:24px; font-size:11px; color:#8c8f94; line-height:1.5;">
			<?php esc_html_e( 'Everything on this page runs on your own server. No accounts, no external calls, no data leaves your site.', 'aeomatic' ); ?>
		</p>
	</aside>

	<main class="aeom-wiz__main">
		<form method="post" action="">
			<?php wp_nonce_field( AEOMATIC_Wizard::NONCE_ACTION ); ?>
			<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>">

			<?php if ( 'welcome' === $step ) : ?>
				<div class="aeom-wiz__head">
					<h1><?php esc_html_e( 'Let\'s make your site readable to AI search.', 'aeomatic' ); ?></h1>
					<p><?php esc_html_e( 'This takes about a minute. We\'ll import what you already have, fill in the gaps, and prove it works.', 'aeomatic' ); ?></p>
				</div>
				<p style="margin:0 0 16px;">
					<a class="button" href="https://www.youtube.com/watch?v=8MZICDVxbFc" target="_blank" rel="noopener noreferrer">
						&#9654; <?php esc_html_e( 'Watch the intro video', 'aeomatic' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( '(opens YouTube in a new tab)', 'aeomatic' ); ?></span>
					</a>
				</p>
				<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:8px;">
					<?php
					$bullets = array(
						__( 'Auto-import your organisation name, logo and social profiles from Yoast, Rank Math, SEOPress, AIOSEO, or The SEO Framework.', 'aeomatic' ),
						__( 'Set sensible defaults for 18 AI crawlers from OpenAI, Anthropic, Perplexity, Google, Meta and others.', 'aeomatic' ),
						__( 'Turn on /llms.txt and /llms-full.txt so AI engines can ingest your whole site in one fetch.', 'aeomatic' ),
						__( 'Send optional AI discovery headers that point crawlers to your llms.txt.', 'aeomatic' ),
					);
					foreach ( $bullets as $b ) : ?>
						<div style="padding:12px 14px; background:#f6f5ff; border-left:3px solid #5B3FFF; border-radius:4px; font-size:13px; color:#1d2327;">✓ <?php echo esc_html( $b ); ?></div>
					<?php endforeach; ?>
				</div>

			<?php elseif ( 'import' === $step ) : ?>
				<div class="aeom-wiz__head">
					<h1><?php esc_html_e( 'Import from your existing SEO plugin', 'aeomatic' ); ?></h1>
					<p><?php esc_html_e( 'We found the following settings on your site. Tick the ones you want to reuse — AEOmatic will never overwrite anything you\'ve already set here.', 'aeomatic' ); ?></p>
				</div>

				<?php if ( empty( $sources ) ) : ?>
					<p class="aeom-wiz__hint" style="margin-top:20px; font-size:13px;">
						<?php esc_html_e( 'No compatible SEO plugin found (or no organisation details configured in it). No problem — you can enter your details in the next step.', 'aeomatic' ); ?>
					</p>
				<?php else : ?>
					<div class="aeom-wiz__source-grid">
						<?php foreach ( $sources as $slug => $src ) : ?>
							<div class="aeom-wiz__source">
								<h3>
									<span><?php echo esc_html( $src['label'] ); ?></span>
									<span style="font-size:10px; text-transform:uppercase; color:#5B3FFF; letter-spacing:.05em;"><?php esc_html_e( 'Found', 'aeomatic' ); ?></span>
								</h3>
								<p class="row"><strong><?php esc_html_e( 'Name:', 'aeomatic' ); ?></strong>
									<?php echo $src['org_name'] ? esc_html( $src['org_name'] ) : '<span class="empty">' . esc_html__( '(none)', 'aeomatic' ) . '</span>'; ?>
								</p>
								<p class="row"><strong><?php esc_html_e( 'Logo:', 'aeomatic' ); ?></strong>
									<?php echo $src['org_logo'] ? esc_html( wp_parse_url( $src['org_logo'], PHP_URL_PATH ) ?: $src['org_logo'] ) : '<span class="empty">' . esc_html__( '(none)', 'aeomatic' ) . '</span>'; ?>
								</p>
								<p class="row"><strong><?php esc_html_e( 'Social profiles:', 'aeomatic' ); ?></strong>
									<?php echo count( $src['org_sameas'] ) ? (int) count( $src['org_sameas'] ) . ' ' . esc_html__( 'URL(s)', 'aeomatic' ) : '<span class="empty">' . esc_html__( '(none)', 'aeomatic' ) . '</span>'; ?>
								</p>
								<label class="check">
									<input type="checkbox" name="import_sources[]" value="<?php echo esc_attr( $slug ); ?>" checked>
									<?php
									printf(
										/* translators: %s: source plugin label */
										esc_html__( 'Import from %s', 'aeomatic' ),
										esc_html( $src['label'] )
									);
									?>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			<?php elseif ( 'org' === $step ) : ?>
				<div class="aeom-wiz__head">
					<h1><?php esc_html_e( 'Your organisation', 'aeomatic' ); ?></h1>
					<p><?php esc_html_e( 'AI engines cite named sources. These details go into your Organization schema, /llms.txt, and speakable metadata.', 'aeomatic' ); ?></p>
				</div>

				<div class="aeom-wiz__body has-preview">
					<div class="aeom-wiz__form">
						<label for="wiz_org_name"><?php esc_html_e( 'Organisation name', 'aeomatic' ); ?></label>
						<input id="wiz_org_name" type="text" name="org_name" value="<?php echo esc_attr( $preview_org_name ); ?>" placeholder="Acme Widgets Ltd" data-preview-target="name">

						<label for="wiz_org_logo"><?php esc_html_e( 'Logo URL', 'aeomatic' ); ?></label>
						<input id="wiz_org_logo" type="url" name="org_logo" value="<?php echo esc_attr( $preview_org_logo ); ?>" placeholder="https://example.com/logo.png" data-preview-target="logo">

						<label for="wiz_org_sameas"><?php esc_html_e( 'Social profiles (one URL per line)', 'aeomatic' ); ?></label>
						<textarea id="wiz_org_sameas" name="org_sameas" rows="4" data-preview-target="sameas" placeholder="https://twitter.com/acme&#10;https://linkedin.com/company/acme"><?php echo esc_textarea( $preview_org_sameas ); ?></textarea>
						<p class="aeom-wiz__hint"><?php esc_html_e( 'These become the sameAs array on your Organization JSON-LD.', 'aeomatic' ); ?></p>
					</div>

					<div>
						<p class="aeom-wiz__preview-title"><?php esc_html_e( 'Live JSON-LD preview', 'aeomatic' ); ?></p>
						<pre class="aeom-wiz__preview" id="wiz-json-preview"></pre>
					</div>
				</div>

				<script>
				(function(){
					const nameEl   = document.getElementById('wiz_org_name');
					const logoEl   = document.getElementById('wiz_org_logo');
					const sameasEl = document.getElementById('wiz_org_sameas');
					const previewEl = document.getElementById('wiz-json-preview');

					function esc(s){ return String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
					function q(s){ return '<span class="ps">"' + esc(s) + '"</span>'; }
					function k(s){ return '<span class="pk">"' + esc(s) + '"</span>'; }

					function render() {
						const name = nameEl.value.trim() || 'Your Organisation';
						const logo = logoEl.value.trim();
						const sameas = sameasEl.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean);

						const lines = [];
						lines.push('{');
						lines.push('  ' + k('@context') + ': ' + q('https://schema.org') + ',');
						lines.push('  ' + k('@type')    + ': ' + q('Organization') + ',');
						lines.push('  ' + k('name')     + ': ' + q(name) + (logo || sameas.length ? ',' : ''));
						if (logo) {
							lines.push('  ' + k('logo') + ': ' + q(logo) + (sameas.length ? ',' : ''));
						}
						if (sameas.length) {
							lines.push('  ' + k('sameAs') + ': [');
							sameas.forEach((u, i) => {
								lines.push('    ' + q(u) + (i < sameas.length - 1 ? ',' : ''));
							});
							lines.push('  ]');
						}
						lines.push('}');
						previewEl.innerHTML = lines.join('\n');
					}
					[nameEl, logoEl, sameasEl].forEach(el => el.addEventListener('input', render));
					render();
				})();
				</script>

			<?php elseif ( 'crawlers' === $step ) : ?>
				<div class="aeom-wiz__head">
					<h1><?php esc_html_e( 'AI crawlers', 'aeomatic' ); ?></h1>
					<p><?php esc_html_e( 'Choose which AI engines are allowed to read your content. Anything you turn off here becomes a Disallow line in your robots.txt.', 'aeomatic' ); ?></p>
				</div>

				<div class="aeom-wiz__crawler-groups">
					<?php foreach ( $crawler_groups as $group ) : ?>
						<div class="aeom-wiz__crawler-group">
							<h4>
								<span class="dot" style="background:<?php echo esc_attr( $group['color'] ); ?>;"></span>
								<?php echo esc_html( $group['label'] ); ?>
							</h4>
							<?php foreach ( $group['bots'] as $bot ) :
								if ( ! array_key_exists( $bot, (array) $crawlers ) ) {
									continue;
								}
								$checked = ! empty( $crawlers[ $bot ] );
							?>
								<label>
									<input type="checkbox" name="crawlers[<?php echo esc_attr( $bot ); ?>]" value="1" <?php checked( $checked ); ?>>
									<span><?php echo esc_html( $bot ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="aeom-wiz__hint" style="margin-top:14px;">
					<?php esc_html_e( 'Recommended: leave all groups on. Blocking a crawler removes your site from that engine\'s AI answers.', 'aeomatic' ); ?>
				</p>

			<?php elseif ( 'readiness' === $step ) : ?>
				<div class="aeom-wiz__head">
					<h1><?php esc_html_e( 'Your first AI readiness audit', 'aeomatic' ); ?></h1>
					<p><?php esc_html_e( 'Scored just now from your settings and your published content. Readiness measures how easy your site is for AI systems to parse and quote — not whether they actually cite you.', 'aeomatic' ); ?></p>
				</div>

				<?php
				$aeomatic_wiz_band = static function ( $score ): string {
					if ( null === $score ) {
						return 'none';
					}
					if ( $score >= 70 ) {
						return 'good';
					}
					return $score >= 40 ? 'okay' : 'poor';
				};
				?>

				<div class="aeom-wiz__score">
					<div class="aeob-score-big score-<?php echo esc_attr( $aeomatic_wiz_band( $readiness['overall'] ) ); ?>">
						<?php echo (int) $readiness['overall']; ?> / 100
					</div>
					<p class="aeom-wiz__hint">
						<?php
						printf(
							/* translators: %d: number of failing checks */
							esc_html( _n( '%d issue found.', '%d issues found.', (int) $readiness['issue_count'], 'aeomatic' ) ),
							(int) $readiness['issue_count']
						);
						?>
					</p>
				</div>

				<div class="aeom-wiz__verify">
					<?php foreach ( $readiness['categories'] as $aeomatic_wiz_cat ) : ?>
						<div class="aeom-wiz__verify-row aeom-wiz__verify-row--neutral">
							<span class="status"><?php echo null === $aeomatic_wiz_cat['score'] ? '&mdash;' : (int) $aeomatic_wiz_cat['score']; ?></span>
							<span>
								<?php echo esc_html( $aeomatic_wiz_cat['label'] ); ?>
								<?php if ( 'heuristic' === $aeomatic_wiz_cat['kind'] ) : ?>
									<em style="font-size:12px;color:#50575e;"><?php esc_html_e( '(heuristic)', 'aeomatic' ); ?></em>
								<?php endif; ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>

				<h2 style="margin-top:24px;font-size:16px;"><?php esc_html_e( 'Start with these three', 'aeomatic' ); ?></h2>
				<?php if ( empty( $readiness['recommendations'] ) ) : ?>
					<p class="aeom-wiz__hint"><?php esc_html_e( 'Nothing outstanding — every check is already passing. Nice start.', 'aeomatic' ); ?></p>
				<?php else : ?>
					<ol class="aeob-recommendations">
						<?php foreach ( $readiness['recommendations'] as $aeomatic_wiz_rec ) : ?>
							<li style="margin-bottom:12px;">
								<strong><?php echo esc_html( $aeomatic_wiz_rec['title'] ); ?></strong><br />
								<span class="aeom-wiz__hint"><?php echo esc_html( $aeomatic_wiz_rec['detail'] ); ?></span>
								<?php if ( ! empty( $aeomatic_wiz_rec['url'] ) ) : ?>
									<br />
									<a href="<?php echo esc_url( $aeomatic_wiz_rec['url'] ); ?>" target="_blank" rel="noopener" class="button button-small" style="margin-top:6px;">
										<?php esc_html_e( 'Fix this (opens in a new tab)', 'aeomatic' ); ?>
									</a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
					<p class="aeom-wiz__hint">
						<?php esc_html_e( 'Fix links open in a new tab so you do not lose your place here. Continue when you are ready — the full list stays on the AI Readiness dashboard.', 'aeomatic' ); ?>
					</p>
				<?php endif; ?>

			<?php elseif ( 'verify' === $step ) :
				$llms_url      = home_url( '/llms.txt' );
				$llms_full_url = home_url( '/llms-full.txt' );
				$robots_url    = home_url( '/robots.txt' );

				// Reflect what is actually switched on. A row that always shows a
				// tick is not verification, and llms.txt / AI headers are both
				// user-toggleable by this point in the wizard.
				$llms_on    = (bool) AEOMATIC_Settings::get( 'llms_txt', 'enabled' );
				$headers_on = AEOMATIC_AI_Headers::is_enabled();
				$org_on     = (bool) AEOMATIC_Settings::get( 'schema', 'organization' );
				$mark       = static function ( bool $ok ): string { return $ok ? '&#10003;' : '&#8212;'; };
				?>
				<div class="aeom-wiz__head">
					<h1><?php esc_html_e( 'You\'re live — here\'s the proof', 'aeomatic' ); ?></h1>
					<p><?php esc_html_e( 'Each of these is served by your own server right now. Click through to see the raw output.', 'aeomatic' ); ?></p>
				</div>

				<div class="aeom-wiz__verify">
					<div class="aeom-wiz__verify-row">
						<span class="status"><?php echo wp_kses( $mark( $llms_on ), array() ); ?></span>
						<span>
							<?php echo $llms_on
								? esc_html__( 'Your AI index is live at', 'aeomatic' )
								: esc_html__( 'llms.txt is turned off in Settings —', 'aeomatic' ); ?>
							<code>/llms.txt</code>
						</span>
						<?php if ( $llms_on ) : ?>
							<a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open →', 'aeomatic' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="aeom-wiz__verify-row">
						<span class="status"><?php echo wp_kses( $mark( $llms_on ), array() ); ?></span>
						<span><?php esc_html_e( 'Full-body ingestion endpoint at', 'aeomatic' ); ?> <code>/llms-full.txt</code></span>
						<?php if ( $llms_on ) : ?>
							<a href="<?php echo esc_url( $llms_full_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open →', 'aeomatic' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="aeom-wiz__verify-row">
						<span class="status">&#10003;</span>
						<span><?php esc_html_e( 'AI crawler directives injected into', 'aeomatic' ); ?> <code>/robots.txt</code></span>
						<a href="<?php echo esc_url( $robots_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open →', 'aeomatic' ); ?></a>
					</div>
					<div class="aeom-wiz__verify-row">
						<span class="status"><?php echo wp_kses( $mark( $headers_on ), array() ); ?></span>
						<span><?php echo $headers_on
							? esc_html__( 'AI HTTP discovery headers emitting on public pages', 'aeomatic' )
							: esc_html__( 'AI HTTP discovery headers are turned off in Settings', 'aeomatic' ); ?></span>
					</div>
					<div class="aeom-wiz__verify-row">
						<span class="status"><?php echo wp_kses( $mark( $org_on ), array() ); ?></span>
						<span><?php echo $org_on
							? esc_html__( 'Organization schema wired into the front end', 'aeomatic' )
							: esc_html__( 'Organization schema is turned off in Settings', 'aeomatic' ); ?></span>
					</div>
				</div>

				<p class="aeom-wiz__hint" style="margin-top:18px;">
					<?php esc_html_e( 'Next: open any post in the block editor and check the AEOmatic sidebar. You\'ll see the schema type detector, citation-gap analyser, and the new "Ask AI: Am I cited?" panel that opens ChatGPT/Perplexity/Google AI/Claude in a new tab with your question pre-filled.', 'aeomatic' ); ?>
				</p>
			<?php endif; ?>

			<div class="aeom-wiz__foot">
				<p class="aeom-wiz__promise">
					<?php esc_html_e( 'AEOmatic never sends your content to third parties. Every check on this page reads only your own site.', 'aeomatic' ); ?>
				</p>
				<div class="buttons">
					<?php if ( 'welcome' !== $step ) : ?>
						<a href="<?php echo esc_url( $wizard->prev_step_url( $step ) ); ?>" class="button button-secondary">
							<?php esc_html_e( '← Back', 'aeomatic' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( AEOMATIC_Wizard::STEPS[ count( AEOMATIC_Wizard::STEPS ) - 1 ] !== $step && 'welcome' !== $step ) : ?>
						<a href="<?php echo esc_url( $wizard->next_step_url( $step ) ); ?>" class="aeom-wiz__skip">
							<?php esc_html_e( 'Skip this step', 'aeomatic' ); ?>
						</a>
					<?php endif; ?>
					<button type="submit" name="aeomatic_wizard_submit" value="1" class="button button-primary">
						<?php echo AEOMATIC_Wizard::STEPS[ count( AEOMATIC_Wizard::STEPS ) - 1 ] === $step
							? esc_html__( 'Finish setup →', 'aeomatic' )
							: ( 'welcome' === $step
								? esc_html__( 'Let\'s go →', 'aeomatic' )
								: esc_html__( 'Continue →', 'aeomatic' ) ); ?>
					</button>
				</div>
			</div>
		</form>
	</main>
</div>
