<?php
/**
 * GET  /api/admin/users.php                     — list users
 * GET  /api/admin/users.php?id=123               — single user
 * POST /api/admin/users.php                     — create user
 * PUT  /api/admin/users.php?id=123               — update user
 * DELETE /api/admin/users.php?id=123            — delete user
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST', 'PUT', 'DELETE']);

require_admin();

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($method === 'GET') {
    if ($id) {
        $u = DB::one('SELECT id,email,name,zodiac,dob,role,plan,active,created_at FROM users WHERE id=?', [$id]);
        if (!$u)
            json_err('User not found', 404);
        $u['services'] = DB::all('SELECT service_slug, purchased_at FROM user_services WHERE user_id=?', [$id]);
        $u['readings_count'] = DB::one('SELECT COUNT(*) as n FROM readings WHERE user_id=?', [$id])['n'];
        json_ok($u);
    }
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    $plan = $_GET['plan'] ?? '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = '(email LIKE ? OR name LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if ($role) {
        $where[] = 'role = ?';
        $params[] = $role;
    }
    if ($plan) {
        $where[] = 'plan = ?';
        $params[] = $plan;
    }

    $whereStr = implode(' AND ', $where);
    $total = DB::one("SELECT COUNT(*) as n FROM users WHERE {$whereStr}", $params)['n'];
    $params[] = $limit;
    $params[] = $offset;
    $users = DB::all("SELECT id,email,name,role,plan,active,created_at FROM users WHERE {$whereStr} ORDER BY created_at DESC LIMIT ? OFFSET ?", $params);

    json_ok(['users' => $users, 'total' => (int) $total, 'page' => $page, 'pages' => ceil($total / $limit)]);
}

if ($method === 'POST') {
    $body = get_body();
    if (!filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL))
        json_err('Invalid email');
    if (strlen($body['password'] ?? '') < 8)
        json_err('Password too short');
    if (DB::one('SELECT id FROM users WHERE email=?', [$body['email']]))
        json_err('Email already exists');

    $uid = DB::insert(
        'INSERT INTO users (email,password_hash,name,role,plan,active) VALUES (?,?,?,?,?,1)',
        [$body['email'], password_hash($body['password'], PASSWORD_DEFAULT), $body['name'] ?? '', $body['role'] ?? 'user', $body['plan'] ?? 'free']
    );
    json_ok(['id' => (int) $uid, 'message' => 'User created'], 201);
}

if ($method === 'PUT' && $id) {
    $body = get_body();
    $allowed = ['name', 'role', 'plan', 'active'];
    $fields = [];
    $params = [];
    foreach ($allowed as $f) {
        if (isset($body[$f])) {
            $fields[] = "`{$f}` = ?";
            $params[] = $body[$f];
        }
    }
    if (!empty($body['password']) && strlen($body['password']) >= 8) {
        $fields[] = 'password_hash = ?';
        $params[] = password_hash($body['password'], PASSWORD_DEFAULT);
    }
    if (empty($fields))
        json_err('Nothing to update');
    $params[] = $id;
    DB::query('UPDATE users SET ' . implode(',', $fields) . ' WHERE id=?', $params);
    json_ok(['message' => 'User updated']);
}

if ($method === 'DELETE' && $id) {
    if ($id === 1)
        json_err('Cannot delete the primary admin');
    DB::query('DELETE FROM users WHERE id=?', [$id]);
    json_ok(['message' => 'User deleted']);
}

json_err('Not found', 404);
