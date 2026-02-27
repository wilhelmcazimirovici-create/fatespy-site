<?php
/**
 * POST /api/auth/forgot.php
 * Body: { email }
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['POST']);

$body = get_body();
$email = trim(strtolower($body['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    json_err('Invalid email');

$user = DB::one('SELECT id, name FROM users WHERE email = ? AND active = 1', [$email]);
if ($user) {
    $token = generate_token(32);
    $expires = date('Y-m-d H:i:s', time() + 3600);
    DB::query('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?', [$token, $expires, $user['id']]);
    $url = SITE_URL . '/reset-password.html?token=' . $token;
    send_email(
        $email,
        'Reset your FateSpy password',
        "<h1>Password Reset</h1><p>Hi {$user['name']},</p>
         <p><a href='{$url}' style='background:#8b5cf6;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none'>Reset Password</a></p>
         <p style='color:#999;font-size:12px'>This link expires in 1 hour. If you didn't request this, ignore it.</p>"
    );
}
json_ok(['message' => 'If that email is registered, a reset link has been sent.']);
