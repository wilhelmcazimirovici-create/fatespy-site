<?php
/**
 * POST /api/reading/aura.php
 * Body: { image_id } — references a previously uploaded aura/selfie image
 * Returns: AI aura analysis as JSON
 *
 * Requires: authenticated user with 'aura_scan' service or VIP plan
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['POST']);
Security::rateLimit('reading_aura', 5, 3600);

$user = require_auth();
$uid = $user['user_id'];
$body = get_body();

// Validate service ownership
if ($user['plan'] !== 'vip') {
    $has = DB::one(
        'SELECT id FROM user_services WHERE user_id = ? AND service_slug = "aura_scan"
         AND (expires_at IS NULL OR expires_at > NOW())',
        [$uid]
    );
    if (!$has)
        json_err('You need the Aura Scan service to use this feature', 403);
}

$image_id = (int) ($body['image_id'] ?? 0);
if (!$image_id)
    json_err('image_id is required');

$img = DB::one(
    'SELECT id, category, filename FROM user_images WHERE id = ? AND user_id = ? AND category = "aura"',
    [$image_id, $uid]
);
if (!$img)
    json_err('Aura image not found', 404);

// 1h cache
$cached = DB::one(
    'SELECT content FROM readings WHERE user_id = ? AND type = "aura"
     AND JSON_EXTRACT(input_data, "$.image_id") = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
     ORDER BY created_at DESC LIMIT 1',
    [$uid, (string) $image_id]
);
if ($cached) {
    $data = json_decode($cached['content'], true);
    $data['source'] = 'cache';
    json_ok($data);
}

$u = DB::one('SELECT zodiac, dob, name FROM users WHERE id = ?', [$uid]);
$zodiac_str = $u['zodiac'] ? " Their zodiac sign is {$u['zodiac']}." : '';
$dob_str = $u['dob'] ? " Born on {$u['dob']}." : '';

// Aura color palette for realistic generation
$aura_colors = ['indigo', 'violet', 'blue', 'green', 'yellow', 'orange', 'red', 'white', 'gold', 'pink', 'turquoise', 'silver'];
$primary = $aura_colors[array_rand($aura_colors)];
$secondary = $aura_colors[array_rand($aura_colors)];
while ($secondary === $primary)
    $secondary = $aura_colors[array_rand($aura_colors)];

$prompt = "You are performing an aura reading for a querent.{$zodiac_str}{$dob_str}\n\n" .
    "Their primary aura color is {$primary} with {$secondary} undertones. " .
    "Generate a deeply insightful aura reading based on these energy colors.\n\n" .
    "Respond ONLY with a JSON object with exactly these keys:\n" .
    '{"primary_color":"' . $primary . '","secondary_color":"' . $secondary . '","chakra_health":"...","emotional_state":"...","spiritual_insight":"...","energy_blocks":"...","healing_advice":"...","overall":"..."}' . "\n" .
    "Each value (except colors): 2-3 detailed sentences. Be mystical, specific, and empowering.";

$result = call_groq($prompt, 'You are FateSpy, an expert aura reader and energy healer. Respond ONLY with valid JSON.');
if (!$result)
    json_err('AI analysis failed. Please try again.', 503);

$defaults = [
    'primary_color' => $primary,
    'secondary_color' => $secondary,
    'chakra_health' => 'Your chakras show a harmonious flow of energy with minor blockages in the throat area.',
    'emotional_state' => 'Your emotional energy radiates warmth and openness to new experiences.',
    'spiritual_insight' => 'Your aura reveals a soul on an accelerating spiritual path.',
    'energy_blocks' => 'Minor energy stagnation detected near the solar plexus — practice deep breathing.',
    'healing_advice' => 'Meditate with crystals matching your aura colors to amplify healing.',
    'overall' => 'Your aura is vibrant and expanding. You are entering a period of spiritual awakening.',
];
foreach ($defaults as $k => $v) {
    if (empty($result[$k]))
        $result[$k] = $v;
}

$reading_id = DB::insert(
    'INSERT INTO readings (user_id, type, zodiac, input_data, content) VALUES (?, ?, ?, ?, ?)',
    [
        $uid,
        'aura',
        $u['zodiac'] ?? null,
        json_encode(['image_id' => $image_id, 'primary_color' => $primary, 'secondary_color' => $secondary]),
        json_encode($result),
    ]
);

DB::query('UPDATE user_images SET reading_id = ? WHERE id = ?', [$reading_id, $image_id]);
Security::auditLog('aura_reading', $uid, ['image_id' => $image_id]);

$result['reading_id'] = (int) $reading_id;
$result['source'] = 'ai';
json_ok($result);
