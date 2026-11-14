<?php

namespace Telepedia\Extensions\Beacon\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use Telepedia\Extensions\Beacon\NotificationPurger;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Delete notifications older than $wgBeaconMaxNotificationAge, for every user on every wiki.
 * Should be ran periodically on a cron job
 * @ingroup Maintenance
 */
class PurgeExpiredNotifications extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Delete Beacon notifications older than $wgBeaconMaxNotificationAge days' );
		$this->setBatchSize( 500 );
		$this->requireExtension( 'Beacon' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): void {
		/** @var NotificationPurger $purger */
		$purger = $this->getServiceContainer()->getService( 'Beacon.NotificationPurger' );

		$total = 0;
		do {
			$deleted = $purger->purgeExpired( $this->getBatchSize() );
			$total += $deleted;
			$this->waitForReplication();
		} while ( $deleted );

		$this->output( "Deleted $total notifications.\n" );
	}
}

$maintClass = PurgeExpiredNotifications::class;
require_once RUN_MAINTENANCE_IF_MAIN;
