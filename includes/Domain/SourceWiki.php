<?php

namespace Telepedia\Extensions\Beacon\Domain;

use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\WikiMap\WikiMap;

readonly class SourceWiki {

	public function __construct(
		public string $id,
		public string $name,
	) {
	}

	public static function newFromConfig( Config $config ): self {
		return new self(
			WikiMap::getCurrentWikiId(),
			$config->get( MainConfigNames::Sitename )
		);
	}
}
