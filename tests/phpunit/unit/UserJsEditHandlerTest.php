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
use Telepedia\Extensions\Beacon\Handlers\UserJsEditHandler;
use Telepedia\Extensions\Beacon\NotificationSender;
use Telepedia\Extensions\Beacon\Presentation\NotificationContext;

/**
 * @covers \Telepedia\Extensions\Beacon\Handlers\UserJsEditHandler
 */
class UserJsEditHandlerTest extends MediaWikiUnitTestCase {

	private const string DIFF_URL = 'https://foo.example.com/wiki/User:Jane_Doe/common.js?diff=300';

	/**
	 * @var array[]
	 */
	private array $sent = [];

	/**
	 * Build a handler for an edit to a page owned by $owner
	 * @param bool $isUserJs whether the page is a user JavaScript page
	 * @param UserIdentityValue|null $owner the page owner, or null if they don't exist
	 * @param bool $ownerIsNamed false for temporary accounts
	 * @return UserJsEditHandler
	 */
	private function newHandler(
		bool $isUserJs = true,
		?UserIdentityValue $owner = null,
		bool $ownerIsNamed = true
	): UserJsEditHandler {
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

		$sender = $this->createMock( NotificationSender::class );
		$sender->method( 'send' )->willReturnCallback(
			function ( string $type, iterable $recipients, NotificationContext $context ) {
				$recipients = [ ...$recipients ];
				$this->sent[] = [ $type, $recipients, $context ];
				return count( $recipients );
			}
		);

		return new UserJsEditHandler( $titleFactory, $userIdentityLookup, $userIdentityUtils, $sender );
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

		$this->assertTrue( $this->newHandler( true, $owner )->notify( $this->newRevision(), $editor ) );

		$this->assertCount( 1, $this->sent );
		[ $type, $recipients, $context ] = $this->sent[0];
		$this->assertSame( 'edited-other-users-js', $type );
		$this->assertSame( [ $owner ], $recipients );
		$this->assertSame( $editor, $context->agent );
		$this->assertSame( self::DIFF_URL, $context->extra['diffUrl'] );
	}

	public function testIgnoresPagesThatAreNotUserJs(): void {
		$handler = $this->newHandler( false, new UserIdentityValue( 3, 'Jane Doe' ) );

		$this->assertFalse( $handler->notify( $this->newRevision(), new UserIdentityValue( 1, 'John Appleseed' ) ) );
		$this->assertSame( [], $this->sent );
	}

	public function testIgnoresOwnersThatDoNotExist(): void {
		$handler = $this->newHandler( true, null );

		$this->assertFalse( $handler->notify( $this->newRevision(), new UserIdentityValue( 1, 'John Appleseed' ) ) );
		$this->assertSame( [], $this->sent );
	}

	public function testIgnoresTemporaryAccountOwners(): void {
		$handler = $this->newHandler( true, new UserIdentityValue( 3, '~2026-1' ), false );

		$this->assertFalse( $handler->notify( $this->newRevision(), new UserIdentityValue( 1, 'John Appleseed' ) ) );
		$this->assertSame( [], $this->sent );
	}

	public function testIgnoresOwnersEditingTheirOwnJs(): void {
		$owner = new UserIdentityValue( 3, 'Jane Doe' );

		$this->assertFalse( $this->newHandler( true, $owner )->notify( $this->newRevision(), $owner ) );
		$this->assertSame( [], $this->sent );
	}
}
