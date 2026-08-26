<?php
/**
 * EGroupware Admin: pure-logic tests for admin_mail's Mail Wizard helper methods
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

use EGroupware\Api\Auth\OpenIDConnectClient;
use EGroupware\Api\Mail;

/**
 * Tests the pure, side-effect-free helper methods used by the Mail Wizard
 * (admin_mail, extended unchanged by mail_wizard - see admin/tests/SmimeGenerateTest.php's
 * docblock) to prepare data for its Etemplate steps and for Mail\Account::write().
 *
 * All methods under test here are protected/private static, called via ReflectionMethod
 * (same approach as SmimeGenerateTest::callPrivateStatic()). None of them touch the
 * database, network, or Etemplate, so this extends plain TestCase - no EGroupware
 * session is needed.
 *
 * adminReadonlyFields() and normalizeAccountType() were extracted out of admin_mail::edit()
 * specifically to make its is_multiple()-driven branching unit-testable without a full
 * Etemplate render; the extraction is behaviour-preserving (verbatim logic, only moved).
 *
 * NOT covered here: autoconfig()/sieve()/smtp()/add()/folder()/edit() end-to-end (they all
 * end in Etemplate::exec()); the OAuth redirect/token-exchange flow; ajax_* endpoints (they
 * die() on an invalid etemplate_exec_id, see SmimeGenerateTest.php). See
 * doc/ai/projects/mail-wizard-jmap-oauth.md for the full test-coverage map.
 */
class AdminMailPureLogicTest extends \PHPUnit\Framework\TestCase
{
	private function callPrivateStatic(string $method, array $args)
	{
		$ref = new ReflectionMethod(\admin_mail::class, $method);
		$ref->setAccessible(true);
		return $ref->invokeArgs(null, $args);
	}

	/**
	 * TLS/SSL/STARTTLS keys, given in arbitrary input order, must come out first in that
	 * exact fixed order - callers rely on this to try the most secure connection first.
	 */
	public function testFixSslOrderPutsTlsSslStarttlsFirstInFixedOrder()
	{
		$data = array('STARTTLS' => 143, 'SSL' => 993, 'TLS' => 993);
		$result = $this->callPrivateStatic('fix_ssl_order', array($data));

		$this->assertSame(array('TLS', 'SSL', 'STARTTLS'), array_keys($result));
	}

	/**
	 * Keys outside the fixed TLS/SSL/STARTTLS list (eg. 'insecure', 'username') must keep
	 * their relative order, appended after the fixed three.
	 */
	public function testFixSslOrderPreservesRemainingKeysOriginalOrder()
	{
		$data = array('username' => 'foo@example.org', 'insecure' => 143, 'SSL' => 993);
		$result = $this->callPrivateStatic('fix_ssl_order', array($data));

		$this->assertSame(array('SSL', 'username', 'insecure'), array_keys($result));
	}

	/**
	 * An empty input must return an empty array without warnings.
	 */
	public function testFixSslOrderWithEmptyArrayReturnsEmptyArray()
	{
		$result = $this->callPrivateStatic('fix_ssl_order', array(array()));

		$this->assertSame(array(), $result);
	}

	/**
	 * oauth2content() must map every OpenIDConnectClient::providerByDomain()-shaped field
	 * onto the wizard's acc_* content keys, and always disable Sieve (OAuth providers in
	 * practice don't support it). Also documents the pre-existing 'acc_smpt_host' typo
	 * (not 'acc_smtp_host') as production behaviour - not fixed here, out of scope for a
	 * test-only change.
	 */
	public function testOauth2ContentMapsProviderFieldsOntoAccContent()
	{
		$oauth = array(
			'imap' => 'imap.example.org',
			'smtp' => 'smtp.example.org',
			'provider' => 'https://example.org/.well-known/openid-configuration',
			'client' => 'client-id',
			'secret' => 'client-secret',
			'scopes' => array('mail'),
		);
		$result = $this->callPrivateStatic('oauth2content', array($oauth));

		$this->assertSame($oauth['smtp'], $result['acc_smpt_host']);
		$this->assertFalse($result['acc_sieve_enabled']);
		$this->assertSame($oauth['provider'], $result['acc_oauth_provider_url']);
		$this->assertSame($oauth['client'], $result['acc_oauth_client_id']);
		$this->assertSame($oauth['secret'], $result['acc_oauth_client_secret']);
		$this->assertSame($oauth['scopes'], $result['acc_oauth_scopes']);
	}

	/**
	 * Omitting OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN/ADD_AUTH_PARAM from the input
	 * must still yield those keys present with value null (callers isset()-check them), not
	 * simply be missing from the result.
	 */
	public function testOauth2ContentDefaultsWellKnownAndAuthParamKeysToNullWhenAbsent()
	{
		$oauth = array(
			'imap' => 'imap.example.org', 'smtp' => 'smtp.example.org',
			'provider' => 'https://example.org', 'client' => 'id', 'secret' => 'secret',
			'scopes' => array(),
		);
		$result = $this->callPrivateStatic('oauth2content', array($oauth));

		$this->assertArrayHasKey(OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN, $result);
		$this->assertNull($result[OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN]);
		$this->assertArrayHasKey(OpenIDConnectClient::ADD_AUTH_PARAM, $result);
		$this->assertNull($result[OpenIDConnectClient::ADD_AUTH_PARAM]);
	}

	/**
	 * Regression guard: this is the exact set of fields a non-mail-admin must never be able
	 * to submit changes for from the account edit dialog. Losing an entry here would silently
	 * let a non-admin edit a field they shouldn't (eg. re-point acc_imap_type/acc_domain).
	 */
	public function testAdminReadonlyFieldsListsExactNonAdminLockdownSet()
	{
		$expected = array(
			'account_id', 'button[multiple]', 'acc_user_editable', 'acc_further_identities',
			'acc_imap_type', 'acc_imap_logintype', 'acc_domain',
			'acc_imap_admin_username', 'acc_imap_admin_password', 'acc_imap_admin_use_without_pw',
			'acc_smtp_type', 'acc_smtp_auth_session',
		);
		$result = $this->callPrivateStatic('adminReadonlyFields', array());

		$this->assertSame($expected, array_keys($result));
		$this->assertSame(array_fill(0, count($expected), true), array_values($result));
	}

	/**
	 * A single-user (not is_multiple()) account must have its IMAP/SMTP type forced back to
	 * the plain classes server-side, even if the client only ever hid the selector - a client
	 * cannot be trusted to have left these fields alone.
	 */
	public function testNormalizeAccountTypeForcesPlainImapSmtpClassesWhenNotMultiple()
	{
		$content = array(
			'acc_imap_type' => 'SomeLdapBackedImapClass',
			'acc_imap_login_type' => 'domain/username',
			'acc_smtp_type' => 'SomeLdapBackedSmtpClass',
			'acc_smtp_auth_session' => true,
			'notify_use_default' => true,
		);
		$result = $this->callPrivateStatic('normalizeAccountType', array($content, false));

		$this->assertSame('EGroupware\\Api\\Mail\\Imap', $result['acc_imap_type']);
		$this->assertSame('EGroupware\\Api\\Mail\\Smtp', $result['acc_smtp_type']);
		$this->assertArrayNotHasKey('acc_imap_login_type', $result);
		$this->assertArrayNotHasKey('acc_smtp_auth_session', $result);
		$this->assertArrayNotHasKey('notify_use_default', $result);
	}

	/**
	 * The one carve-out: a single-user account already configured for JMAP must keep
	 * acc_imap_type as Mail\Imap\Jmap, so single connections can still use JMAP + push.
	 */
	public function testNormalizeAccountTypePreservesJmapTypeWhenNotMultiple()
	{
		$content = array('acc_imap_type' => Mail\Imap\Jmap::class);
		$result = $this->callPrivateStatic('normalizeAccountType', array($content, false));

		$this->assertSame(Mail\Imap\Jmap::class, $result['acc_imap_type']);
	}

	/**
	 * The carve-out must also cover Imap\Jmap SUBCLASSES like Imap\Stalwart, not just an exact
	 * class-name match - a naive `!==` check would silently reset a Stalwart account back to
	 * plain IMAP for a single-user account (never hit before this project, since the existing
	 * acc_id=1 Stalwart account is multi-user). acc_smtp_type must still be reset to plain SMTP
	 * regardless - Smtp\Stalwart is the admin-automation class (user/alias/quota management),
	 * never a personal account's SMTP transport, and must never be auto-assigned here.
	 */
	public function testNormalizeAccountTypePreservesJmapSubclassButAlwaysResetsSmtpType()
	{
		$content = array(
			'acc_imap_type' => Mail\Imap\Stalwart::class,
			'acc_smtp_type' => Mail\Smtp\Stalwart::class,
		);
		$result = $this->callPrivateStatic('normalizeAccountType', array($content, false));

		$this->assertSame(Mail\Imap\Stalwart::class, $result['acc_imap_type']);
		$this->assertSame('EGroupware\\Api\\Mail\\Smtp', $result['acc_smtp_type']);
	}

	/**
	 * A multi-user/"everyone" account must NOT have its acc_imap_type/acc_smtp_type touched
	 * (they stay client-editable, eg. for LDAP-backed logins); only the
	 * ident_email_alias -> ident_email copy applies for multi-user accounts.
	 */
	public function testNormalizeAccountTypeLeavesImapSmtpTypesAloneWhenMultiple()
	{
		$content = array(
			'acc_imap_type' => 'SomeLdapBackedImapClass',
			'acc_smtp_type' => 'SomeLdapBackedSmtpClass',
			'ident_email_alias' => 'alias@example.org',
			'ident_email' => 'primary@example.org',
		);
		$result = $this->callPrivateStatic('normalizeAccountType', array($content, true));

		$this->assertSame('SomeLdapBackedImapClass', $result['acc_imap_type']);
		$this->assertSame('SomeLdapBackedSmtpClass', $result['acc_smtp_type']);
		$this->assertSame('alias@example.org', $result['ident_email']);
	}

	/**
	 * fix_account_id_0()'s $account_id parameter is by-reference - invoke() silently passes
	 * by-ref parameters by value instead (see AdminMailHostDiscoveryTest::tryJmap() for the
	 * same gotcha), so this wrapper takes $account_id by-reference itself, which lets
	 * invokeArgs(..., array(&$account_id)) actually propagate the mutation back to the
	 * caller's variable.
	 */
	private function fixAccountId0(&$account_id, bool $back=false)
	{
		$ref = new ReflectionMethod(\admin_mail::class, 'fix_account_id_0');
		$ref->setAccessible(true);
		return $ref->invokeArgs(null, array(&$account_id, $back));
	}

	/**
	 * Storage shape (Mail\Account::is_multiple()'s convention: "everyone" = scalar 0) must
	 * convert to the widget's display shape (empty array) - this is what makes the
	 * et2-select-account "Everyone" placeholder show up instead of a literal "0" tag.
	 */
	public function testFixAccountId0ConvertsStorageZeroToEmptyArrayForDisplay()
	{
		$account_id = 0;
		$this->fixAccountId0($account_id);

		$this->assertSame(array(), $account_id);
	}

	/**
	 * The reverse direction ($back=true), used right before Mail\Account::write().
	 */
	public function testFixAccountId0ConvertsEmptyArrayToStorageZero()
	{
		$account_id = array();
		$this->fixAccountId0($account_id, true);

		$this->assertSame(0, $account_id);
	}

	/**
	 * Regression guard for the "Everyone account shows a bogus '0' tag after a failed save"
	 * bug (found 2026-08-26): edit()'s save handler converts account_id to storage shape
	 * (fix_account_id_0(..., true)) right before Mail\Account::write(), and only converts it
	 * back ($back=false) on the success path - if write() throws, none of the catch blocks
	 * used to undo that conversion, so the exception's re-render sent the client a raw scalar
	 * 0 instead of an empty array, and the multi-select widget rendered it as a literal "0"
	 * tag instead of showing the "Everyone" placeholder. Fixed with a `finally` block that
	 * unconditionally converts back to display shape - which relies on repeated $back=false
	 * calls being safe on an already-converted value (the success path's own call still runs
	 * first, then finally's call is a harmless no-op). This test guards exactly that
	 * idempotency assumption.
	 */
	public function testFixAccountId0DisplayConversionIsIdempotent()
	{
		$account_id = 0;
		$this->fixAccountId0($account_id);
		$this->fixAccountId0($account_id);

		$this->assertSame(array(), $account_id);
	}

	/**
	 * A real (non-"everyone") multi-account CSV string must still round-trip through both
	 * directions unchanged in shape - only the scalar/falsy "everyone" case is special-cased.
	 */
	public function testFixAccountId0LeavesRealMultiAccountListIntact()
	{
		$account_id = '5,7,9';
		$this->fixAccountId0($account_id);

		$this->assertSame(array('5', '7', '9'), $account_id);

		$this->fixAccountId0($account_id, true);

		$this->assertSame(array('5', '7', '9'), $account_id);
	}
}
