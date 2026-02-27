<?php
/**
 * POST /api/admin/generate.php
 * Trigger horoscope generation from admin panel
 * Query params: ?periods=daily,weekly  &force=1
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['POST']);
require_admin();

// Delegate to the cron script (it detects web call and handles admin auth)
$_SERVER['REQUEST_METHOD'] = 'POST';
require __DIR__ . '/../../cron/generate-horoscopes.php';
