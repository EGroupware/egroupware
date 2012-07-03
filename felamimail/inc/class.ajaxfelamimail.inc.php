<?php
/**
 * EGroupware - FeLaMiMail - xajax actions
 *
 * @link http://www.egroupware.org
 * @package felamimail
 * @author Lars Kneschke [lkneschke@linux-at-work.de]
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2004 by Lars Kneschke <lkneschke-AT-linux-at-work.de>
 * @copyright (c) 2009-10 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * a class containing / implementing the xajax actions triggered by javascript
 */
class ajaxfelamimail
{
		// which profile to use(currently only 0 is supported)
		var $imapServerID=0;

		// the object storing the data about the incoming imap server
		var $icServer;

		var $charset;

		var $_debug = false;

		// boolean if openConnection was successfull or not
		var $_connectionStatus;

		function ajaxfelamimail()
		{
			if($this->_debug) error_log("ajaxfelamimail::ajaxfelamimail");
			$this->charset		=  $GLOBALS['egw']->translation->charset();
			$this->bofelamimail	= CreateObject('felamimail.bofelamimail',$this->charset);
			$this->uiwidgets	= CreateObject('felamimail.uiwidgets');
			$this->_connectionStatus = $this->bofelamimail->openConnection();

			$this->sessionDataAjax	= $GLOBALS['egw']->session->appsession('ajax_session_data','felamimail');
			$this->sessionData	= $GLOBALS['egw']->session->appsession('session_data','felamimail');

			if(!isset($this->sessionDataAjax['folderName'])) {
				$this->sessionDataAjax['folderName'] = 'INBOX';
			}

			$this->icServer = $this->bofelamimail->mailPreferences->getIncomingServer($this->imapServerID);
		}

		function addACL($_accountName, $_aclData)
		{
			if($this->_debug) error_log("ajaxfelamimail::addACL");
			$response = new xajaxResponse();

			if(!empty($_accountName)) {
				$acl = implode('',(array)$_aclData['acl']);
				$data = $this->bofelamimail->setACL($this->sessionDataAjax['folderName'], $_accountName, $acl);
			}

			return $response->getXML();
		}

		/**
		* create a new folder
		*
		* @param string _parentFolder the name of the parent folder
		* @param string _newSubFolder the name of the new subfolder
		* @return xajax response
		*/
		function addFolder($_parentFolder, $_newSubFolder)
		{
			$parentFolder = $this->_decodeEntityFolderName($_parentFolder);
			$parentFolder = ($parentFolder == '--topfolder--' ? '' : $parentFolder);

			$newSubFolder = $GLOBALS['egw']->translation->convert($_newSubFolder, $this->charset, 'UTF7-IMAP');

			if($this->_debug) error_log("ajaxfelamimail::addFolder($parentFolder, $newSubFolder)");

			$response = new xajaxResponse();

			if($folderName = $this->bofelamimail->createFolder($parentFolder, $newSubFolder, true)) {
				$parentFolder = $this->_encodeFolderName($parentFolder);
				$folderName = $this->_encodeFolderName($folderName);
				$newSubFolder = $this->_encodeDisplayFolderName($newSubFolder);
				$response->addScript("tree.insertNewItem('$parentFolder','$folderName','$newSubFolder',onNodeSelect,'folderClosed.gif',0,0,'CHILD,CHECKED');");
			}

			$response->addAssign("newSubFolder", "value", '');

			return $response->getXML();
		}

		function changeSorting($_sortBy)
		{
			if($this->_debug) error_log("ajaxfelamimail::changeSorting");
			$this->sessionData['startMessage']	= 1;

			$oldSort = $this->sessionData['sort'];

			switch($_sortBy) {
				case 'date':
					$this->sessionData['sort'] = SORTDATE;
					break;
				case 'from':
					$this->sessionData['sort'] = SORTFROM;
					break;
				case 'size':
					$this->sessionData['sort'] = SORTSIZE;
					break;
				case 'subject':
					$this->sessionData['sort'] = SORTSUBJECT;
					break;
			}

			if($this->sessionData['sort'] == $oldSort) {
				$this->sessionData['sortReverse'] = !$this->sessionData['sortReverse'];
			} else {
				$this->sessionData['sortReverse'] = false;
			}

			$this->saveSessionData();

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		/**
		* removes any messages marked as delete from current folder
		*
		* @return xajax response
		*/
		function compressFolder()
		{
			if($this->_debug) error_log("ajaxfelamimail::compressFolder");
			$this->bofelamimail->restoreSessionData();
			$this->bofelamimail->compressFolder($this->sessionData['mailbox']);

			$bofilter = CreateObject('felamimail.bofilter');

			$sortResult = $this->bofelamimail->getSortedList(
				$this->sessionData['mailbox'],
				$this->sessionData['sort'],
				$this->sessionData['sortReverse'],
				$bofilter->getFilter($this->sessionData['activeFilter'])
			);

			if(!is_array($sortResult) || empty($sortResult)) {
				$messageCounter = 0;
			} else {
				$messageCounter = count($sortResult);
			}

			// $lastPage is the first message ID of the last page
			if($messageCounter > $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"]) {
				$lastPage = $messageCounter - ($messageCounter % $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"]) + 1;
				if($lastPage > $messageCounter)
					$lastPage -= $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
				if($this->sessionData['startMessage'] > $lastPage)
					$this->sessionData['startMessage'] = $lastPage;
			} else {
				$this->sessionData['startMessage'] = 1;
			}

			$this->saveSessionData();
			$GLOBALS['egw']->session->commit_session();

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		/**
		 * initiateACLTable
		 * creates the ACL table
		 *
		 * @param	string	$_folder folder to initiate the acl table for
		 *
		 * @return	string	html output for ACL table
		 */
		function initiateACLTable($_folder)
		{
			$response = new xajaxResponse();
			if ($folderACL = $this->bofelamimail->getIMAPACL($_folder)) {
				$response->addAssign("aclTable", "innerHTML", $this->createACLTable($folderACL));
			}
			return $response->getXML();
		}

		/**
		 * createACLTable
		 * creates the ACL table
		 *
		 * @param	array	$_acl	array containing acl data
		 *
		 * @return	string	html output for ACL table
		 */
		function createACLTable($_acl)
		{
			$aclList = array('l','r','s','w','i','p','c','d','a');
			$aclShortCuts = array(	'custom'	=> 'custom',
									'lrs'		=> 'readable',
									'lrsp'		=> 'post',
									'lrsip'		=> 'append',
									'lrswipcd'	=> 'write',
									'lrswipcda'	=>	'all'
								);

			ksort($_acl);

			foreach($_acl as $accountAcl) {
				$accountName = $accountAcl['USER'];
				$row .= '<tr class="row_on">';

				$row .= "<td><input type=\"checkbox\" name=\"accountName[]\" id=\"accountName\" value=\"$accountName\"></td>";

				$row .= "<td>$accountName</td>";

				foreach($aclList as $acl) {
					$row .= "<td><input type=\"checkbox\" name=\"acl[$accountName][$acl]\" id=\"acl_$accountName_$acl\"".
						(strpos($accountAcl['RIGHTS'],$acl) !== false ? 'checked' : '') .
						" onclick=\"xajax_doXMLHTTP('felamimail.ajaxfelamimail.updateSingleACL','$accountName','$acl',this.checked); document.getElementById('predefinedFor_$accountName').options[0].selected=true\"</td>";
				}

				$selectFrom = html::select('identity', $accountAcl['RIGHTS'], $aclShortCuts, false, "id=\"predefinedFor_$accountName\" style='width: 100px;' onChange=\"xajax_doXMLHTTP('felamimail.ajaxfelamimail.updateACL','$accountName',this.value)\"");

				$row .= "<td>$selectFrom</td>";

				$row .= "</tr>";
			}

			return "<table border=\"0\" style=\"width: 100%;\"><tr class=\"th\"><th>&nbsp;</th><th style=\"width:100px;\">Name</th><th>L</th><th>R</th><th>S</th><th>W</th><th>I</th><th>P</th><th>C</th><th>D</th><th>A</th><th>&nbsp;</th></tr>$row</table>";
		}

		function deleteACL($_aclData)
		{
			if($this->_debug) error_log("ajaxfelamimail::deleteACL");
			$response = new xajaxResponse();
			if(is_array($_aclData)) {
				foreach($_aclData['accountName'] as $accountName) {
					$data = $this->bofelamimail->deleteACL($this->sessionDataAjax['folderName'], $accountName);
				}

				if ($folderACL = $this->bofelamimail->getIMAPACL($this->sessionDataAjax['folderName'])) {
					$response->addAssign("aclTable", "innerHTML", $this->createACLTable($folderACL));
				}
			}
			return $response->getXML();
		}

		function deleteAttachment($_composeID, $_attachmentID)
		{
			if($this->_debug) error_log("ajaxfelamimail::deleteAttachment");
			$bocompose	= CreateObject('felamimail.bocompose', $_composeID);
			$bocompose->removeAttachment($_attachmentID);

			$response = new xajaxResponse();
			return $response->getXML();
		}

        function toggleEditor($_composeID, $_content ,$_mode)
        {
			if($this->_debug) error_log("ajaxfelamimail::toggleEditor->".$_mode.'->'.$_content);
	        $bocompose  = CreateObject('felamimail.bocompose', $_composeID);
			if($_mode == 'simple') {
				if($this->_debug) error_log(__METHOD__.$_content);
				#if (isset($GLOBALS['egw_info']['server']['enabled_spellcheck'])) $_mode = 'egw_simple_spellcheck';
	    		$this->sessionData['mimeType'] = 'html';
				// convert emailadresses presentet in angle brackets to emailadress only
				$_content = str_replace(array("\r\n","\n","\r","<br>"),array("<br>","<br>","<br>","\r\n"),$_content);
				$bocompose->replaceEmailAdresses($_content);
			} else {
				$this->sessionData['mimeType'] = 'text';
				$_content = str_replace(array("\r\n","\n","\r"),array("<br>","<br>","<br>"),$_content);
				$_content = $bocompose->_getCleanHTML($_content,false,false);
				$_content = $bocompose->convertHTMLToText($_content);
			}
			$htmlObject = html::fckEditorQuick('body', $_mode, $_content);
			$this->saveSessionData();
			$response = new xajaxResponse();
			$response->addScript('FCKeditorAPI_ConfirmCleanup();');
			$response->addScript('FCKeditorAPI_Cleanup();');
			$response->addAssign('editorArea', 'innerHTML', $htmlObject);
	        return $response->getXML();
        }


		/*
		* delete a existing folder
		*
		* @param string _folderName the name of the folder to be deleted
		*
		* @return xajax response
		*/
		function deleteFolder($_folderName)
		{
			$folderName = $this->_decodeEntityFolderName($_folderName);
			if($this->_debug) error_log("ajaxfelamimail::deleteFolder($_folderName)");
			$response = new xajaxResponse();

			// don't delete this folders
			if($folderName == 'INBOX' || $folderName == '--topfolder--') {
				return $response->getXML();
			}

			if($this->bofelamimail->deleteFolder($folderName)) {
				$folderName = $this->_encodeFolderName($folderName);
				$response->addScript("tree.deleteItem('$folderName',1);");
			}

			return $response->getXML();
		}

		/*
		* delete messages
		*
		* @param array _messageList list of UID's
		*
		* @return xajax response
		*/
		function deleteMessages($_messageList)
		{
			if($this->_debug) error_log(__METHOD__." called with Messages ".print_r($_messageList,true));
			$messageCount = 0;
			if(is_array($_messageList) && count($_messageList['msg']) > 0) $messageCount = count($_messageList['msg']);
			$this->bofelamimail->deleteMessages(($_messageList == 'all'? 'all':$_messageList['msg']));

			return $this->generateMessageList($this->sessionData['mailbox'],($_messageList=='all'?0:(-1*$messageCount)));
		}

		function deleteSignatures($_signatures)
		{
			if($this->_debug) error_log("ajaxfelamimail::deleteSignatures");
			$signatures = explode(",",$_signatures);
			require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.felamimail_bosignatures.inc.php');
			$boSignatures = new felamimail_bosignatures();

			$boSignatures->deleteSignatures($signatures);
			unset($signatures);
			$signatures = $boSignatures->getListOfSignatures();

			$response = new xajaxResponse();
			$response->addAssign('signatureTable', 'innerHTML', $this->uiwidgets->createSignatureTable($signatures));
			return $response->getXML();
		}

		function changeActiveAccount($accountData)
		{
			if($this->_debug) error_log("ajaxfelamimail::changeActiveAccount");
			require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.bopreferences.inc.php');
			$boPreferences  = CreateObject('felamimail.bopreferences');
			$boPreferences->setProfileActive(false);
			if ($accountData) $boPreferences->setProfileActive(true,$accountData);

			$response = new xajaxResponse();
			$response->addScript('refreshView();');
			return $response->getXML();
		}

		function deleteAccountData($accountIDs)
		{
			if($this->_debug) error_log("ajaxfelamimail::deleteAccountData");
			$accountData = explode(",",$accountIDs);
			require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.bopreferences.inc.php');
			$boPreferences  = CreateObject('felamimail.bopreferences');
			$boPreferences->deleteAccountData($accountData);
			$preferences =& $boPreferences->getPreferences();
			$allAccountData    = $boPreferences->getAllAccountData($preferences);
			foreach ((array)$allAccountData as $tmpkey => $accountData)
			{
				$identity =& $accountData['identity'];
				foreach($identity as $key => $value) {
					if(is_object($value) || is_array($value)) {
						continue;
					}
					switch($key) {
						default:
							$tempvar[$key] = $value;
					}
				}
				$accountArray[]=$tempvar;
			}
			$response = new xajaxResponse();
			$response->addAssign('userDefinedAccountTable', 'innerHTML', $this->uiwidgets->createAccountDataTable($accountArray));
			return $response->getXML();
		}

		/*
		* empty trash folder
		*
		* @return xajax response
		*/
		function emptyTrash()
		{
			if($this->_debug) error_log("ajaxfelamimail::emptyTrash Folder:".$this->bofelamimail->mailPreferences->preferences['trashFolder']);
			if(!empty($this->bofelamimail->mailPreferences->preferences['trashFolder'])) {
				$this->bofelamimail->compressFolder($this->bofelamimail->mailPreferences->preferences['trashFolder']);
			}

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function extendedSearch($_filterID)
		{
			// start displaying at message 1
			$this->sessionData['startMessage']      = 1;
			$this->sessionData['activeFilter']	= (int)$_filterID;
			$this->saveSessionData();
			$GLOBALS['egw']->session->commit_session();

			// generate the new messageview
			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		/*
		* flag messages as read, unread, flagged, ...
		*
		* @param string _flag name of the flag
		* @param array _messageList list of UID's
		*
		* @return xajax response
		*/
		function flagMessages($_flag, $_messageList)
		{
			if($this->_debug) error_log(__METHOD__."->".$_flag.':'.print_r($_messageList,true));
			if ($_messageList=='all' || !empty($_messageList['msg']))
			{
				$this->bofelamimail->flagMessages($_flag, ($_messageList=='all' ? 'all':$_messageList['msg']));
			}
			else
			{
				if($this->_debug) error_log(__METHOD__."-> No messages selected.");
			}

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function sendNotify ($_uid, $_ret)
		{
			$response = new xajaxResponse();
			if ($_ret==='true' || $_ret===1 || $_ret == "1,") {
				if ( $this->bofelamimail->sendMDN($_uid) )
					$this->bofelamimail->flagMessages("mdnsent",array($_uid));
			} else {
				 $this->bofelamimail->flagMessages("mdnnotsent",array($_uid));
			}
			return $response;

		}


		function generateMessageList($_folderName,$modifyoffset=0)
		{
			if($this->_debug) error_log("ajaxfelamimail::generateMessageList with $_folderName,$modifyoffset");
			$response = new xajaxResponse();
			$response->addScript("activeFolder = \"".$_folderName."\";");
			$response->addScript("activeFolderB64 = \"".base64_encode($_folderName)."\";");
			if($this->_connectionStatus === false) {
				return $response->getXML();
			}

			$listMode = 0;

			$this->bofelamimail->restoreSessionData();
			if($this->bofelamimail->isSentFolder($_folderName) ||
				false !== in_array($_folderName,explode(',',$GLOBALS['egw_info']['user']['preferences']['felamimail']['messages_showassent_0'])))
			{
				$listMode = 1;
			} elseif($this->bofelamimail->isDraftFolder($_folderName)) {
				$listMode = 2;
			} elseif($this->bofelamimail->isTemplateFolder($_folderName)) {
				$listMode = 3;
			}

			$maxMessages = $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];

			$offset = $this->sessionData['startMessage'];
			if($this->_debug) error_log("ajaxfelamimail::generateMessageList with $offset,$modifyoffset");
			if ($modifyoffset != 0 && ($offset+$modifyoffset)>0) $offset = $offset+$modifyoffset;
			if($this->_debug) error_log("ajaxfelamimail::generateMessageList with $offset");
			$headers = $this->bofelamimail->getHeaders(
				$_folderName,
				$offset,
				$maxMessages,
				$this->sessionData['sort'],
				$this->sessionData['sortReverse'],
				(array)$this->sessionData['messageFilter']
			);

			$headerTable = $this->uiwidgets->messageTable(
				$headers,
				$listMode,
				$_folderName,
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['message_newwindow'],
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['rowOrderStyle'],
				$this->sessionData['previewMessage']
			);

			$firstMessage = (int)$headers['info']['first'];
			$lastMessage  = (int)$headers['info']['last'];
			$totalMessage = (int)$headers['info']['total'];
			$shortName = '';
			if($folderStatus = $this->bofelamimail->getFolderStatus($_folderName)) {
				$shortName =$folderStatus['shortDisplayName'];
			}
			if($totalMessage == 0) {
				$response->addAssign("messageCounter", "innerHTML", '<b>'.$shortName.': </b>'.lang('no messages found...'));
			} else {
				$response->addAssign("messageCounter", "innerHTML", '<b>'.$shortName.': </b>'.lang('Viewing messages')." <b>$firstMessage</b> - <b>$lastMessage</b> ($totalMessage ".lang("total").')');
			}

			if($listMode) {
				$response->addAssign("from_or_to", "innerHTML", lang('to'));
			} else {
				$response->addAssign("from_or_to", "innerHTML", lang('from'));
			}
			$headerTable = str_replace("\v",' ',$headerTable);
			$response->addAssign("divMessageList", "innerHTML", $headerTable);

			if($quota = $this->bofelamimail->getQuotaRoot()) {
				if (isset($quota['usage']) && $quota['limit'] != 'NOT SET')
				{
					$quotaDisplay = $this->uiwidgets->quotaDisplay($quota['usage'], $quota['limit']);
					$response->addAssign('quotaDisplay', 'innerHTML', $quotaDisplay);
				}
			}

			if($folderStatus = $this->bofelamimail->getFolderStatus($_folderName)) {
				if($folderStatus['unseen'] > 0) {
					$response->addScript("tree.setItemText('$_folderName', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
				} else {
					$response->addScript("tree.setItemText('$_folderName', '". $folderStatus['shortDisplayName'] ."');");
				}
			}

			if(!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder']) &&
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'] != 'none' ) {
				$folderStatus = $this->bofelamimail->getFolderStatus($GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder']);
				if($folderStatus['unseen'] > 0) {
					$response->addScript("tree.setItemText('". $GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'] ."', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
				} else {
					$response->addScript("tree.setItemText('". $GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'] ."', '". $folderStatus['shortDisplayName'] ."');");
				}
			}

			$response->addScript("tree.selectItem('".$_folderName. "',false);");

			if($this->_debug) error_log('generateMessageList done');

			return $response->getXML();
		}

		function getFolderInfo($_folderName)
		{
			if($this->_debug) error_log("ajaxfelamimail::getFolderInfo($_folderName)");
			$folderName = html_entity_decode($_folderName, ENT_QUOTES, $this->charset);

			if($folderName != '--topfolder--' && $folderStatus = $this->bofelamimail->getFolderStatus($folderName)) {
				$response = new xajaxResponse();

				if($this->sessionDataAjax['oldFolderName'] == '--topfolder--') {
					$this->sessionDataAjax['oldFolderName'] = '';
				}
				// only folders with LATT_NOSELECT not set, can have subfolders
				// seem to work only for uwimap
				#if($folderStatus['attributes'] & LATT_NOSELECT) {
					$response->addScript("document.getElementById('newSubFolder').disabled = false;");
				#} else {
				#	$response->addScript("document.getElementById('newSubFolder').disabled = true;");
				#}

				$this->sessionDataAjax['folderName'] = $folderName;
				$this->saveSessionData();
				$hasChildren=false;
				if ($folderStatus['attributes'][0]=="\\HasChildren") $hasChildren=true;
				if(strtoupper($folderName) != 'INBOX') {
					$response->addAssign("newMailboxName", "value", htmlspecialchars($folderStatus['shortDisplayName'], ENT_QUOTES, $this->charset));
					$response->addAssign("newMailboxMoveName", "value", htmlspecialchars($folderStatus['displayName'], ENT_QUOTES, $this->charset));
					$response->addScript("document.getElementById('mailboxRenameButton').disabled = false;");
					$response->addScript("document.getElementById('newMailboxName').disabled = false;");
					$response->addScript("document.getElementById('divDeleteButton').style.visibility = 'visible';");
					$response->addScript("document.getElementById('divRenameButton').style.visibility = 'visible';");
					// if the folder has children, we dont want to move it, since we dont handle the subscribing to subfolders after moving the folder
					$response->addScript("document.getElementById('divMoveButton').style.visibility = ".($hasChildren ? "'hidden'" : "'visible'").";");
					$response->addScript("document.getElementById('newMailboxMoveName').disabled = ".($hasChildren ? "true" : "false").";");
					$response->addScript("document.getElementById('aMoveSelectFolder').style.visibility = ".($hasChildren ? "'hidden'" : "'visible'").";");
				} else {
					$response->addAssign("newMailboxName", "value", '');
					$response->addAssign("newMailboxMoveName", "value", '');
					$response->addScript("document.getElementById('mailboxRenameButton').disabled = true;");
					$response->addScript("document.getElementById('newMailboxName').disabled = true;");
					$response->addScript("document.getElementById('divDeleteButton').style.visibility = 'hidden';");
					$response->addScript("document.getElementById('divRenameButton').style.visibility = 'hidden';");
					$response->addScript("document.getElementById('divMoveButton').style.visibility = 'hidden';");
					$response->addScript("document.getElementById('newMailboxMoveName').disabled = true;");
					$response->addScript("document.getElementById('aMoveSelectFolder').style.visibility = 'hidden';");
				}
				$response->addAssign("folderName", "innerHTML", htmlspecialchars($folderStatus['displayName'], ENT_QUOTES, $this->charset));
				//error_log(__METHOD__.__LINE__.' Folder:'.$folderName.' ACL:'.array2string($this->bofelamimail->getIMAPACL($folderName)));
				if($folderACL = $this->bofelamimail->getIMAPACL($folderName)) {
					$response->addAssign("aclTable", "innerHTML", $this->createACLTable($folderACL));
				}
				else
				{
					$response->addAssign("aclTable", "innerHTML", '');
				}

				return $response->getXML();
			} else {
				$this->sessionDataAjax['oldFolderName'] = $folderName;
				$this->saveSessionData();

				$response = new xajaxResponse();
				$response->addAssign("newMailboxName", "value", '');
				$response->addAssign("folderName", "innerHTML", '');
				$response->addScript("document.getElementById('newMailboxName').disabled = true;");
				$response->addScript("document.getElementById('mailboxRenameButton').disabled = true;");
				$response->addScript("document.getElementById('divDeleteButton').style.visibility = 'hidden';");
				$response->addScript("document.getElementById('divRenameButton').style.visibility = 'hidden';");
				// we should not need this, but dovecot does not report the correct folderstatus for all folders that he is listing
				//error_log(__METHOD__.__LINE__.' Folder:'.$folderName.' ACL:'.array2string($this->bofelamimail->getIMAPACL($folderName)));
				if($folderName != '--topfolder--' && $folderName != 'user' && ($folderACL = $this->bofelamimail->getIMAPACL($folderName))) {
					$response->addAssign("aclTable", "innerHTML", $this->createACLTable($folderACL));
				}
				else
				{
					$response->addAssign("aclTable", "innerHTML", '');
				}
				return $response->getXML();
			}
		}

		function gotoStart()
		{
			if($this->_debug) error_log("ajaxfelamimail::gotoStart");
			$this->sessionData['startMessage']	= 1;
			$this->saveSessionData();

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function jumpEnd()
		{
			if($this->_debug) error_log("ajaxfelamimail::jumpEnd");
			$sortedList = $this->bofelamimail->getSortedList(
				$this->sessionData['mailbox'],
				$this->sessionData['sort'],
				$this->sessionData['sortReverse'],
				(array)$this->sessionData['messageFilter']
			);
			$messageCounter = count($sortedList);

			$lastPage = $messageCounter - ($messageCounter % $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"]) + 1;
			if($lastPage > $messageCounter)
				$lastPage -= $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];

			$this->sessionData['startMessage'] = $lastPage;

			$this->saveSessionData();

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function jumpStart()
		{
			if($this->_debug) error_log("ajaxfelamimail::jumpStart");
			$this->sessionData['startMessage']	= 1;
			$this->saveSessionData();

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		/*
		* move messages to another folder
		*
		* @param string _folder name of the target folder
		* @param array _selectedMessages UID's of the messages to move
		*
		* @return xajax response
		*/
		function moveMessages($_folderName, $_selectedMessages)
		{
			if($this->_debug) error_log(__METHOD__." move to $_folderName called with Messages ".print_r($_selectedMessages,true));
			$messageCount = 0;
			if(is_array($_selectedMessages) && count($_selectedMessages['msg']) > 0) $messageCount = count($_selectedMessages['msg']);
			$folderName = $this->_decodeEntityFolderName($_folderName);
			if ($_selectedMessages == 'all' || !empty( $_selectedMessages['msg']) && !empty($folderName)) {
				if ($this->sessionData['mailbox'] != $folderName) {
					$this->bofelamimail->moveMessages($folderName, ($_selectedMessages == 'all'? null:$_selectedMessages['msg']));
				} else {
					  if($this->_debug) error_log("ajaxfelamimail::moveMessages-> same folder than current selected");
				}
				if($this->_debug) error_log(__METHOD__." Rebuild MessageList for Folder:".$this->sessionData['mailbox']);
				return $this->generateMessageList($this->sessionData['mailbox'],($_selectedMessages == 'all'?0:(-1*$messageCount)));
			} else {
				$response = new xajaxResponse();
				$response->addScript('resetMessageSelect();');
				$response->addScript('tellUser("'.lang('No messages selected, or lost selection. Changing to folder ').'","'.$_folderName.'");');
				$response->addScript('onNodeSelect("'.$_folderName.'");');
				return $response->getXML();

			}
		}

		/*
		* copy messages to another folder
		*
		* @param string _folder name of the target folder
		* @param array _selectedMessages UID's of the messages to copy
		*
		* @return xajax response
		*/
		function copyMessages($_folderName, $_selectedMessages)
		{
			if($this->_debug) error_log(__METHOD__." called with Messages ".print_r($_selectedMessages,true));
			$messageCount = 0;
			if(is_array($_selectedMessages) && count($_selectedMessages['msg']) > 0) $messageCount = count($_selectedMessages['msg']);
			$folderName = $this->_decodeEntityFolderName($_folderName);
			if ($_selectedMessages == 'all' || !empty( $_selectedMessages['msg']) && !empty($folderName)) {
				if ($this->sessionData['mailbox'] != $folderName)
				{
					$deleteAfterMove = false;
					$this->bofelamimail->moveMessages($folderName, ($_selectedMessages == 'all'? null:$_selectedMessages['msg']),$deleteAfterMove);
				}
				else
				{
					  if($this->_debug) error_log("ajaxfelamimail::copyMessages-> same folder than current selected");
				}

				return $this->generateMessageList($this->sessionData['mailbox'],($_selectedMessages == 'all'?0:(-1*$messageCount)));
			} else {
				$response = new xajaxResponse();
				$response->addScript('resetMessageSelect();');
				$response->addScript('tellUser("'.lang('No messages selected, or lost selection. Changing to folder ').'","'.$_folderName.'");');
				$response->addScript('onNodeSelect("'.$_folderName.'");');
				return $response->getXML();

			}
		}

		function quickSearch($_searchType, $_searchString, $_status)
		{
			// save the filter
			$bofilter		= CreateObject('felamimail.bofilter');

			$filter['filterName']	= lang('Quicksearch');
			$filter['type']		= $_searchType;
			$filter['string']	= str_replace('"','\"', str_replace('\\','\\\\',$_searchString));
			$filter['status']	= $_status;

			$this->sessionData['messageFilter'] = $filter;

			$this->sessionData['startMessage'] = 1;

			$this->saveSessionData();

			// generate the new messageview
			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function refreshMessagePreview($_messageID,$_folderType)
		{

			$this->bofelamimail->restoreSessionData();
			$headerData = $this->bofelamimail->getHeaders(
				$this->sessionData['mailbox'],
				0,
				0,
				'',
				'',
				'',
				$_messageID
			);
			$headerData = $headerData['header'][0];
			foreach ($headerData as $key => $val)
			{
				$headerData[$key] = bofelamimail::htmlentities($val);
			}
			$headerData['subject'] = $this->bofelamimail->decode_subject($headerData['subject'],false);
			$this->sessionData['previewMessage'] = $headerData['uid'];
			$this->saveSessionData();
			//error_log(print_r($headerData,true));
			$response = new xajaxResponse();
			$response->addScript("document.getElementById('messageCounter').innerHTML =MessageBuffer;");
			//$response->addScript("document.getElementById('messageCounter').innerHTML ='';");
			$response->addScript("fm_previewMessageID=".$headerData['uid'].";");
			$response->addAssign('spanMessagePreview', 'innerHTML', $this->uiwidgets->updateMessagePreview($headerData,$_folderType, $this->sessionData['mailbox']));
			return $response->getXML();
		}

		function refreshMessageList()
		{
			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function refreshFolder()
		{
			if ($this->_debug) error_log("ajaxfelamimail::refreshFolder");
			$GLOBALS['egw']->session->commit_session();

			$response = new xajaxResponse();

			if ($this->_connectionStatus === true) {
				$folderName = $this->sessionData['mailbox'];
				//error_log(array2string($this->bofelamimail->getFolderStatus($folderName)));
				if ($folderStatus = $this->bofelamimail->getFolderStatus($folderName)) {
					if ($folderStatus['unseen'] > 0) {
						$response->addScript("tree.setItemText('$folderName', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
					} else {
						$response->addScript("tree.setItemText('$folderName', '". $folderStatus['shortDisplayName'] ."');");
					}
				}
			}

			return $response->getXML();
		}

		function refreshFolderList($activeFolderList ='')
		{
			if ($this->_debug) error_log("ajaxfelamimail::refreshFolderList with folders:".$activeFolderList);
			if ($activeFolderList != '') $activeFolders = explode('#,#',$activeFolderList);
			$GLOBALS['egw']->session->commit_session();

			$response = new xajaxResponse();
			if(!($this->_connectionStatus === true)) $this->_connectionStatus = $this->bofelamimail->openConnection();
			if($this->_connectionStatus === true) {
				//error_log("connected");
				if (is_array($activeFolders)) {
					foreach ($activeFolders as $key => $name) {
						//error_log($key."=>".$name);
						switch($name) {
							case "0": break;
							case "--topfolder--": break;
							default:
								$folders[html_entity_decode($name,ENT_COMPAT)] = $name;
								//error_log("check folder $name");
						}
					}
					if (!(is_array($folders) && count($folders)>0)) $folders = $this->bofelamimail->getFolderObjects(true);
				} else {
					//error_log("check/get all folders");
					$folders = $this->bofelamimail->getFolderObjects(true);
				}
				foreach($folders as $folderName => $folderData) {
					//error_log(__METHOD__.__LINE__."checking $folderName -> ".array2string($this->bofelamimail->getFolderStatus($folderName)));
					if($folderStatus = $this->bofelamimail->getFolderStatus($folderName)) {
						if($folderStatus['unseen'] > 0) {
							$response->addScript("tree.setItemText('".@htmlspecialchars($folderName,ENT_QUOTES, bofelamimail::$displayCharset,false)."', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
						} else {
							$response->addScript("tree.setItemText('".@htmlspecialchars($folderName,ENT_QUOTES, bofelamimail::$displayCharset,false)."', '". $folderStatus['shortDisplayName'] ."');");
						}
					}
				}
			}

			return $response->getXML();

		}

		function refreshSignatureTable()
		{
			require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.felamimail_bosignatures.inc.php');
			$boSignatures = new felamimail_bosignatures();
			$signatures = $boSignatures->getListOfSignatures();

			$response = new xajaxResponse();
			$response->addAssign('signatureTable', 'innerHTML', $this->uiwidgets->createSignatureTable($signatures));
			return $response->getXML();
		}

		function refreshAccountDataTable()
		{
			require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.bopreferences.inc.php');
			$boPreferences  = CreateObject('felamimail.bopreferences');
			$preferences =& $boPreferences->getPreferences();
			$allAccountData    = $boPreferences->getAllAccountData($preferences);
			foreach ($allAccountData as $tmpkey => $accountData)
			{
				$identity =& $accountData['identity'];
				foreach($identity as $key => $value) {
					if(is_object($value) || is_array($value)) {
						continue;
					}
					switch($key) {
						default:
						$tempvar[$key] = $value;
					}
				}
				$accountArray[]=$tempvar;
			}
			$response = new xajaxResponse();
			$response->addAssign('userDefinedAccountTable', 'innerHTML', $this->uiwidgets->createAccountDataTable($accountArray));
			return $response->getXML();
		}

		function reloadAttachments($_composeID)
		{
			$bocompose	= CreateObject('felamimail.bocompose', $_composeID);
			$tableRows	=  array();
			$table		=  '';
			$imgClearLeft	=  $GLOBALS['egw']->common->image('felamimail','clear_left');

			foreach((array)$bocompose->sessionData['attachments'] as $id => $attachment) {
				switch(strtoupper($attachment['type'])) {
					case 'MESSAGE/RFC822':
						$linkData = array (
							'menuaction'    => 'felamimail.uidisplay.display',
							'uid'           => $attachment['uid'],
							'part'          => $attachment['partID']
						);
						$windowName = 'displayMessage_';
						$att_link = "egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',700,egw_getWindowOuterHeight()); return false;";

						break;

					case 'IMAGE/JPEG':
					case 'IMAGE/PNG':
					case 'IMAGE/GIF':
					default:
						$linkData = array (
							'menuaction'    => 'felamimail.uicompose.getAttachment',
							'attID'	=> $id,
							'_composeID' => $_composeID,
						);
						$windowName = 'displayAttachment_';
						$att_link = "egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',800,600);";

						break;
				}
				$tempArray = array (
					'1' => '<a href="#" onclick="'. $att_link .'">'. $attachment['name'] .'</a>', '.1' => 'width="40%"',
					'2' => mime_magic::mime2label($attachment['type']),
					'3' => egw_vfs::hsize($attachment['size']), '.3' => "style='text-align:right;'",
					'4' => '&nbsp;', '.4' => 'width="10%"',
					'5' => "<img src='$imgClearLeft' onclick=\"fm_compose_deleteAttachmentRow(this,'$_composeID','$id')\">"
				);
				$tableRows[] = $tempArray;
			}

			if(count($tableRows) > 0) {
				$table = html::table($tableRows, "style='width:100%'");
			}

			$response = new xajaxResponse();
			$response->addAssign('divAttachments', 'innerHTML', $table);
			return $response->getXML();
		}

		/*
		* rename a folder
		*
		* @param string _folder name of the target folder
		* @param array _selectedMessages UID's of the messages to move
		*
		* @return xajax response
		*/
		function renameFolder($_oldFolderName, $_parentFolder, $_folderName)
		{
			if($this->_debug) error_log("ajaxfelamimail::renameFolder called as ($_oldFolderName, $_parentFolder, $_folderName)");
			$oldFolderName = $this->_decodeEntityFolderName($_oldFolderName);
			$folderName = $GLOBALS['egw']->translation->convert($this->_decodeEntityFolderName($_folderName), $this->charset, 'UTF7-IMAP');
			$parentFolder = $this->_decodeEntityFolderName($_parentFolder);
			$parentFolder = ($_parentFolder == '--topfolder--' ? '' : $parentFolder);
			if($this->_debug) error_log("ajaxfelamimail::renameFolder work with ($oldFolderName, $parentFolder, $folderName)");

			$response = new xajaxResponse();
			if(strtoupper($_oldFolderName) != 'INBOX' ) {
				if($newFolderName = $this->bofelamimail->renameFolder($oldFolderName, $parentFolder, $folderName)) {
					//enforce the subscription to the newly named server, as it seems to fail for names with umlauts
					$rv = $this->bofelamimail->subscribe($newFolderName, true);
					$newFolderName = $this->_encodeFolderName($newFolderName);
					$folderName = $this->_encodeDisplayFolderName($folderName);
					if ($parentFolder == '') {
						#$folderStatus = $this->bofelamimail->getFolderStatus($newFolderName);
						$HierarchyDelimiter = $this->bofelamimail->getHierarchyDelimiter();
						#if($this->_debug) error_log("ajaxfelamimail::renameFolder Status of new Folder:".print_r($folderStatus,true));
						if($this->_debug) error_log("ajaxfelamimail::rename/move Folder($newFolderName, $folderName)");
						$buffarray = explode($HierarchyDelimiter, $newFolderName);
						$folderName = $this->_encodeDisplayFolderName( $this->_decodeEntityFolderName(array_pop($buffarray)));
						$_parentFolder = $parentFolder = implode($HierarchyDelimiter,$buffarray);
						if($this->_debug) error_log("ajaxfelamimail::renameFolder insert new ITEM $folderName at $_parentFolder");
						#$hasChildren = false;
						#if ($folderStatus['attributes'][0]=="\\HasChildren") $hasChildren=true;
					}
					$response->addScript("tree.deleteItem('$_oldFolderName',0);");
					$response->addScript("tree.insertNewItem('$_parentFolder','$newFolderName','$folderName',onNodeSelect,'folderClosed.gif',0,0,'CHILD,CHECKED,SELECT,CALL');");
				}
			}
			return $response->getXML();
		}

		function saveSessionData()
		{
			$GLOBALS['egw']->session->appsession('ajax_session_data','felamimail',$this->sessionDataAjax);
			$GLOBALS['egw']->session->appsession('session_data','felamimail',$this->sessionData);
		}

		function saveSignature($_mode, $_id, $_description, $_signature, $_isDefaultSignature)
		{
			require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.felamimail_bosignatures.inc.php');

			$boSignatures = new felamimail_bosignatures();

			$isDefaultSignature = ($_isDefaultSignature == 'true' ? true : false);

			$signatureID = $boSignatures->saveSignature($_id, $_description, $_signature, $isDefaultSignature);

			$response = new xajaxResponse();

			if($_mode == 'save') {
				#$response->addAssign('signatureID', 'value', $signatureID);
				$response->addScript("opener.fm_refreshSignatureTable()");
				$response->addScript("document.getElementById('signatureDesc').focus();window.close();");
			} else {
				$response->addScript("opener.fm_refreshSignatureTable()");
				$response->addAssign('signatureID', 'value', $signatureID);
			}

			return $response->getXML();
		}

		function setComposeSignature($identity)
		{
			$boPreferences  = CreateObject('felamimail.bopreferences');
			$preferences =& $boPreferences->getPreferences();
			$Identities = $preferences->getIdentity($identity);
			//error_log(print_r($Identities->signature,true));

			$response = new xajaxResponse();
			$response->addScript('setSignature('.$Identities->signature.');');
			return $response->getXML();
		}

		function searchAddress($_searchString)
		{
			$contacts = $GLOBALS['egw']->contacts->search(array(
				'n_fn'       => $_searchString,
				'email'      => $_searchString,
				'email_home' => $_searchString,
			),array('n_fn','email','email_home'),'n_fn','','%',false,'OR',array(0,20));

			$response = new xajaxResponse();

			if(is_array($contacts)) {
				$innerHTML	= '';
				$jsArray	= array();
				$i		= 0;

				foreach($contacts as $contact) {
					foreach(array($contact['email'],$contact['email_home']) as $email) {
						if(!empty($email) && !isset($jsArray[$email])) {
							$i++;
							$str = $GLOBALS['egw']->translation->convert(trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']).' <'.trim($email).'>',$this->charset,'utf-8');
							$innerHTML .= '<div class="inactiveResultRow" onmousedown="keypressed(13,1)" onmouseover="selectSuggestion('.($i-1).')">'.
								htmlentities($str, ENT_QUOTES, 'utf-8').'</div>';
							$jsArray[$email] = addslashes($str);
						}
						if ($i > 10) break;	// we check for # of results here, as we might have empty email addresses
					}
				}

				if($jsArray) {
					$response->addAssign('resultBox', 'innerHTML', $innerHTML);
					$response->addScript('results = new Array("'.implode('","',$jsArray).'");');
					$response->addScript('displayResultBox();');
				}
				//$response->addScript("getResults();");
				//$response->addScript("selectSuggestion(-1);");
			} else {
				$response->addAssign('resultBox', 'className', 'resultBoxHidden');
			}
			return $response->getXML();
		}

		function skipForward()
		{
			$sortedList = $this->bofelamimail->getSortedList(
				$this->sessionData['mailbox'],
				$this->sessionData['sort'],
				$this->sessionData['sortReverse'],
				(array)$this->sessionData['messageFilter']
			);
			$messageCounter = count($sortedList);
			// $lastPage is the first message ID of the last page
			if($messageCounter > $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"]) {
				$lastPage = $messageCounter - ($messageCounter % $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"]) + 1;
				if($lastPage > $messageCounter) {
					$lastPage -= $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
				}
				$this->sessionData['startMessage'] += $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
				if($this->sessionData['startMessage'] > $lastPage) {
					$this->sessionData['startMessage'] = $lastPage;
				}
			} else {
				$this->sessionData['startMessage'] = 1;
			}

			$this->saveSessionData();

			$response = $this->generateMessageList($this->sessionData['mailbox']);

			return $response;
		}

		function skipPrevious()
		{
			$this->sessionData['startMessage']	-= $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
			if($this->sessionData['startMessage'] < 1) {
				$this->sessionData['startMessage'] = 1;
			}
			$this->saveSessionData();

			return $this->generateMessageList($this->sessionData['mailbox']);
		}


		/**
		 * updateACL
		 * updates all ACLs for a single user and returns the updated the acl table
		 * it will do nothing on $_acl == 'custom'
		 *
		 * @param	string	$_user	user to modify acl entries
		 * @param	string	$_acl	new acl list
		 *
		 * @return	string	ajax xml response
		 */
		function updateACL($_user, $_acl)
		{
			if ($_acl == 'custom') {
				$response = new xajaxResponse();
				return $response->getXML();
			}

			$_folderName = $this->sessionDataAjax['folderName'];
			$result = $this->bofelamimail->setACL($_folderName, $_user, $_acl);
			if ($result && $folderACL = $this->bofelamimail->getIMAPACL($_folderName)) {
				return $this->updateACLView();
			}

			$response = new xajaxResponse();
			// add error message
			// $response->add???
			return $response->getXML();
		}


		/**
		 * updateACLView
		 * updates the ACL view table
		 *
		 * @return	string	ajax xml response containing new ACL table
		 */
		function updateACLView()
		{

			$response = new xajaxResponse();
			if($folderACL = $this->bofelamimail->getIMAPACL($this->sessionDataAjax['folderName'])) {
				$response->addAssign("aclTable", "innerHTML", $this->createACLTable($folderACL));
			}
			return $response->getXML();
		}

		/**
		* subscribe/unsubribe from/to a folder
		*/
		function updateFolderStatus($_folderName, $_status)
		{
			$folderName = $this->_decodeEntityFolderName($_folderName);
			$status = (bool)$_status;

			$this->bofelamimail->subscribe($folderName, $status);

			$response = new xajaxResponse();
			return $response->getXML();
		}

		// remove html entities
		function _decodeEntityFolderName($_folderName)
		{
			return html_entity_decode($_folderName, ENT_QUOTES, $this->charset);
		}

		function updateMessageView($_folderName)
		{
			$folderName = $this->_decodeEntityFolderName($_folderName);
			if($this->_debug) error_log("ajaxfelamimail::updateMessageView $folderName $this->charset");

			$this->sessionData['mailbox'] 		= $folderName;
			$this->sessionData['startMessage']	= 1;
			$this->saveSessionData();

			$messageList = $this->generateMessageList($folderName);

			$this->bofelamimail->closeConnection();

			return $messageList;
		}

		function updateSingleACL($_accountName, $_aclType, $_aclStatus)
		{
			$response = new xajaxResponse();
			$data = $this->bofelamimail->updateSingleACL($this->sessionDataAjax['folderName'], $_accountName, $_aclType, $_aclStatus);
			return $response->getXML();
		}

		function xajaxFolderInfo($_formValues)
		{
			$response = new xajaxResponse();

			$response->addAssign("field1", "value", $_formValues['num1']);
			$response->addAssign("field2", "value", $_formValues['num2']);
			$response->addAssign("field3", "value", $_formValues['num1'] * $_formValues['num2']);

			return $response->getXML();
		}

		function _encodeFolderName($_folderName)
		{
			$folderName = htmlspecialchars($_folderName, ENT_QUOTES, $this->charset);

			$search         = array('\\');
			$replace        = array('\\\\');

			return str_replace($search, $replace, $folderName);
		}

		function _encodeDisplayFolderName($_folderName)
		{
			$folderName = $GLOBALS['egw']->translation->convert($_folderName, 'UTF7-IMAP', $this->charset);
			$folderName = htmlspecialchars($folderName, ENT_QUOTES, $this->charset);

			$search         = array('\\');
			$replace        = array('\\\\');

			return str_replace($search, $replace, $folderName);
		}

}
?>
