<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\Notifiers\RevertNotifier;
use Telepedia\Extensions\Beacon\Presentation\RenderedNotification;
use Telepedia\Extensions\Beacon\Presentation\RevertedPresenter;

/**
 * @covers \Telepedia\Extensions\Beacon\Notifiers\RevertNotifier
 */
class RevertNotifierTest extends MediaWikiUnitTestCase {

	private const string DIFF_URL = 'https://foo.example.com/wiki/Placeholder_Page?diff=203';

	/**
	 * @var Notification[] what the notifier passed to the store
	 */
	private array $stored = [];

	/**
	 * Build a notifier whose reverts were written by $authors
	 * @param UserIdentityValue[] $authors
	 * @param bool $revisionsExist whether the reverted revisions can be loaded
	 * @return RevertNotifier
	 */
	private function newNotifier( array $authors, bool $revisionsExist = true ): RevertNotifier {
		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->method( 'getRevisionById' )
			->willReturn( $revisionsExist ? $this->createMock( RevisionRecord::class ) : null );
		$revisionStore->method( 'getAuthorsBetween' )->willReturn( $authors );

		$title = $this->createMock( Title::class );
		$title->method( 'getFullURL' )->willReturn( self::DIFF_URL );
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromPageIdentity' )->willReturn( $title );

		$presenter = $this->createMock( RevertedPresenter::class );
		$presenter->method( 'present' )->willReturnCallback(
			static fn ( $recipient ) => new RenderedNotification(
				language: 'en',
				header: 'Header for ' . $recipient->getName(),
				url: self::DIFF_URL,
				icon: 'revert',
				links: [ new NotificationLink( 'View changes', self::DIFF_URL ) ],
			)
		);

		$store = $this->createMock( NotificationStore::class );
		$store->method( 'store' )->willReturnCallback( function ( Notification $notification ) {
			$this->stored[] = $notification;
			return count( $this->stored );
		} );

		return new RevertNotifier(
			$revisionStore,
			$titleFactory,
			$presenter,
			$store,
			new SourceWiki( 'foowiki', 'Foo Wiki' )
		);
	}

	private function newRevertRevision(): RevisionRecord {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPage' )->willReturn( new PageIdentityValue( 666, 0, 'Some_Page', false ) );
		$revision->method( 'getId' )->willReturn( 203 );
		$revision->method( 'getComment' )->willReturn( null );
		return $revision;
	}

	public function testNotifiesRegisteredAuthorsExceptTheReverter(): void {
		$reverter = new UserIdentityValue( 2, 'Reverter' );
		$notifier = $this->newNotifier( [
			new UserIdentityValue( 1, 'John Appleseed' ),
			new UserIdentityValue( 0, '127.0.0.1' ),
			new UserIdentityValue( 3, 'Jane Doe' ),
			$reverter,
		] );

		$sent = $notifier->notify( $this->newRevertRevision(), $reverter, 201, 202, 'undo' );

		$this->assertSame( 2, $sent );
		$this->assertSame(
			[ 1, 3 ],
			array_map( static fn ( Notification $n ) => $n->recipient->getId(), $this->stored )
		);
	}

	public function testNoNotificationsWhenRevisionsAreMissing(): void {
		$notifier = $this->newNotifier( [ new UserIdentityValue( 1, 'John Appleseed' ) ], false );

		$sent = $notifier->notify( $this->newRevertRevision(), new UserIdentityValue( 2, 'Reverter' ), 201, 202, 'undo' );

		$this->assertSame( 0, $sent );
		$this->assertSame( [], $this->stored );
	}

	public function testNoNotificationsWhenOnlyTheReverterIsReverted(): void {
		$reverter = new UserIdentityValue( 2, 'Reverter' );
		$notifier = $this->newNotifier( [ $reverter ] );

		$this->assertSame( 0, $notifier->notify( $this->newRevertRevision(), $reverter, 201, 202, 'rollback' ) );
		$this->assertSame( [], $this->stored );
	}

	public function testBuildsTheNotificationFromThePresenter(): void {
		$reverter = new UserIdentityValue( 2, 'Reverter' );
		$notifier = $this->newNotifier( [ new UserIdentityValue( 1, 'John Appleseed' ) ] );

		$notifier->notify( $this->newRevertRevision(), $reverter, 201, 201, 'undo' );

		$this->assertCount( 1, $this->stored );
		$n = $this->stored[0];
		$this->assertSame( 'reverted', $n->type );
		$this->assertSame( 'reverted', $n->category );
		$this->assertSame( 'foowiki', $n->wiki->id );
		$this->assertSame( 'Header for John Appleseed', $n->header );
		$this->assertSame( self::DIFF_URL, $n->url );
		$this->assertSame( 'en', $n->language );
		$this->assertSame( 'revert', $n->icon );
		$this->assertSame( $reverter, $n->agent );
		$this->assertCount( 1, $n->links );
		$this->assertNull( $n->id );
		$this->assertNull( $n->created );
		$this->assertNull( $n->read );
	}
}
