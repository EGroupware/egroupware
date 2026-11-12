<?php
/**
 * EGroupware API: verify a pre-existing, too-short Google2FA secret
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage security
 */

namespace EGroupware\Api\Auth;

use PragmaRX\Google2FA\Google2FA;

/**
 * pragmarx/google2fa (since v4) refuses to verify or generate a secret shorter than 16 base32
 * characters (128 bits), throwing SecretKeyTooShortException. Accounts whose secret was created
 * by an older version/library can have a shorter one, which would otherwise lock them out of
 * login after an update. This subclass drops that length check, for verifying such an existing
 * secret only - a new secret is always generated at the regular length by
 * Google2FA::generateSecretKey(), so this is only reached as a verify() fallback, never for
 * generating or storing a new one.
 */
class LegacyGoogle2FA extends Google2FA
{
	protected function checkIsBigEnough($b32)
	{
		// no-op: skip the 128-bit minimum enforced by the parent
	}

	public function oathTotp($secret, $counter)
	{
		$secret = $this->base32Decode($this->getSecret($secret));

		return str_pad(
			$this->oathTruncate($this->generateHotp($secret, $counter)),
			$this->getOneTimePasswordLength(),
			'0',
			STR_PAD_LEFT
		);
	}
}
