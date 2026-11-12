<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationCategoryRegistry;
use Telepedia\Extensions\Beacon\NotificationIconRegistry;
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

	/**
	 * @param array $optedOut usernames of users who turned the category off
	 * @param array $categories the registered categories
	 */
	private function newSender(
		array $optedOut = [],
		array $categories = [ 'reverted-category' => [] ]
	): NotificationSender {
		$presenter = $this->createMock( NotificationPresenter::class );
		$presenter->method( 'present' )->willReturnCallback(
			static fn ( RecipientFormatter $formatter, NotificationContext $context ) => new RenderedNotification(
				language: $formatter->language,
				header: "Header in {$formatter->language}",
				url: 'https://foo.example.com/wiki/Some_Page',
				icon: 'an-icon',
				body: 'Body',
				links: [
					new NotificationLink( 'View changes', 'https://foo.example.com/wiki/Some_Page?diff=2', 'cdxIconDiffs' ),
					new NotificationLink( 'View page', 'https://foo.example.com/wiki/Some_Page' ),
				],
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

		$icons = $this->createMock( NotificationIconRegistry::class );
		$icons->method( 'resolve' )->willReturnCallback(
			static fn ( string $name, string $language ) => "https://foo.example.com/icons/$name-$language.svg"
		);

		$userOptions = $this->createMock( UserOptionsLookup::class );
		$userOptions->method( 'getBoolOption' )->willReturnCallback(
			static fn ( $user, string $option ) => $option === 'beacon-web-reverted-category'
				&& !in_array( $user->getName(), $optedOut, true )
		);

		return new NotificationSender(
			$types,
			$formatterFactory,
			$store,
			new SourceWiki( 'foowiki', 'Foo Wiki' ),
			$icons,
			new NotificationCategoryRegistry( $categories ),
			$userOptions
		);
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
		$this->assertCount( 2, $n->links );
	}

	public function testIconsAreResolvedInTheRecipientsLanguage(): void {
		$this->newSender()->send(
			'reverted',
			[ new UserIdentityValue( 1, 'John Appleseed' ), new UserIdentityValue( 3, 'Jane Doe' ) ],
			new NotificationContext()
		);

		[ $john, $jane ] = $this->stored;
		$this->assertSame( 'https://foo.example.com/icons/an-icon-fr.svg', $john->icon );
		$this->assertSame( 'https://foo.example.com/icons/an-icon-en.svg', $jane->icon );

		$this->assertSame( 'https://foo.example.com/icons/cdxIconDiffs-en.svg', $jane->links[0]->icon );
		$this->assertNull( $jane->links[1]->icon );
	}

	public function testNoRecipientsSendsNothing(): void {
		$this->assertSame( 0, $this->newSender()->send( 'reverted', [], new NotificationContext() ) );
		$this->assertSame( [], $this->stored );
	}

	public function testRecipientsWhoTurnedTheCategoryOffAreSkipped(): void {
		$sent = $this->newSender( [ 'John Appleseed' ] )->send(
			'reverted',
			[ new UserIdentityValue( 1, 'John Appleseed' ), new UserIdentityValue( 3, 'Jane Doe' ) ],
			new NotificationContext()
		);

		$this->assertSame( 1, $sent );
		$this->assertSame( [ 3 ], array_map( static fn ( $n ) => $n->recipient->getId(), $this->stored ) );
	}

	public function testAnUnregisteredCategoryIsAlwaysSent(): void {
		$sent = $this->newSender( [ 'John Appleseed' ], [] )->send(
			'reverted',
			[ new UserIdentityValue( 1, 'John Appleseed' ) ],
			new NotificationContext()
		);

		$this->assertSame( 1, $sent );
	}
}
