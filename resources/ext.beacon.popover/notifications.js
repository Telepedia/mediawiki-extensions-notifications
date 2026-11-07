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
		markRead( id ) {},

		/**
		 * Mark all notifications as read
		 */
		markAllRead() {},

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
		 * @param id
		 */
		markUnread( id ) {},

		toggle() {
			this.isOpen = !this.isOpen;
		}
	}
} );

module.exports = {
	useNotificationStore
}
