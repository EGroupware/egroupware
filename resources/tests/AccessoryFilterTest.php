<?php
/**
 * Test the filter for resources and accessories in the Resources list
 *
 * @link http://www.egroupware.org
 * @package resources
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Resources;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

/**
 * "Accessories of" a resource puts the id of that resource into filter2, which is no option of
 * that filter. It is registered as extra attribute of the nextmatch, so no filter widget checks
 * it: resources_bo::accessoryFilter() has to.
 */
class AccessoryFilterTest extends Api\AppTest
{
	public function testOptionsAndResourceIdsAreKept()
	{
		$this->assertSame(-1, \resources_bo::accessoryFilter('-1'));
		$this->assertSame(-2, \resources_bo::accessoryFilter(-2));
		$this->assertSame(-3, \resources_bo::accessoryFilter('-3'));
		$this->assertSame(2, \resources_bo::accessoryFilter('2'), 'accessories of resource 2');
		$this->assertSame(2, \resources_bo::accessoryFilter(2));
	}

	public function testEverythingElseGivesTheFirstOption()
	{
		$first = key(\resources_bo::$filter_options);
		foreach(['', null, '2 OR 1=1', '-4', '0', ['2'], ['x' => ['y']], 'bogus'] as $value)
		{
			$this->assertSame($first, \resources_bo::accessoryFilter($value), json_encode($value));
		}
	}

	/**
	 * Deleted resources can only be asked for, if they are kept at all.
	 */
	public function testDeletedNeedsTheHistory()
	{
		$expected = !empty(Api\Config::read('resources')['history']) ? \resources_bo::DELETED : key(\resources_bo::$filter_options);
		$this->assertSame($expected, \resources_bo::accessoryFilter(\resources_bo::DELETED));
	}

	/**
	 * The filter is registered for the nextmatch of the list, or validating its filters would drop it.
	 */
	public function testFilterIsRegisteredForTheList()
	{
		$source = file_get_contents(__DIR__.'/../inc/class.resources_ui.inc.php');
		$this->assertMatchesRegularExpression("/'extra_attributes'\\]\\s*=\\s*\\[[^\\]]*'filter2'/", $source);
	}
}
