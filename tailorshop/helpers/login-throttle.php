<?php
// ============================================================
// helpers/login-throttle.php
// Persistent login attempt throttling
// ============================================================

function loginAttemptKey(string $email, string $ipAddress): string
{
    return hash('sha256', strtolower(trim($email)) . "\0" . $ipAddress);
}

function getLoginCooldownSeconds(PDO $pdo, string $attemptKey): int
{
    $pdo->exec('
        DELETE FROM login_attempts
        WHERE last_failed_at < DATE_SUB(NOW(), INTERVAL ' . LOGIN_ATTEMPT_WINDOW_MINUTES . ' MINUTE)
          AND (locked_until IS NULL OR locked_until <= NOW())
    ');

    $stmt = $pdo->prepare('
        SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until))
        FROM login_attempts
        WHERE attempt_key = ? AND locked_until > NOW()
    ');
    $stmt->execute([$attemptKey]);
    $remaining = $stmt->fetchColumn();

    return $remaining === false ? 0 : (int) $remaining;
}

function recordFailedLoginAttempt(PDO $pdo, string $attemptKey): int
{
    $pdo->beginTransaction();

    try {
        $pdo->prepare('
            INSERT INTO login_attempts (attempt_key)
            VALUES (?)
            ON DUPLICATE KEY UPDATE attempt_key = VALUES(attempt_key)
        ')->execute([$attemptKey]);

        $stmt = $pdo->prepare('
            SELECT failed_attempts,
                   last_failed_at < DATE_SUB(NOW(), INTERVAL ' . LOGIN_ATTEMPT_WINDOW_MINUTES . ' MINUTE)
                       OR last_failed_at IS NULL AS window_expired,
                   locked_until > NOW() AS is_locked,
                   GREATEST(1, CEIL(TIMESTAMPDIFF(SECOND, NOW(), locked_until) / 60)) AS minutes_remaining
            FROM login_attempts
            WHERE attempt_key = ?
            FOR UPDATE
        ');
        $stmt->execute([$attemptKey]);
        $attempt = $stmt->fetch();

        if ((int) $attempt['is_locked'] === 1) {
            $pdo->commit();
            return (int) $attempt['minutes_remaining'];
        }

        $failedAttempts = (int) $attempt['window_expired'] === 1
            ? 1
            : (int) $attempt['failed_attempts'] + 1;
        $cooldown = $failedAttempts >= LOGIN_MAX_ATTEMPTS;

        $pdo->prepare('
            UPDATE login_attempts
            SET failed_attempts = ?,
                last_failed_at = NOW(),
                locked_until = ' . ($cooldown
                ? 'DATE_ADD(NOW(), INTERVAL ' . LOGIN_COOLDOWN_MINUTES . ' MINUTE)'
                : 'NULL') . '
            WHERE attempt_key = ?
        ')->execute([$failedAttempts, $attemptKey]);

        $pdo->commit();
        return $cooldown ? LOGIN_COOLDOWN_MINUTES : 0;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function clearLoginAttempts(PDO $pdo, string $attemptKey): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key = ?')
        ->execute([$attemptKey]);
}
