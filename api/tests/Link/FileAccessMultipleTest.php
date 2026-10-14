<?php
/**
 * EGroupware Api: test Link::file_access_multiple() against the per-entry Link::file_access()
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Link;

use EGroupware\Api;
use EGroupware\Api\Acl;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__.'/../LoggedInTest.php');

/**
 * Link::file_access_multiple() exists purely to spare Link::file_access() its per-entry query, so
 * the one thing it must never do is answer differently than the per-entry call it replaces. Every
 * test here therefore compares the two directly, on the same entries, rather than asserting any
 * particular verdict - what access an entry grants depends on the install's grants, but the two
 * code paths agreeing does not.
 *
 * infolog is used as the app, as the first to register 'file_access_rows'.
 */
class FileAccessMultipleTest extends LoggedInTest
{
	/**
	 * info_ids created by this test, to delete in tearDown
	 *
	 * @var int[]
	 */
	protected $entries = array();

	/**
	 * contact_ids created by this test, to delete in tearDown
	 *
	 * @var int[]
	 */
	protected $contacts = array();

	/**
	 * tr_ids created by this test, to delete in tearDown
	 *
	 * @var int[]
	 */
	protected $tickets = array();

	/**
	 * ts_ids created by this test, to delete in tearDown
	 *
	 * @var int[]
	 */
	protected $sheets = array();

	protected function tearDown() : void
	{
		$bo = new \infolog_bo();
		foreach($this->entries as $id)
		{
			// first delete() only flags an entry as deleted (info_status 'deleted') and keeps the
			// row, a second one actually removes it
			$bo->delete($id);
			$bo->delete($id);
		}
		$this->entries = array();

		$ab = new Api\Contacts();
		foreach($this->contacts as $id)
		{
			$ab->delete($id);
		}
		$this->contacts = array();

		if ($this->tickets)
		{
			$tracker = new \tracker_bo();
			foreach($this->tickets as $tr_id)
			{
				$tracker->delete(array('tr_id' => $tr_id));
			}
			$this->tickets = array();
		}

		if ($this->sheets)
		{
			$ts = new \timesheet_bo();
			foreach($this->sheets as $ts_id)
			{
				$ts->delete($ts_id);
			}
			$this->sheets = array();
		}

		parent::tearDown();
	}

	/**
	 * Drop everything that could answer a later check from memory instead of the database
	 */
	protected function coldCaches()
	{
		Api\Link::init_static(true);
		Api\Cache::setSession(Api\Link::class, 'link_title_cache', null);
		Api\Cache::setSession(Api\Link::class, 'link_file_access_cache', null);

		// infolog_bo caches its own access checks per entry for the current user
		$access_cache = new \ReflectionProperty(\infolog_bo::class, 'access_cache');
		$access_cache->setAccessible(true);
		$access_cache->setValue(null, array());
	}

	/**
	 * Create infolog entries differing in everything the access check looks at
	 *
	 * @return int[]
	 */
	protected function makeEntries() : array
	{
		$bo = new \infolog_bo();
		$user = $GLOBALS['egw_info']['user']['account_id'];
		$ids = array();

		foreach([
			['info_status' => 'open',     'info_access' => 'public'],
			['info_status' => 'open',     'info_access' => 'private'],
			['info_status' => 'done',     'info_access' => 'public'],
			['info_status' => 'archive',  'info_access' => 'public'],
			// responsible gets implicit rights, which is a separate branch of the check
			['info_status' => 'open',     'info_access' => 'private', 'info_responsible' => [$user]],
		] as $n => $values)
		{
			$entry = $values + array(
				'info_subject' => 'file_access_multiple test '.$n.' for #'.$this->name(),
				'info_des'     => 'created by '.__CLASS__,
			);
			$this->assertNotEmpty($id = $bo->write($entry, true, true, true, true),
				'could not create infolog fixture '.$n);
			$this->entries[] = $ids[] = $id;
		}
		return $ids;
	}

	/**
	 * The whole point: priming must not change a single verdict
	 */
	public function testPrimedVerdictsEqualPerEntryVerdicts()
	{
		$ids = $this->makeEntries();

		$this->coldCaches();
		$per_entry = array();
		foreach($ids as $id)
		{
			$per_entry[$id] = Api\Link::file_access('infolog', $id, Acl::READ);
		}

		$this->coldCaches();
		Api\Link::file_access_multiple('infolog', $ids);
		$primed = array();
		foreach($ids as $id)
		{
			$primed[$id] = Api\Link::file_access('infolog', $id, Acl::READ);
		}

		$this->assertSame($per_entry, $primed,
			'Link::file_access_multiple() must leave Link::file_access() answering exactly as before');
	}

	/**
	 * ... and must actually have done the work, not just quietly not primed anything
	 *
	 * Without this the test above would still pass if file_access_multiple() returned immediately,
	 * which is the one way it could be "correct" and useless at the same time.
	 */
	public function testPrimingRemovesThePerEntryQueries()
	{
		$ids = $this->makeEntries();

		$this->coldCaches();
		$per_entry = $this->countQueries(function() use ($ids)
		{
			foreach($ids as $id) Api\Link::file_access('infolog', $id, Acl::READ);
		});

		$this->coldCaches();
		$primed = $this->countQueries(function() use ($ids)
		{
			Api\Link::file_access_multiple('infolog', $ids);
			foreach($ids as $id) Api\Link::file_access('infolog', $id, Acl::READ);
		});

		$this->assertGreaterThan(count($ids), $per_entry,
			'per-entry checks should cost at least one query each, or this test proves nothing');
		$this->assertLessThan($per_entry, $primed,
			'priming should cost fewer queries than checking every entry on its own');
	}

	/**
	 * An id the app does not return a row for has to stay unprimed, so the per-entry check decides
	 */
	public function testUnknownIdIsLeftToThePerEntryCheck()
	{
		$unknown = 0;
		foreach($GLOBALS['egw']->db->select('egw_infolog', 'MAX(info_id) AS max_id', false,
			__LINE__, __FILE__, false, '', 'infolog') as $row)
		{
			$unknown = (int)$row['max_id'] + 1000;
		}
		$this->assertGreaterThan(0, $unknown);

		$this->coldCaches();
		$per_entry = Api\Link::file_access('infolog', $unknown, Acl::READ);

		$this->coldCaches();
		Api\Link::file_access_multiple('infolog', array($unknown));

		$this->assertSame($per_entry, Api\Link::file_access('infolog', $unknown, Acl::READ),
			'an id with no row must be answered the same way as without priming');
	}

	/**
	 * Priming for another user must not write into the cache, which is keyed by app:id only
	 *
	 * file_access() answers for another user from its own uncached branch, so anything left in the
	 * cache here would be handed to the *current* user on the next check.
	 */
	public function testDoesNotPrimeForAnotherUser()
	{
		$ids = $this->makeEntries();
		$other_user = $GLOBALS['egw_info']['user']['account_id'] + 1;

		$this->coldCaches();
		Api\Link::file_access_multiple('infolog', $ids, Acl::READ, $other_user);

		$cache = new \ReflectionProperty(Api\Link::class, 'file_access_cache');
		$cache->setAccessible(true);
		foreach($ids as $id)
		{
			$this->assertArrayNotHasKey('infolog:'.$id, $cache->getValue(),
				'priming for another user must leave the current user\'s cache untouched');
		}
	}

	/**
	 * Only Acl::READ is primed - file_access() re-checks a cached EDIT anyway
	 */
	public function testEditIsNotPrimed()
	{
		$ids = $this->makeEntries();

		$this->coldCaches();
		Api\Link::file_access_multiple('infolog', $ids, Acl::EDIT);

		$cache = new \ReflectionProperty(Api\Link::class, 'file_access_cache');
		$cache->setAccessible(true);
		foreach($ids as $id)
		{
			$this->assertArrayNotHasKey('infolog:'.$id, $cache->getValue());
		}
	}

	/**
	 * readRows() has to be read() in bulk - that equivalence is what makes priming safe
	 *
	 * If infolog's readRows() ever stops returning what its read() returns, a primed verdict stops
	 * being the verdict the per-entry check would have reached, and it can go either way: a field
	 * the check reads going missing can just as easily grant access as deny it.
	 */
	public function testReadRowsReturnsWhatReadReturns()
	{
		$ids = $this->makeEntries();

		$so = new \EGroupware\Infolog\Storage();
		$rows = $so->readRows($ids);

		$this->assertSame($ids, array_keys($rows), 'readRows() must return a row per id');
		foreach($ids as $id)
		{
			$read = (new \EGroupware\Infolog\Storage())->read(array('info_id' => $id));
			$this->assertIsArray($read);
			// info_uid is the one documented difference: read() generates and saves one for an
			// entry that has none, readRows() deliberately does not write to a whole listing
			unset($read['info_uid'], $rows[$id]['info_uid']);

			$this->assertEquals($read, $rows[$id], "readRows()[$id] differs from read($id)");
		}
	}

	/**
	 * Create contacts differing in what check_perms() looks at
	 *
	 * @return int[]
	 */
	protected function makeContacts() : array
	{
		$ab = new Api\Contacts();
		$ids = array();
		foreach([
			['private' => 0],
			['private' => 1],
			// a shared contact: check_perms() falls back to sharing when the grants say no, so a
			// row without it is the one that silently changes a verdict
			['private' => 1, 'shared' => array(array(
				'shared_with'     => $GLOBALS['egw_info']['user']['account_id'],
				'shared_by'       => $GLOBALS['egw_info']['user']['account_id'],
				'shared_writable' => 0,
			))],
		] as $n => $values)
		{
			$contact = $values + array(
				'n_family' => 'FileAccessMultiple'.$n,
				'n_given'  => 'Test',
				'org_name' => 'test #'.$this->name(),
			);
			$this->assertNotEmpty($id = $ab->save($contact), 'could not create contact fixture '.$n);
			$this->contacts[] = $ids[] = is_array($id) ? $contact['id'] : $id;
		}
		return $ids;
	}

	/**
	 * Addressbook's rows have to be what read() returns too
	 *
	 * Contacts carry two things beyond the plain row - the sharing information check_perms() falls
	 * back on, and the distribution lists - each of which read() fetches with its own query. A row
	 * arriving without the sharing information would deny a contact the user can actually reach.
	 */
	public function testContactRowsEqualRead()
	{
		$ids = $this->makeContacts();

		$bulk = (new Api\Contacts())->file_access_rows($ids);
		$read = new \ReflectionMethod(Api\Contacts\Storage::class, 'read');

		foreach($ids as $id)
		{
			$this->assertArrayHasKey($id, $bulk, "file_access_rows() is missing contact $id");
			$single = $read->invoke(new Api\Contacts(), $id);
			$this->assertIsArray($single);
			// uid is the documented exception: read() generates and saves one when too short
			unset($single['uid'], $bulk[$id]['uid']);

			$this->assertEquals($single, $bulk[$id], "file_access_rows()[$id] differs from read($id)");
		}
	}

	/**
	 * ... and priming must not change an addressbook verdict either
	 */
	public function testContactPrimedVerdictsEqualPerEntry()
	{
		$ids = $this->makeContacts();

		$this->coldCaches();
		$per_entry = array();
		foreach($ids as $id)
		{
			$per_entry[$id] = Api\Link::file_access('addressbook', $id, Acl::READ);
		}

		$this->coldCaches();
		Api\Link::file_access_multiple('addressbook', $ids);
		$primed = array();
		foreach($ids as $id)
		{
			$primed[$id] = Api\Link::file_access('addressbook', $id, Acl::READ);
		}

		$this->assertSame($per_entry, $primed);
	}

	/**
	 * Timesheet's rows must carry ts_readable, the entry's explicit read-list
	 *
	 * check_acl() falls back to ts_readable when the grants say no, so a row without it would deny
	 * an entry its owner explicitly shared. No entry on a typical install has it set, which is
	 * exactly why this creates one.
	 */
	public function testTimesheetRowsCarryReadable()
	{
		$bo = new \timesheet_bo();
		$user = $GLOBALS['egw_info']['user']['account_id'];

		$bo->data = array();
		$ts_id = $bo->save(array(
			'ts_title'    => 'file_access_multiple test #'.$this->name(),
			'ts_start'    => time(),
			'ts_duration' => 30,
			'ts_owner'    => $user,
			'ts_readable' => (string)$user,
		));
		$ts_id = $ts_id ?: ($bo->data['ts_id'] ?? null);
		if (empty($ts_id) || !is_numeric($ts_id))
		{
			$this->markTestSkipped('could not create a timesheet entry on this install');
		}
		$this->sheets[] = $ts_id;

		$rows = $bo->file_access_rows(array($ts_id));
		$this->assertArrayHasKey($ts_id, $rows);
		$this->assertSame((string)$user, (string)$rows[$ts_id]['ts_readable'],
			'file_access_rows() must carry ts_readable');
		$this->assertArrayHasKey('ts_owner', $rows[$ts_id]);
		$this->assertArrayHasKey('ts_status', $rows[$ts_id]);

		$this->assertSame(
			(bool)$bo->file_access($ts_id, Acl::READ),
			(bool)$bo->file_access($rows[$ts_id], Acl::READ),
			'answering from the row must match answering from the id');
	}

	/**
	 * Tracker's rows must carry the assignees, which live in their own table
	 *
	 * deny_private() reads tr_assigned to decide whether someone may see a private ticket, and it
	 * is the one field here that does not come from the ticket row itself - so a bulk read that
	 * forgot it would hide private tickets from exactly the people assigned to them.
	 */
	public function testTrackerRowsCarryAssignees()
	{
		$bo = new \tracker_bo();
		$user = $GLOBALS['egw_info']['user']['account_id'];

		$bo->data = array();
		$tr_id = $bo->save(array(
			'tr_summary'  => 'file_access_multiple test #'.$this->name(),
			'tr_tracker'  => key($bo->trackers) ?: 1,
			'tr_private'  => 1,
			'tr_assigned' => array($user),
			'tr_description' => 'created by '.__CLASS__,
		));
		$tr_id = is_array($tr_id) ? ($bo->data['tr_id'] ?? null) : ($tr_id ?: $bo->data['tr_id'] ?? null);
		if (empty($tr_id))
		{
			$this->markTestSkipped('could not create a tracker ticket on this install');
		}
		$this->tickets[] = $tr_id;

		$rows = $bo->file_access_rows(array($tr_id));
		$this->assertArrayHasKey($tr_id, $rows);
		$this->assertEqualsCanonicalizing(
			array_map('intval', (array)(new \tracker_bo())->read($tr_id)['tr_assigned']),
			array_map('intval', (array)$rows[$tr_id]['tr_assigned']),
			'file_access_rows() must report the same assignees read() does');
		$this->assertNotEmpty($rows[$tr_id]['tr_assigned'], 'fixture must actually have an assignee');

		// and the verdict has to come out the same either way
		$this->assertSame(
			(bool)$bo->file_access($tr_id, Acl::READ),
			(bool)$bo->file_access($rows[$tr_id], Acl::READ),
			'answering from the row must match answering from the id');
	}

	/**
	 * Entries have to be read in chunks, or a big listing swaps one limit for another
	 *
	 * Entry rows run to a few KB each, so reading a whole directory's worth in one go turns a
	 * max_execution_time problem into a memory_limit one. Only the chunking keeps the memory a
	 * listing costs independent of how many entries it has.
	 */
	public function testPrimingReadsInChunks()
	{
		$register = new \ReflectionProperty(Api\Link::class, 'app_register');
		$register->setAccessible(true);
		$apps = $register->getValue();
		$apps['test-chunking'] = array(
			'name'             => 'test-chunking',
			'file_access'      => 'api.'.ChunkSpyStorage::class.'.file_access',
			'file_access_rows' => 'api.'.ChunkSpyStorage::class.'.file_access_rows',
		);
		$register->setValue(null, $apps);

		ChunkSpyStorage::$chunks = array();
		$chunk = Api\Link::FILE_ACCESS_CACHE_SIZE;
		Api\Link::file_access_multiple('test-chunking', range(1, 2 * $chunk + 7));

		$this->assertSame(array($chunk, $chunk, 7), ChunkSpyStorage::$chunks,
			'the ids must reach the app in chunks, not all at once');
	}

	/**
	 * A listing's primed verdicts must not displace the session cache
	 *
	 * They are worth keeping for the rest of the request and not beyond it: left in the session
	 * cache, one directory listing would evict everything else from a cache holding 1000 entries,
	 * for answers nothing is going to ask for again.
	 */
	public function testPrimedVerdictsAreNotPersistedToTheSession()
	{
		$ids = $this->makeEntries();

		$this->coldCaches();
		Api\Cache::setSession(Api\Link::class, 'link_file_access_cache', null);

		// one verdict reached the ordinary way, which has to survive
		Api\Link::file_access('infolog', $ids[0], Acl::READ);
		// ... and a listing primed on top of it
		Api\Link::file_access_multiple('infolog', array_slice($ids, 1));

		Api\Link::save_session_cache();
		$session = (array)Api\Cache::getSession(Api\Link::class, 'link_file_access_cache');

		$this->assertArrayHasKey('infolog:'.$ids[0], $session,
			'a verdict file_access() worked out itself has to survive a listing');
		foreach(array_slice($ids, 1) as $id)
		{
			$this->assertArrayNotHasKey('infolog:'.$id, $session,
				'a primed verdict does not belong in the session');
		}
		// ... while still answering for the rest of this request
		$this->assertNotEmpty(Api\Link::file_access('infolog', $ids[1], Acl::READ) !== null);
	}

	/**
	 * Count the database queries a callable causes
	 *
	 * @param callable $callable
	 * @return int
	 */
	protected function countQueries(callable $callable) : int
	{
		$log = tempnam(sys_get_temp_dir(), 'egw-query-log');
		$GLOBALS['egw']->db->query_log = $log;
		try
		{
			$callable();
		}
		finally
		{
			$GLOBALS['egw']->db->query_log = null;
		}
		$queries = 0;
		foreach(file($log) ?: array() as $line)
		{
			if (preg_match('/^\[.*?\] [\d-]+ [\d:]+ (SELECT|INSERT|UPDATE|DELETE)/i', $line)) ++$queries;
		}
		unlink($log);

		return $queries;
	}
}

/**
 * Records the chunk sizes file_access_multiple() asks for
 *
 * Its file_access() answers without reading anything, which is fine here - the point is only how
 * the ids arrive, and the rows still get read so the chunking is the real thing.
 */
class ChunkSpyStorage extends \EGroupware\Api\Storage\Base
{
	/**
	 * @var int[]
	 */
	public static $chunks = array();

	public function __construct()
	{
		parent::__construct('infolog', 'egw_infolog');
	}

	public function file_access($id, $check, $rel_path=null)
	{
		unset($id, $check, $rel_path);

		return true;
	}

	public function file_access_rows(array $ids)
	{
		self::$chunks[] = count($ids);

		return $this->readRows($ids);
	}
}
