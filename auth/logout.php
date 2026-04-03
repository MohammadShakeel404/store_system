<?php
require_once __DIR__ . '/../config/config.php';
if (!empty($_SESSION['user_id'])) {
    logAudit('LOGOUT', 'System', 'User logged out');
}
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
