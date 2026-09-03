<?php
/**
 * EGroupware Mail: import a message (.eml/upload) into a folder
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Framework;
use EGroupware\Api\Mail;
use mail_ui;

/**
 * Import-a-message-into-a-folder ajax handlers, extracted from mail_ui.
 *
 * Unlike Mail\Ui\SmimeHandler, this group is NOT independent of mail_ui/Api\Mail instance state
 * (folder existence checks, profile/hierarchy-delimiter lookups, appendMessage() all need the
 * owning mail_ui's already-connected mail_bo) - it takes the owning `mail_ui` as a constructor
 * dependency instead, the same pattern `mail_tree` already uses. This is still a real
 * organizational win (mail_ui.inc.php gets smaller, the import domain lives in one file) even
 * though it isn't a testability win the way the pure Phase 1 classes were - see
 * doc/ai/projects/mail-bo-decoupling.md.
 *
 * `mail_ui::importMessageFromVFS2DraftAndDisplay()` stays in place as a one-line delegation here -
 * required because it's in `mail_ui::$public_functions` (menuaction-dispatched, and registered as
 * the .eml mime-handler in mail_hooks.inc.php). `mail_ui::importMessage()` was NOT moved - it's a
 * dual-purpose entry point (renders the upload form Etemplate OR processes a submitted upload) and
 * the form-rendering half is genuinely `mail_ui`'s job; only its internal call to
 * importMessageToFolder() was repointed here.
 */
class ImportHandler
{
	private mail_ui $ui;

	public function __construct(mail_ui $ui)
	{
		$this->ui = $ui;
	}

	/**
	 * @param array $_formData Array with information of name, type, file and size
	 * @param string $_folder (passed by reference) will set the folder used. must be set with a
	 *      folder, but will hold modifications if folder is modified
	 * @param string $importID ID for the imported message, used by attachments to identify them
	 *      unambiguously
	 * @return string a ready-to-use rowID (NOT a bare uid) - for a classic account this is built
	 *  via Api\Mail::createRowID() same as before, for a JMAP account it's built directly here
	 *  (needs the resolved mailboxId, not just a uid) - see the JMAP-FALLTHROUGH-GUARD below
	 * @throws Api\Exception\WrongUserinput
	 */
	public function importMessageToFolder($_formData, &$_folder, $importID='') : string
	{
		$importfailed = false;
		if (empty($_formData['file']))
		{
			$_formData['file'] = $_formData['tmp_name'];
		}
		// check if formdata meets basic restrictions (in tmp dir, or vfs, mimetype, etc.)
		$alert_msg = '';
		try
		{
			$tmpFileName = Mail::checkFileBasics($_formData, $importID);
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$importfailed = true;
			$alert_msg .= $e->getMessage();
		}
		if ($importfailed === false)
		{
			$mailObject = new Api\Mailer();
			try
			{
				$this->ui->mail_bo->parseFileIntoMailObject($mailObject, $tmpFileName);
			}
			catch (Api\Exception\AssertionFailed $e)
			{
				$importfailed = true;
				$alert_msg .= $e->getMessage();
			}
			$this->ui->mail_bo->openConnection();
			if (empty($_folder))
			{
				$importfailed = true;
				$alert_msg .= lang("Import of message %1 failed. Destination Folder not set.", $_formData['name']);
			}
			$delimiter = $this->ui->mail_bo->getHierarchyDelimiter();
			if ($_folder == 'INBOX'.$delimiter)
			{
				$_folder = 'INBOX';
			}
			$icServer = $this->ui->mail_bo->icServer;
			// JMAP-FALLTHROUGH-GUARD (see [[project_jmap_imap_fallthrough_cleanup]]): folderExists()/
			// appendMessage() below fall through to Horde_Imap_Client_Socket's raw-socket methods,
			// unguarded for a JMAP account (same bug class as elsewhere in this project - hangs or
			// misconnects against a JMAP(S) endpoint; a JMAP account never has a raw IMAP connection
			// to append to in the first place) - materialize via genuine Email/import (RFC 8621 §4.8)
			// instead, uniformly available for both Stalwart and the local shim via jmapClient()
			// (found live 2026-09-03, ralf: clicking an InfoLog-attached .eml on a Stalwart account
			// failed with "Zielordner Drafts existiert nicht").
			if ($importfailed === false && $icServer instanceof Mail\Imap\Jmap)
			{
				try
				{
					$jmap = $icServer->jmapClient();
					$mailboxId = $jmap->mailbox->getMailboxId($_folder);
					if (!$mailboxId)
					{
						throw new Api\Exception\WrongUserinput(lang("Import of message %1 failed. Destination Folder %2 does not exist.", $_formData['name'], $_folder));
					}
					$blobId = $jmap->uploadBlob($mailObject->getRaw(false), 'message/rfc822');
					$emailId = $jmap->emailImport($blobId, $_folder);
				}
				catch (Api\Exception\WrongUserinput $e)
				{
					throw $e;
				}
				catch (\Exception $e)
				{
					throw new Api\Exception\WrongUserinput(lang("Import of message %1 failed. Could not save message to folder %2 due to: %3", $_formData['name'], $_folder, $e->getMessage()));
				}
				return 'mail::'.$GLOBALS['egw_info']['user']['account_id'].'::'.$this->ui->mail_bo->profileID.'::'.$mailboxId.'::'.$emailId;
			}
			if ($importfailed === false)
			{
				if ($this->ui->mail_bo->folderExists($_folder, true))
				{
					try
					{
						$messageUid = $this->ui->mail_bo->appendMessage($_folder, $mailObject->getRaw(), null, '\\Seen');
					}
					catch (Api\Exception\WrongUserinput $e)
					{
						$importfailed = true;
						$alert_msg .= lang("Import of message %1 failed. Could not save message to folder %2 due to: %3", $_formData['name'], $_folder, $e->getMessage());
					}
				}
				else
				{
					$importfailed = true;
					$alert_msg .= lang("Import of message %1 failed. Destination Folder %2 does not exist.", $_formData['name'], $_folder);
				}
			}
		}
		if ($importfailed)
		{
			throw new Api\Exception\WrongUserinput($alert_msg);
		}
		return $this->ui->createRowID($_folder, $messageUid, true);
	}

	/**
	 * @param array $formData Array with information of name, type, file and size; file is
	 *      required, name, type and size may be set here to meet the requirements
	 *      Example: $formData['name'] = 'a_email.eml';
	 *               $formData['type'] = 'message/rfc822';
	 *               $formData['file'] = 'vfs://default/home/leithoff/a_email.eml';
	 *               $formData['size'] = 2136;
	 * @param string $mode mode to open ImportedMessage display and edit are supported
	 */
	public function importMessageFromVFS2DraftAndDisplay($formData='', $mode='display') : void
	{
		if (empty($formData) && isset($_REQUEST['formData']))
		{
			$formData = $_REQUEST['formData'];
		}
		$draftFolder = $this->ui->mail_bo->getDraftFolder(false);
		$importID = Mail::getRandomString();

		// handling for mime-data hash
		if (!empty($formData['data']))
		{
			$formData['file'] = 'egw-data://'.$formData['data'];
		}
		// name should be set to meet the requirements of checkFileBasics
		if (parse_url($formData['file'], PHP_URL_SCHEME) == 'vfs' && empty($formData['name']))
		{
			$buff = explode('/', $formData['file']);
			if (is_array($buff))
			{
				$formData['name'] = array_pop($buff); // take the last part as name
			}
		}
		// type should be set to meet the requirements of checkFileBasics
		if (parse_url($formData['file'], PHP_URL_SCHEME) == 'vfs' && empty($formData['type']))
		{
			$buff = explode('.', $formData['file']);
			$suffix = '';
			if (is_array($buff))
			{
				$suffix = array_pop($buff); // take the last extension to check with ext2mime
			}
			if (!empty($suffix))
			{
				$formData['type'] = Api\MimeMagic::ext2mime($suffix);
			}
		}
		// size should be set to meet the requirements of checkFileBasics
		if (parse_url($formData['file'], PHP_URL_SCHEME) == 'vfs' && !isset($formData['size']))
		{
			$formData['size'] = strlen($formData['file']); // set some size, to meet requirements of checkFileBasics
		}
		try
		{
			$rowId = $this->importMessageToFolder($formData, $draftFolder, $importID);
			$linkData = [
				'menuaction' => $mode == 'display' ? 'mail.mail_ui.displayMessage' : 'mail.mail_compose.composeFromDraft',
				'id' => $rowId,
				'deleteDraftOnClose' => 1,
			];
			if ($mode != 'display')
			{
				unset($linkData['deleteDraftOnClose']);
				$linkData['method'] = 'importMessageToMergeAndSend';
			}
			else
			{
				$linkData['mode'] = $mode;
			}
			Egw::redirect_link('/index.php', $linkData);
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			Framework::window_close($e->getMessage());
		}
	}
}
