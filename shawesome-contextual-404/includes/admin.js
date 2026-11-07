/**
 * Settings page: repeater of the path rules, URL test, save bar; log: month
 * filter and confirmation before deleting the statistics.
 *
 * The first matching rule wins, so the order of the rows is the order of
 * precedence. The form posts the rows in that order; their keys only tell
 * them apart, so moving a row never renames its fields.
 */
jQuery( function ( $ ) {
	$( '#sc404-month' ).on( 'change', function () {
		this.form.submit();
	} );

	$( '[data-confirm]' ).on( 'click', function () {
		return window.confirm( $( this ).data( 'confirm' ) );
	} );

	var $form = $( '#sc404-settings' );

	if ( ! $form.length ) {
		return;
	}

	var $rows = $( '#sc404-rules' );
	var $status = $form.find( '.sc404-status' );
	var $discard = $form.find( '.sc404-discard' );
	var $result = $form.find( '.sc404-result' );
	var template = document.getElementById( 'sc404-rule-template' );
	var savedStatus = $status.text();
	var newKeys = 0;
	var timer;
	var request;

	/**
	 * Form data without the test URL, which is not a setting.
	 *
	 * @return {string} Serialized form.
	 */
	function settingsState() {
		return $form.find( ':input' ).not( '#sc404-test-url' ).serialize();
	}

	var initial = settingsState();

	/**
	 * Disable the arrows that lead nowhere, show the empty state and update
	 * the save bar.
	 */
	function refresh() {
		var last = $rows.children().length - 1;
		var dirty = settingsState() !== initial;

		$rows.children().each( function ( index ) {
			$( this ).find( '.sc404-up' ).prop( 'disabled', 0 === index );
			$( this ).find( '.sc404-down' ).prop( 'disabled', last === index );
		} );

		$form.find( '.sc404-empty' ).prop( 'hidden', last >= 0 );
		$status.toggleClass( 'is-dirty', dirty ).text( dirty ? $status.data( 'dirty' ) : savedStatus );
		$discard.prop( 'hidden', ! dirty );
	}

	/**
	 * Test the URL and check the regular expressions on the server, with the
	 * unsaved values, after typing has paused.
	 */
	function test() {
		clearTimeout( timer );
		timer = setTimeout( function () {
			if ( request ) {
				request.abort();
			}

			var data = $form.serializeArray();

			data.push( { name: 'action', value: 'shawesome_c404_test' } );
			data.forEach( function ( field ) {
				// A path without a scheme always starts at the root.
				if ( 'test_url' === field.name && field.value && ! /^[a-z][a-z0-9+.-]*:\/\//i.test( field.value ) ) {
					field.value = '/' + field.value.replace( /^\/+/, '' );
				}
			} );

			request = $.post( $form.data( 'ajax-url' ), $.param( data ) ).done( function ( response ) {
				if ( ! response.success ) {
					return;
				}

				$result.text( response.data.result );
				$rows.children().each( function () {
					$( this ).toggleClass( 'is-invalid', -1 !== response.data.invalid.indexOf( String( $( this ).data( 'key' ) ) ) );
				} );
			} );
		}, 300 );
	}

	/**
	 * Add an empty rule.
	 *
	 * @param {string} pattern Pattern to fill in.
	 * @return {jQuery} The new row.
	 */
	function addRule( pattern ) {
		newKeys++;

		var $row = $( template.innerHTML.trim().split( '__key__' ).join( 'new' + newKeys ) );

		$row.find( 'input[type="text"]' ).val( pattern );
		$rows.append( $row );

		return $row;
	}

	/**
	 * Keep the focus in a row after moving it, as an arrow can get disabled.
	 *
	 * @param {jQuery} $row    Moved row.
	 * @param {jQuery} $button Pressed arrow.
	 */
	function focusArrow( $row, $button ) {
		( $button.prop( 'disabled' ) ? $row.find( '.sc404-up, .sc404-down' ).not( ':disabled' ) : $button ).first().trigger( 'focus' );
	}

	$( '#sc404-add-rule' ).on( 'click', function () {
		var $row = addRule( '' );

		refresh();
		$row.find( 'input:checked' ).trigger( 'focus' );
	} );

	$rows.on( 'click', '.sc404-remove', function () {
		$( this ).closest( 'li' ).remove();
		refresh();
		test();
		$( '#sc404-add-rule' ).trigger( 'focus' );
	} );

	$rows.on( 'click', '.sc404-up, .sc404-down', function () {
		var $row = $( this ).closest( 'li' );

		if ( $( this ).hasClass( 'sc404-up' ) ) {
			$row.insertBefore( $row.prev() );
		} else {
			$row.insertAfter( $row.next() );
		}

		refresh();
		test();
		focusArrow( $row, $( this ) );
	} );

	$rows.on( 'change', 'input[type="radio"]', function () {
		var regex = 'regex' === this.value;

		$( this ).closest( 'li' ).toggleClass( 'is-regex', regex ).find( 'input[type="text"]' ).attr( 'placeholder', regex ? '^/(sale|offers)/' : 'shop/sale' );
	} );

	$rows.sortable( {
		axis: 'y',
		handle: '.sc404-handle',
		forcePlaceholderSize: true,
		stop: function () {
			refresh();
			test();
		}
	} );

	$form.on( 'input change', ':input', function () {
		refresh();
		test();
	} );

	// A rule handed over from the log stays unsaved until it is saved.
	if ( $form.data( 'prefill-rule' ) ) {
		addRule( String( $form.data( 'prefill-rule' ) ) ).find( 'select' ).trigger( 'focus' );
	}

	refresh();

	if ( $( '#sc404-test-url' ).val() ) {
		test();
	}
} );
