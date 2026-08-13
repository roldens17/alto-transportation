<?php
/**
 * ACF field groups registered in PHP so they ship with the theme and are
 * version-controlled. No JSON sync, no "field group missing on staging".
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	if ( ks_module_enabled( 'fleet' ) ) {
		acf_add_local_field_group( [
			'key'      => 'group_ks_vehicle',
			'title'    => 'Vehicle details',
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'ks_vehicle' ] ] ],
			'fields'   => [
				[ 'key' => 'field_ks_v_class',      'label' => 'Class',            'name' => 'vehicle_class', 'type' => 'text', 'instructions' => 'Shown as the eyebrow label, e.g. Minivan, Luxury SUV.' ],
				[ 'key' => 'field_ks_v_passengers', 'label' => 'Max passengers',   'name' => 'max_passengers', 'type' => 'number', 'required' => 1, 'min' => 1 ],
				[ 'key' => 'field_ks_v_bags',       'label' => 'Max suitcases',    'name' => 'max_bags',       'type' => 'number', 'min' => 0 ],
				[ 'key' => 'field_ks_v_carry',      'label' => 'Max hand bags',    'name' => 'max_carry_on',   'type' => 'number', 'min' => 0 ],
				[ 'key' => 'field_ks_v_order',      'label' => 'Sort weight',      'name' => 'sort_weight',    'type' => 'number', 'default_value' => 10 ],
			],
		] );
	}

	if ( ks_module_enabled( 'places' ) ) {
		acf_add_local_field_group( [
			'key'      => 'group_ks_place',
			'title'    => 'Place details',
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'ks_place' ] ] ],
			'fields'   => [
				[ 'key' => 'field_ks_p_type',    'label' => 'Place type', 'name' => 'place_type', 'type' => 'select', 'choices' => [ 'airport' => 'Airport', 'hotel' => 'Hotel / Resort', 'area' => 'Area / Zone', 'port' => 'Port', 'other' => 'Other' ], 'default_value' => 'area' ],
				[ 'key' => 'field_ks_p_pickup',  'label' => 'Bookable as pickup',  'name' => 'is_pickup',  'type' => 'true_false', 'default_value' => 1, 'ui' => 1 ],
				[ 'key' => 'field_ks_p_drop',    'label' => 'Bookable as dropoff', 'name' => 'is_dropoff', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1 ],
				[ 'key' => 'field_ks_p_lat',     'label' => 'Latitude',  'name' => 'lat', 'type' => 'text' ],
				[ 'key' => 'field_ks_p_lng',     'label' => 'Longitude', 'name' => 'lng', 'type' => 'text' ],
			],
		] );
	}

	if ( ks_module_enabled( 'booking' ) ) {
		acf_add_local_field_group( [
			'key'      => 'group_ks_rate',
			'title'    => 'Rate',
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'ks_rate' ] ] ],
			'fields'   => [
				[ 'key' => 'field_ks_r_from',    'label' => 'From',    'name' => 'from_place', 'type' => 'post_object', 'post_type' => [ 'ks_place' ], 'required' => 1, 'return_format' => 'id' ],
				[ 'key' => 'field_ks_r_to',      'label' => 'To',      'name' => 'to_place',   'type' => 'post_object', 'post_type' => [ 'ks_place' ], 'required' => 1, 'return_format' => 'id' ],
				[ 'key' => 'field_ks_r_vehicle', 'label' => 'Vehicle', 'name' => 'vehicle',    'type' => 'post_object', 'post_type' => [ 'ks_vehicle' ], 'required' => 1, 'return_format' => 'id' ],
				[ 'key' => 'field_ks_r_price',   'label' => 'One-way price', 'name' => 'price', 'type' => 'number', 'required' => 1, 'min' => 0, 'step' => '0.01' ],
				[ 'key' => 'field_ks_r_bidi',    'label' => 'Also applies in reverse', 'name' => 'bidirectional', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1, 'instructions' => 'On means you enter each pair once, not twice.' ],
				[ 'key' => 'field_ks_r_active',  'label' => 'Active',  'name' => 'is_active', 'type' => 'true_false', 'default_value' => 1, 'ui' => 1 ],
			],
		] );
	}
} );

/**
 * Rate rows have no meaningful title, so build one for admin listings.
 */
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( 'ks_rate' !== ( $data['post_type'] ?? '' ) ) {
		return $data;
	}

	$from    = $postarr['acf']['field_ks_r_from'] ?? get_field( 'from_place', $postarr['ID'] ?? 0 );
	$to      = $postarr['acf']['field_ks_r_to'] ?? get_field( 'to_place', $postarr['ID'] ?? 0 );
	$vehicle = $postarr['acf']['field_ks_r_vehicle'] ?? get_field( 'vehicle', $postarr['ID'] ?? 0 );

	if ( $from && $to ) {
		$data['post_title'] = sprintf(
			'%s → %s (%s)',
			get_the_title( (int) $from ),
			get_the_title( (int) $to ),
			$vehicle ? get_the_title( (int) $vehicle ) : 'any'
		);
	}

	return $data;
}, 10, 2 );
