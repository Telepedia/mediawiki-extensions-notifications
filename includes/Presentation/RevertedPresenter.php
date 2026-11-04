<?php

namespace Telepedia\Extensions\Beacon\Presentation;

use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Wikimedia\Message\MessageValue;

class RevertedPresenter {

	public function __construct(
		private readonly RecipientFormatterFactory $formatterFactory,
	) {}

	/**
	 * @param UserIdentity $recipient whose edit was reverted
	 * @param UserIdentity $reverter who did the undo or rollback
	 * @param Title $title the page
	 * @param string $method 'undo' or 'rollback'
	 * @param string $diffUrl link to the revert's diff
	 * @param string|null $summary the revert's edit summary, if visible
	 * @return RenderedNotification
	 */
	public function present(
		UserIdentity $recipient,
		UserIdentity $reverter,
		Title $title,
		string $method,
		string $diffUrl,
		?string $summary
	): RenderedNotification {
		$formatter = $this->formatterFactory->newForUser( $recipient );

		return new RenderedNotification(
			language: $formatter->language,
			header: $formatter->format(
				MessageValue::new( "beacon-notification-reverted-$method" )
					->plaintextParams( $reverter->getName(), $title->getPrefixedText() )
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
