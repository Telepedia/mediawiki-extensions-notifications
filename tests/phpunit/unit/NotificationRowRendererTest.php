<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\Special\NotificationRowRenderer;

/**
 * @covers \Telepedia\Extensions\Beacon\Special\NotificationRowRenderer
 */
class NotificationRowRendererTest extends MediaWikiUnitTestCase {

	private function newRenderer(): NotificationRowRenderer {
		$localizer = $this->createMock( MessageLocalizer::class );
		$localizer->method( 'msg' )->willReturnCallback( function ( $key ) {
			$msg = $this->createMock( Message::class );
			$msg->method( 'text' )->willReturn( "($key)" );
			return $msg;
		} );

		$language = $this->createMock( Language::class );
		$language->method( 'userTime' )->willReturn( '18:35' );
		$language->method( 'userDate' )->willReturn( '8 November 2026' );
		$language->method( 'userTimeAndDate' )->willReturn( '18:35, 8 November 2026' );

		return new NotificationRowRenderer(
			$localizer,
			$language,
			new UserIdentityValue( 3, 'Viewer' ),
			'/wiki/Special:Notifications',
			'token+\\'
		);
	}

	private function newNotification( array $overrides = [] ): Notification {
		return new Notification( ...$overrides + [
			'recipient' => new UserIdentityValue( 3, 'Viewer' ),
			'wiki' => new SourceWiki( 'foowiki', 'Foo Wiki' ),
			'type' => 'reverted',
			'category' => 'reverted',
			'url' => 'https://foo.example.com/wiki/Page?diff=cur',
			'header' => 'Someone reverted your edit to <Page>.',
			'language' => 'en',
			'icon' => 'cdxIconUndo',
			'id' => 15,
			'created' => '20261108183500',
		] );
	}

	public function testUnreadRowMarkupMatchesThePopover(): void {
		$html = $this->newRenderer()->render( $this->newNotification( [ 'body' => 'Summary' ] ) );

		$this->assertStringContainsString( 'class="beacon-item beacon-item--unread"', $html );
		$this->assertStringContainsString( 'data-notification-id="15"', $html );
		$this->assertStringContainsString(
			'<a class="beacon-item__header" href="https://foo.example.com/wiki/Page?diff=cur">' .
				'Someone reverted your edit to &lt;Page>.</a>',
			$html
		);
		$this->assertStringContainsString( '<span class="beacon-item__body">Summary</span>', $html );
		$this->assertStringContainsString( 'class="beacon-icon beacon-icon--cdxIconUndo"', $html );
		$this->assertStringContainsString( '<span class="beacon-item__wiki">Foo Wiki</span>', $html );
		$this->assertStringContainsString(
			'<time class="beacon-item__time" datetime="2026-11-08T18:35:00Z" ' .
				'title="18:35, 8 November 2026">18:35</time>',
			$html
		);
	}

	public function testUnreadRowHasAMarkReadForm(): void {
		$html = $this->newRenderer()->render( $this->newNotification() );

		$this->assertStringContainsString( 'method="post" action="/wiki/Special:Notifications"', $html );
		$this->assertStringContainsString( 'value="markread" name="beaconaction"', $html );
		$this->assertStringContainsString( 'value="15" name="id"', $html );
		$this->assertStringContainsString( 'value="token+\\" name="wpEditToken"', $html );
		$this->assertStringContainsString( 'aria-label="(beacon-popover-mark-read)"', $html );
	}

	public function testReadRowHasAMarkUnreadForm(): void {
		$html = $this->newRenderer()->render( $this->newNotification( [ 'read' => '20261108190000' ] ) );

		$this->assertStringContainsString( 'class="beacon-item"', $html );
		$this->assertStringContainsString( 'value="markunread" name="beaconaction"', $html );
		$this->assertStringContainsString( 'aria-label="(beacon-popover-mark-unread)"', $html );
	}

	public function testLinksAreRendered(): void {
		$html = $this->newRenderer()->render( $this->newNotification( [
			'links' => [ new NotificationLink( 'View changes', 'https://foo.example.com/diff' ) ],
		] ) );

		$this->assertStringContainsString(
			'<a class="beacon-item__link" href="https://foo.example.com/diff">View changes</a>',
			$html
		);
	}

	public function testCustomIconIsAMaskAndCannotBreakOutOfTheUrl(): void {
		$html = $this->newRenderer()->render( $this->newNotification( [
			'icon' => 'https://foo.example.com/load.php?image="x");background:red',
		] ) );

		$this->assertStringContainsString(
			'mask-image: url(&quot;https://foo.example.com/load.php?image=%22x%22);background:red&quot;)',
			$html
		);
	}

	public function testAnythingElseFallsBackToTheDefaultIcon(): void {
		$html = $this->newRenderer()->render( $this->newNotification( [ 'icon' => 'javascript:alert(1)' ] ) );

		$this->assertStringContainsString( '<span class="beacon-icon"></span>', $html );
	}
}
