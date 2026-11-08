<?php

namespace Telepedia\Extensions\Beacon\Hooks;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * Create the tables required
	 * @param DatabaseUpdater $updater
	 * @return void
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = dirname( __DIR__, 2 ) . '/schema';

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-beacon',
			'addTable',
			'beacon_notifications',
			"$dir/$dbType/tables-generated.sql",
			true,
		] );
	}
}
