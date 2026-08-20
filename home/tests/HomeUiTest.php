<?php
/**
 * EGroupware home: regression test for get_portlet()'s class-ancestry guard
 *
 * @link http://www.egroupware.org
 * @package home
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Before the fix, home_ui::get_portlet() did `new $classname($context, $full_exec)` with
 * $classname taken from client-controlled preference data (via ajax_set_properties()),
 * with no check that $classname is actually a home_portlet subclass - unlike the sibling
 * get_portlet_list(), which validates via class_parents(). This let a caller force
 * instantiation of an arbitrary autoloadable class.
 */
class HomeUiTest extends \EGroupware\Api\AppTest
{
	private function callGetPortlet($classname)
	{
		$ui = new home_ui();
		$reflection = new ReflectionMethod($ui, 'get_portlet');
		$reflection->setAccessible(true);

		$context = array('class' => $classname);
		$content = null;
		$attributes = array();

		return $reflection->invoke($ui, 'test_portlet_id', $context, $content, $attributes, false);
	}

	/**
	 * Pass criteria: an arbitrary, unrelated class name must be rejected with
	 * Api\Exception\WrongParameter rather than instantiated.
	 */
	public function testRejectsNonPortletClass()
	{
		$this->expectException(\EGroupware\Api\Exception\WrongParameter::class);

		// stdClass is autoloadable/built-in but not a home_portlet subclass
		$this->callGetPortlet('stdClass');
	}

	/**
	 * Pass criteria: a real home_portlet subclass must still be accepted (no regression).
	 */
	public function testAllowsRealPortletClass()
	{
		$portlet = $this->callGetPortlet('home_link_portlet');

		$this->assertInstanceOf('home_portlet', $portlet);
	}
}
