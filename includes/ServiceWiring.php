<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Telepedia\Extensions\Beacon\Events\EventController;
use Telepedia\Extensions\Beacon\NotificationStore;

/** @phpcs-require-sorted-array */
return [
	'Beacon.EventController' => static function ( MediaWikiServices $services ): EventController {
		return new EventController(
			$services->getJobQueueGroup()
		);
	},

	'Beacon.NotificationStore' => static function ( MediaWikiServices $services ): NotificationStore {
		return new NotificationStore(
			$services->getConnectionProvider(),
			$services->getWANObjectCache(),
			LoggerFactory::getInstance( 'Beacon' )
		);
	},
];
