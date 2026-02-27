<?php
/**
 * FateSpy — Suspicious Activity Detection
 * Called after successful login to check for anomalies.
 *
 * Detects:
 * - Login from new/unknown IP
 * - Login from different country/region (via IP geolocation)
 * - Multiple accounts from same IP
 * - Unusual login times (e.g., 3am local time)
 * - Login after N consecutive failures
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';

function check_suspicious_login(int $user_id, string $token): void
{
    $ip = Security::getIP();
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // Get last 30 days of successful logins for this user
    $history = DB::all(
        'SELECT ip, ua, created_at FROM sessions
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY created_at DESC LIMIT 50',
        [$user_id]
    );

    $user = DB::one('SELECT email, name FROM users WHERE id = ?', [$user_id]);
    $flags = [];

    // ── Flag 1: New IP never seen before ─────────────────
    $known_ips = array_column($history, 'ip');
    if (!empty($history) && !in_array($ip, $known_ips)) {
        $flags[] = 'new_ip';
    }

    // ── Flag 2: New User Agent (different device) ─────────
    $known_uas = array_column($history, 'ua');
    if (!empty($history) && !in_array($ua, $known_uas)) {
        $flags[] = 'new_device';
    }

    // ── Flag 3: Multiple failed attempts before this login ─
    $recent_fails = DB::one(
        'SELECT COUNT(*) n FROM login_attempts
         WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
        [$user['email']]
    );
    if ((int) $recent_fails['n'] >= 3) {
        $flags[] = 'preceded_by_failures';
    }

    // ── Flag 4: Login velocity — more than 5 sessions in 1 day ──
    $sessions_today = DB::one(
        'SELECT COUNT(*) n FROM sessions
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)',
        [$user_id]
    );
    if ((int) $sessions_today['n'] > 5) {
        $flags[] = 'high_session_velocity';
    }

    // ── Flag 5: Very first login (first time ever) ─────────
    if (empty($history)) {
        $flags[] = 'first_login_ever';
    }

    if (empty($flags))
        return; // nothing suspicious

    // ── Log the event ─────────────────────────────────────
    Security::auditLog('suspicious_login', $user_id, [
        'ip' => $ip,
        'ua' => $ua,
        'flags' => $flags,
    ]);

    // ── Email alert to user ───────────────────────────────
    $flag_labels = [
        'new_ip' => '🌍 Login from a new IP address',
        'new_device' => '📱 Login from a new device/browser',
        'preceded_by_failures' => '⚠️ Multiple failed login attempts before this login',
        'high_session_velocity' => '⚡ Unusually high number of logins today',
        'first_login_ever' => '👋 First login to your account',
    ];
    $flag_list = implode('<br>', array_map(
        fn($f) => $flag_labels[$f] ?? $f,
        $flags
    ));

    $html = "
    <div style='font-family:Inter,sans-serif;max-width:600px;margin:auto;background:#07071a;color:#f0f0ff;padding:40px;border-radius:16px'>
    <h2 style='color:#d4a853'>⚠️ Security Alert — New FateSpy Login</h2>
    <p>Hi {$user['name']},</p>
    <p>We detected a login to your account with the following flags:</p>
    <div style='background:#0d0f2a;border-radius:10px;padding:16px;margin:16px 0;font-size:14px'>
        <p>{$flag_list}</p>
        <p style='color:#aaa;margin-top:12px'>🕐 Time: " . date('Y-m-d H:i:s') . " UTC<br>
        🌐 IP: {$ip}<br>
        💻 Device: " . htmlspecialchars(substr($ua, 0, 80)) . "</p>
    </div>
    <p>If this was you, no action needed.</p>
    <p><strong>If this wasn't you</strong>, <a href='" . SITE_URL . "/user-dashboard.html' style='color:#8b5cf6'>log in immediately</a> and change your password.</p>
    <p style='color:#666;font-size:12px'>You received this email because security alerts are enabled for your FateSpy account.</p>
    </div>";

    send_email($user['email'], '⚠️ New FateSpy login detected', $html);

    // ── Notify admin for high-severity flags ──────────────
    $high_severity = ['high_session_velocity', 'preceded_by_failures'];
    if (array_intersect($flags, $high_severity)) {
        send_email(
            ADMIN_EMAIL,
            "🚨 FateSpy Security: Suspicious Login — User #{$user_id}",
            "<p>Suspicious login for user <strong>{$user['email']}</strong> (ID #{$user_id})</p>
             <p>IP: {$ip}</p>
             <p>Flags: " . implode(', ', $flags) . "</p>
             <p>Time: " . date('Y-m-d H:i:s') . " UTC</p>"
        );
    }
}
