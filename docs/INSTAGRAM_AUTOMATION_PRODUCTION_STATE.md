# Instagram Automation — Production State (Source of Truth)

Last Updated: 2026-08-27 (Phase 5 — Facebook Page Publishing adapter added)

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
