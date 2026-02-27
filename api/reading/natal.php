<?php
/**
 * POST /api/reading/natal.php
 * Body: { day, month, year, hour, minute, place, name? }
 * Returns: Comprehensive natal chart analysis as JSON
 *
 * Requires: authenticated user with 'natal_chart' service or VIP plan
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['POST']);
Security::rateLimit('reading_natal', 3, 3600);

$user = require_auth();
$uid = $user['user_id'];
$body = get_body();

// Validate service
if ($user['plan'] !== 'vip') {
    $has = DB::one(
        'SELECT id FROM user_services WHERE user_id = ? AND service_slug = "natal_chart"
         AND (expires_at IS NULL OR expires_at > NOW())',
        [$uid]
    );
    if (!$has)
        json_err('You need the Personal Natal Chart service to use this feature', 403);
}

$day = Security::sanitizeInt($body['day'] ?? 0, 1, 31);
$month = Security::sanitizeInt($body['month'] ?? 0, 1, 12);
$year = Security::sanitizeInt($body['year'] ?? 0, 1900, 2010);
$hour = Security::sanitizeInt($body['hour'] ?? 12, 0, 23);
$minute = Security::sanitizeInt($body['minute'] ?? 0, 0, 59);
$place = Security::sanitizeString($body['place'] ?? '', 200);
$name = Security::sanitizeString($body['name'] ?? $user['name'] ?? '', 100);

if (!$day || !$month || !$year)
    json_err('day, month, and year are required');
if (!$place)
    json_err('place of birth is required');

// Validate date
if (!checkdate($month, $day, $year))
    json_err('Invalid birth date');

$date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
$time_str = sprintf('%02d:%02d', $hour, $minute);

// Determine sun sign
function get_sun_sign(int $m, int $d): string
{
    $signs = [
        [1, 20, 'Capricorn'], [2, 19, 'Aquarius'], [3, 20, 'Pisces'], [4, 20, 'Aries'],
        [5, 21, 'Taurus'], [6, 21, 'Gemini'], [7, 23, 'Cancer'], [8, 23, 'Leo'],
        [9, 23, 'Virgo'], [10, 23, 'Libra'], [11, 22, 'Scorpio'], [12, 22, 'Sagittarius'],
        [12, 31, 'Capricorn'],
    ];
    foreach ($signs as [$sm, $sd, $sz]) {
        if ($m < $sm || ($m === $sm && $d <= $sd))
            return $sz;
    }
    return 'Capricorn';
}

$sun_sign = get_sun_sign($month, $day);

// 24h cache by birth data
$cache_key = md5("{$date_str}_{$time_str}_{$place}");
$cached = DB::one(
    'SELECT content FROM readings WHERE user_id = ? AND type = "natal"
     AND JSON_EXTRACT(input_data, "$.cache_key") = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
     ORDER BY created_at DESC LIMIT 1',
    [$uid, $cache_key]
);
if ($cached) {
    $data = json_decode($cached['content'], true);
    $data['source'] = 'cache';
    json_ok($data);
}

// Approximate moon sign based on birth year/month (simplified)
$moon_signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
$moon_idx = ($day + $month + ($year % 12) + ($hour > 12 ? 1 : 0)) % 12;
$moon_sign = $moon_signs[$moon_idx];

// Rising sign approximation (based on birth hour)
$rising_idx = (int) (($hour * 60 + $minute) / 120) % 12;
$rising_idx = ($rising_idx + array_search($sun_sign, $moon_signs)) % 12;
$rising_sign = $moon_signs[$rising_idx];

$prompt = "Generate a comprehensive natal chart analysis for {$name}, born on {$date_str} at {$time_str} in {$place}.\n\n" .
    "Sun Sign: {$sun_sign}\nMoon Sign: {$moon_sign}\nRising Sign: {$rising_sign}\n\n" .
    "Respond ONLY with a JSON object with these keys:\n" .
    '{' .
    '"sun_sign":"' . $sun_sign . '",' .
    '"moon_sign":"' . $moon_sign . '",' .
    '"rising_sign":"' . $rising_sign . '",' .
    '"sun_analysis":"...",' .
    '"moon_analysis":"...",' .
    '"rising_analysis":"...",' .
    '"mercury":"...",' .
    '"venus":"...",' .
    '"mars":"...",' .
    '"jupiter":"...",' .
    '"saturn":"...",' .
    '"personality":"...",' .
    '"life_purpose":"...",' .
    '"love_compatibility":"...",' .
    '"career_path":"...",' .
    '"challenges":"...",' .
    '"strengths":"...",' .
    '"current_transits":"...",' .
    '"year_ahead":"..."' .
    '}' . "\n" .
    "Each value: 3-4 detailed sentences. Be mystical, specific, and deeply insightful. Reference the birth time, place, and planetary positions.";

$result = call_groq(
    $prompt,
    'You are FateSpy, an expert astrologer specializing in natal chart interpretation. Respond ONLY with valid JSON.',
    0.85,
    3000
);
if (!$result)
    json_err('AI analysis failed. Please try again.', 503);

// Ensure all keys exist
$required_keys = [
    'sun_sign' => $sun_sign,
    'moon_sign' => $moon_sign,
    'rising_sign' => $rising_sign,
    'sun_analysis' => "With your Sun in {$sun_sign}, you carry the core energy of this powerful sign.",
    'moon_analysis' => "Your Moon in {$moon_sign} reveals your emotional landscape and inner world.",
    'rising_analysis' => "With {$rising_sign} rising, the world perceives you through this lens of energy.",
    'mercury' => 'Your Mercury placement enhances your communication and intellectual style.',
    'venus' => 'Venus in your chart shapes your approach to love, beauty, and relationships.',
    'mars' => 'Mars energizes your drive, ambition, and how you take action in the world.',
    'jupiter' => 'Jupiter bestows growth and abundance in key areas of your chart.',
    'saturn' => 'Saturn provides structure and important life lessons for your journey.',
    'personality' => "The blend of {$sun_sign} Sun, {$moon_sign} Moon, and {$rising_sign} Rising creates a unique personality.",
    'life_purpose' => 'Your chart reveals a soul mission aligned with growth and authentic expression.',
    'love_compatibility' => "Your Venus and Moon signs suggest deep compatibility with water and earth signs.",
    'career_path' => 'Your midheaven and planetary alignments suggest a career involving creativity and leadership.',
    'challenges' => 'Saturn aspects in your chart point to areas requiring patience and discipline.',
    'strengths' => 'Your natal chart reveals remarkable resilience and intuitive gifts.',
    'current_transits' => 'Current planetary transits are activating transformative sectors of your chart.',
    'year_ahead' => 'The coming year brings significant opportunities for personal growth and new beginnings.',
];
foreach ($required_keys as $k => $v) {
    if (empty($result[$k]))
        $result[$k] = $v;
}

$result['birth_data'] = [
    'date' => $date_str,
    'time' => $time_str,
    'place' => $place,
    'name' => $name,
];

$reading_id = DB::insert(
    'INSERT INTO readings (user_id, type, zodiac, input_data, content) VALUES (?, ?, ?, ?, ?)',
    [
        $uid,
        'natal',
        $sun_sign,
        json_encode([
            'day' => $day, 'month' => $month, 'year' => $year,
            'hour' => $hour, 'minute' => $minute, 'place' => $place,
            'name' => $name, 'cache_key' => $cache_key,
        ]),
        json_encode($result),
    ]
);

// Update user zodiac if not set
$u = DB::one('SELECT zodiac FROM users WHERE id = ?', [$uid]);
if (empty($u['zodiac'])) {
    DB::query('UPDATE users SET zodiac = ?, dob = ? WHERE id = ?', [strtolower($sun_sign), $date_str, $uid]);
}

Security::auditLog('natal_chart', $uid, ['sun' => $sun_sign, 'moon' => $moon_sign, 'rising' => $rising_sign]);

$result['reading_id'] = (int) $reading_id;
$result['source'] = 'ai';
json_ok($result);
