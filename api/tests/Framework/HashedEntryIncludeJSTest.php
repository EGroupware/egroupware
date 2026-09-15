<?php
/**
 * EGroupware Api: Framework::includeJS() vs. hashed rollup entries
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use EGroupware\Api\Framework\Bundle;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for a hook pulling in ANOTHER app's JS (eg. collabora's Ui::index() hook
 * calling Framework::includeJS('.', 'app.min', 'collabora', true) to load collabora/js/app.min.js
 * into the filemanager app) silently doing nothing once rollup's entries are content-hashed.
 *
 * Root cause: since a10de... "hash rollup entries and pin an open document to its own build",
 * <app>/js/app.min.js is no longer on disk under its literal name - only the hashed chunk
 * (chunks/<app>-js-app.min-<hash>.js) is, with the mapping recorded in the build manifest
 * (Bundle::resolveEntry()). Framework\IncludeMgr::translate_params() already got a
 * Bundle::resolveEntry() fallback for its "$package[0] == '/'" branch (and Etemplate.php:284 has
 * the same fallback for the current template's own app.min.js), but the "$package == '.'" branch
 * - the one Framework::includeJS('.', 'app.min', $app) actually hits - still did a bare
 * is_readable() check on the now-nonexistent literal path and silently dropped the include: no
 * exception, no warning, the app's JS is just never sent to the client. Confirmed live on a real
 * hosting instance: collabora's app.min.js never appeared in the ajax_exec response's "js"
 * entries (only kanban's and filemanager's did), so app.filemanager never got upgraded to
 * collabora's subclass and "New document" silently failed.
 *
 * This does not touch client-side hashed-name resolution (egw.include()/the egw object resolving
 * a logical path like "/collabora/js/app.min.js" against the manifest embedded in the page) -
 * that part already works correctly. This is purely about the include being queued server-side
 * at all, before it ever reaches that client-side step.
 *
 * Setup: fake a manifest entry (via reflection on Bundle's private static cache) for a
 * "/hashedentryincludejstest/js/app.min.js" logical path that is guaranteed to NOT exist on disk
 * under its literal name, so a pass here can only be due to the resolveEntry() fallback, never an
 * accidental is_readable() hit.
 *
 * Pass criteria: Framework::includeJS('.', 'app.min', $app, true) followed by
 * Framework::js_files() must include the logical "/$app/js/app.min.js" path.
 */
class HashedEntryIncludeJSTest extends TestCase
{
	private static $manifestBackup;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		// force Bundle's manifest cache to load once, then remember it so we can restore it
		Bundle::loadManifest();
		$prop = new \ReflectionProperty(Bundle::class, 'manifest');
		self::$manifestBackup = $prop->getValue();
	}

	public static function tearDownAfterClass() : void
	{
		(new \ReflectionProperty(Bundle::class, 'manifest'))->setValue(null, self::$manifestBackup);

		parent::tearDownAfterClass();
	}

	private function fakeManifestEntry(string $logical, string $hashed) : void
	{
		$manifest = is_array(self::$manifestBackup) ? self::$manifestBackup : [];
		$manifest[$logical] = $hashed;

		(new \ReflectionProperty(Bundle::class, 'manifest'))->setValue(null, $manifest);
	}

	public function testIncludeAnotherAppsHashedEntryViaDotPackage() : void
	{
		$app = 'hashedentryincludejstest';
		$logical = "/$app/js/app.min.js";

		$this->assertFileDoesNotExist(EGW_SERVER_ROOT.$logical,
			'Test fixture app must not actually exist on disk, or this proves nothing');

		$this->fakeManifestEntry($logical, '/chunks/'.$app.'-js-app.min-deadbeef.js');

		// clear anything a previous test queued, so the assertion below is unambiguous
		Framework::js_files(null, true);

		Framework::includeJS('.', 'app.min', $app, true);

		$this->assertContains($logical, Framework::js_files(),
			"includeJS('.', 'app.min', '$app') did not queue the app's hashed entry - ".
			"a hook pulling in another app's JS (eg. collabora into filemanager) would silently ".
			"lose that app's script include entirely");
	}
}
