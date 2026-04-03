<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle  = 'Issue Requests';
$activePage = 'issues';
$db         = getDB();
$user       = currentUser();

$action = $_GET['action'] ?? 'list';

// ═══════════════════════════════════════════════════════════════
// POST ACTIONS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['_action'] ?? '';

    // ── SUBMIT NEW ISSUE REQUEST ──────────────────────────────────
    if ($postAction === 'submit_issue') {
        $empName  = trim($_POST['employee_name'] ?? $user['name']);
        $dept     = trim($_POST['department'] ?? $user['dept']);
        $prodId   = (int)($_POST['product_id'] ?? 0);
        $qty      = (float)($_POST['quantity_requested'] ?? 0);
        $purpose  = trim($_POST['purpose'] ?? '');
        $woRef    = trim($_POST['work_order_ref'] ?? '');
        $reqDate  = $_POST['required_date'] ?? date('Y-m-d');

        if (!$prodId || $qty <= 0) {
            setFlash('danger', 'Please select a product and enter a valid quantity.');
        } else {
            $issueNo = generateNumber('issue');
            $db->prepare("INSERT INTO issue_requests (issue_number, employee_id, employee_name, department, product_id, quantity_requested, purpose, work_order_ref, required_date, status) VALUES (?,?,?,?,?,?,?,?,?,'pending')")
               ->execute([$issueNo, $user['id'], $empName, $dept, $prodId, $qty, $purpose, $woRef, $reqDate]);
            $newId = (int)$db->lastInsertId();

            $prod = getProductById($prodId);
            notifyAdminsAndKeepers("📤 New Issue Request {$issueNo}", "{$empName} requested {$qty} {$prod['unit']} of {$prod['name']}", 'info', 'issues.php');
            logAudit('REQUEST', 'Issue Request', "{$issueNo} by {$empName} for {$prod['name']} ×{$qty}", $newId);
            setFlash('success', "Issue request {$issueNo} submitted successfully! Store will review it.");
        }
        header('Location: ' . BASE_URL . '/issues.php');
        exit;
    }

    // ── APPROVE (ISSUE) ────────────────────────────────────────────
    if ($postAction === 'approve_issue') {
        requireRole([ROLE_ADMIN, ROLE_KEEPER]);
        $id  = (int)($_POST['id'] ?? 0);
        $qtyIssued = (float)($_POST['qty_issued'] ?? 0);

        $req = $db->prepare("SELECT ir.*, p.name AS prod_name, p.unit FROM issue_requests ir JOIN products p ON ir.product_id = p.id WHERE ir.id = ? AND ir.status = 'pending'")->execute([$id]) ? null : null;
        $stmt = $db->prepare("SELECT ir.*, p.name AS prod_name, p.unit, p.current_stock, p.reserved_stock FROM issue_requests ir JOIN products p ON ir.product_id = p.id WHERE ir.id = ? AND ir.status = 'pending'");
        $stmt->execute([$id]);
        $req = $stmt->fetch();

        if (!$req) { setFlash('danger', 'Request not found or already processed.'); header('Location: ' . BASE_URL . '/issues.php'); exit; }

        $avail = (float)$req['current_stock'] - (float)$req['reserved_stock'];
        $issueQty = $qtyIssued > 0 ? $qtyIssued : (float)$req['quantity_requested'];

        if ($issueQty > $avail) {
            setFlash('danger', "Insufficient stock. Available: {$avail} {$req['unit']}"); header('Location: ' . BASE_URL . '/issues.php?action=review&id='.$id); exit;
        }

        try {
            $db->beginTransaction();
            $txnId = recordStockTransaction($req['product_id'], 'stock_out', $issueQty, $req['issue_number'], 'issue', '', '', "Issued to: {$req['employee_name']} — {$req['purpose']}");
            $status = $issueQty < (float)$req['quantity_requested'] ? 'partially_issued' : 'issued';
            $db->prepare("UPDATE issue_requests SET status=?, quantity_issued=?, issued_by=?, issued_at=NOW(), stock_txn_id=? WHERE id=?")
               ->execute([$status, $issueQty, $user['id'], $txnId, $id]);
            $db->commit();

            // Notify employee
            if ($req['employee_id']) {
                sendNotification($req['employee_id'], "✅ Issue Request Approved", "Your request {$req['issue_number']} for {$req['prod_name']} has been {$status}.", 'success', 'issues.php');
            }
            logAudit('APPROVE', 'Issue Request', "Approved {$req['issue_number']} — {$req['prod_name']} ×{$issueQty} to {$req['employee_name']}", $id);
            setFlash('success', "Issue request approved. {$issueQty} {$req['unit']} of {$req['prod_name']} issued to {$req['employee_name']}.");
        } catch (Throwable $e) {
            $db->rollBack();
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/issues.php'); exit;
    }

    // ── REJECT ─────────────────────────────────────────────────────
    if ($postAction === 'reject_issue') {
        requireRole([ROLE_ADMIN, ROLE_KEEPER]);
        $id     = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (!$reason) { setFlash('danger', 'Rejection reason is mandatory.'); header('Location: ' . BASE_URL . '/issues.php?action=review&id='.$id); exit; }

        $stmt = $db->prepare("SELECT * FROM issue_requests WHERE id=? AND status='pending'");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) { setFlash('danger', 'Request not found.'); header('Location: ' . BASE_URL . '/issues.php'); exit; }

        $db->prepare("UPDATE issue_requests SET status='rejected', rejection_reason=?, issued_by=?, issued_at=NOW() WHERE id=?")
           ->execute([$reason, $user['id'], $id]);

        if ($req['employee_id']) {
            sendNotification($req['employee_id'], "❌ Issue Request Rejected", "Your request {$req['issue_number']} was rejected. Reason: {$reason}", 'danger', 'issues.php');
        }
        logAudit('REJECT', 'Issue Request', "Rejected {$req['issue_number']} — Reason: {$reason}", $id);
        setFlash('danger', "Issue request {$req['issue_number']} rejected.");
        header('Location: ' . BASE_URL . '/issues.php'); exit;
    }
}

// ═══════════════════════════════════════════════════════════════
// REVIEW PAGE
// ═══════════════════════════════════════════════════════════════
$reviewReq = null;
if ($action === 'review' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT ir.*, p.name AS prod_name, p.unit, p.current_stock, p.reserved_stock, p.min_stock_level FROM issue_requests ir JOIN products p ON ir.product_id = p.id WHERE ir.id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $reviewReq = $stmt->fetch();
}

// ═══════════════════════════════════════════════════════════════
// LIST
// ═══════════════════════════════════════════════════════════════
$status  = $_GET['status'] ?? '';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = ROWS_PER_PAGE;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

// Employees only see their own requests
if ($user['role'] === ROLE_EMPLOYEE) {
    $where[]  = 'ir.employee_id = ?';
    $params[] = $user['id'];
}
if ($status) { $where[] = 'ir.status = ?'; $params[] = $status; }
if ($search) {
    $where[]  = '(ir.issue_number LIKE ? OR ir.employee_name LIKE ? OR p.name LIKE ?)';
    $s        = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM issue_requests ir JOIN products p ON ir.product_id = p.id $whereSQL");
$total->execute($params); $total = (int)$total->fetchColumn();

$stmt = $db->prepare("SELECT ir.*, p.name AS prod_name, p.unit, ub.name AS issued_by_name FROM issue_requests ir JOIN products p ON ir.product_id = p.id LEFT JOIN users ub ON ir.issued_by = ub.id $whereSQL ORDER BY ir.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset]));
$issues = $stmt->fetchAll();

// Products for dropdown
$prodList = $db->query("SELECT id, name, unit, current_stock, reserved_stock FROM products WHERE status='active' ORDER BY name")->fetchAll();

$statusCounts = [];
foreach (['','pending','issued','rejected','partially_issued'] as $s) {
    $sq  = $db->prepare("SELECT COUNT(*) FROM issue_requests ir WHERE " . ($user['role']===ROLE_EMPLOYEE ? "ir.employee_id = {$user['id']} AND " : '') . ($s ? "ir.status = '$s'" : "1=1"));
    $sq->execute(); $statusCounts[$s] = (int)$sq->fetchColumn();
}

$filterUrl = BASE_URL . '/issues.php?' . http_build_query(array_filter(['search'=>$search,'status'=>$status]));

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
  <span class="sep">›</span><span class="cur">Issue Requests</span>
</div>

<?php if ($reviewReq && $action === 'review'): ?>
<!-- ── REVIEW MODE ──────────────────────────────────────────────── -->
<div class="page-header">
  <h1>📤 Review Issue Request</h1>
  <a href="<?= BASE_URL ?>/issues.php" class="btn btn-outline btn-sm">← Back to List</a>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3>Request Details</h3></div>
    <div class="card-body">
      <div class="detail-panel">
        <div class="detail-row">
          <div class="detail-item"><div class="di-label">Request ID</div><div class="di-val"><?= e($reviewReq['issue_number']) ?></div></div>
          <div class="detail-item"><div class="di-label">Status</div><div class="di-val"><?= statusBadge($reviewReq['status']) ?></div></div>
          <div class="detail-item"><div class="di-label">Employee</div><div class="di-val"><?= e($reviewReq['employee_name']) ?></div></div>
          <div class="detail-item"><div class="di-label">Department</div><div class="di-val"><?= e($reviewReq['department']) ?></div></div>
          <div class="detail-item"><div class="di-label">Product</div><div class="di-val"><?= e($reviewReq['prod_name']) ?></div></div>
          <div class="detail-item"><div class="di-label">Qty Requested</div><div class="di-val"><?= e($reviewReq['quantity_requested']) ?> <?= e($reviewReq['unit']) ?></div></div>
          <div class="detail-item"><div class="di-label">Required By</div><div class="di-val"><?= formatDate($reviewReq['required_date']) ?></div></div>
          <div class="detail-item"><div class="di-label">Work Order</div><div class="di-val"><?= e($reviewReq['work_order_ref'] ?: '—') ?></div></div>
        </div>
        <?php if ($reviewReq['purpose']): ?>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--g200);">
          <div style="font-size:11px;font-weight:700;color:var(--g600);text-transform:uppercase;margin-bottom:4px;">Purpose</div>
          <div><?= e($reviewReq['purpose']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>Stock Information</h3></div>
    <div class="card-body">
      <?php
        $avail = (float)$reviewReq['current_stock'] - (float)$reviewReq['reserved_stock'];
        $canIssue = $avail >= (float)$reviewReq['quantity_requested'];
        $pct = $reviewReq['min_stock_level'] > 0 ? min(100, $reviewReq['current_stock']/$reviewReq['min_stock_level']*100) : 100;
      ?>
      <div class="detail-panel" style="margin-bottom:16px;">
        <div class="detail-row">
          <div class="detail-item"><div class="di-label">Current Stock</div><div class="di-val"><?= $reviewReq['current_stock'] ?> <?= e($reviewReq['unit']) ?></div></div>
          <div class="detail-item"><div class="di-label">Reserved</div><div class="di-val" style="color:var(--accent)"><?= $reviewReq['reserved_stock'] ?></div></div>
          <div class="detail-item"><div class="di-label">Available</div><div class="di-val" style="color:<?= $avail >= $reviewReq['quantity_requested'] ? 'var(--success)' : 'var(--danger)' ?>"><?= $avail ?></div></div>
          <div class="detail-item"><div class="di-label">Requested</div><div class="di-val"><?= $reviewReq['quantity_requested'] ?></div></div>
        </div>
        <div class="progress" style="margin-top:8px;">
          <div class="progress-fill" style="width:<?= round($pct) ?>%;background:<?= $pct<=30?'var(--danger)':($pct<=100?'var(--warning)':'var(--success)') ?>;"></div>
        </div>
      </div>

      <?php if ($reviewReq['status'] === 'pending' && canDo('approve_issue')): ?>
        <?php if (!$canIssue): ?>
        <div class="alert alert-warning mb-16">⚠️ Insufficient stock. You can issue partial quantity or reject and convert to indent.</div>
        <?php endif; ?>

        <!-- APPROVE FORM -->
        <form method="post" action="">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="approve_issue">
          <input type="hidden" name="id" value="<?= $reviewReq['id'] ?>">
          <div class="form-group">
            <label>Quantity to Issue</label>
            <input type="number" name="qty_issued" value="<?= min($avail, $reviewReq['quantity_requested']) ?>" min="0.01" max="<?= $avail ?>" step="0.01" required>
            <div class="form-hint">Available: <?= $avail ?> <?= e($reviewReq['unit']) ?></div>
          </div>
          <button type="submit" class="btn btn-success btn-full" <?= $avail <= 0 ? 'disabled' : '' ?>>✅ Approve & Issue</button>
        </form>

        <div class="hr"></div>

        <!-- REJECT FORM -->
        <form method="post" action="">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="reject_issue">
          <input type="hidden" name="id" value="<?= $reviewReq['id'] ?>">
          <div class="form-group">
            <label>Rejection Reason (Mandatory)</label>
            <textarea name="rejection_reason" required placeholder="Enter detailed reason for rejection..."></textarea>
          </div>
          <button type="submit" class="btn btn-danger btn-full" onclick="return confirm('Reject this request?')">❌ Reject Request</button>
        </form>
      <?php else: ?>
        <div class="alert alert-info">
          <?php if ($reviewReq['status'] === 'issued'): ?>
            ✅ Issued <?= $reviewReq['quantity_issued'] ?> <?= e($reviewReq['unit']) ?> on <?= formatDateTime($reviewReq['issued_at']) ?>
          <?php elseif ($reviewReq['status'] === 'rejected'): ?>
            ❌ Rejected — <?= e($reviewReq['rejection_reason']) ?>
          <?php else: ?>
            Status: <?= statusBadge($reviewReq['status']) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── LIST MODE ──────────────────────────────────────────────── -->

<div class="page-header">
  <h1>📤 Issue Requests</h1>
  <?php if (canDo('raise_request')): ?>
  <button onclick="openModal('newIssueModal')" class="btn btn-primary btn-sm">+ New Issue Request</button>
  <?php endif; ?>
</div>

<!-- TABS -->
<div class="tabs mb-16">
  <?php
  $tabs = [''  => 'All Requests', 'pending' => 'Pending', 'issued' => 'Issued', 'rejected' => 'Rejected'];
  foreach ($tabs as $s => $label):
  ?>
  <a href="<?= BASE_URL ?>/issues.php?status=<?= $s ?>" class="tab <?= $status===$s?'active':'' ?>">
    <?= $label ?>
    <?php if ($statusCounts[$s] > 0): ?><span class="badge badge-gray" style="margin-left:4px;"><?= $statusCounts[$s] ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- SEARCH & FILTER -->
<div class="card mb-16">
  <div class="card-body" style="padding:12px 18px;">
    <form method="get" action="">
      <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <div class="filter-bar">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by number, employee, product...">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search): ?><a href="<?= BASE_URL ?>/issues.php<?= $status?'?status='.$status:'' ?>" class="btn btn-outline btn-sm">✕ Clear</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Requests (<?= $total ?>)</h3></div>
  <div class="table-wrap">
    <?php if (empty($issues)): ?>
      <div class="empty-state"><div class="empty-icon">📤</div><h4>No requests found</h4></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Issue #</th><th>Employee</th><th>Dept</th><th>Product</th>
          <th>Qty Req.</th><th>Qty Issued</th><th>Date</th><th>Issued By</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($issues as $iss): ?>
        <tr>
          <td><strong class="mono"><?= e($iss['issue_number']) ?></strong></td>
          <td><?= e($iss['employee_name']) ?></td>
          <td><?= e($iss['department']) ?></td>
          <td><?= e($iss['prod_name']) ?></td>
          <td><?= e($iss['quantity_requested']) ?> <?= e($iss['unit']) ?></td>
          <td><?= $iss['quantity_issued'] ? e($iss['quantity_issued']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-small"><?= formatDate($iss['created_at']) ?></td>
          <td class="text-small text-muted"><?= e($iss['issued_by_name'] ?: '—') ?></td>
          <td><?= statusBadge($iss['status']) ?></td>
          <td>
            <div class="btn-group">
              <?php if ($iss['status'] === 'pending' && canDo('approve_issue')): ?>
                <a href="<?= BASE_URL ?>/issues.php?action=review&id=<?= $iss['id'] ?>" class="btn btn-success btn-xs">Review</a>
              <?php endif; ?>
              <?php if ($iss['status'] === 'issued'): ?>
                <a href="<?= BASE_URL ?>/reports_export/issue_slip.php?id=<?= $iss['id'] ?>" target="_blank" class="btn btn-outline btn-xs">🖨️ Slip</a>
              <?php endif; ?>
              <?php if ($iss['status'] === 'rejected' && $iss['rejection_reason']): ?>
                <span class="text-small text-danger" title="<?= e($iss['rejection_reason']) ?>" style="cursor:help;">⚠️ Reason</span>
              <?php endif; ?>
            </div>
          </td>
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
<?php endif; ?>

<!-- NEW ISSUE MODAL -->
<?php if (canDo('raise_request')): ?>
<div class="modal-overlay" id="newIssueModal">
  <div class="modal">
    <div class="modal-header">
      <h3>📤 New Issue Request</h3>
      <button class="modal-close" onclick="closeModal('newIssueModal')">✕</button>
    </div>
    <form method="post" action="">
      <?= csrfField() ?>
      <input type="hidden" name="_action" value="submit_issue">

      <div class="form-row">
        <div class="form-group">
          <label>Employee Name *</label>
          <input type="text" name="employee_name" value="<?= e($user['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Department *</label>
          <select name="department">
            <?php foreach (['Civil','Electrical','Project','Admin','Safety','Mechanical','Plumbing','HR'] as $d): ?>
            <option <?= $user['dept']===$d?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Product *</label>
        <select name="product_id" id="product_id" required onchange="checkStock()">
          <option value="">-- Select Product --</option>
          <?php foreach ($prodList as $p):
            $avail = (float)$p['current_stock'] - (float)$p['reserved_stock'];
          ?>
          <option value="<?= $p['id'] ?>" data-avail="<?= $avail ?>" data-unit="<?= e($p['unit']) ?>">
            <?= e($p['name']) ?> (<?= $avail ?> <?= e($p['unit']) ?> avail.)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Quantity Requested *</label>
          <input type="number" name="quantity_requested" id="quantity_requested" min="0.01" step="0.01" value="1" required oninput="checkStock()">
        </div>
        <div class="form-group">
          <label>Available Stock</label>
          <input type="text" id="available_stock" readonly>
        </div>
      </div>

      <div id="stock_warning" class="alert alert-warning" style="display:none;">
        ⚠️ Quantity exceeds available stock. Consider raising an Indent request instead.
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Required By Date *</label>
          <input type="date" name="required_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>Work Order / Reference</label>
          <input type="text" name="work_order_ref" placeholder="WO-XXXX or project name">
        </div>
      </div>

      <div class="form-group">
        <label>Purpose / Remarks *</label>
        <textarea name="purpose" rows="2" required placeholder="Describe the purpose of this request..."></textarea>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('newIssueModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Request</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($action === 'new'): ?>
<script>document.addEventListener('DOMContentLoaded', () => openModal('newIssueModal'));</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
