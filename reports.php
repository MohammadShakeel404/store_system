<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
if (!canDo('view_reports')) { http_response_code(403); die(renderAccessDenied()); }

$pageTitle  = 'Reports & Export';
$activePage = 'reports';
$db         = getDB();

// ── Filters ────────────────────────────────────────────────────
$from  = $_GET['from']  ?? date('Y-m-01');
$to    = $_GET['to']    ?? date('Y-m-d');
$dept  = $_GET['dept']  ?? '';
$catId = (int)($_GET['cat'] ?? 0);

// ── Summary Stats for Date Range ───────────────────────────────
$statsStmt = $db->prepare(
    "SELECT
       (SELECT COUNT(*) FROM issue_requests WHERE status='issued' AND DATE(issued_at) BETWEEN ? AND ?) AS total_issued,
       (SELECT COALESCE(SUM(quantity),0) FROM stock_transactions WHERE type='stock_in' AND DATE(created_at) BETWEEN ? AND ?) AS total_stock_in,
       (SELECT COALESCE(SUM(quantity),0) FROM stock_transactions WHERE type='stock_out' AND DATE(created_at) BETWEEN ? AND ?) AS total_stock_out,
       (SELECT COUNT(*) FROM indents WHERE DATE(created_at) BETWEEN ? AND ?) AS total_indents,
       (SELECT COUNT(*) FROM indents WHERE status='approved' AND DATE(created_at) BETWEEN ? AND ?) AS approved_indents,
       (SELECT COUNT(*) FROM issue_requests WHERE status='rejected' AND DATE(created_at) BETWEEN ? AND ?) AS rejected_issues"
);
$statsStmt->execute([$from,$to, $from,$to, $from,$to, $from,$to, $from,$to, $from,$to]);
$stats = $statsStmt->fetch();

// ── Daily Issues (line data) ───────────────────────────────────
$dailyIssues = $db->prepare(
    "SELECT DATE(issued_at) AS day, COUNT(*) AS cnt, SUM(quantity_issued) AS qty
     FROM issue_requests WHERE status='issued' AND DATE(issued_at) BETWEEN ? AND ?
     GROUP BY DATE(issued_at) ORDER BY day"
);
$dailyIssues->execute([$from, $to]);
$dailyIssues = $dailyIssues->fetchAll();

// ── Top Consumed Products ──────────────────────────────────────
$topProducts = $db->prepare(
    "SELECT p.name, p.unit, SUM(ir.quantity_issued) AS total_qty
     FROM issue_requests ir JOIN products p ON ir.product_id = p.id
     WHERE ir.status='issued' AND DATE(ir.issued_at) BETWEEN ? AND ?
     GROUP BY ir.product_id ORDER BY total_qty DESC LIMIT 10"
);
$topProducts->execute([$from, $to]);
$topProducts = $topProducts->fetchAll();

// ── Department-wise Consumption ────────────────────────────────
$deptConsumption = $db->prepare(
    "SELECT department, COUNT(*) AS requests, SUM(quantity_issued) AS total_qty
     FROM issue_requests WHERE status='issued' AND DATE(issued_at) BETWEEN ? AND ?
     GROUP BY department ORDER BY total_qty DESC"
);
$deptConsumption->execute([$from, $to]);
$deptConsumption = $deptConsumption->fetchAll();

// ── Category-wise Stock ────────────────────────────────────────
$categoryStock = $db->query(
    "SELECT c.name, COUNT(p.id) AS items,
            SUM(p.current_stock) AS total_stock,
            SUM(CASE WHEN p.current_stock <= p.min_stock_level THEN 1 ELSE 0 END) AS low_items
     FROM products p JOIN categories c ON p.category_id = c.id
     WHERE p.status='active' GROUP BY c.id ORDER BY c.name"
)->fetchAll();

// ── Employee Issue History ─────────────────────────────────────
$empHistory = $db->prepare(
    "SELECT ir.employee_name, ir.department,
            COUNT(*) AS total_requests,
            SUM(ir.quantity_issued) AS total_qty,
            MAX(ir.issued_at) AS last_request
     FROM issue_requests ir
     WHERE ir.status='issued' AND DATE(ir.issued_at) BETWEEN ? AND ?
     GROUP BY ir.employee_name, ir.department
     ORDER BY total_requests DESC LIMIT 15"
);
$empHistory->execute([$from, $to]);
$empHistory = $empHistory->fetchAll();

// ── Low Stock List ─────────────────────────────────────────────
$lowStockList = $db->query(
    "SELECT p.*, c.name AS cat_name FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.current_stock <= p.min_stock_level AND p.status='active'
     ORDER BY (p.current_stock / NULLIF(p.min_stock_level,0)) ASC"
)->fetchAll();

$categories = $db->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$departments = $db->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Build export base query string
$exportParams = http_build_query(['from'=>$from,'to'=>$to,'dept'=>$dept,'cat'=>$catId]);

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
  <span class="sep">›</span><span class="cur">Reports & Export</span>
</div>

<div class="page-header">
  <h1>📑 Reports & Analytics</h1>
</div>

<!-- DATE FILTER -->
<div class="card mb-20">
  <div class="card-body" style="padding:14px 18px;">
    <form method="get" action="">
      <div class="filter-bar">
        <label style="font-size:12px;font-weight:700;color:var(--g700);">Date Range:</label>
        <input type="date" name="from" value="<?= e($from) ?>">
        <span style="color:var(--g500);">to</span>
        <input type="date" name="to"   value="<?= e($to) ?>">
        <select name="dept">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?><option <?= $dept===$d?'selected':'' ?>><?= e($d) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
        <a href="<?= BASE_URL ?>/reports.php" class="btn btn-outline btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- SUMMARY STATS -->
<div class="grid-4 mb-20">
  <div class="stat-card"><div class="stat-icon green">📤</div><div><div class="stat-val"><?= number_format($stats['total_issued']) ?></div><div class="stat-label">Issues in Period</div></div></div>
  <div class="stat-card"><div class="stat-icon blue">📦</div><div><div class="stat-val"><?= number_format($stats['total_stock_in']) ?></div><div class="stat-label">Stock In (Units)</div></div></div>
  <div class="stat-card"><div class="stat-icon orange">📋</div><div><div class="stat-val"><?= number_format($stats['total_indents']) ?></div><div class="stat-label">Indents Raised</div></div></div>
  <div class="stat-card"><div class="stat-icon red">❌</div><div><div class="stat-val"><?= number_format($stats['rejected_issues']) ?></div><div class="stat-label">Rejected Issues</div></div></div>
</div>

<!-- EXPORT CARDS -->
<h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">⬇️ Export Reports</h3>
<div class="grid-3 mb-20">
  <?php
  $reports = [
    ['icon'=>'📅','name'=>'Daily Issue Report','desc'=>'All materials issued in selected period','type'=>'daily_issues'],
    ['icon'=>'📦','name'=>'Stock Summary','desc'=>'Current stock levels for all products','type'=>'stock_summary'],
    ['icon'=>'⚠️','name'=>'Low Stock Alert','desc'=>'Items below minimum stock level','type'=>'low_stock'],
    ['icon'=>'👷','name'=>'Employee Issue History','desc'=>'Issue records per employee','type'=>'emp_history'],
    ['icon'=>'🏷️','name'=>'Category-wise Report','desc'=>'Stock by product category','type'=>'category_stock'],
    ['icon'=>'🔄','name'=>'Stock Ledger','desc'=>'Full stock movement ledger','type'=>'stock_ledger'],
  ];
  foreach ($reports as $r):
  ?>
  <div class="report-card">
    <div class="report-info">
      <div class="report-icon"><?= $r['icon'] ?></div>
      <div class="report-name"><?= $r['name'] ?></div>
      <div class="report-desc"><?= $r['desc'] ?></div>
    </div>
    <div class="report-actions">
      <a href="<?= BASE_URL ?>/reports_export/export.php?type=<?= $r['type'] ?>&format=csv&<?= $exportParams ?>" class="btn btn-primary btn-sm">📊 CSV</a>
      <a href="<?= BASE_URL ?>/reports_export/export.php?type=<?= $r['type'] ?>&format=html&<?= $exportParams ?>" target="_blank" class="btn btn-outline btn-sm">🖨️ Print</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- DATA TABLES -->
<div class="grid-2 mb-20">

  <!-- TOP CONSUMED PRODUCTS -->
  <div class="card">
    <div class="card-header">
      <h3>🏆 Top Consumed Products</h3>
      <span class="text-small text-muted"><?= e($from) ?> to <?= e($to) ?></span>
    </div>
    <?php if (empty($topProducts)): ?>
      <div class="empty-state" style="padding:24px;"><div>No issues in this period</div></div>
    <?php else: ?>
    <?php $maxQty = max(array_column($topProducts,'total_qty')?:[1]); ?>
    <div class="card-body">
      <?php foreach ($topProducts as $i => $p): ?>
      <div style="margin-bottom:12px;">
        <div class="d-flex justify-between" style="margin-bottom:4px;">
          <span style="font-size:13px;font-weight:600;"><?= ($i+1) ?>. <?= e($p['name']) ?></span>
          <span class="text-small text-muted"><?= number_format((float)$p['total_qty'],2) ?> <?= e($p['unit']) ?></span>
        </div>
        <div class="progress">
          <div class="progress-fill" style="width:<?= $maxQty>0?round($p['total_qty']/$maxQty*100):0 ?>%;background:var(--brand);"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- DEPT CONSUMPTION -->
  <div class="card">
    <div class="card-header"><h3>🏢 Department-wise Consumption</h3></div>
    <div class="table-wrap">
      <?php if (empty($deptConsumption)): ?>
        <div class="empty-state" style="padding:24px;"><div>No data in this period</div></div>
      <?php else: ?>
      <table>
        <thead><tr><th>Department</th><th>Requests</th><th>Total Qty</th></tr></thead>
        <tbody>
          <?php foreach ($deptConsumption as $d): ?>
          <tr>
            <td><strong><?= e($d['department'] ?: 'N/A') ?></strong></td>
            <td><?= number_format($d['requests']) ?></td>
            <td><?= number_format((float)$d['total_qty'],2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- CATEGORY STOCK -->
<div class="card mb-20">
  <div class="card-header"><h3>📦 Category-wise Stock Overview</h3></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Category</th><th>Total Products</th><th>Total Stock (Units)</th><th>Low Stock Items</th></tr></thead>
      <tbody>
        <?php foreach ($categoryStock as $c): ?>
        <tr <?= $c['low_items']>0?'class="low-stock-row"':'' ?>>
          <td><strong><?= e($c['name']) ?></strong></td>
          <td><?= $c['items'] ?></td>
          <td><?= number_format((float)$c['total_stock'],2) ?></td>
          <td><?= $c['low_items']>0 ? '<span class="badge badge-warning">'.$c['low_items'].' Low</span>' : '<span class="badge badge-success">All Good</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- EMPLOYEE HISTORY -->
<div class="card mb-20">
  <div class="card-header">
    <h3>👷 Employee Issue History</h3>
    <span class="text-small text-muted">Top 15 · <?= e($from) ?> to <?= e($to) ?></span>
  </div>
  <div class="table-wrap">
    <?php if (empty($empHistory)): ?>
      <div class="empty-state" style="padding:24px;"><div>No issues in this period</div></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Employee</th><th>Department</th><th>Total Requests</th><th>Total Qty Issued</th><th>Last Request</th></tr></thead>
      <tbody>
        <?php foreach ($empHistory as $e): ?>
        <tr>
          <td><strong><?= e($e['employee_name']) ?></strong></td>
          <td><?= e($e['department'] ?: '—') ?></td>
          <td><?= number_format($e['total_requests']) ?></td>
          <td><?= number_format((float)$e['total_qty'],2) ?></td>
          <td class="text-small text-muted"><?= formatDate($e['last_request']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- LOW STOCK TABLE -->
<?php if (!empty($lowStockList)): ?>
<div class="card">
  <div class="card-header">
    <h3>⚠️ Current Low Stock Items (<?= count($lowStockList) ?>)</h3>
    <a href="<?= BASE_URL ?>/reports_export/export.php?type=low_stock&format=csv" class="btn btn-warning btn-sm">Export</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Current Stock</th><th>Min Level</th><th>Available</th><th>Supplier</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($lowStockList as $p):
          $isCrit = (float)$p['current_stock'] <= (float)$p['min_stock_level']*0.3;
          $avail  = (float)$p['current_stock'] - (float)$p['reserved_stock'];
        ?>
        <tr class="<?= $isCrit?'crit-stock-row':'low-stock-row' ?>">
          <td><strong><?= e($p['name']) ?></strong><div class="mono text-small text-muted"><?= e($p['product_code']) ?></div></td>
          <td><?= e($p['cat_name']) ?></td>
          <td><strong><?= number_format((float)$p['current_stock'],2) ?></strong> <?= e($p['unit']) ?></td>
          <td><?= number_format((float)$p['min_stock_level'],2) ?></td>
          <td style="color:<?= $avail<=0?'var(--danger)':'inherit' ?>"><?= number_format($avail,2) ?></td>
          <td class="text-small text-muted"><?= e($p['supplier_name'] ?: '—') ?></td>
          <td><?= stockBadge((float)$p['current_stock'], (float)$p['min_stock_level']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
