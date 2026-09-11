<?php
/**
 * EGroupware Api: lazily-resolved OAuth access-token
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Credentials;

use EGroupware\Api\Mail\Credentials;

/**
 * Stand-in for an OAuth access-token string, resolving (refreshing the token via a real
 * HTTP request) only on first actual use, instead of eagerly whenever credentials are read.
 *
 * Credentials::read() runs on every Mail\Account construction, including ones that only ever
 * need config data (eg. rendering the mail app's account-tree labels) and never attempt a real
 * login - resolving the access-token there unconditionally cost a real OAuth refresh-token
 * HTTP round-trip (confirmed live 2026-08-26: ~1s against outlook.com) on every one of those,
 * not just the ones that actually connect.
 *
 * Both Horde_Imap_Client_Password_Xoauth2 and Horde_Smtp_Password_Xoauth2 take their
 * access-token constructor argument untyped and only ever consume it via string concatenation
 * in their own getPassword(), called deep inside the real login/SASL handshake - so an instance
 * of this class can be passed in their place unchanged: PHP's implicit __toString() coercion
 * resolves it transparently, exactly at the point (and only if) a real login is attempted.
 */
class AccessToken
{
	private ?string $resolved = null;

	public function __construct(
		private readonly string $username,
		private readonly string $refresh_token,
		private readonly ?string $mailserver,
		private readonly ?int $acc_id,
		private readonly ?int $account_id,
	)
	{
	}

	public function __toString() : string
	{
		return $this->resolved ??= (string)(Credentials::getAccessToken(
			$this->username, $this->refresh_token, $this->mailserver, $this->acc_id, $this->account_id
		) ?? '');
	}
}
