<?php
/**
 * CalDAV tests: create, read and delete an event
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
use PHPUnit\Framework\Attributes\Depends;

class CreateReadDeleteTest extends CalDAVTest
{
	protected const EVENT_UID = 'new-event-create-read-delete';
	protected const EVENT_FIXTURE = __DIR__.'/fixtures/create-read-delete-event.ics.tpl';
	protected static $event_uid;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		self::$event_uid = self::EVENT_UID.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(2));
		self::trackUid(self::$event_uid);
	}

	protected function eventUrl() : string
	{
		return '/'.$this->user().'/calendar/'.$this->eventUid().'.ics';
	}

	protected function user() : string
	{
		return $GLOBALS['EGW_USER'];
	}

	protected function eventUid() : string
	{
		return self::$event_uid;
	}

	protected function eventIcal() : string
	{
		$template = file_get_contents(self::EVENT_FIXTURE);
		$this->assertNotFalse($template, 'Unable to load event fixture');
		return strtr($template, [
			'{{UID}}' => $this->eventUid(),
		]);
	}

	/**
	 * Verify CalDAV endpoint rejects anonymous access.
	 *
	 * Pass criteria:
	 * - Requesting the CalDAV root without auth returns HTTP 401.
	 */
	public function testNoAuth()
	{
		$response = $this->getClient([])->get($this->url('/'));

		$this->assertHttpStatus(401, $response);
	}

	/**
	 * Verify authenticated principal discovery works.
	 *
	 * Pass criteria:
	 * - PROPFIND on authenticated user principal returns HTTP 207.
	 */
	public function testAuth()
	{
		$response = $this->getClient()->propfind($this->url('/principals/users/'.$this->user().'/'), [
			RequestOptions::HEADERS => [
				'Depth' => 0,
			],
		]);

		$this->assertHttpStatus(207, $response);
	}

	/**
	 * Create event from fixture payload.
	 *
	 * Setup:
	 * - Load fixture template and inject test UID.
	 *
	 * Pass criteria:
	 * - PUT returns HTTP 200 or 201.
	 */
	public function testCreate()
	{
		$response = $this->getClient()->put($this->url($this->eventUrl()), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation',
			],
			RequestOptions::BODY => $this->eventIcal(),
		]);
		parent::addCalendarID($response);

		$this->assertHttpStatus([200, 201], $response);
	}

	/**
	 * Read created event and verify iCal payload parity.
	 *
	 * Pass criteria:
	 * - GET returns HTTP 200.
	 * - Returned event matches fixture payload semantics.
	 */
	#[Depends('testCreate')]
	public function testRead()
	{
		$response = $this->getClient()->get($this->url($this->eventUrl()));

		$this->assertHttpStatus(200, $response);
		$this->assertIcal($this->eventIcal(), $response->getBody());
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
		$response = $this->getClient()->delete($this->url($this->eventUrl()));

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
		$response = $this->getClient()->get($this->url($this->eventUrl()));

		$this->assertHttpStatus(404, $response);
	}

	/**
	 * Delete of a non-existing event must return not-found.
	 *
	 * Setup:
	 * - Build a unique, never-created event URL in the authenticated user's
	 *   calendar collection.
	 *
	 * Pass criteria:
	 * - DELETE returns HTTP 404.
	 */
	public function testDeleteMissing()
	{
		$missing_uid = $this->makeUid('caldav-delete-missing');
		$response = $this->getClient()->delete($this->url('/'.$this->user().'/calendar/'.$missing_uid.'.ics'));

		$this->assertHttpStatus(404, $response);
	}
}
