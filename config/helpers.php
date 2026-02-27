<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// ── CORS & JSON headers ──────────────────────────────────
function set_json_headers(array $allowed_methods = ['GET', 'POST', 'OPTIONS']): void
{
    Security::setSecurityHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . SITE_URL);
    header('Access-Control-Allow-Methods: ' . implode(', ', $allowed_methods));
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Response helpers ────────────────────────────────────
function json_ok(mixed $data = null, int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Body parser ─────────────────────────────────────────
function get_body(): array
{
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

// ── Token auth ──────────────────────────────────────────
function require_auth(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($header, 'Bearer ')) {
        json_err('Unauthorized', 401);
    }
    $token = substr($header, 7);
    $session = DB::one(
        'SELECT s.*, u.id as user_id, u.email, u.name, u.plan, u.role
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW() AND u.active = 1',
        [$token]
    );
    if (!$session)
        json_err('Unauthorized', 401);
    // slide session TTL
    DB::query('UPDATE sessions SET expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE token = ?', [$token]);
    return $session;
}

function require_admin(): array
{
    $user = require_auth();
    if ($user['role'] !== 'admin')
        json_err('Forbidden', 403);
    return $user;
}

// ── Token generator ─────────────────────────────────────
function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

// ── AI call ─────────────────────────────────────────────
function call_groq(string $prompt, string $system = 'You are FateSpy, a professional astrologer. Respond ONLY with valid JSON.', float $temp = 0.88, int $max_tokens = 1200): ?array
{
    $api_key = get_setting('groq_api_key', '');
    if (!$api_key)
        return null;

    $payload = json_encode([
        'model' => GROQ_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => $temp,
        'max_tokens' => $max_tokens,
        'response_format' => ['type' => 'json_object'],
    ]);

    $ch = curl_init(GROQ_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp)
        return null;
    $json = json_decode($resp, true);
    $text = $json['choices'][0]['message']['content'] ?? null;
    if (!$text)
        return null;
    return json_decode($text, true);
}

// ── Settings helper ─────────────────────────────────────
function get_setting(string $key, mixed $default = null): mixed
{
    $row = DB::one('SELECT val FROM settings WHERE `key` = ?', [$key]);
    return $row ? $row['val'] : $default;
}
function set_setting(string $key, mixed $value): void
{
    DB::query(
        'INSERT INTO settings (`key`, val) VALUES (?, ?) ON DUPLICATE KEY UPDATE val = VALUES(val)',
        [$key, $value]
    );
}

// ── Simple email via mail() ──────────────────────────────
function send_email(string $to, string $subject, string $html): bool
{
    $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $sent = mail($to, $subject, $html, $headers);

    // Log to email_log table
    try {
        DB::insert(
            'INSERT INTO email_log (to_email, subject, status) VALUES (?, ?, ?)',
            [$to, $subject, $sent ? 'sent' : 'failed']
        );
    } catch (\Throwable) {
    }

    return $sent;
}

// ── Template-based email ────────────────────────────────
function send_template_email(string $slug, string $to, array $vars = []): bool
{
    $tpl = DB::one('SELECT subject, preheader, body_html, active FROM email_templates WHERE slug = ?', [$slug]);
    if (!$tpl || !$tpl['active'])
        return false;

    // Default variables available in all templates
    $vars = array_merge([
        'site_url' => SITE_URL,
        'site_name' => SITE_NAME,
        'year' => date('Y'),
        'unsubscribe_link' => SITE_URL . '/user-dashboard.html#profile',
    ], $vars);

    // Replace {{variable}} placeholders
    $subject = $tpl['subject'];
    $body = $tpl['body_html'];
    foreach ($vars as $key => $val) {
        $subject = str_replace('{{' . $key . '}}', htmlspecialchars((string) $val), $subject);
        $body = str_replace('{{' . $key . '}}', htmlspecialchars((string) $val), $body);
    }

    // Wrap in base email layout
    $html = email_layout($body, $tpl['preheader'] ?? '');

    $sent = send_email($to, $subject, $html);

    // Update log with template_id
    try {
        DB::query(
            'UPDATE email_log SET template_id = (SELECT id FROM email_templates WHERE slug = ?) WHERE to_email = ? ORDER BY id DESC LIMIT 1',
            [$slug, $to]
        );
    } catch (\Throwable) {
    }

    return $sent;
}

// ── Email HTML layout wrapper ──────────────────────────
function email_layout(string $body_html, string $preheader = ''): string
{
    $site = SITE_NAME;
    $url = SITE_URL;
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{$site}</title></head>
<body style="margin:0;padding:0;background:#07071a;font-family:'Segoe UI',Arial,sans-serif">
<span style="display:none!important;font-size:0;line-height:0;max-height:0;max-width:0;overflow:hidden">{$preheader}</span>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#07071a;padding:40px 20px">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#0d0f2a;border-radius:16px;border:1px solid rgba(212,168,83,0.2)">
<tr><td style="padding:32px 40px;text-align:center;border-bottom:1px solid rgba(212,168,83,0.15)">
  <img src="{$url}/assets/images/logo.png" alt="{$site}" width="140" style="max-width:140px">
</td></tr>
<tr><td style="padding:40px;color:#e0e0f0;font-size:16px;line-height:1.6">
  {$body_html}
</td></tr>
<tr><td style="padding:24px 40px;text-align:center;border-top:1px solid rgba(212,168,83,0.15);color:#666;font-size:12px">
  <p style="margin:0">&copy; {$year} {$site}. All rights reserved.</p>
  <p style="margin:8px 0 0"><a href="{$url}/privacy.html" style="color:#d4a853;text-decoration:none">Privacy</a> &middot; <a href="{$url}/terms.html" style="color:#d4a853;text-decoration:none">Terms</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

// ── AI call (raw text response, no JSON) ────────────────
function call_groq_text(string $prompt, string $system = 'You are FateSpy, a mystical astrologer.', float $temp = 0.88, int $max_tokens = 1500): ?string
{
    $api_key = get_setting('groq_api_key', '');
    if (!$api_key)
        return null;

    $payload = json_encode([
        'model' => GROQ_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => $temp,
        'max_tokens' => $max_tokens,
    ]);

    $ch = curl_init(GROQ_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp)
        return null;
    $json = json_decode($resp, true);
    return $json['choices'][0]['message']['content'] ?? null;
}

// ── Upload helper ────────────────────────────────────────
function handle_upload(string $field, string $subdir): string
{
    if (empty($_FILES[$field]))
        json_err('No file uploaded');
    $file = $_FILES[$field];

    // Strict MIME + dimension validation
    $err = Security::validateFileUpload($file);
    if ($err)
        json_err($err);

    // Extension from MIME — never trust original filename
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic'];
    $ext = $ext_map[$mime] ?? 'jpg';

    $dir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir))
        mkdir($dir, 0750, true);

    // Cryptographically random filename — no path traversal possible
    $name = generate_token(20) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name))
        json_err('Failed to save file');
    @chmod($dir . $name, 0640);
    return $subdir . '/' . $name;
}
