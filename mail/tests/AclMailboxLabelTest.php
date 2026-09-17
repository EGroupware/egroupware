<?php
/**
 * EGroupware mail: regression test for mail_acl's "mailbox" select showing an untranslated path
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * mail_acl::edit() used to show the "mailbox" select's currently-selected value as its own raw,
 * untranslated path (eg. "INBOX/Trash" instead of "INBOX/Papierkorb") - unlike the search-as-you-
 * type suggestions, which already went through Compose::ajax_searchFolder()'s translation.
 * translateMailboxLabel() is the fix for that gap, for the classic/shim IMAP case (a real JMAP
 * account's own case is covered by Mail\Jmap\MailboxListAllPathsTest.php, which this method just
 * delegates to).
 */
class AclMailboxLabelTest extends \EGroupware\Api\AppTest
{
	private function aclWithFakeImap(object $imap) : mail_acl
	{
		$acl = new mail_acl();
		$prop = new ReflectionProperty(mail_acl::class, 'imap');
		$prop->setAccessible(true);
		$prop->setValue($acl, $imap);
		return $acl;
	}

	private function callTranslate(mail_acl $acl, string $mailbox) : string
	{
		$method = new ReflectionMethod(mail_acl::class, 'translateMailboxLabel');
		$method->setAccessible(true);
		return $method->invoke($acl, $mailbox);
	}

	private function fakeClassicImap() : object
	{
		// plain object (not a Mail\Imap\Jmap instance) - routes translateMailboxLabel() into the
		// classic/shim branch, same as any real classic Imap/Account object
		return new class {
			public $acc_folder_trash = 'INBOX/Trash';
			public $acc_folder_draft = 'INBOX/Drafts';
			public $acc_folder_sent = 'INBOX/Sent';
			public $acc_folder_template = null;
			public $acc_folder_junk = 'INBOX/Junk';
			public $acc_folder_archive = 'INBOX/Archive';
		};
	}

	public function testTranslatesConfiguredSpecialUseFolder()
	{
		$acl = $this->aclWithFakeImap($this->fakeClassicImap());

		$this->assertSame(lang('Trash'), $this->callTranslate($acl, 'INBOX/Trash'));
		$this->assertSame(lang('Sent'), $this->callTranslate($acl, 'INBOX/Sent'));
	}

	public function testTranslatesInboxCaseInsensitively()
	{
		$acl = $this->aclWithFakeImap($this->fakeClassicImap());

		$this->assertSame(lang('INBOX'), $this->callTranslate($acl, 'INBOX'));
	}

	public function testLeavesAnOrdinaryFolderPathUntranslated()
	{
		$acl = $this->aclWithFakeImap($this->fakeClassicImap());

		$this->assertSame('INBOX/Kunden', $this->callTranslate($acl, 'INBOX/Kunden'));
	}

	public function testEmptyMailboxPassesThroughUnchanged()
	{
		$acl = $this->aclWithFakeImap($this->fakeClassicImap());

		$this->assertSame('', $this->callTranslate($acl, ''));
	}

	/**
	 * A null acc_folder_* (eg. no template folder configured) must not collide with another
	 * folder that's genuinely at an empty/falsy path, nor cause a notice.
	 */
	public function testMissingSpecialUseFolderConfigDoesNotMisfire()
	{
		$acl = $this->aclWithFakeImap($this->fakeClassicImap());

		$this->assertSame('', $this->callTranslate($acl, ''));
	}
}
