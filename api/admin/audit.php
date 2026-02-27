<?php
/**
 * GET  /api/admin/audit.php              — list audit log
 * GET  /api/admin/audit.php?user_id=123  — filter by user
 * GET  /api/admin/audit.php?action=login_failed — filter by action
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/security.php';

Security::setSecurityHeaders();
set_json_headers(['GET']);
require_admin();

$uid = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$action = Security::sanitizeString($_GET['action'] ?? '', 60);
$ip = Security::sanitizeString($_GET['ip'] ?? '', 45);
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$where = ['1=1'];
$params = [];
if ($uid) {
    $where[] = 'user_id = ?';
    $params[] = $uid;
}
if ($action) {
    $where[] = 'action LIKE ?';
    $params[] = "%{$action}%";
}
if ($ip) {
    $where[] = 'ip = ?';
    $params[] = $ip;
}

$whereStr = implode(' AND ', $where);
$total = DB::one("SELECT COUNT(*) n FROM audit_log WHERE {$whereStr}", $params)['n'];

$countParams = $params;
$params[] = $limit;
$params[] = $offset;

$rows = DB::all(
    "SELECT al.id, al.user_id, u.email, al.action, al.ip, al.ua, al.context, al.created_at
     FROM audit_log al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE {$whereStr}
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?",
    $params
);

foreach ($rows as &$row) {
    $row['context'] = json_decode($row['context'] ?? '{}', true);
}

json_ok([
    'log' => $rows,
    'total' => (int) $total,
    'page' => $page,
    'pages' => (int) ceil($total / $limit),
]);
