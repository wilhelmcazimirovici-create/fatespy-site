<?php
/**
 * POST /api/horoscope/personal.php
 * Body: { day, month, year, hour, minute, place, gender }
 * Returns: { love, career, money, health, home, zodiac, source }
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

// Include the personal logic from daily.php by redefining $endpoint
$_file_base = 'personal';
// Re-use the same logic - just include the endpoint logic
set_json_headers(['POST']);

$body = get_body();

$day = (int) ($body['day'] ?? 1);
$month = (int) ($body['month'] ?? 1);
$year = (int) ($body['year'] ?? 1990);
$hour = (int) ($body['hour'] ?? 12);
$minute = (int) ($body['minute'] ?? 0);
$place = trim($body['place'] ?? '');
$gender = ($body['gender'] ?? 'male') === 'female' ? 'female' : 'male';

// Determine zodiac
function get_zodiac(int $month, int $day): string
{
    $data = [
        [1, 20, 'Capricorn'],
        [2, 19, 'Aquarius'],
        [3, 20, 'Pisces'],
        [4, 20, 'Aries'],
        [5, 21, 'Taurus'],
        [6, 21, 'Gemini'],
        [7, 23, 'Cancer'],
        [8, 23, 'Leo'],
        [9, 23, 'Virgo'],
        [10, 23, 'Libra'],
        [11, 22, 'Scorpio'],
        [12, 22, 'Sagittarius'],
        [12, 31, 'Capricorn'],
    ];
    foreach ($data as [$m, $d, $z]) {
        if ($month < $m || ($month === $m && $day <= $d))
            return $z;
    }
    return 'Capricorn';
}
$zodiac = get_zodiac($month, $day);
$date_str = "{$year}-{$month}-{$day}";
$time_str = sprintf('%02d:%02d', $hour, $minute);

// 24h cache
$cache_key = md5("{$date_str}_{$time_str}_{$place}_{$gender}");
$cached = DB::one(
    'SELECT content FROM readings
     WHERE type = "personal" AND JSON_EXTRACT(input_data, "$.cache_key") = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
     ORDER BY created_at DESC LIMIT 1',
    [$cache_key]
);
if ($cached) {
    $data = json_decode($cached['content'], true);
    $data['source'] = 'cache';
    $data['zodiac'] = $zodiac;
    json_ok($data);
}

// AI prompt
$prompt = "Generate a detailed personalized horoscope for a {$gender} born on {$date_str} at {$time_str}" .
    ($place ? " in {$place}" : '') .
    ". Zodiac sign: {$zodiac}.\n\n" .
    "Respond ONLY with a JSON object with exactly these 5 keys:\n" .
    '{"love":"...","career":"...","money":"...","health":"...","home":"..."}' . "\n" .
    "Each value: 2-3 sentences. Be specific, mystical, and empowering. Reference birth time and place when relevant.";

$result = call_groq($prompt);
if (!$result)
    json_err('AI generation failed. Please try again.', 503);

$defaults = [
    'love' => 'The stars align beautifully in your love life.',
    'career' => 'Your professional energy peaks. Trust your instincts.',
    'money' => 'Financial opportunities are approaching.',
    'health' => 'Your vitality is strong. Maintain balance.',
    'home' => 'Harmony flows through your family connections.',
];
foreach ($defaults as $k => $v) {
    if (empty($result[$k]))
        $result[$k] = $v;
}

// Save to reading history if user logged in
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($auth, 'Bearer ')) {
    $session = DB::one('SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()', [substr($auth, 7)]);
    if ($session) {
        DB::insert(
            'INSERT INTO readings (user_id, type, zodiac, input_data, content) VALUES (?,?,?,?,?)',
            [
                $session['user_id'],
                'personal',
                $zodiac,
                json_encode(compact('day', 'month', 'year', 'hour', 'minute', 'place', 'gender', 'cache_key')),
                json_encode($result),
            ]
        );
    }
}

$result['zodiac'] = $zodiac;
$result['source'] = 'ai';
json_ok($result);
