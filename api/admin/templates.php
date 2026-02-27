<?php
/**
 * GET  /api/admin/templates.php         — list all email templates
 * GET  /api/admin/templates.php?slug=x  — get specific template
 * POST /api/admin/templates.php         — create or update template
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';

set_json_headers(['GET', 'POST']);
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['slug'])) {
        $slug = $_GET['slug'];
        $tpl = DB::one('SELECT * FROM email_templates WHERE slug = ?', [$slug]);
        if (!$tpl)
            json_err('Template not found', 404);
        json_ok($tpl);
    } else {
        $templates = DB::all('SELECT id, slug, name, subject, preheader, trigger, active, updated_at FROM email_templates ORDER BY name');
        json_ok($templates);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = get_body();

    $slug = $body['slug'] ?? '';
    $name = $body['name'] ?? '';
    $subject = $body['subject'] ?? '';
    $preheader = $body['preheader'] ?? '';
    $body_html = $body['body_html'] ?? '';

    if (!$slug || !$name || !$subject || !$body_html) {
        json_err('Slug, name, subject, and body_html are required.');
    }

    // Check if exists
    $existing = DB::one('SELECT id FROM email_templates WHERE slug = ?', [$slug]);

    if ($existing) {
        DB::query(
            'UPDATE email_templates SET name = ?, subject = ?, preheader = ?, body_html = ?, updated_at = NOW() WHERE slug = ?',
            [$name, $subject, $preheader, $body_html, $slug]
        );
        json_ok(['message' => 'Template updated']);
    } else {
        $trigger = $body['trigger'] ?? null;
        DB::insert(
            'INSERT INTO email_templates (slug, name, subject, preheader, body_html, trigger) VALUES (?, ?, ?, ?, ?, ?)',
            [$slug, $name, $subject, $preheader, $body_html, $trigger]
        );
        json_ok(['message' => 'Template created']);
    }
}
