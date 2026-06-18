<?php
/**
 * REST API validation and ACL tests.
 *
 * REST (JSON / JsCalendar) equivalent of
 * calendar/tests/CalDAV/PutValidationAclTest.php.
 *
 * Instead of a malformed iCalendar PUT and a foreign-calendar PUT, this uses a
 * malformed JSON POST and a foreign-calendar POST against the REST API.
 *
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar\REST;

require_once __DIR__.'/../../../api/tests/RestBase.php';

use EGroupware\Api\RestBase;
use GuzzleHttp\RequestOptions;

class PutValidationAclTest extends RestBase
{
	protected const OTHER_USER = 'calendar_rest_other';
	protected const EVENT_FIXTURE = __DIR__.'/fixtures/create-read-delete-event.json.tpl';

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

	/**
	 * Build a valid JsEvent payload from the fixture, with a unique title to find it again.
	 */
	protected function eventPayload(string $uid) : array
	{
		$template = file_get_contents(self::EVENT_FIXTURE);
		$this->assertNotFalse($template, 'Unable to load event fixture');
		$event = json_decode(strtr($template, ['{{UID}}' => $uid]), true);
		$this->assertIsArray($event, 'Event fixture is not valid JSON');
		$event['title'] = $uid;
		return $event;
	}

	/**
	 * Malformed JSON payload on the calendar collection must be rejected.
	 *
	 * Setup:
	 * - POST a syntactically broken JSON body to the user's calendar collection.
	 *
	 * Pass criteria:
	 * - POST returns a client error (400 / 415 / 422); the broken payload is not
	 *   silently stored.
	 */
	public function testPostRejectsMalformedJson()
	{
		$response = $this->getClient($this->ownerUser())->post($this->url($this->calendarCollection($this->ownerUser())), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			// truncated / invalid JSON
			RequestOptions::BODY => '{ "@type": "Event", "title": "broken payload", "start": ',
		]);

		$this->assertHttpStatus([400, 415, 422], $response);
	}

	/**
	 * User without ACL must not be able to create in another user's calendar.
	 *
	 * Setup:
	 * - Create a secondary authenticated user without delegated calendar rights.
	 * - POST a valid event into the owner's calendar collection as that user.
	 *
	 * Pass criteria:
	 * - Foreign POST is denied with HTTP 403.
	 * - The event does not show up in the owner's calendar: a collection GET
	 *   over the event's time-range does not return its unique title.
	 */
	public function testPostDeniedForForeignCalendarWithoutAcl()
	{
		$uid = $this->makeUid('calendar-rest-acl-denied');

		$response = $this->getClient(self::OTHER_USER)->post($this->url($this->calendarCollection($this->ownerUser())), [
			RequestOptions::HEADERS => $this->jsonHeaders(['Prefer' => 'return=representation']),
			RequestOptions::BODY => $this->jsonBody($this->eventPayload($uid)),
		]);
		$this->assertHttpStatus(403, $response);

		// owner must not see an event with that unique title; query the time-range
		// the fixture event would fall into (start 2030-01-01T20:00 Europe/Berlin)
		$owner_collection = $this->getClient($this->ownerUser())->get($this->url($this->calendarCollection($this->ownerUser())), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::QUERY => ['filters' => ['start' => '20300101T000000Z', 'end' => '20300102T000000Z']],
		]);
		$this->assertHttpStatus(200, $owner_collection);
		$titles = array_column(array_values($this->jsonDecode($owner_collection)['responses'] ?? []), 'title');
		$this->assertNotContains($uid, $titles, 'Foreign event must not have been created in the owner calendar');
	}
}
