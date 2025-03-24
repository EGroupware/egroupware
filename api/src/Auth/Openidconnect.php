<?php
/**
 * EGroupware Api: OpenIDConnect authentication (EGroupware against another OIC IdP)
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2023 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Auth;

use EGroupware\Api;
use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;

class Openidconnect implements BackendSSO
{
	protected OpenIDConnectClient $client;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->client = new OpenIDConnectClient($GLOBALS['egw_info']['server']['oic_provider'],
			$GLOBALS['egw_info']['server']['oic_client_id'],
			$GLOBALS['egw_info']['server']['oic_client_secret']);

		// add scopes we are processing ('openid' is added automatic)
		$this->client->addScope(['email', 'profile']);
	}

	/**
	 * Attempt SSO login
	 *
	 * @return string sessionid on successful login, null otherwise
	 */
	function login()
	{
		try {
			//error_log(__METHOD__."() session_status()=".session_status().", _SESSION=".json_encode($_SESSION));
			$this->client->authenticate();

			// use configured payload attribute / claim, defaulting to "sub"
			$attribute = ($GLOBALS['egw_info']['server']['oic_username_attribute'] ?? 'sub');
			if ($attribute === 'custom' && !empty($GLOBALS['egw_info']['server']['oic_username_custom']))
			{
				$attribute = $GLOBALS['egw_info']['server']['oic_username_custom'];
			}
			$account_lid = $this->client->getVerifiedClaims($attribute);
			// extract username with regular expression, if configured and matching
			if (!empty($GLOBALS['egw_info']['server']['oic_username_preg']) && preg_match($GLOBALS['egw_info']['server']['oic_username_preg'], $account_lid))
			{
				$account_lid = preg_replace($GLOBALS['egw_info']['server']['oic_username_preg'], '$1', $account_lid);
			}
			$accounts = Api\Accounts::getInstance();
			if (!$accounts->name2id($account_lid, 'account_lid', 'u'))
			{
				// for attribute="email" check, if we have user with given email
				if ($attribute === 'email' && ($account_id = $accounts->name2id($account_lid, 'account_email', 'u')))
				{
					$account_lid = Api\Accounts::id2name($account_id);
				}
				else
				{
					// fail if auto-creation of authenticated users is NOT configured
					if (empty($GLOBALS['egw_info']['server']['auto_create_acct']))
					{
						Api\Auth::log(__METHOD__."() OpenIDConnect login successful, but user '$account_lid' does NOT exist in EGroupware, AND automatic user creating is disabled!");
						$_GET['cd'] = lang("OpenIDConnect login successful, but user '%1' does NOT exist in EGroupware, AND automatic user creating is disabled!", $account_lid);
						return null;
					}
					try {
						$user_info = $this->client->requestUserInfo();
						$GLOBALS['auto_create_acct'] = [
							'firstname' => $user_info->given_name,
							'lastname' => $user_info->family_name,
							'email' => $user_info->email,
							// not (yet) used supported keys
							//'primary_group' => '',
							//'add_group' => '',
							//'account_id' => 0,
						];
					}
					catch (OpenIDConnectClientException $e) {
						// do NOT fail, if IdP does not support user-info
						_egw_log_exception($e);
					}
				}
			}
			// return user session
			return $GLOBALS['egw']->session->create($account_lid, null, null, false, false);
		}
		catch(\Exception $e) {
			_egw_log_exception($e);
			$_GET['cd'] = 'OpenIDConnect Error: '.$e->getMessage();
			Api\Auth::log(__METHOD__."() OpenIDConnect Error: ".$e->getMessage());
			return null;
		}
	}

	/**
	 * Display a IdP selection / discovery
	 *
	 * Will be displayed if IdP(s) are added in setup and a discovery label is specified.
	 *
	 * @return string|null html to display in login page or null to disable the selection
	 */
	static public function discovery()
	{
		if (empty($GLOBALS['egw_info']['server']['openidconnect_discovery']))
		{
			return null;
		}
		return Api\Html::input('auth=openidconnect', $GLOBALS['egw_info']['server']['openidconnect_discovery'], 'submit', 'formmethod="get"');
	}

	/**
	 * Logout SSO system
	 */
	function logout()
	{
		$this->client->signOut($this->client->getIdToken(), null);
	}

	/**
	 * Return (which) parts of session needed by current auth backend
	 *
	 * If this returns any key(s), the session is NOT destroyed by Api\Session::destroy,
	 * just everything but the keys is removed.
	 *
	 * @return array of needed keys in session
	 */
	function needSession()
	{
		return ['openid_connect_state', 'openid_connect_nonce', 'openid_connect_code_verifier',
			Api\Session::EGW_APPSESSION_VAR];	// Auth stores backend via Cache::setSession()
	}

	/**
	 * password authentication against password stored in sql datababse
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type ='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		// add username and password
		$this->client->addAuthParam([
			'username' => $username,
			'password' => $passwd,
		]);
		// perform the auth and return the token (to validate check if the access_token property is there and a valid JWT) :
		try {
			$repsonse = $this->client->requestResourceOwnerToken(TRUE);
			if (empty($repsonse->access_token))
			{
				Api\Auth::log(__METHOD__."('$username', ...) OpenIDConnect Response: ".json_encode($repsonse)." returning FALSE");
				return false;
			}
			Api\Auth::log(__METHOD__."('$username', ...) returning TRUE");
			return true;
		}
		catch(OpenIDConnectClientException $e) {
			// ignore
			_egw_log_exception($e);
			Api\Auth::log(__METHOD__."('$username', ...) OpenIDConnect Error: ".$e->getMessage());
		}
		return false;
	}

	/**
	 * changes password in sql datababse
	 *
	 * @param string $old_passwd must be cleartext
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @throws Exception to give a verbose error, why changing password failed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		return false;
	}
}