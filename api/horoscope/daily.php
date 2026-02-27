<?php
/**
 * GET  /api/horoscope/daily.php?zodiac=aries&period=daily  — get pre-generated horoscope
 * POST /api/horoscope/personal.php                          — generate personalized horoscope (AI)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);

$endpoint = basename(__FILE__, '.php');

// ── GET DAILY/WEEKLY/MONTHLY/YEARLY ──────────────────────
if ($endpoint === 'daily') {
    $zodiac = strtolower(trim($_GET['zodiac'] ?? ''));
    $period = strtolower(trim($_GET['period'] ?? 'daily'));
    $valid_periods = ['daily', 'weekly', 'monthly', 'yearly'];
    $valid_zodiacs = ['aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo', 'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'];

    if (!in_array($zodiac, $valid_zodiacs))
        json_err('Invalid zodiac sign');
    if (!in_array($period, $valid_periods))
        json_err('Invalid period');

    $row = DB::one(
        'SELECT content, generated_at FROM horoscopes
         WHERE zodiac = ? AND period = ?
         ORDER BY generated_at DESC LIMIT 1',
        [$zodiac, $period]
    );

    if (!$row)
        json_ok(['zodiac' => $zodiac, 'period' => $period, 'content' => null, 'source' => 'none']);

    $content = json_decode($row['content'], true);
    json_ok([
        'zodiac' => $zodiac,
        'period' => $period,
        'content' => $content,
        'generated_at' => $row['generated_at'],
        'source' => 'db',
    ]);
}

// ── POST PERSONALIZED ─────────────────────────────────────
if ($endpoint === 'personal') {
    $body = get_body();

    $day = (int) ($body['day'] ?? 1);
    $month = (int) ($body['month'] ?? 1);
    $year = (int) ($body['year'] ?? 1990);
    $hour = (int) ($body['hour'] ?? 12);
    $minute = (int) ($body['minute'] ?? 0);
    $place = trim($body['place'] ?? '');
    $gender = $body['gender'] === 'female' ? 'female' : 'male';

    // Determine zodiac from birthday
    $zodiacs = [
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
    $zodiac = 'Capricorn';
    foreach ($zodiacs as [$m, $d, $z]) {
        if ($month < $m || ($month === $m && $day <= $d)) {
            $zodiac = $z;
            break;
        }
    }

    $date_str = "{$year}-{$month}-{$day}";
    $time_str = sprintf('%02d:%02d', $hour, $minute);

    // Cache check (same user + same birth data within 24h)
    $cache_key = md5("{$date_str}_{$time_str}_{$place}_{$gender}");
    $cached = DB::one(
        'SELECT content FROM readings
         WHERE type = ? AND JSON_EXTRACT(input_data, "$.cache_key") = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY created_at DESC LIMIT 1',
        ['personal', $cache_key]
    );
    if ($cached) {
        json_ok(array_merge(json_decode($cached['content'], true), ['source' => 'cache']));
    }

    // Build AI prompt
    $prompt = <<<PROMPT
Generate a detailed personalized horoscope for:
- Date of birth: {$date_str}
- Time of birth: {$time_str}
- Place of birth: {$place}
- Gender: {$gender}
- Zodiac sign: {$zodiac}

Respond with ONLY a JSON object with exactly these 5 keys (all required, no others):
{
  "love": "2-3 sentences about love & relationships",
  "career": "2-3 sentences about career & work",
  "money": "2-3 sentences about finances",
  "health": "2-3 sentences about health & energy",
  "home": "2-3 sentences about family & home life"
}
Be specific, mystical, and positive. Reference the birth time and place when relevant.
PROMPT;

    $result = call_groq($prompt);
    if (!$result)
        json_err('AI generation failed. Please try again.', 503);

    // Ensure all 5 keys exist
    $defaults = [
        'love' => 'The stars align beautifully in your love life at this moment.',
        'career' => 'Your professional energy is strong. Trust your instincts.',
        'money' => 'Financial opportunities are approaching. Stay alert.',
        'health' => 'Your vitality is good. Maintain balance in body and mind.',
        'home' => 'Harmony flows through your home and family connections.',
    ];
    foreach ($defaults as $k => $v) {
        if (empty($result[$k]))
            $result[$k] = $v;
    }

    // Optional save to reading history (only if user is logged in)
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($auth_header, 'Bearer ')) {
        $session = DB::one(
            'SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()',
            [substr($auth_header, 7)]
        );
        if ($session) {
            DB::insert(
                'INSERT INTO readings (user_id, type, zodiac, input_data, content) VALUES (?, ?, ?, ?, ?)',
                [
                    $session['user_id'],
                    'personal',
                    $zodiac,
                    json_encode([
                        'day' => $day,
                        'month' => $month,
                        'year' => $year,
                        'hour' => $hour,
                        'minute' => $minute,
                        'place' => $place,
                        'gender' => $gender,
                        'cache_key' => $cache_key
                    ]),
                    json_encode($result),
                ]
            );
        }
    }

    json_ok(array_merge($result, ['zodiac' => $zodiac, 'source' => 'ai']));
}

json_err('Not found', 404);
