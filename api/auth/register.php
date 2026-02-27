<?php
/**
 * POST /api/auth/register.php
 * Body: { email, password, name, website (honeypot) }
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['POST']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    json_err('Method not allowed', 405);

$body = get_body();

// Bot protection
Security::checkHoneypot($body);

// Rate limit: 5 registrations per hour per IP
Security::rateLimit('register', 5, 3600);

if (get_setting('registration_open', '1') !== '1')
    json_err('Registration is currently closed');

$email = Security::sanitizeEmail($body['email'] ?? '');
$password = $body['password'] ?? '';
$name = Security::sanitizeString($body['name'] ?? '', 120);

if (!$email)
    json_err('Invalid email address');
if (strlen($name) < 2)
    json_err('Name must be at least 2 characters');

// Strict password validation
$pwError = Security::validatePassword($password);
if ($pwError)
    json_err($pwError);

// Duplicate check
if (DB::one('SELECT id FROM users WHERE email = ?', [$email])) {
    // Don't reveal if email exists — send fake success
    // But actually for UX we can reveal — common practice
    json_err('This email is already registered. Try logging in or resetting your password.');
}

$verify_token = generate_token(32);
$hash = password_hash($password, PASSWORD_DEFAULT);

$id = DB::insert(
    'INSERT INTO users (email, password_hash, name, verify_token, active) VALUES (?, ?, ?, ?, 0)',
    [$email, $hash, $name, $verify_token]
);

// Send verification email
$verify_url = SITE_URL . '/api/auth/verify.php?token=' . $verify_token;
$html = "<div style='font-family:Inter,sans-serif;max-width:600px;margin:auto;background:#07071a;color:#f0f0ff;padding:40px;border-radius:16px'>
<h1 style='color:#d4a853;font-family:serif'>Welcome to FateSpy, {$name}!</h1>
<p style='color:#aaa'>Click below to activate your account and start exploring your destiny.</p>
<p><a href='{$verify_url}' style='display:inline-block;background:linear-gradient(135deg,#d4a853,#8b5cf6);color:#fff;padding:14px 28px;border-radius:10px;text-decoration:none;font-weight:600'>&#128302; Activate My Account</a></p>
<p style='color:#666;font-size:12px'>This link expires in 24 hours. If you didn't register on FateSpy, ignore this email.</p>
</div>";

send_email($email, 'Activate your FateSpy account ✨', $html);

Security::auditLog('register', (int) $id, ['email' => $email]);

json_ok(['message' => 'Account created! Check your email to activate it.', 'user_id' => (int) $id], 201);
