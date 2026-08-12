<?php
/**
 * EGroupware Infolog: regression test tying infolog_export_csv's info_contact/linked
 * filter guard together with importexport_widget_filter::validate()'s partial-link-value
 * guard (see importexport/tests/ImportexportWidgetFilterTest.php)
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Background: a saved filter definition used to be able to store a partial
 * info_contact link value (an 'app' chosen with no entry id). validate() now drops
 * that partial value entirely (see importexport_widget_filter::validate()), so a
 * definition's filter can end up with NO 'info_contact'/'linked' key at all - the same
 * state as a filter that never had a contact filter in the first place.
 *
 * infolog_export_csv::export() has its own guard for this
 * (class.infolog_export_csv.inc.php: `if ($query['col_filter']['info_contact'] ||
 * $query['col_filter']['linked'])`), which must tolerate that key being entirely
 * absent and just proceed to a normal, unfiltered-by-contact search.
 *
 * This is an integration-style check, not a unit test: infolog_export_csv's
 * constructor builds a real infolog_bo, and export() calls infolog_bo::search()
 * against the live DB, so it needs a full logged-in session.
 *
 * Pass criteria: export() completes without throwing/erroring when
 * $_definition->filter has no 'info_contact' key, and returns a normal
 * importexport_export_csv result object. The filter is scoped to an info_type that
 * cannot match any real record, so the search itself stays fast and its row count
 * (0) is deterministic regardless of what other data exists in the dev DB.
 */
class ExportCsvContactFilterTest extends \EGroupware\Api\AppTest
{
	private function makeDefinition(array $filter): importexport_definition
	{
		$definition = new importexport_definition();
		$definition->set_record(array(
			'name' => 'phpunit_export_csv_contact_filter_test',
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

	/**
	 * No 'info_contact' key at all in the filter (the state left after validate()
	 * drops a partial value) - export() must not error, and must run a normal search.
	 */
	public function testExportWithoutContactFilterKeyDoesNotError()
	{
		$definition = $this->makeDefinition(array('info_type' => 'phpunit_nonexistent_type_xyz'));

		$plugin = new infolog_export_csv();
		$stream = fopen('php://memory', 'w+');
		try
		{
			$result = $plugin->export($stream, $definition);
		}
		finally
		{
			fclose($stream);
		}

		$this->assertInstanceOf(importexport_export_csv::class, $result);
		$this->assertSame(0, $result->get_num_of_records(),
			'the info_type filter is scoped to match nothing, so the export must be empty but still succeed');
	}

	/**
	 * A complete info_contact link value (app + real id) must still work through the
	 * info_contact/linked guard in export() - this is the counterpart "guard actually
	 * does something" case, so the empty-filter test above isn't just vacuously true.
	 */
	public function testExportWithCompleteContactFilterDoesNotError()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$definition = $this->makeDefinition(array(
			'info_type' => 'phpunit_nonexistent_type_xyz',
			'info_contact' => array('app' => 'addressbook', 'id' => $account_id),
		));

		$plugin = new infolog_export_csv();
		$stream = fopen('php://memory', 'w+');
		try
		{
			$result = $plugin->export($stream, $definition);
		}
		finally
		{
			fclose($stream);
		}

		$this->assertInstanceOf(importexport_export_csv::class, $result);
		$this->assertSame(0, $result->get_num_of_records());
	}
}
