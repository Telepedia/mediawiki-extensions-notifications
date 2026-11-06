const { defineStore } = require( 'pinia' );

const useNotificationStore = defineStore( 'beacon', {
	state: () => ( {
		notifications: null,
		unreadCount: null,
		isOpen: false,
		isLoading: false,
		error: null
	} ),

	actions: {
		/**
		 * Fetch the notifications from the API
		 */
		fetch() {},

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
	}
} );
