<?php

/**
 * EGroupware Api: DateTime tests
 *
 * @link http://www.stylite.de
 * @package api
 * @author Nathan Gray
 * @copyright (c) 2017 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
namespace EGroupware\Api;

use PHPUnit\Framework\TestCase as TestCase;

/**
 * Testing the Egroupware extension of DateTime
 *
 */
class DateTimeTest extends TestCase {

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

		// Set user time to server time for consistency
		DateTime::setUserPrefs(date_default_timezone_get());
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
	 * Test that dates before 1970 are handled correctly
	 */
	public function testBefore1970()
	{
		$checklist = array(
			// Use sub array instead of key/value pair to preserve type
			// Timestamp         Expected
			array('19690811',   '1969-08-11 00:00:00'),
			array(-3600,        '1969-12-31 23:00:00'),
			array('-119322000', '1966-03-21 23:00:00')
		);
		foreach($checklist as $test)
		{
			list($ts, $expected) = $test;
			$this->checkTimestamp($ts, $expected);
		}
	}

	/**
	 * Check timezone conversion is sane - convert back and forth between same
	 * timezone
	 */
	public function testUserTimeEqualsServerTime()
	{
		$time = time();
		$this->assertEquals($time, DateTime::server2user($time, 'ts'));
		$this->assertEquals(date('Y-m-d H:i:s',$time), DateTime::server2user($time,'Y-m-d H:i:s'));


		$this->assertEquals('2009-10-20, 12:00',DateTime::to(array('full' => '20091020', 'hour' => 12, 'minute' => 0)));

		$ts = DateTime::to(array('full' => '20091027', 'hour' => 10, 'minute' => 0),'ts');
		$this->assertEquals(DateTime::user2server($ts,''), DateTime::server2user(DateTime::user2server($ts),''));

		$ts2 = DateTime::to(array('full' => '20090627', 'hour' => 10, 'minute' => 0),'ts');
		$this->assertEquals(DateTime::user2server($ts2,''), DateTime::server2user(DateTime::user2server($ts2),''));
	}

	/**
	 * Test timezone conversion with actual changes
	 */
	public function testTimezoneConversion()
	{
		// Set server to UTC
		$server_tz = date_default_timezone_get();
		date_default_timezone_set('UTC');

		// Set user to Berlin (UTC-1)
		DateTime::setUserPrefs('Europe/Berlin');
		$ts = DateTime::to(array('full' => '20091027', 'hour' => 10, 'minute' => 0),'ts');
		$this->assertEquals('2009-10-27 09:00:00', DateTime::user2server($ts,'Y-m-d H:i:s'));

		// Set user to Cape Verde (UTC+1)
		DateTime::setUserPrefs('Atlantic/Cape_Verde');
		$ts2 = DateTime::to(array('full' => '20091027', 'hour' => 10, 'minute' => 0),'ts');
		$this->assertEquals('2009-10-27 11:00:00', DateTime::user2server($ts2,'Y-m-d H:i:s'));

		date_default_timezone_set($server_tz);
	}

	/**
	 * Check that a timestamp matches expectations
	 *
	 * @param string|int $ts Something that looks like a time
	 * @param string $expected Expected time, in Y-m-d H:i:s format
	 */
	protected function checkTimestamp($ts, $expected)
	{
		$fail_message = "Checking $ts = $expected";
		$this->assertEquals($expected, DateTime::to($ts,'Y-m-d H:i:s'), $fail_message);

		$dt = new DateTime($ts);
		$this->assertEquals($expected, $dt->format('Y-m-d H:i:s'), $fail_message);
	}
}
