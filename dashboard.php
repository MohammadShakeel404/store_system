<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$db   = getDB();
$user = currentUser();

// ── Stats ──────────────────────────────────────────────────────────
$totalProducts  = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$lowStockCount  = $db->query("SELECT COUNT(*) FROM products WHERE current_stock <= min_stock_level AND status='active'")->fetchColumn();
$criticalCount  = $db->query("SELECT COUNT(*) FROM products WHERE current_stock <= (min_stock_level * 0.3) AND status='active'")->fetchColumn();
$pendingIndents = $db->query("SELECT COUNT(*) FROM indents WHERE status='pending'")->fetchColumn();
$pendingIssues  = $db->query("SELECT COUNT(*) FROM issue_requests WHERE status='pending'")->fetchColumn();
$issuedToday    = $db->query("SELECT COUNT(*) FROM issue_requests WHERE DATE(issued_at)=CURDATE() AND status='issued'")->fetchColumn();
$stockInToday   = $db->query("SELECT COALESCE(SUM(quantity),0) FROM stock_transactions WHERE type='stock_in' AND DATE(created_at)=CURDATE()")->fetchColumn();

// ── Low Stock Products ─────────────────────────────────────────────
$lowStockProducts = $db->query(
    "SELECT p.*, c.name AS cat FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.current_stock <= p.min_stock_level AND p.status='active'
     ORDER BY (p.current_stock / NULLIF(p.min_stock_level,0)) ASC LIMIT 8"
)->fetchAll();

// ── Pending Issue Requests ─────────────────────────────────────────
$pendingIssueList = $db->query(
    "SELECT ir.*, p.name AS product_name, p.unit
     FROM issue_requests ir
     JOIN products p ON ir.product_id = p.id
     WHERE ir.status='pending'
     ORDER BY ir.created_at DESC LIMIT 5"
)->fetchAll();

// ── Recent Activity ────────────────────────────────────────────────
$recentActivity = $db->query(
    "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

// ── Stock by Category ──────────────────────────────────────────────
$categoryStock = $db->query(
    "SELECT c.name, SUM(p.current_stock) AS total
     FROM products p JOIN categories c ON p.category_id = c.id
     WHERE p.status='active'
     GROUP BY c.name ORDER BY total DESC"
)->fetchAll();
$maxCatStock = max(array_column($categoryStock, 'total') ?: [1]);

// ── Monthly Issue Trend (last 6 months) ───────────────────────────
$issueTrend = $db->query(
    "SELECT DATE_FORMAT(issued_at,'%b %Y') AS month,
            COUNT(*) AS count
     FROM issue_requests
     WHERE status='issued' AND issued_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(issued_at,'%Y-%m')
     ORDER BY issued_at"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <span class="cur">📊 Dashboard</span>
</div>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue">📦</div>
    <div>
      <div class="stat-val"><?= $totalProducts ?></div>
      <div class="stat-label">Total Products</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">📋</div>
    <div>
      <div class="stat-val"><?= $pendingIndents ?></div>
      <div class="stat-label">Pending Indents</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div>
      <div class="stat-val"><?= $issuedToday ?></div>
      <div class="stat-label">Issued Today</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red">⚠️</div>
    <div>
      <div class="stat-val"><?= $lowStockCount ?></div>
      <div class="stat-label">Low Stock Items</div>
    </div>
  </div>
</div>

<?php if ($lowStockCount > 0): ?>
<div class="alert alert-warning mb-20">
  ⚠️ <strong><?= $lowStockCount ?> item<?= $lowStockCount > 1 ? 's are' : ' is' ?></strong> below minimum stock level
  <?php if ($criticalCount > 0): ?> — <?= $criticalCount ?> <strong>critically low</strong><?php endif; ?>.
  <a href="<?= BASE_URL ?>/inventory.php?filter=low" style="color:inherit;font-weight:700;margin-left:8px;">View inventory →</a>
</div>
<?php endif; ?>

<!-- QUICK ACTIONS -->
<?php if (canDo('raise_request') || canDo('stock_in')): ?>
<div class="quick-actions">
  <?php if (canDo('raise_request')): ?>
  <a href="<?= BASE_URL ?>/issues.php?action=new" class="qa-btn">
    <div class="qa-icon" style="background:var(--brand-pale);">📤</div>
    <div><div class="qa-label">New Issue Request</div><div class="qa-desc">Request material from store</div></div>
  </a>
  <a href="<?= BASE_URL ?>/indents.php?action=new" class="qa-btn">
    <div class="qa-icon" style="background:var(--accent-pale);">📋</div>
    <div><div class="qa-label">New Indent</div><div class="qa-desc">Raise new item request</div></div>
  </a>
  <?php endif; ?>
  <?php if (canDo('stock_in')): ?>
  <a href="<?= BASE_URL ?>/stock.php?action=stockin" class="qa-btn">
    <div class="qa-icon" style="background:var(--success-pale);">➕</div>
    <div><div class="qa-label">Stock In</div><div class="qa-desc">Record received materials</div></div>
  </a>
  <?php endif; ?>
  <?php if (canDo('view_reports')): ?>
  <a href="<?= BASE_URL ?>/reports.php" class="qa-btn">
    <div class="qa-icon" style="background:var(--warning-pale);">📑</div>
    <div><div class="qa-label">Reports</div><div class="qa-desc">Generate & export</div></div>
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- MAIN GRID -->
<div class="grid-2 mb-20">

  <!-- LOW STOCK TABLE -->
  <div class="card">
    <div class="card-header">
      <h3>⚠️ Low Stock Alerts</h3>
      <a href="<?= BASE_URL ?>/inventory.php?filter=low" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <?php if (empty($lowStockProducts)): ?>
        <div class="empty-state" style="padding:24px;">
          <div>✅ All stocks are adequate</div>
        </div>
      <?php else: ?>
      <table>
        <thead><tr><th>Product</th><th>Stock</th><th>Min</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($lowStockProducts as $p):
            $isCrit = $p['current_stock'] <= $p['min_stock_level'] * 0.3;
          ?>
          <tr class="<?= $isCrit ? 'crit-stock-row' : 'low-stock-row' ?>">
            <td>
              <strong><?= e($p['name']) ?></strong>
              <div class="text-small text-muted"><?= e($p['cat']) ?></div>
            </td>
            <td><strong><?= e($p['current_stock']) ?></strong> <?= e($p['unit']) ?></td>
            <td><?= e($p['min_stock_level']) ?></td>
            <td><?= stockBadge((float)$p['current_stock'], (float)$p['min_stock_level']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- RECENT ACTIVITY -->
  <div class="card">
    <div class="card-header">
      <h3>📋 Recent Activity</h3>
      <?php if (canDo('view_audit')): ?>
      <a href="<?= BASE_URL ?>/audit.php" class="btn btn-outline btn-sm">Full Log</a>
      <?php endif; ?>
    </div>
    <div class="card-body" style="padding:8px 16px;">
      <div class="timeline">
        <?php
        $icons = ['LOGIN'=>['🔑','var(--brand-pale)'],'LOGOUT'=>['🚪','var(--g100)'],'APPROVE'=>['✅','var(--success-pale)'],'REJECT'=>['❌','var(--danger-pale)'],'ISSUE'=>['📤','var(--brand-pale)'],'STOCK IN'=>['📦','var(--success-pale)'],'ADJUSTMENT'=>['🔧','var(--warning-pale)'],'REQUEST'=>['📋','var(--accent-pale)']];
        foreach ($recentActivity as $a):
          [$icon, $bg] = $icons[$a['action']] ?? ['📌','var(--g100)'];
        ?>
        <div class="tl-item">
          <div class="tl-dot" style="background:<?= $bg ?>"><?= $icon ?></div>
          <div class="tl-content">
            <div class="tl-title"><?= e($a['action']) ?> — <?= e($a['module']) ?></div>
            <div class="tl-sub"><?= e(substr($a['details'], 0, 60)) ?></div>
            <div class="tl-time"><?= timeAgo($a['created_at']) ?> · <?= e($a['user_name']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div><!-- /grid-2 -->

<div class="grid-2 mb-20">

  <!-- PENDING ISSUES -->
  <?php if (canDo('approve_issue')): ?>
  <div class="card">
    <div class="card-header">
      <h3>📤 Pending Issue Requests</h3>
      <span class="badge badge-pending"><?= $pendingIssues ?> Pending</span>
    </div>
    <div class="table-wrap">
      <?php if (empty($pendingIssueList)): ?>
        <div class="empty-state" style="padding:24px;"><div>✅ No pending requests</div></div>
      <?php else: ?>
      <table>
        <thead><tr><th>Employee</th><th>Item</th><th>Qty</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($pendingIssueList as $iss): ?>
          <tr>
            <td><?= e($iss['employee_name']) ?><div class="text-small text-muted"><?= e($iss['department']) ?></div></td>
            <td><?= e($iss['product_name']) ?></td>
            <td><?= e($iss['quantity_requested']) ?> <?= e($iss['unit']) ?></td>
            <td>
              <a href="<?= BASE_URL ?>/issues.php?action=review&id=<?= $iss['id'] ?>" class="btn btn-success btn-xs">Review</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <div class="card-footer">
      <a href="<?= BASE_URL ?>/issues.php?status=pending" class="btn btn-outline btn-sm">View All Pending →</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- STOCK BY CATEGORY -->
  <div class="card">
    <div class="card-header"><h3>📊 Stock by Category</h3></div>
    <div class="card-body">
      <?php foreach ($categoryStock as $cat): ?>
      <div style="margin-bottom:14px;">
        <div class="d-flex justify-between" style="margin-bottom:4px;">
          <span style="font-size:13px;font-weight:600;"><?= e($cat['name']) ?></span>
          <span class="text-small text-muted"><?= number_format($cat['total'], 2) ?> units</span>
        </div>
        <div class="progress">
          <div class="progress-fill" style="width:<?= $maxCatStock > 0 ? round($cat['total']/$maxCatStock*100) : 0 ?>%;background:var(--brand);"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
