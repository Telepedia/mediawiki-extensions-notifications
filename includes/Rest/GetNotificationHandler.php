<?php

namespace Telepedia\Extensions\Beacon\Rest;

use JsonException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserIdentityUtils;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Telepedia\Extensions\Beacon\NotificationStore;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Timestamp\TimestampFormat;

class GetNotificationHandler extends SimpleHandler {

	/**
	 * How many notifications the popover returns
	 * @var int
	 */
	private const int DEFAULT_LIMIT = 25;

	/**
	 * The most that can be asked for at once
	 * @var int
	 */
	private const int MAX_LIMIT = 100;

	public function __construct(
		private readonly NotificationStore $notificationStore,
		private readonly UserIdentityUtils $identityUtils
	) {
	}

	/**
	 * @throws JsonException
	 */
	public function run(): Response {
		$user = $this->getAuthority()->getUser();

		if ( !$this->identityUtils->isNamed( $user ) ) {
			throw new LocalizedHttpException( new MessageValue( 'exception-nologin-text' ), 401 );
		}

		$params = $this->getValidatedParams();
		$limit = $params['limit'];

		// this is purposefully +1 than we actually want, so we can check if there are any more notifications
		// without actually having to continue
		$notifications = $this->notificationStore->listForUser( $user, $limit + 1, $params['continue'] );
		$continue = null;
		if ( count( $notifications ) > $limit ) {
			$notifications = array_slice( $notifications, 0, $limit );
			$continue = end( $notifications )->id;
		}

		return $this->getResponseFactory()->createJson(
			[
				'notifications' => array_map( $this->serialize( ... ), $notifications ),
				'unreadCount' => $this->notificationStore->getUnreadCount( $user ),
				'continue' => $continue,
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
	 * @inheritDoc
	 */
	public function getParamSettings(): array {
		return [
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => self::DEFAULT_LIMIT,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => self::MAX_LIMIT,
				IntegerDef::PARAM_MAX2 => self::MAX_LIMIT,
			],
			'continue' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				IntegerDef::PARAM_MIN => 1,
			],
		];
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
