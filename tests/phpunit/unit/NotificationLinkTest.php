<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use InvalidArgumentException;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;

/**
 * @covers \Telepedia\Extensions\Beacon\Domain\NotificationLink
 */
class NotificationLinkTest extends MediaWikiUnitTestCase {

	public static function provideInvalidUrls(): array {
		return [
			'javascript' => [ 'javascript:alert(1)' ],
			'relative' => [ '/wiki/Bar', '/index.php?title=Foo' ],
			'protocol-relative' => [ '//example.org/wiki/Foo' ],
			'empty' => [ '' ],
		];
	}

	/**
	 * @dataProvider provideInvalidUrls
	 */
	public function testRejectsNonAbsoluteUrls( string $url ): void {
		$this->expectException( InvalidArgumentException::class );
		new NotificationLink( 'Label', $url );
	}

	/**
	 * Test JSON roundtrip, that is, encode it and then decode it again and see if we get the same back
	 * @return void
	 * @throws \JsonException
	 */
	public function testJson(): void {
		$links = [
			new NotificationLink( 'View changes', 'https://foo.example.com/w/index.php?diff=1' ),
			new NotificationLink( 'Talk page', 'https://foo.example.com/wiki/User_talk:Bar', 'talk' ),
		];

		$this->assertEquals(
			$links,
			NotificationLink::newListFromJson( NotificationLink::listToJson( $links ) )
		);
	}

	/**
	 * Empty list should be null, obviously!
	 * @return void
	 * @throws \JsonException
	 */
	public function testEmptyListIsNull(): void {
		$this->assertNull( NotificationLink::listToJson( [] ) );
		$this->assertSame( [], NotificationLink::newListFromJson( null ) );
	}

	/**
	 * Icons are optional and are omitted if not passed
	 * @return void
	 */
	public function testNullIconIsOmitted(): void {
		$link = new NotificationLink( 'Label', 'https://example.org/' );
		$this->assertArrayNotHasKey( 'icon', $link->toArray() );
	}

}
