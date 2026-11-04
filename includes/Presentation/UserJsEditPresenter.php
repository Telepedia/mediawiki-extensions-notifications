<?php

namespace Telepedia\Extensions\Beacon\Presentation;

use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Wikimedia\Message\MessageValue;

class UserJsEditPresenter {

	public function __construct(
		private readonly RecipientFormatterFactory $formatterFactory,
	) {}

	/**
	 * @param UserIdentity $recipient the owner of the JavaScript page
	 * @param UserIdentity $editor who edited it
	 * @param Title $title the JavaScript page
	 * @param string $diffUrl link to the edit's diff
	 * @return RenderedNotification
	 */
	public function present(
		UserIdentity $recipient,
		UserIdentity $editor,
		Title $title,
		string $diffUrl
	): RenderedNotification {
		$formatter = $this->formatterFactory->newForUser( $recipient );

		return new RenderedNotification(
			language: $formatter->language,
			header: $formatter->format(
				MessageValue::new( 'beacon-notification-edited-user-js' )
					->plaintextParams( $editor->getName(), $title->getPrefixedText() )
			),
			url: $diffUrl,
			icon: 'code',
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
