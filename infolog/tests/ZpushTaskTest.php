<?php

/**
 * Phase 0 test harness for the InfoLog storage-migration project
 * (doc/ai/projects/infolog-storage-migration.md).
 *
 * Exercises infolog_zpush's OWN conversion methods (GetMessage/StatMessage/
 * ChangeMessage) directly against a real infolog_bo entry - NOT the z-push
 * protocol/WBXML/device-sync layer, which is out of scope here. Only
 * activesync_backend is faked (via a constructor-disabled PHPUnit mock of
 * splitID()); SyncTask/ContentParameters are the real z-push library
 * classes.
 *
 * This is the highest-priority piece of Phase 0: per the migration doc,
 * infolog_zpush had ZERO test coverage before this file, and is the
 * consumer with no defensive date-handling logic of its own - it trusts
 * infolog_bo's read()/write() date contract completely.
 *
 * Updated 2026-08-22: infolog_zpush now uses date_format='object'
 * (Api\DateTime objects) instead of date_format='server' (raw ints),
 * mirroring calendar_zpush.inc.php's existing pattern - see the migration
 * doc's z-push modernization section.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base
// Must be required (not declared inline here) because this file is
// namespaced - see ZpushTestBootstrap.php's docblock for why the ZLog stub
// specifically needs to land in the global namespace.
require_once realpath(__DIR__.'/ZpushTestBootstrap.php');

use EGroupware\Api;

class ZpushTaskTest extends \EGroupware\Api\AppTest
{
	protected static $server_tz;

	// setTimezones() also overwrites this egw-config value (separate from PHP's own default
	// timezone, which $server_tz above already tracks) - restore it too, or it leaks into
	// whatever test class runs next in the same PHPUnit process.
	protected static $server_config_tz;

	protected $bo;
	protected $zpush;

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

		// activesync_backend's constructor does real session/auth work we
		// don't want in a unit test - disable it and stub only the one
		// method infolog_zpush actually calls (splitID()).
		$backend = $this->getMockBuilder(\activesync_backend::class)
			->disableOriginalConstructor()
			->onlyMethods(array('splitID'))
			->getMock();
		$backend->method('splitID')
			->willReturnCallback(function($str, &$type, &$folder, &$app=null) {
				$type = 'infolog';
				$folder = $GLOBALS['egw_info']['user']['account_id'];
			});

		$this->zpush = new \infolog_zpush($backend);
		// infolog_zpush lazily creates its own infolog_bo - replace it with
		// ours so tracking stays mocked and cleanup/tearDown sees the same bo.
		$ref = new \ReflectionProperty(\infolog_zpush::class, 'infolog');
		$ref->setAccessible(true);
		$ref->setValue($this->zpush, $this->bo);
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
		$this->zpush = null;

		$GLOBALS['egw']->preferences->__construct($GLOBALS['egw_info']['user']['account_id']);
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository(false);
		Api\DateTime::init();
	}

	protected function setTimezones($server, $client)
	{
		$GLOBALS['egw_info']['server']['server_timezone'] = $server;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $client;

		date_default_timezone_set($server);

		Api\DateTime::init();
	}

	protected function makeContentParameters()
	{
		return new \ContentParameters();
	}

	/**
	 * GetMessage() must map info_startdate/info_enddate onto SyncTask's
	 * startdate/duedate as real Api\DateTime objects - infolog_bo::read($id,
	 * true, 'object') is now what GetMessage() uses (2026-08-22 modernization,
	 * see doc/ai/projects/infolog-storage-migration.md's z-push section),
	 * mirroring calendar_zpush.inc.php's existing pattern for SyncAppointment.
	 * z-push's own Streamer::Encode() special-cases "instanceof \DateTime"
	 * property values and converts them via Api\DateTime::user2server()
	 * immediately before wire-encoding, so infolog_zpush itself needs no
	 * manual int conversion any more.
	 *
	 * Pass criteria: SyncTask::$startdate/$duedate are Api\DateTime instances
	 * representing the exact same real instant as
	 * infolog_bo::read($id, true, 'object')'s objects for the same entry
	 * (compared via getTimestamp(), which is representation/timezone-agnostic).
	 */
	public function testGetMessageMapsDatesAsDateTimeObjects()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		$start = Api\DateTime::to('2026-03-10 09:00:00', 'ts');
		$end   = Api\DateTime::to('2026-03-10 10:00:00', 'ts');

		$info = array(
			'info_type' => 'task',
			'info_subject' => 'ZpushTaskTest '.$this->name(),
			'info_startdate' => $start,
			'info_enddate' => $end,
		);
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);

		$expected = $this->bo->read($info_id, true, 'object');

		$message = $this->zpush->GetMessage('folder', $info_id, $this->makeContentParameters());

		$this->assertInstanceOf(\SyncTask::class, $message);
		$this->assertInstanceOf(Api\DateTime::class, $message->startdate);
		$this->assertInstanceOf(Api\DateTime::class, $message->duedate);
		$this->assertSame($expected['info_startdate']->getTimestamp(), $message->startdate->getTimestamp(),
			'SyncTask::$startdate must represent the same real instant as read(..., "object")');
		$this->assertSame($expected['info_enddate']->getTimestamp(), $message->duedate->getTimestamp(),
			'SyncTask::$duedate must represent the same real instant as read(..., "object")');
	}

	/**
	 * StatMessage()'s 'mod' must reflect info_datemodified as read in
	 * server-time (same date_format GetMessage() uses), so a caller
	 * comparing StatMessage() against a later GetMessage() sees consistent
	 * values.
	 */
	public function testStatMessageModMatchesServerTimeModified()
	{
		$info = array(
			'info_type' => 'task',
			'info_subject' => 'ZpushTaskTest '.$this->name(),
		);
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);

		$expected = $this->bo->read($info_id, true, 'server');

		$stat = $this->zpush->StatMessage('folder', $info_id);

		$this->assertEquals($info_id, $stat['id']);
		$this->assertEquals($expected['info_datemodified'], $stat['mod']);
	}

	/**
	 * Round trip that mirrors what a real device does on an unmodified
	 * sync: GetMessage() hands the device a SyncTask, and if the device
	 * makes no changes and syncs it straight back, ChangeMessage() should
	 * persist the SAME dates it started with.
	 *
	 * Server and client timezone are IDENTICAL here on purpose - this is
	 * the baseline "it works" case, contrasted with
	 * testUnmodifiedGetChangeRoundTripPreservesDatesWhenTimezonesDiffer()
	 * below, which covers the same scenario with differing timezones.
	 */
	public function testUnmodifiedGetChangeRoundTripPreservesDatesWhenTimezonesMatch()
	{
		$this->setTimezones('UTC', 'UTC');

		list($info_id, $start, $end) = $this->writeAndGetMessage();

		$stat = $this->zpush->ChangeMessage('folder', $info_id, $this->lastMessage, $this->makeContentParameters());
		$this->assertNotFalse($stat, 'ChangeMessage() rejected an unmodified round trip');

		$saved = $this->bo->read($info_id, true, 'server');

		$this->assertEquals($start, $saved['info_startdate'],
			'an unmodified GetMessage()->ChangeMessage() round trip must not move info_startdate when client tz == server tz');
		$this->assertEquals($end, $saved['info_enddate'],
			'an unmodified GetMessage()->ChangeMessage() round trip must not move info_enddate when client tz == server tz');
	}

	/**
	 * Regression test for a bug found by this test file (fixed 2026-08-19,
	 * see doc/ai/projects/infolog-storage-migration.md): GetMessage() reads
	 * dates via infolog_bo::read($id, true, 'server') (server-time ints),
	 * but ChangeMessage() used to hand that same raw int straight to
	 * infolog_bo::write() with its default $user2server=true - i.e. write()
	 * wrongly treated an already-server-time int as user-time and converted
	 * it again. When the client (device) timezone differed from the server
	 * timezone, an UNMODIFIED sync round trip (device makes no edit, just
	 * syncs back what it received) silently shifted info_startdate/
	 * info_enddate by the client/server offset, on every sync.
	 *
	 * Updated 2026-08-22 for the date_format='object' modernization: both
	 * GetMessage() and ChangeMessage() now read via 'object', and
	 * ChangeMessage() wraps whatever raw int it decodes from $message back
	 * into a real Api\DateTime object (server timezone) before handing it to
	 * write() - which then converts correctly regardless of $user2server,
	 * since a real DateTime object carries its own timezone. This removes
	 * the original fix's $user2server=empty($id) special-casing entirely
	 * (see infolog_zpush.inc.php::ChangeMessage()), rather than just
	 * preserving it - this test still guards the same underlying behavior
	 * (no silent timezone-offset drift on an unmodified round trip), just
	 * through the new object-based mechanism.
	 */
	public function testUnmodifiedGetChangeRoundTripPreservesDatesWhenTimezonesDiffer()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		list($info_id) = $this->writeAndGetMessage();

		// the real instant GetMessage() actually handed the device - NOT the
		// original user-format $start/$end literal used to create the fixture,
		// which would be the same instant but isn't what's being round-tripped
		// here - getTimestamp() is representation/timezone-agnostic, so this
		// comparison can't be fooled by a display-timezone difference alone.
		$given_start = $this->lastMessage->startdate->getTimestamp();
		$given_end = $this->lastMessage->duedate->getTimestamp();

		$stat = $this->zpush->ChangeMessage('folder', $info_id, $this->lastMessage, $this->makeContentParameters());
		$this->assertNotFalse($stat, 'ChangeMessage() rejected an unmodified round trip');

		$saved = $this->bo->read($info_id, true, 'object');

		$this->assertSame($given_start, $saved['info_startdate']->getTimestamp(),
			'an unmodified GetMessage()->ChangeMessage() round trip must not move info_startdate when client tz != server tz');
		$this->assertSame($given_end, $saved['info_enddate']->getTimestamp(),
			'an unmodified GetMessage()->ChangeMessage() round trip must not move info_enddate when client tz != server tz');
	}

	/**
	 * A real edit (not just an untouched echo) must still take effect
	 * correctly under the new object-based representation - guards against a
	 * fix that merely made the no-op case pass by coincidence.
	 *
	 * $this->lastMessage->startdate is set to a raw int here, not an
	 * Api\DateTime object, deliberately: this file exercises infolog_zpush's
	 * OWN methods directly (see class docblock), not the real WBXML wire
	 * layer, and the real wire layer's Decode() never produces DateTime
	 * objects (only Encode() does, via the instanceof-\DateTime special case
	 * this test isn't exercising) - so a raw int is what ChangeMessage() must
	 * actually be able to handle from a real device, and is the accurate
	 * simulation here. Api\DateTime::user2server() derives that int from the
	 * current SyncTask value the same way z-push's own Streamer::Encode()
	 * would have when originally putting it on the wire.
	 */
	public function testEditedRoundTripAppliesTheChange()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		list($info_id) = $this->writeAndGetMessage();

		$new_start_ts = Api\DateTime::user2server($this->lastMessage->startdate, 'ts') + 3600;	// move start 1h later
		$this->lastMessage->startdate = $new_start_ts;

		$stat = $this->zpush->ChangeMessage('folder', $info_id, $this->lastMessage, $this->makeContentParameters());
		$this->assertNotFalse($stat, 'ChangeMessage() rejected the edit');

		$saved = $this->bo->read($info_id, true, 'object');

		$expected_start = new Api\DateTime($new_start_ts, Api\DateTime::$server_timezone);
		$this->assertSame($expected_start->getTimestamp(), $saved['info_startdate']->getTimestamp(),
			'a genuine edit to info_startdate must be persisted as given');
	}

	protected $lastMessage;

	/**
	 * Shared setup for the round-trip tests: write a task with known dates,
	 * then read it back via GetMessage(), stashing the SyncTask for the
	 * caller to feed into ChangeMessage().
	 *
	 * @return array [info_id, start ts, end ts]
	 */
	protected function writeAndGetMessage()
	{
		$start = Api\DateTime::to('2026-03-10 09:00:00', 'ts');
		$end   = Api\DateTime::to('2026-03-10 10:00:00', 'ts');

		$info = array(
			'info_type' => 'task',
			'info_subject' => 'ZpushTaskTest '.$this->name(),
			'info_startdate' => $start,
			'info_enddate' => $end,
		);
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);

		$this->lastMessage = $this->zpush->GetMessage('folder', $info_id, $this->makeContentParameters());

		return array($info_id, $start, $end);
	}
}
