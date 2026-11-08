<?php

namespace Telepedia\Extensions\Beacon\Presentation;

use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageValue;

readonly class RecipientFormatter {

	public function __construct(
		public string $language,
		private ITextFormatter $formatter,
	) {
	}

	public function format( MessageValue $message ): string {
		return $this->formatter->format( $message );
	}
}
