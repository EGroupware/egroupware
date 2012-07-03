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

		/**
		 * Reference to felamimail_bo object
		 *
		 * @var felamimail_bo
		 */
		var $bofelamimail;
		/**
		 * Instance of uiwidgets
		 *
		 * @var uiwidgets
		 */
		var $uiwidgets;

		function ajaxfelamimail()
		{
			if($this->_debug) error_log("ajaxfelamimail::ajaxfelamimail");
			if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
					$this->imapServerID = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'];
			//error_log("ajaxfelamimail::ajaxfelamimail ActiveProfile:".$this->imapServerID );
			$this->charset		=  translation::charset();
			$this->bofelamimail	= felamimail_bo::getInstance(true,$this->imapServerID);
			$this->imapServerID = $GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'] = $this->bofelamimail->profileID;
			$this->uiwidgets	= CreateObject('felamimail.uiwidgets');
			$this->icServer = $this->bofelamimail->mailPreferences->getIncomingServer($this->imapServerID);
			$this->_connectionStatus = $this->bofelamimail->openConnection($this->imapServerID);

			$this->sessionDataAjax	=& $GLOBALS['egw']->session->appsession('ajax_session_data','felamimail');
			$this->sessionData	=& $GLOBALS['egw']->session->appsession('session_data','felamimail');
			$this->sessionData['folderStatus'] = egw_cache::getCache(egw_cache::INSTANCE,'email','folderStatus'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
			if (!is_array($this->sessionDataAjax)) $this->sessionDataAjax = array();
			if(!isset($this->sessionDataAjax['folderName'])) {
				$this->sessionDataAjax['folderName'] = $this->sessionData['mailbox']?$this->sessionData['mailbox']:'INBOX';
			}
			//error_log("ajaxfelamimail::ajaxfelamimail ActiveProfile:".$this->imapServerID.' activeFolder:'.$this->sessionDataAjax['folderName'].'./.'.$this->sessionData['mailbox'].' ConnectionStatus:'.array2string($this->_connectionStatus));
		}

		function addACL($_accountName, $_aclData, $_recursive=false)
		{
			if($this->_debug) error_log("ajaxfelamimail::addACL for ".$_accountName."->".array2string($_aclData));
			$response = new xajaxResponse();
			//$_recursive=false;
			if(!empty($_accountName)) {
				$acl = implode('',(array)$_aclData['acl']);
				$data = $this->bofelamimail->setACL($this->sessionDataAjax['folderName'], $_accountName, $acl, $_recursive);
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

			$newSubFolder = translation::convert($_newSubFolder, $this->charset, 'UTF7-IMAP');

			if($this->_debug) error_log("ajaxfelamimail::addFolder($parentFolder, $newSubFolder)");

			$response = new xajaxResponse();

			if($folderName = $this->bofelamimail->createFolder($parentFolder, $newSubFolder, true)) {
				$parentFolder = $this->_encodeFolderName($parentFolder);
				$folderName = $this->_encodeFolderName($folderName);
				$newSubFolder = $this->_encodeDisplayFolderName($newSubFolder);
				$response->addScript("tree.insertNewItem('$parentFolder','$folderName','$newSubFolder',onNodeSelect,'folderClosed.gif',0,0,'CHILD,CHECKED');");
			}
			//reset Form
			$response->addAssign("newSubFolder", "value", '');
			//reset folderObject cache, to trigger reload
			felamimail_bo::resetFolderObjectCache($this->imapServerID);

			return $response->getXML();
		}

		function changeSorting($_sortBy)
		{
			if($this->_debug) error_log("ajaxfelamimail::changeSorting:".$_sortBy.'#');
			$this->sessionData['startMessage']	= 1;

			$oldSort = $this->sessionData['sort'];

			switch($_sortBy) {
				case 'date':
					$this->sessionData['sort'] = SORTDATE;
					break;
				case 'from':
					$this->sessionData['sort'] = SORTFROM;
					break;
				case 'to':
					$this->sessionData['sort'] = SORTTO;
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

			$bofilter = new felamimail_bofilter();

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
			$maxMessages = $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
			if (isset($this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior']) && (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'] > 0)
				$maxMessages = (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'];

			if($messageCounter > $maxMessages) {
				$lastPage = $messageCounter - ($messageCounter % $maxMessages) + 1;
				if($lastPage > $messageCounter)
					$lastPage -= $maxMessages;
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
						" onclick=\"xajax_doXMLHTTP('felamimail.ajaxfelamimail.updateSingleACL','$accountName','$acl',this.checked,document.getElementById('recursive').checked); document.getElementById('recursive').checked=false; document.getElementById('predefinedFor_$accountName').options[0].selected=true\"</td>";
				}

				$selectFrom = html::select('identity', $accountAcl['RIGHTS'], $aclShortCuts, false, "id=\"predefinedFor_$accountName\" style='width: 100px;' onChange=\"xajax_doXMLHTTP('felamimail.ajaxfelamimail.updateACL','$accountName',this.value)\"");

				$row .= "<td>$selectFrom</td>";

				$row .= "</tr>";
			}

			return "<table border=\"0\" style=\"width: 100%;\"><tr class=\"th\"><th>&nbsp;</th><th style=\"width:100px;\">Name</th><th>L</th><th>R</th><th>S</th><th>W</th><th>I</th><th>P</th><th>C</th><th>D</th><th>A</th><th>&nbsp;</th></tr>$row</table>";
		}

		function deleteACL($_aclData,$_recursive=false)
		{
			if($this->_debug) error_log("ajaxfelamimail::deleteACL".array2string($_aclData).' Recursively:'.array2string($_recursive));
			$response = new xajaxResponse();
			if(is_array($_aclData)) {
				foreach($_aclData['accountName'] as $accountName) {
					$data = $this->bofelamimail->deleteACL($this->sessionDataAjax['folderName'], $accountName, $_recursive);
				}

				if ($folderACL = $this->bofelamimail->getIMAPACL($this->sessionDataAjax['folderName'])) {
					$response->addAssign("aclTable", "innerHTML", $this->createACLTable($folderACL));
				}
			}
			//reset folderObject cache, to trigger reload
			felamimail_bo::resetFolderObjectCache($this->imapServerID);
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

		function saveAsDraft($_composeID, $_data, $_autoSave=true)
		{
			if($this->_debug) error_log(__METHOD__.__LINE__.' AutoSave'.$_autoSave.' ID:'.array2string($_composeID).' Data:'.array2string($_data));
			$bocompose   = CreateObject('felamimail.bocompose',$_composeID,$this->charset);
			$folder = $messageFolder = ($this->bofelamimail->mailPreferences->ic_server[$this->bofelamimail->profileID]->draftfolder ? $this->bofelamimail->mailPreferences->ic_server[$this->bofelamimail->profileID]->draftfolder : $this->bofelamimail->mailPreferences->preferences['draftFolder']);
			// autosave should always save to Draft. Manual Save may Save to templates Folder
			if ($_autoSave)
			{
				if (is_array($bocompose->sessionData) && isset($bocompose->sessionData['messageFolder']) && $this->bofelamimail->isTemplateFolder($bocompose->sessionData['messageFolder']))
				{
					$messageFolder = $bocompose->sessionData['messageFolder'];
					$bocompose->sessionData['messageFolder'] = $folder;
					//error_log(__METHOD__.__LINE__.' MessageFolder:'.$messageFolder.' SavingDestination:'.$folder);
				}
			}
			else
			{
				//error_log(__METHOD__.__LINE__.' ID:'.array2string($_composeID).'->'.$folder.' Data:'.array2string($bocompose->sessionData['messageFolder']));
			}

			$this->bofelamimail->reopen($folder);
			$status = $this->bofelamimail->getFolderStatus($folder);
			//error_log(__METHOD__.__LINE__.array2string(array('Folder'=>$folder,'Status'=>$status)));
			$uidNext = $status['uidnext']; // we may need that, if the server does not return messageUIDs of saved/appended messages
			$_data['saveAsDraft'] = 1;
			$formData['identity']	= (int)$_data['identity'];

			foreach((array)$_data['destination'] as $key => $destination) {
				if(!empty($_data['address'][$key])) {
					if($destination == 'folder') {
						$formData[$destination][] = $GLOBALS['egw']->translation->convert($_data['address'][$key], $this->charset, 'UTF7-IMAP');
					} else {
						$formData[$destination][] = $_data['address'][$key];
					}
				}
			}

			$formData['subject'] 	= $bocompose->stripSlashes($_data['subject']);
			$formData['body'] 	= $bocompose->stripSlashes($_data['body']);
/*
			// if the body is empty, maybe someone pasted something with scripts, into the message body
			if(empty($formData['body']))
			{
				// this is to be found with the egw_unset_vars array for the _POST['body'] array
				$name='_POST';
				$key='body';
				//error_log($GLOBALS['egw_unset_vars'][$name.'['.$key.']']);
				if (isset($GLOBALS['egw_unset_vars'][$name.'['.$key.']']))
				{
					$formData['body'] = bocompose::_getCleanHTML( $GLOBALS['egw_unset_vars'][$name.'['.$key.']']);
				}
			}
*/
			$formData['priority'] 	= $bocompose->stripSlashes($_data['priority']);
			$formData['signatureID'] = (int)$_data['signatureID'];
			$formData['stationeryID'] = $_data['stationeryID'];
			$formData['mimeType']	= $bocompose->stripSlashes($_data['mimeType']);
			if ($formData['mimeType'] == 'html' && html::htmlarea_availible()===false)
			{
				$formData['mimeType'] = 'plain';
				$formData['body'] = $bocompose->convertHTMLToText($formData['body']);
			}
			$formData['disposition'] = (bool)$_data['disposition'];
			$formData['to_infolog'] = $_data['to_infolog'];
			$formData['to_tracker'] = $_data['to_tracker'];
			$formData['isDraft'] = 1;
			$lastDrafted = false;
			if (isset($bocompose->sessionData['lastDrafted'])) $lastDrafted = $bocompose->sessionData['lastDrafted'];
			$messageUid = $bocompose->saveAsDraft($formData,$folder); // folder may change
			if ($lastDrafted && is_array($lastDrafted) && isset($lastDrafted['uid']) && !empty($lastDrafted['uid'])) $lastDrafted['uid'] = trim($lastDrafted['uid']);
			if ($lastDrafted && is_array($lastDrafted) && isset($lastDrafted['uid']) && !empty($lastDrafted['uid'])) $this->bofelamimail->deleteMessages((array)$lastDrafted['uid'],$lastDrafted['folder'],"remove_immediately");
			if ($_autoSave)
			{
				$bocompose->sessionData['lastDrafted'] = array('uid'=>$messageUid,'folder'=>$folder);
				if ($this->bofelamimail->isTemplateFolder($messageFolder)) $bocompose->sessionData['messageFolder'] = $messageFolder;
			}
			else
			{
				if (isset($bocompose->sessionData['lastDrafted'])) unset($bocompose->sessionData['lastDrafted']);
			}
			$bocompose->saveSessionData();
			if($this->_debug) error_log(__METHOD__.__LINE__.' saved as:'.$messageUid.' in '.$folder);
		}

		function removeLastDraftedVersion($_composeID)
		{
			if($this->_debug) error_log(__METHOD__.__LINE__.' ID:'.array2string($_composeID));
			if (!empty($_composeID))
			{
				$bocompose   = CreateObject('felamimail.bocompose',$_composeID,$this->charset);
				$folder = ($this->bofelamimail->mailPreferences->ic_server[$this->bofelamimail->profileID]->draftfolder ? $this->bofelamimail->mailPreferences->ic_server[$this->bofelamimail->profileID]->draftfolder : $this->bofelamimail->mailPreferences->preferences['draftFolder']);
				$this->bofelamimail->reopen($folder);
				if (isset($bocompose->sessionData['lastDrafted'])) $lastDrafted = $bocompose->sessionData['lastDrafted'];
				if ($lastDrafted && is_array($lastDrafted) && isset($lastDrafted['uid']) && !empty($lastDrafted['uid'])) $lastDrafted['uid'] = trim($lastDrafted['uid']);
				if ($lastDrafted && is_array($lastDrafted) && isset($lastDrafted['uid']) && !empty($lastDrafted['uid'])) $this->bofelamimail->deleteMessages((array)$lastDrafted['uid'],$lastDrafted['folder']);
				if($this->_debug) error_log(__METHOD__.__LINE__.' removed last drafted:'.$lastDrafted['uid'].' in '.$lastDrafted['folder']);
			}
		}

        function toggleEditor($_composeID, $_content ,$_mode)
        {
			$_content = utf8_decode($_content);

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
				if (stripos($_content,'<pre>')!==false)
				{
					$contentArr = html::splithtmlByPRE($_content);
					foreach ($contentArr as $k =>&$elem)
					{
						if (stripos($elem,'<pre>')!==false) $elem = str_replace(array("\r\n","\n","\r"),array("<br>","<br>","<br>"),$elem);
					}
					$_content = implode('',$contentArr);
				}
				$_content = $bocompose->_getCleanHTML($_content, false, false);
				//$_content = $bocompose->convertHTMLToText($_content);
				$_content = translation::convertHTMLToText($_content,'ISO-8859-1',$stripcrl=false,$stripalltags=true);
			}
			if($this->_debug) error_log(__METHOD__.__LINE__.$_content);
			$this->saveSessionData();

			$response = new xajaxResponse();

			$escaped = utf8_encode(str_replace(array("'", "\r", "\n"), array("\\'", "\\r", "\\n"), $_content));
			if ($_mode == 'simple')
				$response->addScript("showHTMLEditor('$escaped');");
			else
				$response->addScript("showPlainEditor('$escaped');");

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
			//reset folderObject cache, to trigger reload
			felamimail_bo::resetFolderObjectCache($this->imapServerID);

			return $response->getXML();
		}

		/*
		* delete messages
		*
		* @param array _messageList list of UID's
		*
		* @return xajax response
		*/
		function deleteMessages($_messageList,$_refreshMessageList=true)
		{
			if($this->_debug) error_log(__METHOD__." called with Messages ".print_r($_messageList,true));
			$messageCount = 0;
			if(is_array($_messageList) && count($_messageList['msg']) > 0) $messageCount = count($_messageList['msg']);
			try
			{
				$this->bofelamimail->deleteMessages(($_messageList == 'all'? 'all':$_messageList['msg']));
				unset($this->sessionData['previewMessage']);
				$this->saveSessionData();
			}
			catch (egw_exception $e)
			{
				$error = str_replace('"',"'",$e->getMessage());
				$response = new xajaxResponse();
				$response->addScript('resetMessageSelect();');
				$response->addScript('tellUser("'.$error.'");');
				$response->addScript('onNodeSelect("'.$this->sessionData['mailbox'].'");');
				return $response->getXML();
			}
			if ($_refreshMessageList === false)
			{
				$response = new xajaxResponse();
				return $response->getXML();
			}

			return $this->generateMessageList($this->sessionData['mailbox'],($_messageList=='all'?0:(-1*$messageCount)));
		}

		/*
		* undelete messages
		*
		* @param array _messageList list of UID's
		*
		* @return xajax response
		*/
		function undeleteMessages($_messageList, $_refreshMessageList = true)
		{
			if($this->_debug) error_log(__METHOD__." called with Messages ".print_r($_messageList,true));
			$messageCount = 0;
			if(is_array($_messageList) && count($_messageList['msg']) > 0) $messageCount = count($_messageList['msg']);
			try
			{
				$this->bofelamimail->flagMessages('undelete',$message,$mailfolder);
			}
			catch (egw_exception $e)
			{
				$error = str_replace('"',"'",$e->getMessage());
				$response = new xajaxResponse();
				$response->addScript('resetMessageSelect();');
				$response->addScript('tellUser("'.$error.'");');
				$response->addScript('onNodeSelect("'.$this->sessionData['mailbox'].'");');
				return $response->getXML();
			}
			if ($_refreshMessageList === false)
			{
				$response = new xajaxResponse();
				return $response->getXML();
			}

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
			// unset the previewID, as the Message will not be available on another server
			unset($this->sessionData['previewMessage']);
			$this->saveSessionData();

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
			// unset the previewID, as the Message will not probably not be within the selection
			unset($this->sessionData['previewMessage']);
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

			// unset preview, as refresh would mark message again read
			if ($_flag == 'unread' && in_array($this->sessionData['previewMessage'], $_messageList['msg']))
			{
				unset($this->sessionData['previewMessage']);
				$this->saveSessionData();
			}

			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function sendNotify ($_uid, $_ret)
		{
			if($this->_debug) error_log(__METHOD__." with $_uid,$_ret for Folder:".$this->sessionDataAjax['folderName'].'./.'.$this->sessionData['mailbox']);
			$response = new xajaxResponse();
			if ($_ret==='true' || $_ret===1 || $_ret == "1,") {
				if ( $this->bofelamimail->sendMDN($_uid) )
					$this->bofelamimail->flagMessages("mdnsent",array($_uid));
			} else {
				 $this->bofelamimail->flagMessages("mdnnotsent",array($_uid));
			}
			return $response;

		}


		function generateMessageList($_folderName,$modifyoffset=0,$listOnly=false)
		{
			if($this->_debug) error_log("ajaxfelamimail::generateMessageList with $_folderName,$modifyoffset".function_backtrace());
			$response = new xajaxResponse();
			$response->addScript("mail_cleanup()");
			$response->addScript("activeServerID=".$this->imapServerID.";");
			$response->addScript("activeFolder = \"".$_folderName."\";");
			$response->addScript("activeFolderB64 = \"".base64_encode($_folderName)."\";");
			$sentFolder = $this->bofelamimail->getSentFolder(false);
			$response->addScript("sentFolder = \"".($sentFolder?$sentFolder:'')."\";");
			$response->addScript("sentFolderB64 = \"".($sentFolder?base64_encode($sentFolder):'')."\";");
			$draftFolder = $this->bofelamimail->getDraftFolder(false);
			$response->addScript("draftFolder = \"".($draftFolder?$draftFolder:'')."\";");
			$response->addScript("draftFolderB64 = \"".($draftFolder?base64_encode($draftFolder):'')."\";");
			$templateFolder = $this->bofelamimail->getTemplateFolder(false);
			$response->addScript("templateFolder = \"".($templateFolder?$templateFolder:'')."\";");
			$response->addScript("templateFolderB64 = \"".($templateFolder?base64_encode($templateFolder):'')."\";");
			if($this->_connectionStatus === false) {
				return $response->getXML();
			}

			$listMode = 0;

			$this->bofelamimail->restoreSessionData();
			//error_log($this->sessionData['previewMessage']);
			//error_log(__METHOD__.__LINE__.' ->'.$_folderName.' ShowAsSent:'.$GLOBALS['egw_info']['user']['preferences']['felamimail']['messages_showassent_0']);

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
			if (isset($this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior']) && (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'] <> 0)
				$maxMessages = (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'];
			//if ($this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior']==NULL) error_log(__METHOD__.__LINE__.' MailPreferences:'.array2string($this->bofelamimail->mailPreferences));
			$offset = $this->sessionData['startMessage'];
			if($this->_debug) error_log("ajaxfelamimail::generateMessageList with $offset,$modifyoffset");
			if ($modifyoffset != 0 && ($offset+$modifyoffset)>0) $offset = $offset+$modifyoffset;
			if($this->_debug) error_log("ajaxfelamimail::generateMessageList with offset: $offset PreviewMessage:".array2string($this->sessionData['previewMessage']));
			$headers = array();
			$headers['info']['total']	= 0;
			$headers['info']['first']	= $offset;
			$headers['info']['last']	= 0;
			if($this->sessionData['previewMessage'])
			{
				$headers = $this->bofelamimail->getHeaders(
					$_folderName,
					$offset,
					($maxMessages>0?$maxMessages:1),
					$this->sessionData['sort'],
					$this->sessionData['sortReverse'],
					(array)$this->sessionData['messageFilter'],
					$this->sessionData['previewMessage']
				);
				if($this->_debug) error_log(__METHOD__.__LINE__." headers fetched:".array2string($headers));
			}
			$rowsFetched = array();
			if($this->_debug) error_log(__METHOD__.__LINE__.' MaxMessages:'.$maxMessages.' Offset:'.$offset.' Filter:'.array2string($this->sessionData['messageFilter']));
			//error_log(__METHOD__.__LINE__.' Data:'.array2string($headers));
			$headerJs = $this->uiwidgets->get_grid_js($listMode,$_folderName,$rowsFetched,$offset,false,($maxMessages>=0?false:true));
			$headerTable = $this->uiwidgets->messageTable(
				$headers,
				$listMode,
				$_folderName,
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['message_newwindow'],
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['rowOrderStyle'],
				$this->sessionData['previewMessage']
			);
			if ($rowsFetched['messages']>0 && empty($headers['info']['total']))
			{
				if($this->_debug) error_log(__METHOD__.__LINE__.' Rows fetched:'.array2string($rowsFetched).' Headers Info:'.array2string($headers['info']));
				$headers['info']['total'] = $rowsFetched['messages'];
				//error_log(__METHOD__.__LINE__.' Cached FolderInfo:'.array2string($this->sessionData['folderStatus'][$this->imapServerID][$_folderName]));
				if (empty($headers['info']['total'])) $headers['info']['total']	= $this->sessionData['folderStatus'][$this->imapServerID][$_folderName]['messages'];
				if (empty($headers['info']['total']))
				{
					$foldestatus = $this->bofelamimail->getMailBoxCounters($_folderName);
					$headers['info']['total'] = $foldestatus->messages;
				}
				$headers['info']['first']	= $offset;
				$headers['info']['last']	= $offset+$rowsFetched['rowsFetched']-1;
			}
			if($this->_debug) error_log(__METHOD__.__LINE__.' Rows fetched:'.array2string($rowsFetched));
			//error_log(__METHOD__.__LINE__.' HeaderJS:'.$headerJs);
			//error_log(__METHOD__.__LINE__.' HeaderTable:'.$headerTable);
			$firstMessage = (int)$headers['info']['first'];
			$lastMessage  = (int)$headers['info']['last'];
			$totalMessage = (int)$headers['info']['total'];
			if ((int)$maxMessages<0) $totalMessage = $rowsFetched['messages'];
			$shortName = '';
			if($folderStatus = $this->bofelamimail->getFolderStatus($_folderName)) {
				$shortName =$folderStatus['shortDisplayName'];
			}
			if($totalMessage == 0) {
				$response->addAssign("messageCounter", "innerHTML", '<b>'.$shortName.': </b>'.lang('no messages found...'));
			} else {
				$response->addAssign("messageCounter", "innerHTML", '<b>'.$shortName.': </b>'.lang('Viewing messages').($maxMessages>0?" <b>$firstMessage</b> - <b>$lastMessage</b>":"")." ($totalMessage ".lang("total").')');
			}

			$response->addAssign("divMessage".($listOnly?'Table':'')."List", "innerHTML", $headerJs.$headerTable);
			$response->addAssign("skriptGridOnFirstLoad","innerHTML","");

			if($quota = $this->bofelamimail->getQuotaRoot()) {
				if (isset($quota['usage']) && $quota['limit'] != 'NOT SET')
				{
					$quotaDisplay = $this->uiwidgets->quotaDisplay($quota['usage'], $quota['limit']);
					$response->addAssign('quotaDisplay', 'innerHTML', $quotaDisplay);
				}
			}

			if($folderStatus = $this->bofelamimail->getFolderStatus($_folderName)) {
				if($folderStatus['unseen'] > 0) {
					$response->addScript("egw_topWindow().tree.setItemText('$_folderName', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
				} else {
					$response->addScript("egw_topWindow().tree.setItemText('$_folderName', '". $folderStatus['shortDisplayName'] ."');");
				}
			}

			if(!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder']) &&
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'] != 'none' ) {
				$folderStatus = $this->bofelamimail->getFolderStatus($GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder']);
				if($folderStatus['unseen'] > 0) {
					$response->addScript("egw_topWindow().tree.setItemText('". $GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'] ."', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
				} else {
					$response->addScript("egw_topWindow().tree.setItemText('". $GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'] ."', '". $folderStatus['shortDisplayName'] ."');");
				}
			}

			$response->addScript("egw_topWindow().tree.selectItem('".$_folderName. "',false);");

			if($this->_debug) error_log('generateMessageList done');
			if ($this->sessionData['previewMessage']>0)
			{
				$response->addScript('fm_previewMessageID = "";');
				$response->addScript('mail_focusGridElement('.$this->sessionData['previewMessage'].');');
			}
			else
			{
				$response->addScript('mail_focusGridElement();');
			}
			$response->addScript('if (typeof handleResize != "undefined") handleResize();');

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

			$maxMessages = $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
			if (isset($this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior']) && (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'] > 0)
				$maxMessages = (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'];

			$lastPage = $messageCounter - ($messageCounter % $maxMessages) + 1;
			if($lastPage > $messageCounter)
				$lastPage -= $maxMessages;

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
					try
					{
						$this->bofelamimail->moveMessages($folderName, ($_selectedMessages == 'all'? null:$_selectedMessages['msg']));
						unset($this->sessionData['previewMessage']);
						$this->saveSessionData();
					}
					catch (egw_exception $e)
					{
						$error = str_replace('"',"'",$e->getMessage());
						$response = new xajaxResponse();
						$response->addScript('resetMessageSelect();');
						$response->addScript('tellUser("'.$error.' '.lang('Folder').':'.'","'.$_folderName.'");');
						$response->addScript('onNodeSelect("'.$this->sessionData['mailbox'].'");');
						return $response->getXML();
					}
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
			$error = false;
			if(is_array($_selectedMessages) && count($_selectedMessages['msg']) > 0) $messageCount = count($_selectedMessages['msg']);
			$folderName = $this->_decodeEntityFolderName($_folderName);
			if ($_selectedMessages == 'all' || !empty( $_selectedMessages['msg']) && !empty($folderName)) {
				if ($this->sessionData['mailbox'] != $folderName)
				{
					$deleteAfterMove = false;
					try
					{
						$this->bofelamimail->moveMessages($folderName, ($_selectedMessages == 'all'? null:$_selectedMessages['msg']),$deleteAfterMove);
					}
					catch (egw_exception $e)
					{
						$error = str_replace('"',"'",$e->getMessage());
						$response = new xajaxResponse();
						$response->addScript('resetMessageSelect();');
						$response->addScript('tellUser("'.$error.' '.lang('Folder').':'.'","'.$_folderName.'");');
						$response->addScript('onNodeSelect("'.$this->sessionData['mailbox'].'");');
						return $response->getXML();
					}
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
			$bofilter		= new felamimail_bofilter();

			$filter['filterName']	= lang('Quicksearch');
			$filter['type']		= $_searchType;
			$filter['string']	= str_replace('"','\"', str_replace('\\','\\\\',$_searchString));
			$filter['status']	= $_status;

			$this->sessionData['messageFilter'] = $filter;

			$this->sessionData['startMessage'] = 1;
			// unset the previewID, as the Message will not be available with the filtered view
			unset($this->sessionData['previewMessage']);

			$this->saveSessionData();

			// generate the new messageview
			return $this->generateMessageList($this->sessionData['mailbox']);
		}

		function refreshMessagePreview($_messageID,$_folderType)
		{
			if ($this->_debug) error_log(__METHOD__.__LINE__.' MessageId:'.$_messageID.', FolderType:'.$_folderType);
			if (!empty($_messageID))
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
				//error_log(__METHOD__.__LINE__.print_r($headerData,true));
				foreach ($headerData as $key => $val)
				{
					if (is_array($val))
					{
						foreach($val as $ik => $ival)
						{
							//error_log(__METHOD__.__LINE__.print_r($ival,true));
							if (is_array($ival))
							{
								foreach($ival as $jk => $jval)
								{
									$headerData[$key][$ik][$jk] = felamimail_bo::htmlentities($jval);
								}
							}
							else
							{
								$headerData[$key][$ik] = felamimail_bo::htmlentities($ival);
							}
						}
					}
					else
					{
						$headerData[$key] = felamimail_bo::htmlentities($val);
					}
				}
				$headerData['subject'] = $this->bofelamimail->decode_subject($headerData['subject'],false);
				$this->sessionData['previewMessage'] = $headerData['uid'];
				$this->saveSessionData();
			}
			//error_log(__METHOD__.__LINE__.print_r($headerData,true));
			$previewFrameHeight = $GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight'];
			$IFRAMEBody = "<TABLE BORDER=\"1\" rules=\"rows\" style=\"table-layout:fixed;width:100%;\">
						<TR class=\"th\" style=\"width:100%;\">
							<TD nowrap valign=\"top\">
								".'<b><br> '.
								"<center><font color='red'>".(!($_folderType == 2 || $_folderType == 3)?lang("Select a message to switch on its preview (click on subject)"):lang("Preview disabled for Folder: ").$_folderName)."</font></center><br>
								</b>"."
							</TD>
						</TR>
						<TR>
							<TD nowrap id=\"tdmessageIFRAME\" valign=\"top\" height=\"".$previewFrameHeight."\">
								&nbsp;
							</TD>
						</TR>
					   </TABLE>";

			$response = new xajaxResponse();
			$response->addScript("document.getElementById('messageCounter').innerHTML =MessageBuffer;");
			//$response->addScript("document.getElementById('messageCounter').innerHTML ='';");
			$response->addScript("fm_previewMessageID=".(empty($_messageID)?'':$headerData['uid']).";");
			$response->addAssign('spanMessagePreview', 'innerHTML', (empty($_messageID)?$IFRAMEBody:$this->uiwidgets->updateMessagePreview($headerData,$_folderType, $this->sessionData['mailbox'],$this->imapServerID)));
			$response->addScript('if (typeof handleResize != "undefined") handleResize();');

			// Also refresh the folder status
			$this->refreshFolder($response);

			return $response->getXML();
		}

		function refreshMessageList()
		{
			return $this->generateMessageList($this->sessionData['mailbox'],0,$listOnly=true);
		}

		function refreshFolder($injectIntoResponse = false)
		{
			if ($this->_debug) error_log("ajaxfelamimail::refreshFolder");
			$GLOBALS['egw']->session->commit_session();

			if (!$injectIntoResponse)
			{
				$response = new xajaxResponse();
			}
			else
			{
				$response = $injectIntoResponse;
			}

			if ($this->_connectionStatus === true) {
				$folderName = $this->sessionData['mailbox'];
				//error_log(array2string($this->bofelamimail->getFolderStatus($folderName)));
				if ($folderStatus = $this->bofelamimail->getFolderStatus($folderName)) {
					if ($folderStatus['unseen'] > 0) {
						$response->addScript("egw_topWindow().tree.setItemText('$folderName', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
					} else {
						$response->addScript("egw_topWindow().tree.setItemText('$folderName', '". $folderStatus['shortDisplayName'] ."');");
					}
				}
			}

			if (!$injectIntoResponse)
			{
				return $response->getXML();
			}
		}

		function refreshFolderList($activeFolderList ='')
		{
			if ($this->_debug) error_log(__METHOD__.__LINE__." with folders:".$activeFolderList);
			if ($activeFolderList != '') $activeFolders = explode('#,#',$activeFolderList);
			$GLOBALS['egw']->session->commit_session();

			$response = new xajaxResponse();
			if(!($this->_connectionStatus === true)) $this->_connectionStatus = $this->bofelamimail->openConnection($this->imapServerID);
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
							$response->addScript("egw_topWindow().tree.setItemText('".@htmlspecialchars($folderName,ENT_QUOTES, felamimail_bo::$displayCharset,false)."', '<b>". $folderStatus['shortDisplayName'] ." (". $folderStatus['unseen'] .")</b>');");
						} else {
							$response->addScript("egw_topWindow().tree.setItemText('".@htmlspecialchars($folderName,ENT_QUOTES, felamimail_bo::$displayCharset,false)."', '". $folderStatus['shortDisplayName'] ."');");
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

		function reloadImportMail($_importID)
		{
			//error_log(__METHOD__.__LINE__.'called');
			$bocompose	= CreateObject('felamimail.bocompose', $_importID);
			foreach((array)$bocompose->sessionData['attachments'] as $id => $attachment) {
				switch(strtoupper($attachment['type'])) {
					case 'MESSAGE/RFC822':
						//error_log(__METHOD__.__LINE__.array2string($attachment));
						break;
				}
			}

			$response = new xajaxResponse();
			$response->addAssign('addFileName', 'value', $attachment['name']);
			$response->addScript("document.fileUploadForm.submit();");
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
			$folderName = translation::convert($this->_decodeEntityFolderName($_folderName), $this->charset, 'UTF7-IMAP');
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
					$response->addScript("window.tree.deleteItem('$_oldFolderName',0);");
					$response->addScript("window.tree.insertNewItem('$_parentFolder','$newFolderName','$folderName',onNodeSelect,'MailFolderPlain.png',0,0,'CHILD,CHECKED,SELECT,CALL');");
				}
			}
			//reset folderObject cache, to trigger reload
			felamimail_bo::resetFolderObjectCache($this->imapServerID);

			return $response->getXML();
		}

		function saveSessionData()
		{
			$GLOBALS['egw']->session->appsession('ajax_session_data','felamimail',$this->sessionDataAjax);
			if (isset($this->sessionData['folderStatus']) && is_array($this->sessionData['folderStatus']))
			{
				egw_cache::setCache(egw_cache::INSTANCE,'email','folderStatus'.trim($GLOBALS['egw_info']['user']['account_id']),$this->sessionData['folderStatus'], $expiration=60*60*1);
				unset($this->sessionData['folderStatus']);
			}
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
			if ($Identities->signature)
			{
				$response->addScript('setSignature('.$Identities->signature.');');
			}
			else
			{
				$bosignatures	= CreateObject('felamimail.felamimail_bosignatures');
				$defaultSig = $bosignatures->getDefaultSignature();
				if ($defaultSig === false) $defaultSig = -1;
				$response->addScript('setSignature('.$defaultSig.');');
			}

			return $response->getXML();
		}

		function changeComposeSignature($_composeID,$_oldSig,$_signatureID,$_currentMode,$_content)
		{
			// we need a lot of encoding/decoding transforming here to get at least some acceptable result
			// the changing does not work with all sigs, as the old Signature may not match the Signaturepart in Content
			if($this->_debug) error_log(__METHOD__.$_oldSig.','.$_signatureID.'#');
			$bocompose  = CreateObject('felamimail.bocompose', $_composeID);
			// prepare signatures, the selected sig may be used on top of the body
			require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.felamimail_bosignatures.inc.php');
			$boSignatures = new felamimail_bosignatures();
			$oldSignature = $boSignatures->getSignature($_oldSig);
			$oldSigText = $oldSignature->fm_signature;
			$signature = $boSignatures->getSignature($_signatureID);
			$sigText = $signature->fm_signature;
			//error_log(__METHOD__.'Old:'.$oldSigText.'#');
			if ($_currentMode == 'plain')
			{
				$oldSigText = utf8_decode($bocompose->convertHTMLToText($oldSigText));
				$sigText = utf8_decode($bocompose->convertHTMLToText($sigText));
				$_content = utf8_decode($_content);
				if($this->_debug) error_log(__METHOD__." Old signature:".$oldSigText);
			}

			$oldSigText = felamimail_bo::merge($oldSigText,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
			//error_log(__METHOD__.'Old+:'.$oldSigText.'#');
			$sigText = felamimail_bo::merge($sigText,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
			//error_log(__METHOD__.'new+:'.$sigText.'#');
			$oldSigText = str_replace(array("\r","\t","<br />\n",": "),array("","","<br />",":"),($_currentMode == 'html'?html::purify($oldSigText):$oldSigText));
			//error_log(__METHOD__.'Old(clean):'.$oldSigText.'#');
			$_content = str_replace(array("\r","\t","<br />\n",": "),array("","","<br />",":"),($_currentMode == 'html'?html::purify($_content):$_content));
			$found = strpos($_content,trim($oldSigText));
			if ($found !== false && $_oldSig != -2 && !(empty($oldSigText) || trim($bocompose->convertHTMLToText($oldSigText)) ==''))
			{
				//error_log(__METHOD__.'Old Content:'.$_content.'#');
				$_oldSigText = preg_quote($oldSigText,'~');
				//error_log(__METHOD__.'Old(masked):'.$_oldSigText.'#');
				$_content = preg_replace('~'.$_oldSigText.'~mi',$sigText,$_content,1);
				//error_log(__METHOD__.'new Content:'.$_content.'#');
			}
			if ($_oldSig == -2 && (empty($oldSigText) || trim($bocompose->convertHTMLToText($oldSigText)) ==''))
			{
				// if there is no sig selected, there is no way to replace a signature
			}
			if ($found === false)
			{
				if($this->_debug) error_log(__METHOD__." Old Signature failed to match:".$oldSigText);
				if($this->_debug) error_log(__METHOD__." Compare content:".$_content);
			}
			$response = new xajaxResponse();
			if ($_currentMode == 'html') $_content = utf8_decode($_content);
			$escaped = utf8_encode(str_replace(array("'", "\r", "\n"), array("\\'", "\\r", "\\n"), $_content));
			//error_log(__METHOD__.$escaped);
			if ($_currentMode == 'html')
				$response->addScript("showHTMLEditor('$escaped');");
			else
				$response->addScript("showPlainEditor('$escaped');");
			/*
			if ($found===false)
			{
				$warning = lang("Switching of Signatures failed");
				$response->addScript('alert('.$warning.');');
			}
			*/
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
							$str = translation::convert(trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']).' <'.trim($email).'>',$this->charset,'utf-8');
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
			// unset the previewID, as the Message will not be available with the next subset
			unset($this->sessionData['previewMessage']);

			$sortedList = $this->bofelamimail->getSortedList(
				$this->sessionData['mailbox'],
				$this->sessionData['sort'],
				$this->sessionData['sortReverse'],
				(array)$this->sessionData['messageFilter']
			);
			$messageCounter = count($sortedList);
			// $lastPage is the first message ID of the last page
			$maxMessages = $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
			if (isset($this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior']) && (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'] > 0)
				$maxMessages = (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'];

			if($messageCounter > $maxMessages) {
				$lastPage = $messageCounter - ($messageCounter % $maxMessages) + 1;
				if($lastPage > $messageCounter) {
					$lastPage -= $maxMessages;
				}
				$this->sessionData['startMessage'] += $maxMessages;
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
			// unset the previewID, as the Message will not be available on the prev subset
			unset($this->sessionData['previewMessage']);

			$maxMessages = $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
			if (isset($this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior']) && (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'] > 0)
				$maxMessages = (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'];

			$this->sessionData['startMessage']	-= $maxMessages;
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
			//error_log(__METHOD__.__LINE__." called with: $_user, $_acl");
			// not sure this one is used / called anymore
			if ($_acl == 'custom') {
				$response = new xajaxResponse();
				return $response->getXML();
			}
			$_recursive=false;
			$_folderName = $this->sessionDataAjax['folderName'];
			$result = $this->bofelamimail->setACL($_folderName, $_user, $_acl, $_recursive);
			if ($result && $folderACL = $this->bofelamimail->getIMAPACL($_folderName)) {
				//reset folderObject cache, to trigger reload
				felamimail_bo::resetFolderObjectCache($this->imapServerID);
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
			//reset folderObject cache, to trigger reload
			felamimail_bo::resetFolderObjectCache($this->imapServerID);

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
			if($this->_debug)
			{
				error_log("ajaxfelamimail::updateMessageView $folderName $this->charset");
				error_log(__METHOD__.__LINE__.' '.$folderName.' <=> '.$this->sessionData['mailbox']);
			}
			// unset the previewID, as the Message will not be available on another folder
			if ($folderName != $this->sessionData['mailbox']) unset($this->sessionData['previewMessage']);

			$this->sessionData['mailbox'] 		= $folderName;
			$this->sessionData['startMessage']	= 1;
			$this->saveSessionData();

			$messageList = $this->generateMessageList($folderName);

			$this->bofelamimail->closeConnection();

			return $messageList;
		}

		function updateSingleACL($_accountName, $_aclType, $_aclStatus, $_recursive=false)
		{
			$response = new xajaxResponse();
			//$_recursive=false;
			$data = $this->bofelamimail->updateSingleACL($this->sessionDataAjax['folderName'], $_accountName, $_aclType, $_aclStatus, $_recursive);
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
			$folderName = translation::convert($_folderName, 'UTF7-IMAP', $this->charset);
			$folderName = htmlspecialchars($folderName, ENT_QUOTES, $this->charset);

			$search         = array('\\');
			$replace        = array('\\\\');

			return str_replace($search, $replace, $folderName);
		}

}
?>
