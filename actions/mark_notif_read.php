<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
$db = getDB();
$db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
logAudit('READ NOTIFICATIONS', 'Notifications', 'Marked all notifications as read');
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/dashboard.php'));
exit;
