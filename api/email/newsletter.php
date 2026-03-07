<?php
/**
 * GET /api/email/newsletter.php
 * POST /api/email/newsletter.php
 * Email newsletter and marketing system
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
    
    if ($user_id) {
        // Get user newsletter preferences
        $preferences = DB::one(
            'SELECT daily_horoscope, weekly_summary, monthly_forecast, promotional FROM notification_preferences WHERE user_id = ?',
            [$user_id]
        );
        
        if (!$preferences) {
            $preferences = [
                'daily_horoscope' => 0,
                'weekly_summary' => 1,
                'monthly_forecast' => 1,
                'promotional' => 0
            ];
        }
        
        // Get user zodiac for personalization
        $user = DB::one('SELECT zodiac, name FROM users WHERE id = ?', [$user_id]);
        
        json_ok([
            'preferences' => $preferences,
            'user_info' => $user
        ]);
    } else {
        // Get newsletter templates
        $templates = DB::all(
            'SELECT id, name, subject, template_type, active FROM email_templates WHERE active = 1 ORDER BY created_at DESC'
        );
        
        json_ok([
            'templates' => $templates,
            'stats' => [
                'total_subscribers' => DB::one('SELECT COUNT(*) as count FROM users WHERE newsletter_subscribed = 1')['count'],
                'active_subscribers' => DB::one('SELECT COUNT(*) as count FROM users WHERE newsletter_subscribed = 1 AND last_login > DATE_SUB(NOW(), INTERVAL 30 DAY)')['count']
            ]
        ]);
    }
}

if ($method === 'POST') {
    $body = get_body();
    $action = $body['action'] ?? '';
    
    switch ($action) {
        case 'subscribe':
            handleSubscription($body);
            break;
            
        case 'unsubscribe':
            handleUnsubscription($body);
            break;
            
        case 'update_preferences':
            handlePreferencesUpdate($body);
            break;
            
        case 'send_campaign':
            handleCampaignSend($body);
            break;
            
        default:
            json_err('Invalid action');
    }
}

function handleSubscription($body) {
    $email = $body['email'] ?? '';
    $name = $body['name'] ?? '';
    $zodiac = $body['zodiac'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_err('Invalid email address');
    }
    
    // Check if already subscribed
    $existing = DB::one('SELECT id FROM users WHERE email = ?', [$email]);
    
    if ($existing) {
        // Update existing user
        DB::query(
            'UPDATE users SET newsletter_subscribed = 1, name = ?, zodiac = ? WHERE email = ?',
            [$name, $zodiac, $email]
        );
        $message = 'Updated subscription preferences';
    } else {
        // Create new user
        DB::insert(
            'INSERT INTO users (email, name, zodiac, newsletter_subscribed, created_at) VALUES (?, ?, ?, 1, NOW())',
            [$email, $name, $zodiac]
        );
        $message = 'Successfully subscribed to newsletter';
    }
    
    // Send welcome email
    sendWelcomeEmail($email, $name, $zodiac);
    
    json_ok(['message' => $message]);
}

function handleUnsubscription($body) {
    $email = $body['email'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_err('Invalid email address');
    }
    
    DB::query(
        'UPDATE users SET newsletter_subscribed = 0 WHERE email = ?',
        [$email]
    );
    
    json_ok(['message' => 'Successfully unsubscribed']);
}

function handlePreferencesUpdate($body) {
    $user_id = (int) ($body['user_id'] ?? 0);
    $preferences = $body['preferences'] ?? [];
    
    if (!$user_id) {
        json_err('User ID is required');
    }
    
    // Validate preferences
    $valid_preferences = ['daily_horoscope', 'weekly_summary', 'monthly_forecast', 'promotional'];
    $updates = [];
    $params = [];
    
    foreach ($valid_preferences as $pref) {
        if (isset($preferences[$pref])) {
            $updates[] = "{$pref} = ?";
            $params[] = (int) $preferences[$pref];
        }
    }
    
    if (empty($updates)) {
        json_err('No preferences to update');
    }
    
    $params[] = $user_id;
    
    DB::query(
        'INSERT INTO notification_preferences (user_id, ' . implode(', ', $valid_preferences) . ') 
         VALUES (?, ' . implode(', ', array_fill(0, count($valid_preferences), '?')) . ') 
         ON DUPLICATE KEY UPDATE ' . implode(', ', $updates),
        array_merge([$user_id], $params)
    );
    
    json_ok(['message' => 'Preferences updated successfully']);
}

function handleCampaignSend($body) {
    $template_id = (int) ($body['template_id'] ?? 0);
    $zodiac_filter = $body['zodiac_filter'] ?? 'all';
    
    if (!$template_id) {
        json_err('Template ID is required');
    }
    
    // Get template
    $template = DB::one('SELECT * FROM email_templates WHERE id = ?', [$template_id]);
    if (!$template) {
        json_err('Template not found');
    }
    
    // Get subscribers
    $subscribers = getSubscribers($zodiac_filter);
    
    if (empty($subscribers)) {
        json_ok(['message' => 'No subscribers found for the selected filter']);
    }
    
    // Send emails
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($subscribers as $subscriber) {
        $personalized_content = personalizeEmailContent($template['body_html'], $subscriber);
        
        if (send_email($subscriber['email'], $template['subject'], $personalized_content)) {
            $sent_count++;
            
            // Log email delivery
            DB::insert(
                'INSERT INTO email_campaign_log (template_id, user_id, email, sent_at, status) VALUES (?, ?, ?, NOW(), "sent")',
                [$template_id, $subscriber['id'], $subscriber['email']]
            );
        } else {
            $failed_count++;
            
            // Log failed delivery
            DB::insert(
                'INSERT INTO email_campaign_log (template_id, user_id, email, sent_at, status) VALUES (?, ?, ?, NOW(), "failed")',
                [$template_id, $subscriber['id'], $subscriber['email']]
            );
        }
        
        // Small delay to avoid being marked as spam
        usleep(50000); // 50ms
    }
    
    json_ok([
        'message' => 'Campaign completed',
        'sent_count' => $sent_count,
        'failed_count' => $failed_count,
        'total_count' => count($subscribers)
    ]);
}

function getSubscribers($zodiac_filter) {
    if ($zodiac_filter === 'all') {
        return DB::all(
            'SELECT id, email, name, zodiac FROM users WHERE newsletter_subscribed = 1'
        );
    } else {
        return DB::all(
            'SELECT id, email, name, zodiac FROM users WHERE newsletter_subscribed = 1 AND zodiac = ?',
            [$zodiac_filter]
        );
    }
}

function personalizeEmailContent($template, $user) {
    // Replace placeholders with user data
    $personalized = str_replace(
        ['{{name}}', '{{zodiac}}', '{{zodiac_icon}}'],
        [$user['name'], ucfirst($user['zodiac']), getZodiacIcon($user['zodiac'])],
        $template
    );
    
    // Add personalized horoscope if available
    if ($user['zodiac']) {
        $daily_horoscope = getDailyHoroscope($user['zodiac']);
        $personalized = str_replace('{{daily_horoscope}}', $daily_horoscope, $personalized);
    }
    
    return $personalized;
}

function getZodiacIcon($zodiac) {
    $icons = [
        'aries' => '♈', 'taurus' => '♉', 'gemini' => '♊', 'cancer' => '♋',
        'leo' => '♌', 'virgo' => '♍', 'libra' => '♎', 'scorpio' => '♏',
        'sagittarius' => '♐', 'capricorn' => '♑', 'aquarius' => '♒', 'pisces' => '♓'
    ];
    return $icons[$zodiac] ?? '✨';
}

function getDailyHoroscope($zodiac) {
    $prompt = "Generate a short, inspiring daily horoscope for {$zodiac}. Keep it under 50 words and make it mystical and positive.";
    
    $result = call_groq($prompt, 'You are FateSpy, a mystical astrologer. Generate a short, inspiring horoscope.', 0.8, 150);
    
    return $result ? $result['horoscope'] : "The stars align beautifully for you today. Trust in the cosmic journey.";
}

function sendWelcomeEmail($email, $name, $zodiac) {
    $template = DB::one('SELECT * FROM email_templates WHERE slug = "welcome"');
    
    if ($template) {
        $personalized_content = personalizeEmailContent($template['body_html'], [
            'name' => $name,
            'zodiac' => $zodiac,
            'email' => $email
        ]);
        
        send_email($email, $template['subject'], $personalized_content);
    }
}

json_err('Invalid request');
?>