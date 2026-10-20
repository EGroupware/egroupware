<?php
/**
 * EGroupware calendar: regression test for calendar_bo::read() with a keyed id plus a raw fragment
 *
 * @link https://www.egroupware.org
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * calendar_groupdav.inc.php calls $this->bo->read() with an array like
 * ['cal_uid' => $uid, 'cal_reference=0'] (or [$column => $id, 'cal_reference=0']) in several places
 * (its own read(), and the UID-match fallback in put()) - a keyed entry plus one unkeyed raw-SQL
 * entry. calendar_so::read()'s branch test (isset($ids[count($ids)-1])) is false for this shape (the
 * array has 2 elements but no integer key 1), so it falls into the "column => value pairs" branch
 * and the raw fragment reaches column_data_implode() with an integer key - exactly what
 * Api\Db::requireSqlFragmentObjects() (enabled on calendar's own Db instance) now guards. This
 * shape was missed when calendar_so.inc.php/calendar_boupdate.inc.php were converted, since it
 * lives in calendar_groupdav.inc.php; only showed up in CI's real-HTTP CalDAV tests, not local
 * PHPUnit runs that never go through the groupdav HTTP layer.
 */
class ReadWithTrailingRawFragmentTest extends LoggedInTest
{
	/** @var \calendar_boupdate */
	protected $bo;
	protected $event_id;

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
	}

	protected function tearDown() : void
	{
		if ($this->event_id)
		{
			$this->bo->delete($this->event_id, 0, true);
		}
		parent::tearDown();
	}

	public function testReadByUidWithTrailingRawFragmentDoesNotThrow()
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];
		$uid = 'ReadWithTrailingRawFragmentTest-'.bin2hex(random_bytes(4));
		$this->event_id = $this->bo->save([
			'title'        => 'ReadWithTrailingRawFragmentTest',
			'uid'          => $uid,
			'start'        => new Api\DateTime(time() + 3600, Api\DateTime::$server_timezone),
			'end'          => new Api\DateTime(time() + 7200, Api\DateTime::$server_timezone),
			'owner'        => $me,
			'participants' => [$me => 'A'],
		]);
		$this->assertNotFalse($this->event_id, 'could not create the test event');

		// the exact shape calendar_groupdav uses: a keyed column plus one unkeyed raw SQL fragment
		$events = $this->bo->read(['cal_uid' => $uid, Api\Db\SqlFragment::raw('cal_reference=0')], null, false, 'server');

		$this->assertIsArray($events, 'must not throw, and must return the matching event(s)');
		$this->assertNotEmpty($events, 'the event saved above must be found by its uid');
	}

	public function testReadByColumnWithTrailingRawFragmentDoesNotThrow()
	{
		$me = $GLOBALS['egw_info']['user']['account_id'];
		$this->event_id = $this->bo->save([
			'title'        => 'ReadWithTrailingRawFragmentTest',
			'start'        => new Api\DateTime(time() + 3600, Api\DateTime::$server_timezone),
			'end'          => new Api\DateTime(time() + 7200, Api\DateTime::$server_timezone),
			'owner'        => $me,
			'participants' => [$me => 'A'],
		]);
		$this->assertNotFalse($this->event_id, 'could not create the test event');

		// the exact shape calendar_groupdav::read() uses for its $column => $id lookup
		$events = $this->bo->read(['cal_id' => $this->event_id, Api\Db\SqlFragment::raw('cal_reference=0')], null, true, 'object');

		$this->assertIsArray($events, 'must not throw, and must return the matching event(s)');
		$this->assertNotEmpty($events, 'the event saved above must be found by its id');
	}
}
