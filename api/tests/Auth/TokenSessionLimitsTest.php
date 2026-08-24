<?php
/**
 * EGroupware Api: Test that Auth\Token session-restricted rights survive re-verification
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Auth;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * Application-password / token logins can restrict a user to a subset of apps.
 * Session::verify() re-derives that restriction on EVERY request from the core
 * encrypted session var (session_limits), independent of api/src/loader.php's cached
 * egw_info/egw-object blob - it must NOT start trusting a cached, unrestricted apps
 * list instead. This test pins that down so any future change to loader.php's caching
 * (eg. making it change-tracked) can't silently regress it back to full rights.
 *
 * Tokens are created the same way the real UI does it (Admin\Token::edit(),
 * admin/src/Token.php ~120-132): via Token::save() with 'new_token' => true baked into
 * the SAME array passed as save()'s $keys argument - NOT via the static Token::create()
 * convenience helper, which has no callers anywhere in the codebase and is broken (it
 * calls save() with no arguments, so save()'s `$keys['new_token']` check never fires
 * and no token hash/string is ever generated).
 */
class TokenSessionLimitsTest extends LoggedInTest
{
	public function testRestrictedTokenRightsSurviveReVerify()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$full_apps = array_keys($GLOBALS['egw_info']['user']['apps']);
		$this->assertGreaterThan(1, count($full_apps),
			'Test user needs more than 1 app for the restriction to be meaningful');

		$restricted_app = 'calendar';
		$this->assertContains($restricted_app, $full_apps,
			"Test user needs the '$restricted_app' app for this test");

		$token_bo = new Token();
		$content = $token_bo->init() + ['new_token' => true];
		$content['account_id'] = $account_id;
		$content['token_limits'] = [$restricted_app => true];
		$content['token_remark'] = 'phpunit restricted-rights test';
		$token_bo->save($content);
		$token = $token_bo->data;
		$this->assertNotEmpty($token['token'], 'Token::save() did not generate a token string');

		try
		{
			// log in using the restricted token instead of the password - tear down the
			// current session first, otherwise Session::verify() just reattaches to it via
			// the still-valid cookie and never actually re-authenticates with the token
			self::tearDownAfterClass();
			static::load_egw($GLOBALS['EGW_USER'], $token['token'], $GLOBALS['EGW_DOMAIN']);

			$this->assertTrue($GLOBALS['egw']->session->token_auth,
				'Session was not marked as token-authenticated');
			$this->assertSame([$restricted_app], array_keys($GLOBALS['egw_info']['user']['apps']),
				'Login via restricted token did not restrict the apps list');

			// Simulate a SUBSEQUENT request re-verifying the same, still-active session
			// (what happens on every request after the first, whether or not loader.php
			// restored the environment from its session cache): the restriction must be
			// re-derived from the core session, not lost or upgraded back to full rights.
			// Pass sessionid/kp3 explicitly (as eg. Wopi::create_session() does for a
			// non-cookie machine-to-machine callback) - verify() with no args relies on
			// $_REQUEST/$_COOKIE, which aren't meaningfully populated in this CLI harness.
			$this->assertTrue($GLOBALS['egw']->session->verify(
				$GLOBALS['egw']->session->sessionid, $GLOBALS['egw']->session->kp3));
			$user = $GLOBALS['egw']->session->read_repositories();

			$this->assertSame([$restricted_app], array_keys($user['apps']),
				'Rights restriction was lost on re-verify of an existing token-authenticated session');
		}
		finally
		{
			Token::revoke($token['token_id']);
			// restore normal password login, so tests running after this one in the same
			// process are not left on a restricted, token-authenticated session
			self::tearDownAfterClass();
			static::load_egw($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD'], $GLOBALS['EGW_DOMAIN']);
		}
	}
}
