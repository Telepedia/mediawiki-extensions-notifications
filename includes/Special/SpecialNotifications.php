<?php

namespace Telepedia\Extensions\Beacon\Special;

use JsonException;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use Telepedia\Extensions\Beacon\NotificationStore;

class SpecialNotifications extends SpecialPage {

	/**
	 * How many notifications to show on each page
	 * @var int
	 */
	private const int LIMIT = 50;

	public function __construct(
		private readonly NotificationStore $notificationStore,
	) {
		parent::__construct( 'Notifications' );
	}

	/**
	 * @param string|null $subPage
	 * @throws JsonException|UserNotLoggedIn
	 */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->outputHeader();
		$this->requireNamedUser();

		$request = $this->getRequest();
		$continue = $request->getInt( 'continue' ) ?: null;

		$out = $this->getOutput();
		if ( $request->wasPosted() ) {
			if ( $this->handlePost( $continue ) ) {
				return;
			}
			$out->addHTML(
				Html::errorBox(
					$this->msg( 'sessionfailure' )->parse()
				)
			);
		}

		$out->addModuleStyles( [ 'ext.beacon.list.styles', 'ext.beacon.special.styles' ] );
		$out->addModules( 'ext.beacon.special' );

		$user = $this->getUser();

		$notifications = $this->notificationStore->listForUser( $user, self::LIMIT + 1, $continue );
		$hasMore = count( $notifications ) > self::LIMIT;
		$notifications = array_slice( $notifications, 0, self::LIMIT );

		$out->addHTML(
			Html::openElement(
				'div',
				[ 'class' => 'beacon-special' ]
			)
		);

		$out->addHTML( $this->renderToolbar( $continue ) );

		if ( !$notifications ) {
			$out->addHTML( Html::element(
				'p',
				[ 'class' => 'beacon-special__empty' ],
				$this->msg( 'beacon-popover-empty' )->text()
			) );
		} else {
			$out->addHTML( $this->renderList( $notifications ) );
		}

		$out->addHTML( $this->renderNavigation( $continue, $hasMore ? end( $notifications )->id : null ) );
		$out->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Handle marking as read/unread
	 * @param int|null $continue the page number we were on
	 * @return bool false if the CSRF didn't match and we didn't do anything
	 */
	private function handlePost( ?int $continue ): bool {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$this->getContext()->getCsrfTokenSet()->matchTokenField( 'wpEditToken' ) ) {
			return false;
		}

		$id = $request->getInt( 'id' );
		match ( $request->getVal( 'beaconaction' ) ) {
			'markread' => $id && $this->notificationStore->markRead( $user, [ $id ] ),
			'markunread' => $id && $this->notificationStore->markUnread( $user, [ $id ] ),
			'markallread' => $id && $this->notificationStore->markAllRead( $user, $id ),
			default => null,
		};

		$this->getOutput()->redirect(
			$this->getPageTitle()->getFullURL( $continue ? [ 'continue' => $continue ] : [] )
		);
		return true;
	}

	/**
	 * Render the "mark all as read" if the user has any unread notifications, and
	 * handle marking them as read
	 * @param int|null $continue
	 * @return string
	 * @throws JsonException
	 */
	private function renderToolbar( ?int $continue ): string {
		$user = $this->getUser();
		if ( $this->notificationStore->getUnreadCount( $user ) === 0 ) {
			return '';
		}

		$newest = $this->notificationStore->listForUser( $user, 1 )[0] ?? null;
		if ( !$newest ) {
			return '';
		}

		return Html::rawElement( 'div', [ 'class' => 'beacon-special__toolbar' ],
			Html::rawElement( 'form', [ 'method' => 'post', 'action' => $this->getFormAction( $continue ) ],
				Html::hidden( 'wpEditToken', $this->getContext()->getCsrfTokenSet()->getToken()->toString() ) .
				Html::hidden( 'beaconaction', 'markallread' ) .
				Html::hidden( 'id', (string)$newest->id ) .
				Html::element( 'button', [
					'type' => 'submit',
					'class' => 'cdx-button cdx-button--weight-quiet cdx-button--action-progressive',
				], $this->msg( 'beacon-popover-mark-all-read' )->text() )
			)
		);
	}

	/**
	 * @param array $notifications
	 * @return string the list grouped by timestamp (only the day)
	 */
	private function renderList( array $notifications ): string {
		$renderer = new NotificationRowRenderer(
			$this,
			$this->getLanguage(),
			$this->getUser(),
			$this->getFormAction( $this->getRequest()->getInt( 'continue' ) ?: null ),
			$this->getContext()->getCsrfTokenSet()->getToken()->toString()
		);

		$html = '';
		$day = null;
		foreach ( $notifications as $notification ) {
			$key = $renderer->getDayKey( $notification );
			if ( $key !== $day ) {
				if ( $day !== null ) {
					$html .= Html::closeElement( 'ul' ) . Html::closeElement( 'section' );
				}
				$html .= Html::openElement( 'section', [ 'class' => 'beacon-special__section' ] ) .
					$renderer->renderDayHeading( $notification->created ) .
					Html::openElement( 'ul', [ 'class' => 'beacon-list beacon-special__list' ] );
				$day = $key;
			}
			$html .= $renderer->render( $notification );
		}

		return $html . Html::closeElement( 'ul' ) . Html::closeElement( 'section' );
	}

	/**
	 * Render the navigation to go to a different page
	 * @param int|null $continue the current page
	 * @param int|null $next next page or null if none
	 * @return string
	 */
	private function renderNavigation( ?int $continue, ?int $next ): string {
		if ( $continue === null && $next === null ) {
			return '';
		}

		$links = '';
		if ( $continue !== null ) {
			$links .= Html::element( 'a', [
				'class' => 'cdx-button cdx-button--fake-button cdx-button--fake-button--enabled',
				'href' => $this->getPageTitle()->getLocalURL(),
			], $this->msg( 'beacon-special-newest' )->text() );
		}
		if ( $next !== null ) {
			$links .= Html::element( 'a', [
				'class' => 'cdx-button cdx-button--fake-button cdx-button--fake-button--enabled beacon-special__older',
				'href' => $this->getPageTitle()->getLocalURL( [ 'continue' => $next ] ),
			], $this->msg( 'beacon-special-older' )->text() );
		}

		return Html::rawElement( 'nav', [
			'class' => 'beacon-special__nav',
			'aria-label' => $this->msg( 'beacon-special-nav-label' )->text(),
		], $links );
	}

	private function getFormAction( ?int $continue ): string {
		return $this->getPageTitle()->getLocalURL( $continue ? [ 'continue' => $continue ] : [] );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return $this->msg( 'beacon-special-title' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName(): string {
		return 'users';
	}

	/**
	 * @inheritDoc
	 */
	public function isListed(): bool {
		return $this->getUser()->isNamed();
	}
}
