<?php
/**
 * EGroupware Api: OpenIDConnectClient
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2013-22 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Auth;

use EGroupware\Api;
use Jumbojett\OpenIDConnectClientException;

if (!empty($GLOBALS['egw_info']['server']['cookie_samesite_attribute']) && $GLOBALS['egw_info']['server']['cookie_samesite_attribute'] === 'Strict')
{
	throw new Api\Exception("OAuth/OpenIDConnect requires SameSite cookie attribute other then 'Strict' set in Admin > Site configuration > Security > Cookies!");
}

/**
 * Extended OpenIDConnect client allowing to authenticate via some kind of promise, see authenticateThen method.
 *
 * It also uses https://proxy.egroupware.org/oauth as redirect-url to be registered with providers, implemented by the following Nginx location block:
 *
 * location /oauth {
 *  if ($arg_state ~ ^(?<redirect_host>[^&:%]+)(:|%3a)(?<redirect_path>[^&:%]+)(:|%3a)) {
 *      return 302 https://$redirect_host/$redirect_path/api/oauth.php?$args;
 *  }
 *  return 301 https://github.com/EGroupware/egroupware/blob/master/api/src/Auth/OpenIDConnectClient.php;
 * }
 *
 * This redirects requests to a host and path provided additional with the nonce query parameter:
 * https://proxy.egroupware.org/oauth?state=test.egroupware.org:test:<state>&<other-args> --> https://test.egroupware.org/egroupware/api/oauth.php?<all-arguments>
 *
 * @link https://oauth2-proxy.github.io/oauth2-proxy/docs/configuration/oauth_provider
 */
class OpenIDConnectClient extends \Jumbojett\OpenIDConnectClient
{
	const EGROUPWARE_OAUTH_PROXY = 'https://proxy.egroupware.org/oauth';

	public function __construct($provider_url = null, $client_id = null, $client_secret = null, $issuer = null)
	{
		parent::__construct($provider_url, $client_id, $client_secret, $issuer);

		// set https://proxy.egroupware.org/oauth as redirect URL, which redirects to host and path given in nonce parameter plus /api/oauth.php
		$this->setRedirectURL(self::EGROUPWARE_OAUTH_PROXY);
	}

	/**
	 * Stores nonce
	 *
	 * Reimplemented to add host and EGroupware path to the state, to allow proxy.egroupware.org to redirect to the correct host
	 *
	 * @param string $state
	 * @return string
	 */
	protected function setState($state)
	{
		// add host and EGroupware path to nonce
		$state = Api\Header\Http::host().':'.
			(explode('/', parse_url($GLOBALS['egw_info']['server']['webserver_url'] ?: '/', PHP_URL_PATH))[1] ?? '').
			':'.$state;

		return parent::setState($state);
	}

	/**
	 * OAuth/OpenIDConnect authenticate incl. redirecting to OIDC provider
	 *
	 * This method does NOT return, you have to provide a success and failure callback instead!
	 * The callbacks can NOT be closures, as they get serialized in the session, but you can use everything else eg. 'class::staticMethod' or [$obj, 'method'].
	 *
	 * @param callable $success success callback, first parameter is $oidc object/this containing the access and refresh token
	 * @param array $success_params further success callback parameters
	 * @param callable $failure failure callback, first parameter it the exception thrown or false, if authenticate returns false
	 * @param array $failure_params further failure parameters
	 */
	public function authenticateThen(callable $success, array $success_params=[], callable $failure=null, array $failure_params=[])
	{
		Api\Cache::setSession(__CLASS__, 'oidc', $this);
		Api\Cache::setSession(__CLASS__, 'authenticateThenParams', func_get_args());

		try {
			// authenticate might not return, because it redirected
			if ($this->authenticate())
			{
				array_unshift($success_params, $this);
				return call_user_func_array($success, $success_params);
			}
		}
		catch(OpenIDConnectClientException $e) {
			_egw_log_exception($e);
		}
		// authentication failure or exception
		array_unshift($failure_params, $e ?? false);
		call_user_func_array($failure, $failure_params);
	}

	/**
	 * Reimplemented to work with JSON requests too
	 *
	 * @param string $url
	 */
	public function redirect($url)
	{
		Api\Framework::redirect($url);
		exit;
	}

	/**
	 * Called by /api/oauth.php redirect url
	 *
	 * @return void
	 */
	public static function process()
	{
		if (empty($oidc = Api\Cache::getSession(__CLASS__, 'oidc')) ||
			!is_a($oidc, __CLASS__) ||
			!is_array(($authenticateThenParams = Api\Cache::getSession(__CLASS__, 'authenticateThenParams'))))
		{
			throw new OpenIDConnectClientException("Missing OpenIDConnectClient state!");
		}
		call_user_func_array([$oidc, 'authenticateThen'], $authenticateThenParams);
	}
}