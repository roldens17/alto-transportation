<?php
/** Disposable preview only: seed generic draft pages, never real customer content. */
if ( ! defined('ABSPATH') || ! defined('WP_CLI') || ! WP_CLI ) { exit(1); }
$slug = getenv('KS_CLIENT');
if ( ! is_string($slug) || ! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $slug) ) {
    WP_CLI::error('Invalid preview client ID.');
}
$theme = wp_get_theme();
if ( $theme->get_stylesheet() !== $slug ) {
    WP_CLI::error('Active preview theme does not match client ID.');
}
$pages = [
    'home' => 'Home', 'services' => 'Services', 'about' => 'About',
    'contact' => 'Contact', 'privacy' => 'Privacy'
];
foreach ($pages as $page_slug => $title) {
    if (get_page_by_path($page_slug, OBJECT, 'page')) {
        WP_CLI::log("Preserved existing page: $page_slug");
        continue;
    }
    $content = '<!-- wp:paragraph --><p>Placeholder preview content only. Replace with approved business information before publication.</p><!-- /wp:paragraph -->';
    $id = wp_insert_post([
        'post_type' => 'page', 'post_name' => $page_slug,
        'post_title' => $title, 'post_content' => $content,
        'post_status' => 'draft'
    ], true);
    if (is_wp_error($id)) WP_CLI::error($id->get_error_message());
    WP_CLI::log("Created draft page: $page_slug");
}
WP_CLI::success('Preview draft pages seeded. No pages published, payments enabled, or live client data changed.');
