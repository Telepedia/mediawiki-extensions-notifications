<?php

namespace Telepedia\Extensions\Beacon\Hooks;

use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\SpecialPage\SpecialPage;

class SkinHooks implements SkinTemplateNavigation__UniversalHook {

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$sktemplate->getUser()->isNamed() ) {
			return;
		}

		// placeholder for now until we have a way to get this from the DB
		$count = 0;

		$links['notifications']['notifications-alert'] = [
			'href' => SpecialPage::getTitleFor( 'Notifications' )->getLocalURL(),
			'text' => $sktemplate->msg( 'beacon-notifications-badge' )->numParams( $count )->text(),
			// if we pass 'bell' as the icon we get the OOUI bell which is not great, to have Codex we need to pass
			// something that skins like vector don't recognise and then style it ourselves.
			'icon' => 'beacon-bell',
			'link-class' => [ 'beacon-badge' ],
			'data' => [
				'counter-num' => $count,
				'counter-text' => $sktemplate->getLanguage()->formatNum( $count )
			],
			'id' => 'pt-notifications-alert'
		];

		$sktemplate->getOutput()->addModuleStyles( 'ext.beacon.styles' );
	}
}
