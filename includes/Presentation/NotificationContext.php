<?php

namespace Telepedia\Extensions\Beacon\Presentation;

use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

readonly class NotificationContext {

	/**
	 * @param UserIdentity|null $agent who caused the notification or null if system
	 * @param Title|null $title the page the notification relates to or null otherwise
	 * @param array $extra extra attributes for the presenter to use
	 */
	public function __construct(
		public ?UserIdentity $agent = null,
		public ?Title $title = null,
		public array $extra = [],
	) {
	}
}
