<?php
/**
 * CalDAV tests: native rfc6578 sync-collection REPORT (XML) for the calendar collection
 *
 * REST allows callers to request an arbitrary sort order for regular (non
 * sync-collection) requests via filters[order]. A sync-collection REPORT must
 * NOT honor that: since the sync-token is derived from the modification time
 * of the last entry returned, results MUST always come back oldest
 * modification first - both with and without the <D:limit><D:nresults>
 * chunk-size, and even for the very first (empty sync-token) request.
 *
 * This is the native CalDAV/XML counterpart of
 * calendar/tests/REST/SyncCollectionReportTest.php - both enter
 * Api\CalDAV::REPORT() and share the same calendar_groupdav::propfind_generator()
 * logic, but this test exercises the real WebDAV XML request/response parsing
 * instead of the REST/JSON GET shortcut.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @subpackage tests
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar\CalDAV;

require_once __DIR__.'/../../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;
use PHPUnit\Framework\Attributes\Depends;

class SyncCollectionReportTest extends CalDAVTest
{
	/**
	 * Number of events created to sync
	 */
	protected const N = 4;

	/**
	 * account_lid of a dedicated, freshly created test-user (own empty calendar to sync)
	 *
	 * @var string
	 */
	protected static $user;

	/**
	 * uids of the created events, in creation (= expected sync) order
	 *
	 * @var string[]
	 */
	protected static $uids = [];

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		self::$user = 'sync-cal-xml-'.bin2hex(random_bytes(3));
		$data = [];
		self::createUser(self::$user, $data);
	}

	protected function collection() : string
	{
		return $this->collectionUrl('calendar', self::$user);
	}

	/**
	 * Create an event via native CalDAV PUT and track its uid for the expected sync order
	 */
	protected function createEvent(string $uid) : void
	{
		$ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\n".
			"DTSTART:20350101T100000Z\r\nDTEND:20350101T110000Z\r\n".
			"DTSTAMP:20260727T100000Z\r\nSUMMARY:$uid\r\nUID:$uid\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
		$response = $this->putResource($this->collection().$uid.'.ics', 'text/calendar', $ical, self::$user);
		$this->assertHttpStatus([200, 201], $response, 'creating event '.$uid);
	}

	/**
	 * Create self::N events with distinct (1sec granularity!) modification times.
	 *
	 * Pass criteria: none, this is setup for the tests below.
	 */
	public function testCreateEvents()
	{
		for ($i = 0; $i < self::N; ++$i)
		{
			$uid = self::$user.'-'.$i;
			self::$uids[] = $uid;
			$this->createEvent($uid);
			// modification-time / sync-token has only 1sec granularity
			if ($i < self::N - 1) sleep(1);
		}
		$this->assertCount(self::N, self::$uids);
	}

	/**
	 * sync-collection REPORT without a limit must return all changes in a single response, oldest first.
	 *
	 * Pass criteria:
	 * - HTTP 207, no 507/more-results marker response.
	 * - all created events are returned, in creation (= oldest modified first) order.
	 * - a sync-token is returned to resume from.
	 */
	#[Depends('testCreateEvents')]
	public function testSyncCollectionWithoutNresults()
	{
		$result = $this->reportSyncCollection($this->collection(), '', null, self::$user);

		$this->assertFalse($result['more-results'], 'Unexpected more-results without a limit');
		$this->assertNotEmpty($result['sync-token'], 'No sync-token returned');

		$expected = array_map(fn($uid) => $this->collection().$uid.'.ics', self::$uids);
		$this->assertSame($expected, $result['hrefs'],
			'sync-collection REPORT must return oldest modified event first, matching creation order');
	}

	/**
	 * sync-collection REPORT paginated via <D:limit><D:nresults> must return the same entries, in the
	 * same order, without gaps or duplicates, split into chunks with a 507 marker until exhausted.
	 *
	 * Pass criteria:
	 * - each chunk contains at most nresults entries.
	 * - the 507/more-results marker is present for all but the last chunk.
	 * - concatenating all chunks reproduces exactly the oldest-first order of testSyncCollectionWithoutNresults.
	 */
	#[Depends('testCreateEvents')]
	public function testSyncCollectionWithNresults()
	{
		$nresults = 2;
		$this->assertLessThan(self::N, $nresults, 'Test needs more than one chunk to be meaningful');

		$collected = [];
		$token = '';
		$more = true;
		for ($guard = 0; $more && $guard < self::N + 2; ++$guard)
		{
			$result = $this->reportSyncCollection($this->collection(), $token, $nresults, self::$user);

			$this->assertLessThanOrEqual($nresults, count($result['hrefs']), 'Chunk exceeded requested nresults');

			$collected = array_merge($collected, $result['hrefs']);
			$this->assertNotEmpty($result['sync-token'], 'No sync-token returned for chunk');
			$token = $result['sync-token'];
			$more = $result['more-results'];
		}
		$this->assertLessThan(self::N + 2, $guard, 'Pagination did not terminate (possible infinite loop)');

		$expected = array_map(fn($uid) => $this->collection().$uid.'.ics', self::$uids);
		$this->assertSame($expected, $collected,
			'paginated sync-collection must return the same entries in the same oldest-first order, without gaps or duplicates');
	}

	/**
	 * sync-collection REPORT must report deletions too (rfc6578): an entry removed since the given
	 * sync-token must be reported as a response with no properties (status 404), not simply omitted.
	 *
	 * Pass criteria:
	 * - DELETE succeeds (204).
	 * - the deleted event's href is reported without properties since the checkpoint token.
	 * - no other/unexpected entries are reported since that checkpoint (only this one deletion happened).
	 */
	#[Depends('testCreateEvents')]
	public function testSyncCollectionReportsDeletion()
	{
		// modification-time / sync-token has only 1sec granularity: make sure this entry doesn't
		// land in the same second as the last of the events created by testCreateEvents(), or the
		// resulting sync-token's same-second-collision safety margin would legitimately re-include it
		sleep(1);
		$uid = self::$user.'-deleteme';
		$this->createEvent($uid);
		$path = $this->collection().$uid.'.ics';

		$checkpoint = $this->reportSyncCollection($this->collection(), '', null, self::$user);
		$this->assertNotEmpty($checkpoint['sync-token'], 'No sync-token returned for checkpoint');

		$response = $this->deleteResource($path, self::$user);
		$this->assertHttpStatus(204, $response, 'deleting event '.$uid);

		$result = $this->reportSyncCollection($this->collection(), $checkpoint['sync-token'], null, self::$user);

		$this->assertContains($path, $result['deleted'], 'Deleted event must be reported as a no-properties (404) entry');
		$this->assertSame([$path], $result['hrefs'], 'Unexpected extra/missing entries since checkpoint token');
	}
}
