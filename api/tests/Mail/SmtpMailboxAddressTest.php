<?php
/**
 * EGroupware Api: Test Api\Mail\Smtp::mailbox_address()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api\Mail\Smtp;

/**
 * doc/ai/projects/mail-test-coverage.md's priority-3 entry: a small, deterministic 5-way switch on
 * $mail_login_type, flagged as an untested "cheap win whenever picked up".
 *
 * Only the array-$account shape is exercised here - per the method's own docblock, an array
 * $account (with account_id/account_lid/account_email keys) deliberately bypasses
 * $GLOBALS['egw']->accounts->id2name() entirely, so this is testable without a real Accounts
 * singleton (unreliable in PHPUnit CLI, see project memory) or any other global state.
 */
class SmtpMailboxAddressTest extends \PHPUnit\Framework\TestCase
{
	private const ACCOUNT = ['account_id' => 42, 'account_lid' => 'jdoe', 'account_email' => 'jdoe@example.org'];

	public function testEmailTypeReturnsTheAccountsOwnEmailAddress()
	{
		$this->assertSame('jdoe@example.org', Smtp::mailbox_address(self::ACCOUNT, 'example.org', 'email'));
	}

	public function testStandardTypeReturnsTheBareLoginId()
	{
		$this->assertSame('jdoe', Smtp::mailbox_address(self::ACCOUNT, 'example.org', 'standard'));
	}

	public function testUidNumberTypeReturnsUPrefixedAccountIdAtDomain()
	{
		$this->assertSame('u42@example.org', Smtp::mailbox_address(self::ACCOUNT, 'example.org', 'uidNumber'));
	}

	public function testUidNumberTypeReturnsNullWhenNoDomainGiven()
	{
		$this->assertNull(Smtp::mailbox_address(self::ACCOUNT, null, 'uidNumber'));
	}

	public function testDomainSlashUsernameTypeReturnsDomainSlashLoginId()
	{
		$this->assertSame('example.org/jdoe', Smtp::mailbox_address(self::ACCOUNT, 'example.org', 'domain/username'));
	}

	public function testVmailmgrTypeReturnsLoginIdAtDomain()
	{
		$this->assertSame('jdoe@example.org', Smtp::mailbox_address(self::ACCOUNT, 'example.org', 'vmailmgr'));
	}

	public function testVmailmgrTypeReturnsNullWhenNoDomainGiven()
	{
		$this->assertNull(Smtp::mailbox_address(self::ACCOUNT, null, 'vmailmgr'));
	}

	/** An unrecognised/absent $mail_login_type falls through to the same case as 'vmailmgr' (the switch's own default). */
	public function testAnUnrecognisedLoginTypeFallsBackToTheVmailmgrShape()
	{
		$this->assertSame('jdoe@example.org', Smtp::mailbox_address(self::ACCOUNT, 'example.org', 'something-else'));
		$this->assertNull(Smtp::mailbox_address(self::ACCOUNT, null, 'something-else'));
	}

	public function testMailboxAddrInstanceMethodFallsBackToItsOwnDefaultDomainAndLoginTypeProperties()
	{
		$smtp = new Smtp('instance-default.example.org');
		$smtp->loginType = 'standard';

		$this->assertSame('jdoe', $smtp->mailbox_addr(self::ACCOUNT));
	}

	public function testMailboxAddrInstanceMethodParametersOverrideTheInstanceDefaults()
	{
		$smtp = new Smtp('instance-default.example.org');
		$smtp->loginType = 'standard';

		$this->assertSame('u42@override.example.org', $smtp->mailbox_addr(self::ACCOUNT, 'override.example.org', 'uidNumber'));
	}
}
