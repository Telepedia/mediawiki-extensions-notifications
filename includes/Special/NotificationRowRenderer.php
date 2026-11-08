<?php

namespace Telepedia\Extensions\Beacon\Special;

use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MessageLocalizer;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\NotificationIconRegistry;
use Wikimedia\Timestamp\TimestampFormat;

readonly class NotificationRowRenderer {

	/**
	 * @param MessageLocalizer $localizer
	 * @param Language $language viewer language
	 * @param UserIdentity $viewer viewer
	 * @param string $formAction the URL the mark as read goes to
	 * @param string $token the CSRF token
	 */
	public function __construct(
		private MessageLocalizer $localizer,
		private Language $language,
		private UserIdentity $viewer,
		private string $formAction,
		private string $token,
	) {
	}

	/**
	 * Render an individual notification item
	 * @param Notification $n
	 * @return string a <li>
	 */
	public function render( Notification $n ): string {
		$isUnread = $n->read === null;

		return Html::rawElement(
			'li',
			[
				'class' => [ 'beacon-item', 'beacon-item--unread' => $isUnread ],
				'data-notification-id' => $n->id,
			],
			$this->renderIcon( $n->icon ) .
			Html::rawElement( 'span', [ 'class' => 'beacon-item__text' ],
				Html::element( 'a', [ 'class' => 'beacon-item__header', 'href' => $n->url ], $n->header ) .
				( $n->body !== null && $n->body !== ''
					? Html::element( 'span', [ 'class' => 'beacon-item__body' ], $n->body )
					: '' ) .
				Html::rawElement( 'span', [ 'class' => 'beacon-item__footer' ],
					$this->renderLinks( $n ) . $this->renderTime( $n )
				)
			) .
			$this->renderToggle( $n, $isUnread )
		);
	}

	/**
	 * @param string $icon
	 * @return string
	 */
	private function renderIcon( string $icon ): string {
		$attribs = [ 'class' => [ 'beacon-icon' ] ];
		if ( NotificationIconRegistry::isCodexIcon( $icon ) ) {
			$attribs['class'][] = "beacon-icon--$icon";
		} elseif ( preg_match( '/^https?:\/\//', $icon ) ) {
			// oh this is a bit funky, but alas we need to convert the URL to something we can add to a
			// html style declaration and this is the only way I can think to do it rn
			$url = strtr( $icon, [ '"' => '%22', '\\' => '%5C', "\n" => '%0A' ] );
			$attribs['style'] = "mask-image: url(\"$url\"); -webkit-mask-image: url(\"$url\");";
		}

		return Html::rawElement(
			'span',
			[ 'class' => 'beacon-item__icon' ],
			Html::element(
				'span',
				$attribs
			)
		);
	}

	/**
	 * @param Notification $n
	 * @return string
	 */
	private function renderLinks( Notification $n ): string {
		$html = '';
		if ( $n->wiki->id !== WikiMap::getCurrentWikiId() ) {
			$html .= Html::element( 'span', [ 'class' => 'beacon-item__wiki' ], $n->wiki->name );
		}
		foreach ( $n->links as $link ) {
			$html .= Html::element( 'a', [ 'class' => 'beacon-item__link', 'href' => $link->url ], $link->label );
		}

		return Html::rawElement( 'span', [ 'class' => 'beacon-item__links' ], $html );
	}

	/**
	 * @param Notification $n
	 * @return string
	 */
	private function renderTime( Notification $n ): string {
		return Html::element(
			'time',
			[
				'class' => 'beacon-item__time',
				'datetime' => wfTimestamp( TimestampFormat::ISO_8601, $n->created ),
				'title' => $this->language->userTimeAndDate( $n->created, $this->viewer ),
			],
			$this->language->userTime( $n->created, $this->viewer )
		);
	}

	/**
	 * Render the read/unread button
	 * @param Notification $n
	 * @param bool $isUnread
	 * @return string
	 */
	private function renderToggle( Notification $n, bool $isUnread ): string {
		$label = $this->msg( $isUnread ? 'beacon-popover-mark-read' : 'beacon-popover-mark-unread' )->text();

		return Html::rawElement( 'form', [
				'class' => 'beacon-item__form',
				'method' => 'post',
				'action' => $this->formAction,
			],
			Html::hidden( 'wpEditToken', $this->token ) .
			Html::hidden( 'beaconaction', $isUnread ? 'markread' : 'markunread' ) .
			Html::hidden( 'id', (string)$n->id ) .
			Html::element( 'button', [
				'type' => 'submit',
				'class' => 'beacon-item__dot',
				'title' => $label,
				'aria-label' => $label,
			] )
		);
	}

	/**
	 * @param string $timestamp any timestamp on that day
	 * @return string
	 */
	public function renderDayHeading( string $timestamp ): string {
		return Html::element(
			'h2',
			[ 'class' => 'beacon-special__day' ],
			$this->language->userDate( $timestamp, $this->viewer )
		);
	}

	/**
	 * @param Notification $n
	 * @return string
	 */
	public function getDayKey( Notification $n ): string {
		return $this->language->userDate( $n->created, $this->viewer );
	}

	/**
	 * @param string $key
	 * @return Message
	 */
	private function msg( string $key ): Message {
		return $this->localizer->msg( $key );
	}
}
