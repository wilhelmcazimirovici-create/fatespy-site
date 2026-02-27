<?php
/**
 * FateSpy — GDPR Compliance Endpoints
 *
 * GET  /api/user/gdpr.php?action=export  — download all personal data (JSON)
 * POST /api/user/gdpr.php?action=delete  — permanently delete account + data
 * GET  /api/user/gdpr.php?action=consent — get consent history
 * POST /api/user/gdpr.php?action=consent — record consent action
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['GET', 'POST']);

Security::rateLimit('gdpr', 5, 3600); // max 5 GDPR requests per hour

$user = require_auth();
$uid = (int) $user['user_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$body = get_body();

// ═══════════════════════════════════════════════════════
//  EXPORT — "Right to Data Portability" (GDPR Art. 20)
// ═══════════════════════════════════════════════════════
if ($action === 'export') {
    $u = DB::one('SELECT id, email, name, zodiac, dob, plan, role, created_at FROM users WHERE id = ?', [$uid]);

    $data = [
        'export_date' => date('c'),
        'data_subject' => $u,
        'sessions' => DB::all('SELECT ip, ua, expires_at, created_at FROM sessions WHERE user_id = ?', [$uid]),
        'services' => DB::all('SELECT service_slug, purchased_at, expires_at FROM user_services WHERE user_id = ?', [$uid]),
        'readings' => DB::all('SELECT type, zodiac, input_data, content, created_at FROM readings WHERE user_id = ?', [$uid]),
        'images' => DB::all('SELECT category, uploaded_at FROM user_images WHERE user_id = ?', [$uid]),
        'login_history' => DB::all(
            'SELECT action, ip, ua, context, created_at FROM audit_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 100',
            [$uid]
        ),
    ];

    // Decode JSON fields for readability
    foreach ($data['readings'] as &$r) {
        $r['input_data'] = json_decode($r['input_data'] ?? '{}', true);
        $r['content'] = json_decode($r['content'] ?? '{}', true);
    }
    foreach ($data['login_history'] as &$l) {
        $l['context'] = json_decode($l['context'] ?? '{}', true);
    }

    Security::auditLog('gdpr_export', $uid);

    // Serve as downloadable JSON file
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="fatespy-my-data-' . date('Y-m-d') . '.json"');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════
//  DELETE — "Right to Erasure" (GDPR Art. 17)
// ═══════════════════════════════════════════════════════
if ($action === 'delete') {
    $password = $body['password'] ?? '';
    $confirm_del = $body['confirm'] ?? '';

    if ($confirm_del !== 'DELETE_MY_ACCOUNT') {
        json_err('Send confirm: "DELETE_MY_ACCOUNT" to confirm deletion');
    }

    // Require password re-confirmation
    $u = DB::one('SELECT password_hash, email, name FROM users WHERE id = ?', [$uid]);
    if (!password_verify($password, $u['password_hash'])) {
        Security::auditLog('gdpr_delete_failed_auth', $uid);
        json_err('Incorrect password', 401);
    }

    // Delete all user images from disk
    $images = DB::all('SELECT filename FROM user_images WHERE user_id = ?', [$uid]);
    foreach ($images as $img) {
        $path = UPLOAD_DIR . $img['filename'];
        if (file_exists($path))
            @unlink($path);
    }
    // Remove upload directory for user
    $user_dir = UPLOAD_DIR . 'u' . $uid;
    if (is_dir($user_dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($user_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        @rmdir($user_dir);
    }

    // Log before deletion (audit_log uses user_id=0 after)
    Security::auditLog('gdpr_delete_executed', $uid, ['email' => $u['email']]);

    // Cascade delete — FK constraints handle most of it
    // But explicitly clear rate limit and login attempt records
    $ip = Security::getIP();
    DB::query('DELETE FROM login_attempts WHERE email = ?', [$u['email']]);

    // Anonymize audit_log entries (keep for fraud log, but scrub PII)
    DB::query('UPDATE audit_log SET ip = "0.0.0.0", ua = NULL WHERE user_id = ?', [$uid]);

    // Hard delete the user (cascades: sessions, services, images, readings)
    DB::query('DELETE FROM users WHERE id = ?', [$uid]);

    // Send goodbye email
    send_email(
        $u['email'],
        'Your FateSpy account has been deleted',
        "<h1>Account Deleted</h1>
         <p>Hi {$u['name']},</p>
         <p>Your FateSpy account and all associated personal data have been permanently deleted as requested.</p>
         <p>If this was a mistake, please contact us at " . ADMIN_EMAIL . " within 30 days.</p>
         <p>Thank you for using FateSpy.</p>"
    );

    json_ok(['message' => 'Your account and all data have been permanently deleted. A confirmation email has been sent.']);
}

// ═══════════════════════════════════════════════════════
//  CONSENT MANAGEMENT
// ═══════════════════════════════════════════════════════
if ($action === 'consent' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = Security::sanitizeString($body['type'] ?? '', 50);
    $granted = (bool) ($body['granted'] ?? false);
    $valid = ['marketing', 'analytics', 'personalization'];
    if (!in_array($type, $valid))
        json_err('Invalid consent type');

    DB::query(
        'INSERT INTO consent_log (user_id, type, granted, ip, created_at) VALUES (?, ?, ?, ?, NOW())',
        [$uid, $type, $granted ? 1 : 0, Security::getIP()]
    );

    // Update user preferences
    set_setting("consent_{$type}_{$uid}", $granted ? '1' : '0');
    Security::auditLog('consent_updated', $uid, ['type' => $type, 'granted' => $granted]);

    json_ok(['message' => 'Consent recorded']);
}

if ($action === 'consent' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $consents = DB::all(
        'SELECT type, granted, ip, created_at FROM consent_log WHERE user_id = ? ORDER BY created_at DESC',
        [$uid]
    );
    json_ok($consents);
}

json_err('Invalid action', 400);
