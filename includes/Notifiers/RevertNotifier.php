<?php

namespace Telepedia\Extensions\Beacon\Notifiers;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\Presentation\RevertedPresenter;

class RevertNotifier {

	/**
	 * The most authors one undo or rollback will notify
	 * @var int
	 */
	public const int MAX_REVERTED_AUTHORS = 50;

	public function __construct(
		private readonly RevisionStore $revisionStore,
		private readonly TitleFactory $titleFactory,
		private readonly RevertedPresenter $presenter,
		private readonly NotificationStore $store,
		private readonly SourceWiki $wiki,
	) {}

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

		$title = $this->titleFactory->newFromPageIdentity( $page );
		$diffUrl = $title->getFullURL( [ 'diff' => $revertRevision->getId() ] );
		$summary = $revertRevision->getComment()?->text;

		$sent = 0;
		foreach ( $authors as $author ) {
			if ( !$author->isRegistered() || $author->equals( $reverter ) ) {
				continue;
			}

			$rendered = $this->presenter->present( $author, $reverter, $title, $method, $diffUrl, $summary );

			$this->store->store( new Notification(
				recipient: $author,
				wiki: $this->wiki,
				type: 'reverted',
				category: 'reverted',
				url: $rendered->url,
				header: $rendered->header,
				language: $rendered->language,
				icon: $rendered->icon,
				body: $rendered->body,
				agent: $reverter,
				page: $title,
				links: $rendered->links,
			) );
			$sent++;
		}

		return $sent;
	}
}
