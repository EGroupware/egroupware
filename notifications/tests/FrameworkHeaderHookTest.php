<?php
/**
 * EGroupware Notifications: framework_header hook registers app.ts's bundle early enough regression test
 *
 * @link http://www.egroupware.org
 * @package notifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * Regression test for a live bug (2026-09-21): the notification bell showed no count and stopped
 * reacting to clicks, because notifications/js/app.ts never actually loaded. Root cause:
 * Api\Framework\Ajax::header()'s _get_header() call - which resolves Api\Framework::get_script_links()
 * into the page's own JS include list, via _get_js() - runs BEFORE the 'after_navbar' hook fires.
 * notifications/inc/hook_after_navbar.inc.php calling Api\Framework::includeJS() was therefore
 * always too late to have any effect on a real page. Fixed by registering the bundle from the
 * earlier 'framework_header' hook instead (notifications/inc/hook_framework_header.inc.php),
 * exactly the same hook location swoolepush already uses to inject its own websocket bootstrap
 * data. after_navbar still carries the config values (id="notifications_script_id" + data-*
 * attributes) app.ts's constructor reads - that part was never the CSP/timing problem.
 */
class FrameworkHeaderHookTest extends LoggedInTest
{
	private $originalNotificationsGrant;

	protected function setUp() : void
	{
		parent::setUp();

		// hook_framework_header.inc.php's own guard requires the CURRENT user to have the
		// notifications app granted - true for this repo's usual dev/test accounts, but not
		// guaranteed for whatever account a fresh CI install's LoggedInTest ends up using (found
		// live in CI: the hook silently skipped includeJS(), failing
		// testFrameworkHeaderHookRegistersTheAppBundleBeforeGetScriptLinksIsResolved further down -
		// "Failed asserting that an array is not empty"). Force it on for the duration of this
		// test class instead of depending on ambient account state, and restore it after.
		$this->originalNotificationsGrant = $GLOBALS['egw_info']['user']['apps']['notifications'] ?? null;
		$GLOBALS['egw_info']['user']['apps']['notifications'] = 1;
	}

	protected function tearDown() : void
	{
		if (isset($this->originalNotificationsGrant))
		{
			$GLOBALS['egw_info']['user']['apps']['notifications'] = $this->originalNotificationsGrant;
		}
		else
		{
			unset($GLOBALS['egw_info']['user']['apps']['notifications']);
		}
		parent::tearDown();
	}

	public function testNotificationsRegistersAFrameworkHeaderHook()
	{
		Api\Hooks::read(true);
		self::assertContains('notifications', Api\Hooks::implemented('framework_header'),
			'notifications must register a framework_header hook (notifications/setup/setup.inc.php) '.
			'- without it, app.ts never gets included on a real page');
	}

	public function testFrameworkHeaderHookRegistersTheAppBundleBeforeGetScriptLinksIsResolved()
	{
		// simulate exactly the call Api\Framework\Ajax::header() makes, at the point it makes it -
		// BEFORE its own _get_header()/get_script_links() call, never after
		Api\Hooks::process(['location' => 'framework_header', 'popup' => false, 'extra' => []], [], true);

		$included = Api\Framework::get_script_links(true, false);
		self::assertNotEmpty(
			array_filter($included, static fn($path) => str_contains($path, 'notifications/js/app.min.js')),
			'notifications/js/app.min.js must already be registered once framework_header hooks have '.
			'run, or the notification bell never loads on a real page at all'
		);
	}

	public function testFrameworkHeaderHookSkipsPopupWindows()
	{
		Api\Framework::get_script_links(true, true);	// clear anything registered so far

		Api\Hooks::process(['location' => 'framework_header', 'popup' => true, 'extra' => []], [], true);

		$included = Api\Framework::get_script_links(true, false);
		self::assertEmpty(
			array_filter($included, static fn($path) => str_contains($path, 'notifications/js/app.min.js')),
			'a popup window (eg. mail compose) has no bell UI and should not load this bundle at all'
		);
	}
}
