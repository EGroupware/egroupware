<?php
/**
 * Unit test for creating a recurrence exception via calendar_uiforms->process_edit()
 * through an eTemplate submit round-trip.
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__ . '/../../api/tests/Etemplate/WidgetBaseTest.php');

use EGroupware\Api;

class ExceptionViaUIFormsTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{
	/**
	 * @var \calendar_boupdate
	 */
	protected $bo;

	/**
	 * @var int[]
	 */
	protected $event_ids = [];

	protected static $orig_date_tz;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		self::$orig_date_tz = date_default_timezone_get();
	}

	public static function tearDownAfterClass() : void
	{
		date_default_timezone_set(self::$orig_date_tz);
		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
		$this->setTimezones('Europe/Berlin', 'UTC');
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->event_ids) as $id)
		{
			$this->bo->delete($id, 0, true);
			$this->bo->delete($id, 0, true);
		}
		parent::tearDown();
	}

	/**
	 * Build a recurring event suitable for exception editing tests.
	 */
	protected function createDailyRecurringEvent() : int
	{
		$start = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$start->modify('+1 day');
		$start->setTime(9, 0, 0);
		$end = clone $start;
		$end->modify('+1 hour');
		$recur_end = clone $start;
		$recur_end->modify('+7 days');
		$recur_end->setTime(0, 0, 0);

		$event = [
			'title' => 'process_edit exception test ' . uniqid(),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'start' => $start,
			'end' => $end,
			'tzid' => 'UTC',
			'recur_type' => MCAL_RECUR_DAILY,
			'recur_enddate' => $recur_end,
			'participants' => [
				$GLOBALS['egw_info']['user']['account_id'] => 'A',
			],
		];

		$id = (int)$this->bo->save($event);
		$this->assertGreaterThan(0, $id, 'Recurring event could not be created');
		$this->event_ids[] = $id;
		return $id;
	}

	/**
	 * Set explicit user/server timezone split to exercise conversion paths.
	 */
	protected function setTimezones(string $client, string $server) : void
	{
		$GLOBALS['egw_info']['server']['server_timezone'] = $server;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $client;
		date_default_timezone_set($server);
		Api\DateTime::init();
	}

	/**
	 * Find et2_load command payload from JSON response commands.
	 */
	protected function getLoadFromResponse(array $commands) : array
	{
		foreach($commands as $command)
		{
			if(($command['type'] ?? null) === 'et2_load')
			{
				return $command['data'];
			}
		}
		$this->fail('No et2_load command returned');
	}

	/**
	 * Creating an exception from a recurring event through process_edit()
	 * should create one detached event and mark the original slot on master
	 * as recur_exception.
	 *
	 * Setup:
	 * - Create a daily recurring event in UTC.
	 * - Open calendar_uiforms->edit() using $_GET['cal_id'] + $_GET['date'].
	 * - Submit "save" through ajax_process_content with a content change.
	 *
	 * Pass criteria:
	 * - Master includes selected occurrence timestamp in recur_exception.
	 * - A related detached exception exists with matching recurrence/reference.
	 */
	public function testCreateExceptionViaProcessEdit()
	{
		$cal_id = $this->createDailyRecurringEvent();
		$master_before = $this->bo->read($cal_id);
		$this->assertIsArray($master_before, 'Master event could not be loaded');

		$so = new \calendar_so();
		$recurrences = $so->get_recurrences($cal_id);
		unset($recurrences[0]);
		$starts = array_map('intval', array_keys($recurrences));
		sort($starts);
		$this->assertGreaterThanOrEqual(2, count($starts), 'Expected at least two generated recurrences');

		$clicked_ts = $starts[1];
		$clicked_user_ts = Api\DateTime::server2user($clicked_ts);
		$clicked_occurrence = $this->bo->read($cal_id, $clicked_user_ts, true);
		$this->assertIsArray($clicked_occurrence, 'Clicked occurrence could not be loaded');

		$original_get = $_GET;
		$original_framework_response = $GLOBALS['egw']->framework->response ?? null;
		try
		{
			$_GET = [
				'cal_id' => $cal_id,
				'date' => Api\DateTime::to($clicked_occurrence['start'], Api\DateTime::ET2),
				'exception' => 1,
			];
			$this->ajax_response->initResponseArray();
			$GLOBALS['egw']->framework->response = $this->ajax_response;
			$ui = new \calendar_uiforms();
			$ui->edit();
			$initial_commands = $this->ajax_response->returnResult();
		}
		finally
		{
			$GLOBALS['egw']->framework->response = $original_framework_response;
			$_GET = $original_get;
		}

		$load = $this->getLoadFromResponse($initial_commands);
		$exec_id = $load['data']['etemplate_exec_id'];
		$save_payload = $load['data']['content'];
		$this->assertNotEmpty($save_payload['edit_single'] ?? null, 'edit() with $_GET[date] did not select a single occurrence');
		$this->assertEquals($cal_id, (int)($save_payload['reference'] ?? 0), 'edit() did not initialize exception reference');
		$this->assertNotEmpty($save_payload['recurrence'] ?? null, 'edit() did not initialize exception recurrence');
		$selected_ts = (int)Api\DateTime::to($save_payload['edit_single'], 'ts');

		if(!is_array($save_payload['alarm'] ?? null))
		{
			$save_payload['alarm'] = [];
		}
		$save_payload['button'] = ['apply' => true];
		$save_payload['title'] = ($save_payload['title'] ?? $master_before['title']) . ' (detached)';
		$save_payload['start'] = $save_payload['start'] instanceof Api\DateTime ?
			clone $save_payload['start'] : new Api\DateTime($save_payload['start'], Api\DateTime::$user_timezone);
		$save_payload['start']->modify('+2 hours');
		if(empty($save_payload['duration']))
		{
			$save_payload['duration'] = 3600;
		}
		$save_payload['end'] = '';

		$this->ajax_response->initResponseArray();
		Api\Etemplate::ajax_process_content($exec_id, $save_payload, false);
		$save_commands = $this->ajax_response->returnResult();
		$save_command_types = array_map(static function(array $command)
		{
			return $command['type'] ?? null;
		}, $save_commands);
		$save_msg = null;
		$save_state = null;
		foreach($save_commands as $command)
		{
			if(($command['type'] ?? null) !== 'et2_load')
			{
				continue;
			}
			$save_content = $command['data']['data']['content'] ?? [];
			$save_msg = $save_content['msg'] ?? null;
			$save_state = [
				'id' => $save_content['id'] ?? null,
				'reference' => $save_content['reference'] ?? null,
				'recurrence' => $save_content['recurrence'] ?? null,
				'edit_single' => $save_content['edit_single'] ?? null,
				'recur_type' => $save_content['recur_type'] ?? null,
			];
			break;
		}

		$master_after = $this->bo->read($cal_id);
		$this->assertIsArray($master_after, 'Master event could not be loaded after save');
		$this->assertIsArray($master_after['recur_exception'], 'Master recur_exception should be an array');

		$related_ids = (array)$this->bo->so->get_related($master_after['uid']);
		$detached = null;
		foreach($related_ids as $related_id)
		{
			$related = $this->bo->read((int)$related_id);
			if(!$related || (int)$related['reference'] !== $cal_id)
			{
				continue;
			}
			if((int)Api\DateTime::to($related['recurrence'], 'ts') !== $selected_ts)
			{
				continue;
			}
			$detached = $related;
			$this->event_ids[] = (int)$related['id'];
			break;
		}
		$this->assertIsArray(
			$detached,
			'Detached exception event was not created'
			. ' related_ids=' . json_encode($related_ids)
			. ' save_state=' . json_encode($save_state)
		);

		$found_exception_date = false;
		$actual_exception_timestamps = [];
		foreach($master_after['recur_exception'] as $exception_date)
		{
			$actual_exception_timestamps[] = (int)Api\DateTime::to($exception_date, 'ts');
			if((int)Api\DateTime::to($exception_date, 'ts') === $clicked_ts)
			{
				$found_exception_date = true;
				break;
			}
		}
		$this->assertTrue(
			$found_exception_date,
			'Selected occurrence was not added as recur_exception on master'
			. ' expected=' . $selected_ts
			. ' actual=' . json_encode($actual_exception_timestamps)
			. ' save_command_types=' . json_encode($save_command_types)
			. ' save_msg=' . json_encode($save_msg)
			. ' save_state=' . json_encode($save_state)
		);
	}
}
