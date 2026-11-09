<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\ResourceLoader\PopoverConfig;

/**
 * @covers \Telepedia\Extensions\Beacon\ResourceLoader\PopoverConfig
 */
class PopoverConfigTest extends MediaWikiUnitTestCase {

	public function testSkinCanChooseThePlacement(): void {
		$this->assertSame( 'right-end', PopoverConfig::getPlacement( [ 'prism' => 'right-end' ], 'prism' ) );
	}

	public function testOtherSkinsGetTheDefault(): void {
		$this->assertSame(
			PopoverConfig::DEFAULT_PLACEMENT,
			PopoverConfig::getPlacement( [ 'prism' => 'right-end' ], 'vector-2022' )
		);
	}

	public function testInvalidPlacementFallsBackToTheDefault(): void {
		$this->assertSame(
			PopoverConfig::DEFAULT_PLACEMENT,
			PopoverConfig::getPlacement( [ 'prism' => 'sideways' ], 'prism' )
		);
	}
}
