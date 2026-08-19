<?php
/**
 * EGroupware Mail: folder ajax handlers (list/subscribe/create/rename/move/delete)
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\FolderHelpers;
use Horde_Imap_Client_Exception;
use mail_ui;

/**
 * Folder ajax handlers, extracted from mail_ui.
 *
 * Not independent of mail_ui/Api\Mail instance state (folder existence/status checks, hierarchy-
 * delimiter lookups, the connected mail_bo/mail_tree) - takes the owning `mail_ui` as a
 * constructor dependency, the same pattern ImportHandler/MessageActionHandler/AttachmentHandler/
 * MessageDisplayHandler already use. See doc/ai/projects/mail-bo-decoupling.md.
 *
 * These are all classic-fallback paths: the folder-tree JMAP migration
 * (doc/ai/projects/mail-folder-tree-jmap.md) made the main index tree's browsing, create/rename/
 * move/delete/subscribe actions, the subscribe popup, and the folder-management dialog's tree/
 * delete all JMAP-first client-side - every method here is what they fall back to when JMAP isn't
 * reachable, plus the mobile subscribe template, which stays classic-only. Every `mail_ui::ajax_*`
 * method name stays in place as a one-line delegation - required because EGroupware's ajax
 * dispatcher and the .xet template `autoloading=` attribute both resolve handlers by
 * `mail_ui::methodName`, not by class-agnostic name.
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
				$response->call('app.mail.mail_setFolderStatus',$oA);
			}
		}
	}

	/**
	 * This function creates folder/subfolder based on its selected parent
	 *
	 * @param string $_parent folder name or profile+folder name to add a folder to
	 * @param string $_new new folder name to be created
	 */
	public function addFolder($_parent, $_new)
	{
		$error='';
		$created = false;
		$response = Api\Json\Response::get();
		$del = $this->ui->mail_bo->getHierarchyDelimiter(false);
		if (strpos($_new, $del) !== FALSE)
		{
			return $response->call('egw.message', lang('failed to rename %1 ! Reason: %2 is not allowed!',$_parent, $del));
		}
		if ($_parent)
		{
			$parent = FolderHelpers::decodeEntityFolderName($_parent);
			//the conversion is handeled by horde, frontend interaction is all utf-8
			$new = FolderHelpers::decodeEntityFolderName($_new);

			list($profileID,$p_no_delimiter) = explode(mail_ui::$delimiter,$parent,2);

			if (is_numeric($profileID))
			{
				if ($profileID != $this->ui->mail_bo->profileID) $this->ui->changeProfile ($profileID);
				$delimiter = $this->ui->mail_bo->getHierarchyDelimiter(false);
				$parts = explode($delimiter,$new);

				if (!!empty($parent)) $folderStatus = $this->ui->mail_bo->getFolderStatus($parent,false);

				//open the INBOX
				$this->ui->mail_bo->reopen('INBOX');

				// if $new has delimiter ($del) in it, we need to create the subtree
				if (!empty($parts))
				{
					$counter = 0;
					foreach($parts as $subTree)
					{
						$err = null;
						if(($new = $this->ui->mail_bo->createFolder($p_no_delimiter, $subTree, $err)))
						{
							$counter++;
							if (!$p_no_delimiter)
							{
								// we first test below INBOX, because testing just the name wrongly reports it as subscribed
								// for servers not allowing to create folders parallel to INBOX
								$status = $this->ui->mail_bo->getFolderStatus('INBOX'.$delimiter.$new,false, true, true) ?:
									$this->ui->mail_bo->getFolderStatus($new,false, true, true);
								if (!$status['subscribed'])
								{
									try
									{
										$this->ui->mail_bo->icServer->subscribeMailbox ('INBOX'.$delimiter.$new);
									}
									catch(Horde_Imap_Client_Exception $e)
									{
										$error = Lang('Folder %1 has been created successfully,'.
												' although the subscription failed because of %2', $new, $e->getMessage());
									}
								}
							}
						}
						else
						{
							if (!$p_no_delimiter)
							{
								$new = $this->ui->mail_bo->createFolder('INBOX', $subTree, $err);
								if ($new) $counter++;
							}
							else
							{
								$error .= $err;
							}
						}
					}
					if ($counter == count($parts)) $created=true;
				}
				if (!empty($new)) $this->ui->mail_bo->reopen($new);
			}


			if ($created===true && $error =='')
			{
				$this->ui->mail_bo->resetFolderObjectCache($profileID);
				if ( $folderStatus['shortDisplayName'])
				{
					$nodeInfo = array($parent=>$folderStatus['shortDisplayName']);
				}
				else
				{
					$nodeInfo = array($profileID=>lang('INBOX'));
				}
				$response->call('app.mail.mail_reloadNode',$nodeInfo);
			}
			else
			{
				if ($error)
				{
					$response->call('egw.message',$error);
				}
			}
		}
		else {
			error_log(__METHOD__.__LINE__."()"."This function needs a parent folder to work!");
		}
	}

	/**
	 * ajax_renameFolder - rename+refresh a folder
	 *
	 * @param string $_folderName folder to rename and refresh
	 * @param string $_newName new foldername
	 * @return nothing
	 */
	public function renameFolder($_folderName, $_newName)
	{
		if (Mail::$debug) error_log(__METHOD__.__LINE__.' OldFolderName:'.array2string($_folderName).' NewName:'.array2string($_newName));
		$response = Api\Json\Response::get();
		$del = $this->ui->mail_bo->getHierarchyDelimiter(false);
		if (strpos($_newName, $del) !== FALSE)
		{
			return $response->call('egw.message', lang('failed to rename %1 ! Reason: %2 is not allowed!',$_folderName, $del));
		}

		if ($_folderName)
		{
			Api\Translation::add_app('mail');
			$decodedFolderName = FolderHelpers::decodeEntityFolderName($_folderName);
			$_newName = FolderHelpers::decodeEntityFolderName($_newName);

			$oA = array();
			list($profileID,$folderName) = explode(mail_ui::$delimiter,$decodedFolderName,2);
			$hasChildren = false;
			if (is_numeric($profileID))
			{
				if ($profileID != $this->ui->mail_bo->profileID) $this->ui->changeProfile ($profileID);
				$pA = explode($del,$folderName);
				array_pop($pA);
				$parentFolder = implode($del,$pA);
				if (strtoupper($folderName)!= 'INBOX')
				{
					$oldFolderInfo = $this->ui->mail_bo->getFolderStatus($folderName,false);
					if (!empty($oldFolderInfo['attributes']) && stripos(array2string($oldFolderInfo['attributes']),'\hasnochildren')=== false)
					{
						$hasChildren=true; // translates to: hasChildren -> dynamicLoading
						$delimiter = $this->ui->mail_bo->getHierarchyDelimiter();
						$nameSpace = $this->ui->mail_bo->_getNameSpaces();
						$prefix = $this->ui->mail_bo->getFolderPrefixFromNamespace($nameSpace, $folderName);
						$fragments = array();
						$subFolders = $this->ui->mail_bo->getMailBoxesRecursive($folderName, $delimiter, $prefix);
						foreach ($subFolders as $k => $folder)
						{
							// we do not monitor failure or success on subfolders
							if ($folder == $folderName)
							{
								unset($subFolders[$k]);
							}
							else
							{
								$rv = $this->ui->mail_bo->icServer->subscribeMailbox($folder, false);
								$fragments[$profileID.mail_ui::$delimiter.$folder] = substr($folder,strlen($folderName));
							}
						}
					}

					$this->ui->mail_bo->reopen('INBOX');
					$success = false;
					try
					{
						if(($newFolderName = $this->ui->mail_bo->renameFolder($folderName, $parentFolder, $_newName)))
						{
							$this->ui->mail_bo->resetFolderObjectCache($profileID);
							//enforce the subscription to the newly named server, as it seems to fail for names with umlauts
							$this->ui->mail_bo->icServer->subscribeMailbox($newFolderName, true);
							$this->ui->mail_bo->icServer->subscribeMailbox($folderName, false);
							$success = true;
						}
					}
					catch (\Exception $e)
					{
						$newFolderName=$folderName;
						$msg = $e->getMessage();
					}
					$this->ui->mail_bo->reopen($newFolderName);
					$fS = $this->ui->mail_bo->getFolderStatus($newFolderName,false);
					if ($hasChildren)
					{
						$subFolders = $this->ui->mail_bo->getMailBoxesRecursive($newFolderName, $delimiter, $prefix);
						foreach ($subFolders as $k => $folder)
						{
							// we do not monitor failure or success on subfolders
							if ($folder == $folderName)
							{
								unset($subFolders[$k]);
							}
							else
							{
								$rv = $this->ui->mail_bo->icServer->subscribeMailbox($folder, true);
							}
						}
					}

					$oA[$_folderName]['id'] = $profileID.mail_ui::$delimiter.$newFolderName;
					$oA[$_folderName]['olddesc'] = $oldFolderInfo['shortDisplayName'];
					if ($fS['unseen'])
					{
						$oA[$_folderName]['desc'] = $fS['shortDisplayName'];
						$oA[$_folderName]['unseenCount'] = $fS['unseen'];
					}
					else
					{
						$oA[$_folderName]['desc'] = $fS['shortDisplayName'];
					}
					foreach($fragments as $oldFolderName => $fragment)
					{
						$oA[$oldFolderName]['id'] = $profileID.mail_ui::$delimiter.$newFolderName.$fragment;
						$oA[$oldFolderName]['olddesc'] = '#skip-user-interaction-message#';
						$fS = $this->ui->mail_bo->getFolderStatus($newFolderName.$fragment,false);
						if ($fS['unseen'])
						{
							$oA[$oldFolderName]['desc'] = $fS['shortDisplayName'].' ('.$fS['unseen'].')';
						}
						else
						{
							$oA[$oldFolderName]['desc'] = $fS['shortDisplayName'];
						}
					}
				}
			}
			if ($folderName==$this->ui->mail_bo->sessionData['mailbox'])
			{
				$this->ui->mail_bo->sessionData['mailbox']=$newFolderName;
				$this->ui->mail_bo->saveSessionData();
				Framework::ajax_set_preference('mail', $this->ui->mail_bo->profileID.'_LastFolder', $newFolderName);
			}
			$response = Api\Json\Response::get();
			if ($oA && $success)
			{
				$response->call('app.mail.mail_setLeaf',$oA);
			}
			else
			{
				$response->call('egw.refresh',lang('failed to rename %1 ! Reason: %2',$oldFolderName,$msg),'mail');
			}
		}
	}

	/**
	 * move folder
	 *
	 * @param string _folderName  folder to vove
	 * @param string _target target folder
	 * @return void
	 */
	public function moveFolder($_folderName, $_target)
	{
		if (Mail::$debug) error_log(__METHOD__.__LINE__."Move Folder: $_folderName to Target: $_target");
		if ($_folderName)
		{
			$decodedFolderName = FolderHelpers::decodeEntityFolderName($_folderName);
			$_newLocation2 = FolderHelpers::decodeEntityFolderName($_target);
			list($profileID,$folderName) = explode(mail_ui::$delimiter,$decodedFolderName,2);
			list($newProfileID,$_newLocation) = explode(mail_ui::$delimiter,$_newLocation2,2);
			if ($profileID != $this->ui->mail_bo->profileID || $profileID != $newProfileID) $this->ui->changeProfile($profileID);
			$del = $this->ui->mail_bo->getHierarchyDelimiter(false);
			$hasChildren = false;
			if (is_numeric($profileID))
			{
				$pA = explode($del,$folderName);
				$namePart = array_pop($pA);
				$_newName = $namePart;
				$oldParentFolder = implode($del,$pA);
				$parentFolder = $_newLocation;

				if (strtoupper($folderName)!= 'INBOX' &&
					(($oldParentFolder === $parentFolder) || //$oldParentFolder == $parentFolder means move on same level
					(($oldParentFolder != $parentFolder &&
					strlen($parentFolder)>0 && strlen($folderName)>0 &&
					strpos($parentFolder,$folderName)===false)))) // indicates that we move the older up the tree within its own branch
				{
					$oldFolderInfo = $this->ui->mail_bo->getFolderStatus($folderName,false,false,false);
					if (!empty($oldFolderInfo['attributes']) && stripos(array2string($oldFolderInfo['attributes']),'\hasnochildren')=== false)
					{
						$hasChildren=true; // translates to: hasChildren -> dynamicLoading
						$delimiter = $this->ui->mail_bo->getHierarchyDelimiter();
						$nameSpace = $this->ui->mail_bo->_getNameSpaces();
						$prefix = $this->ui->mail_bo->getFolderPrefixFromNamespace($nameSpace, $folderName);

						$subFolders = $this->ui->mail_bo->getMailBoxesRecursive($folderName, $delimiter, $prefix);
						foreach ($subFolders as $k => $folder)
						{
							// we do not monitor failure or success on subfolders
							if ($folder == $folderName)
							{
								unset($subFolders[$k]);
							}
							else
							{
								$rv = $this->ui->mail_bo->icServer->subscribeMailbox($folder, false);
							}
						}
					}

					$this->ui->mail_bo->reopen('INBOX');
					$success = false;
					try
					{
						if(($newFolderName = $this->ui->mail_bo->renameFolder($folderName, $parentFolder, $_newName)))
						{
							$this->ui->mail_bo->resetFolderObjectCache($profileID);
							//enforce the subscription to the newly named server, as it seems to fail for names with umlauts
							$this->ui->mail_bo->icServer->subscribeMailbox($newFolderName, true);
							$this->ui->mail_bo->icServer->subscribeMailbox($folderName, false);
							$this->ui->mail_bo->resetFolderObjectCache($profileID);
							$success = true;
						}
					}
					catch (\Exception $e)
					{
						$newFolderName=$folderName;
						$msg = $e->getMessage();
					}
					$this->ui->mail_bo->reopen($parentFolder);
					$this->ui->mail_bo->getFolderStatus($parentFolder,false,false,false);
					if ($hasChildren)
					{
						$subFolders = $this->ui->mail_bo->getMailBoxesRecursive($parentFolder, $delimiter, $prefix);
						foreach ($subFolders as $k => $folder)
						{
							// we do not monitor failure or success on subfolders
							if ($folder == $folderName)
							{
								unset($subFolders[$k]);
							}
							else
							{
								$rv = $this->ui->mail_bo->icServer->subscribeMailbox($folder, true);
							}
						}
					}
				}
			}
			if ($folderName==$this->ui->mail_bo->sessionData['mailbox'])
			{
				$this->ui->mail_bo->sessionData['mailbox']=$newFolderName;
				$this->ui->mail_bo->saveSessionData();
				Framework::ajax_set_preference('mail', $this->ui->mail_bo->profileID.'_LastFolder', $newFolderName);
			}
			$response = Api\Json\Response::get();
			if ($success)
			{
				Api\Translation::add_app('mail');

				$oldFolderInfo = $this->ui->mail_bo->getFolderStatus($oldParentFolder,false,false,false);
				$folderInfo = $this->ui->mail_bo->getFolderStatus($parentFolder,false,false,false);
				$refreshData = array(
					$profileID.mail_ui::$delimiter.$oldParentFolder=>$oldFolderInfo['shortDisplayName'],
					$profileID.mail_ui::$delimiter.$parentFolder=>$folderInfo['shortDisplayName']);
				// if we move the folder within the same parent-branch of the tree, there is no need no refresh the upper part
				if (strlen($parentFolder)>strlen($oldParentFolder) && strpos($parentFolder,$oldParentFolder)!==false) unset($refreshData[$profileID.mail_ui::$delimiter.$parentFolder]);
				if (count($refreshData)>1 && strlen($oldParentFolder)>strlen($parentFolder) && strpos($oldParentFolder,$parentFolder)!==false) unset($refreshData[$profileID.mail_ui::$delimiter.$oldParentFolder]);

				// Send full info back in the response
				foreach($refreshData as $folder => &$name)
				{
					$name = $this->ui->mail_tree->getTree($folder,$profileID,1,false,!$this->ui->mail_bo->mailPreferences['showAllFoldersInFolderPane'],true);
				}
				$response->call('app.mail.mail_reloadNode',$refreshData);
			}
			else
			{
				$response->call('egw.refresh',lang('failed to move %1 ! Reason: %2',$folderName,$msg),'mail');
			}
		}
	}

	/**
	 * ajax_deleteFolder - delete a folder
	 *
	 * @param string $_folderName folder to delete
	 * @param boolean $_return = false wheter return the success value (true) or send response to client (false)
	 * @return nothing
	 */
	public function deleteFolder($_folderName, $_return = false)
	{
		$success = false;
		if ($_folderName)
		{
			$decodedFolderName = FolderHelpers::decodeEntityFolderName($_folderName);
			$oA = array();
			list($profileID,$folderName) = explode(mail_ui::$delimiter,$decodedFolderName,2);
			if (is_numeric($profileID) && $profileID != $this->ui->mail_bo->profileID) $this->ui->changeProfile ($profileID);
			$del = $this->ui->mail_bo->getHierarchyDelimiter(false);
			$hasChildren = false;
			if (is_numeric($profileID))
			{
				$pA = explode($del,$folderName);
				array_pop($pA);
				if (strtoupper($folderName)!= 'INBOX')
				{
					$oA = array();
					$subFolders = array();
					$oldFolderInfo = $this->ui->mail_bo->getFolderStatus($folderName,false,false,false);
					if (!empty($oldFolderInfo['attributes']) && stripos(array2string($oldFolderInfo['attributes']),'\hasnochildren')=== false)
					{
						$hasChildren=true; // translates to: hasChildren -> dynamicLoading
						$ftD = array();
						$delimiter = $this->ui->mail_bo->getHierarchyDelimiter();
						$nameSpace = $this->ui->mail_bo->_getNameSpaces();
						$prefix = $this->ui->mail_bo->getFolderPrefixFromNamespace($nameSpace, $folderName);
						$subFolders = $this->ui->mail_bo->getMailBoxesRecursive($folderName, $delimiter, $prefix);
						foreach ($subFolders as $k => $f)
						{
							$ftD[substr_count($f,$delimiter)][]=$f;
						}
						krsort($ftD,SORT_NUMERIC);//sort per level
						//we iterate per level of depth of the subtree, deepest nesting is to be deleted first, and then up the tree
						foreach($ftD as $k => $lc)//collection per level
						{
							foreach($lc as $f)//folders contained in that level
							{
								try
								{
									$this->ui->mail_bo->deleteFolder($f);
									$success = true;
									if ($f==$folderName) $oA[$_folderName] = $oldFolderInfo['shortDisplayName'];
								}
								catch (\Exception $e)
								{
									$msg .= ($msg?' ':'').lang("Failed to delete %1. Server responded:",$f).$e->getMessage();
									$success = false;
								}
							}
						}
					}
					else
					{
						try
						{
							$this->ui->mail_bo->deleteFolder($folderName);
							$success = true;
							$oA[$_folderName] = $oldFolderInfo['shortDisplayName'];
						}
						catch (\Exception $e)
						{
							$msg = $e->getMessage();
							$success = false;
						}
					}
				}
				else
				{
					$msg = lang("refused to delete folder INBOX");
				}
			}
			if ($_return) return $success;
			$response = Api\Json\Response::get();
			if ($success)
			{
				$response->call('app.mail.mail_removeLeaf',$oA);
			}
			else
			{
				$response->call('egw.refresh',lang('failed to delete %1 ! Reason: %2',$oldFolderInfo['shortDisplayName'],$msg),'mail');
			}
		}
	}

}
