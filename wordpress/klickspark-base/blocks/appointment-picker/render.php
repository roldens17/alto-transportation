<?php
/**
 * Appointment picker shell. Pre-selecting a service or practitioner turns this
 * into a "book with me" widget for a bio page.
 *
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$ks_id       = wp_unique_id( 'ks-appt-' );
$ks_services = get_posts( [ 'post_type' => 'ks_service', 'posts_per_page' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ] );

if ( ! $ks_services ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p>Add at least one Service before using the appointment picker.</p>';
	}
	return;
}

$ks_preset_service = (int) ( $attributes['serviceId'] ?? 0 );
$ks_preset_person  = (int) ( $attributes['practitionerId'] ?? 0 );
$ks_people         = $ks_preset_person ? [ get_post( $ks_preset_person ) ] : get_posts( [ 'post_type' => 'ks_practitioner', 'posts_per_page' => -1, 'orderby' => 'menu_order title' ] );

$ks_payment = ks_config( 'booking.payment', 'woo' );

$ks_boot = [
	'restUrl'      => esc_url_raw( rest_url( 'ks/v1' ) ),
	'nonce'        => wp_create_nonce( 'wp_rest' ),
	'days'         => max( 7, (int) ( $attributes['days'] ?? 14 ) ),
	'preset'       => [ 'service' => $ks_preset_service, 'practitioner' => $ks_preset_person ],
	'payment'      => $ks_payment,
	'requirePhone' => (bool) ks_config( 'booking.require_phone' ),
];
?>
<div
	<?php echo wp_kses_data( get_block_wrapper_attributes( [ 'class' => 'ks-appt' ] ) ); ?>
	id="<?php echo esc_attr( $ks_id ); ?>"
	data-ks-appt="<?php echo esc_attr( wp_json_encode( $ks_boot ) ); ?>"
>
	<?php if ( ! empty( $attributes['heading'] ) ) : ?>
		<h2 class="ks-appt__heading"><?php echo esc_html( $attributes['heading'] ); ?></h2>
	<?php endif; ?>

	<ol class="ks-appt__progress" data-ks-progress>
		<li data-for="service" class="is-current"><?php echo esc_html( $attributes['serviceLabel'] ?? 'Service' ); ?></li>
		<li data-for="who"><?php echo esc_html( $attributes['personLabel'] ?? 'Practitioner' ); ?></li>
		<li data-for="when">Time</li>
		<?php if ( 'none' === $ks_payment ) : ?>
			<li data-for="details">Your details</li>
		<?php endif; ?>
	</ol>

	<div class="ks-appt__step" data-step="service">
		<div class="ks-appt__cards">
			<?php foreach ( $ks_services as $ks_service ) :
				$ks_duration = (int) get_field( 'duration_minutes', $ks_service->ID );
				$ks_price    = (float) get_field( 'price', $ks_service->ID );
				?>
				<button
					type="button"
					class="ks-card<?php echo $ks_preset_service === $ks_service->ID ? ' is-selected' : ''; ?>"
					data-ks-service="<?php echo esc_attr( $ks_service->ID ); ?>"
					data-duration="<?php echo esc_attr( $ks_duration ); ?>"
				>
					<span class="ks-card__name"><?php echo esc_html( get_the_title( $ks_service ) ); ?></span>
					<span class="ks-card__meta">
						<span class="ks-num"><?php echo esc_html( $ks_duration ); ?></span> min
						<?php if ( $ks_price ) : ?>
							· <span class="ks-num"><?php echo esc_html( strip_tags( wc_price( $ks_price ) ) ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( $ks_service->post_excerpt ) : ?>
						<span class="ks-card__desc"><?php echo esc_html( $ks_service->post_excerpt ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="ks-appt__step" data-step="who" hidden>
		<div class="ks-appt__cards">
			<button type="button" class="ks-card is-selected" data-ks-person="0">
				<span class="ks-card__name">First available</span>
				<span class="ks-card__meta">Soonest opening across the team</span>
			</button>
			<?php foreach ( $ks_people as $ks_person ) :
				if ( ! $ks_person ) {
					continue;
				}
				?>
				<button type="button" class="ks-card" data-ks-person="<?php echo esc_attr( $ks_person->ID ); ?>">
					<?php if ( has_post_thumbnail( $ks_person ) ) : ?>
						<?php echo get_the_post_thumbnail( $ks_person, 'thumbnail', [ 'class' => 'ks-card__avatar', 'alt' => '' ] ); ?>
					<?php endif; ?>
					<span class="ks-card__name"><?php echo esc_html( get_the_title( $ks_person ) ); ?></span>
					<span class="ks-card__meta"><?php echo esc_html( (string) get_field( 'credentials', $ks_person->ID ) ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
		<p class="ks-appt__actions">
			<button type="button" class="ks-btn" data-ks-action="back">Back</button>
			<button type="button" class="ks-btn ks-btn--primary" data-ks-action="times">See times</button>
		</p>
	</div>

	<div class="ks-appt__step" data-step="when" hidden>
		<div class="ks-appt__days" data-ks-days></div>
		<p class="ks-appt__actions">
			<button type="button" class="ks-btn" data-ks-action="back">Back</button>
			<button type="button" class="ks-btn ks-btn--primary" data-ks-action="confirm" disabled>
				<?php echo 'none' === $ks_payment ? 'Continue' : 'Confirm and continue'; ?>
			</button>
		</p>
		<p class="ks-appt__note">
			<?php echo 'none' === $ks_payment
				? 'Nothing is charged and nothing is confirmed at this step.'
				: 'Times hold for 15 minutes while you finish booking.'; ?>
		</p>
	</div>

	<?php if ( 'none' === $ks_payment ) : ?>
		<div class="ks-appt__step" data-step="details" hidden>
			<p class="ks-appt__chosen" data-ks-chosen></p>

			<div class="ks-appt__fields">
				<p class="ks-field">
					<label for="<?php echo esc_attr( $ks_id ); ?>-name">Full name</label>
					<input type="text" id="<?php echo esc_attr( $ks_id ); ?>-name" name="name" autocomplete="name" required>
				</p>
				<p class="ks-field">
					<label for="<?php echo esc_attr( $ks_id ); ?>-email">Email</label>
					<input type="email" id="<?php echo esc_attr( $ks_id ); ?>-email" name="email" autocomplete="email" required>
				</p>
				<p class="ks-field">
					<label for="<?php echo esc_attr( $ks_id ); ?>-phone">Phone<?php echo ks_config( 'booking.require_phone' ) ? '' : ' (optional)'; ?></label>
					<input type="tel" id="<?php echo esc_attr( $ks_id ); ?>-phone" name="phone" autocomplete="tel">
				</p>
			</div>

			<div class="ks-appt__intake" data-ks-intake></div>

			<?php if ( ks_config( 'compliance.enabled' ) ) : ?>
				<div class="ks-appt__consent">
					<?php echo do_blocks( '<!-- wp:ks/legal-notice /-->' ); ?>
					<p class="ks-field ks-field--check">
						<label>
							<input type="checkbox" name="consent" value="1" required>
							I have read the notice above and understand that sending this form does not create an attorney-client relationship.
						</label>
					</p>
				</div>
			<?php endif; ?>

			<p class="ks-appt__actions">
				<button type="button" class="ks-btn" data-ks-action="back">Back</button>
				<button type="button" class="ks-btn ks-btn--primary" data-ks-action="submit">Request this time</button>
			</p>
		</div>
	<?php endif; ?>

	<div class="ks-appt__feedback" data-ks-feedback role="status" aria-live="polite"></div>

	<noscript>
		<p>Online booking needs JavaScript. Call the clinic and we will book you in.</p>
	</noscript>
</div>
