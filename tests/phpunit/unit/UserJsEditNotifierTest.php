<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\Notifiers\UserJsEditNotifier;
use Telepedia\Extensions\Beacon\Presentation\RenderedNotification;
use Telepedia\Extensions\Beacon\Presentation\UserJsEditPresenter;

/**
 * @covers \Telepedia\Extensions\Beacon\Notifiers\UserJsEditNotifier
 */
class UserJsEditNotifierTest extends MediaWikiUnitTestCase {

	private const string DIFF_URL = 'https://foo.example.com/wiki/User:Jane_Doe/common.js?diff=300';

	/** @var Notification[] what the notifier passed to the store */
	private array $stored = [];

	/**
	 * Build a notifier for an edit to a page owned by $owner
	 * @param bool $isUserJs whether the page is a user JavaScript page
	 * @param UserIdentityValue|null $owner the page owner, or null if they don't exist
	 * @param bool $ownerIsNamed false for temporary accounts
	 * @return UserJsEditNotifier
	 */
	private function newNotifier(
		bool $isUserJs = true,
		?UserIdentityValue $owner = null,
		bool $ownerIsNamed = true
	): UserJsEditNotifier {
		$title = $this->createMock( Title::class );
		$title->method( 'isUserJsConfigPage' )->willReturn( $isUserJs );
		$title->method( 'getRootText' )->willReturn( $owner?->getName() ?? 'Nobody' );
		$title->method( 'getFullURL' )->willReturn( self::DIFF_URL );
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromPageIdentity' )->willReturn( $title );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByName' )->willReturn( $owner );

		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isNamed' )->willReturn( $ownerIsNamed );

		$presenter = $this->createMock( UserJsEditPresenter::class );
		$presenter->method( 'present' )->willReturn( new RenderedNotification(
			language: 'en',
			header: 'John Appleseed edited your JavaScript page User:Jane Doe/common.js.',
			url: self::DIFF_URL,
			icon: 'code',
		) );

		$store = $this->createMock( NotificationStore::class );
		$store->method( 'store' )->willReturnCallback( function ( Notification $notification ) {
			$this->stored[] = $notification;
			return count( $this->stored );
		} );

		return new UserJsEditNotifier(
			$titleFactory,
			$userIdentityLookup,
			$userIdentityUtils,
			$presenter,
			$store,
			new SourceWiki( 'foowiki', 'Foo Wiki' )
		);
	}

	private function newRevision(): RevisionRecord {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPage' )->willReturn( new PageIdentityValue( 600, 2, 'Jane Doe/common.js', false ) );
		$revision->method( 'getId' )->willReturn( 300 );
		return $revision;
	}

	public function testNotifiesTheOwnerWhenSomeoneElseEdits(): void {
		$owner = new UserIdentityValue( 3, 'Jane Doe' );
		$editor = new UserIdentityValue( 1, 'John Appleseed' );

		$this->assertTrue( $this->newNotifier( true, $owner )->notify( $this->newRevision(), $editor ) );

		$this->assertCount( 1, $this->stored );
		$n = $this->stored[0];
		$this->assertSame( $owner, $n->recipient );
		$this->assertSame( $editor, $n->agent );
		$this->assertSame( 'edited-other-users-js', $n->type );
		$this->assertSame( 'edited-other-users-js', $n->category );
		$this->assertSame( 'foowiki', $n->wiki->id );
		$this->assertSame( self::DIFF_URL, $n->url );
		$this->assertSame( 'code', $n->icon );
	}

	public function testIgnoresPagesThatAreNotUserJs(): void {
		$notifier = $this->newNotifier( false, new UserIdentityValue( 3, 'Jane Doe' ) );

		$this->assertFalse( $notifier->notify( $this->newRevision(), new UserIdentityValue( 1, 'John Appleseed' ) ) );
		$this->assertSame( [], $this->stored );
	}

	public function testIgnoresOwnersThatDoNotExist(): void {
		$notifier = $this->newNotifier( true, null );

		$this->assertFalse( $notifier->notify( $this->newRevision(), new UserIdentityValue( 1, 'John Appleseed' ) ) );
		$this->assertSame( [], $this->stored );
	}

	public function testIgnoresTemporaryAccountOwners(): void {
		$notifier = $this->newNotifier( true, new UserIdentityValue( 3, '~2026-1' ), false );

		$this->assertFalse( $notifier->notify( $this->newRevision(), new UserIdentityValue( 1, 'John Appleseed' ) ) );
		$this->assertSame( [], $this->stored );
	}

	public function testIgnoresOwnersEditingTheirOwnJs(): void {
		$owner = new UserIdentityValue( 3, 'Jane Doe' );

		$this->assertFalse( $this->newNotifier( true, $owner )->notify( $this->newRevision(), $owner ) );
		$this->assertSame( [], $this->stored );
	}
}
