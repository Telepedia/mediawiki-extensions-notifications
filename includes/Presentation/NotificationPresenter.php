<?php

namespace Telepedia\Extensions\Beacon\Presentation;

interface NotificationPresenter {

	/**
	 * @param RecipientFormatter $formatter formats messages in the recipient's language
	 * @param NotificationContext $context the facts about the notification
	 * @return RenderedNotification
	 */
	public function present( RecipientFormatter $formatter, NotificationContext $context ): RenderedNotification;
}
