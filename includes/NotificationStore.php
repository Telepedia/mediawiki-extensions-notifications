<?php

namespace Telepedia\Extensions\Beacon;

use JsonException;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

class NotificationStore {

	/**
	 * The virtual domain as defined in extension.json
	 */
	private const string DOMAIN = 'virtual-beacon';

	/**
	 * The most unread notifications we count; anything above this is shown as "X+" where X is the value of
	 * this constant
	 */
	public const int MAX_UNREAD_COUNT = 99;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly WANObjectCache $wanCache,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Store a new notification
	 * @param Notification $notification
	 * @return int the new notification ID, or 0 if the user has no central id
	 * @throws JsonException
	 */
	public function store( Notification $notification ): int {
		$centralId = $this->getCentralId( $notification->recipient );
		if ( $centralId === 0 ) {
			$this->logger->warning( 'Storing Beacon notification failed: {user} has no central ID. Skipping...', [
				'user' => $notification->recipient->getName(),
			] );
			return 0;
		}

		$dbw = $this->connectionProvider->getPrimaryDatabase( self::DOMAIN );
		$dbw->newInsertQueryBuilder()
			->insertInto( 'beacon_notifications' )
			->row( [
				'notif_user' => $centralId,
				'notif_wiki' => $notification->wiki->id,
				'notif_wiki_name' => $notification->wiki->name,
				'notif_type' => $notification->type,
				'notif_category' => $notification->category,
				'notif_agent_id' => $notification->agent?->getId(),
				'notif_agent_name' => $notification->agent?->getName(),
				'notif_page_id' => $notification->page?->getId(),
				'notif_page_ns' => $notification->page?->getNamespace(),
				'notif_page_title' => $notification->page?->getDBkey(),
				'notif_url' => $notification->url,
				'notif_header' => $notification->header,
				'notif_body' => $notification->body,
				'notif_language' => $notification->language,
				'notif_icon' => $notification->icon,
				'notif_links' => NotificationLink::listToJson( $notification->links ),
				'notif_group_key' => $notification->groupKey,
				'notif_created' => $dbw->timestamp( $notification->created ?? false ),
				'notif_read' => null,
				'notif_hidden' => 0,
			] )
			->caller( __METHOD__ )
			->execute();

		$id = $dbw->insertId();
		$this->invalidateUnreadCounts( [ $centralId ] );

		return $id;
	}

	/**
	 * List the user's notifications, newest first
	 * @param UserIdentity $user
	 * @param int $limit the most notifications to return
	 * @param int|null $olderThan only return notifications with an ID below this, to get the next page
	 * @return Notification[]
	 * @throws JsonException
	 */
	public function listForUser( UserIdentity $user, int $limit, ?int $olderThan = null ): array {
		$dbr = $this->connectionProvider->getReplicaDatabase( self::DOMAIN );

		$conds = [
			'notif_user' => $this->getCentralId( $user ),
			'notif_hidden' => 0,
		];
		if ( $olderThan !== null ) {
			$conds[] = $dbr->expr( 'notif_id', '<', $olderThan );
		}

		$query = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'beacon_notifications' )
			->where( $conds )
			->orderBy( 'notif_id', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ );

		$notifications = [];

		foreach ( $query->fetchResultSet() as $row ) {
			$notifications[] = Notification::newFromRow( $row );
		}

		return $notifications;
	}

	/**
	 * Get how many unread notifications the user has, cached until invalidateUnreadCounts() is called for them.
	 * We stop counting at MAX_UNREAD_COUNT + 1 so that a user with thousands of notifications doesn't cost us
	 * a big scan on every page view
	 * @param UserIdentity $user
	 * @param bool $usePrimary whether to get the count from the primary database and not a replica. This is mostly for
	 * getting the unread count after a notification is marked read, to avoid returning the old number before the
	 * replica has caught up
	 * @return int between 0 and MAX_UNREAD_COUNT + 1
	 */
	public function getUnreadCount( UserIdentity $user, bool $usePrimary = false ): int {
		$centralId = $this->getCentralId( $user );
		if ( !$centralId ) {
			return 0;
		}

		$checkKey = $this->getUnreadCountCheckKey( $centralId );

		return (int)$this->wanCache->getWithSetCallback(
			$this->wanCache->makeGlobalKey( 'beacon-unread-count-value', $centralId ),
			ExpirationAwareness::TTL_DAY,
			function () use ( $centralId, $usePrimary ) {
				$dbrw = null;
				if ( $usePrimary ) {
					$dbrw = $this->connectionProvider->getPrimaryDatabase( self::DOMAIN );
				} else {
					$dbrw = $this->connectionProvider->getReplicaDatabase( self::DOMAIN );
				}

				return $dbrw->newSelectQueryBuilder()
					->select( 'notif_id' )
					->from( 'beacon_notifications' )
					->where( [
						'notif_user' => $centralId,
						'notif_read' => null,
						'notif_hidden' => 0,
					] )
					->limit( self::MAX_UNREAD_COUNT + 1 )
					->caller( __METHOD__ )
					->fetchRowCount();
			},
			[ 'checkKeys' => [ $checkKey ] ]
		);
	}

	/**
	 * When the user's unread count last changed, so page views can be revalidated
	 * @param UserIdentity $user
	 * @return float|null unix timestamp, or null if the user has no central ID
	 */
	public function getUnreadCountTouched( UserIdentity $user ): ?float {
		$centralId = $this->getCentralId( $user );
		if ( !$centralId ) {
			return null;
		}

		return $this->wanCache->getCheckKeyTime( $this->getUnreadCountCheckKey( $centralId ) );
	}

	/**
	 * Mark a notification or series of notifications as read
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
				'notif_user' => $this->getCentralId( $user ),
				'notif_id' => $ids,
				'notif_read' => null
			] )
			->caller( __METHOD__ )
			->execute();

		$affected = $dbw->affectedRows();
		if ( $affected ) {
			$this->invalidateUnreadCounts( [ $this->getCentralId( $user ) ] );
		}

		return $affected;
	}

	/**
	 * Mark notifications that have been read as unread again
	 * @param UserIdentity $user the user we're working with
	 * @param array $ids the IDs of the notifications to mark as unread
	 * @return int the number of rows affected
	 */
	public function markUnread( UserIdentity $user, array $ids ): int {
		if ( !$ids ) {
			return 0;
		}

		$dbw = $this->connectionProvider->getPrimaryDatabase( self::DOMAIN );
		$dbw->newUpdateQueryBuilder()
			->update( 'beacon_notifications' )
			->set( [ 'notif_read' => null ] )
			->where( [
				'notif_user' => $this->getCentralId( $user ),
				'notif_id' => $ids,
				$dbw->expr( 'notif_read', '!=', null ),
			] )
			->caller( __METHOD__ )
			->execute();

		$affected = $dbw->affectedRows();
		if ( $affected ) {
			$this->invalidateUnreadCounts( [ $this->getCentralId( $user ) ] );
		}

		return $affected;
	}

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
	 * @param int[] $userIds central user IDs (the same as notif_user)
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

	/**
	 * Get the central id of this local user (for $wgSharedDB this will be the same as the local user, but alas
	 * we use the central id so we don't need to change this in the future if we switch)
	 * @param UserIdentity $user
	 * @return int
	 */
	private function getCentralId( UserIdentity $user ): int {
		return $this->centralIdLookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );
	}

	/**
	 * Mark all notifications up to a given ID as read. We purposefully don't just mark everything as read as
	 * another notification may have come in between them last refreshing the notifications and this call being made
	 * @param UserIdentity $user
	 * @param int $upToId the newest notification ID the user has seen (taken from the frontend notification dropdown)
	 * @return int the number of rows changed
	 */
	public function markAllRead( UserIdentity $user, int $upToId ): int {
		$dbw = $this->connectionProvider->getPrimaryDatabase( self::DOMAIN );
		$dbw->newUpdateQueryBuilder()
			->update( 'beacon_notifications' )
			->set( [ 'notif_read' => $dbw->timestamp() ] )
			->where( [
				'notif_user' => $this->getCentralId( $user ),
				'notif_read' => null,
				$dbw->expr( 'notif_id', '<=', $upToId ),
			] )
			->caller( __METHOD__ )
			->execute();

		$affected = $dbw->affectedRows();
		if ( $affected ) {
			$this->invalidateUnreadCounts( [ $this->getCentralId( $user ) ] );
		}

		return $affected;
	}

}
