<?php
/**
 * EGroupware Admin: refreshing single rows of the command history does not replace its stored state
 *
 * @link https://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Admin;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * The command history stores the query of every request in the session, and restores it when the list is
 * opened again. A refresh of single rows (after a push, or a save in a popup) is a query for just
 * those rows: the filters of the list plus col_filter[id], with csv_export='refresh'. Stored,
 * the list showed only the refreshed rows the next time it was opened.
 */
class RefreshKeepsListStateTest extends LoggedInTest
{
	const STORED = ['filter' => 'stored state of the list', 'col_filter' => []];

	protected $before;

	protected function setUp() : void
	{
		parent::setUp();
		$this->before = Api\Cache::getSession('admin', 'cmds');
		Api\Cache::setSession('admin', 'cmds', self::STORED);
	}

	protected function tearDown() : void
	{
		Api\Cache::setSession('admin', 'cmds', $this->before);
		parent::tearDown();
	}

	protected function getRows(array $query) : void
	{
		$query += ['start' => 0, 'num_rows' => 1, 'order' => 'cmd_created', 'sort' => 'ASC', 'filter' => '', 'search' => '',
			'col_filter' => []];
		$rows = $readonlys = [];
		\admin_cmds::get_rows($query, $rows, $readonlys);
	}

	public function testRefreshOfSingleRowsIsNotStored()
	{
		$this->getRows(['csv_export' => 'refresh', 'col_filter' => ['id' => [0]]]);

		$this->assertSame(self::STORED, Api\Cache::getSession('admin', 'cmds'));
	}

	public function testQueryOfTheListIsStored()
	{
		$this->getRows(['col_filter' => ['id' => '']]);

		$this->assertNotSame(self::STORED, Api\Cache::getSession('admin', 'cmds'));
		$this->assertSame('cmd_created', Api\Cache::getSession('admin', 'cmds')['order'] ?? null);
	}
}
