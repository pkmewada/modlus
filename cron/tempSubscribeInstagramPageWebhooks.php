<?php

/*
|--------------------------------------------------------------------------
| TEMPORARY, ONE-TIME production tool — Phase 11 webhook subscription fix.
| NOT a cron job. Do not register on Hostinger Cron. Delete after use.
|
| Purpose: subscribe an ALREADY-CONNECTED Instagram account's linked
| Facebook Page for webhook delivery (POST /{page-id}/subscribed_apps),
| then immediately re-fetch the subscription list (GET, same edge) to
| confirm it took effect and that "comments" is included — one run, two
| calls, no polling. Needed once for accounts connected before
| subscribeInstagramPageWebhooks() (includes/InstagramAutomation.php) was
| added to the OAuth callback; every future connect/reconnect calls it
| automatically, so this script should not be needed again after this run.
|
| Reuses the exact same account-loading, token-decryption, and Graph API
| request path the application already uses everywhere else — no second
| auth mechanism. Never prints, logs, or stores the access token.
|
| Usage:
|   php cron/tempSubscribeInstagramPageWebhooks.php <instagramAccountId>
|--------------------------------------------------------------------------
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the CLI.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';

$accountId = (int)($argv[1] ?? 0);

if ($accountId <= 0) {
    fwrite(STDERR, "Usage: php cron/tempSubscribeInstagramPageWebhooks.php <instagramAccountId>\n");
    exit(1);
}

$account = getInstagramAccountById($con, $accountId);

if (!$account) {
    fwrite(STDERR, "No connected Instagram account found with id {$accountId}.\n");
    exit(1);
}

if ($account['facebookPageId'] === '') {
    fwrite(STDERR, "Account #{$accountId} has no linked facebookPageId.\n");
    exit(1);
}

$pageId = $account['facebookPageId'];

echo "Subscribing Page {$pageId} to webhook field \"comments\"...\n";

try {
    $subscribeResult = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $pageId . '/subscribed_apps',
        [
            'access_token' => $account['accessToken'],
            'subscribed_fields' => 'comments',
        ],
        'POST'
    );

    echo "Subscribe response:\n" . json_encode($subscribeResult, JSON_PRETTY_PRINT) . "\n\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Subscribe call failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Verifying current subscription state for Page {$pageId}...\n";

try {
    $verifyResult = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $pageId . '/subscribed_apps',
        ['access_token' => $account['accessToken']],
        'GET'
    );

    // Neither response contains the access token (Meta's subscribed_apps
    // edge returns app id/name/subscribed_fields only) — safe to print whole.
    echo "Verify response:\n" . json_encode($verifyResult, JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Verify call failed: ' . $e->getMessage() . "\n");
    exit(1);
}
