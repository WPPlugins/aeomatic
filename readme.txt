=== AEOmatic — AI based Answer Engine Optimization ===
Contributors: mmtushar
Tags: schema, seo, ai search, json-ld, llms.txt
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Get cited by ChatGPT, Claude, Perplexity and Google AI Overviews. Visual schema editor, JSON-LD generation, llms.txt, and AI crawler control.

== Description ==

AEOmatic — AI based Answer Engine Optimization is the first WordPress plugin built specifically for **Answer Engine Optimization (AEO)** — the practice of making your content visible in AI-generated answers from ChatGPT, Claude, Perplexity, Google AI Overviews, and other AI search engines.

Classic SEO gets you to page 1. AEO gets you **cited by the AI** that millions of people now use instead of search.

= What makes AEOmatic — AI based Answer Engine Optimization different =

Every other schema plugin outputs the same generic JSON-LD regardless of your content. AEOmatic — AI based Answer Engine Optimization **reads your page** and generates accurate, type-specific schema — like Webflow does — then lets you edit every property in a visual editor, without touching code.

= Core features =

**Visual Schema Editor (post editor sidebar)**
Open any post in the WordPress editor. AEOmatic — AI based Answer Engine Optimization reads your content and auto-populates every schema property. Edit any field. See the live JSON-LD output instantly. Works on all post types, all page builders.

**Intelligent schema type detection**
Analyses title, content, URL slug, categories, ACF fields, and page templates to pick the right schema type. Recipe posts get Recipe schema with ingredients, instructions, cook time, and servings. Job posts get JobPosting schema with salary ranges and employment type. Event posts get Event schema with dates and venue. 22 supported schema types.

**Type-specific property extraction**
Not just Article schema on everything. For each type, extracts the right properties:
- Recipe: ingredients list, step-by-step instructions, cook/prep time, servings, cuisine
- JobPosting: salary range (auto-detected from content), employment type, location, deadline
- Event: start/end dates, venue, organizer, attendance mode, ticket price
- Review: item reviewed, star rating, pros/cons
- Product: price, SKU, availability, ratings (auto-filled from WooCommerce)
- LocalBusiness: address, phone, opening hours, price range
- HowTo: numbered steps, tools, supplies, total time
- FAQPage: Q&A pairs from FAQ blocks or question-style headings

**Page builder support**
Reads content from all major builders — Gutenberg, Elementor, Divi, Beaver Builder, Bricks, Oxygen, WPBakery. ACF text, textarea, wysiwyg, and repeater fields are scanned automatically.

**WooCommerce integration**
Product schema with price, availability, SKU, brand, aggregate ratings, and sale date — all auto-filled from WooCommerce, all editable.

**llms.txt + llms-full.txt generator**
Serves a virtual `/llms.txt` endpoint — an AI-friendly Markdown index of your site. Tells AI crawlers what your site is about, lists your key pages, a full Pages section, and every published post grouped by type. Follows the llmstxt.org specification. Also serves a companion `/llms-full.txt` endpoint containing the complete extracted text of every published page and post, one Markdown block per item — so AI engines can ingest your full content in a single fetch.

**AI crawler control**
Per-bot Allow/Disallow in your robots.txt for 17 AI crawlers: GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, Claude-Web, Claude-SearchBot, PerplexityBot, Perplexity-User, Google-Extended, anthropic-ai, CCBot, Bytespider, Amazonbot, Applebot-Extended, meta-externalagent, DeepSeekBot, Cohere-AI.

**AI discovery headers**
Automatically emits five HTTP response headers on every public page: X-AI-Crawl, X-AI-Citeable, X-Content-License, X-AI-Content-Type, X-AI-Speakable — plus a Link header pointing to your llms.txt. These are emerging citation signals recognised by AI search engines.

**AI crawler visit log**
Records every recognised AI bot visit (bot name, URL, timestamp) in a capped log visible in the admin. See at a glance how many times GPTBot, ClaudeBot, or PerplexityBot have crawled your site.

**VideoObject schema**
Auto-detects YouTube and Vimeo embeds in post content and outputs VideoObject JSON-LD with embedUrl, thumbnailUrl, and uploadDate — no configuration needed.

**Speakable schema**
Outputs WebPage + SpeakableSpecification on every singular page, signalling to voice AI engines (Google Assistant, AI search) which part of the page to read aloud when citing your content.

**WebSite schema with SearchAction**
Outputs WebSite schema with a SearchAction on your homepage, enabling the Google sitelinks search box in search results.

**AEO content audit**
Scores every post 0–100 on 10 criteria that predict AI citation likelihood: direct opening answer, question-style headings, FAQ block present, schema enabled, author authority, internal links, structured content (tables/lists), image alt text, page weight, word count.

**FAQ Gutenberg block**
Native Gutenberg block for Q&A pairs. Renders as accessible `<details>/<summary>` HTML. Automatically generates FAQPage JSON-LD. No configuration needed.

**SEO plugin conflict detection**
Detects Yoast SEO, Rank Math, AIOSEO, SEOPress, and The SEO Framework. Shows a warning if duplicate schema would be emitted. One-click "Fix it for me" disables only the overlapping types in AEOmatic — AI based Answer Engine Optimization — your existing SEO plugin is untouched.

**Live verification**
Makes HTTP requests to your own site and shows proof of every change: llms.txt live with size and line count, robots.txt with AI bot count, schema blocks found on your latest post with direct Google Rich Results Test link, content coverage report showing which builders were detected.

**All public custom post types**
Auto-detects every CPT on your site. Applies appropriate schema to WooCommerce products, LearnDash courses, The Events Calendar events, job boards, knowledge bases, and any custom post type from themes or plugins.

= Free, forever =

All features above are free, no account required, no API keys, no data sent to external servers (except the verification tool which makes requests to your own site). A Pro tier with citation tracking is planned for the future.

== Installation ==

1. Upload the `aeomatic` folder to `/wp-content/plugins/`, or install through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu.
3. Go to **Settings → Permalinks** and click **Save Changes**. This is required once after activation so the `/llms.txt` endpoint works.
4. Go to **AEOmatic — AI based Answer Engine Optimization → Settings** and configure your Organization name, logo, and crawler preferences.
5. Open any post in the editor. Click the **AEOmatic — AI based Answer Engine Optimization** icon in the top-right toolbar to open the sidebar. You will see the detected schema type, auto-populated fields, and a live JSON-LD preview.

**After updating the plugin**, repeat step 3 (Settings → Permalinks → Save Changes) to refresh rewrite rules.

== Frequently Asked Questions ==

= Does this conflict with Yoast SEO or Rank Math? =

It can, if both plugins output the same schema types. AEOmatic — AI based Answer Engine Optimization detects active SEO plugins and shows a warning notice. The "Fix it for me" button disables only the overlapping schema types in AEOmatic — AI based Answer Engine Optimization — your Yoast or Rank Math settings are not changed. AEOmatic — AI based Answer Engine Optimization's unique features (llms.txt, AI crawler control, AEO audit, WooCommerce Product schema, visual editor) do not conflict with any SEO plugin.

= My llms.txt shows a 404. How do I fix it? =

Go to **Settings → Permalinks** and click **Save Changes**. This flushes WordPress rewrite rules and registers the `/llms.txt` virtual endpoint. If the 404 persists, check that your hosting is not serving a physical `robots.txt` or `llms.txt` file from your server root — a physical file will override WordPress's virtual endpoint.

= Does the plugin work with Elementor, Divi, or other page builders? =

Yes. AEOmatic — AI based Answer Engine Optimization has a content extractor for every major page builder: Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen, and WPBakery. It reads the builder's native data format to extract text content. ACF text, textarea, wysiwyg, and repeater fields are also scanned automatically.

= Will this slow down my site? =

No. Schema is generated once per post and cached in post meta with a content fingerprint. The cache is invalidated automatically when you save the post, change meta, switch categories, or edit a field override. There are no external API calls on the front-end. The audit engine and verification tool only run in the WordPress admin.

= What schema types does it support? =

22 types: Article, BlogPosting, NewsArticle, TechArticle, Recipe, HowTo, FAQPage, QAPage, Review, Product, Service, LocalBusiness, Event, JobPosting, Course, Book, WebPage, AboutPage, ContactPage, CollectionPage, ProfilePage, and Movie. The type is auto-detected from your content. You can override it per-post from the editor sidebar.

= Does it work with WooCommerce? =

Yes. WooCommerce products automatically receive Product schema with price, availability, SKU, brand (from product attributes), aggregate rating, and review count. Variable products get AggregateOffer schema with min/max price range. All fields are editable in the visual editor.

= How do I know the schema is actually working? =

Go to **AEOmatic — AI based Answer Engine Optimization → What did we do?**. The verification panel makes live HTTP requests to your site and shows status for llms.txt, robots.txt AI directives, JSON-LD blocks on your latest post, content coverage, and AEO scores. Click "Validate with Google" to open Google's Rich Results Test pre-loaded with your post URL.

= Can I customise the generated schema? =

Yes. In the Gutenberg editor, open the AEOmatic — AI based Answer Engine Optimization sidebar. You will see every property auto-populated from your content. Edit any field. Fields you have edited show an "Edited" badge. Click "↺ Reset" to restore a field to its auto-detected value. Click "Save schema" to persist your changes. Your edits are stored in post meta and applied on every front-end render.

== Screenshots ==

1. Site-wide AEO dashboard with score distribution and worst-performing posts.
2. Visual schema editor in the Gutenberg sidebar — auto-populated fields, live JSON-LD preview, type confidence.
3. Type-aware JSON-LD generation — Recipe schema with auto-extracted ingredients and instructions.
4. Live verification page showing proof of every change with click-to-verify links.

== Changelog ==

= 1.1.1 =
* Fix: ACF repeater rows on the front-end no longer duplicate row-0's content. The schema analyzer was calling `get_field_objects()` during `wp_head`, which poisoned ACF's internal value store before the theme rendered the repeater. Analysis is now cached in post meta and only rebuilt in safe contexts (admin, REST, cron, CLI).
* Fix: Online llms.txt validators no longer fail with "Failed to fetch". The `/llms.txt` and `/llms-full.txt` endpoints now send `Access-Control-Allow-Origin: *` so browser-based validators and AI clients can read the response cross-origin.
* New: "Block PDF files" setting under **Settings → robots.txt rules**. When enabled, AEOmatic appends `User-agent: *` + `Disallow: /*.pdf$` to robots.txt so crawlers skip PDF files site-wide.

= 1.1.0 =
* New `/llms-full.txt` virtual endpoint — serves the complete extracted text of every published page and post, one Markdown `##` block per item, for full-content AI ingestion in a single fetch
* llms.txt now includes a dedicated `## Pages` section listing all published pages
* llms.txt now lists every published post per custom post type — the previous 25-item cap is removed (bound it with the `aeomatic_llms_max_items` filter on very large sites)
* llms.txt header now includes a `Full content:` link pointing to /llms-full.txt
* New "llms-full.txt requests served" counter on the "What did we do?" page, alongside the existing llms.txt counter
* Live verification panel now checks the /llms-full.txt endpoint
* Settings: the auto-include option now reads "Include all published posts and pages in llms.txt"
* Rewrite rules now flush automatically on update so /llms-full.txt works without manually re-saving permalinks

= 1.0.0 =
* Initial public release
* Visual schema editor in Gutenberg sidebar — auto-populates all fields from page content, every field editable
* Intelligent schema type detection — 6 scoring rules, 22 supported schema types
* Type-specific property extraction — Recipe ingredients/instructions, JobPosting salary, Event dates, LocalBusiness address/hours
* Support for Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen, WPBakery, ACF
* WooCommerce Product schema with price, availability, SKU, ratings (auto-filled, all editable)
* WebSite schema with SearchAction on homepage — enables Google sitelinks search box
* VideoObject schema — auto-detected from YouTube and Vimeo embeds in post content
* Speakable schema (WebPage + SpeakableSpecification) on all singular pages for voice AI
* AI discovery HTTP headers (X-AI-Crawl, X-AI-Citeable, X-Content-License, X-AI-Content-Type, X-AI-Speakable) emitted automatically on all public pages
* AI crawler visit log — records last 200 AI bot visits, viewable in admin under "What did we do?"
* llms.txt virtual endpoint following the llmstxt.org specification, with spec-compliant Ignore section
* AI crawler control for 17 bots via robots.txt (GPTBot, ClaudeBot, Claude-SearchBot, PerplexityBot, Google-Extended, DeepSeekBot, Cohere-AI and more)
* AEO content audit engine — 10-criteria scoring with actionable suggestions in the editor sidebar
* FAQ Gutenberg block with automatic FAQPage schema generation
* SEO plugin conflict detection (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework)
* One-click conflict resolution — disables duplicate schema types only
* Live verification panel — proves every feature is working with clickable links
* Schema cache with content fingerprinting — regenerates automatically on content changes
* Request-level settings cache — single DB read per page load
* Deferred activity counter writes — no DB writes on front-end page views
* All public custom post types auto-detected and supported

== Upgrade Notice ==

= 1.1.1 =
Fixes an ACF repeater duplication bug on the front-end and a CORS issue that broke online llms.txt validators. Adds a one-click setting to block PDFs in robots.txt. After updating, save any post that uses ACF repeaters once to refresh the new caches.

= 1.1.0 =
Adds a /llms-full.txt full-content endpoint and lists all pages/posts in llms.txt. Rewrite rules refresh automatically on update; if /llms-full.txt shows a 404, go to Settings → Permalinks → Save Changes.

= 1.0.0 =
First public release. After activation, go to Settings → Permalinks → Save Changes to activate the /llms.txt endpoint.
