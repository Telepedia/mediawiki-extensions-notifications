<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\Events\EventController;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\Notifiers\RevertNotifier;
use Telepedia\Extensions\Beacon\Notifiers\UserJsEditNotifier;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatterFactory;
use Telepedia\Extensions\Beacon\Presentation\RevertedPresenter;
use Telepedia\Extensions\Beacon\Presentation\UserJsEditPresenter;

/** @phpcs-require-sorted-array */
return [
	'Beacon.EventController' => static function ( MediaWikiServices $services ): EventController {
		return new EventController(
			$services->getJobQueueGroup(),
			$services->getService( 'Beacon.RevertNotifier' ),
			$services->getService( 'Beacon.UserJsEditNotifier' )
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

	'Beacon.UserJsEditNotifier' => static function ( MediaWikiServices $services ): UserJsEditNotifier {
		return new UserJsEditNotifier(
			$services->getTitleFactory(),
			$services->getUserIdentityLookup(),
			$services->getUserIdentityUtils(),
			$services->getService( 'Beacon.UserJsEditPresenter' ),
			$services->getService( 'Beacon.NotificationStore' ),
			SourceWiki::newFromConfig( $services->getMainConfig() )
		);
	},

	'Beacon.UserJsEditPresenter' => static function ( MediaWikiServices $services ): UserJsEditPresenter {
		return new UserJsEditPresenter(
			$services->getService( 'Beacon.RecipientFormatterFactory' )
		);
	},
];
