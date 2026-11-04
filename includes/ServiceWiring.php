<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationSender;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\NotificationTypeRegistry;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatterFactory;

/** @phpcs-require-sorted-array */
return [
	'Beacon.NotificationSender' => static function ( MediaWikiServices $services ): NotificationSender {
		return new NotificationSender(
			$services->getService( 'Beacon.NotificationTypeRegistry' ),
			$services->getService( 'Beacon.RecipientFormatterFactory' ),
			$services->getService( 'Beacon.NotificationStore' ),
			SourceWiki::newFromConfig( $services->getMainConfig() )
		);
	},

	'Beacon.NotificationStore' => static function ( MediaWikiServices $services ): NotificationStore {
		return new NotificationStore(
			$services->getConnectionProvider(),
			$services->getWANObjectCache(),
			LoggerFactory::getInstance( 'Beacon' )
		);
	},

	'Beacon.NotificationTypeRegistry' => static function ( MediaWikiServices $services ): NotificationTypeRegistry {
		return new NotificationTypeRegistry(
			$services->getObjectFactory(),
			ExtensionRegistry::getInstance()->getAttribute( 'BeaconTypes' )
		);
	},

	'Beacon.RecipientFormatterFactory' => static function ( MediaWikiServices $services ): RecipientFormatterFactory {
		return new RecipientFormatterFactory(
			$services->getUserOptionsLookup(),
			$services->getMessageFormatterFactory()
		);
	},
];
