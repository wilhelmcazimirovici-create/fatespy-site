<?php
/**
 * POST /api/auth/google.php
 * Body: { id_token: "eyJ..." }  — Google Identity Services credential
 *
 * Flow:
 * 1. Frontend receives Google ID token via GIS
 * 2. Sends it here
 * 3. We verify with Google's tokeninfo API
 * 4. Create or find user in DB
 * 5. Return Bearer token (same as normal login)
 *
 * Setup:
 * - Google Cloud Console → APIs & Services → Credentials
 * - Create OAuth 2.0 Client ID → Web application
 * - Authorized JS origins: https://fatespy.com
 * - Copy Client ID → Admin Panel → Settings → google_client_id
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

// Rate limit: 20 attempts per minute per IP
Security::rateLimit('google_auth', 20, 60);

$body = get_body();
$id_token = trim($body['id_token'] ?? '');
if (!$id_token)
    json_err('id_token is required');

// ── Verify token with Google ──────────────────────────────
$google_client_id = get_setting('google_client_id') ?: GOOGLE_CLIENT_ID;
if (!$google_client_id || $google_client_id === 'CHANGE_ME') {
    json_err('Google Sign-In is not configured on this server', 503);
}

$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200 || !$resp) {
    Security::auditLog('google_auth_verify_failed', 0, ['code' => $code]);
    json_err('Could not verify Google token', 401);
}

$payload = json_decode($resp, true);

// Validate the token is for OUR app (prevent token hijacking)
if (($payload['aud'] ?? '') !== $google_client_id) {
    Security::auditLog('google_auth_wrong_audience', 0, ['aud' => $payload['aud'] ?? '']);
    json_err('Token audience mismatch', 401);
}

// Token expiry check
if (($payload['exp'] ?? 0) < time()) {
    json_err('Google token has expired. Please sign in again.', 401);
}

// Extract user info from verified payload
$google_id = $payload['sub'] ?? '';
$email = strtolower($payload['email'] ?? '');
$name = $payload['name'] ?? '';
$avatar = $payload['picture'] ?? '';
$verified = ($payload['email_verified'] ?? '') === 'true';

if (!$google_id || !$email) {
    json_err('Incomplete Google profile data', 400);
}
if (!$verified) {
    json_err('Your Google email address is not verified', 400);
}

// ── Find or create user ───────────────────────────────────
$user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);

if (!$user) {
    // New user — auto-register via Google
    if (get_setting('registration_open', '1') !== '1') {
        json_err('New registrations are currently closed', 403);
    }

    // Create user (no password — Google handles auth)
    $uid = DB::insert(
        'INSERT INTO users (email, password_hash, name, google_id, avatar_url, active, role, plan)
         VALUES (?, ?, ?, ?, ?, 1, "user", "free")',
        [
            $email,
            password_hash(generate_token(32), PASSWORD_DEFAULT), // random password (can't log in with it)
            $name ?: explode('@', $email)[0],
            $google_id,
            $avatar,
        ]
    );

    $user = DB::one('SELECT * FROM users WHERE id = ?', [(int) $uid]);

    Security::auditLog('google_register', (int) $uid, ['email' => $email]);

    // Send welcome email
    send_email(
        $email,
        'Welcome to FateSpy! 🔮',
        "<div style='font-family:Inter,sans-serif;max-width:600px;margin:auto;background:#07071a;color:#f0f0ff;padding:40px;border-radius:16px'>
        <h1 style='color:#d4a853;font-family:serif'>Welcome to FateSpy, {$name}!</h1>
        <p>Your account was created using Google Sign-In.</p>
        <p><a href='" . SITE_URL . "/user-dashboard.html' style='background:linear-gradient(135deg,#d4a853,#8b5cf6);color:#fff;padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:600'>🔮 Go to My Dashboard</a></p>
        </div>"
    );
} else {
    // Existing user — update Google ID & avatar if missing
    if (empty($user['google_id'])) {
        DB::query(
            'UPDATE users SET google_id = ?, avatar_url = ? WHERE id = ?',
            [$google_id, $avatar, $user['id']]
        );
    }
    if (!$user['active']) {
        json_err('Your account has been deactivated. Contact support.', 403);
    }
}

// ── Create session ────────────────────────────────────────
$token = generate_token(40);
DB::insert(
    'INSERT INTO sessions (user_id, token, ip, ua, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))',
    [$user['id'], $token, Security::getIP(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]
);

// Suspicious login check (non-blocking)
ob_start();
check_suspicious_login((int) $user['id'], $token);
ob_end_clean();

Security::auditLog('google_login_success', (int) $user['id']);

json_ok([
    'token' => $token,
    'user' => [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'zodiac' => $user['zodiac'],
        'plan' => $user['plan'],
        'role' => $user['role'],
        'avatar' => $user['avatar_url'] ?? $avatar,
        'totp_active' => (bool) ($user['totp_enabled'] ?? false),
    ],
]);
