<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use InvalidArgumentException;
use MediaWikiUnitTestCase;
use Psr\Container\ContainerInterface;
use Telepedia\Extensions\Beacon\NotificationTypeRegistry;
use Telepedia\Extensions\Beacon\Presentation\RevertedPresenter;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * @covers \Telepedia\Extensions\Beacon\NotificationTypeRegistry
 */
class NotificationTypeRegistryTest extends MediaWikiUnitTestCase {

	private function newRegistry(): NotificationTypeRegistry {
		return new NotificationTypeRegistry(
			new ObjectFactory( $this->createMock( ContainerInterface::class ) ),
			[
				'reverted' => [
					'category' => 'reverted-category',
					'presenter' => [ 'class' => RevertedPresenter::class ],
				],
				'no-category' => [
					'presenter' => [ 'class' => RevertedPresenter::class ],
				],
			]
		);
	}

	public function testCreatesThePresenterOnce(): void {
		$registry = $this->newRegistry();

		$presenter = $registry->getPresenter( 'reverted' );

		$this->assertInstanceOf( RevertedPresenter::class, $presenter );
		$this->assertSame( $presenter, $registry->getPresenter( 'reverted' ) );
	}

	public function testCategory(): void {
		$registry = $this->newRegistry();

		$this->assertSame( 'reverted-category', $registry->getCategory( 'reverted' ) );
		$this->assertSame( 'no-category', $registry->getCategory( 'no-category' ) );
	}

	public function testHas(): void {
		$registry = $this->newRegistry();

		$this->assertTrue( $registry->has( 'reverted' ) );
		$this->assertFalse( $registry->has( 'not-a-type' ) );
	}

	public function testUnknownTypeThrows(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->newRegistry()->getPresenter( 'not-a-type' );
	}
}
