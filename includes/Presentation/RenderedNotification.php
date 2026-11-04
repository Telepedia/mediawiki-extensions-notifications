<?php

namespace Telepedia\Extensions\Beacon\Presentation;

use Telepedia\Extensions\Beacon\Domain\NotificationLink;

readonly class RenderedNotification {

	/**
	 * @param string $language the language the text was rendered in
	 * @param string $header
	 * @param string $url the primary link
	 * @param string $icon
	 * @param string|null $body
	 * @param NotificationLink[] $links
	 */
	public function __construct(
		public string $language,
		public string $header,
		public string $url,
		public string $icon,
		public ?string $body = null,
		public array $links = [],
	) {}
}
