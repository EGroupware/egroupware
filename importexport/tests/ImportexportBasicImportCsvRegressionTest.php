<?php
/**
 * EGroupware importexport: regression tests for two bugs found and fixed in
 * importexport_basic_import_csv while building out this test suite:
 *
 * 1. do_special_fields() called shift($result) instead of array_shift($result) -
 *    shift() is not a PHP builtin, so ANY record with a 'link_search'/plain-appname
 *    special field fatally errored ("Call to undefined function shift()").
 * 2. preview() computed $label = is_string($fields[$field]) ?: (...), which assigns
 *    the boolean result of is_string() rather than the field's own string value.
 *
 * Both need a logged-in session: do_special_fields()'s buggy branch calls
 * Link::query() (DB), and importexport_definition (needed to drive preview()) clones
 * $GLOBALS['egw']->db in its constructor - see ImportexportWidgetFilterTest for the
 * same DB-at-construction-time constraint elsewhere in this app.
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Minimal fake egw_record, same shape as the one in ImportexportBasicImportCsvTest but
 * declared under a different name to avoid a class-already-declared clash when both
 * test files load in the same PHPUnit run.
 */
class FakeEgwRecordForRegressionTest implements importexport_iface_egw_record
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
 * Test-only plugin exposing do_special_fields() and overriding import() so preview()
 * can be exercised without needing a real app record/wizard class for the *import*
 * side - only the label lookup (a real, deliberately-named fake wizard class below)
 * matters for the bug under test.
 */
class phpunittest_preview_regression_csv extends importexport_basic_import_csv
{
	protected function action($_action, importexport_iface_egw_record &$record, $record_num = 0)
	{
		return true;
	}

	public function setDryRun(bool $dry_run)
	{
		$this->dry_run = $dry_run;
	}

	public function setDefinition(importexport_definition $definition)
	{
		$this->definition = $definition;
	}

	public function callDoSpecialFields(importexport_iface_egw_record &$record, &$import_csv)
	{
		return $this->do_special_fields($record, $import_csv);
	}

	/** @var importexport_iface_egw_record[] records preview() should see, set by the test */
	public $fakePreviewRecords = array();

	/**
	 * Bypass the real CSV/record-class/action pipeline entirely - this test is about
	 * preview()'s post-import label computation, not import() itself (which has its
	 * own coverage in ImportexportBasicImportCsvTest's condition-checking tests and
	 * ImportexportImportCsvTest's CSV-reading tests).
	 */
	public function import($_stream, importexport_definition $_definition)
	{
		$this->preview_records = $this->fakePreviewRecords;
		return count($this->preview_records);
	}
}

/**
 * Fake wizard class matching the naming convention preview() derives from
 * ($definition->application . '_wizard_' . the plugin class name with the app prefix
 * stripped) - see class.importexport_basic_import_csv.inc.php's preview(). Giving
 * preview() a real, controllable wizard class (rather than letting the lookup fail and
 * fall into the catch(Exception) fallback) is what lets this test pin down the exact
 * buggy expression.
 */
class phpunittest_wizard_preview_regression_csv
{
	public function get_import_fields()
	{
		return array(
			// A field whose wizard entry is a plain string label - this is the exact
			// shape that triggered the is_string(...) ?: (...) bug: pre-fix, $label
			// ended up as the boolean true instead of 'String Label'.
			'string_field' => 'String Label',
			// A field whose wizard entry is an array with an explicit 'label' - the
			// other, already-correct branch, kept here so the test also proves the
			// fix didn't break it.
			'array_field' => array('label' => 'Array Label'),
		);
	}
}

class ImportexportBasicImportCsvRegressionTest extends \EGroupware\Api\AppTest
{
	/**
	 * Regression test for the shift() -> array_shift() fix, driven end-to-end rather
	 * than just checking "didn't fatal": a 'link_search' field on a real infolog entry,
	 * searching for a real, uniquely-named contact, must resolve through the buggy
	 * do..while loop to that contact's id and actually create the addressbook link -
	 * pre-fix, Link::query() finding a match made no difference, since the loop body
	 * itself ("shift($result)", not a PHP builtin) fatally errored ("Call to undefined
	 * function shift()") on its very first iteration regardless of what was found.
	 *
	 * Creates a temp contact and a temp infolog entry, and removes both plus the link
	 * created between them in tearDown, even if the test fails.
	 */
	public function testDoSpecialFieldsLinkSearchResolvesAndLinksRealMatch()
	{
		$contacts = new EGroupware\Api\Contacts();
		$contact_data = array('n_family' => 'PhpunitSpecialFieldsFamily'.bin2hex(random_bytes(4)));
		$contacts->save($contact_data);
		$contact_id = $contact_data['id'];
		$this->assertNotEmpty($contact_id, 'test contact must exist before searching for it');

		$infolog_bo = new infolog_bo();
		$info_data = array('info_subject' => 'phpunit special fields test');
		$info_id = $infolog_bo->write($info_data, true, true, true, true);
		$this->assertNotEmpty($info_id, 'test infolog entry must exist to be the link source');

		try
		{
			$record = new FakeEgwRecordForRegressionTest();
			$record->link_search = 'addressbook:'.$contact_data['n_family'];
			$record->id = $info_id;

			$definition = new importexport_definition();
			$definition->set_record(array(
				'name' => 'phpunit_special_fields_test',
				'application' => 'infolog',
				'plugin' => 'infolog_export_csv', // real plugin name, only to pass set_record()'s validation
				'type' => 'import',
				'plugin_options' => array(),
				'filter' => array(),
			));

			$plugin = new phpunittest_preview_regression_csv();
			$plugin->setDryRun(false);
			$plugin->setDefinition($definition);

			$dummy_import_csv = null;
			$plugin->callDoSpecialFields($record, $dummy_import_csv);

			$links = EGroupware\Api\Link::get_links('infolog', $info_id, 'addressbook');
			// Link::get_links() returns ids as numeric strings; $contact_id came from
			// Contacts::save() as an int - compare loosely rather than mixing types.
			$this->assertContains((string)$contact_id, $links,
				'do_special_fields() must resolve the link_search match and create a real addressbook link');
		}
		finally
		{
			// Each cleanup step is independent (own try/catch): a failure in one
			// (eg. unlink() finding nothing to remove because the assertion above
			// failed before a link was ever created) must not skip the rest - found
			// via this test occasionally leaving its temp contact behind when an
			// earlier step in an unguarded sequence threw.
			try { EGroupware\Api\Link::unlink(0, 'infolog', $info_id, '', 'addressbook', $contact_id); } catch (\Throwable $e) {}
			try { $infolog_bo->delete($info_id); } catch (\Throwable $e) {}
			try { $infolog_bo->delete($info_id); } catch (\Throwable $e) {}
			try { (new EGroupware\Api\Storage\Base('api', 'egw_addressbook'))->delete(array('contact_id' => $contact_id)); } catch (\Throwable $e) {}
		}
	}

	/**
	 * Regression test for the is_string(...) ?: (...) -> is_string(...) ? ... : (...)
	 * fix. Drives the real preview() method (import() overridden to skip the
	 * unrelated CSV/record-class pipeline) with one fake preview record and a fake
	 * wizard providing both a string-label field and an array-label field.
	 *
	 * Pass criteria: the string-label field's header in the returned HTML table must
	 * contain the actual label text ('String Label'), not the word "1" (PHP's string
	 * cast of the boolean true that the old ternary produced).
	 */
	public function testPreviewUsesStringLabelNotBooleanTrue()
	{
		$definition = new importexport_definition();
		$definition->set_record(array(
			'name' => 'phpunit_preview_regression_test',
			'application' => 'phpunittest',
			// Deliberately a REAL, registered plugin name - set_record() validates
			// this via importexport_helper_functions::is_valid_plugin(), which
			// doesn't know about our test-only phpunittest_preview_regression_csv
			// class. This is independent of preview()'s own get_called_class() (used
			// for the wizard name lookup below), which correctly reflects the actual
			// object regardless of what's stored in $definition->plugin.
			'plugin' => 'infolog_export_csv',
			'type' => 'import',
			'plugin_options' => array(
				'field_mapping' => array('string_field' => 'string_field', 'array_field' => 'array_field'),
			),
			'filter' => array(),
		));

		$plugin = new phpunittest_preview_regression_csv();
		$plugin->fakePreviewRecords = array(new FakeEgwRecordForRegressionTest());

		$stream = fopen('php://memory', 'w+');
		try
		{
			$html = $plugin->preview($stream, $definition);
		}
		finally
		{
			fclose($stream);
		}

		$this->assertStringContainsString('String Label', $html,
			'the string-label field must show its actual label, not a boolean cast to string');
		$this->assertStringNotContainsString('>1<', $html,
			'the old is_string(...) ?: (...) bug rendered the boolean true (cast to "1") as the header text');
		$this->assertStringContainsString('Array Label', $html,
			'the array-label field (already correct before the fix) must still work');
	}
}
