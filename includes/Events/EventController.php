<?php

namespace Telepedia\Extensions\Beacon\Events;

use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\WikiMap\WikiMap;
use Telepedia\Extensions\Beacon\Jobs\BeaconHidePageNotificationsJob;

readonly class EventController {

	public function __construct(
		private JobQueueGroup $jobQueueGroup,
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
}
