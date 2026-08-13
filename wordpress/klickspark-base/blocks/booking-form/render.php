<?php
/**
 * Server-rendered shell. The form is progressively enhanced: the markup below
 * is a working POST target, and view.js upgrades it to live pricing.
 *
 * @var array $attributes
 */

defined( 'ABSPATH' ) || exit;

$ks_id   = wp_unique_id( 'ks-booking-' );
$ks_data = ks_booking_bootstrap_data();
$ks_cfg  = $ks_data['config'];
$ks_min  = ( new DateTimeImmutable( "+{$ks_cfg['minLeadHours']} hours", wp_timezone() ) )->format( 'Y-m-d' );
?>
<div
	<?php echo wp_kses_data( get_block_wrapper_attributes( [ 'class' => 'ks-booking' ] ) ); ?>
	id="<?php echo esc_attr( $ks_id ); ?>"
	data-ks-booking="<?php echo esc_attr( wp_json_encode( $ks_data ) ); ?>"
>
	<?php if ( ! empty( $attributes['heading'] ) ) : ?>
		<h2 class="ks-booking__heading"><?php echo esc_html( $attributes['heading'] ); ?></h2>
	<?php endif; ?>

	<div class="ks-booking__step" data-step="route">
		<div class="ks-booking__grid">
			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-from">Pick up</label>
				<select id="<?php echo esc_attr( $ks_id ); ?>-from" name="from" data-ks-places="pickup" required>
					<option value="">Loading places…</option>
				</select>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-to">Drop off</label>
				<select id="<?php echo esc_attr( $ks_id ); ?>-to" name="to" data-ks-places="dropoff" required>
					<option value="">Loading places…</option>
				</select>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-date">Date</label>
				<input type="date" id="<?php echo esc_attr( $ks_id ); ?>-date" name="date" min="<?php echo esc_attr( $ks_min ); ?>" required>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-time">Time</label>
				<input type="time" id="<?php echo esc_attr( $ks_id ); ?>-time" name="time" step="<?php echo esc_attr( $ks_cfg['timeStep'] * 60 ); ?>" required>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-passengers">Passengers</label>
				<input type="number" id="<?php echo esc_attr( $ks_id ); ?>-passengers" name="passengers" value="2" min="1" max="60" required>
			</p>

			<p class="ks-field">
				<label for="<?php echo esc_attr( $ks_id ); ?>-bags">Suitcases</label>
				<input type="number" id="<?php echo esc_attr( $ks_id ); ?>-bags" name="bags" value="2" min="0" max="60">
			</p>
		</div>

		<?php if ( $ks_cfg['roundTrip'] ) : ?>
			<fieldset class="ks-booking__triptype">
				<legend>Trip type</legend>
				<label><input type="radio" name="trip_type" value="one_way" checked> One way</label>
				<label><input type="radio" name="trip_type" value="round_trip"> Round trip</label>
			</fieldset>

			<div class="ks-booking__return" data-ks-return hidden>
				<p class="ks-field">
					<label for="<?php echo esc_attr( $ks_id ); ?>-rdate">Return date</label>
					<input type="date" id="<?php echo esc_attr( $ks_id ); ?>-rdate" name="return_date" min="<?php echo esc_attr( $ks_min ); ?>">
				</p>
				<p class="ks-field">
					<label for="<?php echo esc_attr( $ks_id ); ?>-rtime">Return time</label>
					<input type="time" id="<?php echo esc_attr( $ks_id ); ?>-rtime" name="return_time" step="<?php echo esc_attr( $ks_cfg['timeStep'] * 60 ); ?>">
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $attributes['showStops'] ) && $ks_cfg['allowStops'] ) : ?>
			<p class="ks-field ks-field--inline">
				<label for="<?php echo esc_attr( $ks_id ); ?>-stops">Extra stops</label>
				<input type="number" id="<?php echo esc_attr( $ks_id ); ?>-stops" name="stops" value="0" min="0" max="6">
				<span class="ks-field__hint"><?php echo esc_html( sprintf( '%s each', strip_tags( wc_price( $ks_cfg['stopFee'] ) ) ) ); ?></span>
			</p>
		<?php endif; ?>

		<?php if ( $ks_cfg['requireFlight'] ) : ?>
			<p class="ks-field" data-ks-flight hidden>
				<label for="<?php echo esc_attr( $ks_id ); ?>-flight">Flight number</label>
				<input type="text" id="<?php echo esc_attr( $ks_id ); ?>-flight" name="flight_no" placeholder="AA1234" autocomplete="off">
				<span class="ks-field__hint">So we can track delays and adjust your pickup.</span>
			</p>
		<?php endif; ?>

		<p class="ks-booking__actions">
			<button type="button" class="ks-btn ks-btn--primary" data-ks-action="quote">See prices</button>
		</p>
	</div>

	<div class="ks-booking__step" data-step="vehicle" hidden>
		<h3 class="ks-booking__subheading">Choose your vehicle</h3>
		<div class="ks-booking__options" data-ks-options role="radiogroup" aria-label="Available vehicles"></div>
		<p class="ks-booking__actions">
			<button type="button" class="ks-btn" data-ks-action="back">Change details</button>
			<button type="button" class="ks-btn ks-btn--primary" data-ks-action="checkout" disabled>Continue to checkout</button>
		</p>
	</div>

	<div class="ks-booking__feedback" data-ks-feedback role="status" aria-live="polite"></div>

	<noscript>
		<p class="ks-booking__noscript">
			Online booking needs JavaScript. Call or message us and we will book it for you.
		</p>
	</noscript>
</div>
