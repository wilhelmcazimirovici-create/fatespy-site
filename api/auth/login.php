<?php
/**
 * POST /api/auth/login.php — SECURED VERSION with 2FA + suspicious login detection
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/error_handler.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../config/login_detection.php';

Security::setSecurityHeaders();
set_json_headers(['POST']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    json_err('Method not allowed', 405);

$body = get_body();

// ── Bot and abuse protection ──────────────────────────────
Security::checkHoneypot($body);
Security::rateLimit('login', 20, 60);

$email = Security::sanitizeEmail($body['email'] ?? '');
$password = $body['password'] ?? '';

if (!$email)
    json_err('Invalid email address');
if (!$password)
    json_err('Password is required');

// Brute force check
Security::checkBruteForce($email);

// Fetch user (constant-time pattern)
$user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
$hash = $user ? $user['password_hash'] : '$2y$10$invalidhashforlengthpadding00000000000000000000000000000';
$valid = password_verify($password, $hash);

if (!$user || !$valid) {
    Security::recordFailedLogin($email);
    json_err('Invalid email or password', 401);
}

if (!$user['active']) {
    json_err('Please verify your email address. Check your inbox.', 403);
}

// ── 2FA check — if user has 2FA enabled, return partial response ──
if (!empty($user['totp_enabled'])) {
    // Don't create session yet — return a temporary challenge token
    $challenge = generate_token(20);
    DB::query(
        'INSERT INTO settings (`key`, val) VALUES (?, ?) ON DUPLICATE KEY UPDATE val = VALUES(val)',
        ['2fa_challenge_' . $challenge, $user['id']]
    );
    // Challenge expires in 5 minutes (cleaned up by cron or on next request)
    Security::auditLog('login_2fa_required', $user['id']);

    json_ok([
        'requires_2fa' => true,
        'challenge_token' => $challenge,
        'message' => 'Enter your 2-factor authentication code.',
    ]);
}

// ── Successful login — create session ────────────────────
Security::clearLoginAttempts($email);

$token = generate_token(40);
DB::insert(
    'INSERT INTO sessions (user_id, token, ip, ua, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))',
    [$user['id'], $token, Security::getIP(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]
);

// Upgrade password hash if algorithm changed (transparent rehashing)
if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    DB::query(
        'UPDATE users SET password_hash = ? WHERE id = ?',
        [password_hash($password, PASSWORD_DEFAULT), $user['id']]
    );
}

// Check for suspicious activity (async via output buffer — won't block response)
ob_start();
check_suspicious_login((int) $user['id'], $token);
ob_end_clean();

Security::auditLog('login_success', $user['id'], ['plan' => $user['plan']]);

json_ok([
    'token' => $token,
    'user' => [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'zodiac' => $user['zodiac'],
        'plan' => $user['plan'],
        'role' => $user['role'],
        'totp_active' => (bool) $user['totp_enabled'],
    ],
]);
