<?php

namespace Telepedia\Extensions\Beacon\Handlers;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Storage\EditResult;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use Telepedia\Extensions\Beacon\NotificationSender;
use Telepedia\Extensions\Beacon\Presentation\NotificationContext;

class RevertHandler extends DomainEventIngress implements PageLatestRevisionChangedListener {

	/**
	 * The most authors one undo or rollback will notify
	 * @var int
	 */
	public const int MAX_REVERTED_AUTHORS = 50;

	public function __construct(
		private readonly RevisionStore $revisionStore,
		private readonly TitleFactory $titleFactory,
		private readonly NotificationSender $sender,
	) {
	}

	public function handlePageLatestRevisionChangedEvent( PageLatestRevisionChangedEvent $event ): void {
		$editResult = $event->getEditResult();
		if ( $editResult === null || $editResult->isNullEdit() ) {
			return;
		}

		$method = match ( $editResult->getRevertMethod() ) {
			EditResult::REVERT_UNDO => 'undo',
			EditResult::REVERT_ROLLBACK => 'rollback',
			default => null,
		};

		if ( $method !== null ) {
			$this->notify(
				$event->getLatestRevisionAfter(),
				$event->getAuthor(),
				$editResult->getOldestRevertedRevisionId(),
				$editResult->getNewestRevertedRevisionId(),
				$method
			);
		}
	}

	/**
	 * @param RevisionRecord $revertRevision the new revision created by the undo or rollback
	 * @param UserIdentity $reverter who did the undo or rollback
	 * @param int $oldestRevertedId the oldest revision that was reverted
	 * @param int $newestRevertedId the newest revision that was reverted
	 * @param string $method 'undo' or 'rollback'
	 * @return int the number of notifications sent
	 */
	public function notify(
		RevisionRecord $revertRevision,
		UserIdentity $reverter,
		int $oldestRevertedId,
		int $newestRevertedId,
		string $method
	): int {
		$page = $revertRevision->getPage();
		$oldest = $this->revisionStore->getRevisionById( $oldestRevertedId );
		$newest = $this->revisionStore->getRevisionById( $newestRevertedId );
		if ( !$oldest || !$newest ) {
			return 0;
		}

		$authors = $this->revisionStore->getAuthorsBetween(
			$page->getId(),
			$oldest,
			$newest,
			null,
			self::MAX_REVERTED_AUTHORS,
			RevisionStore::INCLUDE_BOTH
		);

		// unregistered users don't get notifications, and also exclude self-notifications
		$recipients = array_filter(
			$authors,
			static fn ( UserIdentity $author ) => $author->isRegistered() && !$author->equals( $reverter )
		);
		if ( !$recipients ) {
			return 0;
		}

		$title = $this->titleFactory->newFromPageIdentity( $page );

		return $this->sender->send( 'reverted', $recipients, new NotificationContext(
			agent: $reverter,
			title: $title,
			extra: [
				'method' => $method,
				'diffUrl' => $title->getFullURL( [ 'diff' => $revertRevision->getId() ] ),
				'summary' => $revertRevision->getComment()?->text,
			],
		) );
	}
}
