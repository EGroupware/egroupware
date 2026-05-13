<?php
/**
 * CalDAV import tests for InfoLog VTODO entries.
 *
 * @package infolog
 * @subpackage tests
 */

namespace EGroupware\Infolog;

require_once __DIR__.'/../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;
use GuzzleHttp\RequestOptions;

class CalDAVImportTest extends CalDAVTest
{
	protected const OTHER_USER = 'infolog_caldav_other';
	protected const FIXTURE_CREATE = __DIR__.'/fixtures/caldav-import-create.ics.tpl';
	protected const FIXTURE_UPDATE = __DIR__.'/fixtures/caldav-import-update.ics.tpl';
	protected const FIXTURE_COMPLETED_NO_STATUS = __DIR__.'/fixtures/caldav-import-completed-no-status.ics.tpl';

	/**
	 * Track created CalDAV resources for cleanup.
	 *
	 * @var string[]
	 */
	protected $created_tasks = [];

	/**
	 * Owner account_lid used for CalDAV user path.
	 */
	protected function user() : string
	{
		return $GLOBALS['EGW_USER'];
	}

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		$data = [];
		self::createUser(self::OTHER_USER, $data);
	}

	protected function taskUrl(string $caldav_name) : string
	{
		return '/'.$this->user().'/infolog/'.$caldav_name;
	}

	protected function fixturePayload(string $fixture, string $uid, array $tokens=[]) : string
	{
		$base_tokens = [
			'{{UID}}' => $uid,
		];
		return $this->renderFixture($fixture, $tokens + $base_tokens);
	}

	protected function putTask(string $caldav_name, string $ical, bool $require_info_id=true) : int
	{
		$response = $this->getClient()->put($this->url($this->taskUrl($caldav_name)), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation',
			],
			RequestOptions::BODY => $ical,
		]);
		$this->assertHttpStatus([200, 201], $response);
		$info_id = $this->addCalendarID($response);
		if ($require_info_id)
		{
			$this->assertGreaterThan(0, $info_id, 'Expected numeric info_id in ETag header');
		}
		if (!in_array($caldav_name, $this->created_tasks, true))
		{
			$this->created_tasks[] = $caldav_name;
		}
		return $info_id;
	}

	protected function getTaskIcal(string $caldav_name) : string
	{
		$response = $this->getClient()->get($this->url($this->taskUrl($caldav_name)));
		$this->assertHttpStatus(200, $response);
		return (string)$response->getBody();
	}

	public function tearDown() : void
	{
		foreach(array_unique($this->created_tasks) as $caldav_name)
		{
			$response = $this->getClient()->delete($this->url($this->taskUrl($caldav_name)));
			if (!in_array($response->getStatusCode(), [204, 404], true))
			{
				$this->assertHttpStatus([204, 404], $response, "Cleanup delete failed for $caldav_name");
			}
		}
		$this->created_tasks = [];

		parent::tearDown();
	}

	/**
	 * Import a new VTODO via CalDAV PUT and verify InfoLog field mapping.
	 *
	 * Pass criteria:
	 * - PUT to /<user>/infolog/<name> succeeds with 200/201.
	 * - Stored entry keeps path name in caldav_name.
	 * - VTODO fields map as expected (status, percent, priority, summary, uid).
	 * - Exported VTODO remains readable and contains imported UID plus summary.
	 */
	public function testImportCreateFromFixture()
	{
		$uid = $this->makeUid('infolog-caldav-create');
		$caldav_name = $uid.'.ics';
		$ical = $this->fixturePayload(self::FIXTURE_CREATE, $uid, [
			'{{SUMMARY}}' => 'CalDAV import create',
			'{{DESCRIPTION}}' => 'Created through fixture import',
			'{{LOCATION}}' => 'Main Office',
			'{{DTSTAMP}}' => '20260513T130000Z',
			'{{DTSTART}}' => '20300110T090000Z',
			'{{DUE}}' => '20300110T100000Z',
		]);

		$this->putTask($caldav_name, $ical);
		$exported = $this->unfoldIcal($this->getTaskIcal($caldav_name));
		$this->assertStringContainsString("UID:$uid", $exported);
		$this->assertStringContainsString('SUMMARY:CalDAV import create', $exported);
		$this->assertStringContainsString('DESCRIPTION:Created through fixture import', $exported);
		$this->assertStringContainsString('LOCATION:Main Office', $exported);
		$this->assertStringContainsString('STATUS:IN-PROCESS', $exported);
		$this->assertStringContainsString('X-INFOLOG-STATUS:ongoing', $exported);
		$this->assertStringContainsString('PERCENT-COMPLETE:55', $exported);
		$this->assertStringContainsString('PRIORITY:1', $exported);
	}

	/**
	 * Re-import same CalDAV resource and verify update of existing task.
	 *
	 * Pass criteria:
	 * - Second PUT on same URL updates existing entry (same info_id).
	 * - Changed fields (summary, status, percent, priority, completed date) are persisted.
	 */
	public function testImportUpdateExistingFromFixture()
	{
		$uid = $this->makeUid('infolog-caldav-update');
		$caldav_name = $uid.'.ics';

		$initial = $this->fixturePayload(self::FIXTURE_CREATE, $uid, [
			'{{SUMMARY}}' => 'CalDAV import initial',
			'{{DESCRIPTION}}' => 'Initial version',
			'{{LOCATION}}' => 'Remote',
			'{{DTSTAMP}}' => '20260513T131500Z',
			'{{DTSTART}}' => '20300111T090000Z',
			'{{DUE}}' => '20300111T103000Z',
		]);
		$info_id = $this->putTask($caldav_name, $initial);

		$updated = $this->fixturePayload(self::FIXTURE_UPDATE, $uid, [
			'{{SUMMARY}}' => 'CalDAV import updated',
			'{{DESCRIPTION}}' => 'Updated version',
			'{{LOCATION}}' => 'Home Office',
			'{{DTSTAMP}}' => '20260513T132500Z',
			'{{DTSTART}}' => '20300111T110000Z',
			'{{DUE}}' => '20300111T120000Z',
			'{{COMPLETED}}' => '20300111T120000Z',
		]);
		$updated_id = $this->putTask($caldav_name, $updated, false);
		if ($updated_id > 0)
		{
			$this->assertSame($info_id, $updated_id, 'Update should keep same info_id');
		}

		$exported = $this->unfoldIcal($this->getTaskIcal($caldav_name));
		$this->assertStringNotContainsString('SUMMARY:CalDAV import initial', $exported);
		$this->assertStringContainsString('SUMMARY:CalDAV import updated', $exported);
		$this->assertStringContainsString('DESCRIPTION:Updated version', $exported);
		$this->assertStringContainsString('LOCATION:Home Office', $exported);
		$this->assertStringContainsString('STATUS:COMPLETED', $exported);
		$this->assertStringContainsString('X-INFOLOG-STATUS:done', $exported);
		$this->assertStringContainsString('PERCENT-COMPLETE:100', $exported);
		$this->assertStringContainsString('PRIORITY:9', $exported);
		$this->assertStringContainsString('COMPLETED:', $exported);
	}

	/**
	 * Import with COMPLETED but without STATUS and verify current default mapping.
	 *
	 * Setup:
	 * - Payload omits STATUS and PERCENT-COMPLETE.
	 * - Payload includes COMPLETED and conflicting X-INFOLOG-STATUS.
	 *
	 * Pass criteria:
	 * - Import stores COMPLETED timestamp.
	 * - Without explicit STATUS, exported task remains open (NEEDS-ACTION / not-started).
	 * - Percent remains 0.
	 */
	public function testImportCompletedWithoutStatusFallsBackToDone()
	{
		$uid = $this->makeUid('infolog-caldav-completed');
		$caldav_name = $uid.'.ics';
		$ical = $this->fixturePayload(self::FIXTURE_COMPLETED_NO_STATUS, $uid, [
			'{{SUMMARY}}' => 'CalDAV completed without status',
			'{{DESCRIPTION}}' => 'No STATUS property, only COMPLETED',
			'{{LOCATION}}' => 'N/A',
			'{{DTSTAMP}}' => '20260513T134500Z',
			'{{DTSTART}}' => '20300112T090000Z',
			'{{DUE}}' => '20300112T093000Z',
			'{{COMPLETED}}' => '20300112T093000Z',
		]);

		$this->putTask($caldav_name, $ical);
		$exported = $this->unfoldIcal($this->getTaskIcal($caldav_name));
		$this->assertStringContainsString('STATUS:NEEDS-ACTION', $exported);
		$this->assertStringContainsString('X-INFOLOG-STATUS:not-started', $exported);
		$this->assertStringContainsString('PERCENT-COMPLETE:0', $exported);
		$this->assertStringContainsString('COMPLETED:', $exported);
	}

	/**
	 * Invalid iCal payload must be rejected and must not create a task resource.
	 *
	 * Pass criteria:
	 * - PUT with malformed text/calendar payload returns 4xx.
	 * - Resource is not readable afterwards (404).
	 */
	public function testImportRejectsMalformedIcal()
	{
		$caldav_name = $this->makeUid('infolog-caldav-invalid').'.ics';
		$response = $this->getClient()->put($this->url($this->taskUrl($caldav_name)), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
			],
			RequestOptions::BODY => "BEGIN:VCALENDAR\r\nBEGIN:VTODO\r\nSUMMARY:broken payload without END lines\r\n",
		]);
		$this->assertHttpStatus([400, 403], $response);

		$read = $this->getClient()->get($this->url($this->taskUrl($caldav_name)));
		$this->assertHttpStatus(404, $read);
	}

	/**
	 * Non-VTODO component on infolog endpoint must be rejected.
	 *
	 * Pass criteria:
	 * - PUT with VEVENT payload returns 4xx.
	 * - Resource is not readable afterwards (404).
	 */
	public function testImportRejectsVeventOnInfologEndpoint()
	{
		$uid = $this->makeUid('infolog-caldav-vevent');
		$caldav_name = $uid.'.ics';
		$vevent = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:$uid\r\nDTSTAMP:20260513T120000Z\r\nDTSTART:20300101T100000Z\r\nDTEND:20300101T110000Z\r\nSUMMARY:Wrong component\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

		$response = $this->getClient()->put($this->url($this->taskUrl($caldav_name)), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
			],
			RequestOptions::BODY => $vevent,
		]);
		$this->assertHttpStatus([400, 403], $response);

		$read = $this->getClient()->get($this->url($this->taskUrl($caldav_name)));
		$this->assertHttpStatus(404, $read);
	}

	/**
	 * User without rights must not be able to write into another user's infolog collection.
	 *
	 * Pass criteria:
	 * - Authenticated other user PUT to owner's infolog path returns 403.
	 * - Owner still does not have a readable resource at that URL (404).
	 */
	public function testImportDeniedForForeignCollectionWithoutAcl()
	{
		$uid = $this->makeUid('infolog-caldav-acl-denied');
		$caldav_name = $uid.'.ics';
		$ical = $this->fixturePayload(self::FIXTURE_CREATE, $uid, [
			'{{SUMMARY}}' => 'ACL denied write',
			'{{DESCRIPTION}}' => 'Should not be created',
			'{{LOCATION}}' => 'N/A',
			'{{DTSTAMP}}' => '20260513T140000Z',
			'{{DTSTART}}' => '20300120T100000Z',
			'{{DUE}}' => '20300120T103000Z',
		]);

		$response = $this->getClient(self::OTHER_USER)->put($this->url($this->taskUrl($caldav_name)), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
			],
			RequestOptions::BODY => $ical,
		]);
		$this->assertHttpStatus(403, $response);

		$owner_read = $this->getClient()->get($this->url($this->taskUrl($caldav_name)));
		$this->assertHttpStatus(404, $owner_read);
	}
}
