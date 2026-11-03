<?php

namespace Telepedia\Extensions\Beacon;

use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IConnectionProvider;

class NotificationStore {

	/**
	 * The virtual domain as defined in extension.json
	 */
	private const string DOMAIN = 'virtual-beacon';

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly LoggerInterface $logger,
	) {}

	public function store( UserIdentity $user ): int {

	}

	public function listForUser( UserIdentity $user ) {}

	/**
	 * Mark a notification – or all notifications, as read
	 * @param UserIdentity $user the user we're working with
	 * @param array $ids the IDs of the notifications to mark as read
	 * @return int the number of rows affected
	 */
	public function markRead( UserIdentity $user, array $ids ): int {
		if ( $ids ) {
			return 0;
		}

		$dbw = $this->connectionProvider->getPrimaryDatabase( self::DOMAIN );
		$dbw->newUpdateQueryBuilder()
			->update( 'beacon_notifications' )
			->set( [ 'notif_read' => $dbw->timestamp() ] )
			->where( [
				'notif_user' => $user->getId(),
				'notif_id' => $ids,
				'notif_read' => null
			] )
			->caller( __METHOD__ )
			->execute();

		return $dbw->affectedRows();
	}

	/**
	 * Mark a notification as seen, but not read
	 * @param UserIdentity $user the user we're working with
	 * @param array $ids the ids of the notifications to mark as read
	 * @return void a simple upsert so does not return anything. Failures are logged but swallowed
	 * and not thrown
	 *
	 */
	public function markSeen( UserIdentity $user, array $ids ): void {}

	/**
	 * Purge notifications from the database
	 * NOT-IMPLEMENTED
	 * @return int the number of rows purged
	 */
	public function purge(): int {
		return 0;
	}

}
