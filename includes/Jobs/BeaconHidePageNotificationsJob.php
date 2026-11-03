<?php

namespace Telepedia\Extensions\Beacon\Jobs;

use MediaWiki\JobQueue\Job;
use Telepedia\Extensions\Beacon\NotificationStore;
use Wikimedia\Rdbms\IConnectionProvider;

class BeaconHidePageNotificationsJob extends Job {

	public const string JOB_NAME = 'BeaconHidePageNotificationsJob';

	private const int BATCH_SIZE = 500;

	public function __construct(
		array $params,
		private readonly NotificationStore $notificationStore,
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

		do {
			$users = $this->notificationStore->setHiddenForPage(
				$this->params['wiki'],
				(int)$this->params['pageId'],
				(bool)$this->params['hidden'],
				self::BATCH_SIZE
			);
			$this->connectionProvider->commitAndWaitForReplication( __METHOD__, $ticket );
		} while ( $users );

		return true;
	}
}
