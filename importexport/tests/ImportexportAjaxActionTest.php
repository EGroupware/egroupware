<?php
/**
 * EGroupware importexport: the ajax endpoint the definition list's actions now call
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

/**
 * importexport_definitions_ui::ajax_action() is a new endpoint: Copy, Create export and Delete
 * used to submit the whole eTemplate, rebuilding the list. Export next to them stays a
 * postSubmit - it needs a real form POST to reach the browser as a file.
 *
 * The endpoint has to name its menuaction: the "<app>.<app>_ui.ajax_action" convention the client
 * falls back to would be importexport.importexport_ui, which does not exist, so the request would
 * 400 and the action be silently lost.
 *
 * PASS CRITERIA
 * The definitions really changed (read back through importexport_definitions_bo), and the
 * response carries an egw.refresh call naming importexport.
 */
class ImportexportAjaxActionTest extends \EGroupware\Api\AppTest
{
	/** @var int[] definition_ids created by this test, including copies */
	private $definition_ids = [];

	protected function setUp(): void
	{
		parent::setUp();
		Api\Json\Response::get()->initResponseArray();
	}

	protected function tearDown(): void
	{
		// bypass the bo's ownership check, so cleanup works whatever a test did to owner
		$so = new Api\Storage\Base(importexport_definitions_bo::_appname,
			importexport_definitions_bo::_defintion_table);
		foreach($this->definition_ids as $id)
		{
			$so->delete(['definition_id' => $id]);
		}
		$this->definition_ids = [];
		parent::tearDown();
	}

	/**
	 * A real eTemplate request id, the way the browser sends one along - the endpoint refuses
	 * without it, see Nextmatch::validateExecId().  Writing to the request is what persists it.
	 */
	private function execId(): string
	{
		$request = \EGroupware\Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = ['nm' => []];
		unset($request);
		return $id;
	}

	/**
	 * The egw.refresh call the response should carry, or null
	 */
	private function refreshCall(): ?array
	{
		$response = Api\Json\Response::get();
		$prop = (new ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['type'] ?? null) === 'apply' && (($chunk['data']['func'] ?? null) === 'egw.refresh'))
			{
				return (array)$chunk['data']['parms'];
			}
		}
		return null;
	}

	private function makeDefinition(string $name, string $type='import'): int
	{
		(new importexport_definitions_bo())->save([
			'name'           => $name,
			'application'    => 'infolog',
			'plugin'         => $type === 'import' ? 'infolog_import_infologs_csv' : 'infolog_export_csv',
			'type'           => $type,
			'plugin_options' => ['fieldsep' => ',', 'charset' => 'utf-8'],
			'owner'          => $GLOBALS['egw_info']['user']['account_id'],
			'allowed_users'  => [$GLOBALS['egw_info']['user']['account_id']],
		]);
		$found = new importexport_definitions_bo(['name' => $name], true);
		$ids = $found->get_definitions();
		$this->assertNotEmpty($ids, 'saved definition must be findable by name afterwards');

		return $this->definition_ids[] = (int)$ids[0];
	}

	private function findByName(string $name): array
	{
		$found = new importexport_definitions_bo(['name' => $name], true);
		$ids = array_map('intval', (array)$found->get_definitions());
		foreach($ids as $id)
		{
			if (!in_array($id, $this->definition_ids)) $this->definition_ids[] = $id;
		}
		return $ids;
	}

	private function exists($id): bool
	{
		$definition = (new importexport_definitions_bo())->read((int)$id);
		return !empty($definition['definition_id']);
	}

	/**
	 * The regression shape: the endpoint has to reach action()'s body and really delete.
	 */
	public function testDeleteRemovesTheDefinition()
	{
		$id = $this->makeDefinition('phpunit_ajax_del_'.bin2hex(random_bytes(5)));
		$this->assertTrue($this->exists($id), 'fixture was not created');

		(new importexport_definitions_ui())->ajax_action($this->execId(), 'delete', [$id]);

		$this->assertFalse($this->exists($id), 'delete must remove the definition');
		$parms = $this->refreshCall();
		$this->assertNotNull($parms, 'the endpoint must answer with egw.refresh');
		$this->assertEquals($id, $parms[2]);
		$this->assertSame('delete', $parms[3]);
		$this->assertStringContainsString('1 definition', (string)$parms[0],
			'and must report what it deleted, not "0 definition(s) deleted"');
	}

	/**
	 * Copy adds a definition, so the list reloads rather than being told about one row.
	 */
	public function testCopyCreatesASecondDefinitionAndReloads()
	{
		$name = 'phpunit_ajax_copy_'.bin2hex(random_bytes(5));
		$id = $this->makeDefinition($name);

		(new importexport_definitions_ui())->ajax_action($this->execId(), 'copy', [$id]);

		$this->assertNotEmpty($this->findByName($name.' copy'), 'copy must create "<name> copy"');
		$parms = $this->refreshCall();
		$this->assertNull($parms[2], 'a copy lands wherever the sort puts it, so no single id');
		$this->assertNull($parms[3], 'and no type, so egw.refresh reloads the list');
	}

	/**
	 * Create export builds a matching export definition from an import one.
	 */
	public function testCreateExportBuildsAnExportDefinition()
	{
		$name = 'phpunit_ajax_cx_'.bin2hex(random_bytes(5));
		$id = $this->makeDefinition($name, 'import');

		(new importexport_definitions_ui())->ajax_action($this->execId(), 'createexport', [$id]);

		// whatever it is called, something new must exist and the list must be told to reload
		$parms = $this->refreshCall();
		$this->assertNotNull($parms, 'the endpoint must answer with egw.refresh');
		$this->assertNull($parms[2], 'a new definition lands wherever the sort puts it');
		// export_from_import() names the new definition "<title>-export"; pick it up by that
		// and by the fixture's own name, so tearDown removes whichever it turns out to be
		$this->findByName($name);
		$this->findByName($name.'-export');
		$this->assertNotEmpty($this->definition_ids, 'something new must exist afterwards');
	}

	/**
	 * Without a valid exec id the endpoint must do nothing at all.
	 */
	public function testABogusExecIdDeletesNothing()
	{
		$id = $this->makeDefinition('phpunit_ajax_bogus_'.bin2hex(random_bytes(5)));

		(new importexport_definitions_ui())->ajax_action('importexport_nobody_not-real', 'delete', [$id]);

		$this->assertTrue($this->exists($id), 'a rejected request must not run the action');
		$this->assertNull($this->refreshCall(), 'and must not answer with egw.refresh either');
	}

	/**
	 * The 2nd argument names the app whose lists to refresh; the 5th (_targetapp) names the
	 * WINDOW, and must stay null here.
	 *
	 * This list is normally opened from the admin tree, so it is rendered in admin's window.
	 * Naming 'importexport' there sends egw.refresh() looking for an importexport window that is
	 * not the one showing the list, and the refresh is silently dropped - confirmed in the
	 * browser: the copy happened, the message appeared, and the list never changed.
	 */
	public function testRefreshNamesTheAppButNotTheWindow()
	{
		$id = $this->makeDefinition('phpunit_ajax_args_'.bin2hex(random_bytes(5)));

		(new importexport_definitions_ui())->ajax_action($this->execId(), 'delete', [$id]);

		$parms = $this->refreshCall();
		$this->assertSame('importexport', $parms[1],
			'importexport sends no push, so it cannot use the msg-only sentinel');
		$this->assertNull($parms[4] ?? null,
			'_targetapp must stay null, or the refresh never reaches the window showing the list');
	}
}
