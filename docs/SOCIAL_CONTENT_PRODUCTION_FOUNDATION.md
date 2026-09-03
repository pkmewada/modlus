# Social Content Production — Foundation

## Purpose

Think of the Social Media module as a small factory:

- **Social Media Data Entry** (`clientSocialContent`) = raw material — the brief for what needs to be made.
- **Content Production** (`socialContentProduction`, this document) = manufacturing — turning that brief into finished content, with a manager assigning work and reviewing it.
- **Social Media Automation** (`socialPosts`, `SocialPostEngine.php`) = final dispatch — publishing finished content to Instagram/Facebook.

This document covers only the middle stage. It is a **foundation**: assignment, status tracking, TAT, and remark history. It intentionally does not yet include the actual content-production fields (captions, song selection, media upload, etc.) — those are future phases built on top of this same `socialContentProduction` row.

## Relationship to clientSocialContent

`socialContentProduction.clientSocialContentId` references `clientSocialContent.id`. One raw entry gets **at most one** production task (enforced by a unique key). `clientSocialContent` remains the single source of truth for the raw brief (client, platform, feature, date, title, raw content, etc.); `socialContentProduction` only stores workflow state and never duplicates those fields.

## Phase 3 — content brief visibility

Phase 3's audit found that most of the "what does Production need to see" fields already existed in `clientSocialContent` — they just weren't read or displayed anywhere. Phase 3 itself is a **read/display change only**: no new tables, no new columns, no migration.

**What changed:** `SocialContentProductionEngine::getTask()` and `::listTasks()` now additionally `SELECT` `c.caption, c.contentDescription, c.songUrl, c.ideaReference, c.referenceLink, c.socialMediaHandle, c.postType, c.remarks AS contentRemarks` from the same `clientSocialContent` join those two queries already had (they were already selecting `c.title`/`c.rawContent`). No new query was added — the existing join was extended.

**Where it's shown:** a new "Content Brief" block inside the existing "Production Task" detail modal on both `pages/social-content-production.php` (manager) and `employee/emp-content-production.php` (editor) — the same modal that already showed scope + history, no new modal or page. Each field renders only if non-empty (no blank labels, no literal "null"/"undefined"). `songUrl`/`referenceLink` render as clickable links only when they pass a `^https?://` check; otherwise as plain escaped text — never raw/unescaped HTML.

**Read-only, deliberately:** these fields are rendered, never editable, from Production. Data Entry remains their sole owner. If an editor or manager needs to communicate something *about* the task, that still goes through the existing `socialContentProductionHistory.remark` mechanism (assignment notes, submission notes, correction instructions, approval notes) — aliased in the query as `contentRemarks` (the Data Entry note) vs. `lastRemark` (the most recent Production history note) so the two are never confused in the API response or the UI.

**Unchanged:** status transitions, assignment/reassignment, ownership checks, TAT, approval, `markReady()` (no remark parameter added — the audit found insufficient evidence to justify it), CSRF, client isolation, the sidebar entries.

**Deferred, not fixed in Phase 3** (all previously flagged by the audit, left untouched again): the `scheduled`/`posted` status badge-vs-task-existence gap; the `caption`/`contentDescription` naming overlap; the `ideaReference`/`referenceLink` naming overlap; the three placeholder sidebar routes with no backing page; production media/file upload; the Automation handoff field mapping.

## Phase 3 — production output, manager review, automatic TAT

The Content Brief phase above made the raw material *visible*; this phase gives the editor an actual way to hand back finished work, and switches TAT from "unset until a manager types one" to a formula driven by the content's calendar date.

**Submission — two columns, nothing duplicated.** `socialContentProduction` gained `submissionType` (`'drive'` or `'media'`) and `submissionUrl` (the Drive link, or the path to an uploaded file — one column serves both, since only one is ever populated). No `submissionRemark` column: the editor's note continues through the existing `socialContentProductionHistory.remark` mechanism on the `submitted` action, exactly as before. Migration: `database/migrations/2026-09-02d-social-content-production-submission.sql`.

**`submit()` → `submitProduction()`.** A bare "submit with just a note and no actual output" no longer represents reality, so the method was replaced rather than duplicated — `IN_PROGRESS → SUBMITTED` now requires a valid `submissionType` + non-blank `submissionUrl`, enforced server-side regardless of what the client sends. `api/social-content-production/emp-update-task.php`'s old `'submit'` action was removed; the sole path is the new `api/social-content-production/emp-submit-production.php` (a dedicated multipart-capable endpoint, since a JSON body can't also carry `$_FILES`).

**Resubmission after a correction** overwrites `submissionType`/`submissionUrl` with the latest version (no version table — matches "don't build a full version system"), but `submitProduction()` writes a history remark naming what was submitted each time, so the sequence of past submissions stays reconstructable from `socialContentProductionHistory` even though the live columns only hold the latest.

**Google Drive submission:** validated as `^https://(drive|docs)\.google\.com/` — no Drive API integration, no download, just a reference link, rendered as an "Open Google Drive" external link (escaped, `target="_blank" rel="noopener noreferrer"`).

**Media upload:** `api/social-content-production/emp-submit-production.php`, modeled on this app's strongest existing upload pattern (`api/employee/updateEmployeeProfile.php`'s MIME-driven validation) since no shared upload helper exists anywhere in the codebase — every feature hand-rolls its own. Allow-list: `video/mp4`, `video/quicktime` (.mov), `video/webm`, `image/jpeg`, `image/png` — checked via `finfo` on the actual file content, never the client-supplied extension (verified: a plain-text file renamed `.mp4` is correctly rejected). The stored extension is derived from the verified MIME type. Filename is fully generated (`production_{taskId}_{time()}.{ext}`) — the original filename is discarded, never trusted. Stored under `uploads/production/{taskId}/`, 100MB cap enforced in code. Ownership is checked *before* any file touches disk (a request for another editor's task never reaches the filesystem), and again inside the engine.

Two new files harden the upload directory beyond this app's existing convention (which has no such protection anywhere in `uploads/`): `uploads/production/.htaccess` blocks `.php`-family files from executing there, and `uploads/production/.user.ini` raises `upload_max_filesize`/`post_max_size` to 100M/105M for this directory only (the server's real default is a stock 2M/8M — nowhere near workable for video). **Could not be verified end-to-end without a live authenticated upload** — if uploads still fail as "too large" in practice, the environment may need this raised at the php.ini/vhost level instead.

**No auth-gated file serving was built.** Every existing upload in this app (lead documents, expense receipts, employee photos, payroll files) is served as a plain static URL under `uploads/`, protected only by an unguessable generated filename — no exception exists anywhere, including files considerably more sensitive than social content. Production media follows the same established convention rather than introducing a new one; the JSON APIs that *return* the URL remain properly session- and ownership-gated (unchanged, existing infrastructure).

**Manager review:** the existing "Production Task" detail modal gained a "Production Output" block between Content Brief and History — submission type badge, an "Open Google Drive" / "View / Open Media" link, an inline `<video>` preview for video-type uploads (plain HTML5 `<video controls>`, no player library), submitted-by/submitted-at. Existing Assign/Review/Mark-Ready controls, modals, and logic: untouched.

**Automatic TAT.** `dueAt = contentDate − 1 day, 17:00` — computed once, in `createTask()`, at production-task creation, not left for a manager to fill in. Verified against every example in the spec, including the September→October month rollover. If the computed deadline already falls in the past (a late Complete Entry), it is **not** pushed forward — the task shows as overdue immediately, via the existing `dueAt < NOW()` logic, unchanged. The Assign/Reassign modal's due-date field is retained (not removed) as an optional manager override — it's now pre-filled with the auto-calculated value rather than left blank, so a manager only ever needs to type into it when deliberately overriding. Reassignment's existing `dueAt = COALESCE(?, dueAt)` already preserved TAT across an editor change; confirmed unchanged.

**Filter bar:** the five existing filters (Month, Status, Editor, Client, Overdue) were moved out of the queue card's header into their own compact filter card — one row on desktop, wrapping responsively on small screens — mirroring `social-data-entry.php`'s existing filter-bar layout. No new filter dimensions were added (Search/Platform/Feature/date-range don't exist in `listTasks()` today, and adding them was out of this phase's scope).

## Data Entry → Production handoff

`clientSocialContent.status` carries the raw-material lifecycle: `draft` → `ready` (→ `scheduled`/`posted`, used elsewhere, not by this handoff). No new column was added — `status` already supported a `ready` value, so the existing column was reused rather than building a second status system.

- **Save** (`api/social-content/save-entry.php` → `SocialContentEngine::saveEntry()`) — unchanged. Writes/updates the entry, leaves `status` exactly as the form set it (usually `draft`). **Never** creates a production task, no matter what status is saved — this is a hard rule, not an implementation detail.
- **Complete Entry** (`api/social-content/complete-entry.php`, new) — the one explicit action that:
  1. Re-validates the entry has a title or raw content (the same minimum `saveEntry()` already enforces at save time — no new business rule invented).
  2. Sets `status = 'ready'`.
  3. Creates the corresponding `socialContentProduction` row via `SocialContentProductionEngine::createTask()` (task creation logic lives in exactly one place — this endpoint doesn't reimplement it), starting at `NEW`, unassigned.
  4. Both steps run inside one DB transaction, so a `ready` entry with no production task (or vice versa) cannot happen.
- **Idempotent**: calling Complete Entry on an already-`ready`/`scheduled`/`posted` entry is a no-op on the status, and the handoff step looks up the existing task (`getTaskByContentId()`) instead of trying to create a second one — safe against double-clicks, retries, or a refreshed page re-submitting.
- **UI** (`pages/social-data-entry.php`): a filled slot shows a **Complete Entry** icon button while `status = draft`; once `ready`/`scheduled`/`posted`, that button is replaced by a passive **"Production Created"** badge. Edit/Delete remain available either way. This replaced the earlier **manual "Send to Production" button**, which has been removed — there is now exactly one path from raw material into production.

**Known limitation, not fixed in this phase**: the Add Entry modal's own Status dropdown (Draft/Ready/Scheduled/Posted, unchanged, pre-existing) lets a user pick "Ready" and click plain **Save**. That correctly does *not* create a production task (Save never does), but it does make the row's status read `ready`, so the UI will show the "Production Created" badge without an actual task existing. Fixing this would mean either changing the modal's dropdown (a UI redesign, out of scope here) or having the row driven by task-existence instead of `status` (an extra query per row, not requested). Flagging it rather than silently changing either.

## Tables

**`socialContentProduction`** — one row per production task.

| Column | Meaning |
|---|---|
| `clientSocialContentId` | the raw entry this task produces |
| `assignedEditorId` | → `employeeusers.id`, nullable until assigned |
| `status` | see lifecycle below |
| `assignedAt`, `dueAt`, `submittedAt`, `approvedAt` | workflow timestamps |
| `createdBy` | who sent the entry to production |

**`socialContentProductionHistory`** — append-only log, one row per assignment/status change. Never overwritten, so every correction cycle stays visible. `performedBy` + `performedByType` (`'admin'` or `'employee'`) together identify who acted, since managers (`users`) and editors (`employeeusers`) are different tables.

## Status lifecycle

```
NEW ──assign──► ASSIGNED ──start──► IN_PROGRESS ──submit──► SUBMITTED ──approve──► APPROVED ──mark ready──► PRODUCTION_READY
                   ▲                     ▲                       │
                   │                     └───────start────────CORRECTION ◄──request correction──┘
                   └──reassign (manager, from ASSIGNED/IN_PROGRESS/CORRECTION)
```

| Status | Meaning |
|---|---|
| `NEW` | Sent to production, no editor yet |
| `ASSIGNED` | Editor assigned, hasn't started |
| `IN_PROGRESS` | Editor actively working |
| `SUBMITTED` | Editor submitted for review |
| `CORRECTION` | Manager sent it back with a required remark |
| `APPROVED` | Manager approved the work |
| `PRODUCTION_READY` | Terminal state for this phase |

All transitions are validated server-side in `includes/SocialContentProductionEngine.php` (a single transition table) — a request for an out-of-order status change is rejected regardless of what the frontend sends.

## Assignment flow

The manager assigns a task to an active `employeeusers` row with `designationName = 'Video Editor'`. Reassignment is allowed from `ASSIGNED`, `IN_PROGRESS`, or `CORRECTION` — the old assignee isn't deleted from history, just superseded (`assigned`/`reassigned` history rows track it).

## Correction flow

`SUBMITTED → CORRECTION` **requires** a remark — the engine throws if one isn't provided. The editor then moves `CORRECTION → IN_PROGRESS → SUBMITTED` again; this loop can repeat any number of times, and every cycle's remark is preserved in `socialContentProductionHistory`, never overwritten.

## Approval flow

Only a `SUBMITTED` task can be reviewed. The manager chooses **Approve** (`→ APPROVED`) or **Request Correction** (`→ CORRECTION`, remark required). A separate **Mark Ready** action (`APPROVED → PRODUCTION_READY`) is the manager's explicit final checkpoint — approval and "ready for the next stage" are kept as two distinct steps on purpose.

## TAT (turnaround time)

`dueAt` is a concrete timestamp, settable/updatable by the manager (at assignment or independently). The UI computes overdue as `dueAt < now AND status NOT IN ('APPROVED','PRODUCTION_READY')`. `submittedAt`/`approvedAt` let the UI later show whether work finished before or after `dueAt` — no SLA engine, no multi-level rules, just the one timestamp per spec.

## Employee access — sidebar and permission

`/emp-content-production` is registered in `routesMaster` (moduleName `Employee Panel`) with a `rolePermissions` row granting the `Video Editor` designation `canView` + `canEdit` (not `canApprove`, `canAdd`, or `canDelete`) — the minimum needed to see the page and act on their own tasks, nothing more.

That permission row existed from Phase 1, but the page was unreachable through normal navigation: `includes/emp-sidebar.php` builds the menu from a hardcoded PHP array (`$menuGroups`), and `routesMaster`/`rolePermissions` only gate *whether an item already in that array is allowed to render* — they don't add new items. Phase 2 added one entry to the existing "Employee Panel" group in that array:

```php
['route' => 'emp-content-production', 'label' => 'Content Production'],
```

It goes through the exact same `$canRenderMenuRoute()` check every other item already uses (`routesMaster.isMenuVisible` **and** `hasRoutePermission($route, 'canView')`) — no bypass, no new gating mechanism. Verified by simulating an employee session directly against `hasRoutePermission()`: with the role's `canView` on, the item renders and the route loads; flipping `canView` to `0` and back confirmed the item disappears and reappears in lock-step, then the row was restored to its original value.

## Manager sidebar

`/social-content-production` is listed under the existing "Social Media" group in `includes/sidebar.php`, next to Calendar and Social Media Data, through the same hardcoded-array-plus-permission-gate mechanism `emp-sidebar.php` uses (routesMaster's `isMenuVisible` and `hasRoutePermission()` — no bypass). Admins reach it unconditionally, matching every other page in that group.

## Manager vs. employee separation

`/social-content-production` (manager) and `/emp-content-production` (employee) are separate routes, separate pages, separate sessions — `includes/auth.php` (admin, `$_SESSION['userId']`) vs `includes/emp-auth.php` (employee, `$_SESSION['candidateId']`). A Video Editor does not need, and was not given, any new access to the manager route. `pages/social-content-production.php` starts with `include auth.php`, which unconditionally requires `$_SESSION['userId']` and redirects otherwise — an employee session never has that, regardless of anything in `rolePermissions`/`userPermissionOverrides`.

**Cleanup performed in Phase 2.1:** a stray `rolePermissions` row and a stray `userPermissionOverrides` row — both granting the `Video Editor` designation/one specific employee full manager-level access to `/social-content-production` — were confirmed inert across three separate audits (the `auth.php` gate above makes them unreachable regardless of their content) and removed. Nothing else in either table was touched; row counts before/after confirmed only these two records changed.

## Editor list source

Both the manager page's "Editor" filter dropdown and the Assign Editor modal's dropdown are populated from a single fetch of `api/social-content-production/get-editors.php` (`SELECT id, fullName FROM employeeusers WHERE employmentStatus='Active' AND designationName='Video Editor'`) — one API call, two `<select>` elements filled from the same response, no duplicate query. Fixed in Phase 2.1: the modal's dropdown was previously left empty in markup and never populated in JS at all (a plain oversight, not an API or data problem — the filter dropdown, fed by the exact same endpoint, always worked), which made assignment impossible since there was nothing to select.

## Assignment / reassignment

The Assign Editor modal (`#scpAssignModal`) is reused for both first assignment and reassignment — its title and the dropdown's pre-selected value adapt to whether the task already has an editor. Both paths call the same `manage-task.php` `action:'assign'` → `SocialContentProductionEngine::assign()`, which records `assigned` or `reassigned` in history depending on whether an editor was already set — verified to preserve both events distinctly, never overwriting the prior assignment's history row.

## Manager responsibilities (`pages/social-content-production.php`, admin session)

View the production queue (filterable by status/editor/client/month/overdue), assign/reassign editors, set due dates, review submitted work (approve or request correction with a remark), mark approved work production-ready, view full history per task.

## Editor responsibilities (`employee/emp-content-production.php`, employee session)

View only their own assigned tasks (server-enforced — the query is hard-scoped to `assignedEditorId = $_SESSION['candidateId']`, never a client-supplied id), start work, submit for review with an optional note, view their own task history. Editors cannot approve their own work — there is no approve action anywhere on the editor-side API or page.

## Security model

- Manager pages/APIs: admin session (`includes/auth.php`, `$_SESSION['userId']`) — same trust level as the existing Data Entry/Overview pages.
- Editor pages/APIs: employee session (`includes/emp-auth.php`, `$_SESSION['candidateId']`) — every read and write is scoped to the logged-in employee's own id inside the SQL itself, re-verified on every mutation (mirrors `employeeInfoEngine.php`'s leave-cancellation ownership check).
- All state-changing endpoints require a valid CSRF token (`includes/Csrf.php`).
- All queries use prepared statements.
- No `companyId` concept introduced — client scoping follows the existing `clientId`/`clientMaster` pattern used throughout the app.

## What is intentionally NOT implemented in this phase

- No content-production fields yet: no caption editor, song library, title/description management, media upload, thumbnail selection, versioning, or hashtag generation.
- No connection to `socialPosts`, `SocialPostEngine.php`, `InstagramAutomation.php`, or `FacebookPublisher.php` — none of those files were touched, and nothing in this phase writes to `socialPosts`.
- No cron/scheduler changes.

**`PRODUCTION_READY` does not yet hand off to Social Media Automation.** Reaching that status is purely a business-state marker for this phase.

## Future handoff (not built yet)

A later phase will define a controlled, explicit action that takes a `PRODUCTION_READY` `socialContentProduction` row and creates the corresponding `socialPosts` draft (with the content-production fields — caption, media, song, etc. — added by then). That handoff is deliberately out of scope here so it can be designed with the real production fields in hand, rather than guessed at now.

## Development rule (project-wide, not specific to this module)

`/social-content-production` and `/emp-content-production` both had correct `routesMaster`/permission records for one to two phases before either was reachable through actual navigation — `includes/sidebar.php` and `includes/emp-sidebar.php` build their menus from a hardcoded PHP array, and a `routesMaster` row alone does not add an entry to it.

**Whenever a new page or route is introduced, its sidebar/menu entry must be added in the same change** — not deferred to a later cleanup pass. Checklist: (1) `routesMaster` row exists, (2) the correct sidebar file's menu-group array has an entry pointing at it, (3) that entry uses the existing permission-aware rendering path (no bypass), (4) the route is reachable and correctly denied/hidden per the normal permission rules.
