<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle  = 'Inventory Management';
$activePage = 'inventory';
$db         = getDB();

// ── Filters ────────────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$cat     = (int)($_GET['cat'] ?? 0);
$filter  = $_GET['filter'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = ROWS_PER_PAGE;

// ── Build Query ────────────────────────────────────────────────────
$where  = ["p.status = 'active'"];
$params = [];

if ($search) {
    $where[]  = "(p.name LIKE ? OR p.product_code LIKE ? OR p.rack_location LIKE ?)";
    $s        = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($cat) {
    $where[]  = "p.category_id = ?";
    $params[] = $cat;
}
if ($filter === 'low')      $where[] = "p.current_stock <= p.min_stock_level";
if ($filter === 'critical') $where[] = "p.current_stock <= (p.min_stock_level * 0.3)";
if ($filter === 'good')     $where[] = "p.current_stock > p.min_stock_level";

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM products p $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();

$offset = ($page - 1) * $perPage;
$stmt   = $db->prepare(
    "SELECT p.*, c.name AS cat_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     $whereSQL
     ORDER BY p.current_stock ASC, p.name ASC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();

// ── Build filter URL for pagination ───────────────────────────────
$filterUrl = BASE_URL . '/inventory.php?' . http_build_query(array_filter(['search'=>$search,'cat'=>$cat,'filter'=>$filter]));

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
  <span class="sep">›</span><span class="cur">Inventory Management</span>
</div>

<div class="page-header">
  <h1>📦 Inventory Management</h1>
  <div class="page-header-actions">
    <?php if (canDo('stock_in')): ?>
    <a href="<?= BASE_URL ?>/stock.php?action=stockin" class="btn btn-success btn-sm">➕ Stock In</a>
    <?php endif; ?>
    <?php if (canDo('stock_adjust')): ?>
    <a href="<?= BASE_URL ?>/stock.php?action=adjust" class="btn btn-warning btn-sm">📊 Adjustment</a>
    <?php endif; ?>
    <?php if (canDo('add_product')): ?>
    <a href="<?= BASE_URL ?>/products.php?action=new" class="btn btn-primary btn-sm">+ New Product</a>
    <?php endif; ?>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 18px;">
    <form method="get" action="" id="filterForm">
      <div class="filter-bar">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search product name, code, location...">
        </div>
        <select name="cat" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="filter" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="low"      <?= $filter==='low'?'selected':'' ?>>Low Stock</option>
          <option value="critical" <?= $filter==='critical'?'selected':'' ?>>Critical</option>
          <option value="good"     <?= $filter==='good'?'selected':'' ?>>Good</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($search || $cat || $filter): ?>
          <a href="<?= BASE_URL ?>/inventory.php" class="btn btn-outline btn-sm">✕ Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- TABLE -->
<div class="card">
  <div class="card-header">
    <h3>Products (<?= $total ?>)</h3>
    <div class="d-flex gap-8">
      <?php if ($filter === 'low' || $filter === 'critical'): ?>
        <span class="badge badge-warning"><?= $total ?> items need attention</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($products)): ?>
      <div class="empty-state">
        <div class="empty-icon">📦</div>
        <h4>No products found</h4>
        <p>Try adjusting your search or filter criteria.</p>
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th>Unit</th>
          <th>Current Stock</th>
          <th>Reserved</th>
          <th>Available</th>
          <th>Min Level</th>
          <th>Location</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p):
          $avail  = (float)$p['current_stock'] - (float)$p['reserved_stock'];
          $isCrit = (float)$p['current_stock'] <= (float)$p['min_stock_level'] * 0.3;
          $isLow  = (float)$p['current_stock'] <= (float)$p['min_stock_level'];
          $rowCls = $isCrit ? 'crit-stock-row' : ($isLow ? 'low-stock-row' : '');
        ?>
        <tr class="<?= $rowCls ?>">
          <td>
            <strong><?= e($p['name']) ?></strong>
            <div class="text-small text-muted mono"><?= e($p['product_code']) ?></div>
          </td>
          <td><span class="badge badge-gray"><?= e($p['cat_name']) ?></span></td>
          <td><?= e($p['unit']) ?></td>
          <td>
            <strong><?= number_format((float)$p['current_stock'],2) ?></strong>
            <div class="stock-bar-wrap" style="margin-top:4px;">
              <?php
                $pct = $p['min_stock_level'] > 0 ? min(100, ($p['current_stock']/$p['min_stock_level']*100)) : 100;
                $barClr = $isCrit ? 'var(--danger)' : ($isLow ? 'var(--warning)' : 'var(--success)');
              ?>
              <div class="stock-bar"><div class="stock-bar-fill" style="width:<?= round($pct) ?>%;background:<?= $barClr ?>;"></div></div>
            </div>
          </td>
          <td style="color:var(--accent);"><?= number_format((float)$p['reserved_stock'],2) ?></td>
          <td><strong style="color:<?= $avail <= 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= number_format($avail,2) ?></strong></td>
          <td><?= number_format((float)$p['min_stock_level'],2) ?></td>
          <td><span class="mono text-small"><?= e($p['rack_location'] ?: '—') ?></span></td>
          <td><?= stockBadge((float)$p['current_stock'], (float)$p['min_stock_level']) ?></td>
          <td>
            <div class="btn-group">
              <?php if (canDo('stock_in')): ?>
              <a href="<?= BASE_URL ?>/stock.php?action=stockin&product_id=<?= $p['id'] ?>" class="btn btn-outline btn-xs">+In</a>
              <?php endif; ?>
              <?php if (canDo('stock_adjust')): ?>
              <a href="<?= BASE_URL ?>/stock.php?action=adjust&product_id=<?= $p['id'] ?>" class="btn btn-outline btn-xs">Adj</a>
              <?php endif; ?>
              <?php if (canDo('edit_product')): ?>
              <a href="<?= BASE_URL ?>/products.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-xs">✏️</a>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>/stock.php?product_id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-xs">Ledger</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($total > $perPage): ?>
  <div class="card-footer">
    <?= paginate($total, $page, $perPage, $filterUrl) ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
