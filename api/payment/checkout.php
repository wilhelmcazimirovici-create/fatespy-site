<?php
/**
 * POST /api/payment/checkout.php
 * Body: { service_slug }
 * Creates a Stripe Checkout Session and returns the URL
 * Requires: Authorization Bearer token
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['POST']);

// Rate limit: 10 checkout attempts per hour per IP
Security::rateLimit('checkout', 10, 3600);

$user = require_auth();
$uid = $user['user_id'];
$body = get_body();

$slug = Security::sanitizeString($body['service_slug'] ?? '', 60);
if (!$slug)
    json_err('service_slug is required');

// Load service
$service = DB::one('SELECT * FROM services WHERE slug = ? AND active = 1', [$slug]);
if (!$service)
    json_err('Service not found', 404);

// Check if already owned
$owned = DB::one(
    'SELECT id FROM user_services WHERE user_id = ? AND service_slug = ? AND (expires_at IS NULL OR expires_at > NOW())',
    [$uid, $slug]
);
if ($owned)
    json_err('You already own this service');

$u = DB::one('SELECT email, name FROM users WHERE id = ?', [$uid]);

// ── Stripe API call (via cURL — no Composer needed) ──────
$price_cents = (int) $service['price'];
$currency = $service['currency'] ?? 'usd';
$is_recurring = (bool) $service['recurring'];

$stripe_sk = get_setting('stripe_sk') ?: STRIPE_SK;
if (!$stripe_sk || str_starts_with($stripe_sk, 'sk_live_CHANGE')) {
    json_err('Stripe is not configured yet. Contact support.', 503);
}

$success_url = SITE_URL . '/user-dashboard.html?payment=success&service=' . urlencode($slug);
$cancel_url = SITE_URL . '/user-dashboard.html?payment=cancelled';

// Build Stripe POST params
$params = [
    'mode' => $is_recurring ? 'subscription' : 'payment',
    'customer_email' => $u['email'],
    'success_url' => $success_url,
    'cancel_url' => $cancel_url,
    'metadata[user_id]' => $uid,
    'metadata[service_slug]' => $slug,
    'line_items[0][quantity]' => 1,
];

if ($is_recurring) {
    // Requires a pre-created Stripe Price ID for subscriptions
    $price_id = get_setting('stripe_price_' . $slug);
    if (!$price_id)
        json_err('Subscription price not configured. Contact admin.', 503);
    $params['line_items[0][price]'] = $price_id;
} else {
    $params['line_items[0][price_data][currency]'] = $currency;
    $params['line_items[0][price_data][unit_amount]'] = $price_cents;
    $params['line_items[0][price_data][product_data][name]'] = $service['name'];
    $params['line_items[0][price_data][product_data][description]'] = $service['description'] ?? '';
}

// Enable Stripe billing address collection for compliance
$params['billing_address_collection'] = 'required';
$params['payment_method_types[0]'] = 'card';

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERPWD => $stripe_sk . ':',
    CURLOPT_HTTPHEADER => ['Stripe-Version: 2023-10-16'],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($resp, true);
if ($code !== 200 || empty($data['url'])) {
    $err = $data['error']['message'] ?? 'Stripe error';
    Security::auditLog('checkout_failed', $uid, ['slug' => $slug, 'stripe_error' => $err]);
    json_err('Payment initialization failed: ' . $err, 503);
}

Security::auditLog('checkout_created', $uid, ['slug' => $slug, 'session_id' => $data['id']]);

json_ok([
    'checkout_url' => $data['url'],
    'session_id' => $data['id'],
]);
