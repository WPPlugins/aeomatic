# AEOmatic

## AEO, AI SEO, llms.txt & Schema for WordPress

**AI search engines cannot cite what they cannot parse.** AEOmatic makes WordPress content machine-readable — then shows you what the crawlers actually got.

Three jobs:

1. **Publish the signals AI systems read** — JSON-LD across 22 schema types, an `llms.txt` index, a `/llms-full.txt` full-text endpoint, per-bot `robots.txt` directives.
2. **Score how extractable the content is** — a site-wide readiness score across three categories, plus per-post audits that name the specific fix.
3. **Report what actually happened** — a server-side log of every recognised AI crawler request, including the HTTP status served. Quietly returning 404s to GPTBot shows up here.

And it reads your page builder. One normalising extraction pass covers Gutenberg, Classic, Elementor, Divi, Beaver Builder, Bricks, Oxygen, WPBakery and ACF — so schema and scores reflect what a builder-assembled page actually renders, not an empty `post_content`.

AEOmatic runs **alongside** Yoast SEO, Rank Math, AIOSEO, SEOPress or The SEO Framework — it writes only its own settings and post meta, and never touches your titles, meta descriptions, canonicals or XML sitemaps.

---

## Contents

- [What AEOmatic is](#what-aeomatic-is)
- [Who it is for](#who-it-is-for)
- [Technical AI readiness vs. actual AI visibility](#technical-ai-readiness-vs-actual-ai-visibility)
- [What it currently does](#what-it-currently-does)
- [Current features](#current-features)
- [How AEOmatic works](#how-aeomatic-works)
- [Installation](#installation)
- [Roadmap](#roadmap)
- [Compatibility](#compatibility)
- [Security and performance](#security-and-performance)
- [Developer information](#developer-information)
- [Contributing](#contributing)
- [License](#license)

---

## What AEOmatic is

A WordPress plugin for improving and measuring **AI readiness** — how easily AI systems can crawl your site, parse what a page is about, and lift a quotable answer out of it.

Concretely, AEOmatic:

- emits JSON-LD structured data for 22 schema types, auto-detected from your content
- serves `/llms.txt` and `/llms-full.txt` so AI clients can fetch a clean index and full bodies
- writes per-crawler `Allow` / `Disallow` directives for 18 AI crawlers into your `robots.txt`
- scores every post 0–100 on the content signals that make a page extractable
- scores the whole site across three readiness categories with a ranked fix list
- logs which AI crawlers hit your site, which URLs they asked for, and what you served them
- detects overlap with your existing SEO plugin so you don't ship duplicate JSON-LD

Everything runs on your own server. There is no account, no API key, and no content sent to a vendor cloud.

## Who it is for

| Audience | What they get from it |
| --- | --- |
| **WordPress site owners** | A guided setup and a plain-language list of what to fix first |
| **Businesses** | Organisation and LocalBusiness entity data AI systems can attribute to you |
| **SEO professionals** | An AI-readiness layer that composes with the SEO stack you already run |
| **Content teams** | Per-post scoring in the block editor while drafting, not after publishing |
| **WordPress developers** | 11 filters, a documented REST namespace, and analyzer interfaces to extend |
| **Agencies** | A repeatable audit and fix list you can run across client sites |

---

## Technical AI readiness vs. actual AI visibility

These are two different things, and AEOmatic only measures one of them today.

**Technical AI readiness** — *implemented.* Whether your site is crawlable by AI user agents, whether pages carry parseable structured data, whether content is shaped so an answer can be lifted out of it, whether an `llms.txt` index exists. All of this is verifiable locally, from your own database and settings.

**Actual AI visibility** — *not implemented.* Whether ChatGPT, Gemini, Claude, Perplexity or Google AI Overviews actually surface or cite your pages, for which prompts, and how that changes over time. Measuring this requires querying those engines repeatedly from outside your site and storing the results — external infrastructure AEOmatic does not have.

> A technically optimised website is not guaranteed to appear or be cited in AI search results. AEOmatic helps improve technical and content signals and, in future cloud functionality, could measure observed AI visibility.

Two consequences worth internalising:

- **The dashboard says "AI Readiness", not "AI Visibility"** — deliberately. The number describes your site's preparation, not any observed outcome.
- **A crawler visit is not a citation.** The crawler log proves GPTBot fetched a URL. It says nothing about whether that page was retained, used, or quoted in an answer. No AI vendor exposes citation data through crawler traffic, so no local log can tell you otherwise.

AEOmatic will not tell you that you rank in ChatGPT, because it cannot know.

---

## What it currently does

### Machine-readable foundation

**AI crawler controls.** Per-bot `Allow` / `Disallow` toggles injected into WordPress's virtual `robots.txt` for 18 AI crawlers: GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, Claude-User, Claude-SearchBot, PerplexityBot, Perplexity-User, Google-Extended, CCBot, Bytespider, Amazonbot, Applebot-Extended, meta-externalagent, Meta-ExternalFetcher, DeepSeekBot, Cohere-AI, DuckAssistBot. Twenty user agents are recognised in the visit log, including legacy Anthropic UAs kept for detection only.

**Schema.** JSON-LD for 22 types — Article, BlogPosting, NewsArticle, TechArticle, Recipe, HowTo, FAQPage, QAPage, Review, Product, Service, LocalBusiness, Event, Course, JobPosting, Book, Movie, WebPage, AboutPage, ContactPage, CollectionPage, ProfilePage. The type is auto-detected from content, overridable per post, and every property is editable in the block-editor sidebar with a live JSON-LD preview. Also emits Organization, Author, Breadcrumb, WebSite + SearchAction, VideoObject (from YouTube/Vimeo embeds) and Speakable. WooCommerce products get price, availability, SKU, brand and ratings, with AggregateOffer for variable products.

**llms.txt.** `/llms.txt` serves a Markdown index per the [llms.txt spec](https://llmstxt.org/): site summary, curated key pages from your primary nav menu, then every published item grouped by post type. `/llms-full.txt` serves the extracted body text of every published item so a crawler can ingest the site in one request. Both are cached for one hour and flushed whenever content changes.

**AI-readable content signals.** Content is extracted through one normalising pass that understands Gutenberg, the Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen and WPBakery, plus ACF text, textarea, wysiwyg, repeater and flexible-content fields. Everything downstream — schema, audit, readiness, llms.txt — reads that single extraction, so a page built in Elementor is scored on what it actually renders.

**AI HTTP discovery headers.** Optional `X-AI-Crawl`, `X-AI-Citeable`, `X-Content-License`, `X-AI-Content-Type`, `X-AI-Speakable` response headers plus a `Link: rel="ai-context"` header pointing at your `llms.txt`. These are proposed conventions, **not a ratified standard** — no AI vendor has published a commitment to read them. They are cheap to send and can be switched off in Settings.

**FAQ.** A native `aeomatic/faq` block with an `aeomatic/faq-item` child, rendering accessible `<details>` / `<summary>` markup with FAQPage microdata, and generating FAQPage JSON-LD. Classic Editor equivalents ship as `[aeo_faq]` / `[aeo_qa]` shortcodes.

**AI readiness audit.** Described below.

**AI crawler log.** Every recognised AI bot request is recorded server-side: bot name, requested URL, user agent (truncated to 255 chars), the HTTP status you served, and a timestamp. **No IP addresses, cookies, referrers or human-visitor data are recorded**, and nothing leaves your server. Retention is configurable (7–365 days, default 90) with a hard 500-entry ceiling and a daily WP-Cron purge.

### Readiness scoring

AEOmatic scores at two levels.

**Per post — the AEO content rubric (0–100).** Thirteen weighted checks on a single post, normalised to 0–100 so the score stays comparable as checks are added or removed:

| Check | Max |
| --- | --- |
| Direct answer in the opening | 15 |
| Article schema enabled | 15 |
| Question-style H2/H3 headings | 10 |
| FAQ block included | 10 |
| Author bio present (30+ chars) | 10 |
| Internal links to related content | 10 |
| Structured content (tables and lists) | 10 |
| Statistics and external citations | 10 |
| Lean page weight | 10 |
| Image alt text complete | 5 |
| Word count above threshold | 5 |
| First-hand experience markers | 5 |
| Content updated recently | 5 |

Runs on save, cached in post meta, and shown live in the editor sidebar. Bands: ≥70 good, 40–69 okay, <40 poor.

**Per post — AI Readiness (five categories).** A second lens scoring Content, Trust, Authority, Entities and Technical independently 0–100, each returning its own issue list and recommendations, so you can see which axis is dragging the total down. Cached against a content fingerprint and extensible via `aeomatic_ai_readiness_analyzers`.

**Site-wide — AI Readiness (three categories).** Powers the dashboard and the onboarding audit. Each category declares whether it is a direct check or a heuristic:

| Category | Kind | Derived from |
| --- | --- | --- |
| **Technical readiness** | Direct check | llms.txt serving, page schema enabled, organisation identity filled in, pretty permalinks on, AI headers on, no duplicate schema with your SEO plugin |
| **Content readiness** | **Heuristic** | Mean of the per-post Content and Entities readiness categories across your 20 most recently modified published items |
| **AI crawler access** | Direct check | Share of known AI crawlers your `robots.txt` settings allow, with answer-time retrieval crawlers weighted separately from training crawlers, plus WordPress's own "discourage search engines" setting |

The overall score is an **unweighted mean** of the categories that have data. Categories with no data (for example, content readiness on a site with no published posts) are excluded rather than counted as zero. The weighting is deliberately flat: no public evidence supports specific relative weights for these axes, so inventing some would be a false precision.

Content readiness is labelled **Heuristic** in the UI because the underlying analyzers use pattern matching — opening-answer detection, question-heading shapes, capitalised-token entity spotting. It estimates how answer-shaped your content is. It does not measure an outcome.

### Additional analysis

**Citation Gaps** — 16 local rules-based checks that flag why a page is unlikely to be quoted: orphan page, no opening answer, thin body, no FAQ block, no question heading, schema type mismatch, missing author signal, missing from llms.txt, low outbound links, low alt coverage, no citation hook, no related links, sibling topic gaps by category, FAQ or HowTo content without matching schema, missing meta description. Two gaps ship a one-click fix.

**Entity Scanner** — scans content, schema, headings and metadata for six entity types (Organization, Person, Service, Product, LocalBusiness, FAQ) and returns a coverage score with per-entity confidence, detected signals, missing signals and recommendations. Extensible via `aeomatic_entity_detectors`.

**Live verification** — makes HTTP requests to your own site and reports what is actually being served: llms.txt with size and line count, robots.txt with AI bot count, schema blocks on your latest post with a Google Rich Results Test link. Admin-only, 5-minute cache, only ever fetches URLs derived from `home_url()`.

**SEO plugin conflict detection** — detects Yoast SEO, Rank Math, AIOSEO, SEOPress and The SEO Framework, and checks whether each is *currently emitting* overlapping schema (a plugin with its schema module switched off is not reported as a conflict). A one-click fix disables only the overlapping types **in AEOmatic**; your other plugin's configuration is never modified.

### WordPress integration

**Gutenberg.** Two registered blocks (`aeomatic/faq`, `aeomatic/faq-item`) and an editor sidebar with five panels: Schema Generator (field editor + live JSON-LD preview), Audit, Citation Gaps, AI Readiness and Entity Scanner. Plus an "Ask AI — Am I cited?" panel that opens ChatGPT, Perplexity, Google AI or Claude in a new tab with your post title pre-filled; these are ordinary links your browser follows, not server-side API calls.

**REST API.** Namespace `aeomatic/v1` — 13 route patterns, 15 method+route endpoints. Every one declares an explicit permission callback — see [Developer information](#developer-information).

**Existing SEO plugin compatibility.** AEOmatic writes only its own settings and its own post meta. It never modifies another plugin's options. The setup wizard can *import* organisation name, logo and social profiles from a detected SEO plugin, per-plugin and opt-in, and never overwrites values you have already set in AEOmatic.

**Other integrations.** WooCommerce product schema; author-profile fields; a wp-admin dashboard widget; a bulk action on post list screens that re-runs the audit and gap analysis across selected rows.

**Requirements.** WordPress 6.4+, PHP 8.0+.

---

## Current features

| Feature | Status |
| --- | --- |
| AI crawler controls (robots.txt, 18 bots) | Available |
| Schema (22 types, JSON-LD) | Available |
| llms.txt | Available |
| llms-full.txt | Available |
| AI HTTP discovery headers | Available |
| AI readiness audit — per post (13 checks) | Available |
| AI readiness audit — per post, 5 categories | Available |
| AI readiness audit — site-wide, 3 categories | Available |
| FAQ block (+ Classic Editor shortcodes) | Available |
| Citation Gaps (16 checks) | Available |
| Entity Scanner (6 entity types) | Available |
| AI crawler log (bot, URL, UA, status, time) | Available |
| AI crawler analytics — dashboard breakdown | Available |
| Setup wizard with first audit | Available |
| SEO plugin conflict detection | Available |
| Live verification panel | Available |
| AI referral analytics (traffic *from* AI answers) | Planned |
| Citation tracking | Planned |
| Prompt tracking | Planned |
| Historical visibility trends | Planned |
| Competitor monitoring | Planned |
| Hosted citation measurement | Planned |
| Multi-site / client dashboards / white-label reports | Planned |

Everything marked **Planned** is not in the codebase. Items requiring repeated queries to external AI engines (citation tracking, prompt tracking, competitor monitoring, historical trends) cannot be implemented locally in a WordPress plugin — they need external infrastructure.

---

## How AEOmatic works

```text
Scan          Available   Extract content across editors and page builders;
              │           detect schema type, entities, FAQs, crawler config
              ▼
Diagnose      Available   Score per post (13 checks + 5 categories) and
              │           site-wide (3 categories); flag 16 citation gaps
              ▼
Improve       Available   Ranked top-3 fixes with deep links; one-click quick
              │           fixes; schema field editor; conflict resolution
              ▼
Monitor       Partial     AI crawler log: which bots hit which URLs and what
              │           status you served. Referral analytics: Planned
              ▼
Measure       Planned     Observed AI visibility — whether engines cite you,
                          for which prompts, trending over time.
                          Requires external infrastructure. Not implemented.
```

**Monitor** is partial and deliberately narrow: AEOmatic monitors *crawler behaviour against your server*, which it can observe directly. It does not monitor AI answers.

**Measure** is not implemented. Where the dashboard shows a number, that number is readiness.

---

## Installation

### Requirements

- WordPress **6.4** or later
- PHP **8.0** or later
- Pretty permalinks enabled (any structure other than "Plain") — required for `/llms.txt` and `/llms-full.txt` to resolve

### Install

1. Install from **Plugins → Add New** and search for "AEOmatic", or upload the `aeomatic` folder to `/wp-content/plugins/`, or use **Plugins → Add New → Upload Plugin**.
2. Activate through the **Plugins** menu.

Activation registers the `/llms.txt` and `/llms-full.txt` rewrite rules and flushes rewrites automatically. **You do not normally need to re-save permalinks** — the plugin also detects a version change on update and re-flushes by itself.

> Troubleshooting: if `/llms.txt` returns 404, check that **Settings → Permalinks** is not set to "Plain", then click **Save Changes** there once to force a rewrite flush.

### Configure

3. The setup wizard opens automatically after activation (six steps, all skippable): welcome → import from an existing SEO plugin → organisation details → AI crawler access → **first readiness audit** → verify. Re-launch it any time from the plugin's action links.
4. The wizard's audit step shows your site readiness score and the three highest-value fixes with direct links.
5. Fine-tune anything under **AEOmatic → Settings** — schema types, llms.txt, AI discovery headers, robots.txt rules, per-crawler access, log retention, audit threshold, post-type coverage.

### First audit

- **AEOmatic → Dashboard** — site readiness: overall score, three category scores labelled direct-check or heuristic, issue count, top 3 recommendations, AI crawler activity, and lowest-scoring posts.
- **Any post in the block editor** — open the AEOmatic sidebar for the per-post schema editor, audit breakdown, citation gaps, AI readiness and entity coverage.
- **AEOmatic → AI Readiness** / **Citation Gaps** / **Entity Scanner** — per-post deep dives with a post picker.

---

## Roadmap

Nothing below is implemented. Presence here is not a commitment to a date.

### Phase 1 — AI Visibility Foundation
- Better onboarding
- Improved audit
- AI crawler analytics
- AI referral analytics
- Compatibility improvements

### Phase 2 — AI Visibility Cloud
- Website monitoring
- Prompt tracking
- Citation tracking
- Historical visibility
- AI visibility score

### Phase 3 — Competitive Intelligence
- Competitor tracking
- Citation comparison
- Content opportunities
- AI search insights

### Phase 4 — Agency
- Multi-site management
- Client dashboards
- Reports
- White-label reports
- Team access
- API

Phases 2–4 require infrastructure outside WordPress: something that queries AI engines on a schedule, stores results over time, and attributes them to a site. That is a hosted service, not a plugin feature, and building a local imitation of it would produce numbers that mean nothing.

---

## Compatibility

| Target | Declared support | Verified |
| --- | --- | --- |
| WordPress | 6.4 – 7.0.2 | **Compatibility testing required** |
| PHP | 8.0+ | **Compatibility testing required** |
| Yoast SEO | Conflict detection + settings import | Detection implemented; runtime testing required |
| Rank Math | Conflict detection + settings import | Detection implemented; runtime testing required |
| AIOSEO | Conflict detection + settings import | Detection implemented; runtime testing required |
| SEOPress | Conflict detection + settings import | Detection implemented; runtime testing required |
| The SEO Framework | Conflict detection + settings import | Detection implemented; runtime testing required |
| Gutenberg / block editor | Blocks + sidebar, apiVersion 3 | Implemented; runtime testing required |
| Classic Editor | Shortcode equivalents | Implemented; runtime testing required |
| Elementor | Content extraction only | Extraction implemented; runtime testing required |
| Divi, Beaver Builder, Bricks, Oxygen, WPBakery | Content extraction only | Extraction implemented; runtime testing required |
| ACF | Text, textarea, wysiwyg, repeater, flexible content | Implemented; runtime testing required |
| WooCommerce | Product + AggregateOffer schema | Implemented; runtime testing required |

**Compatibility testing required.** The integrations above are implemented in code and statically reviewed, but this repository has no automated test suite and no verified runtime test matrix. Treat every row as "implemented, unverified" until a real matrix exists. Page-builder support is *content extraction* — AEOmatic reads builder content for schema and scoring; it does not add builder widgets.

### Interaction notes

- **Duplicate JSON-LD.** AEOmatic does not silently suppress its own schema when another SEO plugin emits the same types. It detects the overlap, warns, and offers a one-click fix — because silently disabling output the user enabled is worse than telling them. Until you resolve it, both plugins emit their types and the site readiness score reports it as a failing technical check.
- **Other plugins' settings are never written.** Import is explicit, per-plugin, opt-in, and one-directional.
- **robots.txt.** Directives are appended through the core `robots_txt` filter. If a caching plugin or a static `robots.txt` file on disk shadows WordPress's virtual one, AEOmatic's directives will not appear — WordPress only serves the virtual file when no physical file exists.
- **Caching plugins.** `/llms.txt` and `/llms-full.txt` send `Cache-Control: public, max-age=3600`. Page caches may hold them longer than AEOmatic's own transient; flush your page cache after a large content change if the index looks stale.

---

## Security and performance

### REST API permissions

All 15 method+route endpoints in `aeomatic/v1` declare an explicit `permission_callback`. None is public.

| Scope | Capability |
| --- | --- |
| Site-wide reads (`/audit/site`, `/readiness/site`, `/citation-gaps/site`, `/verify`) | `manage_options` |
| Per-post reads and writes (`/audit/{id}`, `/schema/{id}*`, `/ai-readiness/{id}`, `/citation-gaps/{id}`, `/citation-gaps/fix/{id}`, `/entity-scan/{id}`) | `edit_post` **for that post ID** |

Per-post routes are gated on the specific post, not the generic `edit_posts` capability, so a contributor cannot read another author's analysis.

### Capability checks, nonces, sanitisation, escaping

- Every admin page is registered under `manage_options`; the wizard re-checks the capability inside its own render method.
- Settings and wizard writes verify a nonce via `check_admin_referer()` **and** re-check `manage_options` before touching anything. The conflict-resolution action is nonce-protected via `wp_nonce_url()`.
- All settings input is sanitised per key — booleans coerced, text through `sanitize_text_field()`, textareas through `sanitize_textarea_field()`, URLs through `esc_url_raw()`, integers through `absint()` and clamped to sane ranges (log retention 7–365 days, audit word threshold 100–5000). Post-type slugs go through `sanitize_key()`; `attachment` is rejected.
- Registered post meta declares an `auth_callback`. Output is escaped at the point of use (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`); admin JS builds DOM strings through a local `escapeHtml()`.
- `$wpdb->delete()` in `uninstall.php` uses the array form, so values are prepared by WordPress.

### Outbound requests and SSRF

The only server-side HTTP requests are in the verification module. It requires `manage_options`, caches for five minutes, and fetches only URLs derived from `home_url()` — no user-supplied URL ever reaches `wp_remote_get()`. The "Ask AI" panel is client-side links only. There are no external calls on the front end and no telemetry.

### Frontend performance

- Schema is built server-side and cached in post meta, invalidated on content, meta and term changes. AEOmatic's own analysis meta keys are excluded from that invalidation, so running an audit does not throw away the built schema.
- `/llms.txt` and `/llms-full.txt` are cached in transients for one hour and flushed on publish, delete and menu changes. `/llms-full.txt` is capped at 200 items per post type by default (`aeomatic_llms_full_max_items`) and states in its own body when it truncated — without a bound, one anonymous request would extract every published post on the site.
- The crawler log only writes when the user agent matches a known AI bot, defers its single `update_option()` to `shutdown`, and stores with `autoload = false`.
- Stat counters accumulate in memory and flush once per request at shutdown rather than reading and writing an option per event.
- Admin CSS/JS load only on AEOmatic screens. Editor sidebar assets load via `enqueue_block_editor_assets`.
- No external assets, fonts or CDN requests anywhere.

### Database usage

One options row for settings, plus separate non-autoloaded rows for the activity log (50 entries), stat counters, crawler log (500 entries) and crawler log stats. No custom tables. Per-post meta: audit score and breakdown, schema type, analysis, cache and overrides, ACF text cache, and fingerprinted results for readiness, citation gaps and entity scan.

### Uninstall behaviour

`uninstall.php` deletes all 9 options, 6 transients, the scheduled purge hook, and all 16 post-meta keys the plugin writes. Deactivation leaves settings intact so a reactivation does not lose configuration.

### Known gaps

Stated rather than hidden:

- **No PHPUnit suite, no PHPCS ruleset, no CI.** `bin/` provides self-testing structural and reference checks that gate packaging, but nothing exercises runtime behaviour.
- **No verified runtime test matrix.** See [Compatibility](#compatibility).
- **The crawler log's 500-entry cap can dominate its retention window.** On a heavily crawled site the ring buffer will drop entries well before the configured retention period expires. Real analytics needs a custom table.
- **The `performance` audit check is a proxy.** It measures post HTML size, not actual page load. It is labelled "Lean page weight" for that reason.
- **The per-post `article_schema` check reads a global setting**, so it scores the same for every post on the site.
- **AI discovery headers are a proposed convention**, not a standard any AI vendor has committed to reading.

---

## Developer information

### Directory structure

```text
aeomatic/
├── aeomatic.php                    Plugin header, constants, requires, activation
├── uninstall.php                   Full option / transient / post-meta cleanup
├── README.md                       This file
├── readme.txt                      WordPress.org plugin readme
├── bin/                            Build + static-check tooling (not shipped)
│   ├── build-plugin.js             Gated packaging: version, dev files, wrapper,
│   │                               separators, static checks
│   ├── check-structure.js          PHP structural check (stands in for php -l)
│   └── check-references.js         Dangling AEOMATIC_*:: reference check
├── admin/
│   ├── class-aeomatic-admin.php    Menus, settings save, review notice, assets
│   ├── class-aeomatic-wizard.php   Six-step onboarding controller
│   └── views/                      dashboard, settings, activity, gaps,
│                                   ai-readiness, entity-scanner, wizard
├── assets/
│   ├── css/admin.css
│   ├── js/admin.js                 Dashboard per-post scores
│   ├── js/editor-sidebar.js        Block-editor sidebar (5 panels)
│   ├── js/verify.js                Live verification panel
│   └── images/
├── blocks/
│   ├── faq/                        block.json, index.js, styles
│   └── faq-item/                   block.json, index.js
└── includes/
    ├── class-aeomatic-plugin.php           Module orchestrator, upgrade routine
    ├── class-aeomatic-settings.php         Single options array + sanitisation
    ├── class-aeomatic-post-types.php       Post-type discovery and enable map
    ├── class-aeomatic-content-extractor.php  One extraction pass, all builders
    ├── class-aeomatic-schema*.php          Analyzer, generator, builder, fields,
    │                                       property extractors, injector
    ├── class-aeomatic-llms-txt.php         /llms.txt and /llms-full.txt
    ├── class-aeomatic-crawler-control.php  robots.txt directives
    ├── class-aeomatic-crawler-log.php      AI bot visit log + retention cron
    ├── class-aeomatic-ai-headers.php       X-AI-* response headers
    ├── class-aeomatic-audit.php            Per-post 13-check rubric + REST
    ├── class-aeomatic-ai-readiness.php     Per-post 5-category orchestrator
    ├── class-aeomatic-site-readiness.php   Site-wide 3-category summary
    ├── class-aeomatic-citation-gaps.php    16 gap checks
    ├── class-aeomatic-entity-scanner.php   6 entity detectors
    ├── class-aeomatic-conflict-detector.php  SEO plugin overlap detection
    ├── class-aeomatic-seo-importer.php     Import from Yoast/RM/AIOSEO/…
    ├── class-aeomatic-verification.php     Live self-checks
    ├── class-aeomatic-woocommerce.php      Product schema
    ├── class-aeomatic-blocks.php           Block + sidebar registration
    ├── class-aeomatic-shortcodes.php       [aeo_faq] / [aeo_qa]
    ├── class-aeomatic-activity.php         Event log + deferred stat counters
    ├── class-aeomatic-author-profile.php   Author meta fields
    ├── class-aeomatic-bulk-actions.php     Bulk re-audit
    ├── class-aeomatic-dashboard-widget.php wp-admin dashboard tile
    ├── entities/                           Detector interface + 6 detectors
    ├── gaps/                                Gap result VO + 2 check groups
    └── readiness/                           Analyzer interface + result VO
                                             + 5 category analyzers
```

### REST endpoints

Namespace: `aeomatic/v1`

| Method | Route | Capability |
| --- | --- | --- |
| GET | `/audit/{id}` | `edit_post` (that ID) |
| GET | `/audit/site` | `manage_options` |
| GET | `/readiness/site` | `manage_options` |
| GET | `/ai-readiness/{id}` | `edit_post` (that ID) |
| GET | `/citation-gaps/{id}` | `edit_post` (that ID) |
| POST | `/citation-gaps/fix/{id}` | `edit_post` (that ID) |
| GET | `/citation-gaps/site` | `manage_options` |
| GET | `/entity-scan/{id}` | `edit_post` (that ID) |
| GET | `/schema/{id}` | `edit_post` (that ID) |
| POST | `/schema/{id}` | `edit_post` (that ID) |
| GET | `/schema/{id}/fields` | `edit_post` (that ID) |
| POST | `/schema/{id}/fields` | `edit_post` (that ID) |
| POST | `/schema/{id}/fields/reset` | `edit_post` (that ID) |
| GET | `/schema/{id}/preview` | `edit_post` (that ID) |
| GET | `/verify` | `manage_options` |

`/readiness/site` and `/ai-readiness/{id}` accept `?force=true` to bypass their caches.

### Filters

| Filter | Purpose |
| --- | --- |
| `aeomatic_ai_readiness_analyzers` | Add, replace or remove per-post readiness categories. Return an array of `AEOMATIC_Readiness_Analyzer_Interface`. |
| `aeomatic_entity_detectors` | Add or replace entity detectors. |
| `aeomatic_citation_gap_results` | Filter the gap list before scoring. |
| `aeomatic_schema_output` | Filter every JSON-LD block before it is printed. |
| `aeomatic_supported_post_types_list` | Restrict or extend the post types AEOmatic considers. |
| `aeomatic_llms_max_items` | Per-type item cap for the `/llms.txt` index. Default `-1` (no cap). |
| `aeomatic_llms_full_max_items` | Per-type item cap for `/llms-full.txt`. Default `200`. |
| `aeomatic_seo_conflicts_detected` | Adjust or suppress detected SEO plugin conflicts. |
| `aeomatic_detect_faqs` | Add FAQ detection for a custom source. |
| `aeomatic_extract_extra_html` | Contribute extra HTML to the content extraction pass. |
| `aeomatic_extract_meta_keys` | Add post-meta keys to the extraction pass. |

### Extension points

Adding a readiness category:

```php
add_filter( 'aeomatic_ai_readiness_analyzers', function ( array $analyzers ): array {
    $analyzers[] = new My_Readiness_Analyzer();
    return $analyzers;
} );

class My_Readiness_Analyzer implements AEOMATIC_Readiness_Analyzer_Interface {
    public function analyze( WP_Post $post ): AEOMATIC_Readiness_Result {
        $score  = 100;
        $issues = array();
        // ... your checks, deducting from $score ...
        return new AEOMATIC_Readiness_Result( 'my-category', $score, $issues, array() );
    }
}
```

Bounding `/llms-full.txt` on a large site:

```php
add_filter( 'aeomatic_llms_full_max_items', fn() => 50 );
```

### Coding standards

WordPress PHP coding standards: tabs for indentation, Yoda conditions, `snake_case` functions, `AEOMATIC_` class prefix, `aeomatic_` hook prefix, `aeomatic` text domain, one class per file named `class-{slug}.php`. All strings are translatable with translator comments on every placeholder. Every file guards on `ABSPATH`.

No PHPCS ruleset is committed yet. To check locally:

```bash
composer global require wp-coding-standards/wpcs
phpcs --standard=WordPress --extensions=php .
```

### Testing instructions

There is **no PHPUnit suite in this repository**. What does exist is a set of
static checks in `bin/`, which run automatically as part of packaging.

```bash
node bin/check-structure.js      # brace / string / comment / endif balance
node bin/check-references.js     # every AEOMATIC_*:: call has a declaration
```

Both take `--self-test`, which feeds them known-bad input and asserts they
fail. **Run the self-test before trusting a pass** — a checker that silently
stopped working reads exactly like a clean codebase.

```bash
node bin/check-structure.js --self-test
node bin/check-references.js --self-test
```

If a PHP binary is available, it is still the authority:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Neither the static checks nor `php -l` say anything about runtime behaviour.
On a staging install with `WP_DEBUG` and `WP_DEBUG_LOG` on:

1. Activate the plugin — no fatals, no notices in `debug.log`.
2. Complete the wizard end to end, including the readiness audit step.
3. Load **AEOmatic → Dashboard**; confirm three category cards render and scores are plausible.
4. Save Settings; confirm values persist and unchecking a box actually stores `false`.
5. Fetch `/llms.txt`, `/llms-full.txt` and `/robots.txt`; confirm content and the one-hour cache header.
6. Publish a post; confirm the llms.txt cache flushed and JSON-LD appears in `<head>`.
7. Call each REST route logged out and as a subscriber; confirm 401/403.
8. Insert the FAQ block; confirm frontend `<details>` markup and FAQPage JSON-LD.
9. Activate Yoast or Rank Math; confirm the conflict notice appears and no fatal occurs.
10. Delete the plugin; confirm no `aeomatic_*` options and no `_aeomatic_*` post meta remain.

Contributions adding a PHPUnit suite are especially welcome — see below.

### Building a release

```bash
node bin/build-plugin.js              # writes ../aeomatic-<version>.zip
node bin/build-plugin.js --out DIR    # somewhere else
```

Do not hand-zip the folder. Windows tooling in particular gets this wrong:
both PowerShell's `Compress-Archive` and .NET Framework's
`ZipFile::CreateFromDirectory` write Windows backslashes into ZIP entry
names, and PHP's unzip treats a backslash as part of the filename — so the
archive extracts as a flat pile of oddly-named files instead of a directory
tree. `build-plugin.js` writes the ZIP itself to avoid this.

Five gates block the build, each one a defect that has actually shipped:

| Gate | Blocks |
| --- | --- |
| G1 | Version drift between the plugin header, `AEOMATIC_VERSION`, `Stable tag:` and the top changelog heading — or a non-`X.Y.Z` placeholder |
| G2 | Dev files in the package. 1.1.8 shipped `.claude/settings.local.json`, `.gitignore` and `.vercel/project.json` (Vercel project and org IDs) into a public download |
| G3 | A missing `aeomatic/` wrapper folder. 1.1.9's first build put files at the archive root, so WordPress installed a *second* plugin folder instead of updating |
| G4 | Backslash separators in entry names |
| G5 | Failing structural or reference checks |

A failed gate writes nothing and exits 1, so it is safe to wire into CI.
`--skip-checks` bypasses G5 only; builds made with it must not be released.

The output is byte-reproducible (fixed ZIP timestamps), so a diff between two
archives means the source genuinely changed.

---

## Contributing

Issues and pull requests are welcome.

- **Match the surrounding code.** WordPress coding standards, existing naming, existing comment density.
- **Do not invent metrics.** A score must be derivable from something real on the user's site. If a check is a heuristic, label it `'kind' => 'heuristic'` and say so in the UI.
- **Do not claim guaranteed AI citations** in code comments, UI copy or docs.
- **Keep front-end cost at zero.** No external requests on public pages.
- **No new dependencies** without a clear justification — the plugin currently has none.
- **Preserve backward compatibility.** Existing settings keys, meta keys and filters are a contract.
- **Add cleanup for anything you persist.** New option or meta key means a new line in `uninstall.php`.
- **Run `node bin/build-plugin.js` before opening a PR.** It runs the static checks and will reject version drift and dangling references.
- **Say what you tested.** Including "I could not test X" — that is more useful than silence.

Highest-value contributions right now: a PHPUnit suite, a committed PHPCS ruleset, a CI workflow, and a verified WordPress/PHP compatibility matrix.

---

## License

GPL v2 or later.

```text
This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.
```

Full text: <https://www.gnu.org/licenses/gpl-2.0.html>
