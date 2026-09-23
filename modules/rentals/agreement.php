<?php
// ============================================================
// modules/rentals/agreement.php
// Printable rental agreement — opens in new tab, uses print CSS
// ============================================================

require_once __DIR__ . '/../../../includes/auth-guard.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/constants.php';

$rentalId = (int)($_GET['id'] ?? 0);
if ($rentalId === 0) {
    header('Location: /tailorshop/modules/rentals/index.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT r.*,
           c.full_name    AS customer_name,
           c.mobile_number,
           c.address,
           g.garment_name, g.garment_code,
           g.size, g.color,
           gc.category_name,
           u.full_name    AS processed_by_name
    FROM rentals r
    JOIN customers        c  ON r.customer_id  = c.customer_id
    JOIN garment_items    g  ON r.garment_id   = g.garment_id
    JOIN garment_categories gc ON g.category_id = gc.category_id
    JOIN users            u  ON r.processed_by = u.user_id
    WHERE r.rental_id = ?
');
$stmt->execute([$rentalId]);
$rental = $stmt->fetch();

if (!$rental) {
    $_SESSION['error'] = 'Rental not found.';
    header('Location: /tailorshop/modules/rentals/index.php');
    exit;
}

$agreementNo = 'RA-' . date('Y') . '-' . str_pad($rentalId, 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rental Agreement — <?= $agreementNo ?></title>
  <style>
    /* ── Screen styles ── */
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Times New Roman', Times, serif;
      font-size: 12pt;
      color: #111;
      background: #f0f0f0;
      padding: 30px;
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
      font-family: sans-serif;
    }
    .print-btn:hover { background: #6D28D9; }

    .agreement {
      background: #fff;
      max-width: 750px;
      margin: 0 auto;
      padding: 50px 60px;
      box-shadow: 0 2px 20px rgba(0,0,0,0.1);
    }

    /* ── Content ── */
    .shop-header { text-align: center; margin-bottom: 24px; }
    .shop-header h1 { font-size: 18pt; font-weight: bold; margin-bottom: 4px; }
    .shop-header p  { font-size: 10pt; color: #444; }

    .doc-title {
      text-align: center;
      font-size: 14pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin: 20px 0;
      border-top: 2px solid #111;
      border-bottom: 2px solid #111;
      padding: 8px 0;
    }

    .meta-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
      font-size: 10.5pt;
    }
    .meta-row span { color: #444; }

    .section-title {
      font-size: 11pt;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin: 20px 0 8px;
      border-bottom: 1px solid #ccc;
      padding-bottom: 4px;
    }

    table.detail-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11pt;
      margin-bottom: 8px;
    }
    table.detail-table td {
      padding: 5px 0;
      vertical-align: top;
    }
    table.detail-table td:first-child {
      width: 40%;
      color: #555;
    }
    table.detail-table td:last-child {
      font-weight: bold;
    }

    table.financial-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11pt;
      margin-top: 8px;
    }
    table.financial-table th,
    table.financial-table td {
      border: 1px solid #ccc;
      padding: 7px 10px;
    }
    table.financial-table th {
      background: #f5f5f5;
      font-weight: bold;
      text-align: left;
    }
    table.financial-table .amount {
      text-align: right;
      font-weight: bold;
    }

    .terms-list {
      padding-left: 18px;
      font-size: 10.5pt;
      line-height: 1.7;
      color: #333;
    }

    .signature-section {
      display: flex;
      justify-content: space-between;
      margin-top: 50px;
      gap: 40px;
    }
    .signature-block { flex: 1; text-align: center; }
    .signature-line {
      border-bottom: 1px solid #111;
      margin-bottom: 6px;
      height: 40px;
    }
    .signature-label { font-size: 10pt; color: #444; }
    .signature-name  { font-weight: bold; font-size: 10.5pt; margin-top: 4px; }

    .footer-note {
      text-align: center;
      font-size: 9pt;
      color: #888;
      margin-top: 30px;
      border-top: 1px solid #eee;
      padding-top: 12px;
    }

    /* ── Print styles ── */
    @media print {
      body         { background: #fff; padding: 0; }
      .print-btn   { display: none; }
      .agreement   { box-shadow: none; padding: 30px 40px; max-width: 100%; }
    }
  </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">
  🖨 Print Agreement
</button>

<div class="agreement print-agreement">

  <!-- Shop header -->
  <div class="shop-header">
    <h1><?= htmlspecialchars(SHOP_NAME) ?></h1>
    <p><?= htmlspecialchars(SHOP_ADDRESS) ?></p>
    <p>Contact: <?= htmlspecialchars(SHOP_CONTACT) ?></p>
  </div>

  <div class="doc-title">Garment Rental Agreement</div>

  <!-- Agreement meta -->
  <div class="meta-row">
    <span>Agreement No: <strong><?= $agreementNo ?></strong></span>
    <span>Date Issued: <strong><?= date('F j, Y') ?></strong></span>
  </div>
  <div class="meta-row">
    <span>Processed By: <strong><?= htmlspecialchars($rental['processed_by_name']) ?></strong></span>
    <span>Status: <strong><?= htmlspecialchars($rental['status']) ?></strong></span>
  </div>

  <!-- Customer information -->
  <div class="section-title">Customer Information</div>
  <table class="detail-table">
    <tr>
      <td>Full Name</td>
      <td><?= htmlspecialchars($rental['customer_name']) ?></td>
    </tr>
    <tr>
      <td>Mobile Number</td>
      <td><?= htmlspecialchars($rental['mobile_number']) ?></td>
    </tr>
    <tr>
      <td>Address</td>
      <td><?= htmlspecialchars($rental['address'] ?? 'N/A') ?></td>
    </tr>
  </table>

  <!-- Garment information -->
  <div class="section-title">Garment Information</div>
  <table class="detail-table">
    <tr>
      <td>Garment Name</td>
      <td><?= htmlspecialchars($rental['garment_name']) ?></td>
    </tr>
    <tr>
      <td>Garment Code</td>
      <td><?= htmlspecialchars($rental['garment_code']) ?></td>
    </tr>
    <tr>
      <td>Category</td>
      <td><?= htmlspecialchars($rental['category_name']) ?></td>
    </tr>
    <tr>
      <td>Size</td>
      <td><?= htmlspecialchars($rental['size'] ?? 'N/A') ?></td>
    </tr>
    <tr>
      <td>Color</td>
      <td><?= htmlspecialchars($rental['color'] ?? 'N/A') ?></td>
    </tr>
  </table>

  <!-- Rental details -->
  <div class="section-title">Rental Details</div>
  <table class="detail-table">
    <tr>
      <td>Rental Date</td>
      <td><?= date('F j, Y', strtotime($rental['rental_date'])) ?></td>
    </tr>
    <tr>
      <td>Expected Return Date</td>
      <td><?= date('F j, Y', strtotime($rental['expected_return_date'])) ?></td>
    </tr>
    <tr>
      <td>Payment Method</td>
      <td><?= htmlspecialchars($rental['payment_method'] === 'Other'
              ? $rental['payment_other'] : $rental['payment_method']) ?></td>
    </tr>
  </table>

  <!-- Financial summary -->
  <div class="section-title">Financial Summary</div>
  <table class="financial-table">
    <thead>
      <tr><th>Description</th><th class="amount">Amount</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>Rental Fee</td>
        <td class="amount">₱<?= number_format($rental['rental_fee'], 2) ?></td>
      </tr>
      <tr>
        <td>Security Deposit (refundable)</td>
        <td class="amount">₱<?= number_format($rental['deposit_amount'], 2) ?></td>
      </tr>
      <tr>
        <td>Penalty Rate (if returned late)</td>
        <td class="amount">₱<?= number_format($rental['penalty_rate_per_day'], 2) ?>/day</td>
      </tr>
    </tbody>
    <tfoot>
      <tr style="background:#f9f9f9">
        <td><strong>Total Amount Due</strong></td>
        <td class="amount">
          <strong>₱<?= number_format($rental['rental_fee'] + $rental['deposit_amount'], 2) ?></strong>
        </td>
      </tr>
    </tfoot>
  </table>

  <!-- Terms and conditions -->
  <div class="section-title">Terms and Conditions</div>
  <ol class="terms-list">
    <li>The renter agrees to return the garment on or before
        <strong><?= date('F j, Y', strtotime($rental['expected_return_date'])) ?></strong>.
    </li>
    <li>A penalty of <strong>₱<?= number_format($rental['penalty_rate_per_day'], 2) ?> per day</strong>
        will be charged for every day the garment is returned late.
    </li>
    <li>The security deposit of <strong>₱<?= number_format($rental['deposit_amount'], 2) ?></strong>
        will be fully refunded upon timely return of the garment in good condition.
    </li>
    <li>Any damage to the garment beyond normal wear and tear will be charged against
        the security deposit. Additional charges may apply if the damage exceeds the deposit.
    </li>
    <li>The renter is responsible for the garment from the time of pickup until its return
        to the shop. Loss of the garment will be charged at its full sale price.
    </li>
    <li>The garment must not be altered, sub-rented, or used for purposes other than
        the event stated at the time of rental.
    </li>
    <li>
        <?= htmlspecialchars(SHOP_NAME) ?> reserves the right to deny rental services to
        any customer with outstanding obligations.
    </li>
  </ol>

  <!-- Signatures -->
  <div class="signature-section">
    <div class="signature-block">
      <div class="signature-line"></div>
      <div class="signature-name"><?= htmlspecialchars($rental['customer_name']) ?></div>
      <div class="signature-label">Customer Signature over Printed Name</div>
    </div>
    <div class="signature-block">
      <div class="signature-line"></div>
      <div class="signature-name"><?= htmlspecialchars(SHOP_NAME) ?></div>
      <div class="signature-label">Authorized Representative</div>
    </div>
  </div>

  <div class="footer-note">
    This agreement was generated on <?= date('F j, Y \a\t g:i A') ?> |
    Agreement No: <?= $agreementNo ?> |
    <?= htmlspecialchars(SHOP_NAME) ?>
  </div>

</div>

</body>
</html>