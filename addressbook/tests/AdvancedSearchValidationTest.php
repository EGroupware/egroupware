<?php
/**
 * EGroupware Addressbook: the advanced search the list sends back is reduced to what the search dialog creates
 *
 * @link https://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Addressbook;

use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * The list of contacts sends its advanced search back to addressbook_ui::get_rows() with every
 * request, as it was set by the client. Each name in it is used as a column of the SQL query,
 * and in the views of organisations or duplicates its operator joins the conditions - both
 * reached the query as they were, so a client could add any condition to it, including one
 * returning contacts it may not read.
 *
 * The tests only read: a name or operator that is no valid SQL makes the query fail, if it gets
 * that far, so they do not need contacts the user can not read.
 */
class AdvancedSearchValidationTest extends LoggedInTest
{
	/** @var \addressbook_ui */
	protected $ui;

	protected function setUp() : void
	{
		parent::setUp();
		$this->ui = new \addressbook_ui();
	}

	/**
	 * Run get_rows() like "select all" does: only the ids, nothing stored in the session or preferences
	 */
	protected function total(array $query) : int
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
		];
		$rows = $readonlys = [];
		return (int)$this->ui->get_rows($query, $rows, $readonlys, true);
	}

	public function testNameWhichIsNoFieldDoesNotReachTheQuery()
	{
		$all = $this->total([]);

		// the query fails if the name reaches it, and with a valid condition instead "1=0" would match nothing
		$this->assertSame($all, $this->total(['advanced_search' => [
			'n_family) no valid SQL (' => 'x',
			'operator' => 'AND',
			'meth_select' => '%',
		]]));
		$this->assertSame($all, $this->total(['advanced_search' => [
			'1=0 AND n_family' => 'x',
			'operator' => 'AND',
			'meth_select' => '%',
		]]));
	}

	public function testOperatorOtherThanAndOrDoesNotReachTheQuery()
	{
		$query = ['grouped_view' => 'org_name', 'advanced_search' => [
			'n_family' => 'x',
			'n_given' => 'x',
			'operator' => 'no valid SQL (',
			'meth_select' => '%',
		]];
		$rows = $readonlys = [];
		$query += ['start' => 0, 'num_rows' => 10, 'order' => 'org_name', 'sort' => 'ASC', 'filter' => '',
			'filter2' => '', 'cat_id' => '', 'search' => '', 'col_filter' => []];
		// the organisations are queried even with $id_only, the query fails if the operator reaches it
		$this->ui->get_rows($query, $rows, $readonlys, true);
		$this->assertIsArray($rows);
	}

	public function testAdvancedSearchOfTheDialogIsKept()
	{
		$method = new \ReflectionMethod($this->ui, 'validAdvancedSearch');

		$search = ['n_family' => 'Becker', 'org_name' => 'Example', 'operator' => 'OR', 'meth_select' => '%'];
		$this->assertSame($search, $method->invoke($this->ui, $search));

		// "exact" is sent as an empty method, and an empty field is no criteria
		$this->assertSame(['n_family' => 'Becker', 'operator' => 'AND'],
			$method->invoke($this->ui, ['n_family' => 'Becker', 'n_given' => '', 'operator' => 'AND', 'meth_select' => '']));
	}

	public function testEverythingElseIsDropped()
	{
		$method = new \ReflectionMethod($this->ui, 'validAdvancedSearch');

		$this->assertSame(['n_family' => 'Becker', 'cat_id' => ['1', '2']], $method->invoke($this->ui, [
			'n_family' => 'Becker',
			'1=1 OR n_family' => 'x',
			'egw_addressbook.contact_id' => '1',
			'n_fn' => 'x',
			7 => 'x',
			'cat_id' => ['1', ['3'], '2'],
			'n_given' => ['nested' => ['x']],
			'operator' => ') OR 1=1 OR (',
			'meth_select' => '1',
		]));
		$this->assertSame([], $method->invoke($this->ui, 'n_family'));
	}
}
