<?php

namespace Telepedia\Extensions\Beacon\Events;

use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\Jobs\BeaconHidePageNotificationsJob;
use Telepedia\Extensions\Beacon\NotificationStore;
use Wikimedia\Message\IMessageFormatterFactory;
use Wikimedia\Message\MessageValue;

readonly class EventController {

	/**
	 * The most authors one undo or rollback will notify
	 * @var int
	 */
	private const int MAX_REVERTED_AUTHORS = 50;

	public function __construct(
		private JobQueueGroup $jobQueueGroup,
		private NotificationStore $store,
		private RevisionStore $revisionStore,
		private TitleFactory $titleFactory,
		private UserOptionsLookup $userOptionsLookup,
		private IMessageFormatterFactory $messageFormatterFactory,
		private Config $config,
	) {}

	/**
	 * When a page is deleted, hide all of the notifications for that page
	 * @param int $pageId
	 * @return void
	 */
	public function onPageDeleted( int $pageId ): void {
		$this->queueSetHidden( $pageId, true );
	}

	/**
	 * When a page is undeleted, restore the notifications hidden when it was deleted
	 * @param int $pageId
	 * @return void
	 */
	public function onPageUndeleted( int $pageId ): void {
		$this->queueSetHidden( $pageId, false );
	}

	/**
	 * Do the work to set the notifications to hidden in a job, since it may affect hundreds of people
	 * @param int $pageId
	 * @param bool $hidden
	 * @return void
	 */
	private function queueSetHidden( int $pageId, bool $hidden ): void {
		$this->jobQueueGroup->lazyPush( new JobSpecification(
			BeaconHidePageNotificationsJob::JOB_NAME,
			[
				'wiki' => WikiMap::getCurrentWikiId(),
				'pageId' => $pageId,
				'hidden' => $hidden,
			],
			[ 'removeDuplicates' => true ]
		) );
	}

	/**
	 * Notify an editor that their edit was reverted (handles both rollback and undo)
	 * @param RevisionRecord $revertRevision the new revision created by the undo or rollback
	 * @param UserIdentity $reverter who did the undo or rollback
	 * @param int $oldestRevertedId the oldest revision that was reverted
	 * @param int $newestRevertedId the newest revision that was reverted
	 * @param string $method 'undo' or 'rollback'
	 * @return void
	 */
	public function onRevert(
		RevisionRecord $revertRevision,
		UserIdentity $reverter,
		int $oldestRevertedId,
		int $newestRevertedId,
		string $method
	): void {
		$page = $revertRevision->getPage();
		$oldest = $this->revisionStore->getRevisionById( $oldestRevertedId );
		$newest = $this->revisionStore->getRevisionById( $newestRevertedId );
		if ( !$oldest || !$newest ) {
			return;
		}

		$authors = $this->revisionStore->getAuthorsBetween(
			$page->getId(),
			$oldest,
			$newest,
			null,
			self::MAX_REVERTED_AUTHORS,
			RevisionStore::INCLUDE_BOTH
		);

		$title = $this->titleFactory->newFromPageIdentity( $page );
		$wiki = SourceWiki::newFromConfig( $this->config );
		$diffUrl = $title->getFullURL( [ 'diff' => $revertRevision->getId() ] );


		$summary = $revertRevision->getComment()?->text;

		foreach ( $authors as $author ) {

			if ( !$author->isRegistered() || $author->equals( $reverter ) ) {
				continue;
			}

			// we render this in the users language if the message exists, based on the language they
			// had selected on the wiki the notification originates from
			$language = $this->userOptionsLookup->getOption( $author, 'language' );
			$formatter = $this->messageFormatterFactory->getTextFormatter( $language );

			$header = $formatter->format(
				MessageValue::new( "beacon-notification-reverted-$method" )
					->plaintextParams( $reverter->getName(), $title->getPrefixedText() )
			);

			$this->store->store( new Notification(
				recipient: $author,
				wiki: $wiki,
				type: 'reverted',
				category: 'reverted',
				url: $diffUrl,
				header: $header,
				language: $language,
				icon: 'revert',
				body: $summary !== null && $summary !== '' ? $summary : null,
				agent: $reverter,
				page: $title,
				links: [
					new NotificationLink(
						$formatter->format( MessageValue::new( 'beacon-notification-link-view-changes' ) ),
						$diffUrl,
						'changes'
					),
					new NotificationLink(
						$formatter->format( MessageValue::new( 'beacon-notification-link-page' ) ),
						$title->getFullURL(),
						'article'
					),
				],
			) );
		}
	}
}
