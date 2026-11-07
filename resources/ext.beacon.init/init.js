/**
 * This is mostly just a wrapper – it is loaded on every page, and allows us to avoid loading Vue when the user may
 * not interact with the notification icon. We then preload the Vue on hover when it appears that the user is going to click
 */
const beaconBadge = document.querySelector( '#pt-notifications-alert a' );

if ( beaconBadge ) {
	beaconBadge.setAttribute( 'aria-haspopup', 'dialog' );
	beaconBadge.setAttribute( 'aria-expanded', 'false' );

	const prefetch = () => mw.loader.using( 'ext.beacon.popover' ).then( ( require ) => {
		require( 'ext.beacon.popover' ).prefetch( beaconBadge );
	} );
	beaconBadge.addEventListener( 'pointerenter', prefetch, { once: true } );
	beaconBadge.addEventListener( 'focus', prefetch, { once: true } );

	beaconBadge.addEventListener( 'click', ( e ) => {
		if ( e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey ) {
			return;
		}

		e.preventDefault();
		mw.loader.using( 'ext.beacon.popover' ).then( ( require ) => {
			require( 'ext.beacon.popover').toggle( beaconBadge );
		} );
	} );
}
