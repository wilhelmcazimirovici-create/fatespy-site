<?php
/**
 * FateSpy — Admin IP Allowlist Middleware
 * Restrict admin panel API access to specific IP addresses.
 *
 * Usage: include at top of any admin API file.
 * Configure allowed IPs in Admin Panel → Settings → Allowed Admin IPs
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';

function check_admin_ip_allowlist(): void
{
    // Get allowlist from settings (comma-separated IPs or CIDR ranges)
    $allowlist_str = get_setting('admin_ip_allowlist', '');
    if (empty(trim($allowlist_str)))
        return; // Not configured — skip (allow all)

    $allowed_ips = array_map('trim', explode(',', $allowlist_str));
    $current_ip = Security::getIP();

    foreach ($allowed_ips as $allowed) {
        if (empty($allowed))
            continue;

        // Exact match
        if ($current_ip === $allowed)
            return;

        // CIDR range match (e.g. 192.168.1.0/24)
        if (str_contains($allowed, '/')) {
            [$subnet, $bits] = explode('/', $allowed);
            $bits = (int) $bits;
            $mask = -1 << (32 - $bits);
            $ip_l = ip2long($current_ip);
            $sub_l = ip2long($subnet);
            if ($ip_l !== false && $sub_l !== false && ($ip_l & $mask) === ($sub_l & $mask)) {
                return;
            }
        }
    }

    // IP not in allowlist
    Security::auditLog('admin_ip_blocked', 0, ['ip' => $current_ip]);
    http_response_code(403);
    // Generic response — don't reveal that IP blocking is active
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}
