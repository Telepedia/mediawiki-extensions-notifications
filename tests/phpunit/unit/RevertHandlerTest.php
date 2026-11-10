<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Telepedia\Extensions\Beacon\Handlers\RevertHandler;
use Telepedia\Extensions\Beacon\NotificationSender;
use Telepedia\Extensions\Beacon\Presentation\NotificationContext;

/**
 * @covers \Telepedia\Extensions\Beacon\Handlers\RevertHandler
 */
class RevertHandlerTest extends MediaWikiUnitTestCase {

	private const string DIFF_URL = 'https://foo.example.com/wiki/Placeholder_Page?diff=203';

	/**
	 * @var array
	 */
	private array $sent = [];

	/**
	 * Build a handler whose revert range was written by $authors
	 * @param UserIdentityValue[] $authors
	 * @param bool $revisionsExist whether the reverted revisions can be loaded
	 * @return RevertHandler
	 */
	private function newHandler(
		array $authors,
		bool $revisionsExist = true,
		?string $formattedSummary = null
	): RevertHandler {
		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->method( 'getRevisionById' )
			->willReturn( $revisionsExist ? $this->createMock( RevisionRecord::class ) : null );
		$revisionStore->method( 'getAuthorsBetween' )->willReturn( $authors );

		$title = $this->createMock( Title::class );
		$title->method( 'getFullURL' )->willReturn( self::DIFF_URL );
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromPageIdentity' )->willReturn( $title );

		$sender = $this->createMock( NotificationSender::class );
		$sender->method( 'send' )->willReturnCallback(
			function ( string $type, iterable $recipients, NotificationContext $context ) {
				$recipients = [ ...$recipients ];
				$this->sent[] = [ $type, $recipients, $context ];
				return count( $recipients );
			}
		);

		$commentFormatter = $this->createMock( CommentFormatter::class );
		if ( $formattedSummary === null ) {
			$commentFormatter->method( 'format' )->willReturnArgument( 0 );
		} else {
			$commentFormatter->method( 'format' )->willReturn( $formattedSummary );
		}

		return new RevertHandler( $revisionStore, $titleFactory, $commentFormatter, $sender );
	}

	private function newRevertRevision( ?string $summary = null ): RevisionRecord {
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getPage' )->willReturn( new PageIdentityValue( 666, 0, 'Some_Page', false ) );
		$revision->method( 'getId' )->willReturn( 203 );
		$revision->method( 'getComment' )->willReturn(
			$summary === null ? null : CommentStoreComment::newUnsavedComment( $summary )
		);
		return $revision;
	}

	public function testNotifiesRegisteredAuthorsExceptTheReverter(): void {
		$reverter = new UserIdentityValue( 2, 'Reverter' );
		$johnAppleseed = new UserIdentityValue( 1, 'John Appleseed' );
		$janeDoe = new UserIdentityValue( 3, 'Jane Doe' );
		$handler = $this->newHandler( [
			$johnAppleseed,
			new UserIdentityValue( 0, '127.0.0.1' ),
			$janeDoe,
			$reverter,
		] );

		$sent = $handler->notify( $this->newRevertRevision(), $reverter, 201, 202, 'undo' );

		$this->assertSame( 2, $sent );
		$this->assertCount( 1, $this->sent );
		[ $type, $recipients ] = $this->sent[0];
		$this->assertSame( 'reverted', $type );
		$this->assertSame( [ $johnAppleseed, $janeDoe ], array_values( $recipients ) );
	}

	public function testNoNotificationsWhenRevisionsAreMissing(): void {
		$handler = $this->newHandler( [ new UserIdentityValue( 1, 'John Appleseed' ) ], false );

		$sent = $handler->notify( $this->newRevertRevision(), new UserIdentityValue( 2, 'Reverter' ), 201, 202, 'undo' );

		$this->assertSame( 0, $sent );
		$this->assertSame( [], $this->sent );
	}

	public function testNoNotificationsWhenOnlyTheReverterIsReverted(): void {
		$reverter = new UserIdentityValue( 2, 'Reverter' );
		$handler = $this->newHandler( [ $reverter ] );

		$this->assertSame( 0, $handler->notify( $this->newRevertRevision(), $reverter, 201, 202, 'rollback' ) );
		$this->assertSame( [], $this->sent );
	}

	public function testPassesTheRevertDetailsToThePresenter(): void {
		$reverter = new UserIdentityValue( 2, 'Reverter' );
		$handler = $this->newHandler( [ new UserIdentityValue( 1, 'John Appleseed' ) ] );

		$handler->notify( $this->newRevertRevision( 'rv vandalism' ), $reverter, 201, 201, 'rollback' );

		/** @var NotificationContext $context */
		$context = $this->sent[0][2];
		$this->assertSame( $reverter, $context->agent );
		$this->assertSame( 'rollback', $context->extra['method'] );
		$this->assertSame( self::DIFF_URL, $context->extra['diffUrl'] );
		$this->assertSame( 'rv vandalism', $context->extra['summary'] );
	}

	public function testSummaryIsStrippedToPlainText(): void {
		$handler = $this->newHandler(
			[ new UserIdentityValue( 1, 'John Appleseed' ) ],
			true,
			'Undid revision <a href="/wiki/Special:Diff/6767" title="Special:Diff/6767">6767</a> by ' .
				'<a href="/wiki/User:Foo" title="User:Foo">Foo &amp; Bar</a>'
		);

		$handler->notify(
			$this->newRevertRevision( 'Undid revision [[Special:Diff/6767|6767]] by [[User:Foo|Foo & Bar]]' ),
			new UserIdentityValue( 2, 'Reverter' ),
			201,
			201,
			'undo'
		);

		$this->assertSame( 'Undid revision 6767 by Foo & Bar', $this->sent[0][2]->extra['summary'] );
	}

	public function testMissingSummaryStaysNull(): void {
		$handler = $this->newHandler( [ new UserIdentityValue( 1, 'John Appleseed' ) ] );

		$handler->notify( $this->newRevertRevision(), new UserIdentityValue( 2, 'Reverter' ), 201, 201, 'undo' );

		$this->assertNull( $this->sent[0][2]->extra['summary'] );
	}
}
