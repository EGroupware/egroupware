<?php
/**
 * EGroupware Api: over-long sub-menus are offered as a selection dialog
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate\Widget\Nextmatch;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../LoggedInTest.php');

/**
 * A sub-menu with one entry per row of user data (a category, a distribution list, an
 * addressbook, a tracker queue, a kanban board) is fine with a handful and unusable with
 * hundreds, and nothing notices when an installation crosses over.
 *
 * WHAT THIS PROVES
 * Nextmatch::egw_actions() marks such a container `data['nm_action'] = 'select_children'` once it
 * has more than DEFAULT_MAX_MENU_SELECT drawable children, which is what makes
 * EgwAction.appendToTree() render it as a plain leaf and the nextmatch controller open a picker
 * over its children instead.
 *
 * PASS CRITERIA
 * Each case asserts only on the presence/absence of that one flag on the container, never on the
 * children, which are untouched by design - picking one still executes the real child action.
 *
 * ENVIRONMENT SENSITIVITY
 * Every case builds its own synthetic action array rather than reading an app's, so the result
 * does not depend on how much data this instance happens to have. It still needs a session,
 * because egw_actions() itself calls lang() and UserAgent::mobile().
 */
class NextmatchSelectChildrenTest extends LoggedInTest
{
	/**
	 * A container with $n plain children, plus any extra attributes
	 */
	private static function container(int $n, array $extra = []) : array
	{
		$children = [];
		for($i = 1; $i <= $n; $i++)
		{
			$children['child_'.$i] = ['caption' => 'Child '.$i];
		}
		return ['menu' => ['caption' => 'Menu', 'children' => $children] + $extra];
	}

	private static function resolve(array $actions) : array
	{
		$action_links = [];
		return Nextmatch::egw_actions($actions, 'test', '', $action_links);
	}

	private static function isSelect(array $resolved) : bool
	{
		return ($resolved['menu']['data']['nm_action'] ?? null) === 'select_children';
	}

	public function testShortMenuStaysAMenu()
	{
		$resolved = self::resolve(self::container(Nextmatch::DEFAULT_MAX_MENU_SELECT));
		$this->assertFalse(self::isSelect($resolved),
			'A sub-menu at exactly the threshold must stay a sub-menu');
		$this->assertCount(Nextmatch::DEFAULT_MAX_MENU_SELECT, $resolved['menu']['children']);
	}

	public function testLongMenuBecomesASelectDialog()
	{
		$resolved = self::resolve(self::container(Nextmatch::DEFAULT_MAX_MENU_SELECT + 1));
		$this->assertTrue(self::isSelect($resolved),
			'One child past the threshold must switch to the picker');
	}

	/**
	 * The children are hidden from the menu, not removed - picking one in the dialog executes
	 * that very action, so it keeps its own onExecute/confirm/enabled.
	 */
	public function testChildrenSurviveUntouched()
	{
		$resolved = self::resolve(self::container(Nextmatch::DEFAULT_MAX_MENU_SELECT + 5));
		$this->assertCount(Nextmatch::DEFAULT_MAX_MENU_SELECT + 5, $resolved['menu']['children']);
		$this->assertSame('Child 1', $resolved['menu']['children']['child_1']['caption']);
	}

	/**
	 * A checkbox child is a modifier ("Copy instead of move", "Share writable"), not an option -
	 * it travels into the dialog alongside the picker, so it must not push a menu over the edge.
	 */
	public function testCheckboxChildrenDoNotCountTowardsTheLength()
	{
		$actions = self::container(Nextmatch::DEFAULT_MAX_MENU_SELECT);
		$actions['menu']['children']['a_modifier'] = ['caption' => 'Copy instead of move', 'checkbox' => true];
		$this->assertFalse(self::isSelect(self::resolve($actions)),
			'A checkbox child is a modifier, not an option, and must not trigger the dialog');
	}

	public function testAppCanRaiseTheThreshold()
	{
		$actions = self::container(Nextmatch::DEFAULT_MAX_MENU_SELECT + 5, ['data' => ['maxMenuLength' => 40]]);
		$this->assertFalse(self::isSelect(self::resolve($actions)));
	}

	public function testAppCanForceTheDialog()
	{
		$actions = self::container(2, ['data' => ['maxMenuLength' => 0]]);
		$this->assertTrue(self::isSelect(self::resolve($actions)),
			'maxMenuLength 0 means always use the dialog, however few children');
	}

	public function testAppCanOptOutEntirely()
	{
		$actions = self::container(100, ['data' => ['maxMenuLength' => false]]);
		$this->assertFalse(self::isSelect(self::resolve($actions)),
			'maxMenuLength false must keep the sub-menu at any size');
	}

	/**
	 * The override has to live in data[], because for a container WITH children egw_actions()
	 * inherits onExecute down to the children and strips it off the parent - so an app setting
	 * onExecute to "take over the dialog" would get the exact opposite. This pins that behaviour
	 * so the override mechanism cannot be quietly moved onto onExecute later.
	 */
	public function testOnExecuteIsInheritedDownAndStrippedOffTheParent()
	{
		$actions = self::container(3, ['onExecute' => 'javaScript:app.test.handler']);
		$resolved = self::resolve($actions);
		$this->assertArrayNotHasKey('onExecute', $resolved['menu'],
			'A container with children does NOT keep its own onExecute');
		$this->assertSame('javaScript:app.test.handler', $resolved['menu']['children']['child_1']['onExecute'],
			'...it is pushed down to every child instead');
	}

	/**
	 * A hand-written nm_action stays at the action's own top level; egw_actions() only writes
	 * into data[] for the cases it derives itself. Both count as "already declared", so an
	 * explicit choice is never overwritten.
	 */
	public function testExplicitNmActionIsNotOverwritten()
	{
		$actions = self::container(Nextmatch::DEFAULT_MAX_MENU_SELECT + 5, ['data' => ['nm_action' => 'open_popup']]);
		$resolved = self::resolve($actions);
		$this->assertSame('open_popup', $resolved['menu']['data']['nm_action'] ?? null,
			'An explicitly declared nm_action must win over the length heuristic');
	}
}
