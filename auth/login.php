<?php
require_once __DIR__ . '/../config/config.php';

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error   = '';
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID on login
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_name']     = $user['name'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['user_dept']     = $user['department'];
            $_SESSION['last_activity'] = time();

            // Update last_login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            logAudit('LOGIN', 'System', 'User logged in successfully');

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            logAudit('FAILED_LOGIN', 'System', "Failed login attempt for: {$username}");
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — DMR Store Management System</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-screen">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-logo-icon">DMR</div>
      <h1>DMR Construction</h1>
      <p>Store Management System</p>
    </div>

    <?php if ($timeout): ?>
      <div class="alert alert-warning mb-16">⏱️ Session expired. Please log in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger mb-16">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               placeholder="username@dmrconstruction.in" required autofocus>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter your password" required>
      </div>

      <button type="submit" class="btn btn-primary btn-full" style="margin-top:6px;">
        🔐 Sign In
      </button>
    </form>

    <div class="login-divider"></div>
    <p style="text-align:center;font-size:10px;color:#BDC1C6;margin-top:18px;">
      <?= COMPANY ?> · v<?= APP_VERSION ?>
    </p>
  </div>
</div>
</body>
</html>
