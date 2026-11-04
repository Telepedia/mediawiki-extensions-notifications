<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationSender;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\NotificationTypeRegistry;
use Telepedia\Extensions\Beacon\Presentation\NotificationContext;
use Telepedia\Extensions\Beacon\Presentation\NotificationPresenter;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatter;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatterFactory;
use Telepedia\Extensions\Beacon\Presentation\RenderedNotification;
use Wikimedia\Message\ITextFormatter;

/**
 * @covers \Telepedia\Extensions\Beacon\NotificationSender
 */
class NotificationSenderTest extends MediaWikiUnitTestCase {

	/**
	 * @var array what the sender passed to the store
	 */
	private array $stored = [];

	private function newSender(): NotificationSender {
		$presenter = $this->createMock( NotificationPresenter::class );
		$presenter->method( 'present' )->willReturnCallback(
			static fn ( RecipientFormatter $formatter, NotificationContext $context ) => new RenderedNotification(
				language: $formatter->language,
				header: "Header in {$formatter->language}",
				url: 'https://foo.example.com/wiki/Some_Page',
				icon: 'revert',
				body: 'Body',
				links: [ new NotificationLink( 'View', 'https://foo.example.com/wiki/Some_Page' ) ],
			)
		);

		$types = $this->createMock( NotificationTypeRegistry::class );
		$types->method( 'getPresenter' )->with( 'reverted' )->willReturn( $presenter );
		$types->method( 'getCategory' )->with( 'reverted' )->willReturn( 'reverted-category' );

		// John Appleseed reads in French, everyone else in English
		$formatterFactory = $this->createMock( RecipientFormatterFactory::class );
		$formatterFactory->method( 'newForUser' )->willReturnCallback(
			fn ( $user ) => new RecipientFormatter(
				$user->getName() === 'John Appleseed' ? 'fr' : 'en',
				$this->createMock( ITextFormatter::class )
			)
		);

		$store = $this->createMock( NotificationStore::class );
		$store->method( 'store' )->willReturnCallback( function ( Notification $notification ) {
			$this->stored[] = $notification;
			return count( $this->stored );
		} );

		return new NotificationSender( $types, $formatterFactory, $store, new SourceWiki( 'foowiki', 'Foo Wiki' ) );
	}

	public function testStoresOneRenderedNotificationPerRecipient(): void {
		$agent = new UserIdentityValue( 2, 'Reverter' );
		$title = $this->createMock( Title::class );

		$sent = $this->newSender()->send(
			'reverted',
			[ new UserIdentityValue( 1, 'John Appleseed' ), new UserIdentityValue( 3, 'Jane Doe' ) ],
			new NotificationContext( agent: $agent, title: $title )
		);

		$this->assertSame( 2, $sent );
		$this->assertSame( [ 1, 3 ], array_map( static fn ( $n ) => $n->recipient->getId(), $this->stored ) );
		
		$this->assertSame( [ 'fr', 'en' ], array_map( static fn ( $n ) => $n->language, $this->stored ) );
		$this->assertSame( 'Header in fr', $this->stored[0]->header );

		$n = $this->stored[1];
		$this->assertSame( 'reverted', $n->type );
		$this->assertSame( 'reverted-category', $n->category );
		$this->assertSame( 'foowiki', $n->wiki->id );
		$this->assertSame( $agent, $n->agent );
		$this->assertSame( $title, $n->page );
		$this->assertSame( 'Body', $n->body );
		$this->assertSame( 'revert', $n->icon );
		$this->assertCount( 1, $n->links );
	}

	public function testNoRecipientsSendsNothing(): void {
		$this->assertSame( 0, $this->newSender()->send( 'reverted', [], new NotificationContext() ) );
		$this->assertSame( [], $this->stored );
	}
}
