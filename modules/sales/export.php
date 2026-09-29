<?php
// ============================================================
// modules/sales/export.php
// Export income records as CSV or PDF
// Admin only
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../includes/role-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../helpers/sales-helper.php';

$format    = trim($_GET['format']    ?? 'csv');
$period    = trim($_GET['period']    ?? 'monthly');
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to']   ?? '');
$typeFilter   = trim($_GET['type']   ?? '');
$methodFilter = trim($_GET['method'] ?? '');

if (!in_array($format, ['csv','pdf'])) $format = 'csv';

// Build query
$where  = [];
$params = [];

if ($dateFrom && $dateTo) {
    $where[]  = 'ir.date_recorded BETWEEN ? AND ?';
    $params[] = $dateFrom . ' 00:00:00';
    $params[] = $dateTo   . ' 23:59:59';
} else {
    // Default to this month
    $where[]  = 'ir.date_recorded >= ?';
    $params[] = date('Y-m-01') . ' 00:00:00';
}
if ($typeFilter !== '') {
    $where[]  = 'ir.income_type = ?';
    $params[] = $typeFilter;
}
if ($methodFilter !== '') {
    $where[]  = 'ir.payment_method = ?';
    $params[] = $methodFilter;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT ir.income_id,
           ir.date_recorded,
           ir.income_type,
           ir.payment_method,
           ir.amount,
           c.full_name    AS customer_name,
           c.mobile_number,
           u.full_name    AS processed_by,
           sr.notes
    FROM income_records ir
    JOIN sales_records sr ON ir.sale_id      = sr.sale_id
    JOIN customers     c  ON sr.customer_id  = c.customer_id
    JOIN users         u  ON sr.processed_by = u.user_id
    $whereSQL
    ORDER BY ir.date_recorded DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

$total      = array_sum(array_column($records, 'amount'));
$exportDate = date('Y-m-d');
$filename   = 'sales-report-' . $exportDate;

// ── CSV Export ────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');

    // Header row
    fputcsv($out, [
        'Record #',
        'Date',
        'Time',
        'Customer Name',
        'Mobile Number',
        'Income Type',
        'Payment Method',
        'Amount (PHP)',
        'Processed By',
        'Notes',
    ]);

    foreach ($records as $r) {
        fputcsv($out, [
            str_pad($r['income_id'], 4, '0', STR_PAD_LEFT),
            date('Y-m-d', strtotime($r['date_recorded'])),
            date('H:i:s', strtotime($r['date_recorded'])),
            $r['customer_name'],
            $r['mobile_number'],
            $r['income_type'],
            $r['payment_method'],
            number_format($r['amount'], 2, '.', ''),
            $r['processed_by'],
            $r['notes'] ?? '',
        ]);
    }

    // Total row
    fputcsv($out, []);
    fputcsv($out, ['', '', '', '', '', '', 'TOTAL', number_format($total, 2, '.', ''), '', '']);

    fclose($out);
    exit;
}

// ── PDF Export (HTML print version) ──────────────────────
// Generates a print-ready HTML page using window.print()
// No TCPDF dependency required
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sales Report — <?= $exportDate ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Helvetica Neue', Arial, sans-serif;
      font-size: 11pt;
      color: #111;
      background: #f0f0f0;
      padding: 20px;
    }
    .print-btn {
      display: block;
      margin: 0 auto 20px;
      padding: 10px 32px;
      background: #7C3AED;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      cursor: pointer;
    }
    .report {
      background: #fff;
      max-width: 900px;
      margin: 0 auto;
      padding: 40px 50px;
      box-shadow: 0 2px 16px rgba(0,0,0,0.1);
    }
    .report-header { text-align: center; margin-bottom: 24px; }
    .report-header h1 { font-size: 16pt; margin-bottom: 4px; }
    .report-header p  { font-size: 9.5pt; color: #666; }

    .meta-row {
      display: flex;
      justify-content: space-between;
      font-size: 10pt;
      margin-bottom: 6px;
      color: #555;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      font-size: 9.5pt;
    }
    thead th {
      background: #1E1030;
      color: #fff;
      padding: 7px 8px;
      text-align: left;
      font-weight: 600;
    }
    tbody tr:nth-child(even) { background: #f9f9f9; }
    tbody td { padding: 6px 8px; border-bottom: 1px solid #eee; }
    tfoot td {
      padding: 8px;
      font-weight: bold;
      border-top: 2px solid #333;
    }
    .text-right { text-align: right; }
    .total-row  { background: #EDE9FE !important; color: #3730a3; }

    .summary-box {
      display: flex;
      gap: 20px;
      margin: 20px 0;
      flex-wrap: wrap;
    }
    .summary-item {
      flex: 1;
      min-width: 140px;
      padding: 12px 16px;
      background: #f9f9f9;
      border-radius: 8px;
      border-left: 3px solid #7C3AED;
    }
    .summary-item .label { font-size: 9pt; color: #888; margin-bottom: 2px; }
    .summary-item .value { font-size: 14pt; font-weight: bold; color: #1E1030; }

    @media print {
      body       { background: #fff; padding: 0; }
      .print-btn { display: none; }
      .report    { box-shadow: none; padding: 20px 30px; max-width: 100%; }
    }
  </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>

<div class="report">

  <div class="report-header">
    <h1><?= htmlspecialchars(SHOP_NAME) ?></h1>
    <p>Sales and Income Report &nbsp;·&nbsp; Generated on <?= date('F j, Y \a\t g:i A') ?></p>
  </div>

  <div class="meta-row">
    <span>Period:
      <strong>
        <?= $dateFrom ? date('F j, Y', strtotime($dateFrom)) : 'This Month' ?>
        <?= $dateTo   ? ' – ' . date('F j, Y', strtotime($dateTo)) : '' ?>
      </strong>
    </span>
    <span>Total Records: <strong><?= count($records) ?></strong></span>
  </div>
  <?php if ($typeFilter): ?>
    <div class="meta-row">
      <span>Filtered by Type: <strong><?= htmlspecialchars($typeFilter) ?></strong></span>
    </div>
  <?php endif; ?>

  <!-- Summary boxes -->
  <?php
  $byType = [];
  foreach ($records as $r) {
      $byType[$r['income_type']] = ($byType[$r['income_type']] ?? 0) + $r['amount'];
  }
  ?>
  <div class="summary-box">
    <div class="summary-item">
      <div class="label">Total Revenue</div>
      <div class="value">₱<?= number_format($total, 2) ?></div>
    </div>
    <?php foreach ($byType as $type => $amt): ?>
      <div class="summary-item">
        <div class="label"><?= htmlspecialchars($type) ?></div>
        <div class="value">₱<?= number_format($amt, 2) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Records table -->
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Income Type</th>
        <th>Method</th>
        <th class="text-right">Amount</th>
        <th>Processed By</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($records)): ?>
        <tr>
          <td colspan="7" style="text-align:center;color:#888;padding:20px">
            No records found for this period.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($records as $r): ?>
          <tr>
            <td><?= str_pad($r['income_id'], 4, '0', STR_PAD_LEFT) ?></td>
            <td><?= date('M j, Y', strtotime($r['date_recorded'])) ?></td>
            <td><?= htmlspecialchars($r['customer_name']) ?></td>
            <td><?= htmlspecialchars($r['income_type']) ?></td>
            <td><?= htmlspecialchars($r['payment_method']) ?></td>
            <td class="text-right">₱<?= number_format($r['amount'], 2) ?></td>
            <td><?= htmlspecialchars($r['processed_by']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="5" class="text-right">TOTAL</td>
        <td class="text-right">₱<?= number_format($total, 2) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <p style="text-align:center;font-size:9pt;color:#aaa;margin-top:30px">
    <?= htmlspecialchars(SHOP_NAME) ?> · <?= htmlspecialchars(SHOP_ADDRESS) ?>
    · <?= htmlspecialchars(SHOP_CONTACT) ?>
  </p>

</div>

</body>
</html>