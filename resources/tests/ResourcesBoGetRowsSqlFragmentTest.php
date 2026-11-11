<?php
/**
 * EGroupware resources: regression test for resources_bo::get_rows() under
 * Db::requireSqlFragmentObjects()
 *
 * @link https://www.egroupware.org
 * @package resources
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * resources_bo::get_rows() builds two of its own filter conditions as raw SQL
 * ('accessory_of != -1' and 'deleted IS NOT NULL') - this proves they're wrapped in
 * Db\SqlFragment, so resources_so's Db::requireSqlFragmentObjects(true) opt-in
 * (doc/ai/projects/sql-fragment-type-safety.md) doesn't throw on its own queries.
 */
class ResourcesBoGetRowsSqlFragmentTest extends \EGroupware\Api\AppTest
{
	private function getRows(array $query) : array
	{
		$bo = new resources_bo();
		$rows = array();
		$readonlys = array();
		$bo->get_rows($query, $rows, $readonlys);
		return $rows;
	}

	public function testAccessoriesOnlyFilterDoesNotThrow()
	{
		$rows = $this->getRows(array('filter2' => -2, 'start' => 0, 'num_rows' => 1));

		$this->assertIsArray($rows);
	}

	public function testDeletedFilterDoesNotThrow()
	{
		$rows = $this->getRows(array('filter2' => resources_bo::DELETED, 'start' => 0, 'num_rows' => 1));

		$this->assertIsArray($rows);
	}

	public function testAllFilterDoesNotThrow()
	{
		$rows = $this->getRows(array('filter2' => -3, 'start' => 0, 'num_rows' => 1));

		$this->assertIsArray($rows);
	}

	/**
	 * resources_export_csv::export()'s own "filter" selection-mode range builder
	 * (`$query['col_filter'][] = "$field >= " . (int)$value['from']`) built a bare string
	 * itself at an integer key, missed by the original conversion - a numeric-range filter
	 * (eg. "quantity from 2 to 10") on the CSV export wizard threw instead of exporting.
	 */
	public function testExportCsvRangeFilterDoesNotThrow()
	{
		$definition = new \importexport_definition();
		$definition->plugin_options = array('selection' => 'filter', 'mapping' => array());
		$definition->filter = array('quantity' => array('from' => 1, 'to' => 10));

		$stream = fopen('php://memory', 'w+');
		try
		{
			$export_object = (new \resources_export_csv())->export($stream, $definition);

			$this->assertInstanceOf(\importexport_export_csv::class, $export_object,
				'export() with a numeric-range filter must complete, not throw');
		}
		finally
		{
			fclose($stream);
		}
	}
}
