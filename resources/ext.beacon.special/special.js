/**
 * Small helper to mark a notification as read when a user clicks it on Special:Notifications if they have JS enabled,
 * since we render S:N entirely without JS for those that may have it disabled.
 */

const list = document.querySelector( '.beacon-special' );

/**
 * Mark the row as read on the page
 * @param {HTMLElement} row
 */
function showAsRead( row ) {
	row.classList.remove( 'beacon-item--unread' );
	const action = row.querySelector( 'input[name="beaconaction"]' );
	const dot = row.querySelector( '.beacon-item__dot' );
	if ( action && dot ) {
		action.value = 'markunread';
		dot.title = mw.msg( 'beacon-popover-mark-unread' );
		dot.setAttribute( 'aria-label', dot.title );
	}
}

/**
 * @param {MouseEvent} e
 */
function onOpen( e ) {
	// don't listen for right clicks, only open in new tab (middle button on mouses)
	if ( e.type === 'auxclick' && e.button !== 1 ) {
		return;
	}
	const link = e.target.closest( 'a' );
	const row = link && link.closest( '.beacon-item--unread' );
	if ( !row ) {
		return;
	}

	showAsRead( row );
	fetch( mw.util.wikiScript( 'rest' ) + '/beacon/v1/notifications/read', {
		method: 'POST',
		keepalive: true,
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( {
			ids: [ Number( row.dataset.notificationId ) ],
			token: mw.user.tokens.get( 'csrfToken' )
		} )
	} );
}

if ( list ) {
	list.addEventListener( 'click', onOpen );
	list.addEventListener( 'auxclick', onOpen );
}
