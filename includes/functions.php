<?php
/**
 * DMR Construction — Core Helper Functions
 */

// ── Output Sanitization ───────────────────────────────────────────────
function e(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Flash Messages ────────────────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $icons = ['success' => '✅', 'danger' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'];
    $icon = $icons[$flash['type']] ?? 'ℹ️';
    return '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible">'
         . $icon . ' ' . e($flash['message'])
         . '<button type="button" class="alert-close" onclick="this.parentElement.remove()">✕</button>'
         . '</div>';
}

// ── Auto-Numbering ────────────────────────────────────────────────────
function generateNumber(string $type): string {
    $db = getDB();
    $ownTransaction = !$db->inTransaction();
    if ($ownTransaction) {
        $db->beginTransaction();
    }
    try {
        $stmt = $db->prepare("SELECT prefix, current_value FROM counters WHERE name = ? FOR UPDATE");
        $stmt->execute([$type]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception("Unknown counter type: $type");
        $next = $row['current_value'] + 1;
        $db->prepare("UPDATE counters SET current_value = ? WHERE name = ?")->execute([$next, $type]);
        if ($ownTransaction) {
            $db->commit();
        }
        return $row['prefix'] . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        if ($ownTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

// ── Audit Logging ─────────────────────────────────────────────────────
function logAudit(string $action, string $module, string $details, ?int $recordId = null): void {
    try {
        $db  = getDB();
        $uid = $_SESSION['user_id']   ?? null;
        $db->prepare("INSERT INTO audit_log (user_id, user_name, user_role, action, module, record_id, details, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([
               $uid,
               $_SESSION['user_name'] ?? 'System',
               $_SESSION['user_role'] ?? 'system',
               strtoupper($action),
               $module,
               $recordId,
               $details,
               $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
               substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
           ]);
    } catch (Throwable $e) {
        // Audit failure should NOT stop the main transaction
        error_log("Audit log error: " . $e->getMessage());
    }
}

// ── Notifications ─────────────────────────────────────────────────────
function sendNotification(int $userId, string $title, string $body, string $type = 'info', string $link = ''): void {
    try {
        getDB()->prepare("INSERT INTO notifications (user_id, title, body, type, link) VALUES (?,?,?,?,?)")
               ->execute([$userId, $title, $body, $type, $link]);
    } catch (Throwable $e) {
        error_log("Notification error: " . $e->getMessage());
    }
}

function notifyAdminsAndKeepers(string $title, string $body, string $type = 'info', string $link = ''): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE role IN ('admin','keeper') AND status = 'active'");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $u) {
        sendNotification($u['id'], $title, $body, $type, $link);
    }
}

function getUnreadNotifCount(int $userId): int {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getRecentNotifications(int $userId, int $limit = 8): array {
    $stmt = getDB()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

// ── Stock Helpers ─────────────────────────────────────────────────────
function getProductById(int $id): ?array {
    $stmt = getDB()->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getAvailableStock(int $productId): float {
    $p = getProductById($productId);
    return $p ? (float)$p['current_stock'] - (float)$p['reserved_stock'] : 0;
}

function recordStockTransaction(
    int $productId, string $type, float $qty,
    string $refNumber, string $refType,
    string $supplier = '', string $invoiceNo = '', string $remarks = ''
): int {
    $db = getDB();
    $p  = getProductById($productId);
    if (!$p) throw new Exception("Product not found: $productId");

    $balBefore = (float)$p['current_stock'];
    $isOut = in_array($type, ['stock_out', 'adjustment_out']);
    $balAfter = $isOut ? $balBefore - $qty : $balBefore + $qty;

    if ($balAfter < 0) throw new Exception("Insufficient stock. Available: {$balBefore}");

    $txnNumber = generateNumber('txn');
    $db->prepare("INSERT INTO stock_transactions (txn_number, product_id, type, quantity, balance_before, balance_after, reference_number, reference_type, supplier, invoice_number, remarks, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$txnNumber, $productId, $type, $qty, $balBefore, $balAfter, $refNumber, $refType, $supplier, $invoiceNo, $remarks, $_SESSION['user_id'] ?? null]);

    $txnId = (int)$db->lastInsertId();
    $db->prepare("UPDATE products SET current_stock = ? WHERE id = ?")->execute([$balAfter, $productId]);

    // Low stock notification
    $p = getProductById($productId);
    if ($p && (float)$p['current_stock'] <= (float)$p['min_stock_level']) {
        $level = (float)$p['current_stock'] < (float)$p['min_stock_level'] * 0.3 ? 'CRITICAL' : 'LOW';
        notifyAdminsAndKeepers(
            "⚠️ {$level} Stock: {$p['name']}",
            "{$p['name']} has only {$p['current_stock']} {$p['unit']} (Min: {$p['min_stock_level']})",
            $level === 'CRITICAL' ? 'danger' : 'warning',
            'inventory.php'
        );
    }

    return $txnId;
}

// ── Date / Format Helpers ─────────────────────────────────────────────
function formatDate(string $dt, string $format = 'd M Y'): string {
    if (!$dt || $dt === '0000-00-00') return '—';
    return date($format, strtotime($dt));
}

function formatDateTime(string $dt): string {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '—';
    return date('d M Y, h:i A', strtotime($dt));
}

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . ' mins ago';
    if ($diff < 86400)  return floor($diff/3600) . ' hours ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return formatDate($dt);
}

// ── Badge Helpers ─────────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'pending'          => ['badge-pending',  'Pending'],
        'approved'         => ['badge-success',  'Approved'],
        'rejected'         => ['badge-danger',   'Rejected'],
        'issued'           => ['badge-success',  'Issued'],
        'converted'        => ['badge-info',     'Converted'],
        'partially_issued' => ['badge-warning',  'Partial'],
        'active'           => ['badge-success',  'Active'],
        'inactive'         => ['badge-gray',     'Inactive'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-gray', ucfirst($status)];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

function roleBadge(string $role): string {
    $map = [
        'admin'      => ['badge-danger',  'Store Admin'],
        'keeper'     => ['badge-info',    'Store Keeper'],
        'employee'   => ['badge-gray',    'Employee'],
        'management' => ['badge-success', 'Management'],
    ];
    [$cls, $label] = $map[$role] ?? ['badge-gray', ucfirst($role)];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

function stockBadge(float $current, float $min): string {
    if ($current <= $min * 0.3) return '<span class="badge badge-danger">Critical</span>';
    if ($current <= $min)       return '<span class="badge badge-warning">Low Stock</span>';
    return '<span class="badge badge-success">Good</span>';
}

function priorityBadge(string $p): string {
    $map = ['normal' => ['badge-gray', 'Normal'], 'urgent' => ['badge-warning', 'Urgent'], 'critical' => ['badge-danger', 'Critical']];
    [$cls, $label] = $map[$p] ?? ['badge-gray', ucfirst($p)];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

// ── Pagination ────────────────────────────────────────────────────────
function paginate(int $total, int $current, int $perPage, string $url): string {
    $pages = ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    $sep  = str_contains($url, '?') ? '&' : '?';
    if ($current > 1)
        $html .= "<a href=\"{$url}{$sep}page=" . ($current-1) . "\" class=\"page-btn\">‹ Prev</a>";
    for ($i = max(1,$current-2); $i <= min($pages,$current+2); $i++) {
        $active = $i === $current ? ' active' : '';
        $html .= "<a href=\"{$url}{$sep}page={$i}\" class=\"page-btn{$active}\">{$i}</a>";
    }
    if ($current < $pages)
        $html .= "<a href=\"{$url}{$sep}page=" . ($current+1) . "\" class=\"page-btn\">Next ›</a>";
    $html .= "<span class=\"page-info\">{$current} / {$pages}</span></div>";
    return $html;
}

// ── CSRF Field ────────────────────────────────────────────────────────
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}
