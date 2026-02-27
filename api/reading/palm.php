<?php
/**
 * POST /api/reading/palm.php
 * Body: { image_id } — references a previously uploaded palm image
 * Returns: AI palm reading analysis as JSON
 *
 * Requires: authenticated user with 'palm_reading' service or VIP plan
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['POST']);
Security::rateLimit('reading_palm', 5, 3600);

$user = require_auth();
$uid = $user['user_id'];
$body = get_body();

// Validate service ownership
if ($user['plan'] !== 'vip') {
    $has = DB::one(
        'SELECT id FROM user_services WHERE user_id = ? AND service_slug = "palm_reading"
         AND (expires_at IS NULL OR expires_at > NOW())',
        [$uid]
    );
    if (!$has)
        json_err('You need the Palm Reading service to use this feature', 403);
}

// Get uploaded image
$image_id = (int) ($body['image_id'] ?? 0);
if (!$image_id)
    json_err('image_id is required');

$img = DB::one(
    'SELECT id, category, filename FROM user_images WHERE id = ? AND user_id = ? AND category IN ("palm_left","palm_right")',
    [$image_id, $uid]
);
if (!$img)
    json_err('Palm image not found', 404);

// Check for recent reading of same image (1h cache)
$cached = DB::one(
    'SELECT content FROM readings WHERE user_id = ? AND type = "palm"
     AND JSON_EXTRACT(input_data, "$.image_id") = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
     ORDER BY created_at DESC LIMIT 1',
    [$uid, (string) $image_id]
);
if ($cached) {
    $data = json_decode($cached['content'], true);
    $data['source'] = 'cache';
    json_ok($data);
}

// Check if we have both palms for deeper reading
$hand = $img['category'] === 'palm_left' ? 'left' : 'right';
$other_hand = $hand === 'left' ? 'right' : 'left';
$other = DB::one(
    'SELECT id FROM user_images WHERE user_id = ? AND category = ?',
    [$uid, 'palm_' . $other_hand]
);

// Get user info for personalization
$u = DB::one('SELECT zodiac, dob, name FROM users WHERE id = ?', [$uid]);
$zodiac_str = $u['zodiac'] ? " Their zodiac sign is {$u['zodiac']}." : '';

$prompt = "You are performing a detailed palmistry reading for the {$hand} hand" .
    ($other ? " (the querent also has their {$other_hand} hand uploaded for cross-reference)" : '') .
    " of a querent.{$zodiac_str}\n\n" .
    "Since you cannot actually see the image, use your deep knowledge of palmistry to generate a rich, " .
    "personalized reading based on the {$hand} hand (the " .
    ($hand === 'left' ? 'receptive/potential hand showing innate talents' : 'dominant/active hand showing life choices') .
    ").\n\n" .
    "Respond ONLY with a JSON object with exactly these keys:\n" .
    '{"heart_line":"...","head_line":"...","life_line":"...","fate_line":"...","mounts":"...","overall":"...","advice":"..."}' . "\n" .
    "Each value: 2-3 detailed sentences. Be mystical, specific, and empowering.";

$result = call_groq($prompt, 'You are FateSpy, an expert palmistry reader and mystic. Respond ONLY with valid JSON.');
if (!$result)
    json_err('AI analysis failed. Please try again.', 503);

$defaults = [
    'heart_line' => 'Your heart line reveals deep emotional capacity and a generous spirit.',
    'head_line' => 'Your head line shows sharp intellect and creative problem-solving ability.',
    'life_line' => 'Your life line indicates strong vitality and resilience through change.',
    'fate_line' => 'Your fate line suggests a purposeful path guided by inner wisdom.',
    'mounts' => 'The mounts on your palm indicate a balance of ambition and sensitivity.',
    'overall' => 'Your palm reveals a soul destined for meaningful connections and growth.',
    'advice' => 'Trust the path your hands have mapped — your potential is vast.',
];
foreach ($defaults as $k => $v) {
    if (empty($result[$k]))
        $result[$k] = $v;
}

$result['hand'] = $hand;
$result['has_both_hands'] = (bool) $other;

// Save reading
$reading_id = DB::insert(
    'INSERT INTO readings (user_id, type, zodiac, input_data, content) VALUES (?, ?, ?, ?, ?)',
    [
        $uid,
        'palm',
        $u['zodiac'] ?? null,
        json_encode(['image_id' => $image_id, 'hand' => $hand]),
        json_encode($result),
    ]
);

// Link reading to image
DB::query('UPDATE user_images SET reading_id = ? WHERE id = ?', [$reading_id, $image_id]);

Security::auditLog('palm_reading', $uid, ['image_id' => $image_id, 'hand' => $hand]);

$result['reading_id'] = (int) $reading_id;
$result['source'] = 'ai';
json_ok($result);
