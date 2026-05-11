<?php
/**
 * CalDAV tests for recurrence / exception participant changes.
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once __DIR__.'/../../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;
use GuzzleHttp\RequestOptions;

class RecurrenceExceptionParticipantTest extends CalDAVTest
{
	protected const ATTENDEE1_MAIL = 'participant-one@example.org';
	protected const ATTENDEE2_MAIL = 'participant-two@example.org';

	/**
	 * Created cal_ids to clean up.
	 *
	 * @var int[]
	 */
	protected static $cal_ids = [];

	/**
	 * Created UIDs to clean up.
	 *
	 * @var string[]
	 */
	protected static $uids = [];

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
	}

	public static function tearDownAfterClass() : void
	{
		if(!empty($GLOBALS['egw']) && !empty($GLOBALS['egw']->db))
		{
			$so = new \calendar_so();
			foreach(array_unique(self::$cal_ids) as $cal_id)
			{
				if((int)$cal_id > 0)
				{
					$so->delete((int)$cal_id);
				}
			}
			foreach(array_unique(self::$uids) as $uid)
			{
				foreach(array_keys($so->read($uid) ?: []) as $cal_id)
				{
					$so->delete((int)$cal_id);
				}
			}
		}
		self::$cal_ids = [];
		self::$uids = [];

		parent::tearDownAfterClass();
	}

	/**
	 * Extract numeric cal_id from ETag header.
	 */
	protected function addCalendarID($response) : int
	{
		$etag = $response->getHeader('ETag')[0] ?? '';
		$array = explode(':', trim($etag, '[]"'));
		$cal_id = !empty($array[0]) ? (int)$array[0] : 0;
		if($cal_id > 0)
		{
			self::$cal_ids[] = $cal_id;
		}
		return $cal_id;
	}

	/**
	 * Organizer account_lid from phpunit config.
	 */
	protected function organizerLid() : string
	{
		return $GLOBALS['EGW_USER'];
	}

	/**
	 * Organizer email used in iCal payload.
	 */
	protected function organizerMail() : string
	{
		return !empty($GLOBALS['egw_info']['user']['account_email']) ?
			$GLOBALS['egw_info']['user']['account_email'] :
			$this->organizerLid().'@example.org';
	}

	/**
	 * Build event URL in organizer calendar.
	 */
	protected function eventUrl(string $uid) : string
	{
		return '/'.$this->organizerLid().'/calendar/'.$uid.'.ics';
	}

	/**
	 * Build event URL in a specific user's calendar.
	 */
	protected function eventUrlFor(string $user, string $uid) : string
	{
		return '/'.$user.'/calendar/'.$uid.'.ics';
	}

	/**
	 * Generate unique test UID.
	 */
	protected function makeUid(string $prefix) : string
	{
		$uid = $prefix.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(2));
		self::$uids[] = $uid;
		return $uid;
	}

	/**
	 * Create recurring master event in organizer calendar via CalDAV PUT.
	 */
	protected function putEvent(string $uid, string $ical, ?string $user=null) : int
	{
		$user = $user ?: $this->organizerLid();
		$response = $this->getClient($user)->put($this->url($this->eventUrlFor($user, $uid)), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation',
			],
			RequestOptions::BODY => $ical,
		]);
		$this->assertHttpStatus([200, 201], $response);
		return $this->addCalendarID($response);
	}

	protected function getEventIcal(string $uid, ?string $user=null) : string
	{
		$user = $user ?: $this->organizerLid();
		$response = $this->getClient($user)->get($this->url($this->eventUrlFor($user, $uid)));
		$this->assertHttpStatus(200, $response);
		return (string)$response->getBody();
	}

	protected function unfoldIcal(string $ical) : string
	{
		return preg_replace("/\r\n[ \t]/", '', $ical);
	}

	/**
	 * Master event with daily recurrence and one attendee.
	 */
	protected function recurringIcal(string $uid, string $extra_components='') : string
	{
		$attendee1_mail = self::ATTENDEE1_MAIL;
		return "BEGIN:VCALENDAR\r\n".
			"VERSION:2.0\r\n".
			"PRODID:-//EGroupware//CalDAV Test//EN\r\n".
			"BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"DTSTAMP:20260511T100000Z\r\n".
			"DTSTART:20300101T090000Z\r\n".
			"DTEND:20300101T100000Z\r\n".
			"RRULE:FREQ=DAILY;COUNT=6\r\n".
			"SUMMARY:Recurring participant test\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:$attendee1_mail\r\n".
			"END:VEVENT\r\n".
			$extra_components.
			"END:VCALENDAR\r\n";
	}

	/**
	 * Create one exception shifted to different date/time.
	 */
	protected function exceptionComponent(string $uid, string $attendee_partstat='NEEDS-ACTION', bool $include_attendee2=false) : string
	{
		$attendee1_mail = self::ATTENDEE1_MAIL;
		$attendee2 = $include_attendee2 ?
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE2_MAIL."\r\n" : '';

		return "BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"RECURRENCE-ID:20300102T090000Z\r\n".
			"DTSTART:20300103T120000Z\r\n".
			"DTEND:20300103T130000Z\r\n".
			"SUMMARY:Recurring participant test exception\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=$attendee_partstat;ROLE=REQ-PARTICIPANT:mailto:$attendee1_mail\r\n".
			$attendee2.
			"END:VEVENT\r\n";
	}

	/**
	 * Change status of participants in different recurrences.
	 */
	public function testChangeStatusOfParticipantsInDifferentRecurrences()
	{
		$this->markTestIncomplete("Not working");
		$uid = $this->makeUid('caldav-recur-status');

		// Two overridden instances with different attendee status.
		$overrides =
			"BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"RECURRENCE-ID:20300102T090000Z\r\n".
			"DTSTART:20300102T091000Z\r\n".
			"DTEND:20300102T101000Z\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"END:VEVENT\r\n".
			"BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"RECURRENCE-ID:20300103T090000Z\r\n".
			"DTSTART:20300103T092000Z\r\n".
			"DTEND:20300103T102000Z\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=TENTATIVE;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"END:VEVENT\r\n";

		$this->putEvent($uid, $this->recurringIcal($uid, $overrides));

		$ical = $this->unfoldIcal($this->getEventIcal($uid));
		$this->assertStringContainsString("RECURRENCE-ID:20300102T090000Z", $ical);
		$this->assertStringContainsString("RECURRENCE-ID:20300103T090000Z", $ical);
		$this->assertStringContainsString("PARTSTAT=ACCEPTED;ROLE=REQ-PARTICIPANT", $ical);
		$this->assertStringContainsString("PARTSTAT=TENTATIVE;ROLE=REQ-PARTICIPANT", $ical);
		$this->assertStringContainsString("PARTSTAT=NEEDS-ACTION;CUTYPE=INDIVIDUAL;RSVP=", $ical);
	}

	/**
	 * Create exception with different date and time via CalDAV.
	 */
	public function testCreateExceptionsWithDifferentDateAndTime()
	{
		$uid = $this->makeUid('caldav-exception-datetime');
		$this->putEvent($uid, $this->recurringIcal($uid, $this->exceptionComponent($uid)));

		$ical = $this->unfoldIcal($this->getEventIcal($uid));
		$this->assertStringContainsString("RECURRENCE-ID:20300102T090000Z", $ical);
		$this->assertStringContainsString("DTSTART:20300103T120000Z", $ical);
		$this->assertStringContainsString("DURATION:PT1H", $ical);
	}

	/**
	 * Change status of participants in exception via CalDAV.
	 */
	public function testChangeStatusOfParticipantsInException()
	{
		$uid = $this->makeUid('caldav-exception-status');

		// Exception attendee accepted while master attendee remains needs-action.
		$this->putEvent($uid, $this->recurringIcal($uid, $this->exceptionComponent($uid, 'ACCEPTED')));

		$ical_after = $this->unfoldIcal($this->getEventIcal($uid));
		$this->assertStringContainsString("RECURRENCE-ID:20300102T090000Z", $ical_after);
		$this->assertStringContainsString("ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED", $ical_after);
		$this->assertStringContainsString("PARTSTAT=NEEDS-ACTION;CUTYPE=INDIVIDUAL;RSVP=", $ical_after);
	}

	/**
	 * Add participants to recurrence without adding them to exception.
	 */
	public function testAddParticipantsToRecurrenceNotInException()
	{
		$uid = $this->makeUid('caldav-add-participant-not-in-exc');
		$attendee2_mail = self::ATTENDEE2_MAIL;
		$ical_master_only = "BEGIN:VCALENDAR\r\n".
			"VERSION:2.0\r\n".
			"PRODID:-//EGroupware//CalDAV Test//EN\r\n".
			"BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"DTSTAMP:20260511T100000Z\r\n".
			"DTSTART:20300101T090000Z\r\n".
			"DTEND:20300101T100000Z\r\n".
			"RRULE:FREQ=DAILY;COUNT=6\r\n".
			"SUMMARY:Recurring participant test\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:$attendee2_mail\r\n".
			"END:VEVENT\r\n".
			$this->exceptionComponent($uid, 'NEEDS-ACTION', false).
			"END:VCALENDAR\r\n";
		$this->putEvent($uid, $ical_master_only);

		$ical_before = $this->unfoldIcal($this->getEventIcal($uid));
		$this->assertStringContainsString("mailto:$attendee2_mail", $ical_before);
		$exception_pos = strpos($ical_before, "RECURRENCE-ID:20300102T090000Z");
		$this->assertNotFalse($exception_pos, 'Exception block missing');
		$this->assertFalse(
			strpos(substr($ical_before, $exception_pos), "mailto:$attendee2_mail"),
			'Added attendee should not be present in exception block'
		);
	}

	/**
	 * Add participants to recurrence and also add them to exception.
	 */
	public function testAddParticipantsToRecurrenceAndException()
	{
		$this->markTestIncomplete("Not working");
		$uid = $this->makeUid('caldav-add-participant-in-exc');
		$attendee2_mail = self::ATTENDEE2_MAIL;

		$master_with_added = "BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"DTSTAMP:20260511T100000Z\r\n".
			"DTSTART:20300101T090000Z\r\n".
			"DTEND:20300101T100000Z\r\n".
			"RRULE:FREQ=DAILY;COUNT=6\r\n".
			"SUMMARY:Recurring participant test\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:$attendee2_mail\r\n".
			"END:VEVENT\r\n";
		$exception_with_added = "BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"RECURRENCE-ID:20300102T090000Z\r\n".
			"DTSTART:20300103T120000Z\r\n".
			"DTEND:20300103T130000Z\r\n".
			"SUMMARY:Recurring participant test exception\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:$attendee2_mail\r\n".
			"END:VEVENT\r\n";
		$this->putEvent($uid, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//EGroupware//CalDAV Test//EN\r\n".$master_with_added.$exception_with_added."END:VCALENDAR\r\n");
		$ical_after = $this->unfoldIcal($this->getEventIcal($uid));
		$this->assertStringContainsString("RECURRENCE-ID:20300102T090000Z", $ical_after);
		$exception_pos = strpos($ical_after, "RECURRENCE-ID:20300102T090000Z");
		$this->assertNotFalse($exception_pos, 'Exception block missing');
		$this->assertNotFalse(
			strpos(substr($ical_after, $exception_pos), "mailto:$attendee2_mail"),
			'Added attendee should be present in exception block'
		);
	}
}
