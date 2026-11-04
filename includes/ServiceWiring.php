<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\Events\EventController;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\Notifiers\RevertNotifier;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatterFactory;
use Telepedia\Extensions\Beacon\Presentation\RevertedPresenter;

/** @phpcs-require-sorted-array */
return [
	'Beacon.EventController' => static function ( MediaWikiServices $services ): EventController {
		return new EventController(
			$services->getJobQueueGroup(),
			$services->getService( 'Beacon.RevertNotifier' )
		);
	},

	'Beacon.NotificationStore' => static function ( MediaWikiServices $services ): NotificationStore {
		return new NotificationStore(
			$services->getConnectionProvider(),
			$services->getWANObjectCache(),
			LoggerFactory::getInstance( 'Beacon' )
		);
	},

	'Beacon.RecipientFormatterFactory' => static function ( MediaWikiServices $services ): RecipientFormatterFactory {
		return new RecipientFormatterFactory(
			$services->getUserOptionsLookup(),
			$services->getMessageFormatterFactory()
		);
	},

	'Beacon.RevertedPresenter' => static function ( MediaWikiServices $services ): RevertedPresenter {
		return new RevertedPresenter(
			$services->getService( 'Beacon.RecipientFormatterFactory' )
		);
	},

	'Beacon.RevertNotifier' => static function ( MediaWikiServices $services ): RevertNotifier {
		return new RevertNotifier(
			$services->getRevisionStore(),
			$services->getTitleFactory(),
			$services->getService( 'Beacon.RevertedPresenter' ),
			$services->getService( 'Beacon.NotificationStore' ),
			SourceWiki::newFromConfig( $services->getMainConfig() )
		);
	},
];
