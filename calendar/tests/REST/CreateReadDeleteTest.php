<?php
/**
 * REST API tests: create, read and delete an event
 *
 * REST (JSON / JsCalendar) equivalent of
 * calendar/tests/CalDAV/CreateReadDeleteTest.php.
 *
 * Instead of CalDAV/WebDAV requests (PUT/GET/DELETE of "<uid>.ics" with
 * text/calendar bodies and PROPFIND for principal discovery) it uses the REST
 * API: a POST of a JsEvent to the collection to create, GET/DELETE of the
 * numeric resource id with "Accept: application/json", and a GET on the
 * collection for discovery.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @subpackage tests
 * @copyright (c) 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar\REST;

require_once __DIR__.'/../../../api/tests/RestBase.php';

use EGroupware\Api\RestBase;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\Depends;

class CreateReadDeleteTest extends RestBase
{
	protected const EVENT_UID = 'rest-event-create-read-delete';
	protected const EVENT_FIXTURE = __DIR__.'/fixtures/create-read-delete-event.json.tpl';

	/**
	 * UID of the event, generated once per test class
	 */
	protected static $event_uid;

	/**
	 * Numeric cal_id of the created event, shared between the dependent tests
	 *
	 * @var int|string
	 */
	protected static $cal_id;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		self::$event_uid = self::EVENT_UID.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(2));
		self::trackUid(self::$event_uid);
	}

	protected function user() : string
	{
		return $GLOBALS['EGW_USER'];
	}

	/**
	 * Build the JsEvent payload to create, from the fixture template.
	 *
	 * @return array decoded JsEvent
	 */
	protected function eventPayload() : array
	{
		$template = file_get_contents(self::EVENT_FIXTURE);
		$this->assertNotFalse($template, 'Unable to load event fixture');
		$event = json_decode(strtr($template, ['{{UID}}' => self::$event_uid]), true);
		$this->assertIsArray($event, 'Event fixture is not valid JSON');
		return $event;
	}

	/**
	 * Verify the REST endpoint rejects anonymous access.
	 *
	 * Pass criteria:
	 * - GET on the calendar collection without auth returns HTTP 401.
	 */
	public function testNoAuth()
	{
		$response = $this->getClient([])->get($this->url($this->calendarCollection($this->user())), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);

		$this->assertHttpStatus(401, $response);
	}

	/**
	 * Verify authenticated collection discovery works (REST equivalent of PROPFIND).
	 *
	 * Pass criteria:
	 * - GET on the authenticated user's calendar collection with
	 *   "Accept: application/json" returns HTTP 200 and a JSON body.
	 */
	public function testAuth()
	{
		$response = $this->getEventResponse('', $this->user());

		$this->assertHttpStatus(200, $response);
		$this->assertIsArray($this->jsonDecode($response), 'Collection response is not valid JSON');
	}

	/**
	 * Create event from the JsEvent fixture payload.
	 *
	 * Setup:
	 * - Load fixture template and inject the test UID.
	 *
	 * Pass criteria:
	 * - POST returns HTTP 200 or 201.
	 * - Response carries a numeric id (Location header / ETag).
	 */
	public function testCreate()
	{
		$response = $this->postEvent($this->eventPayload(), $this->user());

		$this->assertHttpStatus([200, 201], $response);

		self::$cal_id = $this->idFromResponse($response);
		$this->assertNotEmpty(self::$cal_id, 'No id returned for created event');
		$this->assertTrue(is_numeric(self::$cal_id), 'Returned id is not numeric: '.self::$cal_id);
	}

	/**
	 * Read created event and verify the JsEvent payload parity.
	 *
	 * Pass criteria:
	 * - GET returns HTTP 200.
	 * - Returned JsEvent matches the fixture payload semantics.
	 */
	#[Depends('testCreate')]
	public function testRead()
	{
		$event = $this->getEventJson(self::$cal_id, $this->user());

		$this->assertEquals('Tonight', $event['title'] ?? null, 'Unexpected title');
		$this->assertEquals('2030-01-01T20:00:00', $event['start'] ?? null, 'Unexpected start');
		$this->assertEquals('Europe/Berlin', $event['timeZone'] ?? null, 'Unexpected timeZone');
		$this->assertEquals('PT1H', $event['duration'] ?? null, 'Unexpected duration');
		$this->assertEquals('Somewhere', $event['locations']['1']['name'] ?? null, 'Unexpected location');
	}

	/**
	 * Delete created event resource.
	 *
	 * Pass criteria:
	 * - DELETE returns HTTP 204.
	 */
	#[Depends('testCreate')]
	public function testDelete()
	{
		$response = $this->deleteEvent(self::$cal_id, $this->user());

		$this->assertHttpStatus(204, $response);
	}

	/**
	 * Verify deleted event is no longer readable.
	 *
	 * Pass criteria:
	 * - GET returns HTTP 404 after delete.
	 */
	#[Depends('testDelete')]
	public function testReadDeleted()
	{
		$response = $this->getEventResponse(self::$cal_id, $this->user());

		$this->assertHttpStatus(404, $response);
	}

	/**
	 * Delete of a non-existing event must return not-found.
	 *
	 * Setup:
	 * - Build a unique, never-created resource name in the authenticated user's
	 *   calendar collection.
	 *
	 * Pass criteria:
	 * - DELETE returns HTTP 404.
	 */
	public function testDeleteMissing()
	{
		$missing = $this->makeUid('rest-delete-missing');
		$response = $this->deleteEvent($missing, $this->user());

		$this->assertHttpStatus(404, $response);
	}
}
