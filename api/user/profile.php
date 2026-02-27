<?php
/**
 * GET  /api/user/profile.php  — get current user profile + owned services
 * POST /api/user/profile.php  — update profile
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Owned services
    $owned = DB::all(
        'SELECT service_slug, purchased_at, expires_at FROM user_services
         WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY purchased_at DESC',
        [$user['user_id']]
    );

    // Stats
    $stats = DB::one(
        'SELECT COUNT(*) as total_readings FROM readings WHERE user_id = ?',
        [$user['user_id']]
    );

    $profile = DB::one(
        'SELECT id, email, name, zodiac, dob, plan, role, created_at FROM users WHERE id = ?',
        [$user['user_id']]
    );

    json_ok([
        'user' => $profile,
        'services' => array_column($owned, null, 'service_slug'),
        'stats' => $stats,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = get_body();
    $allowed = ['name', 'zodiac', 'dob'];
    $fields = [];
    $params = [];

    foreach ($allowed as $f) {
        if (isset($body[$f])) {
            $fields[] = "`{$f}` = ?";
            $params[] = $body[$f];
        }
    }
    if (empty($fields))
        json_err('Nothing to update');

    $params[] = $user['user_id'];
    DB::query('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

    json_ok(['message' => 'Profile updated']);
}
