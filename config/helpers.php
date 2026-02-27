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
    $key = get_setting('groq_api_key') ?: GROQ_API_URL;
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
            'Authorization: Bearer ' . get_setting('groq_api_key', ''),
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
    return mail($to, $subject, $html, $headers);
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
