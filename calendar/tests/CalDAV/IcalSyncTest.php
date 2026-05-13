<?php
/**
 * CalDAV iCal sync tests for recurring master + exception components.
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once __DIR__.'/../../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;

class IcalSyncTest extends CalDAVTest
{
	protected const ATTENDEE1_MAIL = 'participant-one@example.org';
	protected const ATTENDEE2_MAIL = 'participant-two@example.org';

	protected const FIXTURE_INITIAL = __DIR__.'/fixtures/ical-sync-initial.ics.tpl';
	protected const FIXTURE_UPDATED = __DIR__.'/fixtures/ical-sync-updated.ics.tpl';
	protected const FIXTURE_REMOVED = __DIR__.'/fixtures/ical-sync-removed.ics.tpl';

	protected function fixturePayload(string $fixture_file, string $uid) : array
	{
		$template = file_get_contents($fixture_file);
		$this->assertNotFalse($template, "Unable to load fixture $fixture_file");

		$master_start = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		$master_start = $master_start->setTime(9, 0, 0)->modify('+14 days');
		$master_end = $master_start->modify('+1 hour');
		$recurrence_id = $master_start->modify('+1 day');
		$exception_start = $master_start->modify('+2 days')->setTime(12, 0, 0);
		$exception_end = $exception_start->modify('+1 hour');
		$dtstamp = $master_start->modify('-1 hour')->format('Ymd\THis\Z');

		$ical = strtr($template, [
			'{{UID}}' => $uid,
			'{{DTSTAMP}}' => $dtstamp,
			'{{MASTER_START}}' => $master_start->format('Ymd\THis\Z'),
			'{{MASTER_END}}' => $master_end->format('Ymd\THis\Z'),
			'{{RECURRENCE_ID}}' => $recurrence_id->format('Ymd\THis\Z'),
			'{{EXCEPTION_START}}' => $exception_start->format('Ymd\THis\Z'),
			'{{EXCEPTION_END}}' => $exception_end->format('Ymd\THis\Z'),
			'{{ORGANIZER_MAIL}}' => $this->organizerMail(),
			'{{ATTENDEE1_MAIL}}' => self::ATTENDEE1_MAIL,
			'{{ATTENDEE2_MAIL}}' => self::ATTENDEE2_MAIL,
		]);

		return [$ical, $recurrence_id->format('Ymd\THis\Z')];
	}

	/**
	 * Create a recurring event from fixture and verify master + exception exist.
	 *
	 * Pass criteria:
	 * - CalDAV PUT creates event.
	 * - CalDAV GET contains the expected RECURRENCE-ID exception VEVENT.
	 * - Attendee1 is present in both master and exception.
	 */
	public function testIcalSyncCreateFromFixture()
	{
		$uid = $this->makeUid('caldav-ical-sync-create');
		[$ical_create, $recurrence_id] = $this->fixturePayload(self::FIXTURE_INITIAL, $uid);

		$this->putEvent($uid, $ical_create);
		$ical_after = $this->unfoldIcal($this->getEventIcal($uid));

		$this->assertStringContainsString("RECURRENCE-ID:$recurrence_id", $ical_after);
		$this->assertStringContainsString("mailto:".self::ATTENDEE1_MAIL, $ical_after);
		$exception = $this->exceptionBlock($ical_after, $recurrence_id);
		$this->assertNotEmpty($exception, 'Exception block missing after create');
		$this->assertStringContainsString("mailto:".self::ATTENDEE1_MAIL, $exception);
	}

	/**
	 * Verify updated-state sync payload affecting both master and exception.
	 *
	 * Pass criteria:
	 * - Import updated-state fixture directly.
	 * - GET response contains attendee2 in whole event and specifically exception block.
	 */
	public function testIcalSyncUpdateBothSidesFromFixture()
	{
		$uid = $this->makeUid('caldav-ical-sync-update');
		[$ical_update, $recurrence_id] = $this->fixturePayload(self::FIXTURE_UPDATED, $uid);

		$this->putEvent($uid, $ical_update);
		$ical_after = $this->unfoldIcal($this->getEventIcal($uid));

		$this->assertStringContainsString("RECURRENCE-ID:$recurrence_id", $ical_after);
		$this->assertStringContainsString("mailto:".self::ATTENDEE2_MAIL, $ical_after);
		$exception = $this->exceptionBlock($ical_after, $recurrence_id);
		$this->assertNotEmpty($exception, 'Exception block missing after update');
		$this->assertStringContainsString("mailto:".self::ATTENDEE2_MAIL, $exception);
		$this->assertStringContainsString("PARTSTAT=TENTATIVE", $exception);
	}

	/**
	 * Verify removal-state sync payload affecting both master and exception.
	 *
	 * Pass criteria:
	 * - Import removal-state fixture directly.
	 * - GET response no longer contains attendee2 globally nor in exception block.
	 */
	public function testIcalSyncRemovalBothSidesFromFixture()
	{
		$uid = $this->makeUid('caldav-ical-sync-remove');
		[$ical_remove, $recurrence_id] = $this->fixturePayload(self::FIXTURE_REMOVED, $uid);

		$this->putEvent($uid, $ical_remove);
		$ical_after = $this->unfoldIcal($this->getEventIcal($uid));

		$this->assertStringContainsString("RECURRENCE-ID:$recurrence_id", $ical_after);
		$this->assertStringNotContainsString("mailto:".self::ATTENDEE2_MAIL, $ical_after);
		$exception = $this->exceptionBlock($ical_after, $recurrence_id);
		$this->assertNotEmpty($exception, 'Exception block missing after removal update');
		$this->assertStringNotContainsString("mailto:".self::ATTENDEE2_MAIL, $exception);
	}
}
