<?php
/**
 * EGroupware calendar: regression test for ajax_status()'s ACL-bypassing set_status() call
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

/**
 * Regression coverage for GHSA-q664-h4x6-phxc: calendar_uiforms::ajax_status($eventId, $uid,
 * $status) called set_status(..., $ignore_acl=true) with a HARDCODED true, which short-circuits
 * calendar_boupdate::set_status()'s own $this->check_status_perms($uid,$event) gate. Since $uid
 * is fully request-controlled (not derived from the session), any caller with only READ access to
 * an event (eg. a fellow participant) could forge the Accept/Reject/Tentative status of ANY OTHER
 * co-participant, persisted and notified.
 *
 * Fix (commit 508cd8bf05, "remove hardcoded ignore_acl parameter", predates the advisory by over
 * a month): ajax_status() now calls set_status($event['id'],$uid,$status,$date) with no 5th
 * argument, so set_status()'s own default ($ignore_acl=false) applies and check_status_perms()
 * runs as normal.
 *
 * Pass criterion: a plain participant (no EDIT rights on a co-participant's calendar) calling
 * ajax_status() to change that OTHER participant's status must not actually change it - the
 * write must never reach the datastore, not just fail silently in a way that still saves.
 */
class AjaxStatusIgnoreAclTest extends \EGroupware\Api\AppTest
{
	/**
	 * @var \calendar_boupdate
	 */
	protected $bo;
	protected $event_id;
	protected $victim_account_id;

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
		$this->victim_account_id = $this->make_test_user('ajax_status_ignore_acl_victim');
	}

	protected function tearDown() : void
	{
		if ($this->event_id)
		{
			$this->bo->delete($this->event_id, 0, true);
		}
		if ($this->victim_account_id)
		{
			$this->asAdmin(function()
			{
				$GLOBALS['egw']->accounts->delete($this->victim_account_id);
			});
		}
		parent::tearDown();
	}

	public function testForgingCoParticipantStatusDoesNotPersist()
	{
		$this->assertNotEmpty($this->victim_account_id, 'Did not create victim test user');

		$me = $GLOBALS['egw_info']['user']['account_id'];
		$event = [
			'title' => 'AjaxStatusIgnoreAclTest event '.bin2hex(random_bytes(4)),
			'start' => new Api\DateTime(time() + 3600, Api\DateTime::$server_timezone),
			'end' => new Api\DateTime(time() + 7200, Api\DateTime::$server_timezone),
			'owner' => $me,
			'participants' => [
				$me => 'A1',
				$this->victim_account_id => 'U',
			],
		];
		$this->event_id = $this->bo->save($event);
		$this->assertNotFalse($this->event_id, 'Did not create test event');

		$uiforms = new \calendar_uiforms();
		// current (non-admin, non-owner-of-victim's-calendar) session forges the victim's RSVP
		$uiforms->ajax_status($this->event_id, $this->victim_account_id, 'R');

		$after = $this->bo->read($this->event_id);
		$this->assertSame('U', substr($after['participants'][$this->victim_account_id], 0, 1),
			'A plain participant must not be able to change ANOTHER participant\'s RSVP status - '.
			'if this is "R", the ACL bypass has regressed');
	}

	/**
	 * @return int|false new test-user account_id
	 */
	protected function make_test_user($lid)
	{
		if(($account_id = $GLOBALS['egw']->accounts->name2id($lid)))
		{
			$this->asAdmin(function() use ($account_id)
			{
				$GLOBALS['egw']->accounts->delete($account_id);
			});
		}
		return $this->asAdmin(function() use ($lid)
		{
			$command = new \admin_cmd_edit_user(false, [
				'account_lid' => $lid,
				'account_firstname' => 'AjaxStatusIgnoreAcl',
				'account_lastname' => 'Victim',
			]);
			$command->comment = 'Needed for unit test '.$this->name();
			$command->run();
			return $command->account;
		});
	}
}
