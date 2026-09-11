<?php
/**
 * EGroupware API: OpenAPI::scan()'s per-operationId uniqueness invariant
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
 * doc/openapi/*.json are hand-maintained per-app OpenAPI fragments, merged into one combined spec
 * by OpenAPI::scan() - every operation across EVERY installed app shares one flat operationId
 * namespace (AiTools exposes each operationId as a separately callable tool), so a mistake in any
 * one app's file can break the combined spec for every app, not just its own.
 *
 * This test exists because a real regression of exactly this kind reached master undetected:
 * smallpart.json's path-level "parameters" arrays (valid OpenAPI - a Path Item field shared by
 * every operation on that path, not an operation itself) were being iterated by scan() as if they
 * were operations needing their own operationId, throwing "parameters /smallpart/{id} requires an
 * unique operationId!" for every affected path - only ever discovered when AiTools' admin page
 * (which calls OpenAPI::operationIds() -> scan()) crashed for an install with smallpart enabled.
 * Separately, smallpart.json's own "addAttachment" also genuinely collided with links.json's
 * "addAttachment" - a second, independent bug the same crash was masking.
 *
 * Exercises the real OpenAPI::scan() (not a reimplementation of its checks) with every app
 * enabled/visible (LoggedInTest's default admin-ish session), so it fails the exact way that
 * production crash did, for either failure mode (a non-operation Path Item field mistaken for an
 * operation, or a genuine cross-app operationId collision) or any new one that also throws.
 */
class OpenApiOperationIdsTest extends LoggedInTest
{
	/**
	 * OpenAPI::scan() throws on the first problem it finds (see its own source) - not throwing
	 * already proves the whole combined spec is internally consistent for every currently
	 * installed app.
	 */
	public function testScanDoesNotThrow()
	{
		$json = OpenAPI::scan();

		$this->assertNotEmpty($json['paths'] ?? null,
			'OpenAPI::scan() returned no paths at all - check doc/openapi/*.json exist and are readable');
	}

	/**
	 * Belt-and-braces: re-derive the same uniqueness/presence check scan() enforces, directly from
	 * its own result, so a future refactor that accidentally stops *enforcing* the invariant
	 * (rather than just failing to find a violation) still gets caught here, with a precise
	 * "which operationId, which app" failure message instead of just "an exception was thrown".
	 */
	public function testEveryOperationIdIsPresentAndUniqueAcrossAllApps()
	{
		$seen = [];
		foreach (OpenAPI::scan()['paths'] as $path => $methods)
		{
			foreach ($methods as $method => $data)
			{
				if (!in_array(strtolower((string)$method), OpenAPI::HTTP_METHODS, true))
				{
					continue;	// Path Item field (parameters/summary/description/servers/$ref), not an operation
				}
				$operationId = $data['operationId'] ?? null;
				$this->assertNotEmpty($operationId, "$method $path has no operationId");
				$this->assertArrayNotHasKey($operationId, $seen,
					"operationId '$operationId' ($method $path) already used by '{$seen[$operationId]}'");
				$seen[$operationId] = "$method $path";
			}
		}
	}
}
