<?php
/**
 * REST API tests base class
 *
 * Performs REST (JSON / JsCalendar / JsContact) requests against the same
 * EGroupware groupdav.php endpoint as the CalDAV/CardDAV tests, but using the
 * REST API instead of WebDAV methods.
 *
 * It extends {@see CalDAVTest} to reuse all the heavy lifting (user/ACL setup,
 * authentication, cleanup of created events and the HTTP status assertions) and
 * only adds JSON-specific request- and assertion-helpers.
 *
 * Key differences between the REST API and CalDAV (see doc/REST-CalDAV-CardDAV/):
 * - resources are addressed by their numeric id, NOT by "<uid>.ics": for JSON
 *   requests calendar_groupdav sets $path_attr='id' and $path_extension=''
 * - new resources are created with a POST to the collection; the "Location"
 *   header and "ETag" of the response contain the new id
 * - requests carry a "Content-Type: application/json" (POST/PUT/PATCH) and/or
 *   "Accept: application/json" (GET/DELETE) header, which is how the server
 *   tells REST and CalDAV requests apart (see Api\CalDAV::isJSON())
 * - a DELETE REST request MUST send an "Accept: application/json" header,
 *   otherwise the CalDAV server returns 404, as the url does not end in .ics
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package api
 * @subpackage caldav
 * @copyright (c) 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/CalDAVTest.php';

use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Abstract base class for REST API tests against the EGroupware groupdav.php endpoint.
 *
 * @package EGroupware\Api
 */
abstract class RestBase extends CalDAVTest
{
	/**
	 * Content-Type / Accept used to select the REST (JSON) API
	 */
	const JSON_CONTENT_TYPE = 'application/json';

	/**
	 * URL of a user's calendar collection, e.g. "/<user>/calendar/"
	 *
	 * @param string $user account_lid of the calendar owner
	 * @return string
	 */
	protected function calendarCollection(string $user) : string
	{
		return '/'.$user.'/calendar/';
	}

	/**
	 * URL of a single event, addressed by its numeric id (REST does NOT use a .ics suffix!)
	 *
	 * @param string $user account_lid of the calendar owner / view
	 * @param int|string $id numeric cal_id of the event
	 * @return string
	 */
	protected function eventResource(string $user, $id) : string
	{
		return '/'.$user.'/calendar/'.$id;
	}

	/**
	 * Default JSON request headers, $extra is merged in last (and can overwrite them)
	 *
	 * @param array $extra additional headers
	 * @return array
	 */
	protected function jsonHeaders(array $extra=[]) : array
	{
		return array_merge([
			'Content-Type' => self::JSON_CONTENT_TYPE,
			'Accept'       => self::JSON_CONTENT_TYPE,
		], $extra);
	}

	/**
	 * Encode a JsEvent / JsContact array as a JSON request body
	 *
	 * @param array $data
	 * @return string
	 */
	protected function jsonBody(array $data) : string
	{
		return json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Decode a JSON response body into an array
	 *
	 * @param ResponseInterface $response
	 * @return array decoded body or [] if not (valid) JSON
	 */
	protected function jsonDecode(ResponseInterface $response) : array
	{
		$data = json_decode((string)$response->getBody(), true);
		return is_array($data) ? $data : [];
	}

	/**
	 * POST a JsEvent to a user's calendar collection to create a new event.
	 *
	 * By default "Prefer: return=representation" is requested, so the response
	 * body contains the fully expanded JsEvent.
	 *
	 * @param array $event JsEvent
	 * @param ?string $user account_lid of the calendar owner, default organizer/EGW_USER
	 * @param array $headers additional headers
	 * @return ResponseInterface 201 Created on success; Location & ETag contain the new id
	 */
	protected function postEvent(array $event, ?string $user=null, array $headers=[]) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->post($this->url($this->calendarCollection($user)), [
			RequestOptions::HEADERS => $this->jsonHeaders(array_merge([
				'Prefer' => 'return=representation',
			], $headers)),
			RequestOptions::BODY => $this->jsonBody($event),
		]);
	}

	/**
	 * PUT a full JsEvent to an existing resource (addressed by numeric id).
	 *
	 * Note the REST PUT requires all attributes to be specified.
	 *
	 * @param int|string $id numeric cal_id
	 * @param array $event JsEvent
	 * @param ?string $user account_lid of the calendar owner, default organizer/EGW_USER
	 * @param array $headers additional headers, e.g. ['If-Match' => $etag]
	 * @return ResponseInterface
	 */
	protected function putEventJson($id, array $event, ?string $user=null, array $headers=[]) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->put($this->url($this->eventResource($user, $id)), [
			RequestOptions::HEADERS => $this->jsonHeaders($headers),
			RequestOptions::BODY => $this->jsonBody($event),
		]);
	}

	/**
	 * PATCH a resource with a partial JsEvent (RFC 8984 PatchObject).
	 *
	 * @param int|string $id numeric cal_id
	 * @param array $patch partial JsEvent / PatchObject
	 * @param ?string $user account_lid of the calendar owner, default organizer/EGW_USER
	 * @param array $headers additional headers
	 * @return ResponseInterface
	 */
	protected function patchEventJson($id, array $patch, ?string $user=null, array $headers=[]) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->patch($this->url($this->eventResource($user, $id)), [
			RequestOptions::HEADERS => $this->jsonHeaders($headers),
			RequestOptions::BODY => $this->jsonBody($patch),
		]);
	}

	/**
	 * GET a single resource and return the raw Guzzle response (Accept: application/json).
	 *
	 * @param int|string $id numeric cal_id
	 * @param ?string $user account_lid of the calendar owner, default organizer/EGW_USER
	 * @param array $headers additional headers
	 * @return ResponseInterface
	 */
	protected function getEventResponse($id, ?string $user=null, array $headers=[]) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->get($this->url($this->eventResource($user, $id)), [
			RequestOptions::HEADERS => $this->jsonHeaders($headers),
		]);
	}

	/**
	 * GET a single resource and return the decoded JsEvent (asserts HTTP 200).
	 *
	 * @param int|string $id numeric cal_id
	 * @param ?string $user account_lid of the calendar owner, default organizer/EGW_USER
	 * @return array decoded JsEvent
	 */
	protected function getEventJson($id, ?string $user=null) : array
	{
		$response = $this->getEventResponse($id, $user);
		$this->assertHttpStatus(200, $response);
		return $this->jsonDecode($response);
	}

	/**
	 * DELETE a single resource.
	 *
	 * The "Accept: application/json" header is REQUIRED for REST DELETE requests,
	 * otherwise the CalDAV server returns 404 (url does not end in .ics).
	 *
	 * @param int|string $id numeric cal_id
	 * @param ?string $user account_lid of the calendar owner / view, default organizer/EGW_USER
	 * @param array $headers additional headers
	 * @return ResponseInterface
	 */
	protected function deleteEvent($id, ?string $user=null, array $headers=[]) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->delete($this->url($this->eventResource($user, $id)), [
			RequestOptions::HEADERS => $this->jsonHeaders($headers),
		]);
	}

	/**
	 * Extract the numeric event id of a freshly created resource from the response
	 * (preferring the Location header, falling back to the ETag) and track it for cleanup.
	 *
	 * @param ResponseInterface $response create (POST/PUT) response
	 * @return int|string new id, or '' if none could be determined
	 */
	protected function idFromResponse(ResponseInterface $response)
	{
		// Location: .../groupdav.php/<user>/calendar/<id>
		$location = $response->getHeader('Location')[0] ?? '';
		if ($location !== '' && preg_match('#/calendar/([^/?\#]+)$#', $location, $matches))
		{
			$id = $matches[1];
		}
		else
		{
			// ETag: "<cal_id>:<recurrence>:<modified>"
			$etag = $response->getHeader('ETag')[0] ?? '';
			$parts = explode(':', trim($etag, '[]"'));
			$id = $parts[0] ?? '';
		}
		if (is_numeric($id))
		{
			self::trackCalId((int)$id);
		}
		return $id;
	}

	/**
	 * Find a participant in a JsEvent by its (case-insensitive) email address.
	 *
	 * @param array $event JsEvent
	 * @param string $email
	 * @return array|null participant object or null if not found
	 */
	protected function findParticipantByEmail(array $event, string $email) : ?array
	{
		foreach($event['participants'] ?? [] as $participant)
		{
			if (isset($participant['email']) && strtolower($participant['email']) === strtolower($email))
			{
				return $participant;
			}
		}
		return null;
	}

	/**
	 * Assert a participant identified by its email has an expected participationStatus.
	 *
	 * @param array $event JsEvent (e.g. from getEventJson())
	 * @param string $email participant email
	 * @param string $expected_status e.g. "accepted", "declined", "needs-action", "tentative"
	 * @param string $message additional message prefix
	 */
	protected function assertParticipationStatus(array $event, string $email, string $expected_status, string $message='') : void
	{
		$participant = $this->findParticipantByEmail($event, $email);
		$this->assertNotNull($participant, (!empty($message) ? $message.': ' : '')."Participant $email not found in event");
		$this->assertEquals($expected_status, $participant['participationStatus'] ?? null,
			(!empty($message) ? $message.': ' : '')."Unexpected participationStatus for $email");
	}
}
