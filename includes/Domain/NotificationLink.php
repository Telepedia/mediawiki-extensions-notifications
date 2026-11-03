<?php

namespace Telepedia\Extensions\Beacon\Domain;

use InvalidArgumentException;
use JsonException;

readonly class NotificationLink {

	public function __construct(
		public string $label,
		public string $url,
		public ?string $icon = null
	) {
		$scheme = parse_url( $url, PHP_URL_SCHEME );
		if ( $scheme !== 'https' && $scheme !== 'http' ) {
			throw new InvalidArgumentException( "Notification links must be absolute HTTP(S) urls. Url: $url" );
		}
	}

	/**
	 * @return array
	 */
	public function toArray(): array {
		return array_filter( [
			'label' => $this->label,
			'url' => $this->url,
			'icon' => $this->icon
		], static fn ( $x ) => $x != null );
	}

	/**
	 * Create a new NotificationLink from a given array
	 * @param array $data
	 * @return self
	 */
	public static function newFromArray( array $data ): self {
		return new self( $data['label'], $data['url'], $data['icon'] ?? null );
	}

	/**
	 * @param NotificationLink[] $links
	 * @return string|null JSON for saving in notif_links, or null if there are none
	 * @throws JsonException
	 */
	public static function listToJson( array $links ): ?string {
		if ( !$links ) {
			return null;
		}

		return json_encode(
			array_map( static fn ( self $link ) => $link->toArray(), $links ),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);
	}

	/**
	 * @return NotificationLink[]
	 * @throws JsonException
	 */
	public static function newListFromJson( ?string $json ): array {
		if ( $json === null || $json === '' ) {
			return [];
		}
		return array_map(
			static fn ( array $data ) => self::newFromArray( $data ),
			json_decode( $json, true, 512, JSON_THROW_ON_ERROR )
		);
	}

}
