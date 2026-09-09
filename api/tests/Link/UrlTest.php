<?php
/**
 * EGroupware Api: test coverage for linking an entry to an arbitrary external URL
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Storage\Base;

require_once realpath(__DIR__.'/../AppTest.php');

/**
 * Link::URL_APPNAME ('url') lets any entry link to an arbitrary external URL, stored in
 * egw_links like any other link (link_app2='url', link_id2=<the URL>), instead of a
 * per-app URL table. Covers the schema change (link_id2 widened to varchar(1024), see
 * doc/ai/projects/link-url-support.md) and the Link::title() special-case a 'url' link
 * needs - without it, title() falls through to the unregistered-app branch and returns
 * false, which get_links(..., $cache_titles=true) treats as "no access" and silently
 * drops the link (Link.php's title()-as-ACL-filter at the "remove links, current user
 * has no access, from result" loop).
 */
class LinkUrlTest extends \EGroupware\Api\AppTest
{
	const URL = 'https://www.egroupware.org/';
	const REMARK = 'EGroupware homepage';

	/** @var int|null timesheet entry the links hang off, cleaned up in tearDown */
	private $ts_id;

	protected function setUp(): void
	{
		$so = new Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_url_link_'.bin2hex(random_bytes(6)),
			'ts_start'    => time(),
			'ts_duration' => 60,
			'ts_quantity' => 1.0,
			'ts_owner'    => $GLOBALS['egw_info']['user']['account_id'],
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $GLOBALS['egw_info']['user']['account_id'],
		);
		$so->save();
		$this->ts_id = (int)$so->data['ts_id'];
	}

	protected function tearDown(): void
	{
		if ($this->ts_id)
		{
			Api\Link\Storage::unlink(0, 'timesheet', $this->ts_id);
			(new Base('timesheet', 'egw_timesheet'))->delete(array('ts_id' => $this->ts_id));
			$this->ts_id = null;
		}
	}

	/**
	 * Pass criteria: a 'url' link round-trips through storage and shows up in get_links()
	 * with the URL as its id and app=Link::URL_APPNAME, remark preserved as the label.
	 */
	public function testLinkStoresAndRetrievesUrl()
	{
		$link_id = Api\Link\Storage::link('timesheet', $this->ts_id, Api\Link::URL_APPNAME, self::URL, self::REMARK);
		$this->assertNotFalse($link_id, 'Link::link() failed to store the URL link');

		$links = Api\Link::get_links('timesheet', $this->ts_id, Api\Link::URL_APPNAME);
		$this->assertCount(1, $links);
		$this->assertEquals(self::URL, array_shift($links), 'get_links() with only_app=url must return the URL as the id');

		// full row, to check app/remark too
		$links = Api\Link::get_links('timesheet', $this->ts_id);
		$this->assertCount(1, $links);
		$link = array_shift($links);
		$this->assertEquals(Api\Link::URL_APPNAME, $link['app']);
		$this->assertEquals(self::URL, $link['id']);
		$this->assertEquals(self::REMARK, $link['remark']);
	}

	/**
	 * Pass criteria: Link::title() returns the URL itself instead of falling through to
	 * the unregistered-app branch (which would return false).
	 */
	public function testTitleReturnsUrlItself()
	{
		Api\Link\Storage::link('timesheet', $this->ts_id, Api\Link::URL_APPNAME, self::URL, self::REMARK);

		$this->assertEquals(self::URL, Api\Link::title(Api\Link::URL_APPNAME, self::URL));
	}

	/**
	 * Regression test: get_links(..., $cache_titles=true) uses title() to filter out links
	 * the user has no access to - a falsy title() for an unregistered app ('url' has none)
	 * would silently drop the url link here, even though the current user obviously has
	 * "access" to a plain URL string.
	 */
	public function testCacheTitlesDoesNotDropUrlLink()
	{
		Api\Link\Storage::link('timesheet', $this->ts_id, Api\Link::URL_APPNAME, self::URL, self::REMARK);

		$links = Api\Link::get_links('timesheet', $this->ts_id, '', 'link_lastmod DESC, link_id DESC', true);

		$this->assertCount(1, $links, 'A url link must survive the cache_titles=true title()-based access filter');
	}

	/**
	 * Pass criteria: link_id2 was widened to varchar(1024) specifically to hold real URLs -
	 * make sure a long one (with a query string, close to the new limit) round-trips intact
	 * instead of being silently truncated.
	 */
	public function testLongUrlRoundTrips()
	{
		$long_url = 'https://example.com/path?'.str_repeat('a=1&', 248).'end=1';	// 1023 chars
		$this->assertGreaterThan(1000, strlen($long_url), 'test setup: URL is not actually long');
		$this->assertLessThanOrEqual(1024, strlen($long_url), 'test setup: URL exceeds the column size');

		Api\Link\Storage::link('timesheet', $this->ts_id, Api\Link::URL_APPNAME, $long_url);

		$links = Api\Link::get_links('timesheet', $this->ts_id, Api\Link::URL_APPNAME);
		$this->assertEquals($long_url, array_shift($links), 'Long URL was truncated on the round-trip through egw_links');
	}

	/**
	 * Pass criteria: unlink() removes a 'url' link like any other link.
	 */
	public function testUnlinkRemovesUrl()
	{
		Api\Link\Storage::link('timesheet', $this->ts_id, Api\Link::URL_APPNAME, self::URL);
		$this->assertCount(1, Api\Link::get_links('timesheet', $this->ts_id));

		Api\Link\Storage::unlink(0, 'timesheet', $this->ts_id, 0, Api\Link::URL_APPNAME, self::URL);

		$this->assertCount(0, Api\Link::get_links('timesheet', $this->ts_id));
	}
}
