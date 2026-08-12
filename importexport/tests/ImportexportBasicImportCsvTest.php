<?php
/**
 * EGroupware importexport: tests for the DB-free parts of importexport_basic_import_csv
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/src/loader/common.php');

use PHPUnit\Framework\TestCase;

/**
 * Minimal fake egw_record: just enough of importexport_iface_egw_record for equal()/
 * less_than() to read arbitrary fields via magic __get/__set. No DB, no real app record
 * class involved.
 */
class FakeEgwRecordForBasicImportTest implements importexport_iface_egw_record
{
	public static $types = array();
	private $data = array();

	public function __construct($_identifier = '') {}
	public function __get($name) { return $this->data[$name] ?? null; }
	public function __set($name, $value) { $this->data[$name] = $value; }
	public function get_record_array() { return $this->data; }
	public function get_title() { return (string)($this->data['title'] ?? ''); }
	public function set_record(array $_record) { $this->data = $_record; }
	public function get_identifier() { return $this->data['id'] ?? null; }
	public function get_icon() { return ''; }
	public function save($_dst_identifier) { return null; }
	public function copy($_dst_identifier) { return null; }
	public function move($_dst_identifier) { return null; }
	public function delete() {}
	public function __destruct() {}
}

/**
 * Test-only concrete subclass: importexport_basic_import_csv is abstract (only
 * action() is abstract - implemented here as a no-op), and exposes protected
 * equal()/less_than() as public wrappers so they can be tested directly.
 */
class TestableBasicImportCsv extends importexport_basic_import_csv
{
	protected function action($_action, importexport_iface_egw_record &$record, $record_num = 0)
	{
		return true;
	}

	public function callEqual(importexport_iface_egw_record &$record, array &$condition)
	{
		return $this->equal($record, $condition);
	}

	public function callLessThan(importexport_iface_egw_record &$record, array &$condition)
	{
		return $this->less_than($record, $condition);
	}
}

/**
 * Tests for importexport_basic_import_csv's condition-checking methods (equal(),
 * less_than()) and its static plugin-metadata getters, all of which are pure/DB-free:
 * they only read a field off a fake record via magic properties, or return a static
 * string. The much larger import()/action()/do_special_fields() surface needs a real
 * DB-backed importexport_definition and Link::query(), so is covered separately at the
 * AppTest tier (see ImportexportBasicImportCsvDbTest).
 */
class ImportexportBasicImportCsvTest extends TestCase
{
	private function makeImporter(): TestableBasicImportCsv
	{
		return new TestableBasicImportCsv();
	}

	public function testEqualMatchesWhenFieldEqualsOperand()
	{
		$record = new FakeEgwRecordForBasicImportTest();
		$record->status = 'open';
		$condition = array('string' => 'status', 'op_2' => 'open');

		$result = $this->makeImporter()->callEqual($record, $condition);

		$this->assertTrue($result);
	}

	public function testEqualFailsWhenFieldDiffersFromOperand()
	{
		$record = new FakeEgwRecordForBasicImportTest();
		$record->status = 'open';
		$condition = array('string' => 'status', 'op_2' => 'closed');

		$result = $this->makeImporter()->callEqual($record, $condition);

		$this->assertFalse($result);
	}

	public function testLessThanUsesPhpComparison()
	{
		$record = new FakeEgwRecordForBasicImportTest();
		$record->count = 5;

		$importer = $this->makeImporter();
		$lessCondition = array('string' => 'count', 'op_2' => 10);
		$this->assertTrue($importer->callLessThan($record, $lessCondition));
		$moreCondition = array('string' => 'count', 'op_2' => 2);
		$this->assertFalse($importer->callLessThan($record, $moreCondition));
	}

	public function testStaticPluginMetadata()
	{
		$this->assertSame('Basic CSV import', importexport_basic_import_csv::get_name());
		$this->assertSame('csv', importexport_basic_import_csv::get_filesuffix());
		$this->assertNotEmpty(importexport_basic_import_csv::get_description());
	}
}
