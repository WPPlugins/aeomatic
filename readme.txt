=== AEOmatic — Answer Engine Optimization, GEO, AIO, LLM SEO ===
Contributors: mmtushar
Tags: aeo, answer engine optimization, llms.txt, ai seo, schema
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Get cited by ChatGPT, Claude, Perplexity, and Google AI Overviews. 22 schema types, llms.txt, /llms-full.txt, AI crawler control — free.

== Description ==

AEOmatic is the complete WordPress plugin for Answer Engine Optimization (AEO) — also called Generative Engine Optimization (GEO), AI Optimization (AIO), LLM SEO, AI SEO, or ChatGPT SEO. Same job, different acronyms. Get your site cited by ChatGPT, Claude, Perplexity, and Google AI Overviews.

= Why your site is invisible to AI search =

Classic SEO plugins (Yoast, Rank Math) were built for Google's 2015 search box. They have no idea what an AI answer engine looks for. Your site likely has no AI bot allow-list, no schema for AI to parse, no answer-shaped content signals, no `llms.txt`, no `/llms-full.txt`, and no AI HTTP headers. ChatGPT and Perplexity skip you and cite your competitors.

AEOmatic fixes that. It runs alongside Yoast, Rank Math, AIOSEO, SEOPress, or The SEO Framework. Auto-imports their settings. Adds the AI visibility layer none of them cover. Never overwrites your titles, meta descriptions, canonicals, or sitemaps.

= What you get — all free, no account, no data sent to external servers =

* **Visual schema editor** for 22 types — Article, BlogPosting, NewsArticle, TechArticle, Recipe, HowTo, FAQPage, QAPage, Review, Product, Service, LocalBusiness, Event, JobPosting, Course, Book, WebPage, AboutPage, ContactPage, CollectionPage, ProfilePage, Movie. Auto-detects the right type from your content. Edit any field in the Gutenberg sidebar with a live JSON-LD preview.

* **Type-specific property extraction** — not just generic Article schema on everything. Recipe posts get ingredients and instructions extracted. JobPosting posts get salary ranges auto-detected. Event posts get start/end dates and venue. LocalBusiness gets address and opening hours.

* **Page-builder support** — reads content from Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen, WPBakery. ACF text, textarea, wysiwyg, and repeater fields are scanned automatically.

* **WooCommerce Product schema** — price, availability, SKU, brand, ratings, sale dates all auto-filled. Variable products get AggregateOffer with min/max price range.

* **`/llms.txt` generator** — serves a spec-compliant `/llms.txt` at your site root. Curated `## Key pages` section plus a full list of every published page and post.

* **`/llms-full.txt` endpoint** — a single URL that returns the full markdown body of every published page and post. AI crawlers can ingest your whole site in one HTTP GET instead of following dozens of links from your `llms.txt` index.

* **AI crawler control** — per-bot Allow/Disallow toggles in your `robots.txt` for 18 AI crawlers by default (20 recognised in the visit log): GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, Claude-User, Claude-SearchBot, PerplexityBot, Perplexity-User, Google-Extended, CCBot, Bytespider, Amazonbot, Applebot-Extended, meta-externalagent, Meta-ExternalFetcher, DeepSeekBot, Cohere-AI, DuckAssistBot. Tracks Anthropic's three current user agents (ClaudeBot, Claude-User, Claude-SearchBot) per [Anthropic's published crawler documentation](https://support.claude.com/en/articles/8896518).

* **AI HTTP discovery headers** — five emerging AI-citation headers emitted automatically on every public page: `X-AI-Crawl`, `X-AI-Citeable`, `X-Content-License`, `X-AI-Content-Type`, `X-AI-Speakable`. Plus a `Link` header pointing to your `llms.txt`.

* **AI crawler visit log** — records every recognised AI bot visit (bot name, URL, timestamp) in a capped log. See exactly how often GPTBot, ClaudeBot, or PerplexityBot crawl your site.

* **AEO content audit** — every post scored 0–100 on the 10 signals that predict AI citation: direct opening answer, question-style headings, FAQ block, schema, author authority, internal links, structured content, image alt text, page weight, word count.

* **Setup Wizard (1.1.4+)** — a five-step onboarding that runs on activation. Auto-imports organisation name, logo, and social profiles from Yoast SEO, Rank Math, SEOPress, All in One SEO, or The SEO Framework, with live JSON-LD preview and per-plugin opt-in. Never overwrites values you already set in AEOmatic. Fully offline.

* **WordPress dashboard widget (1.1.4+)** — "AEO Readiness" tile on the native wp-admin dashboard: average AEO score, posts with citation gaps, top three AI-bot visitors with a mini bar chart, and log-retention health.

* **Bulk audit action (1.1.4+)** — one bulk action on Posts/Pages/CPT list screens re-runs the audit and citation-gap analysers across selected rows. Admin notice shows average scores, total gaps flagged, and a per-post score table.

* **Ask AI — "Am I cited?" (1.1.3+)** — four one-click buttons in the editor sidebar (ChatGPT, Perplexity, Google AI, Claude). Opens each engine in a new tab with your post title pre-filled as the question, so you can check for yourself whether your site is quoted in the AI answer. No API keys, no accounts, no external calls from your server — just links your browser opens.

* **AI Citation Gaps (1.1.3+)** — sixteen local, rules-based checks that flag exactly why a page won't get cited by AI engines: orphan pages, missing opening answer, no FAQ block, schema type mismatch, low internal-link count, sibling-topic gaps by category, and more. Runs per-post in the block editor sidebar and site-wide from **AEOmatic → Citation Gaps**. Each gap ships with a one-line fix hint plus a one-click quick-fix where possible. Fully offline; no external calls.

* **FAQ Gutenberg block** — native Q&A block with accessible `<details>/<summary>` HTML and automatic FAQPage JSON-LD. No configuration needed.

* **VideoObject schema** — auto-detected from YouTube and Vimeo embeds in your content.

* **Speakable schema** — signals to voice AI engines which part of the page to read aloud when citing your content.

* **WebSite schema with SearchAction** — enables Google's sitelinks search box on your homepage.

* **SEO plugin conflict detection** — detects Yoast SEO, Rank Math, AIOSEO, SEOPress, and The SEO Framework. Shows a warning if duplicate schema would be emitted. One-click "Fix it for me" disables only the overlapping types in AEOmatic; your existing SEO plugin is untouched.

* **Live verification panel** — makes HTTP requests to your own site and shows proof of every change: llms.txt live with size and line count, robots.txt with AI bot count, schema blocks on your latest post with a direct Google Rich Results Test link.

= AI engines supported =

* **ChatGPT** and **ChatGPT Search** (OpenAI) — GPTBot, OAI-SearchBot, ChatGPT-User
* **Claude** and **Claude Search** (Anthropic) — ClaudeBot, Claude-User, Claude-SearchBot
* **Perplexity AI** — PerplexityBot, Perplexity-User
* **Google AI Overviews** and **Gemini** — Google-Extended (Gemini training control) plus Googlebot (used for AI Overviews retrieval)
* **Microsoft Copilot** and **Bing AI** — indexed via Bingbot (Copilot has no dedicated AI user agent; it retrieves from Bing's index)
* **Meta AI** (LLaMA) — meta-externalagent
* **DeepSeek** — DeepSeekBot
* **Apple Intelligence** — Applebot-Extended
* **Cohere** — Cohere-AI
* **Common Crawl** — CCBot

= No external dependencies on the front-end =

Schema and metadata generate server-side and cache in post meta. No external API calls when a visitor loads a page. The audit engine and verification tool only run inside the WordPress admin. No data sent to external servers.

= How AEOmatic compares to other AEO plugins =

* **Free forever, no account.** The full toolkit works without signing up. No monthly credit limits on the core feature set.
* **Runs entirely on your own server.** Schema, `llms.txt`, `/llms-full.txt`, AI HTTP headers, and audit all generate server-side and cache in post meta. Zero external API calls on the front-end. No content sent to vendor clouds.
* **`/llms-full.txt` alongside the rest of the toolkit.** A single URL that returns the full markdown body of every published page and post — packaged with schema, crawler control, audit, and HTTP headers in one plugin.
* **22 schema types.** Many AEO plugins ship 6–10. Recipe, JobPosting, Event, LocalBusiness, Movie, Book — all auto-detected.
* **8 page builders.** Most plugins read Gutenberg only. AEOmatic extracts content from Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen, and WPBakery.

= Who AEOmatic is for =

* **Publishers and media** who want to be quoted by AI as a source
* **SaaS companies** who want ChatGPT to recommend their software
* **Local businesses** who want to appear in AI location answers
* **E-commerce stores** (WooCommerce + EDD compatible) who want AI shopping visibility
* **Agencies** managing AEO across many client sites
* **Solo creators and consultants** who want to finally be visible in AI search

== Installation ==

1. Upload the `aeomatic` folder to `/wp-content/plugins/`, or install through **Plugins → Add New → Upload Plugin**, or install directly from **Plugins → Add New** searching for "AEOmatic".
2. Activate the plugin through the **Plugins** menu.
3. Go to **Settings → Permalinks** and click **Save Changes**. This is required once after activation so the `/llms.txt` and `/llms-full.txt` endpoints work.
4. Go to **AEOmatic → Settings** and configure your Organization name, logo, and crawler preferences.
5. Open any post in the editor. Click the **AEOmatic** icon in the top-right toolbar to open the sidebar. You will see the detected schema type, auto-populated fields, and a live JSON-LD preview.

**After updating the plugin**, repeat step 3 (Settings → Permalinks → Save Changes) to refresh rewrite rules.

== Frequently Asked Questions ==

= What is Answer Engine Optimization (AEO)? =

AEO means structuring your content so AI answer engines (ChatGPT, Perplexity, Claude, Google AI Overviews, Gemini) can find it, understand it, and cite it. Also called GEO (Generative Engine Optimization), AIO (AI Optimization), LLM SEO, AI SEO, or ChatGPT SEO. Same shift, different acronyms — from optimising for search result links to optimising for AI-generated answers.

= How does AEOmatic compare to other AEO plugins? =

AEOmatic ships a broad free toolkit — 22 schema types, 8 page-builder integrations, 16 AI crawler controls, 5 AI HTTP headers, content audit, and `/llms-full.txt` — all generated on your own server with no external API calls on the front-end. Some paid alternatives offer Citation Trackers that poll ChatGPT, Perplexity, Claude, and Gemini to verify whether they cited you; AEOmatic does not have that feature today. Pick the plugin that matches the outcome you need most.

= Does this conflict with Yoast SEO or Rank Math? =

It can, if both plugins output the same schema types. AEOmatic detects active SEO plugins and shows a warning notice. The "Fix it for me" button disables only the overlapping schema types in AEOmatic — your Yoast or Rank Math settings are not changed. AEOmatic's AEO-specific features (llms.txt, /llms-full.txt, AI crawler control, AEO audit, AI HTTP headers, visual schema editor) sit alongside classic SEO plugins without conflict.

= My llms.txt or /llms-full.txt shows a 404. How do I fix it? =

Go to **Settings → Permalinks** and click **Save Changes**. This flushes WordPress rewrite rules and registers the virtual endpoints. If the 404 persists, check that your hosting is not serving a physical `robots.txt`, `llms.txt`, or `llms-full.txt` file from your server root — a physical file will override WordPress's virtual endpoint.

= What is /llms-full.txt? =

A virtual URL AEOmatic serves at `/llms-full.txt` that returns the full markdown body of every published page and post in a single HTTP response. AI crawlers can ingest your whole site in one fetch instead of following 50 links. AEOmatic shipped `/llms-full.txt` in version 1.1.0 (June 2026) as part of the free tier, packaged alongside schema, crawler control, audit, and AI HTTP headers.

= Does the plugin work with Elementor, Divi, or other page builders? =

Yes. AEOmatic has a content extractor for every major page builder: Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen, and WPBakery. It reads each builder's native data format to extract text content. ACF text, textarea, wysiwyg, and repeater fields are also scanned automatically.

= Will this slow down my site? =

No. Schema is generated once per post and cached in post meta with a content fingerprint. The cache is invalidated automatically when you save the post, change meta, switch categories, or edit a field override. There are no external API calls on the front-end. The audit engine and verification tool only run in the WordPress admin.

= What schema types does it support? =

22 types: Article, BlogPosting, NewsArticle, TechArticle, Recipe, HowTo, FAQPage, QAPage, Review, Product, Service, LocalBusiness, Event, JobPosting, Course, Book, WebPage, AboutPage, ContactPage, CollectionPage, ProfilePage, and Movie. The type is auto-detected from your content. You can override it per-post from the editor sidebar.

= Does it work with WooCommerce? =

Yes. WooCommerce products automatically receive Product schema with price, availability, SKU, brand (from product attributes), aggregate rating, and review count. Variable products get AggregateOffer schema with min/max price range. All fields are editable in the visual editor.

= How do I know the schema is actually working? =

Go to **AEOmatic → What did we do?**. The verification panel makes live HTTP requests to your site and shows status for llms.txt, /llms-full.txt, robots.txt AI directives, JSON-LD blocks on your latest post, content coverage, and AEO scores. Click "Validate with Google" to open Google's Rich Results Test pre-loaded with your post URL.

= Does the plugin send my data anywhere? =

No. AEOmatic makes zero external API calls on the front-end. Schema is generated server-side and cached. The verification panel only makes HTTP requests to your own site to confirm the public output. No content, no analytics, no telemetry is sent off-site.

= Is this white-hat SEO? =

Yes. AEOmatic produces standards-compliant schema, `llms.txt`, `robots.txt` directives, and HTTP headers. Nothing cloaked, no doorway pages, no manipulation. Just the structured signals AI engines and Google's guidelines explicitly recommend.

= Will it help me rank in Google AI Overviews and ChatGPT? =

Yes, that's the whole point. AEOmatic generates the structured signals (schema, llms.txt, /llms-full.txt, AI HTTP headers, answer-shaped content scoring) that AI engines use to identify and cite trustworthy sources. We can't guarantee placement (no plugin can), but we give you every signal those engines look for.

= What is the new AI Citation Gaps feature? =

A local, rules-based analyser (1.1.3+) that runs 16 checks on every post and page and flags the specific reasons a page is unlikely to be cited by AI engines — orphan pages, missing opening answers, FAQ content without FAQPage schema, low-confidence schema types, missing meta descriptions, missing from llms.txt, and more. Each gap comes with a one-line fix hint and — for structurally fixable gaps — a one-click quick-fix button. Available in the block editor sidebar per-post and as a site-wide **AEOmatic → Citation Gaps** table sorted worst-first. No external API calls; no keys, no account. Deeper LLM-powered gap analysis is planned for the Pro tier separately.

= What happens when I uninstall AEOmatic? =

Deactivating the plugin stops the AI crawler visit log, unregisters the `/llms.txt` and `/llms-full.txt` endpoints, and removes the injected schema, HTTP headers, and robots.txt AI directives. Your posts, pages, WooCommerce products, and any content you authored remain untouched. **Uninstalling** (deleting via **Plugins → Delete**) additionally removes AEOmatic's own options and per-post schema cache. It does not remove your Yoast, Rank Math, AIOSEO, or SEOPress settings — those were never modified.

== Screenshots ==

1. Site-wide AEO dashboard with score distribution and worst-performing posts.
2. Visual schema editor in the Gutenberg sidebar — auto-populated fields, live JSON-LD preview, type confidence.
3. Type-aware JSON-LD generation — Recipe schema with auto-extracted ingredients and instructions.
4. Live verification panel showing proof of every change with click-to-verify links and a direct Google Rich Results Test link.

== Privacy Policy ==

AEOmatic does not collect, store, or transmit any personal data to external servers. Schema, `llms.txt`, `/llms-full.txt`, and AI HTTP headers are generated server-side on your own site. The verification panel makes HTTP requests only to your own site to confirm the public output. The AI crawler visit log records bot user-agent strings, requested URLs, and timestamps in your own database — this is bot metadata, not visitor data, and never leaves your site. AEOmatic makes no external API calls on the front-end and ships with no third-party analytics or telemetry.

== Changelog ==

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

== Upgrade Notice ==

= 1.1.4 =
Adds a Setup Wizard (5 steps with live JSON-LD preview and auto-import from Yoast/Rank Math/SEOPress/AIOSEO/The SEO Framework), a WordPress dashboard widget with score + gap count + bot chart, a bulk "refresh audit & gaps" action on Posts/Pages list screens, hybrid 90-day/500-entry log retention, and two new AI crawlers (DuckAssistBot, Meta-ExternalFetcher). Fully offline. No re-flush required.

= 1.1.3 =
Adds "Ask AI: Am I cited?" — one-click buttons in the editor sidebar that open ChatGPT, Perplexity, Google AI, and Claude with your post title pre-filled, so you can verify AI citations yourself. Also adds AI Citation Gaps: 16 local rules with one-click quick-fixes, per-post sidebar panel, and a site-wide Citation Gaps admin page (title search, author filter, score range, date range). Fully offline; no external calls from your server. No re-flush required.

= 1.1.2 =
Anthropic crawler list corrected: adds Claude-User (the dominant Anthropic live-fetch UA), removes deprecated Claude-Web and anthropic-ai from the default allow-list. No re-flush required.

= 1.1.1 =
Documentation refresh. No code changes. No re-flush required.

= 1.1.0 =
llms.txt now includes ALL published posts and pages plus a new /llms-full.txt endpoint with full content. Go to Settings → Permalinks → Save Changes after updating.

= 1.0.0 =
First public release. After activation, go to Settings → Permalinks → Save Changes to activate the /llms.txt endpoint.
