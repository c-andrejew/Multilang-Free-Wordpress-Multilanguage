/**
 * Multilang admin — language rows + flag media picker.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var table = $( '#mlr-languages-table' );
		if ( ! table.length ) {
			return;
		}

		/**
		 * Keep the "main" radio values in sync with row order.
		 */
		function renumber() {
			table.find( 'tbody > tr.mlr-row' ).each( function ( i ) {
				$( this ).find( '.mlr-main-radio' ).val( i );
			} );
		}

		// Add a language row from the template.
		$( '#mlr-add-language' ).on( 'click', function ( e ) {
			e.preventDefault();
			var html = $( '#mlr-row-template' ).html();
			table.find( 'tbody' ).append( html );
			renumber();
			table.find( 'tbody > tr.mlr-row:last input.mlr-code-input' ).trigger( 'focus' );
		} );

		// Remove a row (or clear it if it is the last one).
		table.on( 'click', '.mlr-remove-row', function ( e ) {
			e.preventDefault();
			var row = $( this ).closest( 'tr' );
			var rows = table.find( 'tbody > tr.mlr-row' );
			var wasMain = row.find( '.mlr-main-radio' ).is( ':checked' );

			if ( rows.length <= 1 ) {
				row.find( 'input[type="text"]' ).val( '' );
				row.find( '.mlr-flag-input' ).val( '' );
				row.find( '.mlr-flag-preview' ).empty();
				return;
			}

			row.remove();
			if ( wasMain ) {
				table.find( '.mlr-main-radio' ).first().prop( 'checked', true );
			}
			renumber();
		} );

		// Pick a flag via the WordPress media library.
		table.on( 'click', '.mlr-flag-choose', function ( e ) {
			e.preventDefault();
			var cell = $( this ).closest( '.mlr-flag-cell' );

			var frame = wp.media( {
				title: MLR_ADMIN.choose,
				button: { text: MLR_ADMIN.use },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				var url = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
				cell.find( '.mlr-flag-input' ).val( att.id );
				cell.find( '.mlr-flag-preview' ).html(
					$( '<img>' ).attr( { src: url, alt: '', width: 32, height: 24 } )
				);
			} );

			frame.open();
		} );

		// Clear a flag.
		table.on( 'click', '.mlr-flag-remove', function ( e ) {
			e.preventDefault();
			var cell = $( this ).closest( '.mlr-flag-cell' );
			cell.find( '.mlr-flag-input' ).val( '' );
			cell.find( '.mlr-flag-preview' ).empty();
		} );

		// Force lowercase language codes.
		table.on( 'input', '.mlr-code-input', function () {
			var v = this.value.toLowerCase().replace( /[^a-z]/g, '' );
			if ( v !== this.value ) {
				this.value = v;
			}
		} );

		renumber();
	} );
} )( jQuery );
