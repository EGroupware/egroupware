<?php
/**
 * REST API tests: sync-collection REPORT (rfc6578) for the calendar collection
 *
 * The REST API allows callers to request an arbitrary sort order for regular
 * (non sync-collection) requests via filters[order]. A sync-collection REPORT
 * must NOT honor that: since the sync-token is derived from the modification
 * time of the last entry returned, results MUST always come back oldest
 * modification first - both with and without the nresults chunk-size
 * parameter, and even for the very first (empty sync-token) request.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @subpackage tests
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar\REST;

require_once __DIR__.'/../../../api/tests/RestBase.php';

use EGroupware\Api\RestBase;
use PHPUnit\Framework\Attributes\Depends;

class SyncCollectionReportTest extends RestBase
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
	 * cal_ids of the created events, in creation (= expected sync) order
	 *
	 * @var int[]
	 */
	protected static $ids = [];

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		self::$user = 'sync-cal-'.bin2hex(random_bytes(3));
		$data = [];
		self::createUser(self::$user, $data);
	}

	protected function collection() : string
	{
		return $this->collectionUrl('calendar', self::$user);
	}

	/**
	 * Create an event and track its id for the expected sync order
	 */
	protected function createEvent(string $title) : int
	{
		$response = $this->postResource($this->collection(), [
			'title'    => $title,
			'start'    => '2035-01-01T10:00:00',
			'timeZone' => 'Europe/Berlin',
			'duration' => 'PT1H',
		], self::$user);
		$this->assertHttpStatus([200, 201], $response, 'creating event '.$title);

		$id = (int)$this->idFromResponse($response);
		$this->assertGreaterThan(0, $id, 'No numeric id returned for created event '.$title);
		return $id;
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
			self::$ids[] = $this->createEvent('sync-cal-'.$i);
			// modification-time / sync-token has only 1sec granularity
			if ($i < self::N - 1) sleep(1);
		}
		$this->assertCount(self::N, self::$ids);
	}

	/**
	 * sync-collection REPORT without nresults must return all changes in a single response, oldest first.
	 *
	 * Pass criteria:
	 * - HTTP 200, no "more-results" attribute.
	 * - all created events are returned, in creation (= oldest modified first) order.
	 * - a "sync-token" is returned to resume from.
	 */
	#[Depends('testCreateEvents')]
	public function testSyncCollectionWithoutNresults()
	{
		$result = $this->syncCollection($this->collection(), '', null, self::$user);

		$this->assertArrayNotHasKey('more-results', $result, 'Unexpected more-results without nresults');
		$this->assertNotEmpty($result['sync-token'] ?? null, 'No sync-token returned');

		$paths = array_keys($result['responses'] ?? []);
		$expected = array_map(fn($id) => '/'.self::$user.'/calendar/'.$id, self::$ids);
		$this->assertSame($expected, $paths,
			'sync-collection report must return oldest modified event first, matching creation order');
	}

	/**
	 * sync-collection REPORT paginated via nresults must return the same entries, in the same order,
	 * without gaps or duplicates, split into chunks with "more-results": true until exhausted.
	 *
	 * Pass criteria:
	 * - each chunk contains at most nresults entries.
	 * - "more-results" is true for all but the last chunk.
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
			$result = $this->syncCollection($this->collection(), $token, $nresults, self::$user);

			$paths = array_keys($result['responses'] ?? []);
			$this->assertLessThanOrEqual($nresults, count($paths), 'Chunk exceeded requested nresults');

			$collected = array_merge($collected, $paths);
			$this->assertNotEmpty($result['sync-token'] ?? null, 'No sync-token returned for chunk');
			$token = $result['sync-token'];
			$more = !empty($result['more-results']);
		}
		$this->assertLessThan(self::N + 2, $guard, 'Pagination did not terminate (possible infinite loop)');

		$expected = array_map(fn($id) => '/'.self::$user.'/calendar/'.$id, self::$ids);
		$this->assertSame($expected, $collected,
			'paginated sync-collection must return the same entries in the same oldest-first order, without gaps or duplicates');
	}

	/**
	 * sync-collection REPORT must report deletions too (rfc6578): an entry removed since the given
	 * sync-token must be reported with a null value, not simply omitted from the response.
	 *
	 * Pass criteria:
	 * - DELETE succeeds (204).
	 * - the deleted event's path appears in the sync-collection response since the checkpoint token,
	 *   with a null value.
	 * - no other/unexpected entries are reported since that checkpoint (only this one deletion happened).
	 */
	#[Depends('testCreateEvents')]
	public function testSyncCollectionReportsDeletion()
	{
		// modification-time / sync-token has only 1sec granularity: make sure this entry doesn't
		// land in the same second as the last of the events created by testCreateEvents(), or the
		// resulting sync-token's same-second-collision safety margin would legitimately re-include it
		sleep(1);
		$id = $this->createEvent('sync-cal-deleteme');
		$path = $this->collection().$id;

		$checkpoint = $this->syncCollection($this->collection(), '', null, self::$user);
		$this->assertNotEmpty($checkpoint['sync-token'] ?? null, 'No sync-token returned for checkpoint');

		$response = $this->deleteResource($path, self::$user);
		$this->assertHttpStatus(204, $response, 'deleting event '.$id);

		$result = $this->syncCollection($this->collection(), $checkpoint['sync-token'], null, self::$user);

		$this->assertArrayHasKey($path, $result['responses'] ?? [], 'Deleted event missing from sync-collection since checkpoint');
		$this->assertNull($result['responses'][$path], 'Deleted event must be reported with a null value, not full data');
		$this->assertCount(1, $result['responses'] ?? [], 'Unexpected extra/missing entries since checkpoint token');
	}
}
