<?php
/**
 * GET /api/public-config.php
 * Returns public configuration values (Google Client ID, etc.)
 * No authentication required
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['GET']);

$config = [
    'google_client_id' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '',
    'site_name' => SITE_NAME,
    'site_url' => SITE_URL,
];

json_ok($config);
