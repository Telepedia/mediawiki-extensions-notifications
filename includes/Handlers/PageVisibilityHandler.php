<?php

namespace Telepedia\Extensions\Beacon\Handlers;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Page\Event\PageDeletedEvent;
use MediaWiki\Page\Event\PageDeletedListener;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\Storage\PageUpdateCauses;
use MediaWiki\WikiMap\WikiMap;
use Telepedia\Extensions\Beacon\Jobs\BeaconHidePageNotificationsJob;

class PageVisibilityHandler extends DomainEventIngress implements
	PageDeletedListener,
	PageLatestRevisionChangedListener
{

	public function __construct(
		private readonly JobQueueGroup $jobQueueGroup,
	) {}

	public function handlePageDeletedEvent( PageDeletedEvent $event ): void {
		$this->queueSetHidden( $event->getPageId(), true );
	}

	public function handlePageLatestRevisionChangedEvent( PageLatestRevisionChangedEvent $event ): void {
		// the WMF didn't add an undelete event for some reason?? so check that the revision change
		// was due to an undeletion
		if ( $event->hasCause( PageUpdateCauses::CAUSE_UNDELETE ) ) {
			$this->queueSetHidden( $event->getPageId(), false );
		}
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
