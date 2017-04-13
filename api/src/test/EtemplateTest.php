<?php

/**
 * Test Etemplate main file
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once realpath(__DIR__.'/../Etemplate/test/WidgetBaseTest.php');

use EGroupware\Api;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * Test the main class of Etemplate
 *
 * Etemplate Widget base classscans the apps for widgets, which needs the app
 * list, pulled from the database, so we need to log in.
 */
class EtemplateTest extends Etemplate\WidgetBaseTest {

	/**
	 * Etemplate checks in the app/template/ directory, so we can't easily
	 * use a specific test template.  Using this real template for testing.
	 */
	const TEST_TEMPLATE = 'api.prompt';

	/**
	 * Test reading xml files
	 *
	 * This really just tests that the files can be found and executed.  
	 */
	public function testRead()
	{
		$etemplate = new Etemplate();

		// Test missing template fails
		$this->assertEquals(false, $etemplate->read('totally invalid'), 'Reading invalid template');

		// Templates must be in the correct templates directory - use one from API
		// This does not actually do anything with the template file
		$this->assertEquals(true, $etemplate->read(static::TEST_TEMPLATE));

		// This loads and parses
		$result = $this->mockedExec($etemplate, '',array());

		// Look for the load and match the template name
		foreach($result as $command)
		{
			if($command['type'] == 'et2_load')
			{
				$this->assertEquals(static::TEST_TEMPLATE, $command['data']['name']);
				break;
			}
		}
	}

	/**
	 * Test that we can load the etemplate into a different DOM ID than the
	 * default, which is based on the template name.
	 */
	public function testSetDOMId()
	{
		// Templates must be in the correct templates directory - use one from API
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE);

		// Change the target DOM ID
		$etemplate->set_dom_id('test_id');

		$result = $this->mockedExec($etemplate, '',array());

		// Check for the load
		foreach($result as $command)
		{
			if($command['type'] == 'et2_load')
			{
				$this->assertEquals('test_id', $command['data']['DOMNodeID']);
				break;
			}
		}
	}

	/**
	 * Test that data that is passed in is passed on
	 */
	public function testExec()
	{
		$content = array('id' => 'value');
		$sel_options = array(array('value' => 0, 'label' => 'label'));
		$readonlys = array('id' => true);

		// Templates must be in the correct templates directory - use one from API
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE);

		// Change the target DOM ID
		$etemplate->set_dom_id('test_id');

		$result = $this->mockedExec($etemplate, '',$content, $sel_options, $readonlys);

		// Check for the load
		$data = array();
		foreach($result as $command)
		{
			if($command['type'] == 'et2_load')
			{
				$data = $command['data'];
				break;
			}
		}

		$this->assertArraySubset($content, $data['data']['content'], false, 'Content does not match');
		$this->assertArraySubset($sel_options, $data['data']['sel_options'], false, 'Select options do not match');
		$this->assertArraySubset($readonlys, $data['data']['readonlys'], false, 'Readonlys does not match');
	}
}
