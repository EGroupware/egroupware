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
		foreach(array('smimeGenerate', 'smimeCertUpload', 'smime_import_passphrase') as $name)
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
}
