<?php
/**
 * Regression tests for calendar_uiforms date handling in event_changed()
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__ . '/../inc/class.calendar_uiforms.inc.php');

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

class EventChangedDateTimeTest extends TestCase
{
	/**
	 * @var \calendar_uiforms
	 */
	protected $ui;

	protected function setUp() : void
	{
		// event_changed() does not depend on full UI initialization.
		// Instantiate without constructor to keep this a true unit test.
		$ref = new \ReflectionClass(\calendar_uiforms::class);
		$this->ui = $ref->newInstanceWithoutConstructor();
	}

	/**
	 * Ensure regular start/end modifications are reported as Api\DateTime objects
	 * and old_start preserves the previous start as Api\DateTime.
	 */
	public function testChangedStartEndRemainDateTimeObjects() : void
	{
		$user = 1;
		$old_start = new Api\DateTime('2026-06-15 09:00:00', Api\DateTime::$user_timezone);
		$old_end = new Api\DateTime('2026-06-15 10:00:00', Api\DateTime::$user_timezone);
		$new_start = clone $old_start;
		$new_start->modify('+1 hour');
		$new_end = clone $old_end;
		$new_end->modify('+1 hour');

		$old = [
			'start' => $old_start,
			'end' => $old_end,
			'title' => 'Old title',
			'participants' => [$user => 'A'],
		];
		$event = [
			'start' => $new_start,
			'end' => $new_end,
			'title' => 'Old title',
			'participants' => [$user => 'A'],
		];

		// Regression guard: changed start/end used to be exposed as timestamps.
		$changes = $this->ui->event_changed($event, $old);

		$this->assertArrayHasKey('start', $changes, 'Expected changed start to be detected');
		$this->assertArrayHasKey('end', $changes, 'Expected changed end to be detected');
		$this->assertInstanceOf(Api\DateTime::class, $changes['start'], 'Changed start must stay Api\\DateTime');
		$this->assertInstanceOf(Api\DateTime::class, $changes['end'], 'Changed end must stay Api\\DateTime');
		$this->assertInstanceOf(Api\DateTime::class, $event['old_start'], 'old_start must stay Api\\DateTime');
		$this->assertSame(
			$old_start->getTimestamp(),
			$event['old_start']->getTimestamp(),
			'old_start must contain previous event start'
		);
	}

	/**
	 * Ensure explicit recurrence exception detection returns changed start/end as
	 * Api\DateTime objects while preserving the original event duration.
	 */
	public function testExplicitExceptionChangeUsesDateTimeObjects() : void
	{
		$user = 1;
		$start = new Api\DateTime('2026-06-15 09:00:00', Api\DateTime::$user_timezone);
		$end = new Api\DateTime('2026-06-15 10:00:00', Api\DateTime::$user_timezone);
		$recurrence = clone $start;
		$recurrence->modify('+1 day');

		$old = [
			'start' => clone $start,
			'end' => clone $end,
			'title' => 'Recurring event',
			'participants' => [$user => 'A'],
		];
		$event = [
			'start' => clone $start,
			'end' => clone $end,
			'recurrence' => $recurrence,
			'recur_type' => 0,
			'title' => 'Recurring event',
			'participants' => [$user => 'A'],
		];

		// Explicit exception path should also preserve DateTime object semantics.
		$changes = $this->ui->event_changed($event, $old);

		$this->assertArrayHasKey('start', $changes, 'Expected explicit exception to report changed start');
		$this->assertArrayHasKey('end', $changes, 'Expected explicit exception to report changed end');
		$this->assertInstanceOf(Api\DateTime::class, $changes['start'], 'Exception start must stay Api\\DateTime');
		$this->assertInstanceOf(Api\DateTime::class, $changes['end'], 'Exception end must stay Api\\DateTime');
		$this->assertSame(
			$recurrence->getTimestamp(),
			$changes['start']->getTimestamp(),
			'Exception start must match recurrence timestamp'
		);
		$this->assertSame(
			((int)Api\DateTime::to($end, 'ts') - (int)Api\DateTime::to($start, 'ts')),
			$changes['end']->getTimestamp() - $changes['start']->getTimestamp(),
			'Exception end must preserve event duration'
		);
	}
}
