<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
if (!canDo('view_audit')) { http_response_code(403); die(renderAccessDenied()); }

$pageTitle  = 'Audit Log';
$activePage = 'audit';
$db         = getDB();

$search   = trim($_GET['search'] ?? '');
$module   = $_GET['module'] ?? '';
$action   = $_GET['action_filter'] ?? '';
$userId   = (int)($_GET['user_id'] ?? 0);
$from     = $_GET['from'] ?? '';
$to       = $_GET['to'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;
$offset   = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(al.details LIKE ? OR al.user_name LIKE ? OR al.action LIKE ?)';
    $s        = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($module) { $where[] = 'al.module = ?'; $params[] = $module; }
if ($action) { $where[] = 'al.action = ?'; $params[] = $action; }
if ($userId) { $where[] = 'al.user_id = ?'; $params[] = $userId; }
if ($from)   { $where[] = 'DATE(al.created_at) >= ?'; $params[] = $from; }
if ($to)     { $where[] = 'DATE(al.created_at) <= ?'; $params[] = $to; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM audit_log al $whereSQL");
$total->execute($params); $total = (int)$total->fetchColumn();

$stmt = $db->prepare(
    "SELECT al.*, u.name AS user_full_name
     FROM audit_log al
     LEFT JOIN users u ON al.user_id = u.id
     $whereSQL
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$logs = $stmt->fetchAll();

// Distinct modules and actions for filters
$modules = $db->query("SELECT DISTINCT module FROM audit_log ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$actions = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$usersList = $db->query("SELECT DISTINCT user_id, user_name FROM audit_log WHERE user_name IS NOT NULL ORDER BY user_name")->fetchAll();

$filterUrl = BASE_URL . '/audit.php?' . http_build_query(array_filter(['search'=>$search,'module'=>$module,'action_filter'=>$action,'user_id'=>$userId,'from'=>$from,'to'=>$to]));

$actionColors = [
    'LOGIN'         => 'badge-info',
    'LOGOUT'        => 'badge-gray',
    'APPROVE'       => 'badge-success',
    'REJECT'        => 'badge-danger',
    'STOCK IN'      => 'badge-success',
    'ADJUSTMENT'    => 'badge-warning',
    'CREATE USER'   => 'badge-info',
    'UPDATE USER'   => 'badge-warning',
    'STATUS'        => 'badge-warning',
    'ADD'           => 'badge-success',
    'EDIT'          => 'badge-warning',
    'REQUEST'       => 'badge-pending',
    'ISSUE'         => 'badge-success',
    'FAILED_LOGIN'  => 'badge-danger',
    'EXPORT'        => 'badge-gray',
];

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
  <span class="sep">›</span><span class="cur">Audit Log</span>
</div>

<div class="page-header">
  <h1>🔍 Audit Log</h1>
  <a href="<?= BASE_URL ?>/reports_export/export.php?type=audit_log&format=csv&<?= $filterUrl ?>" class="btn btn-outline btn-sm">📊 Export CSV</a>
</div>

<div class="alert alert-info mb-16">
  🔒 <strong>Immutable Audit Trail:</strong> This log records all user actions permanently. Entries cannot be edited or deleted. It provides complete accountability for all system activities.
</div>

<!-- FILTERS -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 18px;">
    <form method="get" action="">
      <div class="filter-bar">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search action, details, user...">
        </div>
        <select name="module">
          <option value="">All Modules</option>
          <?php foreach ($modules as $m): ?><option <?= $module===$m?'selected':'' ?>><?= e($m) ?></option><?php endforeach; ?>
        </select>
        <select name="action_filter">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?><option <?= $action===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?>
        </select>
        <select name="user_id">
          <option value="">All Users</option>
          <?php foreach ($usersList as $u): ?><option value="<?= $u['user_id'] ?>" <?= $userId==$u['user_id']?'selected':'' ?>><?= e($u['user_name']) ?></option><?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?= e($from) ?>" title="From date">
        <input type="date" name="to"   value="<?= e($to) ?>"   title="To date">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($search||$module||$action||$userId||$from||$to): ?>
          <a href="<?= BASE_URL ?>/audit.php" class="btn btn-outline btn-sm">✕ Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- LOG TABLE -->
<div class="card">
  <div class="card-header">
    <h3>Log Entries (<?= number_format($total) ?>)</h3>
    <span class="text-small text-muted">Showing page <?= $page ?> · <?= $perPage ?> per page</span>
  </div>
  <div class="table-wrap">
    <?php if (empty($logs)): ?>
      <div class="empty-state"><div class="empty-icon">🔍</div><h4>No log entries found</h4></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th style="white-space:nowrap;">Timestamp</th>
          <th>User</th>
          <th>Role</th>
          <th>Action</th>
          <th>Module</th>
          <th>Details</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $i => $log):
          $actionCls = $actionColors[$log['action']] ?? 'badge-gray';
        ?>
        <tr>
          <td class="text-small text-muted"><?= ($total - $offset - $i) ?></td>
          <td style="white-space:nowrap;font-size:12px;font-family:'DM Mono',monospace;"><?= e($log['created_at']) ?></td>
          <td><strong><?= e($log['user_name'] ?: 'System') ?></strong></td>
          <td><?= roleBadge($log['user_role'] ?? 'system') ?></td>
          <td><span class="badge <?= $actionCls ?>"><?= e($log['action']) ?></span></td>
          <td class="text-small"><?= e($log['module']) ?></td>
          <td style="max-width:280px;">
            <span style="font-size:12px;color:var(--g700);"><?= e($log['details']) ?></span>
          </td>
          <td class="mono text-small text-muted"><?= e($log['ip_address'] ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($total > $perPage): ?>
  <div class="card-footer"><?= paginate($total, $page, $perPage, $filterUrl) ?></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
