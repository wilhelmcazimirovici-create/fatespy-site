<?php
/**
 * GET /api/social/posts.php
 * POST /api/social/posts.php
 * Social community features for sharing experiences
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);

$user = require_auth();
$uid = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Filter by zodiac if specified
    $zodiac = $_GET['zodiac'] ?? null;
    
    $where = 'WHERE p.is_public = 1';
    $params = [];
    
    if ($zodiac) {
        $where .= ' AND p.zodiac = ?';
        $params[] = $zodiac;
    }
    
    // Get posts
    $posts = DB::all(
        "SELECT p.*, u.name, u.zodiac, 
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as likes,
                (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comments
         FROM posts p 
         JOIN users u ON p.user_id = u.id 
         {$where} 
         ORDER BY p.created_at DESC 
         LIMIT {$offset}, {$limit}",
        $params
    );
    
    // Check if user liked each post
    foreach ($posts as &$post) {
        $post['user_liked'] = DB::one(
            'SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?',
            [$uid, $post['id']]
        ) ? true : false;
    }
    
    json_ok([
        'posts' => $posts,
        'page' => $page,
        'has_more' => count($posts) === $limit
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = get_body();
    
    // Validate input
    $content = trim($body['content'] ?? '');
    $is_public = (int) ($body['is_public'] ?? 0);
    $zodiac = $body['zodiac'] ?? null;
    
    if (empty($content)) {
        json_err('Content is required');
    }
    
    if (strlen($content) > 1000) {
        json_err('Content too long (max 1000 characters)');
    }
    
    // Create post
    $post_id = DB::insert(
        'INSERT INTO posts (user_id, content, zodiac, is_public, created_at) VALUES (?, ?, ?, ?, NOW())',
        [$uid, $content, $zodiac, $is_public]
    );
    
    json_ok([
        'post_id' => (int) $post_id,
        'message' => 'Post created successfully'
    ], 201);
}
?>