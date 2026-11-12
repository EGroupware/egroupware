<?php
/**
 * EGroupware Timesheet: regression test for timesheet_export_csv's range-filter column validation
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

/**
 * timesheet_export_csv::export()'s "Handle ranges" step took $field straight from the stored export
 * definition's own filter array keys and used to splice it unvalidated into a raw col_filter string
 * ("$field >= ..."), the same class of bug already fixed for resources_export_csv/infolog_export_csv:
 * only a plain column-name shape is now accepted and run through Api\Db\SqlFragment::range().
 */
class TimesheetExportCsvRangeFilterTest extends \EGroupware\Api\AppTest
{
	/** @var int[] ts_ids created by the current test, cleaned up in tearDown */
	private $ts_ids = array();

	protected function tearDown(): void
	{
		foreach ($this->ts_ids as $ts_id)
		{
			(new Api\Storage\Base('timesheet', 'egw_timesheet'))->delete(array('ts_id' => $ts_id));
		}
		$this->ts_ids = array();
		parent::tearDown();
	}

	private function createTimesheet(float $quantity) : int
	{
		$so = new Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'ExportCsvRangeFilterTest-'.bin2hex(random_bytes(6)),
			'ts_start'    => time(),
			'ts_duration' => 60,
			'ts_quantity' => $quantity,
			'ts_owner'    => $GLOBALS['egw_info']['user']['account_id'],
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $GLOBALS['egw_info']['user']['account_id'],
		);
		$so->save();

		return $this->ts_ids[] = (int)$so->data['ts_id'];
	}

	private function makeDefinition(array $filter): importexport_definition
	{
		$definition = new importexport_definition();
		$definition->set_record(array(
			'name' => 'phpunit_export_csv_range_filter_test',
			'application' => 'timesheet',
			'plugin' => 'timesheet_export_csv',
			'type' => 'export',
			'plugin_options' => array(
				'selection' => 'filter',
				'mapping' => array('ts_title' => 'Title'),
			),
			'filter' => $filter,
		));
		return $definition;
	}

	private function export(array $filter) : importexport_export_csv
	{
		$plugin = new timesheet_export_csv();
		$stream = fopen('php://memory', 'w+');
		try
		{
			return $plugin->export($stream, $this->makeDefinition($filter));
		}
		finally
		{
			fclose($stream);
		}
	}

	public function testLegitimateRangeFilterStillWorks()
	{
		$this->createTimesheet(1.0);
		$matching = $this->createTimesheet(99.0);

		$result = $this->export(array(
			'ts_owner' => $GLOBALS['egw_info']['user']['account_id'],
			'ts_quantity' => array('from' => 99, 'to' => 99),
		));

		$this->assertInstanceOf(importexport_export_csv::class, $result);
		$this->assertSame(1, $result->get_num_of_records(),
			'the range must select only the matching entry, not every owned entry');
	}

	/**
	 * A $field that isn't a plain (table.)column name must be dropped, not spliced into SQL.
	 */
	public function testMaliciousFieldNameIsDroppedNotSpliced()
	{
		$this->createTimesheet(1.0);

		$result = $this->export(array(
			'ts_owner' => $GLOBALS['egw_info']['user']['account_id'],
			"ts_quantity) OR 1=1-- -" => array('from' => 99, 'to' => 99),
		));

		$this->assertInstanceOf(importexport_export_csv::class, $result,
			'a malformed filter field must be dropped silently, not break the export');
		$this->assertGreaterThanOrEqual(1, $result->get_num_of_records(),
			'the malicious field must be ignored, not turn into a condition that excludes everything');
	}
}
