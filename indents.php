<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle  = 'Indent Requests';
$activePage = 'indents';
$db         = getDB();
$user       = currentUser();
$action     = $_GET['action'] ?? 'list';

// ═══════════════════════════════════════════════════════════════
// POST ACTIONS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['_action'] ?? '';

    if ($postAction === 'submit_indent') {
        $empName  = trim($_POST['employee_name'] ?? $user['name']);
        $dept     = trim($_POST['department'] ?? $user['dept']);
        $itemName = trim($_POST['item_name'] ?? '');
        $qty      = (float)($_POST['quantity'] ?? 0);
        $unit     = trim($_POST['unit'] ?? 'Nos');
        $catId    = (int)($_POST['category_id'] ?? 0);
        $purpose  = trim($_POST['purpose'] ?? '');
        $reqDate  = $_POST['required_date'] ?? date('Y-m-d');
        $priority = $_POST['priority'] ?? 'normal';

        if (!$itemName || $qty <= 0) { setFlash('danger', 'Item name and quantity are required.'); }
        else {
            $indNo = generateNumber('indent');
            $db->prepare("INSERT INTO indents (indent_number, employee_id, employee_name, department, item_name, quantity, unit, category_id, purpose, required_date, priority, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending')")
               ->execute([$indNo, $user['id'], $empName, $dept, $itemName, $qty, $unit, $catId ?: null, $purpose, $reqDate, $priority]);
            $newId = (int)$db->lastInsertId();
            notifyAdminsAndKeepers("📋 New Indent {$indNo}", "{$empName} requested {$qty} {$unit} of {$itemName}", 'info', 'indents.php');
            logAudit('REQUEST', 'Indent', "{$indNo} by {$empName} for {$itemName} ×{$qty}", $newId);
            setFlash('success', "Indent {$indNo} submitted for approval.");
        }
        header('Location: ' . BASE_URL . '/indents.php'); exit;
    }

    if ($postAction === 'approve_indent') {
        requireRole([ROLE_ADMIN, ROLE_KEEPER]);
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM indents WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]); $req = $stmt->fetch();
        if (!$req) { setFlash('danger', 'Indent not found or already processed.'); header('Location: ' . BASE_URL . '/indents.php'); exit; }
        $db->prepare("UPDATE indents SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$user['id'], $id]);
        if ($req['employee_id']) sendNotification($req['employee_id'], "✅ Indent Approved", "{$req['indent_number']} for {$req['item_name']} has been approved.", 'success', 'indents.php');
        logAudit('APPROVE', 'Indent', "Approved {$req['indent_number']} — {$req['item_name']}", $id);
        setFlash('success', "Indent {$req['indent_number']} approved successfully.");
        header('Location: ' . BASE_URL . '/indents.php'); exit;
    }

    if ($postAction === 'reject_indent') {
        requireRole([ROLE_ADMIN, ROLE_KEEPER]);
        $id     = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (!$reason) { setFlash('danger', 'Rejection reason is mandatory.'); header('Location: ' . BASE_URL . '/indents.php?action=review&id='.$id); exit; }
        $stmt = $db->prepare("SELECT * FROM indents WHERE id=? AND status='pending'"); $stmt->execute([$id]); $req = $stmt->fetch();
        if (!$req) { setFlash('danger', 'Indent not found.'); header('Location: ' . BASE_URL . '/indents.php'); exit; }
        $db->prepare("UPDATE indents SET status='rejected', rejection_reason=?, approved_by=?, approved_at=NOW() WHERE id=?")->execute([$reason, $user['id'], $id]);
        if ($req['employee_id']) sendNotification($req['employee_id'], "❌ Indent Rejected", "{$req['indent_number']} rejected. Reason: {$reason}", 'danger', 'indents.php');
        logAudit('REJECT', 'Indent', "Rejected {$req['indent_number']} — Reason: {$reason}", $id);
        setFlash('danger', "Indent {$req['indent_number']} rejected.");
        header('Location: ' . BASE_URL . '/indents.php'); exit;
    }
}

// ── REVIEW ────────────────────────────────────────────────────
$reviewReq = null;
if ($action === 'review' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT i.*, c.name AS cat_name, u.name AS approved_by_name FROM indents i LEFT JOIN categories c ON i.category_id=c.id LEFT JOIN users u ON i.approved_by=u.id WHERE i.id=?");
    $stmt->execute([(int)$_GET['id']]); $reviewReq = $stmt->fetch();
}

// ── LIST ──────────────────────────────────────────────────────
$status  = $_GET['status'] ?? '';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = ROWS_PER_PAGE;
$offset  = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($user['role'] === ROLE_EMPLOYEE) { $where[] = 'i.employee_id = ?'; $params[] = $user['id']; }
if ($status) { $where[] = 'i.status = ?'; $params[] = $status; }
if ($search) { $where[] = '(i.indent_number LIKE ? OR i.employee_name LIKE ? OR i.item_name LIKE ?)'; $s="%{$search}%"; $params[]=$s; $params[]=$s; $params[]=$s; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM indents i $whereSQL"); $total->execute($params); $total=(int)$total->fetchColumn();
$stmt  = $db->prepare("SELECT i.*, c.name AS cat_name FROM indents i LEFT JOIN categories c ON i.category_id=c.id $whereSQL ORDER BY i.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset])); $indents = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$units = ['Nos','Kg','Gram','Ton','Meter','CM','Liter','ML','Box','Bag','Ream','Roll','Set','Pair','Cum','Sqm'];

$statusCounts = [];
foreach (['','pending','approved','rejected'] as $s) {
    $sq = $db->prepare("SELECT COUNT(*) FROM indents i WHERE " . ($user['role']===ROLE_EMPLOYEE?"i.employee_id={$user['id']} AND ":"") . ($s?"i.status='$s'":"1=1"));
    $sq->execute(); $statusCounts[$s]=(int)$sq->fetchColumn();
}

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
  <span class="sep">›</span><span class="cur">Indent Requests</span>
</div>

<?php if ($reviewReq && $action === 'review'): ?>
<!-- REVIEW -->
<div class="page-header">
  <h1>📋 Review Indent</h1>
  <a href="<?= BASE_URL ?>/indents.php" class="btn btn-outline btn-sm">← Back</a>
</div>
<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3>Indent Details</h3></div>
    <div class="card-body">
      <div class="detail-panel">
        <div class="detail-row">
          <div class="detail-item"><div class="di-label">Indent #</div><div class="di-val"><?= e($reviewReq['indent_number']) ?></div></div>
          <div class="detail-item"><div class="di-label">Status</div><div class="di-val"><?= statusBadge($reviewReq['status']) ?></div></div>
          <div class="detail-item"><div class="di-label">Priority</div><div class="di-val"><?= priorityBadge($reviewReq['priority']) ?></div></div>
          <div class="detail-item"><div class="di-label">Employee</div><div class="di-val"><?= e($reviewReq['employee_name']) ?></div></div>
          <div class="detail-item"><div class="di-label">Department</div><div class="di-val"><?= e($reviewReq['department']) ?></div></div>
          <div class="detail-item"><div class="di-label">Item Name</div><div class="di-val"><?= e($reviewReq['item_name']) ?></div></div>
          <div class="detail-item"><div class="di-label">Quantity</div><div class="di-val"><?= e($reviewReq['quantity']) ?> <?= e($reviewReq['unit']) ?></div></div>
          <div class="detail-item"><div class="di-label">Category</div><div class="di-val"><?= e($reviewReq['cat_name'] ?: '—') ?></div></div>
          <div class="detail-item"><div class="di-label">Required By</div><div class="di-val"><?= formatDate($reviewReq['required_date']) ?></div></div>
          <div class="detail-item"><div class="di-label">Submitted</div><div class="di-val"><?= formatDateTime($reviewReq['created_at']) ?></div></div>
        </div>
        <?php if ($reviewReq['purpose']): ?>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--g200);">
          <div style="font-size:11px;font-weight:700;color:var(--g600);text-transform:uppercase;margin-bottom:4px;">Purpose / Justification</div>
          <div><?= e($reviewReq['purpose']) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($reviewReq['status'] === 'rejected'): ?>
        <div class="alert alert-danger mt-16">❌ Rejected: <?= e($reviewReq['rejection_reason']) ?></div>
      <?php elseif ($reviewReq['status'] === 'approved'): ?>
        <div class="alert alert-success mt-16">✅ Approved by <?= e($reviewReq['approved_by_name']) ?> on <?= formatDateTime($reviewReq['approved_at']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($reviewReq['status'] === 'pending' && canDo('approve_indent')): ?>
  <div class="card">
    <div class="card-header"><h3>Take Action</h3></div>
    <div class="card-body">
      <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="approve_indent">
        <input type="hidden" name="id" value="<?= $reviewReq['id'] ?>">
        <p style="margin-bottom:16px;color:var(--g600);font-size:13px;">Review the indent request and approve or reject it. Approved indents will be forwarded for purchase.</p>
        <button type="submit" class="btn btn-success btn-full" style="margin-bottom:10px;">✅ Approve Indent</button>
      </form>
      <hr>
      <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="reject_indent">
        <input type="hidden" name="id" value="<?= $reviewReq['id'] ?>">
        <div class="form-group">
          <label>Rejection Reason (Mandatory)</label>
          <textarea name="rejection_reason" required placeholder="Provide reason for rejection..."></textarea>
        </div>
        <button type="submit" class="btn btn-danger btn-full" onclick="return confirm('Reject this indent?')">❌ Reject Indent</button>
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="card"><div class="card-body"><div class="empty-state"><div>📋</div><h4>Already Processed</h4></div></div></div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- LIST -->
<div class="page-header">
  <h1>📋 Indent Requests</h1>
  <?php if (canDo('raise_request')): ?>
  <button onclick="openModal('newIndentModal')" class="btn btn-primary btn-sm">+ New Indent</button>
  <?php endif; ?>
</div>

<div class="tabs mb-16">
  <?php foreach ([''  => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'converted' => 'Converted'] as $s => $lbl): ?>
  <a href="<?= BASE_URL ?>/indents.php?status=<?= $s ?>" class="tab <?= $status===$s?'active':'' ?>">
    <?= $lbl ?>
    <?php if ($statusCounts[$s] > 0): ?><span class="badge badge-gray" style="margin-left:4px;"><?= $statusCounts[$s] ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card mb-16"><div class="card-body" style="padding:12px 18px;">
  <form method="get"><div class="filter-bar">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" name="search" value="<?= e($search) ?>" placeholder="Search..."></div>
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    <?php if ($search): ?><a href="<?= BASE_URL ?>/indents.php<?= $status?'?status='.$status:'' ?>" class="btn btn-outline btn-sm">✕</a><?php endif; ?>
  </div></form>
</div></div>

<div class="card">
  <div class="card-header"><h3>Indents (<?= $total ?>)</h3></div>
  <div class="table-wrap">
    <?php if (empty($indents)): ?>
      <div class="empty-state"><div class="empty-icon">📋</div><h4>No indents found</h4></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Indent #</th><th>Employee</th><th>Dept</th><th>Item</th><th>Qty</th><th>Priority</th><th>Req. Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($indents as $ind): ?>
        <tr>
          <td><strong class="mono"><?= e($ind['indent_number']) ?></strong></td>
          <td><?= e($ind['employee_name']) ?></td>
          <td><?= e($ind['department']) ?></td>
          <td>
            <strong><?= e($ind['item_name']) ?></strong>
            <?php if ($ind['cat_name']): ?><div class="text-small text-muted"><?= e($ind['cat_name']) ?></div><?php endif; ?>
          </td>
          <td><?= e($ind['quantity']) ?> <?= e($ind['unit']) ?></td>
          <td><?= priorityBadge($ind['priority']) ?></td>
          <td class="text-small"><?= formatDate($ind['required_date']) ?></td>
          <td><?= statusBadge($ind['status']) ?></td>
          <td>
            <div class="btn-group">
              <?php if ($ind['status'] === 'pending' && canDo('approve_indent')): ?>
                <a href="<?= BASE_URL ?>/indents.php?action=review&id=<?= $ind['id'] ?>" class="btn btn-success btn-xs">Review</a>
              <?php elseif ($ind['status'] === 'rejected'): ?>
                <span class="text-small text-danger" title="<?= e($ind['rejection_reason']) ?>" style="cursor:help;">⚠️ Reason</span>
              <?php else: ?>
                <a href="<?= BASE_URL ?>/indents.php?action=review&id=<?= $ind['id'] ?>" class="btn btn-outline btn-xs">View</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($total > $perPage): ?><div class="card-footer"><?= paginate($total, $page, $perPage, BASE_URL.'/indents.php?'.http_build_query(array_filter(['search'=>$search,'status'=>$status]))) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<!-- NEW INDENT MODAL -->
<?php if (canDo('raise_request')): ?>
<div class="modal-overlay" id="newIndentModal">
  <div class="modal">
    <div class="modal-header">
      <h3>📋 New Indent Request</h3>
      <button class="modal-close" onclick="closeModal('newIndentModal')">✕</button>
    </div>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="_action" value="submit_indent">
      <div class="form-row">
        <div class="form-group"><label>Employee Name *</label><input type="text" name="employee_name" value="<?= e($user['name']) ?>" required></div>
        <div class="form-group"><label>Department *</label>
          <select name="department"><?php foreach (['Civil','Electrical','Project','Admin','Safety','Mechanical','Plumbing','HR'] as $d): ?><option <?= $user['dept']===$d?'selected':'' ?>><?= $d ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="form-group"><label>Item Name *</label><input type="text" name="item_name" required placeholder="Name of required item"></div>
      <div class="form-row">
        <div class="form-group"><label>Quantity *</label><input type="number" name="quantity" min="0.01" step="0.01" value="1" required></div>
        <div class="form-group"><label>Unit</label><select name="unit"><?php foreach ($units as $u): ?><option><?= $u ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Category</label>
          <select name="category_id"><option value="">-- Select Category --</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Priority</label>
          <select name="priority"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="critical">Critical</option></select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Required By Date</label><input type="date" name="required_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></div>
      </div>
      <div class="form-group"><label>Purpose / Justification *</label><textarea name="purpose" rows="2" required placeholder="Detailed justification for this indent..."></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('newIndentModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Indent</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php if ($action==='new'): ?><script>document.addEventListener('DOMContentLoaded',()=>openModal('newIndentModal'));</script><?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
