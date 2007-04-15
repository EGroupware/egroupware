<?php
	/***************************************************************************\
	* eGroupWare - FeLaMiMail                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/

	/* $Id$ */

	class ajaxfelamimail {
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
			$this->bofelamimail	=& CreateObject('felamimail.bofelamimail',$this->charset);
			$this->uiwidgets	=& CreateObject('felamimail.uiwidgets');
			$this->_connectionStatus = $this->bofelamimail->openConnection();

			$this->sessionDataAjax	= $GLOBALS['egw']->session->appsession('ajax_session_data');
			$this->sessionData	= $GLOBALS['egw']->session->appsession('session_data');

			if(!isset($this->sessionDataAjax['folderName'])) {
				$this->sessionDataAjax['folderName'] = 'INBOX';
			}
			
			$this->icServer = $this->bofelamimail->mailPreferences->getIncomingServer($this->imapServerID);
		}
		
		function addACL($_accountName, $_aclData) 
		{
			if($this->_debug) error_log("ajaxfelamimail::addACL");
			$response =& new xajaxResponse();

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

			$response =& new xajaxResponse();

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

			$bofilter =& CreateObject('felamimail.bofilter');
			
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
		 * createACLTable
		 * creates the ACL table
		 *
		 * @param	array	$_acl	array containing acl data
		 *
		 * @return	string	html output for ACL table
		 */
		function createACLTable($_acl) 
		{
			if(!is_object($GLOBALS['egw']->html)) {
				$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
			}

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

				$selectFrom = $GLOBALS['egw']->html->select('identity', $accountAcl['RIGHTS'], $aclShortCuts, false, "id=\"predefinedFor_$accountName\" style='width: 100px;' onChange=\"xajax_doXMLHTTP('felamimail.ajaxfelamimail.updateACL','$accountName',this.value)\"");

				$row .= "<td>$selectFrom</td>";
				
				$row .= "</tr>";
			}
			
			return "<table border=\"0\" style=\"width: 100%;\"><tr class=\"th\"><th>&nbsp;</th><th style=\"width:100px;\">Name</th><th>L</th><th>R</th><th>S</th><th>W</th><th>I</th><th>P</th><th>C</th><th>D</th><th>A</th><th>&nbsp;</th></tr>$row</table>";
		}
		
		function deleteACL($_aclData) 
		{
			if($this->_debug) error_log("ajaxfelamimail::deleteACL");
			$response =& new xajaxResponse();
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
			$bocompose	=& CreateObject('felamimail.bocompose', $_composeID);
			$bocompose->removeAttachment($_attachmentID);

			$response =& new xajaxResponse();
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
			$response =& new xajaxResponse();
			
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
			if($this->_debug) error_log("ajaxfelamimail::deleteMessages");
			$this->bofelamimail->deleteMessages($_messageList['msg']);

			return $this->generateMessageList($this->sessionData['mailbox']);
		}
		
		function deleteSignatures($_signatures) 
		{
			if($this->_debug) error_log("ajaxfelamimail::deleteSignatures");
			$boPreferences = CreateObject('felamimail.bopreferences');
				
			$boPreferences->deleteSignatures($_signatures);
				
			$signatures = $boPreferences->getListOfSignatures();

			$response =& new xajaxResponse();
			$response->addAssign('signatureTable', 'innerHTML', $this->uiwidgets->createSignatureTable($signatures));
			return $response->getXML();
		}
		
		/*
		* empty trash folder
		*
		* @return xajax response
		*/
		function emptyTrash() 
		{
			if($this->_debug) error_log("ajaxfelamimail::emptyTrash");
			if(!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'])) {
				$this->bofelamimail->compressFolder($GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder']);
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
			if($this->_debug) error_log("ajaxfelamimail::flagMessages");
			$this->bofelamimail->flagMessages($_flag, $_messageList);

			return $this->generateMessageList($this->sessionData['mailbox']);
		}
		
		function generateMessageList($_folderName) 
		{
			if($this->_debug) error_log("ajaxfelamimail::generateMessageList");
			$response =& new xajaxResponse();

			if($this->_connectionStatus === false) {
				return $response->getXML();
			}

			$listMode = 0;
		
			$this->bofelamimail->restoreSessionData();
			
			if($this->bofelamimail->isSentFolder($_folderName)) {
				$listMode = 1;
			} elseif($this->bofelamimail->isDraftFolder($_folderName)) {
				$listMode = 2;
			}

			$maxMessages = $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];

			$headers = $this->bofelamimail->getHeaders(
				$_folderName, 
				$this->sessionData['startMessage'], 
				$maxMessages, 
				$this->sessionData['sort'], 
				$this->sessionData['sortReverse'],
				(array)$this->sessionData['messageFilter']
			);

			$headerTable = $this->uiwidgets->messageTable(
				$headers, 
				$listMode, 
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['message_newwindow'],
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['rowOrderStyle']
			);
			
			$firstMessage = (int)$headers['info']['first'];
			$lastMessage  = (int)$headers['info']['last'];
			$totalMessage = (int)$headers['info']['total'];

			if($totalMessage == 0) {
				$response->addAssign("messageCounter", "innerHTML", lang('no messages found...'));
			} else {
				$response->addAssign("messageCounter", "innerHTML", lang('Viewing messages')." <b>$firstMessage</b> - <b>$lastMessage</b> ($totalMessage ".lang("total").')');
			}
			
			if($listMode) {
				$response->addAssign("from_or_to", "innerHTML", lang('to'));
			} else {
				$response->addAssign("from_or_to", "innerHTML", lang('from'));
			}
			
			$response->addAssign("divMessageList", "innerHTML", $headerTable);
			
			if($quota = $this->bofelamimail->getQuotaRoot()) {
				$quotaDisplay = $this->uiwidgets->quotaDisplay($quota['usage'], $quota['limit']);
				$response->addAssign('quotaDisplay', 'innerHTML', $quotaDisplay);
			}

			if($folderStatus = $this->bofelamimail->getFolderStatus($_folderName)) {
				if($folderStatus['unseen'] > 0) {
					$response->addScript("tree.setItemText('$_folderName', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
				} else {
					$response->addScript("tree.setItemText('$_folderName', '". $folderStatus['shortDisplayName'] ."');");
				}
			}

			if(!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'])) {
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
				$response =& new xajaxResponse();

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
				
				if(strtoupper($folderName) != 'INBOX') {
					$response->addAssign("newMailboxName", "value", htmlspecialchars($folderStatus['shortDisplayName'], ENT_QUOTES, $this->charset));
					$response->addScript("document.getElementById('mailboxRenameButton').disabled = false;");
					$response->addScript("document.getElementById('newMailboxName').disabled = false;");
					$response->addScript("document.getElementById('divDeleteButton').style.visibility = 'visible';");
					$response->addScript("document.getElementById('divRenameButton').style.visibility = 'visible';");
				} else {
					$response->addAssign("newMailboxName", "value", '');
					$response->addScript("document.getElementById('mailboxRenameButton').disabled = true;");
					$response->addScript("document.getElementById('newMailboxName').disabled = true;");
					$response->addScript("document.getElementById('divDeleteButton').style.visibility = 'hidden';");
					$response->addScript("document.getElementById('divRenameButton').style.visibility = 'hidden';");
				}
				$response->addAssign("folderName", "innerHTML", htmlspecialchars($folderStatus['displayName'], ENT_QUOTES, $this->charset));
				if($folderACL = $this->bofelamimail->getIMAPACL($folderName)) {
					$response->addAssign("aclTable", "innerHTML", $this->createACLTable($folderACL));
				}

				return $response->getXML();
			} else {
				$this->sessionDataAjax['oldFolderName'] = $folderName;
				$this->saveSessionData();

				$response =& new xajaxResponse();
				$response->addAssign("newMailboxName", "value", '');
				$response->addAssign("folderName", "innerHTML", '');
				$response->addScript("document.getElementById('newMailboxName').disabled = true;");
				$response->addScript("document.getElementById('mailboxRenameButton').disabled = true;");
				$response->addScript("document.getElementById('divDeleteButton').style.visibility = 'hidden';");
				$response->addScript("document.getElementById('divRenameButton').style.visibility = 'hidden';");
				$response->addAssign("aclTable", "innerHTML", '');
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
			if($this->_debug) error_log("ajaxfelamimail::moveMessages");
			$folderName = $this->_decodeEntityFolderName($_folderName);
			$this->bofelamimail->moveMessages($folderName, $_selectedMessages['msg']);

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function quickSearch($_searchType, $_searchString, $_status) 
		{
			// save the filter
			$bofilter		=& CreateObject('felamimail.bofilter');

			$filter['filterName']	= lang('Quicksearch');
			$filter['type']		= $_searchType;
			$filter['string']	= $_searchString;
			$filter['status']	= $_status;

			$this->sessionData['messageFilter'] = $filter;
			
			$this->sessionData['startMessage'] = 1;

			$this->saveSessionData();
			
			// generate the new messageview                
			return $this->generateMessageList($this->sessionData['mailbox']);
		}
		
		function refreshMessageList() 
		{
			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function refreshFolder()
		{
			if ($this->_debug) error_log("ajaxfelamimail::refreshFolder");
			$GLOBALS['egw']->session->commit_session();

            $response =& new xajaxResponse();

			if ($this->_connectionStatus === true) {
				$folderName = $this->sessionData['mailbox'];

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

		function refreshFolderList() 
		{
			if($this->_debug) error_log("ajaxfelamimail::refreshFolderList");
			$GLOBALS['egw']->session->commit_session();
			
			$response =& new xajaxResponse();

			if($this->_connectionStatus === true) {
				$folders = $this->bofelamimail->getFolderObjects();
			
				foreach($folders as $folderName => $folderData) {
					if($folderStatus = $this->bofelamimail->getFolderStatus($folderName)) {
						if($folderStatus['unseen'] > 0) {
							$response->addScript("tree.setItemText('$folderName', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
						} else {
							$response->addScript("tree.setItemText('$folderName', '". $folderStatus['shortDisplayName'] ."');");
						}
					}
				}
			}
			
			return $response->getXML();
			
		}
		
		function refreshSignatureTable() 
		{
				$boPreferences = CreateObject('felamimail.bopreferences');
				
				$signatures = $boPreferences->getListOfSignatures();

				$response =& new xajaxResponse();
				$response->addAssign('signatureTable', 'innerHTML', $this->uiwidgets->createSignatureTable($signatures));
				return $response->getXML();
		}
		
		function reloadAttachments($_composeID) 
		{
			$bocompose	=& CreateObject('felamimail.bocompose', $_composeID);
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
					'1' => '<a href="#" onclick="'. $att_link .'">'. $attachment['name'] .'</a>',
					'2' => $attachment['type'], '.2' => "style='text-align:center;'",
					'3' => $attachment['size'],
					'4' => "<img src='$imgClearLeft' onclick=\"fm_compose_deleteAttachmentRow(this,'$_composeID','$id')\">"
				);
				$tableRows[] = $tempArray;
			}
			
			if(count($tableRows) > 0) {
				if(!is_object($GLOBALS['egw']->html)) {
					$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
				}
				$table = $GLOBALS['egw']->html->table($tableRows, "style='width:100%'");
			}

			$response =& new xajaxResponse();
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
			$oldFolderName = $this->_decodeEntityFolderName($_oldFolderName);
			$folderName = $GLOBALS['egw']->translation->convert($_folderName, $this->charset, 'UTF7-IMAP');
			$parentFolder = $this->_decodeEntityFolderName($_parentFolder);
			$parentFolder = ($_parentFolder == '--topfolder--' ? '' : $parentFolder);
			if($this->_debug) error_log("ajaxfelamimail::renameFolder($oldFolderName, $parentFolder, $folderName)");

			$response =& new xajaxResponse();
			if(strtoupper($_oldFolderName) != 'INBOX' ) {
				if($newFolderName = $this->bofelamimail->renameFolder($oldFolderName, $parentFolder, $folderName)) {
					$newFolderName = $this->_encodeFolderName($newFolderName);
					$folderName = $this->_encodeDisplayFolderName($folderName);

					$response->addScript("tree.deleteItem('$_oldFolderName',0);");
					$response->addScript("tree.insertNewItem('$_parentFolder','$newFolderName','$folderName',onNodeSelect,'folderClosed.gif',0,0,'CHILD,CHECKED,SELECT,CALL');");
				}
			}
			return $response->getXML();
		}
		
		function saveSessionData() 
		{
			$GLOBALS['egw']->session->appsession('ajax_session_data','',$this->sessionDataAjax);
			$GLOBALS['egw']->session->appsession('session_data','',$this->sessionData);
		}
		
		function saveSignature($_mode, $_id, $_description, $_signature, $_isDefaultSignature) 
		{
			$boPreferences = CreateObject('felamimail.bopreferences');
			
			$isDefaultSignature = ($_isDefaultSignature == 'true' ? true : false);
				
			$signatureID = $boPreferences->saveSignature($_id, $_description, $_signature, $isDefaultSignature);

			$response =& new xajaxResponse();

			if($_mode == 'save') {
				#$response->addAssign('signatureID', 'value', $signatureID);
				#$response->addScript('window.close()');
			} else {
				$response->addScript("opener.fm_refreshSignatureTable()");
				$response->addAssign('signatureID', 'value', $signatureID);
			}
				
			return $response->getXML();
		}
		
		function searchAddress($_searchString) 
		{
			if (!is_object($GLOBALS['egw']->contacts)) {
				$GLOBALS['egw']->contacts =& CreateObject('phpgwapi.contacts');
			}
			if (method_exists($GLOBALS['egw']->contacts,'search')) {	// 1.3+
				$contacts = $GLOBALS['egw']->contacts->search(array(
					'n_fn'       => $_searchString,
					'email'      => $_searchString,
					'email_home' => $_searchString,
				),array('n_fn','email','email_home'),'n_fn','','%',false,'OR',array(0,20));
			} else {	// < 1.3
				$contacts = $GLOBALS['egw']->contacts->read(0,20,array(
					'fn' => 1,
					'email' => 1,
					'email_home' => 1,
				), $_searchString, 'tid=n', '', 'fn');
			}
			$response =& new xajaxResponse();

			if(is_array($contacts)) {
				$innerHTML	= '';
				$jsArray	= array();
				$i		= 0;
				
				foreach($contacts as $contact) {
					foreach(array($contact['email'],$contact['email_home']) as $email) {
						if(!empty($email) && !isset($jsArray[$email])) {
							$i++;
							$str = $GLOBALS['egw']->translation->convert(trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']).' <'.trim($email).'>',$this->charset,'utf-8');
							$innerHTML .= '<div class="inactiveResultRow" onclick="selectSuggestion($i)">'.
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
				$response =& new xajaxResponse();
				return $response->getXML();
			}

			$_folderName = $this->sessionDataAjax['folderName'];
			$result = $this->bofelamimail->setACL($_folderName, $_user, $_acl);
			if ($result && $folderACL = $this->bofelamimail->getIMAPACL($_folderName)) {
				return $this->updateACLView();
			}

			$response =& new xajaxResponse();
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
			
			$response =& new xajaxResponse();
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

			$response =& new xajaxResponse();
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
			$response =& new xajaxResponse();
			$data = $this->bofelamimail->updateSingleACL($this->sessionDataAjax['folderName'], $_accountName, $_aclType, $_aclStatus);
			return $response->getXML();
		}
		
		function xajaxFolderInfo($_formValues) 
		{
			$response =& new xajaxResponse();

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
