<?php
/**
 * POST /api/auth/reset.php
 * Body: { token, password }
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['POST']);

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

DB::query(
    'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?',
    [password_hash($password, PASSWORD_DEFAULT), $user['id']]
);
DB::query('DELETE FROM sessions WHERE user_id = ?', [$user['id']]);

json_ok(['message' => 'Password updated. Please log in.']);
