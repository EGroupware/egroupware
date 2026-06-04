<?php
/**
 * REST API PUT precondition tests (ETag / Schedule-Tag).
 *
 * REST (JSON / JsCalendar) equivalent of
 * calendar/tests/CalDAV/PutPreconditionTest.php.
 *
 * The event is created with a POST of a JsEvent and afterwards updated with a
 * PUT to its numeric resource id, carrying the same "If-Match" /
 * "If-Schedule-Tag-Match" precondition headers as the CalDAV variant. The
 * precondition handling lives in the shared CalDAV request handler, so it
 * applies to REST PUT requests identically.
 *
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar\REST;

require_once __DIR__.'/../../../api/tests/RestBase.php';

use EGroupware\Api\RestBase;

class PutPreconditionTest extends RestBase
{
	protected const EVENT_FIXTURE = __DIR__.'/fixtures/create-read-delete-event.json.tpl';

	protected function ownerUser() : string
	{
		$this->assertNotEmpty($GLOBALS['EGW_USER'], 'EGW_USER must be configured for REST tests');
		return $GLOBALS['EGW_USER'];
	}

	/**
	 * Build a JsEvent payload from the fixture with a given UID and title.
	 */
	protected function eventPayload(string $uid, string $title) : array
	{
		$template = file_get_contents(self::EVENT_FIXTURE);
		$this->assertNotFalse($template, 'Unable to load event fixture');
		$event = json_decode(strtr($template, ['{{UID}}' => $uid]), true);
		$this->assertIsArray($event, 'Event fixture is not valid JSON');
		$event['title'] = $title;
		return $event;
	}

	/**
	 * Create an event via POST and return its numeric id.
	 */
	protected function createEvent(string $uid, string $title='Precondition Base')
	{
		$response = $this->postEvent($this->eventPayload($uid, $title), $this->ownerUser());
		$this->assertHttpStatus([200, 201], $response);
		$id = $this->idFromResponse($response);
		$this->assertNotEmpty($id, 'No id returned for created event');
		return $id;
	}

	/**
	 * GET the current title of an event.
	 */
	protected function currentTitle($id) : ?string
	{
		return $this->getEventJson($id, $this->ownerUser())['title'] ?? null;
	}

	/**
	 * Stale ETag precondition must block overwrite.
	 *
	 * Setup:
	 * - Create event.
	 * - Attempt PUT update with an intentionally stale "If-Match".
	 *
	 * Pass criteria:
	 * - PUT returns HTTP 412.
	 * - Event content remains unchanged.
	 */
	public function testPutWithStaleIfMatchReturns412()
	{
		$uid = $this->makeUid('calendar-rest-ifmatch-stale');
		$id = $this->createEvent($uid, 'IfMatch Base');

		$update = $this->putEventJson($id, $this->eventPayload($uid, 'IfMatch Updated'), $this->ownerUser(), [
			'If-Match' => '"stale-etag-value"',
		]);
		$this->assertHttpStatus(412, $update);

		$this->assertEquals('IfMatch Base', $this->currentTitle($id), 'Event must not have been updated');
	}

	/**
	 * Matching ETag precondition allows update.
	 *
	 * Setup:
	 * - Create event and fetch its current ETag from a GET.
	 * - PUT updated payload with the exact "If-Match" value.
	 *
	 * Pass criteria:
	 * - PUT returns success (200/204).
	 * - Subsequent GET contains the updated title.
	 */
	public function testPutWithMatchingIfMatchSucceeds()
	{
		$uid = $this->makeUid('calendar-rest-ifmatch-ok');
		$id = $this->createEvent($uid, 'IfMatch Success Base');

		$current = $this->getEventResponse($id, $this->ownerUser());
		$this->assertHttpStatus(200, $current);
		$etag = $current->getHeader('ETag')[0] ?? '';
		$this->assertNotEmpty($etag, 'Expected ETag header on GET response');

		$update = $this->putEventJson($id, $this->eventPayload($uid, 'IfMatch Success Updated'), $this->ownerUser(), [
			'If-Match' => $etag,
		]);
		$this->assertHttpStatus([200, 204], $update);

		$this->assertEquals('IfMatch Success Updated', $this->currentTitle($id), 'Event should have been updated');
	}

	/**
	 * Stale schedule-tag precondition must return 412.
	 *
	 * Setup:
	 * - Create event.
	 * - PUT updated payload with an intentionally stale "If-Schedule-Tag-Match".
	 *
	 * Pass criteria:
	 * - PUT returns HTTP 412.
	 * - Event content remains unchanged.
	 */
	public function testPutWithStaleScheduleTagReturns412()
	{
		$uid = $this->makeUid('calendar-rest-scheduletag-stale');
		$id = $this->createEvent($uid, 'ScheduleTag Base');

		$update = $this->putEventJson($id, $this->eventPayload($uid, 'ScheduleTag Updated'), $this->ownerUser(), [
			'If-Schedule-Tag-Match' => '"stale-schedule-tag"',
		]);
		$this->assertHttpStatus(412, $update);

		$this->assertEquals('ScheduleTag Base', $this->currentTitle($id), 'Event must not have been updated');
	}

	/**
	 * Event owner: If-Match takes precedence when both preconditions are present.
	 *
	 * Setup:
	 * - Create event and fetch its current ETag.
	 * - PUT update with a valid "If-Match" and a stale "If-Schedule-Tag-Match".
	 *
	 * Pass criteria:
	 * - Update succeeds (owner path ignores stale schedule-tag when both are sent).
	 * - Updated title is returned by a later GET.
	 */
	public function testOwnerIfMatchTakesPrecedenceOverScheduleTag()
	{
		$uid = $this->makeUid('calendar-rest-owner-header-precedence');
		$id = $this->createEvent($uid, 'Header Precedence Base');

		$current = $this->getEventResponse($id, $this->ownerUser());
		$this->assertHttpStatus(200, $current);
		$etag = $current->getHeader('ETag')[0] ?? '';
		$this->assertNotEmpty($etag, 'Expected ETag header on GET response');

		$update = $this->putEventJson($id, $this->eventPayload($uid, 'Header Precedence Updated'), $this->ownerUser(), [
			'If-Match' => $etag,
			'If-Schedule-Tag-Match' => '"stale-schedule-tag"',
		]);
		$this->assertHttpStatus([200, 204], $update);

		$this->assertEquals('Header Precedence Updated', $this->currentTitle($id), 'Event should have been updated');
	}
}
