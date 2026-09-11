<?php
/**
 * EGroupware Api: Test Session::init_handler()'s cookie-less session reattachment
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__ . '/LoggedInTest.php';

/**
 * Session::init_handler() is used to reattach to an existing user session WITHOUT a
 * cookie - notably by Collabora's WOPI callbacks (Wopi::create_session() ->
 * Session::init_handler($share['share_with'])), which are machine-to-machine HTTP
 * requests from the Collabora server, not the editing user's browser.
 *
 * A 2022 refactor (fe4d0dbbe32a57b7f5d690a04770f4aea1b78fa0, reverted 3 days later)
 * accidentally dropped the `ini_set('session.use_cookies', 0)` call from this method's
 * PHP_SESSION_NONE branch (Session.php ~2117) while touching this file for an unrelated
 * change - re-enabling PHP's default cookie handling broke WOPI's ability to reattach to
 * the right session by ID. This test pins that ini setting down so a future refactor of
 * this file can't silently drop it again.
 */
class SessionInitHandlerTest extends LoggedInTest
{
	public function testDisablesCookiesForCookielessReattach()
	{
		$sessionid = $GLOBALS['egw']->session->sessionid;
		$kp3 = $GLOBALS['egw']->session->kp3;

		// close the session, so init_handler() sees PHP_SESSION_NONE, same as a fresh
		// WOPI callback request would
		$GLOBALS['egw']->session->commit_session();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());

		// force use_cookies back on, so we can tell if init_handler() actually disables it
		ini_set('session.use_cookies', 1);

		$this->assertTrue(Session::init_handler($sessionid));

		$this->assertSame('0', ini_get('session.use_cookies'),
			"Session::init_handler() no longer disables session.use_cookies for cookie-less reattachment - " .
			"this is what WOPI callbacks rely on to reattach to the editing user's session by ID");

		// leave the session in a normal state for whatever runs next
		$GLOBALS['egw']->session->verify($sessionid, $kp3);
	}

	protected function tearDown() : void
	{
		ini_restore('session.use_cookies');
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			$GLOBALS['egw']->session->verify($GLOBALS['egw']->session->sessionid, $GLOBALS['egw']->session->kp3);
		}
		parent::tearDown();
	}
}
