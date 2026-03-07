<?php
/**
 * POST /api/reading/generate.php
 * Generate AI readings for various services
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['POST']);

$user = require_auth();
$uid = $user['user_id'];
$body = get_body();

$service = $body['service'] ?? '';
$zodiac = $body['zodiac'] ?? '';
$images = $body['images'] ?? [];

// Validate service
$valid_services = [
    'daily_horoscope', 'palm_reading', 'aura_scan', 
    'coffee_reading', 'tarot_reading', 'natal_chart'
];

if (!in_array($service, $valid_services)) {
    json_err('Invalid service');
}

// Check service ownership or VIP status
if ($user['plan'] !== 'vip') {
    $has_service = DB::one(
        'SELECT id FROM user_services WHERE user_id = ? AND service_slug = ? AND (expires_at IS NULL OR expires_at > NOW())',
        [$uid, $service]
    );
    if (!$has_service) {
        json_err('You need to purchase this service first', 403);
    }
}

// Validate required images
if (in_array($service, ['palm_reading', 'aura_scan', 'coffee_reading'])) {
    if (empty($images)) {
        json_err('Images are required for this service');
    }
    
    // Check if user has required images
    $required_images = [];
    switch ($service) {
        case 'palm_reading':
            $required_images = ['palm_left', 'palm_right'];
            break;
        case 'aura_scan':
            $required_images = ['aura'];
            break;
        case 'coffee_reading':
            $required_images = ['coffee'];
            break;
    }
    
    foreach ($required_images as $cat) {
        $has_img = DB::one('SELECT id FROM user_images WHERE user_id = ? AND category = ?', [$uid, $cat]);
        if (!$has_img) {
            json_err("Missing required image: {$cat}");
        }
    }
}

// Generate reading based on service type
$reading_data = null;

switch ($service) {
    case 'daily_horoscope':
        $reading_data = generateDailyHoroscope($zodiac, $user);
        break;
        
    case 'palm_reading':
        $reading_data = generatePalmReading($images, $user);
        break;
        
    case 'aura_scan':
        $reading_data = generateAuraScan($images, $user);
        break;
        
    case 'coffee_reading':
        $reading_data = generateCoffeeReading($images, $user);
        break;
        
    case 'tarot_reading':
        $reading_data = generateTarotReading($user);
        break;
        
    case 'natal_chart':
        $reading_data = generateNatalChart($user);
        break;
}

if (!$reading_data) {
    json_err('Failed to generate reading');
}

// Save reading to database
$reading_id = DB::insert(
    'INSERT INTO readings (user_id, type, zodiac, content, created_at) VALUES (?, ?, ?, ?, NOW())',
    [$uid, $service, $zodiac, json_encode($reading_data)]
);

// Update user stats
DB::query(
    'UPDATE users SET last_reading = NOW() WHERE id = ?',
    [$uid]
);

json_ok([
    'reading_id' => (int) $reading_id,
    'service' => $service,
    'data' => $reading_data,
    'generated_at' => date('Y-m-d H:i:s')
]);

// Helper functions for generating readings
function generateDailyHoroscope($zodiac, $user) {
    $prompt = "Generate a personalized daily horoscope for {$zodiac}. Include love, career, money, and health insights. Make it mystical and personalized.";
    
    $result = call_groq($prompt, 'You are FateSpy, a mystical astrologer. Generate a personalized horoscope with love, career, money, and health insights.', 0.85, 800);
    
    return $result ?: [
        'love' => 'The stars align beautifully for love today.',
        'career' => 'Professional opportunities are on the horizon.',
        'money' => 'Financial stability is favored today.',
        'health' => 'Focus on self-care and emotional balance.'
    ];
}

function generatePalmReading($images, $user) {
    $prompt = "Analyze palm reading images and provide detailed insights about life path, relationships, career, and health. Be mystical and specific.";
    
    $result = call_groq($prompt, 'You are an expert palm reader. Provide detailed mystical insights about life path, relationships, career, and health.', 0.88, 1200);
    
    return $result ?: [
        'life_path' => 'Your life line shows remarkable resilience and potential.',
        'relationships' => 'Heart line indicates deep emotional capacity and strong partnerships.',
        'career' => 'Career line suggests success through creative endeavors.',
        'health' => 'Strong health line indicates vitality and longevity.'
    ];
}

function generateAuraScan($images, $user) {
    $prompt = "Analyze aura image and provide insights about emotional state, spiritual energy, chakra balance, and overall well-being.";
    
    $result = call_groq($prompt, 'You are an aura expert. Analyze colors and energy patterns to provide spiritual and emotional insights.', 0.82, 1000);
    
    return $result ?: [
        'dominant_color' => 'Blue - Indicates calmness and intuition',
        'emotional_state' => 'Balanced and harmonious emotional field',
        'chakra_balance' => 'All chakras are well-aligned and energized',
        'spiritual_advice' => 'Trust your intuition and maintain current spiritual practices'
    ];
}

function generateCoffeeReading($images, $user) {
    $prompt = "Analyze coffee cup symbols and provide mystical insights about upcoming events, opportunities, and warnings.";
    
    $result = call_groq($prompt, 'You are a coffee cup reader. Interpret symbols and patterns to provide mystical guidance about the future.', 0.86, 900);
    
    return $result ?: [
        'symbols' => ['Heart', 'Circle', 'Line'],
        'interpretation' => 'Love and new opportunities are indicated with some challenges to overcome.',
        'timing' => 'Within the next 2-3 weeks',
        'advice' => 'Stay open to new relationships and career opportunities.'
    ];
}

function generateTarotReading($user) {
    $cards = ['The Fool', 'The Magician', 'The High Priestess', 'The Empress', 'The Emperor', 'The Hierophant', 'The Lovers', 'The Chariot', 'Strength', 'The Hermit', 'Wheel of Fortune', 'Justice', 'The Hanged Man', 'Death', 'Temperance', 'The Devil', 'The Tower', 'The Star', 'The Moon', 'The Sun', 'Judgement', 'The World'];
    
    $spread = [];
    for ($i = 0; $i < 3; $i++) {
        $spread[] = [
            'card' => $cards[array_rand($cards)],
            'position' => ['Past', 'Present', 'Future'][$i],
            'meaning' => 'General meaning of this card in the spread'
        ];
    }
    
    return [
        'spread' => 'Past-Present-Future',
        'cards' => $spread,
        'overall_message' => 'The cards indicate a period of transformation and new beginnings.'
    ];
}

function generateNatalChart($user) {
    $prompt = "Generate a comprehensive natal chart analysis including planetary positions, house placements, major aspects, and life path insights.";
    
    $result = call_groq($prompt, 'You are an expert astrologer. Provide a detailed natal chart analysis with planetary positions, houses, aspects, and life path insights.', 0.9, 1500);
    
    return $result ?: [
        'sun_sign' => 'Your Sun sign indicates your core identity and life purpose.',
        'moon_sign' => 'Your Moon sign reveals your emotional nature and inner world.',
        'rising_sign' => 'Your Rising sign shows how you present yourself to others.',
        'major_aspects' => 'Key aspects in your chart indicate significant life themes.',
        'life_path' => 'Your chart suggests a path of spiritual growth and creativity.'
    ];
}
?>