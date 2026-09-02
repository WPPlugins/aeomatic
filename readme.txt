=== AEOmatic — AI SEO, llms.txt & Schema Markup ===
Contributors: mmtushar
Tags: structured data, json-ld, ai search, aeo, ai crawler
Requires at least: 6.4
Tested up to: 7.0.2
Requires PHP: 8.0
Stable tag: 1.1.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make WordPress readable to AI search: 22 schema types with a visual JSON-LD editor, llms.txt, and AI crawler control. Reads Elementor, Divi and ACF.

== Description ==

AI search engines cannot cite what they cannot parse. AEOmatic makes your WordPress content machine-readable — then shows you what the crawlers actually got.

It does three jobs:

1. **Publishes the signals AI systems read.** JSON-LD structured data across 22 schema types, an `llms.txt` index at your site root, a `/llms-full.txt` full-text endpoint, and per-bot crawl directives in `robots.txt`.
2. **Scores how extractable your content is.** A site-wide AI readiness score across three categories, plus per-post audits that name the specific thing to fix rather than handing you a number.
3. **Reports what actually happened.** A server-side log of every recognised AI crawler request — which bot, which URL, and the HTTP status your site returned. If you are quietly serving 404s to GPTBot, this is where you find out.

= It reads your page builder, not just Gutenberg =

Most AI SEO plugins parse `post_content` and stop. If your pages are built in Elementor or Divi, or your copy lives in ACF fields, that means your schema and your scores are computed against an almost-empty string.

AEOmatic runs one normalising extraction pass that understands **Gutenberg, the Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen and WPBakery**, plus ACF text, textarea, wysiwyg, repeater and flexible-content fields. Everything downstream — schema generation, the audit, readiness scoring, `llms.txt` — reads that single extraction, so a page assembled in a builder is scored on what it actually renders.

This work goes by several names — Answer Engine Optimization (AEO), Generative Engine Optimization (GEO), LLM SEO, AI search optimization. Same underlying job.

= Built to sit beside your SEO plugin, not replace it =

AEOmatic writes only its own settings and its own post meta. It never touches your titles, meta descriptions, canonicals or XML sitemaps, and it never modifies another plugin's configuration.

It detects Yoast SEO, Rank Math, All in One SEO, SEOPress and The SEO Framework, can import your organisation details from whichever you run, and flags the case where two plugins would emit the same JSON-LD type on one page — with a one-click fix that adjusts **AEOmatic's** output, never theirs.

= What AEOmatic measures, and what it refuses to guess at =

AEOmatic scores **readiness** — everything about your site that a machine can check locally: can AI crawlers reach your pages, do those pages carry structured data something can parse, is your content shaped so an answer can be lifted out of it. Measurable from your own install, so AEOmatic measures it.

It does not score **visibility** — whether ChatGPT, Gemini, Claude or Perplexity actually surface or cite you, for which prompts, trending over time. Answering that means querying those engines on a schedule from outside your site. That is a hosted service, not a plugin feature, and a local imitation of it would just be a number with nothing behind it.

So the dashboard is labelled **AI Readiness**, not AI Visibility. A well-prepared site still is not guaranteed to be cited — no plugin can promise that, and every check in the audit tells you whether it is a direct check or a heuristic so you can see which is which.

One distinction the crawler log makes easy to get wrong: **a crawl is not a citation.** The log proves a bot fetched a URL. Whether that page ended up in an answer is not visible in crawler traffic, and no AI vendor exposes it there.

= Everything included, no account and no API key =

* **AI readiness audit, three levels.** Site-wide across three categories (Technical readiness, Content readiness, AI crawler access), per post across five categories (Content, Trust, Authority, Entities, Technical), and a per-post 0–100 content rubric of thirteen weighted checks. Every category states whether it is a direct check or a heuristic.

* **Visual schema editor for 22 types** — Article, BlogPosting, NewsArticle, TechArticle, Recipe, HowTo, FAQPage, QAPage, Review, Product, Service, LocalBusiness, Event, JobPosting, Course, Book, WebPage, AboutPage, ContactPage, CollectionPage, ProfilePage, Movie. The type is auto-detected from your content and every field is editable in the block-editor sidebar with a live JSON-LD preview.

* **Type-specific property extraction** — Recipe posts get ingredients and instructions, JobPosting posts get salary ranges, Event posts get dates and venue, LocalBusiness gets address and opening hours. Not generic Article schema on everything.

* **`/llms.txt` generator** — a spec-compliant Markdown index at your site root: site summary, curated key pages from your nav menu, then every published item grouped by post type.

* **`/llms-full.txt` endpoint** — the extracted body text of your published content at one URL, so an AI crawler can ingest the site in a single request. Bounded and cached so it cannot become a load problem.

* **AI crawler control** — per-bot Allow/Disallow toggles in `robots.txt` for 18 AI crawlers: GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, Claude-User, Claude-SearchBot, PerplexityBot, Perplexity-User, Google-Extended, CCBot, Bytespider, Amazonbot, Applebot-Extended, meta-externalagent, Meta-ExternalFetcher, DeepSeekBot, Cohere-AI, DuckAssistBot. Twenty user agents recognised in the visit log.

* **AI crawler log and breakdown** — which AI bots requested which URLs, what HTTP status you served them, and when. A 404 or 403 being served to OAI-SearchBot is the kind of thing you want to know. Records bot name, URL, user agent and status only: **no IP addresses, no cookies, no visitor data**, nothing leaves your server.

* **Page-builder support** — content is read from Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen and WPBakery, plus ACF text, textarea, wysiwyg, repeater and flexible-content fields. Scoring reflects what your pages actually render.

* **WooCommerce Product schema** — price, availability, SKU, brand, ratings and sale dates auto-filled; variable products get AggregateOffer with a min/max range.

* **AI Citation Gaps** — sixteen local rules-based checks that name why a page is unlikely to be quoted: orphan pages, no opening answer, thin body, no FAQ block, no question heading, schema type mismatch, missing author signal, missing from llms.txt, low outbound links, low alt coverage, no citation hook, no related links, sibling-topic gaps by category, FAQ or HowTo content without matching schema, missing meta description. Two ship a one-click fix.

* **Entity Scanner** — scans content, schema, headings and metadata for Organization, Person, Service, Product, LocalBusiness and FAQ entities, returning a coverage score with per-entity confidence, detected signals, missing signals and recommendations.

* **FAQ block** — a native Q&A block producing accessible `<details>`/`<summary>` markup plus FAQPage JSON-LD. `[aeo_faq]` / `[aeo_qa]` shortcodes for the Classic Editor.

* **Setup wizard with a real first audit** — six skippable steps: welcome, import from your existing SEO plugin, organisation details, AI crawler access, **your first readiness audit**, verify. The audit step shows your score and the three highest-value fixes with direct links, so you finish onboarding knowing what to do next.

* **SEO plugin conflict detection** — detects Yoast SEO, Rank Math, All in One SEO, SEOPress and The SEO Framework, and checks whether each is *currently emitting* overlapping schema. One click disables only the overlapping types **in AEOmatic**; your other plugin's configuration is never modified.

* **AI HTTP discovery headers** — optional `X-AI-Crawl`, `X-AI-Citeable`, `X-Content-License`, `X-AI-Content-Type`, `X-AI-Speakable` headers plus a `Link: rel="ai-context"` pointer to your llms.txt. These are proposed conventions, not a ratified standard, and no AI vendor has committed to reading them — so they ship as a setting you can switch off.

* **Live verification panel** — makes HTTP requests to your own site and shows what is actually being served: llms.txt with size and line count, robots.txt with AI bot count, schema blocks on your latest post with a direct Google Rich Results Test link.

* **VideoObject, Speakable, WebSite + SearchAction schema**, an author-profile panel, a wp-admin dashboard widget, and a bulk re-audit action on post list screens.

= AI crawlers recognised =

* **ChatGPT / ChatGPT Search** (OpenAI) — GPTBot, OAI-SearchBot, ChatGPT-User
* **Claude / Claude Search** (Anthropic) — ClaudeBot, Claude-User, Claude-SearchBot
* **Perplexity** — PerplexityBot, Perplexity-User
* **Google AI Overviews / Gemini** — Google-Extended controls Gemini training; AI Overviews retrieval uses Googlebot
* **Microsoft Copilot** — no dedicated AI user agent; retrieves from the Bing index via Bingbot
* **Meta AI** — meta-externalagent, Meta-ExternalFetcher
* **DeepSeek** — DeepSeekBot
* **Apple Intelligence** — Applebot-Extended
* **Cohere** — Cohere-AI
* **DuckDuckGo AI answers** — DuckAssistBot
* **Common Crawl** — CCBot

Controlling a crawler's access controls whether it can read you. It does not control whether an engine cites you.

= Runs entirely on your own server =

Schema and metadata generate server-side and cache in post meta. There are no external API calls on the front end, no account, no API key, no credit limits, and no content sent to a vendor cloud. The verification panel only ever requests URLs on your own site, and only for logged-in administrators. No third-party analytics, no telemetry.

= Who it is for =

* **Publishers and media** who want to be quoted by AI as a source
* **Businesses and SaaS companies** who want their entity data to be unambiguous to AI systems
* **Local businesses** who want correct LocalBusiness signals
* **E-commerce stores** on WooCommerce
* **SEO professionals and content teams** who need an AI layer on top of their existing stack
* **Agencies** running a repeatable audit across client sites
* **WordPress developers** — 11 filters, a documented REST namespace, and analyzer interfaces to extend

= What is not in this plugin =

Stated plainly so you can decide before installing. None of the following exists here:

* Citation tracking — whether an AI engine quoted you
* Prompt tracking — which questions surface your site
* Historical AI visibility trends
* Competitor monitoring or citation comparison
* AI referral analytics (traffic arriving *from* AI answers)
* Multi-site management, client dashboards, or white-label reports

These all need infrastructure outside WordPress — something querying AI engines on a schedule and storing results over time. They are on the roadmap as hosted functionality, not faked locally.

== Installation ==

1. Install from **Plugins → Add New** and search for "AEOmatic", or upload the `aeomatic` folder to `/wp-content/plugins/`, or use **Plugins → Add New → Upload Plugin**.
2. Activate through the **Plugins** menu. Activation registers the `/llms.txt` and `/llms-full.txt` endpoints and flushes rewrite rules for you.
3. The setup wizard opens automatically. Six skippable steps, ending with your first readiness audit and top three fixes.
4. Visit **AEOmatic → Dashboard** for your site AI readiness score, category breakdown, top recommendations and AI crawler activity.
5. Open any post in the block editor and click the AEOmatic icon in the top-right toolbar for the schema editor, audit breakdown, citation gaps, AI readiness and entity coverage.

**Requirements:** WordPress 6.4+, PHP 8.0+, and pretty permalinks (any structure other than "Plain").

You do **not** normally need to re-save permalinks after installing or updating — the plugin flushes rewrite rules on activation and again automatically when it detects a version change. If `/llms.txt` returns a 404, confirm **Settings → Permalinks** is not set to "Plain", then click **Save Changes** there once.

== Frequently Asked Questions ==

= Will this get me cited by ChatGPT or ranked in AI search? =

No plugin can promise that, and AEOmatic does not. What it does is fix the technical and content signals you control: crawler access, parseable structured data, an llms.txt index, and content shaped so an answer can be extracted. Whether an AI engine then cites you depends on that engine's own ranking and retrieval, which nobody outside those companies controls. Any plugin promising guaranteed AI citations is guessing.

= What is the difference between AI readiness and AI visibility? =

AI readiness is how well-prepared your site is — measurable from your own database and settings, which is what AEOmatic scores. AI visibility is whether AI systems actually surface or cite you — measurable only by querying those engines from outside your site, which AEOmatic does not do. The dashboard is labelled "AI Readiness" for that reason.

= Does the crawler log tell me if a page was cited? =

No. It tells you a crawler requested a URL and what status you served. Whether that page was retained, used, or quoted in an AI answer is not visible in crawler traffic, and no AI vendor exposes it there. Treat crawler visits as access evidence, not citation evidence.

= Does this conflict with Yoast SEO or Rank Math? =

It is designed to run beside them. AEOmatic writes only its own settings and its own post meta and never modifies another plugin's configuration. Because several SEO plugins also emit Article, Organization and Breadcrumb schema, AEOmatic detects that overlap and offers a one-click fix that disables only the overlapping types *in AEOmatic*. The detector checks whether the other plugin's schema module is actually switched on, so it does not nag you about a conflict that is not happening. Until you resolve a real overlap, both plugins emit their types and the site readiness score reports it as a failing technical check.

= Can I import my existing SEO plugin's settings? =

Yes — organisation name, logo and social profiles from Yoast SEO, Rank Math, SEOPress, All in One SEO or The SEO Framework. Import is per-plugin, opt-in, one-directional, and never overwrites a value you have already set in AEOmatic.

= What is /llms-full.txt? =

A single URL returning the extracted body text of your published content, so an AI crawler can ingest the site in one request instead of following dozens of links. It is capped at 200 items per post type by default and says so in its own output when it truncates; raise or remove the cap with the `aeomatic_llms_full_max_items` filter.

= My llms.txt or /llms-full.txt shows a 404. How do I fix it? =

Check that **Settings → Permalinks** is not set to "Plain" — the endpoints need a rewrite rule, which plain permalinks do not support. Then click **Save Changes** on that screen once. Also confirm llms.txt is enabled under **AEOmatic → Settings**.

= Does the plugin work with Elementor, Divi, or other page builders? =

Content extraction does. AEOmatic reads content from Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen and WPBakery, plus ACF fields, so schema and scoring reflect what your pages actually render. It does not add builder-native widgets.

= Will this slow down my site? =

Schema is generated server-side and cached in post meta. `/llms.txt` and `/llms-full.txt` are cached for an hour and flushed when content changes. The crawler log writes only when a recognised AI bot requests a page, and defers its single database write to the end of that request. There are no external requests and no third-party assets on public pages. Admin CSS and JS load only on AEOmatic screens.

= What schema types does it support? =

Twenty-two page-level types (Article, BlogPosting, NewsArticle, TechArticle, Recipe, HowTo, FAQPage, QAPage, Review, Product, Service, LocalBusiness, Event, JobPosting, Course, Book, Movie, WebPage, AboutPage, ContactPage, CollectionPage, ProfilePage) plus Organization, Author/Person, Breadcrumb, WebSite with SearchAction, VideoObject and Speakable.

= Does it work with WooCommerce? =

Yes. Product schema with price, availability, SKU, brand and ratings; variable products get AggregateOffer with a min/max price range.

= How do I know the schema is actually working? =

Open **AEOmatic → What did we do?** for the live verification panel. It fetches your own URLs and shows what is being served, with a direct link to Google's Rich Results Test for your latest post.

= Does the plugin send my data anywhere? =

No. No external API calls on the front end, no telemetry, no analytics, no account. The only server-side HTTP requests are the verification panel fetching your own site, for administrators only.

= Is the audit score reliable? =

It is a rubric, and it is honest about which parts are heuristic. The Technical and AI crawler access categories are direct checks — a setting is either on or off. Content readiness is explicitly labelled a heuristic: it uses pattern matching to estimate how answer-shaped your content is. Category scores are combined with an unweighted mean, because no public evidence supports specific relative weights and inventing some would be false precision.

= What happens when I uninstall AEOmatic? =

Deleting the plugin removes every option, transient, scheduled task and post-meta key it created. Deactivating alone leaves your settings intact so reactivating does not lose your configuration.

== Screenshots ==

1. AI Readiness dashboard — overall score, three category cards labelled direct-check or heuristic, issue count and top recommendations.
2. Visual schema editor in the block-editor sidebar — auto-populated fields, live JSON-LD preview, type confidence.
3. Type-aware JSON-LD generation — Recipe schema with auto-extracted ingredients and instructions.
4. AI crawler activity — which bots requested which URLs and what HTTP status was served.
5. Setup wizard readiness step — your first audit score with the three highest-value fixes.
6. Live verification panel showing what is actually being served, with a direct Google Rich Results Test link.

== Privacy Policy ==

AEOmatic does not collect, store, or transmit any personal data to external servers.

Schema, `llms.txt`, `/llms-full.txt` and the AI discovery headers are generated server-side on your own site. The verification panel makes HTTP requests only to your own site, only for logged-in administrators, to confirm public output.

The AI crawler visit log records, in your own database only: the matched bot name, the requested URL, the user-agent string (truncated to 255 characters), the HTTP status your site returned, and a timestamp. It records **no IP addresses, no cookies, no referrers and no human-visitor data**, and only logs requests whose user agent matches a known AI crawler. Retention is configurable from 7 to 365 days (default 90) with a 500-entry ceiling and a daily purge. Nothing in the log ever leaves your site.

AEOmatic makes no external API calls on the front end and ships with no third-party analytics or telemetry.

== Upgrade Notice ==

= 1.1.9 =
Fixes page-builder and ACF pages being scored as empty across six readiness checks — expect scores to rise on Elementor, Divi, Bricks, Oxygen, Beaver Builder and WPBakery sites. Renamed, with a new AI Readiness dashboard, an AI crawler log that records the HTTP status served to each bot, and a first-audit onboarding step. Fixes two readiness checks that were silently degraded, tightens the per-post citation-gaps permission check, caches and bounds /llms.txt and /llms-full.txt, and makes uninstall remove everything it should. Recommended update.

= 1.1.8 =
Conflict detection now respects your SEO plugin's own schema settings, and custom post type coverage saves reliably. Recommended update.

== Changelog ==

= 1.1.9 =

Renamed to **AEOmatic — AI SEO, llms.txt & Schema Markup**, plus a new site-level AI readiness score, a first-audit onboarding step, richer crawler analytics, and a set of correctness, security and performance fixes.

* **New: site-wide AI Readiness score with three categories.** A new `AEOMATIC_Site_Readiness` module aggregates what the plugin already measures into Technical readiness (direct checks on llms.txt, schema, organisation identity, permalinks, AI headers and duplicate-schema conflicts), Content readiness (mean of the per-post Content and Entities readiness categories across your 20 most recently modified published items) and AI crawler access (share of AI crawlers your robots.txt settings allow, with answer-time retrieval crawlers weighted separately from training crawlers). Every category declares whether it is a direct check or a heuristic, and the overall score is an unweighted mean with no-data categories excluded rather than counted as zero. Exposed at `GET /wp-json/aeomatic/v1/readiness/site` (`manage_options`).
* **New: rebuilt dashboard as an AI Readiness dashboard.** Overall score, three category cards each labelled "Direct check" or "Heuristic", issue count, top three recommendations with deep links, an "Improve my site" action, and an AI crawler activity table. Copy states explicitly that readiness is not visibility and that a crawler visit is not a citation.
* **New: the setup wizard now runs a real audit.** A sixth step between "AI crawlers" and "Verify" runs a fresh site readiness audit and shows the score, the category breakdown and the three highest-value fixes with links that open in a new tab. Onboarding now ends with the user knowing what to do next instead of only having filled in settings.
* **New: AI crawler log records user agent and response status.** Entries now carry the truncated user-agent string and the HTTP status the site returned, so a 404 or 403 being served to GPTBot is visible. The write is deferred to `shutdown` because the status code is not final earlier in the request. A new `AEOMATIC_Crawler_Log::get_breakdown()` returns per-bot visits, error counts, last-seen time and last URL. Still no IP addresses, cookies or visitor data.
* **New: AI discovery headers are now a setting.** `AEOMATIC_Analyzer_Technical` already read an `ai_headers.enabled` setting that was never declared, so the check silently always passed. The setting now exists, defaults to on for existing installs, and has a toggle in Settings. `X-AI-Crawl` now reports `restricted` instead of `allowed` when every AI crawler is blocked, rather than contradicting the site's own robots.txt.
* **Fix: page-builder and ACF pages are no longer scored as empty.** This is the big one. Six readiness checks read `$post->post_content` directly instead of going through the shared content extractor, so on an Elementor, Divi, Bricks, Oxygen, Beaver Builder or WPBakery site — where the body lives in post meta — they saw a near-empty string and deducted points for content that was plainly there: Content lost 30 (no headings, no FAQ structure), Entities 25 (no named entities), Technical 20 ("body under 20 words"), Trust 25 (no outbound links) and Authority 20 (no authoritative outbound). All six now read the extractor, which merges post_content, page-builder data and ACF fields.

  The Technical analyzer's message was the worst of it: it told page-builder users *"content may be JS-only or hidden behind a page builder that AI crawlers cannot parse"* — fired **because** they used a page builder, on content that server-renders perfectly well. Rewritten to describe what it now actually measures.

  **Expect readiness scores to rise on page-builder sites after updating.** That is the correction landing, not new optimisation.
* **Fix: Elementor headings and links are now visible to the analysers.** The Elementor extractor flattened everything to bare text, so a Heading widget lost its level entirely — `settings.title` became a plain line and `settings.header_size` (which holds `h2`) was being collected *as content*, injecting the literal string "h2" into the extracted text. Heading widgets are now rebuilt as real `<hN>` markup, and widget links in `settings.link.url` are rebuilt as `<a href>` anchors so internal and outbound link counts can see them. Heading titles and button labels are emitted exactly once, not twice.
* **Fix: the "generate meta description" quick-fix works on page-builder pages.** It summarised `post_content`, so on a builder page it returned "Post has no content to summarise". It now summarises extracted text.
* **Fix: `AEOMATIC_Content_Extractor::extract_text()` now exists.** The Content readiness analyzer called it behind a `method_exists()` guard and silently fell back to `wp_strip_all_tags( $post->post_content )` — so page-builder and ACF content was scored as thin or empty. Same class of bug fixed for the Entity Scanner in 1.1.7; this was the remaining caller.
* **Fix: `AEOMATIC_LLMs_Txt::is_post_included()` now exists.** The Technical readiness analyzer called it behind a guard and fell back to a post-type check, so a post excluded from llms.txt by a disabled feature or a password was still scored as included.
* **Fix: uninstall now removes everything.** `uninstall.php` deleted 7 of the 16 post-meta keys the plugin writes and leaked the `aeomatic_crawler_log_stats` and `aeomatic_wizard_completed` options. It now clears all 9 options, 6 transients, the scheduled purge hook and all 16 meta keys — readiness, citation-gap and entity-scan results included.
* **Fix: robots.txt no longer advertises llms.txt as a `Sitemap:`.** The `Sitemap:` directive expects an XML sitemap or a plain list of URLs; llms.txt is Markdown, so conforming crawlers reported a malformed sitemap. It is now emitted as a comment. Discovery still works through the well-known path and the `Link: rel="ai-context"` header.
* **Performance: `/llms.txt` and `/llms-full.txt` are now cached and bounded.** Both were rebuilt from scratch on every anonymous request with `nocache_headers()` and `posts_per_page => -1`, so each GET walked every published post — and `/llms-full.txt` ran full page-builder extraction on all of them. Both bodies are now cached in a transient for one hour, flushed on publish, delete and menu changes, and served with `Cache-Control: public, max-age=3600`. `/llms-full.txt` is capped at 200 items per post type via the new `aeomatic_llms_full_max_items` filter and states in its own body when it truncated.
* **Performance: running an audit no longer discards built schema.** `AEOMATIC_Schema::on_meta_changed()` invalidated the schema cache on any post-meta write, including the plugin's own readiness, citation-gap and entity-scan result keys — so every analysis run forced a schema rebuild on the next front-end request. Those keys are now in the skip list.
* **Security: the per-post citation gaps endpoint is now gated per post.** `GET /citation-gaps/{id}` checked the generic `edit_posts` capability, which let any contributor read the gap report for another author's draft. It now checks `edit_post` for the requested ID, matching every other per-post route.
* **Fix: the wizard's verify step no longer shows unconditional checkmarks.** Rows for llms.txt, AI discovery headers and Organization schema ticked green even when those features were switched off. They now reflect the actual setting.
* **Renamed to "AEOmatic — AI SEO, llms.txt & Schema Markup".** The plugin name now carries the terms people actually search the plugin directory for, and the description leads with the mechanism (making content machine-readable, then reporting what crawlers got) rather than a promise about citations. Tag slots were re-spent on terms the name does not already cover.
* **Docs: added README.md and rewrote readme.txt.** New developer README covering the readiness-vs-visibility distinction, the full feature status table, the roadmap, security and performance notes, REST endpoints, all 11 filters, extension points, build tooling, and honest "compatibility testing required" markers. readme.txt drops unsupported claims ("get cited by…", "ChatGPT skips you and cites your competitors"), corrects the audit check count from 10 to 13, corrects installation instructions that told users to re-save permalinks when the plugin already flushes rewrites automatically, and adds a section listing what the plugin does *not* do.
* **New: build tooling in `bin/`.** `build-plugin.js` packages a release behind five gates — version consistency across all four release locations, a dev-file allow-list, the required `aeomatic/` wrapper folder, forward-slash ZIP entry names, and passing static checks. `check-structure.js` and `check-references.js` stand in for `php -l` and catch dangling `AEOMATIC_*::` calls; both self-test. Fixes two packaging defects: 1.1.8 shipped `.claude/` and `.vercel/` files into a public download, and an unwrapped archive would have installed as a second plugin folder instead of updating.

= 1.1.8 =
* **Fix: SEO-plugin conflict warning no longer fires when Yoast or Rank Math has its schema module turned off.** Previously the detector treated "plugin installed & active" as "plugin is emitting schema", so users who had disabled Rank Math's Schema (Structured Data) module or set every CPT's Yoast schema page/article type to "None" still saw the yellow "Schema conflict detected" banner. The detector now inspects each plugin's own settings: Rank Math conflicts only report when `rich-snippet` is present in the `rank_math_modules` option; Yoast conflicts short-circuit to empty when its Schema Framework master switch (`wpseo.enable_schema`) is off, otherwise they are gated per-key on `wpseo_titles` — `breadcrumb` needs `breadcrumbs-enable`, `article`/`author` need at least one real `schema-article-type-*` value, and `organization` needs `company_or_person` set alongside at least one real `schema-page-type-*` value. Also adds a new `aeomatic_seo_conflicts_detected` filter so sites with unusual overrides can drop or adjust entries programmatically.
* **New: conflict detector diagnostic panel in Settings.** Under the "SEO plugin status" line there is now a collapsible "why is this warning firing?" section (admin-only) that dumps the exact `wpseo_titles` keys, `rank_math_modules` value, and computed conflict decision the detector used. Makes it possible to see at a glance which Yoast setting is still emitting schema without hand-inspecting the database. Warning banner also now includes the exact Yoast menu path required to actually disable Yoast schema (Search Appearance → Content Types → set Default Page type and Default Article type to None, plus disable breadcrumbs and clear "Company or person"), since Yoast has no single "disable schema" toggle.
* **Fix: post-type checkboxes in Settings → Content coverage now persist for custom post types.** 1.1.7 fixed the built-in `post` and `page` checkboxes by flushing the `AEOMATIC_Post_Types` in-memory map after a save, but CPTs from other plugins (WooCommerce, CPT UI, JetEngine, ACF, Pods, etc.) could still revert to unchecked after saving. Root cause was in `AEOMATIC_Settings::sanitise()` — it iterated only the raw `get_post_types( public=>true )` list at `admin_init` time, and any CPT that either (a) was filtered into the plugin's own supported-post-types list via `aeomatic_supported_post_types_list` but not registered as `public=>true` in core, or (b) registered on a hook that fires after `admin_init`, was silently dropped from the saved payload. The sanitiser now builds its slug list from three sources: the same filtered list the settings view renders, the raw public post types, and every slug submitted in `$_POST` — so no checkbox the user actually saw can be lost.
* **Hardening: `AEOMATIC_Settings::save()` now also busts the WordPress options cache and repopulates its own request-level cache with the freshly-written array.** Guards against persistent object caches (Redis, Memcached) returning the pre-save value on the remainder of the same request, and against any downstream reader seeing an intermediate `null`-cache state between `update_option()` and the next `all()` call.

= 1.1.7 =
* **Fix: post-type checkboxes in Settings → Content coverage now persist their checked state after saving.** The `AEOMATIC_Post_Types` in-memory map is populated on `init` (before the `admin_init` save handler runs) and was not being invalidated after a save, so the settings view re-rendered from a stale map and boxes appeared unchecked even though the DB was correct. `AEOMATIC_Settings::save()` now flushes the Post_Types cache immediately after `update_option()`.
* **Fix: Entity Scanner now sees ACF and page-builder content.** The Signal Collector was calling a non-existent `AEOMATIC_Content_Extractor::extract_text()` method and silently falling back to `wp_strip_all_tags( $post->post_content )` — so every text signal (keyword patterns, founding-date, NAP pattern) and every heading-based signal missed ACF fields, Elementor, Divi, Beaver, Bricks, Oxygen, and WPBakery content entirely. The collector now calls the real `AEOMATIC_Content_Extractor::extract()` API which merges all supported sources. ACF wysiwyg headings are now detected too.
* **Fix: Entity Scanner respects the Organisation name / logo / sameAs set in Settings.** The Signal Collector was reading org config from a non-existent `organization` settings section, so `organization_setting('name')` and `organization_setting('logo')` always returned empty strings — the Organization detector permanently reported "Organisation name is not set" and "Organisation logo not configured" even when both were filled in. It now reads from the correct `schema.org_name` / `schema.org_logo` / `schema.org_sameas` keys.
* **Fix: Entity Scanner LocalBusiness detector accepts per-page address.** The detector now also credits address / streetAddress / addressLocality set on the page's LocalBusiness schema via the Schema Generator sidebar, not only site-wide address settings. Recommendation text updated to reflect both paths.
* **Fix: Entity Scanner FAQ detection now covers ACF and every supported page builder.** The FAQ signal only matched Gutenberg `wp:aeomatic/faq` block markers; it now also credits FAQs surfaced by the content extractor (ACF FAQ repeaters, Elementor / Divi / WPBakery accordions, shortcode Q&As, universal HTML detection).
* **New: Entity Scanner recommendations now adapt to your editor.** The Signal Collector exposes the detected editor / page builder (Gutenberg, Classic, Elementor, Divi, Beaver Builder, Bricks, Oxygen, WPBakery) to every detector. Recommendations that used to say "insert the AEOmatic FAQ block" or "in the Schema Generator sidebar" now emit builder-appropriate wording — an Elementor site is told to add a Toggle / Accordion widget; a Divi site is told to add a Toggle / Accordion module; ACF-only sites are told to use the `aeomatic_schema_overrides` filter. Signals themselves are unchanged; only the recommendation strings adapt.
* **Improved: ACF FAQ detection now covers flexible_content layouts and nested containers.** Previous behaviour only recognised Q&As inside a **top-level ACF repeater**. It now walks the full ACF field tree, so Q&As are also picked up from: flexible_content layouts whose sub-fields include "question" + "answer"; repeaters nested inside a flexible_content layout; repeaters nested inside a group; groups whose fields directly contain "question"/"answer". Detection is name-based — any field whose name contains `question` and another whose name contains `answer` qualifies, so common variants like `faq_question` / `faq_answer` / `question_text` / `answer_text` all work.

= 1.1.6 =
* **New: Entity Scanner module.** Scans every post across four sources (content, schema, headings, metadata) for six entity types — **Organization**, **Person**, **Service**, **Product**, **LocalBusiness**, **FAQ** — and returns a coverage score, list of missing entities, and per-entity recommendations to raise confidence. New **AEOmatic → Entity Scanner** admin page and Gutenberg sidebar panel. New REST endpoint `GET /aeomatic/v1/entity-scan/{id}`. Fingerprint-cached in post meta. Filter hook `aeomatic_entity_detectors` for custom detectors. Ships one reusable signal-collector service consumed by all six detectors — no code duplication with existing extraction.

= 1.1.5 =
* **New: AI Readiness module.** A second scoring lens complementing the existing AEO Audit. Every post is scored 0–100 across five explicit categories — **Content**, **Trust**, **Authority**, **Entities**, **Technical** — with a per-category list of issues plus recommendations so you know precisely which axis needs work. Overall score is the mean of the five categories.
* **New: AEOmatic → AI Readiness admin page.** Pick any published post from the recent-posts dropdown to run the analyzer on demand, or open a post via `?post={id}`. "Recompute (skip cache)" button forces a fresh analysis.
* **New: "AI Readiness" panel in the block-editor sidebar.** Sits alongside the existing Schema, Audit, and Citation Gaps panels. Auto-loads on open, auto-refreshes after save, has its own recompute button.
* New REST endpoint: `GET /aeomatic/v1/ai-readiness/{id}?force=false` — returns `{ overall_score, categories[], fingerprint, cached }`. Same auth model as `/audit/{id}` (edit_post capability check).
* Fingerprint-based caching in post meta `_aeomatic_ai_readiness` (payload) + `_aeomatic_ai_readiness_fingerprint` (invalidation key) + `_aeomatic_ai_readiness_score` (overall score, exposed via REST). Cache auto-invalidates on `save_post`.
* Analyzer registry is filterable via `aeomatic_ai_readiness_analyzers` so third-party code can add or replace categories without patching core.
* Zero duplication: reuses `AEOMATIC_Content_Extractor`, `AEOMATIC_Schema_Analyzer`, `AEOMATIC_Schema_Generator`, `AEOMATIC_LLMs_Txt`, `AEOMATIC_Post_Types`, and `AEOMATIC_Settings`.

= 1.1.4 =
* **New: Setup Wizard.** Five-step guided onboarding runs automatically on activation. Auto-imports your organisation name, logo, and social profiles from Yoast SEO, Rank Math, SEOPress, All in One SEO, or The SEO Framework — never overwrites values you already set in AEOmatic. Live JSON-LD preview updates as you type. Fully offline; no accounts, no external calls. Re-runnable any time from the Plugins list.
* **New: WordPress dashboard widget "AEO Readiness".** A compact tile on the native wp-admin dashboard showing average AEO score, count of posts with citation gaps, top three AI-bot visitors with a mini bar chart, and log-retention health. Colour-coded score chip so status reads at a glance.
* **New: Bulk audit action on Posts/Pages/CPT list screens.** Select any rows, choose "AEOmatic — refresh audit & gaps," and recompute audit scores and citation gaps for all selected posts in one pass. Results appear in a dismissible admin notice with average scores, total gaps flagged, and an expandable per-post table. Safety cap of 50 posts per batch. Everything runs on your own server.
* **New: Hybrid crawler-log retention.** The AI bot visit log now uses a daily WP-Cron purge (default 90-day retention, configurable 7–365 days from **AEOmatic → Settings → Crawler visit log**) plus a 500-entry safety ceiling, replacing the previous fixed 200-entry cap. The new Settings section also shows live log status: entries kept, age of the oldest row, and the last purge outcome.
* **New AI crawlers recognised:** `DuckAssistBot` (DuckDuckGo AI) and `Meta-ExternalFetcher` (Meta's per-user fetcher, distinct from the training UA `meta-externalagent`). Both added to detection and to the default allow-list. Total recognised: 20 AI user agents.
* Refactor: extracted `AEOMATIC_Audit::site_summary()` and `AEOMATIC_Citation_Gaps::site_summary()` as reusable public entry points so the dashboard widget, bulk-action notice, and REST endpoints all share one code path.

= 1.1.3 =
* **New: Ask AI — "Am I cited?" panel.** A new block-editor sidebar panel with four one-click buttons — Ask ChatGPT, Ask Perplexity, Ask Google AI, Ask Claude. Each button opens the AI engine in a new tab with your post title pre-filled as the question, so you can see with your own eyes whether your site is quoted in the AI's answer. No API keys, no accounts, no external calls from your server — the plugin only builds the link; your browser does the rest. Turns "did my AEO work?" from a guess into a 5-second test.
* **New: AI Citation Gaps.** Sixteen local, rules-based checks that flag exactly why a page is unlikely to be cited by AI engines and what to change. Runs entirely on your own server — no external calls, no keys, no account.
* Five internal-coverage rules: orphan pages, low internal-link count, sibling-topic gaps by category, thin body for depth-heavy schema types, and missing "related reading" hooks.
* Eleven answer-shape rules: no direct opening answer, no question-style headings, missing FAQ block, FAQ content without FAQPage schema, HowTo content without HowTo schema, low-confidence schema type match, low image alt-text coverage, no citation hook (list/table/bold summary) near the top, missing author signal, missing meta description, and posts missing from the emitted `/llms.txt`.
* New **AEOmatic → Citation Gaps** admin subpage with a filterable, paginated table across all posts and pages, plus a scanner-style summary strip (pages scanned / with gaps / clean / average score). Sorts worst-scoring first so you can triage. Filters: title search, post type, author, severity, gap type, score range, modified date range.
* New **AI Citation Gaps** panel in the block editor sidebar — shows the top high/medium gaps for the open post with a one-line fix hint each and a one-click quick-fix button where a structural fix is possible. Available on every enabled post type (posts, pages, WooCommerce products, and any registered public CPT).
* Fingerprint-based caching on post save — results persist in post meta and only rebuild when content, title, or schema type changes.
* Filter hook `aeomatic_citation_gap_results` so power users can register their own rules.

= 1.1.2 =
* **AI crawler list updated to match Anthropic's current published user agents.** Added `Claude-User` (Anthropic's user-triggered live-fetch UA, equivalent to `ChatGPT-User`) to the default allow-list and audit detection. Removed deprecated `Claude-Web` and `anthropic-ai` from the default allow-list for new installs; they remain recognised by the visit log for backward compatibility. Source: [Anthropic crawler documentation](https://support.claude.com/en/articles/8896518).
* Existing installations do not need to re-flush permalinks.

= 1.1.1 =
* Documentation: updated readme with clearer feature breakdown, AI engines supported, and comparison guidance.
* No code changes. Existing installations do not need to re-flush permalinks.

= 1.1.0 =
* llms.txt now includes **all published pages and posts** (previously capped at 25 most recent per type; pages were skipped if not in the primary nav menu).
* New dedicated `## Pages` section in llms.txt — lists every published page (parent and child), ordered by `menu_order` then title.
* New `/llms-full.txt` virtual endpoint — emits the full markdown body of every included page and post, designed for direct LLM ingestion. Advertised from llms.txt via a `Full content:` line.
* New activity counter `llms_full_serves` records hits to the new endpoint.
* Settings copy updated to reflect the new behaviour.
* **Upgrade step**: after updating, go to Settings → Permalinks → Save Changes once to register the new `/llms-full.txt` rewrite rule.

= 1.0.0 =
* Initial public release.
* Visual schema editor in Gutenberg sidebar — auto-populates all fields from page content, every field editable.
* Intelligent schema type detection — 6 scoring rules, 22 supported schema types.
* Type-specific property extraction — Recipe ingredients/instructions, JobPosting salary, Event dates, LocalBusiness address/hours.
* Support for Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen, WPBakery, ACF.
* WooCommerce Product schema with price, availability, SKU, ratings (auto-filled, all editable).
* WebSite schema with SearchAction on homepage — enables Google sitelinks search box.
* VideoObject schema — auto-detected from YouTube and Vimeo embeds in post content.
* Speakable schema (WebPage + SpeakableSpecification) on all singular pages for voice AI.
* AI discovery HTTP headers (X-AI-Crawl, X-AI-Citeable, X-Content-License, X-AI-Content-Type, X-AI-Speakable) emitted automatically on all public pages.
* AI crawler visit log — records last 200 AI bot visits, viewable in admin under "What did we do?".
* llms.txt virtual endpoint following the llmstxt.org specification, with spec-compliant Ignore section.
* AI crawler control via robots.txt (GPTBot, ClaudeBot, Claude-SearchBot, PerplexityBot, Google-Extended, DeepSeekBot, Cohere-AI and more).
* AEO content audit engine — 10-criteria scoring with actionable suggestions in the editor sidebar.
* FAQ Gutenberg block with automatic FAQPage schema generation.
* SEO plugin conflict detection (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework).
* One-click conflict resolution — disables duplicate schema types only.
* Live verification panel — proves every feature is working with clickable links.
* Schema cache with content fingerprinting — regenerates automatically on content changes.
* Request-level settings cache — single DB read per page load.
* Deferred activity counter writes — no DB writes on front-end page views.
* All public custom post types auto-detected and supported.
