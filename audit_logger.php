<?php
/**
 * audit_logger.php
 * ─────────────────────────────────────────────────────────────────
 * Shared helper. Include this file in any PHP page/controller and
 * call auditLog() to record a system action to the audit_logs table.
 *
 * Usage:
 *   require_once 'audit_logger.php';
 *   auditLog($pdo, $userId, $username, $userRole, 'User Login', null, 'success');
 *   auditLog($pdo, $userId, $username, $userRole, 'Updated Plant: Lagundi', 'Plant ID: 42', 'success');
 *   auditLog($pdo, null,    'unknown', 'unknown',  'Failed Login Attempt', 'Email: foo@bar.com', 'failed');
 */

function auditLog(
    PDO    $pdo,
    ?int   $userId,
    string $username,
    string $userRole,
    string $action,
    ?string $detail   = null,
    string  $status   = 'success'
): void {
    try {
        $ip        = $_SERVER['HTTP_X_FORWARDED_FOR']
                  ?? $_SERVER['REMOTE_ADDR']
                  ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs
                (user_id, username, user_role, action, detail, ip_address, user_agent, status)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $username, $userRole, $action, $detail, $ip, $userAgent, $status]);
    } catch (PDOException $e) {
        // Silently fail — never block the user flow for a logging error
        error_log('[AuditLog Error] ' . $e->getMessage());
    }
}
