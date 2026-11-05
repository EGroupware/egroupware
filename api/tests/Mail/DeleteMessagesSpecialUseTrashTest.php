<?php
/**
 * EGroupware API: Mail::isSpecialUseTrashFolder() - ticket #126811 follow-up
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail;

/**
 * Live report 2026-11-05 (Gerald Franck / Birgit Becker, ticket #126811): deleting mail while
 * already viewing the Trash folder on a mittwald.de/agenturserver.de IMAP account did not
 * really delete it - "lassen sich nicht aus dem Papierkorb löschen, erscheinen immer wieder"
 * (can't be deleted from Trash, they keep reappearing). A live IMAP listing confirmed the
 * account has TWO trash-like mailboxes: a provider-default "INBOX.Trash" carrying the real
 * SPECIAL-USE \Trash attribute (barely used, 3 messages), and a localized "INBOX.Papierkorb"
 * with no special-use flag at all but 34(!) unseen messages piling up - almost certainly the
 * one actually displayed/used as Trash. Mail::deleteMessages()/compressFolder() decided
 * "am I being asked to delete from Trash" purely by comparing against the ONE configured
 * acc_folder_trash name (Mail::isTrashFolder()) - whichever folder isn't that exact name just
 * gets "moved to trash" (copied into the other folder with 'move'=>true) instead of really
 * deleted, which for a duplicate-trash setup looks exactly like "it disappears and an
 * identical copy reappears".
 *
 * Fixed by also honouring the server's own SPECIAL-USE \Trash attribute
 * (isSpecialUseTrashFolder() below, OR'd with isTrashFolder() at both call sites), independent
 * of whatever is (perhaps wrongly) configured as acc_folder_trash.
 *
 * DB-free bare TestCase: Mail::__construct() is private, so the fake below defines its own
 * trivial constructor instead of calling it, and getFolderStatus() (isSpecialUseTrashFolder()'s
 * only dependency) is stubbed - no real IMAP/DB connection is ever made.
 */
class DeleteMessagesSpecialUseTrashTest extends \PHPUnit\Framework\TestCase
{
	private function fakeMailWithFolderAttributes(array $attributesByFolder) : Mail
	{
		return new class($attributesByFolder) extends Mail
		{
			private array $attributesByFolder;
			public function __construct(array $attributesByFolder)
			{
				$this->attributesByFolder = $attributesByFolder;
			}
			function getFolderStatus($_folderName, $ignoreStatusCache=false, $basicInfoOnly=false, $fetchSubscribedInfo=true, bool $getModSeq=false)
			{
				if (!isset($this->attributesByFolder[$_folderName]))
				{
					throw new \Exception("no such folder: $_folderName");
				}
				return ['attributes' => $this->attributesByFolder[$_folderName]];
			}
		};
	}

	public function testTrueWhenServerFlagsFolderAsTrash()
	{
		$mail = $this->fakeMailWithFolderAttributes(['INBOX.Trash' => ['\\trash']]);

		$this->assertTrue($mail->isSpecialUseTrashFolder('INBOX.Trash'));
	}

	public function testMatchIsCaseInsensitive()
	{
		$mail = $this->fakeMailWithFolderAttributes(['INBOX.Trash' => ['\\Trash']]);

		$this->assertTrue($mail->isSpecialUseTrashFolder('INBOX.Trash'));
	}

	/**
	 * The actual ticket #126811 scenario: a folder with NO special-use attribute at all
	 * (eg. the localized "INBOX.Papierkorb" the user actually deletes from) must not be
	 * mistaken for the special-use trash folder - Mail::isTrashFolder()'s own configured-name
	 * check is what's supposed to catch that one instead.
	 */
	public function testFalseForAFolderWithoutTheTrashAttribute()
	{
		$mail = $this->fakeMailWithFolderAttributes(['INBOX.Papierkorb' => []]);

		$this->assertFalse($mail->isSpecialUseTrashFolder('INBOX.Papierkorb'));
	}

	public function testFalseForAnUnrelatedSpecialUseAttribute()
	{
		$mail = $this->fakeMailWithFolderAttributes(['INBOX.Sent' => ['\\sent']]);

		$this->assertFalse($mail->isSpecialUseTrashFolder('INBOX.Sent'));
	}

	public function testFalseWhenTheFolderLookupFails()
	{
		$mail = $this->fakeMailWithFolderAttributes([]); // no entry -> getFolderStatus() throws

		$this->assertFalse($mail->isSpecialUseTrashFolder('INBOX.DoesNotExist'));
	}
}
