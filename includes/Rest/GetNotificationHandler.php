<?php

namespace Telepedia\Extensions\Beacon\Rest;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserIdentityUtils;
use Telepedia\Extensions\Beacon\NotificationStore;

class GetNotificationHandler extends SimpleHandler {

	public function __construct(
		private readonly NotificationStore $notificationStore,
		private readonly UserIdentityUtils $identityUtils
	) {}

	public function run(): Response {
		$user = $this->getAuthority()->getUser();

		if ( !$this->identityUtils->isNamed( $user ) ) {
			return $this->getResponseFactory()->createHttpError(
				403,
				// maybe need a different name for this but alas
				[ 'error' => wfMessage( 'beacon-error-named-only' )->text() ]
			);
		}

		$notifications = $this->notificationStore->listForUser( $user );
		// we could call the cache here to get this number but it should be the same so we can avoid
		// work where it's not needed
		$notificationCount = count( $notifications );

		return $this->getResponseFactory()->createJson(
			[
				'notifications' => $notifications,
				'unreadCount' => $notificationCount
			],
		);
	}

	/**
	 * @inheritDoc
	 */
	public function needsWriteAccess(): bool {
		return false;
	}

}
