<?php

namespace Telepedia\Extensions\Beacon;

use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\Presentation\NotificationContext;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatterFactory;

class NotificationSender {

	public function __construct(
		private readonly NotificationTypeRegistry $types,
		private readonly RecipientFormatterFactory $formatterFactory,
		private readonly NotificationStore $store,
		private readonly SourceWiki $wiki,
	) {}

	/**
	 * @param string $type a type registered in the Beacon "Types" attribute
	 * @param iterable $recipients (iterates over UserIdentity::class)
	 * @param NotificationContext $context
	 * @return int the number of notifications sent
	 */
	public function send( string $type, iterable $recipients, NotificationContext $context ): int {
		$presenter = $this->types->getPresenter( $type );
		$category = $this->types->getCategory( $type );

		$sent = 0;
		foreach ( $recipients as $recipient ) {
			$rendered = $presenter->present( $this->formatterFactory->newForUser( $recipient ), $context );

			$this->store->store( new Notification(
				recipient: $recipient,
				wiki: $this->wiki,
				type: $type,
				category: $category,
				url: $rendered->url,
				header: $rendered->header,
				language: $rendered->language,
				icon: $rendered->icon,
				body: $rendered->body,
				agent: $context->agent,
				page: $context->title,
				links: $rendered->links,
			) );
			$sent++;
		}

		return $sent;
	}
}
