const { defineStore } = require( 'pinia' );

let inFlight = null;

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
			inFlight = new mw.Rest().get( '/beacon/v1/notifications' )
				.then( ( data ) => {
					this.unreadCount = data.unreadCount;
					this.notifications = data.notifications;
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
		syncBadge() {},

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
