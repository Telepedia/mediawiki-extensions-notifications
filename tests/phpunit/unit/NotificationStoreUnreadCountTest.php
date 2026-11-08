<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Telepedia\Extensions\Beacon\NotificationStore;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Expression;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\UpdateQueryBuilder;

/**
 * @covers \Telepedia\Extensions\Beacon\NotificationStore
 */
class NotificationStoreUnreadCountTest extends MediaWikiUnitTestCase {

	/**
	 * Local ID of the test user.
	 * @var int
	 */
	private const int LOCAL_ID = 3;

	/**
	 * @var int
	 */
	private const int CENTRAL_ID = 42;

	/**
	 * @var float
	 */
	private float $now = 1700000000.0;

	/**
	 * @var int the count the db returns
	 */
	private int $dbCount = 0;

	/**
	 * @var array
	 */
	private array $countQueries = [];

	/**
	 * @var WANObjectCache
	 */
	private WANObjectCache $wanCache;

	private function newStore(): NotificationStore {
		$this->wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$this->wanCache->setMockTime( $this->now );

		$db = $this->createMock( IDatabase::class );
		$db->method( 'newSelectQueryBuilder' )->willReturnCallback( static fn () => new SelectQueryBuilder( $db ) );
		$db->method( 'newUpdateQueryBuilder' )->willReturnCallback( static fn () => new UpdateQueryBuilder( $db ) );
		$db->method( 'selectRowCount' )->willReturnCallback(
			function ( $tables, $var, $conds, $fname, $options ) {
				$this->countQueries[] = [ $conds, $options ];
				return $this->dbCount;
			}
		);
		$db->method( 'expr' )->willReturnCallback(
			static fn ( string $field, string $op, $value ) => new Expression( $field, $op, $value )
		);
		$db->method( 'timestamp' )->willReturn( '20231114221320' );
		$db->method( 'affectedRows' )->willReturn( 1 );

		$connectionProvider = $this->createMock( IConnectionProvider::class );
		$connectionProvider->method( 'getReplicaDatabase' )->willReturn( $db );
		$connectionProvider->method( 'getPrimaryDatabase' )->willReturn( $db );

		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )->willReturnCallback(
			static fn ( $user ) => $user->getId() === self::LOCAL_ID ? self::CENTRAL_ID : 0
		);

		return new NotificationStore( $connectionProvider, $centralIdLookup, $this->wanCache, new NullLogger() );
	}

	private function newUser(): UserIdentityValue {
		return new UserIdentityValue( self::LOCAL_ID, 'TestUser' );
	}

	private function primeCache( NotificationStore $store ): void {
		$store->getUnreadCount( $this->newUser() );
		$this->now += WANObjectCache::HOLDOFF_TTL + 1;
		$store->getUnreadCount( $this->newUser() );
		$this->now += 1;
	}

	public function testCountsUnreadVisibleNotificationsForTheCentralUser(): void {
		$this->dbCount = 7;
		$store = $this->newStore();

		$this->assertSame( 7, $store->getUnreadCount( $this->newUser() ) );

		[ $conds, $options ] = $this->countQueries[0];
		$this->assertSame(
			[ 'notif_user' => self::CENTRAL_ID, 'notif_read' => null, 'notif_hidden' => 0 ],
			$conds
		);
		$this->assertSame( NotificationStore::MAX_UNREAD_COUNT + 1, $options['LIMIT'] );
	}

	public function testCountIsCached(): void {
		$this->dbCount = 2;
		$store = $this->newStore();
		$this->primeCache( $store );
		$queries = count( $this->countQueries );
		$this->dbCount = 5;

		$this->assertSame( 2, $store->getUnreadCount( $this->newUser() ) );
		$this->assertCount( $queries, $this->countQueries, 'no new query' );
	}

	public function testInvalidatingRecounts(): void {
		$this->dbCount = 2;
		$store = $this->newStore();
		$this->primeCache( $store );

		$this->dbCount = 3;
		$store->invalidateUnreadCounts( [ self::CENTRAL_ID ] );

		$this->assertSame( 3, $store->getUnreadCount( $this->newUser() ) );
	}

	public function testMarkAllReadInvalidatesTheCount(): void {
		$this->dbCount = 2;
		$store = $this->newStore();
		$this->primeCache( $store );

		$this->dbCount = 0;
		$store->markAllRead( $this->newUser(), 100 );

		$this->assertSame( 0, $store->getUnreadCount( $this->newUser() ) );
	}

	public function testMarkReadInvalidatesTheCount(): void {
		$this->dbCount = 2;
		$store = $this->newStore();
		$this->primeCache( $store );

		$this->dbCount = 1;
		$store->markRead( $this->newUser(), [ 10 ] );

		$this->assertSame( 1, $store->getUnreadCount( $this->newUser() ) );
	}

	public function testUserWithoutCentralIdHasNoUnreadNotifications(): void {
		$this->dbCount = 7;
		$store = $this->newStore();
		$user = new UserIdentityValue( 99, 'Nobody' );

		$this->assertSame( 0, $store->getUnreadCount( $user ) );
		$this->assertNull( $store->getUnreadCountTouched( $user ) );
		$this->assertSame( [], $this->countQueries );
	}

	public function testTouchedTimeMovesWhenTheCountIsInvalidated(): void {
		$store = $this->newStore();
		$before = $store->getUnreadCountTouched( $this->newUser() );

		$this->now += 60;
		$store->invalidateUnreadCounts( [ self::CENTRAL_ID ] );

		$this->assertGreaterThan( $before, $store->getUnreadCountTouched( $this->newUser() ) );
	}
}
