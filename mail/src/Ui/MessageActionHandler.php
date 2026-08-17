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
use EGroupware\Api\Etemplate\Widget\Nextmatch;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\AddressList;
use EGroupware\Api\Mail\CustomLabels;
use EGroupware\Api\Mail\FolderHelpers;
use Horde_Mime_Headers;
use mail_ui;

/**
 * Message-action ajax handlers, extracted from mail_ui - the full "Message action ajax handlers"
 * group from doc/ai/projects/mail-bo-decoupling.md except ajax_saveModifiedMessageSubject(), which
 * actually belongs with "Attachment/body-fetch ajax handlers" (it shares that group's
 * fetchMessageBytesJmap()/replaceMessageJmap(), see Mail\Ui\AttachmentJmap).
 *
 * Like ImportHandler, this takes the owning mail_ui as a constructor dependency rather than being
 * a zero-dependency class - see the "session dependency shape" note in mail-bo-decoupling.md.
 * flagMessages()/deleteMessages()/copyMessages() call back into `mail_ui`'s own `get_actions()`
 * (widened from private to package-default so this class could call it via
 * `$this->ui->get_actions()` - it's pure UI-action array construction, nothing security-sensitive),
 * `Mail\Ui\FolderHandler::setFolderStatus()` (via `$this->ui->folderHandler()`, package-default for
 * the same reason), and the still-`mail_ui`-static `generateRowID()`/`$delimiter` (the "Row-id
 * helpers" group, deliberately left in place - see mail-bo-decoupling.md).
 *
 * emptySpam()/emptyTrash() (from mail_ui's `ajax_emptySpam`/`ajax_emptyTrash`) joined this group
 * later - see doc/ai/projects/mail-folder-tree-jmap.md's "Resolved" note: `app.ts`'s
 * `mail_emptySpam()`/`mail_emptyTrash()` already try a JMAP fast path (`MailJmap.purgeFolder()`)
 * first, so these are permanent classic-fallbacks, not folder-tree-migration-blocked code. They're
 * bulk message-clearing operations on a special folder (same domain as flagMessages()/
 * deleteMessages() above), not folder-tree code, hence living here rather than a new class.
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

	/**
	 * flag messages as read, unread, flagged, ...
	 *
	 * @param string $_flag name of the flag
	 * @param array $_messageList list of UID's
	 * @param bool $_sendJsonResponse tell fuction to send the JsonResponse
	 */
	public function flagMessages($_flag, $_messageList, $_sendJsonResponse=true) : void
	{
		if (Mail::$debug)
		{
			error_log(__METHOD__."->".$_flag.':'.array2string($_messageList));
		}
		Api\Translation::add_app('mail');
		$alreadyFlagged = false;
		$flag2check = '';
		$filter2toggle = $query = [];
		if ($_messageList == 'all' || !empty($_messageList['msg']))
		{
			if (isset($_messageList['all']) && $_messageList['all'])
			{
				// we have both messageIds AND allFlag folder information
				$uidA = Mail::splitRowID($_messageList['msg'][0]);
				if ($uidA['profileID'] && $uidA['profileID'] != $this->ui->mail_bo->profileID)
				{
					$this->ui->changeProfile($uidA['profileID']);
				}
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
				//_messageList['msg'][0] was in form of {accountID}::{folderName}
				// so we need to correct $folder and $profileID
				if (!$folder && !$uidA['msg'] && $uidA['accountID'])
				{
					$folder = $uidA['accountID'];
				}
				$profileID = $uidA['profileID'];
				if (!$profileID && !$uidA['msg'] && $uidA['app'])
				{
					$profileID = $uidA['app'];
				}
				//end correction
				if (isset($_messageList['activeFilters']) && $_messageList['activeFilters'])
				{
					$query = $_messageList['activeFilters'];
					if (!empty($query['search']) || !empty($query['filter']) || ($query['cat_id'] == 'bydate' && (!empty($query['startdate']) || !empty($query['enddate']))))
					{
						if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$this->ui->mail_bo->profileID]))
						{
							Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE, 'email', 'supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, [], 60*60*10);
							if (!isset(Mail::$supportsORinQuery[$this->ui->mail_bo->profileID]))
							{
								Mail::$supportsORinQuery[$this->ui->mail_bo->profileID] = true;
							}
						}
						$cutoffdate = $cutoffdate2 = null;
						if ($query['startdate'])
						{
							$cutoffdate = Api\DateTime::to($query['startdate'], 'ts');//SINCE, enddate
						}
						if ($query['enddate'])
						{
							$cutoffdate2 = Api\DateTime::to($query['enddate'], 'ts');//BEFORE, startdate
						}
						$filter = [
							'filterName' => lang('subject'),
							'type' => ($query['cat_id'] ?: 'subject'),
							'string' => $query['search'],
							'status' => '',//this is a status change. status will be manipulated later on
						];
						if ($query['enddate'] || $query['startdate'])
						{
							$filter['range'] = "BETWEEN";
							if ($cutoffdate)
							{
								$filter[(empty($cutoffdate2) ? 'date' : 'since')] = date("d-M-Y", $cutoffdate);
								if (empty($cutoffdate2))
								{
									$filter['range'] = "SINCE";
								}
							}
							if ($cutoffdate2)
							{
								$filter[(empty($cutoffdate) ? 'date' : 'before')] = date("d-M-Y", $cutoffdate2);
								if (empty($cutoffdate))
								{
									$filter['range'] = "BEFORE";
								}
							}
						}
						$filter2toggle = $filter;
					}
					else
					{
						$filter = $filter2toggle = [];
					}
					// read and flagged can be toggled here for legacy non-JMAP callers
					// should be affected serverside. here.
					$messageList = $messageListForToggle = [];
					$flag2check = ($_flag == 'read' ? 'seen' : $_flag);
					if (in_array($_flag, ['read', 'flagged']) &&
						!($flag2check == $query['filter'] || stripos($query['filter'], $flag2check) !== false))
					{
						$filter2toggle['status'] = ['un'.$_flag];
						if ($query['filter'])
						{
							$filter2toggle['status'][] = $query['filter'];
						}
						$reverse = 1;
						$rByUid = true;
						$_sRt = $this->ui->mail_bo->getSortedList($folder, $sort = 0, $reverse, $filter2toggle, $rByUid, false);
						$messageListForToggle = $_sRt['match']->ids;
						$filter['status'] = [$_flag];
						if ($query['filter'])
						{
							$filter['status'][] = $query['filter'];
						}
						$reverse = 1;
						$rByUid = true;
						$_sR = $this->ui->mail_bo->getSortedList($folder, $sort = 0, $reverse, $filter, $rByUid, false);
						$messageList = $_sR['match']->ids;
						if (count($messageListForToggle) > 0)
						{
							$flag2set = strtolower($_flag);
							$this->ui->mail_bo->flagMessages($flag2set, $messageListForToggle, $folder);
						}
						if (count($messageList) > 0)
						{
							$flag2set = 'un'.$_flag;
							$this->ui->mail_bo->flagMessages($flag2set, $messageList, $folder);
						}
						$alreadyFlagged = true;
					}
					elseif (!empty($filter) &&
						(!in_array($_flag, ['read', 'flagged']) ||
							(in_array($_flag, ['read', 'flagged']) &&
								($flag2check == $query['filter'] || stripos($query['filter'], $flag2check) !== false))))
					{
						if ($query['filter'])
						{
							$filter['status'] = $query['filter'];
							// since we toggle and we toggle by the filtered flag we must must change _flag
							$_flag = ($query['filter'] == 'unseen' && $_flag == 'read' ? 'read' : ($query['filter'] == 'seen' && $_flag == 'read' ? 'unread' : ($_flag == $query['filter'] ? 'un'.$_flag : $_flag)));
						}
						$rByUid = true;
						$reverse = 1;
						$_sR = $this->ui->mail_bo->getSortedList($folder, $sort = 0, $reverse, $filter, $rByUid, false);
						$messageList = $_sR['match']->ids;
						unset($_messageList['all']);
						$_messageList['msg'] = [];
					}
					else
					{
						$alreadyFlagged = true;
						$uidA = Mail::splitRowID($_messageList['msg'][0]);
						$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
						$this->ui->mail_bo->flagMessages($_flag, 'all', $folder);
					}
				}
			}
			else
			{
				$uidA = Mail::splitRowID($_messageList['msg'][0]);
				if ($uidA['profileID'] && $uidA['profileID'] != $this->ui->mail_bo->profileID)
				{
					$this->ui->changeProfile($uidA['profileID']);
				}
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
			}
			if (!$alreadyFlagged)
			{
				foreach ($_messageList['msg'] as $rowID)
				{
					$hA = Mail::splitRowID($rowID);
					$messageList[] = $hA['msgUID'];
				}
				$this->ui->mail_bo->flagMessages(
					$_flag,
					((isset($_messageList['all']) && $_messageList['all']) ? 'all' : $messageList),
					$folder
				);
			}
		}

		if ($_sendJsonResponse)
		{
			$flag = [
				'label1' => 'important',
				'label2' => 'job',
				'label3' => 'personal',
				'label4' => 'to do',
				'label5' => 'later',
				'customFlag1' => 'red',
				'customFlag2' => 'orange',
				'customFlag3' => 'green',
				'customFlag4' => 'blue',
				'customFlag5' => 'purple',
			];
			foreach (CustomLabels::getCustomLabels() as $id => $customLabel)
			{
				$flag[$id] = $customLabel['name'];
			}
			$response = Api\Json\Response::get();
			if (isset($_messageList['msg']) && $_messageList['popup'])
			{
				$response->call('egw.refresh', lang('flagged %1 messages as %2 in %3', $_messageList['msg'], lang(($flag[$_flag] ?: $_flag)), lang($folder)), 'mail', $_messageList['msg'], 'update');
			}
			elseif ((isset($_messageList['all']) && $_messageList['all']) || ($query['filter'] && ($flag2check == $query['filter'] || stripos($query['filter'], $flag2check) !== false)))
			{
				$this->ui->folderHandler()->setFolderStatus([$profileID."::".$folder], true);
				$response->call('egw.refresh', lang('flagged %1 messages as %2 in %3', (isset($_messageList['all']) && $_messageList['all'] ? lang('all') : count($_messageList['msg'])), lang(($flag[$_flag] ?: $_flag)), lang($folder)), 'mail');
			}
			else
			{
				$response->call(
					'egw.refresh',
					lang('flagged %1 messages as %2 in %3', (isset($_messageList['all']) && $_messageList['all'] ? lang('all') : count($_messageList['msg'])), lang(($flag[$_flag] ?: $_flag)), lang($folder)),
					'mail',
					$_messageList['msg'],
					'update-in-place'
				);
			}
		}
	}

	/**
	 * delete messages
	 *
	 * @param array $_messageList list of UID's
	 * @param string $_forceDeleteMethod - method of deletion to be enforced
	 */
	public function deleteMessages($_messageList, $_forceDeleteMethod=null) : void
	{
		$error = null;
		if ($_messageList == 'all' || !empty($_messageList['msg']))
		{
			if (isset($_messageList['all']) && $_messageList['all'])
			{
				// we have both messageIds AND allFlag folder information
				$uidA = Mail::splitRowID($_messageList['msg'][0]);
				if ($uidA['profileID'] && $uidA['profileID'] != $this->ui->mail_bo->profileID)
				{
					$this->ui->changeProfile($uidA['profileID']);
				}
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
				if (isset($_messageList['activeFilters']) && $_messageList['activeFilters'])
				{
					$query = $_messageList['activeFilters'];
					if (!empty($query['search']) || !empty($query['filter']) || ($query['cat_id'] == 'bydate' && (!empty($query['startdate']) || !empty($query['enddate']))))
					{
						if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$this->ui->mail_bo->profileID]))
						{
							Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE, 'email', 'supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, [], 60*60*10);
							if (!isset(Mail::$supportsORinQuery[$this->ui->mail_bo->profileID]))
							{
								Mail::$supportsORinQuery[$this->ui->mail_bo->profileID] = true;
							}
						}
						$cutoffdate = $cutoffdate2 = null;
						if ($query['startdate'])
						{
							$cutoffdate = Api\DateTime::to($query['startdate'], 'ts');//SINCE, enddate
						}
						if ($query['enddate'])
						{
							$cutoffdate2 = Api\DateTime::to($query['enddate'], 'ts');//BEFORE, startdate
						}
						$filter = [
							'filterName' => lang('subject'),
							'type' => $query['cat_id'] ?: 'subject',
							'string' => $query['search'],
							'status' => (string)$query['filter'],
						];
						if ($query['enddate'] || $query['startdate'])
						{
							$filter['range'] = "BETWEEN";
							if ($cutoffdate)
							{
								$filter[(empty($cutoffdate2) ? 'date' : 'since')] = date("d-M-Y", $cutoffdate);
								if (empty($cutoffdate2))
								{
									$filter['range'] = "SINCE";
								}
							}
							if ($cutoffdate2)
							{
								$filter[(empty($cutoffdate) ? 'date' : 'before')] = date("d-M-Y", $cutoffdate2);
								if (empty($cutoffdate))
								{
									$filter['range'] = "BEFORE";
								}
							}
						}
					}
					else
					{
						$filter = [];
					}
					$reverse = 1;
					$rByUid = true;
					$_sR = $this->ui->mail_bo->getSortedList($folder, $sort = 0, $reverse, $filter, $rByUid, false);
					$messageList = $_sR['match']->ids;
				}
				else
				{
					$messageList = 'all';
				}
				try
				{
					$this->ui->mail_bo->deleteMessages(($messageList == 'all' ? 'all' : $messageList), $folder, (empty($_forceDeleteMethod) ? 'no' : $_forceDeleteMethod));
				}
				catch (Api\Exception $e)
				{
					$error = str_replace('"', "'", $e->getMessage());
				}
			}
			else
			{
				$uidA = Mail::splitRowID($_messageList['msg'][0]);
				if ($uidA['profileID'] && $uidA['profileID'] != $this->ui->mail_bo->profileID)
				{
					$this->ui->changeProfile($uidA['profileID']);
				}
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
				foreach ($_messageList['msg'] as $rowID)
				{
					$hA = Mail::splitRowID($rowID);
					$messageList[] = $hA['msgUID'];
				}
				try
				{
					$this->ui->mail_bo->deleteMessages($messageList, $folder, (empty($_forceDeleteMethod) ? 'no' : $_forceDeleteMethod));
				}
				catch (Api\Exception $e)
				{
					$error = str_replace('"', "'", $e->getMessage());
				}
			}
			$response = Api\Json\Response::get();
			if (empty($error))
			{
				$this->ui->folderHandler()->setFolderStatus([$uidA['profileID']."::".$folder], true);
				$response->call('app.mail.mail_deleteMessagesShowResult', ['egw_message' => '', 'msg' => $_messageList['msg']]);
			}
			else
			{
				$error = str_replace('\n', "\n", lang('mailserver reported:\n%1 \ndo you want to proceed by deleting the selected messages immediately (click ok)?\nif not, please try to empty your trashfolder before continuing. (click cancel)', $error));
				$response->call('app.mail.mail_retryForcedDelete', ['response' => $error, 'messageList' => $_messageList]);
			}
		}
	}

	/**
	 * copy messages
	 *
	 * @param array $_folderName target folder
	 * @param array $_messageList list of UID's
	 * @param string $_copyOrMove method to use copy or move allowed
	 * @param string $_move2ArchiveMarker marker to indicate if a move 2 archive was triggered
	 * @param boolean $_return if true the function will return the result instead of
	 * responding to client
	 * @return string|void
	 */
	public function copyMessages($_folderName, $_messageList, $_copyOrMove='copy', $_move2ArchiveMarker='_', $_return=false)
	{
		Api\Translation::add_app('mail');
		$folderName = FolderHelpers::decodeEntityFolderName($_folderName);
		// only copy or move are supported as method
		if (!($_copyOrMove == 'copy' || $_copyOrMove == 'move'))
		{
			$_copyOrMove = 'copy';
		}
		[$targetProfileID, $targetFolder] = explode(mail_ui::$delimiter, $folderName, 2);
		// check if move2archive was called with the correct archiveFolder
		$archiveFolder = $this->ui->mail_bo->getArchiveFolder();
		if ($_move2ArchiveMarker == '2' && $targetFolder != $archiveFolder)
		{
			$targetProfileID = $this->ui->mail_bo->profileID;
			$targetFolder = $archiveFolder;
		}
		$lastFoldersUsedForMoveCont = Api\Cache::getCache(Api\Cache::INSTANCE, 'email', 'lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']), null, [], $expiration = 60*60*1);
		$changeFolderActions = false;
		if (!isset($lastFoldersUsedForMoveCont[$targetProfileID][$targetFolder]))
		{
			if ($lastFoldersUsedForMoveCont[$targetProfileID] && count($lastFoldersUsedForMoveCont[$targetProfileID]) > 3)
			{
				$keys = array_keys($lastFoldersUsedForMoveCont[$targetProfileID]);
				foreach ($keys as &$f)
				{
					if (count($lastFoldersUsedForMoveCont[$targetProfileID]) > 9)
					{
						unset($lastFoldersUsedForMoveCont[$targetProfileID][$f]);
					}
					else
					{
						break;
					}
				}
			}
			$lastFoldersUsedForMoveCont[$targetProfileID][$targetFolder] = $folderName;
			$changeFolderActions = true;
		}
		$filtered = false;
		if ($_messageList == 'all' || !empty($_messageList['msg']))
		{
			$error = false;
			if (isset($_messageList['all']) && $_messageList['all'])
			{
				// we have both messageIds AND allFlag folder information
				$uidA = Mail::splitRowID($_messageList['msg'][0]);
				$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
				$sourceProfileID = $uidA['profileID'];
				if (isset($_messageList['activeFilters']) && $_messageList['activeFilters'])
				{
					$query = $_messageList['activeFilters'];
					if (!empty($query['search']) || !empty($query['filter']) || ($query['cat_id'] == 'bydate' && (!empty($query['startdate']) || !empty($query['enddate']))))
					{
						if (is_null(Mail::$supportsORinQuery) || !isset(Mail::$supportsORinQuery[$this->ui->mail_bo->profileID]))
						{
							Mail::$supportsORinQuery = Api\Cache::getCache(Api\Cache::INSTANCE, 'email', 'supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']), null, [], 60*60*10);
							if (!isset(Mail::$supportsORinQuery[$this->ui->mail_bo->profileID]))
							{
								Mail::$supportsORinQuery[$this->ui->mail_bo->profileID] = true;
							}
						}
						$filtered = true;
						$cutoffdate = $cutoffdate2 = null;
						if ($query['startdate'])
						{
							$cutoffdate = Api\DateTime::to($query['startdate'], 'ts');//SINCE, enddate
						}
						if ($query['enddate'])
						{
							$cutoffdate2 = Api\DateTime::to($query['enddate'], 'ts');//BEFORE, startdate
						}
						$filter = [
							'filterName' => lang('subject'),
							'type' => ($query['cat_id'] ?: 'subject'),
							'string' => $query['search'],
							'status' => (!empty($query['filter']) ? $query['filter'] : 'any'),
						];
						if ($query['enddate'] || $query['startdate'])
						{
							$filter['range'] = "BETWEEN";
							if ($cutoffdate)
							{
								$filter[(empty($cutoffdate2) ? 'date' : 'since')] = date("d-M-Y", $cutoffdate);
								if (empty($cutoffdate2))
								{
									$filter['range'] = "SINCE";
								}
							}
							if ($cutoffdate2)
							{
								$filter[(empty($cutoffdate) ? 'date' : 'before')] = date("d-M-Y", $cutoffdate2);
								if (empty($cutoffdate))
								{
									$filter['range'] = "BEFORE";
								}
							}
						}
					}
					else
					{
						$filter = [];
					}
					$reverse = 1;
					$rByUid = true;
					$_sR = $this->ui->mail_bo->getSortedList($folder, $sort = 0, $reverse, $filter, $rByUid, false);
					$messageList = $_sR['match']->ids;
					foreach ($messageList as $uID)
					{
						if ($_copyOrMove == 'move')
						{
							$messageListForRefresh[] = mail_ui::generateRowID($sourceProfileID, $folderName, $uID, $_prependApp = false);
						}
					}
				}
				else
				{
					$messageList = 'all';
				}
				try
				{
					$this->ui->mail_bo->moveMessages($targetFolder, $messageList, ($_copyOrMove == 'copy' ? false : true), $folder, false, $sourceProfileID, ($targetProfileID != $sourceProfileID ? $targetProfileID : null));
				}
				catch (Api\Exception $e)
				{
					$error = str_replace('"', "'", $e->getMessage());
				}
			}
			else
			{
				$messageList = [];
				while (count($_messageList['msg']) > 0)
				{
					$uidA = Mail::splitRowID($_messageList['msg'][0]);
					$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
					$sourceProfileID = $uidA['profileID'];
					$moveList = [];
					foreach ($_messageList['msg'] as $rowID)
					{
						$hA = Mail::splitRowID($rowID);

						// If folder changes, stop and move what we've got
						if ($hA['folder'] != $folder)
						{
							break;
						}

						array_shift($_messageList['msg']);
						$messageList[] = $hA['msgUID'];
						$moveList[] = $hA['msgUID'];
						if ($_copyOrMove == 'move')
						{
							$helpvar = explode(mail_ui::$delimiter, $rowID);
							array_shift($helpvar);
							$messageListForRefresh[] = implode(mail_ui::$delimiter, $helpvar);
						}
					}
					try
					{
						$this->ui->mail_bo->moveMessages($targetFolder, $moveList, ($_copyOrMove == 'copy' ? false : true), $folder, false, $sourceProfileID, ($targetProfileID != $sourceProfileID ? $targetProfileID : null));
					}
					catch (Api\Exception $e)
					{
						$error = str_replace('"', "'", $e->getMessage());
					}
				}
			}

			$response = Api\Json\Response::get();
			if ($error)
			{
				if ($changeFolderActions == false)
				{
					unset($lastFoldersUsedForMoveCont[$targetProfileID][$targetFolder]);
					$changeFolderActions = true;
				}
				if ($_return)
				{
					return $error;
				}
				$response->call('egw.message', $error, "error");
			}
			else
			{
				if ($_copyOrMove == 'copy')
				{
					$msg = lang('copied %1 message(s) from %2 to %3', ($messageList == 'all' || $_messageList['all'] ? ($filtered ? lang('all filtered') : lang('all')) : count($messageList)), lang($folder), lang($targetFolder));
					if ($_return)
					{
						return $msg;
					}
					$response->call('egw.message', $msg);
				}
				else
				{
					$msg = lang('moved %1 message(s) from %2 to %3', ($messageList == 'all' || $_messageList['all'] ? ($filtered ? lang('all filtered') : lang('all')) : count($messageList)), lang($folder), lang($targetFolder));
					if ($_return)
					{
						return $msg;
					}
					foreach ($messageListForRefresh as $mail_id)
					{
						$response->call('egw.refresh', '', 'mail', $mail_id, 'delete');
					}
					$response->message($msg, 'success');
				}
			}
			if ($changeFolderActions == true)
			{
				Api\Cache::setCache(Api\Cache::INSTANCE, 'email', 'lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']), $lastFoldersUsedForMoveCont, $expiration = 60*60*1);
				$actionsnew = Nextmatch::egw_actions($this->ui->get_actions());
				$response->call('app.mail.mail_rebuildActionsOnList', $actionsnew);
			}
		}
	}

	/**
	 * Empty spam/junk folder
	 *
	 * @param string $icServerID id of the server to empty its junkFolder
	 * @param string $selectedFolder seleted(active) folder by nm filter
	 * @return nothing
	 */
	public function emptySpam($icServerID, $selectedFolder)
	{
		Api\Translation::add_app('mail');
		$response = Api\Json\Response::get();
		$rememberServerID = $this->ui->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($icServerID);
		}
		$junkFolder = $this->ui->mail_bo->getJunkFolder();
		if(!empty($junkFolder)) {
			if ($selectedFolder == $icServerID.mail_ui::$delimiter.$junkFolder)
			{
				// Lock the tree if the active folder is junk folder
				$response->call('app.mail.lock_tree');
			}
			$this->ui->mail_bo->deleteMessages('all',$junkFolder,'remove_immediately');
			$fStatus = array(
				$icServerID.mail_ui::$delimiter.$junkFolder => 0
			);
			//Call to reset folder status counter, after junkFolder triggered not from Junk folder
			//-as we don't have junk folder specific information available on client-side we need to deal with it on server
			$response->call('app.mail.mail_setFolderStatus',$fStatus);
		}
		if ($rememberServerID != $this->ui->mail_bo->profileID)
		{
			$oldFolderInfo = $this->ui->mail_bo->getFolderStatus($junkFolder,false,false,false);
			$response->call('egw.message',lang('empty junk'));
			$response->call('app.mail.mail_reloadNode',array($icServerID.mail_ui::$delimiter.$junkFolder=>$oldFolderInfo['shortDisplayName']));
			$this->ui->changeProfile($rememberServerID);
		}
		else if ($selectedFolder == $icServerID.mail_ui::$delimiter.$junkFolder)
		{
			$response->call('egw.refresh',lang('empty junk'),'mail');
		}
	}

	/**
	 * Empty trash folder
	 *
	 * @param string $icServerID id of the server to empty its trashFolder
	 * @param string $selectedFolder seleted(active) folder by nm filter
	 * @return nothing
	 */
	public function emptyTrash($icServerID, $selectedFolder)
	{
		Api\Translation::add_app('mail');
		$response = Api\Json\Response::get();
		$rememberServerID = $this->ui->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($icServerID);
		}
		$trashFolder = $this->ui->mail_bo->getTrashFolder();
		if(!empty($trashFolder)) {
			if ($selectedFolder == $icServerID.mail_ui::$delimiter.$trashFolder)
			{
				// Lock the tree if the active folder is Trash folder
				$response->call('app.mail.lock_tree');
			}
			$this->ui->mail_bo->compressFolder($trashFolder);
			$fStatus = array(
				$icServerID.mail_ui::$delimiter.$trashFolder => 0
			);
			//Call to reset folder status counter, after emptyTrash triggered not from Trash folder
			//-as we don't have trash folder specific information available on client-side we need to deal with it on server
			$response->call('app.mail.mail_setFolderStatus',$fStatus);
		}
		if ($rememberServerID != $this->ui->mail_bo->profileID)
		{
			$oldFolderInfo = $this->ui->mail_bo->getFolderStatus($trashFolder,false,false,false);
			$response->call('egw.message',lang('empty trash'));
			$response->call('app.mail.mail_reloadNode',array($icServerID.mail_ui::$delimiter.$trashFolder=>$oldFolderInfo['shortDisplayName']));
			$this->ui->changeProfile($rememberServerID);
		}
		else if ($selectedFolder == $icServerID.mail_ui::$delimiter.$trashFolder)
		{
			$response->call('egw.refresh',lang('empty trash'),'mail');
		}
	}
}
