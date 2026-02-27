<?php
/**
 * GET /api/user/readings.php          — list user's reading history
 * GET /api/user/readings.php?id=123    — get single reading
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET']);

$user = require_auth();
$uid = $user['user_id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$type = $_GET['type'] ?? null;
$limit = min((int) ($_GET['limit'] ?? 20), 100);

if ($id) {
    $row = DB::one(
        'SELECT id, type, zodiac, content, created_at FROM readings WHERE id = ? AND user_id = ?',
        [$id, $uid]
    );
    if (!$row)
        json_err('Reading not found', 404);
    $row['content'] = json_decode($row['content'], true);
    json_ok($row);
}

$sql = 'SELECT id, type, zodiac, created_at, LEFT(content, 200) as preview FROM readings WHERE user_id = ?';
$params = [$uid];
if ($type) {
    $sql .= ' AND type = ?';
    $params[] = $type;
}
$sql .= ' ORDER BY created_at DESC LIMIT ?';
$params[] = $limit;

$rows = DB::all($sql, $params);
json_ok($rows);
