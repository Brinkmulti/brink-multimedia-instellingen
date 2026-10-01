jQuery( function ( $ ) {
	'use strict';

	var labels = ( typeof bmiSettings !== 'undefined' ) ? bmiSettings : {};

	// --------------------------------------------------------
	// Sleepbare menu-volgorde + witruimtes
	// --------------------------------------------------------
	var $list   = $( '#bmi-menu-order-list' );
	var $input  = $( '#bmi_menu_order_input' );
	var $addBtn = $( '#bmi-add-spacer' );

	if ( $list.length ) {
		function updateOrderInput() {
			var tokens = [];
			$list.find( 'li' ).each( function () {
				tokens.push( $( this ).data( 'slug' ) );
			} );
			$input.val( tokens.join( ',' ) );
		}

		$list.sortable( {
			axis: 'y',
			update: updateOrderInput
		} );

		$addBtn.on( 'click', function () {
			var spacerLabel = labels.spacerLabel || 'Witruimte (groepering)';
			var removeLabel = labels.removeLabel || 'Verwijder witruimte';

			var $spacer = $( '<li class="bmi-spacer-item" data-slug="spacer"></li>' );
			$spacer.append( $( '<span class="dashicons dashicons-minus"></span>' ) );
			$spacer.append( document.createTextNode( ' ' + spacerLabel + ' ' ) );
			$spacer.append(
				$( '<button type="button" class="bmi-remove-spacer button-link">&times;</button>' )
					.attr( 'aria-label', removeLabel )
			);

			$list.append( $spacer );
			updateOrderInput();
		} );

		$list.on( 'click', '.bmi-remove-spacer', function () {
			$( this ).closest( 'li' ).remove();
			updateOrderInput();
		} );

		updateOrderInput();
	}

	// --------------------------------------------------------
	// Kleurkiezers
	// --------------------------------------------------------
	if ( $.fn.wpColorPicker ) {
		$( '.bmi-color-picker' ).wpColorPicker();
	}

	// --------------------------------------------------------
	// Mediabibliotheek-velden (logo/achtergrond)
	// --------------------------------------------------------
	$( '.bmi-media-select' ).on( 'click', function ( e ) {
		e.preventDefault();

		var targetId = $( this ).data( 'target' );
		var $input2  = $( '#' + targetId + '-input' );
		var $preview = $( '#' + targetId + '-preview' );

		var frame = wp.media( {
			title: labels.mediaTitle || 'Kies een afbeelding',
			button: { text: labels.mediaButtonLabel || 'Gebruik deze afbeelding' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$input2.val( attachment.url );
			$preview.attr( 'src', attachment.url ).show();
		} );

		frame.open();
	} );

	$( '.bmi-media-remove' ).on( 'click', function ( e ) {
		e.preventDefault();

		var targetId = $( this ).data( 'target' );
		$( '#' + targetId + '-input' ).val( '' );
		$( '#' + targetId + '-preview' ).hide().attr( 'src', '' );
	} );

	// --------------------------------------------------------
	// Rollen & Rechten: menu-matrix
	// --------------------------------------------------------

	// Een top-level item aan-/uitvinken voor een rol vinkt automatisch
	// ook de submenu's van dat item mee aan/uit voor diezelfde rol.
	$( '.bmi-parent-checkbox' ).on( 'change', function () {
		var $checkbox = $( this );
		var role      = $checkbox.data( 'role' );
		var isChecked = $checkbox.prop( 'checked' );

		$checkbox
			.closest( 'tr.bmi-parent-row' )
			.nextUntil( 'tr.bmi-parent-row', 'tr.bmi-child-row' )
			.find( '.bmi-child-checkbox[data-role="' + role + '"]' )
			.prop( 'checked', isChecked );
	} );

	// "Alles" / "Niets" per rol-kolom.
	$( '.bmi-role-select-all' ).on( 'click', function () {
		var role = $( this ).data( 'role' );
		$( '.bmi-role-checkbox[data-role="' + role + '"]' ).prop( 'checked', true );
	} );

	$( '.bmi-role-select-none' ).on( 'click', function () {
		var role = $( this ).data( 'role' );
		$( '.bmi-role-checkbox[data-role="' + role + '"]' ).prop( 'checked', false );
	} );
} );
