<?php
/**
 * GET /api/user/achievements.php
 * User achievements and progress tracking system
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET']);

$user = require_auth();
$uid = $user['user_id'];

// Get user achievements
$achievements = DB::all(
    'SELECT ua.achievement_id, a.name, a.description, a.icon, ua.unlocked_at, ua.progress 
     FROM user_achievements ua 
     JOIN achievements a ON ua.achievement_id = a.id 
     WHERE ua.user_id = ? AND ua.unlocked_at IS NOT NULL 
     ORDER BY ua.unlocked_at DESC',
    [$uid]
);

// Get in-progress achievements
$in_progress = DB::all(
    'SELECT ua.achievement_id, a.name, a.description, a.icon, ua.progress, a.target 
     FROM user_achievements ua 
     JOIN achievements a ON ua.achievement_id = a.id 
     WHERE ua.user_id = ? AND ua.unlocked_at IS NULL',
    [$uid]
);

// Calculate user level and XP
$xp_stats = DB::one(
    'SELECT 
        (SELECT COUNT(*) FROM readings WHERE user_id = ?) as total_readings,
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) as total_posts,
        (SELECT COUNT(*) FROM post_likes WHERE user_id = ?) as total_likes,
        (SELECT COUNT(*) FROM user_services WHERE user_id = ?) as services_owned
    ',
    [$uid, $uid, $uid, $uid]
);

// Calculate XP
$xp = (
    $xp_stats['total_readings'] * 10 +
    $xp_stats['total_posts'] * 25 +
    $xp_stats['total_likes'] * 5 +
    $xp_stats['services_owned'] * 50
);

// Determine level
$level = floor($xp / 100) + 1;
$xp_to_next = ($level * 100) - ($xp % 100);

// Check for new achievements
checkNewAchievements($uid, $xp_stats);

json_ok([
    'achievements' => $achievements,
    'in_progress' => $in_progress,
    'level' => $level,
    'xp' => $xp,
    'xp_to_next' => $xp_to_next,
    'stats' => $xp_stats
]);

// Function to check and unlock achievements
function checkNewAchievements($uid, $stats) {
    $achievements_to_check = [
        ['name' => 'First Reading', 'condition' => $stats['total_readings'] >= 1, 'type' => 'reading', 'target' => 1],
        ['name' => 'Reading Enthusiast', 'condition' => $stats['total_readings'] >= 10, 'type' => 'reading', 'target' => 10],
        ['name' => 'Reading Master', 'condition' => $stats['total_readings'] >= 50, 'type' => 'reading', 'target' => 50],
        ['name' => 'Social Butterfly', 'condition' => $stats['total_posts'] >= 5, 'type' => 'post', 'target' => 5],
        ['name' => 'Community Star', 'condition' => $stats['total_likes'] >= 20, 'type' => 'like', 'target' => 20],
        ['name' => 'Service Collector', 'condition' => $stats['services_owned'] >= 3, 'type' => 'service', 'target' => 3],
        ['name' => 'VIP Member', 'condition' => false, 'type' => 'vip', 'target' => 1], // Special case
    ];
    
    foreach ($achievements_to_check as $achievement) {
        if ($achievement['condition']) {
            // Check if achievement already exists
            $exists = DB::one(
                'SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = (SELECT id FROM achievements WHERE name = ?)',
                [$uid, $achievement['name']]
            );
            
            if (!$exists) {
                // Get or create achievement
                $achievement_id = DB::one(
                    'SELECT id FROM achievements WHERE name = ?',
                    [$achievement['name']]
                );
                
                if (!$achievement_id) {
                    // Create new achievement
                    $achievement_id = DB::insert(
                        'INSERT INTO achievements (name, description, icon, type, target) VALUES (?, ?, ?, ?, ?)',
                        [
                            $achievement['name'],
                            'Achievement unlocked!',
                            '🏆',
                            $achievement['type'],
                            $achievement['target']
                        ]
                    );
                }
                
                // Award achievement
                DB::insert(
                    'INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?, ?, NOW())',
                    [$uid, $achievement_id]
                );
                
                // Send notification
                DB::insert(
                    'INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (?, ?, ?, ?, NOW())',
                    [
                        $uid,
                        'achievement',
                        'Achievement Unlocked!',
                        "Congratulations! You've unlocked the '{$achievement['name']}' achievement."
                    ]
                );
            }
        }
    }
}
?>