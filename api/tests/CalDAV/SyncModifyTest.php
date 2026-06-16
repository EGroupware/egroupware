<?php
/**
 * Unit tests for CalDAV subscription import modifications.
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage caldav
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once EGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_ical.inc.php';
require_once EGW_INCLUDE_ROOT.'/api/src/CalDAV/Sync.php';

/**
 * Minimal storage object for exercising calendar_ical::importVCal() without DB access.
 */
class SyncModifyTestCalendarStorage
{
	public function isWholeDay(array $event) : bool
	{
		return !empty($event['whole_day']);
	}
}

/**
 * Test double that captures events calendar_ical::importVCal() would store.
 */
class SyncModifyTestCalendarIcal extends \calendar_ical
{
	public array $events = [];
	public array $updated_events = [];

	public function icaltoegw($_vcalData, $principalURL='', $charset=null)
	{
		return $this->events;
	}

	public function get_event_info(&$event)
	{
		return [
			'type' => 'SINGLE',
			'acl_edit' => true,
			'stored_event' => false,
		];
	}

	public function check_perms($needed, $event=0, $other=0, $date_format='ts', $date_to_read=null, $user=null)
	{
		return true;
	}

	public function update(&$event, $ignore_conflicts=false, $touch_modified=true, $ignore_acl=false, $updateTS=true, &$messages=null, $skip_notification=false)
	{
		$this->updated_events[] = $event;
		return 0;
	}

	public function read($ids, \EGroupware\Api\DateTime|int|null $date=null, $ignore_acl=false, $date_format='ts', $clear_private_infos_users=null, $read_recurrence=false)
	{
		return ['id' => $ids] + end($this->updated_events);
	}

	public function update_status($new_event, $old_event, $recur_date=0, $skip_notification=false)
	{
		return null;
	}
}

/**
 * Tests Sync::modify(), which is called for each imported subscription event.
 */
class SyncModifyTest extends TestCase
{
	/**
	 * Call the protected modifier under test.
	 *
	 * @param array $event Imported event before subscription-level modifications
	 * @param array $modifications Subscription modifications
	 * @return array Modified event
	 */
	protected function modify(array $event, array $modifications) : array
	{
		$method = new \ReflectionMethod(Sync::class, 'modify');
		$method->setAccessible(true);
		return $method->invoke(null, $event, $modifications);
	}

	/**
	 * Base event used by the modifier tests.
	 *
	 * @param int $non_blocking Incoming imported event value
	 * @return array
	 */
	protected function event(int $non_blocking) : array
	{
		return [
			'category' => '',
			'participants' => [],
			'public' => 1,
			'non_blocking' => $non_blocking,
			'deleted' => 123,
		];
	}

	/**
	 * Base subscription modifications required by Sync::modify().
	 *
	 * @param array $modifications Modification values under test
	 * @return array
	 */
	protected function modifications(array $modifications=[]) : array
	{
		return array_replace([
			'cat_id' => 42,
			'participants' => [],
		], $modifications);
	}

	/**
	 * Parse iCalendar without constructing calendar_ical's database-backed BO.
	 *
	 * @param string $ical iCalendar payload
	 * @return array Parsed events
	 */
	protected function parseIcalEvents(string $ical) : array
	{
		$reflection = new \ReflectionClass(\calendar_ical::class);
		$ical_bo = $reflection->newInstanceWithoutConstructor();
		$ical_bo->setSupportedFields('full');
		return $ical_bo->icaltoegw($ical);
	}

	/**
	 * Build a constructor-free calendar_ical test double.
	 *
	 * @param array $events Parsed events to return from icaltoegw()
	 * @return SyncModifyTestCalendarIcal
	 */
	protected function importCapture(array $events) : SyncModifyTestCalendarIcal
	{
		$reflection = new \ReflectionClass(SyncModifyTestCalendarIcal::class);
		$ical_bo = $reflection->newInstanceWithoutConstructor();
		$ical_bo->events = $events;
		$ical_bo->updated_events = [];
		$ical_bo->supportedFields = [];
		$ical_bo->so = new SyncModifyTestCalendarStorage();
		$ical_bo->user = 1;
		$ical_bo->resources = [];
		$ical_bo->tzid = 'UTC';
		$ical_bo->nonBlockingAllday = true;
		return $ical_bo;
	}

	/**
	 * Build a parsed all-day event for importVCal() tests.
	 *
	 * @return array Parsed event with no TRANSP/non_blocking value
	 */
	protected function allDayEventWithoutTransp() : array
	{
		return [
			'uid' => 'all-day-force-blocking',
			'title' => 'All day',
			'start' => new \EGroupware\Api\DateTime('2026-06-17 00:00:00', \EGroupware\Api\DateTime::$server_timezone),
			'end' => new \EGroupware\Api\DateTime('2026-06-17 23:59:59', \EGroupware\Api\DateTime::$server_timezone),
			'whole_day' => true,
			'recur_type' => MCAL_RECUR_NONE,
			'recur_exception' => [],
			'participants' => [],
			'alarm' => [],
			'public' => 1,
			'priority' => 0,
			'category' => '',
		];
	}

	/**
	 * Test all tri-state blocking choices against both imported event values.
	 *
	 * Setup:
	 * - Build an imported event with non_blocking=0 or non_blocking=1.
	 * - Apply a subscription blocking value matching the UI select:
	 *   "blocking", "non_blocking", or "" for unchanged.
	 *
	 * Pass criteria:
	 * - "blocking" always stores non_blocking=0.
	 * - "non_blocking" always stores non_blocking=1.
	 * - "" leaves the imported event value unchanged.
	 *
	 * @dataProvider blockingModificationProvider
	 */
	#[DataProvider('blockingModificationProvider')]
	public function testBlockingModificationControlsImportedEvent(
		$blocking,
		int $incoming_non_blocking,
		int $expected_non_blocking
	) : void
	{
		$event = $this->modify(
			$this->event($incoming_non_blocking),
			$this->modifications(['blocking' => $blocking])
		);

		$this->assertSame(
			$expected_non_blocking,
			$event['non_blocking'],
			'Subscription blocking setting did not produce the expected imported event value'
		);
	}

	public static function blockingModificationProvider() : array
	{
		return [
			'blocking keeps blocking event blocking' => ['blocking', 0, 0],
			'blocking changes non-blocking event to blocking' => ['blocking', 1, 0],
			'non-blocking changes blocking event to non-blocking' => ['non_blocking', 0, 1],
			'non-blocking keeps non-blocking event non-blocking' => ['non_blocking', 1, 1],
			'unchanged keeps blocking event blocking' => ['', 0, 0],
			'unchanged keeps non-blocking event non-blocking' => ['', 1, 1],
		];
	}

	/**
	 * Test subscription blocking choices against actual iCalendar TRANSP input.
	 *
	 * Setup:
	 * - Parse a VCALENDAR with one busy event (TRANSP:OPAQUE) and one free event
	 *   (TRANSP:TRANSPARENT).
	 * - Apply the same subscription modifications used during external calendar sync.
	 *
	 * Pass criteria:
	 * - "From file" keeps OPAQUE as blocking and TRANSPARENT as non-blocking.
	 * - "Blocking" forces both imported events to blocking.
	 * - "Non blocking" forces both imported events to non-blocking.
	 *
	 * @dataProvider icalBlockingModificationProvider
	 */
	#[DataProvider('icalBlockingModificationProvider')]
	public function testBlockingModificationAfterIcalTranspParsing($blocking, array $expected_non_blocking) : void
	{
		$events = $this->parseIcalEvents(
			"BEGIN:VCALENDAR\r\n".
			"VERSION:2.0\r\n".
			"BEGIN:VEVENT\r\n".
			"UID:busy-test-123\r\n".
			"DTSTAMP:20260616T120000Z\r\n".
			"DTSTART:20260617T120000Z\r\n".
			"DTEND:20260617T130000Z\r\n".
			"SUMMARY:Busy\r\n".
			"TRANSP:OPAQUE\r\n".
			"END:VEVENT\r\n".
			"BEGIN:VEVENT\r\n".
			"UID:free-test-123\r\n".
			"DTSTAMP:20260616T120000Z\r\n".
			"DTSTART:20260618T120000Z\r\n".
			"DTEND:20260618T130000Z\r\n".
			"SUMMARY:Free\r\n".
			"TRANSP:TRANSPARENT\r\n".
			"END:VEVENT\r\n".
			"END:VCALENDAR\r\n"
		);

		$actual = [];
		foreach($events as $event)
		{
			$event = $this->modify($event, $this->modifications(['blocking' => $blocking]));
			$actual[$event['title']] = (int)$event['non_blocking'];
		}

		$this->assertSame(
			$expected_non_blocking,
			$actual,
			'iCalendar TRANSP parsing plus subscription blocking modification produced unexpected event values'
		);
	}

	public static function icalBlockingModificationProvider() : array
	{
		return [
			'from file keeps TRANSP values' => ['', ['Busy' => 0, 'Free' => 1]],
			'blocking forces all events blocking' => ['blocking', ['Busy' => 0, 'Free' => 0]],
			'non-blocking forces all events non-blocking' => ['non_blocking', ['Busy' => 1, 'Free' => 1]],
		];
	}

	/**
	 * Test that forced blocking survives the later all-day default.
	 *
	 * Setup:
	 * - Import a new all-day event without TRANSP.
	 * - Enable calendar_ical's existing nonBlockingAllday preference.
	 * - Apply subscription blocking="blocking" in the import callback.
	 *
	 * Pass criteria:
	 * - The event passed to update() remains blocking with non_blocking=0.
	 */
	public function testForcedBlockingSurvivesAllDayNonBlockingDefault() : void
	{
		$ical_bo = $this->importCapture([$this->allDayEventWithoutTransp()]);
		$ical_bo->event_callback = function(array &$event)
		{
			$event = $this->modify($event, $this->modifications(['blocking' => 'blocking']));
			return true;
		};

		$ical_bo->importVCal('ignored');

		$this->assertSame(
			0,
			$ical_bo->updated_events[0]['non_blocking'],
			'Forced subscription blocking was overwritten by the all-day non-blocking default'
		);
	}

	/**
	 * Test that the all-day default still applies when subscription keeps file value.
	 *
	 * Setup:
	 * - Import a new all-day event without TRANSP/non_blocking.
	 * - Enable calendar_ical's existing nonBlockingAllday preference.
	 * - Apply subscription blocking="" to keep the imported/file value.
	 *
	 * Pass criteria:
	 * - The event passed to update() is non-blocking, preserving existing all-day import behavior.
	 */
	public function testAllDayNonBlockingDefaultStillAppliesWithoutForcedBlocking() : void
	{
		$ical_bo = $this->importCapture([$this->allDayEventWithoutTransp()]);
		$ical_bo->event_callback = function(array &$event)
		{
			$event = $this->modify($event, $this->modifications(['blocking' => '']));
			return true;
		};

		$ical_bo->importVCal('ignored');

		$this->assertSame(
			1,
			$ical_bo->updated_events[0]['non_blocking'],
			'All-day non-blocking default should still apply when subscription keeps the file value'
		);
	}

	/**
	 * Test backward compatibility for subscriptions saved before the tri-state select.
	 *
	 * Setup:
	 * - Apply legacy subscription data where non_blocking=true was the only way
	 *   to force imported events to be non-blocking.
	 * - Apply legacy non_blocking=false to confirm old unchecked subscriptions
	 *   continue to leave the imported event value alone.
	 *
	 * Pass criteria:
	 * - Legacy non_blocking=true always stores non_blocking=1.
	 * - Legacy non_blocking=false leaves the imported event value unchanged.
	 *
	 * @dataProvider legacyNonBlockingModificationProvider
	 */
	#[DataProvider('legacyNonBlockingModificationProvider')]
	public function testLegacyNonBlockingModificationIsPreserved(
		bool $legacy_non_blocking,
		int $incoming_non_blocking,
		int $expected_non_blocking
	) : void
	{
		$event = $this->modify(
			$this->event($incoming_non_blocking),
			$this->modifications(['non_blocking' => $legacy_non_blocking])
		);

		$this->assertSame(
			$expected_non_blocking,
			$event['non_blocking'],
			'Legacy subscription non_blocking value did not produce the expected imported event value'
		);
	}

	public static function legacyNonBlockingModificationProvider() : array
	{
		return [
			'legacy checked changes blocking event to non-blocking' => [true, 0, 1],
			'legacy checked keeps non-blocking event non-blocking' => [true, 1, 1],
			'legacy unchecked keeps blocking event blocking' => [false, 0, 0],
			'legacy unchecked keeps non-blocking event non-blocking' => [false, 1, 1],
		];
	}

	/**
	 * Test that the new tri-state field takes precedence over legacy data.
	 *
	 * Setup:
	 * - Simulate a saved subscription containing both the new "blocking" field
	 *   and a stale legacy non_blocking=true value.
	 *
	 * Pass criteria:
	 * - Explicit "unchanged" leaves the imported value unchanged.
	 * - Explicit "blocking" forces non_blocking=0.
	 * - Explicit "non_blocking" forces non_blocking=1.
	 *
	 * @dataProvider blockingPrecedenceProvider
	 */
	#[DataProvider('blockingPrecedenceProvider')]
	public function testBlockingModificationTakesPrecedenceOverLegacyValue(
		$blocking,
		int $incoming_non_blocking,
		int $expected_non_blocking
	) : void
	{
		$event = $this->modify(
			$this->event($incoming_non_blocking),
			$this->modifications([
				'blocking' => $blocking,
				'non_blocking' => true,
			])
		);

		$this->assertSame(
			$expected_non_blocking,
			$event['non_blocking'],
			'New blocking setting should take precedence over stale legacy non_blocking data'
		);
	}

	public static function blockingPrecedenceProvider() : array
	{
		return [
			'new unchanged ignores stale legacy value for blocking event' => ['', 0, 0],
			'new unchanged ignores stale legacy value for non-blocking event' => ['', 1, 1],
			'new blocking overrides stale legacy non-blocking value' => ['blocking', 1, 0],
			'new non-blocking remains non-blocking with stale legacy value' => ['non_blocking', 0, 1],
		];
	}

	/**
	 * Test the non-blocking change together with the other import modifications.
	 *
	 * Setup:
	 * - Start with an imported event that already has a category, one participant,
	 *   public visibility, and a deleted marker from an older sync.
	 * - Apply category, additional participants, private, and blocking changes.
	 *
	 * Pass criteria:
	 * - The subscription category is appended.
	 * - Only missing subscription participants are added with unknown status.
	 * - Private and blocking flags are applied.
	 * - The deleted marker is cleared so an existing deleted event can be restored.
	 */
	public function testModifyKeepsOtherSubscriptionModificationBehaviour() : void
	{
		$event = $this->modify(
			[
				'category' => '7',
				'participants' => [
					10 => 'A',
				],
				'public' => 1,
				'non_blocking' => 1,
				'deleted' => 123,
			],
			$this->modifications([
				'cat_id' => 42,
				'participants' => [10, 11],
				'set_private' => true,
				'blocking' => 'blocking',
			])
		);

		$this->assertSame('7,42', $event['category'], 'Subscription category was not appended');
		$this->assertSame('A', $event['participants'][10], 'Existing participant status should be preserved');
		$this->assertSame('U', $event['participants'][11], 'Missing subscription participant should be added as unknown');
		$this->assertSame(0, $event['public'], 'Subscription private setting was not applied');
		$this->assertSame(0, $event['non_blocking'], 'Subscription blocking setting was not applied');
		$this->assertNull($event['deleted'], 'Deleted marker should be cleared on imported events');
	}
}
