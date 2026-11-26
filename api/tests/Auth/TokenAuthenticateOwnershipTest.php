<?php
/**
 * EGroupware Api: Test that Auth\Token::authenticate() enforces token owner and revocation
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
 * Auth\Token::authenticate() must verify token owner and revocation itself, not just password.
 */
class TokenAuthenticateOwnershipTest extends LoggedInTest
{
	protected $token;

	protected function tearDown() : void
	{
		if (!empty($this->token['token_id']))
		{
			// Token::revoke() calls save(), which requires admin rights to touch a token whose
			// account_id isn't the current user's own (eg. the account_id=0 "impersonate any"
			// token below) - elevate just for this cleanup call, regardless of which test ran.
			$had_admin = $GLOBALS['egw_info']['user']['apps']['admin'] ?? null;
			$GLOBALS['egw_info']['user']['apps']['admin'] = true;
			try
			{
				Token::revoke($this->token['token_id']);
			}
			finally
			{
				if ($had_admin === null)
				{
					unset($GLOBALS['egw_info']['user']['apps']['admin']);
				}
				else
				{
					$GLOBALS['egw_info']['user']['apps']['admin'] = $had_admin;
				}
			}
			$this->token = null;
		}
		parent::tearDown();
	}

	protected function createToken(string $remark) : array
	{
		$token_bo = new Token();
		$content = $token_bo->init() + ['new_token' => true];
		$content['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
		$content['token_remark'] = $remark;
		$token_bo->save($content);
		$this->token = $token_bo->data;
		$this->assertNotEmpty($this->token['token'], 'Token::save() did not generate a token string');
		return $this->token;
	}

	public function testTokenRejectsUsernameItWasNotIssuedFor()
	{
		$token = $this->createToken('phpunit ownership test');

		$this->assertFalse(Token::authenticate('not-this-users-login-xyz', $token['token']),
			'Token authenticated a username it was never issued for');

		$this->assertTrue(Token::authenticate($GLOBALS['EGW_USER'], $token['token']),
			'Token did not authenticate its own owner');
	}

	public function testRevokedTokenIsRejected()
	{
		$token = $this->createToken('phpunit revocation test');

		Token::revoke($token['token_id']);

		$this->assertFalse(Token::authenticate($GLOBALS['EGW_USER'], $token['token']),
			'A revoked token still authenticated');
	}

	public function testEmptyUsernameIsRejected()
	{
		$token = $this->createToken('phpunit empty-username test');

		$this->assertFalse(Token::authenticate('', $token['token']),
			'An empty username was accepted');
	}

	/**
	 * account_id=0 ("0=all users", see egw_tokens schema) is a deliberate feature: an admin-created
	 * token meant to authenticate as whatever username is given, not a specific account. authenticate()
	 * must only reject a MISMATCHING owner, never one that was never restricted to begin with -
	 * creating such a token already requires admin rights, enforced separately by Token::save().
	 */
	public function testAccountIdZeroStillImpersonatesAnyUser()
	{
		// creating an account_id=0 token requires admin rights (Token::save()'s own check) -
		// elevate only for that call, restored immediately after so no later test in this shared
		// process inherits admin rights on the non-admin phpunit test account
		$had_admin = $GLOBALS['egw_info']['user']['apps']['admin'] ?? null;
		$GLOBALS['egw_info']['user']['apps']['admin'] = true;
		try
		{
			$token_bo = new Token();
			$content = $token_bo->init() + ['new_token' => true];
			$content['account_id'] = 0;
			$content['token_remark'] = 'phpunit impersonate-any test';
			$token_bo->save($content);
			$this->token = $token_bo->data;
		}
		finally
		{
			if ($had_admin === null)
			{
				unset($GLOBALS['egw_info']['user']['apps']['admin']);
			}
			else
			{
				$GLOBALS['egw_info']['user']['apps']['admin'] = $had_admin;
			}
		}
		$this->assertNotEmpty($this->token['token'], 'Token::save() did not generate a token string');

		$this->assertTrue(Token::authenticate($GLOBALS['EGW_USER'], $this->token['token']),
			'An account_id=0 token must still authenticate as the real logged-in user');
		$this->assertTrue(Token::authenticate('some-other-made-up-login-abc', $this->token['token']),
			'An account_id=0 token must still authenticate as ANY username (admin-created impersonation)');
	}
}
