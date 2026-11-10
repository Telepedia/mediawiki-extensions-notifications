<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\Rest\GetNotificationHandler;

/**
 * @covers \Telepedia\Extensions\Beacon\Rest\GetNotificationHandler
 */
class GetNotificationHandlerTest extends MediaWikiUnitTestCase {

	use HandlerTestTrait;

	/**
	 * @var array
	 */
	private array $listCalls = [];

	/**
	 * @param array $ids
	 * @return GetNotificationHandler
	 */
	private function newHandler( array $ids, bool $named = true ): GetNotificationHandler {
		$store = $this->createMock( NotificationStore::class );
		$store->method( 'listForUser' )->willReturnCallback(
			function ( UserIdentity $user, int $limit, ?int $olderThan ) use ( $ids ) {
				$this->listCalls[] = [ $limit, $olderThan ];
				$ids = array_filter( $ids, static fn ( $id ) => $olderThan === null || $id < $olderThan );
				// this can go to a pipe operator potentially but the refactor PHPStorm gives is really unclear
				// and cluttered so just leaving it as it is now as it is easier to reason about
				return array_map(
					static fn ( $id ) => new Notification(
						recipient: $user,
						wiki: new SourceWiki( 'foowiki', 'Foo Wiki' ),
						type: 'reverted',
						category: 'reverted',
						url: 'https://foo.example.com/wiki/Page',
						header: "Notification $id",
						language: 'en',
						icon: 'cdxIconUndo',
						id: $id,
						created: '20241221000000',
					),
					array_slice( array_values( $ids ), 0, $limit )
				);
			}
		);
		$store->method( 'getUnreadCount' )->willReturn( 2 );

		$identityUtils = $this->createMock( UserIdentityUtils::class );
		$identityUtils->method( 'isNamed' )->willReturn( $named );

		return new GetNotificationHandler( $store, $identityUtils );
	}

	private function get( GetNotificationHandler $handler, array $query = [] ): array {
		return $this->executeHandlerAndGetBodyData(
			$handler,
			new RequestData( [ 'queryParams' => $query ] ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority()
		);
	}

	public function testReturnsAPageAndWhereToContinue(): void {
		$data = $this->get( $this->newHandler( [ 5, 4, 3, 2, 1 ] ), [ 'limit' => 2 ] );

		$this->assertSame( [ 5, 4 ], array_column( $data['notifications'], 'id' ) );
		$this->assertSame( 4, $data['continue'] );
		$this->assertSame( 2, $data['unreadCount'] );
		$this->assertSame( [ 3, null ], $this->listCalls[0] );
	}

	public function testContinueGetsTheNextPage(): void {
		$data = $this->get( $this->newHandler( [ 5, 4, 3, 2, 1 ] ), [ 'limit' => 2, 'continue' => 4 ] );

		$this->assertSame( [ 3, 2 ], array_column( $data['notifications'], 'id' ) );
		$this->assertSame( [ 3, 4 ], $this->listCalls[0] );
	}

	public function testLastPageHasNoContinue(): void {
		$data = $this->get( $this->newHandler( [ 2, 1 ] ), [ 'limit' => 2 ] );

		$this->assertSame( [ 2, 1 ], array_column( $data['notifications'], 'id' ) );
		$this->assertNull( $data['continue'] );
	}

	public function testDefaultsToTheNewest25(): void {
		$this->get( $this->newHandler( [] ) );

		$this->assertSame( [ 26, null ], $this->listCalls[0] );
	}

	public function testLoggedOutAndTemporaryUsersMustLogIn(): void {
		$exception = $this->executeHandlerAndGetHttpException( $this->newHandler( [ 1 ], false ), new RequestData() );

		$this->assertInstanceOf( LocalizedHttpException::class, $exception );
		$this->assertSame( 401, $exception->getCode() );
		$this->assertSame( 'exception-nologin-text', $exception->getMessageValue()->getKey() );
		$this->assertSame( [], $this->listCalls );
	}
}
