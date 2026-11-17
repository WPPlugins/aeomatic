=== AEOmatic – AEO for AI Search: llms.txt, Schema & AI Crawler Check ===
Contributors: mmtushar
Tags: aeo, answer engine optimization, llms-txt, gptbot, ai crawler
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Can ChatGPT, Perplexity and Google AI read your site? Check AI crawler access, add llms.txt and schema, fix AEO gaps. Free, no API key.

== Description ==

**Can ChatGPT, Perplexity and Google's AI actually read your WordPress site?**

https://www.youtube.com/watch?v=8MZICDVxbFc

**How AEOmatic works:**

https://www.youtube.com/watch?v=tESLUHAR_R8

Many sites are invisible to AI search for reasons their owners never see: a CDN, firewall or security plugin quietly blocking AI crawlers; content that only appears after JavaScript runs, which the ChatGPT, Claude and Perplexity crawlers do not execute; or pages with nothing a machine can parse. AEOmatic checks for each of these, fixes what a plugin can fix, and tells you plainly about the rest.

Answer Engine Optimization (AEO), also called Generative Engine Optimization (GEO), LLM SEO or AI search optimization, is mostly good SEO done carefully, plus a few checks that are specific to AI. AEOmatic covers that AI layer and runs beside Yoast SEO, Rank Math, All in One SEO or SEOPress without touching their settings.

= What you find out in the first few minutes =

* **Whether AI crawlers get your real pages.** AEOmatic requests your pages as GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, PerplexityBot, Googlebot and Bingbot, and compares each answer with a normal browser. A block, a bot-challenge page or a 404 shows up by name.
* **What AI actually reads on a page:** the text a crawler that does not run JavaScript receives, and the sentences that only appear after JavaScript runs.
* **Your AI readiness score** and the three fixes worth doing first, each linked to the page that needs it.
* **Which posts are hard to quote:** answers buried under filler, missing author signals, orphan pages, thin content. Some have a one-click fix.

= Why site owners trust it =

* **No promises it cannot keep.** No plugin can guarantee a citation in ChatGPT or Google AI Overviews, and AEOmatic does not claim to. It measures readiness, the part you control, and the dashboard is labelled that way.
* **Every score shows its working.** Each check says whether it is a direct check or a heuristic.
* **Honest about llms.txt.** Google has said it does not use llms.txt, and AI crawlers rarely request it so far. AEOmatic generates it automatically because it costs nothing to keep and some AI tools can use it, not because it is a ranking switch.
* **Your data stays on your server.** No account, no API key, no telemetry, and no external calls on the front end. The AI crawler log stores no IP addresses.
* **Safe beside your SEO plugin.** It never edits your titles, meta descriptions, canonicals, sitemaps or another plugin's settings, and it resolves schema overlap on its own side.
* **Clean uninstall.** Deleting the plugin removes everything it created.

= What it does =

It does three jobs:

1. **Publishes the signals AI systems read.** JSON-LD structured data across 21 schema types, an `llms.txt` index at your site root, a `/llms-full.txt` full-text endpoint, and per-bot crawl directives in `robots.txt`.
2. **Scores how extractable your content is.** A site-wide AI readiness score across three categories, plus per-post audits that name the specific thing to fix rather than handing you a number.
3. **Reports what actually happened.** A server-side log of every recognised AI crawler request — which bot, which URL, and the HTTP status your site returned. If you are quietly serving 404s to GPTBot, this is where you find out.

= It reads your page builder, not just Gutenberg =

Most AI SEO plugins parse `post_content` and stop. If your pages are built in Elementor or Divi, or your copy lives in ACF fields, that means your schema and your scores are computed against an almost-empty string.

AEOmatic runs one normalising extraction pass that understands **Gutenberg, the Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen and WPBakery**, plus ACF text, textarea, wysiwyg, repeater and flexible-content fields. Everything downstream — schema generation, the audit, readiness scoring, `llms.txt` — reads that single extraction, so a page assembled in a builder is scored on what it actually renders.

= Built to sit beside your SEO plugin, not replace it =

AEOmatic writes only its own settings and its own post meta. It never touches your titles, meta descriptions, canonicals or XML sitemaps, and it never modifies another plugin's configuration.

It detects Yoast SEO, Rank Math, All in One SEO, SEOPress and The SEO Framework, can import your organisation details from whichever you run, and flags the case where two plugins would emit the same JSON-LD type on one page — with a one-click fix that adjusts **AEOmatic's** output, never theirs.

= Check what AI crawlers actually get =

Settings pages tell you what you asked for. **AI Access** tells you what happens:

* **Crawler check** — reads the robots.txt your site is really serving (including a physical file that overrides WordPress), then requests your home page and recent pages as OAI-SearchBot, ChatGPT-User, Claude-SearchBot, PerplexityBot, Googlebot, Bingbot, GPTBot, ClaudeBot and more, and compares each answer with a normal browser. A firewall, CDN or security plugin that blocks AI crawlers, a bot challenge page, or a crawler-specific noindex shows up by name.
* **What AI sees** — ChatGPT, Claude and Perplexity crawlers do not run JavaScript. This shows the text they get for a page and lists the sentences from your editor that only appear after JavaScript runs.

= Everything included, no account and no API key =

* **AI readiness audit, three levels.** Site-wide across three categories (Technical readiness, Content readiness, AI crawler access), per post across five categories (Content, Trust, Authority, Entities, Technical), and a per-post 0–100 content rubric of thirteen weighted checks. Every category states whether it is a direct check or a heuristic.

* **Visual schema editor for 21 types** — Article, BlogPosting, NewsArticle, TechArticle, Recipe, Review, Product, SoftwareApplication, Service, LocalBusiness, Event, JobPosting, Course, Dataset, Book, Movie, WebPage, AboutPage, ContactPage, CollectionPage, ProfilePage. Fields Google requires are marked, and every node links by @id to one Organization, WebSite and WebPage. The type is auto-detected from your content and every field is editable in the block-editor sidebar with a live JSON-LD preview.

* **Type-specific property extraction** — Recipe posts get ingredients and instructions, JobPosting posts get salary ranges, Event posts get dates and venue, LocalBusiness gets address and opening hours. Not generic Article schema on everything.

* **`/llms.txt` generator** — a spec-compliant Markdown index at your site root: site summary, curated key pages from your nav menu, then every published item grouped by post type.

* **`/llms-full.txt` endpoint** — the extracted body text of your published content at one URL, so an AI crawler can ingest the site in a single request. Bounded and cached so it cannot become a load problem.

* **AI crawler control** — per-bot Allow/Disallow toggles in `robots.txt` for 18 AI crawlers: GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, Claude-User, Claude-SearchBot, PerplexityBot, Perplexity-User, Google-Extended, CCBot, Bytespider, Amazonbot, Applebot-Extended, meta-externalagent, Meta-ExternalFetcher, DeepSeekBot, Cohere-AI, DuckAssistBot. Twenty user agents recognised in the visit log.

* **AI crawler log and breakdown** — which AI bots requested which URLs, what HTTP status you served them, and when. A 404 or 403 being served to OAI-SearchBot is the kind of thing you want to know, so an **Errors only** filter lists exactly which URLs failed. Records bot name, URL, user agent and status only: **no IP addresses, no cookies, no visitor data**, nothing leaves your server.

* **Page-builder support** — content is read from Gutenberg, Classic Editor, Elementor, Divi, Beaver Builder, Bricks, Oxygen and WPBakery, plus ACF text, textarea, wysiwyg, repeater and flexible-content fields. Scoring reflects what your pages actually render.

* **WooCommerce Product schema** — price, availability, SKU, brand, ratings and sale dates auto-filled; variable products get AggregateOffer with a min/max range.

* **Answer-first fixes** — finds answers buried below filler under a question heading ("What is X?" answered in the fourth sentence) and moves them to the top of the section in one click on block-editor and Classic posts. WordPress keeps a revision, so you can undo it.

* **AI Citation Gaps** — seventeen local rules-based checks that name why a page is unlikely to be quoted: orphan pages, no opening answer, thin body, no FAQ block, no question heading, schema type mismatch, missing author signal, missing from llms.txt, low outbound links, low alt coverage, no citation hook, no related links, sibling-topic gaps by category, question headings without a snippet-length answer, steps not in a numbered list, missing meta description, buried answers. Three ship a one-click fix.

* **Entity Scanner** — scans content, schema, headings and metadata for Organization, Person, Service, Product, LocalBusiness and FAQ entities, returning a coverage score with per-entity confidence, detected signals, missing signals and recommendations.

* **FAQ block** — a native Q&A block producing accessible `<details>`/`<summary>` markup, written for featured snippets. The visible Q&A carries lightweight schema.org FAQPage microdata; no FAQPage JSON-LD is added. `[aeo_faq]` / `[aeo_qa]` shortcodes for the Classic Editor.

* **Setup wizard with a real first audit** — six skippable steps: welcome, import from your existing SEO plugin, organisation details, AI crawler access, **your first readiness audit**, verify. The audit step shows your score and the three highest-value fixes with direct links, so you finish onboarding knowing what to do next.

* **SEO plugin conflict detection** — detects Yoast SEO, Rank Math, All in One SEO, SEOPress and The SEO Framework, and checks whether each is *currently emitting* overlapping schema. One click disables only the overlapping types **in AEOmatic**; your other plugin's configuration is never modified.

* **AI HTTP discovery headers** — `X-AI-Crawl`, `X-AI-Citeable`, `X-Content-License`, `X-AI-Content-Type`, `X-AI-Speakable` headers plus a `Link: rel="ai-context"` pointer to your llms.txt. These are proposed conventions, not a ratified standard, and no AI vendor has committed to reading them. They are on by default and can be switched off under **AEOmatic → Settings → AI discovery headers**. `X-Content-License` (free-to-cite-with-attribution) and `X-AI-Content-Type` (human-authored) state a licence and an authorship claim for your content, so they are **off unless you turn them on** in the same section.

* **Ask AI tab** — type a question, then open ChatGPT, Perplexity, Google AI or Claude in a new tab with it pre-filled, to see for yourself how those engines answer. The plugin only builds the link; nothing is sent from your server.

* **Live verification panel** — makes HTTP requests to your own site and shows what is actually being served: llms.txt with size and line count, robots.txt with AI bot count, schema blocks on your latest post with a direct Google Rich Results Test link.

* **VideoObject, Speakable, WebSite and WebPage schema**, an author-profile panel, a wp-admin dashboard widget, and a bulk re-audit action on post list screens.

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
* **Amazon** — Amazonbot
* **ByteDance** — Bytespider
* **Common Crawl** — CCBot

Controlling a crawler's access controls whether it can read you. It does not control whether an engine cites you.

= Runs entirely on your own server =

Schema and metadata generate server-side and cache in post meta. There are no external API calls on the front end, no account, no API key, no credit limits, and no content sent to a vendor cloud. The verification panel only ever requests URLs on your own site, and only for logged-in administrators. No third-party analytics, no telemetry.

= Who it is for =

* **Business owners and bloggers** who see AI answers replacing clicks and want to know whether AI can even read their site
* **SEO consultants and agencies** who need evidence to show clients, not a vanity score, and a repeatable audit across sites
* **Elementor, Divi, Bricks and ACF sites** that tools reading only the post content score against a nearly empty page
* **Publishers and media** who want to be quoted by AI as a source
* **Businesses and SaaS companies** who want their entity data to be unambiguous to AI systems
* **Local businesses and WooCommerce stores** who need correct LocalBusiness and Product data
* **WordPress developers** — 19 filters, a documented REST namespace, and analyzer interfaces to extend

== Installation ==

1. Install from **Plugins → Add New** and search for "AEOmatic", or upload the `aeomatic` folder to `/wp-content/plugins/`, or use **Plugins → Add New → Upload Plugin**.
2. Activate through the **Plugins** menu. Activation registers the `/llms.txt` and `/llms-full.txt` endpoints and flushes rewrite rules for you.
3. The setup wizard opens automatically. Six skippable steps, ending with your first readiness audit and top three fixes.
4. Visit **AEOmatic → Dashboard** for your site AI readiness score, category breakdown, top recommendations and AI crawler activity.
5. Open any post in the block editor and click the AEOmatic icon in the top-right toolbar for the schema editor, audit breakdown, citation gaps, AI readiness and entity coverage.

**Requirements:** WordPress 6.4+, PHP 7.4+, and pretty permalinks (any structure other than "Plain").

You do **not** normally need to re-save permalinks after installing or updating — the plugin flushes rewrite rules on activation and again automatically when it detects a version change. If `/llms.txt` returns a 404, confirm **Settings → Permalinks** is not set to "Plain", then click **Save Changes** there once.

== Frequently Asked Questions ==

= Will this get me cited by ChatGPT or ranked in AI search? =

No plugin can promise that, and AEOmatic does not. What it does is fix the technical and content signals you control: crawler access, parseable structured data, an llms.txt index, and content shaped so an answer can be extracted. Whether an AI engine then cites you depends on that engine's own ranking and retrieval, which nobody outside those companies controls. Any plugin promising guaranteed AI citations is guessing.

= Is AEO different from SEO? =

Mostly not, and be careful with anyone who says otherwise. Google says AI Overviews and AI Mode use the same crawling and indexing as normal search, with no special AI markup required. What is different is practical: CDNs and firewalls often block AI crawlers while letting Googlebot through, most AI crawlers do not run JavaScript, and AI answers are lifted from pages that state the answer plainly. AEOmatic focuses on those differences.

= Does llms.txt actually help? =

It is not a ranking factor. Google does not use it, and public crawl data shows AI crawlers rarely request it so far. Some AI tools can use it when they are pointed at a site. AEOmatic generates and updates it automatically, so it costs you nothing to have, and you can switch it off under **AEOmatic → Settings**.

= I already use Yoast SEO or Rank Math. Do I need this? =

They handle classic SEO, and AEOmatic does not replace them. It adds checks they are not built around: whether AI crawlers really get through your CDN or firewall, what a crawler that does not run JavaScript reads, which AI bots visit and what status they get, and how easily an answer can be quoted from each post.

= Is it free? =

Yes. Everything described on this page is in this plugin, with no account, no API key and no usage limits.

= What is the difference between AI readiness and AI visibility? =

AI readiness is how well-prepared your site is — measurable from your own database and settings, which is what AEOmatic scores. AI visibility is whether AI systems actually surface or cite you — measurable only by querying those engines from outside your site, which AEOmatic does not do. The dashboard is labelled "AI Readiness" for that reason.

= Why does the crawler check say a crawler is blocked when the crawler log shows it visiting? =

The check sends requests from your own server using each crawler's name. Some firewalls also verify a crawler's IP address, so they block this test while letting the real crawler in. When the AI crawler log shows successful real visits from that crawler, the check says so and marks it for review instead of blocked.

= Does the crawler check contact any AI company? =

No. Every request goes to your own site. The test requests are tagged so the AI crawler log does not record them as real visits.

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

Twenty-one page-level types (Article, BlogPosting, NewsArticle, TechArticle, Recipe, Review, Product, SoftwareApplication, Service, LocalBusiness, Event, JobPosting, Course, Dataset, Book, Movie, WebPage, AboutPage, ContactPage, CollectionPage, ProfilePage) plus Organization, WebSite, WebPage, Author/Person, Breadcrumb, VideoObject and Speakable. FAQPage and HowTo JSON-LD and the sitelinks search box are not emitted: Google no longer shows them. The FAQ block keeps FAQPage microdata on its visible questions and answers.

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

1. AI Readiness dashboard — overall score, technical, content and AI crawler access cards labelled direct check or heuristic, and the top recommendations.
2. AI Citation Gaps — site-wide table of pages to fix, filterable by post type, author, severity, gap type, score and date.
3. Per-post AI readiness — five categories (Content, Trust, Authority, Entities, Technical), each with issues and recommendations.
4. What did we do? — live verification of llms.txt, llms-full.txt, robots.txt and JSON-LD on your own site, with a Google Rich Results Test link.
5. Setup wizard — welcome, organisation details with a live JSON-LD preview, AI crawler choices, your first audit, and verification.
6. Entity Scanner — coverage for Organization, Person, Service, Product, LocalBusiness and FAQ entities.
7. Schema tab in the editor sidebar — detected schema type with confidence, editable fields and the live JSON-LD.
8. Readiness tab in the editor — the per-post readiness breakdown while you write.
9. Audit tab — a 0–100 content score from thirteen weighted checks, each with a hint.
10. Gaps tab — the top citation gaps for the open post, with a one-click fix where one exists.
11. Entities tab — entity coverage for the open post.
12. Ask AI tab — opens ChatGPT, Perplexity, Google AI or Claude with your question so you can check their answers yourself.

== Privacy Policy ==

AEOmatic does not collect, store, or transmit any personal data to external servers.

Schema, `llms.txt`, `/llms-full.txt` and the AI discovery headers are generated server-side on your own site. The verification panel makes HTTP requests only to your own site, only for logged-in administrators, to confirm public output.

The AI crawler visit log records, in your own database only: the matched bot name, the requested URL, the user-agent string (truncated to 255 characters), the HTTP status your site returned, and a timestamp. It records **no IP addresses, no cookies, no referrers and no human-visitor data**, and only logs requests whose user agent matches a known AI crawler. Retention is configurable from 7 to 365 days (default 90) with a 500-entry ceiling and a daily purge. Nothing in the log ever leaves your site.

AEOmatic makes no external API calls on the front end and ships with no third-party analytics or telemetry.

== Upgrade Notice ==

= 1.3.1 =
Adds a review nudge at the end of the setup wizard — a small purple card on the final "You're live" step with one button that opens WordPress.org in a new tab. No scoring, schema or output changes. Shares the existing dismiss state with the day-7 admin notice, so you are never asked twice. Safe update.

= 1.3.0 =
Security release on top of 1.2.9. Fixes a script injection in the JSON-LD preview, password-protected posts leaking into /llms-full.txt, bulk audit running on posts the user cannot edit, missing nonce on review-notice dismiss, and crawler-log URLs growing without bound. robots.txt now keeps your other rules for AI crawlers, respects "Discourage search engines", and the Block PDF files toggle can be turned off again. Entities and Authority scores corrected, non-Latin scripts are now counted as words, Citation Gaps and Rescan work on plain permalinks, and the "Set schema type" quick fix is reinstated. The 1.2.9 intro video on the setup wizard and AI Readiness dashboard is kept. Scores may change — click Re-run in the Audit tab or update a post to refresh. Recommended update.

= 1.2.9 =
Adds a short intro video to the setup wizard and the AI Readiness dashboard. No changes to scores or settings.

= 1.2.8 =
Page-builder pages are no longer counted twice, and builder settings, synced patterns and post-listing blocks are now read. The AI crawler log now shows which requests got an error. Scores may change on builder pages; re-run the audit.

= 1.2.7 =
ACF relationship, post object, page link and link fields now count as internal links, the internal-links check counts relative links and each page once, and the speakable "No matches found" validator error is fixed. Re-run the audit to refresh scores.

= 1.2.6 =
The licence and "human-authored" AI headers are now opt-in, so AEOmatic no longer states either on your behalf; turn them back on in Settings if they are true for your site. Also removes a predicted citation timeline and other overstated claims from the admin screens.

= 1.2.5 =
Fixes the Search Console error "Missing field 'item' (in 'itemListElement')" in breadcrumb schema on author, date and post-type archives. Recommended update.

= 1.2.4 =
Schema now follows Google's current rules, with new SoftwareApplication and Dataset types and fuller Organization details. New AI Access screen shows whether AI crawlers can really reach your pages, and a one-click fix moves buried answers to the top of their section. Now runs on PHP 7.4. Fixes llms.txt redirects and escaping, and cached results breaking when text contained quotes. Recommended update.

= 1.2.3 =
Edit schema inside Elementor, Beaver Builder, Bricks, Divi, Oxygen and WPBakery, and a wider Gutenberg sidebar. Fixes the schema type dropdown and edited fields going blank after saving.

= 1.2.2 =
New tab layout for the visual editor, and Classic Editor users now get the full editor as a metabox. Fixes the JSON-LD preview not updating in real time, and the setup wizard "not allowed to access" error on WordPress 6.5+ / Playground.

= 1.2.1 =
Cosmetic but worth having: JSON-LD is now pretty-printed and no longer escapes ampersands, slashes and accented characters. Purely an output-formatting change — the schema itself is unchanged.

= 1.2.0 =
Fixes custom post types not staying selected under Settings → Content coverage (your saved selections were never lost — only mis-displayed, so nothing needs re-saving). Also important for Elementor, Divi, Bricks, Oxygen, Beaver Builder and WPBakery sites: readiness checks were reading post_content directly and scoring builder pages as empty. Expect scores to rise after updating. Also fixes post types whose slug is not plain lowercase being impossible to disable, and a settings save leaving the upgrade routine re-flushing rewrite rules on every admin page load.

= 1.1.9 =
Fixes page-builder and ACF pages being scored as empty across six readiness checks — expect scores to rise on Elementor, Divi, Bricks, Oxygen, Beaver Builder and WPBakery sites. Renamed, with a new AI Readiness dashboard, an AI crawler log that records the HTTP status served to each bot, and a first-audit onboarding step. Fixes two readiness checks that were silently degraded, tightens the per-post citation-gaps permission check, caches and bounds /llms.txt and /llms-full.txt, and makes uninstall remove everything it should. Recommended update.

= 1.1.8 =
Conflict detection now respects your SEO plugin's own schema settings, and custom post type coverage saves reliably. Recommended update.

== Changelog ==

= 1.3.1 =

* **New: review nudge at the end of the setup wizard.** The final "You're live — here's the proof" step shows a small purple card inviting a WordPress.org review, with one button that opens the review form in a new tab. It shares the same dismiss state as the existing day-7 admin notice (`aeomatic_review_dismissed`), so once you review (or dismiss) you are never asked twice. No scoring, schema or front-end output changes.

= 1.3.0 =

Rolls the 1.2.9 intro-video addition together with the security, robots.txt and score fixes shipped on top of it.

**Kept from 1.2.9**

* **Intro video.** The setup wizard's welcome step and the AI Readiness dashboard link to a short intro video. It opens on YouTube in a new tab, so the plugin still makes no external calls.

**Security**

* **Fix: script injection in the JSON-LD preview.** A user with the Contributor role could save schema values that ran as code when an editor or administrator opened **Preview JSON-LD**. The preview now escapes everything it shows, schema field values saved through the WordPress REST API are cleaned the same way as in the editor, and only someone who can edit that post may change them. Scores and scan results can no longer be written through the REST API at all.
* **Fix: password-protected posts were published in `/llms-full.txt`,** and listed in `/llms.txt`. They are now left out, and schema for a protected post no longer includes anything from its content until it is unlocked. Cached llms.txt files are rebuilt on update.
* **Fix: the "AEOmatic — refresh audit & gaps" bulk action** now skips posts the current user cannot edit.
* **Fix: a fake AI crawler user agent could store very long URLs in the crawler log.** Stored URLs are now limited to 255 characters.
* The review notice's dismiss and snooze links are now nonce-protected.

**robots.txt**

* **Fix: sites set to "Discourage search engines" no longer get Allow rules for AI crawlers.** WordPress blocks every crawler on those sites, and AEOmatic now leaves that alone.
* **Fix: allowed AI crawlers now keep your other robots.txt rules.** A crawler named in its own group ignores the `User-agent: *` rules, so GPTBot, ClaudeBot and the others skipped `/wp-admin/`, rules from other plugins and AEOmatic's own **Block PDF files** setting. Each allowed crawler's group now repeats those rules.
* **Fix: "Block PDF files" can be turned off again,** and unticking every crawler now saves.

**Scores and fixes**

* **Fix: the Entities score was too low on almost every post.** It never saw the detected schema type (−30) and asked for an `about` field the schema editor does not have (−15). The Entity Scanner had the same type bug and also missed address, opening hours, offers and phone signals from the schema editor.
* **Fix: the Authority check "Organization schema" always failed.** It now reads the organization name from Settings.
* **Fix: posts in Cyrillic, Greek, Arabic, Hebrew, Hindi, Chinese, Japanese and other non-Latin scripts were scored as nearly empty.** Words are now counted in every script.
* **Fix: on sites with plain permalinks,** the Citation Gaps table did not load, and **Recompute (skip cache)** and **Rescan (skip cache)** failed on the AI Readiness and Entity Scanner screens.
* **Fix: AI Readiness and Entity Scanner results now refresh** after you change settings, the schema type or schema fields, or the author bio.
* **Fix: the "Generate from first paragraph" meta description fix no longer replaces a description you wrote** in Yoast SEO, Rank Math, AIOSEO or SEOPress. It fills only empty fields and tells you when yours was kept.
* **Fix: the "Set schema type" quick fix** in Citation Gaps was never offered and logged a PHP warning.
* Scores will change after updating. Click **Re-run** in the Audit tab, or update a post, to refresh it.

= 1.2.9 =

* **New: intro video.** The setup wizard's welcome step and the AI Readiness dashboard link to a short intro video. It opens on YouTube in a new tab, so the plugin still makes no external calls.

= 1.2.8 =

* **Fix: page-builder pages were counted twice.** Elementor and Beaver Builder save a copy of the layout into the post content, and Divi and WPBakery pages were read both raw and parsed, so every word and link appeared twice. Each page is now read once. **Word counts and readiness scores on builder pages may go down — that is the correction.**
* **Fix: Divi and WPBakery content stored in shortcode settings is now read** — module titles, button labels, Divi `header_level` headings, WPBakery custom headings (with their H1–H6 level) and links such as `button_url` and `vc_link`. Raw shortcode text no longer leaks into the analysed content.
* **Fix: Beaver Builder and Bricks read only page text.** CSS values, colours, IDs and element names are no longer counted as words. Headings keep their level, button and link settings become links, Bricks internal links stored as a post ID resolve to the page URL, and accordion and list rows are read.
* **Fix: content included from another post is now read** — Elementor global widgets and Template widgets, Bricks Template elements, and Gutenberg synced patterns (reusable blocks). Templates that include themselves are detected and stop.
* **Fix: Gutenberg Latest Posts, Query Loop and Page List blocks** now count toward internal links. They are rendered when the audit runs in the editor or admin, not on public page views.
* **New: see which AI crawler requests got an error.** The AI crawler visits table on the **What did we do?** screen now has a Status column (for example *404 Not found*, *403 Blocked*, *503 Server error*), an **Errors only** filter, and a filter per crawler. The error count on the dashboard links straight to the matching requests, with a short note on what each error usually means.
* Click **Re-run** in the Audit tab, or update a post, to refresh its saved scores.

= 1.2.7 =

* **Fix: related content in ACF relationship fields is now counted.** Relationship, Post Object, Page Link and Link fields (including inside repeaters, groups and flexible content) are read as links, so related resources chosen in ACF count toward the audit's internal-links check and the citation gaps. Click Re-run in the Audit tab, or update the post, to refresh an existing score.
* **Fix: the audit's internal-links check** now counts relative links (`/guide/`), treats `www.` and letter case as the same site, counts each page once, and ignores links to the post itself, in-page anchors, admin pages and uploaded files. The hint now shows how many pages were found.
* **Fix: "No matches found for expression .aeomatic-speakable" in schema validators** on Article, NewsArticle, TechArticle and other posts without a manual excerpt. The excerpt AEOmatic builds for the page description used up the speakable mark, so the visible paragraph never got the class. The mark now goes on the paragraph visitors see, and speakable is left out when there is none.

= 1.2.6 =

* **Changed: licence and authorship headers are opt-in.** `X-Content-License: free-to-cite-with-attribution` and `X-AI-Content-Type: human-authored` are no longer sent unless you turn on **Settings → AI discovery headers → Licence and authorship headers**. They state a licence and an authorship claim for your content, so the site owner should choose them. The other discovery headers are unchanged.
* **Changed: honest wording on "What did we do?"** The "How long until I see results?" timeline predicted AI citations by week 4–8. It is replaced by "What happens next?", which covers only what your site controls and points to the crawler log and the Ask AI tab.
* **Changed: setup wizard** no longer says it sets defaults for Google AI Overviews (Google-Extended controls Gemini training; AI Overviews uses Googlebot), and describes the discovery headers as optional.
* **Changed:** author, verification and audit hints no longer describe author bios, word count or first-hand experience as proven AI citation factors.
* **Fix: entity recommendations named a filter that does not exist.** Product, Service and LocalBusiness hints for Classic Editor and page-builder posts now point to the AEOmatic schema editor (Classic Editor metabox or the AEOmatic button in your page builder) instead of an `aeomatic_schema_overrides` filter.
* **Changed:** the FAQ block description now matches what it does (visible Q&A), and the readme documents its FAQPage microdata, the full crawler list, all 19 filters and the Ask AI tab, with screenshot captions that match the screenshots.

= 1.2.5 =

* **Fix: "Missing field 'item' (in 'itemListElement')" in breadcrumb schema.** Every breadcrumb now carries its URL. Author, date and post-type archives link to themselves, and a breadcrumb whose link cannot be found is left out instead of printed without one.
* **Fix: breadcrumbs with no name.** An untitled post or an empty archive title uses the page title instead, so the schema no longer fails with "Missing field 'name'".

= 1.2.4 =

* **New: AI Access → Crawler check.** Requests your pages as each AI and search crawler, using the robots.txt your site actually serves, and compares the result with a normal browser visit. Flags robots.txt blocks, firewall and CDN blocks (403, 429, bot challenges), pages that return "not found" only to crawlers, stripped pages and crawler-specific noindex. Warns when a physical robots.txt file overrides AEOmatic's crawler settings, and cross-checks results with the AI crawler log.
* **New: AI Access → What AI sees.** Shows the text a crawler that does not run JavaScript receives, the heading outline, title and description, and which sentences from the editor are missing from the HTML.
* **New: answer-first fix.** A citation gap finds answers buried below a question heading or question-style title, and a one-click fix moves them to the top of the section on block-editor and Classic posts. A revision is saved. Page-builder pages get the suggestion only.
* **New: SoftwareApplication and Dataset schema types.**
* **New: Organization details in Settings** — legal name, address, contact details and registry IDs (D-U-N-S, LEI, NAICS, VAT, tax ID).
* **New: live page check.** After you save a published post, AEOmatic loads the live page in the background. FAQs printed by your theme, templates or other plugins then count in the audit, citation gaps and entity scan, and duplicate structured data is recorded.
* **New: citation gaps** for question headings without a short (40–50 word) answer, and for steps not in a numbered list. They replace the FAQ and HowTo schema gaps.
* **New:** the site readiness score includes the last AI Access crawler check.
* **Improved: schema follows Google's current required fields** for every type, and Organization, WebSite, WebPage and each post are linked into one graph.
* **Improved: FAQs are recognised from any editor, theme or plugin** — Elementor (including nested accordions), ACF, Yoast, Rank Math, Spectra, Kadence and ARIA accordions no longer trigger "No FAQ" warnings.
* **Improved: runs on PHP 7.4 and later** (was 8.0).
* **Fix: /llms.txt and /llms-full.txt no longer redirect** to a trailing-slash URL.
* **Fix: llms.txt is plain text again** — no more "&gt;" or HTML codes for apostrophes, and no empty summary line.
* **Fix: cached results no longer break when text contains a quote mark** — citation gaps, AI readiness, entity scan, audit and saved schema field edits. Cached citation gaps could show an empty list.
* **Fix: the "Set schema type" quick fix** always failed.
* **Fix: "cssSelector" errors in schema validators** — Speakable now points at a paragraph that exists on the page.
* **Fix: the AEOmatic sidebar loads on WordPress 6.6 and later.**
* **Fix: AEOmatic's own test requests** are no longer recorded in the AI crawler log.
* **Fix:** replaced a deprecated WordPress user query argument.
* **Fix:** uninstall now also removes AI Access data, live page check data and a leftover citation-gap timestamp.
* **Changed: honest wording.** Hints no longer claim that FAQ or HowTo schema, meta descriptions, author bios or freshness are proven AI citation factors.
* **Removed: FAQPage, HowTo, Q&A page and sitelinks search box schema**, and their Settings options — Google no longer shows them. FAQ blocks still display on the page.

= 1.2.3 =

* **New: edit schema inside page builders** — Elementor, Beaver Builder, Bricks, Divi, Oxygen and WPBakery get a floating AEOmatic button that opens the full editor.
* **Improved: wider Gutenberg sidebar** with all tabs visible, plus an Expand button for a large view.
* **Fix: Schema type dropdown keeps your selection** — it reverted to the auto-detected type.
* **Fix: edited schema fields no longer go blank after saving** — including Average rating, Review count, Item reviewed and dates. Values saved by earlier versions reappear.
* **Fix: dropdown fields show "— Select —" when unset** instead of an option that was never saved.
* **Fix: saving the Schema tab keeps unedited fields automatic**, so the modified date and WooCommerce price and rating stay up to date.
* **Fix: FAQ Q&A pairs, postcodes, SKUs, ISBNs and phone numbers now save correctly** — FAQ pairs were stored as "Array" and leading zeros and "+" were dropped.
* **Fix: the page now outputs the schema type you chose** straight after saving, instead of the default type.

= 1.2.2 =

* **New: visual editor now uses a tab layout** — Schema, Audit, Gaps, Readiness, Entities, Ask AI. Same layout in the Gutenberg sidebar and the Classic Editor metabox.
* **New: Classic Editor gets the full visual editor** as a metabox below the content area.
* **Fix: JSON-LD preview updates in real time** — was showing the last-saved state.
* **Fix: setup wizard "not allowed to access" error** on WordPress 6.5+ / Playground. Now a visible submenu under AEOmatic → Setup wizard.

= 1.2.1 =

* **Fixed: JSON-LD output is now readable.** Schema was emitted as a single unbroken line with every `&` written as `&`, every `<` as `<`, every `/` as `/`, and every accented or non-Latin character as an escape sequence. It was valid and search engines accepted it, but it looked broken in page source and in validators — and on non-English sites a headline became nothing but escape codes. Output is now pretty-printed with slashes and Unicode intact. Nothing about which schema is generated has changed, only how it is written.

= 1.2.0 =

* **Fixed: custom post types now stay selected** under Settings → Content coverage. Your saved selections were never lost, only displayed wrongly — nothing needs re-saving.
* **Fixed: page-builder and ACF pages are no longer scored as empty.** Six readiness checks were reading the post body directly and missing Elementor, Divi, Bricks, Oxygen, Beaver Builder and WPBakery content. **Expect readiness scores to rise on those sites** — that is the correction, not new optimisation.
* **Fixed: Elementor headings and widget links** are now visible to the analysers.
* **Fixed:** post types whose slug is not plain lowercase (for example MyCPT) can now be disabled.
* **Fixed:** the setup wizard no longer promises AI citations, and no longer triggers a PHP deprecation notice.
* **Fixed:** saving settings no longer leaves the upgrade routine re-running on every admin page load.
* Renamed to "AEOmatic — AEO, AI SEO, llms.txt & Schema".

= 1.1.9 =

* **New: AI Readiness dashboard.** One site score across three categories — Technical readiness, Content readiness and AI crawler access — with an issue count and your top three fixes. Every category states whether it is a direct check or a heuristic.
* **New: the setup wizard runs a real audit** and shows your score plus the three highest-value fixes.
* **New: the AI crawler log records the HTTP status** served to each bot, so a 404 or 403 served to GPTBot is visible.
* **New:** AI discovery headers are now a setting you can switch off.
* **Fixed:** two readiness checks that were silently degraded; uninstall now removes everything it created; robots.txt no longer advertises llms.txt as a sitemap.
* **Performance:** /llms.txt and /llms-full.txt are now cached and bounded, and running an audit no longer discards built schema.
* **Security:** the per-post citation-gaps endpoint is now gated per post rather than on a general capability.
* Renamed, with rewritten documentation and new build tooling.

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
