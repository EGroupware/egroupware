<?php
/**
 * EGroupware - Test ACL enforcement of the password-widget's ajax_decrypt()
 *
 * Regression coverage for commit f7efe77624 ("Password: verify decrypt requests match a
 * ciphertext we actually sent"). Before that commit, Etemplate\Widget\Password::ajax_decrypt()
 * decrypted WHATEVER ciphertext the caller supplied, gated only by the caller re-authenticating
 * with their own login password - an authenticated user who could obtain (or guess) any OTHER
 * stored credential's ciphertext could decrypt it themselves, without that ciphertext ever having
 * been exposed to them. The fix added a per-request whitelist (Request::allowPasswordDecrypt() /
 * isPasswordDecryptAllowed()), the same pattern Link::checkLinkAccess() already used - only a
 * ciphertext beforeSendToClient() actually sent to THIS user in THIS request may be decrypted.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once __DIR__ . '/../../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Etemplate\Request;
use EGroupware\Api\Mail\Credentials;

class PasswordDecryptAclTest extends LoggedInTest
{
	protected function tearDown() : void
	{
		// ajax_decrypt() calls Response::get()->data(), a singleton that only accepts one
		// data-response per instance - reset it so a leftover response can't break a later
		// test in this process (same gotcha as api/tests/Etemplate/Widget/LinkAclTest.php)
		Api\Json\Response::get()->initResponseArray();
		parent::tearDown();
	}

	/**
	 * A real SYSTEM_AES ciphertext for $plaintext, exactly the shape ajax_decrypt() tries first.
	 */
	protected function encrypt(string $plaintext) : string
	{
		$pw_enc = null;
		return Credentials::encrypt($plaintext, $GLOBALS['egw_info']['user']['account_id'], $pw_enc, true);
	}

	/**
	 * Simulate an etemplate-request that recorded $ciphertext as decrypt-allowed (what
	 * Password::beforeSendToClient() does for a 'viewable'/'togglePassword' widget), without
	 * needing to actually render a template.
	 */
	protected function whitelistCiphertext(string $ciphertext) : string
	{
		$request = Request::read();
		$request->allowPasswordDecrypt($ciphertext);
		$exec_id = $request->id();
		unset($request);	// force __destruct() to persist the request data
		return $exec_id;
	}

	/**
	 * @return string decrypted plaintext, or '' if ajax_decrypt() refused
	 */
	protected function decrypt(string $user_password, string $ciphertext, ?string $exec_id) : string
	{
		Password::ajax_decrypt($user_password, $ciphertext, $exec_id);
		$result = Api\Json\Response::get()->initResponseArray();
		return (string)($result[0]['data'] ?? '');
	}

	public function testDecryptDeniedWithoutExecId()
	{
		$ciphertext = $this->encrypt('s3cr3t-1');

		// no etemplate_exec_id at all - must fall through to the empty default, same as pre-fix
		// code would have decrypted this successfully with just the right own-password
		$this->assertSame('', $this->decrypt($GLOBALS['EGW_PASSWORD'], $ciphertext, null));
	}

	public function testDecryptDeniedWithForeignCiphertext()
	{
		$ciphertext = $this->encrypt('s3cr3t-2');
		$other_ciphertext = $this->encrypt('some-other-users-secret');

		// exec_id whitelists a DIFFERENT ciphertext than the one we try to decrypt
		$exec_id = $this->whitelistCiphertext($other_ciphertext);

		$this->assertSame('', $this->decrypt($GLOBALS['EGW_PASSWORD'], $ciphertext, $exec_id));
	}

	public function testDecryptDeniedWithWrongOwnPassword()
	{
		$ciphertext = $this->encrypt('s3cr3t-3');
		$exec_id = $this->whitelistCiphertext($ciphertext);

		$this->assertSame('', $this->decrypt('definitely-the-wrong-password', $ciphertext, $exec_id));
	}

	public function testDecryptAllowedViaWhitelistedExecId()
	{
		$ciphertext = $this->encrypt('s3cr3t-4');
		$exec_id = $this->whitelistCiphertext($ciphertext);

		$this->assertSame('s3cr3t-4', $this->decrypt($GLOBALS['EGW_PASSWORD'], $ciphertext, $exec_id));
	}
}
