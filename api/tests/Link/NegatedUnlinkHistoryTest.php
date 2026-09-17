<?php
/**
 * EGroupware Api: regression test for history written under a negated app filter ("!file")
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Storage\Base;

require_once realpath(__DIR__.'/../AppTest.php');

/**
 * Api\Link::unlink() accepts "!file" as its $app2 to mean "unlink everything except the file
 * attachments" - infolog_bo::delete() and projectmanager_bo::delete() both use it to hide an
 * entry's links on a soft-delete.  That is a filter, not the other end of a link.
 *
 * unlink2() stripped it before handing $app2 to the storage layer, but the history-logging
 * right after it kept using the raw value, so every such delete wrote two junk rows:
 * one on the entry itself claiming its link partner was "!file:", and one under the appname
 * "!file" with record_id 0, which no get_rows() will ever ask for again.
 *
 * A negated filter must produce no ~link~ history at all - there is no second endpoint to name.
 */
class LinkNegatedUnlinkHistoryTest extends \EGroupware\Api\AppTest
{
	/** @var int|null timesheet entry the links hang off, cleaned up in tearDown */
	private $ts_id;
	/** @var int|null contact linked to it, cleaned up in tearDown */
	private $contact_id;
	/** @var int highest history_id before the test ran, see tearDown */
	private $max_history_id = 0;

	protected function setUp(): void
	{
		$so = new Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_unlink_history_'.bin2hex(random_bytes(6)),
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
			'n_family' => 'phpunit_unlink_history_'.bin2hex(random_bytes(6)),
			'n_given'  => 'Test',
			'tid'      => 'n',
			'owner'    => $GLOBALS['egw_info']['user']['account_id'],
		);
		$this->contact_id = $contacts->save($contact) ? $contact['id'] : null;
		if (!$this->contact_id)
		{
			$this->markTestSkipped('Could not create the contact to link to');
		}

		// via the storage layer: Api\Link::link() queues a notification that only runs at
		// shutdown, by which time the test environment is gone.  Only unlink is under test.
		Api\Link\Storage::link('timesheet', $this->ts_id, 'addressbook', $this->contact_id);

		// high-water mark: a FAILING run writes exactly the junk rows under test, and nothing
		// keyed to this test can find them again (appname "!file", record_id 0), so remember
		// where the table ended to be able to remove them in tearDown.
		$this->max_history_id = (int)$GLOBALS['egw']->db->select(Api\Storage\History::TABLE,
			'MAX(history_id)', false, __LINE__, __FILE__, false, '', 'phpgwapi')->fetchColumn();
	}

	protected function tearDown(): void
	{
		if ($this->ts_id)
		{
			Api\Link\Storage::unlink(0, 'timesheet', $this->ts_id);
			(new Base('timesheet', 'egw_timesheet'))->delete(array('ts_id' => $this->ts_id));
			(new Api\Storage\History('timesheet'))->delete($this->ts_id);
			$this->ts_id = null;
		}
		if ($this->contact_id)
		{
			(new Api\Contacts())->delete($this->contact_id, false);
			$this->contact_id = null;
		}
		// only ever matches when the regression is back - leave the pre-existing rows alone
		if ($this->max_history_id)
		{
			$GLOBALS['egw']->db->delete(Api\Storage\History::TABLE, array(
				'history_appname' => '!'.Api\Link::VFS_APPNAME,
				'history_id > '.$this->max_history_id,
			), __LINE__, __FILE__, 'phpgwapi');
			$this->max_history_id = 0;
		}
	}

	/**
	 * Read the ~link~ history rows of an appname, optionally for one record only.
	 *
	 * Deliberately NOT via History::search(): that refuses any query without a truthy
	 * record_id, and the junk rows this test guards against have record_id 0 - which is
	 * exactly why they were invisible in the UI and could pile up unnoticed.
	 */
	private function linkHistory(string $appname, $record_id = null) : array
	{
		$where = array('history_appname' => $appname, 'history_status' => '~link~');
		if (isset($record_id)) $where['history_record_id'] = $record_id;

		$rows = array();
		foreach($GLOBALS['egw']->db->select(Api\Storage\History::TABLE, '*', $where,
			__LINE__, __FILE__, false, '', 'phpgwapi') as $row)
		{
			$rows[] = Api\Db::strip_array_keys($row, 'history_');
		}
		return $rows;
	}

	/**
	 * Pass criteria: unlinking with the "!file" filter writes no history row under the
	 * appname "!file" - that is a filter string, never an app.
	 */
	public function testNegatedApp2WritesNoHistoryUnderTheFilterName()
	{
		$before = count($this->linkHistory('!'.Api\Link::VFS_APPNAME));

		$id = $this->ts_id;
		Api\Link::unlink(0, 'timesheet', $id, '', '!'.Api\Link::VFS_APPNAME, '', true);

		$this->assertCount($before, $this->linkHistory('!'.Api\Link::VFS_APPNAME),
			'A negated app-filter must never end up in history_appname');
	}

	/**
	 * Pass criteria: the entry itself gets no ~link~ row naming "!file:" as its link partner
	 * either - both sides of the bogus pair have to go.
	 */
	public function testNegatedApp2WritesNoHistoryOnTheEntry()
	{
		$id = $this->ts_id;
		Api\Link::unlink(0, 'timesheet', $id, '', '!'.Api\Link::VFS_APPNAME, '', true);

		foreach($this->linkHistory('timesheet', $this->ts_id) as $row)
		{
			$this->assertStringNotContainsString('!'.Api\Link::VFS_APPNAME, (string)$row['old_value'],
				'The link partner logged on the entry must be a real app:id, not a filter');
		}
	}

	/**
	 * Pass criteria: the filter still does its job - the links are gone (held for purge), so
	 * suppressing the history must not have suppressed the unlink.
	 */
	public function testNegatedApp2StillUnlinks()
	{
		$id = $this->ts_id;
		Api\Link::unlink(0, 'timesheet', $id, '', '!'.Api\Link::VFS_APPNAME, '', true);

		$this->assertEmpty(Api\Link::get_links('timesheet', $this->ts_id),
			'The links must still be removed, only the history logging changes');
	}

	/**
	 * Pass criteria: a real second app is still logged on both sides - the fix must only skip
	 * the negated form, not turn off ~link~ history in general.
	 */
	public function testRealApp2StillWritesHistoryOnBothSides()
	{
		$id = $this->ts_id;
		Api\Link::unlink(0, 'timesheet', $id, '', 'addressbook', $this->contact_id);

		$contact_id = $this->contact_id;
		$ts_id = $this->ts_id;
		$ours = array_filter($this->linkHistory('timesheet', $ts_id),
			static function($row) use ($contact_id) { return $row['old_value'] === 'addressbook:'.$contact_id; });
		$theirs = array_filter($this->linkHistory('addressbook', $contact_id),
			static function($row) use ($ts_id) { return $row['old_value'] === 'timesheet:'.$ts_id; });

		$this->assertNotEmpty($ours, 'The entry must still log which link was removed');
		$this->assertNotEmpty($theirs, 'The other endpoint must still log it too');

		(new Api\Storage\History('addressbook'))->delete($this->contact_id);
	}
}
