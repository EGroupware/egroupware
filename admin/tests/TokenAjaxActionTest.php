<?php
/**
 * Test the ajax endpoint the token list's context-menu actions now call
 *
 * @link https://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Admin;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__ . '/../../api/tests/LoggedInTest.php');

/**
 * Token::ajax_action() is what Activate and Revoke call from the list's context menu.
 *
 * WHY THE MENUACTION IS NAMED EXPLICITLY
 * This same list is shown from two apps - admin's token page and preferences' "Security &
 * Password" popup, via EGroupware\Preferences\Token which only overrides APP.  EgwApp's default
 * "<app>.<app>_ui.ajax_action" is wrong for both, and a single hard-coded admin menuaction would
 * be refused for a preferences-only user: json.php requires the caller to have the app the
 * menuaction names.  So get_actions() builds it from static::APP/static::class, and these tests
 * pin that both ends of that come out right.
 *
 * SETUP
 * Each test creates its own token and removes it again in tearDown.
 */
class TokenAjaxActionTest extends LoggedInTest
{
	protected $token_id;

	protected function setUp() : void
	{
		Api\Json\Response::get()->initResponseArray();
	}

	protected function tearDown() : void
	{
		if ($this->token_id)
		{
			$GLOBALS['egw']->db->delete('egw_tokens', ['token_id' => $this->token_id], __LINE__, __FILE__);
			$this->token_id = null;
		}
	}

	protected function makeToken() : int
	{
		$token = Api\Auth\Token::create($GLOBALS['egw_info']['user']['account_id'], null, 'TokenAjaxActionTest');
		$this->assertNotEmpty($token['token_id'] ?? null, 'could not create the test token');
		return $this->token_id = (int)$token['token_id'];
	}

	protected function isRevoked() : bool
	{
		return (bool)$GLOBALS['egw']->db->select('egw_tokens', 'token_revoked',
			['token_id' => $this->token_id], __LINE__, __FILE__)->fetchColumn();
	}

	/**
	 * A real eTemplate request id, the way the browser sends one along - the endpoint refuses
	 * without it, see Nextmatch::validateExecId().  Writing to the request is what persists it.
	 */
	protected function execId() : string
	{
		$request = \EGroupware\Api\Etemplate\Request::read();
		$id = $request->id();
		$request->content = ['token' => []];
		unset($request);
		return $id;
	}

	protected function refreshCall() : ?array
	{
		$response = Api\Json\Response::get();
		$prop = (new \ReflectionClass($response))->getProperty('responseArray');
		$prop->setAccessible(true);
		foreach((array)$prop->getValue($response) as $chunk)
		{
			$chunk = (array)$chunk;
			if (($chunk['type'] ?? null) === 'apply' && ($chunk['data']['func'] ?? null) === 'egw.refresh')
			{
				return (array)$chunk['data']['parms'];
			}
		}
		return null;
	}

	/**
	 * The regression shape: the endpoint has to reach action()'s body and persist.
	 */
	public function testRevokeAndActivateThroughTheEndpoint()
	{
		$this->makeToken();
		$this->assertFalse($this->isRevoked(), 'a fresh token should not be revoked');

		Token::ajax_action($this->execId(), 'revoke', [$this->token_id]);
		$this->assertTrue($this->isRevoked(), 'revoke must persist');
		$this->assertNotNull($this->refreshCall(),
			'the endpoint must answer with egw.refresh, or the list updates no rows');

		Api\Json\Response::get()->initResponseArray();
		Token::ajax_action($this->execId(), 'activate', [$this->token_id]);
		$this->assertFalse($this->isRevoked(), 'activate must undo it');
	}

	/**
	 * json.php has no CSRF token of its own, so the exec id is what says the caller had one of our
	 * pages open.  Without a live one the endpoint must do nothing at all.
	 */
	public function testWithoutALiveExecIdDoesNothing()
	{
		$this->makeToken();

		Token::ajax_action('', 'revoke', [$this->token_id]);
		$this->assertFalse($this->isRevoked(), 'no exec id must not revoke anything');

		Token::ajax_action('admin_nobody_'.base64_encode(random_bytes(32)), 'revoke', [$this->token_id]);
		$this->assertFalse($this->isRevoked(), 'a made-up exec id must not revoke anything either');
	}

	/**
	 * action() throws for anything it does not implement; the endpoint has to report that rather
	 * than let it escape as a 500.
	 */
	public function testAnUnknownActionIsReportedNotThrown()
	{
		$this->makeToken();

		Token::ajax_action($this->execId(), 'not_an_action', [$this->token_id]);

		$parms = $this->refreshCall();
		$this->assertNotNull($parms, 'even a failure has to answer the client');
		$this->assertSame('error', end($parms), 'and it has to say it failed');
	}

	/**
	 * _targetapp must be a real app name: egw.refresh() resolves it before its msg-only
	 * early-return, and a name that is not an app throws in the kdots framework.
	 */
	public function testRefreshTargetappIsTheRightApp()
	{
		$this->makeToken();

		Token::ajax_action($this->execId(), 'revoke', [$this->token_id]);

		$parms = $this->refreshCall();
		$this->assertNotNull($parms);
		$this->assertSame('admin', $parms[4], 'never the msg-only-push-refresh sentinel');
	}

	/**
	 * The menuaction the actions carry has to name the class's OWN app, or the preferences copy of
	 * this list calls an endpoint its user has no rights for.
	 */
	public function testEachAppGetsItsOwnMenuaction()
	{
		$admin = Token::get_actions('admin');
		$this->assertSame('admin.EGroupware\\Admin\\Token.ajax_action',
			$admin['revoke']['data']['menuaction'] ?? null);

		$prefs = \EGroupware\Preferences\Token::get_actions('preferences');
		$this->assertSame('preferences.EGroupware\\Preferences\\Token.ajax_action',
			$prefs['revoke']['data']['menuaction'] ?? null,
			'the preferences copy must call its own app, or json.php refuses it for a non-admin');
	}
}
