<?php

/**
 * Phase 0 test harness for the InfoLog storage-migration project
 * (doc/ai/projects/infolog-storage-migration.md).
 *
 * Locks down infolog_bo's current date/timezone-conversion contract at the
 * read()/write() boundary BEFORE infolog_so is swapped for Api\Storage, so a
 * regression in that swap shows up here instead of in a live consumer.
 *
 * Specifically covers:
 * - the classic 'ts' (default) and 'server' date_format round trip
 * - read($id, ..., 'object') returning real Api\DateTime instances
 *   (time2time()'s $type passthrough via Api\DateTime::to())
 * - write() accepting Api\DateTime objects on date fields, not just ints -
 *   this is the fact the planned infolog_zpush object-based modernization
 *   (see the migration doc's "Alongside Phase 1" section) depends on
 * - time2time()'s all-day ("keep the same calendar date across timezones")
 *   semantics, which Api\Storage's automatic conversion has no equivalent of
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

class DateHandlingTest extends \EGroupware\Api\AppTest
{
	protected static $server_tz;

	// setTimezones() also overwrites this egw-config value (separate from PHP's own default
	// timezone, which $server_tz above already tracks) - restore it too, or it leaks into
	// whatever test class runs next in the same PHPUnit process.
	protected static $server_config_tz;

	protected $bo;

	protected $info_ids = array();

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		static::$server_tz = date_default_timezone_get();
		static::$server_config_tz = $GLOBALS['egw_info']['server']['server_timezone'] ?? null;
	}

	public static function tearDownAfterClass() : void
	{
		date_default_timezone_set(static::$server_tz);
		$GLOBALS['egw_info']['server']['server_timezone'] = static::$server_config_tz;

		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();

		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->info_ids) as $info_id)
		{
			// Twice, so it's really gone and not just kept for history
			$this->bo->delete($info_id);
			$this->bo->delete($info_id);
		}
		$this->info_ids = array();
		$this->bo = null;

		// Restore whatever timezone prefs setTimezones() below changed
		$GLOBALS['egw']->preferences->__construct($GLOBALS['egw_info']['user']['account_id']);
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository(false);
		Api\DateTime::init();
	}

	/**
	 * Set the current client & server timezones, same technique as
	 * calendar/tests/TimezoneTest.php::setTimezones().
	 *
	 * @param string $server
	 * @param string $client
	 */
	protected function setTimezones($server, $client)
	{
		$GLOBALS['egw_info']['server']['server_timezone'] = $server;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $client;

		date_default_timezone_set($server);

		Api\DateTime::init();
	}

	protected function getTestInfolog(array $fields = array())
	{
		$info = array(
			'info_type'    => 'task',
			'info_subject' => 'DateHandlingTest entry for ' . $this->name(),
		);
		foreach($fields as $field => $value)
		{
			$info[$field] = $value;
		}
		return $info;
	}

	/**
	 * Baseline: classic default ('ts' = user-time int) read/write round trip.
	 *
	 * Pass criteria: what write() is given for info_startdate/info_enddate
	 * (user-time ints) is exactly what read() (default format) returns back.
	 */
	public function testTimestampRoundTrip()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		$start = Api\DateTime::to('2026-03-10 09:00:00', 'ts');
		$end   = Api\DateTime::to('2026-03-10 10:00:00', 'ts');

		$info = $this->getTestInfolog(array(
			'info_startdate' => $start,
			'info_enddate'   => $end,
		));

		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		$this->assertNotEmpty($info_id, 'write() did not return a valid info_id');

		$saved = $this->bo->read($info_id);

		$this->assertEquals($start, $saved['info_startdate'], 'info_startdate did not round-trip as an int');
		$this->assertEquals($end, $saved['info_enddate'], 'info_enddate did not round-trip as an int');
		$this->assertIsInt($saved['info_startdate'], 'default date_format should return an int');
	}

	/**
	 * read($id, ..., 'object') must hand back real Api\DateTime instances,
	 * matching the same instant as the classic 'ts' contract.
	 *
	 * Pass criteria: info_startdate/info_enddate are Api\DateTime instances
	 * whose 'ts' formatting matches the plain int read of the same entry.
	 */
	public function testReadObjectFormatReturnsApiDateTime()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		$start = Api\DateTime::to('2026-03-10 09:00:00', 'ts');

		$info = $this->getTestInfolog(array('info_startdate' => $start));
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);

		$as_ts = $this->bo->read($info_id, true, 'ts');
		$as_object = $this->bo->read($info_id, true, 'object');

		$this->assertInstanceOf(Api\DateTime::class, $as_object['info_startdate'],
			"date_format='object' must return an Api\\DateTime instance");
		$this->assertEquals($as_ts['info_startdate'], Api\DateTime::to($as_object['info_startdate'], 'ts'),
			'object-format read must describe the same instant as the ts-format read');
	}

	/**
	 * write() must accept Api\DateTime objects on date fields, not just ints -
	 * this is the pre-existing capability the planned infolog_zpush
	 * object-based modernization (see migration doc) depends on, since
	 * time2time()'s `new Api\DateTime($values[$key], $tz)` already accepts a
	 * DateTimeInterface transparently (Api\DateTime::__construct()'s 'object'
	 * case). This test proves that today, before any so-backend change.
	 *
	 * Pass criteria: writing with an Api\DateTime-valued info_startdate
	 * produces the exact same stored value as writing the equivalent int.
	 */
	public function testWriteAcceptsApiDateTimeObjects()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		$as_int = Api\DateTime::to('2026-03-10 09:00:00', 'ts');
		$as_object = new Api\DateTime('2026-03-10 09:00:00', Api\DateTime::$user_timezone);

		$info_int = $this->getTestInfolog(array('info_startdate' => $as_int));
		$this->info_ids[] = $id_int = $this->bo->write($info_int, true, true, true, true);

		$info_object = $this->getTestInfolog(array('info_startdate' => $as_object));
		$this->info_ids[] = $id_object = $this->bo->write($info_object, true, true, true, true);

		$saved_int = $this->bo->read($id_int);
		$saved_object = $this->bo->read($id_object);

		$this->assertEquals($saved_int['info_startdate'], $saved_object['info_startdate'],
			'write() with an Api\\DateTime-valued date field must store the same instant as the equivalent int');
	}

	/**
	 * time2time()'s all-day ("keep the same calendar date, not the same
	 * instant") semantics must survive a client-timezone change between
	 * write and read. This is the regression net for the migration doc's
	 * design decision #2 (keep the all-day-aware wrapper) - Api\Storage's
	 * plain server2user()/user2server() conversion has no equivalent, so if
	 * a future so-backend swap drops this behavior, this test catches it.
	 *
	 * Pass criteria: an all-day entry (midnight local time) written while
	 * the client is in one timezone still shows the SAME calendar date when
	 * read back after the client timezone preference changes to a
	 * significantly different one.
	 */
	public function testAllDayDatePreservedAcrossTimezoneChange()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		// Midnight in the (Berlin) client timezone - the all-day marker
		// time2time() keys off of (format('Hi') == '0000').
		$midnight = new Api\DateTime('2026-03-10 00:00:00', Api\DateTime::$user_timezone);

		$info = $this->getTestInfolog(array('info_startdate' => $midnight));
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);

		// Switch the client to a very different timezone before reading back.
		$this->setTimezones('UTC', 'Pacific/Auckland');

		$saved = $this->bo->read($info_id, true, 'object');

		$this->assertEquals('2026-03-10', $saved['info_startdate']->format('Y-m-d'),
			'all-day info_startdate must keep the same calendar date across a client timezone change');
		$this->assertEquals('00:00:00', $saved['info_startdate']->format('H:i:s'),
			'all-day info_startdate must stay at local midnight, not shift to a different wall-clock time');
	}

	/**
	 * A non-all-day (timed) entry must NOT get the all-day treatment - it
	 * should follow the viewer's timezone like a normal instant, in
	 * contrast to testAllDayDatePreservedAcrossTimezoneChange() above.
	 *
	 * Note: EGroupware's 'ts' format is deliberately NOT a UTC epoch (see
	 * Api\DateTime::format()'s docblock: "EGroupware's integer timestamp is
	 * NOT the usual UTC timestamp, but has a timezone offset applied") - it's
	 * the object's own wall-clock digits read via mktime(), so it is only
	 * comparable between two DateTime instances in the SAME timezone. The
	 * true, timezone-independent instant is 'utc' (::getTimestamp()), used
	 * here instead.
	 *
	 * Pass criteria: a 09:00 Berlin-time entry, read back as a Berlin user,
	 * reports 09:00; the same instant read back as an Auckland user reports
	 * Auckland's local wall-clock time for that same instant (NOT 09:00),
	 * while the underlying UTC instant stays identical.
	 */
	public function testTimedEntryFollowsViewerTimezone()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		$berlin_9am = new Api\DateTime('2026-03-10 09:00:00', Api\DateTime::$user_timezone);
		$expected_instant = Api\DateTime::to($berlin_9am, 'utc');

		$info = $this->getTestInfolog(array('info_startdate' => $berlin_9am));
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);

		$this->setTimezones('UTC', 'Pacific/Auckland');

		$saved = $this->bo->read($info_id, true, 'object');

		$this->assertNotEquals('09:00:00', $saved['info_startdate']->format('H:i:s'),
			'a timed entry must NOT keep the same wall-clock time across a timezone change');
		$this->assertEquals($expected_instant, Api\DateTime::to($saved['info_startdate'], 'utc'),
			'a timed entry must describe the same UTC instant regardless of viewer timezone');
	}
}
