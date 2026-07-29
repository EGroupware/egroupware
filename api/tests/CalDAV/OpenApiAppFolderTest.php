<?php
/**
 * EGroupware API: OpenAPI descriptions shipped in an app's own folder are not public
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage caldav/rest
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * doc/openapi/openapi-json.php answers anonymous callers too, and without a user there is no
 * user app list to filter by: every description found went out. The core ones in doc/openapi are
 * public on purpose, one in <app>/doc/openapi is only for a user who has that app.
 */
class OpenApiAppFolderTest extends LoggedInTest
{
	/**
	 * First description shipped in an app folder and one of its paths, or skip
	 *
	 * @return array [app, path]
	 */
	protected function appFolderPath() : array
	{
		foreach(glob(EGW_SERVER_ROOT.'/*/doc/openapi/*.json') ?: [] as $file)
		{
			$json = json_decode(file_get_contents($file), true);
			if (!empty($json['paths']))
			{
				return [basename(dirname($file, 3)), array_key_first($json['paths'])];
			}
		}
		$this->markTestSkipped('No app ships its own OpenAPI description');
	}

	public function testAppFolderDescriptionOnlyForUsersOfTheApp()
	{
		[$app, $path] = $this->appFolderPath();
		$backup = $GLOBALS['egw_info']['user']['apps'] ?? null;
		try
		{
			$GLOBALS['egw_info']['user']['apps'][$app] = ['name' => $app];
			$this->assertArrayHasKey($path, OpenAPI::scan()['paths'],
				"A user of $app must get its description");

			unset($GLOBALS['egw_info']['user']['apps'][$app]);
			$this->assertArrayNotHasKey($path, OpenAPI::scan()['paths'],
				"A user without $app must not get its description");

			// anonymous: openapi-json.php without a session has no user app list at all
			unset($GLOBALS['egw_info']['user']['apps']);
			$anonymous = OpenAPI::scan()['paths'];
			$this->assertArrayNotHasKey($path, $anonymous, "An anonymous caller must not get $app's description");
			$this->assertArrayHasKey('/calendar/', $anonymous, 'The core descriptions stay public');
		}
		finally
		{
			if (isset($backup))
			{
				$GLOBALS['egw_info']['user']['apps'] = $backup;
			}
		}
	}
}
