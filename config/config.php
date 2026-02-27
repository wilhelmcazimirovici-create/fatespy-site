<?php
// ══════════════════════════════════════════════════
//  FateSpy — Central Configuration
//  Edit this file after uploading to hostico.ro
// ══════════════════════════════════════════════════

// ── Database (fill in after creating MySQL DB in cPanel) ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'fatespy_db');
define('DB_USER', 'fatespy_user');
define('DB_PASS', 'CHANGE_THIS_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

// ── Site ──
define('SITE_URL', 'https://fatespy.com');
define('SITE_NAME', 'FateSpy');
define('ADMIN_EMAIL', 'admin@fatespy.com');

// ── Security ──
define('JWT_SECRET', 'CHANGE_THIS_RANDOM_SECRET_32CHARS');
define('SESSION_LIFETIME', 86400 * 30);   // 30 days
define('UPLOAD_MAX_MB', 8);

// ── Upload paths (relative to document root) ──
define('UPLOAD_DIR', __DIR__ . '/../uploads/images/');
define('UPLOAD_URL', SITE_URL . '/uploads/images/');

// ── AI (defaults — admin can override via DB settings) ──
define('DEFAULT_AI_PROVIDER', 'groq');
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');

// ── Email (SMTP via PHPMailer or mail()) ──
define('SMTP_HOST', 'mail.hostico.ro');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@fatespy.com');
define('SMTP_PASS', 'CHANGE_THIS');
define('FROM_NAME', 'FateSpy');
define('FROM_EMAIL', 'noreply@fatespy.com');

// ── Stripe ──
define('STRIPE_SK', 'sk_live_CHANGE_THIS');
define('STRIPE_PK', 'pk_live_CHANGE_THIS');
define('STRIPE_WEBHOOK_SECRET', 'whsec_CHANGE_THIS');

// ── Google OAuth ──
// Get from: console.cloud.google.com → APIs & Services → Credentials → OAuth 2.0 Client IDs
define('GOOGLE_CLIENT_ID', 'CHANGE_THIS.apps.googleusercontent.com');

// ── Environment ──
define('DEBUG', false);  // set true on dev, false on production

// ── Plan prices ──
define('PLANS', [
    'palm_reading' => ['name' => 'Palm Reading (AI)', 'price' => 499, 'currency' => 'usd'],
    'aura_scan' => ['name' => 'Aura Scan (AI)', 'price' => 499, 'currency' => 'usd'],
    'coffee_reading' => ['name' => 'Coffee Reading (AI)', 'price' => 299, 'currency' => 'usd'],
    'natal_chart' => ['name' => 'Personal Natal Chart', 'price' => 999, 'currency' => 'usd'],
    'tarot_session' => ['name' => 'AI Tarot Session', 'price' => 399, 'currency' => 'usd'],
    'year_report' => ['name' => 'Year Ahead Report', 'price' => 2999, 'currency' => 'usd'],
    'vip_monthly' => ['name' => 'VIP Membership', 'price' => 999, 'currency' => 'usd', 'recurring' => true],
]);

if (DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
