/**
 * Repeater of the path rules: add, remove and sort rows. The first matching
 * rule wins, so the order of the rows is the order of precedence.
 */
jQuery( function ( $ ) {
	var $rows = $( '#shawesome-c404-rules' );
	var template = document.getElementById( 'shawesome-c404-rule-template' );

	if ( ! $rows.length ) {
		return;
	}

	/**
	 * Number the fields in the order of the rows and disable the arrows
	 * that lead nowhere.
	 */
	function refresh() {
		var last = $rows.children().length - 1;

		$rows.children().each( function ( index ) {
			$( this ).find( '[name]' ).each( function () {
				this.name = this.name.replace( /^paths\[[^\]]*\]/, 'paths[' + index + ']' );
			} );
			$( this ).find( '.shawesome-c404-up' ).prop( 'disabled', 0 === index );
			$( this ).find( '.shawesome-c404-down' ).prop( 'disabled', last === index );
		} );
	}

	/**
	 * Keep the focus in a row after moving it, as an arrow can get disabled.
	 *
	 * @param {jQuery} $row    Moved row.
	 * @param {jQuery} $button Pressed arrow.
	 */
	function focusArrow( $row, $button ) {
		( $button.prop( 'disabled' ) ? $row.find( '.shawesome-c404-up, .shawesome-c404-down' ).not( ':disabled' ) : $button ).first().trigger( 'focus' );
	}

	$( '#shawesome-c404-add-rule' ).on( 'click', function () {
		var $row = $( template.innerHTML.trim() );

		$rows.append( $row );
		refresh();
		$row.find( 'input' ).trigger( 'focus' );
	} );

	$rows.on( 'click', '.shawesome-c404-remove', function () {
		$( this ).closest( 'tr' ).remove();
		refresh();
		$( '#shawesome-c404-add-rule' ).trigger( 'focus' );
	} );

	$rows.on( 'click', '.shawesome-c404-up', function () {
		var $row = $( this ).closest( 'tr' );

		$row.insertBefore( $row.prev() );
		refresh();
		focusArrow( $row, $( this ) );
	} );

	$rows.on( 'click', '.shawesome-c404-down', function () {
		var $row = $( this ).closest( 'tr' );

		$row.insertAfter( $row.next() );
		refresh();
		focusArrow( $row, $( this ) );
	} );

	$rows.sortable( {
		axis: 'y',
		handle: '.shawesome-c404-handle',
		forcePlaceholderSize: true,
		// Keep the column widths while a row is detached from the table.
		helper: function ( event, $row ) {
			$row.children().each( function () {
				$( this ).width( $( this ).width() );
			} );

			return $row;
		},
		stop: function ( event, ui ) {
			ui.item.children().css( 'width', '' );
			refresh();
		}
	} );

	refresh();
} );
