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

	/** @var array common date/time preferences as found, restored in tearDown */
	private $saved_prefs = array();

	/**
	 * The merged date-times pass through two independent format sources: importexport
	 * formats them with the raw 'dateformat' / 'timeformat' preferences, while Merge parses
	 * them back with Api\DateTime::$user_dateformat / $user_timeformat, which keep their
	 * built-in defaults when the preference is empty.  On an install where those preferences
	 * are not set (a fresh CI database, for one) the two disagree: importexport writes a
	 * date-time with no date at all, and every expectation here drifts.  Pin both to the
	 * same known format instead of trusting whatever the test user happens to have.
	 */
	protected function setUp(): void
	{
		$prefs =& $GLOBALS['egw_info']['user']['preferences']['common'];
		$this->saved_prefs = array(
			'dateformat' => $prefs['dateformat'] ?? null,
			'timeformat' => $prefs['timeformat'] ?? null,
		);
		$prefs['dateformat'] = 'Y-m-d';
		$prefs['timeformat'] = '24';
		EGroupware\Api\DateTime::setUserPrefs(EGroupware\Api\DateTime::$user_timezone->getName(), 'Y-m-d', '24');
	}

	protected function tearDown(): void
	{
		foreach ($this->ts_ids as $ts_id)
		{
			$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
			$so->delete(array('ts_id' => $ts_id));
		}
		$this->ts_ids = array();

		$prefs =& $GLOBALS['egw_info']['user']['preferences']['common'];
		foreach ($this->saved_prefs as $name => $value)
		{
			if (isset($value))
			{
				$prefs[$name] = $value;
			}
			else
			{
				unset($prefs[$name]);
			}
		}
		EGroupware\Api\DateTime::setUserPrefs(EGroupware\Api\DateTime::$user_timezone->getName(),
			$this->saved_prefs['dateformat'] ?? '', $this->saved_prefs['timeformat'] ?? '');
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
	 * Pass criteria: {{ts_end/date}} and {{ts_end/time}} merge to the date resp. time half of
	 * {{ts_end}}, like every other date-time field's sub-placeholders do.
	 *
	 * Merge::replace() builds those two from Merge::$date_fields, which is a separate list from
	 * the $types['date-time'] one timesheet_replacements() adds 'ts_end' to - so registering it
	 * in only one of them leaves {{ts_end}} itself working while both sub-placeholders merge
	 * to nothing.
	 */
	public function testTsEndDateAndTimeSubPlaceholders()
	{
		$start = strtotime('2026-01-15 09:00:00');
		$duration = 90;    // minutes
		$ts_id = $this->createTimesheet($start, $duration);
		$end = $start + 60 * $duration;

		$merge = new timesheet_merge();
		$err = null;
		$result = $merge->merge_string('[{{ts_end/date}}][{{ts_end/time}}]', array($ts_id), $err, 'text/plain');

		$this->assertEmpty($err, 'Errors when merging');
		$this->assertSame(
			'['.date(EGroupware\Api\DateTime::$user_dateformat, $end).']'.
			'['.date(EGroupware\Api\DateTime::$user_timeformat, $end).']',
			$result
		);
	}

	/**
	 * Every date format of the preferences x 24 and 12 hour time
	 */
	public static function userFormatProvider(): array
	{
		$data = array();
		foreach (array('d.m.Y', 'Y-m-d', 'm/d/Y', 'm-d-Y', 'm.d.Y', 'Y/d/m', 'Y-d-m', 'Y.d.m', 'Y/m/d', 'Y.m.d',
					 'd/m/Y', 'd-m-Y', 'd-M-Y') as $dateformat)
		{
			foreach (array('24', '12') as $timeformat)
			{
				$data["$dateformat {$timeformat}h"] = array($dateformat, $timeformat);
			}
		}
		return $data;
	}

	/**
	 * Date placeholders of a real app, for every date and time format a user can pick.
	 *
	 * Timesheet hands Merge its date-times as strings importexport already formatted in the user's
	 * format (with seconds), which Merge parses back for {{ts_start/date}}, {{ts_start/time}} and
	 * {{ts_start:FORMAT}}.  Merged into an ODF text document, where all of these are available.
	 *
	 * Setup: a timesheet starting 2026-03-15 14:30 (a day > 12, so swapping day and month can not
	 * go unnoticed), lasting 90 minutes, merged with the date and time format set in the
	 * preferences and Api\DateTime.
	 *
	 * Pass criteria: the merge reports no error and throws no exception, {{ts_start}} and {{ts_end}}
	 * are formatted like importexport formats them, their /date and /time parts in the user's
	 * date and time format, and {{ts_start:d.m.Y H.i}} in the given format.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatProvider')]
	public function testDatePlaceholdersForUserFormat(string $dateformat, string $timeformat)
	{
		$prefs =& $GLOBALS['egw_info']['user']['preferences']['common'];
		$prefs['dateformat'] = $dateformat;
		$prefs['timeformat'] = $timeformat;
		EGroupware\Api\DateTime::setUserPrefs(EGroupware\Api\DateTime::$user_timezone->getName(), $dateformat, $timeformat);

		$start = strtotime('2026-03-15 14:30:00');
		$duration = 90;    // minutes
		$ts_id = $this->createTimesheet($start, $duration);
		$end = $start + 60 * $duration;

		$merge = new timesheet_merge();
		$err = null;
		try
		{
			$result = $merge->merge_string(
				'<text:p>[{{ts_start}}][{{ts_start/date}}][{{ts_start/time}}][{{ts_start:d.m.Y H.i}}]</text:p>'.
				'<text:p>[{{ts_end}}][{{ts_end/date}}][{{ts_end/time}}]</text:p>',
				array($ts_id), $err, 'application/vnd.oasis.opendocument.text');
		}
		catch (\Throwable $e)
		{
			$this->fail("Merging with date format '$dateformat' failed with ".get_class($e).': '.$e->getMessage());
		}

		$this->assertEmpty($err, 'Errors when merging');
		$this->assertSame(
			'<text:p>['.$this->formatDateTime($start).']'.
			'['.date(EGroupware\Api\DateTime::$user_dateformat, $start).']'.
			'['.date(EGroupware\Api\DateTime::$user_timeformat, $start).']'.
			'['.date('d.m.Y H.i', $start).']</text:p>'.
			'<text:p>['.$this->formatDateTime($end).']'.
			'['.date(EGroupware\Api\DateTime::$user_dateformat, $end).']'.
			'['.date(EGroupware\Api\DateTime::$user_timeformat, $end).']</text:p>',
			$result
		);
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
