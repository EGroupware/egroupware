<?php
/**
 * CalDAV tests: DELETE requests for non-series by Outlook CalDAV Synchronizer and other clients
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @subpackage tests
 * @copyright (c) 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once __DIR__.'/../../../api/tests/CalDAVTest.php';

use EGroupware\Api\CalDAVTest;
use GuzzleHttp\RequestOptions;
use EGroupware\Api\Acl;

/**
 * Class CalDAVsingleDELETE
 *
 * This tests check all sorts of DELETE requests by organizer and attendees, with and without (delete) rights on the organizer.
 *
 * For CalDAV Synchronizer, which does not distingues between deleting and rejecting events, we only allow the
 * organizer to delete an event.
 *
 * @package EGroupware\calendar
 * @covers \calendar_groupdav::delete()
 * @uses   \calendar_groupdav::put()
 * @uses   \calendar_groupdav::get()
 */
class SingleDeleteTest extends CalDAVTest
{
	protected const FIXTURE_BOSS_ATTENDEE = __DIR__.'/fixtures/single-delete-boss-attendee.ics';
	protected const FIXTURE_BOSS_ORGANIZER = __DIR__.'/fixtures/single-delete-boss-organizer.ics';
	protected const FIXTURE_SECRETARY_ATTENDEE = __DIR__.'/fixtures/single-delete-secretary-attendee.ics';

	/**
	 * Users and their ACL for the test
	 *
	 * @var array
	 */
	protected static $users = [
		'boss' => [],
		'secretary' => [
			'rights'    => [
				'boss'  => Acl::READ|Acl::ADD|Acl::EDIT|Acl::DELETE,
			]
		],
		'other' => [],
	];

	// Keep track so we can be sure to clean up
	protected $cal_ids = [];
	/**
	 * Create some users incl. ACL
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		self::createUsersACL(self::$users, 'calendar');
	}

	public function tearDown() : void
	{
		parent::tearDown();
		$so = new \calendar_so();
		foreach($this->cal_ids as $cal_id)
		{
			$so->delete($cal_id);
		}
		$this->cal_ids = [];
	}

	protected function addCalendarID($response) : int
	{
		$array = explode(":", trim(($response->getHeader('ETag')[0] ?? ""), '[]"') ?? "");
		if(count($array) && $array[0])
		{
			$cal_id = (int)$array[0];
			$this->cal_ids[] = $cal_id;
			return $cal_id;
		}
		return 0;
	}

	protected function fixtureIcal(string $fixture_file) : string
	{
		$ical = file_get_contents($fixture_file);
		$this->assertNotFalse($ical, "Unable to load fixture $fixture_file");
		return $ical;
	}

	/**
	 * Verify test principals are reachable after fixture setup.
	 *
	 * Pass criteria:
	 * - Each configured user principal returns HTTP 207 on PROPFIND.
	 */
	public function testPrincipals()
	{
		foreach(self::$users as $user => &$data)
		{
			$response = $this->getClient()->propfind($this->url('/principals/users/'.$user.'/'), [
				RequestOptions::HEADERS => [
					'Depth' => 0,
				],
			]);
			$this->assertHttpStatus(207, $response);
		}
	}

	const EVENT_BOSS_ATTENDEE_ORGANIZER_URL = '/other/calendar/new-event-boss-attendee-123456789-new.ics';
	const EVENT_BOSS_ATTENDEE_URL = '/boss/calendar/new-event-boss-attendee-123456789-new.ics';

	/**
	 * Check secretary deletes in boss's calendar event he is an attendee / invited
	 *
	 * Setup:
	 * - Create event where organizer is "other" and boss is attendee.
	 *
	 * Pass criteria:
	 * - Secretary deleting in boss attendee calendar rejects/declines (204) without removing organizer copy.
	 * - Secretary cannot delete organizer copy (403).
	 * - Boss can delete/reject in both attendee and organizer views (204).
	 * - Organizer can finally delete event and GET then returns 404.
	 *
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testSecretaryDeletesBossAttendee()
	{
		$event_ical = $this->fixtureIcal(self::FIXTURE_BOSS_ATTENDEE);
		// create invitation by organizer
		$response = $this->getClient('other')->put($this->url(self::EVENT_BOSS_ATTENDEE_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => $event_ical,
		]);
		$this->addCalendarID($response);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal($event_ical, $response->getBody());

		// secretrary deletes event in boss's calendar
		$response = $this->getClient('secretary')->delete($this->url(self::EVENT_BOSS_ATTENDEE_URL));
		$this->assertHttpStatus(204, $response, 'Secretary delete/rejects for boss');

		// use organizer to check event still exists and boss rejected
		$response = $this->getClient('other')->get($this->url(self::EVENT_BOSS_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(200, $response, 'Check event still exists after DELETE in attendee calendar');
		$this->assertIcal($event_ical, $response->getBody(),
			'Boss should have declined the invitation',
			['vEvent' => [['ATTENDEE' => ['mailto:boss@example.org' => ['PARTSTAT' => 'DECLINED', 'RSVP' => 'FALSE']]]]]
		);

		// secretary tries to delete event in organizers calendar
		$response = $this->getClient('secretary')->delete($this->url(self::EVENT_BOSS_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(403, $response, 'Secretary not allowed to delete for organizer');

		// boss deletes/rejects event in his calendar
		$response = $this->getClient('boss')->delete($this->url(self::EVENT_BOSS_ATTENDEE_URL));
		$this->assertHttpStatus(204, $response, 'Boss deletes/rejects in his calendar');

		// boss deletes/rejects event in organizers calendar
		$response = $this->getClient('boss')->delete($this->url(self::EVENT_BOSS_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(204, $response, 'Boss deletes/rejects in organizers calendar');

		// use organizer to delete event
		$response = $this->getClient('other')->delete($this->url(self::EVENT_BOSS_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(204, $response);

		// use organizer to check event deleted
		$response = $this->getClient('other')->get($this->url(self::EVENT_BOSS_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(404, $response, "Check event deleted by organizer");
	}

	const EVENT_BOSS_ORGANIZER_URL = '/boss/calendar/new-event-boss-organizer-123456789-new.ics';
	const EVENT_BOSS_ORGANIZER_OTHER_URL = '/other/calendar/new-event-boss-organizer-123456789-new.ics';

	/**
	 * Check secretary deletes for boss, which is organizer of event
	 *
	 * Setup:
	 * - Create event where boss is organizer and other is attendee.
	 *
	 * Pass criteria:
	 * - Secretary (with delegated rights) can delete organizer event (204).
	 * - Organizer copy is gone afterwards (404).
	 *
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testSecretaryDeletesBossOrganizer()
	{
		$event_ical = $this->fixtureIcal(self::FIXTURE_BOSS_ORGANIZER);
		// create invitation by boss as organizer
		$response = $this->getClient('boss')->put($this->url(self::EVENT_BOSS_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => $event_ical,
		]);
		$this->addCalendarID($response);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal($event_ical, $response->getBody());

		// attendee deletes/rejects event in his calendar
		$response = $this->getClient('other')->delete($this->url(self::EVENT_BOSS_ORGANIZER_OTHER_URL));
		$this->assertHttpStatus(204, $response);

		// secretrary deletes event in boss's calendar
		$response = $this->getClient('secretary')->delete($this->url(self::EVENT_BOSS_ORGANIZER_URL));
		$this->assertHttpStatus(204, $response, 'Secretary deletes for boss');

		// use organizer/boss to check event deleted
		$response = $this->getClient('boss')->get($this->url(self::EVENT_BOSS_ORGANIZER_URL));
		$this->assertHttpStatus(404, $response, "Check event deleted by secretary");
	}

	/**
	 * Check organizer (boss) can delete event in his calendar
	 *
	 * Setup:
	 * - Create event where boss is organizer and other is attendee.
	 *
	 * Pass criteria:
	 * - Organizer delete returns 204.
	 * - Event is removed for both organizer and attendee views (404).
	 *
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testOrganizerDeletes()
	{
		$event_ical = $this->fixtureIcal(self::FIXTURE_BOSS_ORGANIZER);
		// create invitation by boss as organizer
		$response = $this->getClient('boss')->put($this->url(self::EVENT_BOSS_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => $event_ical,
		]);
		$this->addCalendarID($response);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal($event_ical, $response->getBody());

		// organizer deletes event in his calendar
		$response = $this->getClient('boss')->delete($this->url(self::EVENT_BOSS_ORGANIZER_URL));
		$this->assertHttpStatus(204, $response, 'Organizer deletes');

		// use organizer/boss to check event deleted
		$response = $this->getClient('boss')->get($this->url(self::EVENT_BOSS_ORGANIZER_URL));
		$this->assertHttpStatus(404, $response, "Check event deleted by organizer");

		// use attendee to check event deleted
		$response = $this->getClient('other')->get($this->url(self::EVENT_BOSS_ORGANIZER_OTHER_URL));
		$this->assertHttpStatus(404, $response, "Check event deleted by organizer");
	}

	const EVENT_SECRETARY_ATTENDEE_URL = '/secretary/calendar/new-event-secreatary-attendee-123456789-new.ics';
	const EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL = '/boss/calendar/new-event-secreatary-attendee-123456789-new.ics';

	/**
	 * Check secretary as attendee deletes event
	 *
	 * Setup:
	 * - Create event where boss is organizer and secretary is attendee.
	 *
	 * Pass criteria:
	 * - Secretary delete in attendee calendar returns 204.
	 * - Organizer copy is deleted (404).
	 *
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testSecretaryAttendeeDeletes()
	{
		$event_ical = $this->fixtureIcal(self::FIXTURE_SECRETARY_ATTENDEE);
		// create invitation by boss as organizer
		$response = $this->getClient('boss')->put($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => $event_ical,
		]);
		$this->addCalendarID($response);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal($event_ical, $response->getBody());

		// secretary deletes in her calendar
		$response = $this->getClient('secretary')->delete($this->url(self::EVENT_SECRETARY_ATTENDEE_URL));
		$this->assertHttpStatus(204, $response, 'Secretary (attendee) deletes');

		// use organizer to check it's really deleted
		$response = $this->getClient('boss')->get($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(404, $response, "Check event deleted by secretary");
	}

	/**
	 * Check secretary as attendee deletes event with CalDAVSynchronizer
	 *
	 * Setup:
	 * - Create event where boss is organizer and secretary is attendee.
	 * - Execute delete with `User-Agent: CalDAVSynchronizer`.
	 *
	 * Pass criteria:
	 * - Attendee delete/reject returns 204 but organizer copy remains (200) with secretary declined.
	 * - Organizer delete with same user-agent still deletes event (final 404).
	 *
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testSecretaryAttendeeDeletesCalDAVSynchronizer()
	{
		$event_ical = $this->fixtureIcal(self::FIXTURE_SECRETARY_ATTENDEE);
		// create invitation by boss as organizer
		$response = $this->getClient('boss')->put($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => $event_ical,
		]);
		$this->addCalendarID($response);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal($event_ical, $response->getBody());

		// secretary deletes in her calendar with CalDAVSynchronizer
		$response = $this->getClient('secretary')->delete($this->url(self::EVENT_SECRETARY_ATTENDEE_URL),
			[RequestOptions::HEADERS => ['User-Agent' => 'CalDAVSynchronizer']]);
		$this->assertHttpStatus(204, $response, 'Secretary (attendee) deletes/rejects');

		// use organizer to check it's NOT deleted, as CalDAVSynchronizer / Outlook does not distinguish between reject and delete
		$response = $this->getClient('boss')->get($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(200, $response, "Check event NOT deleted by secretary");
		$this->assertIcal($event_ical, $response->getBody(),
			'Secretary should have declined the invitation',
			['vEvent' => [['ATTENDEE' => ['mailto:secretary@example.org' => ['PARTSTAT' => 'DECLINED', 'RSVP' => 'FALSE']]]]]
		);

		// organizer deletes in his calendar with CalDAVSynchronizer
		$response = $this->getClient('boss')->delete($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL),
			[RequestOptions::HEADERS => ['User-Agent' => 'CalDAVSynchronizer']]);
		$this->assertHttpStatus(204, $response, 'Organizer deletes');

		// use organizer to check it's deleted, as CalDAVSynchronizer / Outlook should still delete for organizer
		$response = $this->getClient('boss')->get($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(404, $response, "Check event deleted by organizer");
	}
}
