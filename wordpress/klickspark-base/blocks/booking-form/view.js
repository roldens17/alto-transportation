/**
 * Booking form front end. Vanilla, no build step — drop-in for any client site.
 * State lives in one object; every render reads from it.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-ks-booking]' ).forEach( init );

	function init( root ) {
		const boot = JSON.parse( root.dataset.ksBooking );
		const cfg = boot.config;

		const el = {
			from: root.querySelector( '[data-ks-places="pickup"]' ),
			to: root.querySelector( '[data-ks-places="dropoff"]' ),
			returnBlock: root.querySelector( '[data-ks-return]' ),
			flightBlock: root.querySelector( '[data-ks-flight]' ),
			options: root.querySelector( '[data-ks-options]' ),
			feedback: root.querySelector( '[data-ks-feedback]' ),
			stepRoute: root.querySelector( '[data-step="route"]' ),
			stepVehicle: root.querySelector( '[data-step="vehicle"]' ),
			quote: root.querySelector( '[data-ks-action="quote"]' ),
			back: root.querySelector( '[data-ks-action="back"]' ),
			checkout: root.querySelector( '[data-ks-action="checkout"]' ),
		};

		const state = { places: [], options: [], vehicle: 0, busy: false };

		loadPlaces();
		bind();

		async function loadPlaces() {
			try {
				const [ pickups, dropoffs ] = await Promise.all( [
					api( '/places?role=pickup' ),
					api( '/places?role=dropoff' ),
				] );
				fillSelect( el.from, pickups, 'Select pickup' );
				fillSelect( el.to, dropoffs, 'Select drop-off' );
				state.places = pickups.concat( dropoffs );
			} catch ( e ) {
				fillSelect( el.from, [], 'Unavailable' );
				fillSelect( el.to, [], 'Unavailable' );
				say( 'We could not load locations. Reload the page or message us to book.', true );
			}
		}

		function fillSelect( select, places, placeholder ) {
			if ( ! select ) return;
			const groups = {};
			places.forEach( ( p ) => {
				const key = ( p.region && p.region[ 0 ] ) || 'Other';
				( groups[ key ] = groups[ key ] || [] ).push( p );
			} );

			select.innerHTML = '';
			select.appendChild( option( '', placeholder ) );

			Object.keys( groups ).sort().forEach( ( name ) => {
				const group = document.createElement( 'optgroup' );
				group.label = name;
				groups[ name ].forEach( ( p ) => group.appendChild( option( p.id, p.label ) ) );
				select.appendChild( group );
			} );
		}

		function option( value, label ) {
			const o = document.createElement( 'option' );
			o.value = value;
			o.textContent = label;
			return o;
		}

		function bind() {
			root.querySelectorAll( '[name="trip_type"]' ).forEach( ( input ) => {
				input.addEventListener( 'change', () => {
					const round = value( 'trip_type' ) === 'round_trip';
					if ( el.returnBlock ) el.returnBlock.hidden = ! round;
					toggleRequired( el.returnBlock, round );
				} );
			} );

			[ el.from, el.to ].forEach( ( select ) => {
				select && select.addEventListener( 'change', maybeShowFlight );
			} );

			el.quote && el.quote.addEventListener( 'click', getQuote );
			el.back && el.back.addEventListener( 'click', () => step( 'route' ) );
			el.checkout && el.checkout.addEventListener( 'click', checkout );
		}

		function maybeShowFlight() {
			if ( ! el.flightBlock || ! cfg.requireFlight ) return;
			const ids = [ Number( el.from.value ), Number( el.to.value ) ];
			const airport = state.places.some( ( p ) => ids.includes( p.id ) && p.type === 'airport' );
			el.flightBlock.hidden = ! airport;
			toggleRequired( el.flightBlock, airport );
		}

		function toggleRequired( block, on ) {
			if ( ! block ) return;
			block.querySelectorAll( 'input, select' ).forEach( ( field ) => {
				field.required = on;
				if ( ! on ) field.setCustomValidity && field.setCustomValidity( '' );
			} );
		}

		async function getQuote() {
			if ( state.busy ) return;
			clearErrors();
			busy( true, el.quote, 'Checking rates…' );

			try {
				const res = await api( '/quote', payload() );

				if ( ! res.ok ) {
					showErrors( res.errors );
					return;
				}

				if ( ! res.options.length ) {
					say( res.message, true );
					return;
				}

				state.options = res.options;
				state.vehicle = 0;
				renderOptions();
				step( 'vehicle' );
			} catch ( e ) {
				say( 'Something went wrong getting prices. Try again in a moment.', true );
			} finally {
				busy( false, el.quote, 'See prices' );
			}
		}

		function renderOptions() {
			el.options.innerHTML = '';

			state.options.forEach( ( opt ) => {
				const card = document.createElement( 'button' );
				card.type = 'button';
				card.className = 'ks-option';
				card.setAttribute( 'role', 'radio' );
				card.setAttribute( 'aria-checked', 'false' );
				card.dataset.vehicle = opt.vehicle_id;

				card.innerHTML = [
					opt.image ? '<img class="ks-option__img" src="' + escapeAttr( opt.image ) + '" alt="" loading="lazy">' : '',
					'<span class="ks-option__body">',
					opt.class ? '<span class="ks-option__class">' + escapeHtml( opt.class ) + '</span>' : '',
					'<span class="ks-option__name">' + escapeHtml( opt.name ) + '</span>',
					'<span class="ks-option__cap">' + opt.capacity.passengers + ' passengers · ' + opt.capacity.bags + ' suitcases</span>',
					'</span>',
					'<span class="ks-option__price">' + money( opt.total ) + '</span>',
				].join( '' );

				card.addEventListener( 'click', () => selectVehicle( opt.vehicle_id ) );
				el.options.appendChild( card );
			} );
		}

		function selectVehicle( id ) {
			state.vehicle = id;
			el.options.querySelectorAll( '.ks-option' ).forEach( ( card ) => {
				const on = Number( card.dataset.vehicle ) === id;
				card.classList.toggle( 'is-selected', on );
				card.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			} );
			el.checkout.disabled = false;
		}

		async function checkout() {
			if ( state.busy || ! state.vehicle ) return;
			clearErrors();
			busy( true, el.checkout, 'Reserving…' );

			try {
				const res = await api( '/booking', Object.assign( payload(), { vehicle: state.vehicle } ) );

				if ( ! res.ok ) {
					showErrors( res.errors );
					step( 'route' );
					return;
				}

				window.location.href = res.redirect;
			} catch ( e ) {
				say( 'We could not reserve that. Try again or message us.', true );
			} finally {
				busy( false, el.checkout, 'Continue to checkout' );
			}
		}

		function payload() {
			return {
				from: Number( value( 'from' ) ),
				to: Number( value( 'to' ) ),
				date: value( 'date' ),
				time: value( 'time' ),
				round_trip: value( 'trip_type' ) === 'round_trip',
				return_date: value( 'return_date' ),
				return_time: value( 'return_time' ),
				passengers: Number( value( 'passengers' ) || 1 ),
				bags: Number( value( 'bags' ) || 0 ),
				stops: Number( value( 'stops' ) || 0 ),
				flight_no: value( 'flight_no' ),
			};
		}

		function value( name ) {
			const field = root.querySelector( '[name="' + name + '"]:checked' ) || root.querySelector( '[name="' + name + '"]' );
			return field ? field.value : '';
		}

		function step( name ) {
			el.stepRoute.hidden = name !== 'route';
			el.stepVehicle.hidden = name !== 'vehicle';
			say( '' );
			root.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		function showErrors( errors ) {
			const messages = [];

			Object.keys( errors || {} ).forEach( ( field ) => {
				const target = root.querySelector( '[name="' + field + '"]' ) || root.querySelector( '[name="' + field.replace( 'route', 'from' ) + '"]' );
				if ( target ) {
					target.classList.add( 'has-error' );
					target.setAttribute( 'aria-invalid', 'true' );
				}
				messages.push( errors[ field ] );
			} );

			say( messages.join( ' ' ), true );
		}

		function clearErrors() {
			root.querySelectorAll( '.has-error' ).forEach( ( f ) => {
				f.classList.remove( 'has-error' );
				f.removeAttribute( 'aria-invalid' );
			} );
			say( '' );
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

		function money( amount ) {
			try {
				return new Intl.NumberFormat( document.documentElement.lang || 'en', {
					style: 'currency',
					currency: boot.currency,
				} ).format( amount );
			} catch ( e ) {
				return boot.currency + ' ' + Number( amount ).toFixed( 2 );
			}
		}

		async function api( path, body ) {
			const res = await fetch( boot.restUrl + path, {
				method: body ? 'POST' : 'GET',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': boot.nonce },
				credentials: 'same-origin',
				body: body ? JSON.stringify( body ) : undefined,
			} );

			if ( ! res.ok && res.status !== 200 ) {
				const data = await res.json().catch( () => ( {} ) );
				if ( data && data.errors ) return data;
				throw new Error( 'Request failed: ' + res.status );
			}

			return res.json();
		}

		function escapeHtml( str ) {
			const div = document.createElement( 'div' );
			div.textContent = str == null ? '' : String( str );
			return div.innerHTML;
		}

		function escapeAttr( str ) {
			return escapeHtml( str ).replace( /"/g, '&quot;' );
		}
	}
}() );
