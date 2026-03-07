<?php
/**
 * GET /api/personalization/recommendations.php
 * Personalized recommendations based on user behavior and preferences
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET']);

$user = require_auth();
$uid = $user['user_id'];
$type = $_GET['type'] ?? 'all';

$valid_types = ['services', 'articles', 'meditations', 'community'];

if (!in_array($type, $valid_types)) {
    json_err('Invalid recommendation type');
}

$recommendations = [];

// Get user preferences
$preferences = DB::one(
    'SELECT zodiac, plan FROM users WHERE id = ?',
    [$uid]
);

// Get user behavior data
$behavior = DB::one(
    'SELECT 
        (SELECT COUNT(*) FROM readings WHERE user_id = ?) as total_readings,
        (SELECT GROUP_CONCAT(DISTINCT type) FROM readings WHERE user_id = ? LIMIT 5) as reading_types,
        (SELECT COUNT(*) FROM user_services WHERE user_id = ?) as services_owned,
        (SELECT COUNT(*) FROM posts WHERE user_id = ?) as social_posts,
        (SELECT created_at FROM users WHERE id = ?) as join_date
    ',
    [$uid, $uid, $uid, $uid, $uid]
);

// Generate recommendations based on type and behavior
switch ($type) {
    case 'services':
        $recommendations = generateServiceRecommendations($uid, $preferences, $behavior);
        break;
        
    case 'articles':
        $recommendations = generateArticleRecommendations($uid, $preferences, $behavior);
        break;
        
    case 'meditations':
        $recommendations = generateMeditationRecommendations($uid, $preferences, $behavior);
        break;
        
    case 'community':
        $recommendations = generateCommunityRecommendations($uid, $preferences, $behavior);
        break;
        
    case 'all':
        $recommendations = [
            'services' => generateServiceRecommendations($uid, $preferences, $behavior),
            'articles' => generateArticleRecommendations($uid, $preferences, $behavior),
            'meditations' => generateMeditationRecommendations($uid, $preferences, $behavior),
            'community' => generateCommunityRecommendations($uid, $preferences, $behavior)
        ];
        break;
}

json_ok([
    'recommendations' => $recommendations,
    'user_profile' => [
        'zodiac' => $preferences['zodiac'],
        'plan' => $preferences['plan'],
        'member_since' => $behavior['join_date']
    ],
    'behavior_insights' => [
        'reading_frequency' => $behavior['total_readings'],
        'preferred_services' => explode(',', $behavior['reading_types']),
        'engagement_level' => $behavior['social_posts'] > 5 ? 'high' : ($behavior['social_posts'] > 0 ? 'medium' : 'low')
    ]
]);

// Helper functions for generating recommendations
function generateServiceRecommendations($uid, $preferences, $behavior) {
    $recommendations = [];
    
    // Based on zodiac sign
    $zodiac_services = [
        'aries' => ['career_guidance', 'energy_boost'],
        'taurus' => 'financial_abundance',
        'gemini' => 'communication_clarity',
        'cancer' => 'emotional_healing',
        'leo' => 'confidence_boost',
        'virgo' => 'organization_clarity',
        'libra' => 'relationship_harmony',
        'scorpio' => 'transformation_guidance',
        'sagittarius' => 'adventure_guidance',
        'capricorn' => 'career_acceleration',
        'aquarius' => 'innovation_guidance',
        'pisces' => 'intuitive_development'
    ];
    
    // Add zodiac-specific recommendations
    if (isset($zodiac_services[$preferences['zodiac']])) {
        $recommendations[] = [
            'type' => 'zodiac_specific',
            'service' => $zodiac_services[$preferences['zodiac']],
            'reason' => 'Perfectly aligned with your ' . $preferences['zodiac'] . ' energy',
            'priority' => 'high'
        ];
    }
    
    // Based on reading patterns
    if ($behavior['total_readings'] < 5) {
        $recommendations[] = [
            'type' => 'beginner_friendly',
            'service' => 'daily_horoscope',
            'reason' => 'Start with daily insights to build your practice',
            'priority' => 'medium'
        ];
    } else {
        $recommendations[] = [
            'type' => 'advanced_reading',
            'service' => 'natal_chart',
            'reason' => 'Deepen your understanding with a comprehensive birth chart',
            'priority' => 'high'
        ];
    }
    
    // Based on social engagement
    if ($behavior['social_posts'] > 0) {
        $recommendations[] = [
            'type' => 'social_sharing',
            'service' => 'tarot_reading',
            'reason' => 'Perfect for sharing mystical insights with your community',
            'priority' => 'medium'
        ];
    }
    
    return $recommendations;
}

function generateArticleRecommendations($uid, $preferences, $behavior) {
    $recommendations = [];
    
    // Based on zodiac
    $zodiac_topics = [
        'aries' => ['leadership', 'courage', 'new_beginnings'],
        'taurus' => ['manifestation', 'patience', 'financial_abundance'],
        'gemini' => ['communication', 'learning', 'adaptability'],
        'cancer' => ['emotional_wellness', 'intuition', 'home'],
        'leo' => ['creativity', 'self_expression', 'confidence'],
        'virgo' => ['mindfulness', 'organization', 'health'],
        'libra' => ['relationships', 'balance', 'harmony'],
        'scorpio' => ['transformation', 'depth', 'mystery'],
        'sagittarius' => ['spirituality', 'adventure', 'expansion'],
        'capricorn' => ['ambition', 'discipline', 'success'],
        'aquarius' => ['innovation', 'community', 'future'],
        'pisces' => ['intuition', 'compassion', 'spirituality']
    ];
    
    if (isset($zodiac_topics[$preferences['zodiac']])) {
        foreach ($zodiac_topics[$preferences['zodiac']] as $topic) {
            $recommendations[] = [
                'type' => 'zodiac_learning',
                'topic' => $topic,
                'reason' => 'Learn more about ' . $topic . ' in your ' . $preferences['zodiac'] . ' journey',
                'priority' => 'high'
            ];
        }
    }
    
    // Based on reading frequency
    if ($behavior['total_readings'] > 20) {
        $recommendations[] = [
            'type' => 'advanced_study',
            'topic' => 'astrology_techniques',
            'reason' => 'Deepen your practice with advanced techniques',
            'priority' => 'medium'
        ];
    }
    
    return $recommendations;
}

function generateMeditationRecommendations($uid, $preferences, $behavior) {
    $recommendations = [];
    
    // Based on zodiac energy
    $zodiac_meditations = [
        'aries' => 'energy_balancing',
        'taurus' => 'grounding',
        'gemini' => 'mental_clarity',
        'cancer' => 'emotional_healing',
        'leo' => 'confidence_building',
        'virgo' => 'mindfulness',
        'libra' => 'harmony',
        'scorpio' => 'transformation',
        'sagittarius' => 'expansion',
        'capricorn' => 'focus',
        'aquarius' => 'innovation',
        'pisces' => 'intuition'
    ];
    
    if (isset($zodiac_meditations[$preferences['zodiac']])) {
        $recommendations[] = [
            'type' => 'zodiac_meditation',
            'meditation' => $zodiac_meditations[$preferences['zodiac']],
            'reason' => 'Balance your ' . $preferences['zodiac'] . ' energy through meditation',
            'priority' => 'high'
        ];
    }
    
    // Based on stress levels (inferred from reading patterns)
    if ($behavior['total_readings'] > 30) {
        $recommendations[] = [
            'type' => 'stress_relief',
            'meditation' => 'calming_breath',
            'reason' => 'Find peace and calm in your daily practice',
            'priority' => 'high'
        ];
    }
    
    return $recommendations;
}

function generateCommunityRecommendations($uid, $preferences, $behavior) {
    $recommendations = [];
    
    // Find users with similar zodiac
    $similar_users = DB::all(
        'SELECT id, name FROM users WHERE zodiac = ? AND id != ? LIMIT 5',
        [$preferences['zodiac'], $uid]
    );
    
    if (!empty($similar_users)) {
        $recommendations[] = [
            'type' => 'zodiac_group',
            'users' => $similar_users,
            'reason' => 'Connect with fellow ' . $preferences['zodiac'] . ' enthusiasts',
            'priority' => 'medium'
        ];
    }
    
    // Based on social engagement
    if ($behavior['social_posts'] === 0) {
        $recommendations[] = [
            'type' => 'social_encouragement',
            'suggestion' => 'share_experience',
            'reason' => 'Share your mystical journey with the community',
            'priority' => 'low'
        ];
    }
    
    return $recommendations;
}
?>