<?php
/**
 * CalDAV PUT validation and ACL tests.
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once __DIR__.'/../../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;
use GuzzleHttp\RequestOptions;

class PutValidationAclTest extends CalDAVTest
{
	protected const OTHER_USER = 'calendar_caldav_other';
	protected const EVENT_FIXTURE = __DIR__.'/fixtures/create-read-delete-event.ics.tpl';

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		$data = [];
		self::createUser(self::OTHER_USER, $data);
	}

	protected function ownerUser() : string
	{
		$this->assertNotEmpty($GLOBALS['EGW_USER'], 'EGW_USER must be configured for CalDAV tests');
		return $GLOBALS['EGW_USER'];
	}

	protected function eventIcal(string $uid) : string
	{
		$template = file_get_contents(self::EVENT_FIXTURE);
		$this->assertNotFalse($template, 'Unable to load event fixture');
		return strtr($template, ['{{UID}}' => $uid]);
	}

	/**
	 * Malformed iCalendar payload on calendar endpoint must be rejected.
	 *
	 * Setup:
	 * - PUT broken text/calendar payload to a unique calendar resource.
	 * - Try reading same resource afterwards.
	 *
	 * Pass criteria:
	 * - PUT returns either explicit failure (400/403) or no-op success (204)
	 *   for malformed payload that is ignored by importer.
	 * - Resource remains absent and GET returns 404.
	 */
	public function testPutRejectsMalformedIcal()
	{
		$uid = $this->makeUid('calendar-caldav-invalid');
		$path = $this->eventUrlFor($this->ownerUser(), $uid);
		$response = $this->getClient()->put($this->url($path), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
			],
			RequestOptions::BODY => "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nSUMMARY:broken payload without END lines\r\n",
		]);
		$this->assertHttpStatus([204, 400, 403], $response);

		$read = $this->getClient()->get($this->url($path));
		$this->assertHttpStatus(404, $read);
	}

	/**
	 * User without ACL must not be able to write into another user's calendar.
	 *
	 * Setup:
	 * - Create a secondary authenticated user without delegated calendar rights.
	 * - PUT a valid event into owner's calendar path using secondary user.
	 *
	 * Pass criteria:
	 * - Foreign PUT is denied with HTTP 403.
	 * - Owner does not see created resource afterwards (GET 404).
	 */
	public function testPutDeniedForForeignCalendarWithoutAcl()
	{
		$uid = $this->makeUid('calendar-caldav-acl-denied');
		$path = $this->eventUrlFor($this->ownerUser(), $uid);
		$response = $this->getClient(self::OTHER_USER)->put($this->url($path), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
			],
			RequestOptions::BODY => $this->eventIcal($uid),
		]);
		$this->assertHttpStatus(403, $response);

		$owner_read = $this->getClient()->get($this->url($path));
		$this->assertHttpStatus(404, $owner_read);
	}
}
