<?php
/**
 * Tests for importing an iCal of an already stored event without attendees, like the calendar subscription does
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;

/**
 * Importing an event, which is already stored and has no attendees (eg. a personal event of a Google calendar), added the
 * participant "" (empty id) with status 'A': "if file contains no participants add current user" used the $user parameter of
 * importVCal(), which is null for the subscription sync. As a stored event then differs from the imported one, every sync
 * saved it again: new egw_cal_user rows (using up auto-increment ids), a bogus "participants" history entry, notifications.
 */
class IcalImportNoParticipantsTest extends Api\LoggedInTest
{
	private ?int $cal_id = null;

	protected function tearDown() : void
	{
		if ($this->cal_id)
		{
			$db = $GLOBALS['egw']->db;
			foreach (['egw_cal_user', 'egw_cal_dates', 'egw_cal_repeats', 'egw_cal'] as $table)
			{
				$db->delete($table, ['cal_id' => $this->cal_id], __LINE__, __FILE__, 'calendar');
			}
			$db->delete('egw_history_log', ['history_appname' => 'calendar', 'history_record_id' => $this->cal_id],
				__LINE__, __FILE__, 'api');
			$this->cal_id = null;
		}
		parent::tearDown();
	}

	private function ics(string $uid, string $summary='no attendees') : string
	{
		$start = (new \DateTimeImmutable('+10 days', new \DateTimeZone('UTC')))->setTime(9, 0);
		return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Google Inc//Google Calendar 70.9054//EN\r\nBEGIN:VEVENT\r\n".
			"DTSTART:".$start->format('Ymd\THis\Z')."\r\nDTEND:".$start->modify('+1 hour')->format('Ymd\THis\Z')."\r\n".
			"UID:$uid\r\nSUMMARY:$summary\r\nDTSTAMP:20261001T080000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	}

	private function emptyParticipantRows() : int
	{
		return (int)$GLOBALS['egw']->db->select('egw_cal_user', 'COUNT(*)',
			['cal_id' => $this->cal_id, 'cal_user_type' => '', 'cal_user_id' => ''],
			__LINE__, __FILE__, false, '', 'calendar')->fetchColumn();
	}

	public function testReimportOfEventWithoutAttendeesAddsNoEmptyParticipant()
	{
		$uid = 'ical-no-attendees-'.uniqid().'@example.org';
		$ical = new \calendar_ical();
		// like Api\CalDAV\Sync does: no $user and skip notifications
		$this->cal_id = (int)$ical->importVCal($this->ics($uid), -1, null, false, 0, '', null, null, null, true);
		$this->assertGreaterThan(0, $this->cal_id, 'event imported');
		$this->assertSame(0, $this->emptyParticipantRows(), 'new event');

		// the next sync imports it again, now the event is stored
		$ical = new \calendar_ical();
		$second = (int)$ical->importVCal($this->ics($uid, 'changed'), -1, null, false, 0, '', null, null, null, true);
		$this->assertSame($this->cal_id, $second, 'same event updated');

		$participants = (new \calendar_bo())->read($this->cal_id)['participants'];
		$this->assertArrayNotHasKey('', $participants, 'no participant with an empty id: '.json_encode($participants));
		$this->assertSame(0, $this->emptyParticipantRows(), 'and no such row in egw_cal_user');
		$this->assertNotEmpty($participants, 'the event still has a participant');
	}

	public function testUnchangedReimportDoesNotChangeTheParticipants()
	{
		$uid = 'ical-no-attendees-2-'.uniqid().'@example.org';
		$ical = new \calendar_ical();
		$this->cal_id = (int)$ical->importVCal($this->ics($uid), -1, null, false, 0, '', null, null, null, true);
		(new \calendar_ical())->importVCal($this->ics($uid), -1, null, false, 0, '', null, null, null, true);
		$after_second = (new \calendar_bo())->read($this->cal_id)['participants'];
		(new \calendar_ical())->importVCal($this->ics($uid), -1, null, false, 0, '', null, null, null, true);
		$after_third = (new \calendar_bo())->read($this->cal_id)['participants'];

		$this->assertEquals($after_second, $after_third, 'participants are stable');
		$history = (int)$GLOBALS['egw']->db->select('egw_history_log', 'COUNT(*)',
			['history_appname' => 'calendar', 'history_record_id' => $this->cal_id, 'history_status' => 'participants'],
			__LINE__, __FILE__, false, '', 'api')->fetchColumn();
		$this->assertLessThanOrEqual(1, $history, 'unchanged imports do not log "participants" changes over and over');
	}

	/**
	 * @return array status and role of the participant $uid of the stored event
	 */
	private function statusAndRole(string $uid) : array
	{
		$participants = (new \calendar_bo())->read($this->cal_id)['participants'];
		$this->assertArrayHasKey($uid, $participants, 'participants: '.json_encode($participants));
		$quantity = null;
		$role = null;
		$status = \calendar_so::split_status($participants[$uid], $quantity, $role);
		return [$status, $role];
	}

	/**
	 * Status and role of the user, who is already a participant of the stored event, are kept: they must not change from
	 * CHAIR to REQ-PARTICIPANT (what the default of combine_status() is) with the first sync after the fix, also not if he
	 * declined or set another role
	 */
	public function testStoredStatusAndRoleOfTheUserAreKept()
	{
		$user = (string)$GLOBALS['egw_info']['user']['account_id'];
		$uid = 'ical-no-attendees-keep-'.uniqid().'@example.org';
		$this->cal_id = (int)(new \calendar_ical())->importVCal($this->ics($uid), -1, null, false, 0, '', null, null, null, true);
		$this->assertGreaterThan(0, $this->cal_id);

		foreach ([['A', 'CHAIR'], ['D', 'OPT-PARTICIPANT'], ['T', 'REQ-PARTICIPANT'], ['A', 'REQ-PARTICIPANT']] as [$status, $role])
		{
			$GLOBALS['egw']->db->update('egw_cal_user', ['cal_status' => $status, 'cal_role' => $role],
				['cal_id' => $this->cal_id, 'cal_user_type' => 'u', 'cal_user_id' => $user], __LINE__, __FILE__, 'calendar');
			(new \calendar_ical())->importVCal($this->ics($uid, 'again '.$status.$role), -1, null, false, 0, '', null, null, null, true);
			$this->assertSame([$status, $role], $this->statusAndRole($user), "stored $status/$role is kept");
		}
	}

	/**
	 * A personal event with just the user in it: he is the organizer, so the role is CHAIR
	 */
	public function testUserNotYetParticipantBecomesTheChair()
	{
		$user = (string)$GLOBALS['egw_info']['user']['account_id'];
		$uid = 'ical-no-attendees-chair-'.uniqid().'@example.org';
		$this->cal_id = (int)(new \calendar_ical())->importVCal($this->ics($uid), -1, null, false, 0, '', null, null, null, true);
		$this->assertGreaterThan(0, $this->cal_id);

		// the stored event has another participant, but not the user
		$db = $GLOBALS['egw']->db;
		$db->delete('egw_cal_user', ['cal_id' => $this->cal_id], __LINE__, __FILE__, 'calendar');
		$db->insert('egw_cal_user', ['cal_id' => $this->cal_id, 'cal_recur_date' => 0, 'cal_user_type' => 'e',
			'cal_user_id' => 'someone@example.org', 'cal_status' => 'U', 'cal_role' => 'REQ-PARTICIPANT'], false,
			__LINE__, __FILE__, 'calendar');

		(new \calendar_ical())->importVCal($this->ics($uid, 'imported again'), -1, null, false, 0, '', null, null, null, true);
		$this->assertSame(['A', 'CHAIR'], $this->statusAndRole($user), 'the user is the CHAIR of his personal event');
		$this->assertSame(0, $this->emptyParticipantRows());
	}
}
