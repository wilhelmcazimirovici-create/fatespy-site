<?php
/**
 * FateSpy — Daily Horoscope Generator (Cron Job)
 * ════════════════════════════════════════════════
 * Hostico cPanel Cron: 0 6 * * * php /home/USERNAME/public_html/cron/generate-horoscopes.php
 *
 * Also callable from admin panel via POST /api/admin/generate.php
 *
 * Generates: daily (every day), weekly (Mondays), monthly (1st), yearly (Jan 1)
 */

// Allow both CLI and web call (web requires admin auth)
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/helpers.php';
    set_json_headers(['POST']);
    require_admin();
} else {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/helpers.php';
}

set_time_limit(600);

$zodiacs = ['aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo', 'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'];
$today = date('Y-m-d');
$dow = (int) date('N');   // 1=Monday
$dom = (int) date('j');   // day of month
$doy = (int) date('z');   // day of year

// Determine which periods to generate today
$periods_to_generate = ['daily'];
if ($dow === 1)
    $periods_to_generate[] = 'weekly';
if ($dom === 1)
    $periods_to_generate[] = 'monthly';
if ($doy === 0)
    $periods_to_generate[] = 'yearly';

// Override: if called from admin with ?periods=daily,weekly
if (!empty($_GET['periods'])) {
    $periods_to_generate = array_intersect(
        explode(',', $_GET['periods']),
        ['daily', 'weekly', 'monthly', 'yearly']
    );
}

$results = [];
$errors = [];

function build_prompt(string $zodiac, string $period): string
{
    $period_map = [
        'daily' => 'today',
        'weekly' => 'this week',
        'monthly' => 'this month',
        'yearly' => 'this year',
    ];
    $timeframe = $period_map[$period] ?? $period;
    $zodiac_cap = ucfirst($zodiac);

    return "Generate a horoscope for {$zodiac_cap} for {$timeframe}.\n" .
        "Include astrological insights about key planetary influences.\n" .
        "Respond ONLY with a JSON object with exactly these 5 keys:\n" .
        '{"love":"...","career":"...","money":"...","health":"...","home":"..."}' . "\n" .
        "Each value: 2-3 sentences. Be mystical, empowering, and specific to {$zodiac_cap}.";
}

foreach ($periods_to_generate as $period) {
    foreach ($zodiacs as $zodiac) {
        // Skip if already generated today for daily, this week for weekly, etc.
        $existing = DB::one(
            'SELECT id FROM horoscopes WHERE zodiac=? AND period=? AND generated_at=?',
            [$zodiac, $period, $today]
        );
        if ($existing && !isset($_GET['force'])) {
            $results[] = "SKIP {$zodiac}/{$period} — already exists";
            continue;
        }

        $prompt = build_prompt($zodiac, $period);
        $data = call_groq($prompt, 'You are FateSpy, a professional astrologer. Respond ONLY with valid JSON.', 0.88, 800);

        if (!$data) {
            $errors[] = "FAIL {$zodiac}/{$period} — AI error";
            usleep(500000); // 0.5s pause on error
            continue;
        }

        $defaults = [
            'love' => ucfirst($zodiac) . ' shines in matters of the heart this ' . $period . '.',
            'career' => 'Professional opportunities abound for ' . ucfirst($zodiac) . '.',
            'money' => 'Financial stability increases for ' . ucfirst($zodiac) . ' this ' . $period . '.',
            'health' => ucfirst($zodiac) . ' enjoys good vitality. Balance is key.',
            'home' => 'Family bonds strengthen for ' . ucfirst($zodiac) . ' this ' . $period . '.',
        ];
        foreach ($defaults as $k => $v) {
            if (empty($data[$k]))
                $data[$k] = $v;
        }

        // Upsert
        DB::query(
            'INSERT INTO horoscopes (zodiac, period, content, generated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE content=VALUES(content), created_at=NOW()',
            [$zodiac, $period, json_encode($data, JSON_UNESCAPED_UNICODE), $today]
        );

        $results[] = "OK  {$zodiac}/{$period}";

        // Save JSON file (for fast static serving — no DB query needed)
        $dir = __DIR__ . '/../data/horoscopes/';
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        $all_signs = [];

        // Also update the combined horoscopes.json that main.js reads
        usleep(200000); // 0.2s rate limit between AI calls
    }
}

// Write combined horoscopes.json
try {
    $rows = DB::all(
        'SELECT zodiac, period, content FROM horoscopes WHERE generated_at = ? ORDER BY zodiac, period',
        [$today]
    );
    $out = ['generated_at' => $today, 'readings' => []];
    foreach ($rows as $r) {
        $out['readings'][$r['zodiac']][$r['period']] = json_decode($r['content'], true);
    }
    $json_path = __DIR__ . '/../data/horoscopes.json';
    file_put_contents($json_path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $results[] = 'OK  horoscopes.json written';
} catch (Exception $e) {
    $errors[] = 'FAIL JSON write: ' . $e->getMessage();
}

// Output
$summary = [
    'date' => $today,
    'periods' => $periods_to_generate,
    'ok' => count(array_filter($results, fn($r) => str_starts_with($r, 'OK'))),
    'skipped' => count(array_filter($results, fn($r) => str_starts_with($r, 'SKIP'))),
    'errors' => count($errors),
    'log' => array_merge($results, $errors),
];

if (php_sapi_name() === 'cli') {
    echo "FateSpy Horoscope Generator — {$today}\n";
    echo "Periods: " . implode(', ', $periods_to_generate) . "\n";
    echo "OK: {$summary['ok']}  SKIP: {$summary['skipped']}  ERR: {$summary['errors']}\n";
    foreach ($summary['log'] as $line)
        echo $line . "\n";
} else {
    json_ok($summary);
}
