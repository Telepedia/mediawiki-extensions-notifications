<?php

namespace Telepedia\Extensions\Beacon\Tests\Unit;

use JsonException;
use MediaWikiUnitTestCase;
use stdClass;
use Telepedia\Extensions\Beacon\Domain\Notification;
use Telepedia\Extensions\Beacon\Domain\NotificationLink;

/**
 * @covers \Telepedia\Extensions\Beacon\Domain\Notification
 */
class NotificationTest extends MediaWikiUnitTestCase {

	/**
	 * A row as the database returns it
	 * @param array $overrides columns to change from the defaults
	 * @return stdClass
	 */
	private static function makeRow( array $overrides = [] ): stdClass {
		return (object)( $overrides + [
			'notif_id' => '5',
			'notif_user' => '3',
			'notif_wiki' => 'foowiki',
			'notif_wiki_name' => 'Foo Wiki',
			'notif_type' => 'reverted',
			'notif_category' => 'reverted',
			'notif_agent_id' => '2',
			'notif_agent_name' => 'John Appleseed',
			'notif_page_id' => '666',
			'notif_page_ns' => '0',
			'notif_page_title' => 'Placeholder_Page',
			'notif_url' => 'https://foo.example.com/wiki/Placeholder_Page?diff=203',
			'notif_header' => 'John Appleseed reverted your edit to Placeholder Page.',
			'notif_body' => 'rv vandalism',
			'notif_language' => 'en',
			'notif_icon' => 'revert',
			'notif_links' => '[{"label":"View changes","url":"https://foo.example.com/wiki/Placeholder_Page?diff=203",'
				. '"icon":"changes"}]',
			'notif_group_key' => null,
			'notif_created' => '20261104001815',
			'notif_read' => null,
			'notif_hidden' => '0',
		] );
	}

	public function testMapsEveryColumn(): void {
		$n = Notification::newFromRow( self::makeRow() );

		$this->assertSame( 5, $n->id );
		$this->assertSame( 3, $n->recipient->getId() );
		$this->assertSame( 'foowiki', $n->wiki->id );
		$this->assertSame( 'Foo Wiki', $n->wiki->name );
		$this->assertSame( 'reverted', $n->type );
		$this->assertSame( 'reverted', $n->category );
		$this->assertSame( 'https://foo.example.com/wiki/Placeholder_Page?diff=203', $n->url );
		$this->assertSame( 'John Appleseed reverted your edit to Placeholder Page.', $n->header );
		$this->assertSame( 'rv vandalism', $n->body );
		$this->assertSame( 'en', $n->language );
		$this->assertSame( 'revert', $n->icon );
		$this->assertNull( $n->groupKey );
		$this->assertSame( '20261104001815', $n->created );
		$this->assertNull( $n->read );
		$this->assertFalse( $n->hidden );
	}

	/**
	 * @throws JsonException
	 */
	public function testAgent(): void {
		$agent = Notification::newFromRow( self::makeRow() )->agent;

		$this->assertNotNull( $agent );
		$this->assertSame( 2, $agent->getId() );
		$this->assertSame( 'John Appleseed', $agent->getName() );
		$this->assertTrue( $agent->isRegistered() );
	}

	public function testIpAgentIsUnregistered(): void {
		$agent = Notification::newFromRow( self::makeRow( [
			'notif_agent_id' => '0',
			'notif_agent_name' => '127.0.0.1',
		] ) )->agent;

		$this->assertNotNull( $agent );
		$this->assertSame( '127.0.0.1', $agent->getName() );
		$this->assertFalse( $agent->isRegistered() );
	}

	public function testNoAgent(): void {
		$n = Notification::newFromRow( self::makeRow( [
			'notif_agent_id' => null,
			'notif_agent_name' => null,
		] ) );

		$this->assertNull( $n->agent );
	}

	public function testPageCarriesSourceWikiId(): void {
		$page = Notification::newFromRow( self::makeRow() )->page;

		$this->assertNotNull( $page );
		$this->assertSame( 'foowiki', $page->getWikiId() );
		$this->assertSame( 666, $page->getId( 'foowiki' ) );
		$this->assertSame( 0, $page->getNamespace() );
		$this->assertSame( 'Placeholder_Page', $page->getDBkey() );
	}

	/**
	 * @throws JsonException
	 */
	public function testNoPage(): void {
		$n = Notification::newFromRow( self::makeRow( [
			'notif_page_id' => null,
			'notif_page_ns' => null,
			'notif_page_title' => null,
		] ) );

		$this->assertNull( $n->page );
	}

	/**
	 * @throws JsonException
	 */
	public function testLinksAreDecoded(): void {
		$links = Notification::newFromRow( self::makeRow() )->links;

		$this->assertEquals(
			[ new NotificationLink( 'View changes', 'https://foo.example.com/wiki/Placeholder_Page?diff=203', 'changes' ) ],
			$links
		);
	}

	public function testNoLinks(): void {
		$n = Notification::newFromRow( self::makeRow( [ 'notif_links' => null ] ) );

		$this->assertSame( [], $n->links );
	}

	/**
	 * @throws JsonException
	 */
	public function testReadAndHidden(): void {
		$n = Notification::newFromRow( self::makeRow( [
			'notif_read' => '20261104120000',
			'notif_hidden' => '1',
		] ) );

		$this->assertSame( '20261104120000', $n->read );
		$this->assertTrue( $n->hidden );
	}
}
