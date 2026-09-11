<?php
/**
 * EGroupware Api: regression test for a negated only_app ("!appname") link filter
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Etemplate\Widget;
use EGroupware\Api\Storage\Base;

require_once realpath(__DIR__.'/../AppTest.php');

/**
 * only_app may be negated ("!projectmanager" = every app except projectmanager), and
 * Link\Storage::get_links() has always implemented that - but the layers above it assumed
 * only_app is a plain app name, which broke the negated form in three ways:
 *
 * - Etemplate\Widget\Link::ajax_link_list() (what et2-link-string calls) passed it on to
 *   Api\Link::get_links_multiple() as an app name, together with the array of complete link
 *   arrays a negated filter returns - fatal, so the widget stayed empty.
 * - Api\Link::get_links() aggregated titles under the literal "!appname" key, so no title
 *   was ever pre-fetched for the apps actually in the result.
 * - Api\Link::get_links()'s not-yet-saved branch had substr($offset, $string) swapped, a PHP 8
 *   TypeError, and reduced the links to bare IDs even though they can be from any app.
 *
 * A negated filter must return complete link arrays (mixed apps, so an ID alone says nothing),
 * where a positive one still returns bare IDs.
 */
class LinkNegatedOnlyAppTest extends \EGroupware\Api\AppTest
{
	/** @var int|null timesheet entry the links hang off, cleaned up in tearDown */
	private $ts_id;
	/** @var int|null contact linked to it, cleaned up in tearDown */
	private $contact_id;

	protected function setUp(): void
	{
		$so = new Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_only_app_'.bin2hex(random_bytes(6)),
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

		$contacts = new Api\Contacts();
		$contact = array(
			'n_family' => 'phpunit_only_app_'.bin2hex(random_bytes(6)),
			'n_given'  => 'Test',
			'tid'      => 'n',
			'owner'    => $GLOBALS['egw_info']['user']['account_id'],
		);
		$this->contact_id = $contacts->save($contact) ? $contact['id'] : null;
		if (!$this->contact_id)
		{
			$this->markTestSkipped('Could not create the contact to link to');
		}

		// via the storage layer: Api\Link::link() would queue a notification that only runs at
		// shutdown, by which time the test environment is torn down and timesheet_bo can no
		// longer be constructed.  Only the read path is under test here.
		Api\Link\Storage::link('timesheet', $this->ts_id, 'addressbook', $this->contact_id);
	}

	protected function tearDown(): void
	{
		if ($this->ts_id)
		{
			Api\Link\Storage::unlink(0, 'timesheet', $this->ts_id);
			(new Base('timesheet', 'egw_timesheet'))->delete(array('ts_id' => $this->ts_id));
			$this->ts_id = null;
		}
		if ($this->contact_id)
		{
			(new Api\Contacts())->delete($this->contact_id, false);
			$this->contact_id = null;
		}
	}

	/**
	 * Pass criteria: an app that is not linked, negated, keeps the link - and delivers it as a
	 * complete array, since a negated filter can return links from any number of apps.
	 */
	public function testNegatedOnlyAppKeepsOtherApps()
	{
		$links = Api\Link::get_links('timesheet', $this->ts_id, '!projectmanager');

		$this->assertCount(1, $links, 'Excluding projectmanager must not drop the addressbook link');
		$link = array_shift($links);
		$this->assertIsArray($link, 'A negated only_app must return complete links, not bare IDs');
		$this->assertEquals('addressbook', $link['app']);
		$this->assertEquals($this->contact_id, $link['id']);
	}

	/**
	 * Pass criteria: negating the one app that IS linked leaves nothing.
	 */
	public function testNegatedOnlyAppExcludesItsApp()
	{
		$this->assertEmpty(Api\Link::get_links('timesheet', $this->ts_id, '!addressbook'),
			'The negated app itself must be excluded');
	}

	/**
	 * Pass criteria: the positive form still reduces to bare IDs - the negated-filter fix must
	 * not change what a plain app name returns, several callers rely on the ID-only shape.
	 */
	public function testPositiveOnlyAppStillReturnsBareIds()
	{
		$links = Api\Link::get_links('timesheet', $this->ts_id, 'addressbook');

		$this->assertCount(1, $links);
		$this->assertEquals($this->contact_id, array_shift($links));
	}

	/**
	 * Pass criteria: ajax_link_list() - the entry point et2-link-string uses - answers with the
	 * links instead of fataling on "Cannot access offset of type array in isset or empty".
	 */
	public function testAjaxLinkListHandlesNegatedOnlyApp()
	{
		Api\Json\Response::get()->initResponseArray();
		Widget\Link::ajax_link_list(array(
			'to_app'       => 'timesheet',
			'to_id'        => $this->ts_id,
			'only_app'     => '!projectmanager',
			'show_deleted' => false,
			'limit'        => array(0, 20),
		));
		$response = Api\Json\Response::get()->initResponseArray();

		$data = null;
		foreach($response as $part)
		{
			if ($part['type'] === 'data') $data = $part['data'];
		}
		$this->assertNotNull($data, 'ajax_link_list() sent no data response');
		$dump = json_encode($data);
		unset($data['total']);
		$this->assertCount(1, $data, 'Expected exactly the addressbook link, got: '.$dump);
		$link = array_shift($data);
		$this->assertEquals('addressbook', $link['app']);
		$this->assertEquals($this->contact_id, $link['id']);
		$this->assertNotEmpty($link['title'], 'Title must still be resolved for a negated only_app');
	}

	/**
	 * Links of an entry that has not been saved yet, in the shape Api\Link::get_links() detects
	 * by its [0] element: a list of link arrays instead of an entry ID.
	 */
	private static function notYetSavedLinks() : array
	{
		return array(
			0 => array('link_id' => 1, 'app' => 'addressbook',    'id' => '11'),
			1 => array('link_id' => 2, 'app' => 'projectmanager', 'id' => '22'),
		);
	}

	/**
	 * Pass criteria: the not-yet-saved branch (an edit dialog before the first save passes its
	 * links in as an array, instead of an entry ID) filters instead of throwing a TypeError.
	 */
	public function testNegatedOnlyAppOnNotYetSavedLinks()
	{
		$links = Api\Link::get_links('timesheet', self::notYetSavedLinks(), '!projectmanager');

		$this->assertCount(1, $links);
		$link = array_shift($links);
		$this->assertIsArray($link, 'A negated only_app must keep the app, IDs alone are ambiguous');
		$this->assertEquals('addressbook', $link['app']);
		$this->assertEquals('11', $link['id']);
	}

	/**
	 * Pass criteria: same branch, positive filter - still reduced to bare IDs.
	 */
	public function testPositiveOnlyAppOnNotYetSavedLinks()
	{
		$this->assertEquals(array(1 => '11'),
			Api\Link::get_links('timesheet', self::notYetSavedLinks(), 'addressbook'));
	}
}
