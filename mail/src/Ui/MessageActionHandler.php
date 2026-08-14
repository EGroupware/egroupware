<?php
/**
 * EGroupware Mail: message-action ajax handlers (save/MDN)
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\AddressList;
use Horde_Mime_Headers;
use mail_ui;

/**
 * Message-action ajax handlers, extracted from mail_ui.
 *
 * This is a partial extraction of the "Message action ajax handlers" group from
 * doc/ai/projects/mail-bo-decoupling.md - only saveMessage()/ajax_sendMDN() moved here.
 * ajax_flagMessages()/ajax_deleteMessages()/ajax_copyMessages() stayed on mail_ui: each is a large
 * (100-200 line), heavily-branched legacy method that calls back into OTHER not-yet-extracted
 * mail_ui internals (`self::ajax_setFolderStatus()` - itself blocked on
 * doc/ai/projects/mail-folder-tree-jmap.md, `self::generateRowID()`, `self::get_actions()`,
 * `self::$delimiter`), so moving them now would be a large, high-risk mechanical diff with none of
 * Phase 1's testability payoff and no real organizational win either (they'd still be 100% coupled
 * back to mail_ui). ajax_saveModifiedMessageSubject() was also left in place - it depends on
 * `self::fetchMessageBytesJmap()`/`self::replaceMessageJmap()`, private statics that belong to the
 * not-yet-extracted "Attachment/body-fetch ajax handlers" group, not this one.
 *
 * Like ImportHandler, this takes the owning mail_ui as a constructor dependency rather than being
 * a zero-dependency class - see the "session dependency shape" note in mail-bo-decoupling.md.
 */
class MessageActionHandler
{
	private mail_ui $ui;

	public function __construct(mail_ui $ui)
	{
		$this->ui = $ui;
	}

	/**
	 * Save message on disk or filemanager, or display it in popup
	 *
	 * all params are passed as GET Parameters
	 */
	public function saveMessage() : void
	{
		$display = false;
		if (isset($_GET['id']))
		{
			$rowID = $_GET['id'];
		}
		if (isset($_GET['part']))
		{
			$partID = $_GET['part'];
		}
		if (isset($_GET['location']) && ($_GET['location'] == 'display' || $_GET['location'] == 'filemanager'))
		{
			$display = $_GET['location'];
		}

		$hA = Mail::splitRowID($rowID);
		$uid = $hA['msgUID'];
		$mailbox = $hA['folder'];
		$icServerID = $hA['profileID'];
		$rememberServerID = $this->ui->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($icServerID);
		}

		$this->ui->mail_bo->reopen($mailbox);

		$message = $this->ui->mail_bo->getMessageRawBody($uid, $partID, $mailbox);

		$this->ui->mail_bo->closeConnection();
		if ($rememberServerID != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($rememberServerID);
		}

		$GLOBALS['egw']->session->commit_session();
		$headers = Horde_Mime_Headers::parseHeaders($message);
		$subject = str_replace('$$', '__', AddressList::decode_header($headers['SUBJECT']));
		if (!$display)
		{
			$subject = Mail::clean_subject_for_filename($subject);
			$mime = 'message/rfc822';
			Api\Header\Content::safe($message, $subject.".eml", $mime);
			echo $message;
		}
		else
		{
			$subject = Mail::clean_subject_for_filename($subject);
			$mime = 'text/html';
			$size = 0;
			Api\Header\Content::safe($message, $subject.".eml", $mime, $size, true, false);
			print '<pre>'.htmlspecialchars($message, ENT_NOQUOTES | ENT_SUBSTITUTE, 'utf-8').'</pre>';
		}
	}

	/**
	 * sendMDN
	 *
	 * @param array $_messageList list of UID's
	 */
	public function sendMDN($_messageList) : void
	{
		$uidA = Mail::splitRowID($_messageList['msg'][0]);
		if ($uidA['profileID'] && $uidA['profileID'] != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($uidA['profileID']);
		}
		$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
		$this->ui->mail_bo->sendMDN($uidA['msgUID'], $folder);
	}
}
