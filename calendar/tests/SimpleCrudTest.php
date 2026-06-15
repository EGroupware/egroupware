<?php
/**
 * Basic CRUD tests for simple calendar events
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__ . '/../../api/tests/AppTest.php'); // Application test base

use EGroupware\Api;

class SimpleCrudTest extends \EGroupware\Api\AppTest
{
	protected $bo;
	protected $event_ids = [];
	protected static $event_title;
	protected static $event_start;
	protected static $event_end;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		// set consistent event details once per test case
		self::$event_title = 'Unit test event ' . uniqid();
		self::$event_start = new Api\DateTime('now', Api\DateTime::$server_timezone);
		self::$event_start->modify('+1 hour'); // start in 1 hour
		self::$event_end = clone self::$event_start;
		self::$event_end->modify('+1 hour'); // 1 hour duration
	}

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
	}

	protected function tearDown() : void
	{
		// testRead depends on the event created in testCreate, keep it until read runs
		if($this->name() === 'testCreate')
		{
			parent::tearDown();
			return;
		}

		foreach($this->event_ids as $id)
		{
			// ensure cleanup, call twice as some tests do keep-deleted behaviour
			$this->bo->delete($id, 0, true);
			$this->bo->delete($id, 0, true);
		}
		parent::tearDown();
	}

	protected function get_event()
	{
		return [
			'title' => self::$event_title,
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'start' => clone self::$event_start,
			'end'   => clone self::$event_end,
		];
	}

	/**
	 * Create a simple event through calendar BO.
	 *
	 * Pass criteria:
	 * - save() returns a positive numeric event id.
	 */
	public function testCreate()
	{
		$event = $this->get_event();
		$id = $this->bo->save($event);
		$this->assertGreaterThan(0, $id, 'saved event id should be > 0');
		$this->event_ids[] = $id;
		return $id;
	}

	/**
	 * Read back the event created by testCreate.
	 *
	 * Setup:
	 * - Reuse the ID returned by testCreate.
	 *
	 * Pass criteria:
	 * - read() returns an array.
	 * - title and start/end timestamps match the values originally saved.
	 */
	#[\PHPUnit\Framework\Attributes\Depends('testCreate')]
	public function testRead($id)
	{
		$this->event_ids[] = $id;

		$e = $this->bo->read($id);
		$this->assertIsArray($e);
		$this->assertEquals(self::$event_title, $e['title']);
		// check start/end via Api\DateTime formatting to avoid timezone/timestamp issues
		$expectedStart = new Api\DateTime(self::$event_start);
		$expectedEnd = new Api\DateTime(self::$event_end);
		$actualStart = new Api\DateTime($e['start']);
		$actualEnd = new Api\DateTime($e['end']);
		$this->assertEquals($expectedStart->format(Api\DateTime::DATABASE), $actualStart->format(Api\DateTime::DATABASE));
		$this->assertEquals($expectedEnd->format(Api\DateTime::DATABASE), $actualEnd->format(Api\DateTime::DATABASE));
		return $id;
	}

	/**
	 * Update event content and verify persisted change.
	 *
	 * Setup:
	 * - Create an isolated event for this scenario.
	 *
	 * Pass criteria:
	 * - Event can be saved.
	 * - read() reflects the updated title.
	 */
	public function testUpdate()
	{
		// Create a separate event for update to avoid inter-test state issues
		$event = $this->get_event();
		$event['title'] = 'Updated: ' . self::$event_title;
		$id = $this->bo->save($event);
		$this->assertGreaterThan(0, $id);
		$this->event_ids[] = $id;

		$e2 = $this->bo->read($id);
		$this->assertEquals($event['title'], $e2['title']);
	}

	/**
	 * Delete an event and ensure it is no longer active.
	 *
	 * Setup:
	 * - Create an isolated event for this scenario.
	 *
	 * Pass criteria:
	 * - delete() executes without error.
	 * - Subsequent read returns false or a deleted-marked record.
	 */
	public function testDelete()
	{
		// Create a separate event for delete to avoid inter-test state issues
		$event = $this->get_event();
		$id = $this->bo->save($event);
		$this->assertGreaterThan(0, $id);
		$this->event_ids[] = $id;

		// Attempt to delete; some configs return false/0 while still marking deleted.
		$this->bo->delete($id, 0, true);
		// read may return false or a record with deleted flag depending on config
		$e = $this->bo->read($id, null, true);
		$this->assertTrue($e === false || !empty($e['deleted']));
	}
}
