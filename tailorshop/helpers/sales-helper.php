<?php
// ============================================================
// helpers/sales-helper.php
// Income recording and report aggregation
// ============================================================

require_once __DIR__ . '/audit-helper.php';

/**
 * Record a sale and create an income entry
 *
 * @param int    $customerId    customers.customer_id
 * @param int    $processedBy  users.user_id
 * @param string $saleType     'Garment Sale' | 'Rental Fee' | 'Tailoring Payment'
 * @param float  $amount       Amount in PHP peso
 * @param string $paymentMethod 'Cash' | 'GCash' | 'Card' | 'Other'
 * @param int|null $garmentId  garment_items.garment_id (if sale)
 * @param int|null $rentalId   rentals.rental_id (if rental fee)
 * @param int|null $orderId    tailoring_orders.order_id (if tailoring)
 * @param string|null $paymentOther Specify if payment_method is Other
 * @param string|null $notes   Optional notes
 * @return int  The new sale_id
 */
function recordSale(
    int    $customerId,
    int    $processedBy,
    string $saleType,
    float  $amount,
    string $paymentMethod,
    ?int   $garmentId     = null,
    ?int   $rentalId      = null,
    ?int   $orderId       = null,
    ?string $paymentOther = null,
    ?string $notes        = null
): int {
    global $pdo;

    // Insert sales record
    $pdo->prepare('
        INSERT INTO sales_records
            (customer_id, processed_by, garment_id, rental_id, order_id,
             sale_type, amount, payment_method, payment_other, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([
        $customerId, $processedBy,
        $garmentId, $rentalId, $orderId,
        $saleType, $amount, $paymentMethod, $paymentOther, $notes,
    ]);

    $saleId = (int) $pdo->lastInsertId();

    // Insert income record
    $pdo->prepare('
        INSERT INTO income_records
            (sale_id, income_type, amount, payment_method)
        VALUES (?, ?, ?, ?)
    ')->execute([$saleId, $saleType, $amount, $paymentMethod]);

    // Audit log
    auditCreate($processedBy, 'Sales', 'sales_records', $saleId, [
        'sale_type'      => $saleType,
        'amount'         => $amount,
        'payment_method' => $paymentMethod,
    ]);

    return $saleId;
}

/**
 * Get income report aggregated by period
 *
 * @param string $period  'daily' | 'weekly' | 'monthly'
 * @param string|null $startDate  Custom start date Y-m-d (overrides period)
 * @param string|null $endDate    Custom end date Y-m-d (overrides period)
 * @return array {
 *   total: float,
 *   by_type: array,
 *   by_method: array,
 *   records: array
 * }
 */
function getIncomeReport(
    string  $period    = 'monthly',
    ?string $startDate = null,
    ?string $endDate   = null
): array {
    global $pdo;

    // Determine date range
    if ($startDate && $endDate) {
        $from = $startDate . ' 00:00:00';
        $to   = $endDate   . ' 23:59:59';
    } else {
        switch ($period) {
            case 'daily':
                $from = date('Y-m-d') . ' 00:00:00';
                $to   = date('Y-m-d') . ' 23:59:59';
                break;
            case 'weekly':
                $from = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $to   = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                break;
            case 'monthly':
            default:
                $from = date('Y-m-01') . ' 00:00:00';
                $to   = date('Y-m-t')  . ' 23:59:59';
                break;
        }
    }

    // Total revenue
    $totalStmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM income_records
        WHERE date_recorded BETWEEN ? AND ?
    ');
    $totalStmt->execute([$from, $to]);
    $total = (float) $totalStmt->fetchColumn();

    // By income type
    $typeStmt = $pdo->prepare('
        SELECT income_type, COALESCE(SUM(amount), 0) AS subtotal,
               COUNT(*) AS count
        FROM income_records
        WHERE date_recorded BETWEEN ? AND ?
        GROUP BY income_type
        ORDER BY subtotal DESC
    ');
    $typeStmt->execute([$from, $to]);
    $byType = $typeStmt->fetchAll();

    // By payment method
    $methodStmt = $pdo->prepare('
        SELECT payment_method, COALESCE(SUM(amount), 0) AS subtotal,
               COUNT(*) AS count
        FROM income_records
        WHERE date_recorded BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY subtotal DESC
    ');
    $methodStmt->execute([$from, $to]);
    $byMethod = $methodStmt->fetchAll();

    // Individual records with customer and processor names
    $recordsStmt = $pdo->prepare('
        SELECT ir.*, sr.sale_type, sr.payment_method,
               c.full_name AS customer_name,
               u.full_name AS processed_by_name
        FROM income_records ir
        JOIN sales_records sr ON ir.sale_id   = sr.sale_id
        JOIN customers     c  ON sr.customer_id = c.customer_id
        JOIN users         u  ON sr.processed_by = u.user_id
        WHERE ir.date_recorded BETWEEN ? AND ?
        ORDER BY ir.date_recorded DESC
    ');
    $recordsStmt->execute([$from, $to]);
    $records = $recordsStmt->fetchAll();

    return [
        'period'    => $period,
        'from'      => $from,
        'to'        => $to,
        'total'     => $total,
        'by_type'   => $byType,
        'by_method' => $byMethod,
        'records'   => $records,
    ];
}

/**
 * Format PHP peso amount
 */
function peso(float $amount): string {
    return '₱' . number_format($amount, 2);
}