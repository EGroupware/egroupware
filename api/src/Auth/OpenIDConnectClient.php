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
 */
class OpenIDConnectClient extends \Jumbojett\OpenIDConnectClient
{
	public function __construct($provider_url = null, $client_id = null, $client_secret = null, $issuer = null)
	{
		parent::__construct($provider_url, $client_id, $client_secret, $issuer);

		// set correct redirect URL, which is NOT the current URL, but always /api/oauth.php
		$this->setRedirectURL(Api\Framework::getUrl(Api\Framework::link('/api/oauth.php')));
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