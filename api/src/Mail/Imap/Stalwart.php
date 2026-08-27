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

			// JMAP-FALLTHROUGH-GUARD (see [[project_jmap_imap_fallthrough_cleanup]]):
			// a successful JMAP access-token grant already proves this account's credentials
			// and connectivity - calling parent::login() (real Horde raw IMAP socket) on top of
			// that falls through to Horde_Imap_Client_Socket, which for an account whose
			// acc_imap_port is actually Stalwart's JMAP(S)/admin endpoint (not a real IMAP port,
			// eg. a wizard-created pure-JMAP account) completes the TCP/TLS handshake fine, then
			// hangs waiting for an IMAP greeting banner the peer never sends, until the
			// connection's read-timeout (default 20s, Account::imapServer()) expires. Confirmed
			// live 2026-08-26 against acc_id=1 (acc_imap_port=443): added ~20s to every
			// mail_ui::index() reload via is_imap()'s connectivity check. Returning here skips
			// that real socket attempt (and Mail\Imap::login()'s cert-verification-upgrade/
			// run_on_login extras) whenever JMAP itself already proved login works; only an
			// account whose JMAP token grant fails (eg. MFA required) still falls through to the
			// real IMAP login below, same as before this fix.
			return;
		}
		parent::login();
	}

	/**
	 * Return Jmap client, using a Bearer access-token instead of Basic auth, if we have one
	 *
	 * @return Api\Mail\Jmap\Http
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
		$ttl = max(60, ($response['expires_in'] ?? 3600) - self::TOKEN_EXPIRY_MARGIN);

		Api\Cache::setInstance(__CLASS__, 'access:'.$cache_key, $response['access_token'], $ttl);
		// cached alongside the token (same TTL), so clients asking for a bootstrap payload
		// can report a remaining expires_in, without us storing the token twice
		Api\Cache::setInstance(__CLASS__, 'access_expires:'.$cache_key, time() + $ttl, $ttl);

		if (!empty($response['refresh_token']))
		{
			Api\Cache::setInstance(__CLASS__, 'refresh:'.$cache_key, $response['refresh_token'], self::REFRESH_TOKEN_TTL);
		}
		return $response['access_token'];
	}

	/**
	 * Bootstrap payload for browser clients that want to talk to Stalwart's JMAP API directly
	 *
	 * Never sends the refresh-token or password to the client: only a short-lived access-token
	 * plus the public session-discovery URL and JMAP accountId needed to use it. Callers are
	 * expected to re-request this shortly before expires_in elapses, instead of reacting to a
	 * 401, so the refresh-token stays server-side at all times.
	 *
	 * @return array|null null if not eligible (admin/master connection, or no token available)
	 *  otherwise values for keys "sessionUrl", "accountId", "access_token", "expires_in"
	 */
	public function jmapBootstrap() : ?array
	{
		if ($this->isAdminConnection || !($access_token = $this->accessToken()))
		{
			return null;
		}
		$cache_key = $this->acc_id.':'.$this->acc_imap_username;
		$jmap = parent::jmapClient();

		return [
			'sessionUrl' => $jmap->sessionUrl(),
			'accountId' => $jmap->accountId,
			'access_token' => $access_token,
			'expires_in' => max(0, (int)Api\Cache::getInstance(__CLASS__, 'access_expires:'.$cache_key) - time()),
		];
	}
}
