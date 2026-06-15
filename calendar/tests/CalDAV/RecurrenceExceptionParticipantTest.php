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

class RecurrenceExceptionParticipantTest extends CalDAVTest
{
	protected const ATTENDEE1_MAIL = 'participant-one@example.org';
	protected const ATTENDEE2_MAIL = 'participant-two@example.org';
	protected const FIXTURE_VCALENDAR = __DIR__.'/fixtures/recurrence-participant-vcalendar.ics.tpl';
	protected const FIXTURE_MASTER = __DIR__.'/fixtures/recurrence-participant-master.ics.tpl';
	protected const FIXTURE_EXCEPTION = __DIR__.'/fixtures/recurrence-participant-exception.ics.tpl';

	protected function renderVcalendar(array $components) : string
	{
		return $this->renderFixture(self::FIXTURE_VCALENDAR, [
			'{{VEVENTS}}' => implode('', $components),
		]);
	}

	protected function renderMasterComponent(
		string $uid,
		string $dtstamp,
		string $master_start,
		string $master_end,
		bool $include_attendee2=false,
		string $summary='Recurring participant test'
	) : string
	{
		return $this->renderFixture(self::FIXTURE_MASTER, [
			'{{UID}}' => $uid,
			'{{DTSTAMP}}' => $dtstamp,
			'{{MASTER_START}}' => $master_start,
			'{{MASTER_END}}' => $master_end,
			'{{SUMMARY}}' => $summary,
			'{{ORGANIZER_MAIL}}' => $this->organizerMail(),
			'{{ATTENDEE1_MAIL}}' => self::ATTENDEE1_MAIL,
			'{{ATTENDEE2_LINE}}' => $include_attendee2 ?
				"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE2_MAIL."\r\n" : '',
		]);
	}

	protected function renderExceptionComponent(
		string $uid,
		string $dtstamp,
		string $recurrence_id,
		string $exception_start,
		string $exception_end,
		string $attendee1_partstat='NEEDS-ACTION',
		bool $include_attendee2=false,
		string $summary='Recurring participant test exception'
	) : string
	{
		return $this->renderFixture(self::FIXTURE_EXCEPTION, [
			'{{UID}}' => $uid,
			'{{DTSTAMP}}' => $dtstamp,
			'{{RECURRENCE_ID}}' => $recurrence_id,
			'{{EXCEPTION_START}}' => $exception_start,
			'{{EXCEPTION_END}}' => $exception_end,
			'{{SUMMARY}}' => $summary,
			'{{ORGANIZER_MAIL}}' => $this->organizerMail(),
			'{{ATTENDEE1_PARTSTAT}}' => $attendee1_partstat,
			'{{ATTENDEE1_MAIL}}' => self::ATTENDEE1_MAIL,
			'{{ATTENDEE2_LINE}}' => $include_attendee2 ?
				"ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT:mailto:".self::ATTENDEE2_MAIL."\r\n" : '',
		]);
	}

	/**
	 * Master event with daily recurrence and one attendee.
	 */
	protected function recurringIcal(string $uid, string $extra_components='') : string
	{
		$master = $this->renderMasterComponent(
			$uid,
			'20260511T100000Z',
			'20300101T090000Z',
			'20300101T100000Z'
		);
		return $this->renderVcalendar([$master, $extra_components]);
	}

	/**
	 * Create one exception shifted to different date/time.
	 */
	protected function exceptionComponent(string $uid, string $attendee_partstat='NEEDS-ACTION', bool $include_attendee2=false) : string
	{
		return $this->renderExceptionComponent(
			$uid,
			'20260511T100000Z',
			'20300102T090000Z',
			'20300103T120000Z',
			'20300103T130000Z',
			$attendee_partstat,
			$include_attendee2
		);
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

		$master = $this->renderMasterComponent(
			$uid,
			$dtstamp,
			$master_start_ical,
			$master_end_ical
		);

		// Two overridden instances with different attendee status.
		$exception1 = $this->renderExceptionComponent(
			$uid,
			$dtstamp,
			$recurrence1_ical,
			$recurrence1_moved_start_ical,
			$recurrence1_moved_end_ical,
			'ACCEPTED',
			false,
			'Recurring participant test exception accepted'
		);
		$exception2 = $this->renderExceptionComponent(
			$uid,
			$dtstamp,
			$recurrence2_ical,
			$recurrence2_moved_start_ical,
			$recurrence2_moved_end_ical,
			'TENTATIVE',
			false,
			'Recurring participant test exception tentative'
		);

		$this->putEvent($uid, $this->renderVcalendar([$master, $exception1, $exception2]));

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
		$master = $this->renderMasterComponent(
			$uid,
			'20260511T100000Z',
			'20300101T090000Z',
			'20300101T100000Z',
			true
		);
		$exception = $this->exceptionComponent($uid, 'NEEDS-ACTION', false);
		$ical_master_only = $this->renderVcalendar([$master, $exception]);
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

		$master_with_added = $this->renderMasterComponent(
			$uid,
			$dtstamp,
			$master_start_ical,
			$master_end_ical,
			true
		);
		$exception_with_added = $this->renderExceptionComponent(
			$uid,
			$dtstamp,
			$exception_original_ical,
			$exception_start_ical,
			$exception_end_ical,
			'NEEDS-ACTION',
			true
		);
		$this->putEvent($uid, $this->renderVcalendar([$master_with_added, $exception_with_added]));
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
