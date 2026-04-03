<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole([ROLE_ADMIN]);

$pageTitle  = 'Product Master';
$activePage = 'products';
$db         = getDB();

$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);

// ── SAVE PRODUCT (POST) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['_action'] ?? '';

    if ($postAction === 'save_product') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $catId       = (int)($_POST['category_id'] ?? 0);
        $unit        = trim($_POST['unit'] ?? 'Nos');
        $minStock    = (float)($_POST['min_stock_level'] ?? 0);
        $location    = trim($_POST['rack_location'] ?? '');
        $supplier    = trim($_POST['supplier_name'] ?? '');
        $supplierCt  = trim($_POST['supplier_contact'] ?? '');
        $unitPrice   = (float)($_POST['unit_price'] ?? 0);
        $opening     = (float)($_POST['opening_stock'] ?? 0);

        if (!$name || !$catId) {
            setFlash('danger', 'Product name and category are required.');
        } else {
            try {
                $db->beginTransaction();

                if ($id) {
                    // UPDATE
                    $db->prepare("UPDATE products SET name=?, category_id=?, unit=?, min_stock_level=?, rack_location=?, supplier_name=?, supplier_contact=?, unit_price=? WHERE id=?")
                       ->execute([$name, $catId, $unit, $minStock, $location, $supplier, $supplierCt, $unitPrice, $id]);
                    $msg = "Product '{$name}' updated successfully.";
                    logAudit('EDIT', 'Product Master', "Updated product: {$name} (ID:{$id})", $id);
                } else {
                    // INSERT
                    $code = generateNumber('product') ?? 'P-' . str_pad($db->query("SELECT COUNT(*)+1 FROM products")->fetchColumn(), 4,'0',STR_PAD_LEFT);
                    // use a simple code if counter doesn't exist
                    $cnt  = (int)$db->query("SELECT COUNT(*)+1 FROM products")->fetchColumn();
                    $code = 'P-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);

                    $db->prepare("INSERT INTO products (product_code, name, category_id, unit, current_stock, min_stock_level, rack_location, supplier_name, supplier_contact, unit_price, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                       ->execute([$code, $name, $catId, $unit, $opening, $minStock, $location, $supplier, $supplierCt, $unitPrice, $_SESSION['user_id']]);
                    $newId = (int)$db->lastInsertId();

                    if ($opening > 0) {
                        // Record opening stock transaction
                        $db->prepare("INSERT INTO stock_transactions (txn_number, product_id, type, quantity, balance_before, balance_after, reference_number, reference_type, remarks, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                           ->execute(['TXN-OP-'.$newId, $newId, 'stock_in', $opening, 0, $opening, 'OPENING', 'opening', 'Opening stock', $_SESSION['user_id']]);
                    }
                    $msg = "Product '{$name}' added successfully.";
                    logAudit('ADD', 'Product Master', "New product added: {$name} ({$code})", $newId);
                }

                $db->commit();
                setFlash('success', $msg);
            } catch (Throwable $e) {
                $db->rollBack();
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
        header('Location: ' . BASE_URL . '/products.php');
        exit;
    }

    if ($postAction === 'toggle_status') {
        $id  = (int)($_POST['id'] ?? 0);
        $new = $_POST['new_status'] ?? 'inactive';
        $db->prepare("UPDATE products SET status=? WHERE id=?")->execute([$new, $id]);
        logAudit('STATUS CHANGE', 'Product Master', "Product ID:{$id} set to {$new}", $id);
        setFlash('success', 'Product status updated.');
        header('Location: ' . BASE_URL . '/products.php');
        exit;
    }
}

// ── EDIT MODE ─────────────────────────────────────────────────────
$editProduct = null;
if ($action === 'edit' && $editId) {
    $editProduct = getProductById($editId);
    if (!$editProduct) { setFlash('danger', 'Product not found.'); header('Location: ' . BASE_URL . '/products.php'); exit; }
}

// ── LIST ──────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage= ROWS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$whereSQL = $search ? "WHERE (p.name LIKE ? OR p.product_code LIKE ?)" : '';
$params   = $search ? ["%{$search}%", "%{$search}%"] : [];

$total = $db->prepare("SELECT COUNT(*) FROM products p $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();

$stmt = $db->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereSQL ORDER BY p.name LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$units = ['Nos','Kg','Gram','Ton','Meter','CM','Liter','ML','Box','Bag','Ream','Roll','Set','Pair','Cum','Sqm','Bundle'];

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
  <span class="sep">›</span><span class="cur">Product Master</span>
</div>

<div class="page-header">
  <h1>🏷️ Product Master</h1>
  <div class="page-header-actions">
    <button onclick="openModal('productModal')" class="btn btn-primary btn-sm">+ Add New Product</button>
  </div>
</div>

<!-- SEARCH -->
<div class="card mb-16">
  <div class="card-body" style="padding:12px 18px;">
    <form method="get" action="">
      <div class="filter-bar">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search product name or code...">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search): ?><a href="<?= BASE_URL ?>/products.php" class="btn btn-outline btn-sm">✕ Clear</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Products (<?= $total ?>)</h3></div>
  <div class="table-wrap">
    <?php if (empty($products)): ?>
      <div class="empty-state"><div class="empty-icon">🏷️</div><h4>No products yet</h4><p>Add your first product to get started.</p></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Code</th><th>Product Name</th><th>Category</th><th>Unit</th><th>Min Stock</th><th>Location</th><th>Supplier</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><span class="mono"><?= e($p['product_code']) ?></span></td>
          <td>
            <strong><?= e($p['name']) ?></strong>
            <?php if ($p['description']): ?><div class="text-small text-muted"><?= e(substr($p['description'],0,50)) ?></div><?php endif; ?>
          </td>
          <td><span class="badge badge-gray"><?= e($p['cat_name']) ?></span></td>
          <td><?= e($p['unit']) ?></td>
          <td><?= number_format((float)$p['min_stock_level'],2) ?></td>
          <td><span class="mono text-small"><?= e($p['rack_location'] ?: '—') ?></span></td>
          <td class="text-small text-muted"><?= e($p['supplier_name'] ?: '—') ?></td>
          <td><?= statusBadge($p['status']) ?></td>
          <td>
            <div class="btn-group">
              <button onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)" class="btn btn-outline btn-xs">✏️ Edit</button>
              <form method="post" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="_action" value="toggle_status">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <input type="hidden" name="new_status" value="<?= $p['status']==='active'?'inactive':'active' ?>">
                <button type="submit" class="btn btn-outline btn-xs <?= $p['status']==='active'?'text-danger':'' ?>"
                        onclick="return confirm('Change product status?')">
                  <?= $p['status']==='active' ? '🚫 Disable' : '✅ Enable' ?>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($total > $perPage): ?>
  <div class="card-footer"><?= paginate($total, $page, $perPage, BASE_URL . '/products.php' . ($search?"?search=".urlencode($search):'')) ?></div>
  <?php endif; ?>
</div>

<!-- ADD / EDIT MODAL -->
<div class="modal-overlay" id="productModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="productModalTitle">🏷️ Add New Product</h3>
      <button class="modal-close" onclick="closeModal('productModal')">✕</button>
    </div>
    <form method="post" action="">
      <?= csrfField() ?>
      <input type="hidden" name="_action" value="save_product">
      <input type="hidden" name="id" id="prod_id" value="">

      <div class="form-row">
        <div class="form-group">
          <label>Product Name *</label>
          <input type="text" name="name" id="prod_name" required placeholder="Full product name">
        </div>
        <div class="form-group">
          <label>Category *</label>
          <select name="category_id" id="prod_cat" required>
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Unit of Measure *</label>
          <select name="unit" id="prod_unit">
            <?php foreach ($units as $u): ?><option><?= $u ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Min Stock Level (Alert)</label>
          <input type="number" name="min_stock_level" id="prod_min" min="0" step="0.01" value="0">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Rack / Shelf Location</label>
          <input type="text" name="rack_location" id="prod_loc" placeholder="e.g. R-A3, Shelf-2, Yard-B">
        </div>
        <div class="form-group" id="openingStockGroup">
          <label>Opening Stock (New Only)</label>
          <input type="number" name="opening_stock" id="prod_opening" min="0" step="0.01" value="0">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Supplier Name</label>
          <input type="text" name="supplier_name" id="prod_supplier" placeholder="Primary supplier">
        </div>
        <div class="form-group">
          <label>Supplier Contact</label>
          <input type="text" name="supplier_contact" id="prod_supplier_ct" placeholder="Phone / Email">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Unit Price (₹)</label>
          <input type="number" name="unit_price" id="prod_price" min="0" step="0.01" value="0">
        </div>
        <div class="form-group">
          <label>HSN Code (optional)</label>
          <input type="text" name="hsn_code" id="prod_hsn" placeholder="HSN code">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('productModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Product</button>
      </div>
    </form>
  </div>
</div>

<script>
function editProduct(p) {
  document.getElementById('productModalTitle').textContent = '✏️ Edit Product';
  document.getElementById('prod_id').value      = p.id;
  document.getElementById('prod_name').value    = p.name;
  document.getElementById('prod_cat').value     = p.category_id;
  document.getElementById('prod_unit').value    = p.unit;
  document.getElementById('prod_min').value     = p.min_stock_level;
  document.getElementById('prod_loc').value     = p.rack_location || '';
  document.getElementById('prod_supplier').value= p.supplier_name || '';
  document.getElementById('prod_supplier_ct').value = p.supplier_contact || '';
  document.getElementById('prod_price').value   = p.unit_price || 0;
  document.getElementById('prod_hsn').value     = p.hsn_code || '';
  document.getElementById('openingStockGroup').style.display = 'none';
  openModal('productModal');
}
document.querySelector('[onclick*="productModal"]').addEventListener('click', function() {
  document.getElementById('productModalTitle').textContent = '🏷️ Add New Product';
  document.getElementById('prod_id').value = '';
  document.getElementById('openingStockGroup').style.display = 'block';
  document.querySelector('[name="opening_stock"]').value = 0;
});
<?php if ($action === 'new'): ?>
  document.addEventListener('DOMContentLoaded', () => openModal('productModal'));
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
