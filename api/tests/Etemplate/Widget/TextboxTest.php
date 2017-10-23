<?php

/**
 * Test for textbox
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @subpackage etemplate
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

use EGroupware\Api\Etemplate;

class TextboxTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{

	const TEST_TEMPLATE = 'api.textbox_test';

	/**
	 * Test the widget's basic functionallity - we put data in, it comes back
	 * unchanged.
	 */
	public function testBasic()
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Exec
		$content = array(
			'widget'            =>	'',
			'widget_readonly'   =>	''
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array());

		// Check
		$this->assertEquals(array('widget' => ''), $result);

		$content = array(
			'widget'            =>	'Hello',
			'widget_readonly'   =>	'World'
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array());

		// Check only the editable widget gives a value
		$this->assertEquals(array('widget' => 'Hello'), $result);
	}

	/**
	 * Test that the widget does not return a value if readonly
	 */
	public function testReadonly()
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Exec
		$content = array(
			'widget'            =>	'Hello',
			'widget_readonly'   =>	'World'
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array('widget' => true));

		// Check
		$this->assertEquals(array(), $result);
	}

	/**
	 * Test that an edited read-only widget does not return a value, even if the
	 * client side gives one, which should be an unusual occurrence.
	 */
	public function testEditedReadonly()
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Exec
		$content = array(
			'widget'            =>	'Hello',
			'widget_readonly'   =>	'World'
		);
		$result = $this->mockedExec($etemplate, $content, array(), array('widget' => true), array());

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

		// 'Edit' the data client side
		$data['data']['content'] = array(
			'widget' => 'Goodnight',
			'widget_readonly' => 'Moon'
		);

		Etemplate::ajax_process_content($data['data']['etemplate_exec_id'], $data['data']['content'], false);

		$content = static::$mocked_exec_result;
		static::$mocked_exec_result = array();

		$this->assertEquals(array(), $content);
	}

	/**
	 * Test regex validation
	 *
	 * @dataProvider regexProvider
	 */
	public function testRegex($value, $error)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Content - doesn't really matter, we're changing it
		$content = array(
			'widget'            =>	'Hello',
			'widget_readonly'   =>	'World'
		);
		$result = $this->mockedExec($etemplate, $content, array(), array(), array());

		// Only lowercase
		$etemplate->getElementById('widget')->attrs['validator'] = '/^[a-z]*$/';

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

		// 'Edit' the data client side
		$data['data']['content'] = array('widget' => $value);

		// Let it validate
		Etemplate::ajax_process_content($data['data']['etemplate_exec_id'], $data['data']['content'], false);

		$content = static::$mocked_exec_result;
		static::$mocked_exec_result = array();

		return $this->validateTest($content, $error ? array() : array('widget' => $value), $error ? array('widget' => $error) : array());
	}

	public function regexProvider()
	{
		return array(
			// Value       Errors
			array('',      FALSE),
			array('Hello', TRUE),
			array('hello', FALSE),
			array(1234,    TRUE),
			array('hi1234',TRUE)
		);
	}
}
