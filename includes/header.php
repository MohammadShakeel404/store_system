<?php
/**
 * DMR Construction — Page Header Include
 * Usage: include at top of each page after requireLogin()
 * Expects: $pageTitle, $activePage
 */
$user       = currentUser();
$notifCount = getUnreadNotifCount($user['id']);
$notifs     = getRecentNotifications($user['id']);
$roleName   = ['admin' => 'Store Admin', 'keeper' => 'Store Keeper', 'employee' => 'Employee', 'management' => 'Management'][$user['role']] ?? 'User';
$pageTitle  = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — DMR Store</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<div class="app-layout">

  <!-- ═══════════════════════════════════════════════════════
       SIDEBAR
  ═══════════════════════════════════════════════════════ -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-mark">
        <div class="logo-sq">DMR</div>
        <div>
          <div class="brand-name">DMR Construction</div>
          <div class="brand-sub">Store Management System</div>
        </div>
      </div>
    </div>

    <div class="nav-body">

      <div class="nav-section">
        <div class="nav-section-title">Main</div>
        <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item <?= $activePage==='dashboard'?'active':'' ?>">
          <span class="nav-icon">📊</span> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/inventory.php" class="nav-item <?= $activePage==='inventory'?'active':'' ?>">
          <span class="nav-icon">📦</span> Inventory
          <?php $low = getLowStockCount(); if($low>0): ?>
            <span class="nav-badge"><?= $low ?></span>
          <?php endif; ?>
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-section-title">Requests</div>
        <a href="<?= BASE_URL ?>/indents.php" class="nav-item <?= $activePage==='indents'?'active':'' ?>">
          <span class="nav-icon">📋</span> Indent Requests
          <?php $pi = getPendingCount('indents'); if($pi>0): ?>
            <span class="nav-badge"><?= $pi ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/issues.php" class="nav-item <?= $activePage==='issues'?'active':'' ?>">
          <span class="nav-icon">📤</span> Issue Requests
          <?php $pis = getPendingCount('issue_requests'); if($pis>0): ?>
            <span class="nav-badge"><?= $pis ?></span>
          <?php endif; ?>
        </a>
      </div>

      <?php if (in_array($user['role'], ['admin','keeper'])): ?>
      <div class="nav-section">
        <div class="nav-section-title">Store Operations</div>
        <?php if (canDo('add_product')): ?>
        <a href="<?= BASE_URL ?>/products.php" class="nav-item <?= $activePage==='products'?'active':'' ?>">
          <span class="nav-icon">🏷️</span> Product Master
        </a>
        <?php endif; ?>
        <?php if (canDo('view_stock_ledger')): ?>
        <a href="<?= BASE_URL ?>/stock.php" class="nav-item <?= $activePage==='stock'?'active':'' ?>">
          <span class="nav-icon">🔄</span> Stock Movement
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (canDo('manage_users')): ?>
      <div class="nav-section">
        <div class="nav-section-title">Administration</div>
        <a href="<?= BASE_URL ?>/users.php" class="nav-item <?= $activePage==='users'?'active':'' ?>">
          <span class="nav-icon">👥</span> User Management
        </a>
      </div>
      <?php endif; ?>

      <?php if (canDo('view_reports')): ?>
      <div class="nav-section">
        <div class="nav-section-title">Analytics</div>
        <a href="<?= BASE_URL ?>/reports.php" class="nav-item <?= $activePage==='reports'?'active':'' ?>">
          <span class="nav-icon">📑</span> Reports & Export
        </a>
        <?php if (canDo('view_audit')): ?>
        <a href="<?= BASE_URL ?>/audit.php" class="nav-item <?= $activePage==='audit'?'active':'' ?>">
          <span class="nav-icon">🔍</span> Audit Log
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div><!-- /nav-body -->

    <div class="sidebar-user">
      <div class="user-mini">
        <div class="user-avatar"><?= e($user['initials']) ?></div>
        <div class="user-info">
          <div class="u-name"><?= e($user['name']) ?></div>
          <div class="u-role"><?= e($roleName) ?></div>
        </div>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="logout-btn" title="Logout">⏻</a>
      </div>
    </div>
  </nav><!-- /sidebar -->

  <!-- ═══════════════════════════════════════════════════════
       MAIN CONTENT AREA
  ═══════════════════════════════════════════════════════ -->
  <div class="main-wrap">

    <!-- TOPBAR -->
    <header class="topbar">
      <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">☰</button>
      <div class="topbar-title"><?= e($pageTitle) ?></div>
      <div class="topbar-right">

        <!-- Notification Bell -->
        <div class="notif-wrap">
          <button class="notif-btn" id="notifToggle" onclick="toggleNotif()" title="Notifications">
            🔔
            <?php if ($notifCount > 0): ?>
              <span class="notif-badge"><?= $notifCount ?></span>
            <?php endif; ?>
          </button>
          <div class="notif-panel" id="notifPanel">
            <div class="notif-header">
              <strong>Notifications</strong>
              <?php if ($notifCount > 0): ?>
                <a href="<?= BASE_URL ?>/actions/mark_notif_read.php" class="mark-read-link">Mark all read</a>
              <?php endif; ?>
            </div>
            <div class="notif-list">
              <?php if (empty($notifs)): ?>
                <div class="notif-empty">No notifications</div>
              <?php else: foreach ($notifs as $n): ?>
                <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                  <div class="notif-title"><?= e($n['title']) ?></div>
                  <div class="notif-body"><?= e($n['body']) ?></div>
                  <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>
        </div>

        <!-- Quick Action -->
        <?php if (canDo('raise_request')): ?>
        <a href="<?= BASE_URL ?>/issues.php?action=new" class="btn btn-primary btn-sm">+ New Request</a>
        <?php endif; ?>

        <div class="topbar-user">
          <div class="user-avatar sm"><?= e($user['initials']) ?></div>
          <span><?= e(explode(' ', $user['name'])[0]) ?></span>
        </div>
      </div>
    </header><!-- /topbar -->

    <!-- FLASH MESSAGE -->
    <div class="flash-container" id="flashContainer">
      <?= renderFlash() ?>
    </div>

    <!-- PAGE CONTENT -->
    <main class="page-content">
<?php
// ── Nav Count Helpers (called by sidebar above) ────────────────────────
function getLowStockCount(): int {
    try {
        return (int)getDB()->query("SELECT COUNT(*) FROM products WHERE current_stock <= min_stock_level AND status='active'")->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function getPendingCount(string $table): int {
    try {
        $allowed = ['indents', 'issue_requests'];
        if (!in_array($table, $allowed)) return 0;
        return (int)getDB()->query("SELECT COUNT(*) FROM `{$table}` WHERE status='pending'")->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
?>
