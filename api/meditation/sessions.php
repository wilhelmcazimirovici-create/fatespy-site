<?php
/**
 * GET /api/meditation/sessions.php
 * POST /api/meditation/sessions.php
 * Meditation and mindfulness sessions
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);

$user = require_auth();
$uid = $user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $type = $_GET['type'] ?? 'all';
    $duration = $_GET['duration'] ?? 'all';
    $limit = min((int) ($_GET['limit'] ?? 10), 20);
    
    $where = 'WHERE status = "active"';
    $params = [];
    
    if ($type !== 'all') {
        $where .= ' AND type = ?';
        $params[] = $type;
    }
    
    if ($duration !== 'all') {
        $where .= ' AND duration <= ?';
        $params[] = (int) $duration;
    }
    
    // Get meditation sessions
    $sessions = DB::all(
        "SELECT id, title, description, type, duration, difficulty, audio_url, image_url, created_at 
         FROM meditation_sessions {$where} 
         ORDER BY difficulty ASC, duration ASC 
         LIMIT {$limit}",
        $params
    );
    
    // Get user meditation progress
    $progress = DB::all(
        'SELECT session_id, completed_at, rating, notes FROM user_meditation_progress WHERE user_id = ?',
        [$uid]
    );
    
    // Mark completed sessions
    foreach ($sessions as &$session) {
        $session['completed'] = in_array($session['id'], array_column($progress, 'session_id'));
        $session['user_rating'] = null;
        $session['user_notes'] = null;
        
        $user_progress = array_filter($progress, function($p) use ($session) {
            return $p['session_id'] == $session['id'];
        });
        
        if (!empty($user_progress)) {
            $user_progress = array_shift($user_progress);
            $session['user_rating'] = $user_progress['rating'];
            $session['user_notes'] = $user_progress['notes'];
        }
    }
    
    // Get meditation statistics
    $stats = DB::one(
        'SELECT 
            COUNT(*) as total_sessions,
            SUM(duration) as total_minutes,
            COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completed_sessions
        FROM user_meditation_progress WHERE user_id = ?',
        [$uid]
    );
    
    json_ok([
        'sessions' => $sessions,
        'stats' => $stats,
        'types' => ['breathing', 'mindfulness', 'visualization', 'chakra', 'sleep'],
        'durations' => [5, 10, 15, 20, 30]
    ]);
}

if ($method === 'POST') {
    $body = get_body();
    $session_id = (int) ($body['session_id'] ?? 0);
    $rating = (int) ($body['rating'] ?? 0);
    $notes = $body['notes'] ?? '';
    
    if (!$session_id) {
        json_err('Session ID is required');
    }
    
    // Check if session exists
    $session = DB::one('SELECT id FROM meditation_sessions WHERE id = ?', [$session_id]);
    if (!$session) {
        json_err('Session not found', 404);
    }
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        json_err('Rating must be between 1 and 5');
    }
    
    // Update or create progress
    DB::query(
        'INSERT INTO user_meditation_progress (user_id, session_id, completed_at, rating, notes) 
         VALUES (?, ?, NOW(), ?, ?) 
         ON DUPLICATE KEY UPDATE completed_at = NOW(), rating = VALUES(rating), notes = VALUES(notes)',
        [$uid, $session_id, $rating, $notes]
    );
    
    // Award achievement if this is the first completion
    $first_completion = DB::one(
        'SELECT COUNT(*) as count FROM user_meditation_progress WHERE user_id = ? AND completed_at IS NOT NULL',
        [$uid]
    );
    
    if ($first_completion['count'] === 1) {
        // First meditation achievement
        DB::insert(
            'INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) 
             SELECT ?, id, NOW() FROM achievements WHERE name = "First Meditation"',
            [$uid]
        );
        
        // Send notification
        DB::insert(
            'INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$uid, 'achievement', 'Achievement Unlocked!', 'Congratulations! You\'ve completed your first meditation session.']
        );
    }
    
    json_ok([
        'message' => 'Meditation progress updated',
        'session_id' => $session_id,
        'rating' => $rating
    ]);
}
?>