<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission-helper.php';
require_once __DIR__ . '/../includes/Csrf.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';
require_once __DIR__ . '/../includes/leadActivityLogger.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

$postId = (int)($_POST['postId'] ?? 0);
$clientId = (int)($_POST['clientId'] ?? 0);
$instagramAccountId = (int)($_POST['instagramAccountId'] ?? 0);
$mediaType = trim((string)($_POST['mediaType'] ?? 'image'));
$caption = trim((string)($_POST['caption'] ?? ''));
$action = trim((string)($_POST['action'] ?? 'draft'));
$scheduledAtInput = trim((string)($_POST['scheduledAt'] ?? ''));

$allowedMediaTypes = ['image', 'reel', 'carousel'];

if (!in_array($mediaType, $allowedMediaTypes, true)) {
    respond(false, 'Invalid media type.');
}

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    respond(false, 'Please select a valid client.');
}

// The core guarantee: an account must actually belong to the selected
// client. This is what makes cross-client publishing impossible — a post
// can never be saved with an account it doesn't own, regardless of what
// the client sends.
if ($instagramAccountId <= 0 || !instagramAccountBelongsToClient($con, $instagramAccountId, $clientId)) {
    respond(false, 'Please select an Instagram account that belongs to the selected client.');
}

if (strlen($caption) > 2200) {
    respond(false, 'Caption must be 2200 characters or fewer.');
}

$status = $action === 'schedule' ? 'scheduled' : 'draft';
$scheduledAt = null;
$isImmediate = false;

if ($status === 'scheduled') {
    if ($scheduledAtInput !== '') {
        $timestamp = strtotime($scheduledAtInput);

        if ($timestamp === false) {
            respond(false, 'Please enter a valid schedule date/time.');
        }

        $scheduledAt = date('Y-m-d H:i:s', $timestamp);
    } else {
        $scheduledAt = date('Y-m-d H:i:s');
        $isImmediate = true;
    }
}

$existingPost = $postId > 0 ? getInstagramPostById($con, $postId) : null;

if ($postId > 0 && !$existingPost) {
    respond(false, 'Instagram post not found.');
}

if ($existingPost && !in_array($existingPost['status'], ['draft', 'scheduled', 'failed'], true)) {
    respond(false, 'This post can no longer be edited.');
}

$mediaCategory = $mediaType === 'reel' ? 'video' : 'image';
$maxFiles = $mediaType === 'carousel' ? 10 : 1;

// Only carry forward existing media when the post type is unchanged —
// otherwise a post left with mismatched media (e.g. an image reused as a
// reel's "video") would fail confusingly at Meta's end instead of here.
$mediaPaths = ($existingPost && $existingPost['mediaType'] === $mediaType)
    ? decodeInstagramPostMediaPaths($existingPost['mediaUrl'])
    : [];

if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0] ?? '')) {
    $uploadResult = saveInstagramMediaFiles($_FILES['media'], $mediaCategory, $maxFiles);

    if (!empty($uploadResult['errors'])) {
        respond(false, implode(' ', $uploadResult['errors']));
    }

    if (!empty($uploadResult['paths'])) {
        $mediaPaths = $uploadResult['paths'];
    }
}

if (empty($mediaPaths)) {
    respond(false, 'Please upload media for this post.');
}

if ($mediaType === 'carousel' && count($mediaPaths) < 2) {
    respond(false, 'A carousel post needs at least 2 images.');
}

if ($mediaType !== 'carousel' && count($mediaPaths) > 1) {
    $mediaPaths = [$mediaPaths[0]];
}

try {
    $savedId = saveInstagramPost(
        $con,
        [
            'id' => $postId,
            'clientId' => $clientId,
            'instagramAccountId' => $instagramAccountId,
            'mediaType' => $mediaType,
            'mediaPaths' => $mediaPaths,
            'caption' => $caption,
            'status' => $status,
            'scheduledAt' => $scheduledAt,
        ],
        getLoggedInUserId()
    );

    $isUpdate = $postId > 0;
    $clientLabel = getInstagramClientLabel($con, $clientId);

    if ($status === 'draft') {
        $auditLabel = 'draft';
        $successMessage = 'Instagram post saved as draft.';
    } elseif ($isImmediate) {
        $auditLabel = 'scheduled - immediate';
        $successMessage = 'Instagram post queued for publishing. It will go live once the scheduler next processes it.';
    } else {
        $auditLabel = 'scheduled - future';
        $successMessage = 'Instagram post scheduled for ' . date('M j, Y g:i A', strtotime($scheduledAt)) . '.';
    }

    saveActivityLog(
        $con,
        'InstagramAutomation',
        $savedId,
        $isUpdate ? 'update' : 'create',
        'Instagram post ' . ($isUpdate ? 'updated' : 'created') . ' (' . $auditLabel . ') for Client: ' . $clientLabel . '.'
    );

    respond(true, $successMessage, [
        'post' => getInstagramPostById($con, $savedId),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
