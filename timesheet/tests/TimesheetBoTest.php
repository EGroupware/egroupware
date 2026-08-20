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
	/** @var int|null ts_id created by the current test, cleaned up in tearDown */
	private $ts_id;

	protected function tearDown(): void
	{
		if ($this->ts_id)
		{
			$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
			$so->delete(array('ts_id' => $this->ts_id));
			$this->ts_id = null;
		}
	}

	/**
	 * Insert a timesheet row directly (bypassing timesheet_bo::save()'s own, now-fixed
	 * ownership guard) owned by an account the current test-user has no ACL grant for.
	 */
	private function createVictimTimesheet(int $victim_owner): int
	{
		$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_victim_'.bin2hex(random_bytes(6)),
			'ts_start'    => time(),
			'ts_duration' => 60,
			'ts_quantity' => 1.0,
			'ts_owner'    => $victim_owner,
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $victim_owner,
		);
		$so->save();

		return (int)$so->data['ts_id'];
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

		$this->ts_id = $this->createVictimTimesheet($victim_owner);

		$bo = new timesheet_bo();
		// simulate the vulnerable flow: attacker-forged in-memory data claiming
		// ownership of an existing victim record identified by its real ts_id
		$bo->data = array('ts_id' => $this->ts_id, 'ts_owner' => $account_id);

		$this->assertFalse((bool)$bo->check_acl(Acl::EDIT),
			'check_acl() must verify against the persisted owner, not forged in-memory data');

		$result = $bo->save();
		$this->assertNotEquals(0, $result, 'save() must refuse to overwrite another owner\'s timesheet entry');

		$still = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
		$row = $still->read($this->ts_id);
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
		$this->ts_id = $this->createVictimTimesheet($account_id);

		$bo = new timesheet_bo();
		$bo->data = array('ts_id' => $this->ts_id, 'ts_owner' => $account_id);

		$this->assertTrue((bool)$bo->check_acl(Acl::EDIT), 'owner must still be able to edit their own entry');

		$bo->data['ts_title'] = 'phpunit_edited_'.bin2hex(random_bytes(6));
		$this->assertSame(0, $bo->save(), 'save() must succeed for the entry\'s real owner');
	}
}
