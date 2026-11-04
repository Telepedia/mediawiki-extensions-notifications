<?php

namespace Telepedia\Extensions\Beacon\Presentation;

use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Wikimedia\Message\MessageValue;

class RevertedPresenter implements NotificationPresenter {

	/**
	 * @param RecipientFormatter $formatter
	 * @param NotificationContext $context
	 * @return RenderedNotification
	 */
	public function present( RecipientFormatter $formatter, NotificationContext $context ): RenderedNotification {
		$title = $context->title;
		$diffUrl = $context->extra['diffUrl'];
		$summary = $context->extra['summary'] ?? null;

		return new RenderedNotification(
			language: $formatter->language,
			header: $formatter->format(
				MessageValue::new( 'beacon-notification-reverted-' . $context->extra['method'] )
					->plaintextParams( $context->agent->getName(), $title->getPrefixedText() )
			),
			url: $diffUrl,
			icon: 'revert',
			body: $summary !== null && $summary !== '' ? $summary : null,
			links: [
				new NotificationLink(
					$formatter->format( MessageValue::new( 'beacon-notification-link-view-changes' ) ),
					$diffUrl,
					'changes'
				),
				new NotificationLink(
					$formatter->format( MessageValue::new( 'beacon-notification-link-page' ) ),
					$title->getFullURL(),
					'article'
				),
			],
		);
	}
}
