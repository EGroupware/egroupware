<?php
/**
 * EGroupware Api: Nextmatch::category_action() chooses between a sub-menu and the picker dialog
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Categories;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../LoggedInTest.php');

/**
 * WHAT THIS PROVES
 * A single-value category action (multiple=false) is a flat sub-menu with its own onExecute
 * while the list, counting "None", fits DEFAULT_MAX_MENU_SELECT, and the nm_action=categories
 * picker dialog beyond that. Multi-value (multiple=true) always gets the dialog; multiple=null
 * is the plain sub-menu.
 *
 * SETUP
 * Categories are created under a private application name with globals=false, so no real
 * category is counted, and are deleted again in tearDown.
 *
 * PASS CRITERIA
 * Presence of 'children' / 'onExecute' / data.nm_action on the returned action, and the action
 * ids (keys) of the children.
 */
class NextmatchCategoryActionTest extends LoggedInTest
{
	const APP = 'nmcatactiontest';

	/** @var int[] */
	private $cat_ids = [];

	protected function tearDown() : void
	{
		$cats = new Categories(null, self::APP);
		foreach($this->cat_ids as $id)
		{
			$cats->delete($id);
		}
		$this->cat_ids = [];
		parent::tearDown();
	}

	private function makeCategories(int $n) : void
	{
		$cats = new Categories(null, self::APP);
		for($i = 1; $i <= $n; $i++)
		{
			$this->cat_ids[] = $cats->add(['name' => 'Test cat '.$i, 'parent' => 0, 'descr' => '', 'data' => '']);
		}
	}

	private static function action(?bool $multiple) : array
	{
		return Nextmatch::category_action(self::APP, 1, 'Change category', 'cat_', false, 0,
			Nextmatch::DEFAULT_MAX_MENU_LENGTH, $multiple);
	}

	public function testSingleValueFewCategoriesIsSubMenu()
	{
		$this->makeCategories(4);
		$action = self::action(false);

		$this->assertArrayHasKey('children', $action);
		$this->assertSame('javaScript:app.'.self::APP.'.ajax_action', $action['onExecute'] ?? null);
		$this->assertArrayNotHasKey('nm_action', $action['data'] ?? []);
		$this->assertSame('cat_', $action['prefix']);
		$this->assertCount(5, $action['children'], '4 categories + None');
		$this->assertSame(array_merge([''], $this->cat_ids), array_keys($action['children']));
	}

	public function testSingleValueAtThresholdIsSubMenu()
	{
		$this->makeCategories(Nextmatch::DEFAULT_MAX_MENU_SELECT - 1);
		$this->assertArrayHasKey('children', self::action(false));
	}

	public function testSingleValueManyCategoriesIsDialog()
	{
		$this->makeCategories(Nextmatch::DEFAULT_MAX_MENU_SELECT);
		$action = self::action(false);

		$this->assertSame('categories', $action['data']['nm_action'] ?? null);
		$this->assertFalse($action['data']['categories']['multiple']);
		$this->assertArrayNotHasKey('children', $action);
		$this->assertArrayNotHasKey('onExecute', $action);
	}

	public function testMultiValueIsAlwaysDialog()
	{
		$this->makeCategories(4);
		$action = self::action(true);

		$this->assertSame('categories', $action['data']['nm_action'] ?? null);
		$this->assertTrue($action['data']['categories']['multiple']);
		$this->assertArrayNotHasKey('children', $action);
	}

	public function testNullIsPlainSubMenu()
	{
		$this->makeCategories(4);
		$action = self::action(null);

		$this->assertArrayHasKey('children', $action);
		$this->assertArrayNotHasKey('onExecute', $action);
		$this->assertArrayNotHasKey('nm_action', $action['data'] ?? []);
	}
}
