<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationCategoryRegistry;
use Telepedia\Extensions\Beacon\NotificationIconRegistry;
use Telepedia\Extensions\Beacon\NotificationSender;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\NotificationTypeRegistry;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatterFactory;

/** @phpcs-require-sorted-array */
return [
	'Beacon.NotificationCategoryRegistry' => static function (): NotificationCategoryRegistry {
		return new NotificationCategoryRegistry(
			ExtensionRegistry::getInstance()->getAttribute( 'BeaconCategories' )
		);
	},

	'Beacon.NotificationIconRegistry' => static function ( MediaWikiServices $services ): NotificationIconRegistry {
		return new NotificationIconRegistry(
			ExtensionRegistry::getInstance()->getAttribute( 'BeaconIcons' ),
			$services->getResourceLoader(),
			$services->getMainConfig()->get( MainConfigNames::LoadScript ),
			$services->getUrlUtils(),
			LoggerFactory::getInstance( 'Beacon' )
		);
	},

	'Beacon.NotificationSender' => static function ( MediaWikiServices $services ): NotificationSender {
		return new NotificationSender(
			$services->getService( 'Beacon.NotificationTypeRegistry' ),
			$services->getService( 'Beacon.RecipientFormatterFactory' ),
			$services->getService( 'Beacon.NotificationStore' ),
			SourceWiki::newFromConfig( $services->getMainConfig() ),
			$services->getService( 'Beacon.NotificationIconRegistry' )
		);
	},

	'Beacon.NotificationStore' => static function ( MediaWikiServices $services ): NotificationStore {
		return new NotificationStore(
			$services->getConnectionProvider(),
			$services->getCentralIdLookup(),
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
