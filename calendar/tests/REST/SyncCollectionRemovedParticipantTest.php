<?php
/**
 * REST API tests: sync-collection REPORT must not list participants removed from an event (ticket #125261)
 *
 * Removing a participant keeps the participant in the DB with cal_status 'X' ("deleted"), so the removed
 * user's own calendar still learns about the change. A regular GET never returns such participants, but
 * the sync-collection REPORT searches with filter 'everything' (to also return deleted events), which keeps
 * them. JsCalendar has no mapping for status 'X', so the removed participant was returned in the REPORT
 * without a participationStatus, while the very same event via GET did not contain it anymore.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @subpackage tests
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar\REST;

require_once __DIR__.'/../../../api/tests/RestBase.php';

use EGroupware\Api\RestBase;
use PHPUnit\Framework\Attributes\Depends;

class SyncCollectionRemovedParticipantTest extends RestBase
{
	/**
	 * account_lid of the calendar owner, and of the participant we add and remove again
	 *
	 * @var string
	 */
	protected static $owner, $participant;

	/**
	 * cal_id of the event, and the removed participant's sync-token from before the removal
	 */
	protected static $id, $participant_token;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		$suffix = bin2hex(random_bytes(3));
		$data = [];
		self::$owner = 'syncrp-o-'.$suffix;
		self::createUser(self::$owner, $data);
		$data = [];
		self::$participant = 'syncrp-p-'.$suffix;
		self::createUser(self::$participant, $data);
	}

	protected function collection() : string
	{
		return $this->collectionUrl('calendar', self::$owner);
	}

	/**
	 * Emails of the participants of the given JsEvent
	 *
	 * @param array $event
	 * @return string[]
	 */
	protected static function emails(array $event) : array
	{
		return array_values(array_map('trim', array_filter(array_column($event['participants'] ?? [], 'email'))));
	}

	/**
	 * Removing a participant: GET and sync-collection REPORT have to agree on the participants.
	 *
	 * Setup:
	 * - owner creates an event inviting the second user, then removes that participant again via PUT.
	 *
	 * Pass criteria:
	 * - GET no longer returns the removed participant (documents the existing behavior).
	 * - the sync-collection REPORT returns the changed event with the same participants as the GET,
	 *   in particular NOT the removed one, and none without a participationStatus.
	 */
	public function testRemovedParticipantNotInSyncCollection()
	{
		$participant_email = self::$participant.'@example.org';

		$response = $this->postResource($this->collection(), [
			'title' => 'removed participant',
			'start' => '2035-01-01T10:00:00',
			'timeZone' => 'Europe/Berlin',
			'duration' => 'PT1H',
			'participants' => [
				'p1' => [
					'@type' => 'Participant',
					'email' => $participant_email,
					'name' => 'Removed Participant',
					'roles' => ['attendee' => true],
				],
			],
		], self::$owner);
		$this->assertHttpStatus([200, 201], $response, 'creating event');
		$id = (int)$this->idFromResponse($response);
		$this->assertGreaterThan(0, $id);

		$event = $this->getEventJson($id, self::$owner);
		$this->assertContains($participant_email, self::emails($event), 'participant should be there after creating');

		// sync-token of the state with the participant
		sleep(1);	// modification-time / sync-token has only 1sec granularity
		self::$id = $id;
		self::$participant_token = $this->syncCollection($this->collectionUrl('calendar', self::$participant), '', null, self::$participant)['sync-token'] ?? '';
		$this->assertNotEmpty(self::$participant_token, 'No sync-token for participant returned');
		$token = $this->syncCollection($this->collection(), '', null, self::$owner)['sync-token'] ?? '';
		$this->assertNotEmpty($token, 'No sync-token returned');
		sleep(1);

		// remove the participant again, keeping everything else
		$event['participants'] = array_filter($event['participants'], function($participant) use ($participant_email)
		{
			return trim($participant['email'] ?? '') !== $participant_email;
		});
		$this->assertHttpStatus([200, 204], $this->putEventJson($id, $event, self::$owner), 'removing participant');

		$event = $this->getEventJson($id, self::$owner);
		$this->assertNotContains($participant_email, self::emails($event), 'GET must not return the removed participant');

		$result = $this->syncCollection($this->collection(), $token, null, self::$owner);
		$path = '/'.self::$owner.'/calendar/'.$id;
		$this->assertArrayHasKey($path, $result['responses'] ?? [], 'changed event missing in sync-collection REPORT');
		$synced = $result['responses'][$path];

		$this->assertNotContains($participant_email, self::emails($synced),
			'sync-collection REPORT must not return the removed participant: '.json_encode($synced['participants'] ?? null));
		foreach($synced['participants'] ?? [] as $key => $participant)
		{
			$this->assertNotEmpty($participant['participationStatus'] ?? null, "participant $key without participationStatus");
		}
	}

	/**
	 * The removed participant's own calendar must still learn about the removal.
	 *
	 * The deleted participant is kept in the DB (cal_status 'X') exactly for this, only the JSON output of the
	 * REPORT filters it out of the participants list.
	 *
	 * Pass criteria:
	 * - the sync-collection REPORT of the removed participant's calendar, resumed from before the removal,
	 *   returns the event path without any properties (= "no longer in this calendar").
	 */
	#[Depends('testRemovedParticipantNotInSyncCollection')]
	public function testRemovedParticipantStillGetsRemovalInOwnCalendar()
	{
		$result = $this->syncCollection($this->collectionUrl('calendar', self::$participant), self::$participant_token, null, self::$participant);
		$path = '/'.self::$participant.'/calendar/'.self::$id;
		$this->assertArrayHasKey($path, $result['responses'] ?? [],
			'removal missing in the removed participant\'s own sync-collection REPORT');
		$this->assertArrayNotHasKey('title', (array)$result['responses'][$path],
			'removed event must be reported without properties: '.json_encode($result['responses'][$path]));
	}
}
