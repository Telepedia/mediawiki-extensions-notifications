<?php

namespace Telepedia\Extensions\Beacon\Rest;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\User\UserIdentityUtils;
use Telepedia\Extensions\Beacon\NotificationStore;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class DismissAllHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	public function __construct(
		private readonly NotificationStore $notificationStore,
		private readonly UserIdentityUtils $identityUtils
	) {
	}

	public function run(): Response {
		$user = $this->getAuthority()->getUser();

		if ( !$this->identityUtils->isNamed( $user ) ) {
			return $this->getResponseFactory()->createHttpError(
				403,
				// maybe need a different name for this but alas
				[ 'error' => wfMessage( 'beacon-error-named-only' )->text() ]
			);
		}

		$body = $this->getValidatedBody();

		$upTo = $body['upTo'];

		$this->notificationStore->markAllRead( $user, $upTo );
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
			'upTo' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				IntegerDef::PARAM_MIN => 1,
			]
		] + $this->getTokenParamDefinition();
	}
}
