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
	 * Return full VEVENT block containing a given RECURRENCE-ID.
	 *
	 * EGroupware export order may place RECURRENCE-ID late in the VEVENT, after
	 * ATTENDEE lines. Assertions must therefore inspect the whole VEVENT block,
	 * not only the substring after RECURRENCE-ID.
	 */
	protected function exceptionBlock(string $ical, string $recurrence_id) : string
	{
		$pattern = "/BEGIN:VEVENT\r\n(?:(?!BEGIN:VEVENT).)*RECURRENCE-ID:" .
			preg_quote($recurrence_id, '/') .
			"(?:(?!BEGIN:VEVENT).)*END:VEVENT/s";
		if (preg_match($pattern, $ical, $matches))
		{
			return $matches[0];
		}
		return '';
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
	 *
	 * Strategy / pass criteria:
	 * - Build one recurring master with two explicit overridden recurrences.
	 * - Each override has a different attendee PARTSTAT for attendee1.
	 * - Export via CalDAV GET.
	 * - Pass when both RECURRENCE-ID components are present and each expected
	 *   status variant is present in the exported iCal.
	 *
	 * Uses near-future dynamic UTC timestamps so recurrence expansion stays
	 * inside typical horizon limits across environments.
	 */
	public function testChangeStatusOfParticipantsInDifferentRecurrences()
	{
		$uid = $this->makeUid('caldav-recur-status');
		$master_start = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		$master_start = $master_start->setTime(9, 0, 0)->modify('+14 days');
		$master_end = $master_start->modify('+1 hour');
		$recurrence1 = $master_start->modify('+1 day');
		$recurrence2 = $master_start->modify('+2 days');
		$recurrence1_moved_start = $recurrence1->modify('+10 minutes');
		$recurrence1_moved_end = $recurrence1_moved_start->modify('+1 hour');
		$recurrence2_moved_start = $recurrence2->modify('+20 minutes');
		$recurrence2_moved_end = $recurrence2_moved_start->modify('+1 hour');
		$dtstamp = $master_start->modify('-1 hour')->format('Ymd\THis\Z');
		$master_start_ical = $master_start->format('Ymd\THis\Z');
		$master_end_ical = $master_end->format('Ymd\THis\Z');
		$recurrence1_ical = $recurrence1->format('Ymd\THis\Z');
		$recurrence2_ical = $recurrence2->format('Ymd\THis\Z');
		$recurrence1_moved_start_ical = $recurrence1_moved_start->format('Ymd\THis\Z');
		$recurrence1_moved_end_ical = $recurrence1_moved_end->format('Ymd\THis\Z');
		$recurrence2_moved_start_ical = $recurrence2_moved_start->format('Ymd\THis\Z');
		$recurrence2_moved_end_ical = $recurrence2_moved_end->format('Ymd\THis\Z');

		$master = "BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"DTSTAMP:$dtstamp\r\n".
			"DTSTART:$master_start_ical\r\n".
			"DTEND:$master_end_ical\r\n".
			"RRULE:FREQ=DAILY;COUNT=6\r\n".
			"SUMMARY:Recurring participant test\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"END:VEVENT\r\n";

		// Two overridden instances with different attendee status.
		$overrides =
			"BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"DTSTAMP:$dtstamp\r\n".
			"RECURRENCE-ID:$recurrence1_ical\r\n".
			"DTSTART:$recurrence1_moved_start_ical\r\n".
			"DTEND:$recurrence1_moved_end_ical\r\n".
			"SUMMARY:Recurring participant test exception accepted\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"END:VEVENT\r\n".
			"BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"DTSTAMP:$dtstamp\r\n".
			"RECURRENCE-ID:$recurrence2_ical\r\n".
			"DTSTART:$recurrence2_moved_start_ical\r\n".
			"DTEND:$recurrence2_moved_end_ical\r\n".
			"SUMMARY:Recurring participant test exception tentative\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=TENTATIVE;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"END:VEVENT\r\n";

		$this->putEvent($uid, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//EGroupware//CalDAV Test//EN\r\n".$master.$overrides."END:VCALENDAR\r\n");

		$ical = $this->unfoldIcal($this->getEventIcal($uid));
		$this->assertStringContainsString("RECURRENCE-ID:$recurrence1_ical", $ical);
		$this->assertStringContainsString("RECURRENCE-ID:$recurrence2_ical", $ical);
		$exception1 = $this->exceptionBlock($ical, $recurrence1_ical);
		$exception2 = $this->exceptionBlock($ical, $recurrence2_ical);
		$this->assertNotEmpty($exception1, 'First exception block missing');
		$this->assertNotEmpty($exception2, 'Second exception block missing');
		$this->assertStringContainsString("mailto:".self::ATTENDEE1_MAIL, $exception1);
		$this->assertStringContainsString("mailto:".self::ATTENDEE1_MAIL, $exception2);
		$this->assertStringContainsString("PARTSTAT=ACCEPTED", $exception1);
		$this->assertStringContainsString("PARTSTAT=TENTATIVE", $exception2);
		$this->assertStringContainsString("PARTSTAT=NEEDS-ACTION;CUTYPE=INDIVIDUAL;RSVP=", $ical);
	}

	/**
	 * Create exception with different date and time via CalDAV.
	 *
	 * Strategy / pass criteria:
	 * - Import recurring master plus one exception component linked by
	 *   RECURRENCE-ID.
	 * - Exception moves occurrence time to a different day/hour.
	 * - Export via CalDAV GET.
	 * - Pass when export contains the RECURRENCE-ID and moved DTSTART, and the
	 *   expected one-hour duration.
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
	 *
	 * Strategy / pass criteria:
	 * - Import recurring master plus one exception where attendee1 PARTSTAT is
	 *   ACCEPTED on the exception only.
	 * - Export via CalDAV GET.
	 * - Pass when export contains the exception RECURRENCE-ID and the exception
	 *   participant status, while master/default status remains NEEDS-ACTION.
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
	 *
	 * Strategy / pass criteria:
	 * - Import one VCALENDAR containing a recurring master VEVENT and one
	 *   overridden exception VEVENT for a specific RECURRENCE-ID.
	 * - Add attendee2 only on the master component.
	 * - Export the event again via CalDAV GET.
	 * - Pass when attendee2 exists in the overall export (master side), but is
	 *   absent from the exception VEVENT block.
	 *
	 * This verifies that exception participants are not implicitly inherited from
	 * later master changes when the exception does not carry that participant.
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
		$exception_block = $this->exceptionBlock($ical_before, '20300102T090000Z');
		$this->assertNotEmpty($exception_block, 'Exception block missing');
		$this->assertFalse(
			strpos($exception_block, "mailto:$attendee2_mail"),
			'Added attendee should not be present in exception block'
		);
	}

	/**
	 * Add participants to recurrence and also add them to exception.
	 *
	 * Uses near-future dynamic UTC timestamps so recurrence expansion stays
	 * inside typical horizon limits across environments.
	 *
	 * Strategy / pass criteria:
	 * - Import one VCALENDAR containing a recurring master VEVENT and one
	 *   overridden exception VEVENT for a specific RECURRENCE-ID.
	 * - Add attendee2 on both master and exception components.
	 * - Export the event again via CalDAV GET.
	 * - Pass when the export still contains the exception component and attendee2
	 *   is present inside that exception VEVENT block.
	 *
	 * This verifies participant persistence on detached exceptions, independent
	 * from property serialization order in returned iCalendar data.
	 */
	public function testAddParticipantsToRecurrenceAndException()
	{
		$uid = $this->makeUid('caldav-add-participant-in-exc');
		$attendee2_mail = self::ATTENDEE2_MAIL;
		// Keep this series close to "now" to avoid horizon-dependent false negatives.
		$master_start = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		$master_start = $master_start->setTime(9, 0, 0)->modify('+14 days');
		$master_end = $master_start->modify('+1 hour');
		$exception_original = $master_start->modify('+1 day');
		$exception_start = $master_start->modify('+2 days')->setTime(12, 0, 0);
		$exception_end = $exception_start->modify('+1 hour');
		$dtstamp = $master_start->modify('-1 hour')->format('Ymd\THis\Z');
		$master_start_ical = $master_start->format('Ymd\THis\Z');
		$master_end_ical = $master_end->format('Ymd\THis\Z');
		$exception_original_ical = $exception_original->format('Ymd\THis\Z');
		$exception_start_ical = $exception_start->format('Ymd\THis\Z');
		$exception_end_ical = $exception_end->format('Ymd\THis\Z');

		$master_with_added = "BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"DTSTAMP:$dtstamp\r\n".
			"DTSTART:$master_start_ical\r\n".
			"DTEND:$master_end_ical\r\n".
			"RRULE:FREQ=DAILY;COUNT=6\r\n".
			"SUMMARY:Recurring participant test\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:$attendee2_mail\r\n".
			"END:VEVENT\r\n";
		$exception_with_added = "BEGIN:VEVENT\r\n".
			"UID:$uid\r\n".
			"DTSTAMP:$dtstamp\r\n".
			"RECURRENCE-ID:$exception_original_ical\r\n".
			"DTSTART:$exception_start_ical\r\n".
			"DTEND:$exception_end_ical\r\n".
			"SUMMARY:Recurring participant test exception\r\n".
			"ORGANIZER:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:".$this->organizerMail()."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE1_MAIL."\r\n".
			"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:$attendee2_mail\r\n".
			"END:VEVENT\r\n";
		$this->putEvent($uid, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//EGroupware//CalDAV Test//EN\r\n".$master_with_added.$exception_with_added."END:VCALENDAR\r\n");
		$ical_after = $this->unfoldIcal($this->getEventIcal($uid));
		$this->assertStringContainsString("RECURRENCE-ID:$exception_original_ical", $ical_after);
		$exception_block = $this->exceptionBlock($ical_after, $exception_original_ical);
		$this->assertNotEmpty($exception_block, 'Exception block missing');
		$this->assertNotFalse(
			strpos($exception_block, "mailto:$attendee2_mail"),
			'Added attendee should be present in exception block'
		);
	}
}
