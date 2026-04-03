<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole([ROLE_ADMIN, ROLE_KEEPER, ROLE_MANAGEMENT]);

$pageTitle  = 'Stock Movement';
$activePage = 'stock';
$db         = getDB();
$user       = currentUser();
$action     = $_GET['action'] ?? 'list';

// ═══════════════════════════════════════════════════════════════
// POST: STOCK IN
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    requireRole([ROLE_ADMIN, ROLE_KEEPER]);
    $postAction = $_POST['_action'] ?? '';

    if ($postAction === 'stock_in') {
        $prodId   = (int)($_POST['product_id'] ?? 0);
        $qty      = (float)($_POST['quantity'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $invoice  = trim($_POST['invoice_number'] ?? '');
        $remarks  = trim($_POST['remarks'] ?? '');
        $date     = $_POST['entry_date'] ?? date('Y-m-d');

        if (!$prodId || $qty <= 0) {
            setFlash('danger', 'Select a product and enter quantity greater than 0.');
        } else {
            try {
                $grnNo = generateNumber('grn');
                recordStockTransaction($prodId, 'stock_in', $qty, $grnNo, 'grn', $supplier, $invoice, $remarks);
                $prod = getProductById($prodId);
                logAudit('STOCK IN', 'Stock Movement', "{$grnNo} — {$prod['name']} +{$qty} {$prod['unit']} | Supplier: {$supplier}", $prodId);
                setFlash('success', "Stock In recorded. {$qty} {$prod['unit']} of {$prod['name']} added. GRN: {$grnNo}");
            } catch (Throwable $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/stock.php');
        exit;
    }

    if ($postAction === 'adjustment') {
        requireRole([ROLE_ADMIN]);
        $prodId  = (int)($_POST['product_id'] ?? 0);
        $qty     = (float)($_POST['quantity'] ?? 0);
        $adjType = $_POST['adjustment_type'] ?? 'adjustment_out';
        $reason  = trim($_POST['reason'] ?? '');

        if (!$prodId || $qty <= 0 || !$reason) {
            setFlash('danger', 'All fields including reason are mandatory for adjustments.');
        } else {
            try {
                $adjNo = generateNumber('adjustment');
                recordStockTransaction($prodId, $adjType, $qty, $adjNo, 'adjustment', '', '', $reason);
                $prod = getProductById($prodId);
                $sign = $adjType === 'adjustment_in' ? '+' : '-';
                logAudit('ADJUSTMENT', 'Stock', "{$adjNo} — {$prod['name']} {$sign}{$qty} | {$reason}", $prodId);
                setFlash('success', "Adjustment {$adjNo} recorded successfully.");
            } catch (Throwable $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/stock.php');
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// LIST / LEDGER
// ═══════════════════════════════════════════════════════════════
$search    = trim($_GET['search'] ?? '');
$typeF     = $_GET['type'] ?? '';
$prodF     = (int)($_GET['product_id'] ?? 0);
$dateFrom  = $_GET['from'] ?? '';
$dateTo    = $_GET['to'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = ROWS_PER_PAGE;
$offset    = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($prodF) { $where[] = 'st.product_id = ?'; $params[] = $prodF; }
if ($typeF) { $where[] = 'st.type = ?'; $params[] = $typeF; }
if ($dateFrom) { $where[] = 'DATE(st.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(st.created_at) <= ?'; $params[] = $dateTo; }
if ($search)   {
    $where[]  = '(p.name LIKE ? OR st.txn_number LIKE ? OR st.reference_number LIKE ?)';
    $s        = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM stock_transactions st JOIN products p ON st.product_id = p.id $whereSQL");
$total->execute($params); $total = (int)$total->fetchColumn();

$stmt = $db->prepare(
    "SELECT st.*, p.name AS prod_name, p.unit, u.name AS created_by_name
     FROM stock_transactions st
     JOIN products p ON st.product_id = p.id
     LEFT JOIN users u ON st.created_by = u.id
     $whereSQL
     ORDER BY st.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$transactions = $stmt->fetchAll();

$products = $db->query("SELECT id, name, unit FROM products WHERE status='active' ORDER BY name")->fetchAll();

$filterUrl = BASE_URL . '/stock.php?' . http_build_query(array_filter(['search'=>$search,'type'=>$typeF,'product_id'=>$prodF,'from'=>$dateFrom,'to'=>$dateTo]));

// Type labels
$typeLabels = [
    'stock_in'        => ['📦 Stock In',      'badge-success'],
    'stock_out'       => ['📤 Stock Out',      'badge-info'],
    'adjustment_in'   => ['🔼 Adjustment In',  'badge-warning'],
    'adjustment_out'  => ['🔽 Adjustment Out', 'badge-danger'],
    'return'          => ['↩️ Return',          'badge-gray'],
];

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
  <span class="sep">›</span><span class="cur">Stock Movement</span>
</div>

<div class="page-header">
  <h1>🔄 Stock Movement Ledger</h1>
  <div class="page-header-actions">
    <?php if (canDo('stock_in')): ?>
    <button onclick="openModal('stockInModal')" class="btn btn-success btn-sm">➕ Stock In</button>
    <?php endif; ?>
    <?php if (canDo('stock_adjust')): ?>
    <button onclick="openModal('adjModal')" class="btn btn-warning btn-sm">📊 Adjustment</button>
    <?php endif; ?>
  </div>
</div>

<div class="alert alert-info mb-16">
  🔒 <strong>Immutable Ledger:</strong> Stock deletions are not permitted. Every stock change is permanently recorded here for full traceability and audit compliance.
</div>

<!-- FILTERS -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 18px;">
    <form method="get" action="">
      <div class="filter-bar">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search product, TXN#, GRN#...">
        </div>
        <select name="product_id">
          <option value="">All Products</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $prodF==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="type">
          <option value="">All Types</option>
          <?php foreach ($typeLabels as $t => $info): ?>
          <option value="<?= $t ?>" <?= $typeF===$t?'selected':'' ?>><?= strip_tags($info[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?= e($dateFrom) ?>" title="From date">
        <input type="date" name="to"   value="<?= e($dateTo) ?>"   title="To date">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($search || $typeF || $prodF || $dateFrom || $dateTo): ?>
        <a href="<?= BASE_URL ?>/stock.php" class="btn btn-outline btn-sm">✕ Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- LEDGER TABLE -->
<div class="card">
  <div class="card-header">
    <h3>Transactions (<?= number_format($total) ?>)</h3>
    <a href="<?= BASE_URL ?>/reports_export/stock_ledger_export.php?<?= http_build_query(array_filter(['product_id'=>$prodF,'type'=>$typeF,'from'=>$dateFrom,'to'=>$dateTo])) ?>" class="btn btn-outline btn-sm">📊 Export CSV</a>
  </div>
  <div class="table-wrap">
    <?php if (empty($transactions)): ?>
      <div class="empty-state"><div class="empty-icon">🔄</div><h4>No transactions found</h4></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>TXN #</th>
          <th>Date & Time</th>
          <th>Product</th>
          <th>Type</th>
          <th>Qty In</th>
          <th>Qty Out</th>
          <th>Balance Before</th>
          <th>Balance After</th>
          <th>Reference</th>
          <th>Done By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t):
          [$typeLabel, $typeCls] = $typeLabels[$t['type']] ?? [$t['type'], 'badge-gray'];
          $isIn  = in_array($t['type'], ['stock_in', 'adjustment_in', 'return']);
          $isOut = in_array($t['type'], ['stock_out', 'adjustment_out']);
        ?>
        <tr>
          <td><span class="mono"><?= e($t['txn_number']) ?></span></td>
          <td class="text-small" style="white-space:nowrap;"><?= formatDateTime($t['created_at']) ?></td>
          <td>
            <strong><?= e($t['prod_name']) ?></strong>
            <?php if ($t['supplier']): ?><div class="text-small text-muted"><?= e($t['supplier']) ?></div><?php endif; ?>
          </td>
          <td><span class="badge <?= $typeCls ?>"><?= $typeLabel ?></span></td>
          <td style="color:var(--success);font-weight:600;"><?= $isIn  ? '+' . number_format((float)$t['quantity'],2) . ' ' . e($t['unit']) : '—' ?></td>
          <td style="color:var(--danger);font-weight:600;" ><?= $isOut ? '−' . number_format((float)$t['quantity'],2) . ' ' . e($t['unit']) : '—' ?></td>
          <td class="text-muted"><?= number_format((float)$t['balance_before'],2) ?></td>
          <td><strong><?= number_format((float)$t['balance_after'],2) ?></strong></td>
          <td>
            <?php if ($t['reference_number']): ?>
            <span class="mono text-small"><?= e($t['reference_number']) ?></span>
            <?php endif; ?>
            <?php if ($t['invoice_number']): ?>
            <div class="text-small text-muted"><?= e($t['invoice_number']) ?></div>
            <?php endif; ?>
            <?php if ($t['remarks']): ?>
            <div class="text-small text-muted" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($t['remarks']) ?>"><?= e($t['remarks']) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-small text-muted"><?= e($t['created_by_name'] ?: 'System') ?></td>
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

<!-- ══ STOCK IN MODAL ══ -->
<?php if (canDo('stock_in')): ?>
<div class="modal-overlay" id="stockInModal">
  <div class="modal">
    <div class="modal-header">
      <h3>➕ Stock In — Receive Materials</h3>
      <button class="modal-close" onclick="closeModal('stockInModal')">✕</button>
    </div>
    <form method="post" action="">
      <?= csrfField() ?>
      <input type="hidden" name="_action" value="stock_in">

      <div class="form-group">
        <label>Product *</label>
        <select name="product_id" required>
          <option value="">-- Select Product --</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= isset($_GET['product_id']) && $_GET['product_id']==$p['id']?'selected':'' ?>>
            <?= e($p['name']) ?> (<?= e($p['unit']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Quantity Received *</label>
          <input type="number" name="quantity" min="0.01" step="0.01" value="1" required>
        </div>
        <div class="form-group">
          <label>Entry Date</label>
          <input type="date" name="entry_date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Supplier Name</label>
          <input type="text" name="supplier" placeholder="Supplier / vendor name">
        </div>
        <div class="form-group">
          <label>Invoice / Challan Number</label>
          <input type="text" name="invoice_number" placeholder="INV-XXXX or DC-XXXX">
        </div>
      </div>

      <div class="form-group">
        <label>Remarks</label>
        <input type="text" name="remarks" placeholder="Optional notes about this receipt">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('stockInModal')">Cancel</button>
        <button type="submit" class="btn btn-success">✓ Record Stock In</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ══ ADJUSTMENT MODAL ══ -->
<?php if (canDo('stock_adjust')): ?>
<div class="modal-overlay" id="adjModal">
  <div class="modal">
    <div class="modal-header">
      <h3>📊 Stock Adjustment</h3>
      <button class="modal-close" onclick="closeModal('adjModal')">✕</button>
    </div>
    <div class="alert alert-warning" style="margin-bottom:16px;">
      ⚠️ Adjustments are <strong>permanent</strong> and recorded in the audit trail. Provide accurate reason.
    </div>
    <form method="post" action="">
      <?= csrfField() ?>
      <input type="hidden" name="_action" value="adjustment">

      <div class="form-group">
        <label>Product *</label>
        <select name="product_id" required>
          <option value="">-- Select Product --</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= isset($_GET['product_id']) && $_GET['product_id']==$p['id']?'selected':'' ?>>
            <?= e($p['name']) ?> (<?= e($p['unit']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Adjustment Type *</label>
          <select name="adjustment_type">
            <option value="adjustment_out">Deduct (Damage / Loss / Write-off)</option>
            <option value="adjustment_in">Add   (Physical Count Correction)</option>
            <option value="return">Return to Stock</option>
          </select>
        </div>
        <div class="form-group">
          <label>Quantity *</label>
          <input type="number" name="quantity" min="0.01" step="0.01" value="1" required>
        </div>
      </div>

      <div class="form-group">
        <label>Reason (Mandatory) *</label>
        <textarea name="reason" rows="3" required placeholder="Provide detailed reason — this is recorded permanently in the audit trail..."></textarea>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('adjModal')">Cancel</button>
        <button type="submit" class="btn btn-warning" onclick="return confirm('This adjustment is permanent. Confirm?')">Record Adjustment</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php
// Auto-open modal from URL param
if ($action === 'stockin'): ?>
<script>document.addEventListener('DOMContentLoaded',()=>openModal('stockInModal'));</script>
<?php elseif ($action === 'adjust'): ?>
<script>document.addEventListener('DOMContentLoaded',()=>openModal('adjModal'));</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
