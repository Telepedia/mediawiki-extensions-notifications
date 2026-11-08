const { defineStore } = require( 'pinia' );

let inFlight = null;

// @note: mirrored from NotificationStore::MAX_UNREAD_COUNT, keep it in sync with that
const MAX_UNREAD_COUNT = 99;

const useNotificationStore = defineStore( 'beacon-notifications', {
	state: () => ( {
		notifications: null,
		unreadCount: null,
		isOpen: false,
		isLoading: false,
		error: null
	} ),

actions: {
	/**
	 * Fetch the notifications from the API. We re-use a request thats already in flight if the modal was never
	 * opened
	 * @return {Promise}
	 */
	fetch() {
		if ( inFlight ) {
			return inFlight;
		}

		this.isLoading = true;
		this.error = null;
		inFlight = Promise.resolve( new mw.Rest().get( '/beacon/v1/notifications' ) )
			.then( ( data ) => {
				this.unreadCount = data.unreadCount;
				this.notifications = data.notifications;
				this.syncBadge();
				} )
			.catch( ( err ) => {
				this.error = err;
				} )
			.finally( () => {
				this.isLoading = false;
				inFlight = null;
				} );

		return inFlight;
	},

	/**
	 * Mark a notification as read
	 * @param id the ID of the notification to be marked as read
	 */
	async markRead( id ) {
		// we eagerly update the state of the store, ie, mark the notification as read on the frontend before
		// the request from the API takes place; if the request fails, we roll the state back
		const notification = this.notifications?.find( ( n ) => n.id === id );
		if ( !notification || notification.read !== null ) {
			return;
		}
		notification.read = new Date().toISOString();
		this.unreadCount--;
		this.syncBadge();

		try {
			const data = await new mw.Rest().post( '/beacon/v1/notifications/read', {
				ids: [ id ],
				token: mw.user.tokens.get( 'csrfToken' )
				} );
			this.unreadCount = data.unreadCount;
		} catch ( err ) {
			// something failed; roll the state back and mark the notification as unread
			notification.read = null;
			this.unreadCount++;
			mw.notify( mw.msg( 'beacon-popover-action-error' ), { type: 'error' } );
		} finally {
			this.syncBadge();
		}
	},

	/**
	 * Mark all notifications as read, up to the newest one we have loaded so that any which may
	 * have come in since the last fetch are left alone
	 */
	async markAllRead() {
		if ( !this.notifications || !this.notifications.length ) {
			return;
		}

		const upTo = Math.max( ...this.notifications.map( ( n ) => n.id ) );
		const changed = this.notifications.filter( ( n ) => n.read === null );
		const previousCount = this.unreadCount;
		const now = new Date().toISOString();

		changed.forEach( ( notification ) => {
			notification.read = now;
			} );
		this.unreadCount = 0;
		this.syncBadge();

	try {
		const data = await new mw.Rest().post( '/beacon/v1/notifications/read-all', {
			upTo,
			token: mw.user.tokens.get( 'csrfToken' )
			} );
		this.unreadCount = data.unreadCount;
	} catch ( err ) {
		changed.forEach( ( notif ) => {
			notif.read = null;
			} );
		this.unreadCount = previousCount;
		mw.notify( mw.msg( 'beacon-popover-action-error' ), { type: 'error' } );
	} finally {
		this.syncBadge();
		}
	},

	/**
	 * Sync the badge (rendered by PHP) with the actual count after changes have been made
	 * such as marking notifications as read/unread
	 */
	syncBadge() {
		const badge = document.querySelector( '#pt-notifications-alert a' );
		if ( !badge || this.unreadCount === null ) {
			return;
		}

		const text = this.unreadCount > MAX_UNREAD_COUNT ?
			mw.msg( 'beacon-badge-count-max', mw.language.convertNumber( MAX_UNREAD_COUNT ) ) :
			mw.language.convertNumber( this.unreadCount );

		badge.dataset.counterNum = this.unreadCount;
		badge.dataset.counterText = text;

		const label = badge.querySelector( 'span:not( [class] )' );
		if ( label ) {
			label.textContent = mw.msg( 'beacon-notifications-badge', text );
		}
	},

	/**
	 * Mark a notification that has already been marked as read, as unread again
	 * @param id the ID of the notification to be marked as unread
	 */
	async markUnread( id ) {
		const notification = this.notifications?.find( ( n ) => n.id === id );
		if ( !notification || notification.read === null ) {
			return;
		}
		const previousRead = notification.read;
		notification.read = null;
		this.unreadCount++;
		this.syncBadge();

		try {
			const data = await new mw.Rest().post( '/beacon/v1/notifications/unread', {
				ids: [ id ],
				token: mw.user.tokens.get( 'csrfToken' )
				} );
			this.unreadCount = data.unreadCount;
		} catch ( err ) {
			notification.read = previousRead;
			this.unreadCount--;
			mw.notify( mw.msg( 'beacon-popover-action-error' ), { type: 'error' } );
		} finally {
			this.syncBadge();
		}
	},

	toggle() {
		this.isOpen = !this.isOpen;
	}
	}
} );

module.exports = {
	useNotificationStore
}
