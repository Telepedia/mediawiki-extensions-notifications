<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use Closure;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Hooks\PreferencesHooks;
use Telepedia\Extensions\Beacon\NotificationCategoryRegistry;

/**
 * @covers \Telepedia\Extensions\Beacon\Hooks\PreferencesHooks
 */
class PreferencesHooksTest extends MediaWikiUnitTestCase {

	private function newHooks( bool $hasGlobalPreferences = false ): PreferencesHooks {
		return new PreferencesHooks( new NotificationCategoryRegistry( [
			'reverted' => [ 'label' => 'label-reverted' ],
			'thanks' => [ 'label' => 'label-thanks', 'default' => false ],
		] ), $hasGlobalPreferences );
	}

	public function testAddsACheckboxForEachCategory(): void {
		$preferences = [ 'existing' => [] ];

		$this->newHooks()->onGetPreferences( $this->createMock( User::class ), $preferences );

		$this->assertSame( [
			'existing' => [],
			'beacon-web-reverted' => [
				'type' => 'toggle',
				'label-message' => 'label-reverted',
				'section' => 'beacon/beacon-categories',
			],
			'beacon-web-thanks' => [
				'type' => 'toggle',
				'label-message' => 'label-thanks',
				'section' => 'beacon/beacon-categories',
			],
		], $preferences );
	}

	public function testExplainsThePerWikiValuesWithGlobalPreferencesInstalled(): void {
		$preferences = [];

		$this->newHooks( true )->onGetPreferences( $this->createMock( User::class ), $preferences );

		$this->assertSame( 'beacon-per-wiki-note', array_key_first( $preferences ) );
		$note = $preferences['beacon-per-wiki-note'];
		$this->assertSame( 'info', $note['type'] );
		$this->assertTrue( $note['raw'] );
		$this->assertTrue( $note['noglobal'] );
		$this->assertSame( 'beacon/beacon-categories', $note['section'] );
		$this->assertInstanceOf( Closure::class, $note['default'] );
	}

	public function testAddsTheDefaultsWithoutChangingOtherOptions(): void {
		$options = [ 'skin' => 'vector' ];

		$this->newHooks()->onUserGetDefaultOptions( $options );

		$this->assertSame( 'vector', $options['skin'] );
		$this->assertSame( 1, $options['beacon-web-reverted'] );
		$this->assertSame( 0, $options['beacon-web-thanks'] );
	}
}
