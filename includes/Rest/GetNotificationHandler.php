<?php

namespace Telepedia\Extensions\Beacon\Rest;

use JsonException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserIdentityUtils;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Telepedia\Extensions\Beacon\NotificationStore;
use Wikimedia\Timestamp\TimestampFormat;

class GetNotificationHandler extends SimpleHandler {

	public function __construct(
		private readonly NotificationStore $notificationStore,
		private readonly UserIdentityUtils $identityUtils
	) {}

	/**
	 * @throws JsonException
	 */
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
				'notifications' => array_map( $this->serialize( ... ), $notifications ),
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

	/**
	 * json_encode can only serialise public properties, but since UserIdentityValue and PageIdentityValue
	 * have private properties with getters and setters, json_encode just writes {} so we need to manually build it
	 * @param Notification $n
	 * @return array
	 */
	private function serialize( Notification $n ): array {
		return [
			'id' => $n->id,
			'type' => $n->type,
			'category' => $n->category,
			'wiki' => [ 'id' => $n->wiki->id, 'name' => $n->wiki->name ],
			'url' => $n->url,
			'header' => $n->header,
			'body' => $n->body,
			'icon' => $n->icon,
			'language' => $n->language,
			'agent' => $n->agent ? [
				'id' => $n->agent->getId(),
				'name' => $n->agent->getName(),
			] : null,
			'page' => $n->page ? [
				'id' => $n->page->getId( $n->page->getWikiId() ),
				'namespace' => $n->page->getNamespace(),
				'title' => $n->page->getDBkey( $n->page->getWikiId() ),
			] : null,
			'links' => array_map( static fn ( NotificationLink $l ) => $l->toArray(), $n->links ),
			'created' => wfTimestamp( TimestampFormat::ISO_8601, $n->created ),
			'read' => $n->read !== null ? wfTimestamp( TimestampFormat::ISO_8601, $n->read ) : null,
		];
	}

}
