const Vue = require( 'vue' );
const Pinia = require( 'pinia' );

let app = null;

function toggle( badge ) {
	if ( !app ) {
		const mount = document.body.appendChild( document.createElement( 'div' ) );
		app = Vue.createMwApp( require( './components/App.vue' ), { anchor: badge } )
			.use( Pinia.createPinia() );
		app.mount( mount );
	}
	// useNotificationsStore.toggle();
}

module.exports = {
	toggle
}
