<?php
/**
 * DMR Construction PVT. LTD. — Store Management System
 * Application Configuration
 */

define('APP_NAME',    'DMR Store Management System');
define('COMPANY',     'DMR Construction PVT. LTD.');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    '/store_system');
define('TIMEZONE',    'Asia/Kolkata');
define('SESSION_NAME',     'dmr_store_session');
define('SESSION_LIFETIME', 3600);
define('ROWS_PER_PAGE', 25);
define('ROLE_ADMIN',      'admin');
define('ROLE_KEEPER',     'keeper');
define('ROLE_EMPLOYEE',   'employee');
define('ROLE_MANAGEMENT', 'management');

date_default_timezone_set(TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset(); session_destroy();
        header('Location: ' . BASE_URL . '/auth/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles)) {
        http_response_code(403);
        echo '<div style="text-align:center;padding:60px;font-family:sans-serif;"><h2 style="color:#C0392B;">Access Denied</h2><p>You do not have permission.</p><a href="'.BASE_URL.'/dashboard.php">Return to Dashboard</a></div>';
        exit;
    }
}

function verifyCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die('Invalid security token. Please go back and try again.');
        }
    }
}

function currentUser(): array {
    $name = $_SESSION['user_name'] ?? 'User';
    $words = explode(' ', $name);
    $initials = implode('', array_map(fn($w) => strtoupper($w[0] ?? ''), array_slice($words, 0, 2)));
    return [
        'id'       => $_SESSION['user_id']   ?? 0,
        'name'     => $name,
        'role'     => $_SESSION['user_role'] ?? '',
        'dept'     => $_SESSION['user_dept'] ?? '',
        'initials' => $initials,
    ];
}

function canDo(string $permission): bool {
    $role = $_SESSION['user_role'] ?? '';
    $perms = [
        'add_product'        => [ROLE_ADMIN],
        'edit_product'       => [ROLE_ADMIN],
        'approve_indent'     => [ROLE_ADMIN, ROLE_KEEPER],
        'approve_issue'      => [ROLE_ADMIN, ROLE_KEEPER],
        'stock_in'           => [ROLE_ADMIN, ROLE_KEEPER],
        'stock_adjust'       => [ROLE_ADMIN],
        'manage_users'       => [ROLE_ADMIN],
        'view_reports'       => [ROLE_ADMIN, ROLE_KEEPER, ROLE_MANAGEMENT],
        'raise_request'      => [ROLE_ADMIN, ROLE_KEEPER, ROLE_EMPLOYEE],
        'view_audit'         => [ROLE_ADMIN, ROLE_MANAGEMENT],
        'view_stock_ledger'  => [ROLE_ADMIN, ROLE_KEEPER, ROLE_MANAGEMENT],
    ];
    return isset($perms[$permission]) && in_array($role, $perms[$permission]);
}
