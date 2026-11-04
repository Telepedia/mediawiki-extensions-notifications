<?php

namespace Telepedia\Extensions\Beacon;

use InvalidArgumentException;
use Telepedia\Extensions\Beacon\Presentation\NotificationPresenter;
use Wikimedia\ObjectFactory\ObjectFactory;

class NotificationTypeRegistry {

	/**
	 * @var array
	 */
	private array $presenters = [];

	/**
	 * @param ObjectFactory $objectFactory
	 * @param array $types the attribute
	 */
	public function __construct(
		private readonly ObjectFactory $objectFactory,
		private readonly array $types,
	) {}

	public function has( string $type ): bool {
		return isset( $this->types[$type] );
	}

	public function getCategory( string $type ): string {
		return $this->getType( $type )['category'] ?? $type;
	}

	public function getPresenter( string $type ): NotificationPresenter {
		$this->presenters[$type] ??= $this->objectFactory->createObject(
			$this->getType( $type )['presenter'],
			[ 'assertClass' => NotificationPresenter::class ]
		);
		return $this->presenters[$type];
	}

	private function getType( string $type ): array {
		if ( !isset( $this->types[$type] ) ) {
			throw new InvalidArgumentException( "Unknown Beacon notification type '$type'" );
		}
		return $this->types[$type];
	}
}
