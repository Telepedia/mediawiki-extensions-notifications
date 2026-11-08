<?php

namespace Telepedia\Extensions\Beacon\ResourceLoader;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Context;

class PopoverConfig {

	/**
	 * Default placement if a skin didn't indicate where it wants the popover to open
	 * @var string
	 */
	public const string DEFAULT_PLACEMENT = 'bottom-end';

	/**
	 * The placements CdxPopover accepts
	 * @var array
	 */
	private const array PLACEMENTS = [
		'top', 'top-start', 'top-end',
		'right', 'right-start', 'right-end',
		'bottom', 'bottom-start', 'bottom-end',
		'left', 'left-start', 'left-end',
	];

	/**
	 * We allow a skin to use the BeaconPopoverPlacement attribute in skin.json to indicate where they
	 * want the popover to open, so that it can open in different places if the bell is in the top right (vector),
	 * or if it is in the sidebar (prism, citizen)
	 * @param Context $context
	 * @return array
	 */
	public static function getConfig( Context $context ): array {
		return [
			'placement' => self::getPlacement(
				ExtensionRegistry::getInstance()->getAttribute( 'BeaconPopoverPlacement' ),
				$context->getSkin()
			),
		];
	}

	/**
	 * @param array $placements the attribute
	 * @param string $skin the skin name
	 * @return string
	 */
	public static function getPlacement( array $placements, string $skin ): string {
		$placement = $placements[$skin] ?? null;
		return in_array( $placement, self::PLACEMENTS, true ) ? $placement : self::DEFAULT_PLACEMENT;
	}
}
