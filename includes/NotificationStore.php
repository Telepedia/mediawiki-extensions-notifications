<?php

namespace Telepedia\Extensions\Beacon;

use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;

class NotificationStore {

	/**
	 * The virtual domain as defined in extension.json
	 */
	private const string DOMAIN = 'virtual-beacon';

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly WANObjectCache $wanCache,
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
		if ( !$ids ) {
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

		$affected = $dbw->affectedRows();
		if ( $affected ) {
			$this->invalidateUnreadCounts( [ $user->getId() ] );
		}

		return $affected;
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
	 * Hide or restore one batch of notifications about a page, i.e. when it is deleted or restored
	 * @param string $wiki the wiki ID the page belongs to
	 * @param int $pageId the page ID on that wiki
	 * @param bool $hidden true to hide, false to restore
	 * @param int $batchSize the most rows to change in one call
	 * @return int[] the IDs of the users whose notifications were changed in this batch
	 */
	public function setHiddenForPage( string $wiki, int $pageId, bool $hidden, int $batchSize ): array {
		$dbw = $this->connectionProvider->getPrimaryDatabase( self::DOMAIN );

		$res = $dbw->newSelectQueryBuilder()
			->select( [ 'notif_id', 'notif_user' ] )
			->from( 'beacon_notifications' )
			->where( [
				'notif_wiki' => $wiki,
				'notif_page_id' => $pageId,
				'notif_hidden' => $hidden ? 0 : 1,
			] )
			->limit( $batchSize )
			->caller( __METHOD__ )
			->fetchResultSet();

		$ids = [];
		$users = [];
		foreach ( $res as $row ) {
			$ids[] = (int)$row->notif_id;
			$users[(int)$row->notif_user] = true;
		}

		if ( !$ids ) {
			return [];
		}

		$dbw->newUpdateQueryBuilder()
			->update( 'beacon_notifications' )
			->set( [ 'notif_hidden' => $hidden ? 1 : 0 ] )
			->where( [ 'notif_id' => $ids ] )
			->caller( __METHOD__ )
			->execute();

		$userIds = array_keys( $users );
		$this->invalidateUnreadCounts( $userIds );

		return $userIds;
	}

	/**
	 * Invalidate cached unread counts for these users, so their next request recounts.
	 * @param int[] $userIds
	 * @return void
	 */
	public function invalidateUnreadCounts( array $userIds ): void {
		foreach ( $userIds as $userId ) {
			$this->wanCache->touchCheckKey( $this->getUnreadCountCheckKey( $userId ) );
		}
	}

	/**
	 * The cache check key for a user's unread count.
	 * @param int $userId
	 * @return string
	 */
	public function getUnreadCountCheckKey( int $userId ): string {
		return $this->wanCache->makeGlobalKey( 'beacon-unread-count', $userId );
	}

	/**
	 * Purge notifications from the database
	 * NOT-IMPLEMENTED
	 * @return int the number of rows purged
	 */
	public function purge(): int {
		return 0;
	}

}
