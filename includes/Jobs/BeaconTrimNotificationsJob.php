<?php

namespace Telepedia\Extensions\Beacon\Jobs;

use MediaWiki\JobQueue\Job;
use MediaWiki\User\UserIdentityLookup;
use Telepedia\Extensions\Beacon\NotificationPurger;
use Wikimedia\Rdbms\IConnectionProvider;

class BeaconTrimNotificationsJob extends Job {

	public const string JOB_NAME = 'BeaconTrimNotificationsJob';

	private const int BATCH_SIZE = 500;

	/**
	 * @param array $params
	 * @param NotificationPurger $purger
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param IConnectionProvider $connectionProvider
	 */
	public function __construct(
		array $params,
		private readonly NotificationPurger $purger,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly IConnectionProvider $connectionProvider,
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->removeDuplicates = true;
	}

	/**
	 * @inheritDoc
	 */
	public function run(): bool {
		$ticket = $this->connectionProvider->getEmptyTransactionTicket( __METHOD__ );

		foreach ( $this->params['users'] as $userId ) {
			$user = $this->userIdentityLookup->getUserIdentityByUserId( (int)$userId );
			if ( !$user ) {
				continue;
			}

			do {
				$deleted = $this->purger->trimUser( $user, self::BATCH_SIZE );
				$this->connectionProvider->commitAndWaitForReplication( __METHOD__, $ticket );
			} while ( $deleted );
		}

		return true;
	}
}
