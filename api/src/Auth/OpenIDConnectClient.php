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
 *  if ($arg_state ~* ^(?<redirect_host>[^&:%]+)(:|%3a)(?<redirect_path>[^&:%]+)(:|%3a)) {
 *      return 302 https://$redirect_host/$redirect_path/api/oauth.php?$args;
 *  }
 *  return 301 https://github.com/EGroupware/egroupware/blob/master/api/src/Auth/OpenIDConnectClient.php;
 * }
 *
 * This redirects requests to a host and path provided additional with the nonce query parameter:
 * https://proxy.egroupware.org/oauth?state=test.egroupware.org:test:<state>&<other-args> --> https://test.egroupware.org/egroupware/api/oauth.php?<all-arguments>
 *
 * @link https://oauth2-proxy.github.io/oauth2-proxy/docs/configuration/oauth_provider
 * @link https://github.com/mozilla/releases-comm-central/blob/master/mailnews/base/src/OAuth2Providers.jsm
 */
class OpenIDConnectClient extends \Jumbojett\OpenIDConnectClient
{
	const EGROUPWARE_OAUTH_PROXY = 'https://proxy.egroupware.org/oauth';

	const ADD_CLIENT_TO_WELL_KNOWN = 'add-client-to-well-known';
	const ADD_AUTH_PARAM = 'add-auth-param';

	/**
	 * Regular expressions to match domain in username to imap/smtp servers and oauth provider
	 *
	 * @var array[] email-regexp => [imap-host, smtp-host, oauth-provider, client-id, client-secret, scopes] pairs
	 */
	public static $oauth_domain_regexps = [
		// MS domains from https://www.internetearnings.com/how-to-register-live-or-hotmail-e-mail-address/
		'/(^|@)([^.@]+\.onmicrosoft\.com|'.
				'outlook\.(sa|com|com\.(ar|au|cz|gr|in|tw|tr|vn)|co\.(in|th)|at|cl|fr|de|hu|ie|it|jp|kr|lv|my|ph|pt|sg|sk|es)|'.
				'hotmail\.(com|com\.(ar|au|br|hk|tr|vn)|co\.(in|il|jp|kr|za|th|uk)|be|ca|cz|cl|dk|fi|fr|gr|de|hu|it|lv|lt|my|nl|no|ph|rs|sg|sk|es|se)|'.
				'live\.(com|com\.(ar|br|my|mx|ph|pt|sg)|co\.(il|kr|za|uk)|at|be|ca|cl|cn|dk|fi|fr|de|hk|ie|it|jp|nl|no|ru|se)|'.
				'windowslive\.com|livemail\.tw)$/i' => ['outlook.office365.com', 'smtp.office365.com', 'login.microsoftonline.com/common/v2.0',
			'e09fe57b-ffc5-496e-9ef8-3e6c7d628c09', 'Hd18Q~t-8_-ImvPFXlh8DSFjWKYyvpUTqURRJc7i',
			'https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send offline_access email',
			[/*self::ADD_CLIENT_TO_WELL_KNOWN => 'appid',*/ self::ADD_AUTH_PARAM => ['login_hint' => '$username', 'approval_prompt' => 'auto']],
			null],
		'/(^|@)g(oogle)?mail\.com$/i' => ['imap.gmail.com', 'smtp.gmail.com', 'accounts.google.com',
			'581021931838-unqjf9tivr9brnmo34rbsoj179ojp79p.apps.googleusercontent.com', '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',
			'https://mail.google.com/ https://www.googleapis.com/auth/userinfo.email',
			// https://stackoverflow.com/questions/10827920/not-receiving-google-oauth-refresh-token
			[self::ADD_AUTH_PARAM => ['access_type' => 'offline', 'prompt' => 'consent']],
			'/^(imap|smtp|mail)\.g(oogle)?mail\.com$/i'],
	];

	public function __construct($provider_url = null, $client_id = null, $client_secret = null, $issuer = null)
	{
		parent::__construct($provider_url, $client_id, $client_secret, $issuer);

		// set https://proxy.egroupware.org/oauth as redirect URL, which redirects to host and path given in nonce parameter plus /api/oauth.php
		$this->setRedirectURL(self::EGROUPWARE_OAUTH_PROXY);

		// set proxy, if configured in EGroupware
		if (!empty($GLOBALS['egw_info']['server']['httpproxy_server']))
		{
			$this->setHttpProxy('http://'.(!empty($GLOBALS['egw_info']['server']['httpproxy_server_username']) ?
				urlencode($GLOBALS['egw_info']['server']['httpproxy_server_username']).':'.urlencode($GLOBALS['egw_info']['server']['httpproxy_server_password']).'@' : '').
				$GLOBALS['egw_info']['server']['httpproxy_server'].':'.($GLOBALS['egw_info']['server']['httpproxy_port'] ?? 8080).'/');
		}

		// login.microsoftonline.com/common returns as issuer an URL with {tenantid}
		if ($this->getProviderURL() === 'https://login.microsoftonline.com/common/v2.0')
		{
			$this->setIssuerValidator(new MicrosoftIssuerValidator($this));
		}
	}

	/**
	 * Find server config by email-domain incl. oauth data
	 *
	 * @param string $domain domain or email address
	 * @param string $mailserver option name of imap or smtp server to identify the provider
	 * @return array|null for keys provider, client, secret, scopes, imap, smtp
	 */
	public static function providerByDomain($domain, $mailserver=null)
	{
		foreach(self::$oauth_domain_regexps as $regexp => [$imap, $smtp, $provider, $client, $secret, $scopes, $extra, $server_regexp])
		{
			if (preg_match($regexp, $domain, $matches) ||
				!empty($mailserver) && (in_array($mailserver, [$imap, $smtp]) || !empty($server_regexp) && preg_match($server_regexp, $mailserver)))
			{
				return [
						'imap' => $imap,
						'smtp' => $smtp,
						'provider' => $provider ? 'https://'.strtr($provider, ['$1' => $matches[1] ?? 'common', '$2' => $matches[2] ?? 'common']): null,
						'client' => $client,
						'secret' => $secret,
						'scopes' => array_merge(['openid'], explode(' ', $scopes)),
					]+($extra ?? []);
			}
		}
		return null;
	}

	/**
	 * Get OIDC client object for the given domain/email
	 *
	 * @param string $domain domain or email address
	 * @param string|null $mailserver
	 * @return self|null
	 */
	public static function byDomain($domain, $mailserver=null)
	{
		if (!($provider = self::providerByDomain($domain, $mailserver)))
		{
			return null;
		}
		$oidc = new self($provider['provider'], $provider['client'], $provider['secret']);

		// we need to use response_code=query / GET request to keep our session token!
		$oidc->setResponseTypes(['code']);  // to be able to use query, not 'id_token'

		// Office365 requires client-ID as appid GET parameter (https://github.com/jumbojett/OpenID-Connect-PHP/issues/190)
		if (!empty($provider[OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN]))
		{
			$oidc->setWellKnownConfigParameters([$provider[OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN] => $provider['client']]);
		}

		// Google requires access_type=offline to return a refresh-token
		if (!empty($provider[self::ADD_AUTH_PARAM]))
		{
			$oidc->addAuthParam(str_replace('$username', $domain, $provider[self::ADD_AUTH_PARAM]));
		}

		$oidc->addScope($provider['scopes']);

		return $oidc;
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
				$_GET['cd'] = 'popup';  // let framework know we are a popup
				array_unshift($success_params, $this);
				return call_user_func_array($success, $success_params);
			}
		}
		catch(OpenIDConnectClientException $e) {
			_egw_log_exception($e);
		}
		$_GET['cd'] = 'popup';  // let framework know we are a popup
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

/**
 * login.microsoftonline.com/common returns as issuer an URL with {tenantid}
 *
 * We currently only check the there is some reasonable tenantid, not necessary the correct one, for which we would need to know the tenant.
 */
class MicrosoftIssuerValidator
{
	/**
	 * @var OpenIDConnectClient
	 */
	private $oidc;

	public function __construct(OpenIDConnectClient $oidc)
	{
		$this->oidc = $oidc;
	}

	/**
	 * Validator for Microsoft issuer
	 *
	 * @param string $iss
	 * @return bool
	 * @throws OpenIDConnectClientException
	 */
	public function __invoke($iss)
	{
		$issuer_regexp = '#^'.str_replace('{tenantid}', '[a-f0-9-]+', $this->oidc->getWellKnownIssuer()).'$#';

		return (bool)preg_match($issuer_regexp, $iss);
	}
}