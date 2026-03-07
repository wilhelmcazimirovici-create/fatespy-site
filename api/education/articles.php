<?php
/**
 * GET /api/education/articles.php
 * Educational content about astrology and divination
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_err('Method not allowed', 405);
}

$category = $_GET['category'] ?? 'all';
$limit = min((int) ($_GET['limit'] ?? 10), 50);
$offset = max(0, (int) ($_GET['offset'] ?? 0));

$valid_categories = ['all', 'beginner', 'advanced', 'tarot', 'palmistry', 'astrology', 'meditation'];

if (!in_array($category, $valid_categories)) {
    json_err('Invalid category');
}

$where = 'WHERE status = "published"';
$params = [];

if ($category !== 'all') {
    $where .= ' AND category = ?';
    $params[] = $category;
}

// Get articles
$articles = DB::all(
    "SELECT id, title, slug, excerpt, category, reading_time, created_at, updated_at, featured_image 
     FROM articles {$where} 
     ORDER BY created_at DESC 
     LIMIT {$offset}, {$limit}",
    $params
);

// Get featured articles
$featured = DB::all(
    "SELECT id, title, slug, excerpt, category, reading_time, created_at 
     FROM articles 
     WHERE status = 'published' AND featured = 1 
     ORDER BY created_at DESC 
     LIMIT 6"
);

// Get popular articles
$popular = DB::all(
    "SELECT a.id, a.title, a.slug, a.excerpt, a.category, a.reading_time, 
            COUNT(v.id) as views, COUNT(c.id) as comments
     FROM articles a 
     LEFT JOIN article_views v ON a.id = v.article_id 
     LEFT JOIN article_comments c ON a.id = c.article_id 
     WHERE a.status = 'published' 
     GROUP BY a.id 
     ORDER BY (COUNT(v.id) * 2 + COUNT(c.id)) DESC 
     LIMIT 6"
);

// Get categories
$categories = DB::all(
    "SELECT category, COUNT(*) as count 
     FROM articles 
     WHERE status = 'published' 
     GROUP BY category 
     ORDER BY count DESC"
);

json_ok([
    'articles' => $articles,
    'featured' => $featured,
    'popular' => $popular,
    'categories' => $categories,
    'pagination' => [
        'offset' => $offset,
        'limit' => $limit,
        'has_more' => count($articles) === $limit
    ]
]);
?>