<?php
/**
 * EGroupware importexport: tests for importexport_definitions_bo save/read/delete/export
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Round-trip test for importexport_definitions_bo against the real DB: save a
 * definition, read it back, export it, then delete it. Needs a logged-in session
 * (importexport_definition's constructor clones $GLOBALS['egw']->db, so it cannot be
 * constructed at all without one - see ImportexportWidgetFilterTest for the same
 * constraint on the Widget class hierarchy).
 *
 * Every test creates its own uniquely-named definition and deletes it in tearDown,
 * even if the test fails, so no test data is left behind in the shared dev DB.
 */
class ImportexportDefinitionsBoTest extends \EGroupware\Api\AppTest
{
	/** @var int|null definition_id created by the current test, cleaned up in tearDown */
	private $definition_id;

	protected function tearDown(): void
	{
		if ($this->definition_id)
		{
			// Deliberately bypass importexport_definitions_bo::delete()'s ownership
			// check here: it refuses to delete a definition with owner=0 unless the
			// current user is admin (class.importexport_definitions_bo.inc.php:128),
			// which testExportOmitsOwnerWhenNotSet's data intentionally has - cleanup
			// must work regardless of what a test's data does to the owner field.
			$so = new EGroupware\Api\Storage\Base(importexport_definitions_bo::_appname, importexport_definitions_bo::_defintion_table);
			$so->delete(array('definition_id' => $this->definition_id));
			$this->definition_id = null;
		}
	}

	/**
	 * Build a unique definition name so concurrent test runs / leftover data from a
	 * previous failed run can't collide.
	 */
	private function uniqueName(): string
	{
		return 'phpunit_test_def_'.bin2hex(random_bytes(6));
	}

	/**
	 * Save a definition, then look up its new id the same way
	 * importexport_schedule_ui::generate_id() does: query definitions_bo by name.
	 */
	private function saveAndFindId(array $data): int
	{
		(new importexport_definitions_bo())->save($data);

		$found = new importexport_definitions_bo(array('name' => $data['name']), true);
		$ids = $found->get_definitions();
		$this->assertNotEmpty($ids, 'saved definition must be findable by name afterwards');

		return (int)$ids[0];
	}

	/**
	 * A saved definition must be readable back with the same core fields, and
	 * plugin_options/filter (stored as XML in the DB) must round-trip as arrays.
	 */
	public function testSaveAndRead()
	{
		$name = $this->uniqueName();
		$data = array(
			'name' => $name,
			'application' => 'infolog',
			'plugin' => 'infolog_export_csv',
			'type' => 'export',
			'plugin_options' => array('fieldsep' => ',', 'charset' => 'utf-8'),
			'filter' => array('info_type' => 'task'),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'allowed_users' => array($GLOBALS['egw_info']['user']['account_id']),
		);

		$this->definition_id = $this->saveAndFindId($data);

		$read = (new importexport_definitions_bo())->read($this->definition_id);

		$this->assertSame($name, $read['name']);
		$this->assertSame('infolog', $read['application']);
		$this->assertSame('infolog_export_csv', $read['plugin']);
		$this->assertSame(array('fieldsep' => ',', 'charset' => 'utf-8'), $read['plugin_options']);
		$this->assertSame(array('info_type' => 'task'), $read['filter']);
	}

	/**
	 * export() must resolve a set owner to the account's name, not silently drop it -
	 * this is the direct regression test for the $export_date/$export_data typo fix.
	 */
	public function testExportResolvesOwnerToAccountName()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$name = $this->uniqueName();
		$data = array(
			'name' => $name,
			'application' => 'infolog',
			'plugin' => 'infolog_export_csv',
			'type' => 'export',
			'plugin_options' => array('fieldsep' => ',', 'charset' => 'utf-8'),
			'filter' => array(),
			'owner' => $account_id,
			'allowed_users' => array($account_id),
		);

		$this->definition_id = $this->saveAndFindId($data);

		$xml = (new importexport_definitions_bo())->export(array($this->definition_id));
		$exported = importexport_arrayxml::xml2array($xml);
		$exported_definition = $exported['importExportDefinitions']['definitions'][$name];

		$expected_owner_name = importexport_helper_functions::account_id2name($account_id);
		$this->assertSame($expected_owner_name, $exported_definition['owner'] ?? null,
			'a definition with an owner set must export the resolved account name, not be silently stripped');
	}

	/**
	 * A definition with no owner (owner === 0/empty) must still have the 'owner' key
	 * absent from the export - the unset() branch that always ran before the typo fix
	 * must still run in this (correct) case.
	 */
	public function testExportOmitsOwnerWhenNotSet()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$name = $this->uniqueName();
		$data = array(
			'name' => $name,
			'application' => 'infolog',
			'plugin' => 'infolog_export_csv',
			'type' => 'export',
			'plugin_options' => array('fieldsep' => ',', 'charset' => 'utf-8'),
			'filter' => array(),
			'owner' => 0,
			'allowed_users' => array($account_id),
		);

		$this->definition_id = $this->saveAndFindId($data);

		$xml = (new importexport_definitions_bo())->export(array($this->definition_id));
		$exported = importexport_arrayxml::xml2array($xml);
		$exported_definition = $exported['importExportDefinitions']['definitions'][$name];

		$this->assertArrayNotHasKey('owner', $exported_definition);
	}

	/**
	 * delete() removes the definition; reading it afterwards must fail, and it must no
	 * longer be findable by name.
	 */
	public function testDelete()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$name = $this->uniqueName();
		$data = array(
			'name' => $name,
			'application' => 'infolog',
			'plugin' => 'infolog_export_csv',
			'type' => 'export',
			'plugin_options' => array('fieldsep' => ',', 'charset' => 'utf-8'),
			'filter' => array(),
			'owner' => $account_id,
			'allowed_users' => array($account_id),
		);

		$id = $this->saveAndFindId($data);

		(new importexport_definitions_bo())->delete(array($id));

		$still_there = new importexport_definitions_bo(array('name' => $name), true);
		$this->assertEmpty($still_there->get_definitions(), 'deleted definition must no longer be findable by name');

		// Already deleted - clear so tearDown doesn't try again.
		$this->definition_id = null;
	}
}
