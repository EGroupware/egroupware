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
 * Updated 2026-08-22: infolog_zpush reads via date_format='object'
 * (Api\DateTime objects) instead of date_format='server' (raw ints) -
 * see the migration doc's z-push modernization section. Updated again
 * 2026-11-18: GetMessage() converts those objects back to raw
 * server-timestamp ints before assigning them onto SyncTask (via the same
 * Api\DateTime::user2server() call z-push's own Streamer::Encode() applies
 * at wire-encoding time) - SyncObject->Check() runs arithmetic directly on
 * these properties BEFORE any encoding happens and TypeErrors on a
 * DateTime object; see infolog_zpush.inc.php::GetMessage()'s comment.
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
	 * startdate/duedate as raw server-timestamp ints, NOT Api\DateTime
	 * objects - see the comment above infolog_zpush.inc.php::GetMessage()'s
	 * date-field cases for why: z-push's SyncObject->Check() (eg.
	 * SyncTask::Check(), synctask.php:165) runs `$this->duedate % ...`
	 * directly on these properties BEFORE any wire-encoding, so leaving a
	 * DateTime object here throws a TypeError (reported live as a forum bug
	 * on 26.10.20261030, ActiveSync InfoLog sync of any task with a due date
	 * aborting with "TypeError ... Utils::getDayStartOfTimestamp() ...
	 * EGroupware\Api\DateTime % int"). GetMessage() converts via the exact
	 * same Api\DateTime::user2server($v, 'ts') call z-push's own
	 * Streamer::Encode() would have applied at wire-encoding time
	 * (streamer.php:249-251), so the wire value is unchanged.
	 *
	 * Pass criteria: SyncTask::$startdate/$duedate are plain ints equal to
	 * Api\DateTime::user2server(...)'s result for the same
	 * infolog_bo::read($id, true, 'object') entry.
	 */
	public function testGetMessageMapsDatesAsServerTimestamps()
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
		$this->assertIsInt($message->startdate, 'SyncTask::$startdate must be a raw int, or SyncTask::Check() TypeErrors');
		$this->assertIsInt($message->duedate, 'SyncTask::$duedate must be a raw int, or SyncTask::Check() TypeErrors');
		$this->assertSame(Api\DateTime::user2server($expected['info_startdate'], 'ts'), $message->startdate,
			'SyncTask::$startdate must be the same server-timestamp Streamer::Encode() would have produced');
		$this->assertSame(Api\DateTime::user2server($expected['info_enddate'], 'ts'), $message->duedate,
			'SyncTask::$duedate must be the same server-timestamp Streamer::Encode() would have produced');

		// the SyncTask::Check() call that originally crashed on this (reproduces the forum bug if unfixed)
		$this->assertTrue($message->Check(true), 'SyncTask::Check() must not TypeError/fail on a GetMessage() result');
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
		// here. $this->lastMessage->startdate/duedate are already raw
		// server-timestamp ints (GetMessage() converts via
		// Api\DateTime::user2server($v, 'ts'), the same z-push's own
		// Streamer::Encode() would apply), so no further conversion is needed.
		$given_start = $this->lastMessage->startdate;
		$given_end = $this->lastMessage->duedate;

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
	 * $this->lastMessage->startdate is already a raw server-timestamp int
	 * (GetMessage() now converts it via Api\DateTime::user2server($v, 'ts'),
	 * the same conversion z-push's own Streamer::Encode() applies at
	 * wire-encoding time) - exactly the kind of value a real device would
	 * receive over the wire and, for an edit, send back modified. ChangeMessage()
	 * must handle that raw int from a real device, which this simulates.
	 */
	public function testEditedRoundTripAppliesTheChange()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		list($info_id) = $this->writeAndGetMessage();

		$new_start_ts = $this->lastMessage->startdate + 3600;	// move start 1h later
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
