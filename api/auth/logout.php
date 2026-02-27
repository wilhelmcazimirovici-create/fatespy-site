<?php
/**
 * POST /api/auth/logout.php
 * POST /api/auth/forgot.php  — request password reset
 * POST /api/auth/reset.php   — reset password with token
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['POST']);

$action = basename(__FILE__, '.php');

// ── LOGOUT ────────────────────────────────────────────────
if ($action === 'logout') {
    $user = require_auth();
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = substr($header, 7);
    DB::query('DELETE FROM sessions WHERE token = ?', [$token]);
    json_ok(['message' => 'Logged out']);
}

// ── FORGOT PASSWORD ───────────────────────────────────────
if ($action === 'forgot') {
    $body = get_body();
    $email = trim(strtolower($body['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        json_err('Invalid email');

    $user = DB::one('SELECT id, name FROM users WHERE email = ? AND active = 1', [$email]);
    if ($user) {
        $token = generate_token(32);
        $expires = date('Y-m-d H:i:s', time() + 3600);
        DB::query(
            'UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?',
            [$token, $expires, $user['id']]
        );
        $url = SITE_URL . '/reset-password.html?token=' . $token;
        send_email(
            $email,
            'Reset your FateSpy password',
            "<h1>Password Reset</h1><p>Hi {$user['name']},</p>
             <p><a href='{$url}' style='background:#8b5cf6;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none'>Reset Password</a></p>
             <p style='color:#999;font-size:12px'>This link expires in 1 hour.</p>"
        );
    }
    // Always return ok (don't leak user existence)
    json_ok(['message' => 'If that email is registered, a reset link has been sent.']);
}

// ── RESET PASSWORD ────────────────────────────────────────
if ($action === 'reset') {
    $body = get_body();
    $token = $body['token'] ?? '';
    $password = $body['password'] ?? '';
    if (strlen($password) < 8)
        json_err('Password must be at least 8 characters');

    $user = DB::one(
        'SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND active = 1',
        [$token]
    );
    if (!$user)
        json_err('Invalid or expired reset link', 400);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    DB::query(
        'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?',
        [$hash, $user['id']]
    );
    DB::query('DELETE FROM sessions WHERE user_id = ?', [$user['id']]);

    json_ok(['message' => 'Password updated. Please log in again.']);
}
