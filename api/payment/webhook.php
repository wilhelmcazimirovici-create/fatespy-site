<?php
/**
 * POST /api/payment/webhook.php
 * Stripe Webhook — verifies signature, handles payment events
 * Configure in Stripe Dashboard → Webhooks → https://fatespy.com/api/payment/webhook.php
 * Events to listen to:
 *   - checkout.session.completed
 *   - invoice.paid
 *   - customer.subscription.deleted
 *   - charge.dispute.created
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

// Webhooks must NOT have CORS — raw body only
$payload = file_get_contents('php://input');
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret = get_setting('stripe_webhook_secret') ?: STRIPE_WEBHOOK_SECRET;

// ── Stripe Signature Verification ────────────────────────
// Recreate the expected signature without Stripe SDK
function verify_stripe_signature(string $payload, string $sig_header, string $secret): bool
{
    // Parse t= and v1= from signature header
    $parts = explode(',', $sig_header);
    $timestamp = '';
    $v1sigs = [];
    foreach ($parts as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
        if ($k === 't')
            $timestamp = $v;
        if ($k === 'v1')
            $v1sigs[] = $v;
    }
    if (!$timestamp || empty($v1sigs))
        return false;

    // Reject old webhooks (>5 minutes) to prevent replay attacks
    if (abs(time() - (int) $timestamp) > 300)
        return false;

    $signed_payload = $timestamp . '.' . $payload;
    $expected_sig = hash_hmac('sha256', $signed_payload, $secret);

    foreach ($v1sigs as $v1) {
        if (hash_equals($expected_sig, $v1))
            return true;
    }
    return false;
}

if (!verify_stripe_signature($payload, $sig, $secret)) {
    Security::auditLog('webhook_invalid_signature', 0, ['ip' => Security::getIP()]);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);
if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit;
}

$type = $event['type'];
$obj = $event['data']['object'];

// ── Handle events ─────────────────────────────────────────
switch ($type) {

    case 'checkout.session.completed':
        $user_id = (int) ($obj['metadata']['user_id'] ?? 0);
        $service_slug = $obj['metadata']['service_slug'] ?? '';
        $stripe_pi = $obj['payment_intent'] ?? $obj['id'];
        $mode = $obj['mode'] ?? 'payment';

        if ($user_id && $service_slug) {
            // Determine expiry (null = lifetime, 1 month for recurring)
            $expires = $mode === 'subscription'
                ? date('Y-m-d H:i:s', strtotime('+1 month'))
                : null;

            // Upsert ownership
            $existing = DB::one(
                'SELECT id FROM user_services WHERE user_id = ? AND service_slug = ?',
                [$user_id, $service_slug]
            );
            if ($existing) {
                DB::query(
                    'UPDATE user_services SET expires_at = ?, stripe_pi = ?, purchased_at = NOW() WHERE id = ?',
                    [$expires, $stripe_pi, $existing['id']]
                );
            } else {
                DB::insert(
                    'INSERT INTO user_services (user_id, service_slug, stripe_pi, expires_at) VALUES (?, ?, ?, ?)',
                    [$user_id, $service_slug, $stripe_pi, $expires]
                );
            }

            // VIP plan → upgrade user
            if ($service_slug === 'vip_monthly') {
                DB::query('UPDATE users SET plan = "vip" WHERE id = ?', [$user_id]);
            }

            // Log in email_log (send welcome email for purchase)
            $u = DB::one('SELECT email, name FROM users WHERE id = ?', [$user_id]);
            if ($u) {
                $svc = DB::one('SELECT name FROM services WHERE slug = ?', [$service_slug]);
                send_email(
                    $u['email'],
                    "Your FateSpy purchase: " . ($svc['name'] ?? $service_slug),
                    "<h1>Purchase Confirmed!</h1>
                     <p>Hi {$u['name']},</p>
                     <p>Your purchase of <strong>" . htmlspecialchars($svc['name'] ?? $service_slug) . "</strong> is now active.</p>
                     <p><a href='" . SITE_URL . "/user-dashboard.html'>Go to Dashboard</a></p>"
                );
            }

            Security::auditLog('purchase_completed', $user_id, [
                'service' => $service_slug,
                'stripe_pi' => $stripe_pi
            ]);
        }
        break;

    case 'invoice.paid':
        // Recurring subscription renewed — extend expiry
        $customer_email = $obj['customer_email'] ?? '';
        $sub_id = $obj['subscription'] ?? '';
        if ($customer_email) {
            $user = DB::one('SELECT id FROM users WHERE email = ?', [$customer_email]);
            if ($user) {
                DB::query(
                    'UPDATE user_services SET expires_at = DATE_ADD(NOW(), INTERVAL 1 MONTH)
                     WHERE user_id = ? AND service_slug = "vip_monthly"',
                    [$user['id']]
                );
                Security::auditLog('subscription_renewed', $user['id'], ['sub' => $sub_id]);
            }
        }
        break;

    case 'customer.subscription.deleted':
        // Subscription cancelled — revoke VIP
        $customer_email = $obj['customer_email'] ?? '';
        if ($customer_email) {
            $user = DB::one('SELECT id FROM users WHERE email = ?', [$customer_email]);
            if ($user) {
                DB::query('UPDATE users SET plan = "free" WHERE id = ?', [$user['id']]);
                DB::query(
                    'UPDATE user_services SET expires_at = NOW() WHERE user_id = ? AND service_slug = "vip_monthly"',
                    [$user['id']]
                );
                Security::auditLog('subscription_cancelled', $user['id']);
            }
        }
        break;

    case 'charge.dispute.created':
        // Fraud alert — flag user for review
        $charge_id = $obj['id'] ?? '';
        Security::auditLog('dispute_created', 0, ['charge_id' => $charge_id]);
        // Notify admin
        send_email(
            ADMIN_EMAIL,
            '⚠️ Stripe Dispute Created',
            "<p>A chargeback dispute was created for charge: <strong>{$charge_id}</strong></p>
             <p>Check Stripe Dashboard immediately.</p>"
        );
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
