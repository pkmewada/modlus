<?php

require_once __DIR__ . '/SocialContentProductionEngine.php';
require_once __DIR__ . '/InstagramAutomation.php'; // read-only reuse of instagramAccountBelongsToClient() — not modified

/*
|--------------------------------------------------------------------------
| Social Automation Handoff Engine
|--------------------------------------------------------------------------
|
| Owns exactly the PRODUCTION_READY -> Automation boundary (Architecture
| Lock, approved).
|
| Phase 4.1: eligibility validation (task exists, status is
| PRODUCTION_READY), platform eligibility validation (Instagram/Facebook
| only, resolved by name from deliverablePlatforms, never by hardcoded id),
| and the idempotent handoff-row creation primitive.
|
| Phase 4.2: automatic destination-account resolution, folded into
| checkEligibility() per the locked 0/1/2+ rule — no account selection UI,
| no mapping table. Reuses instagramAccounts (already client-scoped) and
| the existing instagramAccountBelongsToClient() guard.
|
| Phase 4.3 (this change): resolveMedia() — inspects the task's latest
| submission (server upload or Drive link) and reports whether it is
| usable as Automation media. V1 scope is image/JPEG only, per the
| Architecture Lock. Kept separate from checkEligibility() on purpose —
| media validity is an orthogonal concern from platform/account
| eligibility, not folded into that method's return shape. The production
| file itself is never moved, copied, or deleted; no Drive download, no
| Drive API call.
|
| Phase 4.4 (this change): resolveAndRegisterHandoff() now performs the
| complete Production -> socialPosts handoff (ordered-write, no DB
| transaction -- see its own doc comment). It creates a real socialPosts
| row via the EXISTING, unmodified saveSocialPost(), called in insert mode
| only. The handoff row's own status ('pending' -> 'sent'/'failed') is the
| source of truth for whether that actually succeeded.
|
| None of these phases touch SocialPostEngine.php, InstagramAutomation.php,
| FacebookPublisher.php, cron/instagramScheduler.php, or any schema. From
| here on, an existing, entirely unmodified cron/instagramScheduler.php run
| is what actually publishes the created socialPosts row.
|
| Deliberately NOT implemented here (later phases):
|   - the API endpoint and UI button (Phase 4.5)
|
| Dependency direction is one-way: this engine reads
| SocialContentProductionEngine::getTask() (read-only, never mutates
| production data); SocialContentProductionEngine has no knowledge of this
| engine. This engine never writes to socialContentProduction, socialPosts,
| or any publishing table.
|
*/

class SocialAutomationHandoffEngine
{
    private $con;

    private const ELIGIBLE_PLATFORMS = ['instagram', 'facebook'];

    public function __construct($con)
    {
        $this->con = $con;
    }

    /**
     * Read-only: is this production task allowed to enter Automation right
     * now, and if so, which account would it publish through? Never
     * mutates anything — safe to call as often as needed (e.g. to decide
     * what a future "Send to Automation" button should show).
     *
     * Checks, in order: task exists -> status is exactly PRODUCTION_READY
     * -> platform (by name, not id) is Instagram or Facebook -> no handoff
     * already registered for this task -> exactly one active connected
     * Instagram account exists for the task's client (Phase 4.2). Zero or
     * multiple active accounts block eligibility — never guessed, never
     * chosen by newest/handle text.
     */
    public function checkEligibility($productionId)
    {
        $productionId = (int)$productionId;

        if ($productionId <= 0) {
            return $this->ineligible('NOT_FOUND', 'Production task not found.');
        }

        $task = (new SocialContentProductionEngine($this->con))->getTask($productionId);
        if (!$task) {
            return $this->ineligible('NOT_FOUND', 'Production task not found.');
        }

        if ($task['status'] !== 'PRODUCTION_READY') {
            return $this->ineligible('INVALID_STATUS', 'Production task is not ready for automation.', $task);
        }

        $platformName = strtolower(trim((string)($task['platformName'] ?? '')));
        if (!in_array($platformName, self::ELIGIBLE_PLATFORMS, true)) {
            return $this->ineligible('UNSUPPORTED_PLATFORM', 'This platform is not currently supported by Social Automation.', $task);
        }

        $existing = $this->findExistingHandoff($productionId);
        if ($existing) {
            return [
                'eligible' => false,
                'state' => 'ALREADY_HANDED_OFF',
                'message' => 'Automation handoff is already registered for this production task.',
                'clientId' => (int)$task['clientId'],
                'platformName' => $task['platformName'],
                'task' => $task,
                'existingHandoffId' => (int)$existing['id'],
                'resolvedAccountId' => null,
            ];
        }

        // clientId is derived from the production record itself (via
        // clientSocialContent, already resolved inside getTask()) — never
        // accepted from a caller/request.
        $clientId = (int)$task['clientId'];
        $resolution = $this->resolveActiveAccount($clientId);

        if (!$resolution['resolved']) {
            return [
                'eligible' => false,
                'state' => $resolution['state'],
                'message' => $resolution['message'],
                'clientId' => $clientId,
                'platformName' => $task['platformName'],
                'task' => $task,
                'existingHandoffId' => null,
                'resolvedAccountId' => null,
            ];
        }

        // Defense in depth: re-verify ownership through the existing,
        // unmodified guard even though resolveActiveAccount() already
        // scoped its own query by clientId.
        if (!instagramAccountBelongsToClient($this->con, $resolution['accountId'], $clientId)) {
            return [
                'eligible' => false,
                'state' => 'NO_ACTIVE_ACCOUNT',
                'message' => 'No connected Instagram account is available for this client. Connect an account before sending to Automation.',
                'clientId' => $clientId,
                'platformName' => $task['platformName'],
                'task' => $task,
                'existingHandoffId' => null,
                'resolvedAccountId' => null,
            ];
        }

        return [
            'eligible' => true,
            'state' => 'ELIGIBLE',
            'message' => 'Ready to be handed off to Automation.',
            'clientId' => $clientId,
            'platformName' => $task['platformName'],
            'task' => $task,
            'existingHandoffId' => null,
            'resolvedAccountId' => $resolution['accountId'],
        ];
    }

    /**
     * Phase 4.4: the full Production -> socialPosts handoff. This is the
     * method a future "Send to Automation" action (Phase 4.5) is expected
     * to call. Ordered-write approach, deliberately NOT wrapped in a
     * database transaction (Architecture Lock, Transaction Verification):
     * saveSocialPost() unconditionally calls ensureSocialPostsTable(),
     * which can run DDL, and DDL implicitly commits an open transaction in
     * MySQL/MariaDB — a transaction here would be silently defeated.
     *
     *   1-2. checkEligibility() -- task/status/platform/not-already-handed-
     *        off/account resolution (Phase 4.1/4.2, unchanged).
     *   3.   resolveMedia() -- Phase 4.3, unchanged.
     *   4.   registerHandoff() -- creates the 'pending' row (Phase 4.1's
     *        idempotent primitive, unchanged; UNIQUE(productionId) is the
     *        real concurrency guard, not this method).
     *   5-6. call the EXISTING saveSocialPost() in insert mode, then check
     *        the returned id -- mysqli_report(MYSQLI_REPORT_OFF) is global,
     *        so a failed insert returns 0 rather than throwing.
     *   7-8. mark the handoff 'failed' (with a stored reason) or 'sent'
     *        (with the real socialPostId) accordingly. Never marked 'sent'
     *        without a confirmed, real socialPosts row.
     *
     * registerHandoff() itself keeps its original signature and behavior
     * for direct/manual use — not redesigned by this change.
     */
    public function resolveAndRegisterHandoff($productionId, $createdBy)
    {
        $eligibility = $this->checkEligibility($productionId);

        if (!$eligibility['eligible']) {
            return [
                'success' => false,
                'state' => $eligibility['state'],
                'message' => $eligibility['message'],
                'handoffId' => $eligibility['existingHandoffId'],
                'socialPostId' => null,
            ];
        }

        $media = $this->resolveMedia($productionId);
        if (!$media['success']) {
            return [
                'success' => false,
                'state' => $media['state'],
                'message' => $media['message'],
                'handoffId' => null,
                'socialPostId' => null,
            ];
        }

        $registration = $this->registerHandoff($productionId, $eligibility['resolvedAccountId'], $createdBy);
        if (!$registration['success']) {
            // ALREADY_HANDED_OFF (a concurrent request won the UNIQUE
            // constraint) or a genuine registration failure -- either way,
            // no socialPosts row is ever created past this point.
            return [
                'success' => false,
                'state' => $registration['state'],
                'message' => $registration['message'],
                'handoffId' => $registration['handoffId'],
                'socialPostId' => null,
            ];
        }

        $handoffId = $registration['handoffId'];
        $task = $eligibility['task'];
        $platform = strtolower(trim((string)$task['platformName']));

        $socialPostId = 0;
        $saveError = null;

        try {
            $socialPostId = (int)saveSocialPost(
                $this->con,
                [
                    'clientId' => (int)$task['clientId'],
                    'instagramAccountId' => $eligibility['resolvedAccountId'],
                    'mediaType' => 'image',
                    'mediaPaths' => [$media['mediaPath']],
                    'caption' => (string)($task['caption'] ?? ''),
                    'status' => 'scheduled',
                    'scheduledAt' => date('Y-m-d H:i:s'), // immediate -- same convention api/social-media/saveSocialPost.php uses when no future time is given; the existing scheduler picks it up on its next run, unchanged
                    'platforms' => [$platform],
                ],
                (int)$createdBy
            );
        } catch (Throwable $e) {
            // saveSocialPost() should not normally throw, but never assume
            // the PHP call completing means success -- handle it the same
            // as a returned-zero failure.
            $socialPostId = 0;
            $saveError = $e->getMessage();
        }

        if ($socialPostId <= 0) {
            $message = $saveError !== null
                ? 'Failed to create the automation post: ' . $saveError
                : 'Failed to create the automation post.';
            $this->markHandoffFailed($handoffId, $message);

            return [
                'success' => false,
                'state' => 'FAILED',
                'message' => $message,
                'handoffId' => $handoffId,
                'socialPostId' => null,
            ];
        }

        $this->markHandoffSent($handoffId, $socialPostId);

        return [
            'success' => true,
            'state' => 'SENT',
            'message' => 'Sent to Automation.',
            'handoffId' => $handoffId,
            'socialPostId' => $socialPostId,
        ];
    }

    private function markHandoffFailed($handoffId, $message)
    {
        $handoffId = (int)$handoffId;
        $stmt = mysqli_prepare($this->con, "UPDATE socialContentAutomationHandoff SET status = 'failed', errorMessage = ?, updatedAt = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $message, $handoffId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    private function markHandoffSent($handoffId, $socialPostId)
    {
        $handoffId = (int)$handoffId;
        $socialPostId = (int)$socialPostId;
        $stmt = mysqli_prepare($this->con, "UPDATE socialContentAutomationHandoff SET status = 'sent', socialPostId = ?, errorMessage = NULL, updatedAt = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $socialPostId, $handoffId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Resolves the single, unambiguous destination account for a client,
     * per the Architecture Lock's account-resolution rule:
     *   0 active accounts        -> block (NO_ACTIVE_ACCOUNT)
     *   exactly 1 active account -> auto-resolve
     *   2+ active accounts       -> block (MULTIPLE_ACTIVE_ACCOUNTS)
     * "Active" matches getInstagramAccountById()'s own predicate exactly
     * (status='connected' AND (tokenExpiry IS NULL OR tokenExpiry > NOW())),
     * so a resolved account is guaranteed usable at actual publish time
     * too. Never picks by "newest", never matches on socialMediaHandle.
     */
    private function resolveActiveAccount($clientId)
    {
        $clientId = (int)$clientId;

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id FROM instagramAccounts
             WHERE clientId = ? AND status = 'connected' AND (tokenExpiry IS NULL OR tokenExpiry > NOW())"
        );
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $accountIds = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $accountIds[] = (int)$row['id'];
        }
        mysqli_stmt_close($stmt);

        if (count($accountIds) === 0) {
            return [
                'resolved' => false,
                'state' => 'NO_ACTIVE_ACCOUNT',
                'message' => 'No connected Instagram account is available for this client. Connect an account before sending to Automation.',
                'accountId' => null,
            ];
        }

        if (count($accountIds) > 1) {
            return [
                'resolved' => false,
                'state' => 'MULTIPLE_ACTIVE_ACCOUNTS',
                'message' => 'This client has more than one connected Instagram account. An administrator must resolve the account configuration before automation can proceed.',
                'accountId' => null,
            ];
        }

        return [
            'resolved' => true,
            'state' => 'RESOLVED',
            'message' => 'Account resolved.',
            'accountId' => $accountIds[0],
        ];
    }

    /**
     * Phase 4.3: inspects the production task's latest submission and
     * reports whether it is usable as Automation media, and if so, its
     * resolved public URL. Re-fetches and re-checks the task itself rather
     * than trusting a caller's earlier checkEligibility() result. Never
     * moves, copies, or deletes the production file; never calls a Drive
     * API. V1 scope, per the Architecture Lock: image/JPEG only.
     *
     * @return array{success:bool,state:string,message:string,mediaType:?string,mediaUrl:?string}
     */
    public function resolveMedia($productionId)
    {
        $productionId = (int)$productionId;

        if ($productionId <= 0) {
            return $this->mediaResult(false, 'NOT_FOUND', 'Production task not found.');
        }

        $task = (new SocialContentProductionEngine($this->con))->getTask($productionId);
        if (!$task) {
            return $this->mediaResult(false, 'NOT_FOUND', 'Production task not found.');
        }

        if ($task['status'] !== 'PRODUCTION_READY') {
            return $this->mediaResult(false, 'INVALID_STATUS', 'Production task is not ready for automation.');
        }

        $submissionType = (string)($task['submissionType'] ?? '');
        $submissionUrl = trim((string)($task['submissionUrl'] ?? ''));

        if ($submissionType === 'drive') {
            // Same pattern api/social-content-production/emp-submit-production.php
            // already validates at submission time — re-checked here rather
            // than trusted blindly.
            if (!preg_match('#^https://(drive|docs)\.google\.com/#i', $submissionUrl)) {
                return $this->mediaResult(false, 'INVALID_DRIVE_URL', 'The stored Drive link is not a valid Google Drive/Docs URL.');
            }

            // Locked decision (Architecture Lock, Decision #5): a Drive
            // share link returns Drive's HTML viewer to an unauthenticated
            // fetch, not raw image bytes — Meta's Graph API image_url fetch
            // requires a direct, publicly fetchable media URL. No download,
            // no Drive API integration — this is published manually.
            return $this->mediaResult(false, 'MANUAL_PUBLISH_REQUIRED', 'This item was submitted as a Google Drive link and cannot be published automatically. Publish it manually via the Social Post Composer.');
        }

        if ($submissionType === 'media') {
            if ($submissionUrl === '') {
                return $this->mediaResult(false, 'MISSING_SUBMISSION', 'No production file is on record for this task.');
            }

            $relativePath = ltrim($submissionUrl, '/');
            $fullPath = dirname(__DIR__) . '/' . $relativePath;

            if (!is_file($fullPath)) {
                return $this->mediaResult(false, 'MISSING_FILE', 'The submitted production file could not be found on disk.');
            }

            // Real content-type detection only — never the file extension,
            // never a browser-supplied type.
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? finfo_file($finfo, $fullPath) : false;
            if ($finfo) {
                finfo_close($finfo);
            }

            if ($mimeType === 'image/jpeg') {
                return $this->mediaResult(true, 'RESOLVED', 'Media resolved.', 'image', BASE_URL . '/' . $relativePath, $relativePath);
            }

            if ($mimeType === 'image/png') {
                // Verified against includes/InstagramAutomation.php's own
                // saveInstagramMediaFile(): "Meta's Content Publishing API
                // only accepts JPEG for image_url-based posts (image and
                // carousel) — PNG uploads would pass our own validation but
                // fail silently at Meta's end." Rejected here, not
                // converted — no image-processing system exists in this
                // codebase and none is being added for this.
                return $this->mediaResult(false, 'UNSUPPORTED_IMAGE_FORMAT', 'This production file is a PNG. Only JPEG images can currently be sent to Automation.');
            }

            return $this->mediaResult(false, 'NOT_AN_IMAGE', 'The submitted file is not a supported image (its real content type is not JPEG).');
        }

        return $this->mediaResult(false, 'MISSING_SUBMISSION', 'No production submission is on record for this task.');
    }

    private function mediaResult($success, $state, $message, $mediaType = null, $mediaUrl = null, $mediaPath = null)
    {
        return [
            'success' => $success,
            'state' => $state,
            'message' => $message,
            'mediaType' => $mediaType,
            'mediaUrl' => $mediaUrl,   // absolute, informational
            'mediaPath' => $mediaPath, // relative -- the form saveSocialPost()'s mediaPaths expects (scheduler prepends BASE_URL itself at publish time)
        ];
    }

    /**
     * The idempotent handoff-row creation primitive. Not wired to any
     * automatic Phase 4.1 flow — Phase 4.1 has no legitimate source for
     * $instagramAccountId (account resolution is Phase 4.2), so nothing in
     * this phase calls this method with real request data. It exists now,
     * fully working, so later phases only need to supply a resolved
     * account id — no new locking logic to design then.
     *
     * Always re-validates eligibility itself — never trusts a caller's
     * earlier checkEligibility() result, since state can change between
     * the two calls.
     *
     * Concurrency: relies on the UNIQUE(productionId) database constraint,
     * not a SELECT-then-INSERT check — two racing requests both attempt the
     * INSERT; exactly one succeeds. mysqli_report(MYSQLI_REPORT_OFF) is set
     * globally (includes/db.php), so a failed statement does not throw —
     * the duplicate-key case (errno 1062) is detected explicitly via
     * mysqli_errno() and treated as "already handed off", not an error.
     *
     * @throws Exception if $instagramAccountId is not a real, positive id —
     *   this method must never silently accept a fabricated/zero account.
     */
    public function registerHandoff($productionId, $instagramAccountId, $createdBy)
    {
        $productionId = (int)$productionId;
        $instagramAccountId = (int)$instagramAccountId;
        $createdBy = (int)$createdBy;

        if ($instagramAccountId <= 0) {
            throw new Exception('A resolved Instagram account is required before a handoff can be registered.');
        }

        $eligibility = $this->checkEligibility($productionId);
        if (!$eligibility['eligible']) {
            return [
                'success' => false,
                'state' => $eligibility['state'],
                'message' => $eligibility['message'],
                'handoffId' => $eligibility['existingHandoffId'],
            ];
        }

        $status = 'pending';
        $stmt = mysqli_prepare(
            $this->con,
            'INSERT INTO socialContentAutomationHandoff (productionId, instagramAccountId, status, createdBy, createdAt) VALUES (?, ?, ?, ?, NOW())'
        );
        mysqli_stmt_bind_param($stmt, 'iisi', $productionId, $instagramAccountId, $status, $createdBy);
        $executed = mysqli_stmt_execute($stmt);

        if (!$executed) {
            $errno = mysqli_errno($this->con);
            mysqli_stmt_close($stmt);

            if ($errno === 1062) {
                // A concurrent request won the race — not an error, just
                // the outcome the UNIQUE constraint exists to guarantee.
                $existing = $this->findExistingHandoff($productionId);
                return [
                    'success' => false,
                    'state' => 'ALREADY_HANDED_OFF',
                    'message' => 'Automation handoff is already registered for this production task.',
                    'handoffId' => $existing ? (int)$existing['id'] : null,
                ];
            }

            return [
                'success' => false,
                'state' => 'FAILED',
                'message' => 'Failed to register the automation handoff. Please try again.',
                'handoffId' => null,
            ];
        }

        $handoffId = (int)mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        return [
            'success' => true,
            'state' => 'CREATED',
            'message' => 'Automation handoff registered.',
            'handoffId' => $handoffId,
        ];
    }

    private function findExistingHandoff($productionId)
    {
        $productionId = (int)$productionId;
        $stmt = mysqli_prepare($this->con, 'SELECT id, status, socialPostId FROM socialContentAutomationHandoff WHERE productionId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $productionId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    private function ineligible($state, $message, $task = null)
    {
        return [
            'eligible' => false,
            'state' => $state,
            'message' => $message,
            'clientId' => $task ? (int)$task['clientId'] : null,
            'platformName' => $task ? $task['platformName'] : null,
            'task' => $task,
            'existingHandoffId' => null,
            'resolvedAccountId' => null,
        ];
    }
}
