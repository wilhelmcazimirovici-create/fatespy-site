<?php
/**
 * GET  /api/admin/settings.php         — get all settings
 * POST /api/admin/settings.php         — update setting(s)
 * GET  /api/admin/settings.php?stats=1 — dashboard stats
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Dashboard stats
    if ($_GET['stats'] ?? false) {
        $stats = [
            'total_users' => (int) DB::one('SELECT COUNT(*) n FROM users')['n'],
            'vip_users' => (int) DB::one('SELECT COUNT(*) n FROM users WHERE plan="vip"')['n'],
            'total_readings' => (int) DB::one('SELECT COUNT(*) n FROM readings')['n'],
            'readings_today' => (int) DB::one('SELECT COUNT(*) n FROM readings WHERE DATE(created_at)=CURDATE()')['n'],
            'emails_sent' => (int) DB::one('SELECT COUNT(*) n FROM email_log')['n'],
            'emails_month' => (int) DB::one('SELECT COUNT(*) n FROM email_log WHERE MONTH(sent_at)=MONTH(NOW())')['n'],
            'horoscopes_fresh' => (int) DB::one('SELECT COUNT(*) n FROM horoscopes WHERE generated_at=CURDATE()')['n'],
        ];
        json_ok($stats);
    }

    // All key-value settings (mask API keys)
    $rows = DB::all('SELECT `key`, val, updated_at FROM settings ORDER BY `key`');
    foreach ($rows as &$r) {
        if (str_ends_with($r['key'], '_api_key') && strlen($r['val'] ?? '') > 8) {
            $r['val_masked'] = '****' . substr($r['val'], -4);
            unset($r['val']);
        }
    }
    // Also return API key providers
    $apiKeys = DB::all('SELECT provider, model, active, last_tested FROM api_keys ORDER BY provider');
    json_ok(['settings' => $rows, 'api_keys' => $apiKeys]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = get_body();

    // Handle API keys update
    if (!empty($body['api_keys']) && is_array($body['api_keys'])) {
        foreach ($body['api_keys'] as $provider => $keyval) {
            $provider = preg_replace('/[^a-z0-9_]/', '', strtolower($provider));
            DB::query(
                'INSERT INTO api_keys (provider, key_enc) VALUES (?,?) ON DUPLICATE KEY UPDATE key_enc=VALUES(key_enc), updated_at=NOW()',
                [$provider, $keyval]
            );
            // Also save to settings for easy lookup
            set_setting("{$provider}_api_key", $keyval);
        }
    }

    // Handle generic settings
    if (!empty($body['settings']) && is_array($body['settings'])) {
        foreach ($body['settings'] as $key => $val) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
            set_setting($key, $val);
        }
    }

    json_ok(['message' => 'Settings saved']);
}
