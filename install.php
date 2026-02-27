<?php
/**
 * FateSpy — Database Installer
 * Run once: https://fatespy.com/install.php?key=INSTALL_SECRET
 * DELETE this file after installation!
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$secret = 'CHANGE_THIS_INSTALL_SECRET'; // change before running!
if (($_GET['key'] ?? '') !== $secret) {
  http_response_code(403);
  die('Forbidden. Pass ?key=YOUR_SECRET');
}

$tables = [];

// ── Users ────────────────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`           VARCHAR(180) NOT NULL UNIQUE,
  `password_hash`   VARCHAR(255) NOT NULL,
  `name`            VARCHAR(120) DEFAULT '',
  `zodiac`          VARCHAR(30)  DEFAULT '',
  `dob`             DATE         DEFAULT NULL,
  `role`            ENUM('user','admin') NOT NULL DEFAULT 'user',
  `plan`            ENUM('free','vip')   NOT NULL DEFAULT 'free',
  `active`          TINYINT(1) NOT NULL DEFAULT 0,
  `verify_token`    VARCHAR(64)  DEFAULT NULL,
  `reset_token`     VARCHAR(64)  DEFAULT NULL,
  `reset_expires`   DATETIME     DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// ── Sessions ─────────────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `sessions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token`      VARCHAR(128) NOT NULL UNIQUE,
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `ua`         VARCHAR(255) DEFAULT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Settings (key-value) ─────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `settings` (
  `key`  VARCHAR(80)  NOT NULL PRIMARY KEY,
  `val`  TEXT         DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── API Keys (encrypted) ─────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `api_keys` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `provider`     VARCHAR(60)  NOT NULL UNIQUE,
  `key_enc`      TEXT         DEFAULT NULL,
  `model`        VARCHAR(100) DEFAULT NULL,
  `active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `last_tested`  DATETIME     DEFAULT NULL,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Horoscopes (pre-generated) ───────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `horoscopes` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `zodiac`       VARCHAR(30)  NOT NULL,
  `period`       ENUM('daily','weekly','monthly','yearly') NOT NULL,
  `content`      JSON         NOT NULL,
  `generated_at` DATE         NOT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_zodiac_period_date` (`zodiac`, `period`, `generated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Services catalogue ───────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `services` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slug`        VARCHAR(60)  NOT NULL UNIQUE,
  `name`        VARCHAR(120) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `price`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'cents',
  `currency`    VARCHAR(3)   NOT NULL DEFAULT 'usd',
  `recurring`   TINYINT(1)   NOT NULL DEFAULT 0,
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── User-owned services ──────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `user_services` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `service_slug` VARCHAR(60)  NOT NULL,
  `stripe_pi`    VARCHAR(120) DEFAULT NULL COMMENT 'Stripe PaymentIntent ID',
  `expires_at`   DATETIME     DEFAULT NULL COMMENT 'NULL = lifetime',
  `purchased_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_service` (`user_id`, `service_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── User images ──────────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `user_images` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `category`    ENUM('palm_left','palm_right','aura','coffee') NOT NULL,
  `filename`    VARCHAR(200) NOT NULL,
  `size_bytes`  INT UNSIGNED DEFAULT NULL,
  `reading_id`  INT UNSIGNED DEFAULT NULL COMMENT 'linked reading result',
  `uploaded_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_category` (`user_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Readings history ─────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `readings` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       ENUM('personal','palm','aura','coffee','tarot','natal') NOT NULL,
  `zodiac`     VARCHAR(30)  DEFAULT NULL,
  `input_data` JSON         DEFAULT NULL,
  `content`    JSON         NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_type` (`user_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Email templates ──────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `email_templates` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slug`       VARCHAR(80)  NOT NULL UNIQUE,
  `name`       VARCHAR(120) NOT NULL,
  `subject`    VARCHAR(255) NOT NULL,
  `preheader`  VARCHAR(255) DEFAULT '',
  `body_html`  LONGTEXT     NOT NULL,
  `trigger`    VARCHAR(80)  DEFAULT NULL COMMENT 'event that fires this template',
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Email log ────────────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `email_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `to_email`    VARCHAR(180) NOT NULL,
  `template_id` INT UNSIGNED DEFAULT NULL,
  `subject`     VARCHAR(255) NOT NULL,
  `status`      ENUM('sent','delivered','opened','bounced','failed') NOT NULL DEFAULT 'sent',
  `sent_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email_status` (`to_email`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Rate Limits ───────────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `rate_limits` (
  `key`        VARCHAR(128) NOT NULL PRIMARY KEY,
  `hits`       INT UNSIGNED NOT NULL DEFAULT 1,
  `expires_at` DATETIME     NOT NULL,
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Login Attempts (brute force) ──────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`        VARCHAR(180) NOT NULL,
  `ip`           VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email_time` (`email`, `attempted_at`),
  INDEX `idx_ip_time`    (`ip`,    `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Audit Log ─────────────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `action`     VARCHAR(80)  NOT NULL,
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `ua`         VARCHAR(255) DEFAULT NULL,
  `context`    JSON         DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_action` (`user_id`, `action`),
  INDEX `idx_created`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── TOTP 2FA + Google OAuth columns (alter users table) ──
$tables[] = "ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `totp_secret`  TEXT DEFAULT NULL COMMENT 'libsodium encrypted',
  ADD COLUMN IF NOT EXISTS `totp_backup`  TEXT DEFAULT NULL COMMENT 'JSON array of bcrypt hashed backup codes',
  ADD COLUMN IF NOT EXISTS `google_id`    VARCHAR(128) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `avatar_url`   VARCHAR(500) DEFAULT NULL";

// ── Consent Log (GDPR) ────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `consent_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(50)  NOT NULL,
  `granted`    TINYINT(1)   NOT NULL DEFAULT 0,
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_consent` (`user_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// ── Audit Log Archive ─────────────────────────────────────
$tables[] = "CREATE TABLE IF NOT EXISTS `audit_log_archive` LIKE `audit_log`";

// ── Logs directory (htaccess protection) ─────────────────
@mkdir(__DIR__ . '/logs', 0750, true);
@file_put_contents(__DIR__ . '/logs/.htaccess', "Order Allow,Deny\nDeny from all\n");

// ── Execute ──────────────────────────────────────────────
$db = DB::get();
$errors = [];
foreach ($tables as $sql) {
  try {
    $db->exec($sql);
  } catch (PDOException $e) {
    $errors[] = $e->getMessage();
  }
}

// ── Seed default data ─────────────────────────────────────
$seeds = [
  // Default admin user (change password immediately!)
  "INSERT IGNORE INTO `users` (email, password_hash, name, role, active)
     VALUES ('admin@fatespy.com', '" . password_hash('Admin1234!', PASSWORD_DEFAULT) . "', 'Administrator', 'admin', 1)",

  // Default settings
  "INSERT IGNORE INTO `settings` (`key`, val) VALUES ('site_name', 'FateSpy')",
  "INSERT IGNORE INTO `settings` (`key`, val) VALUES ('groq_api_key', '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')",
  "INSERT IGNORE INTO `settings` (`key`, val) VALUES ('default_ai_provider', 'groq')",
  "INSERT IGNORE INTO `settings` (`key`, val) VALUES ('gdpr_enabled', '1')",
  "INSERT IGNORE INTO `settings` (`key`, val) VALUES ('registration_open', '1')",

  // Services catalogue
  "INSERT IGNORE INTO `services` (slug,name,price,description,sort_order) VALUES
     ('palm_reading','Palm Reading (AI)',499,'AI analysis of your left and right palm lines',1),
     ('aura_scan','Aura Scan (AI)',499,'AI decodes colors and energy of your aura from a selfie',2),
     ('coffee_reading','Coffee Reading (AI)',299,'AI interprets symbols in your coffee cup',3),
     ('natal_chart','Personal Natal Chart',999,'Comprehensive 20-page natal chart analysis',4),
     ('tarot_session','AI Tarot Session',399,'Full 10-card Celtic Cross spread with AI interpretation',5),
     ('year_report','Year Ahead Report',2999,'Detailed 12-month personalized forecast',6),
     ('vip_monthly','VIP Membership',999,'Unlimited access to all services monthly',7)",

  // Default email templates
  "INSERT IGNORE INTO `email_templates` (slug,name,subject,preheader,body_html,`trigger`) VALUES
     ('welcome','Welcome Email','Welcome to FateSpy, {{name}}! &#128302;','Your cosmic journey starts now.',
      '<h1>Welcome, {{name}}!</h1><p>Your zodiac sign is <strong>{{zodiac}}</strong>. Explore your destiny at <a href=\"{{site_url}}\">FateSpy</a>.</p><p><a href=\"{{horoscope_link}}\">View My Horoscope</a></p><p style=\"font-size:12px\"><a href=\"{{unsubscribe_link}}\">Unsubscribe</a></p>',
      'register'),
     ('verify_email','Email Verification','Verify your FateSpy account','Click the link to activate your account.',
      '<h1>Verify your email</h1><p>Click below to activate your FateSpy account, {{name}}:</p><p><a href=\"{{verify_url}}\">Activate Account</a></p><p>Link expires in 24 hours.</p>',
      'register'),
     ('weekly_digest','Weekly Horoscope','Your {{zodiac}} weekly horoscope &#127775;','Your stars for the week ahead.',
      '<h1>Your {{zodiac}} Horoscope for This Week</h1><p>{{horoscope_text}}</p><p><a href=\"{{horoscope_link}}\">Read Full Horoscope</a></p><p><a href=\"{{unsubscribe_link}}\">Unsubscribe</a></p>',
      'cron_weekly'),
     ('password_reset','Reset your FateSpy password','Password reset request','Use this link to reset your password.',
      '<h1>Password Reset</h1><p>Click the link below to reset your password. This link expires in 1 hour.</p><p><a href=\"{{reset_url}}\">Reset Password</a></p><p>If you didn''t request this, ignore this email.</p>',
      'user_request')",

  // Default API key providers
  "INSERT IGNORE INTO `api_keys` (provider, model) VALUES
     ('openai','gpt-4o-mini'),('groq','llama-3.3-70b-versatile'),('gemini','gemini-2.0-flash'),
     ('grok','grok-2'),('claude','claude-3-5-sonnet'),('mistral','mistral-large'),
     ('minimax','minimax-01'),('kimi','moonshot-v1-128k'),('cohere','command-r-plus'),('together','meta-llama/Llama-3-70b')",
];

foreach ($seeds as $sql) {
  try {
    $db->exec($sql);
  } catch (PDOException $e) {
    $errors[] = 'Seed: ' . $e->getMessage();
  }
}

// ── Result ───────────────────────────────────────────────
echo '<html><head><meta charset="utf-8"><style>body{font-family:monospace;background:#07071a;color:#f0f0ff;padding:40px}
h1{color:#d4a853}pre{background:#0d0f2a;padding:16px;border-radius:8px;color:#10b981}
.err{color:#ef4444}.ok{color:#10b981}</style></head><body>';
echo '<h1>&#128302; FateSpy — Database Install</h1>';
if (empty($errors)) {
  echo '<pre class="ok">&#10003; All tables created successfully!</pre>';
  echo '<pre class="ok">&#10003; Seed data inserted!</pre>';
  echo '<p>Default admin: <strong>admin@fatespy.com</strong> / <strong>Admin1234!</strong></p>';
  echo '<p class="err">&#9888; CHANGE the admin password immediately after login!</p>';
  echo '<p class="err">&#9888; DELETE this file (install.php) after setup is complete!</p>';
} else {
  echo '<pre class="err">Errors:<br>' . implode("\n", $errors) . '</pre>';
}
echo '</body></html>';
