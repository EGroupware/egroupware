<?php

/**
 * Tests for Date widget
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
use EGroupware\Api\DateTime;

class DateTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{

	const TEST_TEMPLATE = 'api.date_test';

	protected static $usertime;
	protected static $server_tz;

	/**
	 * Work in server time, so tests match expectations
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		static::$usertime = DateTime::$user_timezone;
		static::$server_tz = date_default_timezone_get();

		// Set time to UTC time for consistency
		DateTime::setUserPrefs('UTC');
		date_default_timezone_set('UTC');
		DateTime::$server_timezone = new \DateTimeZone('UTC');
	}
	public static function tearDownAfterClass() : void
	{
		// Reset
		DateTime::setUserPrefs(static::$usertime->getName());
		date_default_timezone_set(static::$server_tz);

		unset($GLOBALS['egw']);
		parent::tearDownAfterClass();
	}

	/**
	 * Test the widget's basic functionality - we put data in, it comes back
	 * unchanged.
	 *
	 * @dataProvider basicProvider
	 */
	public function testBasic($content, $expected)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Send it around
		$result = $this->mockedRoundTrip($etemplate, $content);

		// Test it
		$this->validateTest($result, $expected ? $expected : $content);
	}

	public function basicProvider()
	{
		// Reset server timezone here, as it tends to go back to php.ini value
		DateTime::$server_timezone = new \DateTimeZone('UTC');

		$now = new DateTime(time(),DateTime::$server_timezone);
		$now->setTime(22, 13, 20); // Just because 80000 seconds after epoch is 22:13:20

		$today = clone $now;
		$today->setTime(0,0);

		$time = new DateTime('1970-01-01',new \DateTimeZone('UTC'));
		$time->setTime(22, 13, 20); // Just because 80000 seconds after epoch is 22:13:20

		$data = array(
			array(
				array('date' => $today->getTimestamp(), 'date_time' => $today->getTimestamp()),
				false // Expect what went in
			),
			array(
				// Timeonly is epoch
				array('date' => $now->getTimestamp(), 'date_time' => $now->getTimestamp(), 'date_timeonly' => $now->getTimestamp()),
				array('date' => $now->getTimestamp(), 'date_time' => $now->getTimestamp(), 'date_timeonly' => $time->getTimestamp())
			)
		);
		return $data;
	}

	/**
	 * Check some basic validation stuff
	 *
	 * @param type $content
	 * @param type $validation_errors
	 *
	 * @dataProvider validationProvider
	 */
	public function testValidation($content)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		$this->validateRoundTrip($etemplate, Array(), $content, Array(), array_flip(array_keys($content)));
	}

	public function validationProvider()
	{
		// All these are invalid, and should not give a value back
		return array(
			array(array('date' => 'Invalid')),
			array(array('date_time' => 'Invalid')),
			array(array('date_timeonly' => 'Invalid')),
		);
	}

	/**
	 * Test for minimum attribute
	 *
	 * @param String|numeric $value
	 * @param float $min Minimum allowed value
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
		$content = array();

		// Need to exec the template so the widget is there to modify
		$result = $this->mockedExec($etemplate, $content, array(), array(), array());

		// Set limits
		$etemplate->getElementById('date')->attrs['min'] = $min;
		$etemplate->getElementById('date')->attrs['max'] = null;

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
		$data['data']['content'] = array('date' => $value);

		// Let it validate
		Etemplate::ajax_process_content($data['data']['etemplate_exec_id'], $data['data']['content'], false);

		$content = static::$mocked_exec_result;
		static::$mocked_exec_result = array();

		$this->validateTest($content,
				$error ? array() : array('date' => is_string($value) ? strtotime($value) : $value),
				$error ? array('date' => $error) : array()
		);
	}

	public function minProvider()
	{
		return Array(
			// User value,             Min,         Error
			array('',                  0,           FALSE),
			array('2018-01-01',       '2017-12-31', FALSE),
			array('2018-01-01',       '2018-01-01', FALSE),
			array('2017-12-01',       '2017-12-31', TRUE),
			// Relative days
			array('two days from now', 2,           FALSE),
			array(time(),              2,           TRUE),
			array(time(),              -1,          FALSE),
			array('yesterday',         'today',     TRUE),
			// Different periods
			array('yesterday',        '+2d',        TRUE),
			array('yesterday',        '-2d',        FALSE),
			array('yesterday',        '-1m',        FALSE),
			array('yesterday',        '-1y +1m',    FALSE),
			array(time(),             '+1d',        TRUE),
			array(time(),             '+1m',        TRUE),
			array(time(),             '+1y -1m',    TRUE),
		);
	}


	/**
	 * Test for maximum attribute
	 *
	 * @param String|numeric $value
	 * @param float $max Maximum allowed value
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
		$content = array();

		// Need to exec the template so the widget is there to modify
		$result = $this->mockedExec($etemplate, $content, array(), array(), array());

		// Set limits
		$etemplate->getElementById('date')->attrs['min'] = null;
		$etemplate->getElementById('date')->attrs['max'] = $max;

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
		$data['data']['content'] = array('date' => $value);

		// Let it validate
		Etemplate::ajax_process_content($data['data']['etemplate_exec_id'], $data['data']['content'], false);

		$content = static::$mocked_exec_result;
		static::$mocked_exec_result = array();

		return $this->validateTest($content,
				$error ? array() : array('date' => is_string($value) ? strtotime($value) : $value),
				$error ? array('date' => $error) : array()
		);
	}

	public function maxProvider()
	{
		return Array(
			// User value,              Max,         Error
			array('',                   0,           FALSE),
			array('2017-12-31',        '2018-01-01', FALSE),
			array('2018-01-01',        '2018-01-01', FALSE),
			array('2017-12-31',        '2017-12-01', TRUE),
			// Relative days
			array('two days from now',  2,           FALSE),
			array(time(),               2,           FALSE),
			array(time(),               -1,          TRUE),
			array('yesterday',          0,           FALSE),
			// Different periods
			array('yesterday',         '+2d',        FALSE),
			array('yesterday',         '-2d',        TRUE),
			array('yesterday',         '+1m',        FALSE),
			array('yesterday',         '+1y -1m',    FALSE),
			array(time(),              '-1d',        TRUE),
			array(time(),              '-1m',        TRUE),
			array(time(),              '-1y -1m',    TRUE),
		);
	}
}
