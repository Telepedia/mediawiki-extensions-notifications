<?php

namespace Telepedia\Extensions\Beacon\Domain;

use JsonException;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use stdClass;

readonly class Notification {

	public function __construct(
		public UserIdentity $recipient,
		public SourceWiki $wiki,
		public string $type,
		public string $category,
		public string $url,
		public string $header,
		public string $language,
		public string $icon,
		public ?string $body = null,
		public ?UserIdentity $agent = null,
		public ?PageIdentity $page = null,
		public array $links = [],
		public ?string $groupKey = null,
		public ?int $id = null,
		public ?string $created = null,
		public ?string $read = null,
		public bool $hidden = false,
	) {}

	/**
	 * @throws JsonException
	 */
	public static function newFromRow( stdClass $row ): self {
		// prevent MediaWiki complaining that the page does not belong to the current wiki
		// if the notification is not for the current wiki
		$pageWikiId = $row->notif_wiki === WikiMap::getCurrentWikiId()
			? WikiAwareEntity::LOCAL
			: $row->notif_wiki;

		return new self(
			recipient: new UserIdentityValue( (int)$row->notif_user, '' ),
			wiki: new SourceWiki( $row->notif_wiki, $row->notif_wiki_name ),
			type: $row->notif_type,
			category: $row->notif_category,
			url: $row->notif_url,
			header: $row->notif_header,
			language: $row->notif_language,
			icon: $row->notif_icon,
			body: $row->notif_body,
			agent: $row->notif_agent_name !== null
				? new UserIdentityValue( (int)$row->notif_agent_id, $row->notif_agent_name ) : null,
			page: $row->notif_page_id !== null
				? new PageIdentityValue(
					(int)$row->notif_page_id,
					(int)$row->notif_page_ns,
					$row->notif_page_title,
					$pageWikiId
				) : null,
			links: NotificationLink::newListFromJson( $row->notif_links ),
			groupKey: $row->notif_group_key,
			id: (int)$row->notif_id,
			created: $row->notif_created,
			read: $row->notif_read,
			hidden: (bool)$row->notif_hidden
		);
	}
}
