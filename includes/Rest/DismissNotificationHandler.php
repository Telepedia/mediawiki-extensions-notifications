<?php

namespace Telepedia\Extensions\Beacon\Rest;

use MediaWiki\ParamValidator\TypeDef\ArrayDef;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\User\UserIdentityUtils;
use Telepedia\Extensions\Beacon\NotificationStore;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class DismissNotificationHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	public function __construct(
		private readonly NotificationStore $notificationStore,
		private readonly UserIdentityUtils $identityUtils
	) {
	}

	/**
	 * Mark the given notifications as read
	 * @return Response the user's unread count afterwards read from the primary to avoid replication
	 * lag
	 */
	public function run(): Response {
		$user = $this->getAuthority()->getUser();

		if ( !$this->identityUtils->isNamed( $user ) ) {
			throw new LocalizedHttpException( new MessageValue( 'exception-nologin-text' ), 401 );
		}

		$body = $this->getValidatedBody();
		$ids = $body['ids'];

		$this->notificationStore->markRead( $user, $ids );
		$unreadCount = $this->notificationStore->getUnreadCount( $user, true );

		return $this->getResponseFactory()->createJson( [ 'unreadCount' => $unreadCount ] );
	}

	/**
	 * @inheritDoc
	 */
	public function needsWriteAccess(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function validate( Validator $restValidator ): void {
		parent::validate( $restValidator );
		$this->validateToken();
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		return [
			'ids' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				ArrayDef::PARAM_SCHEMA => [
					'type' => 'array',
					'items' => [ 'type' => 'integer', 'minimum' => 1 ],
					'minItems' => 1,
					'maxItems' => 100,
				],
			]
		] + $this->getTokenParamDefinition();
	}
}
