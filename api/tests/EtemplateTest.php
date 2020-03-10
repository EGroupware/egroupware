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

require_once realpath(__DIR__.'/Etemplate/WidgetBaseTest.php');

/**
 * Test the main class of Etemplate
 *
 * Etemplate Widget base class scans the apps for widgets, which needs the app
 * list, pulled from the database, so we need to log in.
 */
class EtemplateTest extends Etemplate\WidgetBaseTest {

	/**
	 * Etemplate checks in the app/template/ directory, so we can't easily
	 * use a specific test template.  Using this real template for testing.
	 */
	const TEST_TEMPLATE = 'api.prompt';

	protected $content = array('value' => 'test content');
	protected $sel_options = array(array('value' => 0, 'label' => 'label'));
	protected $readonlys = array('value' => true);

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
		$result = $this->mockedExec($etemplate, array());

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

		$result = $this->mockedExec($etemplate, array());

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
		// Templates must be in the correct templates directory - use one from API
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE);

		// Change the target DOM ID
		$etemplate->set_dom_id('test_id');

		$result = $this->mockedExec($etemplate, $this->content, $this->sel_options, $this->readonlys);

		// Check for the load
		$data = array();
		foreach ($result as $command)
		{
			if ($command['type'] == 'et2_load')
			{
				$data = $command['data'];
				break;
			}
		}

		foreach ($this->content as $check_key => $check_value)
		{
			$this->assertArrayHasKey($check_key, $data['data']['content'], 'Content does not match');
			$this->assertEquals($check_value, $data['data']['content'][$check_key], 'Content does not match');
		}
		foreach ($this->sel_options as $check_key => $check_value)
		{
			$this->assertArrayHasKey($check_key, $data['data']['sel_options'], 'Select options does not match');
			$this->assertEquals($check_value, $data['data']['sel_options'][$check_key], 'Select options does not match');
		}
		foreach ($this->readonlys as $check_key => $check_value)
		{
			$this->assertArrayHasKey($check_key, $data['data']['readonlys'], 'Readonlys does not match');
			$this->assertEquals($check_value, $data['data']['readonlys'][$check_key], 'Readonlys does not match');
		}
	}

	/**
	 * Test that data passed in is passed back
	 *
	 * In this case, since there's one input widget and we're passing it's value, and
	 * we're not passing anything extra and no preserve, it should be the same.
	 *
	 * @depends testExec
	 */
	public function testRoundTrip()
	{
		// Templates must be in the correct templates directory - use one from API
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE);

		$this->readonlys['value'] = false;

		$result = $this->mockedRoundTrip($etemplate, $this->content, $this->sel_options, $this->readonlys);

		$this->assertEquals($this->content, $result);
	}

	/**
	 * Simple test of a read-only widget
	 *
	 * The value is passed in, but does not come back
	 *
	 * @depends testExec
	 */
	public function testSimpleReadonly()
	{
		// Templates must be in the correct templates directory - use one from API
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE);

		$this->readonlys['value'] = true;

		$result = $this->mockedRoundTrip($etemplate, $this->content, $this->sel_options, $this->readonlys);

		// The only input widget is readonly, expect an empty array
		$this->assertEquals(array(), $result);
	}

	/**
	 * Simple test of preserve
	 *
	 * The value is passed in, and comes back, even if the widget is readonly,
	 * or if there is no matching widget.
	 *
	 * @depends testExec
	 */
	public function testArbitraryPreserve()
	{
		// Templates must be in the correct templates directory - use one from API
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE);

		$this->readonlys['value'] = true;

		$preserve = array('arbitrary' => 'value');
		$result = $this->mockedRoundTrip($etemplate, $this->content, $this->sel_options, $this->readonlys, $preserve);

		// The only input widget is readonly, expect preserve back
		$this->assertEquals($preserve, $result);

		// Now try with widget
		$this->readonlys['value'] = false;

		$result2 = $this->mockedRoundTrip($etemplate, $this->content, $this->sel_options, $this->readonlys, $preserve);

		// The only input widget is readonly, expect preserve + content back
		foreach ($this->content as $check_key => $check_value)
		{
			$this->assertArrayHasKey($check_key, $result2, 'Content does not match');
			$this->assertEquals($check_value, $result2[$check_key], 'Content does not match');
		}
		foreach ($preserve as $check_key => $check_value)
		{
			$this->assertArrayHasKey($check_key, $result2, 'Preserve does not match');
			$this->assertEquals($check_value, $result2[$check_key], 'Preserve does not match');
		}
	}

	/**
	 * Test of editable widget value overriding preserved value but a readonly
	 * widget does not override preserved value.
	 */
	public function testReadonlyPreserve()
	{
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE);

		$this->readonlys['value'] = true;
		$preserve['value'] = 'preserved_value';

		$result = $this->mockedRoundTrip($etemplate, $this->content, $this->sel_options, $this->readonlys, $preserve);

		// The only input widget is readonly, expect preserve back, not content
		$this->assertEquals($preserve['value'], $result['value']);

		$this->readonlys['value'] = false;
		$result2 = $this->mockedRoundTrip($etemplate, $this->content, $this->sel_options, $this->readonlys, $preserve);

		// The only input widget is editable, expect content back, not preserve
		$this->assertEquals($this->content['value'], $result2['value']);
	}
}
