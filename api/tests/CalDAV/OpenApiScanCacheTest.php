<?php
/**
 * EGroupware API: OpenAPI::scan()'s instance-wide cache must not leak the per-user app filter
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage caldav/rest
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * OpenAPI::scan() used to scandir()+json_decode() every app's doc/openapi/*.json file on every
 * single call - real disk I/O, repeated on every AiTools prompt-editor open/save/apply, since it
 * calls OpenAPI::operationIds() -> scan() unconditionally (ticket #124681).
 *
 * Fix: cache the decoded-but-unfiltered per-app JSON instance-wide (identical for every user), and
 * keep applying the per-user app-visibility filter fresh on every call from that cached data. This
 * test pins down exactly the invariant that split makes risky: a restricted user must still not see
 * paths for apps they don't have access to, and the cache must not let one user's filtered view
 * leak into another user's (or the same user's later, wider) result.
 */
class OpenApiScanCacheTest extends LoggedInTest
{
	public function testCachedScanStillHonoursPerUserAppFilter()
	{
		$had_calendar = isset($GLOBALS['egw_info']['user']['apps']['calendar']);

		try
		{
			// warm the instance-wide cache with full (LoggedInTest default) access
			$full = OpenAPI::scan();
			$this->assertArrayHasKey('/calendar/', $full['paths'] ?? [],
				'Precondition: calendar paths must be present with full access');

			// now simulate a user without calendar access - must NOT see calendar's paths, even
			// though the underlying file scan is now served from the instance-wide cache
			unset($GLOBALS['egw_info']['user']['apps']['calendar']);
			$restricted = OpenAPI::scan();
			$this->assertArrayNotHasKey('/calendar/', $restricted['paths'] ?? [],
				'A user without calendar access must not get calendar paths from the cached scan');

			// and restoring access must immediately see them again - proving the cache holds the
			// unfiltered per-file data, not a stale filtered result from the previous call
			$GLOBALS['egw_info']['user']['apps']['calendar'] = $had_calendar ?: 1;
			$restored = OpenAPI::scan();
			$this->assertArrayHasKey('/calendar/', $restored['paths'] ?? [],
				'Restoring calendar access must see calendar paths again, not a stale filtered cache');
		}
		finally
		{
			if ($had_calendar)
			{
				$GLOBALS['egw_info']['user']['apps']['calendar'] = 1;
			}
			else
			{
				unset($GLOBALS['egw_info']['user']['apps']['calendar']);
			}
		}
	}
}
