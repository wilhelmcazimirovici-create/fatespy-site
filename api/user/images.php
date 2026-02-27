<?php
/**
 * GET    /api/user/images.php?category=palm_left  — list images
 * POST   /api/user/images.php                      — upload image (multipart)
 * DELETE /api/user/images.php?id=123               — delete image
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST', 'DELETE']);

$user = require_auth();
$uid = $user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

$valid_cats = ['palm_left', 'palm_right', 'aura', 'coffee'];

// ── LIST ──────────────────────────────────────────────────
if ($method === 'GET') {
    $cat = $_GET['category'] ?? null;
    if ($cat && !in_array($cat, $valid_cats))
        json_err('Invalid category');

    $sql = 'SELECT id, category, filename, size_bytes, uploaded_at FROM user_images WHERE user_id = ?';
    $params = [$uid];
    if ($cat) {
        $sql .= ' AND category = ?';
        $params[] = $cat;
    }
    $sql .= ' ORDER BY uploaded_at DESC';

    $images = DB::all($sql, $params);
    foreach ($images as &$img) {
        $img['url'] = UPLOAD_URL . $img['filename'];
    }
    json_ok($images);
}

// ── UPLOAD ────────────────────────────────────────────────
if ($method === 'POST') {
    // Check service ownership for the category
    $cat = $_POST['category'] ?? '';
    if (!in_array($cat, $valid_cats))
        json_err('Invalid category');

    $service_map = [
        'palm_left' => 'palm_reading',
        'palm_right' => 'palm_reading',
        'aura' => 'aura_scan',
        'coffee' => 'coffee_reading',
    ];
    $needed = $service_map[$cat];

    // VIP users skip service check
    if ($user['plan'] !== 'vip') {
        $has = DB::one(
            'SELECT id FROM user_services WHERE user_id = ? AND service_slug = ?
             AND (expires_at IS NULL OR expires_at > NOW())',
            [$uid, $needed]
        );
        if (!$has)
            json_err("You need the '{$needed}' service to upload this image", 403);
    }

    // Max 10 images per category
    $count = DB::one('SELECT COUNT(*) as n FROM user_images WHERE user_id = ? AND category = ?', [$uid, $cat]);
    if ($count['n'] >= 10)
        json_err('Maximum 10 images per category. Delete some first.');

    $subdir = 'u' . $uid . '/' . $cat;
    $relpath = handle_upload('image', $subdir);
    $size = $_FILES['image']['size'] ?? 0;

    $img_id = DB::insert(
        'INSERT INTO user_images (user_id, category, filename, size_bytes) VALUES (?, ?, ?, ?)',
        [$uid, $cat, $relpath, $size]
    );

    json_ok([
        'id' => (int) $img_id,
        'url' => UPLOAD_URL . $relpath,
        'category' => $cat,
        'uploaded_at' => date('Y-m-d H:i:s'),
    ], 201);
}

// ── DELETE ────────────────────────────────────────────────
if ($method === 'DELETE') {
    $img_id = (int) ($_GET['id'] ?? 0);
    $img = DB::one('SELECT id, filename FROM user_images WHERE id = ? AND user_id = ?', [$img_id, $uid]);
    if (!$img)
        json_err('Image not found', 404);

    $path = UPLOAD_DIR . $img['filename'];
    if (file_exists($path))
        @unlink($path);

    DB::query('DELETE FROM user_images WHERE id = ?', [$img_id]);
    json_ok(['message' => 'Image deleted']);
}

json_err('Method not allowed', 405);
