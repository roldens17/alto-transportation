<?php
/**
 * Content model, generated from config. No per-client copy-paste.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	foreach ( ks_config( 'post_types', [] ) as $slug => $args ) {
		if ( ! ks_post_type_active( $slug ) ) {
			continue;
		}

		$singular = $args['singular'] ?? ucfirst( $slug );
		$plural   = $args['plural'] ?? $singular . 's';
		$public   = $args['public'] ?? true;

		register_post_type( $slug, [
			'labels' => [
				'name'          => $plural,
				'singular_name' => $singular,
				'add_new_item'  => "Add {$singular}",
				'edit_item'     => "Edit {$singular}",
				'search_items'  => "Search {$plural}",
			],
			'public'        => $public,
			'show_ui'       => true,
			'show_in_rest'  => true,   // required for the block editor
			'has_archive'   => $public,
			'hierarchical'  => false,
			'menu_icon'     => 'dashicons-' . ( $args['icon'] ?? 'admin-post' ),
			'supports'      => $args['supports'] ?? [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes' ],
			'rewrite'       => $public ? [ 'slug' => $args['rewrite'] ?? sanitize_title( $plural ) ] : false,
			'template'      => $args['template'] ?? [],
		] );

		if ( ! empty( $args['taxonomy'] ) ) {
			register_taxonomy( $args['taxonomy'], $slug, [
				'labels' => [
					'name'          => $args['taxonomy_plural'] ?? 'Regions',
					'singular_name' => $args['taxonomy_singular'] ?? 'Region',
				],
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
			] );
		}
	}
} );

/**
 * A post type is active if its module is on. Keeps a hair salon site from
 * showing a "Fleet" menu.
 */
function ks_post_type_active( string $slug ): bool {
	$map = [
		'ks_place'    => 'places',
		'ks_vehicle'  => 'fleet',
		'ks_rate'     => 'booking',
		'ks_activity' => 'activities',
	];

	$module = $map[ $slug ] ?? null;

	return null === $module ? true : ks_module_enabled( $module );
}
