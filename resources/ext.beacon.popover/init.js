const Vue = require( 'vue' );
const Pinia = require( 'pinia' );
const { useNotificationStore } = require( './notifications.js' );
let app = null;

function setup( badge ) {
	if ( !app ) {
		const mount = document.body.appendChild( document.createElement( 'div' ) );
		app = Vue.createMwApp( require( './components/App.vue' ), { anchor: badge } )
			.use( Pinia.createPinia() );
		app.mount( mount );
	}
	return useNotificationStore();
}

/**
 * Eagerly load the notifications when the user looks like they're about to click
 * so that in most cases, they are available before the modal actually opens
 *
 * @param {HTMLElement} badge
 */
function prefetch( badge ) {
	const store = setup( badge );
	if ( !store.notifications ) {
		store.fetch();
	}
}

/**
 * @param {HTMLElement} badge
 */
function toggle( badge ) {
	const store = setup( badge );
	store.toggle();
	if ( store.isOpen ) {
		store.fetch();
	}
}

module.exports = {
	prefetch,
	toggle
};
