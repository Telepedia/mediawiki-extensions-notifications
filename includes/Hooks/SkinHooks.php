<?php

namespace Telepedia\Extensions\Beacon\Hooks;

use MediaWiki\Output\Hook\OutputPageCheckLastModifiedHook;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\SpecialPage\SpecialPage;
use Telepedia\Extensions\Beacon\NotificationStore;
use Wikimedia\Timestamp\TimestampFormat;

class SkinHooks implements
	SkinTemplateNavigation__UniversalHook,
	OutputPageCheckLastModifiedHook
{

	public function __construct(
		private readonly NotificationStore $notificationStore,
	) {}

	/**
	 * @inheritDoc
	 */
	public function onOutputPageCheckLastModified( &$modifiedTimes, $out ): void {
		$action = $out->getRequest()->getRawVal( 'action' );
		if ( $action === 'raw' || $action === 'render' || !$out->getUser()->isNamed() ) {
			return;
		}

		$touched = $this->notificationStore->getUnreadCountTouched( $out->getUser() );
		if ( $touched !== null ) {
			$modifiedTimes['beacon-notifications'] = wfTimestamp( TimestampFormat::MW, (int)ceil( $touched ) );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$sktemplate->getUser()->isNamed() ) {
			return;
		}

		$count = $this->notificationStore->getUnreadCount( $sktemplate->getUser() );
		$lang = $sktemplate->getLanguage();
		$countText = $count > NotificationStore::MAX_UNREAD_COUNT
			? $sktemplate->msg( 'beacon-badge-count-max' )->numParams( NotificationStore::MAX_UNREAD_COUNT )->text()
			: $lang->formatNum( $count );

		$links['notifications']['notifications-alert'] = [
			'href' => SpecialPage::getTitleFor( 'Notifications' )->getLocalURL(),
			'text' => $sktemplate->msg( 'beacon-notifications-badge' )->params( $countText )->text(),
			// if we pass 'bell' as the icon we get the OOUI bell which is not great, to have Codex we need to pass
			// something that skins like vector don't recognise and then style it ourselves.
			'icon' => 'beacon-bell',
			'link-class' => [ 'beacon-badge' ],
			'data' => [
				'counter-num' => $count,
				'counter-text' => $countText
			],
			'id' => 'pt-notifications-alert'
		];

		$sktemplate->getOutput()->addModuleStyles( 'ext.beacon.styles' );
		$sktemplate->getOutput()->addModules( 'ext.beacon.init' );
	}
}
