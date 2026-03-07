<?php
/**
 * POST /api/analytics/track.php
 * Track user interactions and behavior
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['POST']);

// No auth required for analytics tracking (anonymous)
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err('Method not allowed', 405);
}

$body = get_body();
$action = $body['action'] ?? '';
$category = $body['category'] ?? 'general';
$metadata = $body['metadata'] ?? [];

// Validate action
$valid_actions = [
    'page_view', 'button_click', 'form_submit', 'reading_generated',
    'service_purchased', 'image_uploaded', 'social_post', 'achievement_unlocked'
];

if (!in_array($action, $valid_actions)) {
    json_err('Invalid action');
}

// Get user info if available
$userId = 0;
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$ipAddress = Security::getIP();
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// Try to get user from session if available
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    try {
        $token = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
        $session = DB::one(
            'SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()',
            [$token]
        );
        if ($session) {
            $userId = (int) $session['user_id'];
        }
    } catch (\Throwable $e) {
        // Ignore auth errors for analytics
    }
}

// Store analytics event
DB::insert(
    'INSERT INTO analytics_events (user_id, action, category, metadata, ip_address, user_agent, referer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
    [
        $userId,
        $action,
        $category,
        json_encode($metadata),
        $ipAddress,
        $userAgent,
        $referer
    ]
);

// Special handling for important events
if ($action === 'reading_generated') {
    // Update reading statistics
    DB::query(
        'INSERT INTO reading_stats (date, service, count) VALUES (CURDATE(), ?, 1) ON DUPLICATE KEY UPDATE count = count + 1',
        [$metadata['service'] ?? 'unknown']
    );
}

if ($action === 'service_purchased') {
    // Track revenue
    DB::insert(
        'INSERT INTO revenue_stats (date, amount, service) VALUES (CURDATE(), ?, ?)',
        [
            $metadata['amount'] ?? 0,
            $metadata['service'] ?? 'unknown'
        ]
    );
}

json_ok(['message' => 'Event tracked']);
?>