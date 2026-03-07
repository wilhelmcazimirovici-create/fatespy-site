<?php
/**
 * GET /api/user/notifications.php
 * POST /api/user/notifications.php
 * Manage user notifications and email subscriptions
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);

$user = require_auth();
$uid = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user notification preferences
    $prefs = DB::one(
        'SELECT daily_horoscope, weekly_summary, monthly_forecast, promotional FROM notification_preferences WHERE user_id = ?',
        [$uid]
    );
    
    if (!$prefs) {
        // Create default preferences
        DB::insert(
            'INSERT INTO notification_preferences (user_id, daily_horoscope, weekly_summary, monthly_forecast, promotional) VALUES (?, 1, 1, 1, 0)',
            [$uid]
        );
        $prefs = [
            'daily_horoscope' => 1,
            'weekly_summary' => 1,
            'monthly_forecast' => 1,
            'promotional' => 0
        ];
    }
    
    // Get recent notifications
    $notifications = DB::all(
        'SELECT id, type, title, message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
        [$uid]
    );
    
    json_ok([
        'preferences' => $prefs,
        'notifications' => $notifications
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = get_body();
    
    // Update notification preferences
    $allowed = ['daily_horoscope', 'weekly_summary', 'monthly_forecast', 'promotional'];
    $updates = [];
    $params = [];
    
    foreach ($allowed as $key) {
        if (isset($body[$key])) {
            $updates[] = "{$key} = ?";
            $params[] = (int) $body[$key];
        }
    }
    
    if (empty($updates)) {
        json_err('No preferences to update');
    }
    
    $params[] = $uid;
    
    // Insert or update preferences
    DB::query(
        'INSERT INTO notification_preferences (user_id, ' . implode(', ', $allowed) . ') 
         VALUES (?, ' . implode(', ', array_fill(0, count($allowed), '?')) . ') 
         ON DUPLICATE KEY UPDATE ' . implode(', ', $updates),
        array_merge([$uid], $params)
    );
    
    json_ok(['message' => 'Notification preferences updated']);
}
?>