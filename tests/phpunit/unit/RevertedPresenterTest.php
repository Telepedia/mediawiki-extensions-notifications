<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Presentation\NotificationContext;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatter;
use Telepedia\Extensions\Beacon\Presentation\RevertedPresenter;
use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageParam;
use Wikimedia\Message\MessageValue;

/**
 * @covers \Telepedia\Extensions\Beacon\Presentation\RevertedPresenter
 */
class RevertedPresenterTest extends MediaWikiUnitTestCase {

	private const string DIFF_URL = 'https://foo.example.com/wiki/Some_Page?diff=203';
	private const string PAGE_URL = 'https://foo.example.com/wiki/Some_Page';

	/**
	 * @return RecipientFormatter
	 */
	private function newFormatter(): RecipientFormatter {
		$textFormatter = $this->createMock( ITextFormatter::class );
		$textFormatter->method( 'format' )->willReturnCallback(
			static fn ( MessageValue $message ) => implode( '|', [
				$message->getKey(),
				...array_map( static fn ( MessageParam $param ) => $param->getValue(), $message->getParams() ),
			] )
		);
		return new RecipientFormatter( 'en', $textFormatter );
	}

	private function newContext( string $method, ?string $summary ): NotificationContext {
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( 'Some Page' );
		$title->method( 'getFullURL' )->willReturn( self::PAGE_URL );

		return new NotificationContext(
			agent: new UserIdentityValue( 2, 'Reverter' ),
			title: $title,
			extra: [ 'method' => $method, 'diffUrl' => self::DIFF_URL, 'summary' => $summary ],
		);
	}

	public static function provideMethods(): array {
		return [
			'undo' => [ 'undo' ],
			'rollback' => [ 'rollback' ],
		];
	}

	/**
	 * @dataProvider provideMethods
	 */
	public function testHeaderUsesTheMethodsMessage( string $method ): void {
		$rendered = new RevertedPresenter()->present( $this->newFormatter(), $this->newContext( $method, null ) );

		$this->assertSame( "beacon-notification-reverted-$method|Reverter|Some Page", $rendered->header );
		$this->assertSame( 'en', $rendered->language );
	}

	public function testLinksAndUrl(): void {
		$rendered = new RevertedPresenter()->present( $this->newFormatter(), $this->newContext( 'undo', null ) );

		$this->assertSame( self::DIFF_URL, $rendered->url );
		$this->assertSame( 'revert', $rendered->icon );
		$this->assertSame(
			[ self::DIFF_URL, self::PAGE_URL ],
			array_map( static fn ( $link ) => $link->url, $rendered->links )
		);
		$this->assertSame( 'beacon-notification-link-view-changes', $rendered->links[0]->label );
	}

	public function testSummaryBecomesTheBody(): void {
		$presenter = new RevertedPresenter();

		$this->assertSame(
			'rv vandalism',
			$presenter->present( $this->newFormatter(), $this->newContext( 'undo', 'rv vandalism' ) )->body
		);

		$this->assertNull( $presenter->present( $this->newFormatter(), $this->newContext( 'undo', '' ) )->body );
		$this->assertNull( $presenter->present( $this->newFormatter(), $this->newContext( 'undo', null ) )->body );
	}
}
