<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();
if (!canDo('view_reports')) { http_response_code(403); die('Access denied.'); }

$db     = getDB();
$type   = $_GET['type']   ?? '';
$format = $_GET['format'] ?? 'csv'; // csv | html
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$dept   = $_GET['dept']   ?? '';

logAudit('EXPORT', 'Reports', "Exported {$type} as {$format} [{$from} to {$to}]");

$rows    = [];
$headers = [];
$title   = '';

switch ($type) {

    case 'daily_issues':
        $title   = 'Daily Issue Report';
        $headers = ['Issue #','Date','Employee','Department','Product','Qty Requested','Qty Issued','Work Order','Issued By','Status'];
        $q       = "SELECT ir.issue_number, ir.created_at, ir.employee_name, ir.department, p.name, ir.quantity_requested, COALESCE(ir.quantity_issued,0), COALESCE(ir.work_order_ref,''), COALESCE(u.name,''), ir.status
                    FROM issue_requests ir JOIN products p ON ir.product_id=p.id LEFT JOIN users u ON ir.issued_by=u.id
                    WHERE DATE(ir.created_at) BETWEEN ? AND ?";
        $params  = [$from, $to];
        if ($dept) { $q .= " AND ir.department = ?"; $params[] = $dept; }
        $q      .= " ORDER BY ir.created_at DESC";
        $stmt    = $db->prepare($q); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'stock_summary':
        $title   = 'Stock Summary Report';
        $headers = ['Product Code','Product Name','Category','Unit','Current Stock','Reserved','Available','Min Level','Location','Supplier','Status'];
        $stmt    = $db->query(
            "SELECT p.product_code, p.name, c.name, p.unit, p.current_stock, p.reserved_stock,
                    (p.current_stock - p.reserved_stock), p.min_stock_level, COALESCE(p.rack_location,''),
                    COALESCE(p.supplier_name,''),
                    CASE WHEN p.current_stock <= p.min_stock_level*0.3 THEN 'Critical'
                         WHEN p.current_stock <= p.min_stock_level THEN 'Low'
                         ELSE 'Good' END
             FROM products p LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.status='active' ORDER BY p.name"
        ); $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'low_stock':
        $title   = 'Low Stock Alert Report';
        $headers = ['Product Code','Product Name','Category','Unit','Current Stock','Min Level','Available','Supplier','Location','Alert Level'];
        $stmt    = $db->query(
            "SELECT p.product_code, p.name, c.name, p.unit, p.current_stock, p.min_stock_level,
                    (p.current_stock - p.reserved_stock), COALESCE(p.supplier_name,''), COALESCE(p.rack_location,''),
                    CASE WHEN p.current_stock <= p.min_stock_level*0.3 THEN 'CRITICAL' ELSE 'LOW' END
             FROM products p LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.current_stock <= p.min_stock_level AND p.status='active'
             ORDER BY (p.current_stock / NULLIF(p.min_stock_level,0)) ASC"
        ); $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'emp_history':
        $title   = 'Employee Issue History';
        $headers = ['Employee','Department','Total Requests','Total Qty Issued','Last Request Date'];
        $q       = "SELECT ir.employee_name, ir.department, COUNT(*), SUM(ir.quantity_issued), MAX(DATE(ir.issued_at))
                    FROM issue_requests ir WHERE ir.status='issued' AND DATE(ir.issued_at) BETWEEN ? AND ?";
        $params  = [$from, $to];
        if ($dept) { $q .= " AND ir.department = ?"; $params[] = $dept; }
        $q      .= " GROUP BY ir.employee_name, ir.department ORDER BY COUNT(*) DESC";
        $stmt    = $db->prepare($q); $stmt->execute($params); $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'category_stock':
        $title   = 'Category-wise Stock Report';
        $headers = ['Category','Total Products','Total Stock','Low Stock Items','Critical Items'];
        $stmt    = $db->query(
            "SELECT c.name, COUNT(p.id),
                    COALESCE(SUM(p.current_stock),0),
                    SUM(CASE WHEN p.current_stock <= p.min_stock_level THEN 1 ELSE 0 END),
                    SUM(CASE WHEN p.current_stock <= p.min_stock_level*0.3 THEN 1 ELSE 0 END)
             FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.status='active'
             GROUP BY c.id ORDER BY c.name"
        ); $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'stock_ledger':
        $title   = 'Stock Movement Ledger';
        $headers = ['TXN Number','Date','Product','Type','Qty In','Qty Out','Balance Before','Balance After','Reference','Invoice','Remarks','Done By'];
        $q       = "SELECT st.txn_number, st.created_at, p.name,
                    CASE st.type WHEN 'stock_in' THEN 'Stock In' WHEN 'stock_out' THEN 'Stock Out'
                    WHEN 'adjustment_in' THEN 'Adjustment In' WHEN 'adjustment_out' THEN 'Adjustment Out' ELSE 'Return' END,
                    CASE WHEN st.type IN ('stock_in','adjustment_in','return') THEN st.quantity ELSE 0 END,
                    CASE WHEN st.type IN ('stock_out','adjustment_out') THEN st.quantity ELSE 0 END,
                    st.balance_before, st.balance_after,
                    COALESCE(st.reference_number,''), COALESCE(st.invoice_number,''),
                    COALESCE(st.remarks,''), COALESCE(u.name,'System')
                    FROM stock_transactions st JOIN products p ON st.product_id=p.id LEFT JOIN users u ON st.created_by=u.id
                    WHERE DATE(st.created_at) BETWEEN ? AND ? ORDER BY st.created_at DESC";
        $stmt    = $db->prepare($q); $stmt->execute([$from, $to]); $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    case 'audit_log':
        $title   = 'Audit Log Export';
        $headers = ['Timestamp','User','Role','Action','Module','Details','IP Address'];
        $stmt    = $db->prepare("SELECT created_at, user_name, user_role, action, module, details, ip_address FROM audit_log WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
        $stmt->execute([$from, $to]); $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        break;

    default:
        die('Invalid report type.');
}

$filename = $type . '_' . $from . '_to_' . $to;

// ═══════════════════════════════════════════════════════════════
// CSV OUTPUT
// ═══════════════════════════════════════════════════════════════
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

    $out = fopen('php://output', 'w');
    // Report header
    fputcsv($out, [COMPANY . ' — ' . $title]);
    fputcsv($out, ['Generated: ' . date('d M Y H:i'), 'Period: ' . $from . ' to ' . $to, 'By: ' . ($_SESSION['user_name'] ?? '')]);
    fputcsv($out, []);
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, $row);
    fputcsv($out, []);
    fputcsv($out, ['Total Records: ' . count($rows)]);
    fclose($out);
    exit;
}

// ═══════════════════════════════════════════════════════════════
// HTML PRINT OUTPUT
// ═══════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?> — DMR Store</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #202124; padding: 20px; }
  .report-header { text-align: center; margin-bottom: 24px; padding-bottom: 14px; border-bottom: 2px solid #1B4F72; }
  .report-header h1 { font-size: 18px; color: #1B4F72; }
  .report-header p  { font-size: 11px; color: #5F6368; margin-top: 4px; }
  .meta { display: flex; justify-content: space-between; margin-bottom: 14px; font-size: 11px; color: #5F6368; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1B4F72; color: #fff; padding: 7px 10px; text-align: left; font-size: 11px; }
  td { padding: 6px 10px; border-bottom: 1px solid #E8EAED; }
  tr:nth-child(even) td { background: #F8F9FA; }
  .footer { margin-top: 20px; font-size: 10px; color: #9AA0A6; text-align: center; }
  @media print {
    body { padding: 10px; }
    .no-print { display: none; }
  }
</style>
</head>
<body>

<div class="no-print" style="margin-bottom:16px;">
  <button onclick="window.print()" style="padding:8px 16px;background:#1B4F72;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">🖨️ Print / Save PDF</button>
  <button onclick="window.close()" style="padding:8px 16px;background:#fff;border:1px solid #ddd;border-radius:6px;cursor:pointer;font-size:13px;margin-left:8px;">✕ Close</button>
</div>

<div class="report-header">
  <h1><?= htmlspecialchars(COMPANY) ?></h1>
  <h2 style="font-size:14px;margin-top:4px;"><?= htmlspecialchars($title) ?></h2>
  <p>Generated on <?= date('d M Y, h:i A') ?> by <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></p>
</div>

<div class="meta">
  <span>Period: <strong><?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></strong></span>
  <?php if ($dept): ?><span>Department: <strong><?= htmlspecialchars($dept) ?></strong></span><?php endif; ?>
  <span>Total Records: <strong><?= count($rows) ?></strong></span>
</div>

<table>
  <thead>
    <tr><?php foreach ($headers as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="<?= count($headers) ?>" style="text-align:center;padding:20px;color:#9AA0A6;">No records found for the selected period.</td></tr>
    <?php else: foreach ($rows as $row): ?>
    <tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<div class="footer">
  <?= htmlspecialchars(COMPANY) ?> — Store Management System v<?= APP_VERSION ?> · This is a computer-generated report.
</div>

</body>
</html>
