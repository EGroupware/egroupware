<?php
/**
 * Test the column filters addressbook_ui::get_rows() sets itself
 *
 * @link https://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Addressbook;

use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * get_rows() searches by the addressbook, distribution list and category as column filters,
 * which it sets from the standard filters. It gives them to the search only: the query goes back
 * to the client with the list, and if it held them the client would take them for filters of
 * their own, send them with every request and save them with a favorite - where no filter widget
 * stands for them.
 */
class DerivedColFilterTest extends LoggedInTest
{
	/** @var \addressbook_ui */
	protected $ui;

	protected function setUp() : void
	{
		parent::setUp();
		$this->ui = new \addressbook_ui();
	}

	/**
	 * Run get_rows() for the list, without storing the query in the session or the preferences
	 *
	 * @param array &$query filters, on return what get_rows() made of it
	 * @return int total
	 */
	protected function rows(array &$query) : int
	{
		$query += [
			'start' => 0,
			'num_rows' => 10,
			'order' => 'n_family',
			'sort' => 'ASC',
			'filter' => '',
			'filter2' => '',
			'cat_id' => '',
			'search' => '',
			'col_filter' => [],
			'grouped_view' => '',
			'csv_export' => true,
		];
		$rows = $readonlys = [];
		return (int)$this->ui->get_rows($query, $rows, $readonlys);
	}

	public function testFiltersItSetsItselfAreNotReturned()
	{
		$query = ['filter' => '0', 'filter2' => '-1', 'cat_id' => '999999999'];
		$this->rows($query);

		foreach(\addressbook_ui::SEARCH_COL_FILTERS as $name)
		{
			$this->assertArrayNotHasKey($name, $query['col_filter'], "col_filter[$name] is given back to the client");
		}
		$this->assertSame('0', $query['filter'], 'the standard filters stay');
		$this->assertSame('-1', $query['filter2']);
		$this->assertSame('999999999', $query['cat_id']);
	}

	/**
	 * They are still searched by: a category no contact has gives no rows.
	 */
	public function testStandardFiltersAreStillApplied()
	{
		$all = [];
		$this->assertGreaterThan(0, $this->rows($all), 'test user sees no contacts at all');

		$none = ['cat_id' => '999999999'];
		$this->assertSame(0, $this->rows($none));
	}

	/**
	 * A copy the client sends is neither searched by nor returned: only the standard filter counts.
	 */
	public function testCopySentByTheClientIsIgnored()
	{
		$all = [];
		$total = $this->rows($all);

		$query = ['col_filter' => ['cat_id' => '999999999', 'list' => '999999999', 'owner' => '999999999']];
		$this->assertSame($total, $this->rows($query));
		$this->assertSame([], array_intersect_key($query['col_filter'], array_flip(\addressbook_ui::SEARCH_COL_FILTERS)));
	}
}
