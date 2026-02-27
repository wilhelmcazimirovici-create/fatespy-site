<?php
/**
 * GET  /api/auth/verify.php?token=xxx  — email verification link
 * POST /api/auth/verify.php             — resend verification
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';
    if (!$token) {
        header('Location: ' . SITE_URL . '/?verify=invalid');
        exit;
    }

    $user = DB::one('SELECT id FROM users WHERE verify_token = ? AND active = 0', [$token]);
    if (!$user) {
        header('Location: ' . SITE_URL . '/?verify=invalid');
        exit;
    }

    DB::query('UPDATE users SET active = 1, verify_token = NULL WHERE id = ?', [$user['id']]);
    header('Location: ' . SITE_URL . '/?verify=success');
    exit;
}

// POST — resend
$body = get_body();
$email = trim(strtolower($body['email'] ?? ''));
$user = DB::one('SELECT id, name, verify_token, active FROM users WHERE email = ?', [$email]);

if (!$user || $user['active'])
    json_err('Cannot resend verification');

$verify_url = SITE_URL . '/api/auth/verify.php?token=' . $user['verify_token'];
send_email(
    $email,
    'Verify your FateSpy account',
    "<p>Click <a href='{$verify_url}'>here</a> to verify your account, {$user['name']}.</p>"
);

json_ok(['message' => 'Verification email resent']);
