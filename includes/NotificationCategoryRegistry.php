<?php

namespace Telepedia\Extensions\Beacon;

class NotificationCategoryRegistry {

	/**
	 * We are using beacon-web here so that we can add email options later if we feel like it without
	 * messing up any existing preferences
	 */
	private const string PREFERENCE_PREFIX = 'beacon-web-';

	/**
	 * @param array $categories
	 */
	public function __construct(
		private readonly array $categories,
	) {
	}

	/**
	 * @return array
	 */
	public function getNames(): array {
		return array_keys( $this->categories );
	}

	/**
	 * @param string $category
	 * @return string the message key describing the category in Special:Preferences
	 */
	public function getLabel( string $category ): string {
		return $this->categories[$category]['label'] ?? "beacon-category-$category";
	}

	/**
	 * @param string $category
	 * @return string
	 */
	public function getPreferenceName( string $category ): string {
		return self::PREFERENCE_PREFIX . $category;
	}

	/**
	 * @return array the default value for every category user option
	 * all options we return true unless we have been told otherwise
	 */
	public function getDefaultOptions(): array {
		$defaults = [];
		foreach ( $this->categories as $category => $definition ) {
			$defaults[$this->getPreferenceName( $category )] = (int)( $definition['default'] ?? true );
		}
		return $defaults;
	}
}
