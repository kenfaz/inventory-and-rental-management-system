<?php
// ============================================================
// helpers/audit-helper.php
// Records all sensitive actions to audit_logs table
// ============================================================

/**
 * Write an audit log entry
 *
 * @param int    $userId           The user performing the action
 * @param string $actionType       e.g. 'Created', 'Updated', 'Deleted', 'Status Changed'
 * @param string $module           e.g. 'Inventory', 'Rentals', 'Tailoring Orders'
 * @param string|null $affectedTable     The database table affected
 * @param int|null    $affectedRecordId  The primary key of the affected record
 * @param mixed|null  $previousValue     Previous value (will be JSON-encoded if array)
 * @param mixed|null  $newValue          New value (will be JSON-encoded if array)
 */
function logAudit(
    int    $userId,
    string $actionType,
    string $module,
    ?string $affectedTable    = null,
    ?int    $affectedRecordId = null,
    mixed   $previousValue    = null,
    mixed   $newValue         = null
): void {
    global $pdo;

    try {
        $prev = is_array($previousValue)
            ? json_encode($previousValue, JSON_UNESCAPED_UNICODE)
            : $previousValue;

        $next = is_array($newValue)
            ? json_encode($newValue, JSON_UNESCAPED_UNICODE)
            : $newValue;

        $pdo->prepare('
            INSERT INTO audit_logs
                (user_id, action_type, module, affected_table,
                 affected_record_id, previous_value, new_value, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $userId,
            $actionType,
            $module,
            $affectedTable,
            $affectedRecordId,
            $prev,
            $next,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

/**
 * Convenience wrappers for common action types
 */
function auditCreate(int $userId, string $module, string $table, int $recordId, mixed $data = null): void {
    logAudit($userId, 'Created', $module, $table, $recordId, null, $data);
}

function auditUpdate(int $userId, string $module, string $table, int $recordId, mixed $old, mixed $new): void {
    logAudit($userId, 'Updated', $module, $table, $recordId, $old, $new);
}

function auditDelete(int $userId, string $module, string $table, int $recordId, mixed $data = null): void {
    logAudit($userId, 'Deleted', $module, $table, $recordId, $data, null);
}

function auditStatusChange(int $userId, string $module, string $table, int $recordId, string $from, string $to): void {
    logAudit($userId, 'Status Changed', $module, $table, $recordId, $from, $to);
}