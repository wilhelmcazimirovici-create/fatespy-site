<?php
/**
 * FateSpy — Scheduled Security Maintenance (Cron)
 * Run hourly: 0 * * * * php /home/USERNAME/public_html/cron/security-cleanup.php
 *
 * Tasks:
 * 1. Clean expired sessions
 * 2. Clean expired rate limits
 * 3. Clean old login attempts
 * 4. Rotate/archive old audit logs
 * 5. Check for anomalies and alert admin
 * 6. Clean expired 2FA challenges
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/security.php';

$log = [];
$start = microtime(true);

// ── 1. Clean expired sessions ─────────────────────────────
$rows = DB::query('DELETE FROM sessions WHERE expires_at < NOW()');
$log[] = "Sessions cleaned: " . $rows->rowCount();

// ── 2. Clean expired rate limits ──────────────────────────
$rows = DB::query('DELETE FROM rate_limits WHERE expires_at < NOW()');
$log[] = "Rate limits cleaned: " . $rows->rowCount();

// ── 3. Clean old login attempts (>24h) ────────────────────
$rows = DB::query('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
$log[] = "Login attempts cleaned: " . $rows->rowCount();

// ── 4. Clean 2FA pending challenges (>10 min) ────────────
$rows = DB::query("DELETE FROM settings WHERE `key` LIKE '2fa_challenge_%' AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$log[] = "2FA challenges cleaned: " . $rows->rowCount();

// ── 5. Clean 2FA pending setups (>1 hour) ────────────────
$rows = DB::query("DELETE FROM settings WHERE `key` LIKE '2fa_pending_%' AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$log[] = "2FA pending setups cleaned: " . $rows->rowCount();

// ── 6. Anomaly detection — spike in failed logins ─────────
$fail_count = DB::one(
    'SELECT COUNT(*) n FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
);
if ((int) $fail_count['n'] > 50) {
    send_email(
        ADMIN_EMAIL,
        '🚨 FateSpy Security Alert: High Failed Login Rate',
        "<p>In the last hour, there were <strong>" . $fail_count['n'] . " failed login attempts</strong>.</p>
         <p>This may indicate a credential stuffing or brute force attack.</p>
         <p>Check the admin audit log: <a href='" . SITE_URL . "/admin.html'>Admin Panel</a></p>"
    );
    Security::auditLog('admin_alert_high_failures', 0, ['count' => $fail_count['n']]);
    $log[] = "⚠️ ALERT: High failed logins ({$fail_count['n']}) — admin notified";
}

// ── 7. Anomaly detection — spike in rate limit hits ───────
$rl_blocked = DB::one(
    "SELECT COUNT(*) n FROM audit_log
     WHERE action = 'rate_limit_exceeded' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
if ((int) $rl_blocked['n'] > 100) {
    Security::auditLog('admin_alert_rate_limit_spike', 0, ['count' => $rl_blocked['n']]);
    send_email(
        ADMIN_EMAIL,
        '🚨 FateSpy: Rate Limit Spike',
        "<p><strong>{$rl_blocked['n']} rate limit hits</strong> in the last hour. Possible DoS attempt.</p>"
    );
    $log[] = "⚠️ ALERT: Rate limit spike ({$rl_blocked['n']}) — admin notified";
}

// ── 8. Archive old audit logs (>90 days) to separate table ─
$archived = DB::one("SELECT COUNT(*) n FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
if ((int) $archived['n'] > 0) {
    // Move to archive table (create if not exists)
    DB::query("CREATE TABLE IF NOT EXISTS `audit_log_archive` LIKE `audit_log`");
    DB::query("INSERT IGNORE INTO `audit_log_archive` SELECT * FROM `audit_log` WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    DB::query("DELETE FROM `audit_log` WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $log[] = "Audit log archived: {$archived['n']} records";
}

// ── 9. Check expired VIP subscriptions ───────────────────
$expired_vip = DB::all(
    "SELECT user_id FROM user_services WHERE service_slug = 'vip_monthly' AND expires_at < NOW() AND expires_at IS NOT NULL"
);
foreach ($expired_vip as $ev) {
    DB::query("UPDATE users SET plan = 'free' WHERE id = ? AND plan = 'vip'", [$ev['user_id']]);
}
if (!empty($expired_vip)) {
    $log[] = "VIP subscriptions expired: " . count($expired_vip);
}

// ── Output ────────────────────────────────────────────────
$elapsed = round(microtime(true) - $start, 2);
$log[] = "Done in {$elapsed}s";

echo "FateSpy Security Cleanup — " . date('Y-m-d H:i:s') . "\n";
echo implode("\n", $log) . "\n";

// Write to log file
$log_dir = __DIR__ . '/../logs/';
if (!is_dir($log_dir))
    mkdir($log_dir, 0750, true);
file_put_contents(
    $log_dir . 'security_cleanup_' . date('Y-m') . '.log',
    date('Y-m-d H:i:s') . "\n" . implode("\n", $log) . "\n\n",
    FILE_APPEND
);
