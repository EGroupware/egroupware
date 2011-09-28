<?php
/**
 * EGroupware - FeLaMiMail - preference user interface
 *
 * @link http://www.egroupware.org
 * @package felamimail
 * @author Lars Kneschke [lkneschke@linux-at-work.de]
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2009-10 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * FeLaMiMail preference user interface class, provides UI functionality for preferences/actions like
 * managing folders, acls, signatures, rules
 */

	class uipreferences
	{
		/**
		 * Reference to felamimail_bo
		 *
		 * @var felamimail_bo
		 */
		var $bofelamimail;

		var $public_functions = array
		(
			'addACL'		=> 'True',
			'editAccountData'	=> 'True',
			'editForwardingAddress'	=> 'True',
			'editSignature'		=> 'True',
			'listFolder'		=> 'True',
			'listSignatures'	=> 'True',
			'listAccountData'	=> 'True',
			'showHeader'		=> 'True',
			'getAttachment'		=> 'True',
			'listSelectFolder'	=> 'True',
		);

		function uipreferences()
		{
			$this->t = $GLOBALS['egw']->template;
			$this->charset = translation::charset();

			$this->bofelamimail	= felamimail_bo::getInstance();
			$this->bopreferences	= $this->bofelamimail->bopreferences;
			$this->uiwidgets	= CreateObject('felamimail.uiwidgets');

			if (is_object($this->bofelamimail->mailPreferences))
			{
				// account select box
				$selectedID = $this->bofelamimail->getIdentitiesWithAccounts($identities);
				// if nothing valid is found return to user defined account definition
				if (empty($this->bofelamimail->icServer->host) && count($identities)==0 && $this->bofelamimail->mailPreferences->userDefinedAccounts)
				{
					// redirect to new personal account
					$this->editAccountData(lang("There is no IMAP Server configured.")." - ".lang("Please configure access to an existing individual IMAP account."), 'new');
					exit;
				}
			}

			$this->rowColor[0] = $GLOBALS['egw_info']["theme"]["bg01"];
			$this->rowColor[1] = $GLOBALS['egw_info']["theme"]["bg02"];
		}

		function addACL()
		{
			$this->display_app_header(FALSE);

			$this->t->set_file(array("body" => "preferences_manage_folder.tpl"));
			$this->t->set_block('body','main');
			$this->t->set_block('body','add_acl');

			$this->translate();

			$this->t->pparse("out","add_acl");

		}

		// $_displayNavbar false == don't display navbar
		function display_app_header($_displayNavbar)
		{
			switch($_GET['menuaction'])
			{
				case 'felamimail.uipreferences.editSignature':
					egw_framework::validate_file('jscode','listSignatures','felamimail');
					egw_framework::validate_file('ckeditor3','ckeditor','phpgwapi');
					#$GLOBALS['egw']->js->set_onload('fm_initEditLayout();');
					break;
				case 'felamimail.uipreferences.listAccountData':
				case 'felamimail.uipreferences.editAccountData':
					egw_framework::validate_file('tabs','tabs');
					egw_framework::validate_file('jscode','editAccountData','felamimail');
					$GLOBALS['egw']->js->set_onload('javascript:initEditAccountData();');
					if ($_GET['menuaction'] == 'felamimail.uipreferences.editAccountData') $GLOBALS['egw']->js->set_onload('javascript:initTabs();');
					break;

				case 'felamimail.uipreferences.listSignatures':
					egw_framework::validate_file('jscode','listSignatures','felamimail');
					#$GLOBALS['egw']->js->set_onload('javascript:initEditAccountData();');
					break;

				case 'felamimail.uipreferences.listFolder':
				case 'felamimail.uipreferences.addACL':
				case 'felamimail.uipreferences.listSelectFolder':
					egw_framework::validate_file('tabs','tabs');
					// this call loads js and css for the treeobject
					html::tree(false,false,false,null,'foldertree','','',false,'/',null,false);
					egw_framework::validate_file('jscode','listFolder','felamimail');
					$GLOBALS['egw']->js->set_onload('javascript:initAll();');
					break;
			}

			$GLOBALS['egw_info']['flags']['include_xajax'] = True;

			$GLOBALS['egw']->common->egw_header();
			if($_displayNavbar == TRUE)
				echo $GLOBALS['egw']->framework->navbar();
		}

		function editForwardingAddress()
		{
			if (!isset($this->bofelamimail)) $this->bofelamimail	= felamimail_bo::getInstance();
			$mailPrefs	= $this->bofelamimail->getMailPreferences();
			$ogServer	= $mailPrefs->getOutgoingServer(0);

			if(!($ogServer instanceof defaultsmtp) || !$ogServer->editForwardingAddress) {
				die('You should not be here!');
			}

			if($_POST['save']) {
				//_debug_array($_POST);_debug_array($_POST);_debug_array($_POST);
				$ogServer->saveSMTPForwarding($GLOBALS['egw_info']['user']['account_id'],$_POST['forwardingAddress'],$_POST['keepLocalCopy']);
			} elseif($_POST['cancel']) {
				ExecMethod('felamimail.uifelamimail.viewMainScreen');
				return;
			}

			$userData = $ogServer->getUserData($GLOBALS['egw_info']['user']['account_id']);

			$this->display_app_header(TRUE);

			$this->t->set_file(array("body" => "edit_forwarding_address.tpl"));
			$this->t->set_block('body','main');

			$this->translate();

			$linkData = array (
				'menuaction'    => 'felamimail.uipreferences.editForwardingAddress'
			);
			$this->t->set_var('form_action',$GLOBALS['egw']->link('/index.php',$linkData));
			$this->t->set_var('forwarding_address',$userData['mailForwardingAddress'][0]);

			#deliveryMode checked_keep_local_copy
			if($userData['deliveryMode'] != 'forwardOnly') {
				$this->t->set_var('checked_keep_local_copy','checked');
			}

			$this->t->parse("out","main");

			print $this->t->get('out','main');
		}

		function editSignature() {
			if(isset($_GET['signatureID'])) {
				$signatureID = (int)$_GET['signatureID'];

				$boSignatures = new felamimail_bosignatures();
				$signatureData = $boSignatures->getSignature($signatureID,true);
			}

			$this->display_app_header(false);

			$this->t->set_file(array('body' => 'preferences_edit_signature.tpl'));
			$this->t->set_block('body','main');

			$this->translate();

			$linkData = array (
				'menuaction'    => 'felamimail.uipreferences.editSignature'
			);
			$this->t->set_var('form_action', $GLOBALS['egw']->link('/index.php',$linkData));
			$height = "350px";
			if(isset($_GET['signatureID'])) {

				$this->t->set_var('description', @htmlspecialchars($signatureData->fm_description, ENT_QUOTES, $this->charset));

				$this->t->set_var('signatureID', $signatureID);

				$this->t->set_var('tinymce',html::fckEditorQuick(
					'signature', 'advanced',
					$signatureData->fm_signature,
					$height,'100%',false)
				);

				$this->t->set_var('checkbox_isDefaultSignature',html::checkbox(
					'isDefaultSignature',
					$signatureData->fm_defaultsignature,
					'true',
					'id="isDefaultSignature"'
					)
				);
			} else {
				$this->t->set_var('description','');
				$this->t->set_var('tinymce',html::fckEditorQuick('signature', 'advanced', '', $height,'100%',false));

				$this->t->set_var('checkbox_isDefaultSignature',html::checkbox(
					'isDefaultSignature', false, 'true', 'id="isDefaultSignature"'
				));

			}

			$this->t->pparse("out","main");
		}

		function editAccountData($msg='', $account2retrieve='active')
		{
			if ($_GET['msg']) $msg = html::purify($_GET['msg']);
			if (!isset($this->bofelamimail)) $this->bofelamimail    = felamimail_bo::getInstance();
			if (!isset($this->bopreferences)) $this->bopreferences	= $this->bofelamimail->bopreferences;
			$preferences =& $this->bopreferences->getPreferences();

			$referer = '../index.php?menuaction=felamimail.uipreferences.listAccountData';
			if(!($preferences->userDefinedAccounts || $preferences->userDefinedIdentities)) {
				die('you are not allowed to be here');
			}

			if($_POST['save'] || $_POST['apply']) {
				// IMAP connection settings
				$icServer =& CreateObject('emailadmin.defaultimap');
				if(is_array($_POST['ic']) && (int)$_POST['active']) {
					foreach($_POST['ic'] as $key => $value) {
						switch($key) {
							case 'validatecert':
								$icServer->$key = ($value != 'dontvalidate');
								break;

							case 'enableSieve':
								$icServer->$key = ($value == 'enableSieve');
								break;

							default:
								$icServer->$key = $value;
								break;
						}
					}
				} else {
					$icServer = NULL;
				}
				// SMTP connection settings
				$ogServer = CreateObject('emailadmin.defaultsmtp');
				if(is_array($_POST['og']) && (int)$_POST['active']) {
					foreach($_POST['og'] as $key => $value) {
						$ogServer->$key = $value;
					}
				} else {
					$ogServer = NULL;
				}

				// identity settings
				$identity = CreateObject('emailadmin.ea_identity');
				if(is_array($_POST['identity'])) {
					foreach($_POST['identity'] as $key => $value) {
						$identity->$key = $value;
					}
				}


				$newID = $this->bopreferences->saveAccountData($icServer, $ogServer, $identity);
				if ($identity->id == 'new') $identity->id = $newID;
				if((int)$_POST['active']) {
					#$boPreferences->saveAccountData($icServer, $ogServer, $identity);
					$this->bopreferences->setProfileActive(false);
					$this->bopreferences->setProfileActive(true,$identity->id);
				} else {
					$this->bopreferences->setProfileActive(false,$identity->id);
				}

				if($_POST['save']) {
					//ExecMethod('felamimail.uifelamimail.viewMainScreen');
					$GLOBALS['egw']->redirect_link($referer,array('msg' => lang('Entry saved')));
					return;
				}
			} elseif($_POST['cancel']) {
				//ExecMethod('felamimail.uifelamimail.viewMainScreen');
				$GLOBALS['egw']->redirect_link($referer,array('msg' => lang('aborted')));
				return;
			}
			$this->display_app_header(TRUE);

			$this->t->set_file(array("body" => "edit_account_data.tpl"));
			$this->t->set_block('body','main');
			if ($msg) $this->t->set_var("message", $msg); else $this->t->set_var("message", '');
			$this->translate();
			// initalize the folderList array
			$folderList = array();

			// if there is no accountID with the call of the edit method, retrieve an active account
			if ((int)$_GET['accountID']) {
				$account2retrieve = $_GET['accountID'];
			}
			if ($_GET['accountID'] == 'new') $account2retrieve = 'new';
			if (!empty($newID) && $newID>0) $account2retrieve = $newID;
			if ($account2retrieve != 'new') {
				$accountData	= $this->bopreferences->getAccountData($preferences, $account2retrieve);
				$icServer =& $accountData['icServer'];
				//_debug_array($icServer);
				$ogServer =& $accountData['ogServer'];
				$identity =& $accountData['identity'];
				//_debug_array($identity);
				if (!isset($this->bofelamimail) || ((int)$_POST['active'] && !empty($icServer->host))) $this->bofelamimail = felamimail_bo::getInstance(false,$icServer->ImapServerId);
				if((int)$_POST['active'] && !empty($icServer->host) && $this->bofelamimail->openConnection(($icServer->ImapServerId?$icServer->ImapServerId:0))) {
					$folderObjects = $this->bofelamimail->getFolderObjects();
					foreach($folderObjects as $folderName => $folderInfo) {
						//_debug_array($folderInfo);
						$folderList[$folderName] = $folderInfo->displayName;
					}
					$this->bofelamimail->closeConnection();
				}
			}
			else
			{
				$this->t->set_var('identity[realName]','');
				$this->t->set_var('identity[organization]','');
				$this->t->set_var('identity[emailAddress]','');
				$this->t->set_var('identity[signature]',-1);
				$this->t->set_var('ic[host]','');
				$this->t->set_var('ic[port]',143);
				$this->t->set_var('ic[username]','');
				$this->t->set_var('ic[password]','');
				$this->t->set_var('ic[sievePort]','');
				$this->t->set_var('og[host]','');
				$this->t->set_var('og[port]',25);
				$this->t->set_var('og[username]','');
				$this->t->set_var('og[password]','');
			}

			if ($icServer) {
				foreach($icServer as $key => $value) {
					if(is_object($value) || is_array($value)) {
						continue;
					}
					switch($key) {
						case 'encryption':
							$this->t->set_var('checked_ic_'. $key .'_'. $value, 'checked');
							break;

						case 'enableSieve':
							$this->t->set_var('checked_ic_'.$key,($value ? 'checked' : ''));
							break;

						case 'validatecert':
							$this->t->set_var('checked_ic_'.$key,($value ? '' : 'checked'));
							break;

						default:
							$this->t->set_var("ic[$key]", $value);
							break;
					}
				}
			}
			if ($ogServer) {
				foreach($ogServer as $key => $value) {
					if(is_object($value) || is_array($value)) {
						continue;
					}
					#print "$key => $value<bR>";
					switch($key) {
						case 'smtpAuth':
							$this->t->set_var('checked_og_'.$key,($value ? 'checked' : ''));
						default:
							$this->t->set_var("og[$key]", $value);
					}
				}
			}
			$felamimail_bosignatures = new felamimail_bosignatures();
			$signatures = $felamimail_bosignatures->getListOfSignatures();
			$allSignatures = array(
				'-2' => lang('no signature')
			);
			$systemsig = false;
			foreach ($signatures as $sigkey => $sig) {
				//echo "Keys to check: $sigkey with ".$sig['fm_signatureid']."<br>";
				if ($sig['fm_signatureid'] == -1) $systemsig = true;
				$allSignatures[$sig['fm_signatureid']] = $sig['fm_description'];
			}
			// if there is a system signature, then use the systemsignature as preset/default
			$sigvalue = $defaultsig = ($systemsig ? -1 : -2);
			if ($identity) {
				foreach($identity as $key => $value) {
					if(is_object($value) || is_array($value)) {
						continue;
					}
					switch($key) {
						case 'signature':
							// if empty, use the default
							$sigvalue = (!empty($value)?$value:$defaultsig);
							break;
						default:
							$this->t->set_var("identity[$key]", $value);
					}
				}
 				$this->t->set_var('accountID',$identity->id);
				$this->t->set_var('checked_active',($accountData['active'] ? ($preferences->userDefinedAccounts ? 'checked' : '') : ''));
			} else {
				if ($signatureData = $felamimail_bosignatures->getDefaultSignature()) {
					if (is_array($signatureData)) {
						$sigvalue = $signatureData['signatureid'];
					} else {
						$sigvalue =$signatureData;
					}
				}
				$this->t->set_var('accountID','new');
			}

			$trashOptions = array_merge(array('' => lang('default').' '.lang("folder settings"), 'none' => lang("Don't use Trash")),($accountData['active'] ? $folderList :array($icServer->trashfolder => $icServer->trashfolder)));
			$sentOptions = array_merge(array('' => lang('default').' '.lang("folder settings"), 'none' => lang("Don't use Sent")),($accountData['active'] ? $folderList :array($icServer->sentfolder => $icServer->sentfolder)));
			$draftOptions = array_merge(array('' => lang('default').' '.lang("folder settings"), 'none' => lang("Don't use draft folder")),($accountData['active'] ? $folderList :array($icServer->draftfolder => $icServer->draftfolder)));
			$templateOptions = array_merge(array('' => lang('default').' '.lang("folder settings"), 'none' => lang("Don't use template folder")),($accountData['active'] ? $folderList :array($icServer->templatefolder => $icServer->templatefolder)));
			$tomerge = ($accountData['active'] ? $folderList :$icServer->folderstoshowinhome);
			$folderList = array_merge( array('' => lang('default').' '.lang("folder settings")),(is_array($tomerge)?$tomerge:array()));

			$this->t->set_var('allowAccounts',($preferences->userDefinedAccounts ? 1 : 0));
			$this->t->set_var('identity_selectbox', html::select('identity[signature]',$sigvalue,$allSignatures, true, " id=\"identity[signature]\" style='width: 250px;'"));
			$this->t->set_var('folder_selectbox', html::select('ic[folderstoshowinhome]',$icServer->folderstoshowinhome,$folderList, true, "id=\"ic[folderstoshowinhome]\" style='width: 250px;'",6));
			$this->t->set_var('trash_selectbox', html::select('ic[trashfolder]',$icServer->trashfolder,$trashOptions, true, "id=\"ic[trashfolder]\" style='width: 250px;'"));
			$this->t->set_var('sent_selectbox', html::select('ic[sentfolder]',$icServer->sentfolder,$sentOptions, true, "id=\"ic[sentfolder]\" style='width: 250px;'"));
			$this->t->set_var('draft_selectbox', html::select('ic[draftfolder]',$icServer->draftfolder,$draftOptions, true, "id=\"ic[draftfolder]\" style='width: 250px;'"));
			$this->t->set_var('template_selectbox', html::select('ic[templatefolder]',$icServer->templatefolder,$templateOptions, true, "id=\"ic[templatefolder]\" style='width: 250px;'"));
			$linkData = array
			(
				'menuaction'    => 'felamimail.uipreferences.editAccountData'
			);
			$this->t->set_var('form_action',$GLOBALS['egw']->link('/index.php',$linkData));

			$this->t->parse("out","main");
			print $this->t->get('out','main');
		}

		function listFolder()
		{
			if (!isset($this->bofelamimail)) $this->bofelamimail    = felamimail_bo::getInstance();
			$this->bofelamimail->openConnection();
			if (!isset($this->bopreferences)) $this->bopreferences  = $this->bofelamimail->bopreferences;
			$preferences =& $this->bopreferences->getPreferences();
			if(!(empty($preferences->preferences['prefpreventmanagefolders']) || $preferences->preferences['prefpreventmanagefolders'] == 0)) {
				die('you are not allowed to be here');
			}
			// rename a mailbox
			if(isset($_POST['newMailboxName']))
			{
				$oldMailboxName = $this->bofelamimail->sessionData['preferences']['mailbox'];
				$newMailboxName = $_POST['newMailboxName'];

				if($position = strrpos($oldMailboxName,'.'))
				{
					$newMailboxName		= substr($oldMailboxName,0,$position+1).$newMailboxName;
				}


				if($this->bofelamimail->imap_renamemailbox($oldMailboxName, $newMailboxName))
				{
					$this->bofelamimail->sessionData['preferences']['mailbox']
						= $newMailboxName;
					$this->bofelamimail->saveSessionData();
				}
			}

			// delete a Folder
			if(isset($_POST['deleteFolder']) && $this->bofelamimail->sessionData['preferences']['mailbox'] != 'INBOX')
			{
				if($this->bofelamimail->imap_deletemailbox($this->bofelamimail->sessionData['preferences']['mailbox']))
				{
					$this->bofelamimail->sessionData['preferences']['mailbox']
						= "INBOX";
					$this->bofelamimail->saveSessionData();
				}
			}

			// create a new Mailbox
			if(isset($_POST['newSubFolder']))
			{
				$oldMailboxName = $this->bofelamimail->sessionData['preferences']['mailbox'].'.';
				$oldMailboxName	= ($oldMailboxName == '--topfolderselected--.') ? '' : $oldMailboxName;
				$newMailboxName = $oldMailboxName.$_POST['newSubFolder'];

				$this->bofelamimail->imap_createmailbox($newMailboxName,True);
			}

			$folderList	= $this->bofelamimail->getFolderObjects();
			// check user input BEGIN
			// the name of the new current folder
			if(get_var('mailboxName',array('POST')) && $folderList[get_var('mailboxName',array('POST'))] ||
			get_var('mailboxName',array('POST')) == '--topfolderselected--')
			{
				$this->bofelamimail->sessionData['preferences']['mailbox']
					= get_var('mailboxName',array('POST'));
				$this->bofelamimail->saveSessionData();
			}

			$this->selectedFolder	= $this->bofelamimail->sessionData['preferences']['mailbox'];

			// (un)subscribe to a folder??
			if(isset($_POST['folderStatus']))
			{
				$this->bofelamimail->subscribe($this->selectedFolder,$_POST['folderStatus']);
			}

			$this->selectedFolder	= $this->bofelamimail->sessionData['preferences']['mailbox'];

			// check user input END

			if($this->selectedFolder != '--topfolderselected--')
			{
				$folderStatus	= $this->bofelamimail->getFolderStatus($this->selectedFolder);
			}
			$mailPrefs	= $this->bofelamimail->getMailPreferences();

			$this->display_app_header(TRUE);

			$this->t->set_file(array("body" => "preferences_manage_folder.tpl"));
			$this->t->set_block('body','main');
			#$this->t->set_block('body','select_row');
			$this->t->set_block('body','folder_settings');
			$this->t->set_block('body','mainFolder_settings');
			#$this->t->set_block('body','folder_acl');

			$this->translate();

			#print "<pre>";print_r($folderList);print "</pre>";
			// set the default values for the sort links (sort by subject)
			$linkData = array
			(
				'menuaction'    => 'felamimail.uipreferences.listFolder'
			);
			$this->t->set_var('form_action',$GLOBALS['egw']->link('/index.php',$linkData));

			$linkData = array
			(
				'menuaction'    => 'felamimail.uipreferences.addACL'
			);
			$this->t->set_var('url_addACL',$GLOBALS['egw']->link('/index.php',$linkData));

			$linkData = array
			(
					'menuaction'    => 'felamimail.uipreferences.listSelectFolder',
			);
			$this->t->set_var('folder_select_url',$GLOBALS['egw']->link('/index.php',$linkData));

			// folder select box
			$icServer = $mailPrefs->getIncomingServer(0);
			$folderTree = $this->uiwidgets->createHTMLFolder
			(
				$folderList,
				$this->selectedFolder,
				0,
				lang('IMAP Server'),
				$icServer->username.'@'.$icServer->host,
				'divFolderTree',
				TRUE
			);
			$this->t->set_var('folder_tree',$folderTree);

			switch($_GET['display'])
			{
				case 'settings':
				default:
					// selected folder data
					if($folderStatus['subscribed'])
					{
						$this->t->set_var('subscribed_checked','checked');
						$this->t->set_var('unsubscribed_checked','');
					}
					else
					{
						$this->t->set_var('subscribed_checked','');
						$this->t->set_var('unsubscribed_checked','checked');
					}

					if(is_array($quota))
					{
						$this->t->set_var('storage_usage',$quota['STORAGE']['usage']);
						$this->t->set_var('storage_limit',$quota['STORAGE']['limit']);
						$this->t->set_var('message_usage',$quota['MESSAGE']['usage']);
						$this->t->set_var('message_limit',$quota['MESSAGE']['limit']);
					}
					else
					{
						$this->t->set_var('storage_usage',lang('unknown'));
						$this->t->set_var('storage_limit',lang('unknown'));
						$this->t->set_var('message_usage',lang('unknown'));
						$this->t->set_var('message_limit',lang('unknown'));
					}

					if($this->selectedFolder != '--topfolderselected--')
					{
						$this->t->parse('settings_view','folder_settings',True);
					}
					else
					{
						$this->t->parse('settings_view','mainFolder_settings',True);
					}

					break;
			}

			$mailBoxTreeName 	= '';
			$mailBoxName		= $this->selectedFolder;
			if($position = strrpos($this->selectedFolder,'.'))
			{
				$mailBoxTreeName 	= substr($this->selectedFolder,0,$position+1);
				$mailBoxName		= substr($this->selectedFolder,$position+1);
			}

			$this->t->set_var('mailboxTreeName',$mailBoxTreeName);
			$this->t->set_var('mailboxNameShort',$mailBoxName);
			$this->t->set_var('mailboxName',$mailBoxName);
			$this->t->set_var('folderName',$this->selectedFolder);
			$this->t->set_var('imap_server',$icServer->host);

			$this->t->pparse("out","main");
			$this->bofelamimail->closeConnection();
		}

		function listSignatures()
		{
			$this->display_app_header(TRUE);

			$this->t->set_file(array("body" => "preferences_list_signatures.tpl"));
			$this->t->set_block('body','main');

			$this->translate();

			#print "<pre>";print_r($folderList);print "</pre>";
			// set the default values for the sort links (sort by subject)
			$linkData = array
			(
				'menuaction'    => 'felamimail.uipreferences.listFolder'
			);
			$this->t->set_var('form_action', $GLOBALS['egw']->link('/index.php',$linkData));

			$linkData = array
			(
				'menuaction'    => 'felamimail.uipreferences.editSignature'
			);
			$this->t->set_var('url_addSignature', $GLOBALS['egw']->link('/index.php',$linkData));

			$this->t->set_var('url_image_add',$GLOBALS['egw']->common->image('phpgwapi','new'));
			$this->t->set_var('url_image_delete',$GLOBALS['egw']->common->image('phpgwapi','delete'));

			$felamimail_bosignatures = new felamimail_bosignatures();
			$signatures = $felamimail_bosignatures->getListOfSignatures();

			$this->t->set_var('table', $this->uiwidgets->createSignatureTable($signatures));

			$this->t->pparse("out","main");
			$this->bofelamimail->closeConnection();
		}

		function listAccountData()
		{
			$this->display_app_header(TRUE);
			if (!isset($this->bopreferences)) $this->bopreferences  = CreateObject('felamimail.bopreferences');
			$preferences =& $this->bopreferences->getPreferences();
			$allAccountData    = $this->bopreferences->getAllAccountData($preferences);
			if ($allAccountData) {
				foreach ($allAccountData as $tmpkey => $accountData)
				{
					$identity =& $accountData['identity'];

					#_debug_array($identity);

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
			}
			$this->t->set_file(array("body" => "preferences_list_accounts.tpl"));
			$this->t->set_block('body','main');

			$this->translate();

			#print "<pre>";print_r($folderList);print "</pre>";
			// set the default values for the sort links (sort by subject)
			#$linkData = array
			#(
			#	'menuaction'    => 'felamimail.uipreferences.listFolder'
			#);
			#$this->t->set_var('form_action', $GLOBALS['egw']->link('/index.php',$linkData));

			$linkData = array
			(
				'menuaction'    => 'felamimail.uipreferences.editAccountData',
				'accountID'		=> 'new'
			);
			$this->t->set_var('url_addAccount', $GLOBALS['egw']->link('/index.php',$linkData));

			$this->t->set_var('url_image_add',$GLOBALS['egw']->common->image('phpgwapi','new'));
			$this->t->set_var('url_image_delete',$GLOBALS['egw']->common->image('phpgwapi','delete'));

			$this->t->set_var('table', $this->uiwidgets->createAccountDataTable($accountArray));

			$this->t->pparse("out","main");
			$this->bofelamimail->closeConnection();
		}

		function listSelectFolder()
		{
			$this->display_app_header(False);
			if (!isset($this->bofelamimail)) $this->bofelamimail    = felamimail_bo::getInstance();
			if (!isset($this->uiwidgets)) $this->uiwidgets          = CreateObject('felamimail.uiwidgets');
			$this->bofelamimail->openConnection();
			$mailPrefs  = $this->bofelamimail->getMailPreferences();
			$icServer = $mailPrefs->getIncomingServer(0);
			$folderObjects = $this->bofelamimail->getFolderObjects(false);
			$folderTree = $this->uiwidgets->createHTMLFolder
				(
					$folderObjects,
					'INBOX',
					0,
					lang('IMAP Server'),
					$icServer->username.'@'.$icServer->host,
					'divFolderTree',
					false,
					true
				);
			print '<script type="text/javascript">function onNodeSelect(_folderName){opener.document.getElementById("newMailboxMoveName").value = _folderName + (opener.document.getElementById("newMailboxName").value?"' . $this->bofelamimail->getHierarchyDelimiter() . '":"") + opener.document.getElementById("newMailboxName").value;self.close();}</script>';
			print '<div id="divFolderTree" style="overflow:auto; width:375px; height:474px; margin-bottom: 0px;padding-left: 0px; padding-top:0px; z-index:100; border : 1px solid Silver;"></div>';
			print $folderTree;
		}


		function translate()
		{
			$this->t->set_var('lang_signature',lang('Signatur'));
			$this->t->set_var("lang_folder_name",lang('folder name'));
			$this->t->set_var("lang_folder_list",lang('folderlist'));
			$this->t->set_var("lang_select",lang('select'));
			$this->t->set_var("lang_folder_status",lang('folder status'));
			$this->t->set_var("lang_subscribed",lang('subscribed'));
			$this->t->set_var("lang_unsubscribed",lang('unsubscribed'));
			$this->t->set_var("lang_subscribe",lang('subscribe'));
			$this->t->set_var("lang_unsubscribe",lang('unsubscribe'));
			$this->t->set_var("lang_update",lang('update'));
			$this->t->set_var("lang_rename_folder",lang('rename folder'));
			$this->t->set_var("lang_create_subfolder",lang('create subfolder'));
			$this->t->set_var("lang_delete_folder",lang('delete folder'));
			$this->t->set_var("lang_confirm_delete",addslashes(lang("Do you really want to delete the '%1' folder?",$this->bofelamimail->sessionData['preferences']['mailbox'])));
			$this->t->set_var("lang_really_delete_accountsettings",lang("Do you really want to delete the selected Accountsettings and the assosiated Identity."));
			$this->t->set_var("lang_delete",lang('delete'));
			$this->t->set_var("lang_imap_server",lang('IMAP Server'));
			$this->t->set_var("lang_folder_settings",lang('folder settings'));
			$this->t->set_var("lang_folder_acl",lang('folder acl'));
			$this->t->set_var("lang_anyone",lang('anyone'));
			$this->t->set_var("lang_reading",lang('reading'));
			$this->t->set_var("lang_writing",lang('writing'));
			$this->t->set_var("lang_posting",lang('posting'));
			$this->t->set_var("lang_none",lang('none'));
			$this->t->set_var("lang_rename",lang('rename'));
			$this->t->set_var("lang_move",lang('move'));
			$this->t->set_var("lang_move_folder",lang('move folder'));
			$this->t->set_var("lang_create",lang('create'));
			$this->t->set_var('lang_open_all',lang("open all"));
			$this->t->set_var('lang_close_all',lang("close all"));
			$this->t->set_var('lang_add',lang("add"));
			$this->t->set_var('lang_delete_selected',lang("delete selected"));
			$this->t->set_var('lang_cancel',lang("close"));
			$this->t->set_var('lang_ACL',lang('ACL'));
			$this->t->set_var('lang_save',lang('save'));
			$this->t->set_var('lang_cancel',lang('cancel'));
			$this->t->set_var('lang_setrecursively',lANG('apply recursively?'));
			$this->t->set_var('lang_Overview',lang('Overview'));
			$this->t->set_var('lang_edit_forwarding_address',lang('edit email forwarding address'));
			$this->t->set_var('lang_forwarding_address',lang('email forwarding address'));
			$this->t->set_var('lang_keep_local_copy',lang('keep local copy of email'));
			$this->t->set_var('hostname_address',lang('hostname / address'));
			$this->t->set_var('lang_username',lang('username'));
			$this->t->set_var('lang_password',lang('password'));
			$this->t->set_var('lang_port',lang('port'));
			$this->t->set_var('lang_apply',lang('apply'));
			$this->t->set_var('lang_use_costum_settings',lang('use custom settings'));
			$this->t->set_var('lang_use_custom_ids',lang('use custom identities'));
			$this->t->set_var('lang_identity',lang('identity'));
			$this->t->set_var('lang_name',lang('name'));
			$this->t->set_var('lang_organization',lang('organization'));
			$this->t->set_var('lang_emailaddress',lang('emailaddress'));
			$this->t->set_var('lang_encrypted_connection',lang('encrypted connection'));
			$this->t->set_var('lang_do_not_validate_certificate',lang('do not validate certificate'));
			$this->t->set_var("lang_incoming_server",lang('incoming mail server(IMAP)'));
			$this->t->set_var("lang_outgoing_server",lang('outgoing mail server(SMTP)'));
			$this->t->set_var("auth_required",lang('authentication required'));
			$this->t->set_var('lang_add_acl',lang('add acl'));
			$this->t->set_var('lang_foldername',lang('foldername'));
			$this->t->set_var('lang_description',lang('description'));
			$this->t->set_var('lang_really_delete_signatures',lang('Do you really want to delete the selected signatures?'));
			$this->t->set_var('lang_no_encryption',lang('no encryption'));
			$this->t->set_var('lang_default_signature',lang('default signature'));
			$this->t->set_var('lang_server_supports_sieve',lang('server supports mailfilter(sieve)'));
			$this->t->set_var('lang_sent_folder', lang('sent folder'));
			$this->t->set_var('lang_trash_folder', lang('trash folder'));
			$this->t->set_var('lang_draft_folder', lang('draft folder'));
			$this->t->set_var('lang_template_folder', lang('template folder'));
			$this->t->set_var('lang_folder_to_appear_on_main_screen', lang('if shown, which folders should appear on main screen'));
			$this->t->set_var('lang_confirm_delete_folder', lang('Delete this folder irreversible? '));
			$this->t->set_var("th_bg",$GLOBALS['egw_info']["theme"]["th_bg"]);
			$this->t->set_var("bg01",$GLOBALS['egw_info']["theme"]["bg01"]);
			$this->t->set_var("bg02",$GLOBALS['egw_info']["theme"]["bg02"]);
			$this->t->set_var("bg03",$GLOBALS['egw_info']["theme"]["bg03"]);
		}
	}

