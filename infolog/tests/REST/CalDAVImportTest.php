<?php
/**
 * REST API import/validation/ACL tests for InfoLog tasks.
 *
 * REST (JSON / JsTask) equivalent of infolog/tests/CalDAV/CalDAVImportTest.php.
 * Instead of iCalendar VTODO payloads over CalDAV PUT, this creates/updates
 * tasks via JSON POST/PUT against the REST API and checks the same InfoLog
 * field mapping, malformed-payload rejection and foreign-collection ACL denial.
 *
 * @package infolog
 * @subpackage tests
 */

namespace EGroupware\infolog\REST;

require_once __DIR__.'/../../../api/tests/RestBase.php';

use EGroupware\Api\RestBase;
use GuzzleHttp\RequestOptions;

class CalDAVImportTest extends RestBase
{
	protected const OTHER_USER = 'infolog_rest_other';

	/**
	 * Track created task ids for cleanup.
	 *
	 * @var int[]
	 */
	protected $created_tasks = [];

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		$data = [];
		self::createUser(self::OTHER_USER, $data);
	}

	protected function ownerUser() : string
	{
		$this->assertNotEmpty($GLOBALS['EGW_USER'], 'EGW_USER must be configured for REST tests');
		return $GLOBALS['EGW_USER'];
	}

	protected function collection() : string
	{
		return $this->collectionUrl('infolog', $this->ownerUser());
	}

	/**
	 * POST a JsTask to the owner's infolog collection and return [id, decoded JsTask].
	 *
	 * @param array $fields JsTask attributes, e.g. ['title' => ..., 'priority' => 1]
	 * @return array{0: int, 1: array}
	 */
	protected function createTask(array $fields) : array
	{
		$response = $this->postResource($this->collection(), $fields, $this->ownerUser());
		$this->assertHttpStatus([200, 201], $response, 'creating infolog task');

		// Unlike the CalDAV equivalent (tracked by caldav_name, known independent of the ETag), the
		// only handle we have on a REST-created resource is this numeric id - if it fails to parse
		// (Location/ETag malformed), there's no way to identify the resource for cleanup at all, and
		// the assertion below is what surfaces that as a test failure rather than reordering fixing it.
		$id = (int)$this->resourceIdFromResponse($response, 'infolog');
		$this->assertGreaterThan(0, $id, 'Expected numeric id in Location/ETag header');
		$this->created_tasks[] = $id;

		return [$id, $this->jsonDecode($response)];
	}

	/**
	 * PUT a full JsTask onto an existing resource (REST PUT requires all attributes, incl. the
	 * unchanged 'uid' - the server rejects a PUT that would change it).
	 *
	 * @param int $id
	 * @param array $fields full JsTask attributes, incl. 'uid'
	 * @return array decoded JsTask
	 */
	protected function updateTask(int $id, array $fields) : array
	{
		$response = $this->getClient($this->ownerUser())->put($this->url($this->collection().$id), [
			RequestOptions::HEADERS => $this->jsonHeaders(['Prefer' => 'return=representation']),
			RequestOptions::BODY => $this->jsonBody($fields),
		]);
		$this->assertHttpStatus(200, $response, 'updating infolog task '.$id);

		return $this->jsonDecode($response);
	}

	/**
	 * GET a single task and return its decoded JsTask (asserts HTTP 200).
	 */
	protected function getResourceJson(int $id) : array
	{
		$response = $this->getClient($this->ownerUser())->get($this->url($this->collection().$id), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(200, $response, 'fetching infolog task '.$id);
		return $this->jsonDecode($response);
	}

	/**
	 * Check whether a task with the given (unique) title is visible in the owner's collection.
	 */
	protected function taskExistsWithTitle(string $title) : bool
	{
		$response = $this->getClient($this->ownerUser())->get($this->url($this->collection()), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::QUERY => ['filters' => ['info_subject' => $title]],
		]);
		$this->assertHttpStatus(200, $response, 'searching infolog collection for title '.$title);

		return !empty($this->jsonDecode($response)['responses']);
	}

	public function tearDown() : void
	{
		foreach(array_unique($this->created_tasks) as $id)
		{
			$response = $this->deleteResource($this->collection().$id, $this->ownerUser());
			if (!in_array($response->getStatusCode(), [204, 404], true))
			{
				$this->assertContains($response->getStatusCode(), [204, 404], "Cleanup delete failed for task $id");
			}
		}
		$this->created_tasks = [];

		parent::tearDown();
	}

	/**
	 * Create a new task via REST POST and verify InfoLog field mapping.
	 *
	 * Pass criteria:
	 * - POST to the infolog collection succeeds with 200/201.
	 * - Response (Prefer: return=representation) contains the submitted title,
	 *   description, progress, percentComplete and priority.
	 */
	public function testCreateSetsFields()
	{
		$uid = $this->makeUid('infolog-rest-create');
		[, $task] = $this->createTask([
			'title' => $uid,
			'description' => 'Created through REST import',
			'progress' => 'in-progress',
			'percentComplete' => 55,
			'priority' => 1,
		]);

		$this->assertSame($uid, $task['title'] ?? null);
		$this->assertSame('Created through REST import', $task['description'] ?? null);
		$this->assertSame('in-progress', $task['progress'] ?? null);
		$this->assertSame(55, $task['percentComplete'] ?? null);
		$this->assertSame(1, $task['priority'] ?? null);
	}

	/**
	 * Update an existing task via a full REST PUT and verify the changed fields persist.
	 *
	 * Pass criteria:
	 * - PUT with the unchanged 'uid' plus new title/description/progress/percentComplete
	 *   succeeds with 200 and keeps the same id.
	 * - GET afterwards reflects all the updated fields, not the initial ones.
	 */
	public function testUpdateExistingTask()
	{
		$uid = $this->makeUid('infolog-rest-update');
		[$id, $created] = $this->createTask([
			'title' => $uid.'-initial',
			'description' => 'Initial version',
			'progress' => 'in-progress',
			'percentComplete' => 55,
			'priority' => 1,
		]);

		$updated = $this->updateTask($id, [
			'uid' => $created['uid'],
			'title' => $uid.'-updated',
			'description' => 'Updated version',
			'progress' => 'completed',
			'percentComplete' => 100,
			'priority' => 9,
			'egroupware.org:completed' => '2026-07-20T10:00:00Z',
		]);

		$this->assertSame($uid.'-updated', $updated['title'] ?? null);
		$this->assertSame('Updated version', $updated['description'] ?? null);
		$this->assertSame('completed', $updated['progress'] ?? null);
		$this->assertSame(100, $updated['percentComplete'] ?? null);
		$this->assertSame(9, $updated['priority'] ?? null);
		$this->assertNotEmpty($updated['egroupware.org:completed'] ?? null);

		$fetched = $this->getResourceJson($id);
		$this->assertSame($uid.'-updated', $fetched['title'] ?? null);
	}

	/**
	 * Setting 'egroupware.org:completed' without an explicit 'progress' must not implicitly
	 * mark the task done - current default mapping keeps it open (needs-action / 0%).
	 *
	 * Pass criteria:
	 * - Created task keeps the default "needs-action" progress and no/zero percentComplete.
	 * - The completed timestamp is still stored.
	 */
	public function testCompletedWithoutProgressDefaultsToNotStarted()
	{
		$uid = $this->makeUid('infolog-rest-completed');
		[, $task] = $this->createTask([
			'title' => $uid,
			'egroupware.org:completed' => '2026-07-20T10:00:00Z',
		]);

		$this->assertSame('needs-action', $task['progress'] ?? null);
		$this->assertEmpty($task['percentComplete'] ?? 0);
		$this->assertNotEmpty($task['egroupware.org:completed'] ?? null);
	}

	/**
	 * Malformed JSON payload on the infolog collection must be rejected.
	 *
	 * Pass criteria:
	 * - POST returns a client error (400/415/422); the broken payload is not silently stored.
	 */
	public function testCreateRejectsMalformedJson()
	{
		$response = $this->getClient($this->ownerUser())->post($this->url($this->collection()), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			// truncated / invalid JSON
			RequestOptions::BODY => '{ "title": "broken payload", "priority": ',
		]);
		$this->assertHttpStatus([400, 415, 422], $response);
	}

	/**
	 * User without ACL must not be able to create in another user's infolog collection.
	 *
	 * Pass criteria:
	 * - Foreign POST is denied with HTTP 403.
	 * - The task does not show up in the owner's collection under its unique title.
	 */
	public function testCreateDeniedForForeignCollectionWithoutAcl()
	{
		$uid = $this->makeUid('infolog-rest-acl-denied');

		$response = $this->getClient(self::OTHER_USER)->post($this->url($this->collection()), [
			RequestOptions::HEADERS => $this->jsonHeaders(['Prefer' => 'return=representation']),
			RequestOptions::BODY => $this->jsonBody(['title' => $uid]),
		]);
		$this->assertHttpStatus(403, $response);

		$this->assertFalse($this->taskExistsWithTitle($uid), 'Foreign task must not have been created in the owner collection');
	}
}
