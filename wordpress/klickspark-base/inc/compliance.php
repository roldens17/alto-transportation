<?php
/**
 * Regulated-industry notices.
 *
 * Law firm advertising rules are state by state. Rather than hardcoding one
 * state's wording, the notice text and jurisdictions come from config and the
 * theme guarantees they appear.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( ! ks_config( 'compliance.enabled', false ) ) {
		return;
	}

	register_block_type( KS_BASE_DIR . '/blocks/legal-notice' );
} );

/**
 * Belt and braces: append the notice to the footer even if someone deletes the
 * block from a template. Compliance should not depend on an editor remembering.
 */
add_action( 'wp_footer', function () {
	if ( ! ks_config( 'compliance.enabled', false ) || ! ks_config( 'compliance.force_footer', true ) ) {
		return;
	}

	if ( did_action( 'ks/compliance/notice_rendered' ) ) {
		return;
	}

	printf(
		'<div class="ks-notice ks-notice--footer"><p>%s</p></div>',
		esc_html( ks_config( 'compliance.site_notice', '' ) )
	);
}, 5 );

function ks_compliance_jurisdictions(): string {
	$list = (array) ks_config( 'compliance.jurisdictions', [] );

	if ( ! $list ) {
		return '';
	}

	return sprintf(
		'Licensed to practice in %s.',
		count( $list ) > 1
			? implode( ', ', array_slice( $list, 0, -1 ) ) . ' and ' . end( $list )
			: $list[0]
	);
}
