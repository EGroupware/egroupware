<?php

/**
 * Test for integer textboxes
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

use EGroupware\Api\Etemplate;

class IntegerTest extends \EGroupware\Api\Etemplate\WidgetBaseTest {

	const TEST_TEMPLATE = 'api.integer_test';

	/**
	 * Test for validation - integers
	 *
	 *
	 * @dataProvider integerProvider
	 */
	public function testInteger($value, $error)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Content - doesn't really matter, we're changing it
		$content = array(
			'widget'            =>	'Hello',
			'widget_readonly'   =>	'World'
		);

		$this->validateRoundTrip($etemplate, $content, array('widget' => $value),
				$error ? array() : array('widget' => $value),
				$error ? array('widget' => $error) : array()
		);
	}

	/**
	 * Data provider for integer tests
	 */
	public function integerProvider()
	{
		return array(
			// User value,    Error
			array('',         false),
			array(1,          false),
			array(0,          false),
			array(-1,         false),
			array(1.5,        true),
			array('one',      true)
		);
	}

	/**
	 * Test for integer minimum attribute
	 *
	 * @param String|numeric $value
	 * @param int $min Minimum allowed value
	 * @param boolean $error
	 *
	 * @dataProvider minProvider
	 */
	public function testMin($value, $min, $error)
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

		// Set limits
		$etemplate->getElementById('widget')->attrs['min'] = $min;
		$etemplate->getElementById('widget')->attrs['max'] = null;

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

		return $this->validateTest($content,
				$error ? array() : array('widget' => $value),
				$error ? array('widget' => $error) : array()
		);
	}

	public function minProvider()
	{
		return Array(
			// User value, Min,      Error
			array('',    0,          FALSE),
			array(1,     0,          FALSE),
			array(0,     0,          FALSE),
			array(-1,    0,          TRUE),
			array(1.5,   0,          TRUE), // Errors because it's not an int
			array(1,    10,          TRUE),
			array(10,   10,          FALSE),
		);
	}

	/**
	 * Test for integer maximum attribute
	 *
	 * @param String|numeric $value
	 * @param int $max Maximum allowed value
	 * @param boolean $error
	 *
	 * @dataProvider maxProvider
	 */
	public function testMax($value, $max, $error)
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

		// Set limits
		$etemplate->getElementById('widget')->attrs['min'] = null;
		$etemplate->getElementById('widget')->attrs['max'] = $max;

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

		return $this->validateTest($content,
				$error ? array() : array('widget' => $value),
				$error ? array('widget' => $error) : array()
		);
	}

	public function maxProvider()
	{
		return Array(
			// User value, Max,      Error
			array('',        0,      FALSE),
			array(1,         0,      TRUE),
			array(0,         0,      FALSE),
			array(-1,        0,      FALSE),
			array(1.5,       2,      TRUE), // Errors because it's not an int
			array(1,        10,      FALSE),
			array(10,       10,      FALSE),
		);
	}
}
