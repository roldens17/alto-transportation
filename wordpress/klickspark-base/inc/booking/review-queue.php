<?php
/**
 * Conflict-check review queue.
 *
 * Reserved times sit here until a human clears them. Approving releases the
 * confirmation; declining frees the slot and tells the enquirer why in the
 * firm's own words rather than a silent cancellation.
 */

defined( 'ABSPATH' ) || exit;

class KS_Review_Queue {

	const SLUG = 'ks-review-queue';

	public static function init(): void {
		if ( ! ks_config( 'booking.conflict_check', false ) ) {
			return;
		}

		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_post_ks_review_action', [ self::class, 'handle' ] );
	}

	public static function menu(): void {
		$pending = count( KS_Slot_Store::by_status( 'pending' ) );
		$label   = $pending ? sprintf( 'Review queue <span class="update-plugin-count">%d</span>', $pending ) : 'Review queue';

		add_menu_page(
			'Review queue',
			$label,
			'edit_posts',
			self::SLUG,
			[ self::class, 'render' ],
			'dashicons-shield-alt',
			26
		);
	}

	public static function render(): void {
		$pending = KS_Slot_Store::by_status( 'pending' );
		?>
		<div class="wrap">
			<h1>Review queue</h1>
			<p>Times reserved for a client, awaiting a conflict check. The slot is off the calendar until you decide.</p>

			<?php if ( isset( $_GET['done'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'approved' === $_GET['done'] ? 'Confirmed and the client has been emailed.' : 'Declined and the time is back on the calendar.' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $pending ) : ?>
				<p><strong>Nothing waiting.</strong> New requests will appear here.</p>
				<?php return; ?>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>When</th>
						<th>Who</th>
						<th>Matter</th>
						<th>Contact</th>
						<th>Intake</th>
						<th>Decision</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $pending as $row ) :
					$start   = ( new DateTimeImmutable( $row->start_utc, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
					$contact = $row->enquiry_id ? get_post_meta( (int) $row->enquiry_id, '_ks_contact', true ) : [];
					$answers = $row->enquiry_id ? get_post_meta( (int) $row->enquiry_id, '_ks_answers', true ) : [];
					?>
					<tr>
						<td><strong><?php echo esc_html( wp_date( 'D j M, g:ia', $start->getTimestamp() ) ); ?></strong></td>
						<td><?php echo esc_html( get_the_title( (int) $row->practitioner_id ) ); ?></td>
						<td><?php echo esc_html( get_the_title( (int) $row->service_id ) ); ?></td>
						<td>
							<?php echo esc_html( $contact['name'] ?? $row->patient_name ); ?><br>
							<a href="mailto:<?php echo esc_attr( $contact['email'] ?? '' ); ?>"><?php echo esc_html( $contact['email'] ?? '' ); ?></a>
							<?php if ( ! empty( $contact['phone'] ) ) : ?><br><?php echo esc_html( $contact['phone'] ); ?><?php endif; ?>
						</td>
						<td>
							<?php if ( $answers ) : ?>
								<details>
									<summary><?php echo esc_html( sprintf( '%d answers', count( $answers ) ) ); ?></summary>
									<dl>
										<?php foreach ( $answers as $question => $answer ) : ?>
											<dt><strong><?php echo esc_html( $question ); ?></strong></dt>
											<dd><?php echo esc_html( $answer ); ?></dd>
										<?php endforeach; ?>
									</dl>
								</details>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:.4rem">
								<?php wp_nonce_field( 'ks_review_' . $row->id ); ?>
								<input type="hidden" name="action" value="ks_review_action">
								<input type="hidden" name="appointment" value="<?php echo esc_attr( $row->id ); ?>">
								<button class="button button-primary" name="decision" value="approve">Clear</button>
								<button class="button" name="decision" value="decline">Conflict</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function handle(): void {
		$id = absint( $_POST['appointment'] ?? 0 );

		if ( ! $id || ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'ks_review_' . $id ) ) {
			wp_die( 'Not allowed.' );
		}

		$decision = 'approve' === ( $_POST['decision'] ?? '' ) ? 'approve' : 'decline';
		$row      = KS_Slot_Store::get( $id );

		if ( ! $row ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
			exit;
		}

		if ( 'approve' === $decision ) {
			KS_Slot_Store::approve( $id );
		} else {
			KS_Slot_Store::decline( $id );
		}

		self::notify( $row, $decision );

		do_action( 'ks/review/decided', $id, $decision );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&done=' . ( 'approve' === $decision ? 'approved' : 'declined' ) ) );
		exit;
	}

	private static function notify( object $row, string $decision ): void {
		if ( ! $row->patient_email ) {
			return;
		}

		$start = ( new DateTimeImmutable( $row->start_utc, new DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
		$when  = wp_date( 'l j F Y, g:ia', $start->getTimestamp() );

		if ( 'approve' === $decision ) {
			$subject = 'Consultation confirmed — ' . get_bloginfo( 'name' );
			$body    = [
				"Your consultation is confirmed for $when with " . get_the_title( (int) $row->practitioner_id ) . '.',
				'',
				ks_config( 'compliance.confirmed_notice', 'Bring any documents you have received, including anything with a receipt or case number on it.' ),
			];
		} else {
			$subject = 'We cannot take this matter — ' . get_bloginfo( 'name' );
			$body    = [
				"Thank you for contacting us. After checking, we are not able to take this matter, and we have released the $when time.",
				'',
				ks_config( 'compliance.declined_notice', 'This is not advice about your case, and no attorney-client relationship was created. If there is a deadline in your matter, please speak with another attorney promptly.' ),
			];
		}

		wp_mail( $row->patient_email, $subject, implode( "\n", $body ) );
	}
}

add_action( 'after_setup_theme', [ 'KS_Review_Queue', 'init' ] );
