/**
 * Multilang language switcher — accessible dropdown toggle (no dependencies).
 */
( function () {
	'use strict';

	function closeAll( except ) {
		var open = document.querySelectorAll( '.mlr-switcher.is-open' );
		for ( var i = 0; i < open.length; i++ ) {
			if ( open[ i ] === except ) {
				continue;
			}
			open[ i ].classList.remove( 'is-open' );
			var t = open[ i ].querySelector( '.mlr-switcher__toggle' );
			if ( t ) {
				t.setAttribute( 'aria-expanded', 'false' );
			}
		}
	}

	function init( sw ) {
		var toggle = sw.querySelector( '.mlr-switcher__toggle' );
		if ( ! toggle ) {
			return; // Inline variant has no toggle.
		}

		toggle.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			var isOpen = sw.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
			closeAll( sw );
		} );

		sw.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key || 'Esc' === e.key ) {
				sw.classList.remove( 'is-open' );
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.focus();
			}
		} );
	}

	function boot() {
		var switchers = document.querySelectorAll( '.mlr-switcher' );
		for ( var i = 0; i < switchers.length; i++ ) {
			init( switchers[ i ] );
		}
	}

	// Close when clicking outside.
	document.addEventListener( 'click', function () {
		closeAll( null );
	} );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
