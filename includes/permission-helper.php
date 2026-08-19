<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getLoggedInUserType(): string
{
    $authUserType = (string)($_SESSION['authUserType'] ?? '');

    if ($authUserType === 'employee' && !empty($_SESSION['candidateId'])) {
        return 'employee';
    }

    if ($authUserType === 'admin' && !empty($_SESSION['userId'])) {
        return 'admin';
    }

    if (!empty($_SESSION['userId'])) {
        return 'admin';
    }

    if (!empty($_SESSION['candidateId'])) {
        return 'employee';
    }

    return 'guest';
}

function getLoggedInUserId(): int
{
    $userType = getLoggedInUserType();

    if ($userType === 'admin') {
        return (int)$_SESSION['userId'];
    }

    if ($userType === 'employee') {
        return (int)$_SESSION['candidateId'];
    }

    return (int)($_SESSION['id'] ?? 0);
}

function isLoggedIn(): bool
{
    return getLoggedInUserId() > 0;
}

function getLoggedInEmployeeUser(): ?array
{
    global $con;

    $candidateId = (int)($_SESSION['candidateId'] ?? 0);

    if ($candidateId <= 0) {
        return null;
    }

    $stmt = $con->prepare("
        SELECT 
            id,
            fullName,
            emailAddress,
            departmentName,
            designationName,
            accountStatus,
            employmentStatus
        FROM employeeusers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $candidateId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    return $user ?: null;
}

function getLoggedInAdminUser(): ?array
{
    global $con;

    $userId = (int)($_SESSION['userId'] ?? 0);

    if ($userId <= 0) {
        return null;
    }

    $stmt = $con->prepare("
        SELECT 
            id,
            fullName,
            email
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    return $user ?: null;
}

function getLoggedInUserRoleName(): string
{
    /*
     * Admin panel users come from `users` table.
     * There is no role column in users table, so all are treated as Admin.
     */
    if (getLoggedInUserType() === 'admin') {
        return 'Admin';
    }

    /*
     * Employee panel users come from `employeeusers` table.
     * Permission role is employeeusers.designationName.
     */
    if (getLoggedInUserType() === 'employee') {
        $user = getLoggedInEmployeeUser();

        return trim((string)($user['designationName'] ?? ''));
    }

    return '';
}

function isLoggedInUserSuperAdmin(): bool
{
    if (getLoggedInUserType() !== 'admin') {
        return false;
    }

    $user = getLoggedInAdminUser();

    if (!$user) {
        return false;
    }

    $email = strtolower(trim((string)($user['email'] ?? '')));

    return in_array($email, [
        'varun.mqlus@gmail.com',
        'hr@mqlus.in',
        'superadmin@mqlus.in'
    ], true);
}

function getRouteByPath(string $routePath): ?array
{
    global $con;

    $routePath = '/' . ltrim(trim($routePath), '/');
    $routePath = rtrim($routePath, '/');

    if ($routePath === '') {
        $routePath = '/';
    }

    $stmt = $con->prepare("
        SELECT *
        FROM routesMaster
        WHERE routePath = ?
        AND isActive = 1
        LIMIT 1
    ");

    $stmt->bind_param("s", $routePath);
    $stmt->execute();

    $route = $stmt->get_result()->fetch_assoc();

    return $route ?: null;
}

function isAlwaysAllowedRoute(string $routePath): bool
{
    $routePath = '/' . ltrim(trim($routePath), '/');
    $routePath = rtrim($routePath, '/');

    $alwaysAllowedRoutes = [
    '/dashboard',
    '/emp-dashboard',
    '/permission-denied',
    '/logout',
    '/emp-logout',
    '/candidate-logout',
    '/candidate-forgot-password',
    '/candidate-forgot-password'
    ];

    return in_array($routePath, $alwaysAllowedRoutes, true);
}

function hasRoutePermission(string $routePath, string $action = 'canView'): bool
{
    global $con;

    $allowedActions = [
        'canView',
        'canAdd',
        'canEdit',
        'canDelete',
        'canApprove'
    ];

    if (!in_array($action, $allowedActions, true)) {
        return false;
    }

    $routePath = '/' . ltrim(trim($routePath), '/');
    $routePath = rtrim($routePath, '/');

    $route = getRouteByPath($routePath);

    if (!$route) {
        return false;
    }

    if ((int)$route['isPublic'] === 1) {
        return true;
    }

    if (isAlwaysAllowedRoute($routePath)) {
        return true;
    }

    if (!isLoggedIn()) {
        return false;
    }

    if (getLoggedInUserType() === 'admin') {
        return true;
    }

    $userId = getLoggedInUserId();
    $roleName = getLoggedInUserRoleName();

    if ($userId <= 0 || $roleName === '') {
        return false;
    }

    $routeId = (int)$route['id'];

    $stmt = $con->prepare("
        SELECT
            canView,
            canAdd,
            canEdit,
            canDelete,
            canApprove
        FROM userPermissionOverrides
        WHERE userId = ?
        AND routeId = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $userId, $routeId);
    $stmt->execute();

    $userPermission = $stmt->get_result()->fetch_assoc();

    if ($userPermission) {
        return (int)$userPermission[$action] === 1;
    }

    /*
     * Priority 2:
     * Role/designation permission.
     */
    $stmt = $con->prepare("
        SELECT $action
        FROM rolePermissions
        WHERE roleName = ?
        AND routeId = ?
        LIMIT 1
    ");

    $stmt->bind_param("si", $roleName, $routeId);
    $stmt->execute();

    $rolePermission = $stmt->get_result()->fetch_assoc();

    return $rolePermission && (int)$rolePermission[$action] === 1;
}

function requireRoutePermission(string $routePath, string $action = 'canView'): void
{
    if (!hasRoutePermission($routePath, $action)) {
        header('Location: ' . BASE_URL . '/permission-denied?from=' . urlencode($routePath));
        exit;
    }
}

function hasActionPermission(string $routePath, string $actionKey): bool
{
    global $con;

    $routePath = '/' . ltrim(trim($routePath), '/');
    $routePath = rtrim($routePath, '/');
    $actionKey = trim($actionKey);

    if ($routePath === '' || $actionKey === '' || !isLoggedIn()) {
        return false;
    }

    /*
     * Admin panel users keep full button access by default.
     */
    if (getLoggedInUserType() === 'admin') {
        return true;
    }

    $userId = getLoggedInUserId();
    $roleName = getLoggedInUserRoleName();

    if ($userId <= 0 || $roleName === '') {
        return false;
    }

    $stmt = $con->prepare("
        SELECT
            pa.id,
            pa.permissionType
        FROM permissionActions pa
        INNER JOIN routesMaster rm ON rm.id = pa.routeId
        WHERE rm.routePath = ?
        AND rm.isActive = 1
        AND pa.actionKey = ?
        AND pa.isActive = 1
        LIMIT 1
    ");

    $stmt->bind_param('ss', $routePath, $actionKey);
    $stmt->execute();
    $permissionAction = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$permissionAction) {
        return false;
    }

    $permissionType = (string)($permissionAction['permissionType'] ?? 'special');
    $standardPermissionTypes = [
        'canAdd',
        'canEdit',
        'canDelete',
        'canApprove'
    ];

    if (in_array($permissionType, $standardPermissionTypes, true)) {
        return hasRoutePermission($routePath, $permissionType);
    }

    if ($permissionType !== 'special') {
        return false;
    }

    $actionId = (int)$permissionAction['id'];

    $stmt = $con->prepare("
        SELECT canAccess
        FROM userActionPermissionOverrides
        WHERE userId = ?
        AND actionId = ?
        LIMIT 1
    ");

    $stmt->bind_param('ii', $userId, $actionId);
    $stmt->execute();
    $userPermission = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($userPermission) {
        return (int)$userPermission['canAccess'] === 1;
    }

    $stmt = $con->prepare("
        SELECT canAccess
        FROM roleActionPermissions
        WHERE roleName = ?
        AND actionId = ?
        LIMIT 1
    ");

    $stmt->bind_param('si', $roleName, $actionId);
    $stmt->execute();
    $rolePermission = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $rolePermission && (int)$rolePermission['canAccess'] === 1;
}

function requireActionPermission(string $routePath, string $actionKey): void
{
    if (hasActionPermission($routePath, $actionKey)) {
        return;
    }

    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');

    if (strpos($requestUri, '/api/') !== false) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to perform this action.'
        ]);
        exit;
    }

    header('Location: ' . BASE_URL . '/permission-denied?from=' . urlencode($routePath));
    exit;
}

function getCurrentUserPermissions(): array
{
    global $con;

    if (isLoggedInUserSuperAdmin()) {
        return [
            'isSuperAdmin' => true
        ];
    }

    $userId = getLoggedInUserId();
    $roleName = getLoggedInUserRoleName();

    if ($userId <= 0 || $roleName === '') {
        return [];
    }

    $permissions = [];

    $stmt = $con->prepare("
        SELECT 
            rm.routePath,
            rp.canView,
            rp.canAdd,
            rp.canEdit,
            rp.canDelete,
            rp.canApprove
        FROM rolePermissions rp
        INNER JOIN routesMaster rm ON rm.id = rp.routeId
        WHERE rp.roleName = ?
        AND rm.isActive = 1
    ");

    $stmt->bind_param("s", $roleName);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $permissions[$row['routePath']] = [
            'canView' => (int)$row['canView'],
            'canAdd' => (int)$row['canAdd'],
            'canEdit' => (int)$row['canEdit'],
            'canDelete' => (int)$row['canDelete'],
            'canApprove' => (int)$row['canApprove'],
        ];
    }

    $stmt = $con->prepare("
        SELECT 
            rm.routePath,
            upo.canView,
            upo.canAdd,
            upo.canEdit,
            upo.canDelete,
            upo.canApprove
        FROM userPermissionOverrides upo
        INNER JOIN routesMaster rm ON rm.id = upo.routeId
        WHERE upo.userId = ?
        AND rm.isActive = 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $permissions[$row['routePath']] = [
            'canView' => (int)$row['canView'],
            'canAdd' => (int)$row['canAdd'],
            'canEdit' => (int)$row['canEdit'],
            'canDelete' => (int)$row['canDelete'],
            'canApprove' => (int)$row['canApprove'],
        ];
    }

    foreach (['/dashboard', '/emp-dashboard'] as $routePath) {
        $permissions[$routePath] = [
            'canView' => 1,
            'canAdd' => 0,
            'canEdit' => 0,
            'canDelete' => 0,
            'canApprove' => 0,
        ];
    }

    return $permissions;
}
