<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FileModule;
use MediaWiki\ResourceLoader\Image;
use MediaWiki\ResourceLoader\ImageModule;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Utils\UrlUtils;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Telepedia\Extensions\Beacon\NotificationIconRegistry;

/**
 * @covers \Telepedia\Extensions\Beacon\NotificationIconRegistry
 */
class NotificationIconRegistryTest extends MediaWikiUnitTestCase {

	private const array ICONS = [
		'an-icon' => [ 'module' => 'ext.someExtension.icons', 'image' => 'anIcon' ],
		'missing-image' => [ 'module' => 'ext.someExtension.icons', 'image' => 'nope' ],
		'missing-module' => [ 'module' => 'ext.nope', 'image' => 'anIcon' ],
		'not-an-image-module' => [ 'module' => 'ext.beacon.popover', 'image' => 'anIcon' ],
	];

	/**
	 * @return ResourceLoader
	 */
	private function newResourceLoader(): ResourceLoader {
		$images = [];
		foreach ( [ 'anIcon' ] as $name ) {
			$image = $this->createMock( Image::class );
			$image->method( 'getUrl' )->willReturnCallback(
				static function ( Context $context, string $script, ?string $variant, string $format ) use ( $name ) {
					$lang = $context->getRequest()->getRawVal( 'lang' );
					return "$script?modules=ext.someExtension.icons&image=$name&format=$format&lang=$lang";
				}
			);
			$images[$name] = $image;
		}

		$imageModule = $this->createMock( ImageModule::class );
		$imageModule->method( 'getImage' )->willReturnCallback(
			static fn ( string $name ) => $images[$name] ?? null
		);

		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->method( 'getModule' )->willReturnMap( [
			[ 'ext.someExtension.icons', $imageModule ],
			[ 'ext.beacon.popover', $this->createMock( FileModule::class ) ],
			[ 'ext.nope', null ],
		] );
		return $resourceLoader;
	}

	private function newRegistry( array $icons = self::ICONS ): NotificationIconRegistry {
		return new NotificationIconRegistry(
			$icons,
			$this->newResourceLoader(),
			'/w/load.php',
			new UrlUtils( [
				UrlUtils::SERVER => '//meta.example.com',
				UrlUtils::CANONICAL_SERVER => 'https://meta.example.com',
			] ),
			new NullLogger()
		);
	}

	public function testCodexIconIsStoredAsIs(): void {
		$this->assertSame( 'cdxIconUndo', $this->newRegistry()->resolve( 'cdxIconUndo', 'en' ) );
	}

	public function testCustomIconBecomesCanonicalLoadPhpUrl(): void {
		$this->assertSame(
			'https://meta.example.com/w/load.php?modules=ext.someExtension.icons&image=anIcon&format=original&lang=en',
			$this->newRegistry()->resolve( 'an-icon', 'en' )
		);
	}

	public function testRecipientLanguageIsPassedToResourceLoader(): void {
		$this->assertStringEndsWith( '&lang=he', $this->newRegistry()->resolve( 'an-icon', 'he' ) );
	}

	public static function provideBrokenIcons(): array {
		return [
			'not registered' => [ 'no-such-icon' ],
			'module has no such image' => [ 'missing-image' ],
			'module does not exist' => [ 'missing-module' ],
			'module is not an image module' => [ 'not-an-image-module' ],
			'looks like Codex but is not' => [ 'cdxicon-undo' ],
		];
	}

	/**
	 * @dataProvider provideBrokenIcons
	 */
	public function testBrokenIconFallsBackToBell( string $name ): void {
		$this->assertSame( NotificationIconRegistry::FALLBACK, $this->newRegistry()->resolve( $name, 'en' ) );
	}
}
