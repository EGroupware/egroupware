<?php
/**
 * EGroupware Infolog: regression test for infolog_export_csv's range-filter column validation
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

/**
 * infolog_export_csv::export()'s "Handle ranges" step takes $field straight from the stored export
 * definition's own filter array keys and used to splice it unvalidated into a raw col_filter string
 * ("$field >= ..."). The definition's filter is admin/user-authored config, not runtime-constant, so
 * an unvalidated $field there is the same class of bug as the "stored definition" sibling already
 * fixed in resources_export_csv/tracker_export_csv: only a plain column-name shape is now accepted,
 * quoted, and wrapped in Api\Db\SqlFragment::raw() - anything else is dropped rather than spliced in.
 */
class ExportCsvRangeFilterTest extends \EGroupware\Api\AppTest
{
	private function makeDefinition(array $filter): importexport_definition
	{
		$definition = new importexport_definition();
		$definition->set_record(array(
			'name' => 'phpunit_export_csv_range_filter_test',
			'application' => 'infolog',
			'plugin' => 'infolog_export_csv',
			'type' => 'export',
			'plugin_options' => array(
				'selection' => 'filter',
				'mapping' => array('info_subject' => 'Subject'),
			),
			'filter' => $filter,
		));
		return $definition;
	}

	private function export(array $filter) : importexport_export_csv
	{
		$plugin = new infolog_export_csv();
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

	/**
	 * A legitimate numeric-column range filter must still actually filter.
	 */
	public function testLegitimateRangeFilterStillWorks()
	{
		$result = $this->export(array(
			'info_type' => 'phpunit_nonexistent_type_xyz',
			'info_priority' => array('from' => 99, 'to' => 99),
		));

		$this->assertInstanceOf(importexport_export_csv::class, $result);
		$this->assertSame(0, $result->get_num_of_records(),
			'combined with the nonexistent info_type, this must still just filter down to nothing, not error');
	}

	/**
	 * A $field that isn't a plain (table.)column name - the shape a stored definition should never
	 * legitimately have, but could if crafted or corrupted - must be dropped, not spliced into SQL.
	 */
	public function testMaliciousFieldNameIsDroppedNotSpliced()
	{
		$result = $this->export(array(
			'info_type' => 'phpunit_nonexistent_type_xyz',
			"info_priority) OR 1=1-- -" => array('from' => 1, 'to' => 1),
		));

		$this->assertInstanceOf(importexport_export_csv::class, $result,
			'a malformed filter field must be dropped silently, not break the export');
		$this->assertSame(0, $result->get_num_of_records());
	}
}
