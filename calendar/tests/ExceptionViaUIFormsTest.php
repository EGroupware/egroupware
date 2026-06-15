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
require_once realpath(__DIR__ . '/../../admin/inc/class.admin_cmd_edit_user.inc.php');
require_once realpath(__DIR__ . '/../../admin/inc/class.admin_cmd_delete_account.inc.php');

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
	protected $account_ids = [];

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
		foreach(array_unique($this->account_ids) as $account_id)
		{
			try
			{
				$command = new \admin_cmd_delete_account((int)$account_id, null, true);
				$command->comment = 'Removing in tearDown for unit test ' . $this->name();
				$command->run();
			}
			catch (\Throwable $e)
			{
			}
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

	protected function createDailyRecurringEventWithParticipants(array $participants) : int
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
			'title'         => 'process_edit participant exception test ' . uniqid(),
			'owner'         => $GLOBALS['egw_info']['user']['account_id'],
			'start'         => $start,
			'end'           => $end,
			'tzid'          => 'UTC',
			'recur_type'    => MCAL_RECUR_DAILY,
			'recur_enddate' => $recur_end,
			'participants'  => $participants,
		];
		$id = (int)$this->bo->save($event);
		$this->assertGreaterThan(0, $id, 'Recurring event could not be created');
		$this->event_ids[] = $id;
		return $id;
	}

	/**
	 * Return generated recurrence start timestamps for a series event.
	 */
	protected function recurrenceStarts(int $cal_id) : array
	{
		$so = new \calendar_so();
		$recurrences = $so->get_recurrences($cal_id);
		unset($recurrences[0]);
		$starts = array_map('intval', array_keys($recurrences));
		sort($starts);
		return $starts;
	}

	/**
	 * Create a disposable secondary account for participant-related test steps.
	 */
	protected function createSecondaryUser(string $prefix = 'ui_exception') : int
	{
		$account = [
			'account_lid'       => $prefix . '_' . uniqid(),
			'account_firstname' => 'UI',
			'account_lastname'  => 'Participant',
		];
		$command = new \admin_cmd_edit_user(false, $account);
		$command->comment = 'Needed for unit test ' . $this->name();
		$command->run();
		$account_id = (int)$command->account;
		$this->assertGreaterThan(0, $account_id, 'Unable to create secondary user');
		$this->account_ids[] = $account_id;
		return $account_id;
	}

	/**
	 * Load calendar edit form state and return exec id, content, and readonly map.
	 */
	protected function loadEditPayload(int $cal_id, ?int $recur_start_server = null, bool $exception = false) : array
	{
		$original_get = $_GET;
		$original_framework_response = $GLOBALS['egw']->framework->response ?? null;
		try
		{
			$_GET = ['cal_id' => $cal_id];
			if($recur_start_server)
			{
				$occurrence = $this->bo->read($cal_id, Api\DateTime::server2user($recur_start_server), true);
				$this->assertIsArray($occurrence, 'Occurrence could not be loaded');
				$_GET['date'] = Api\DateTime::to($occurrence['start'], Api\DateTime::ET2);
			}
			if($exception)
			{
				$_GET['exception'] = 1;
			}
			$this->ajax_response->initResponseArray();
			$GLOBALS['egw']->framework->response = $this->ajax_response;
			$ui = new \calendar_uiforms();
			$ui->edit();
			$commands = $this->ajax_response->returnResult();
		}
		finally
		{
			$GLOBALS['egw']->framework->response = $original_framework_response;
			$_GET = $original_get;
		}
		$load = $this->getLoadFromResponse($commands);
		$content = $load['data']['content'];
		return [$load['data']['etemplate_exec_id'], $content, $load['data']['readonlys'] ?? []];
	}

	/**
	 * Submit form content through etemplate ajax processing.
	 */
	protected function processEditPayload(string $exec_id, array $payload, bool $skip_validation = false) : array
	{
		$this->ajax_response->initResponseArray();
		Api\Etemplate::ajax_process_content($exec_id, $payload, $skip_validation);
		return $this->ajax_response->returnResult();
	}

	/**
	 * Call UI process_edit directly.
	 *
	 * Participant edits in these tests can be filtered by etemplate validation in
	 * PHPUnit context. Direct process_edit skips validation so we don't have to worry
	 * about it.
	 */
	protected function processEditDirect(array $payload) : array
	{
		$original_framework_response = $GLOBALS['egw']->framework->response ?? null;
		try
		{
			if(!is_array($payload['alarm'] ?? null))
			{
				$payload['alarm'] = [];
			}
			$this->ajax_response->initResponseArray();
			$GLOBALS['egw']->framework->response = $this->ajax_response;
			$ui = new \calendar_uiforms();
			$ui->process_edit($payload);
			return $this->ajax_response->returnResult();
		}
		finally
		{
			$GLOBALS['egw']->framework->response = $original_framework_response;
		}
	}

	/**
	 * Find the numeric participant row index for a given account id.
	 */
	protected function participantRowIndex(array $payload, int $uid) : int
	{
		foreach($payload['participants'] as $row => $data)
		{
			if(is_numeric($row) && (int)($data['uid'] ?? 0) === $uid)
			{
				return (int)$row;
			}
		}
		$this->fail("Participant row for uid $uid not found");
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
		$save_payload['non_blocking'] = true;
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
		$exceptions = $this->bo->read(['cal_reference' => $cal_id], null, true);
		foreach((array)$exceptions as $exception)
		{
			if(!$exception || (int)($exception['reference'] ?? 0) !== $cal_id)
			{
				continue;
			}
			if((int)Api\DateTime::to($exception['recurrence'], 'ts') !== $selected_ts)
			{
				continue;
			}
			$detached = $exception;
			$this->event_ids[] = (int)$exception['id'];
			break;
		}
		$this->assertIsArray(
			$detached,
			'Detached exception event was not created'
			. ' related_ids=' . json_encode($related_ids)
			. ' exception_ids=' . json_encode(array_keys((array)$exceptions))
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

	/**
	 * UI round-trip coverage for participant and exception behaviour in recurring events.
	 *
	 * Verifies through calendar_uiforms edit/process_edit flow that:
	 * - detached exceptions can be created with shifted date/time
	 * - participant status can be changed in different detached exceptions
	 * - adding a participant to the series updates regular recurrences
	 * - already detached exceptions are not retroactively modified
	 */
	public function testParticipantChangesAcrossRecurrencesAndExceptionsViaProcessEdit()
	{
		$owner = (int)$GLOBALS['egw_info']['user']['account_id'];
		$user_a = $this->createSecondaryUser('ui_status');
		$user_b = $this->createSecondaryUser('ui_add');
		$cal_id = $this->createDailyRecurringEventWithParticipants([$owner => 'U', $user_a => 'U']);

		$starts = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThanOrEqual(4, count($starts), 'Expected at least four recurrences');
		$r1 = $starts[1];
		$r2 = $starts[2];
		$r3 = $starts[3];

		// Create exceptions at different date/time through UI.
		$exception_ids = [];
		foreach([[$r1, '+2 hours'], [$r2, '+1 day +3 hours']] as [$recur_start, $shift])
		{
			[$exec_id, $payload] = $this->loadEditPayload($cal_id, $recur_start, true);
			$selected_ts = (int)Api\DateTime::to($payload['edit_single'], 'ts');
			$payload['button'] = ['apply' => true];
			$payload['non_blocking'] = true;
			$payload['title'] = ($payload['title'] ?? '') . ' shifted';
			$payload['start'] = $payload['start'] instanceof Api\DateTime ?
				clone $payload['start'] : new Api\DateTime($payload['start'], Api\DateTime::$user_timezone);
			$payload['start']->modify($shift);
			$payload['end'] = '';
			$this->processEditPayload($exec_id, $payload);

			$exceptions = (array)$this->bo->read(['cal_reference' => $cal_id], null, true);
			foreach($exceptions as $exception)
			{
				if((int)($exception['reference'] ?? 0) !== $cal_id)
				{
					continue;
				}
				if((int)Api\DateTime::to($exception['recurrence'], 'ts') !== $selected_ts)
				{
					continue;
				}
				$exception_ids[] = (int)$exception['id'];
				$this->event_ids[] = (int)$exception['id'];
				break;
			}
		}
		$this->assertCount(2, array_unique($exception_ids), 'Expected two detached exceptions');

		// Change participant status in different recurrence exceptions via edit/process_edit.
		foreach([[$exception_ids[0], 'A'], [$exception_ids[1], 'R']] as [$exception_id, $status])
		{
			$exception = $this->bo->read($exception_id);
			$this->assertIsArray($exception, 'Exception not found for status update');
			[$exec_id, $payload, $readonlys] = $this->loadEditPayload((int)$exception['id'], null, false);
			$this->assertEmpty(
				$payload['edit_single'] ?? null,
				'Detached exception edit unexpectedly has edit_single set'
			);
			$row = $this->participantRowIndex($payload, $owner);
			$this->assertNotTrue(
				(bool)($readonlys['participants'][$row]['status'] ?? false),
				'Owner status widget is read-only in detached exception edit form'
			);
			$payload['participants'][$row]['status'] = $status;
			$payload['non_blocking'] = '1';
			$payload['action'] = '';
			unset($payload['button']);
			$status_commands = $this->processEditDirect($payload);
			$status_msg = null;
			foreach($status_commands as $command)
			{
				if(($command['type'] ?? null) !== 'et2_load')
				{
					continue;
				}
				$status_msg = $command['data']['data']['content']['msg'] ?? null;
				break;
			}
			$this->assertNotSame(
				lang('Permission denied'),
				$status_msg,
				'Status submit returned permission denied for detached exception'
			);
		}
		$exception_after_1 = $this->bo->read($exception_ids[0]);
		$exception_after_2 = $this->bo->read($exception_ids[1]);
		$actual_status_1 = (string)($exception_after_1['participants'][$owner] ?? '');
		$actual_status_2 = (string)($exception_after_2['participants'][$owner] ?? '');
		$this->assertStringStartsWith(
			'A',
			$actual_status_1,
			'Owner status in first detached exception did not change to accepted'
			. " (actual status: '{$actual_status_1}')"
		);
		$this->assertStringStartsWith(
			'R',
			$actual_status_2,
			'Owner status in second detached exception did not change to rejected'
			. " (actual status: '{$actual_status_2}')"
		);

		// Add participant to recurrence/series through edit/process_edit.
		[$exec_id, $payload] = $this->loadEditPayload($cal_id, null, false);
		$payload['participants']['participant'] = [$user_b];
		$payload['participants']['quantity'] = 1;
		$payload['participants']['role'] = 'REQ-PARTICIPANT';
		$payload['tabs'] = 'participants';
		$payload['non_blocking'] = true;
		$payload['participants']['add'] = true;
		$payload['action'] = '';
		unset($payload['button']);
		$orig_require_acl_invite = $this->bo->require_acl_invite;
		try
		{
			// Locally created throwaway test users can fail invite ACL checks.
			// This test is about UI propagation rules, not invite permission rules.
			$this->bo->require_acl_invite = false;
			$add_commands = $this->processEditDirect($payload);
			$add_load = $this->getLoadFromResponse($add_commands);
			$save_payload = $add_load['data']['content'];
			$save_payload['tabs'] = 'participants';
			$save_payload['non_blocking'] = true;
			$save_payload['button'] = ['apply' => true];
			$save_commands = $this->processEditDirect($save_payload);
		}
		finally
		{
			$this->bo->require_acl_invite = $orig_require_acl_invite;
		}
		$add_msg = null;
		foreach($save_commands as $command)
		{
			if(($command['type'] ?? null) !== 'et2_load')
			{
				continue;
			}
			$add_msg = $command['data']['data']['content']['msg'] ?? null;
			break;
		}

		$series_occurrence = $this->bo->read($cal_id, Api\DateTime::server2user($r3), true);
		$master_after_add = $this->bo->read($cal_id);
		$this->assertArrayHasKey(
			$user_b,
			$series_occurrence['participants'],
			'Added participant missing in recurrence'
			. ' add_msg=' . json_encode($add_msg)
			. ' master_participants=' . json_encode(array_keys((array)$master_after_add['participants']))
			. ' occurrence_participants=' . json_encode(array_keys((array)$series_occurrence['participants']))
		);

		$exception1 = $this->bo->read($exception_ids[0]);
		$exception2 = $this->bo->read($exception_ids[1]);
		$this->assertArrayNotHasKey($user_b, $exception1['participants'], 'Added participant leaked into first detached exception');
		$this->assertArrayNotHasKey($user_b, $exception2['participants'], 'Added participant leaked into second detached exception');
	}
}
