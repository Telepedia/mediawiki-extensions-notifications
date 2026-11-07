const Vue = require( 'vue' );
const Pinia = require( 'pinia' );
const { useNotificationStore } = require( './notifications.js' );
let app = null;

function toggle( badge ) {
	if ( !app ) {
		const mount = document.body.appendChild( document.createElement( 'div' ) );
		app = Vue.createMwApp( require( './components/App.vue' ), { anchor: badge } )
			.use( Pinia.createPinia() );
		app.mount( mount );
	}
	const store = useNotificationStore();
	store.toggle();
	if ( store.isOpen ) {
		store.fetch();
	}
}

module.exports = {
	toggle
}
