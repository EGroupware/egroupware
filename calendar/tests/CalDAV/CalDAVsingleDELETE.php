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
class CalDAVsingleDELETE extends CalDAVTest
{
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

	/**
	 * Create some users incl. ACL
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		self::createUsersACL(self::$users, 'calendar');
	}

	/**
	 * Check created users
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
	const EVENT_BOSS_ATTENDEE_ICAL = <<<EOICAL
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20110406T210000
DTEND;TZID=Europe/Berlin:20110406T220000
DTSTAMP:20110406T183747Z
LAST-MODIFIED:20110406T183747Z
LOCATION:Somewhere
SUMMARY:Tonight
ORGANIZER;CN="Other User":mailto:other@example.org
ATTENDEE;CN="Other User";CUTYPE=INDIVIDUAL;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:other@example.org
ATTENDEE;CN="Boss User";CUTYPE=INDIVIDUAL;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT;RSVP=TRUE:mailto:boss@example.org
UID:new-event-boss-attendee-123456789-new
END:VEVENT
END:VCALENDAR
EOICAL;

	/**
	 * Check secretary deletes in boss's calendar event he is an attendee / invited
	 *
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testSecretaryDeletesBossAttendee()
	{
		// create invitation by organizer
		$response = $this->getClient('other')->put($this->url(self::EVENT_BOSS_ATTENDEE_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => self::EVENT_BOSS_ATTENDEE_ICAL,
		]);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal(self::EVENT_BOSS_ATTENDEE_ICAL, $response->getBody());

		// secretrary deletes event in boss's calendar
		$response = $this->getClient('secretary')->delete($this->url(self::EVENT_BOSS_ATTENDEE_URL));
		$this->assertHttpStatus(204, $response, 'Secretary delete/rejects for boss');

		// use organizer to check event still exists and boss rejected
		$response = $this->getClient('other')->get($this->url(self::EVENT_BOSS_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(200, $response, 'Check event still exists after DELETE in attendee calendar');
		$this->assertIcal(self::EVENT_BOSS_ATTENDEE_ICAL, $response->getBody(),
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
	const EVENT_BOSS_ORGANIZER_ICAL = <<<EOICAL
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20110406T210000
DTEND;TZID=Europe/Berlin:20110406T220000
DTSTAMP:20110406T183747Z
LAST-MODIFIED:20110406T183747Z
LOCATION:Somewhere
SUMMARY:Tonight
ORGANIZER;CN="Boss User":mailto:boss@example.org
ATTENDEE;CN="Boss User";CUTYPE=INDIVIDUAL;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:boss@example.org
ATTENDEE;CN="Other User";CUTYPE=INDIVIDUAL;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT;RSVP=TRUE:mailto:other@example.org
UID:new-event-boss-organizer-123456789-new
END:VEVENT
END:VCALENDAR
EOICAL;

	/**
	 * Check secretary deletes for boss, which is organizer of event
	 *
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testSecretaryDeletesBossOrganizer()
	{
		// create invitation by boss as organizer
		$response = $this->getClient('boss')->put($this->url(self::EVENT_BOSS_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => self::EVENT_BOSS_ORGANIZER_ICAL,
		]);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal(self::EVENT_BOSS_ORGANIZER_ICAL, $response->getBody());

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
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testOrganizerDeletes()
	{
		// create invitation by boss as organizer
		$response = $this->getClient('boss')->put($this->url(self::EVENT_BOSS_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => self::EVENT_BOSS_ORGANIZER_ICAL,
		]);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal(self::EVENT_BOSS_ORGANIZER_ICAL, $response->getBody());

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
	const EVENT_SECRETARY_ATTENDEE_ICAL = <<<EOICAL
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTART;TZID=Europe/Berlin:20110406T210000
DTEND;TZID=Europe/Berlin:20110406T220000
DTSTAMP:20110406T183747Z
LAST-MODIFIED:20110406T183747Z
LOCATION:Somewhere
SUMMARY:Tonight
ORGANIZER;CN="Boss User":mailto:boss@example.org
ATTENDEE;CN="Boss User";CUTYPE=INDIVIDUAL;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:boss@example.org
ATTENDEE;CN="Secretary User";CUTYPE=INDIVIDUAL;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT;RSVP=TRUE:mailto:secretary@example.org
UID:new-event-secreatary-attendee-123456789-new
END:VEVENT
END:VCALENDAR
EOICAL;

	/**
	 * Check secretary as attendee deletes event
	 *
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testSecretaryAttendeeDeletes()
	{
		// create invitation by boss as organizer
		$response = $this->getClient('boss')->put($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => self::EVENT_SECRETARY_ATTENDEE_ICAL,
		]);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal(self::EVENT_SECRETARY_ATTENDEE_ICAL, $response->getBody());

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
	 * @throws \Horde_Icalendar_Exception
	 */
	public function testSecretaryAttendeeDeletesCalDAVSynchronizer()
	{
		// create invitation by boss as organizer
		$response = $this->getClient('boss')->put($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation'
			],
			RequestOptions::BODY => self::EVENT_SECRETARY_ATTENDEE_ICAL,
		]);
		$this->assertHttpStatus([200,201], $response);
		$this->assertIcal(self::EVENT_SECRETARY_ATTENDEE_ICAL, $response->getBody());

		// secretary deletes in her calendar with CalDAVSynchronizer
		$response = $this->getClient('secretary')->delete($this->url(self::EVENT_SECRETARY_ATTENDEE_URL),
			[RequestOptions::HEADERS => ['User-Agent' => 'CalDAVSynchronizer']]);
		$this->assertHttpStatus(204, $response, 'Secretary (attendee) deletes/rejects');

		// use organizer to check it's NOT deleted, as CalDAVSynchronizer / Outlook does not distinguish between reject and delete
		$response = $this->getClient('boss')->get($this->url(self::EVENT_SECRETARY_ATTENDEE_ORGANIZER_URL));
		$this->assertHttpStatus(200, $response, "Check event NOT deleted by secretary");
		$this->assertIcal(self::EVENT_SECRETARY_ATTENDEE_ICAL, $response->getBody(),
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