<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\NotificationCategoryRegistry;

/**
 * @covers \Telepedia\Extensions\Beacon\NotificationCategoryRegistry
 */
class NotificationCategoryRegistryTest extends MediaWikiUnitTestCase {

	private function newRegistry(): NotificationCategoryRegistry {
		return new NotificationCategoryRegistry( [
			'reverted' => [ 'label' => 'custom-label' ],
			'thanks' => [ 'default' => false ],
		] );
	}

	public function testListsTheCategories(): void {
		$this->assertSame( [ 'reverted', 'thanks' ], $this->newRegistry()->getNames() );
	}

	public function testKnowsWhichCategoriesAreRegistered(): void {
		$registry = $this->newRegistry();

		$this->assertTrue( $registry->has( 'thanks' ) );
		$this->assertFalse( $registry->has( 'mention' ) );
	}

	public function testLabelFallsBackToAMessageNamedAfterTheCategory(): void {
		$registry = $this->newRegistry();

		$this->assertSame( 'custom-label', $registry->getLabel( 'reverted' ) );
		$this->assertSame( 'beacon-category-thanks', $registry->getLabel( 'thanks' ) );
	}

	public function testPreferenceNameIncludesTheChannel(): void {
		$this->assertSame( 'beacon-web-reverted', $this->newRegistry()->getPreferenceName( 'reverted' ) );
	}

	public function testCategoriesAreOnUnlessToldOtherwise(): void {
		$this->assertSame(
			[ 'beacon-web-reverted' => 1, 'beacon-web-thanks' => 0 ],
			$this->newRegistry()->getDefaultOptions()
		);
	}
}
