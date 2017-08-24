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

require_once realpath(__DIR__.'/../../test/WidgetBaseTest.php');

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
	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();

		static::$usertime = DateTime::$user_timezone;
		static::$server_tz = date_default_timezone_get();

		// Set time to UTC time for consistency
		DateTime::setUserPrefs('UTC');
		date_default_timezone_set('UTC');
		DateTime::$server_timezone = new \DateTimeZone('UTC');
	}
	public static function tearDownAfterClass()
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
			// Instanciate the template\
			$etemplate = new Etemplate();
			$etemplate->read(static::TEST_TEMPLATE, 'test');

			$result = $this->mockedRoundTrip($etemplate, $content);
			$this->validateTest($result, $expected ? $expected : $content);
	}

	public function basicProvider()
	{
		$now = new DateTime(time());
		$now->setTime(22, 13, 20); // Just because 80000 seconds after epoch is 22:13:20

		$today = clone $now;
		$today->setTime(0,0);

		$time = new DateTime(80000); // 22:13:20
		$data = array(
			array(
				array('date' => $today->getTimestamp(), 'date_time' => $today->getTimestamp()),
				false
			),
			array(
				// Timestamp in a date field gets adjusted to day start, timeonly is epoch
				array('date' => $now->getTimestamp(), 'date_time' => $now->getTimestamp(), 'date_timeonly' => $now->getTimestamp()),
				array('date' => $now->getTimestamp(), 'date_time' => $now->getTimestamp(), 'date_timeonly' => $time->getTimestamp())
			)
		);
		return $data;
	}
}
