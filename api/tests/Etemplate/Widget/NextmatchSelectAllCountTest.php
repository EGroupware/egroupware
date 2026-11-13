<?php
/**
 * EGroupware Api: a "select all" action must run on the number of entries the user's list showed
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../LoggedInTest.php');

/**
 * With "select all" the client sends no ids. It says everything matching the list's filters is
 * meant, and the server works out which entries those are by re-running the list's query. When
 * that is not the query the list is showing, the action runs on entries the user never selected,
 * or misses some. The client therefore sends the number of entries its list shows as the "all
 * selected" flag, and Nextmatch::checkSelectAllCount() compares it with the number the expansion
 * found, before anything is changed.
 *
 * WHAT THIS PROVES
 * - which actions are marked Nextmatch::SERVER_SELECT_ALL ("the server expands select-all itself,
 *   the client need not fetch all ids"), and that it reaches exactly the sub-menu children that
 *   run through the marked handler - see the tests at the end
 * - the comparison itself: equal and off-by-tolerance pass, anything further apart throws a
 *   WrongUserinput naming both numbers
 * - a flag that carries no count (true, false, null, 0, a negative or non-integer value) is
 *   never checked, so an older client or a server-side caller keeps working
 *
 * SETUP
 * Pure calls of the static helpers on a made-up action list, nothing is read or written. LoggedInTest only because the
 * message goes through lang().
 *
 * PASS CRITERIA
 * Exactly the cases listed as mismatches throw, and no others.
 */
class NextmatchSelectAllCountTest extends LoggedInTest
{
	/**
	 * @return array[] [flag sent by the client, number of entries found by the server]
	 */
	public static function matching() : array
	{
		return [
			'same number'                      => [25, 25],
			'one more, created meanwhile'      => [25, 26],
			'one less, deleted meanwhile'      => [25, 24],
			'one entry'                        => [1, 1],
			'plain true carries no count'      => [true, 5000],
			'false is no select-all'           => [false, 5000],
			'null'                             => [null, 5000],
			'0 is not a count'                 => [0, 5000],
			'a negative total is not a count'  => [-1, 5000],
			'a string is not a count'          => ['25', 5000],
			'a float is not a count'           => [25.0, 5000],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('matching')]
	public function testMatchingOrUncountedSelectionPasses($all_selected, int $actual)
	{
		Nextmatch::checkSelectAllCount($all_selected, $actual);
		$this->addToAssertionCount(1);	// not throwing is the assertion
	}

	/**
	 * @return array[] [flag sent by the client, number of entries found by the server]
	 */
	public static function mismatching() : array
	{
		return [
			'filter lost, everything selected' => [3, 5000],
			'two more'                         => [25, 27],
			'two less'                         => [25, 23],
			'list showed many, server finds 1' => [400, 1],
			'server finds nothing'             => [2, 0],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('mismatching')]
	public function testMismatchThrowsNamingBothNumbers(int $all_selected, int $actual)
	{
		try
		{
			Nextmatch::checkSelectAllCount($all_selected, $actual);
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$this->assertStringContainsString((string)$all_selected, $e->getMessage());
			$this->assertStringContainsString((string)$actual, $e->getMessage());
			return;
		}
		$this->fail("expected $all_selected entries, acting on $actual must be refused");
	}

	/**
	 * The tolerance is a parameter, for a caller that knows its list can not have changed
	 */
	public function testToleranceCanBeTightened()
	{
		$this->expectException(Api\Exception\WrongUserinput::class);
		Nextmatch::checkSelectAllCount(25, 26, 0);
	}

	public function testSelectAllExpected()
	{
		$this->assertSame(25, Nextmatch::selectAllExpected(25));
		$this->assertNull(Nextmatch::selectAllExpected(true));
		$this->assertNull(Nextmatch::selectAllExpected(false));
		$this->assertNull(Nextmatch::selectAllExpected(0));
		$this->assertNull(Nextmatch::selectAllExpected('25'));
	}

	/**
	 * An app's get_actions() list, reduced to the shapes that matter for SERVER_SELECT_ALL
	 */
	protected function actions() : array
	{
		return [
			'open' => ['caption' => 'Open', 'egw_open' => 'edit-example', 'group' => 1],
			'close' => ['caption' => 'Close', 'onExecute' => 'javaScript:app.example.ajax_action', 'group' => 2],
			'no_notifications' => ['caption' => 'Do not notify', 'checkbox' => true,
				'onExecute' => 'javaScript:app.example.ajax_action', 'group' => 2],
			'change' => ['caption' => 'Change', 'group' => 2, 'children' => [
				'status' => ['caption' => 'Status', 'prefix' => 'status_',
					'onExecute' => 'javaScript:app.example.ajax_action', 'children' => [
						'done' => 'Done',
						'open' => ['caption' => 'Open'],
						'mail' => ['caption' => 'Mail', 'onExecute' => 'javaScript:app.example.mail'],
						'view' => ['caption' => 'View', 'url' => 'menuaction=example.example_ui.view&id=$id'],
						'copy' => ['caption' => 'Copy instead', 'checkbox' => true],
					]],
				'cat' => ['caption' => 'Category', 'data' => ['nm_action' => 'categories', 'categories' => []]],
			]],
			'merge' => ['caption' => 'Merge', 'onExecute' => 'javaScript:app.example.ajax_action',
				'nm_action' => 'long_task', 'group' => 3],
			'export' => ['caption' => 'Export', 'onExecute' => 'javaScript:app.example.export', 'group' => 3],
		];
	}

	/**
	 * flagServerSelectAll() marks, in an action's data, exactly the actions run by the given
	 * handler - plus the category picker dialog, which posts to the same
	 * endpoint - so the client can skip fetching all ids for them. Anything else keeps the
	 * fetch: an action handled on the client needs the ids, a checkbox runs nothing, and a long
	 * task sends one request per row.
	 */
	public function testFlagServerSelectAllMarksOnlyActionsOfTheGivenHandler()
	{
		$flagged = Nextmatch::flagServerSelectAll($this->actions(), ['javaScript:app.example.ajax_action']);
		$key = Nextmatch::SERVER_SELECT_ALL;

		$this->assertTrue($flagged['close']['data'][$key], 'an action run by the handler is marked');
		$this->assertTrue($flagged['change']['children']['status']['data'][$key], 'at any depth');
		$this->assertTrue($flagged['change']['children']['cat']['data'][$key], 'the category dialog posts to the same endpoint');
		$this->assertSame([], $flagged['change']['children']['cat']['data']['categories'], 'existing data is kept');

		$this->assertArrayNotHasKey('data', $flagged['open'], 'an action of another kind is not');
		$this->assertArrayNotHasKey('data', $flagged['export'], 'nor one with a different handler');
		$this->assertArrayNotHasKey('data', $flagged['no_notifications'], 'nor a checkbox');
		$this->assertArrayNotHasKey('data', $flagged['merge'], 'a long task needs every id on the client');
		$this->assertArrayNotHasKey('data', $flagged['change'], 'a container without the handler is not marked itself');
	}

	/**
	 * A sub-menu's children get their onExecute from the container, so egw_actions() has to hand
	 * SERVER_SELECT_ALL down with it - but only to a child that really runs through the
	 * container's handler. A child declaring its own onExecute or url, and a checkbox, must not
	 * get it, or its "select all" would silently act on the loaded rows only.
	 */
	public function testEgwActionsHandsTheFlagDownOnlyToChildrenUsingTheContainersHandler()
	{
		$links = [];
		$actions = Nextmatch::egw_actions(
			Nextmatch::flagServerSelectAll($this->actions(), ['javaScript:app.example.ajax_action']),
			'example.index', '', $links);
		$key = Nextmatch::SERVER_SELECT_ALL;
		$status = $actions['change']['children']['status']['children'];

		$this->assertTrue($status['status_done']['data'][$key], 'a child given as plain caption inherits it');
		$this->assertTrue($status['status_open']['data'][$key], 'a child without behaviour of its own inherits it');
		$this->assertSame('javaScript:app.example.ajax_action', $status['status_open']['onExecute']);

		$this->assertEmpty($status['status_mail']['data'][$key] ?? null, 'a child with its own onExecute does not');
		$this->assertEmpty($status['status_view']['data'][$key] ?? null, 'a child with a url does not');
		$this->assertEmpty($status['status_copy']['data'][$key] ?? null, 'a checkbox does not');
		foreach($status as $id => $child)
		{
			$this->assertArrayNotHasKey($key, $child, "$id: the flag lives in data, never as an attribute");
		}
	}

	/**
	 * A long task sends one request per row, so it needs every id on the client whatever its
	 * data says: egw_actions() removes a flag set by hand on one.
	 */
	public function testEgwActionsNeverLeavesTheFlagOnALongTask()
	{
		$links = [];
		$key = Nextmatch::SERVER_SELECT_ALL;
		$actions = Nextmatch::egw_actions([
			'declared' => ['caption' => 'A', 'nm_action' => 'long_task', 'data' => [$key => true]],
			'in_data' => ['caption' => 'B', 'data' => ['nm_action' => 'long_task', $key => true]],
			'tasks' => ['caption' => 'C', 'onExecute' => 'javaScript:app.example.ajax_action',
				'data' => [$key => true], 'children' => [
					'task' => ['caption' => 'D', 'data' => ['nm_action' => 'long_task', 'menuaction' => 'x.y.z']],
					'plain' => ['caption' => 'E'],
				]],
		], 'example.index', '', $links);

		$this->assertEmpty($actions['declared']['data'][$key] ?? null);
		$this->assertEmpty($actions['in_data']['data'][$key] ?? null);
		$this->assertEmpty($actions['tasks']['children']['task']['data'][$key] ?? null,
			'a long task below a flagged container does not inherit it');
		$this->assertTrue($actions['tasks']['children']['plain']['data'][$key], 'its sibling does');
	}
}
