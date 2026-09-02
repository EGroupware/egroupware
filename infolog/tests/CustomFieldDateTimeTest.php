<?php

/**
 * Phase 0 test harness for the InfoLog storage-migration project
 * (doc/ai/projects/infolog-storage-migration.md).
 *
 * Covers a date-time custom field's read/write round trip through
 * infolog_bo, including its own timezone conversion path - distinct from
 * (and in addition to) the main-table date columns DateHandlingTest.php
 * covers. This matters for the migration because the current
 * infolog_so::read()/write()/search() hand-roll this exact conversion
 * (UTC-with-"Z"-suffix storage, Api\DateTime hydration on read) instead of
 * delegating to Api\Storage's already-native support for it - see the
 * migration doc's "Custom fields" section.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;
use EGroupware\Api\Storage\Customfields;

class CustomFieldDateTimeTest extends \EGroupware\Api\AppTest
{
	const CF_NAME = 'phpunit_datehandling_cf';

	protected static $cf = array(
		'app'     => 'infolog',
		'name'    => self::CF_NAME,
		'label'   => 'PHPUnit date-time CF',
		'type'    => 'date-time',
		'type2'   => array(),
		'help'    => '',
		'values'  => null,
		'len'     => null,
		'rows'    => null,
		'order'   => null,
		'needed'  => null,
		'private' => array(),
	);

	protected $bo;

	protected $info_ids = array();

	/**
	 * The cf must exist BEFORE infolog_bo/infolog_so are constructed - both
	 * cache Api\Storage\Customfields::get('infolog') once in their own
	 * constructor (see migration doc's "Custom fields" section), so
	 * registering it here (once per class) rather than per-test is both
	 * sufficient and cheaper.
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		Customfields::update(self::$cf);
	}

	public static function tearDownAfterClass() : void
	{
		$fields = Customfields::get('infolog');
		unset($fields[self::CF_NAME]);
		Customfields::save('infolog', $fields);

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
			$this->bo->delete($info_id);
			$this->bo->delete($info_id);
		}
		$this->info_ids = array();
		$this->bo = null;
	}

	protected function makeInfolog(array $fields = array())
	{
		$info = array(
			'info_type'    => 'task',
			'info_subject' => 'CustomFieldDateTimeTest ' . $this->name(),
		);
		foreach($fields as $field => $value)
		{
			$info[$field] = $value;
		}
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	protected function cfKey()
	{
		return '#'.self::CF_NAME;
	}

	/**
	 * The cf must actually be visible to a freshly-constructed infolog_bo,
	 * and (per the migration doc's note on infolog_bo::__construct()
	 * back-filling date-time cfs into $this->timestamps) must have made it
	 * into $this->bo->timestamps too, or time2time() would silently skip
	 * converting it.
	 */
	public function testCustomFieldIsRegisteredAndTrackedAsTimestamp()
	{
		$this->assertArrayHasKey(self::CF_NAME, $this->bo->customfields,
			'the date-time cf must be visible to a freshly constructed infolog_bo');
		$this->assertContains($this->cfKey(), $this->bo->timestamps,
			'a date-time cf must be added to $this->bo->timestamps so time2time() converts it');
	}

	/**
	 * Baseline round trip: a date-time cf value written through write() must
	 * come back as an Api\DateTime instance when read with date_format=
	 * 'object'.
	 *
	 * A date-time cf follows the SAME $date_format contract as the
	 * main-table date columns, not a cf-specific one: infolog_bo::
	 * __construct() merges its key into $this->timestamps alongside
	 * info_startdate/etc. (see testCustomFieldIsRegisteredAndTrackedAsTimestamp()
	 * above), and time2time() converts every entry in that array uniformly
	 * per the caller's requested $date_format - so the default plain
	 * read($id) (format 'ts') returns a plain int for the cf too, same as
	 * it does for info_startdate.
	 */
	public function testDateTimeCustomFieldRoundTrip()
	{
		$value = new Api\DateTime('2026-03-10 09:00:00', Api\DateTime::$user_timezone);

		$info_id = $this->makeInfolog(array($this->cfKey() => $value));

		$saved = $this->bo->read($info_id, true, 'object');

		$this->assertInstanceOf(Api\DateTime::class, $saved[$this->cfKey()],
			"date_format='object' must return an Api\\DateTime instance for a date-time cf too");
		$this->assertEquals(Api\DateTime::to($value, 'utc'), Api\DateTime::to($saved[$this->cfKey()], 'utc'),
			'a date-time custom field must round-trip to the same UTC instant');
	}

	/**
	 * Same all-day ("keep the same calendar date across timezones")
	 * semantics main-table date columns get via time2time() must also apply
	 * to a date-time custom field, since infolog_bo::__construct() folds cf
	 * date-time fields into the same $this->timestamps array time2time()
	 * iterates - this is the exact mechanism a future Api\Storage-backed
	 * so class would need to either keep or deliberately drop for cfs too.
	 */
	public function testDateTimeCustomFieldAllDayPreservedAcrossTimezoneChange()
	{
		// try/finally starts right after this first tz mutation (not just around the later
		// read()) so that if makeInfolog() itself throws before the 2nd tz change below, the
		// client tz preference still gets restored instead of leaking 'Europe/Berlin' into
		// whatever test runs next in the same PHPUnit process.
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = 'Europe/Berlin';
		Api\DateTime::init();

		try
		{
			$midnight = new Api\DateTime('2026-03-10 00:00:00', Api\DateTime::$user_timezone);
			$info_id = $this->makeInfolog(array($this->cfKey() => $midnight));

			$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = 'Pacific/Auckland';
			Api\DateTime::init();

			$saved = $this->bo->read($info_id, true, 'object');

			$this->assertEquals('2026-03-10', $saved[$this->cfKey()]->format('Y-m-d'),
				'an all-day date-time cf must keep the same calendar date across a client timezone change');
			$this->assertEquals('00:00:00', $saved[$this->cfKey()]->format('H:i:s'),
				'an all-day date-time cf must stay at local midnight, not shift to a different wall-clock time');
		}
		finally
		{
			// restore before tearDown() runs its own delete()/read() calls
			$GLOBALS['egw']->preferences->__construct($GLOBALS['egw_info']['user']['account_id']);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository(false);
			Api\DateTime::init();
		}
	}
}
