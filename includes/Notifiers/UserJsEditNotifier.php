<?php

namespace Telepedia\Extensions\Beacon\Notifiers;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityUtils;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\NotificationStore;
use Telepedia\Extensions\Beacon\Presentation\UserJsEditPresenter;

class UserJsEditNotifier {

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly UserJsEditPresenter $presenter,
		private readonly NotificationStore $store,
		private readonly SourceWiki $wiki,
	) {}

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

		// skop anons and editing your own javascript
		if ( !$owner || !$this->userIdentityUtils->isNamed( $owner ) || $owner->equals( $author ) ) {
			return false;
		}

		$diffUrl = $title->getFullURL( [ 'diff' => $revision->getId() ] );
		$rendered = $this->presenter->present( $owner, $author, $title, $diffUrl );

		$this->store->store( new Notification(
			recipient: $owner,
			wiki: $this->wiki,
			type: 'edited-other-users-js',
			category: 'edited-other-users-js',
			url: $rendered->url,
			header: $rendered->header,
			language: $rendered->language,
			icon: $rendered->icon,
			body: $rendered->body,
			agent: $author,
			page: $title,
			links: $rendered->links,
		) );

		return true;
	}
}
