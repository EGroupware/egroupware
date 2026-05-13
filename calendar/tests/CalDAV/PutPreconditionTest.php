<?php
/**
 * CalDAV PUT precondition tests (ETag / Schedule-Tag).
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once __DIR__.'/../../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;
use GuzzleHttp\RequestOptions;

class PutPreconditionTest extends CalDAVTest
{
	protected const EVENT_FIXTURE = __DIR__.'/fixtures/create-read-delete-event.ics.tpl';

	protected function ownerUser() : string
	{
		$this->assertNotEmpty($GLOBALS['EGW_USER'], 'EGW_USER must be configured for CalDAV tests');
		return $GLOBALS['EGW_USER'];
	}

	protected function eventIcal(string $uid, string $summary) : string
	{
		$template = file_get_contents(self::EVENT_FIXTURE);
		$this->assertNotFalse($template, 'Unable to load event fixture');
		$ical = strtr($template, ['{{UID}}' => $uid]);
		return str_replace('SUMMARY:Tonight', 'SUMMARY:'.$summary, $ical);
	}

	protected function putEventRaw(string $path, string $ical, array $headers=[])
	{
		return $this->getClient()->put($this->url($path), [
			RequestOptions::HEADERS => array_merge([
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation',
			], $headers),
			RequestOptions::BODY => $ical,
		]);
	}

	protected function createEvent(string $uid, string $summary='Precondition Base') : string
	{
		$path = $this->eventUrlFor($this->ownerUser(), $uid);
		$response = $this->putEventRaw($path, $this->eventIcal($uid, $summary));
		$this->assertHttpStatus([200, 201], $response);
		$this->addCalendarID($response);
		return $path;
	}

	protected function getEventResponse(string $path)
	{
		$response = $this->getClient()->get($this->url($path));
		$this->assertHttpStatus(200, $response);
		return $response;
	}

	/**
	 * Stale ETag precondition must block overwrite.
	 *
	 * Setup:
	 * - Create event and capture current ETag.
	 * - Attempt PUT update with intentionally stale `If-Match`.
	 *
	 * Pass criteria:
	 * - PUT returns HTTP 412.
	 * - Event content remains unchanged.
	 */
	public function testPutWithStaleIfMatchReturns412()
	{
		$uid = $this->makeUid('calendar-caldav-ifmatch-stale');
		$path = $this->createEvent($uid, 'IfMatch Base');
		$current = $this->getEventResponse($path);
		$this->assertNotEmpty($current->getHeader('ETag'), 'Expected ETag header on GET response');

		$update = $this->putEventRaw($path, $this->eventIcal($uid, 'IfMatch Updated'), [
			'If-Match' => '"stale-etag-value"',
		]);
		$this->assertHttpStatus(412, $update);

		$after = (string)$this->getEventResponse($path)->getBody();
		$this->assertStringContainsString('SUMMARY:IfMatch Base', $after);
		$this->assertStringNotContainsString('SUMMARY:IfMatch Updated', $after);
	}

	/**
	 * Matching ETag precondition allows update.
	 *
	 * Setup:
	 * - Create event and fetch current ETag from GET.
	 * - PUT updated payload with exact `If-Match` value.
	 *
	 * Pass criteria:
	 * - PUT returns success (200/204).
	 * - Subsequent GET contains updated summary.
	 */
	public function testPutWithMatchingIfMatchSucceeds()
	{
		$uid = $this->makeUid('calendar-caldav-ifmatch-ok');
		$path = $this->createEvent($uid, 'IfMatch Success Base');
		$current = $this->getEventResponse($path);
		$etag = $current->getHeader('ETag')[0] ?? '';
		$this->assertNotEmpty($etag, 'Expected ETag header on GET response');

		$update = $this->putEventRaw($path, $this->eventIcal($uid, 'IfMatch Success Updated'), [
			'If-Match' => $etag,
		]);
		$this->assertHttpStatus([200, 204], $update);

		$after = (string)$this->getEventResponse($path)->getBody();
		$this->assertStringContainsString('SUMMARY:IfMatch Success Updated', $after);
	}

	/**
	 * Stale schedule-tag precondition must return 412.
	 *
	 * Setup:
	 * - Create event and fetch current Schedule-Tag.
	 * - PUT updated payload with intentionally stale `If-Schedule-Tag-Match`.
	 *
	 * Pass criteria:
	 * - PUT returns HTTP 412.
	 * - Event content remains unchanged.
	 */
	public function testPutWithStaleScheduleTagReturns412()
	{
		$uid = $this->makeUid('calendar-caldav-scheduletag-stale');
		$path = $this->createEvent($uid, 'ScheduleTag Base');
		$current = $this->getEventResponse($path);
		$schedule_tag = $current->getHeader('Schedule-Tag')[0] ?? '';
		$this->assertNotEmpty($schedule_tag, 'Expected Schedule-Tag header on GET response');

		$update = $this->putEventRaw($path, $this->eventIcal($uid, 'ScheduleTag Updated'), [
			'If-Schedule-Tag-Match' => '"stale-schedule-tag"',
		]);
		$this->assertHttpStatus(412, $update);

		$after = (string)$this->getEventResponse($path)->getBody();
		$this->assertStringContainsString('SUMMARY:ScheduleTag Base', $after);
		$this->assertStringNotContainsString('SUMMARY:ScheduleTag Updated', $after);
	}

	/**
	 * Event owner: If-Match takes precedence when both preconditions are present.
	 *
	 * Setup:
	 * - Create event and fetch current ETag.
	 * - PUT update with valid `If-Match` and stale `If-Schedule-Tag-Match`.
	 *
	 * Pass criteria:
	 * - Update succeeds (owner path ignores stale schedule-tag when both are sent).
	 * - Updated summary is returned by later GET.
	 */
	public function testOwnerIfMatchTakesPrecedenceOverScheduleTag()
	{
		$uid = $this->makeUid('calendar-caldav-owner-header-precedence');
		$path = $this->createEvent($uid, 'Header Precedence Base');
		$current = $this->getEventResponse($path);
		$etag = $current->getHeader('ETag')[0] ?? '';
		$this->assertNotEmpty($etag, 'Expected ETag header on GET response');

		$update = $this->putEventRaw($path, $this->eventIcal($uid, 'Header Precedence Updated'), [
			'If-Match' => $etag,
			'If-Schedule-Tag-Match' => '"stale-schedule-tag"',
		]);
		$this->assertHttpStatus([200, 204], $update);

		$after = (string)$this->getEventResponse($path)->getBody();
		$this->assertStringContainsString('SUMMARY:Header Precedence Updated', $after);
	}
}
