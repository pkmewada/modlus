<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Crypto.php';

function ensureInstagramSettingsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS instagramSettings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            metaAppId VARCHAR(191) NOT NULL DEFAULT '',
            metaAppSecret TEXT NOT NULL,
            redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
            webhookVerifyToken VARCHAR(191) NOT NULL DEFAULT '',
            isActive TINYINT(1) NOT NULL DEFAULT 1,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    instagramSettingsEnsureColumn($con, 'webhookVerifyToken', "VARCHAR(191) NOT NULL DEFAULT ''");
}

function instagramSettingsEnsureColumn(mysqli $con, string $column, string $definition): void
{
    $columnName = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM instagramSettings LIKE '{$columnName}'");

    if ($result && mysqli_num_rows($result) > 0) {
        return;
    }

    mysqli_query($con, "ALTER TABLE instagramSettings ADD COLUMN {$column} {$definition}");
}

function ensureInstagramAccountsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS instagramAccounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            clientId INT NULL DEFAULT NULL,
            instagramUserId VARCHAR(64) NOT NULL DEFAULT '',
            facebookPageId VARCHAR(64) NOT NULL DEFAULT '',
            username VARCHAR(180) NOT NULL DEFAULT '',
            accessToken TEXT NOT NULL,
            tokenExpiry DATETIME NULL DEFAULT NULL,
            lastAnalyticsSyncAt DATETIME NULL DEFAULT NULL,
            lastAnalyticsSyncError TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'connected',
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniqInstagramUserId (instagramUserId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    instagramAccountsEnsureColumn($con, 'clientId', 'INT NULL DEFAULT NULL');
    instagramAccountsEnsureColumn($con, 'lastAnalyticsSyncAt', 'DATETIME NULL DEFAULT NULL');
    instagramAccountsEnsureColumn($con, 'lastAnalyticsSyncError', 'TEXT NULL');
}

function instagramAccountsEnsureColumn(mysqli $con, string $column, string $definition): void
{
    $columnName = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM instagramAccounts LIKE '{$columnName}'");

    if ($result && mysqli_num_rows($result) > 0) {
        return;
    }

    mysqli_query($con, "ALTER TABLE instagramAccounts ADD COLUMN {$column} {$definition}");
}

function getInstagramSettingsDefaults(): array
{
    return [
        'metaAppId' => '',
        'redirectUrl' => '',
        'webhookVerifyToken' => '',
        'hasAppSecret' => false,
    ];
}

function getInstagramSettingsRow(mysqli $con): ?array
{
    ensureInstagramSettingsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramSettings WHERE isActive = 1 ORDER BY id DESC LIMIT 1"
    );

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function getInstagramSettings(mysqli $con): array
{
    $row = getInstagramSettingsRow($con);
    $defaults = getInstagramSettingsDefaults();

    if (!$row) {
        return $defaults;
    }

    return [
        'metaAppId' => (string)$row['metaAppId'],
        'redirectUrl' => (string)$row['redirectUrl'],
        'webhookVerifyToken' => (string)$row['webhookVerifyToken'],
        'hasAppSecret' => trim((string)$row['metaAppSecret']) !== '',
    ];
}

function getInstagramSettingsForOAuth(mysqli $con): ?array
{
    $row = getInstagramSettingsRow($con);

    if (!$row || trim((string)$row['metaAppId']) === '' || trim((string)$row['metaAppSecret']) === '') {
        return null;
    }

    return [
        'metaAppId' => (string)$row['metaAppId'],
        'metaAppSecret' => decryptSecret((string)$row['metaAppSecret']),
        'redirectUrl' => (string)$row['redirectUrl'],
        'webhookVerifyToken' => (string)$row['webhookVerifyToken'],
    ];
}

function saveInstagramSettings(mysqli $con, array $settings, int $userId): bool
{
    ensureInstagramSettingsTable($con);

    $metaAppId = trim((string)($settings['metaAppId'] ?? ''));
    $metaAppSecret = trim((string)($settings['metaAppSecret'] ?? ''));
    $redirectUrl = trim((string)($settings['redirectUrl'] ?? ''));

    $existing = getInstagramSettingsRow($con);

    $encryptedSecret = $metaAppSecret !== ''
        ? encryptSecret($metaAppSecret)
        : (string)($existing['metaAppSecret'] ?? '');

    // A verify token is required for Meta's webhook GET handshake — generate
    // one on first save rather than leaving it blank (an empty token would
    // make verifyMetaWebhookSignature-adjacent handshake checks meaningless).
    $webhookVerifyToken = (string)($existing['webhookVerifyToken'] ?? '');

    if ($webhookVerifyToken === '') {
        $webhookVerifyToken = bin2hex(random_bytes(20));
    }

    if ($existing) {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE instagramSettings
             SET metaAppId = ?, metaAppSecret = ?, redirectUrl = ?, webhookVerifyToken = ?
             WHERE id = ?"
        );

        if (!$stmt) {
            return false;
        }

        $existingId = (int)$existing['id'];
        mysqli_stmt_bind_param($stmt, 'ssssi', $metaAppId, $encryptedSecret, $redirectUrl, $webhookVerifyToken, $existingId);
    } else {
        $stmt = mysqli_prepare(
            $con,
            "INSERT INTO instagramSettings (metaAppId, metaAppSecret, redirectUrl, webhookVerifyToken, isActive, createdBy)
             VALUES (?, ?, ?, ?, 1, ?)"
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ssssi', $metaAppId, $encryptedSecret, $redirectUrl, $webhookVerifyToken, $userId);
    }

    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $saved;
}

function getInstagramAccounts(mysqli $con, ?int $clientId = null): array
{
    ensureInstagramAccountsTable($con);

    if ($clientId !== null) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT id, clientId, instagramUserId, facebookPageId, username, tokenExpiry,
                    lastAnalyticsSyncAt, lastAnalyticsSyncError, status, createdAt
             FROM instagramAccounts
             WHERE clientId = ?
             ORDER BY id DESC"
        );
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query(
            $con,
            "SELECT id, clientId, instagramUserId, facebookPageId, username, tokenExpiry,
                    lastAnalyticsSyncAt, lastAnalyticsSyncError, status, createdAt
             FROM instagramAccounts
             ORDER BY id DESC"
        );
    }

    $accounts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $accounts[] = $row;
    }

    return $accounts;
}

function saveInstagramAccountFromOAuth(mysqli $con, array $account, int $userId): int
{
    ensureInstagramAccountsTable($con);

    $clientId = (int)($account['clientId'] ?? 0);
    $instagramUserId = trim((string)($account['instagramUserId'] ?? ''));
    $facebookPageId = trim((string)($account['facebookPageId'] ?? ''));
    $username = trim((string)($account['username'] ?? ''));
    $encryptedToken = encryptSecret((string)($account['accessToken'] ?? ''));
    $tokenExpiry = $account['tokenExpiry'] ?? null;

    if ($instagramUserId === '' || $clientId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare($con, "SELECT id FROM instagramAccounts WHERE instagramUserId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $instagramUserId);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($existing) {
        $existingId = (int)$existing['id'];
        $stmt = mysqli_prepare(
            $con,
            "UPDATE instagramAccounts
             SET clientId = ?, facebookPageId = ?, username = ?, accessToken = ?, tokenExpiry = ?, status = 'connected'
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'issssi', $clientId, $facebookPageId, $username, $encryptedToken, $tokenExpiry, $existingId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $existingId;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO instagramAccounts
            (createdBy, clientId, instagramUserId, facebookPageId, username, accessToken, tokenExpiry, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'connected')"
    );
    mysqli_stmt_bind_param($stmt, 'iisssss', $userId, $clientId, $instagramUserId, $facebookPageId, $username, $encryptedToken, $tokenExpiry);
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

function disconnectInstagramAccount(mysqli $con, int $accountId): bool
{
    ensureInstagramAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE instagramAccounts SET status = 'disconnected', accessToken = '' WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/*
|--------------------------------------------------------------------------
| Phase 2: Content Publishing
|--------------------------------------------------------------------------
*/

function ensureInstagramPostsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS instagramPosts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            createdBy INT UNSIGNED NOT NULL DEFAULT 0,
            clientId INT NULL DEFAULT NULL,
            instagramAccountId INT UNSIGNED NULL DEFAULT NULL,
            mediaType VARCHAR(20) NOT NULL DEFAULT 'image',
            mediaUrl TEXT NOT NULL,
            caption TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            scheduledAt DATETIME NULL DEFAULT NULL,
            publishedAt DATETIME NULL DEFAULT NULL,
            instagramMediaId VARCHAR(64) NOT NULL DEFAULT '',
            errorMessage TEXT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idxStatusScheduledAt (status, scheduledAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    instagramPostsEnsureColumn($con, 'clientId', 'INT NULL DEFAULT NULL');
    instagramPostsEnsureColumn($con, 'instagramAccountId', 'INT UNSIGNED NULL DEFAULT NULL');
}

function instagramPostsEnsureColumn(mysqli $con, string $column, string $definition): void
{
    $columnName = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM instagramPosts LIKE '{$columnName}'");

    if ($result && mysqli_num_rows($result) > 0) {
        return;
    }

    mysqli_query($con, "ALTER TABLE instagramPosts ADD COLUMN {$column} {$definition}");
}

/**
 * Loads a specific Instagram account by id — the account a given post is
 * actually tied to. This replaces the old "always use whichever account
 * connected most recently" lookup now that accounts are client-scoped:
 * a post must always publish through its own instagramAccountId, never
 * an arbitrary "primary" account.
 */
function getInstagramAccountById(mysqli $con, int $accountId): ?array
{
    ensureInstagramAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramAccounts
         WHERE id = ? AND status = 'connected' AND (tokenExpiry IS NULL OR tokenExpiry > NOW())
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'clientId' => $row['clientId'] !== null ? (int)$row['clientId'] : null,
        'instagramUserId' => (string)$row['instagramUserId'],
        'username' => (string)$row['username'],
        'accessToken' => decryptSecret((string)$row['accessToken']),
    ];
}

/**
 * Confirms an Instagram account belongs to a given client — the core
 * guarantee that prevents cross-client publishing. Used both when saving a
 * post (client picked an account that isn't theirs) and defensively.
 */
function instagramAccountBelongsToClient(mysqli $con, int $accountId, int $clientId): bool
{
    ensureInstagramAccountsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT id FROM instagramAccounts WHERE id = ? AND clientId = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $accountId, $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return (bool)$row;
}

/**
 * Confirms a clientId corresponds to a real, active clientMaster row —
 * reuses the same clientMaster + leads join already established by
 * DeliverableEngine::getClients() / calendarEngine.php's getClients().
 */
function instagramClientExists(mysqli $con, int $clientId): bool
{
    $stmt = mysqli_prepare($con, "SELECT id FROM clientMaster WHERE id = ? AND status = 'active' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    return (bool)$row;
}

/**
 * Human-readable client label for audit log / redirect messages —
 * "Full Name (CLIENT-CODE)", matching the display format already used by
 * the existing client selector dropdowns (client-deliverable.js, calendar.php).
 */
function getInstagramClientLabel(mysqli $con, ?int $clientId): string
{
    if ($clientId === null || $clientId <= 0) {
        return 'Unassigned';
    }

    $stmt = mysqli_prepare(
        $con,
        "SELECT cm.clientCode, l.fullName
         FROM clientMaster cm
         INNER JOIN leads l ON l.id = cm.leadId
         WHERE cm.id = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $clientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        return 'Client #' . $clientId;
    }

    return (string)$row['fullName'] . ' (' . (string)$row['clientCode'] . ')';
}

/*
|--------------------------------------------------------------------------
| Media Upload
|--------------------------------------------------------------------------
| Mirrors the finfo-validated, collision-safe filename pattern used by
| LeadEngine::uploadFile() (includes/leadEngine.php) — real content-type
| detection rather than trusting the extension, unique filenames.
*/

function saveInstagramMediaFile(array $file, string $mediaCategory = 'image'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => '', 'error' => 'File upload failed.'];
    }

    // Meta's Content Publishing API only accepts JPEG for image_url-based
    // posts (image and carousel) — PNG uploads would pass our own
    // validation but fail silently at Meta's end.
    $isVideo = $mediaCategory === 'video';
    $allowedTypes = $isVideo
        ? ['video/mp4' => 'mp4', 'video/quicktime' => 'mov']
        : ['image/jpeg' => 'jpg'];
    $maxBytes = $isVideo ? 100 * 1024 * 1024 : 8 * 1024 * 1024;

    if ((int)($file['size'] ?? 0) > $maxBytes) {
        return [
            'success' => false,
            'path' => '',
            'error' => $isVideo ? 'Video must be 100 MB or smaller.' : 'Image must be 8 MB or smaller.',
        ];
    }

    $tmpName = (string)($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'path' => '', 'error' => 'Invalid upload.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : false;

    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType === false || !isset($allowedTypes[$mimeType])) {
        return [
            'success' => false,
            'path' => '',
            'error' => $isVideo ? 'Video must be MP4 or MOV.' : 'Image must be a JPEG (.jpg) file.',
        ];
    }

    $uploadDir = dirname(__DIR__) . '/uploads/instagram-posts/';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['success' => false, 'path' => '', 'error' => 'Unable to create upload directory.'];
    }

    $filename = uniqid('ig_', true) . '_' . time() . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        return ['success' => false, 'path' => '', 'error' => 'Unable to save uploaded file.'];
    }

    return ['success' => true, 'path' => 'uploads/instagram-posts/' . $filename, 'error' => ''];
}

function saveInstagramMediaFiles(array $filesField, string $mediaCategory = 'image', int $maxFiles = 10): array
{
    $paths = [];
    $errors = [];

    if (!isset($filesField['name']) || !is_array($filesField['name'])) {
        return ['paths' => $paths, 'errors' => ['No files were uploaded.']];
    }

    $count = min(count($filesField['name']), $maxFiles);

    for ($i = 0; $i < $count; $i++) {
        if (($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $singleFile = [
            'name' => $filesField['name'][$i],
            'type' => $filesField['type'][$i],
            'tmp_name' => $filesField['tmp_name'][$i],
            'error' => $filesField['error'][$i],
            'size' => $filesField['size'][$i],
        ];

        $result = saveInstagramMediaFile($singleFile, $mediaCategory);

        if ($result['success']) {
            $paths[] = $result['path'];
        } else {
            $errors[] = $result['error'];
        }
    }

    return ['paths' => $paths, 'errors' => $errors];
}

/*
|--------------------------------------------------------------------------
| Post CRUD
|--------------------------------------------------------------------------
*/

function encodeInstagramPostMediaPaths(array $paths): string
{
    return (string)json_encode(array_values($paths));
}

function decodeInstagramPostMediaPaths(?string $mediaUrl): array
{
    $decoded = json_decode((string)$mediaUrl, true);

    return is_array($decoded) ? $decoded : array_values(array_filter([(string)$mediaUrl]));
}

function instagramPostMediaAbsoluteUrls(array $relativePaths): array
{
    return array_map(
        static fn(string $path): string => BASE_URL . '/' . ltrim($path, '/'),
        $relativePaths
    );
}

function getInstagramPosts(mysqli $con, string $status = '', ?int $clientId = null): array
{
    ensureInstagramPostsTable($con);

    $where = [];
    $types = '';
    $params = [];

    if ($status !== '') {
        $where[] = 'status = ?';
        $types .= 's';
        $params[] = $status;
    }

    if ($clientId !== null) {
        $where[] = 'clientId = ?';
        $types .= 'i';
        $params[] = $clientId;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramPosts {$whereSql} ORDER BY COALESCE(scheduledAt, createdAt) DESC, id DESC"
    );

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $posts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $row['mediaUrls'] = instagramPostMediaAbsoluteUrls(decodeInstagramPostMediaPaths($row['mediaUrl']));
        $posts[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $posts;
}

function getInstagramPostById(mysqli $con, int $id): ?array
{
    ensureInstagramPostsTable($con);

    $stmt = mysqli_prepare($con, "SELECT * FROM instagramPosts WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) {
        return null;
    }

    $row['mediaUrls'] = instagramPostMediaAbsoluteUrls(decodeInstagramPostMediaPaths($row['mediaUrl']));

    return $row;
}

function saveInstagramPost(mysqli $con, array $data, int $userId): int
{
    ensureInstagramPostsTable($con);

    $clientId = (int)($data['clientId'] ?? 0);
    $instagramAccountId = (int)($data['instagramAccountId'] ?? 0);
    $mediaType = (string)($data['mediaType'] ?? 'image');
    $mediaPathsJson = encodeInstagramPostMediaPaths($data['mediaPaths'] ?? []);
    $caption = (string)($data['caption'] ?? '');
    $status = (string)($data['status'] ?? 'draft');
    $scheduledAt = $data['scheduledAt'] ?? null;
    $postId = (int)($data['id'] ?? 0);

    if ($postId > 0) {
        $stmt = mysqli_prepare(
            $con,
            "UPDATE instagramPosts
             SET clientId = ?, instagramAccountId = ?, mediaType = ?, mediaUrl = ?, caption = ?, status = ?, scheduledAt = ?, errorMessage = ''
             WHERE id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'iisssssi',
            $clientId,
            $instagramAccountId,
            $mediaType,
            $mediaPathsJson,
            $caption,
            $status,
            $scheduledAt,
            $postId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $postId;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO instagramPosts
            (createdBy, clientId, instagramAccountId, mediaType, mediaUrl, caption, status, scheduledAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'iiisssss',
        $userId,
        $clientId,
        $instagramAccountId,
        $mediaType,
        $mediaPathsJson,
        $caption,
        $status,
        $scheduledAt
    );
    mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $newId;
}

function deleteInstagramPostRecord(mysqli $con, int $id): bool
{
    $post = getInstagramPostById($con, $id);

    if (!$post) {
        return false;
    }

    $stmt = mysqli_prepare($con, "DELETE FROM instagramPosts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        foreach (decodeInstagramPostMediaPaths($post['mediaUrl']) as $relativePath) {
            $fullPath = dirname(__DIR__) . '/' . ltrim((string)$relativePath, '/');

            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    return $ok;
}

function getDueInstagramPosts(mysqli $con, int $limit = 20): array
{
    ensureInstagramPostsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramPosts
         WHERE status = 'scheduled' AND scheduledAt <= NOW()
         ORDER BY scheduledAt ASC
         LIMIT ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $posts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $posts[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $posts;
}

function getInstagramPostsByStatus(mysqli $con, string $status, int $limit = 20): array
{
    ensureInstagramPostsTable($con);

    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramPosts WHERE status = ? ORDER BY updatedAt ASC LIMIT ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $status, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $posts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $posts[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $posts;
}

function markInstagramPostPublishing(mysqli $con, int $id): void
{
    $stmt = mysqli_prepare($con, "UPDATE instagramPosts SET status = 'publishing' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function updateInstagramPostContainerId(mysqli $con, int $id, string $containerId): void
{
    $stmt = mysqli_prepare($con, "UPDATE instagramPosts SET instagramMediaId = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $containerId, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function markInstagramPostPublished(mysqli $con, int $id, string $mediaId): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE instagramPosts
         SET status = 'published', instagramMediaId = ?, publishedAt = NOW(), errorMessage = ''
         WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $mediaId, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function markInstagramPostFailed(mysqli $con, int $id, string $message): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE instagramPosts SET status = 'failed', errorMessage = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $message, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Requeues a post that was mid-publish when a transient (network-level)
 * error occurred, so the next scheduler run retries it instead of it being
 * stuck in 'publishing' or wrongly marked 'failed' for a blip that wasn't
 * Meta rejecting the post.
 */
function revertInstagramPostToScheduled(mysqli $con, int $id): void
{
    $stmt = mysqli_prepare($con, "UPDATE instagramPosts SET status = 'scheduled' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/*
|--------------------------------------------------------------------------
| Meta Graph API Publishing
|--------------------------------------------------------------------------
| All requests route through the single instagramGraphApiRequest() curl
| wrapper defined below — no separate curl implementations per media type.
*/

function publishInstagramContainer(array $account, string $containerId): string
{
    $publish = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media_publish',
        [
            'creation_id' => $containerId,
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($publish['id'])) {
        throw new RuntimeException('Meta did not return a published media id.');
    }

    return (string)$publish['id'];
}

function getInstagramContainerStatus(array $account, string $containerId): string
{
    $status = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $containerId,
        [
            'fields' => 'status_code',
            'access_token' => $account['accessToken'],
        ],
        'GET'
    );

    return (string)($status['status_code'] ?? 'UNKNOWN');
}

function publishInstagramImagePost(array $account, string $mediaUrl, string $caption): array
{
    $container = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media',
        [
            'image_url' => $mediaUrl,
            'caption' => $caption,
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($container['id'])) {
        throw new RuntimeException('Meta did not return a media container id.');
    }

    return ['instagramMediaId' => publishInstagramContainer($account, (string)$container['id'])];
}

function publishInstagramCarouselPost(array $account, array $mediaUrls, string $caption): array
{
    if (count($mediaUrls) < 2) {
        throw new RuntimeException('A carousel post needs at least 2 images.');
    }

    $childIds = [];

    foreach ($mediaUrls as $url) {
        $item = instagramGraphApiRequest(
            'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media',
            [
                'image_url' => $url,
                'is_carousel_item' => 'true',
                'access_token' => $account['accessToken'],
            ]
        );

        if (empty($item['id'])) {
            throw new RuntimeException('Meta did not return a carousel item container id.');
        }

        $childIds[] = $item['id'];
    }

    $parent = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media',
        [
            'media_type' => 'CAROUSEL',
            'caption' => $caption,
            'children' => implode(',', $childIds),
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($parent['id'])) {
        throw new RuntimeException('Meta did not return a carousel container id.');
    }

    return ['instagramMediaId' => publishInstagramContainer($account, (string)$parent['id'])];
}

/**
 * Reels are processed asynchronously by Meta. This starts the container and
 * publishes immediately if it's already FINISHED; otherwise it returns a
 * 'pending' status with the container id so the caller (the cron scheduler)
 * can finalize it on a later run via getInstagramContainerStatus() +
 * publishInstagramContainer() — no blocking sleep loops.
 */
function publishInstagramVideoPost(array $account, string $videoUrl, string $caption): array
{
    $container = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'] . '/media',
        [
            'video_url' => $videoUrl,
            'caption' => $caption,
            'media_type' => 'REELS',
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($container['id'])) {
        throw new RuntimeException('Meta did not return a video container id.');
    }

    $containerId = (string)$container['id'];
    $statusCode = getInstagramContainerStatus($account, $containerId);

    if ($statusCode === 'FINISHED') {
        return ['status' => 'published', 'instagramMediaId' => publishInstagramContainer($account, $containerId)];
    }

    if ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
        throw new RuntimeException('Meta reported video processing status: ' . $statusCode);
    }

    return ['status' => 'pending', 'instagramMediaId' => $containerId];
}

function instagramGraphApiRequest(string $url, array $params, string $method = 'POST'): array
{
    $ch = curl_init();

    if (strtoupper($method) === 'GET') {
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        // Network-level failure (DNS, timeout, connection reset) — treated as
        // transient so callers can retry rather than permanently failing a post.
        throw new InstagramTransientApiException('Meta API request failed: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Meta API returned an invalid response.');
    }

    if (isset($decoded['error'])) {
        $errorInfo = is_array($decoded['error']) ? $decoded['error'] : [];
        $message = (string)($errorInfo['message'] ?? '');
        $code = (int)($errorInfo['code'] ?? 0);
        throw new RuntimeException($message !== '' ? $message : 'Unknown Meta API error.', $code);
    }

    return $decoded;
}

/**
 * Thrown for network-level failures talking to the Meta API (as opposed to
 * an error Meta itself returned). Callers should retry rather than fail.
 */
class InstagramTransientApiException extends RuntimeException
{
}

/**
 * Code 190 is Meta's standard "invalid/expired OAuth access token" error.
 */
function isInstagramAuthError(Throwable $e): bool
{
    return (int)$e->getCode() === 190;
}
