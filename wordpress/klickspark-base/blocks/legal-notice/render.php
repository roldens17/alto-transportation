<?php
/**
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$ks_text = $attributes['text'] ?: ks_config( 'compliance.site_notice', '' );

if ( ! $ks_text ) {
	return;
}

$ks_variant = in_array( $attributes['variant'] ?? 'stamp', [ 'stamp', 'plain' ], true ) ? $attributes['variant'] : 'stamp';

do_action( 'ks/compliance/notice_rendered' );
?>
<aside <?php echo wp_kses_data( get_block_wrapper_attributes( [ 'class' => 'ks-notice ks-notice--' . $ks_variant ] ) ); ?>>
	<p class="ks-notice__text"><?php echo esc_html( $ks_text ); ?></p>
	<?php $ks_jurisdictions = ks_compliance_jurisdictions(); ?>
	<?php if ( $ks_jurisdictions ) : ?>
		<p class="ks-notice__meta"><?php echo esc_html( $ks_jurisdictions ); ?></p>
	<?php endif; ?>
</aside>
