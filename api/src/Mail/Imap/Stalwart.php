<?php
/**
 * EGroupware Api: Stalwart mail-server using OAuth token instead of password authentication
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2026 by Ralf Becker <rb-AT-egroupware.org>
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Imap;

use EGroupware\Api;

/**
 * Stalwart mail-server, authenticating with a short-lived OAuth access-token instead of
 * checking the (bcrypt, therefore expensive) password on every single IMAP or JMAP request.
 *
 * We authenticate once with username/password (see Mail\Jmap::passwordGrant()) to obtain an
 * access- and refresh-token, cache both, and use the access-token (refreshing it as needed)
 * for further IMAP (XOAUTH2) and JMAP (Bearer) requests. Falls back transparently to regular
 * password authentication, if no token could be obtained, e.g. because the account has MFA
 * enabled, or requireClientRegistration is set on the Stalwart server.
 *
 * Non-user (admin/master) connections keep using the password directly, same as regular
 * OAuth accounts in Mail\Imap, as the master-user login trick (self::MASTER_SEPARATOR) has
 * no equivalent in Stalwart's token based authentication.
 */
class Stalwart extends Jmap
{
	/**
	 * Label shown in EMailAdmin
	 */
	const DESCRIPTION = 'Stalwart/JMAP (token auth)';

	/**
	 * Give cached access-tokens some margin before their real expiry
	 */
	const TOKEN_EXPIRY_MARGIN = 60;

	/**
	 * How long to keep a refresh-token cached, before requiring a fresh password login
	 */
	const REFRESH_TOKEN_TTL = 30*24*60*60;	// 30 days

	/**
	 * Reimplemented to authenticate via a Stalwart OAuth access-token instead of the plain
	 * password, to avoid the cost of an extra bcrypt check on every connection.
	 *
	 * @throws \Horde_Imap_Client_Exception
	 */
	public function login()
	{
		if (!$this->isAdminConnection && ($access_token = $this->accessToken()))
		{
			$this->setParam('xoauth2_token',
				new \Horde_Imap_Client_Password_Xoauth2($this->acc_imap_username, $access_token));
		}
		parent::login();
	}

	/**
	 * Return Jmap client, using a Bearer access-token instead of Basic auth, if we have one
	 *
	 * @return Api\Mail\Jmap
	 */
	public function jmapClient()
	{
		$jmap = parent::jmapClient();

		if (!$this->isAdminConnection && ($access_token = $this->accessToken()))
		{
			$jmap->setBearerToken($access_token);
		}
		return $jmap;
	}

	/**
	 * Get a valid access-token: cached one, or refreshed, or a fresh password login, in that
	 * order. Tokens are only cached (NOT stored in the credentials table), so a cache-flush
	 * just costs one extra password based login.
	 *
	 * Never throws: any failure (network, wrong credentials, MFA required, server does not
	 * support this login flow) results in null, so callers transparently fall back to using
	 * the plain password for authentication.
	 *
	 * @return string|null
	 */
	protected function accessToken() : ?string
	{
		try {
			$cache_key = $this->acc_id.':'.$this->acc_imap_username;

			if (($token = Api\Cache::getInstance(__CLASS__, 'access:'.$cache_key)))
			{
				return $token;
			}
			$jmap = parent::jmapClient();

			if (($refresh_token = Api\Cache::getInstance(__CLASS__, 'refresh:'.$cache_key)) &&
				($response = $jmap->refreshToken($refresh_token)))
			{
				return $this->cacheToken($cache_key, $response);
			}
			if (($response = $jmap->passwordGrant($this->acc_imap_username, $this->acc_imap_password)))
			{
				return $this->cacheToken($cache_key, $response);
			}
		}
		catch (\Throwable $e) {
			_egw_log_exception($e);
		}
		return null;
	}

	/**
	 * Cache access- and refresh-token from a Stalwart /auth/token response
	 *
	 * @param string $cache_key
	 * @param array $response values for keys "access_token", "refresh_token", "expires_in"
	 * @return string access_token
	 */
	protected function cacheToken(string $cache_key, array $response) : string
	{
		Api\Cache::setInstance(__CLASS__, 'access:'.$cache_key, $response['access_token'],
			max(60, ($response['expires_in'] ?? 3600) - self::TOKEN_EXPIRY_MARGIN));

		if (!empty($response['refresh_token']))
		{
			Api\Cache::setInstance(__CLASS__, 'refresh:'.$cache_key, $response['refresh_token'], self::REFRESH_TOKEN_TTL);
		}
		return $response['access_token'];
	}
}
