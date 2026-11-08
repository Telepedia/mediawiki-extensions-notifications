<?php

namespace Telepedia\Extensions\Beacon\Hooks;

use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use Telepedia\Extensions\Beacon\NotificationCategoryRegistry;

class PreferencesHooks implements GetPreferencesHook, UserGetDefaultOptionsHook {

	/**
	 * Whether we have global preferences or not
	 * @var bool
	 */
	private readonly bool $hasGlobalPreferences;

	/**
	 * @param NotificationCategoryRegistry $categoryRegistry
	 * @param bool|null $hasGlobalPreferences whether the GlobalPreferences extension is installed (pass null to check)
	 */
	public function __construct(
		private readonly NotificationCategoryRegistry $categoryRegistry,
		?bool $hasGlobalPreferences = null,
	) {
		$this->hasGlobalPreferences = $hasGlobalPreferences
			?? ExtensionRegistry::getInstance()->isLoaded( 'GlobalPreferences' );
	}

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ): void {
		if ( $this->hasGlobalPreferences ) {
			$preferences['beacon-per-wiki-note'] = [
				'type' => 'info',
				'raw' => true,
				// @note: we use this closure like core does, so that the parsing is only done
				// when the message is being displayed
				'default' => static fn () => wfMessage( 'beacon-preferences-per-wiki' )->parse(),
				'section' => 'beacon/beacon-categories',
				'noglobal' => true,
			];
		}

		foreach ( $this->categoryRegistry->getNames() as $category ) {
			$preferences[$this->categoryRegistry->getPreferenceName( $category )] = [
				'type' => 'toggle',
				'label-message' => $this->categoryRegistry->getLabel( $category ),
				'section' => 'beacon/beacon-categories',
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ): void {
		$defaultOptions = $this->categoryRegistry->getDefaultOptions() + $defaultOptions;
	}
}
