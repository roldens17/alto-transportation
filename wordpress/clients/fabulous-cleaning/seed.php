<?php
/**
 * Safe, idempotent content bootstrap for Fabulous Cleaning.
 * Run: wp eval-file wordpress/clients/fabulous-cleaning/seed.php
 *
 * Does NOT activate payments, publish prices, create crews or enable booking.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( 1 );
}
$pages = [
    'home' => [ 'Home', '<!-- wp:heading {"level":1} --><h1>Fabulous Cleaning LLC</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Professional cleaning services. Website details are being prepared.</p><!-- /wp:paragraph -->' ],
    'services' => [ 'Services', '<!-- wp:paragraph --><p>Service details and pricing will be confirmed before bookings open.</p><!-- /wp:paragraph -->' ],
    'about' => [ 'About', '<!-- wp:paragraph --><p>Learn more about Fabulous Cleaning LLC. Business-approved details coming soon.</p><!-- /wp:paragraph -->' ],
    'contact' => [ 'Contact', '<!-- wp:paragraph --><p>Contact information will be published once verified.</p><!-- /wp:paragraph -->' ],
    'booking' => [ 'Booking', '<!-- wp:paragraph --><p>Online booking is not available yet. Please check back later.</p><!-- /wp:paragraph -->' ],
];
foreach ( $pages as $slug => $data ) {
    $existing = get_page_by_path( $slug, OBJECT, 'page' );
    if ( $existing ) {
        WP_CLI::log( "Preserved existing page: {$slug}" );
        continue;
    }
    $id = wp_insert_post( [
        'post_type' => 'page',
        'post_name' => $slug,
        'post_title' => $data[0],
        'post_content' => $data[1],
        'post_status' => 'draft',
    ], true );
    if ( is_wp_error( $id ) ) {
        WP_CLI::error( "Could not create {$slug}: " . $id->get_error_message() );
    }
    WP_CLI::log( "Created draft page: {$slug}" );
}
$services = [
    'standard-cleaning' => 'Standard Cleaning',
    'deep-cleaning' => 'Deep Cleaning',
    'move-in-out-cleaning' => 'Move-In / Move-Out Cleaning',
];
if ( post_type_exists( 'ks_service' ) ) {
    foreach ( $services as $slug => $title ) {
        $existing = get_posts( [
            'post_type' => 'ks_service', 'name' => $slug,
            'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids',
        ] );
        if ( $existing ) {
            WP_CLI::log( "Preserved existing service: {$slug}" );
            continue;
        }
        $id = wp_insert_post( [
            'post_type' => 'ks_service',
            'post_name' => $slug,
            'post_title' => $title,
            'post_status' => 'draft',
        ], true );
        if ( is_wp_error( $id ) ) {
            WP_CLI::error( "Could not create {$slug}: " . $id->get_error_message() );
        }
        WP_CLI::log( "Created draft service: {$slug}" );
    }
} else {
    WP_CLI::warning( 'ks_service is not registered. Activate the compatible theme before seeding services.' );
}
WP_CLI::success( 'Draft content seeded without overwriting existing posts. Booking and payments remain unconfigured.' );
