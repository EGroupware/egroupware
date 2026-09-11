<?php
/**
 * EGroupware Mail: differential tests for mail_wizard vs. admin_mail
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;

/**
 * mail_wizard extends admin_mail to let non-admins run the Mail Wizard from inside the mail
 * app itself (vs. admin's "create/manage accounts for other users" use of admin_mail
 * directly). admin/tests/SmimeGenerateTest.php already documents that edit() is inherited
 * unchanged; these tests reinforce that claim for a second method (mailboxes()) and pin down
 * the one deliberate behavioural difference (APP_CLASS), so a future edit that accidentally
 * diverges the two classes' shared behaviour gets caught.
 *
 * Extends Api\LoggedInTest because both classes' constructors need a DB-backed session
 * (Api\Translation::add_app(), Api\Preferences::setlocale()).
 */
class MailWizardDifferentialTest extends Api\LoggedInTest
{
	/**
	 * APP_CLASS is the one constant that must differ, since it's used as the etemplate
	 * postback target prefix and picks admin vs. mail as the calling app.
	 */
	public function testAppClassConstantDiffersBetweenAdminMailAndMailWizard()
	{
		$this->assertSame('admin.admin_mail.', admin_mail::APP_CLASS);
		$this->assertSame('mail.mail_wizard.', mail_wizard::APP_CLASS);
	}

	/**
	 * Smoke test only: mail_wizard's constructor additionally queues admin's app.css/app.js
	 * and force-loads admin's lang file (see class docblock), none of which are
	 * introspectable via a public API - this only asserts construction completes without
	 * throwing.
	 */
	public function testMailWizardConstructorDoesNotThrow()
	{
		$wizard = new mail_wizard();

		$this->assertInstanceOf(admin_mail::class, $wizard);
	}

	/**
	 * mailboxes() is not overridden by mail_wizard, so calling it via either class must
	 * behave identically for the same input.
	 */
	public function testMailboxesBehavesIdenticallyOnMailWizardAndAdminMail()
	{
		$mailboxes = array(
			'INBOX' => array('attributes' => array(), 'delimiter' => '.'),
			'Trash' => array('attributes' => array(), 'delimiter' => '.'),
		);
		$imapForAdmin = $this->createStub(Horde_Imap_Client_Socket::class);
		$imapForAdmin->method('listMailboxes')->willReturn($mailboxes);
		$imapForWizard = $this->createStub(Horde_Imap_Client_Socket::class);
		$imapForWizard->method('listMailboxes')->willReturn($mailboxes);

		$adminContent = array();
		$adminResult = admin_mail::mailboxes($imapForAdmin, $adminContent);
		$wizardContent = array();
		$wizardResult = mail_wizard::mailboxes($imapForWizard, $wizardContent);

		$this->assertSame($adminResult, $wizardResult);
		$this->assertSame($adminContent['acc_folder_trash'], $wizardContent['acc_folder_trash']);
	}
}
