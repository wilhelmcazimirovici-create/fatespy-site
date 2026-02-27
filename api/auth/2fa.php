<?php
/**
 * FateSpy — TOTP Two-Factor Authentication (RFC 6238)
 * Zero dependencies — pure PHP implementation
 * Compatible with: Google Authenticator, Authy, Bitwarden, 1Password
 *
 * Endpoints:
 * GET  /api/auth/2fa.php?action=setup   — generate secret + QR URI (admin only)
 * POST /api/auth/2fa.php?action=enable  — verify code and enable 2FA
 * POST /api/auth/2fa.php?action=verify  — verify code at login
 * POST /api/auth/2fa.php?action=disable — disable 2FA (requires current code)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['GET', 'POST']);

Security::rateLimit('2fa', 10, 60);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$body = get_body();

// ── TOTP Implementation ───────────────────────────────────

function totp_generate_secret(int $length = 32): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $bytes = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[ord($bytes[$i]) & 31];
    }
    return $secret;
}

function totp_base32_decode(string $input): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(str_replace('=', '', $input));
    $output = '';
    $n = $bits = 0;
    for ($i = 0; $i < strlen($input); $i++) {
        $val = strpos($chars, $input[$i]);
        if ($val === false)
            continue;
        $n = ($n << 5) | $val;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $output .= chr(($n >> $bits) & 0xFF);
        }
    }
    return $output;
}

function totp_get_code(string $secret, int $timestamp = 0): string
{
    if (!$timestamp)
        $timestamp = time();
    $time = pack('N*', 0) . pack('N*', intdiv($timestamp, 30));
    $key = totp_base32_decode($secret);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xF;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;
    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6)
        return false;
    $t = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_get_code($secret, $t + $i * 30), $code))
            return true;
    }
    return false;
}

function totp_qr_uri(string $secret, string $email): string
{
    $label = rawurlencode('FateSpy:' . $email);
    $issuer = rawurlencode('FateSpy');
    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
}

function totp_qr_url(string $uri): string
{
    // Google Charts QR (HTTPS, no data stored server-side)
    return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . rawurlencode($uri);
}

// ═══════════════════════════════════════════════════════
//  SETUP — generate secret, return QR URI
// ═══════════════════════════════════════════════════════
if ($action === 'setup') {
    $user = require_auth();
    $uid = (int) $user['user_id'];

    // Generate fresh secret (not stored yet — only saved on enable)
    $secret = totp_generate_secret();
    $u = DB::one('SELECT email FROM users WHERE id = ?', [$uid]);
    $uri = totp_qr_uri($secret, $u['email']);

    // Temporarily store in session or as pending in DB
    DB::query(
        'INSERT INTO settings (`key`, val) VALUES (?, ?) ON DUPLICATE KEY UPDATE val = VALUES(val)',
        ['2fa_pending_' . $uid, $secret]
    );

    Security::auditLog('2fa_setup_started', $uid);

    json_ok([
        'secret' => $secret,
        'qr_uri' => $uri,
        'qr_url' => totp_qr_url($uri),
        'message' => 'Scan the QR code in your authenticator app, then verify with a code to enable 2FA.',
    ]);
}

// ═══════════════════════════════════════════════════════
//  ENABLE — verify code from setup and save secret
// ═══════════════════════════════════════════════════════
if ($action === 'enable') {
    $user = require_auth();
    $uid = (int) $user['user_id'];
    $code = preg_replace('/\D/', '', $body['code'] ?? '');

    $pending = DB::one('SELECT val FROM settings WHERE `key` = ?', ['2fa_pending_' . $uid]);
    if (!$pending)
        json_err('No setup in progress. Generate a secret first.');

    $secret = $pending['val'];
    if (!totp_verify($secret, $code)) {
        Security::auditLog('2fa_enable_failed', $uid);
        json_err('Invalid code. Make sure your device clock is synced.');
    }

    // Generate backup codes (8 codes, one-use)
    $backup_codes = [];
    for ($i = 0; $i < 8; $i++) {
        $backup_codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    $backup_hashes = array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $backup_codes);

    DB::query(
        'UPDATE users SET totp_secret = ?, totp_backup = ?, totp_enabled = 1 WHERE id = ?',
        [Security::encrypt($secret), json_encode($backup_hashes), $uid]
    );
    DB::query('DELETE FROM settings WHERE `key` = ?', ['2fa_pending_' . $uid]);

    Security::auditLog('2fa_enabled', $uid);

    json_ok([
        'message' => '2FA enabled successfully!',
        'backup_codes' => $backup_codes,
        'warning' => 'Save these backup codes in a safe place. Each can only be used once if you lose your authenticator.',
    ]);
}

// ═══════════════════════════════════════════════════════
//  VERIFY — called during login when 2FA is enabled
// ═══════════════════════════════════════════════════════
if ($action === 'verify') {
    Security::rateLimit('2fa_verify', 5, 60);

    $body = get_body();
    $user_id = (int) ($body['user_id'] ?? 0);
    $code = preg_replace('/\D/', '', $body['code'] ?? '');

    $u = DB::one('SELECT totp_secret, totp_backup, totp_enabled FROM users WHERE id = ? AND active = 1', [$user_id]);
    if (!$u || !$u['totp_enabled'])
        json_err('2FA not enabled for this account', 400);

    $secret = Security::decrypt($u['totp_secret']);
    if (!$secret)
        json_err('2FA configuration error', 500);

    // Try TOTP code first
    if (totp_verify($secret, $code)) {
        Security::auditLog('2fa_success', $user_id);
        json_ok(['verified' => true]);
    }

    // Try backup codes
    $backups = json_decode($u['totp_backup'] ?? '[]', true);
    foreach ($backups as $i => $hash) {
        if (password_verify($code, $hash)) {
            // Invalidate used backup code
            unset($backups[$i]);
            DB::query('UPDATE users SET totp_backup = ? WHERE id = ?', [json_encode(array_values($backups)), $user_id]);
            Security::auditLog('2fa_backup_code_used', $user_id);
            json_ok(['verified' => true, 'backup_used' => true, 'remaining_backups' => count($backups)]);
        }
    }

    Security::auditLog('2fa_failed', $user_id, ['code_len' => strlen($code)]);
    json_err('Invalid authentication code. Try again.', 401);
}

// ═══════════════════════════════════════════════════════
//  DISABLE — requires valid code
// ═══════════════════════════════════════════════════════
if ($action === 'disable') {
    $user = require_auth();
    $uid = (int) $user['user_id'];
    $code = preg_replace('/\D/', '', $body['code'] ?? '');

    $u = DB::one('SELECT totp_secret, totp_enabled FROM users WHERE id = ?', [$uid]);
    if (!$u['totp_enabled'])
        json_err('2FA is not enabled');

    $secret = Security::decrypt($u['totp_secret']);
    if (!totp_verify($secret, $code)) {
        Security::auditLog('2fa_disable_failed', $uid);
        json_err('Invalid code');
    }

    DB::query('UPDATE users SET totp_secret = NULL, totp_backup = NULL, totp_enabled = 0 WHERE id = ?', [$uid]);
    Security::auditLog('2fa_disabled', $uid);
    json_ok(['message' => '2FA disabled.']);
}

json_err('Invalid action', 400);
