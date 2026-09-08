<?php
/**
 * EGroupware Mail: folder ajax handlers (subscribe/status)
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Mail;
use Horde_Imap_Client_Exception;
use mail_ui;

/**
 * Folder ajax handlers, extracted from mail_ui.
 *
 * Not independent of mail_ui/Api\Mail instance state (folder existence/status checks, hierarchy-
 * delimiter lookups, the connected mail_bo) - takes the owning `mail_ui` as a constructor
 * dependency, the same pattern ImportHandler/MessageActionHandler/AttachmentHandler/
 * MessageDisplayHandler already use. See doc/ai/projects/mail-bo-decoupling.md.
 *
 * `folderSubscription()` stays classic - the mobile subscribe template
 * (mail/templates/mobile/subscribe.xet) has no JMAP-native tree at all, unlike the desktop
 * subscribe popup's `MailApp.subscriptionLoad()`. `setFolderStatus()` is a no-op for JMAP accounts
 * (their unread/folder state is already fully client-side) and classic-only for plain IMAP.
 *
 * `addFolder()`/`renameFolder()`/`moveFolder()`/`deleteFolder()` (+ mail_ui's own
 * `ajax_addFolder()`/`ajax_renameFolder()`/`ajax_MoveFolder()`/`ajax_deleteFolder()` delegations)
 * removed 2026-09-08 - the folder-tree JMAP migration (doc/ai/projects/mail-folder-tree-jmap.md)
 * already made all 4 of these client-side-only, and a full-mail_ui audit found their classic
 * fallback call sites had all become unreachable in practice: `addFolder()`'s JMAP-side wrapper
 * never actually returned the "fall back to classic" signal at all, and the other 3 could only be
 * reached by right-clicking Rename/Move/Delete on a bare *account* node - not a real folder - which
 * `MailApp.checkFolderNoSelect()` now explicitly disables (a genuine, if minor, UI-bug fix
 * alongside the cleanup).
 */
class FolderHandler
{
	private mail_ui $ui;

	public function __construct(mail_ui $ui)
	{
		$this->ui = $ui;
	}

	/**
	 * Ajax callback to subscribe / unsubscribe a Mailbox of an account
	 *
	 * @param {int} $_acc_id profile Id of selected mailbox
	 * @param {string} $_folderName name of mailbox needs to be subcribe or unsubscribed
	 * @param {boolean} $_status set true for subscribe and false to unsubscribe
	 */
	public function folderSubscription($_acc_id, $_folderName, $_status)
	{
		//Change the Mail object to related profileId
		$this->ui->changeProfile($_acc_id);
		try
		{
			$this->ui->mail_bo->icServer->subscribeMailbox($_folderName, $_status);
			$this->ui->mail_bo->resetFolderObjectCache($_acc_id);
			// same "account changed, please refresh" signal admin_mail/mail_wizard already send
			// after saving an account (mail/js/app.ts's observer() 'mail-account' case) - reloads
			// this account's tree node via its own JMAP-first autoloading callback, no need for a
			// server-computed subtree just to add/remove one folder
			Framework::refresh_opener('', 'mail-account', $_acc_id, 'update');
		}
		catch (Horde_Imap_Client_Exception $ex)
		{
			error_log(__METHOD__.__LINE__."()". lang('Folder %1 %2 failed because of %3!',$_folderName,$_status?'subscribed':'unsubscribed', $ex));
			Framework::message(lang('Folder %1 %2 failed!',$_folderName,$_status));
		}
	}

	/**
	 * ajax_setFolderStatus - gets the counters and sets the text of a treenode if needed (unread
	 * Messages found)
	 *
	 * @param array $_folder folders to refresh its unseen message counters
	 * @return nothing
	 */
	public function setFolderStatus($_folder, $force_change = false)
	{
		Api\Translation::add_app('mail');
		// JMAP-FALLTHROUGH-GUARD (see [[project_jmap_imap_fallthrough_cleanup]]):
		// unseen-counter refresh for the classic folder tree - a JMAP account's folder/unread
		// state is already fully client-side (MailJmap), and getFolderStatus() below falls
		// through to Horde_Imap_Client_Socket's raw-socket listMailboxes() (neither Imap\Jmap
		// nor Imap\Stalwart override it), hanging/misconnecting against a JMAP(S) endpoint
		// (found live 2026-08-24, same root cause as openConnection()/_getSpecialUseFolder()/
		// getHierarchyDelimiter() elsewhere in this class of bug)
		if ($this->ui->mail_bo->icServer instanceof Mail\Imap\Jmap)
		{
			return;
		}
		if ($_folder)
		{
			$this->ui->mail_bo->getHierarchyDelimiter(false);
			$oA = array();
			foreach ($_folder as $_folderName)
			{
				list($profileID,$folderName) = explode(mail_ui::$delimiter,$_folderName,2);
				if (is_numeric($profileID)) //things like mail::xxx will be ignored
				{
					if ($profileID != $this->ui->mail_bo->profileID) continue; // only current connection
					if ($folderName)
					{
						try
						{
							$fS = $this->ui->mail_bo->getFolderStatus($folderName,false,false,false);
						}
						catch (\Exception $e)
						{
							if (Mail::$debug) error_log(__METHOD__,' ()'.$e->getMessage ());
							continue;
						}
						if ($fS['unseen'] || $force_change)
						{
							$oA[$_folderName] = ''.$fS['unseen'];
						}
					}
				}
			}
			if ($oA)
			{
				$response = Api\Json\Response::get();
				$response->call('app.mail.setFolderStatus',$oA);
			}
		}
	}
}
