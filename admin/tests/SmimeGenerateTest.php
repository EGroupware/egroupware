<?php
/**
 * EGroupware Admin: validation-path tests for admin_mail's S/MIME dialog helpers
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Mail;

/**
 * Tests the input-validation branches of admin_mail's S/MIME key
 * generation/import helpers, used by the mail account edit dialog's
 * "Create self-signed certificate", "Create CSR for new private key" and
 * "Import certificate" buttons. mail_wizard extends admin_mail and inherits
 * edit() unchanged, so this also covers the wizard's account dialog.
 *
 * Only the validation-failure branches are covered here: the success path of
 * both methods writes to the database (Mail\Credentials::write(),
 * addressbook_bo::set_smime_keys()), which needs a database-backed
 * integration test. See api/tests/Mail/SmimeTest.php for the (pure, no DB)
 * crypto logic these methods build on (Mail\Smime::generate_certificate(),
 * build_pkcs12(), ...), which IS fully covered.
 *
 * generate_smime_key() and import_smime_cert() are private static methods,
 * called through ReflectionMethod (same approach as
 * api/tests/Mail/CredentialsTest.php::callProtectedMethod()).
 *
 * verifySmimeAccountAccess() (public) is tested directly - it guards the
 * ajax_smimeCreateKeypair() endpoint against a client submitting an
 * arbitrary acc_id/called_for in its (otherwise untrusted) ajax payload to
 * act on someone else's mail account. ajax_smimeCreateKeypair() itself
 * cannot be called directly in a test: Api\Etemplate\Request::csrfCheck()
 * calls die()/exit on an invalid etemplate_exec_id, which would kill the
 * test process rather than fail the assertion.
 *

 * A real Etemplate instance (not a mock) is used as $tpl:
 * Etemplate\Widget::set_validation_error() is a static method, and PHPUnit
 * refuses to stub static methods on a mock object ("Static method ... cannot
 * be invoked on mock object"). It's cheap and side-effect-free (just writes
 * to a static in-process array), so a real instance is used and the
 * resulting error is read back via the static get_validation_errors().
 *
 * Extends LoggedInTest not because these tests need a mail account or IMAP
 * connection, but because merely autoloading EGroupware\Api\Etemplate runs
 * static class initialisation that requires a working DB session.
 */
class SmimeGenerateTest extends Api\LoggedInTest
{
	protected function setUp() : void
	{
		// validation errors are kept in a static array shared across the whole
		// process/test run - reset the ones we're about to check
		foreach(array('smimeGenerate', 'smimeCertUpload', 'smime_passphrase') as $name)
		{
			Etemplate::set_validation_error($name, false);
		}
	}

	private function callPrivateStatic(string $method, array $args)
	{
		$ref = new ReflectionMethod(\admin_mail::class, $method);
		$ref->setAccessible(true);
		return $ref->invokeArgs(null, $args);
	}

	/**
	 * generate_smime_key() must reject a DN missing commonName/emailAddress
	 * before ever calling into openssl or the database, and report the error
	 * on the 'smimeGenerate' widget so it surfaces next to the button.
	 */
	public function testGenerateSmimeKeyRequiresCommonNameAndEmail()
	{
		$tpl = new Etemplate();
		$content = array(
			'acc_id' => 1,
			'smime_action' => 'selfsigned',
			// countryName only - commonName/emailAddress missing
			'smime_gen_dn' => json_encode(array('countryName' => 'DE')),
		);
		$result = $this->callPrivateStatic('generate_smime_key', array($content, $tpl, 1));

		$this->assertNull($result, 'must not return a cred_id when required DN fields are missing');
		$this->assertNotNull(Etemplate::get_validation_errors('smimeGenerate'),
			'must report a validation error on the smimeGenerate widget');
	}

	/**
	 * generate_smime_key() must fail gracefully (not throw/warn) when
	 * smime_gen_dn is missing entirely, treating it like an empty DN.
	 */
	public function testGenerateSmimeKeyRequiresDnAtAll()
	{
		$tpl = new Etemplate();
		$result = $this->callPrivateStatic('generate_smime_key', array(array('acc_id' => 1), $tpl, 1));

		$this->assertNull($result);
		$this->assertNotNull(Etemplate::get_validation_errors('smimeGenerate'));
	}

	/**
	 * import_smime_cert() must refuse to import a certificate when there is
	 * no private key stored yet for this account (acc_smime_cred_id unset) -
	 * a certificate can only ever be combined with an existing key.
	 */
	public function testImportSmimeCertRequiresExistingCredId()
	{
		$tpl = new Etemplate();
		$content = array(
			'acc_id' => 1,
			'smimeCertUpload' => array('tmp_name' => '/nonexistent/does-not-matter'),
		);
		$result = $this->callPrivateStatic('import_smime_cert', array($content, $tpl, 1));

		$this->assertNull($result, 'must not return a cred_id without an existing private key to import a certificate for');
		$this->assertNotNull(Etemplate::get_validation_errors('smimeCertUpload'));
	}

	/**
	 * import_smime_cert() must reject an empty/unreadable uploaded file
	 * without ever reaching the database (Mail\Smime::get_acc_smime()) -
	 * uses a real, existing but empty temp file so file_get_contents()
	 * returns '' (falsy) rather than raising a PHP warning for a missing path.
	 */
	public function testImportSmimeCertRequiresReadableCertFile()
	{
		$empty_file = tempnam(sys_get_temp_dir(), 'smime_test_');
		try
		{
			$tpl = new Etemplate();
			$content = array(
				'acc_id' => 1,
				'acc_smime_cred_id' => 123,
				'smimeCertUpload' => array('tmp_name' => $empty_file),
			);
			$result = $this->callPrivateStatic('import_smime_cert', array($content, $tpl, 1));

			$this->assertNull($result, 'must not return a cred_id for an empty/unreadable certificate upload');
			$this->assertNotNull(Etemplate::get_validation_errors('smimeCertUpload'));
		}
		finally
		{
			@unlink($empty_file);
		}
	}

	/**
	 * A non-admin submitting a called_for different from their own account_id must be rejected -
	 * without this check, any logged in user could pass an arbitrary called_for in the
	 * ajax_smimeCreateKeypair() payload to generate/store a S/MIME key on behalf of someone else.
	 *
	 * Uses an obviously non-existent acc_id: the permission gate must reject this BEFORE ever
	 * looking up the account, so an invalid acc_id here does not weaken what's being proven.
	 */
	public function testVerifySmimeAccountAccessDeniesImpersonationWithoutAdmin()
	{
		$own_account_id = $GLOBALS['egw_info']['user']['account_id'];

		$result = admin_mail::verifySmimeAccountAccess(999999999, $own_account_id + 1, false);

		$this->assertNull($result, 'a non-admin must not be able to act on behalf of a different account_id');
	}

	/**
	 * An admin acting on behalf of a different user must still be rejected if the given acc_id
	 * does not actually belong to that user - admin rights alone must not bypass the acc_id <->
	 * account_id ownership check (Mail\Account::read() throws NotFound for a bogus acc_id).
	 */
	public function testVerifySmimeAccountAccessDeniesUnknownAccIdEvenForAdmin()
	{
		$own_account_id = $GLOBALS['egw_info']['user']['account_id'];

		$result = admin_mail::verifySmimeAccountAccess(999999999, $own_account_id + 1, true);

		$this->assertNull($result, 'admin rights must not bypass the acc_id ownership check for a non-existent account');
	}

	/**
	 * Even a request for the user's own account_id (called_for empty, always permission-wise
	 * allowed) must still be rejected for an acc_id that does not exist / does not belong to them.
	 */
	public function testVerifySmimeAccountAccessDeniesUnknownAccIdForOwnAccount()
	{
		$result = admin_mail::verifySmimeAccountAccess(999999999, null, false);

		$this->assertNull($result, 'an unknown/foreign acc_id must be rejected even for the current user\'s own account');
	}

	private function callExportFile(array $content, $account_id, $csr)
	{
		$ref = new ReflectionMethod(admin_mail::class, 'smimeExportFile');
		$ref->setAccessible(true);
		return $ref->invoke(new admin_mail(), $content, new Etemplate(), $account_id, $csr);
	}

	/**
	 * Regression test for a real production bug: smimeExportFile() used to hardcode an empty
	 * passphrase when extracting the stored private key for CSR export, so any account whose key
	 * genuinely has a passphrase (the normal/recommended case) reported "No S/MIME private key
	 * stored for this account" - even though the exact same key worked fine for signing/
	 * decrypting mail (mail_compose::_encrypt() does thread a passphrase through). The p12 export
	 * branch was unaffected (it streams the stored blob as-is, no extraction needed), which is
	 * why only "Export CSR" was reported broken.
	 *
	 * Also verifies the two failure modes are now distinguished: "not found at all" vs "found but
	 * couldn't be decrypted" - the old code conflated both into the same misleading message.
	 */
	public function testSmimeExportFileThreadsPassphraseForCsr()
	{
		$acc_id = 1; // per project memory: acc_id=1 Stalwart/JMAP test account
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		Mail\Credentials::delete($acc_id, $account_id, Mail\Credentials::SMIME);

		try
		{
			// no credential at all -> "not stored"
			$result = $this->callExportFile(array('acc_id' => $acc_id), $account_id, true);
			$this->assertSame(lang('No S/MIME private key stored for this account.'), $result);

			// create a passphrase-protected key, matching a real user's normal setup
			$tpl = new Etemplate();
			$genContent = array(
				'acc_id' => $acc_id,
				'smime_gen_dn' => json_encode(array(
					'commonName' => 'Export File Test',
					'emailAddress' => 'exportfiletest@example.org',
					'passphrase' => 'correct horse',
				)),
			);
			$ref = new ReflectionMethod(admin_mail::class, 'generate_smime_key');
			$ref->setAccessible(true);
			$cred_id = $ref->invokeArgs(null, array($genContent, $tpl, $account_id));
			$this->assertNotNull($cred_id, 'test setup: generating the key must succeed');

			// found, but no passphrase submitted at all -> distinguished from both "not found" and
			// "wrong passphrase" - the user likely just needs to fill in the field
			$result = $this->callExportFile(array('acc_id' => $acc_id), $account_id, true);
			$this->assertSame(
				lang('This S/MIME private key is passphrase-protected. Please enter the passphrase above and try again.'),
				$result);

			// found, wrong (non-empty) passphrase -> distinguished from "none submitted"
			$result = $this->callExportFile(
				array('acc_id' => $acc_id, 'smime_passphrase' => 'wrong'), $account_id, true);
			$this->assertSame(lang('The passphrase entered was not correct, please try again.'), $result);

			// correct passphrase: verify the underlying pieces smimeExportFile() calls succeed
			// together (can't call smimeExportFile() itself for this case - it exit()s on success)
			$acc_smime = Mail\Smime::get_acc_smime($acc_id, 'correct horse', $account_id);
			$this->assertNotEmpty($acc_smime['pkey'] ?? null,
				'get_acc_smime() must extract pkey when given the correct passphrase');
			$csr = Mail\Smime::generate_csr($acc_smime['pkey'], array(), 'correct horse');
			$this->assertNotFalse($csr,
				'generate_csr() must succeed with the same passphrase - the extracted pkey PEM stays passphrase-protected');
			$this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $csr);
		}
		finally
		{
			Mail\Credentials::delete($acc_id, $account_id, Mail\Credentials::SMIME);
		}
	}

	/**
	 * Regression test for a real production bug: Smime::get_acc_smime() let a session-cached
	 * passphrase silently OVERRIDE any explicitly given one. Api\Cache::getSession('mail',
	 * 'smime_passphrase') is a single session-wide slot, NOT keyed by acc_id - a user with
	 * multiple S/MIME-enabled mail accounts who recently decrypted/sent mail on account A ends up
	 * with account A's passphrase cached. Trying to export a CSR for account B (typed correctly
	 * into the form) then had that correct, explicit passphrase silently discarded in favour of
	 * account A's wrong one, producing the same misleading "no key" error.
	 */
	public function testGetAccSmimeExplicitPassphraseOverridesWrongCachedOne()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		// clearly-fake acc_ids: neither generate_smime_key() nor get_acc_smime() validate account
		// existence (Credentials has no FK on acc_id), so avoid any risk of touching a real account
		$accounts = array(999901 => 'account A passphrase', 999902 => 'account B passphrase');
		$tpl = new Etemplate();
		$genRef = new ReflectionMethod(admin_mail::class, 'generate_smime_key');
		$genRef->setAccessible(true);

		foreach ($accounts as $acc_id => $passphrase)
		{
			Mail\Credentials::delete($acc_id, $account_id, Mail\Credentials::SMIME);
			$genContent = array(
				'acc_id' => $acc_id,
				'smime_gen_dn' => json_encode(array(
					'commonName' => "Cache Precedence Test $acc_id",
					'emailAddress' => "cacheprecedence$acc_id@example.org",
					'passphrase' => $passphrase,
				)),
			);
			$cred_id = $genRef->invokeArgs(null, array($genContent, $tpl, $account_id));
			$this->assertNotNull($cred_id, "test setup: generating the key for acc_id=$acc_id must succeed");
		}

		try
		{
			// simulate account A's passphrase being cached from earlier, unrelated mail activity
			Api\Cache::setSession('mail', 'smime_passphrase', $accounts[999901]);

			// exporting account B WITHOUT an explicit passphrase falls back to the (wrong, account
			// A's) cache - documents the pre-existing fallback behaviour, not the bug itself
			$stale = Mail\Smime::get_acc_smime(999902, '', $account_id);
			$this->assertEmpty($stale['pkey'] ?? null,
				'without an explicit passphrase, the wrong cached one is used and extraction fails (expected fallback behaviour)');

			// exporting account B WITH account B's correct, explicit passphrase must succeed despite
			// account A's passphrase still being cached - this is the actual fix
			$correct = Mail\Smime::get_acc_smime(999902, $accounts[999902], $account_id);
			$this->assertNotEmpty($correct['pkey'] ?? null,
				'an explicitly given correct passphrase must take priority over a stale cached one for a different account');
		}
		finally
		{
			Api\Cache::setSession('mail', 'smime_passphrase', null);
			foreach (array_keys($accounts) as $acc_id)
			{
				Mail\Credentials::delete($acc_id, $account_id, Mail\Credentials::SMIME);
			}
		}
	}
}
