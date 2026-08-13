<?php
defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script' ] );

	if ( ks_module_enabled( 'booking' ) ) {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-zoom' );
	}

	load_theme_textdomain( 'ks-base', KS_BASE_DIR . '/languages' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'ks-base', get_template_directory_uri() . '/style.css', [], KS_BASE_VERSION );

	if ( is_child_theme() && file_exists( get_stylesheet_directory() . '/style.css' ) ) {
		wp_enqueue_style( 'ks-child', get_stylesheet_uri(), [ 'ks-base' ], wp_get_theme()->get( 'Version' ) );
	}
} );

/** Performance floor. Every client site gets this without asking. */
add_action( 'init', function () {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
} );

/** Fail loudly in admin when a dependency is missing, rather than silently half-working. */
add_action( 'admin_notices', function () {
	$missing = [];
	if ( ! class_exists( 'ACF' ) ) {
		$missing[] = 'Advanced Custom Fields Pro';
	}
	if ( ks_module_enabled( 'booking' ) && ! class_exists( 'WooCommerce' ) ) {
		$missing[] = 'WooCommerce';
	}
	if ( ks_module_enabled( 'booking' ) && ! ks_config( 'booking.product_id' ) ) {
		$missing[] = 'a booking container product ID in config.php';
	}
	if ( $missing ) {
		printf(
			'<div class="notice notice-error"><p><strong>KlickSpark Base:</strong> %s</p></div>',
			esc_html( 'Not configured: ' . implode( ', ', $missing ) )
		);
	}
} );
