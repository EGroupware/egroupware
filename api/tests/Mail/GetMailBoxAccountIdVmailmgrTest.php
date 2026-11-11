<?php
/**
 * EGroupware Api: Mail\Imap::getMailBoxAccountId() must strip the '@domain' suffix symmetrically
 * to how getMailBoxUserName() adds it for the 'vmailmgr' login type ("username@domainname")
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

require_once realpath(__DIR__.'/../AppTest.php');

/**
 * Found live (2026-11-11, testing the mail ACL dialog against a real Stalwart account on
 * pole.egroupware.org, acc_imap_logintype 'vmailmgr' aka "username@domainname" in the admin UI):
 * an ACL grant - including one just created through this very dialog - showed as an unresolved
 * "#ingo@egroupware.org" instead of a real account tag, even though account "ingo" genuinely
 * exists. Root cause: getMailBoxUserName()'s 'vmailmgr' case appends '@'.$this->domainName to
 * build the effective username (eg. "ingo@egroupware.org") - exactly what Stalwart then reports
 * back as that shareWith entry's own email (Imap\Jmap::jmapPrincipalEmail()). But
 * getMailBoxAccountId() had no 'vmailmgr' case at all, falling through to the default
 * (name2id($_username, 'account_lid') on the UNSTRIPPED string) - so a value this very login type
 * had itself constructed could never resolve back to the account that created it.
 */
class GetMailBoxAccountIdVmailmgrTest extends \EGroupware\Api\AppTest
{
	private function imapWithParams(?string $loginType, ?string $domainName) : Imap
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		$loginTypeProp = new \ReflectionProperty(Imap::class, 'loginType');
		$loginTypeProp->setAccessible(true);
		$loginTypeProp->setValue($imap, $loginType);

		$domainNameProp = new \ReflectionProperty(Imap::class, 'domainName');
		$domainNameProp->setAccessible(true);
		$domainNameProp->setValue($imap, $domainName);

		return $imap;
	}

	public function testStripsMatchingDomainSuffix()
	{
		$imap = $this->imapWithParams('vmailmgr', 'egroupware.org');

		// confirms the domain-stripping happened before the lookup - a real account_id is
		// environment-specific, so this just checks the method returns whatever account_lid
		// 'ingo' resolves to in this environment, not that it's some specific id
		$this->assertSame(
			$GLOBALS['egw']->accounts->name2id('ingo', 'account_lid'),
			$imap->getMailBoxAccountId('ingo@egroupware.org')
		);
	}

	public function testStripIsCaseInsensitive()
	{
		$imap = $this->imapWithParams('vmailmgr', 'EGroupware.org');

		$this->assertSame(
			$GLOBALS['egw']->accounts->name2id('ingo', 'account_lid'),
			$imap->getMailBoxAccountId('ingo@egroupware.ORG')
		);
	}

	public function testLeavesUsernameAloneWhenDomainDiffers()
	{
		$imap = $this->imapWithParams('vmailmgr', 'egroupware.org');

		// a different domain must NOT be stripped
		$this->assertSame(
			$GLOBALS['egw']->accounts->name2id('ingo@example.com', 'account_lid'),
			$imap->getMailBoxAccountId('ingo@example.com')
		);
	}

	public function testEmailLoginTypeUnaffected()
	{
		// 'email' loginType must keep using account_email, completely untouched by this fix
		$imap = $this->imapWithParams('email', 'egroupware.org');

		$this->assertSame(
			$GLOBALS['egw']->accounts->name2id('ralf@egroupware.org', 'account_email'),
			$imap->getMailBoxAccountId('ralf@egroupware.org')
		);
	}

	public function testDefaultLoginTypeUnaffected()
	{
		// no loginType at all must keep matching account_lid raw, unaffected by this fix
		$imap = $this->imapWithParams(null, 'egroupware.org');

		$this->assertSame(
			$GLOBALS['egw']->accounts->name2id('ingo@egroupware.org', 'account_lid'),
			$imap->getMailBoxAccountId('ingo@egroupware.org')
		);
	}
}
