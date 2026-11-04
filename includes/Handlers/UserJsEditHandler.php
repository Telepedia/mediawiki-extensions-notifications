<?php

namespace Telepedia\Extensions\Beacon\Handlers;

use MediaWiki\DomainEvent\DomainEventIngress;
use MediaWiki\Page\Event\PageLatestRevisionChangedEvent;
use MediaWiki\Page\Event\PageLatestRevisionChangedListener;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityUtils;
use Telepedia\Extensions\Beacon\NotificationSender;
use Telepedia\Extensions\Beacon\Presentation\NotificationContext;

class UserJsEditHandler extends DomainEventIngress implements PageLatestRevisionChangedListener {

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly NotificationSender $sender,
	) {}

	public function handlePageLatestRevisionChangedEvent( PageLatestRevisionChangedEvent $event ): void {
		$editResult = $event->getEditResult();
		if ( $editResult === null || $editResult->isNullEdit() ) {
			return;
		}

		$this->notify( $event->getLatestRevisionAfter(), $event->getAuthor() );
	}

	/**
	 * @param RevisionRecord $revision the revision the edit created
	 * @param UserIdentity $author who made the edit
	 * @return bool whether a notification was sent
	 */
	public function notify( RevisionRecord $revision, UserIdentity $author ): bool {
		$title = $this->titleFactory->newFromPageIdentity( $revision->getPage() );

		if ( !$title->isUserJsConfigPage() ) {
			return false;
		}

		$owner = $this->userIdentityLookup->getUserIdentityByName( $title->getRootText() );

		// skip anons and editing your own javascript
		if ( !$owner || !$this->userIdentityUtils->isNamed( $owner ) || $owner->equals( $author ) ) {
			return false;
		}

		return $this->sender->send( 'edited-other-users-js', [ $owner ], new NotificationContext(
			agent: $author,
			title: $title,
			extra: [
				'diffUrl' => $title->getFullURL( [ 'diff' => $revision->getId() ] ),
			],
		) ) > 0;
	}
}
