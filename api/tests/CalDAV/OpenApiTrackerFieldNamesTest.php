<?php
/**
 * EGroupware API: doc/openapi/tracker.json's Ticket field names vs. the real REST parser
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage caldav/rest
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;
use EGroupware\Tracker\JsTracker;

/**
 * doc/openapi/tracker.json is hand-maintained separately from tracker/src/JsTracker.php, the code
 * that actually reads REST request bodies - nothing keeps them in sync. A real regression of
 * exactly this kind reached master undetected: the spec documented dueDate/completion/private/
 * assigned/cc as the writable Ticket fields long after JsTracker::parseJsTicket() had been renamed
 * to due/percentComplete/privacy/participants, so any client built against the published spec sent
 * fields the server silently dropped (logged server-side only, the request still returned success).
 *
 * This exercises the real parseJsTicket() with every non-readOnly property the spec currently
 * documents, so a future rename on either side (code or spec) that reintroduces a mismatch fails
 * here with "unknown field" instead of only being noticed by an integrator's bug report.
 */
class OpenApiTrackerFieldNamesTest extends LoggedInTest
{
	/**
	 * Fields whose real JsTracker::parseJsTicket() name differs from the historical/legacy name a
	 * client might still send - not under test here, @see \EGroupware\Tracker\JsTracker::parseJsTicket()
	 * for the authoritative mapping.
	 */
	const SAMPLE_VALUES = [
		'title'           => 'OpenAPI field-name consistency check',
		'description'     => 'test',
		'tracker'         => 1,
		'status'          => 'Open',	// built-in label, resolved without a DB lookup
		'priority'        => 5,
		'percentComplete' => 0,
		'start'           => '2026-01-01T00:00:00Z',
		'due'             => '2026-01-01T00:00:00Z',
		'privacy'         => 'public',
		'category'        => null,	// null bypasses the queue-scoped label DB lookup
		'version'         => null,
		'resolution'      => null,
		'group'           => null,
		'participants'    => [],
		'egroupware.org:customfields' => [],
	];

	protected function trackerOpenApiPath(): string
	{
		$path = EGW_SERVER_ROOT.'/doc/openapi/tracker.json';
		if (!file_exists($path))
		{
			$this->markTestSkipped("$path not found");
		}
		return $path;
	}

	/**
	 * Every non-readOnly property the spec documents for the Ticket schema must be a field
	 * JsTracker::parseJsTicket() actually recognizes - not one it silently logs as unknown and drops.
	 */
	public function testEveryWritableTicketPropertyIsRecognizedByTheRealParser()
	{
		$spec = json_decode(file_get_contents($this->trackerOpenApiPath()), true, 512, JSON_THROW_ON_ERROR);
		$properties = $spec['components']['schemas']['Ticket']['properties'] ?? null;
		$this->assertNotEmpty($properties, 'doc/openapi/tracker.json has no components.schemas.Ticket.properties');

		$body = [];
		$untested = [];
		foreach ($properties as $name => $def)
		{
			if (!empty($def['readOnly']))
			{
				continue;	// e.g. id/created/updated/etag - never part of a request body
			}
			if (!array_key_exists($name, self::SAMPLE_VALUES))
			{
				$untested[] = $name;
				continue;
			}
			$body[$name] = self::SAMPLE_VALUES[$name];
		}
		$this->assertSame([], $untested,
			'add a sample value to '.static::class.'::SAMPLE_VALUES for the newly-documented field(s) above');

		$unknown = [];
		set_error_handler(static function() { return true; });	// swallow the deprecation noise, not what's under test
		$restore = ini_set('log_errors', '1');
		$log = tempnam(sys_get_temp_dir(), 'jstracker-errorlog-');
		$original_error_log = ini_set('error_log', $log);
		try
		{
			JsTracker::parseJsTicket(json_encode($body, JSON_THROW_ON_ERROR), [], null, 'PUT');
		}
		finally
		{
			ini_set('error_log', $original_error_log);
			ini_set('log_errors', $restore);
			restore_error_handler();
			foreach (preg_split('/\R/', (string)file_get_contents($log)) as $line)
			{
				if (preg_match('/unknown field ([^=]+)=/', $line, $m))
				{
					$unknown[] = trim($m[1]);
				}
			}
			@unlink($log);
		}

		$this->assertSame([], $unknown,
			'doc/openapi/tracker.json documents these Ticket properties as writable, but '.
			'JsTracker::parseJsTicket() does not recognize them: '.implode(', ', $unknown));
	}
}
