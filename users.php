<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole([ROLE_ADMIN]);

$pageTitle  = 'User Management';
$activePage = 'users';
$db         = getDB();
$user       = currentUser();

// ═══════════════════════════════════════════════════════════════
// POST ACTIONS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['_action'] ?? '';

    // ── CREATE USER ────────────────────────────────────────────
    if ($postAction === 'create_user') {
        $name    = trim($_POST['name'] ?? '');
        $empId   = trim($_POST['employee_id'] ?? '');
        $dept    = trim($_POST['department'] ?? '');
        $desig   = trim($_POST['designation'] ?? '');
        $role    = $_POST['role'] ?? ROLE_EMPLOYEE;
        $uname   = strtolower(trim($_POST['username'] ?? ''));
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = trim($_POST['phone'] ?? '');
        $pass    = $_POST['password'] ?? '';

        if (!$name || !$uname || !$pass || !$role) {
            setFlash('danger', 'Name, username, password and role are required.');
        } elseif (strlen($pass) < 8) {
            setFlash('danger', 'Password must be at least 8 characters.');
        } else {
            // Check duplicate username
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$uname]);
            if ($check->fetch()) {
                setFlash('danger', "Username '{$uname}' already exists.");
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("INSERT INTO users (employee_id, name, username, password, email, phone, department, designation, role) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$empId ?: null, $name, $uname, $hash, $email, $phone, $dept, $desig, $role]);
                $newId = (int)$db->lastInsertId();
                logAudit('CREATE USER', 'User Management', "Created user: {$name} ({$uname}) Role: {$role}", $newId);
                setFlash('success', "User '{$name}' created successfully.");
            }
        }
        header('Location: ' . BASE_URL . '/users.php'); exit;
    }

    // ── UPDATE USER ────────────────────────────────────────────
    if ($postAction === 'update_user') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $dept  = trim($_POST['department'] ?? '');
        $desig = trim($_POST['designation'] ?? '');
        $role  = $_POST['role'] ?? ROLE_EMPLOYEE;
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');

        if (!$id || !$name) { setFlash('danger', 'Invalid request.'); }
        else {
            // Prevent last admin from being demoted
            if ($id === $user['id'] && $role !== ROLE_ADMIN) {
                setFlash('danger', 'You cannot change your own role.');
            } else {
                $db->prepare("UPDATE users SET name=?, department=?, designation=?, role=?, email=?, phone=?, updated_at=NOW() WHERE id=?")
                   ->execute([$name, $dept, $desig, $role, $email, $phone, $id]);

                // Optional password reset
                $newPass = $_POST['new_password'] ?? '';
                if ($newPass) {
                    if (strlen($newPass) < 8) { setFlash('danger', 'New password must be at least 8 characters.'); header('Location: ' . BASE_URL . '/users.php'); exit; }
                    $db->prepare("UPDATE users SET password=?, password_changed_at=NOW() WHERE id=?")->execute([password_hash($newPass, PASSWORD_BCRYPT, ['cost'=>12]), $id]);
                }

                logAudit('UPDATE USER', 'User Management', "Updated user ID:{$id} — {$name}", $id);
                setFlash('success', "User '{$name}' updated.");
            }
        }
        header('Location: ' . BASE_URL . '/users.php'); exit;
    }

    // ── TOGGLE STATUS ──────────────────────────────────────────
    if ($postAction === 'toggle_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $newSt  = $_POST['new_status'] ?? 'inactive';
        if ($id === $user['id']) { setFlash('danger', 'You cannot disable your own account.'); }
        else {
            $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newSt, $id]);
            logAudit('STATUS', 'User Management', "User ID:{$id} status changed to {$newSt}", $id);
            setFlash('success', "User " . ($newSt==='active'?'enabled':'disabled') . " successfully.");
        }
        header('Location: ' . BASE_URL . '/users.php'); exit;
    }
}

// ── LIST ───────────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$roleF   = $_GET['role'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = ROWS_PER_PAGE;
$offset  = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($search) { $where[] = '(name LIKE ? OR username LIKE ? OR department LIKE ? OR employee_id LIKE ?)'; $s="%{$search}%"; $params[]=$s;$params[]=$s;$params[]=$s;$params[]=$s; }
if ($roleF)  { $where[] = 'role = ?'; $params[] = $roleF; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM users $whereSQL"); $total->execute($params); $total=(int)$total->fetchColumn();
$stmt  = $db->prepare("SELECT * FROM users $whereSQL ORDER BY name LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$perPage, $offset])); $users = $stmt->fetchAll();

$departments = ['Civil','Electrical','Project','Admin','Safety','Mechanical','Plumbing','HR','Store','Management'];
$roles = [ROLE_ADMIN=>'Store Admin', ROLE_KEEPER=>'Store Keeper', ROLE_EMPLOYEE=>'Employee', ROLE_MANAGEMENT=>'Management'];

// Edit target
$editUser = null;
if (isset($_GET['edit'])) {
    $s2 = $db->prepare("SELECT * FROM users WHERE id=?"); $s2->execute([(int)$_GET['edit']]); $editUser = $s2->fetch();
}

include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
  <span class="sep">›</span><span class="cur">User Management</span>
</div>

<div class="page-header">
  <h1>👥 User Management</h1>
  <button onclick="openModal('createUserModal')" class="btn btn-primary btn-sm">+ Add New User</button>
</div>

<!-- FILTERS -->
<div class="card mb-16"><div class="card-body" style="padding:12px 18px;">
  <form method="get"><div class="filter-bar">
    <div class="search-wrap"><span class="search-icon">🔍</span>
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, username, department...">
    </div>
    <select name="role" onchange="this.form.submit()">
      <option value="">All Roles</option>
      <?php foreach ($roles as $r => $lbl): ?><option value="<?= $r ?>" <?= $roleF===$r?'selected':'' ?>><?= $lbl ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    <?php if ($search || $roleF): ?><a href="<?= BASE_URL ?>/users.php" class="btn btn-outline btn-sm">✕ Clear</a><?php endif; ?>
  </div></form>
</div></div>

<div class="card">
  <div class="card-header"><h3>Users (<?= $total ?>)</h3></div>
  <div class="table-wrap">
    <?php if (empty($users)): ?>
      <div class="empty-state"><div class="empty-icon">👥</div><h4>No users found</h4></div>
    <?php else: ?>
    <table>
      <thead><tr><th>Employee</th><th>Emp. ID</th><th>Username</th><th>Department</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u):
          $initials = implode('', array_map(fn($w)=>strtoupper($w[0]??''), array_slice(explode(' ',$u['name']),0,2)));
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px;">
              <div class="user-avatar" style="background:var(--brand);flex-shrink:0;"><?= e($initials) ?></div>
              <div>
                <strong><?= e($u['name']) ?></strong>
                <?php if ($u['designation']): ?><div class="text-small text-muted"><?= e($u['designation']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td><span class="mono text-small"><?= e($u['employee_id'] ?: '—') ?></span></td>
          <td class="text-small"><?= e($u['username']) ?></td>
          <td><?= e($u['department'] ?: '—') ?></td>
          <td><?= roleBadge($u['role']) ?></td>
          <td><?= statusBadge($u['status']) ?></td>
          <td class="text-small text-muted"><?= $u['last_login'] ? formatDateTime($u['last_login']) : 'Never' ?></td>
          <td>
            <div class="btn-group">
              <button onclick='editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)' class="btn btn-outline btn-xs">✏️ Edit</button>
              <?php if ($u['id'] != $user['id']): ?>
              <form method="post" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="_action"    value="toggle_status">
                <input type="hidden" name="id"         value="<?= $u['id'] ?>">
                <input type="hidden" name="new_status" value="<?= $u['status']==='active'?'inactive':'active' ?>">
                <button type="submit" class="btn btn-outline btn-xs <?= $u['status']==='active'?'text-danger':'' ?>"
                        onclick="return confirm('Change user status?')">
                  <?= $u['status']==='active'?'🚫':'✅' ?>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($total > $perPage): ?><div class="card-footer"><?= paginate($total, $page, $perPage, BASE_URL.'/users.php?'.http_build_query(array_filter(['search'=>$search,'role'=>$roleF]))) ?></div><?php endif; ?>
</div>

<!-- ══ CREATE USER MODAL ══ -->
<div class="modal-overlay" id="createUserModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 id="userModalTitle">👤 Create New User</h3>
      <button class="modal-close" onclick="closeModal('createUserModal');closeModal('editUserModal')">✕</button>
    </div>
    <form method="post" action="" id="userForm">
      <?= csrfField() ?>
      <input type="hidden" name="_action" value="create_user" id="userFormAction">
      <input type="hidden" name="id"      value=""            id="userFormId">

      <div class="form-row">
        <div class="form-group"><label>Full Name *</label><input type="text" name="name" id="uf_name" required placeholder="Employee full name"></div>
        <div class="form-group"><label>Employee ID</label><input type="text" name="employee_id" id="uf_empid" placeholder="EMP-0001"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Department</label>
          <select name="department" id="uf_dept">
            <?php foreach ($departments as $d): ?><option><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Designation</label><input type="text" name="designation" id="uf_desig" placeholder="e.g. Site Engineer"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Role *</label>
          <select name="role" id="uf_role">
            <?php foreach ($roles as $r=>$lbl): ?><option value="<?= $r ?>"><?= $lbl ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="uf_phone" placeholder="+91 XXXXX XXXXX"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Username *</label><input type="text" name="username" id="uf_username" required placeholder="user@dmrconstruction.in"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" id="uf_email" placeholder="user@dmrconstruction.in"></div>
      </div>
      <div class="form-row">
        <div class="form-group" id="passGroup">
          <label id="passLabel">Password * (min 8 chars)</label>
          <input type="password" name="password" id="uf_pass" placeholder="Set strong password">
        </div>
        <div class="form-group" id="newPassGroup" style="display:none;">
          <label>New Password (leave blank to keep current)</label>
          <input type="password" name="new_password" id="uf_newpass" placeholder="Enter new password or leave blank">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('createUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="userFormSubmit">Create User</button>
      </div>
    </form>
  </div>
</div>

<script>
function editUser(u) {
  document.getElementById('userModalTitle').textContent  = '✏️ Edit User';
  document.getElementById('userFormAction').value        = 'update_user';
  document.getElementById('userFormId').value            = u.id;
  document.getElementById('uf_name').value               = u.name;
  document.getElementById('uf_empid').value              = u.employee_id || '';
  document.getElementById('uf_dept').value               = u.department  || '';
  document.getElementById('uf_desig').value              = u.designation || '';
  document.getElementById('uf_role').value               = u.role;
  document.getElementById('uf_phone').value              = u.phone || '';
  document.getElementById('uf_username').value           = u.username;
  document.getElementById('uf_username').readOnly        = true;
  document.getElementById('uf_email').value              = u.email || '';
  document.getElementById('passGroup').style.display     = 'none';
  document.getElementById('newPassGroup').style.display  = 'block';
  document.getElementById('userFormSubmit').textContent  = 'Update User';
  openModal('createUserModal');
}

// Reset modal on close
document.querySelector('.modal-close').addEventListener('click', function() {
  document.getElementById('userModalTitle').textContent  = '👤 Create New User';
  document.getElementById('userFormAction').value        = 'create_user';
  document.getElementById('userFormId').value            = '';
  document.getElementById('uf_username').readOnly        = false;
  document.getElementById('passGroup').style.display     = 'block';
  document.getElementById('newPassGroup').style.display  = 'none';
  document.getElementById('userFormSubmit').textContent  = 'Create User';
  document.getElementById('userForm').reset();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
