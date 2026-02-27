<?php
/**
 * POST /api/reading/coffee.php
 * Body: { image_id } — references a previously uploaded coffee cup image
 * Returns: AI coffee cup reading (tasseography) as JSON
 *
 * Requires: authenticated user with 'coffee_reading' service or VIP plan
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['POST']);
Security::rateLimit('reading_coffee', 5, 3600);

$user = require_auth();
$uid = $user['user_id'];
$body = get_body();

// Validate service
if ($user['plan'] !== 'vip') {
    $has = DB::one(
        'SELECT id FROM user_services WHERE user_id = ? AND service_slug = "coffee_reading"
         AND (expires_at IS NULL OR expires_at > NOW())',
        [$uid]
    );
    if (!$has)
        json_err('You need the Coffee Reading service to use this feature', 403);
}

$image_id = (int) ($body['image_id'] ?? 0);
if (!$image_id)
    json_err('image_id is required');

$img = DB::one(
    'SELECT id, category, filename FROM user_images WHERE id = ? AND user_id = ? AND category = "coffee"',
    [$image_id, $uid]
);
if (!$img)
    json_err('Coffee cup image not found', 404);

// 1h cache
$cached = DB::one(
    'SELECT content FROM readings WHERE user_id = ? AND type = "coffee"
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

// Traditional coffee cup symbols for rich interpretation
$cup_symbols = [
    'bird' => 'journey or good news', 'tree' => 'growth and stability', 'heart' => 'love and relationships',
    'snake' => 'wisdom or hidden enemy', 'mountain' => 'obstacle to overcome', 'star' => 'hope and guidance',
    'ring' => 'partnership or commitment', 'fish' => 'prosperity', 'horse' => 'strength and travel',
    'eye' => 'protection and awareness', 'flower' => 'happiness blooming', 'moon' => 'intuition and change',
    'key' => 'new opportunity', 'bridge' => 'transition period', 'crown' => 'success and recognition',
];
$selected_symbols = array_rand($cup_symbols, 4);
$symbols_desc = [];
foreach ($selected_symbols as $sym) {
    $symbols_desc[] = "{$sym} ({$cup_symbols[$sym]})";
}
$symbols_str = implode(', ', $symbols_desc);

$prompt = "You are performing a traditional Turkish/Greek coffee cup reading (tasseography) for a querent.{$zodiac_str}\n\n" .
    "The following symbols were identified in the coffee grounds: {$symbols_str}.\n" .
    "Interpret these symbols and their positions (rim = near future, middle = coming weeks, bottom = distant future).\n\n" .
    "Respond ONLY with a JSON object with exactly these keys:\n" .
    '{"symbols_found":["..."],"near_future":"...","coming_weeks":"...","distant_future":"...","love":"...","career":"...","warning":"...","overall":"...","lucky_number":0}' . "\n" .
    "symbols_found: array of symbol names. lucky_number: integer 1-99. Each other value: 2-3 sentences. Be mystical and specific.";

$result = call_groq($prompt, 'You are FateSpy, an expert tasseography reader (coffee cup divination). Respond ONLY with valid JSON.');
if (!$result)
    json_err('AI analysis failed. Please try again.', 503);

$defaults = [
    'symbols_found' => $selected_symbols,
    'near_future' => 'The patterns near the rim suggest positive changes approaching swiftly.',
    'coming_weeks' => 'The middle of the cup reveals a period of reflection and important decisions.',
    'distant_future' => 'The bottom formations promise stability and fulfillment of long-held wishes.',
    'love' => 'Romantic energy flows strongly through the cup patterns.',
    'career' => 'Professional symbols indicate advancement and recognition.',
    'warning' => 'Be cautious of hasty decisions in the next few days.',
    'overall' => 'The cup tells a story of transformation and approaching abundance.',
    'lucky_number' => rand(1, 99),
];
foreach ($defaults as $k => $v) {
    if (empty($result[$k]))
        $result[$k] = $v;
}

$reading_id = DB::insert(
    'INSERT INTO readings (user_id, type, zodiac, input_data, content) VALUES (?, ?, ?, ?, ?)',
    [
        $uid,
        'coffee',
        $u['zodiac'] ?? null,
        json_encode(['image_id' => $image_id, 'symbols' => $selected_symbols]),
        json_encode($result),
    ]
);

DB::query('UPDATE user_images SET reading_id = ? WHERE id = ?', [$reading_id, $image_id]);
Security::auditLog('coffee_reading', $uid, ['image_id' => $image_id]);

$result['reading_id'] = (int) $reading_id;
$result['source'] = 'ai';
json_ok($result);
