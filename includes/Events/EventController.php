<?php

namespace Telepedia\Extensions\Beacon\Events;

use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use Telepedia\Extensions\Beacon\Jobs\BeaconHidePageNotificationsJob;
use Telepedia\Extensions\Beacon\Notifiers\RevertNotifier;

readonly class EventController {

	public function __construct(
		private JobQueueGroup $jobQueueGroup,
		private RevertNotifier $revertNotifier,
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
		$this->revertNotifier->notify( $revertRevision, $reverter, $oldestRevertedId, $newestRevertedId, $method );
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
}
