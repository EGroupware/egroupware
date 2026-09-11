<?php
/**
 * Regression tests for whole-day event start/end shown in notifications
 *
 * Whole-day events have no time-of-day: start/end are just the 00:00:00/23:59:59
 * boundaries of the event's day(s). calendar_boupdate::_send_update() re-expresses
 * an event's start/end in each notification recipient's own timezone. For timed
 * events that's a real instant-preserving conversion, but doing the same thing for
 * whole-day events shifts the displayed date/time by the offset between the
 * acting user's and the recipient's timezone - eg. a full-day event showing
 * "Start: 2:00" / "End: 1:59 [next day]" instead of "Start: 0:00" / "End: 23:59".
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

class NotificationWholeDayTimezoneTest extends TestCase
{
	/**
	 * @var \calendar_boupdate
	 */
	protected $bo;

	/**
	 * @var \ReflectionMethod
	 */
	protected $notification_dates;

	protected function setUp() : void
	{
		// _notification_dates() only does DateTime arithmetic, no DB access needed
		$ref = new \ReflectionClass(\calendar_boupdate::class);
		$this->bo = $ref->newInstanceWithoutConstructor();

		$this->notification_dates = new \ReflectionMethod(\calendar_boupdate::class, '_notification_dates');
		$this->notification_dates->setAccessible(true);
	}

	protected function callNotificationDates(array $event, Api\DateTime $start, Api\DateTime $end, \DateTimeZone $timezone)
	{
		return $this->notification_dates->invoke($this->bo, $event, $start, $end, $timezone);
	}

	/**
	 * Whole-day event, acting user in server-timezone UTC, recipient in Europe/Berlin.
	 *
	 * This is exactly the customer-reported case: a full-day event ends up showing
	 * "Start: 2:00" / "End: 1:59 [next day]" instead of "Start: 0:00" / "End: 23:59".
	 */
	public function testWholeDayServerUtcRecipientBerlin() : void
	{
		$event = array('whole_day' => true);
		$start = new Api\DateTime('2026-08-10 00:00:00', new \DateTimeZone('UTC'));
		$end = new Api\DateTime('2026-08-10 23:59:59', new \DateTimeZone('UTC'));

		list($fixed_start, $fixed_end) = $this->callNotificationDates($event, $start, $end, new \DateTimeZone('Europe/Berlin'));

		$this->assertSame('2026-08-10 00:00:00', $fixed_start->format('Y-m-d H:i:s'), 'Whole-day start must stay at midnight');
		$this->assertSame('2026-08-10 23:59:59', $fixed_end->format('Y-m-d H:i:s'), 'Whole-day end must stay at 23:59:59 the same day');
	}

	/**
	 * Same whole-day event, but the other direction: acting user in Europe/Berlin,
	 * recipient in UTC. Covers "not sure if this only happens for server UTC or
	 * Europe/Berlin" from the bug report.
	 */
	public function testWholeDayServerBerlinRecipientUtc() : void
	{
		$event = array('whole_day' => true);
		$start = new Api\DateTime('2026-08-10 00:00:00', new \DateTimeZone('Europe/Berlin'));
		$end = new Api\DateTime('2026-08-10 23:59:59', new \DateTimeZone('Europe/Berlin'));

		list($fixed_start, $fixed_end) = $this->callNotificationDates($event, $start, $end, new \DateTimeZone('UTC'));

		$this->assertSame('2026-08-10 00:00:00', $fixed_start->format('Y-m-d H:i:s'), 'Whole-day start must stay at midnight');
		$this->assertSame('2026-08-10 23:59:59', $fixed_end->format('Y-m-d H:i:s'), 'Whole-day end must stay at 23:59:59 the same day');
	}

	/**
	 * Whole-day events spanning several days must keep their first/last day boundaries.
	 */
	public function testMultiDayWholeDayServerUtcRecipientBerlin() : void
	{
		$event = array('whole_day' => true);
		$start = new Api\DateTime('2026-08-10 00:00:00', new \DateTimeZone('UTC'));
		$end = new Api\DateTime('2026-08-12 23:59:59', new \DateTimeZone('UTC'));

		list($fixed_start, $fixed_end) = $this->callNotificationDates($event, $start, $end, new \DateTimeZone('Europe/Berlin'));

		$this->assertSame('2026-08-10 00:00:00', $fixed_start->format('Y-m-d H:i:s'));
		$this->assertSame('2026-08-12 23:59:59', $fixed_end->format('Y-m-d H:i:s'));
	}

	/**
	 * Regression guard: timed (non-whole-day) events must still get a real,
	 * instant-preserving timezone conversion for each recipient.
	 */
	public function testTimedEventStillConvertsInstant() : void
	{
		$event = array('whole_day' => false);
		// 10:00 UTC in August is 12:00 in Europe/Berlin (CEST, UTC+2)
		$start = new Api\DateTime('2026-08-10 10:00:00', new \DateTimeZone('UTC'));
		$end = new Api\DateTime('2026-08-10 11:00:00', new \DateTimeZone('UTC'));

		list($converted_start, $converted_end) = $this->callNotificationDates($event, $start, $end, new \DateTimeZone('Europe/Berlin'));

		$this->assertSame('2026-08-10 12:00:00', $converted_start->format('Y-m-d H:i:s'));
		$this->assertSame('2026-08-10 13:00:00', $converted_end->format('Y-m-d H:i:s'));
		// same instant, just re-expressed
		$this->assertSame($start->getTimestamp(), $converted_start->getTimestamp());
		$this->assertSame($end->getTimestamp(), $converted_end->getTimestamp());
	}
}
