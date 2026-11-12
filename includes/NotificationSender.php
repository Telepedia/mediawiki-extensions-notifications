<?php

namespace Telepedia\Extensions\Beacon;

use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;
use Telepedia\Extensions\Beacon\Domain\SourceWiki;
use Telepedia\Extensions\Beacon\Presentation\NotificationContext;
use Telepedia\Extensions\Beacon\Presentation\RecipientFormatterFactory;

class NotificationSender {

	public function __construct(
		private readonly NotificationTypeRegistry $types,
		private readonly RecipientFormatterFactory $formatterFactory,
		private readonly NotificationStore $store,
		private readonly SourceWiki $wiki,
		private readonly NotificationIconRegistry $icons,
		private readonly NotificationPurger $purger,
	) {
	}

	/**
	 * @param string $type a type registered in the Beacon "Types" attribute
	 * @param iterable $recipients (iterates over UserIdentity::class)
	 * @param NotificationContext $context
	 * @return int the number of notifications sent
	 */
	public function send( string $type, iterable $recipients, NotificationContext $context ): int {
		$presenter = $this->types->getPresenter( $type );
		$category = $this->types->getCategory( $type );

		$sent = [];
		foreach ( $recipients as $recipient ) {
			$rendered = $presenter->present( $this->formatterFactory->newForUser( $recipient ), $context );

			$id = $this->store->store( new Notification(
				recipient: $recipient,
				wiki: $this->wiki,
				type: $type,
				category: $category,
				url: $rendered->url,
				header: $rendered->header,
				language: $rendered->language,
				icon: $this->icons->resolve( $rendered->icon, $rendered->language ),
				body: $rendered->body,
				agent: $context->agent,
				page: $context->title,
				links: array_map(
					fn ( NotificationLink $link ) => $link->icon === null ? $link : new NotificationLink(
						$link->label,
						$link->url,
						$this->icons->resolve( $link->icon, $rendered->language )
					),
					$rendered->links
				),
			) );
			if ( $id !== 0 ) {
				$sent[] = $recipient;
			}
		}

		$this->purger->queueTrim( $sent );

		return count( $sent );
	}
}
