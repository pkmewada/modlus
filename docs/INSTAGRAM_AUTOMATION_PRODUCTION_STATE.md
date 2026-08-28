# Instagram Automation — Production State (Source of Truth)

Last Updated: 2026-08-28 (Phase 8 — Unified module naming refactor added)

> **CURRENT BASELINE IS PRODUCTION WORKING.**
>
> End-to-end image publishing (CRM → media upload → Meta Graph API →
> published Instagram post) is confirmed working in production as of this
> date. See §16-17 for the exact evidence. Read this document before
> touching Instagram OAuth, media publishing, Cron, token handling, or
> client/account mapping — see §22.

This document is the permanent record of **what has actually been verified
in production**, with dates, IDs, and log lines — as opposed to what the
code merely implements. For architecture/onboarding, see
`docs/instagram-automation-readme.md`. For internal implementation detail
(function-by-function reasoning, phase-by-phase change log), see
`docs/instagram-automation-flow.md`. For a pre-launch checklist template,
see `docs/instagram-automation-production-checklist.md`. This file does not
replace those — it records production-verified state and debugging history
that the other three don't carry.

---

## 1. Project Overview

**Project**: MQlus Instagram Automation (module within Modlus CRM)

**Purpose**: Lets Modlus CRM manage Instagram Business accounts on behalf of
multiple clients from one admin panel — connect each client's Instagram
account via Meta OAuth, create and schedule posts (images, carousels,
reels), and publish them automatically via a cron-driven scheduler.

**Technology**:
- Core PHP, MySQL/MariaDB
- Hostinger hosting (shared, Apache/LiteSpeed behind Hostinger's `hcdn` CDN)
- Meta Graph API v19.0
- Facebook Login for Business
- Instagram Business Account (via a linked Facebook Page)
- Server-side Cron (Hostinger Cron → PHP CLI)

**Architecture rule**: the system is **client-scoped / multi-client**.
There is **no `companyId` architecture** anywhere in this module — every
Instagram account and post is tied to Modlus's existing `clientMaster`
entity via `clientId`. Never introduce `companyId`.

---

## 2. Current Production Architecture

```
Modlus CRM
    |
clientMaster                (existing Modlus entity — a converted lead)
    |
instagramAccounts           (one client can connect multiple IG accounts)
    |
instagramPosts               (every post belongs to one client + one of
                               that client's specific accounts)
```

**The one rule that matters most**: every Instagram object resolves to a
specific `instagramAccountId` first, and `clientId` is *derived from that
account* — never picked from session/UI state, never defaulted to
"whichever account connected most recently." See §8.

---

## 3. OAuth Architecture (Facebook Login for Business)

Flow:

```
CRM (admin selects a client)
  → Connect Instagram Account button
  → api/instagramOauthStart.php builds the Meta authorize URL
  → Facebook Login for Business dialog
  → Page selection → Business selection → Instagram Business Account selection
  → Meta redirects to api/instagramOauthCallback.php with an auth code
  → callback validates OAuth state, exchanges code for a token,
    resolves the linked Instagram Business Account, stores it
  → Instagram account connected to the selected CRM client
```

Confirmed production behavior:
- OAuth `state` is generated with `bin2hex(random_bytes(16))`, stored in
  `$_SESSION['instagramOauthState']`, and validated in the callback with
  `hash_equals()`. Session state is unset immediately after being read.
- The selected `clientId` is stored in `$_SESSION['instagramOauthClientId']`
  during the flow and validated against `clientMaster` in the callback
  before any account is saved.
- `config_id` (Facebook Login for Business Configuration ID) is sent
  **only** on the authorization request (`api/instagramOauthStart.php`) —
  **never** on the token-exchange call (`/oauth/access_token` in
  `api/instagramOauthCallback.php`).
- App Secret is never exposed in the browser or in the authorization URL.
- Access tokens are never exposed in the authorization URL — they're only
  ever handled server-side, inside `instagramGraphApiRequest()` calls.

---

## 4. Meta App / Configuration ID Setup

| Setting | Value |
| --- | --- |
| Production Meta App ID | `1091563173330460` |
| Production Facebook Login for Business Configuration ID | `1397228955807717` |
| Production redirect URI | `https://modlus.in/api/instagramOauthCallback.php` |
| Graph API version in use | `v19.0` (hardcoded per-call throughout `includes/InstagramAutomation.php`, `includes/InstagramInsights.php`, `includes/InstagramComments.php`, `api/instagramOauthStart.php`, `api/instagramOauthCallback.php` — **not centralized into a constant**. Do not change or centralize this unless explicitly requested — see §20.) |

Confirmed production authorization URL shape:

```
https://www.facebook.com/v19.0/dialog/oauth?
client_id=1091563173330460
&redirect_uri=https%3A%2F%2Fmodlus.in%2Fapi%2FinstagramOauthCallback.php
&state=...
&response_type=code
&config_id=1397228955807717
```

`metaConfigId` is stored in `instagramSettings` (added specifically for
Facebook Login for Business — see §7 and
`database/migrations/2026-08-26-instagram-oauth-config-id.sql`). When it's
set, `api/instagramOauthStart.php` uses `config_id` and omits `scope`
(permissions come from the Meta App Dashboard configuration instead). If
`metaConfigId` is empty, the code falls back to the legacy
`scope=instagram_basic,instagram_manage_insights,pages_show_list,pages_read_engagement`
flow — this fallback exists for backward compatibility and is **not** the
production path currently in use.

---

## 5. Redirect URI

```
https://modlus.in/api/instagramOauthCallback.php
```

Stored as `instagramSettings.redirectUrl`, entered via the settings UI
(`pages/instagram-automation.php`). If left blank, the code falls back to
`BASE_URL . '/api/instagramOauthCallback.php'` — production has this set
explicitly rather than relying on the fallback.

---

## 6. Meta Permission Flow (Confirmed Screens)

The Meta permission/review screen successfully showed:
- Manage your business
- Access profile and posts from the selected Instagram account
- Upload media and create posts for the Instagram account
- Manage comments for the selected Instagram account
- Access insights for the Instagram account
- Read content posted on the Page
- Show a list of the Pages you manage

User selections during the confirmed test: **Current Pages only** →
**Specific Page** → **Current Instagram accounts only** → **Specific
Instagram Business Account**.

OAuth redirected back to the CRM with:
> "Instagram account connected successfully for Praveen Mewada (000001)."

---

## 7. Database Architecture

Core tables (all created/self-healed by `ensureInstagramSettingsTable()`,
`ensureInstagramAccountsTable()`, `ensureInstagramPostsTable()` in
`includes/InstagramAutomation.php`):

**`instagramSettings`** — one active row, platform-wide Meta credentials:
`metaAppId`, `metaAppSecret` (encrypted), `metaConfigId`, `redirectUrl`,
`webhookVerifyToken`, `isActive`, `createdBy`, `createdAt`, `updatedAt`.

**`instagramAccounts`** — one row per connected Instagram Business account:
`id`, `createdBy`, `clientId`, `instagramUserId`, `facebookPageId`,
`username`, `accessToken` (encrypted), `tokenExpiry`,
`lastAnalyticsSyncAt`, `lastAnalyticsSyncError`, `status`, `createdAt`,
`updatedAt`.

**`instagramPosts`** — one row per post: `id`, `createdBy`, `clientId`,
`instagramAccountId`, `mediaType`, `mediaUrl` (JSON array of relative
paths), `caption`, `status`, `scheduledAt`, `publishedAt`,
`instagramMediaId`, `errorMessage`, `createdAt`, `updatedAt`.

Relevant migrations: `database/migrations/2026-08-22-instagram-automation-tables.sql`,
`2026-08-22-instagram-posts-tables.sql`, `2026-08-22-instagram-multi-client.sql`,
`2026-08-22-instagram-phase3-tables.sql`, `2026-08-22-instagram-phase31-hardening.sql`,
`2026-08-26-instagram-oauth-config-id.sql`.

### Confirmed production `instagramAccounts` record

```
id = 1
createdBy = 6
clientId = 1
instagramUserId = 17841444393623973
facebookPageId = 336886156185070
username = gymlabzequipments
status = connected
```

The `accessToken` value is encrypted at rest (`includes/Crypto.php`,
AES-256-CBC) and is **not** recorded in this document.

---

## 8. Client → Instagram Account Relationship

- `instagramAccounts.clientId` ties every connected account to exactly one
  Modlus client.
- `instagramPosts.clientId` + `instagramPosts.instagramAccountId` tie every
  post to both a client and one specific one of that client's accounts.
- `instagramAccountBelongsToClient(mysqli $con, int $accountId, int $clientId): bool`
  (`includes/InstagramAutomation.php`) is the guard that makes cross-client
  publishing impossible — `api/saveInstagramPost.php` calls it before
  saving any post.
- `getInstagramAccountById()` loads the **specific** account a post is tied
  to — this is deliberate: the module used to pick "whichever account
  connected most recently" (a real bug fixed in an earlier phase, "Phase
  2.5" per `instagram-automation-flow.md`). **Never reintroduce
  latest-account selection logic.**

---

## 9. Media Upload Architecture

- `saveInstagramMediaFile(array $file, string $mediaCategory = 'image'): array`
  and `saveInstagramMediaFiles(array $filesField, string $mediaCategory = 'image', int $maxFiles = 10): array`
  (`includes/InstagramAutomation.php`) validate real content-type (via
  `finfo`, not just the file extension — images must be real JPEG, videos
  must be real MP4/MOV), enforce size limits (8 MB image / 100 MB video),
  and move the upload into place.
- Files are stored under `uploads/instagram-posts/` (relative to the
  project root — on production, `public_html/uploads/instagram-posts/`).
- **The directory is created lazily** — `mkdir($uploadDir, 0755, true)`
  inside `saveInstagramMediaFile()` only runs the first time a real upload
  actually happens. There is no separate provisioning/install step for it.
- `instagramPosts.mediaUrl` stores relative paths as a JSON array, e.g.
  `["uploads/instagram-posts/ig_....jpg"]` (`encodeInstagramPostMediaPaths()`
  / `decodeInstagramPostMediaPaths()`).
- **Known gap (not yet fixed, diagnosed but not implemented)**: when
  editing/rescheduling/retrying an existing post **without** re-attaching a
  new file, `api/saveInstagramPost.php` carries forward the existing
  `mediaUrl` from the database with **no check that the physical file still
  exists on disk**. This was identified as the likely mechanism behind the
  Post #1 failure (§15) and remains unfixed as of this document. If you
  touch `api/saveInstagramPost.php`'s media-handling block, read that
  diagnosis first.

---

## 10. Public Media URL Architecture

- `instagramPostMediaAbsoluteUrls(array $relativePaths): array` converts
  stored relative paths into absolute URLs using `BASE_URL`.
- `BASE_URL` (`includes/config.php`) resolution, current logic (as of the
  2026-08-26 CLI fix):
  1. `MODLUS_BASE_URL` environment variable, if set (normalized, trailing
     slash stripped) — wins over everything else.
  2. Else, if `PHP_SAPI === 'cli'` (cron/CLI context) → hardcoded
     `https://modlus.in` (production fallback — CLI has no `HTTP_HOST` and
     no reliable web-facing `DOCUMENT_ROOT`).
  3. Else (normal web request) → original dynamic
     protocol + `HTTP_HOST` + filesystem-relative-basePath logic,
     unchanged from before this fix.
- Production public upload URL base: `https://modlus.in/uploads/...`
- **Historical bug (fixed)**: before the CLI branch was added, cron runs
  produced `BASE_URL = http://localhost/domains/modlus.in/public_html`,
  which Meta's Graph API cannot fetch media from. This was the root cause
  of the Post #1 failure's underlying trigger — see §15.
- The successful Post #2 publish (§16) confirms `BASE_URL` now resolves
  correctly to `https://modlus.in` under the actual production cron
  environment.

---

## 11. Cron Architecture

- Scheduler file: `cron/instagramScheduler.php` — CLI-only
  (`PHP_SAPI !== 'cli'` guard rejects HTTP hits).
- Configured on Hostinger Cron (external to this repo — not something this
  document can verify further than the log evidence in §17).
- On each run: finalizes any in-flight (`publishing` status, e.g. Reels
  awaiting Meta's async processing) posts, then publishes newly due
  (`status = 'scheduled'`, `scheduledAt <= NOW()`) posts.
- Logs via `instagramSchedulerLog()` — confirmed format:
  `Post #{id} (Client: {label}) published ({type}). Media ID: {id}` and
  `Scheduler run complete. Finalized {n} in-flight, published {n} newly due.`
- **Confirmed working in production** — see §17 for the exact log line.

---

## 12. Publishing Flow

```
CRM: Instagram Post creation (pages/instagram-create-post.php)
  → media upload (saveInstagramMediaFiles())
  → media stored under uploads/instagram-posts/
  → public HTTPS media URL (via BASE_URL)
  → post saved to instagramPosts (api/saveInstagramPost.php → saveInstagramPost())
  → Hostinger Cron executes cron/instagramScheduler.php
  → scheduler detects due post (getDueInstagramPosts())
  → Meta Graph API media container created (POST /{instagramUserId}/media
    with image_url/video_url + caption + access_token)
  → container published (POST /{instagramUserId}/media_publish with
    creation_id + access_token) via publishInstagramContainer()
  → Instagram returns a Media ID
  → CRM marks the post as published (status = 'published', publishedAt,
    instagramMediaId recorded)
```

Publishing functions in `includes/InstagramAutomation.php`:
- `publishInstagramImagePost(array $account, string $mediaUrl, string $caption): array`
- `publishInstagramCarouselPost(array $account, array $mediaUrls, string $caption): array`
- `publishInstagramVideoPost(array $account, string $videoUrl, string $caption): array`
  — Reels are asynchronous; this function starts the container and returns
  a `pending` status if not immediately `FINISHED`. Finalization happens on
  a later scheduler run via `getInstagramContainerStatus()` +
  `publishInstagramContainer()`.

**Image publishing is production-confirmed (§16-17). Carousel and Reel
publishing are implemented in code but not yet production-verified — see
§19.**

---

## 13. Important Files and Responsibilities

| File | Responsibility |
| --- | --- |
| `includes/InstagramAutomation.php` | Core domain logic: settings, accounts, posts, media upload/validation, Graph API request wrapper (`instagramGraphApiRequest()`), publishing functions, diagnostic logging helpers. |
| `api/instagramOauthStart.php` | Builds and redirects to the Meta authorize URL (`config_id`-based, with legacy `scope` fallback). Validates the selected client and that Meta credentials exist first. |
| `api/instagramOauthCallback.php` | OAuth callback: validates state (`hash_equals()`) and client, exchanges the auth code for a token, attempts long-lived token exchange, resolves Facebook Pages → linked Instagram Business Account → username, saves the account, logs the connection, redirects with a status message. Exception details are logged server-side only (`error_log()`); the browser only ever sees a generic error message. |
| `api/saveInstagramSettings.php` | Persists `instagramSettings` (Meta App ID/Secret, Configuration ID, Redirect URL) via `saveInstagramSettings()`. CSRF-protected. |
| `api/saveInstagramPost.php` | Post create/update endpoint: validates client + account ownership (`instagramAccountBelongsToClient()`), handles media upload via `saveInstagramMediaFiles()`, calls `saveInstagramPost()` to insert/update `instagramPosts`. |
| `cron/instagramScheduler.php` | CLI-only scheduler — the only thing that actually calls Meta to publish. Finalizes in-flight posts, publishes newly due posts, logs every outcome. |
| `pages/instagram-automation.php` | Settings UI: Meta App ID/Secret/Configuration ID/Redirect URL form, "Connect Instagram Account" button, connected-accounts list per client. |
| `pages/instagram-scheduled-posts.php` | Lists all posts (draft/scheduled/publishing/published/failed) with filter, edit, delete, "View Error" for failed posts. |
| `database/migrations/2026-08-26-instagram-oauth-config-id.sql` | Adds `instagramSettings.metaConfigId` for Facebook Login for Business. |

(Full file map, including Phase 3 analytics/comments/webhooks files not
listed above, is in `docs/instagram-automation-flow.md` §2 — not
duplicated here.)

---

## 14. Error Logging Architecture

- `instagramGraphApiRequest()` (`includes/InstagramAutomation.php`) logs a
  complete diagnostic entry on every Meta API error, invalid-JSON response,
  or network/cURL failure — **not** on success.
- Logged fields: request URL, HTTP method, HTTP status
  (`curl_getinfo($ch, CURLINFO_HTTP_CODE)`), sanitized request parameters,
  and the complete Meta error object (`message`, `code`, `error_subcode`,
  `error_user_title`, `error_user_msg`, `fbtrace_id` — whatever Meta
  actually returned, via `json_encode()`).
- Sanitization (`instagramSanitizeParamsForLog()`): redacts `access_token`,
  `client_secret`, `code`, `fb_exchange_token` as `[REDACTED]`. `image_url`
  / `video_url` are deliberately **not** redacted (needed for media-fetch
  debugging).
- Written to two places: `error_log()` (PHP's own error log — unreliable to
  locate on this Hostinger deployment) **and** a dedicated file,
  `logs/instagram-api.log` (project root, via `instagramWriteApiDebugLog()`),
  added specifically because the Hostinger PHP error log couldn't be
  located during the Post #1 investigation. `logs/.htaccess` denies direct
  web access to the whole `logs/` directory (`Require all denied` /
  `Deny from all` fallback) — verify this is actually enforced on Hostinger
  if you rely on it (Apache/`AllowOverride` dependent, not independently
  confirmed from this environment).
- `api/instagramOauthCallback.php`'s final `catch (Throwable $e)` block
  logs the real exception via `error_log()` but returns a generic
  browser-facing message: "Instagram connection failed. Please try again or
  contact the administrator." No exception message, token, or secret is
  ever sent to the browser.

**This logging is explicitly marked temporary/diagnostic** in its own code
comments (added to debug Post #1) — it is safe to keep running
indefinitely (it never logs on success, so it has no ongoing cost), but if
it's ever removed, remove it deliberately, not accidentally.

---

## 15. Previous Post #1 Failure and What Was Learned

**Symptom**: Post #1 failed with Meta error:
```
code: 9004
error_subcode: 2207052
message: "Only photo or video can be accepted as media type."
error_user_title: "Media download has failed. The media URI doesn't meet our requirements."
error_user_msg: "The media could not be fetched from this URI: https://modlus.in/uploads/instagram-posts/ig_6a8eb32f626384.71442019_1787736879.jpg"
```

**Investigation timeline**:
1. Added diagnostic logging to `instagramGraphApiRequest()` (§14) to
   capture the full Meta error instead of just the short message.
2. Discovered `BASE_URL` was resolving to
   `http://localhost/domains/modlus.in/public_html` under cron/CLI
   execution — a real, confirmed bug (§10) — and fixed it in
   `includes/config.php` with a `PHP_SAPI === 'cli'` production fallback to
   `https://modlus.in`.
3. After that fix, direct `curl` testing of the *old* Post #1 media URL
   still showed **intermittent** behavior — sometimes a valid 200/JPEG,
   sometimes a 404 generated by Modlus's own PHP router (`Route "..." is
   not configured.`), across identical repeated requests, with **no**
   evidence of WAF/hotlink/UA-based blocking (same pattern with and without
   a `facebookexternalhit` User-Agent).
4. Direct inspection confirmed `public_html/uploads/instagram-posts/` did
   **not exist** on production filesystem at that point, despite the
   `instagramPosts` row referencing a file inside it.
5. Code review of `api/saveInstagramPost.php` found the mechanism that
   allows a DB row to reference a non-existent file: when editing/retrying
   a post **without** re-attaching a file, the existing `mediaUrl` is
   carried forward from the database with no check that the file still
   exists on disk (§9, "Known gap").

**Resolution taken**: Post #1 was **deleted**, not repaired — a completely
new post (Post #2) was created from scratch with a fresh upload. The fresh
upload correctly created `uploads/instagram-posts/` and its file, and Meta
successfully fetched and published it (§16). **The §9 "Known gap" itself
was diagnosed but has not been code-fixed** — it remains a standing risk
for any future post that gets edited/retried without a fresh upload.

**Do not treat the Post #1 failure as evidence that the current publishing
implementation is broken** — the fix (BASE_URL) and the workaround (fresh
post) are both confirmed effective by Post #2. Do treat the §9 gap as
real, open, unfixed technical debt.

---

## 16. Successful Post #2 Production Test

- **Post**: #2
- **Client**: Praveen Mewada (000001)
- **Instagram account**: `gymlabzequipments` (`instagramAccounts.id = 1`,
  `instagramUserId = 17841444393623973`)
- **Media type**: image
- **Result**: Published successfully. Visually confirmed as actually live
  on the connected Instagram account (confirmed by the user, not just by
  the API response).
- **Published Media ID**: `18007863326766159`

---

## 17. Exact Successful Scheduler Log

```
[2026-08-26 18:21:09] Post #2 (Client: Praveen Mewada (000001)) published (image). Media ID: 18007863326766159
[2026-08-26 18:21:09] Scheduler run complete. Finalized 0 in-flight, published 1 newly due.
```

This confirms: Hostinger Cron executed `cron/instagramScheduler.php`, the
scheduler correctly identified Post #2 as due, published it through Meta's
Graph API, and logged the outcome in the documented format (§11, §14).

---

## 18. Current Production Verification Checklist

Confirmed:

- [x] Meta Developer App configured
- [x] Facebook Login for Business configured
- [x] Configuration ID implemented
- [x] OAuth authorization URL generated correctly
- [x] Meta permission flow completed
- [x] Page selected successfully
- [x] Business selected successfully
- [x] Instagram Business Account selected successfully
- [x] OAuth callback successful
- [x] Instagram account stored in database
- [x] Client-to-Instagram account relationship working
- [x] Access token stored encrypted
- [x] Instagram post creation working
- [x] Media upload working
- [x] `uploads/instagram-posts/` created successfully
- [x] Public media URL working for fresh upload
- [x] Cron executing
- [x] Scheduler detecting scheduled posts
- [x] Meta image container creation working
- [x] Instagram `media_publish` working
- [x] Instagram image actually published
- [x] Published Media ID returned
- [x] CRM marks post as published
- [x] Production end-to-end image publishing confirmed

---

## 19. Features Implemented But Not Yet Production-Verified

These exist in code (`includes/InstagramAutomation.php`,
`includes/InstagramInsights.php`, `includes/InstagramComments.php`,
`includes/InstagramWebhooks.php` and related `api/`/`cron/` files per
`docs/instagram-automation-flow.md`), but have **no recorded production
evidence** of a successful run as of this document. Do not mark these as
"production verified" without the same kind of evidence as §16-17
(specific IDs, logs, visual confirmation):

- Carousel publishing (`publishInstagramCarouselPost()`)
- Reel/video publishing (`publishInstagramVideoPost()`, async container
  finalization)
- Automatic analytics synchronization (`cron/instagramAnalyticsSync.php`)
- Comment management (`includes/InstagramComments.php`,
  `api/replyInstagramComment.php`, `api/hideInstagramComment.php`)
- Webhook event processing (`api/instagramWebhook.php`,
  `includes/InstagramWebhooks.php`)
- Multiple simultaneous client publishing (only one client/account has
  been exercised so far)
- Token expiration/reconnection behavior
- Production retry behavior after a Meta API failure (Post #1 was deleted
  and recreated, not retried in place)

"Implemented" ≠ "production verified." Keep that distinction when reporting
status.

---

## 20. Rules for Future Development

1. **Read this document first** before modifying Instagram OAuth, media
   publishing, Cron, token handling, or client/account mapping.
2. Inspect the current implementation before assuming anything — code may
   have moved on since this document was last updated.
3. Do not reimplement already-working functionality (§18).
4. Do not assume previously-fixed bugs (§15, BASE_URL) still exist without
   checking.
5. Do not remove working logic without evidence it's actually wrong.
6. Make minimum targeted changes — this module has a documented history of
   being touched by narrowly-scoped, single-file changes (OAuth config_id,
   BASE_URL CLI fix, diagnostic logging); keep that pattern.
7. Preserve the client-scoped architecture (§8) — every account and post
   resolves through `clientId` + `instagramAccountId`, never "latest
   account."
8. **Never introduce `companyId`.**
9. Never expose access tokens or App Secrets — not in browser output, not
   in logs, not in URLs.
10. After changes, run `php -l` on every touched file and document exactly
    what changed (files, functions, behavior) — the pattern used throughout
    this module's recent history.
11. The Graph API version (`v19.0`) is hardcoded in ~15 places across 5
    files, not centralized. Do not change or centralize it unless
    explicitly requested (§4).
12. The §9 "Known gap" (stale `mediaUrl` on edit/retry without re-upload)
    is real, diagnosed, and unfixed — read §9 and §15 before touching
    `api/saveInstagramPost.php`'s media-handling block.

---

## 21. Troubleshooting Notes

- **Meta error 9004 / `error_subcode 2207052`** ("Only photo or video can
  be accepted as media type" / "Media download has failed"): means Meta's
  crawler could not fetch the `image_url`/`video_url` you sent it. Check,
  in order: (a) is `BASE_URL` actually resolving to `https://modlus.in` in
  the context that generated the URL (§10); (b) does the file actually
  exist on disk at that path right now — `curl -I` the exact URL a few
  times in a row, since Hostinger's infrastructure showed intermittent
  200/404 behavior for the same file during the Post #1 investigation
  (§15); (c) is the `instagramPosts.mediaUrl` value stale from a
  carried-forward edit (§9).
- **Diagnostic logs**: check `logs/instagram-api.log` (project root) first
  — it has the complete, unredacted (except credentials) Meta error object,
  not just the short exception message the UI shows.
- **Can't find `logs/instagram-api.log` or PHP's own error log on
  Hostinger**: this was the exact problem that led to adding the dedicated
  log file (§14) — use that file instead of hunting for the server's
  native PHP error log.
- **A post's `mediaUrl` points at a missing file**: this is the known,
  unfixed gap in §9. The immediate workaround used for Post #1 was to
  delete the post and create a fresh one with a new upload, rather than
  editing/retrying in place.

---

## 22.5. Phase 4 (Social Media Automation roadmap) — Dashboard Analytics Integration

**Date**: 2026-08-27. This work is part of a broader "Modlus Social Media
Automation Platform" roadmap (Instagram → Facebook → LinkedIn, unified
scheduler, social inbox). Its Phase 4 asked for Instagram analytics
(`instagramAnalytics` table, `includes/InstagramAnalytics.php`,
`cron/instagramAnalyticsScheduler.php`, `api/getInstagramAnalytics.php`,
CRM dashboard cards).

**Investigation finding**: that analytics pipeline already existed, built
earlier under the name "Phase 3: Analytics" — `instagramInsights` table,
`includes/InstagramInsights.php`, `cron/instagramAnalyticsSync.php`,
`api/getInstagramInsights.php`, and a dedicated `pages/instagram-analytics.php`
page with stat cards. Per the "do not rebuild working features" rule, that
existing pipeline was **extended in place** rather than duplicated under new
names. If a future task references `instagramAnalytics`/
`InstagramAnalytics.php`/`instagramAnalyticsScheduler.php` by those exact
names, know that they don't exist — the equivalent is the `instagramInsights`
stack described in §7 and above.

**What was actually added** (additive only — no existing function, table, or
file behavior was changed):

1. `fetchInstagramAccountInsights()` (`includes/InstagramInsights.php`) now
   also requests `website_clicks` and `total_interactions` alongside the
   existing `reach`/`profile_views` account-level metrics. Routed through
   the existing `fetchInstagramInsightsResilient()` fallback, so an account
   where Meta doesn't support one of these metrics simply won't get that
   metric — no error, no fabricated value.
2. New `getInstagramDashboardSummary(mysqli $con): array` (`includes/InstagramInsights.php`)
   — an **admin-wide, read-only rollup** (connected account count, sum of
   each connected account's latest `followers_count`, sum of today's
   `reach`/`total_interactions` across all accounts). This is the one
   function in the Instagram module that is deliberately **not**
   client-scoped, because it only aggregates already-client-scoped rows for
   a summary display — it writes nothing and every underlying row still
   carries its own `clientId`/`instagramAccountId`.
3. New `api/getInstagramDashboardSummary.php` — thin wrapper exposing the
   above, auth-gated like every other Instagram API endpoint.
4. `pages/dashboard.php` — new "Instagram Overview" card row (connected
   accounts, total followers, today's reach, today's interactions), hidden
   by default and only shown once `connectedAccounts > 0` (so an install
   with no Instagram accounts connected yet sees no empty/zero widget).
   Populated client-side via `fetch()` against the new API on page load.

**Still not production-verified** (unchanged from §19): the underlying
`cron/instagramAnalyticsSync.php` sync itself has no recorded evidence of a
successful real-data run against Meta, and it's unconfirmed whether it's
actually registered in Hostinger's cron alongside `instagramScheduler.php`.
The new `website_clicks`/`total_interactions` metrics and the dashboard
widget inherit that same "implemented, not yet verified" status until a real
sync run and a real dashboard load are confirmed in production.

**Verified during this change**: `php -l` clean on all three touched/created
files; `getInstagramDashboardSummary()` executed successfully against the
local dev database (returned all-zero summary, consistent with no connected
accounts in that database — no SQL errors, table auto-created as expected).

---

## 22.6. Phase 5 (Social Media Automation roadmap) — Facebook Page Publishing Adapter

**Date**: 2026-08-27. Phase 4 (§22.5) was confirmed production-verified by
the user (real Meta insights synced: `followers_count: 1212`,
`media_count: 53`, `reach: 17`) before this phase started.

**Audit findings before writing any code:**

1. **The Page Access Token already exists per account.** `api/instagramOauthCallback.php`
   (lines ~85-113) calls `/me/accounts`, and stores **`$page['access_token']`**
   (a Page Access Token) — not the user token — into `instagramAccounts.accessToken`,
   alongside `facebookPageId`. So no new OAuth flow was needed for Facebook
   publishing; the credential material was already there, just unused for
   this purpose. Do not change what gets stored here without checking every
   existing caller of `getInstagramAccountById()`/`accessToken` first.
2. **Permission scope is unconfirmed.** §6's confirmed permission screens
   list "Read content posted on the Page" — there is **no confirmed grant
   of `pages_manage_posts`**, which Meta requires to create Page posts
   (`/{page-id}/photos`, `/{page-id}/feed`). Neither the `config_id`-based
   flow (permissions come from the Meta Dashboard configuration, opaque to
   this repo) nor the legacy `scope=` fallback in `api/instagramOauthStart.php`
   requests it. **This was flagged to the user and left unresolved** — it's
   an external Meta App Dashboard change, not something fixable from code.
   If a publish call fails with an OAuthException about missing permission:
   add `pages_manage_posts` to Configuration ID `1397228955807717` in the
   Meta App Dashboard, then **reconnect** the affected account(s) — existing
   stored tokens will not retroactively gain the new scope.
3. **`getInstagramAccountById()` was missing `facebookPageId` in its return
   array** even though the DB row has always had the column (used internally
   in `saveInstagramAccountFromOAuth()`/`getInstagramAccounts()` already).
   Fixed additively — added one key to the returned array
   (`includes/InstagramAutomation.php`, `getInstagramAccountById()`). No
   existing caller's behavior changes; they simply never read that key
   before.

**What was built:**

- `includes/FacebookPublisher.php` — new, separate from
  `InstagramAutomation.php` per the roadmap's "keep publishers separate"
  rule:
  - `publishFacebookImagePost(array $account, string $imageUrl, string $caption): array`
    → `POST /{facebookPageId}/photos`
  - `publishFacebookTextPost(array $account, string $message): array`
    → `POST /{facebookPageId}/feed`
  - Both reuse the existing `instagramGraphApiRequest()` transport (curl +
    JSON decode + Meta error handling + diagnostic logging) rather than
    duplicating that plumbing — it's a generic Graph API HTTP wrapper, not
    Instagram-specific business logic, already shared across
    Insights/Comments/Webhooks.
  - `$account` is exactly the array `getInstagramAccountById()` returns —
    same account-lookup path Instagram publishing already uses, same
    client-scoping guarantees (§8) apply unchanged.
- `cron/testFacebookPublish.php` — **manual CLI test tool, not a cron job**.
  Do not register it on Hostinger Cron. Lets an admin manually verify a
  real publish against one already-connected account:
  `php cron/testFacebookPublish.php <instagramAccountId> image <imageUrl> [caption]`
  or `... <instagramAccountId> text "<message>"`.

**Not yet done (deferred to later phases per the roadmap):** no
`socialPosts`/scheduler/UI wiring — Phase 5 is adapter-only, matching the
roadmap's own phase boundary ("Both should use common scheduling
architecture later" = Phase 6/7).

**Verified during this change:** `php -l` clean on all three touched/created
files (`includes/InstagramAutomation.php`, `includes/FacebookPublisher.php`,
`cron/testFacebookPublish.php`). `cron/testFacebookPublish.php` exercised
locally with an invalid account id and a missing-argument case — both fail
cleanly with a clear message and exit code 1, no fatals. **Not yet
exercised against a real Facebook Page** — that requires the user to run it
against a real connected account in production, and depends on the
`pages_manage_posts` permission question above being resolved first.

---

## 22.7. Phase 6 — Unified Social Post Engine

**Date**: 2026-08-28. Phase 5 (§22.6) was confirmed production-verified by
the user before this phase started — real Facebook post id
`336886156185070_122207178524467606`, published via
`cron/testFacebookPublish.php` against Page `336886156185070`
(Gym Labz Equipments), proving `pages_manage_posts` now works after the
Meta Login Configuration update.

**Objective**: one publishing entry point that can send the same post to
Instagram only, Facebook only, or both, with independent per-platform
results and correct partial-success reporting — without touching Instagram
publishing, Facebook publishing, OAuth, or `cron/instagramScheduler.php`.

### Architecture decision made with the user before coding

Nothing in Modlus publishes synchronously today — even Instagram-only
posts don't publish on "Schedule Post" click; that only saves a DB row,
and `cron/instagramScheduler.php` (batch, async) publishes it later. Two
options existed to get an immediate "Publish → see both results now" UX:
extend the scheduler, or add a new synchronous path alongside it. The user
explicitly chose **a new synchronous "Publish Now" action**, and explicitly
required `cron/instagramScheduler.php` to stay completely untouched in this
phase. That is what was built — the existing Draft/Schedule/cron pipeline
was not modified in any way.

### What was built

**`includes/SocialPostEngine.php`** (new) — the Unified Social Post Engine.
Pure orchestration, no Meta API code of its own:

- `publishSocialPost(mysqli $con, int $clientId, int $accountId, array $platforms, string $type, array $content): array`
  is the single entry point. `$platforms` is a subset of `['instagram', 'facebook']`;
  `$type` is `'image'` or `'text'`; `$content` carries `imageUrl`/`caption`
  (image) or `message` (text, falls back to `caption` if not given).
- Validates, in order: platform list, post type, `instagramClientExists()`,
  `instagramAccountBelongsToClient()` (the exact same cross-client guard
  `api/saveInstagramPost.php` already uses — no second account/ownership
  system), `getInstagramAccountById()` (the exact same account lookup and
  decryption path everything else uses — no second token/account lookup
  system), then per-type content requirements, then
  `facebookPageAccountValid()` when Facebook is requested. Every validation
  failure returns a structured `['success' => false, 'status' => 'failed', 'message' => ..., 'platforms' => []]`
  before any Meta API call is made — nothing here can throw an uncaught
  exception to a caller.
- `socialPostEnginePublishOne()` dispatches exactly one platform:
  - Instagram + image → calls the **existing**
    `publishInstagramImagePost()` (`includes/InstagramAutomation.php`,
    unchanged) — the same container-create-then-publish flow already
    production-verified in Phase 2/§16-17.
  - Instagram + text → returns a structured unsupported result
    (`'unsupported' => true`) and **never calls any Meta endpoint** —
    Instagram has no normal text-only feed post.
  - Facebook + image → calls the **existing** `publishFacebookImagePost()`.
  - Facebook + text → calls the **existing** `publishFacebookTextPost()`.
  - Any Meta/transport exception is caught here and turned into a
    structured `{success:false, message: <sanitized Meta error>}` — one
    platform's failure can never take down the other platform's call or
    throw a fatal to the UI. `instagramGraphApiRequest()` (unchanged,
    reused by both publishers) already redacts `access_token`/
    `client_secret`/`code` before an exception is ever raised, so the
    message surfaced here is always safe to show/log.
  - `socialPostEngineFinalize()` computes overall `status`: `'success'`
    (all requested platforms succeeded), `'partial'` (some succeeded, some
    failed — **never** collapsed into a plain failure), or `'failed'`
    (none succeeded). `success` (boolean) is `true` only for `'success'`;
    callers must read `status`/`platforms` to distinguish `'partial'` from
    `'failed'`.
  - `socialPostEngineLog()` — new diagnostic log,
    `logs/social-post-engine.log`, one line per platform attempt:
    timestamp, clientId, accountId, platform, type, success, postId,
    sanitized message. Never writes `accessToken` — the logged `message`
    is always the same sanitized string returned to the caller, and the
    token itself is never passed into this function at all (only the
    `$account` array's non-secret fields are implied by context, and even
    those aren't logged — only the ids).

No duplicate Graph API transport, no duplicate token decryption, no
duplicate account lookup, no duplicate Instagram/Facebook publishing logic
— every actual Meta API call in this phase happens inside
`InstagramAutomation.php` or `FacebookPublisher.php`, unchanged.

**`api/publishSocialPostNow.php`** (new) — the synchronous "Publish Now"
endpoint. Auth + CSRF gated like every other Instagram API. Validates
client/account ownership (same two checks as `api/saveInstagramPost.php`),
uploads media via the **existing** `saveInstagramMediaFiles()` into the
**existing** `uploads/instagram-posts/` directory (no new upload path), then
calls `publishSocialPost()`. Reports `success => ($status !== 'failed')` at
the HTTP layer specifically so a `'partial'` result reaches the UI as
partial (with both platform results rendered), never as a blanket
"Publishing failed" when one platform actually succeeded. Writes one
`saveActivityLog()` entry (existing audit log table), never the token.

**`pages/instagram-create-post.php`** (extended, additively) — the
**existing** Instagram composer, not a second composer. Added:
- "Post To" checkboxes (Instagram, Facebook). Facebook is disabled/unchecked
  automatically when the selected account has no `facebookPageId`
  (`getInstagramSettings.php` already returns that field per account — no
  new API needed).
- A "Publish Now" button, enabled only when Post Type is "Image Post"
  (reels/carousels still use the existing "Schedule Post" + cron flow,
  completely untouched).
- A results panel rendering each requested platform's outcome
  independently (✓/✕, message, post id) — the exact "never show only a
  generic failure when one platform succeeded" requirement.
- The existing Draft/Schedule form, its validation, its file input, and its
  submission to `api/saveInstagramPost.php` are all unchanged; "Publish
  Now" reads the same form fields via `FormData` and posts to the new
  endpoint instead — it does not touch or reuse `saveInstagramPost()`.

**`cron/testUnifiedSocialPost.php`** (new) — **MANUAL TEST ONLY, NOT A
CRON JOB.** Do not register it on Hostinger Cron. Calls
`publishSocialPost()` directly against one real connected account:
```
php cron/testUnifiedSocialPost.php <accountId> <platforms> image <imageUrl> [caption]
php cron/testUnifiedSocialPost.php <accountId> <platforms> text "<message>"
```
`<platforms>` is comma-separated (`instagram`, `facebook`, or
`instagram,facebook`). Never prints the access token.

### Database

**No schema changes.** `publishSocialPost()`/`api/publishSocialPostNow.php`
do not read or write `instagramPosts` (or any other table) — "Publish Now"
is an ephemeral, immediate action, not a scheduled post, so there is
nothing to persist for it yet. Extending `instagramPosts` with
`platforms`/`facebookPostId`/`facebookStatus` columns (to give scheduled
posts the same dual-platform capability) is deferred to the phase that
unifies scheduling — doing it now would add columns with no code path
using them yet, which the project's own DB rule explicitly warns against
("do not create unnecessary tables/columns... do not modify the database
just because it seems convenient").

### Client isolation

Unchanged mechanism, reused exactly: `instagramAccountBelongsToClient()`
and `instagramClientExists()` (both pre-existing, both from
`InstagramAutomation.php`) are the only guards, called once each, before
any account is loaded or any platform is published to. No second
client/account validation path was created.

### Token security

Unchanged: `getInstagramAccountById()` is still the only place that reads
and decrypts `instagramAccounts.accessToken`; `SocialPostEngine.php` only
ever receives the already-decrypted `$account` array, exactly like
`FacebookPublisher.php` already did in Phase 5. No new token storage, no
new decryption path, no token in any HTTP response, log line, or error
message anywhere in this phase's new code (verified by inspection of every
`respond()`/log call added — see Testing below).

### Scheduling compatibility

Architectural only, as scoped by the user — no scheduler was built or
modified in this phase. `publishSocialPost()` takes a plain
`(clientId, accountId, platforms, type, content)` request and returns a
plain structured result, with no dependency on how or when it's called;
a future scheduler can call it exactly the same way `api/publishSocialPostNow.php`
does. `cron/instagramScheduler.php` was not opened for editing in this
phase — no dependency was found that would have required changing it, so
nothing was reported/stopped-on per the user's "stop and report if the
scheduler needs changing" instruction.

### Manual testing performed (local, no production credentials available here)

Ran directly against `includes/SocialPostEngine.php` (no Meta network
calls involved for these — all failures below happen before any platform
dispatch):
- Invalid platform (`'twitter'`) → clean structured failure, no exception.
- Invalid post type (`'video'`) → clean structured failure.
- Invalid `clientId` (999999) → clean structured failure ("Please select a
  valid client.").
- Invalid `accountId` against a real local `clientId` → clean structured
  failure ("The selected account does not belong to this client."), no
  fatal.
- Instagram + text, called directly against a synthetic account array →
  returned the structured unsupported result; confirmed by code path
  inspection that no Meta endpoint is reachable from that branch.
- `socialPostEngineFinalize()` unit-level checks: all-success → `status=success`;
  one success + one failure → `status=partial`, `success=false`, and the
  successful platform's real `postId`/message are preserved unchanged in
  the output; all-failure → `status=failed`.
- `cron/testUnifiedSocialPost.php` run against a non-existent account id
  and against missing required arguments — both exit cleanly with code 1
  and a clear stderr message, no PHP warnings/fatals.
- `php -l` clean on all four touched/created files:
  `includes/SocialPostEngine.php`, `api/publishSocialPostNow.php`,
  `cron/testUnifiedSocialPost.php`, `pages/instagram-create-post.php`.

**NOT EXECUTED — REQUIRES PRODUCTION TEST** (no real connected Instagram/
Facebook account or Meta network access from this environment):
- Facebook-only image publish via the unified engine (real Facebook post id).
- Instagram-only image publish via the unified engine (real Instagram media id).
- Instagram + Facebook combined publish in one call (both real ids, overall
  `status=success`).
- Facebook-only text publish via the unified engine.
- Real partial-failure scenario (one platform succeeding, the other
  genuinely rejected by Meta).
- Real cross-client isolation attempt (a second client's real account).
- The "Publish Now" button end-to-end through the actual browser UI.

To run these on production, mirroring how Phase 5's
`cron/testFacebookPublish.php` test was run:
```
php cron/testUnifiedSocialPost.php <accountId> facebook image https://modlus.in/uploads/instagram-posts/test.jpg "MODLUS Phase 6 Facebook Test"
php cron/testUnifiedSocialPost.php <accountId> instagram image https://modlus.in/uploads/instagram-posts/test.jpg "MODLUS Phase 6 Instagram Test"
php cron/testUnifiedSocialPost.php <accountId> instagram,facebook image https://modlus.in/uploads/instagram-posts/test.jpg "MODLUS Phase 6 Unified Test"
php cron/testUnifiedSocialPost.php <accountId> facebook text "MODLUS Phase 6 Facebook Text Test"
```

### Known limitations

- "Publish Now" supports image posts only — reels/carousels still require
  the existing Draft/Schedule + cron flow, unchanged.
- "Publish Now" is not persisted to `instagramPosts` or shown in
  `pages/instagram-scheduled-posts.php` — it's an immediate, one-off action
  with no history row (by design, see Database above).
- Platform-specific captions (different text per platform) were explicitly
  out of scope this phase — the engine's `$content` shape already allows
  it later (`caption` vs a future `instagramCaption`/`facebookCaption`
  split) without a redesign, but the composer UI only exposes one caption
  field today, matching the user's "don't overcomplicate the initial UI"
  instruction.
- Real Meta-API-touching tests are unverified until run on production (see
  above) — do not treat this phase as production-verified the way Phase 4
  and Phase 5 are (§18) until the user runs them and confirms real post
  ids, per this doc's own standing rule (§19/§20).

---

## 22.8. Phase 7 — Unified Scheduled Publishing

**Date**: 2026-08-28. Follows the stop-and-report exchange in this same
session (recorded conceptually here since it wasn't a separate doc entry):
the existing `instagramPosts.status`/`instagramMediaId`/`errorMessage`
triplet was found to be single-platform-only and incapable of representing
independent Instagram/Facebook completion state; the user approved four
additive columns plus a `'partial'` status value before any code was
written.

### Architecture

`cron/instagramScheduler.php` remains the **only** scheduler — no second
cron system was created. Its existing three-part shape is preserved and
extended with one new phase:

```
Phase A  (unchanged) — finalize Reels still processing at Meta
Phase A2 (NEW)       — recover image/carousel posts stuck in 'publishing'
Phase B  (extended)  — publish newly due scheduled posts
```

Phase A is now explicitly scoped to `mediaType = 'reel'`
(`getInstagramPostsByStatus($con, 'publishing', 20, 'reel')`) so it can
never overlap with Phase A2's image/carousel recovery scan
(`getStuckSocialPosts()`). Every existing Instagram-only code path — the
legacy image branch, the carousel branch, the reel branch, `handleInstagramAuthFailure()`,
the `flock` single-instance lock — is **byte-for-byte unchanged**. The only
new logic is one `if ($platforms === ['instagram'] || empty($platforms))`
branch inside the existing `case 'image':` block: when true (every
pre-Phase-7 post, since `platforms` defaults to `'instagram'`), it takes
the exact original code path; only when a post's `platforms` differs from
that default does it route into the new unified logic.

### Files created

- `database/migrations/2026-08-28-instagram-posts-phase7-scheduling.sql` —
  documents the 4 additive columns (also self-healed at runtime).
- `cron/testScheduledUnifiedSocialPost.php` — **MANUAL TEST ONLY, NOT A
  CRON JOB.** A harness with three subcommands (`create`, `simulate-stuck`,
  `show`) that lets a real due/stuck row be set up deterministically and
  then exercised with the real `cron/instagramScheduler.php`, instead of
  waiting hours for a real schedule or faking a real crash.

### Files modified

- `includes/InstagramAutomation.php` — `ensureInstagramPostsTable()` gains
  4 self-healed columns; `getInstagramPostsByStatus()` gains an optional
  `$mediaType` filter (additive, existing 2-arg/3-arg callers unaffected);
  `saveInstagramPost()` gains an optional `platforms` key (defaults to
  `['instagram']` — legacy callers/behavior unchanged); new functions
  `getStuckSocialPosts()`, `socialScheduledRecoveryPlan()`,
  `recordInstagramPlatformResult()`, `markFacebookScheduledPublished()`,
  `markFacebookScheduledFailed()`, `setInstagramPostOverallStatus()`,
  `normalizeSocialEngineResult()`, `finalizeSocialScheduledPost()`. Every
  pre-existing function (`markInstagramPostPublished()`,
  `markInstagramPostFailed()`, `revertInstagramPostToScheduled()`,
  `getDueInstagramPosts()`, etc.) is untouched.
- `includes/SocialPostEngine.php` — one additive change: catches
  `InstagramTransientApiException` distinctly from other errors and adds
  `'transient' => true` to that platform's result, so a scheduled-post
  caller can tell a network blip apart from a real Meta rejection. `publishSocialPost()`'s
  signature, validation order, and return shape are unchanged; Phase 6's
  `api/publishSocialPostNow.php` ignores the new key and is unaffected.
- `cron/instagramScheduler.php` — adds Phase A2; the `case 'image':` branch
  gains the platforms-based fork described above.
- `api/saveInstagramPost.php` — accepts `platforms[]`, defaults to
  `['instagram']` when absent, rejects Facebook + non-image at save time.
- `pages/instagram-create-post.php` — wires the Phase 6 platform checkboxes
  into the Draft/Schedule submit (previously they only fed Publish Now);
  Facebook checkbox is now gated by *both* having a linked Page *and*
  Post Type = Image; edit mode restores a post's saved platform selection.
- `pages/instagram-scheduled-posts.php` — adds a Platforms column
  (per-platform badges, Facebook's badge colored by its own
  `facebookStatus`), a `'partial'` status badge/filter option, and extends
  "View Error" to show both platforms' errors when present.

### Database changes

Four additive columns on `instagramPosts` (see migration file above for
exact types/defaults) plus one new value (`'partial'`) for the existing
`status` column — no new tables, no `companyId`, no changes to any other
table.

### Platform selection / Instagram-only / Facebook-only / Instagram+Facebook behavior

`platforms` is a comma list persisted at save time. The scheduler reads it
per-post and only ever calls the platform(s) actually selected — confirmed
by testing (below): an Instagram-only post takes the untouched legacy path;
a Facebook-only post and a dual post both route through
`publishSocialPost()` with exactly the requested platform list, never more.

### Partial-success handling

`finalizeSocialScheduledPost()` computes `status` from combining both
platforms' outcomes, never from a single platform's side effect — verified
directly (see Tests below): Instagram success + Facebook permanent failure
→ `status = 'partial'`, with Facebook's own error preserved in
`facebookErrorMessage` and Instagram's real post id preserved in
`instagramMediaId`.

### Retry / idempotency behavior

- "Done" is determined **only** from a persisted result
  (`instagramMediaId` / `facebookPostId`), never from `status` — so a
  platform whose success is already recorded is **never** re-attempted, by
  either fresh Phase B processing or Phase A2 recovery.
- A platform result marked `transient` (network-level Meta API failure) is
  left completely unwritten — the row stays `'publishing'` and Phase A2
  retries exactly that platform on the next run, never the other one.
- **Acknowledged, not solved**: if Meta accepts a publish call but the PHP
  process dies before the response is recorded, the next recovery pass has
  no way to know that and will attempt that platform again — this is a
  narrow, inherent limitation of at-least-once retry against a
  non-idempotent Graph API with no client-supplied idempotency key. It
  predates Phase 7 (the same gap already existed, unhandled, for
  Instagram-only posts) and is not claimed to be closed by this work —
  only the specific "already-recorded success" case (the vast majority of
  real crash timing) is now safely handled.

### Token security / client isolation

Unchanged mechanisms, reused exactly: `getInstagramAccountById()` remains
the only decryption path; `instagramAccountBelongsToClient()` /
`instagramClientExists()` remain the only ownership guards. No new token
storage, no token in any log line (grepped all touched files and the
actual log output produced during testing — see Tests below).

### Manual + local testing actually performed

**Schema**: `ensureInstagramPostsTable()` run locally — confirmed all 4
columns created with the exact documented types/defaults.

**`saveInstagramPost()`**: confirmed a call with `platforms: ['instagram','facebook']`
persists `platforms='instagram,facebook'`, `facebookStatus='pending'`; a
legacy call with no `platforms` key persists `platforms='instagram'`,
`facebookStatus='not_applicable'` — proving old callers are unaffected.

**`socialScheduledRecoveryPlan()`** (pure function, no DB/network):
exercised all 6 combinations (neither done, Instagram done, Facebook done,
both done, Facebook-only, legacy Instagram-only) — every `instagramNeeded`/
`facebookNeeded`/`alreadyComplete` value matched expectation.

**`finalizeSocialScheduledPost()`**: exercised 6 scenarios against real
local DB rows (Instagram-done-then-Facebook-succeeds, mixed success/permanent-failure,
Instagram-succeeds-with-Facebook-transient, both-already-done,
Facebook-only-success, and the mirror Facebook-done-then-Instagram-succeeds)
— **two real bugs were found and fixed during this testing**, not
discovered by static review:
1. The original implementation trusted `markInstagramPostPublished()`/
   `markInstagramPostFailed()`'s side effect on `status` to reflect the
   aggregate outcome, which is wrong whenever a platform's success wasn't
   freshly attempted this run (recovery skip) or a sibling platform is
   still transiently unresolved. Fixed by splitting into two phases: each
   platform's own fields are written independently
   (`recordInstagramPlatformResult()`), and `status` is decided exactly
   once, explicitly, only when nothing is left unresolved.
2. `publishSocialPost()` returns an **empty** `platforms` array when its
   own top-level validation rejects a request before dispatching to any
   platform (e.g. missing Facebook Page) — `finalizeSocialScheduledPost()`
   was misreading that as "not attempted, trust existing state" instead of
   "this attempt failed", silently leaving a genuinely-failed platform
   stuck at `'pending'` with no error recorded. Fixed with
   `normalizeSocialEngineResult()`, which turns a top-level rejection into
   an explicit failure entry for every platform that was actually intended
   to be attempted.

After both fixes, all 6 scenarios produced exactly the expected `status`/
`instagramMediaId`/`facebookStatus`/`facebookPostId`/`facebookErrorMessage`.

**End-to-end against the real `cron/instagramScheduler.php`** (local
machine, synthetic client/account, real code path, real scheduler
execution — not mocked):
- Simulated Instagram-already-done + Facebook-pending → ran the real
  scheduler → confirmed via log line and DB state: Instagram was **not**
  re-attempted (`instagramMediaId` unchanged), only Facebook was attempted.
- Simulated Facebook-already-done + Instagram-pending (the mirror
  direction) → confirmed Facebook was **not** re-attempted
  (`facebookPostId` unchanged); Instagram's attempt hit a local-machine-only
  curl/SSL issue (no CA bundle on this dev machine — unrelated to Phase 7
  logic) and was correctly classified `transient`, leaving the row safely
  retriable rather than marked failed.
- Simulated both-already-done → ran the real scheduler → log line
  explicitly confirms *"finalized without a new Meta API call"*; `status`
  correctly became `'published'`.
- Created a fresh due Instagram+Facebook post with a synthetic (invalid)
  test account and ran the real scheduler → both platforms correctly
  attempted, both correctly failed (test account has no valid credentials)
  with the real, specific error message preserved per platform, `status`
  correctly `'failed'` (both failed) — confirming `normalizeSocialEngineResult()`'s
  fix works on the fresh-due path too, not just recovery.
- `php -l` clean on every touched/created file.
- Grepped `cron/instagramScheduler.log` and `logs/social-post-engine.log`
  for token-shaped content after all of the above runs — none found;
  logged messages are exactly the sanitized error strings shown above.

**Incident during testing, caught and fixed**: initial cleanup of
synthetic test posts pointed at the shared real test image
(`uploads/instagram-posts/test.jpg`, used throughout Phase 5/6 testing) —
`deleteInstagramPostRecord()`'s existing (unmodified, correct) behavior of
removing a deleted post's referenced media file deleted that shared file
as a side effect. Caught immediately via `git status` and restored via
`git checkout`. Recorded here as a reminder for any future local testing:
use a throwaway media path for synthetic posts, never the shared
documented test image.

**NOT EXECUTED — REQUIRES PRODUCTION TEST** (no production DB/Meta network
access from this environment):
- Test A: schedule a real Instagram-only image post — confirm unchanged
  behavior.
- Test B: schedule a real Facebook-only image post — confirm a real
  Facebook post id.
- Test C: schedule a real Instagram+Facebook image post — confirm both a
  real Instagram media id and a real Facebook post id, `status = 'published'`.
- A real crash-recovery scenario (kill `-9` the scheduler process between
  the two platform calls) — the local testing above simulates the
  *outcome* of a crash (via `simulate-stuck`) and proves the recovery
  *logic* is correct, but has not observed an actual process crash on
  production.

### Production test commands

```
# A — Instagram only (existing behavior, must be unaffected)
php cron/testScheduledUnifiedSocialPost.php create <accountId> instagram image uploads/instagram-posts/test.jpg "Phase 7 IG-only test"
php cron/instagramScheduler.php

# B — Facebook only
php cron/testScheduledUnifiedSocialPost.php create <accountId> facebook image uploads/instagram-posts/test.jpg "Phase 7 FB-only test"
php cron/instagramScheduler.php

# C — Instagram + Facebook (the most important test)
php cron/testScheduledUnifiedSocialPost.php create <accountId> instagram,facebook image uploads/instagram-posts/test.jpg "Phase 7 unified test"
php cron/instagramScheduler.php

# After each: inspect the resulting row
php cron/testScheduledUnifiedSocialPost.php show <postId>

# Recovery tests (deterministic, no need to wait for or force a real crash)
php cron/testScheduledUnifiedSocialPost.php simulate-stuck <postId> yes no   # IG done, FB pending
php cron/instagramScheduler.php
php cron/testScheduledUnifiedSocialPost.php simulate-stuck <postId> no yes   # FB done, IG pending
php cron/instagramScheduler.php
php cron/testScheduledUnifiedSocialPost.php simulate-stuck <postId> yes yes  # both done — must log "without a new Meta API call"
php cron/instagramScheduler.php
```

**`cron/testScheduledUnifiedSocialPost.php` and its log/lock output are
manual test tooling — do not register it on Hostinger Cron.**

### Known limitations

- Facebook scheduling is image-only (enforced at save time) — no verified
  Facebook equivalent for Reels/carousels exists yet, matching Phase 5/6.
- Platform-specific captions remain out of scope (same content is reused
  for both platforms), as explicitly scoped by the user.
- The narrow crash-timing gap described under Retry/idempotency above is
  acknowledged, not solved — exactly-once delivery is not claimed.
- Production verification is outstanding — see status below.

### Production verification status

**IMPLEMENTED — AWAITING PRODUCTION VERIFICATION.** Do not treat Phase 7 as
production-verified (unlike Phase 4/5/6, which have real post ids on
record) until the production test commands above have actually been run
and their results confirmed.

---

## 22.9. Phase 7.x — Production Bug Fix: Instagram Image Container Readiness

**Date**: 2026-08-28. Discovered via a real production scheduled
Instagram+Facebook post: Facebook published successfully
(`336886156185070_122207273216467606`), Instagram failed with HTTP 400,
`error_subcode 2207027` — *"The media is not ready to be published. Please
wait a moment."* Meta had accepted the image container creation call but
hadn't finished processing it by the time `media_publish` was called
immediately afterward. Phase 7's own behavior around this was correct
(`status='partial'`, Facebook's post id preserved, Instagram's error
preserved) — the bug was purely in the timing of the two Instagram Graph
API calls, not in the Phase 7 architecture.

**Fix**: `publishInstagramImagePost()` (`includes/InstagramAutomation.php`)
now polls the **existing** `getInstagramContainerStatus()` (already used,
unchanged, by Phase A's Reel finalization) between creating the container
and calling `media_publish`, waiting for `FINISHED` before proceeding.
Bounded: polls every `INSTAGRAM_CONTAINER_POLL_INTERVAL_SECONDS` (2s), for
at most `INSTAGRAM_CONTAINER_POLL_MAX_SECONDS` (30s) total. `ERROR`/`EXPIRED`
is a permanent failure; a timeout without `FINISHED`/`ERROR` throws the
**existing** `InstagramTransientApiException` — the same class every other
retryable failure in this module already uses, so both existing callers
(`SocialPostEngine.php`'s per-platform catch, and the scheduler's legacy
try/catch) already handle it correctly as retryable with **no changes
needed in either file**. Each poll attempt logs `creation_id` + `status_code`
via the existing `instagramWriteApiDebugLog()` — never the access token.

Because `publishInstagramImagePost()` is the single function both Phase 6
Publish Now and Phase 7 Scheduled Publishing already call (directly, or via
`SocialPostEngine.php`), this one change benefits both automatically — no
scheduler-only polling, no duplicated Instagram publishing logic.

**Scope**: Instagram **image** posts only, matching the reported bug.
Carousel/Reel publishing were not touched — Reels already have their own
async finalization (Phase A, unchanged); carousels create containers the
same way images do and could theoretically hit the same race, but that
was not part of the reported bug or this fix's scope.

**Database**: none. This is a pure application/service-layer timing fix.

**Verified locally**: `php -l` clean; Facebook-only publishing produces the
exact same error as before the fix (proving Facebook was not touched);
Instagram-only publishing still fails at container *creation* (before ever
reaching the new polling code) with the correct account/token, proving the
polling is correctly positioned after creation and its exception handling
integrates with the existing transient/permanent classification; `git diff`
confirms `publishInstagramCarouselPost()`/`publishInstagramVideoPost()`/Phase
A are byte-for-byte untouched. **Live polling behavior (an actual
IN_PROGRESS→FINISHED transition, and a real deliberately-delayed
container) requires a production account with real Meta credentials and
was NOT executed from this environment.**

No exactly-once guarantee is claimed beyond what Phase 7 already
documented (§22.8) — this fix reduces the specific "container not ready"
race, it does not change the acknowledged crash-timing limitation.

---

## 22.10. Phase 8 — Unified Module Naming Refactor

**Date**: 2026-08-28. Naming/refactoring only — no behavior, OAuth, permissions,
scheduler logic, or data semantics changed. The module evolved (Phases 5-7)
from Instagram-only into a unified publisher supporting Instagram-only,
Facebook-only, and Instagram+Facebook — the old all-"Instagram" naming had
become misleading. This phase renames exactly the parts that are genuinely
part of the unified post/scheduling domain, and deliberately leaves every
genuinely platform-specific name untouched.

### UI naming

| Old sidebar label | New sidebar label | Route |
| --- | --- | --- |
| Instagram Automation | **Social Media Automation** | `/instagram-automation` (unchanged — see below) |
| Create Instagram Post | **Create Social Post** | `/instagram-create-post` → `/social-create-post` |
| Instagram Posts | **Social Posts** | `/instagram-scheduled-posts` → `/social-posts` |
| Instagram Comments | *(unchanged)* | `/instagram-comments` |
| Instagram Analytics | *(unchanged)* | `/instagram-analytics` |

Comments and Analytics were deliberately **not** renamed — both remain
genuinely Instagram-only in their actual implementation (`instagramComments`/
`instagramInsights` tables, Instagram Graph API only, no Facebook data
anywhere in either feature). Renaming their labels would have been cosmetic,
not accurate — exactly what this phase was told not to do.

### Route naming — one deliberate exception

`/instagram-automation` (the Meta connect/settings page) keeps its **route
path** unchanged — only its sidebar label and `routesMaster.routeTitle`
changed to "Social Media Automation". Reason: `api/instagramOauthCallback.php`
redirects back to this exact path by name; renaming it would have required
touching the OAuth callback file, which every phase of this project has
been told to leave alone unless proven necessary. Not proven necessary here
— a label change fully satisfies "the user sees Social Media Automation",
without touching OAuth.

### File renames (via `git mv`, history preserved)

- `pages/instagram-create-post.php` → `pages/social-create-post.php`
- `pages/instagram-scheduled-posts.php` → `pages/social-posts.php`
- `api/saveInstagramPost.php` → `api/saveSocialPost.php`
- `api/getInstagramPosts.php` → `api/getSocialPosts.php`
- `api/deleteInstagramPost.php` → `api/deleteSocialPost.php`

`api/publishSocialPostNow.php` was already correctly named (Phase 6) — not
touched. `cron/instagramScheduler.php` was **deliberately not renamed** —
see below.

### Function renames (unified post/scheduling domain only)

`includes/InstagramAutomation.php`: `ensureInstagramPostsTable` →
`ensureSocialPostsTable`, `instagramPostsEnsureColumn` →
`socialPostsEnsureColumn`, `encodeInstagramPostMediaPaths` →
`encodeSocialPostMediaPaths`, `decodeInstagramPostMediaPaths` →
`decodeSocialPostMediaPaths`, `instagramPostMediaAbsoluteUrls` →
`socialPostMediaAbsoluteUrls`, `getInstagramPosts` → `getSocialPosts`,
`getInstagramPostById` → `getSocialPostById`, `saveInstagramPost` →
`saveSocialPost`, `deleteInstagramPostRecord` → `deleteSocialPostRecord`,
`getDueInstagramPosts` → `getDueSocialPosts`, `getInstagramPostsByStatus` →
`getSocialPostsByStatus`, `markInstagramPostPublishing` →
`markSocialPostPublishing`, `markInstagramPostPublished` →
`markSocialPostPublished`, `markInstagramPostFailed` →
`markSocialPostFailed`, `revertInstagramPostToScheduled` →
`revertSocialPostToScheduled`, `setInstagramPostOverallStatus` →
`setSocialPostOverallStatus`.

### Platform-specific functions intentionally NOT renamed

Every Instagram Graph API function: `publishInstagramImagePost()`,
`publishInstagramCarouselPost()`, `publishInstagramVideoPost()`,
`publishInstagramContainer()`, `getInstagramContainerStatus()`,
`waitForInstagramContainerReady()`, `updateInstagramPostContainerId()`
(Reel container tracking — genuinely Instagram-specific),
`instagramGraphApiRequest()`, `instagramSanitizeParamsForLog()`,
`instagramWriteApiDebugLog()`, `InstagramTransientApiException`. Every
account/OAuth/settings function: `getInstagramAccountById()`,
`getInstagramAccounts()`, `saveInstagramAccountFromOAuth()`,
`instagramAccountBelongsToClient()`, `instagramClientExists()`,
`getInstagramClientLabel()`, `ensureInstagramAccountsTable()`,
`disconnectInstagramAccount()`, `isInstagramAuthError()`, everything in
`instagramSettings`. Facebook-specific: `publishFacebookImagePost()`,
`publishFacebookTextPost()`, `facebookPageAccountValid()`,
`markFacebookScheduledPublished()`, `markFacebookScheduledFailed()`. Phase
7's own additions that were already generic: `getStuckSocialPosts()`,
`socialScheduledRecoveryPlan()`, `finalizeSocialScheduledPost()`,
`normalizeSocialEngineResult()`. `recordInstagramPlatformResult()` was
kept Instagram-named on purpose — it writes only Instagram's own columns
(`instagramMediaId`/`errorMessage`) and deliberately never touches
Facebook's, so an Instagram-specific name is the accurate one.

### Database: `instagramPosts` → `socialPosts`

Renamed via `RENAME TABLE` — an atomic metadata operation. Confirmed
locally: every row, column, index, primary key, default, and timestamp
preserved unchanged; only the table's name changed. Two ways this is
applied, matching this project's established migration convention:

1. **Self-healing** (`ensureSocialPostsTable()`): on any request, if
   `socialPosts` doesn't exist but the old `instagramPosts` does, it's
   renamed in place before anything else happens. Verified locally by
   renaming `socialPosts` back to `instagramPosts` and confirming
   `ensureSocialPostsTable()` correctly renamed it forward again.
2. **Explicit migration** (for documentation and manual/production runs):
   `database/migrations/2026-08-28-social-posts-naming.sql`.

No column was added, removed, or reinterpreted. No `companyId`. Two other
files had raw SQL against this table and were updated to keep working:
`includes/InstagramInsights.php` (post-level analytics JOIN) and
`includes/InstagramComments.php` (comment-to-post resolution) — both kept
their own Instagram-specific function names (they only ever query Instagram
media), only their `FROM`/`SELECT` table reference changed.

`instagramAccounts`, `instagramSettings`, `instagramInsights`,
`instagramComments`, `instagramWebhookEvents` were **not** renamed — the
audit found no evidence any of them have become cross-platform.

### routesMaster: `UPDATE`, never `DELETE`+`INSERT`

`database/migrations/2026-08-28-social-posts-routes-naming.sql` updates the
existing rows' `routePath`/`pageFile`/`routeTitle` in place. This matters:
`rolePermissions` grants reference a route by its `routeId`, not by path
string — updating in place preserves every role's existing permission grant
for these pages; deleting and re-inserting would issue new `routeId`s and
silently revoke access until someone manually re-granted it. **This
migration is not self-healing — it must actually be run** (unlike the table
rename). Verified locally: ran it, confirmed the new routes resolve via
`getRouteByPath()` and the pages render correctly.

### Backward compatibility for old URLs

Old bookmarks to `/instagram-create-post` and `/instagram-scheduled-posts`
will 404 after this change. A trivial alias was considered (per the
request) but rejected: `routesMaster` permissions are keyed by `routeId`,
so a second row for the old path would be a *new*, unpermissioned route —
nobody would have access to it without a separate admin step, which is
exactly the "complicated routing abstraction" this phase was told to
avoid. The sidebar and every internal link were updated to the new paths,
so normal in-app navigation is unaffected; only manually-typed/bookmarked
old URLs are affected.

### `cron/instagramScheduler.php`: intentionally NOT renamed

Hostinger Cron points directly at this filename in production. Renaming it
would require coordinating a production cron configuration change purely
for cosmetic naming — explicitly out of scope. It remains an internal
legacy filename; the module's user-facing identity is now "Social Media
Automation" regardless of what the cron script backing it is called.
`instagramSchedulerLog()` and `cron/instagramScheduler.log` were left
unchanged for the same reason (consistency with the file they belong to).

### Incident during testing (caught, fixed)

Local test cleanup twice deleted the shared `uploads/instagram-posts/test.jpg`
(used since Phase 5) as a side effect of `deleteSocialPostRecord()`'s
existing, correct behavior of removing a deleted post's referenced media.
Caught both times via `git status` and restored via `git checkout`.
Separately, bulk-renaming several files via PowerShell's `-Encoding UTF8`
silently added a UTF-8 BOM to the start of five PHP files — invisible to
`php -l` but would have emitted stray bytes before any `header()` call in
production. Caught by an actual functional test run (not by linting) and
stripped from all five files before finalizing.

### Testing performed

`php -l` clean on every modified file. Full functional regression against
local synthetic data: `saveSocialPost()` → `getSocialPostById()` →
`getSocialPosts()` → `getDueSocialPosts()` → `markSocialPostPublishing()` →
`markSocialPostPublished()` → `deleteSocialPostRecord()`, all against the
renamed `socialPosts` table. Full recovery cycle re-run end-to-end through
the real `cron/instagramScheduler.php` (Instagram-already-done +
Facebook-pending → correctly skipped Instagram, attempted only Facebook,
finalized `status='partial'` with a clean error message) — identical
correct behavior to the pre-rename Phase 7 test. `routesMaster` verified to
resolve the two new paths correctly. No access token in any log output.

### Production verification status

**IMPLEMENTED — AWAITING PRODUCTION VERIFICATION.** Requires running
`database/migrations/2026-08-28-social-posts-naming.sql` and
`database/migrations/2026-08-28-social-posts-routes-naming.sql` on
production (or simply deploying the code and letting the table rename
self-heal, then running just the routes migration, which is not
self-healing) and confirming the sidebar/pages/scheduler all behave as
verified locally.

---

## 22. Do Not Modify Working Production Components Without Explicit Request

The following are **confirmed working in production** (§18) and must not be
refactored, reimplemented, or "cleaned up" incidentally while working on
something else:

- OAuth start/callback flow (`api/instagramOauthStart.php`,
  `api/instagramOauthCallback.php`)
- `config_id`-based Facebook Login for Business flow
- `BASE_URL` CLI/cron resolution (`includes/config.php`)
- Image publishing (`publishInstagramImagePost()`,
  `publishInstagramContainer()`)
- The scheduler's due-post detection and logging
  (`cron/instagramScheduler.php`)
- Client/account scoping (`instagramAccountBelongsToClient()`,
  `getInstagramAccountById()`)

If a change is needed to any of these, make the smallest change that
addresses the specific request, verify with `php -l`, and update this
document's relevant section afterward so it stays accurate.
