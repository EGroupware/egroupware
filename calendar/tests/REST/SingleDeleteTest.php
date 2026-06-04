<?php
/**
 * REST API tests: DELETE requests for non-series events by organizer and attendees.
 *
 * REST (JSON / JsCalendar) equivalent of
 * calendar/tests/CalDAV/SingleDeleteTest.php.
 *
 * The event is created with a POST of a JsEvent (organizer + attendees as
 * participants referenced by their numeric account-id), and afterwards deleted
 * via REST DELETE requests addressing the shared numeric cal_id in the
 * respective user's calendar collection. Participant status is verified on the
 * returned JsEvent ("participationStatus": "declined") instead of the iCal
 * "PARTSTAT=DECLINED".
 *
 * The delete/reject and ACL semantics are implemented in the shared
 * calendar_groupdav::delete() handler, so they behave identically for REST and
 * CalDAV requests (incl. the "User-Agent: CalDAVSynchronizer" special case).
 *
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @covers \calendar_groupdav::delete()
 * @uses   \calendar_groupdav::put()
 * @uses   \calendar_groupdav::get()
 */

namespace EGroupware\calendar\REST;

require_once __DIR__.'/../../../api/tests/RestBase.php';

use EGroupware\Api\RestBase;
use EGroupware\Api\Acl;
use GuzzleHttp\RequestOptions;

class SingleDeleteTest extends RestBase
{
	protected const BOSS_MAIL = 'boss@example.org';
	protected const SECRETARY_MAIL = 'secretary@example.org';
	protected const OTHER_MAIL = 'other@example.org';

	/**
	 * account_id of the created test users, keyed by account_lid
	 *
	 * @var array<string,int>
	 */
	protected static $ids = [];

	/**
	 * Create the test users (boss, secretary, other) incl. ACL.
	 *
	 * secretary gets full rights on boss's calendar - this mirrors exactly the
	 * grant CalDAVTest::createUsersACL() would issue, but we create the users
	 * directly to capture their account-ids for building participant objects.
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		$boss = [];
		self::$ids['boss'] = self::createUser('boss', $boss);
		$secretary = [];
		self::$ids['secretary'] = self::createUser('secretary', $secretary);
		$other = [];
		self::$ids['other'] = self::createUser('other', $other);

		// secretary gets full rights on boss's calendar
		self::addAcl('calendar', self::$ids['secretary'], 'boss', Acl::READ|Acl::ADD|Acl::EDIT|Acl::DELETE);
	}

	/**
	 * Build a participant object referenced by numeric account-id.
	 */
	protected function participant(string $email, array $roles, string $status) : array
	{
		return [
			'@type' => 'Participant',
			'email' => $email,
			'kind'  => 'individual',
			'roles' => $roles,
			'participationStatus' => $status,
		];
	}

	/**
	 * Build a non-recurring JsEvent with the given organizer + attendee participants.
	 *
	 * @param string $uid
	 * @param string $title
	 * @param array $participants account_id => participant object
	 * @return array JsEvent
	 */
	protected function buildEvent(string $uid, string $title, array $participants) : array
	{
		return [
			'@type'    => 'Event',
			'uid'      => $uid,
			'title'    => $title,
			'start'    => '2030-01-01T20:00:00',
			'timeZone' => 'Europe/Berlin',
			'duration' => 'PT1H',
			'participants' => $participants,
		];
	}

	/**
	 * Create an event as $organizer (POST to their collection) and return the numeric cal_id.
	 */
	protected function createInvitation(string $organizer, array $event)
	{
		$response = $this->postEvent($event, $organizer);
		$this->assertHttpStatus([200, 201], $response, "Create event as $organizer");
		$id = $this->idFromResponse($response);
		$this->assertNotEmpty($id, 'No id returned for created event');
		return $id;
	}

	/**
	 * DELETE $path_user's view of an event, authenticated as $auth_user.
	 */
	protected function deleteAs(string $auth_user, string $path_user, $id, array $headers=[]) : \Psr\Http\Message\ResponseInterface
	{
		return $this->getClient($auth_user)->delete($this->url($this->eventResource($path_user, $id)), [
			RequestOptions::HEADERS => $this->jsonHeaders($headers),
		]);
	}

	/**
	 * GET $path_user's view of an event, authenticated as $auth_user.
	 */
	protected function getAs(string $auth_user, string $path_user, $id) : \Psr\Http\Message\ResponseInterface
	{
		return $this->getClient($auth_user)->get($this->url($this->eventResource($path_user, $id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
	}

	/**
	 * Verify the test users' calendar collections are reachable (REST discovery).
	 *
	 * Pass criteria:
	 * - GET on each user's calendar collection returns HTTP 200.
	 */
	public function testCollections()
	{
		foreach(['boss', 'secretary', 'other'] as $user)
		{
			$response = $this->getAs($user, $user, '');
			$this->assertHttpStatus(200, $response, "Collection of $user reachable");
		}
	}

	/**
	 * Secretary deletes in boss's calendar an event boss is invited to (as attendee).
	 *
	 * Setup:
	 * - Create event where organizer is "other" and boss is attendee.
	 *
	 * Pass criteria:
	 * - Secretary deleting in boss's (attendee) calendar rejects/declines (204)
	 *   without removing the organizer copy.
	 * - Secretary cannot delete the organizer copy (403).
	 * - Boss can delete/reject in both attendee and organizer views (204).
	 * - Organizer can finally delete the event and GET then returns 404.
	 */
	public function testSecretaryDeletesBossAttendee()
	{
		$uid = $this->makeUid('rest-single-delete-boss-attendee');
		$event = $this->buildEvent($uid, 'Single delete: boss attendee', [
			(string)self::$ids['other'] => $this->participant(self::OTHER_MAIL, ['owner' => true, 'chair' => true], 'accepted'),
			(string)self::$ids['boss']  => $this->participant(self::BOSS_MAIL, ['attendee' => true], 'needs-action'),
		]);

		// create invitation by organizer "other"
		$cal_id = $this->createInvitation('other', $event);

		// secretary deletes event in boss's calendar
		$response = $this->deleteAs('secretary', 'boss', $cal_id);
		$this->assertHttpStatus(204, $response, 'Secretary delete/rejects for boss');

		// organizer checks event still exists and boss declined
		$response = $this->getAs('other', 'other', $cal_id);
		$this->assertHttpStatus(200, $response, 'Check event still exists after DELETE in attendee calendar');
		$this->assertParticipationStatus($this->jsonDecode($response), self::BOSS_MAIL, 'declined',
			'Boss should have declined the invitation');

		// secretary tries to delete event in organizer's calendar
		$response = $this->deleteAs('secretary', 'other', $cal_id);
		$this->assertHttpStatus(403, $response, 'Secretary not allowed to delete for organizer');

		// boss deletes/rejects event in his calendar
		$response = $this->deleteAs('boss', 'boss', $cal_id);
		$this->assertHttpStatus(204, $response, 'Boss deletes/rejects in his calendar');

		// boss deletes/rejects event in organizer's calendar
		$response = $this->deleteAs('boss', 'other', $cal_id);
		$this->assertHttpStatus(204, $response, 'Boss deletes/rejects in organizer calendar');

		// organizer deletes event
		$response = $this->deleteAs('other', 'other', $cal_id);
		$this->assertHttpStatus(204, $response, 'Organizer deletes');

		// organizer checks event deleted
		$response = $this->getAs('other', 'other', $cal_id);
		$this->assertHttpStatus(404, $response, 'Check event deleted by organizer');
	}

	/**
	 * Secretary deletes for boss, who is the organizer of the event.
	 *
	 * Setup:
	 * - Create event where boss is organizer and other is attendee.
	 *
	 * Pass criteria:
	 * - Attendee can delete/reject in his calendar (204).
	 * - Secretary (with delegated rights) can delete the organizer event (204).
	 * - Organizer copy is gone afterwards (404).
	 */
	public function testSecretaryDeletesBossOrganizer()
	{
		$uid = $this->makeUid('rest-single-delete-boss-organizer');
		$event = $this->buildEvent($uid, 'Single delete: boss organizer', [
			(string)self::$ids['boss']  => $this->participant(self::BOSS_MAIL, ['owner' => true, 'chair' => true], 'accepted'),
			(string)self::$ids['other'] => $this->participant(self::OTHER_MAIL, ['attendee' => true], 'needs-action'),
		]);

		$cal_id = $this->createInvitation('boss', $event);

		// attendee deletes/rejects event in his calendar
		$response = $this->deleteAs('other', 'other', $cal_id);
		$this->assertHttpStatus(204, $response, 'Attendee deletes/rejects');

		// secretary deletes event in boss's calendar
		$response = $this->deleteAs('secretary', 'boss', $cal_id);
		$this->assertHttpStatus(204, $response, 'Secretary deletes for boss');

		// boss (organizer) checks event deleted
		$response = $this->getAs('boss', 'boss', $cal_id);
		$this->assertHttpStatus(404, $response, 'Check event deleted by secretary');
	}

	/**
	 * Organizer (boss) can delete the event in his calendar.
	 *
	 * Setup:
	 * - Create event where boss is organizer and other is attendee.
	 *
	 * Pass criteria:
	 * - Organizer delete returns 204.
	 * - Event is removed for both organizer and attendee views (404).
	 */
	public function testOrganizerDeletes()
	{
		$uid = $this->makeUid('rest-single-delete-organizer');
		$event = $this->buildEvent($uid, 'Single delete: organizer deletes', [
			(string)self::$ids['boss']  => $this->participant(self::BOSS_MAIL, ['owner' => true, 'chair' => true], 'accepted'),
			(string)self::$ids['other'] => $this->participant(self::OTHER_MAIL, ['attendee' => true], 'needs-action'),
		]);

		$cal_id = $this->createInvitation('boss', $event);

		// organizer deletes event in his calendar
		$response = $this->deleteAs('boss', 'boss', $cal_id);
		$this->assertHttpStatus(204, $response, 'Organizer deletes');

		// organizer checks event deleted
		$response = $this->getAs('boss', 'boss', $cal_id);
		$this->assertHttpStatus(404, $response, 'Check event deleted by organizer');

		// attendee checks event deleted
		$response = $this->getAs('other', 'other', $cal_id);
		$this->assertHttpStatus(404, $response, 'Check event deleted by organizer');
	}

	/**
	 * Secretary as attendee deletes the event in her own calendar.
	 *
	 * Setup:
	 * - Create event where boss is organizer and secretary is attendee.
	 *
	 * Pass criteria:
	 * - Secretary delete in her attendee calendar returns 204.
	 * - Organizer copy is deleted (404).
	 */
	public function testSecretaryAttendeeDeletes()
	{
		$uid = $this->makeUid('rest-single-delete-secretary-attendee');
		$event = $this->buildEvent($uid, 'Single delete: secretary attendee', [
			(string)self::$ids['boss']      => $this->participant(self::BOSS_MAIL, ['owner' => true, 'chair' => true], 'accepted'),
			(string)self::$ids['secretary'] => $this->participant(self::SECRETARY_MAIL, ['attendee' => true], 'needs-action'),
		]);

		$cal_id = $this->createInvitation('boss', $event);

		// secretary deletes in her calendar
		$response = $this->deleteAs('secretary', 'secretary', $cal_id);
		$this->assertHttpStatus(204, $response, 'Secretary (attendee) deletes');

		// organizer checks it's really deleted
		$response = $this->getAs('boss', 'boss', $cal_id);
		$this->assertHttpStatus(404, $response, 'Check event deleted by secretary');
	}

	/**
	 * Secretary as attendee deletes the event using "User-Agent: CalDAVSynchronizer".
	 *
	 * Setup:
	 * - Create event where boss is organizer and secretary is attendee.
	 * - Execute delete with "User-Agent: CalDAVSynchronizer".
	 *
	 * Pass criteria:
	 * - Attendee delete/reject returns 204, but the organizer copy remains (200)
	 *   with secretary declined (CalDAVSynchronizer does not distinguish reject
	 *   from delete).
	 * - Organizer delete with the same user-agent still deletes the event (404).
	 */
	public function testSecretaryAttendeeDeletesCalDAVSynchronizer()
	{
		$uid = $this->makeUid('rest-single-delete-secretary-attendee-sync');
		$event = $this->buildEvent($uid, 'Single delete: secretary attendee (sync)', [
			(string)self::$ids['boss']      => $this->participant(self::BOSS_MAIL, ['owner' => true, 'chair' => true], 'accepted'),
			(string)self::$ids['secretary'] => $this->participant(self::SECRETARY_MAIL, ['attendee' => true], 'needs-action'),
		]);

		$cal_id = $this->createInvitation('boss', $event);

		// secretary deletes in her calendar with CalDAVSynchronizer user-agent
		$response = $this->deleteAs('secretary', 'secretary', $cal_id, ['User-Agent' => 'CalDAVSynchronizer']);
		$this->assertHttpStatus(204, $response, 'Secretary (attendee) deletes/rejects');

		// organizer checks it's NOT deleted, only declined
		$response = $this->getAs('boss', 'boss', $cal_id);
		$this->assertHttpStatus(200, $response, 'Check event NOT deleted by secretary');
		$this->assertParticipationStatus($this->jsonDecode($response), self::SECRETARY_MAIL, 'declined',
			'Secretary should have declined the invitation');

		// organizer deletes in his calendar with CalDAVSynchronizer user-agent
		$response = $this->deleteAs('boss', 'boss', $cal_id, ['User-Agent' => 'CalDAVSynchronizer']);
		$this->assertHttpStatus(204, $response, 'Organizer deletes');

		// organizer checks it's deleted
		$response = $this->getAs('boss', 'boss', $cal_id);
		$this->assertHttpStatus(404, $response, 'Check event deleted by organizer');
	}
}
