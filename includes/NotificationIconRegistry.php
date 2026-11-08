<?php

namespace Telepedia\Extensions\Beacon;

use MediaWiki\Request\FauxRequest;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\ImageModule;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Utils\UrlUtils;
use Psr\Log\LoggerInterface;

class NotificationIconRegistry {

	/**
	 * Icon which will be stored for unknown or broken icons
	 * @var string
	 */
	public const string FALLBACK = 'cdxIconBell';

	/**
	 * @param array $icons the BeaconIcons attribute
	 * @param ResourceLoader $resourceLoader
	 * @param string $loadScript $wgLoadScript
	 * @param UrlUtils $urlUtils
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly array $icons,
		private readonly ResourceLoader $resourceLoader,
		private readonly string $loadScript,
		private readonly UrlUtils $urlUtils,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Check whether what we were given is a Codex icon
	 * This relies on all icons starting with cdxIcon so pls WMF don't break this
	 * @param string $icon
	 * @return bool
	 */
	public static function isCodexIcon( string $icon ): bool {
		return preg_match( '/^cdxIcon[A-Z][A-Za-z0-9]*$/', $icon ) === 1;
	}

	/**
	 * @param string $icon a Codex icon name or a registered custom icon name
	 * @param string $language the recipient's language code
	 * @return string the value to store
	 */
	public function resolve( string $icon, string $language ): string {
		if ( self::isCodexIcon( $icon ) ) {
			return $icon;
		}

		$url = $this->getCustomIconUrl( $icon, $language );
		if ( $url === null ) {
			return self::FALLBACK;
		}

		return $this->urlUtils->expand( $url, PROTO_CANONICAL ) ?? $url;
	}

	/**
	 * This is a bit of a hack, but since a wiki receiving a notification from a foregin wiki
	 * may not have that extension installed, we call out to ResourceLoader on that wiki to fetch the icon
	 * and then save the canonical url to that icon in the database so that it can be fetched later on
	 * This works because ResourceLoader adds the CORS header to image modules
	 * @return string|null the URL, or null if the icon can't be found
	 */
	private function getCustomIconUrl( string $name, string $language ): ?string {
		$icon = $this->icons[$name] ?? null;
		if ( $icon === null ) {
			$this->logger->warning( 'Unknown Beacon icon {name}', [ 'name' => $name ] );
			return null;
		}

		$module = $this->resourceLoader->getModule( $icon['module'] );
		if ( !$module instanceof ImageModule ) {
			$this->logger->warning( 'Beacon icon {name}: {module} is not a ResourceLoader image module', [
				'name' => $name,
				'module' => $icon['module'],
			] );
			return null;
		}

		$context = new Context( $this->resourceLoader, new FauxRequest( [ 'lang' => $language ] ) );
		$image = $module->getImage( $icon['image'], $context );
		if ( $image === null ) {
			$this->logger->warning( 'Beacon icon {name}: {module} has no image {image}', [
				'name' => $name,
				'module' => $icon['module'],
				'image' => $icon['image'],
			] );
			return null;
		}

		return $image->getUrl( $context, $this->loadScript, null, 'original' );
	}
}
