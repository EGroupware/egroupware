<?php
/**
 * EGroupware timesheet: regression test for check_acl()/save() ownership guard
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Acl;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Regression test for the fix to timesheet_bo::check_acl(): before the fix, calling
 * check_acl($required) with no explicit $data checked the ACL against $this->data even
 * when that held not-yet-saved / client-supplied values for an *existing* ts_id, instead
 * of the persisted record. That let a caller forge $this->data['ts_owner'] to their own
 * account id and pass the EDIT check for a timesheet entry they don't actually own.
 */
class TimesheetBoTest extends \EGroupware\Api\AppTest
{
	/** @var int[] ts_ids created by the current test, cleaned up in tearDown */
	private $ts_ids = array();

	protected function tearDown(): void
	{
		foreach ($this->ts_ids as $ts_id)
		{
			$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
			$so->delete(array('ts_id' => $ts_id));
		}
		$this->ts_ids = array();
	}

	/**
	 * Insert a timesheet row directly (bypassing timesheet_bo::save()'s own, now-fixed
	 * ownership guard) owned by an account the current test-user has no ACL grant for.
	 *
	 * @param int $victim_owner owner of the fixture entry
	 * @param int|null $start entry start timestamp, defaults to now
	 * @param int $duration entry duration in minutes
	 * @param int|null $status ts_status to set, or null to leave it unset (DB NULL)
	 */
	private function createTestTimesheet(int $victim_owner, ?int $start = null, int $duration = 60, ?int $status = null): int
	{
		$start ??= time();
		$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_victim_'.bin2hex(random_bytes(6)),
			'ts_start'    => $start,
			'ts_duration' => $duration,
			'ts_quantity' => 1.0,
			'ts_owner'    => $victim_owner,
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $victim_owner,
		);
		if (isset($status))
		{
			$so->data['ts_status'] = $status;
		}
		$so->save();

		return $this->ts_ids[] = (int)$so->data['ts_id'];
	}

	/**
	 * check_acl(Acl::EDIT) must reject an existing entry when the caller has no grant
	 * over its real, persisted owner - even if $this->data['ts_owner'] has already been
	 * overwritten (e.g. by attacker-supplied form content) to the caller's own account id.
	 *
	 * Pass criteria: check_acl() returns false, and save() refuses to persist (returns
	 * a non-zero error) instead of overwriting the victim's entry.
	 */
	public function testCheckAclUsesPersistedOwnerNotForgedInMemoryData()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		// an account id the current test-user has no ACL grant for
		$victim_owner = 999999;

		$ts_id = $this->createTestTimesheet($victim_owner);

		$bo = new timesheet_bo();
		// simulate the vulnerable flow: attacker-forged in-memory data claiming
		// ownership of an existing victim record identified by its real ts_id
		$bo->data = array('ts_id' => $ts_id, 'ts_owner' => $account_id);

		$this->assertFalse((bool)$bo->check_acl(Acl::EDIT),
			'check_acl() must verify against the persisted owner, not forged in-memory data');

		$result = $bo->save();
		$this->assertNotEquals(0, $result, 'save() must refuse to overwrite another owner\'s timesheet entry');

		$still = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
		$row = $still->read($ts_id);
		$this->assertSame($victim_owner, (int)$row['ts_owner'], 'victim record owner must be unchanged');
	}

	/**
	 * Regression check: the fix must not break a user editing their own entry - re-reading
	 * the persisted record for the ACL check must still find the caller as the real owner.
	 *
	 * Pass criteria: check_acl() returns true, and save() succeeds (returns 0).
	 */
	public function testCheckAclStillAllowsEditingOwnEntry()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$ts_id = $this->createTestTimesheet($account_id);

		$bo = new timesheet_bo();
		$bo->data = array('ts_id' => $ts_id, 'ts_owner' => $account_id);

		$this->assertTrue((bool)$bo->check_acl(Acl::EDIT), 'owner must still be able to edit their own entry');

		$bo->data['ts_title'] = 'phpunit_edited_'.bin2hex(random_bytes(6));
		$this->assertSame(0, $bo->save(), 'save() must succeed for the entry\'s real owner');
	}

	/**
	 * get_last_end() must return the latest computed end-time for entries starting today,
	 * even when it belongs to an earlier-starting entry.  The generated owner isolates the
	 * fixture from any timesheets belonging to the logged-in test user.
	 */
	public function testGetLastEndUsesLatestComputedEndTime()
	{
		$bo = new timesheet_bo();
		$owner = random_int(1000000000, 2000000000);
		$bo->grants[$owner] = Acl::READ;
		$early_start = $bo->today + 2 * 3600;

		$this->createTestTimesheet($owner, $early_start, 8 * 60);
		$this->createTestTimesheet($owner, $bo->today + 9 * 3600, 30);

		$last_end = $bo->get_last_end($owner);

		$this->assertInstanceOf(EGroupware\Api\DateTime::class, $last_end,
			'get_last_end() must return an end-time when matching entries exist');
		$this->assertSame($early_start + 8 * 60 * 60, (int)$last_end->format('ts'),
			'get_last_end() must return the maximum of start time plus duration');
	}

	/**
	 * get_last_end($user, $date) must only consider entries on the requested day, and
	 * get_last_end($user) without a $date must still default to today - not to any other
	 * day the owner happens to have entries on.
	 */
	public function testGetLastEndScopesToRequestedDay()
	{
		$bo = new timesheet_bo();
		$owner = random_int(1000000000, 2000000000);
		$bo->grants[$owner] = Acl::READ;
		$yesterday_start = $bo->today - 24 * 3600 + 3 * 3600;
		$today_start = $bo->today + 9 * 3600;

		$this->createTestTimesheet($owner, $yesterday_start, 45);
		$this->createTestTimesheet($owner, $today_start, 30);

		$yesterday = new EGroupware\Api\DateTime($bo->today - 24 * 3600);
		$last_end_yesterday = $bo->get_last_end($owner, $yesterday);
		$this->assertInstanceOf(EGroupware\Api\DateTime::class, $last_end_yesterday,
			'get_last_end() must find an entry on the explicitly requested day');
		$this->assertSame($yesterday_start + 45 * 60, (int)$last_end_yesterday->format('ts'),
			'get_last_end() with an explicit date must use that day\'s entry, not today\'s');

		$last_end_today = $bo->get_last_end($owner);
		$this->assertSame($today_start + 30 * 60, (int)$last_end_today->format('ts'),
			'get_last_end() without a date must default to today');
	}

	/**
	 * get_last_end() must return null, not the entry from a different day, when the owner
	 * has no timesheets on the requested day.
	 */
	public function testGetLastEndReturnsNullForDayWithNoEntries()
	{
		$bo = new timesheet_bo();
		$owner = random_int(1000000000, 2000000000);
		$bo->grants[$owner] = Acl::READ;
		$this->createTestTimesheet($owner, $bo->today + 3600, 30);

		$other_day = new EGroupware\Api\DateTime($bo->today - 5 * 24 * 3600);

		$this->assertNull($bo->get_last_end($owner, $other_day),
			'get_last_end() must not fall back to an entry from a different day');
	}

	/**
	 * search() with filter ts_status => timesheet_bo::ALL_STATUS must return entries that
	 * have any status set, while excluding both entries with no status (NULL) and deleted
	 * entries (ts_status == DELETED_STATUS) - unlike the default/empty filter, which also
	 * matches no-status entries, and unlike the legacy 'all' filter value, which also
	 * matches deleted entries.
	 *
	 * Setup: three fixture entries for a random, isolated owner - one with a real status,
	 * one with no status (NULL), one marked deleted. Pass criteria: search() with the
	 * ALL_STATUS filter returns exactly the entry with a real status.
	 */
	public function testAllStatusFilterExcludesEntriesWithoutStatus()
	{
		$bo = new timesheet_bo();
		$owner = random_int(1000000000, 2000000000);
		$bo->grants[$owner] = Acl::READ;

		$with_status_id = $this->createTestTimesheet($owner, null, 60, 1);
		$this->createTestTimesheet($owner, null, 60, null);
		$this->createTestTimesheet($owner, null, 60, timesheet_bo::DELETED_STATUS);

		$rows = $bo->search('', true, '', '', '', false, 'AND', false,
			array('ts_owner' => $owner, 'ts_status' => timesheet_bo::ALL_STATUS));

		$this->assertIsArray($rows);
		$this->assertCount(1, $rows,
			'ALL_STATUS filter must return only the entry that has a status set');
		$this->assertSame($with_status_id, (int)current($rows)['ts_id'],
			'ALL_STATUS filter must return the entry with a real status, not the no-status or deleted one');
	}

	/**
	 * Call ajax_get_last_end() and pull the "data" part back out of the (process-wide
	 * singleton) JSON response.
	 *
	 * Resets the singleton both before and after: before, because some earlier test
	 * elsewhere in the same PHPUnit process may have left Response::data()'s "only once"
	 * guard set without clearing it, which would otherwise make this call throw even
	 * though it never added a data response of its own; after, so later calls in this
	 * test run don't hit that same guard because of what we just added here.
	 */
	private function callAjaxGetLastEnd(timesheet_ui $ui, $ts_owner, $date)
	{
		EGroupware\Api\Json\Response::get()->initResponseArray();

		$ui->ajax_get_last_end($ts_owner, $date);

		$data = null;
		foreach (EGroupware\Api\Json\Response::get()->initResponseArray() as $part)
		{
			if ($part['type'] === 'data') $data = $part['data'];
		}
		return $data;
	}

	/**
	 * get_last_end() itself must reject an owner the caller has no READ grant for, rather than
	 * happily returning that owner's last end-time - this is enforced in the bo, not just by
	 * the ajax wrapper, so any other caller gets the same protection.
	 */
	public function testGetLastEndRejectsOwnerWithoutGrant()
	{
		$bo = new timesheet_bo();
		$victim_owner = random_int(1000000000, 2000000000);
		$this->createTestTimesheet($victim_owner, $bo->today + 3600, 60);

		$this->expectException(EGroupware\Api\Exception\NoPermission::class);
		$bo->get_last_end($victim_owner);
	}

	/**
	 * ajax_get_last_end() must not leak another owner's last end-time to a caller who has no
	 * READ grant for that owner - it must reject the request outright, instead of silently
	 * substituting the current user (which would look like success to the caller while
	 * quietly serving different data).
	 */
	public function testAjaxGetLastEndRejectsRequestForOwnerWithoutGrant()
	{
		$ui = new timesheet_ui();
		$victim_owner = random_int(1000000000, 2000000000);
		$date = new EGroupware\Api\DateTime('+5 years');

		$this->createTestTimesheet($victim_owner, $date->format('ts') + 3600, 60);

		$this->expectException(EGroupware\Api\Exception\NoPermission::class);
		$this->callAjaxGetLastEnd($ui, $victim_owner, $date->format('Y-m-d'));
	}

	/**
	 * ajax_get_last_end() must use the current user when no owner is given (0/empty) -
	 * only an explicitly requested owner needs the ACL check.
	 */
	public function testAjaxGetLastEndUsesCurrentUserWhenNoOwnerGiven()
	{
		$ui = new timesheet_ui();
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$date = new EGroupware\Api\DateTime('+5 years');
		$start = $date->format('ts') + 3600;

		$this->createTestTimesheet($account_id, $start, 60);

		$result = $this->callAjaxGetLastEnd($ui, 0, $date->format('Y-m-d'));

		$this->assertSame((new EGroupware\Api\DateTime($start + 60 * 60))->format('H:i'), $result,
			'ajax_get_last_end() must default to the current user\'s own last end-time when no owner is given');
	}

	/**
	 * ajax_get_last_end() must return null (not throw/fatal) for a date string it can't parse,
	 * since $date is unvalidated client input.
	 */
	public function testAjaxGetLastEndReturnsNullForUnparsableDate()
	{
		$ui = new timesheet_ui();
		$account_id = $GLOBALS['egw_info']['user']['account_id'];

		$result = $this->callAjaxGetLastEnd($ui, $account_id, 'not-a-date');

		$this->assertNull($result, 'ajax_get_last_end() must tolerate unparsable client-supplied dates');
	}
}
