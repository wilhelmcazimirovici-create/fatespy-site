<?php
/**
 * POST /api/reading/tarot.php
 * Body: { spread?: "celtic_cross"|"three_card"|"single", question?: "..." }
 * Returns: AI tarot card reading as JSON
 *
 * Requires: authenticated user with 'tarot_session' service or VIP plan
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['POST']);
Security::rateLimit('reading_tarot', 10, 3600);

$user = require_auth();
$uid = $user['user_id'];
$body = get_body();

// Validate service
if ($user['plan'] !== 'vip') {
    $has = DB::one(
        'SELECT id FROM user_services WHERE user_id = ? AND service_slug = "tarot_session"
         AND (expires_at IS NULL OR expires_at > NOW())',
        [$uid]
    );
    if (!$has)
        json_err('You need the Tarot Session service to use this feature', 403);
}

$spread = $body['spread'] ?? 'celtic_cross';
if (!in_array($spread, ['celtic_cross', 'three_card', 'single']))
    $spread = 'celtic_cross';

$question = Security::sanitizeString($body['question'] ?? '', 500);

// Major Arcana
$major = [
    'The Fool', 'The Magician', 'The High Priestess', 'The Empress', 'The Emperor',
    'The Hierophant', 'The Lovers', 'The Chariot', 'Strength', 'The Hermit',
    'Wheel of Fortune', 'Justice', 'The Hanged Man', 'Death', 'Temperance',
    'The Devil', 'The Tower', 'The Star', 'The Moon', 'The Sun', 'Judgement', 'The World',
];
// Minor Arcana
$suits = ['Wands', 'Cups', 'Swords', 'Pentacles'];
$ranks = ['Ace', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Page', 'Knight', 'Queen', 'King'];
$minor = [];
foreach ($suits as $suit) {
    foreach ($ranks as $rank) {
        $minor[] = "{$rank} of {$suit}";
    }
}

$deck = array_merge($major, $minor);
shuffle($deck);

// Draw cards based on spread
$spread_sizes = ['single' => 1, 'three_card' => 3, 'celtic_cross' => 10];
$num_cards = $spread_sizes[$spread];
$drawn = array_slice($deck, 0, $num_cards);

// Add reversed/upright
$cards = [];
foreach ($drawn as $card) {
    $reversed = rand(0, 1) === 1;
    $cards[] = [
        'name' => $card,
        'reversed' => $reversed,
        'display' => $reversed ? "{$card} (Reversed)" : $card,
    ];
}

$u = DB::one('SELECT zodiac, name FROM users WHERE id = ?', [$uid]);
$zodiac_str = $u['zodiac'] ? " The querent's zodiac sign is {$u['zodiac']}." : '';
$question_str = $question ? " The querent asks: \"{$question}\"" : '';

// Position names for Celtic Cross
$position_names = [
    'celtic_cross' => ['Present', 'Challenge', 'Past Foundation', 'Recent Past', 'Best Outcome', 'Near Future', 'Self-Image', 'External Influences', 'Hopes & Fears', 'Final Outcome'],
    'three_card' => ['Past', 'Present', 'Future'],
    'single' => ['The Card'],
];

$cards_desc = [];
foreach ($cards as $i => $c) {
    $pos = $position_names[$spread][$i] ?? "Position " . ($i + 1);
    $cards_desc[] = "{$pos}: {$c['display']}";
}
$cards_str = implode('; ', $cards_desc);

$prompt = "You are performing a {$spread} tarot reading.{$zodiac_str}{$question_str}\n\n" .
    "Cards drawn: {$cards_str}\n\n" .
    "Interpret each card in its position. For reversed cards, give the reversed meaning.\n\n" .
    "Respond ONLY with a JSON object with these keys:\n" .
    '{"card_interpretations":[{"position":"...","card":"...","reversed":false,"meaning":"..."}],"synthesis":"...","advice":"...","energy":"..."}' . "\n" .
    "card_interpretations: array with one entry per card. synthesis: 3-4 sentences tying all cards together. " .
    "advice: 2-3 sentences of actionable guidance. energy: one-word energy of the reading (e.g. 'Transformative').";

$result = call_groq(
    $prompt,
    'You are FateSpy, an expert tarot reader and mystic. Respond ONLY with valid JSON.',
    0.9,
    2000
);
if (!$result)
    json_err('AI analysis failed. Please try again.', 503);

// Ensure structure
if (empty($result['card_interpretations']) || !is_array($result['card_interpretations'])) {
    // Build from individual cards
    $result['card_interpretations'] = [];
    foreach ($cards as $i => $c) {
        $pos = $position_names[$spread][$i] ?? "Position " . ($i + 1);
        $result['card_interpretations'][] = [
            'position' => $pos,
            'card' => $c['name'],
            'reversed' => $c['reversed'],
            'meaning' => "The {$c['display']} in the {$pos} position speaks to your current journey.",
        ];
    }
}
if (empty($result['synthesis']))
    $result['synthesis'] = 'The cards reveal a powerful narrative of growth and transformation in your life.';
if (empty($result['advice']))
    $result['advice'] = 'Trust the wisdom the cards have shared. Meditate on the key themes that emerged.';
if (empty($result['energy']))
    $result['energy'] = 'Illuminating';

$result['spread'] = $spread;
$result['cards_drawn'] = $cards;

$reading_id = DB::insert(
    'INSERT INTO readings (user_id, type, zodiac, input_data, content) VALUES (?, ?, ?, ?, ?)',
    [
        $uid,
        'tarot',
        $u['zodiac'] ?? null,
        json_encode(['spread' => $spread, 'question' => $question, 'cards' => $cards]),
        json_encode($result),
    ]
);

Security::auditLog('tarot_reading', $uid, ['spread' => $spread]);

$result['reading_id'] = (int) $reading_id;
$result['source'] = 'ai';
json_ok($result);
