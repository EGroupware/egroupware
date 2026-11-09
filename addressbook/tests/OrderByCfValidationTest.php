<?php
/**
 * EGroupware Addressbook: regression test for Storage::order_by_cf()'s sort-direction validation
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
 * Storage::order_by_cf() (sorting by a custom field's value) splits its "#cfname ASC|DESC" input on
 * the space character to recover the field name and direction - but only ever validated the field
 * name against the real custom-field list. The direction token was spliced straight into the SQL
 * ORDER BY clause unexamined, and since the split only looks for a literal space character, any
 * other whitespace (a tab, for instance) in the direction position survives as a single token while
 * still being valid SQL whitespace once it reaches the database - letting arbitrary SQL reach the
 * query, not just a non-ASC/DESC word.
 */
class OrderByCfValidationTest extends LoggedInTest
{
	const CF_NAME = 'OrderByCfValidationTest field';

	private array $contact_ids = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$fields = Customfields::get('addressbook');
		$fields[self::CF_NAME] = [
			'type' => 'text', 'type2' => [], 'label' => 'OrderByCfValidationTest field',
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

	/**
	 * A direction token that isn't ASC/DESC - using a tab rather than a space to stay a single
	 * token after the space-based split, while still being whitespace once the database parses it -
	 * must not be spliced into the query, and must not break it either.
	 */
	public function testMaliciousDirectionTokenIsNeutralized()
	{
		$bo = new Api\Contacts();
		$contact = ['n_family' => 'OrderByCfValidationTest-'.uniqid(), '#'.self::CF_NAME => 'x'];
		$id = $bo->save($contact);
		$this->contact_ids = [$id];

		$malicious_order_by = '#'.self::CF_NAME." ASC\tWHATEVER_FOLLOWS_HERE";

		$rows = $bo->search('', false, $malicious_order_by, '', '', false, 'AND', false, ['contact_id' => $id]);

		$this->assertIsArray($rows, 'a malicious direction token must not break the query');
		$this->assertCount(1, $rows);
	}

	/**
	 * Legitimate sorting by a custom field, in both directions, must keep working.
	 */
	public function testLegitimateAscAndDescStillWork()
	{
		$bo = new Api\Contacts();
		$contact_a = ['n_family' => 'OrderByCfValidationTest-A-'.uniqid(), '#'.self::CF_NAME => 'a'];
		$contact_b = ['n_family' => 'OrderByCfValidationTest-B-'.uniqid(), '#'.self::CF_NAME => 'b'];
		$id_a = $bo->save($contact_a);
		$id_b = $bo->save($contact_b);
		$this->contact_ids = [$id_a, $id_b];

		foreach (['ASC', 'DESC'] as $direction)
		{
			$rows = $bo->search('', false, '#'.self::CF_NAME.' '.$direction, '', '', false, 'AND', false,
				['contact_id' => [$id_a, $id_b]]);
			$this->assertCount(2, $rows, "sorting $direction by the custom field must still return both rows");
		}
	}
}
