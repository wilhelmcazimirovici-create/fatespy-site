<?php
/**
 * FateSpy — Security Layer
 * Handles: Rate Limiting, CSRF, Encryption, Brute Force, IP blocks
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class Security
{

    // ═══════════════════════════════════════════════════
    //  RATE LIMITING
    // ═══════════════════════════════════════════════════

    /**
     * Check rate limit. Exits with 429 if exceeded.
     * @param string $key    Unique action key (e.g. "login:{ip}")
     * @param int    $limit  Max requests allowed
     * @param int    $window Seconds window
     */
    public static function rateLimit(string $key, int $limit, int $window = 60): void
    {
        $ip = self::getIP();
        $key = $key . ':' . md5($ip);

        try {
            // Clean expired
            DB::query('DELETE FROM rate_limits WHERE expires_at < NOW()');

            $row = DB::one('SELECT hits, expires_at FROM rate_limits WHERE `key` = ?', [$key]);

            if (!$row) {
                DB::query(
                    'INSERT INTO rate_limits (`key`, hits, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))',
                    [$key, $window]
                );
            } else {
                if ($row['hits'] >= $limit) {
                    self::auditLog('rate_limit_exceeded', 0, ['key' => $key, 'hits' => $row['hits']]);
                    http_response_code(429);
                    header('Retry-After: ' . $window);
                    echo json_encode(['ok' => false, 'error' => 'Too many requests. Please try again later.']);
                    exit;
                }
                DB::query('UPDATE rate_limits SET hits = hits + 1 WHERE `key` = ?', [$key]);
            }
        } catch (\Throwable $e) {
            // Don't block if rate limit table has issue
        }
    }

    // ═══════════════════════════════════════════════════
    //  BRUTE FORCE PROTECTION
    // ═══════════════════════════════════════════════════

    public static function recordFailedLogin(string $email): void
    {
        $ip = self::getIP();
        DB::query(
            'INSERT INTO login_attempts (email, ip, attempted_at) VALUES (?, ?, NOW())',
            [strtolower($email), $ip]
        );
        self::auditLog('login_failed', 0, ['email' => $email, 'ip' => $ip]);
    }

    public static function checkBruteForce(string $email): void
    {
        $ip = self::getIP();

        // Max 10 failures by email in 15 minutes
        $by_email = DB::one(
            'SELECT COUNT(*) as n FROM login_attempts WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            [strtolower($email)]
        );
        if ((int) $by_email['n'] >= 10) {
            self::auditLog('brute_force_blocked', 0, ['email' => $email, 'source' => 'email']);
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => 'Too many failed attempts. Please wait 15 minutes or reset your password.']);
            exit;
        }

        // Max 20 failures by IP in 15 minutes
        $by_ip = DB::one(
            'SELECT COUNT(*) as n FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            [$ip]
        );
        if ((int) $by_ip['n'] >= 20) {
            self::auditLog('brute_force_blocked', 0, ['ip' => $ip, 'source' => 'ip']);
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => 'Too many requests from your IP. Please wait 15 minutes.']);
            exit;
        }
    }

    public static function clearLoginAttempts(string $email): void
    {
        DB::query('DELETE FROM login_attempts WHERE email = ?', [strtolower($email)]);
    }

    // ═══════════════════════════════════════════════════
    //  CSRF PROTECTION
    // ═══════════════════════════════════════════════════

    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        $stored = $_SESSION['csrf_token'] ?? '';
        return $stored && hash_equals($stored, $token);
    }

    /**
     * Validate CSRF for form submissions (not needed for JSON Bearer-token APIs)
     */
    public static function requireCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        if (!self::validateCsrf($token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF validation failed']);
            exit;
        }
    }

    // ═══════════════════════════════════════════════════
    //  ENCRYPTION (for API keys and sensitive data in DB)
    // ═══════════════════════════════════════════════════

    public static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
        sodium_memzero($plaintext);
        return base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $ciphertext): ?string
    {
        try {
            $key = self::getEncryptionKey();
            $data = base64_decode($ciphertext);
            $nonce = substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            return $plain === false ? null : $plain;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function getEncryptionKey(): string
    {
        $secret = JWT_SECRET;
        if (strlen($secret) < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $secret = str_pad($secret, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $secret);
        }
        return substr($secret, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    // ═══════════════════════════════════════════════════
    //  INPUT VALIDATION & SANITIZATION
    // ═══════════════════════════════════════════════════

    public static function sanitizeString(string $input, int $maxLen = 255): string
    {
        $s = trim($input);
        $s = strip_tags($s);
        $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return mb_substr($s, 0, $maxLen);
    }

    public static function sanitizeEmail(string $email): ?string
    {
        $e = trim(strtolower($email));
        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    public static function sanitizeInt(mixed $val, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
    {
        $i = filter_var($val, FILTER_VALIDATE_INT);
        if ($i === false)
            return null;
        $i = (int) $i;
        if ($i < $min || $i > $max)
            return null;
        return $i;
    }

    public static function validatePassword(string $password): ?string
    {
        if (strlen($password) < 8)
            return 'Password must be at least 8 characters';
        if (!preg_match('/[A-Z]/', $password))
            return 'Password must contain at least one uppercase letter';
        if (!preg_match('/[0-9]/', $password))
            return 'Password must contain at least one number';
        return null; // valid
    }

    public static function validateFileUpload(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Upload error: ' . $file['error'];
        }
        $maxBytes = UPLOAD_MAX_MB * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            return 'File too large (max ' . UPLOAD_MAX_MB . 'MB)';
        }
        // Validate MIME via finfo (not just extension)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];
        if (!in_array($mime, $allowed)) {
            return 'Invalid file type. Allowed: JPEG, PNG, WebP, HEIC';
        }
        // Check dimensions / not a PHP file disguised as image
        $img = @getimagesize($file['tmp_name']);
        if (!$img)
            return 'File does not appear to be a valid image';
        if ($img[0] > 8000 || $img[1] > 8000)
            return 'Image resolution too large (max 8000×8000)';
        return null; // valid
    }

    // ═══════════════════════════════════════════════════
    //  AUDIT LOG
    // ═══════════════════════════════════════════════════

    public static function auditLog(string $action, int $userId = 0, array $context = []): void
    {
        try {
            DB::query(
                'INSERT INTO audit_log (user_id, action, ip, ua, context, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    $userId ?: 0,
                    $action,
                    self::getIP(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    json_encode($context),
                ]
            );
        } catch (\Throwable) {
            // Never let audit logging break the main flow
        }
    }

    // ═══════════════════════════════════════════════════
    //  SECURITY HEADERS
    // ═══════════════════════════════════════════════════

    public static function setSecurityHeaders(): void
    {
        // Prevent MIME sniffing
        header('X-Content-Type-Options: nosniff');
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        // XSS protection (legacy)
        header('X-XSS-Protection: 1; mode=block');
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // No powered-by leakage
        header_remove('X-Powered-By');
        // HSTS (1 year, include subdomains)
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        // Permissions policy
        header('Permissions-Policy: geolocation=(self), camera=(self), microphone=(), payment=(self "https://js.stripe.com")');
        // Content Security Policy
        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://js.stripe.com https://fonts.googleapis.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "img-src 'self' data: https:; " .
            "connect-src 'self' https://api.groq.com https://api.open-meteo.com https://nominatim.openstreetmap.org https://api.stripe.com; " .
            "frame-src https://js.stripe.com https://hooks.stripe.com; " .
            "form-action 'self'; " .
            "base-uri 'self'; " .
            "object-src 'none';"
        );
    }

    // ═══════════════════════════════════════════════════
    //  IP HELPER
    // ═══════════════════════════════════════════════════

    public static function getIP(): string
    {
        // Trust Cloudflare / proxy headers only from known proxies
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            $ip = $_SERVER[$h] ?? '';
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    // ═══════════════════════════════════════════════════
    //  HONEYPOT (bot detection for forms)
    // ═══════════════════════════════════════════════════

    public static function checkHoneypot(array $body): void
    {
        // A field named 'website' should always be empty (bots fill it)
        if (!empty($body['website'])) {
            http_response_code(200); // pretend success to fool bots
            echo json_encode(['ok' => true, 'data' => null]);
            exit;
        }
    }
}
