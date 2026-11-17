<?php
/**
 * EGroupware Addressbook: regression test for addressbook_import_contacts_csv's "exists" condition
 * on a custom field (ticket #128061)
 *
 * @link https://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Storage\Customfields;

/**
 * init()'s pre-cache for an "exists" dedup condition (eg. a CSV import matching rows on a
 * "Matrikelnummer"/"kundennummer" custom field) used to put the bare custom-field name at an
 * integer col_filter key, which Api\Db now rejects outright as an unvalidated raw SQL fragment -
 * it was never a working filter to begin with (confirmed: it throws a SQL syntax error even with
 * that check disabled), just one that happened not to blow up loudly before. It now joins the
 * extra table itself, scoped to that one field, instead of relying on the filter array for it.
 */
class ImportCsvExistsConditionCfTest extends LoggedInTest
{
	const CF_NAME = 'ImportCsvExistsConditionCfTest field';

	private array $contact_ids = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$fields = Customfields::get('addressbook');
		$fields[self::CF_NAME] = [
			'type' => 'text', 'type2' => [], 'label' => self::CF_NAME,
			'help' => '', 'values' => null, 'len' => null, 'rows' => null,
			'order' => null, 'needed' => null, 'private' => [],
		];
		Customfields::save('addressbook', $fields);
	}

	public static function tearDownAfterClass(): void
	{
		$fields = Customfields::get('addressbook');
		unset($fields[self::CF_NAME]);
		Customfields::save('addressbook', $fields);
		parent::tearDownAfterClass();
	}

	protected function tearDown(): void
	{
		$bo = new Api\Contacts();
		foreach ($this->contact_ids as $id)
		{
			$bo->delete($id);
		}
		$this->contact_ids = [];
		parent::tearDown();
	}

	public function testInitWithCfExistsConditionDoesNotThrowAndFindsMatch()
	{
		$bo = new Api\Contacts();
		$contact_a = ['n_family' => 'ImportCsvExistsConditionCfTest-A-'.uniqid(), '#'.self::CF_NAME => 'ABC123'];
		$contact_b = ['n_family' => 'ImportCsvExistsConditionCfTest-B-'.uniqid()];	// no value for the CF at all
		$id_a = $bo->save($contact_a);
		$id_b = $bo->save($contact_b);
		$this->contact_ids = [$id_a, $id_b];

		$definition = new importexport_definition('');
		$definition->plugin_options = [
			'contact_owner' => $this->user,
			'conditions' => [[
				'type' => 'exists',
				'string' => '#'.self::CF_NAME,
				'true' => ['action' => 'update', 'last' => true],
				'false' => ['action' => 'insert', 'last' => true],
			]],
		];

		$plugin = new addressbook_import_contacts_csv();
		$init = new \ReflectionMethod($plugin, 'init');
		$init->setAccessible(true);
		$init->invoke($plugin, $definition);
		$this->addToAssertionCount(1);	// got here without throwing

		$cached = new \ReflectionProperty($plugin, 'cached_condition');
		$cached->setAccessible(true);
		$cache = $cached->getValue($plugin)['#'.self::CF_NAME];

		$this->assertArrayHasKey('ABC123', $cache, 'the contact with the CF value set must be found in the cache');
		$this->assertSame($id_a, (int)$cache['ABC123'][0]['contact_id']);
	}
}
