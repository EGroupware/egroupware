<?php
/**
 * EGroupware Addressbook: regression test for Storage::cf_filter()'s lettersearch branch
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
 * Storage::cf_filter()'s lettersearch branch (an integer col_filter key) used to receive its
 * condition as one pre-assembled raw string ("#cfname LIKE 's%'") and had to re-parse it apart again
 * to recover the custom-field name and re-validate whatever followed it, before daring to splice it
 * into the JOIN - a sink that has to deconstruct and guess at a string someone else built. It now
 * instead takes an Api\Db\CfLetterSearchFragment, whose constructor validates the operator and quotes
 * the value once, at the moment a caller actually has those parts - so cf_filter() only ever reads
 * $cf_name back out and casts the rest to its already-safe SQL text.
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
	 * matched; a real LIKE operator) must keep working exactly as before.
	 */
	public function testLegitimateLettersearchStillMatches()
	{
		[$bo, $id_a, $id_b] = $this->twoContacts();

		$like = $bo->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE];
		$condition = new Api\Db\CfLetterSearchFragment($bo->db, self::CF_NAME, $like, 'a%');

		$rows = $bo->search('', false, '', '', '', false, 'AND', false,
			[0 => $condition, 'contact_id' => [$id_a, $id_b]]);

		$this->assertCount(1, $rows, 'the lettersearch condition must still select exactly the "apple" row');
		$this->assertSame($id_a, (int)$rows[0]['id']);
	}

	/**
	 * An operator that isn't one of the handful of real SQL comparison operators (eg. SQL boolean
	 * keywords, used by the pre-fix bypass to turn the condition into an always-true/always-false
	 * no-op) must never reach construction - there's no "tail" left to later validate or drop, the
	 * fragment simply cannot be built from one.
	 */
	public function testInvalidOperatorIsRejectedAtConstruction()
	{
		$bo = new Api\Contacts();

		$this->expectException(\InvalidArgumentException::class);
		new Api\Db\CfLetterSearchFragment($bo->db, self::CF_NAME, 'or not false', 'a%');
	}

	/**
	 * The legacy shape (a plain, pre-assembled string at an integer col_filter key) is no longer
	 * recognised by cf_filter() at all - Contacts\Sql opts into requireSqlFragmentObjects(), so
	 * anything reaching the sink that isn't itself a SqlFragment instance (the typed fragment being
	 * the only legitimate one for this branch) is rejected outright rather than string-sniffed.
	 */
	public function testRawStringAtIntegerKeyIsRejectedNotSniffed()
	{
		[$bo, $id_a, $id_b] = $this->twoContacts();

		$like = $bo->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE];
		$condition = '#'.self::CF_NAME.' '.$like.' '.$bo->db->quote('a%');

		$this->expectException(Api\Db\Exception\InvalidArgument::class);
		$bo->search('', false, '', '', '', false, 'AND', false,
			[0 => $condition, 'contact_id' => [$id_a, $id_b]]);
	}

	/**
	 * End-to-end: the actual reported sink, addressbook_ui::get_rows() with a 'searchletter' and an
	 * 'order' naming a custom field - not just Storage::cf_filter() in isolation.
	 */
	public function testGetRowsSearchletterOnCustomFieldOrderFiltersCorrectly()
	{
		[, $id_a, $id_b] = $this->twoContacts();

		$query = [
			'order' => '#'.self::CF_NAME, 'sort' => 'ASC', 'searchletter' => 'a',
			'col_filter' => ['contact_id' => [$id_a, $id_b]], 'start' => 0, 'num_rows' => 10,
			'search' => '', 'filter' => '', 'cat_id' => '', 'grouped_view' => '',
			'advanced_search' => null, 'csv_export' => false, 'template' => '',
		];
		$rows = $readonlys = [];
		(new \addressbook_ui())->get_rows($query, $rows, $readonlys, true);

		$this->assertSame([$id_a], array_map('intval', $rows),
			"searchletter 'a' on the custom-field order must select only the \"apple\" contact");
	}
}
