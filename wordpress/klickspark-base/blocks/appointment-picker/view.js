/**
 * Appointment picker. Three steps, one state object, no build step.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-ks-appt]' ).forEach( init );

	function init( root ) {
		const boot = JSON.parse( root.dataset.ksAppt );

		const el = {
			progress: root.querySelector( '[data-ks-progress]' ),
			days: root.querySelector( '[data-ks-days]' ),
			feedback: root.querySelector( '[data-ks-feedback]' ),
			confirm: root.querySelector( '[data-ks-action="confirm"]' ),
			submit: root.querySelector( '[data-ks-action="submit"]' ),
			times: root.querySelector( '[data-ks-action="times"]' ),
			intake: root.querySelector( '[data-ks-intake]' ),
			chosen: root.querySelector( '[data-ks-chosen]' ),
			steps: root.querySelectorAll( '[data-step]' ),
		};

		const state = {
			step: 'service',
			service: boot.preset.service || 0,
			practitioner: boot.preset.practitioner || 0,
			date: '',
			time: '',
			busy: false,
		};

		if ( state.service ) {
			go( 'who' );
		}

		root.querySelectorAll( '[data-ks-service]' ).forEach( ( card ) => {
			card.addEventListener( 'click', () => {
				state.service = Number( card.dataset.ksService );
				select( '[data-ks-service]', card );
				go( 'who' );
			} );
		} );

		root.querySelectorAll( '[data-ks-person]' ).forEach( ( card ) => {
			card.addEventListener( 'click', () => {
				state.practitioner = Number( card.dataset.ksPerson );
				select( '[data-ks-person]', card );
			} );
		} );

		el.times && el.times.addEventListener( 'click', loadTimes );
		el.confirm && el.confirm.addEventListener( 'click', () => {
			// With no payment step, the cart is replaced by an intake form.
			boot.payment === 'none' ? openDetails() : confirm();
		} );
		el.submit && el.submit.addEventListener( 'click', submitIntake );

		root.querySelectorAll( '[data-ks-action="back"]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const previous = { who: 'service', when: 'who', details: 'when' };
				go( previous[ state.step ] || 'service' );
			} );
		} );

		function select( selector, chosen ) {
			root.querySelectorAll( selector ).forEach( ( card ) => {
				card.classList.toggle( 'is-selected', card === chosen );
			} );
		}

		function go( step ) {
			state.step = step;
			el.steps.forEach( ( node ) => { node.hidden = node.dataset.step !== step; } );

			if ( el.progress ) {
				const order = [ 'service', 'who', 'when', 'details' ];
				el.progress.querySelectorAll( 'li' ).forEach( ( item ) => {
					const position = order.indexOf( item.dataset.for );
					item.classList.toggle( 'is-current', item.dataset.for === step );
					item.classList.toggle( 'is-done', position < order.indexOf( step ) );
				} );
			}

			say( '' );
		}

		async function loadTimes() {
			if ( state.busy ) return;
			busy( true, el.times, 'Checking the diary…' );
			el.days.innerHTML = '';

			try {
				const query = new URLSearchParams( {
					service: state.service,
					practitioner: state.practitioner,
					days: boot.days,
				} );

				const res = await api( '/availability?' + query.toString() );

				if ( ! res.ok ) {
					say( Object.values( res.errors ).join( ' ' ), true );
					return;
				}

				const open = res.days.filter( ( day ) => day.slots.length );

				if ( ! open.length ) {
					say( 'Nothing open in the next ' + boot.days + ' days. Call us and we will find you a time.', true );
					return;
				}

				renderDays( open );
				go( 'when' );
			} catch ( e ) {
				say( 'We could not load available times. Try again in a moment.', true );
			} finally {
				busy( false, el.times, 'See times' );
			}
		}

		function renderDays( days ) {
			days.forEach( ( day ) => {
				const group = document.createElement( 'section' );
				group.className = 'ks-day';

				const heading = document.createElement( 'h3' );
				heading.className = 'ks-day__label';
				heading.textContent = day.label;
				group.appendChild( heading );

				const times = document.createElement( 'div' );
				times.className = 'ks-day__times';
				times.setAttribute( 'role', 'radiogroup' );
				times.setAttribute( 'aria-label', 'Times on ' + day.label );

				day.slots.forEach( ( slot ) => {
					const button = document.createElement( 'button' );
					button.type = 'button';
					button.className = 'ks-slot';
					button.setAttribute( 'role', 'radio' );
					button.setAttribute( 'aria-checked', 'false' );
					button.textContent = slot.time;
					button.addEventListener( 'click', () => {
						state.date = day.date;
						state.time = slot.time;
						// "First available" resolves to whoever is actually open then.
						if ( ! state.practitioner ) {
							state.resolved = slot.practitioners[ 0 ];
						}
						root.querySelectorAll( '.ks-slot' ).forEach( ( other ) => {
							const on = other === button;
							other.classList.toggle( 'is-selected', on );
							other.setAttribute( 'aria-checked', on ? 'true' : 'false' );
						} );
						el.confirm.disabled = false;
						say( '' );
					} );
					times.appendChild( button );
				} );

				group.appendChild( times );
				el.days.appendChild( group );
			} );
		}

		/** Load the matter-specific intake questions, then show the details step. */
		async function openDetails() {
			busy( true, el.confirm, 'One moment…' );

			try {
				const res = await api( '/intake-questions?service=' + state.service );
				renderIntake( res.questions || [] );
			} catch ( e ) {
				renderIntake( [] );
			} finally {
				busy( false, el.confirm, 'Continue' );
			}

			if ( el.chosen ) {
				el.chosen.textContent = 'Requesting ' + state.time + ' on ' + state.date + '.';
			}

			go( 'details' );
		}

		function renderIntake( questions ) {
			if ( ! el.intake ) return;
			el.intake.innerHTML = '';

			questions.forEach( ( q ) => {
				const wrap = document.createElement( 'p' );
				wrap.className = 'ks-field';

				const id = 'ks-q-' + q.key + '-' + Math.random().toString( 36 ).slice( 2, 7 );
				const label = document.createElement( 'label' );
				label.setAttribute( 'for', id );
				label.textContent = q.label + ( q.required ? '' : ' (optional)' );
				wrap.appendChild( label );

				let field;

				if ( q.type === 'textarea' ) {
					field = document.createElement( 'textarea' );
					field.rows = 3;
				} else if ( q.type === 'select' ) {
					field = document.createElement( 'select' );
					const blank = document.createElement( 'option' );
					blank.value = '';
					blank.textContent = 'Choose one';
					field.appendChild( blank );
					q.choices.forEach( ( choice ) => {
						const option = document.createElement( 'option' );
						option.value = choice;
						option.textContent = choice;
						field.appendChild( option );
					} );
				} else {
					field = document.createElement( 'input' );
					field.type = q.type === 'date' ? 'date' : 'text';
				}

				field.id = id;
				field.dataset.question = q.key;
				field.required = q.required;
				wrap.appendChild( field );
				el.intake.appendChild( wrap );
			} );
		}

		async function submitIntake() {
			if ( state.busy ) return;

			const answers = {};
			root.querySelectorAll( '[data-question]' ).forEach( ( field ) => {
				answers[ field.dataset.question ] = field.value;
			} );

			busy( true, el.submit, 'Sending…' );

			try {
				const res = await api( '/consultation', {
					service: state.service,
					practitioner: state.practitioner || state.resolved,
					date: state.date,
					time: state.time,
					name: field( 'name' ),
					email: field( 'email' ),
					phone: field( 'phone' ),
					consent: root.querySelector( '[name="consent"]' ) ? root.querySelector( '[name="consent"]' ).checked : true,
					answers: answers,
				} );

				if ( ! res.ok ) {
					say( Object.values( res.errors ).join( ' ' ), true );
					return;
				}

				root.querySelectorAll( '[data-step]' ).forEach( ( node ) => { node.hidden = true; } );
				if ( el.progress ) el.progress.hidden = true;
				say( res.message );
			} catch ( e ) {
				say( 'We could not send that. Call the office and we will take your details.', true );
			} finally {
				busy( false, el.submit, 'Request this time' );
			}
		}

		function field( name ) {
			const node = root.querySelector( '[name="' + name + '"]' );
			return node ? node.value.trim() : '';
		}

		async function confirm() {
			if ( state.busy || ! state.date ) return;
			busy( true, el.confirm, 'Holding your time…' );

			try {
				const res = await api( '/appointment', {
					service: state.service,
					practitioner: state.practitioner || state.resolved,
					date: state.date,
					time: state.time,
				} );

				if ( ! res.ok ) {
					say( Object.values( res.errors ).join( ' ' ), true );
					// The slot may have gone while they were choosing.
					loadTimes();
					return;
				}

				window.location.href = res.redirect;
			} catch ( e ) {
				say( 'We could not hold that time. Try again.', true );
			} finally {
				busy( false, el.confirm, 'Confirm and continue' );
			}
		}

		function say( message, isError ) {
			el.feedback.textContent = message || '';
			el.feedback.classList.toggle( 'is-error', !! isError );
		}

		function busy( on, button, label ) {
			state.busy = on;
			if ( ! button ) return;
			button.disabled = on;
			button.dataset.label = button.dataset.label || button.textContent;
			button.textContent = on ? label : button.dataset.label;
		}

		async function api( path, body ) {
			const res = await fetch( boot.restUrl + path, {
				method: body ? 'POST' : 'GET',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': boot.nonce },
				credentials: 'same-origin',
				body: body ? JSON.stringify( body ) : undefined,
			} );

			const data = await res.json().catch( () => null );

			if ( ! data ) {
				throw new Error( 'Bad response' );
			}

			return data;
		}
	}
}() );
