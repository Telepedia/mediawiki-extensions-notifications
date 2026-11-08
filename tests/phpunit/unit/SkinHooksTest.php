<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Hooks\SkinHooks;
use Telepedia\Extensions\Beacon\NotificationStore;

/**
 * @covers \Telepedia\Extensions\Beacon\Hooks\SkinHooks
 */
class SkinHooksTest extends MediaWikiUnitTestCase {

	/**
	 * The page's last-modified time
	 * +10 points if you are reading this and can guess the easter egg
	 */
	private const string PAGE_TOUCHED = '20031208000000';

	/**
	 * When the unread count last changed
	 * + another 10 points if you guess this one!
	 */
	private const float COUNT_TOUCHED = 1734739200.2;

	/**
	 * @param float|null $touched when the store says the count last changed
	 * @param bool $named whether the user is a named user
	 * @param array $query the request query parameters
	 * @return array the modified times after the hook has run
	 */
	private function runCheckLastModified( ?float $touched, bool $named = true, array $query = [] ): array {
		$user = $this->createMock( User::class );
		$user->method( 'isNamed' )->willReturn( $named );

		$out = $this->createMock( OutputPage::class );
		$out->method( 'getRequest' )->willReturn( new FauxRequest( $query ) );
		$out->method( 'getUser' )->willReturn( $user );

		$store = $this->createMock( NotificationStore::class );
		$store->method( 'getUnreadCountTouched' )->willReturn( $touched );

		$modifiedTimes = [ 'page' => self::PAGE_TOUCHED ];
		new SkinHooks( $store )->onOutputPageCheckLastModified( $modifiedTimes, $out );
		return $modifiedTimes;
	}

	public function testAddsWhenTheUnreadCountLastChanged(): void {
		$this->assertSame(
			[ 'page' => self::PAGE_TOUCHED, 'beacon-notifications' => '20241221000001' ],
			$this->runCheckLastModified( self::COUNT_TOUCHED )
		);
	}

	public static function provideSkipped(): array {
		return [
			'not a named user' => [ self::COUNT_TOUCHED, false, [] ],
			'action=raw' => [ self::COUNT_TOUCHED, true, [ 'action' => 'raw' ] ],
			'action=render' => [ self::COUNT_TOUCHED, true, [ 'action' => 'render' ] ],
			'no central ID' => [ null, true, [] ],
		];
	}

	/**
	 * @dataProvider provideSkipped
	 */
	public function testSkipped( ?float $touched, bool $named, array $query ): void {
		$this->assertSame(
			[ 'page' => self::PAGE_TOUCHED ],
			$this->runCheckLastModified( $touched, $named, $query )
		);
	}
}
