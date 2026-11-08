<?php

namespace Telepedia\Extensions\Beacon\Presentation;

use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Wikimedia\Message\MessageValue;

class UserJsEditPresenter implements NotificationPresenter {

	public function present( RecipientFormatter $formatter, NotificationContext $context ): RenderedNotification {
		$title = $context->title;
		$diffUrl = $context->extra['diffUrl'];

		return new RenderedNotification(
			language: $formatter->language,
			header: $formatter->format(
				MessageValue::new( 'beacon-notification-edited-user-js' )
					->plaintextParams( $context->agent->getName(), $title->getPrefixedText() )
			),
			url: $diffUrl,
			icon: 'cdxIconCode',
			links: [
				new NotificationLink(
					$formatter->format( MessageValue::new( 'beacon-notification-link-view-changes' ) ),
					$diffUrl,
					'cdxIconDiffs'
				),
				new NotificationLink(
					$formatter->format( MessageValue::new( 'beacon-notification-link-page' ) ),
					$title->getFullURL(),
					'cdxIconArticle'
				),
			],
		);
	}
}
