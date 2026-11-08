<?php

namespace Telepedia\Extensions\Beacon\Presentation;

use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use Wikimedia\Message\IMessageFormatterFactory;

class RecipientFormatterFactory {

	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly IMessageFormatterFactory $messageFormatterFactory,
	) {
	}

	/**
	 * @param UserIdentity $recipient
	 * @return RecipientFormatter
	 */
	public function newForUser( UserIdentity $recipient ): RecipientFormatter {
		$language = $this->userOptionsLookup->getOption( $recipient, 'language' );

		return new RecipientFormatter(
			$language,
			$this->messageFormatterFactory->getTextFormatter( $language )
		);
	}
}
