<?php
require_once __DIR__ . '/../config/config.php';
requireLogin();

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT ir.*, p.name AS product_name, p.unit, p.product_code, p.rack_location,
            c.name AS category_name,
            ub.name AS issued_by_name, ub.designation AS issued_by_desig,
            ue.name AS employee_full_name, ue.designation AS employee_desig
     FROM issue_requests ir
     JOIN products p ON ir.product_id = p.id
     LEFT JOIN categories c ON p.category_id = c.id
     LEFT JOIN users ub ON ir.issued_by = ub.id
     LEFT JOIN users ue ON ir.employee_id = ue.id
     WHERE ir.id = ? AND ir.status = 'issued'"
);
$stmt->execute([$id]);
$slip = $stmt->fetch();

if (!$slip) {
    die('<p style="font-family:sans-serif;padding:40px;text-align:center;color:red;">Issue slip not found or request not yet issued.</p>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Issue Slip — <?= htmlspecialchars($slip['issue_number']) ?></title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', Arial, sans-serif; font-size: 12px; background: #fff; color: #202124; }
  .slip { max-width: 720px; margin: 0 auto; padding: 32px; }

  /* HEADER */
  .slip-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #1B4F72; }
  .company-info h1 { font-size: 18px; font-weight: 800; color: #1B4F72; letter-spacing: -0.5px; }
  .company-info p  { font-size: 11px; color: #5F6368; margin-top: 2px; }
  .slip-title { text-align: right; }
  .slip-title h2 { font-size: 15px; font-weight: 700; color: #1B4F72; }
  .slip-title .slip-no { font-size: 20px; font-weight: 800; color: #E67E22; margin-top: 4px; letter-spacing: -0.5px; }

  /* META BAR */
  .meta-bar { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; background: #F8F9FA; border: 1px solid #E8EAED; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; }
  .meta-item .meta-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #80868B; margin-bottom: 3px; }
  .meta-item .meta-val   { font-size: 13px; font-weight: 600; }

  /* ISSUE TABLE */
  .issue-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  .issue-table thead th { background: #1B4F72; color: #fff; padding: 9px 12px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
  .issue-table tbody td { padding: 10px 12px; border-bottom: 1px solid #E8EAED; font-size: 13px; }
  .issue-table tfoot td { padding: 8px 12px; font-weight: 600; background: #F8F9FA; border-top: 2px solid #E8EAED; }

  /* SIGNATURE AREA */
  .sig-area { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 24px; padding-top: 16px; border-top: 1px dashed #DADCE0; }
  .sig-box { text-align: center; }
  .sig-line { border-bottom: 1px solid #DADCE0; margin-bottom: 6px; height: 40px; }
  .sig-label { font-size: 11px; color: #5F6368; font-weight: 600; }
  .sig-sublabel { font-size: 10px; color: #9AA0A6; margin-top: 2px; }

  /* PURPOSE BOX */
  .purpose-box { border: 1px solid #E8EAED; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; }
  .purpose-box .pb-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #80868B; margin-bottom: 4px; }

  /* FOOTER */
  .slip-footer { margin-top: 20px; padding-top: 12px; border-top: 1px solid #E8EAED; display: flex; justify-content: space-between; }
  .slip-footer p { font-size: 10px; color: #9AA0A6; }

  /* WATERMARK for issued */
  .status-watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%) rotate(-30deg); font-size: 72px; font-weight: 800; color: rgba(30,132,73,0.06); pointer-events: none; white-space: nowrap; z-index: 0; letter-spacing: -2px; }

  /* PRINT CONTROLS */
  .print-controls { padding: 12px 32px; background: #F8F9FA; border-bottom: 1px solid #E8EAED; display: flex; gap: 10px; }
  @media print {
    .print-controls { display: none !important; }
    body { padding: 0; }
    .slip { padding: 20px; }
  }
</style>
</head>
<body>

<div class="print-controls">
  <button onclick="window.print()" style="padding:7px 16px;background:#1B4F72;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;">🖨️ Print Slip</button>
  <button onclick="window.close()" style="padding:7px 16px;background:#fff;border:1.5px solid #DADCE0;border-radius:6px;cursor:pointer;font-size:13px;">✕ Close</button>
</div>

<div class="status-watermark">ISSUED</div>

<div class="slip">

  <!-- HEADER -->
  <div class="slip-header">
    <div class="company-info">
      <h1><?= htmlspecialchars(COMPANY) ?></h1>
      <p>Store Management Department</p>
      <p style="margin-top:6px;font-size:11px;color:#BDC1C6;">Generated: <?= date('d M Y, h:i A') ?></p>
    </div>
    <div class="slip-title">
      <h2>MATERIAL ISSUE SLIP</h2>
      <div class="slip-no"><?= htmlspecialchars($slip['issue_number']) ?></div>
    </div>
  </div>

  <!-- META INFO -->
  <div class="meta-bar">
    <div class="meta-item">
      <div class="meta-label">Issue Date</div>
      <div class="meta-val"><?= date('d M Y', strtotime($slip['issued_at'])) ?></div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Issue Time</div>
      <div class="meta-val"><?= date('h:i A', strtotime($slip['issued_at'])) ?></div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Work Order / Ref.</div>
      <div class="meta-val"><?= htmlspecialchars($slip['work_order_ref'] ?: '—') ?></div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Employee Name</div>
      <div class="meta-val"><?= htmlspecialchars($slip['employee_name']) ?></div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Department</div>
      <div class="meta-val"><?= htmlspecialchars($slip['department'] ?: '—') ?></div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Designation</div>
      <div class="meta-val"><?= htmlspecialchars($slip['employee_desig'] ?: '—') ?></div>
    </div>
  </div>

  <!-- PURPOSE -->
  <?php if ($slip['purpose']): ?>
  <div class="purpose-box">
    <div class="pb-label">Purpose / Remarks</div>
    <div><?= htmlspecialchars($slip['purpose']) ?></div>
  </div>
  <?php endif; ?>

  <!-- ISSUE ITEMS TABLE -->
  <table class="issue-table">
    <thead>
      <tr>
        <th style="width:40px;">#</th>
        <th>Product Code</th>
        <th>Product Name</th>
        <th>Category</th>
        <th>Location</th>
        <th style="text-align:right;">Qty Requested</th>
        <th style="text-align:right;">Qty Issued</th>
        <th>Unit</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>1</td>
        <td style="font-family:monospace;"><?= htmlspecialchars($slip['product_code']) ?></td>
        <td><strong><?= htmlspecialchars($slip['product_name']) ?></strong></td>
        <td><?= htmlspecialchars($slip['category_name'] ?: '—') ?></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($slip['rack_location'] ?: '—') ?></td>
        <td style="text-align:right;"><?= number_format((float)$slip['quantity_requested'],2) ?></td>
        <td style="text-align:right;font-weight:700;color:#1E8449;"><?= number_format((float)$slip['quantity_issued'],2) ?></td>
        <td><?= htmlspecialchars($slip['unit']) ?></td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" style="text-align:right;">Total Issued:</td>
        <td style="text-align:right;color:#1E8449;"><?= number_format((float)$slip['quantity_issued'],2) ?> <?= htmlspecialchars($slip['unit']) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <!-- SIGNATURE AREA -->
  <div class="sig-area">
    <div class="sig-box">
      <div class="sig-line"></div>
      <div class="sig-label">Employee Signature</div>
      <div class="sig-sublabel"><?= htmlspecialchars($slip['employee_name']) ?></div>
    </div>
    <div class="sig-box">
      <div class="sig-line"></div>
      <div class="sig-label">Store Keeper / Issued By</div>
      <div class="sig-sublabel"><?= htmlspecialchars($slip['issued_by_name'] ?: '—') ?></div>
    </div>
    <div class="sig-box">
      <div class="sig-line"></div>
      <div class="sig-label">Store In-charge / Admin</div>
      <div class="sig-sublabel">Authorized Signatory</div>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="slip-footer">
    <p><?= htmlspecialchars(COMPANY) ?> — Store Management System v<?= APP_VERSION ?></p>
    <p>This is a computer-generated slip. Valid without physical signature if digitally approved.</p>
  </div>

</div><!-- /slip -->
</body>
</html>
