<?php

namespace Telepedia\Extensions\Beacon\Events;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageDeletedEvent;
use MediaWiki\Page\Event\PageDeletedListener;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\Storage\PageUpdateCauses;

class PageEventIngress extends DomainEventIngress implements
	PageDeletedListener,
	PageLatestRevisionChangedListener
{

	public function __construct(
		private readonly EventController $controller
	) {}

	/**
	 * Hide all notifications which relate to a specific page when that page is deleted
	 * @param PageDeletedEvent $event
	 * @return void
	 */
	public function handlePageDeletedEvent( PageDeletedEvent $event ): void {
		$this->controller->onPageDeleted( $event->getPageId() );
	}

	/**
	 * Restore notifications for undeleted pages
	 * @param PageLatestRevisionChangedEvent $event
	 * @return void
	 */
	public function handlePageLatestRevisionChangedEvent( PageLatestRevisionChangedEvent $event ): void {
		// the WMF didn't add an undelete event for some reason?? so check that the revision change
		// was due to an undeletion
		if ( $event->hasCause( PageUpdateCauses::CAUSE_UNDELETE ) ) {
			$this->controller->onPageUndeleted( $event->getPageId() );
		}
	}
}
