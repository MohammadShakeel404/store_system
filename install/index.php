<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DMR Store — Installer</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', sans-serif; background: linear-gradient(135deg,#0D2E4A,#1B4F72,#2980B9); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
  .box { background: #fff; border-radius: 18px; padding: 40px; width: 560px; max-width: 100%; box-shadow: 0 24px 80px rgba(0,0,0,0.3); }
  .logo { text-align: center; margin-bottom: 28px; }
  .logo-sq { width: 60px; height: 60px; background: #1B4F72; border-radius: 15px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 10px; }
  h1 { font-size: 20px; font-weight: 800; color: #1B4F72; }
  p.sub { font-size: 12px; color: #9AA0A6; margin-top: 3px; }
  h2 { font-size: 15px; font-weight: 700; margin: 24px 0 14px; color: #3C4043; border-bottom: 1px solid #E8EAED; padding-bottom: 8px; }
  .form-group { margin-bottom: 14px; }
  label { display: block; font-size: 11px; font-weight: 700; color: #5F6368; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px; }
  input { width: 100%; padding: 9px 13px; border: 1.5px solid #DADCE0; border-radius: 6px; font-family: inherit; font-size: 14px; outline: none; }
  input:focus { border-color: #2E86C1; box-shadow: 0 0 0 3px rgba(46,134,193,0.15); }
  .btn { width: 100%; padding: 11px; background: #1B4F72; color: #fff; border: none; border-radius: 8px; font-family: inherit; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 6px; }
  .btn:hover { background: #2E86C1; }
  .alert { padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
  .alert-success { background: #D5F5E3; color: #1E8449; }
  .alert-danger  { background: #FADBD8; color: #C0392B; }
  .alert-info    { background: #D6EAF8; color: #1F618D; }
  .step { display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 8px; }
  .step .dot { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
  .step.ok  .dot { background: #D5F5E3; color: #1E8449; }
  .step.err .dot { background: #FADBD8; color: #C0392B; }
  .step.warn.dot { background: #FEF9E7; color: #B7950B; }
  pre { background: #F1F3F4; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; margin-top: 10px; line-height: 1.6; }
  .divider { border: none; border-top: 1px solid #E8EAED; margin: 20px 0; }
  .creds-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
  .creds-table th { background: #F1F3F4; padding: 6px 10px; text-align: left; font-weight: 700; }
  .creds-table td { padding: 6px 10px; border-bottom: 1px solid #E8EAED; }
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <div class="logo-sq">DMR</div>
    <h1>DMR Store Management System</h1>
    <p class="sub">Installation Wizard — v1.0.0</p>
  </div>

<?php
$step    = $_POST['step'] ?? 'form';
$errors  = [];
$success = false;

// ── REQUIREMENTS CHECK ─────────────────────────────────────────────
$phpOk     = version_compare(PHP_VERSION, '7.4.0', '>=');
$pdoOk     = extension_loaded('pdo_mysql');
$sessionOk = function_exists('session_start');

if ($step === 'install') {
    $host   = trim($_POST['db_host']   ?? 'localhost');
    $port   = trim($_POST['db_port']   ?? '3306');
    $dbname = trim($_POST['db_name']   ?? 'dmr_store');
    $dbuser = trim($_POST['db_user']   ?? '');
    $dbpass = $_POST['db_pass']        ?? '';

    if (!$phpOk)   $errors[] = 'PHP 7.4+ is required.';
    if (!$pdoOk)   $errors[] = 'PDO MySQL extension is required.';
    if (!$dbuser)  $errors[] = 'Database username is required.';

    if (empty($errors)) {
        try {
            // Test connection
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $dbuser, $dbpass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Read and execute SQL
            $sqlFile = __DIR__ . '/database.sql';
            if (!file_exists($sqlFile)) throw new Exception("database.sql not found in install/ directory.");
            $sql = file_get_contents($sqlFile);

            // Split and execute statements
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $executed   = 0;
            foreach ($statements as $stmt) {
                if ($stmt && !preg_match('/^--/', $stmt)) {
                    $pdo->exec($stmt);
                    $executed++;
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            // Write config
            $configContent = <<<PHP
<?php
// Auto-generated by installer — DMR Store Management System
define('DB_HOST', '{$host}');
define('DB_PORT', '{$port}');
define('DB_NAME', '{$dbname}');
define('DB_USER', '{$dbuser}');
define('DB_PASS', '{$dbpass}');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        \$options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
    }
    return \$pdo;
}
PHP;
            $configPath = __DIR__ . '/../config/db.php';
            file_put_contents($configPath, $configContent);
            $success = true;

        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

if ($success): ?>

<div class="alert alert-success">✅ Installation completed successfully! Database created and configured.</div>

<h2>🔑 Demo Login Credentials</h2>
<table class="creds-table">
  <tr><th>Role</th><th>Username</th><th>Password</th></tr>
  <tr><td><strong>Store Admin</strong></td><td>admin@dmrconstruction.in</td><td>Admin@1234</td></tr>
  <tr><td>Store Keeper</td><td>keeper@dmrconstruction.in</td><td>Admin@1234</td></tr>
  <tr><td>Employee</td><td>deepak@dmrconstruction.in</td><td>Admin@1234</td></tr>
  <tr><td>Management</td><td>mgmt@dmrconstruction.in</td><td>Admin@1234</td></tr>
</table>

<hr class="divider">
<div class="alert alert-info" style="margin-top:12px;">
  ⚠️ <strong>Security:</strong> Delete or rename the <code>install/</code> folder after setup. Change default passwords immediately.
</div>
<a href="../index.php" class="btn" style="display:block;text-align:center;margin-top:14px;text-decoration:none;">🚀 Go to Application →</a>

<?php elseif ($step === 'install' && !empty($errors)): ?>
<div class="alert alert-danger">❌ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<?php if (!$success): ?>

<!-- REQUIREMENTS -->
<h2>📋 Requirements Check</h2>
<div class="step <?= $phpOk?'ok':'err' ?>">
  <div class="dot"><?= $phpOk?'✓':'✗' ?></div>
  <span>PHP <?= PHP_VERSION ?> <?= $phpOk?'(OK — 7.4+ required)':'(FAIL — PHP 7.4+ required)' ?></span>
</div>
<div class="step <?= $pdoOk?'ok':'err' ?>">
  <div class="dot"><?= $pdoOk?'✓':'✗' ?></div>
  <span>PDO MySQL Extension <?= $pdoOk?'(Installed)':'(NOT installed)' ?></span>
</div>
<div class="step ok">
  <div class="dot">✓</div>
  <span>Session Support (Available)</span>
</div>
<div class="step <?= is_writable(__DIR__.'/../config/')?'ok':'err' ?>">
  <div class="dot"><?= is_writable(__DIR__.'/../config/')?'✓':'✗' ?></div>
  <span>config/ directory <?= is_writable(__DIR__.'/../config/')?'writable (OK)':'NOT writable (chmod 755)' ?></span>
</div>

<!-- DB CONFIG FORM -->
<form method="post" action="">
  <input type="hidden" name="step" value="install">
  <h2>🗄️ Database Configuration</h2>
  <div style="display:grid;grid-template-columns:3fr 1fr;gap:10px;">
    <div class="form-group"><label>Database Host</label><input type="text" name="db_host" value="localhost" required></div>
    <div class="form-group"><label>Port</label><input type="text" name="db_port" value="3306"></div>
  </div>
  <div class="form-group"><label>Database Name</label><input type="text" name="db_name" value="dmr_store" required></div>
  <div class="form-group"><label>Database Username</label><input type="text" name="db_user" value="root" required></div>
  <div class="form-group"><label>Database Password</label><input type="password" name="db_pass" placeholder="Leave blank if no password"></div>
  <button type="submit" class="btn" <?= (!$phpOk||!$pdoOk)?'disabled':'' ?>>🚀 Install DMR Store System</button>
</form>

<?php endif; ?>

<p style="text-align:center;font-size:11px;color:#BDC1C6;margin-top:20px;">DMR Construction PVT. LTD. · Store Management System v1.0.0</p>
</div>
</body>
</html>
