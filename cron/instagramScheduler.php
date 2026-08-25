<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the CLI.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';
require_once __DIR__ . '/../includes/leadActivityLogger.php';

date_default_timezone_set('Asia/Kolkata');

function instagramSchedulerLog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(__DIR__ . '/instagramScheduler.log', $line, FILE_APPEND);
    echo $line;
}

function handleInstagramAuthFailure(mysqli $con, array &$accountCache, array $account, Throwable $e): void
{
    if (!isInstagramAuthError($e)) {
        return;
    }

    disconnectInstagramAccount($con, $account['id']);
    // Invalidate the cache entry so any other due post sharing this account
    // in the same run correctly fails too, instead of reusing a stale
    // "connected" copy of it.
    $accountCache[$account['id']] = null;

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);
    instagramSchedulerLog(
        'Instagram account #' . $account['id'] . ' (Client: ' . $clientLabel . ') access token is invalid/expired — marked disconnected.'
    );
}

/**
 * Every post carries its own instagramAccountId — this loads (and caches
 * for the rest of the run) the specific account it must publish through.
 * A post is never published using "whichever account connected most
 * recently"; if its own account isn't connected/valid, the post fails
 * rather than silently falling back to a different client's account.
 */
function loadInstagramAccountForPost(mysqli $con, array &$accountCache, array $post): ?array
{
    $accountId = (int)($post['instagramAccountId'] ?? 0);

    if ($accountId <= 0) {
        return null;
    }

    if (array_key_exists($accountId, $accountCache)) {
        return $accountCache[$accountId];
    }

    return $accountCache[$accountId] = getInstagramAccountById($con, $accountId);
}

/*
|--------------------------------------------------------------------------
| Single-instance lock — prevents an overrunning invocation from overlapping
| with the next scheduled tick and double-processing/double-publishing posts.
|--------------------------------------------------------------------------
*/
$lockHandle = fopen(__DIR__ . '/.instagramScheduler.lock', 'c');

if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    instagramSchedulerLog('Another instagramScheduler run is already in progress. Exiting.');
    exit(0);
}

register_shutdown_function(static function () use ($lockHandle) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

$accountCache = [];

/*
|--------------------------------------------------------------------------
| Phase A: finalize Reels still processing at Meta (across all clients)
|--------------------------------------------------------------------------
*/
$processingPosts = getInstagramPostsByStatus($con, 'publishing', 20);

foreach ($processingPosts as $post) {
    $postId = (int)$post['id'];
    $account = loadInstagramAccountForPost($con, $accountCache, $post);

    if (!$account) {
        markInstagramPostFailed($con, $postId, 'Linked Instagram account is no longer connected or its token has expired.');
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed: linked account unavailable.');
        instagramSchedulerLog('Post #' . $postId . ' failed: linked Instagram account unavailable.');
        continue;
    }

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);

    try {
        $statusCode = getInstagramContainerStatus($account, (string)$post['instagramMediaId']);

        if ($statusCode === 'FINISHED') {
            $mediaId = publishInstagramContainer($account, (string)$post['instagramMediaId']);
            markInstagramPostPublished($con, $postId, $mediaId);
            saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram video post via scheduler for Client: ' . $clientLabel . '.');
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') published (video). Media ID: ' . $mediaId);
        } elseif ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
            markInstagramPostFailed($con, $postId, 'Meta reported video processing status: ' . $statusCode);
            saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram video post failed at Meta for Client: ' . $clientLabel . ' (' . $statusCode . ').');
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') failed. Status: ' . $statusCode);
        } elseif ($statusCode === 'IN_PROGRESS') {
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') still processing at Meta (IN_PROGRESS). Will retry next run.');
        } else {
            // Unexpected status (e.g. PUBLISHED without our own record of
            // finishing the publish call) — don't retry forever; surface it.
            markInstagramPostFailed($con, $postId, 'Unexpected Meta processing status: ' . $statusCode . '. Please verify manually on Instagram.');
            saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post has unexpected status for Client: ' . $clientLabel . ' (' . $statusCode . ').');
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') has unexpected status ' . $statusCode . '; marked failed for manual review.');
        }
    } catch (InstagramTransientApiException $e) {
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') status check failed transiently, will retry next run: ' . $e->getMessage());
    } catch (Throwable $e) {
        markInstagramPostFailed($con, $postId, $e->getMessage());
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed for Client: ' . $clientLabel . ' (' . $e->getMessage() . ').');
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') failed: ' . $e->getMessage());
        handleInstagramAuthFailure($con, $accountCache, $account, $e);
    }
}

/*
|--------------------------------------------------------------------------
| Phase B: publish newly due scheduled posts (across all clients)
|--------------------------------------------------------------------------
*/
$duePosts = getDueInstagramPosts($con, 20);

foreach ($duePosts as $post) {
    $postId = (int)$post['id'];
    $account = loadInstagramAccountForPost($con, $accountCache, $post);

    if (!$account) {
        markInstagramPostFailed($con, $postId, 'Linked Instagram account is no longer connected or its token has expired.');
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed: linked account unavailable.');
        instagramSchedulerLog('Post #' . $postId . ' failed: linked Instagram account unavailable.');
        continue;
    }

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);
    markInstagramPostPublishing($con, $postId);

    try {
        $mediaUrls = instagramPostMediaAbsoluteUrls(decodeInstagramPostMediaPaths($post['mediaUrl']));
        $caption = (string)$post['caption'];

        switch ($post['mediaType']) {
            case 'carousel':
                $result = publishInstagramCarouselPost($account, $mediaUrls, $caption);
                markInstagramPostPublished($con, $postId, $result['instagramMediaId']);
                saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram carousel post via scheduler for Client: ' . $clientLabel . '.');
                instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') published (carousel). Media ID: ' . $result['instagramMediaId']);
                break;

            case 'reel':
                $result = publishInstagramVideoPost($account, $mediaUrls[0] ?? '', $caption);

                if ($result['status'] === 'published') {
                    markInstagramPostPublished($con, $postId, $result['instagramMediaId']);
                    saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram reel via scheduler for Client: ' . $clientLabel . '.');
                    instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') published (reel). Media ID: ' . $result['instagramMediaId']);
                } else {
                    updateInstagramPostContainerId($con, $postId, $result['instagramMediaId']);
                    instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') video container started (' . $result['instagramMediaId'] . '). Will finalize next run.');
                }
                break;

            case 'image':
            default:
                $result = publishInstagramImagePost($account, $mediaUrls[0] ?? '', $caption);
                markInstagramPostPublished($con, $postId, $result['instagramMediaId']);
                saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram image post via scheduler for Client: ' . $clientLabel . '.');
                instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') published (image). Media ID: ' . $result['instagramMediaId']);
                break;
        }
    } catch (InstagramTransientApiException $e) {
        // Network-level blip, not Meta rejecting the post — requeue rather
        // than permanently failing it.
        revertInstagramPostToScheduled($con, $postId);
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') publish failed transiently, requeued for retry: ' . $e->getMessage());
    } catch (Throwable $e) {
        markInstagramPostFailed($con, $postId, $e->getMessage());
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed for Client: ' . $clientLabel . ' (' . $e->getMessage() . ').');
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') failed: ' . $e->getMessage());
        handleInstagramAuthFailure($con, $accountCache, $account, $e);
    }
}

instagramSchedulerLog(
    'Scheduler run complete. Finalized ' . count($processingPosts) . ' in-flight, published ' . count($duePosts) . ' newly due.'
);
