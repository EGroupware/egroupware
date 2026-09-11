<?php
/**
 * EGroupware timesheet: regression test for the {{ts_end}} merge placeholder
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * timesheet_merge only stores ts_start and ts_duration (minutes) in the DB, there is
 * no ts_end column - the {{ts_end}} placeholder is computed on the fly in
 * timesheet_replacements() as ts_start + 60*ts_duration and, crucially, has to be added
 * to timesheet_egw_record::$types['date-time'] *before* importexport_export_csv::convert()
 * runs, so it gets formatted the same way {{ts_start}} does instead of leaking out as a
 * raw unix timestamp.
 */
class TimesheetMergeTest extends \EGroupware\Api\AppTest
{
	/** @var int[] ts_ids created by the current test, cleaned up in tearDown */
	private $ts_ids = array();

	protected function tearDown(): void
	{
		foreach ($this->ts_ids as $ts_id)
		{
			$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
			$so->delete(array('ts_id' => $ts_id));
		}
		$this->ts_ids = array();
	}

	/**
	 * Insert a timesheet row directly, with a fixed, known start+duration.
	 */
	private function createTimesheet(int $start, int $duration): int
	{
		$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_merge_'.bin2hex(random_bytes(6)),
			'ts_start'    => $start,
			'ts_duration' => $duration,
			'ts_quantity' => $duration / 60.0,
			'ts_owner'    => $GLOBALS['egw_info']['user']['account_id'],
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $GLOBALS['egw_info']['user']['account_id'],
		);
		$so->save();

		return $this->ts_ids[] = (int)$so->data['ts_id'];
	}

	/**
	 * Same date-time formatting importexport_export_csv::convert() applies to
	 * date-time fields, so the expectation tracks the actual user prefs used at
	 * test time instead of hard-coding a format that could drift out of sync.
	 */
	private function formatDateTime(int $timestamp): string
	{
		$prefs = $GLOBALS['egw_info']['user']['preferences']['common'];
		return date($prefs['dateformat'].' '.($prefs['timeformat'] == '24' ? 'H:i:s' : 'h:i:s a'), $timestamp);
	}

	/**
	 * Pass criteria: {{ts_end}} is present, and equals ts_start + ts_duration minutes,
	 * formatted exactly like {{ts_start}} - not a raw timestamp, not missing.
	 */
	public function testTsEndPlaceholderMatchesStartPlusDuration()
	{
		$start = strtotime('2026-01-15 09:00:00');
		$duration = 90;    // minutes
		$ts_id = $this->createTimesheet($start, $duration);

		$merge = new timesheet_merge();
		$replacements = $merge->timesheet_replacements($ts_id);

		$this->assertArrayHasKey('$$ts_end$$', $replacements, '{{ts_end}} placeholder must be present');
		$this->assertSame($this->formatDateTime($start + 60 * $duration), $replacements['$$ts_end$$']);
		// Guard against silently regressing to a raw timestamp if 'ts_end' ever
		// drops out of $types['date-time'] again.
		$this->assertFalse(is_numeric($replacements['$$ts_end$$']),
			'{{ts_end}} must be formatted like {{ts_start}}, not left as a raw timestamp');
	}

	/**
	 * Pass criteria: 'ts_end' is registered in the placeholder picker list, so users
	 * composing a document actually see {{ts_end}} as an available option.
	 */
	public function testTsEndListedInPlaceholderPicker()
	{
		$merge = new timesheet_merge();
		$placeholders = $merge->get_placeholder_list();

		$found = false;
		foreach ($placeholders['timesheet'] ?? array() as $placeholder)
		{
			if (($placeholder['value'] ?? null) === '{{ts_end}}')
			{
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, "'{{ts_end}}' must be listed in get_placeholder_list()");
	}
}
