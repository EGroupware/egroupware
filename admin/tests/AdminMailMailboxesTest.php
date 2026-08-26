<?php
/**
 * EGroupware Admin: tests for admin_mail::mailboxes() special-folder guessing
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

use EGroupware\Api\Mail;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests admin_mail::mailboxes(), the special-folder-guessing algorithm used by the Mail
 * Wizard's "Step 2: Folder" screen (admin_mail::folder()) to pre-select sent/trash/drafts/
 * junk/archive folders for the user - plus jmapMailboxes(), its JMAP-native equivalent used
 * for JMAP/Stalwart accounts (see [[project-mail-wizard-jmap-oauth]]).
 *
 * mailboxes() is public static and takes a Horde_Imap_Client_Socket directly, so it's
 * called here without going through folder() at all - no Etemplate/DB/network dependency.
 * Horde_Imap_Client_Socket and its listMailboxes()/login() methods are confirmed non-final
 * (vendor/egroupware/imap-client/lib/Horde/Imap/Client/{Socket,Base}.php), so a plain
 * PHPUnit stub can stand in for it. jmapMailboxes() is protected static and takes a
 * Mail\Jmap, reached the same way via ReflectionMethod, with a mocked jmapCall()/__get().
 *
 * NOT covered here: folder() itself (ends in Etemplate::exec()) or how mailboxes()/
 * jmapMailboxes() are reached during a live autoconfig() run. See
 * doc/ai/projects/mail-wizard-jmap-oauth.md.
 */
#[AllowMockObjectsWithoutExpectations]
class AdminMailMailboxesTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * @param array $mailboxes mailbox name => ['attributes' => [...], 'delimiter' => '.']
	 * @return Horde_Imap_Client_Socket
	 */
	private function mockImap(array $mailboxes)
	{
		$imap = $this->createStub(Horde_Imap_Client_Socket::class);
		$imap->method('listMailboxes')->willReturn($mailboxes);
		return $imap;
	}

	/**
	 * A mailbox tagged with the IMAP \Sent special-use attribute must be picked for
	 * acc_folder_sent even when a differently-named mailbox would otherwise be preferred by
	 * common-name matching - special-use attributes are the authoritative signal.
	 */
	public function testMailboxesPrefersSpecialUseAttributeOverCommonName()
	{
		$imap = $this->mockImap(array(
			'INBOX' => array('attributes' => array(), 'delimiter' => '.'),
			'MySpecialSentFolder' => array('attributes' => array('\\sent'), 'delimiter' => '.'),
			'Sent' => array('attributes' => array(), 'delimiter' => '.'),
		));
		$content = array();
		admin_mail::mailboxes($imap, $content);

		$this->assertSame('MySpecialSentFolder', $content['acc_folder_sent']);
	}

	/**
	 * With no special-use attributes reported at all, folders must still be guessed purely
	 * from common mailbox names (case-insensitive).
	 */
	public function testMailboxesFallsBackToCommonNameWhenNoSpecialUseAttributes()
	{
		$imap = $this->mockImap(array(
			'INBOX' => array('attributes' => array(), 'delimiter' => '.'),
			'Trash' => array('attributes' => array(), 'delimiter' => '.'),
			'Drafts' => array('attributes' => array(), 'delimiter' => '.'),
		));
		$content = array();
		admin_mail::mailboxes($imap, $content);

		$this->assertSame('Trash', $content['acc_folder_trash']);
		$this->assertSame('Drafts', $content['acc_folder_draft']);
	}

	/**
	 * When two mailboxes both match a common name (eg. "Trash" and "Archive.Trash" both
	 * contain the "trash" path component), the shorter one must win.
	 */
	public function testMailboxesPicksShortestMatchingNameOnCommonNameTie()
	{
		$imap = $this->mockImap(array(
			'INBOX' => array('attributes' => array(), 'delimiter' => '.'),
			// longer match listed first, to prove the shorter one replaces it
			'Archive.Trash' => array('attributes' => array(), 'delimiter' => '.'),
			'Trash' => array('attributes' => array(), 'delimiter' => '.'),
		));
		$content = array();
		admin_mail::mailboxes($imap, $content);

		$this->assertSame('Trash', $content['acc_folder_trash']);
	}

	/**
	 * The return value is always ALL mailboxes as a name => name array, independent of
	 * which special folders were successfully guessed - it's used as the select-box options
	 * for every acc_folder_* field.
	 */
	public function testMailboxesReturnValueIsAllMailboxesAsKeyValueArray()
	{
		$mailboxes = array(
			'INBOX' => array('attributes' => array(), 'delimiter' => '.'),
			'Trash' => array('attributes' => array(), 'delimiter' => '.'),
		);
		$imap = $this->mockImap($mailboxes);
		$content = array();
		$result = admin_mail::mailboxes($imap, $content);

		$this->assertSame(array('INBOX' => 'INBOX', 'Trash' => 'Trash'), $result);
	}

	/**
	 * When no mailbox matches a given special folder's special-use attribute or common
	 * names (eg. no archive folder exists at all), that content key must stay unset rather
	 * than being set to an empty/bogus value.
	 */
	public function testMailboxesLeavesFolderContentKeyUnsetWhenNoMatch()
	{
		$imap = $this->mockImap(array(
			'INBOX' => array('attributes' => array(), 'delimiter' => '.'),
		));
		$content = array();
		admin_mail::mailboxes($imap, $content);

		$this->assertArrayNotHasKey('acc_folder_archive', $content);
	}

	// --- jmapMailboxes() ---

	/**
	 * @param array $list JMAP Mailbox objects, each with 'name' and optional 'role'
	 * @return Mail\Jmap
	 */
	private function mockJmap(array $list) : Mail\Jmap
	{
		$jmap = $this->getMockBuilder(Mail\Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['jmapCall', '__get'])
			->getMock();
		$jmap->method('__get')->willReturnCallback(fn($name) => $name === 'accountId' ? 'a1' : null);
		$jmap->method('jmapCall')->willReturn([
			'methodResponses' => [['Mailbox/get', ['list' => $list], '0']],
		]);
		return $jmap;
	}

	private function jmapMailboxes(Mail\Jmap $jmap, array &$content)
	{
		$ref = new ReflectionMethod(admin_mail::class, 'jmapMailboxes');
		return $ref->invokeArgs(null, [$jmap, &$content]);
	}

	/**
	 * A mailbox tagged with the JMAP "sent" role must be picked for acc_folder_sent, the
	 * JMAP-native equivalent of testMailboxesPrefersSpecialUseAttributeOverCommonName().
	 */
	public function testJmapMailboxesUsesRoleForStandardFolders()
	{
		$jmap = $this->mockJmap(array(
			array('name' => 'Inbox'),
			array('name' => 'MySpecialSentFolder', 'role' => 'sent'),
			array('name' => 'Trash', 'role' => 'trash'),
		));
		$content = array();
		$this->jmapMailboxes($jmap, $content);

		$this->assertSame('MySpecialSentFolder', $content['acc_folder_sent']);
		$this->assertSame('Trash', $content['acc_folder_trash']);
	}

	/**
	 * "Templates" and "Ham" have no standard JMAP role - they must be matched by common
	 * name (case-insensitive) instead, same as mailboxes()'s IMAP fallback.
	 */
	public function testJmapMailboxesFallsBackToCommonNameForRolelessFolders()
	{
		$jmap = $this->mockJmap(array(
			array('name' => 'Inbox'),
			array('name' => 'Templates'),
			array('name' => 'Ham'),
		));
		$content = array();
		$this->jmapMailboxes($jmap, $content);

		$this->assertSame('Templates', $content['acc_folder_template']);
		$this->assertSame('Ham', $content['acc_folder_ham']);
	}

	/**
	 * The return value is always ALL mailboxes as a name => name array, same contract as
	 * mailboxes().
	 */
	public function testJmapMailboxesReturnValueIsAllMailboxesAsKeyValueArray()
	{
		$jmap = $this->mockJmap(array(
			array('name' => 'Inbox'),
			array('name' => 'Trash', 'role' => 'trash'),
		));
		$content = array();
		$result = $this->jmapMailboxes($jmap, $content);

		$this->assertSame(array('Inbox' => 'Inbox', 'Trash' => 'Trash'), $result);
	}

	/**
	 * When no mailbox matches a given special folder's role or common name (eg. no archive
	 * folder exists at all), that content key must stay unset rather than being set to an
	 * empty/bogus value.
	 */
	public function testJmapMailboxesLeavesFolderContentKeyUnsetWhenNoMatch()
	{
		$jmap = $this->mockJmap(array(
			array('name' => 'Inbox'),
		));
		$content = array();
		$this->jmapMailboxes($jmap, $content);

		$this->assertArrayNotHasKey('acc_folder_archive', $content);
	}
}
