<?php
/**
 * EGroupware Addressbook: regression test for Storage::cf_filter()'s lettersearch tail validation
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
 * Storage::cf_filter()'s lettersearch branch (`#cfname` at an integer col_filter key) only ever
 * legitimately splices a matched, validated custom-field name plus a trailing operator and a single
 * safely-quoted value (eg. " LIKE 's%'", built server-side from a search letter) into the SQL JOIN.
 * The custom-field *name* itself is correctly validated (it must match a real, existing field, which
 * is why it's allowed to contain spaces), but the *tail* after it used to be spliced in verbatim with
 * no validation of its own shape at all - letting anything reaching this sink with that exact shape
 * (operator text/spaces, then one well-formed quoted literal) through, and rejecting anything else.
 */
class CfLettersearchValidationTest extends LoggedInTest
{
	const CF_NAME = 'CfLettersearchValidationTest field';	// deliberately contains spaces

	private array $contact_ids = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$fields = Customfields::get('addressbook');
		$fields[self::CF_NAME] = [
			'type' => 'text', 'type2' => [], 'label' => 'CfLettersearchValidationTest field',
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
	 * @return array [Api\Contacts $bo, int $id_a ("apple"), int $id_b ("banana")]
	 */
	private function twoContacts() : array
	{
		$bo = new Api\Contacts();
		$contact_a = ['n_family' => 'CfLettersearchValidationTest-A-'.uniqid(), '#'.self::CF_NAME => 'apple'];
		$contact_b = ['n_family' => 'CfLettersearchValidationTest-B-'.uniqid(), '#'.self::CF_NAME => 'banana'];
		$id_a = $bo->save($contact_a);
		$id_b = $bo->save($contact_b);
		$this->contact_ids = [$id_a, $id_b];
		return [$bo, $id_a, $id_b];
	}

	/**
	 * A properly-built lettersearch condition (field name, including its embedded spaces, correctly
	 * matched; tail a single `LIKE`/quoted-value pair) must keep working exactly as before.
	 */
	public function testLegitimateLettersearchStillMatches()
	{
		[$bo, $id_a, $id_b] = $this->twoContacts();

		$like = $bo->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE];
		$condition = '#'.self::CF_NAME.' '.$like.' '.$bo->db->quote('a%');

		$rows = $bo->search('', false, '', '', '', false, 'AND', false,
			[0 => $condition, 'contact_id' => [$id_a, $id_b]]);

		$this->assertCount(1, $rows, 'the lettersearch condition must still select exactly the "apple" row');
		$this->assertSame($id_a, (int)$rows[0]['id']);
	}

	/**
	 * A tail built from SQL boolean keywords instead of symbols (which the regex guarding the
	 * `order` parameter itself still allows, since custom-field names may contain spaces) must not
	 * reach the JOIN as a no-op-disguised-as-true condition.
	 */
	public function testMalformedTailCannotForceAMatch()
	{
		[$bo, $id_a, $id_b] = $this->twoContacts();

		$condition = '#'.self::CF_NAME.' or not false';
		$rows = $bo->search('', false, '', '', '', false, 'AND', false,
			[0 => $condition, 'contact_id' => [$id_a, $id_b]]);

		$this->assertCount(2, $rows,
			'a malformed tail must be dropped entirely, not force every row to match');
	}

	/**
	 * Same shape, the inverse direction: a tail that would otherwise suppress every row.
	 */
	public function testMalformedTailCannotSuppressAllRows()
	{
		[$bo, $id_a, $id_b] = $this->twoContacts();

		$condition = '#'.self::CF_NAME.' and false';
		$rows = $bo->search('', false, '', '', '', false, 'AND', false,
			[0 => $condition, 'contact_id' => [$id_a, $id_b]]);

		$this->assertCount(2, $rows,
			'a malformed tail must be dropped entirely, not suppress every row');
	}
}
