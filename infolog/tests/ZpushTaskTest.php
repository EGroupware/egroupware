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

	protected $bo;
	protected $zpush;

	protected $info_ids = array();

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		static::$server_tz = date_default_timezone_get();
	}

	public static function tearDownAfterClass() : void
	{
		date_default_timezone_set(static::$server_tz);

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
	 * Classic path: GetMessage() must map info_startdate/info_enddate onto
	 * SyncTask's startdate/duedate as the plain server-time ints that
	 * infolog_bo::read($id, true, 'server') returns - infolog_zpush does
	 * zero date-conversion of its own (per the migration doc's consumer
	 * map), it just copies the array value onto the SyncTask property.
	 *
	 * Pass criteria: SyncTask::$startdate/$duedate equal
	 * infolog_bo::read($id, true, 'server')'s raw ints for the same entry.
	 */
	public function testGetMessageMapsDatesAsServerTimeIntegers()
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

		$expected = $this->bo->read($info_id, true, 'server');

		$message = $this->zpush->GetMessage('folder', $info_id, $this->makeContentParameters());

		$this->assertInstanceOf(\SyncTask::class, $message);
		$this->assertEquals($expected['info_startdate'], $message->startdate,
			'SyncTask::$startdate must equal the server-time int, unconverted');
		$this->assertEquals($expected['info_enddate'], $message->duedate,
			'SyncTask::$duedate must equal the server-time int, unconverted');
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
	 * Fix: ChangeMessage() now reads the existing entry via
	 * read($id, true, 'server') (matching GetMessage()'s format) and calls
	 * write($infolog, true, true, false) for edits of an existing entry, so
	 * $infolog stays server-time-consistent throughout instead of mixing
	 * representations. New-entry creation (empty $id) is deliberately left
	 * on the old $user2server=true default, since there is no prior
	 * server-time read to be consistent with there.
	 */
	public function testUnmodifiedGetChangeRoundTripPreservesDatesWhenTimezonesDiffer()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		list($info_id) = $this->writeAndGetMessage();

		// What GetMessage() actually handed the device - the server-time int -
		// is the right thing to compare against, NOT the original user-format
		// $start/$end literal used to create the fixture: 'server' and 'ts'
		// are two legitimately different int representations of the same
		// instant (offset by the client/server tz difference), so comparing
		// the round trip against the wrong one would just reintroduce this
		// test as a false failure.
		$given_start = $this->lastMessage->startdate;
		$given_end = $this->lastMessage->duedate;

		$stat = $this->zpush->ChangeMessage('folder', $info_id, $this->lastMessage, $this->makeContentParameters());
		$this->assertNotFalse($stat, 'ChangeMessage() rejected an unmodified round trip');

		$saved = $this->bo->read($info_id, true, 'server');

		$this->assertEquals($given_start, $saved['info_startdate'],
			'an unmodified GetMessage()->ChangeMessage() round trip must not move info_startdate when client tz != server tz');
		$this->assertEquals($given_end, $saved['info_enddate'],
			'an unmodified GetMessage()->ChangeMessage() round trip must not move info_enddate when client tz != server tz');
	}

	/**
	 * A real edit (not just an untouched echo) must still take effect
	 * correctly under the same server-time-consistent representation the
	 * fix above relies on - guards against a fix that merely made the
	 * no-op case pass by coincidence.
	 */
	public function testEditedRoundTripAppliesTheChange()
	{
		$this->setTimezones('UTC', 'Europe/Berlin');

		list($info_id) = $this->writeAndGetMessage();

		$new_start = $this->lastMessage->startdate + 3600;	// move start 1h later, server-time int
		$this->lastMessage->startdate = $new_start;

		$stat = $this->zpush->ChangeMessage('folder', $info_id, $this->lastMessage, $this->makeContentParameters());
		$this->assertNotFalse($stat, 'ChangeMessage() rejected the edit');

		$saved = $this->bo->read($info_id, true, 'server');

		$this->assertEquals($new_start, $saved['info_startdate'],
			'a genuine edit to info_startdate must be persisted as given, server-time-for-server-time');
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
