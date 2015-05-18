<?php
/**
 * EGroupware - Mail - interface class for compose mails in popup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013-2014 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Mail interface class for compose mails in popup
 */
class mail_compose
{
	var $public_functions = array
	(
		'compose'		=> True,
		'getAttachment'		=> True,
	);

	/**
	 * class vars for destination, priorities, mimeTypes
	 */
	static $destinations = array(
		'to' 		=> 'to',  // lang('to')
		'cc'		=> 'cc',  // lang('cc')
		'bcc'		=> 'bcc', // lang('bcc')
		'replyto'	=> 'replyto', // lang('replyto')
		'folder'	=> 'folder'  // lang('folder')
	);
	static $priorities = array(
		1=>"high", // lang('high')
		3=>"normal", // lang('normal')
		5=>"low"  // lang('low')
	);
	static $mimeTypes = array(
		"plain"=>"plain",
		"html"=>"html"
	);

	/**
	 * Instance of mail_bo
	 *
	 * @var mail_bo
	 */
	var $mail_bo;

	/**
	 * Active preferences, reference to $this->mail_bo->mailPreferences
	 *
	 * @var array
	 */
	var $mailPreferences;
	var $attachments;	// Array of attachments
	var $displayCharset;
	var $composeID;
	var $sessionData;

	function __construct()
	{
		$this->displayCharset   = translation::charset();

		$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		$this->mail_bo	= mail_bo::getInstance(true,$profileID);
		$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $this->mail_bo->profileID;

		$this->mailPreferences	=& $this->mail_bo->mailPreferences;
		//force the default for the forwarding -> asmail
		if (!is_array($this->mailPreferences) || empty($this->mailPreferences['message_forwarding']))
		{
			$this->mailPreferences['message_forwarding'] = 'asmail';
		}
		if (is_null(mail_bo::$mailConfig)) mail_bo::$mailConfig = config::read('mail');

		$this->mailPreferences  =& $this->mail_bo->mailPreferences;
	}

	/**
	 * changeProfile
	 *
	 * @param int $_icServerID
	 */
	function changeProfile($_icServerID)
	{
		if ($this->mail_bo->profileID!=$_icServerID)
		{
			if (mail_bo::$debug) error_log(__METHOD__.__LINE__.'->'.$this->mail_bo->profileID.'<->'.$_icServerID);
			$this->mail_bo = mail_bo::getInstance(false,$_icServerID);
			if (mail_bo::$debug) error_log(__METHOD__.__LINE__.' Fetched IC Server:'.$this->mail_bo->profileID.':'.function_backtrace());
			// no icServer Object: something failed big time
			if (!isset($this->mail_bo->icServer)) exit; // ToDo: Exception or the dialog for setting up a server config
			$this->mail_bo->openConnection($this->mail_bo->profileID);
			$this->mailPreferences  =& $this->mail_bo->mailPreferences;
		}
	}

	/**
	 * Provide toolbar actions used for compose toolbar
	 * @param array $content content of compose temp
	 *
	 * @return array an array of actions
	 */
	function getToolbarActions($content)
	{
		$group = 0;
		$actions = array(
			'send' => array(
				'caption' => 'Send',
				'icon'	=> 'mail_send',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose_submitAction',
				'hint' => 'Send',
				'toolbarDefault' => true
			),
			'button[saveAsDraft]' => array(
				'caption' => 'Save',
				'icon' => 'save',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.saveAsDraft',
				'hint' => 'Save as Draft',
				'toolbarDefault' => true
			),
			'button[saveAsDraftAndPrint]' => array(
				'caption' => 'Print',
				'icon' => 'print',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.saveAsDraft',
				'hint' => 'Save as Draft and Print'
			),
			'selectFromVFSForCompose' => array(
				'caption' => 'VFS',
				'icon' => 'filemanager',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose_triggerWidget',
				'hint' => 'Select file(s) from VFS',
				'toolbarDefault' => true
			),
			'uploadForCompose' => array(
				'caption' => 'Upload files...',
				'icon' => 'attach',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose_triggerWidget',
				'hint' => 'Select files to upload',
				'toolbarDefault' => true
			),
			'to_infolog' => array(
				'caption' => 'Infolog',
				'icon' => 'to_infolog',
				'group' => ++$group,
				'checkbox' => true,
				'hint' => 'check to save as infolog on send',
				'toolbarDefault' => true,
				'onExecute' => 'javaScript:app.mail.compose_setToggle'
			),
			'to_tracker' => array(
				'caption' => 'Tracker',
				'icon' => 'to_tracker',
				'group' => $group,
				'checkbox' => true,
				'hint' => 'check to save as trackerentry on send',
				'onExecute' => 'javaScript:app.mail.compose_setToggle'
			),
			'to_calendar' => array(
				'caption' => 'Calendar',
				'icon' => 'to_calendar',
				'group' => $group,
				'checkbox' => true,
				'hint' => 'check to save as calendar event on send',
				'onExecute' => 'javaScript:app.mail.compose_setToggle'
			),
			'disposition' => array(
				'caption' => 'Notification',
				'icon' => 'high',
				'group' => ++$group,
				'checkbox' => true,
				'hint' => 'check to recieve a notification when the message is read (note: not all clients support this and/or the reciever may not authorize the notification)',
				'toolbarDefault' => true,
				'onExecute' => 'javaScript:app.mail.compose_setToggle'
			),
			'prty' => array(
				'caption' => 'Priority',
				'group' => ++$group,
				'icon' => 'priority',
				'children' => array(),
				'toolbarDefault' => true,
				'hint' => 'Select the message priority tag',

			),
			'save2vfs' => array (
				'caption' => 'Save to VFS',
				'icon' => 'filesave',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose_saveDraft2fm',
				'hint' => 'Save the drafted message as eml file into VFS'
			)
		);
		foreach (self::$priorities as $key => $priority)
		{
			$actions['prty']['children'][$key] = array(
						'caption' => $priority,
						'icon' => 'prio_high',
						'default' => false,
						'onExecute' => 'javaScript:app.mail.compose_priorityChange'
			);
			switch ($priority)
			{
				case 'high':
					$actions['prty']['children'][$key]['icon'] = 'prio_high';
					break;
				case 'normal':
					$actions['prty']['children'][$key]['icon'] = 'prio_normal';
					break;
				case 'low':
					$actions['prty']['children'][$key]['icon'] = 'prio_low';
			}
		}
		// Set the priority action its current state
		if ($content['priority'])
		{
			$actions['prty']['children'][$content['priority']]['default'] = true;
		}

		return $actions;
	}

	/**
	 * Compose dialog
	 *
	 * @var arra $_content =null etemplate content array
	 * @var string $msg =null a possible message to be passed and displayed to the userinterface
	 * @var string $_focusElement ='to' subject, to, body supported
	 * @var boolean $suppressSigOnTop =false
	 * @var boolean $isReply =false
	 */
	function compose(array $_content=null,$msg=null, $_focusElement='to',$suppressSigOnTop=false, $isReply=false)
	{
		if ($msg) egw_framework::message($msg);

		if (!empty($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed']))
		{
			$sigPref = $GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'];
		}
		else
		{
			$sigPref = array();
		}
		// split mailaccount (acc_id) and identity (ident_id)
		if ($_content && isset($_content['mailaccount']))
		{
			list($_content['mailaccount'], $_content['mailidentity']) = explode(':', $_content['mailaccount']);
		}
		//error_log(__METHOD__.__LINE__.array2string($sigPref));
		//lang('compose'),lang('from') // needed to be found by translationtools
		//error_log(__METHOD__.__LINE__.array2string($_REQUEST).function_backtrace());
		//error_log(__METHOD__.__LINE__.array2string($_content).function_backtrace());
		$_contentHasSigID = array_key_exists('mailidentity',(array)$_content);
		$_contentHasMimeType = array_key_exists('mimeType',(array)$_content);
		if (isset($_GET['reply_id'])) $replyID = $_GET['reply_id'];
		if (!$replyID && isset($_GET['id'])) $replyID = $_GET['id'];

		// Process different places we can use as a start for composing an email
		$actionToProcess = 'compose';
		if($_GET['from'] && $replyID)
		{
			$_content = array_merge((array)$_content, $this->getComposeFrom(
				// Parameters needed for fetching appropriate data
				$replyID, $_GET['part_id'], $_GET['from'],
				// Additionally may be changed
				$_focusElement, $suppressSigOnTop, $isReply
			));
			$actionToProcess = $_GET['from'];
			unset($_GET['from']);
			unset($_GET['reply_id']);
			unset($_GET['part_id']);
			unset($_GET['id']);
			unset($_GET['mode']);
			//error_log(__METHOD__.__LINE__.array2string($_content));
		}

		$composeCache = array();
		if (isset($_content['composeID'])&&!empty($_content['composeID']))
		{
			$isFirstLoad = false;
			$composeCache = egw_cache::getCache(egw_cache::SESSION,'mail','composeCache'.trim($GLOBALS['egw_info']['user']['account_id']).'_'.$_content['composeID'],$callback=null,$callback_params=array(),$expiration=60*60*2);
			$this->composeID = $_content['composeID'];
			//error_log(__METHOD__.__LINE__.array2string($composeCache));
		}
		else
		{
			// as we use isFirstLoad to trigger the initalStyle on ckEditor, we
			// respect that composeasnew may not want that, as we assume there
			// is some style already set and our initalStyle always adds a span with &nbsp;
			// and we want to avoid that
			$isFirstLoad = !($actionToProcess=='composeasnew');//true;
			$this->composeID = $_content['composeID'] = $this->generateComposeID();
			if (!is_array($_content))
			{
				$_content = $this->setDefaults();
			}
			else
			{
				$_content = $this->setDefaults($_content);
			}
		}
		// VFS Selector was used
		if (is_array($_content['selectFromVFSForCompose']))
		{
			$suppressSigOnTop = true;
			foreach ($_content['selectFromVFSForCompose'] as $i => $path)
			{
				$_content['uploadForCompose'][] = array(
					'name' => egw_vfs::basename($path),
					'type' => egw_vfs::mime_content_type($path),
					'file' => egw_vfs::PREFIX.$path,
					'size' => filesize(egw_vfs::PREFIX.$path),
				);
			}
			unset($_content['selectFromVFSForCompose']);
		}
		// check everything that was uploaded
		if (is_array($_content['uploadForCompose']))
		{
			$suppressSigOnTop = true;
			foreach ($_content['uploadForCompose'] as $i => &$upload)
			{
				if (!isset($upload['file'])) $upload['file'] = $upload['tmp_name'];
				try
				{
					$upload['file'] = $upload['tmp_name'] = mail_bo::checkFileBasics($upload,$this->composeID,false);
				}
				catch (egw_exception_wrong_userinput $e)
				{
					egw_framework::message($e->getMessage(), 'error');
					unset($_content['uploadForCompose'][$i]);
					continue;
				}
				if (is_dir($upload['file']) && (!$_content['filemode'] || $_content['filemode'] == egw_sharing::ATTACH))
				{
					$_content['filemode'] = egw_sharing::READONLY;
					egw_framework::message(lang('Directories have to be shared.'), 'info');
				}
			}
		}
		// check if someone did hit delete on the attachments list
		if (!empty($_content['attachments']['delete']))
		{
			//error_log(__METHOD__.__LINE__.':'.array2string($_content['attachments']));
			//error_log(__METHOD__.__LINE__.':'.array2string($_content['attachments']['delete']));

			$suppressSigOnTop = true;
			$toDelete = $_content['attachments']['delete'];
			unset($_content['attachments']['delete']);
			$attachments = $_content['attachments'];
			unset($_content['attachments']);
			foreach($attachments as $i => $att)
			{
				$remove=false;
				foreach(array_keys($toDelete) as $k)
				{
					if ($att['tmp_name']==$k) $remove=true;
				}
				if (!$remove) $_content['attachments'][] = $att;
			}
		}
		// someone clicked something like send, or saveAsDraft
		// make sure, we are connected to the correct server for sending and storing the send message
		$activeProfile = $composeProfile = $this->mail_bo->profileID; // active profile may not be the profile uised in/for compose
		$activeFolderCache = egw_cache::getCache(egw_cache::INSTANCE,'email','activeMailbox'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*10);
		if (!empty($activeFolderCache[$this->mail_bo->profileID]))
		{
			//error_log(__METHOD__.__LINE__.' CurrentFolder:'.$activeFolderCache[$this->mail_bo->profileID]);
			$activeFolder = $activeFolderCache[$this->mail_bo->profileID];
		}
		//error_log(__METHOD__.__LINE__.array2string($_content));
		if (!empty($_content['serverID']) && $_content['serverID'] != $this->mail_bo->profileID &&
			($_content['composeToolbar'] === 'send' || $_content['button']['saveAsDraft']||$_content['button']['saveAsDraftAndPrint'])
		)
		{
			$this->changeProfile($_content['serverID']);
			$composeProfile = $this->mail_bo->profileID;
		}
		// make sure $acc is set/initalized properly with the current composeProfile, as $acc is used down there
		// at several locations and not neccesaryly initialized before
		$acc = emailadmin_account::read($composeProfile);
		$buttonClicked = false;
		if ($_content['composeToolbar'] === 'send')
		{
			$buttonClicked = $suppressSigOnTop = true;
			$sendOK = true;
			$_content['body'] = ($_content['body'] ? $_content['body'] : $_content['mail_'.($_content['mimeType'] == 'html'?'html':'plain').'text']);
			/*
			perform some simple checks, before trying to send on:
			$_content['to'];$_content['cc'];$_content['bcc'];
			trim($_content['subject']);
			trim(strip_tags(str_replace('&nbsp;','',$_content['body'])));
			*/
			if (strlen(trim(strip_tags(str_replace('&nbsp;','',$_content['body']))))==0 && count($_content['attachments'])==0)
			{
				$sendOK = false;
				$_content['msg'] = $message = lang("no message body supplied");
			}
			if ($sendOK && strlen(trim($_content['subject']))==0)
			{
				$sendOK = false;
				$_content['msg'] = $message = lang("no subject supplied");
			}
			if ($sendOK && empty($_content['to']) && empty($_content['cc']) && empty($_content['bcc']))
			{
				$sendOK = false;
				$_content['msg'] = $message = lang("no adress, to send this mail to, supplied");
			}
			if ($sendOK)
			{
				try
				{
					$success = $this->send($_content);
					if ($success==false)
					{
						$sendOK=false;
						$message = $this->errorInfo;
					}
					if (!empty($_content['mailidentity']) && $_content['mailidentity'] != $sigPref[$this->mail_bo->profileID])
					{
						$sigPref[$this->mail_bo->profileID]=$_content['mailidentity'];
						$GLOBALS['egw']->preferences->add('mail','LastSignatureIDUsed',$sigPref,'user');
						// save prefs
						$GLOBALS['egw']->preferences->save_repository(true);
					}
				}
				catch (egw_exception_wrong_userinput $e)
				{
					$sendOK = false;
					$message = $e->getMessage();
				}
			}
			if ($activeProfile != $composeProfile)
			{
				$this->changeProfile($activeProfile);
				$activeProfile = $this->mail_bo->profileID;
			}
			if ($sendOK)
			{
				$workingFolder = $activeFolder;
				$mode = 'compose';
				$idsForRefresh = array();
				if (isset($_content['mode']) && !empty($_content['mode']))
				{
					$mode = $_content['mode'];
					if ($_content['mode']=='forward' && !empty($_content['processedmail_id']))
					{
						$_content['processedmail_id'] = explode(',',$_content['processedmail_id']);
						foreach ($_content['processedmail_id'] as $k =>$rowid)
						{
							$fhA = mail_ui::splitRowID($rowid);
							//$this->sessionData['uid'][] = $fhA['msgUID'];
							//$this->sessionData['forwardedUID'][] = $fhA['msgUID'];
							$idsForRefresh[] = mail_ui::generateRowID($fhA['profileID'], $fhA['folder'], $fhA['msgUID'], $_prependApp=false);
							if (!empty($fhA['folder'])) $workingFolder = $fhA['folder'];
						}
					}
					if ($_content['mode']=='reply' && !empty($_content['processedmail_id']))
					{
						$rhA = mail_ui::splitRowID($_content['processedmail_id']);
						//$this->sessionData['uid'] = $rhA['msgUID'];
						$idsForRefresh[] = mail_ui::generateRowID($rhA['profileID'], $rhA['folder'], $rhA['msgUID'], $_prependApp=false);
						$workingFolder = $rhA['folder'];
					}
				}
				//the line/condition below should not be needed
				if (empty($idsForRefresh) && !empty($_content['processedmail_id']))
				{
					$rhA = mail_ui::splitRowID($_content['processedmail_id']);
					$idsForRefresh[] = mail_ui::generateRowID($rhA['profileID'], $rhA['folder'], $rhA['msgUID'], $_prependApp=false);
				}
				$response = egw_json_response::get();
				if ($activeProfile != $composeProfile)
				{
					// we need a message only, when account ids (composeProfile vs. activeProfile) differ
					$response->call('opener.egw_message',lang('Message send successfully.'));
				}
				elseif ($activeProfile == $composeProfile && ($workingFolder==$activeFolder && $mode != 'compose') || ($this->mail_bo->isSentFolder($workingFolder)||$this->mail_bo->isDraftFolder($workingFolder)))
				{
					if ($this->mail_bo->isSentFolder($workingFolder)||$this->mail_bo->isDraftFolder($workingFolder))
					{
						// we may need a refresh when on sent folder or in drafts, as drafted messages will/should be deleted after succeeded send action
						$response->call('opener.egw_refresh',lang('Message send successfully.'),'mail');
					}
					else
					{
						//error_log(__METHOD__.__LINE__.array2string($idsForRefresh));
						$response->call('opener.egw_refresh',lang('Message send successfully.'),'mail',$idsForRefresh,'update');
					}
				}
				else
				{
					$response->call('opener.egw_message',lang('Message send successfully.'));
				}
				//egw_framework::refresh_opener(lang('Message send successfully.'),'mail');
				egw_framework::window_close();
			}
			if ($sendOK == false)
			{
				$response = egw_json_response::get();
				egw_framework::message(lang('Message send failed: %1',$message),'error');// maybe error is more appropriate
				$response->call('app.mail.clearIntevals');
			}
		}

		if ($activeProfile != $composeProfile) $this->changeProfile($activeProfile);
		$insertSigOnTop = false;
		$content = (is_array($_content)?$_content:array());
		if ($_contentHasMimeType)
		{
			// mimeType is now a checkbox; convert it here to match expectations
			// ToDo: match Code to meet checkbox value
			if ($content['mimeType']==1)
			{
				$_content['mimeType'] = $content['mimeType']='html';
			}
			else
			{
				$_content['mimeType'] = $content['mimeType']='plain';
			}

		}
		// user might have switched desired mimetype, so we should convert
		if ($content['is_html'] && $content['mimeType']=='plain')
		{
			//error_log(__METHOD__.__LINE__.$content['mail_htmltext']);
			$suppressSigOnTop = true;
			if (stripos($content['mail_htmltext'],'<pre>')!==false)
			{
				$contentArr = html::splithtmlByPRE($content['mail_htmltext']);
				if (is_array($contentArr))
				{
					foreach ($contentArr as $k =>&$elem)
					{
						if (stripos($elem,'<pre>')!==false) $elem = str_replace(array("\r\n","\n","\r"),array("<br>","<br>","<br>"),$elem);
					}
					$content['mail_htmltext'] = implode('',$contentArr);
				}
			}
			$content['mail_htmltext'] = $this->_getCleanHTML($content['mail_htmltext'], false, false);
			$content['mail_htmltext'] = translation::convertHTMLToText($content['mail_htmltext'],$charset=false,false,true);

			$content['body'] = $content['mail_htmltext'];
			unset($content['mail_htmltext']);
			$content['is_html'] = false;
			$content['is_plain'] = true;
		}
		if ($content['is_plain'] && $content['mimeType']=='html')
		{
			// the possible font span should only be applied on first load or on switch plain->html
			$isFirstLoad = "switchedplaintohtml";
			//error_log(__METHOD__.__LINE__.$content['mail_plaintext']);
			$suppressSigOnTop = true;
			$content['mail_plaintext'] = str_replace(array("\r\n","\n","\r"),array("<br>","<br>","<br>"),$content['mail_plaintext']);
			//$this->replaceEmailAdresses($content['mail_plaintext']);
			$content['body'] = $content['mail_plaintext'];
			unset($content['mail_plaintext']);
			$content['is_html'] = true;
			$content['is_plain'] = false;
		}

		$content['body'] = ($content['body'] ? $content['body'] : $content['mail_'.($content['mimeType'] == 'html'?'html':'plain').'text']);
		unset($_content['body']);
		unset($_content['mail_htmltext']);
		unset($_content['mail_plaintext']);

		// form was submitted either by clicking a button or by changing one of the triggering selectboxes
		// identity and signatureid; this might trigger that the signature in mail body may have to be altered
		if ( !empty($content['body']) &&
			(!empty($composeCache['mailaccount']) && !empty($_content['mailaccount']) && $_content['mailaccount'] != $composeCache['mailaccount']) ||
			(!empty($composeCache['mailidentity']) && !empty($_content['mailidentity']) && $_content['mailidentity'] != $composeCache['mailidentity'])
		)
		{
			$buttonClicked = true;
			$suppressSigOnTop = true;
			if (!empty($composeCache['mailaccount']) && !empty($_content['mailaccount']) && $_content['mailaccount'] != $composeCache['mailaccount'])
			{
				$acc = emailadmin_account::read($_content['mailaccount']);
				//error_log(__METHOD__.__LINE__.array2string($acc));
				$Identities = emailadmin_account::read_identity($acc['ident_id'],true);
				//error_log(__METHOD__.__LINE__.array2string($Identities));
				if ($Identities['ident_id'])
				{
					$newSig = $Identities['ident_id'];
				}
				else
				{
					$newSig = $this->mail_bo->getDefaultIdentity();
					if ($newSig === false) $newSig = -2;
				}
			}
			$_oldSig = $composeCache['mailidentity'];
			$_signatureid = ($newSig?$newSig:$_content['mailidentity']);
			$_currentMode = $_content['mimeType'];
			if ($_oldSig != $_signatureid)
			{
				if($this->_debug) error_log(__METHOD__.__LINE__.' old,new ->'.$_oldSig.','.$_signatureid.'#'.$content['body']);
				// prepare signatures, the selected sig may be used on top of the body
				try
				{
					$oldSignature = emailadmin_account::read_identity($_oldSig,true);
					//error_log(__METHOD__.__LINE__.'Old:'.array2string($oldSignature).'#');
					$oldSigText = $oldSignature['ident_signature'];
				}
				catch (Exception $e)
				{
					$oldSignature=array();
					$oldSigText = null;
				}
				try
				{
					$signature = emailadmin_account::read_identity($_signatureid,true);
					//error_log(__METHOD__.__LINE__.'New:'.array2string($signature).'#');
					$sigText = $signature['ident_signature'];
				}
				catch (Exception $e)
				{
					$signature=array();
					$sigText = null;
				}
				//error_log(__METHOD__.'Old:'.$oldSigText.'#');
				//error_log(__METHOD__.'New:'.$sigText.'#');
				if ($_currentMode == 'plain')
				{
					$oldSigText = $this->convertHTMLToText($oldSigText,true,true);
					$sigText = $this->convertHTMLToText($sigText,true,true);
					if($this->_debug) error_log(__METHOD__." Old signature:".$oldSigText);
				}

				//$oldSigText = mail_bo::merge($oldSigText,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
				//error_log(__METHOD__.'Old+:'.$oldSigText.'#');
				//$sigText = mail_bo::merge($sigText,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
				//error_log(__METHOD__.'new+:'.$sigText.'#');
				$_htmlConfig = mail_bo::$htmLawed_config;
				mail_bo::$htmLawed_config['comment'] = 2;
				mail_bo::$htmLawed_config['transform_anchor'] = false;
				$oldSigTextCleaned = str_replace(array("\r","\t","<br />\n",": "),array("","","<br />",":"),($_currentMode == 'html'?html::purify($oldSigText,null,array(),true):$oldSigText));
				//error_log(__METHOD__.'Old(clean):'.$oldSigTextCleaned.'#');
				if ($_currentMode == 'html')
				{
					$content['body'] = str_replace("\n",'\n',$content['body']);	// dont know why, but \n screws up preg_replace
					$styles = mail_bo::getStyles(array(array('body'=>$content['body'])));
					if (stripos($content['body'],'style')!==false) translation::replaceTagsCompletley($content['body'],'style',$endtag='',true); // clean out empty or pagewide style definitions / left over tags
				}
				$content['body'] = str_replace(array("\r","\t","<br />\n",": "),array("","","<br />",":"),($_currentMode == 'html'?html::purify($content['body'],mail_bo::$htmLawed_config,array(),true):$content['body']));
				mail_bo::$htmLawed_config = $_htmlConfig;
				if ($_currentMode == 'html')
				{
					$replaced = null;
					$content['body'] = preg_replace($reg='|'.preg_quote('<!-- HTMLSIGBEGIN -->','|').'.*'.preg_quote('<!-- HTMLSIGEND -->','|').'|u',
						$rep='<!-- HTMLSIGBEGIN -->'.$sigText.'<!-- HTMLSIGEND -->', $in=$content['body'], -1, $replaced);
					$content['body'] = str_replace(array('\n',"\xe2\x80\x93","\xe2\x80\x94","\xe2\x82\xac"),array("\n",'&ndash;','&mdash;','&euro;'),$content['body']);
					//error_log(__METHOD__."() preg_replace('$reg', '$rep', '$in', -1)='".$content['body']."', replaced=$replaced");
					unset($rep, $in);
					if ($replaced)
					{
						$content['mailidentity'] = $_content['mailidentity'] = $presetSig = $_signatureid;
						$found = false; // this way we skip further replacement efforts
					}
					else
					{
						// try the old way
						$found = (strlen(trim($oldSigTextCleaned))>0?strpos($content['body'],trim($oldSigTextCleaned)):false);
					}
				}
				else
				{
					$found = (strlen(trim($oldSigTextCleaned))>0?strpos($content['body'],trim($oldSigTextCleaned)):false);
				}

				if ($found !== false && $_oldSig != -2 && !(empty($oldSigTextCleaned) || trim($this->convertHTMLToText($oldSigTextCleaned,true,true)) ==''))
				{
					//error_log(__METHOD__.'Old Content:'.$content['body'].'#');
					$_oldSigText = preg_quote($oldSigTextCleaned,'~');
					//error_log(__METHOD__.'Old(masked):'.$_oldSigText.'#');
					$content['body'] = preg_replace('~'.$_oldSigText.'~mi',$sigText,$content['body'],1);
					//error_log(__METHOD__.'new Content:'.$content['body'].'#');
				}

				if ($_oldSig == -2 && (empty($oldSigTextCleaned) || trim($this->convertHTMLToText($oldSigTextCleaned,true,true)) ==''))
				{
					// if there is no sig selected, there is no way to replace a signature
				}

				if ($found === false)
				{
					if($this->_debug) error_log(__METHOD__." Old Signature failed to match:".$oldSigTextCleaned);
					if($this->_debug) error_log(__METHOD__." Compare content:".$content['body']);
				}
				else
				{
					$content['mailidentity'] = $_content['mailidentity'] = $presetSig = $_signatureid;
				}
				if ($styles)
				{
					//error_log($styles);
					$content['body'] = $styles.$content['body'];
				}
			}
		}

		// do not double insert a signature on a server roundtrip
		if ($buttonClicked) $suppressSigOnTop = true;
		if ($isFirstLoad)
		{
			$alwaysAttachVCardAtCompose = false; // we use this to eliminate double attachments, if users VCard is already present/attached
			if ( isset($GLOBALS['egw_info']['apps']['stylite']) && (isset($this->mailPreferences['attachVCardAtCompose']) &&
				$this->mailPreferences['attachVCardAtCompose']))
			{
				$alwaysAttachVCardAtCompose = true;
				if (!is_array($_REQUEST['preset']['file']) && !empty($_REQUEST['preset']['file']))
				{
					$f = $_REQUEST['preset']['file'];
					$_REQUEST['preset']['file'] = array($f);
				}
				$_REQUEST['preset']['file'][] = "vfs://default/apps/addressbook/".$GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')."/.entry";
			}
			// an app passed the request for fetching and mailing an entry
			if (isset($_REQUEST['app']) && isset($_REQUEST['method']) && isset($_REQUEST['id']))
			{
				$app = $_REQUEST['app'];
				$mt = $_REQUEST['method'];
				$id = $_REQUEST['id'];
				// passed method MUST be registered
				$method = egw_link::get_registry($app,$mt);
				//error_log(__METHOD__.__LINE__.array2string($method));
				if ($method)
				{
					$res = ExecMethod($method,array($id,'html'));
					//_debug_array($res);
					if (!empty($res))
					{
						$insertSigOnTop = 'below';
						if (isset($res['attachments']) && is_array($res['attachments']))
						{
							foreach($res['attachments'] as $f)
							{
								$_REQUEST['preset']['file'][] = $f;
							}
						}
						$content['subject'] = lang($app).' #'.$res['id'].': ';
						foreach(array('subject','body','mimetype') as $name) {
							$sName = $name;
							if ($name=='mimetype')
							{
								$sName = 'mimeType';
								$content[$sName] = $res[$name];
							}
							else
							{
								if ($res[$name]) $content[$sName] .= (strlen($content[$sName])>0 ? ' ':'') .$res[$name];
							}
						}
					}
				}
			}
			// handle preset info/values
			if (is_array($_REQUEST['preset']))
			{
				//_debug_array($_REQUEST);
				if ($_REQUEST['preset']['mailto']) {
					// handle mailto strings such as
					// mailto:larry,dan?cc=mike&bcc=sue&subject=test&body=type+your&body=message+here
					// the above string may be htmlentyty encoded, then multiple body tags are supported
					// first, strip the mailto: string out of the mailto URL
					$tmp_send_to = (stripos($_REQUEST['preset']['mailto'],'mailto')===false?$_REQUEST['preset']['mailto']:trim(substr(html_entity_decode($_REQUEST['preset']['mailto']),7)));
					// check if there is more than the to address
					$mailtoArray = explode('?',$tmp_send_to,2);
					if ($mailtoArray[1]) {
						// check if there are more than one requests
						$addRequests = explode('&',$mailtoArray[1]);
						foreach ($addRequests as $key => $reqval) {
							// the additional requests should have a =, to separate key from value.
							$keyValuePair = explode('=',$reqval,2);
							$content[$keyValuePair[0]] .= (strlen($content[$keyValuePair[0]])>0 ? ' ':'') . $keyValuePair[1];
						}
					}
					$content['to']=$mailtoArray[0];
					// if the mailto string is not htmlentity decoded the arguments are passed as simple requests
					foreach(array('cc','bcc','subject','body') as $name) {
						if ($_REQUEST[$name]) $content[$name] .= (strlen($content[$name])>0 ? ( $name == 'cc' || $name == 'bcc' ? ',' : ' ') : '') . $_REQUEST[$name];
					}
				}

				if ($_REQUEST['preset']['mailtocontactbyid']) {
					if ($GLOBALS['egw_info']['user']['apps']['addressbook']) {
						$addressbookprefs =& $GLOBALS['egw_info']['user']['preferences']['addressbook'];
						if (method_exists($GLOBALS['egw']->contacts,'search')) {

							$addressArray = explode(',',$_REQUEST['preset']['mailtocontactbyid']);
							foreach ((array)$addressArray as $id => $addressID)
							{
								$addressID = (int) $addressID;
								if (!($addressID>0))
								{
									unset($addressArray[$id]);
								}
							}
							if (count($addressArray))
							{
								$_searchCond = array('contact_id'=>$addressArray);
								//error_log(__METHOD__.__LINE__.$_searchString);
								if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) $showAccounts=false;
								$filter = ($showAccounts?array():array('account_id' => null));
								$filter['cols_to_search']=array('n_fn','email','email_home');
								$contacts = $GLOBALS['egw']->contacts->search($_searchCond,array('n_fn','email','email_home'),'n_fn','','%',false,'OR',array(0,100),$filter);
								// additionally search the accounts, if the contact storage is not the account storage
								if ($showAccounts &&
									$GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
									$GLOBALS['egw_info']['server']['contact_repository'] == 'sql')
								{
									$accounts = $GLOBALS['egw']->contacts->search($_searchCond,array('n_fn','email','email_home'),'n_fn','','%',false,'OR',array(0,100),array('owner' => 0));

									if ($contacts && $accounts)
									{
										$contacts = array_merge($contacts,$accounts);
										usort($contacts,create_function('$a,$b','return strcasecmp($a["n_fn"],$b["n_fn"]);'));
									}
									elseif($accounts)
									{
										$contacts =& $accounts;
									}
									unset($accounts);
								}
							}
							if(is_array($contacts)) {
								$mailtoArray = array();
								$primary = $addressbookprefs['distributionListPreferredMail'];
								if ($primary != 'email' && $primary != 'email_home') $primary = 'email';
								$secondary = ($primary == 'email'?'email_home':'email');
								//error_log(__METHOD__.__LINE__.array2string($contacts));
								foreach($contacts as $contact) {
									$innerCounter=0;
									foreach(array($contact[$primary],$contact[$secondary]) as $email) {
										// use pref distributionListPreferredMail for the primary address
										// avoid wrong addresses, if an rfc822 encoded address is in addressbook
										$email = preg_replace("/(^.*<)([a-zA-Z0-9_\-]+@[a-zA-Z0-9_\-\.]+)(.*)/",'$2',$email);
										$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
										$completeMailString = addslashes(trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']) .' <'. trim($email) .'>');
										if($innerCounter==0 && !empty($email) && in_array($completeMailString ,$mailtoArray) === false) {
											$i++;
											$innerCounter++;
											$mailtoArray[$i] = $completeMailString;
										}
									}
								}
							}
							//error_log(__METHOD__.__LINE__.array2string($mailtoArray));
							$content['to']=$mailtoArray;
						}
					}
				}

				if (isset($_REQUEST['preset']['file']))
				{
					$content['filemode'] = !empty($_REQUEST['preset']['filemode']) &&
						isset(egw_sharing::$modes[$_REQUEST['preset']['filemode']]) ?
							$_REQUEST['preset']['filemode'] : egw_sharing::ATTACH;

					$names = (array)$_REQUEST['preset']['name'];
					$types = (array)$_REQUEST['preset']['type'];
					//if (!empty($types) && in_array('text/calendar; method=request',$types))
					$files = (array)$_REQUEST['preset']['file'];
					foreach($files as $k => $path)
					{
						if (!empty($types[$k]) && stripos($types[$k],'text/calendar')!==false)
						{
							$insertSigOnTop = 'below';
						}
						//error_log(__METHOD__.__LINE__.$path.'->'.array2string(parse_url($path,PHP_URL_SCHEME == 'vfs')));
						if (parse_url($path,PHP_URL_SCHEME == 'vfs'))
						{
							//egw_vfs::load_wrapper('vfs');
							$type = egw_vfs::mime_content_type($path);
							// special handling for attaching vCard of iCal --> use their link-title as name
							if (substr($path,-7) != '/.entry' ||
								!(list($app,$id) = array_slice(explode('/',$path),-3)) ||
								!($name = egw_link::title($app, $id)))
							{
								$name = egw_vfs::decodePath(egw_vfs::basename($path));
							}
							else
							{
								$name .= '.'.mime_magic::mime2ext($type);
							}
							$path = str_replace('+','%2B',$path);
							$formData = array(
								'name' => $name,
								'type' => $type,
								'file' => egw_vfs::decodePath($path),
								'size' => filesize(egw_vfs::decodePath($path)),
							);
							if ($formData['type'] == egw_vfs::DIR_MIME_TYPE && $content['filemode'] == egw_sharing::ATTACH)
							{
								$content['filemode'] = egw_sharing::READONLY;
								egw_framework::message(lang('Directories have to be shared.'), 'info');
							}
						}
						elseif(is_readable($path))
						{
							$formData = array(
								'name' => isset($names[$k]) ? $names[$k] : basename($path),
								'type' => isset($types[$k]) ? $types[$k] : (function_exists('mime_content_type') ? mime_content_type($path) : mime_magic::filename2mime($path)),
								'file' => $path,
								'size' => filesize($path),
							);
						}
						else
						{
							continue;
						}
						$this->addAttachment($formData,$content,($alwaysAttachVCardAtCompose?true:false));
					}
					$remember = array();
					if (isset($_REQUEST['preset']['mailto']) || (isset($_REQUEST['app']) && isset($_REQUEST['method']) && isset($_REQUEST['id'])))
					{
						foreach(array_keys($content) as $k)
						{
							if (in_array($k,array('to','cc','bcc','subject','body','mimeType'))&&isset($this->sessionData[$k])) $remember[$k] = $this->sessionData[$k];
						}
					}
					if(!empty($remember)) $content = array_merge($content,$remember);
				}
				foreach(array('to','cc','bcc','subject','body') as $name)
				{
					if ($_REQUEST['preset'][$name]) $content[$name] = $_REQUEST['preset'][$name];
				}
			}
			// is the to address set already?
			if (!empty($_REQUEST['send_to']))
			{
				$content['to'] = base64_decode($_REQUEST['send_to']);
				// first check if there is a questionmark or ampersand
				if (strpos($content['to'],'?')!== false) list($content['to'],$rest) = explode('?',$content['to'],2);
				$content['to'] = html_entity_decode($content['to']);
				if (($at_pos = strpos($content['to'],'@')) !== false)
				{
					if (($amp_pos = strpos(substr($content['to'],$at_pos),'&')) !== false)
					{
						//list($email,$addoptions) = explode('&',$value,2);
						$email = substr($content['to'],0,$amp_pos+$at_pos);
						$rest = substr($content['to'], $amp_pos+$at_pos+1);
						//error_log(__METHOD__.__LINE__.$email.' '.$rest);
						$content['to'] = $email;
					}
				}
				if (strpos($content['to'],'%40')!== false) $content['to'] = html::purify(str_replace('%40','@',$content['to']));
				$rarr = array(html::purify($rest));
				if (isset($rest)&&!empty($rest) && strpos($rest,'&')!== false) $rarr = explode('&',$rest);
				//error_log(__METHOD__.__LINE__.$content['to'].'->'.array2string($rarr));
				$karr = array();
				foreach ($rarr as &$rval)
				{
					//must contain =
					if (strpos($rval,'=')!== false)
					{
						list($k,$v) = explode('=',$rval,2);
						$karr[$k] = (string)$v;
						unset($k,$v);
					}
				}
				//error_log(__METHOD__.__LINE__.$content['to'].'->'.array2string($karr));
				foreach(array('cc','bcc','subject','body') as $name)
				{
					if ($karr[$name]) $content[$name] = $karr[$name];
				}
				if (!empty($_REQUEST['subject'])) $content['subject'] = html::purify(trim(html_entity_decode($_REQUEST['subject'])));
			}
		}
		//is the MimeType set/requested
		if ($isFirstLoad && !empty($_REQUEST['mimeType']))
		{
			if (($_REQUEST['mimeType']=="text" ||$_REQUEST['mimeType']=="plain") && $content['mimeType'] == 'html')
			{
				$_content['mimeType'] = $content['mimeType']  = 'plain';
				$content['body'] = $this->convertHTMLToText(str_replace(array("\n\r","\n"),' ',$content['body']));
			}
			if ($_REQUEST['mimeType']=="html" && $content['mimeType'] != 'html')
			{
				$_content['mimeType'] = $content['mimeType']  = 'html';
				$content['body'] = "<pre>".$content['body']."</pre>";
				// take care this assumption is made on the creation of the reply header in bocompose::getReplyData
				if (strpos($content['body'],"<pre> \r\n \r\n---")===0) $content['body'] = substr_replace($content['body']," <br>\r\n<pre>---",0,strlen("<pre> \r\n \r\n---")-1);
			}
		}
		else
		{
			// try to enforce a mimeType on reply ( if type is not of the wanted type )
			if ($isReply)
			{
				if (!empty($this->mailPreferences['replyOptions']) && $this->mailPreferences['replyOptions']=="text" &&
					$content['mimeType'] == 'html')
				{
					$_content['mimeType'] = $content['mimeType']  = 'plain';
					$content['body'] = $this->convertHTMLToText(str_replace(array("\n\r","\n"),' ',$content['body']));
				}
				if (!empty($this->mailPreferences['replyOptions']) && $this->mailPreferences['replyOptions']=="html" &&
					$content['mimeType'] != 'html')
				{
					$_content['mimeType'] = $content['mimeType']  = 'html';
					$content['body'] = "<pre>".$content['body']."</pre>";
					// take care this assumption is made on the creation of the reply header in bocompose::getReplyData
					if (strpos($content['body'],"<pre> \r\n \r\n---")===0) $content['body'] = substr_replace($content['body']," <br>\r\n<pre>---",0,strlen("<pre> \r\n \r\n---")-1);
				}
			}
		}

		if ($content['mimeType'] == 'html' && html::htmlarea_availible()===false)
		{
			$_content['mimeType'] = $content['mimeType'] = 'plain';
			$content['body'] = $this->convertHTMLToText($content['body']);
		}
		// is a certain signature requested?
		// only the following values are supported (and make sense)
		// no => means -2
		// system => means -1
		// default => fetches the default, which is standard behavior
		if (!empty($_REQUEST['signature']) && (strtolower($_REQUEST['signature']) == 'no' || strtolower($_REQUEST['signature']) == 'system'))
		{
			$content['mailidentity'] = $presetSig = (strtolower($_REQUEST['signature']) == 'no' ? -2 : -1);
		}

		$disableRuler = false;
		//_debug_array(($presetSig ? $presetSig : $content['mailidentity']));
		try
		{
			$signature = emailadmin_account::read_identity($content['mailidentity'] ? $content['mailidentity'] : $presetSig,true);
		}
		catch (Exception $e)
		{
			//PROBABLY NOT FOUND
			$signature=array();
		}
		if ((isset($this->mailPreferences['disableRulerForSignatureSeparation']) &&
			$this->mailPreferences['disableRulerForSignatureSeparation']) ||
			empty($signature['ident_signature']) || trim($this->convertHTMLToText($signature['ident_signature'],true,true)) =='')
		{
			$disableRuler = true;
		}
		$font_span = $font_part = '';
		if($content['mimeType'] == 'html' /*&& trim($content['body'])==''*/) {
			// User preferences for style
			$font = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font'];
			$font_size = egw_ckeditor_config::font_size_from_prefs();
			$font_part = '<span style="width:100%; display: inline; '.($font?'font-family:'.$font.'; ':'').($font_size?'font-size:'.$font_size.'; ':'').'">';
			$font_span = $font_part.'&#8203;</span>';
			if (empty($font) && empty($font_size)) $font_span = '';
		}
		// the font span should only be applied on first load or on switch plain->html and the absence of the font_part of the span
		if (!$isFirstLoad && !empty($font_span) && stripos($content['body'],$font_part)===false) $font_span = '';
		//remove possible html header stuff
		if (stripos($content['body'],'<html><head></head><body>')!==false) $content['body'] = str_ireplace(array('<html><head></head><body>','</body></html>'),array('',''),$content['body']);
		//error_log(__METHOD__.__LINE__.array2string($this->mailPreferences));
		$blockElements = array('address','blockquote','center','del','dir','div','dl','fieldset','form','h1','h2','h3','h4','h5','h6','hr','ins','isindex','menu','noframes','noscript','ol','p','pre','table','ul');
		if ($this->mailPreferences['insertSignatureAtTopOfMessage']!='no_belowaftersend' &&
			!(isset($_POST['mySigID']) && !empty($_POST['mySigID']) ) && !$suppressSigOnTop
		)
		{
			// ON tOP OR BELOW? pREF CAN TELL
			/*
				Signature behavior preference changed. New default, if not set -> 0
						'0' => 'after reply, visible during compose',
						'1' => 'before reply, visible during compose',
						'no_belowaftersend'  => 'appended after reply before sending',
			*/
			$insertSigOnTop = ($insertSigOnTop?$insertSigOnTop:($this->mailPreferences['insertSignatureAtTopOfMessage']?$this->mailPreferences['insertSignatureAtTopOfMessage']:'below'));
			$sigText = mail_bo::merge($signature['ident_signature'],array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
			if ($content['mimeType'] == 'html')
			{
				$sigTextStartsWithBlockElement = ($disableRuler?false:true);
				foreach($blockElements as $e)
				{
					if ($sigTextStartsWithBlockElement) break;
					if (stripos(trim($sigText),'<'.$e)===0) $sigTextStartsWithBlockElement = true;
				}
			}
			if($content['mimeType'] == 'html') {
				$before = $disableRuler ? '' : '<hr style="border:1px dotted silver; width:100%;">';
				$inbetween = '';
			} else {
				$before = ($disableRuler ?"\r\n\r\n":"\r\n\r\n-- \r\n");
				$inbetween = "\r\n";
			}
			if ($content['mimeType'] == 'html')
			{
				$sigText = ($sigTextStartsWithBlockElement?'':"<div>")."<!-- HTMLSIGBEGIN -->".$sigText."<!-- HTMLSIGEND -->".($sigTextStartsWithBlockElement?'':"</div>");
			}

			if ($insertSigOnTop === 'below')
			{
				$content['body'] = $font_span.$content['body'].$before.($content['mimeType'] == 'html'?$sigText:$this->convertHTMLToText($sigText,true,true));
			}
			else
			{
				$content['body'] = $font_span.$before.($content['mimeType'] == 'html'?$sigText:$this->convertHTMLToText($sigText,true,true)).$inbetween.$content['body'];
			}
		}
		else
		{
			$content['body'] = ($font_span?($isFirstLoad === "switchedplaintohtml"?$font_part:$font_span):/*($content['mimeType'] == 'html'?'&nbsp;':'')*/'').$content['body'].($isFirstLoad === "switchedplaintohtml"?"</span>":"");
		}
		//error_log(__METHOD__.__LINE__.$content['body']);

		// prepare body
		// in a way, this tests if we are having real utf-8 (the displayCharset) by now; we should if charsets reported (or detected) are correct
		if (strtoupper($this->displayCharset) == 'UTF-8')
		{
			$test = @json_encode($content['body']);
			//error_log(__METHOD__.__LINE__.' ->'.strlen($singleBodyPart['body']).' Error:'.json_last_error().'<- BodyPart:#'.$test.'#');
			//if (json_last_error() != JSON_ERROR_NONE && strlen($singleBodyPart['body'])>0)
			if ($test=="null" && strlen($content['body'])>0)
			{
				// try to fix broken utf8
				$x = (function_exists('mb_convert_encoding')?mb_convert_encoding($content['body'],'UTF-8','UTF-8'):(function_exists('iconv')?@iconv("UTF-8","UTF-8//IGNORE",$content['body']):$content['body']));
				$test = @json_encode($x);
				if ($test=="null" && strlen($content['body'])>0)
				{
					// this should not be needed, unless something fails with charset detection/ wrong charset passed
					error_log(__METHOD__.__LINE__.' Charset problem detected; Charset Detected:'.mail_bo::detect_encoding($content['body']));
					$content['body'] = utf8_encode($content['body']);
				}
				else
				{
					$content['body'] = $x;
				}
			}
		}
		//error_log(__METHOD__.__LINE__.array2string($content));

		// get identities of all accounts as "$acc_id:$ident_id" => $identity
		$sel_options['mailaccount'] = $identities = array();
		foreach(emailadmin_account::search() as $acc_id => $account)
		{
			foreach(emailadmin_account::identities($acc_id) as $ident_id => $identity)
			{
				$sel_options['mailaccount'][$acc_id.':'.$ident_id] = $identity;
				$identities[$ident_id] = $identity;
			}
			unset($account);
		}

		//$content['bcc'] = array('kl@stylite.de','kl@leithoff.net');
		// address stuff like from, to, cc, replyto
		$destinationRows = 0;
		foreach(self::$destinations as $destination) {
			if (!is_array($content[$destination]))
			{
				if (!empty($content[$destination])) $content[$destination] = (array)$content[$destination];
			}
			$addr_content = $content[strtolower($destination)];
			// we clear the given address array and rebuild it
			unset($content[strtolower($destination)]);
			foreach((array)$addr_content as $key => $value) {
				if ($value=="NIL@NIL") continue;
				if ($destination=='replyto' && str_replace('"','',$value) ==
					str_replace('"','',$identities[$presetId ? $presetId : $this->mail_bo->getDefaultIdentity()]))
				{
					// preserve/restore the value to content.
					$content[strtolower($destination)][]=$value;
					continue;
				}
				//error_log(__METHOD__.__LINE__.array2string(array('key'=>$key,'value'=>$value)));
				$value = str_replace("\"\"",'"', htmlspecialchars_decode($value, ENT_COMPAT));
				foreach(emailadmin_imapbase::parseAddressList($value) as $addressObject) {
					if ($addressObject->host == '.SYNTAX-ERROR.') continue;
					$address = imap_rfc822_write_address($addressObject->mailbox,$addressObject->host,$addressObject->personal);
					//$address = mail_bo::htmlentities($address, $this->displayCharset);
					$content[strtolower($destination)][]=$address;
					$destinationRows++;
				}
			}
		}
		if ($_content)
		{
			//input array of _content had no signature information but was seeded later, and content has a valid setting
			if (!$_contentHasSigID && $content['mailidentity'] && array_key_exists('mailidentity',$_content)) unset($_content['mailidentity']);
			$content = array_merge($content,$_content);

			if (!empty($content['folder'])) $sel_options['folder']=$this->ajax_searchFolder(0,true);
			if (empty($content['mailaccount'])) $content['mailaccount'] = $this->mail_bo->profileID;
		}
		else
		{
			//error_log(__METHOD__.__LINE__.array2string(array($sel_options['mailaccount'],$selectedSender)));
			$content['mailaccount'] = $this->mail_bo->profileID;
			//error_log(__METHOD__.__LINE__.$content['body']);
		}
		$content['is_html'] = ($content['mimeType'] == 'html'?true:'');
		$content['is_plain'] = ($content['mimeType'] == 'html'?'':true);
		$content['mail_'.($content['mimeType'] == 'html'?'html':'plain').'text'] =$content['body'];
		$content['showtempname']=0;
		//if (is_array($content['attachments']))error_log(__METHOD__.__LINE__.'before merging content with uploadforCompose:'.array2string($content['attachments']));
		$content['attachments']=(is_array($content['attachments'])&&is_array($content['uploadForCompose'])?array_merge($content['attachments'],(!empty($content['uploadForCompose'])?$content['uploadForCompose']:array())):(is_array($content['uploadForCompose'])?$content['uploadForCompose']:(is_array($content['attachments'])?$content['attachments']:null)));
		//if (is_array($content['attachments'])) foreach($content['attachments'] as $k => &$file) $file['delete['.$file['tmp_name'].']']=0;
		$content['no_griddata'] = empty($content['attachments']);
		$preserv['attachments'] = $content['attachments'];

		//if (is_array($content['attachments']))error_log(__METHOD__.__LINE__.' Attachments:'.array2string($content['attachments']));
		// if no filemanager -> no vfsFileSelector
		if (!$GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$content['vfsNotAvailable'] = "mail_DisplayNone";
		}
		// if no infolog -> no save as infolog
		if (!$GLOBALS['egw_info']['user']['apps']['infolog'])
		{
			$content['noInfologAvailable'] = "mail_DisplayNone";
		}
		// if no tracker -> no save as tracker
		if (!$GLOBALS['egw_info']['user']['apps']['tracker'])
		{
			$content['noTrackerAvailable'] = "mail_DisplayNone";
		}
		if (!$GLOBALS['egw_info']['user']['apps']['infolog'] && !$GLOBALS['egw_info']['user']['apps']['tracker'])
		{
			$content['noSaveAsAvailable'] = "mail_DisplayNone";
		}
		// composeID to detect if we have changes to certain content
		$preserv['composeID'] = $content['composeID'] = $this->composeID;
		//error_log(__METHOD__.__LINE__.' ComposeID:'.$preserv['composeID']);
		$preserv['is_html'] = $content['is_html'];
		$preserv['is_plain'] = $content['is_plain'];
		if (isset($content['mimeType'])) $preserv['mimeType'] = $content['mimeType'];
		$sel_options['mimeType'] = self::$mimeTypes;
		$sel_options['priority'] = self::$priorities;
		$sel_options['filemode'] = egw_sharing::$modes;
		if (!isset($content['priority']) || empty($content['priority'])) $content['priority']=3;
		//$GLOBALS['egw_info']['flags']['currentapp'] = 'mail';//should not be needed
		$etpl = new etemplate_new('mail.compose');

		$etpl->setElementAttribute('composeToolbar', 'actions', $this->getToolbarActions($_content));
		if ($content['mimeType']=='html')
		{
			//mode="$cont[rtfEditorFeatures]" validation_rules="$cont[validation_rules]" base_href="$cont[upload_dir]"
			$_htmlConfig = mail_bo::$htmLawed_config;
			mail_bo::$htmLawed_config['comment'] = 2;
			mail_bo::$htmLawed_config['transform_anchor'] = false;
			// it is intentional to use that simple-withimage configuration for the ckeditor
			// and not the eGroupware wide pref to prevent users from trying things that will potentially not work
			// or not work as expected, as a full featured editor that may be wanted in other apps
			// is way overloading the "normal" needs for composing mails
			$content['rtfEditorFeatures']='simple-withimage';//egw_ckeditor_config::get_ckeditor_config();
			//$content['rtfEditorFeatures']='advanced';//egw_ckeditor_config::get_ckeditor_config();
			$content['validation_rules']= json_encode(mail_bo::$htmLawed_config);
			$etpl->setElementAttribute('mail_htmltext','mode',$content['rtfEditorFeatures']);
			$etpl->setElementAttribute('mail_htmltext','validation_rules',$content['validation_rules']);
			mail_bo::$htmLawed_config = $_htmlConfig;
		}

		if (isset($content['composeID'])&&!empty($content['composeID']))
		{
			$composeCache = $content;
			unset($composeCache['body']);
			unset($composeCache['mail_htmltext']);
			unset($composeCache['mail_plaintext']);
			egw_cache::setCache(egw_cache::SESSION,'mail','composeCache'.trim($GLOBALS['egw_info']['user']['account_id']).'_'.$this->composeID,$composeCache,$expiration=60*60*2);
		}
		if (!isset($_content['serverID'])||empty($_content['serverID']))
		{
			$content['serverID'] = $this->mail_bo->profileID;
		}
		$preserv['serverID'] = $content['serverID'];
		$preserv['lastDrafted'] = $content['lastDrafted'];
		$preserv['processedmail_id'] = $content['processedmail_id'];
		$preserv['references'] = $content['references'];
		$preserv['in-reply-to'] = $content['in-reply-to'];
		// thread-topic is a proprietary microsoft header and deprecated with the current version
		// horde does not support the encoding of thread-topic, and probably will not no so in the future
		//$preserv['thread-topic'] = $content['thread-topic'];
		$preserv['thread-index'] = $content['thread-index'];
		$preserv['list-id'] = $content['list-id'];
		$preserv['mode'] = $content['mode'];
		// convert it back to checkbox expectations
		if($content['mimeType'] == 'html') {
			$content['mimeType']=1;
		} else {
			$content['mimeType']=0;
		}
		// set the current selected mailaccount as param for folderselection
		$etpl->setElementAttribute('folder','autocomplete_params',array('mailaccount'=>$content['mailaccount']));
		// join again mailaccount and identity
		$content['mailaccount'] .= ':'.$content['mailidentity'];

		// Resolve distribution list before send content to client
		foreach(array('to', 'cc', 'bcc', 'replyto')  as $f)
		{
			if (is_array($content[$f])) $content[$f]= self::resolveEmailAddressList ($content[$f]);
		}

		$content['to'] = self::resolveEmailAddressList($content['to']);
		//error_log(__METHOD__.__LINE__.array2string($content));
		$etpl->exec('mail.mail_compose.compose',$content,$sel_options,array(),$preserv,2);
	}

	/**
	 * Get pre-fill a new compose based on an existing email
	 *
	 * @param type $mail_id If composing based on an existing mail, this is the ID of the mail
	 * @param type $part_id For multi-part mails, indicates which part
	 * @param type $from Indicates what the mail is based on, and how to extract data.
	 *	One of 'compose', 'composeasnew', 'reply', 'reply_all', 'forward' or 'merge'
	 * @param boolean $_focusElement varchar subject, to, body supported
	 * @param boolean $suppressSigOnTop
	 * @param boolean $isReply
	 *
	 * @return mixed[] Content array pre-filled according to source mail
	 */
	private function getComposeFrom($mail_id, $part_id, $from, &$_focusElement, &$suppressSigOnTop, &$isReply)
	{
		$content = array();
		//error_log(__METHOD__.__LINE__.array2string($mail_id).", $part_id, $from, $_focusElement, $suppressSigOnTop, $isReply");
		// on forward we may have to support multiple ids
		if ($from=='forward')
		{
			$replyIds = explode(',',$mail_id);
			$mail_id = $replyIds[0];
		}
		$hA = mail_ui::splitRowID($mail_id);
		$msgUID = $hA['msgUID'];
		$folder = $hA['folder'];
		$icServerID = $hA['profileID'];
		if ($icServerID != $this->mail_bo->profileID)
		{
			$this->changeProfile($icServerID);
		}
		$icServer = $this->mail_bo->icServer;
		if (!empty($folder) && !empty($msgUID) )
		{
			// this fill the session data with the values from the original email
			switch($from)
			{
				case 'composefromdraft':
					$content['mode'] = 'composefromdraft';
				case 'composeasnew':
					$content = $this->getDraftData($icServer, $folder, $msgUID, $part_id);
					$content['processedmail_id'] = $mail_id;

					$_focusElement = 'body';
					$suppressSigOnTop = true;
					break;
				case 'reply':
				case 'reply_all':
					$content = $this->getReplyData($from == 'reply' ? 'single' : 'all', $icServer, $folder, $msgUID, $part_id);
					$content['processedmail_id'] = $mail_id;
					$content['mode'] = 'reply';
					$_focusElement = 'body';
					$suppressSigOnTop = false;
					$isReply = true;
					break;
				case 'forward':
					$mode  = ($_GET['mode']=='forwardinline'?'inline':'asmail');
					// this fill the session data with the values from the original email
					foreach ($replyIds as &$mail_id)
					{
						//error_log(__METHOD__.__LINE__.' ID:'.$mail_id.' Mode:'.$mode);
						$hA = mail_ui::splitRowID($mail_id);
						$msgUID = $hA['msgUID'];
						$folder = $hA['folder'];
						$content = $this->getForwardData($icServer, $folder, $msgUID, $part_id, $mode);
					}
					$content['processedmail_id'] = implode(',',$replyIds);
					$content['mode'] = 'forward';
					$isReply = ($mode?$mode=='inline':$this->mailPreferences['message_forwarding'] == 'inline');
					$suppressSigOnTop = false;// ($mode && $mode=='inline'?true:false);// may be a better solution
					$_focusElement = 'to';
					break;
				default:
					error_log('Unhandled compose source: ' . $from);
			}
		}
		else if ($from == 'merge' && $_REQUEST['document'])
		{
			/*
			 * Special merge from everywhere else because all other apps merge gives
			 * a document to be downloaded, this opens a compose dialog.
			 * Use ajax_merge to merge & send multiple
			 */
			// Merge selected ID (in mailtocontactbyid or $mail_id) into given document
			$merge_class = preg_match('/^([a-z_-]+_merge)$/', $_REQUEST['merge']) ? $_REQUEST['merge'] : 'addressbook_merge';
			$document_merge = new $merge_class();
			$this->mail_bo->openConnection();
			$merge_ids = $_REQUEST['preset']['mailtocontactbyid'] ? $_REQUEST['preset']['mailtocontactbyid'] : $mail_id;
			if (!is_array($merge_ids)) $merge_ids = explode(',',$merge_ids);
			try
			{
				$merged_mail_id = '';
				$folder = '';
				if(($error = $document_merge->check_document($_REQUEST['document'],'')))
				{
					$content['msg'] = $error;
					return $content;
				}

				// Merge does not work correctly (missing to) if current app is not addressbook
				//$GLOBALS['egw_info']['flags']['currentapp'] = 'addressbook';

				// Actually do the merge
				if(count($merge_ids) <= 1)
				{
					$results = $this->mail_bo->importMessageToMergeAndSend(
						$document_merge, egw_vfs::PREFIX . $_REQUEST['document'], $merge_ids, $folder, $merged_mail_id
					);

					// Open compose
					$merged_mail_id = trim($GLOBALS['egw_info']['user']['account_id']).mail_ui::$delimiter.
						$this->mail_bo->profileID.mail_ui::$delimiter.
						base64_encode($folder).mail_ui::$delimiter.$merged_mail_id;
					$content = $this->getComposeFrom($merged_mail_id, $part_id, 'composefromdraft', $_focusElement, $suppressSigOnTop, $isReply);
				}
				else
				{
					$success = implode(', ',$results['success']);
					$fail = implode(', ', $results['failed']);
					if($success) egw_framework::message($success, 'success');
					egw_framework::window_close($fail);
				}
			}
			catch (egw_exception_wrong_userinput $e)
			{
				// if this returns with an exeption, something failed big time
				$content['msg'] = $e->getMessage();
			}
		}
		return $content;
	}

	/**
	 * previous bocompose stuff
	 */

	/**
	 * replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
	 * always returns 1
	 */
	static function replaceEmailAdresses(&$text)
	{
		// replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
		mail_bo::replaceEmailAdresses($text);
		return 1;
	}

	function convertHTMLToText(&$_html,$sourceishtml = true, $stripcrl=false)
	{
		$stripalltags = true;
		// third param is stripalltags, we may not need that, if the source is already in ascii
		if (!$sourceishtml) $stripalltags=false;
		return translation::convertHTMLToText($_html,$this->displayCharset,$stripcrl,$stripalltags);
	}

	function generateRFC822Address($_addressObject)
	{
		if($_addressObject->personal && $_addressObject->mailbox && $_addressObject->host) {
			return sprintf('"%s" <%s@%s>', $this->mail_bo->decode_header($_addressObject->personal), $_addressObject->mailbox, $this->mail_bo->decode_header($_addressObject->host,'FORCE'));
		} elseif($_addressObject->mailbox && $_addressObject->host) {
			return sprintf("%s@%s", $_addressObject->mailbox, $this->mail_bo->decode_header($_addressObject->host,'FORCE'));
		} else {
			return $this->mail_bo->decode_header($_addressObject->mailbox,true);
		}
	}

	/**
	 *  create a unique id, to keep track of different compose windows
	 */
	function generateComposeID()
	{
		return mail_bo::getRandomString();
	}

	// $_mode can be:
	// single: for a reply to one address
	// all: for a reply to all
	function getDraftData($_icServer, $_folder, $_uid, $_partID=NULL)
	{
		unset($_icServer);	// not used
		$this->sessionData['to'] = array();

		$mail_bo = $this->mail_bo;
		$mail_bo->openConnection();
		$mail_bo->reopen($_folder);

		// get message headers for specified message
		#$headers	= $mail_bo->getMessageHeader($_folder, $_uid);
		$headers	= $mail_bo->getMessageEnvelope($_uid, $_partID);
		$addHeadInfo = $mail_bo->getMessageHeader($_uid, $_partID);
		// thread-topic is a proprietary microsoft header and deprecated with the current version
		// horde does not support the encoding of thread-topic, and probably will not no so in the future
		//if ($addHeadInfo['THREAD-TOPIC']) $this->sessionData['thread-topic'] = $addHeadInfo['THREAD-TOPIC'];

		//error_log(__METHOD__.__LINE__.array2string($headers));
		if (!empty($addHeadInfo['X-MAILFOLDER'])) {
			foreach ( explode('|',$addHeadInfo['X-MAILFOLDER']) as $val ) {
				$fval=$val;
				$icServerID = $mail_bo->icServer->ImapServerId;
				if (stripos($val,'::')!==false) list($icServerID,$fval) = explode('::',$val,2);
				if ($icServerID != $mail_bo->icServer->ImapServerId) continue;
				if ($mail_bo->folderExists($fval)) $this->sessionData['folder'][] = $val;
			}
		}
		if (!empty($addHeadInfo['X-MAILIDENTITY'])) {
			// with the new system it would be the identity
			try
			{
				emailadmin_account::read_identity($addHeadInfo['X-MAILIDENTITY']);
				$this->sessionData['mailidentity'] = $addHeadInfo['X-MAILIDENTITY'];
			}
			catch (Exception $e)
			{
			}
		}
		/*
		if (!empty($addHeadInfo['X-STATIONERY'])) {
			$this->sessionData['stationeryID'] = $addHeadInfo['X-STATIONERY'];
		}
		*/
		if (!empty($addHeadInfo['X-MAILACCOUNT'])) {
			// with the new system it would the identity is the account id
			try
			{
				emailadmin_account::read($addHeadInfo['X-MAILACCOUNT']);
				$this->sessionData['mailaccount'] = $addHeadInfo['X-MAILACCOUNT'];
			}
			catch (Exception $e)
			{
				// fail silently
				$this->sessionData['mailaccount'] = $mail_bo->profileID;
			}
		}
		// if the message is located within the draft folder, add it as last drafted version (for possible cleanup on abort))
		if ($mail_bo->isDraftFolder($_folder)) $this->sessionData['lastDrafted'] = mail_ui::generateRowID($this->mail_bo->profileID, $_folder, $_uid);//array('uid'=>$_uid,'folder'=>$_folder);
		$this->sessionData['uid'] = $_uid;
		$this->sessionData['messageFolder'] = $_folder;
		$this->sessionData['isDraft'] = true;
		$foundAddresses = array();
		foreach((array)$headers['CC'] as $val) {
			$rfcAddr=emailadmin_imapbase::parseAddressList($val);
			$_rfcAddr = $rfcAddr[0];
			if (!$_rfcAddr->valid) continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (!$_rfcAddr->mailbox && !$_rfcAddr->host) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(!$foundAddresses[$keyemail]) {
				$address = $this->mail_bo->decode_header($val,true);
				$this->sessionData['cc'][] = $val;
				$foundAddresses[$keyemail] = true;
			}
		}

		foreach((array)$headers['TO'] as $val) {
			if(!is_array($val))
			{
				$this->sessionData['to'][] = $val;
				continue;
			}
			$rfcAddr=emailadmin_imapbase::parseAddressList($val);
			$_rfcAddr = $rfcAddr[0];
			if (!$_rfcAddr->valid) continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (!$_rfcAddr->mailbox && !$_rfcAddr->host) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(!$foundAddresses[$keyemail]) {
				$address = $this->mail_bo->decode_header($val,true);
				$this->sessionData['to'][] = $val;
				$foundAddresses[$keyemail] = true;
			}
		}

		foreach((array)$headers['REPLY-TO'] as $val) {
			$rfcAddr=emailadmin_imapbase::parseAddressList($val);
			$_rfcAddr = $rfcAddr[0];
			if (!$_rfcAddr->valid) continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (empty($_rfcAddr->mailbox) && empty($_rfcAddr->host)) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(!$foundAddresses[$keyemail]) {
				$address = $this->mail_bo->decode_header($val,true);
				$this->sessionData['replyto'][] = $val;
				$foundAddresses[$keyemail] = true;
			}
		}

		foreach((array)$headers['BCC'] as $val) {
			$rfcAddr=emailadmin_imapbase::parseAddressList($val);
			$_rfcAddr = $rfcAddr[0];
			if (!$_rfcAddr->valid) continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (empty($_rfcAddr->mailbox) && empty($_rfcAddr->host)) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(!$foundAddresses[$keyemail]) {
				$address = $this->mail_bo->decode_header($val,true);
				$this->sessionData['bcc'][] = $val;
				$foundAddresses[$keyemail] = true;
			}
		}
		//_debug_array($this->sessionData);
		$this->sessionData['subject']	= $mail_bo->decode_header($headers['SUBJECT']);
		// remove a printview tag if composing
		$searchfor = '/^\['.lang('printview').':\]/';
		$this->sessionData['subject'] = preg_replace($searchfor,'',$this->sessionData['subject']);
		$bodyParts = $mail_bo->getMessageBody($_uid, $this->mailPreferences['always_display'], $_partID);
		//_debug_array($bodyParts);
		#$fromAddress = ($headers['FROM'][0]['PERSONAL_NAME'] != 'NIL') ? $headers['FROM'][0]['RFC822_EMAIL'] : $headers['FROM'][0]['EMAIL'];
		if($bodyParts['0']['mimeType'] == 'text/html') {
			$this->sessionData['mimeType'] 	= 'html';

			for($i=0; $i<count($bodyParts); $i++) {
				if($i>0) {
					$this->sessionData['body'] .= '<hr>';
				}
				if($bodyParts[$i]['mimeType'] == 'text/plain') {
					#$bodyParts[$i]['body'] = nl2br($bodyParts[$i]['body']);
					$bodyParts[$i]['body'] = "<pre>".$bodyParts[$i]['body']."</pre>";
				}
				if ($bodyParts[$i]['charSet']===false) $bodyParts[$i]['charSet'] = mail_bo::detect_encoding($bodyParts[$i]['body']);
				$bodyParts[$i]['body'] = translation::convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
				#error_log( "GetDraftData (HTML) CharSet:".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
				$this->sessionData['body'] .= ($i>0?"<br>":""). $bodyParts[$i]['body'] ;
			}

		} else {
			$this->sessionData['mimeType']	= 'plain';

			for($i=0; $i<count($bodyParts); $i++) {
				if($i>0) {
					$this->sessionData['body'] .= "<hr>";
				}
				if ($bodyParts[$i]['charSet']===false) $bodyParts[$i]['charSet'] = mail_bo::detect_encoding($bodyParts[$i]['body']);
				$bodyParts[$i]['body'] = translation::convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
				#error_log( "GetDraftData (Plain) CharSet".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
				$this->sessionData['body'] .= ($i>0?"\r\n":""). $bodyParts[$i]['body'] ;
			}
		}

		if(($attachments = $mail_bo->getMessageAttachments($_uid,$_partID))) {
			foreach($attachments as $attachment) {
				$this->addMessageAttachment($_uid, $attachment['partID'],
					$_folder,
					$attachment['name'],
					$attachment['mimeType'],
					$attachment['size']);
			}
		}
		$mail_bo->closeConnection();
		return $this->sessionData;
	}

	function getErrorInfo()
	{
		if(isset($this->errorInfo)) {
			$errorInfo = $this->errorInfo;
			unset($this->errorInfo);
			return $errorInfo;
		}
		return false;
	}

	function getForwardData($_icServer, $_folder, $_uid, $_partID, $_mode=false)
	{
		if ($_mode)
		{
			$modebuff = $this->mailPreferences['message_forwarding'];
			$this->mailPreferences['message_forwarding'] = $_mode;
		}
		if  ($this->mailPreferences['message_forwarding'] == 'inline') {
			$this->getReplyData('forward', $_icServer, $_folder, $_uid, $_partID);
		}
		$mail_bo    = $this->mail_bo;
		$mail_bo->openConnection();
		$mail_bo->reopen($_folder);

		// get message headers for specified message
		$headers	= $mail_bo->getMessageEnvelope($_uid, $_partID);
		//error_log(__METHOD__.__LINE__.array2string($headers));
		//_debug_array($headers); exit;
		// check for Re: in subject header
		$this->sessionData['subject'] 	= "[FWD] " . $mail_bo->decode_header($headers['SUBJECT']);
		// the three attributes below are substituted by processedmail_id and mode
		//$this->sessionData['sourceFolder']=$_folder;
		//$this->sessionData['forwardFlag']='forwarded';
		//$this->sessionData['forwardedUID']=$_uid;
		if  ($this->mailPreferences['message_forwarding'] == 'asmail') {
			$this->sessionData['mimeType']  = $this->mailPreferences['composeOptions'];
			if($headers['SIZE'])
				$size				= $headers['SIZE'];
			else
				$size				= lang('unknown');

			$this->addMessageAttachment($_uid, $_partID, $_folder,
				$mail_bo->decode_header(($headers['SUBJECT']?$headers['SUBJECT']:lang('no subject'))).'.eml',
				'MESSAGE/RFC822', $size);
		}
		else
		{
			unset($this->sessionData['in-reply-to']);
			unset($this->sessionData['to']);
			unset($this->sessionData['cc']);
			if(($attachments = $mail_bo->getMessageAttachments($_uid,$_partID))) {
				//error_log(__METHOD__.__LINE__.':'.array2string($attachments));
				foreach($attachments as $attachment) {
					$this->addMessageAttachment($_uid, $attachment['partID'],
						$_folder,
						$attachment['name'],
						$attachment['mimeType'],
						$attachment['size']);
				}
			}
		}
		$mail_bo->closeConnection();
		if ($_mode)
		{
			$this->mailPreferences['message_forwarding'] = $modebuff;
		}
		//error_log(__METHOD__.__LINE__.array2string($this->sessionData));
		return $this->sessionData;
	}

	/**
	 * adds uploaded files or files in eGW's temp directory as attachments
	 *
	 * passes the given $_formData representing an attachment to $_content
	 *
	 * @param array $_formData fields of the compose form (to,cc,bcc,reply-to,subject,body,priority,signature), plus data of the file (name,file,size,type)
	 * @param array $_content the content passed to the function and to be modified
	 * @return void
	 */
	function addAttachment($_formData,&$_content,$eliminateDoubleAttachments=false)
	{
		//error_log(__METHOD__.__LINE__.' Formdata:'.array2string($_formData).' Content:'.array2string($_content));

		$attachfailed = false;
		// to guard against exploits the file must be either uploaded or be in the temp_dir
		// check if formdata meets basic restrictions (in tmp dir, or vfs, mimetype, etc.)
		try
		{
			$tmpFileName = mail_bo::checkFileBasics($_formData,$this->composeID,false);
		}
		catch (egw_exception_wrong_userinput $e)
		{
			$attachfailed = true;
			$alert_msg = $e->getMessage();
			egw_framework::message($e->getMessage(), 'error');
		}
		//error_log(__METHOD__.__LINE__.array2string($tmpFileName));
		//error_log(__METHOD__.__LINE__.array2string($_formData));

		if ($eliminateDoubleAttachments == true)
		{
			foreach ((array)$_content['attachments'] as $attach)
			{
				if ($attach['name'] && $attach['name'] == $_formData['name'] &&
					strtolower($_formData['type'])== strtolower($attach['type']) &&
					stripos($_formData['file'],'vfs://') !== false) return;
			}
		}
		if ($attachfailed === false)
		{
			$buffer = array(
				'name'	=> $_formData['name'],
				'type'	=> $_formData['type'],
				'file'	=> $tmpFileName,
				'tmp_name'	=> $tmpFileName,
				'size'	=> $_formData['size']
			);
			if (!is_array($_content['attachments'])) $_content['attachments']=array();
			$_content['attachments'][] = $buffer;
			unset($buffer);
		}
		else
		{
			error_log(__METHOD__.__LINE__.array2string($alert_msg));
		}
	}

	function addMessageAttachment($_uid, $_partID, $_folder, $_name, $_type, $_size)
	{
		$this->sessionData['attachments'][]=array (
			'uid'		=> $_uid,
			'partID'	=> $_partID,
			'name'		=> $_name,
			'type'		=> $_type,
			'size'		=> $_size,
			'folder'	=> $_folder,
			'tmp_name'	=> mail_ui::generateRowID($this->mail_bo->profileID, $_folder, $_uid).'_'.(!empty($_partID)?$_partID:count($this->sessionData['attachments'])+1),
		);
	}

	function getAttachment()
	{
		// read attachment data from etemplate request, use tmpname only to identify it
		if (($request = etemplate_request::read($_GET['etemplate_exec_id'])))
		{
			foreach($request->preserv['attachments'] as $attachment)
			{
				if ($_GET['tmpname'] === $attachment['tmp_name']) break;
			}
		}
		if (!$request || $_GET['tmpname'] !== $attachment['tmp_name'])
		{
			header('HTTP/1.1 404 Not found');
			die('Attachment '.htmlspecialchars($_GET['tmpname']).' NOT found!');
		}

		//error_log(__METHOD__.__LINE__.array2string($_GET));
		if (parse_url($attachment['tmp_name'],PHP_URL_SCHEME) == 'vfs')
		{
			egw_vfs::load_wrapper('vfs');
		}
		// attachment data in temp_dir, only use basename of given name, to not allow path traversal
		else
		{
			$attachment['tmp_name'] = $GLOBALS['egw_info']['server']['temp_dir'].SEP.basename($attachment['tmp_name']);
		}
		if(!file_exists($attachment['tmp_name']))
		{
			header('HTTP/1.1 404 Not found');
			die('Attachment '.htmlspecialchars($attachment['tmp_name']).' NOT found!');
		}
		$attachment['attachment'] = file_get_contents($attachment['tmp_name']);

		//error_log(__METHOD__.__LINE__.' FileSize:'.filesize($attachment['tmp_name']));
		if ($_GET['mode'] != "save")
		{
			if (strtoupper($attachment['type']) == 'TEXT/DIRECTORY')
			{
				$sfxMimeType = $attachment['type'];
				$buff = explode('.',$attachment['tmp_name']);
				$suffix = '';
				if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
				if (!empty($suffix)) $sfxMimeType = mime_magic::ext2mime($suffix);
				$attachment['type'] = $sfxMimeType;
				if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD') $attachment['type'] = strtoupper($sfxMimeType);
			}
			//error_log(__METHOD__.print_r($attachment,true));
			if (strtoupper($attachment['type']) == 'TEXT/CALENDAR' || strtoupper($attachment['type']) == 'TEXT/X-VCALENDAR')
			{
				//error_log(__METHOD__."about to call calendar_ical");
				$calendar_ical = new calendar_ical();
				$eventid = $calendar_ical->search($attachment['attachment'],-1);
				//error_log(__METHOD__.array2string($eventid));
				if (!$eventid) $eventid = -1;
				$event = $calendar_ical->importVCal($attachment['attachment'],(is_array($eventid)?$eventid[0]:$eventid),null,true);
				//error_log(__METHOD__.$event);
				if ((int)$event > 0)
				{
					$vars = array(
						'menuaction'      => 'calendar.calendar_uiforms.edit',
						'cal_id'      => $event,
					);
					$GLOBALS['egw']->redirect_link('../index.php',$vars);
				}
				//Import failed, download content anyway
			}
			if (strtoupper($attachment['type']) == 'TEXT/X-VCARD' || strtoupper($attachment['type']) == 'TEXT/VCARD')
			{
				$addressbook_vcal = new addressbook_vcal();
				// double \r\r\n seems to end a vcard prematurely, so we set them to \r\n
				//error_log(__METHOD__.__LINE__.$attachment['attachment']);
				$attachment['attachment'] = str_replace("\r\r\n", "\r\n", $attachment['attachment']);
				$vcard = $addressbook_vcal->vcardtoegw($attachment['attachment']);
				if ($vcard['uid'])
				{
					$vcard['uid'] = trim($vcard['uid']);
					//error_log(__METHOD__.__LINE__.print_r($vcard,true));
					$contact = $addressbook_vcal->find_contact($vcard,false);
				}
				if (!$contact) $contact = null;
				// if there are not enough fields in the vcard (or the parser was unable to correctly parse the vcard (as of VERSION:3.0 created by MSO))
				if ($contact || count($vcard)>2)
				{
					$contact = $addressbook_vcal->addVCard($attachment['attachment'],(is_array($contact)?array_shift($contact):$contact),true);
				}
				if ((int)$contact > 0)
				{
					$vars = array(
						'menuaction'	=> 'addressbook.addressbook_ui.edit',
						'contact_id'	=> $contact,
					);
					$GLOBALS['egw']->redirect_link('../index.php',$vars);
				}
				//Import failed, download content anyway
			}
		}
		//error_log(__METHOD__.__LINE__.'->'.array2string($attachment));
		html::safe_content_header($attachment['attachment'], $attachment['name'], $attachment['type'], $size=0, true, $_GET['mode'] == "save");
		echo $attachment['attachment'];

		common::egw_exit();
	}

	/**
	 * Test if string contains one of the keys of an array
	 *
	 * @param array arrayToTestAgainst to test its keys against haystack
	 * @param string haystack
	 * @return boolean
	 */
	function testIfOneKeyInArrayDoesExistInString($arrayToTestAgainst,$haystack) {
		foreach (array_keys($arrayToTestAgainst) as $k)
		{
			//error_log(__METHOD__.__LINE__.':'.$k.'<->'.$haystack);
			if (stripos($haystack,$k)!==false)
			{
				//error_log(__METHOD__.__LINE__.':FOUND:'.$k.'<->'.$haystack.function_backtrace());
				return true;
			}
		}
		return false;
	}

	/**
	 * Gather the replyData and save it with the session, to be used then
	 *
	 * @param $_mode can be:
	 * 		single: for a reply to one address
	 * 		all: for a reply to all
	 * 		forward: inlineforwarding of a message with its attachments
	 * @param $_icServer number (0 as it is the active Profile)
	 * @param $_folder string
	 * @param $_uid number
	 * @param $_partID number
	 */
	function getReplyData($_mode, $_icServer, $_folder, $_uid, $_partID)
	{
		unset($_icServer);	// not used
		$foundAddresses = array();

		$mail_bo  = $this->mail_bo;
		$mail_bo->openConnection();
		$mail_bo->reopen($_folder);

		$userEMailAddresses = $mail_bo->getUserEMailAddresses();

		// get message headers for specified message
		//print "AAAA: $_folder, $_uid, $_partID<br>";
		$headers	= $mail_bo->getMessageEnvelope($_uid, $_partID,false,$_folder,$useHeaderInsteadOfEnvelope=true);
		//$headers	= $mail_bo->getMessageHeader($_uid, $_partID, true, true, $_folder);
		$this->sessionData['uid'] = $_uid;
		$this->sessionData['messageFolder'] = $_folder;
		$this->sessionData['in-reply-to'] = ($headers['IN-REPLY-TO']?$headers['IN-REPLY-TO']:$headers['MESSAGE_ID']);
		$this->sessionData['references'] = ($headers['REFERENCES']?$headers['REFERENCES']:$headers['MESSAGE_ID']);
		// thread-topic is a proprietary microsoft header and deprecated with the current version
		// horde does not support the encoding of thread-topic, and probably will not no so in the future
		//if ($headers['THREAD-TOPIC']) $this->sessionData['thread-topic'] = $headers['THREAD-TOPIC'];
		if ($headers['THREAD-INDEX']) $this->sessionData['thread-index'] = $headers['THREAD-INDEX'];
		if ($headers['LIST-ID']) $this->sessionData['list-id'] = $headers['LIST-ID'];
		//error_log(__METHOD__.__LINE__.' Mode:'.$_mode.':'.array2string($headers));
		// check for Reply-To: header and use if available
		if(!empty($headers['REPLY-TO']) && ($headers['REPLY-TO'] != $headers['FROM'])) {
			foreach($headers['REPLY-TO'] as $val) {
				if(!$foundAddresses[$val]) {
					$oldTo[] = $val;
					$foundAddresses[$val] = true;
				}
			}
			$oldToAddress	= (is_array($headers['REPLY-TO'])?$headers['REPLY-TO'][0]:$headers['REPLY-TO']);
		} else {
			foreach($headers['FROM'] as $val) {
				if(!$foundAddresses[$val]) {
					$oldTo[] = $val;
					$foundAddresses[$val] = true;
				}
			}
			$oldToAddress	= (is_array($headers['FROM'])?$headers['FROM'][0]:$headers['FROM']);
		}
		//error_log(__METHOD__.__LINE__.' OldToAddress:'.$oldToAddress.'#');
		if($_mode != 'all' || ($_mode == 'all' && !empty($oldToAddress) && !$this->testIfOneKeyInArrayDoesExistInString($userEMailAddresses,$oldToAddress)) ) {
			$this->sessionData['to'] = $oldTo;
		}

		if($_mode == 'all') {
			// reply to any address which is cc, but not to my self
			#if($headers->cc) {
				foreach($headers['CC'] as $val) {
					if($this->testIfOneKeyInArrayDoesExistInString($userEMailAddresses,$val)) {
						continue;
					}
					if(!$foundAddresses[$val]) {
						$this->sessionData['cc'][] = $val;
						$foundAddresses[$val] = true;
					}
				}
			#}

			// reply to any address which is to, but not to my self
			#if($headers->to) {
				foreach($headers['TO'] as $val) {
					if($this->testIfOneKeyInArrayDoesExistInString($userEMailAddresses,$val)) {
						continue;
					}
					if(!$foundAddresses[$val]) {
						$this->sessionData['to'][] = $val;
						$foundAddresses[$val] = true;
					}
				}
			#}

			#if($headers->from) {
				foreach($headers['FROM'] as $val) {
					if($this->testIfOneKeyInArrayDoesExistInString($userEMailAddresses,$val)) {
						continue;
					}
					//error_log(__METHOD__.__LINE__.' '.$val);
					if(!$foundAddresses[$val]) {
						$this->sessionData['to'][] = $val;
						$foundAddresses[$val] = true;
					}
				}
			#}
		}

		// check for Re: in subject header
		if(strtolower(substr(trim($mail_bo->decode_header($headers['SUBJECT'])), 0, 3)) == "re:") {
			$this->sessionData['subject'] = $mail_bo->decode_header($headers['SUBJECT']);
		} else {
			$this->sessionData['subject'] = "Re: " . $mail_bo->decode_header($headers['SUBJECT']);
		}

		//_debug_array($headers);
		//error_log(__METHOD__.__LINE__.'->'.array2string($this->mailPreferences['htmlOptions']));
		$bodyParts = $mail_bo->getMessageBody($_uid, ($this->mailPreferences['htmlOptions']?$this->mailPreferences['htmlOptions']:''), $_partID);
		//_debug_array($bodyParts);
		$styles = mail_bo::getStyles($bodyParts);

		$fromAddress = implode(', ', str_replace(array('<','>'),array('[',']'),$headers['FROM']));

		$toAddressA = array();
		$toAddress = '';
		foreach ($headers['TO'] as $mailheader) {
			$toAddressA[] =  $mailheader;
		}
		if (count($toAddressA)>0)
		{
			$toAddress = implode(', ', str_replace(array('<','>'),array('[',']'),$toAddressA));
			$toAddress = @htmlspecialchars(lang("to")).": ".$toAddress.($bodyParts['0']['mimeType'] == 'text/html'?"<br>":"\r\n");
		}
		$ccAddressA = array();
		$ccAddress = '';
		foreach ($headers['CC'] as $mailheader) {
			$ccAddressA[] =  $mailheader;
		}
		if (count($ccAddressA)>0)
		{
			$ccAddress = implode(', ', str_replace(array('<','>'),array('[',']'),$ccAddressA));
			$ccAddress = @htmlspecialchars(lang("cc")).": ".$ccAddress.($bodyParts['0']['mimeType'] == 'text/html'?"<br>":"\r\n");
		}
		if($bodyParts['0']['mimeType'] == 'text/html') {
			$this->sessionData['body']	= /*"<br>".*//*"&nbsp;".*/"<div>".'----------------'.lang("original message").'-----------------'."".'<br>'.
				@htmlspecialchars(lang("from")).": ".$fromAddress."<br>".
				$toAddress.$ccAddress.
				@htmlspecialchars(lang("date").": ".$headers['DATE'],ENT_QUOTES | ENT_IGNORE,mail_bo::$displayCharset, false)."<br>".
				'----------------------------------------------------------'."</div>";
			$this->sessionData['mimeType'] 	= 'html';
			if (!empty($styles)) $this->sessionData['body'] .= $styles;
			$this->sessionData['body']	.= '<blockquote type="cite">';

			for($i=0; $i<count($bodyParts); $i++) {
				if($i>0) {
					$this->sessionData['body'] .= '<hr>';
				}
				if($bodyParts[$i]['mimeType'] == 'text/plain') {
					#$bodyParts[$i]['body'] = nl2br($bodyParts[$i]['body'])."<br>";
					$bodyParts[$i]['body'] = "<pre>".$bodyParts[$i]['body']."</pre>";
				}
				if ($bodyParts[$i]['charSet']===false) $bodyParts[$i]['charSet'] = mail_bo::detect_encoding($bodyParts[$i]['body']);

				$_htmlConfig = mail_bo::$htmLawed_config;
				mail_bo::$htmLawed_config['comment'] = 2;
				mail_bo::$htmLawed_config['transform_anchor'] = false;
				$this->sessionData['body'] .= "<br>".self::_getCleanHTML(translation::convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']));
				mail_bo::$htmLawed_config = $_htmlConfig;
				#error_log( "GetReplyData (HTML) CharSet:".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
			}

			$this->sessionData['body']	.= '</blockquote><br>';
			$this->sessionData['body'] =  mail_ui::resolve_inline_images($this->sessionData['body'], $_folder, $_uid, $_partID, 'html');
		} else {
			//$this->sessionData['body']	= @htmlspecialchars(lang("on")." ".$headers['DATE']." ".$mail_bo->decode_header($fromAddress), ENT_QUOTES) . " ".lang("wrote").":\r\n";
			// take care the way the ReplyHeader is created here, is used later on in uicompose::compose, in case you force replys to be HTML (prefs)
            $this->sessionData['body']  = " \r\n \r\n".'----------------'.lang("original message").'-----------------'."\r\n".
                @htmlspecialchars(lang("from")).": ".$fromAddress."\r\n".
				$toAddress.$ccAddress.
				@htmlspecialchars(lang("date").": ".$headers['DATE'], ENT_QUOTES | ENT_IGNORE,mail_bo::$displayCharset, false)."\r\n".
                '-------------------------------------------------'."\r\n \r\n ";
			$this->sessionData['mimeType']	= 'plain';

			for($i=0; $i<count($bodyParts); $i++) {
				if($i>0) {
					$this->sessionData['body'] .= "<hr>";
				}

				// add line breaks to $bodyParts
				if ($bodyParts[$i]['charSet']===false) $bodyParts[$i]['charSet'] = mail_bo::detect_encoding($bodyParts[$i]['body']);
				$newBody	= translation::convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
				#error_log( "GetReplyData (Plain) CharSet:".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
				$newBody = mail_ui::resolve_inline_images($newBody, $_folder, $_uid, $_partID, 'plain');
				$this->sessionData['body'] .= "\r\n";
				// create body new, with good line breaks and indention
				foreach(explode("\n",$newBody) as $value) {
					// the explode is removing the character
					if (trim($value) != '') {
						#if ($value != "\r") $value .= "\n";
					}
					$numberOfChars = strspn(trim($value), ">");
					$appendString = str_repeat('>', $numberOfChars + 1);

					$bodyAppend = $this->mail_bo->wordwrap($value, 76-strlen("\r\n$appendString "), "\r\n$appendString ",'>');

					if($bodyAppend[0] == '>') {
						$bodyAppend = '>'. $bodyAppend;
					} else {
						$bodyAppend = '> '. $bodyAppend;
					}

					$this->sessionData['body'] .= $bodyAppend;
				}
			}
		}

		$mail_bo->closeConnection();
		return $this->sessionData;

	}

	static function _getCleanHTML($_body, $usepurify = false, $cleanTags=true)
	{
		static $nonDisplayAbleCharacters = array('[\016]','[\017]',
				'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
				'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
		mail_bo::getCleanHTML($_body, $usepurify, $cleanTags);
		return preg_replace($nonDisplayAbleCharacters, '', $_body);
	}

	static function _getHostName()
	{
		if (isset($_SERVER['SERVER_NAME'])) {
			$result = $_SERVER['SERVER_NAME'];
		} else {
			$result = 'localhost.localdomain';
		}
		return $result;
	}

	/**
	 * Create a message from given data and identity
	 *
	 * @param egw_mailer $_mailObject
	 * @param array $_formData
	 * @param array $_identity
	 * @param boolean $_autosaving =false true: autosaving, false: save-as-draft or send
	 */
	function createMessage(egw_mailer $_mailObject, array $_formData, array $_identity, $_autosaving=false)
	{
		if (substr($_formData['body'], 0, 27) == '-----BEGIN PGP MESSAGE-----')
		{
			$_formData['mimeType'] = 'openpgp';
		}
		//error_log(__METHOD__."(, formDate[filemode]=$_formData[filemode], _autosaving=".array2string($_autosaving).') '.function_backtrace());
		$mail_bo	= $this->mail_bo;
		$activeMailProfile = emailadmin_account::read($this->mail_bo->profileID);

		// you need to set the sender, if you work with different identities, since most smtp servers, dont allow
		// sending in the name of someone else
		if ($_identity['ident_id'] != $activeMailProfile['ident_id'] && !empty($_identity['ident_email']) && strtolower($activeMailProfile['ident_email']) != strtolower($_identity['ident_email']))
		{
			error_log(__METHOD__.__LINE__.' Faking From/SenderInfo for '.$activeMailProfile['ident_email'].' with ID:'.$activeMailProfile['ident_id'].'. Identitiy to use for sending:'.array2string($_identity));
		}
		$_mailObject->setFrom($_identity['ident_email'] ? $_identity['ident_email'] : $activeMailProfile['ident_email'],
			mail_bo::generateIdentityString($_identity,false));

		$_mailObject->addHeader('X-Priority', $_formData['priority']);
		$_mailObject->addHeader('X-Mailer', 'EGroupware-Mail');
		if(!empty($_formData['in-reply-to'])) {
			if (stripos($_formData['in-reply-to'],'<')===false) $_formData['in-reply-to']='<'.trim($_formData['in-reply-to']).'>';
			//error_log(__METHOD__.__LINE__.'$_mailObject->addHeader(In-Reply-To', $_formData['in-reply-to'].")");
			$_mailObject->addHeader('In-Reply-To', $_formData['in-reply-to']);
		}
		if(!empty($_formData['references'])) {
			if (stripos($_formData['references'],'<')===false) $_formData['references']='<'.trim($_formData['references']).'>';
			//error_log(__METHOD__.__LINE__.'$_mailObject->addHeader(References', $_formData['references'].")");
			$_mailObject->addHeader('References', $_formData['references']);
		}
		// thread-topic is a proprietary microsoft header and deprecated with the current version
		// horde does not support the encoding of thread-topic, and probably will not no so in the future
		//if(!empty($_formData['thread-topic']) && class_exists('Horde_Mime_Headers_ThreadTopic')) {
		//	//$_mailObject->addHeader('Thread-Topic', Horde_Mime::encode($_formData['thread-topic']));
		//	$_mailObject->addHeader('Thread-Topic', $_formData['thread-topic']);
		//}

		if(!empty($_formData['thread-index'])) {
			//error_log(__METHOD__.__LINE__.'$_mailObject->addHeader(Tread-Index', $_formData['thread-index'].")");
			$_mailObject->addHeader('Thread-Index', $_formData['thread-index']);
		}
		if(!empty($_formData['list-id'])) {
			//error_log(__METHOD__.__LINE__.'$_mailObject->addHeader(List-Id', $_formData['list-id'].")");
			$_mailObject->addHeader('List-Id', $_formData['list-id']);
		}
		//error_log(__METHOD__.__LINE__.' notify to:'.$_identity['ident_email'].'->'.array2string($_formData));
		if($_formData['disposition']=='on') {
			$_mailObject->addHeader('Disposition-Notification-To', $_identity['ident_email']);
		}
		//error_log(__METHOD__.__LINE__.' Organization:'.array2string($_identity));
		//if(!empty($_identity['ident_org'])) {
		//	$_mailObject->addHeader('Organization', $_identity['ident_org']);
		//}

		// Expand any mailing lists
		foreach(array('to', 'cc', 'bcc', 'replyto')  as $field)
		{
			if ($field != 'replyto') $_formData[$field] = self::resolveEmailAddressList($_formData[$field]);

			if ($_formData[$field]) $_mailObject->addAddress($_formData[$field], '', $field);
		}

		$_mailObject->addHeader('Subject', $_formData['subject']);

		// this should never happen since we come from the edit dialog
		if (mail_bo::detect_qp($_formData['body'])) {
			//error_log("Error: bocompose::createMessage found QUOTED-PRINTABLE while Composing Message. Charset:$realCharset Message:".print_r($_formData['body'],true));
			$_formData['body'] = preg_replace('/=\r\n/', '', $_formData['body']);
			$_formData['body'] = quoted_printable_decode($_formData['body']);
		}
		$disableRuler = false;
		$signature = $_identity['ident_signature'];
		/*
			Signature behavior preference changed. New default, if not set -> 0
					'0' => 'after reply, visible during compose',
					'1' => 'before reply, visible during compose',
					'no_belowaftersend'  => 'appended after reply before sending',
		*/
		$sigAlreadyThere = $this->mailPreferences['insertSignatureAtTopOfMessage']!='no_belowaftersend'?1:0;
		if ($sigAlreadyThere)
		{
			// note: if you use stationery ' s the insert signatures at the top does not apply here anymore, as the signature
			// is already part of the body, so the signature part of the template will not be applied.
			$signature = null; // note: no signature, no ruler!!!!
		}
		if ((isset($this->mailPreferences['disableRulerForSignatureSeparation']) &&
			$this->mailPreferences['disableRulerForSignatureSeparation']) ||
			empty($signature) || trim($this->convertHTMLToText($signature)) =='')
		{
			$disableRuler = true;
		}
		/* should be handled by identity object itself
		if($signature)
		{
			$signature = mail_bo::merge($signature,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
		}
		*/
		if ($_formData['attachments'] && $_formData['filemode'] != egw_sharing::ATTACH && !$_autosaving)
		{
			$attachment_links = $this->getAttachmentLinks($_formData['attachments'], $_formData['filemode'],
				$_formData['mimeType'] == 'html',
				array_unique(array_merge((array)$_formData['to'], (array)$_formData['cc'], (array)$_formData['bcc'])),
				$_formData['expiration'], $_formData['password']);
		}
		if($_formData['mimeType'] == 'html')
		{
			$body = $_formData['body'];
			if ($attachment_links)
			{
				if (strpos($body, '<!-- HTMLSIGBEGIN -->') !== false)
				{
					$body = str_replace('<!-- HTMLSIGBEGIN -->', $attachment_links.'<!-- HTMLSIGBEGIN -->', $body);
				}
				else
				{
					$body .= $attachment_links;
				}
			}
			if(!empty($signature))
			{
				$_mailObject->setBody($this->convertHTMLToText($body, true, true).
					($disableRuler ? "\r\n" : "\r\n-- \r\n").
					$this->convertHTMLToText($signature, true, true));

				$body .= ($disableRuler ?'<br>':'<hr style="border:1px dotted silver; width:90%;">').$signature;
			}
			else
			{
				$_mailObject->setBody($this->convertHTMLToText($body, true, true));
			}
			// convert URL Images to inline images - if possible
			if (!$_autosaving) mail_bo::processURL2InlineImages($_mailObject, $body, $mail_bo);
			if (strpos($body,"<!-- HTMLSIGBEGIN -->")!==false)
			{
				$body = str_replace(array('<!-- HTMLSIGBEGIN -->','<!-- HTMLSIGEND -->'),'',$body);
			}
			$_mailObject->setHtmlBody($body, null, false);	// false = no automatic alternative, we called setBody()
		}
		elseif ($_formData['mimeType'] == 'openpgp')
		{
			$_mailObject->setOpenPgpBody($_formData['body']);
		}
		else
		{
			$body = $this->convertHTMLToText($_formData['body'],false);

			if ($attachment_links) $body .= $attachment_links;

			#$_mailObject->Body = $_formData['body'];
			if(!empty($signature)) {
				$body .= ($disableRuler ?"\r\n":"\r\n-- \r\n").
					$this->convertHTMLToText($signature,true,true);
			}
			$_mailObject->setBody($body);
		}
		//error_log(__METHOD__.__LINE__.array2string($_formData['attachments']));
		// add the attachments
		if (is_array($_formData) && isset($_formData['attachments']))
		{
			$connection_opened = false;
			//error_log(__METHOD__.__LINE__.array2string($_formData['attachments']));
			$tnfattachments = null;
			foreach((array)$_formData['attachments'] as $attachment) {
				if(is_array($attachment))
				{
					if (!empty($attachment['uid']) && !empty($attachment['folder'])) {
						/* Example:
						Array([0] => Array(
						[uid] => 21178
						[partID] => 2
						[name] => [Untitled].pdf
						[type] => application/pdf
						[size] => 622379
						[folder] => INBOX))
						*/
						if (!$connection_opened)
						{
							$mail_bo->openConnection();
							$connection_opened = true;
						}
						$mail_bo->reopen($attachment['folder']);
						switch(strtoupper($attachment['type'])) {
							case 'MESSAGE/RFC':
							case 'MESSAGE/RFC822':
								$rawHeader='';
								if (isset($attachment['partID'])) {
									$rawHeader      = $mail_bo->getMessageRawHeader($attachment['uid'], $attachment['partID'],$attachment['folder']);
								}
								$rawBody        = $mail_bo->getMessageRawBody($attachment['uid'], $attachment['partID'],$attachment['folder']);
								$_mailObject->addStringAttachment($rawHeader.$rawBody, $attachment['name'], 'message/rfc822');
								break;
							default:
								$attachmentData	= $mail_bo->getAttachment($attachment['uid'], $attachment['partID'],0,false);
								if ($attachmentData['type'] == 'APPLICATION/MS-TNEF')
								{
									if (!is_array($tnfattachments)) $tnfattachments = $mail_bo->decode_winmail($attachment['uid'], $attachment['partID']);
									foreach ($tnfattachments as $k)
									{
										if ($k['name'] == $attachment['name'])
										{
											$tnfpart = $mail_bo->decode_winmail($attachment['uid'], $attachment['partID'],$k['is_winmail']);
											$attachmentData['attachment'] = $tnfpart['attachment'];
											//error_log(__METHOD__.__LINE__.$k['name'].'<->'.$attachment['name'].':'.array2string($attachmentData['attachment']));
											break;
										}
									}
								}
								$_mailObject->addStringAttachment($attachmentData['attachment'], $attachment['name'], $attachment['type']);
								break;
						}
					}
					// attach files not for autosaving
					elseif ($_formData['filemode'] == egw_sharing::ATTACH && !$_autosaving)
					{
						if (isset($attachment['file']) && parse_url($attachment['file'],PHP_URL_SCHEME) == 'vfs')
						{
							egw_vfs::load_wrapper('vfs');
							$tmp_path = $attachment['file'];
						}
						else	// non-vfs file has to be in temp_dir
						{
							$tmp_path = $GLOBALS['egw_info']['server']['temp_dir'].SEP.basename($attachment['file']);
						}
						$_mailObject->addAttachment (
							$tmp_path,
							$attachment['name'],
							$attachment['type']
						);
					}
				}
			}
			if ($connection_opened) $mail_bo->closeConnection();
		}
	}

	/**
	 * Get html or text containing links to attachments
	 *
	 * We only care about file attachments, not forwarded messages or parts
	 *
	 * @param array $attachments
	 * @param string $filemode egw_sharing::(ATTACH|LINK|READONL|WRITABLE)
	 * @param boolean $html
	 * @param array $recipients =array()
	 * @param string $expiration =null
	 * @param string $password =null
	 * @return string might be empty if no file attachments found
	 */
	protected function getAttachmentLinks(array $attachments, $filemode, $html, $recipients=array(), $expiration=null, $password=null)
	{
		if ($filemode == egw_sharing::ATTACH) return '';

		$links = array();
		foreach($attachments as $attachment)
		{
			$path = $attachment['file'];
			if (empty($path)) continue;	// we only care about file attachments, not forwarded messages or parts
			if (parse_url($attachment['file'],PHP_URL_SCHEME) != 'vfs')
			{
				$path = $GLOBALS['egw_info']['server']['temp_dir'].SEP.basename($path);
			}
			// create share
			if ($filemode == egw_sharing::WRITABLE || $expiration || $password)
			{
				$share = stylite_sharing::create($path, $filemode, $attachment['name'], $recipients, $expiration, $password);
			}
			else
			{
				$share = egw_sharing::create($path, $filemode, $attachment['name'], $recipients);
			}
			$link = egw_sharing::share2link($share);

			$name = egw_vfs::basename($attachment['name'] ? $attachment['name'] : $attachment['file']);

			if ($html)
			{
				$links[] = html::a_href($name, $link).' '.
					(is_dir($path) ? lang('Directory') : egw_vfs::hsize($attachment['size']));
			}
			else
			{
				$links[] = $name.' '.egw_vfs::hsize($attachment['size']).': '.
					(is_dir($path) ? lang('Directory') : $link);
			}
		}
		if (!$links)
		{
			return null;	// no file attachments found
		}
		elseif ($html)
		{
			return '<p>'.lang('Download attachments').":</p>\n<ul><li>".implode("</li>\n<li>", $links)."</li></ul>\n";
		}
		return lang('Download attachments').":\n- ".implode("\n- ", $links)."\n";
	}

	/**
	 * Save compose mail as draft
	 *
	 * @param array $content content sent from client-side
	 * @param string $action ='button[saveAsDraft]' 'autosaving', 'button[saveAsDraft]' or 'button[saveAsDraftAndPrint]'
	 */
	public function ajax_saveAsDraft ($content, $action='button[saveAsDraft]')
	{
		//error_log(__METHOD__.__LINE__.array2string($content)."(, action=$action)");
		$response = egw_json_response::get();
		$success = true;

		// check if default account is changed then we need to change profile
		if (!empty($content['serverID']) && $content['serverID'] != $this->mail_bo->profileID)
		{
			$this->changeProfile($content['serverID']);
		}

		$formData = array_merge($content, array(
			'isDrafted' => 1,
			'body' => $content['mail_'.($content['mimeType']?'htmltext':'plaintext')],
			'mimeType' => $content['mimeType']?'html':'plain' // checkbox has only true|false value
		));

		//Saving draft procedure
		try
		{
			$folder = $this->mail_bo->getDraftFolder();
			$this->mail_bo->reopen($folder);
			$status = $this->mail_bo->getFolderStatus($folder);
			if (($messageUid = $this->saveAsDraft($formData, $folder, $action)))
			{
				// saving as draft, does not mean closing the message
				$messageUid = ($messageUid===true ? $status['uidnext'] : $messageUid);
				if (is_array($this->mail_bo->getMessageHeader($messageUid, '',false, false, $folder)))
				{
					$draft_id = mail_ui::generateRowID($this->mail_bo->profileID, $folder, $messageUid);
					if ($content['lastDrafted'] != $draft_id && isset($content['lastDrafted']))
					{
						$dhA = mail_ui::splitRowID($content['lastDrafted']);
						$duid = $dhA['msgUID'];
						$dmailbox = $dhA['folder'];
						// beware: do not delete the original mail as found in processedmail_id
						$pMuid='';
						if ($content['processedmail_id'])
						{
							$pMhA = mail_ui::splitRowID($content['processedmail_id']);
							$pMuid = $pMhA['msgUID'];
						}
						//error_log(__METHOD__.__LINE__."#$pMuid#$pMuid!=$duid#".array2string($content['attachments']));
						// do not delete the original message if attachments are present
						if (empty($pMuid) || $pMuid!=$duid || empty($content['attachments']))
						{
							try
							{
								$this->mail_bo->deleteMessages($duid,$dmailbox,'remove_immediately');
							}
							catch (egw_exception $e)
							{
								$msg = str_replace('"',"'",$e->getMessage());
								$success = false;
								error_log(__METHOD__.__LINE__.$msg);
							}
						}
					}
				}
			}
			else
			{
				throw new egw_exception_wrong_userinput(lang("Error: Could not save Message as Draft"));
			}
		}
		catch (egw_exception_wrong_userinput $e)
		{
			$msg = str_replace('"',"'",$e->getMessage());
			error_log(__METHOD__.__LINE__.$msg);
			$success = false;
		}

		if ($success) $msg = lang('Message saved successfully.');

		// Include new information to json respose, because we need them in client-side callback
		$response->data(array(
			'draftedId' => $draft_id,
			'message' => $msg,
			'success' => $success,
			'draftfolder' => $this->mail_bo->profileID.mail_ui::$delimiter.$this->mail_bo->getDraftFolder()
		));
	}

	/**
	 * resolveEmailAddressList
	 * @param array $_emailAddressList list of emailaddresses, may contain distributionlists
	 * @return array return the list of emailaddresses with distributionlists resolved
	 */
	static function resolveEmailAddressList($_emailAddressList)
	{
		$addrFromList=array();
		foreach((array)$_emailAddressList as $ak => $address)
		{
			if(is_int($address))
			{
				// List was selected, expand to addresses
				unset($_emailAddressList[$ak]);
				$list = $GLOBALS['egw']->contacts->search('',array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home'),'','','',False,'AND',false,array('list' =>(int)$address));
				// Just add email addresses, they'll be checked below
				foreach($list as $email)
				{
					$addrFromList[] = $email['email'] ? $email['email'] : $email['email_home'];
				}
			}
		}
		if (!empty($addrFromList))
		{
			foreach ($addrFromList as $addr)
			{
				if (!empty($addr)) $_emailAddressList[]=$addr;
			}
		}
		return is_array($_emailAddressList) ? array_values($_emailAddressList) : (array)$_emailAddressList;
	}

	/**
	 * Save message as draft to specific folder
	 *
	 * @param array $_formData content
	 * @param string &$savingDestination ='' destination folder
	 * @param string $action ='button[saveAsDraft]' 'autosaving', 'button[saveAsDraft]' or 'button[saveAsDraftAndPrint]'
	 * @return boolean return messageUID| false due to an error
	 */
	function saveAsDraft($_formData, &$savingDestination='', $action='button[saveAsDraft]')
	{
		//error_log(__METHOD__."(..., $savingDestination, action=$action)");
		$mail_bo	= $this->mail_bo;
		$mail		= new egw_mailer($this->mail_bo->profileID);

		// preserve the bcc and if possible the save to folder information
		$this->sessionData['folder']    = $_formData['folder'];
		$this->sessionData['bcc']   = $_formData['bcc'];
		$this->sessionData['mailidentity'] = $_formData['mailidentity'];
		//$this->sessionData['stationeryID'] = $_formData['stationeryID'];
		$this->sessionData['mailaccount']  = $_formData['mailaccount'];
		$this->sessionData['attachments']  = $_formData['attachments'];
		try
		{
			$acc = emailadmin_account::read($this->sessionData['mailaccount']);
			//error_log(__METHOD__.__LINE__.array2string($acc));
			$identity = emailadmin_account::read_identity($acc['ident_id'],true);
		}
		catch (Exception $e)
		{
			$identity=array();
		}

		$flags = '\\Seen \\Draft';

		$this->createMessage($mail, $_formData, $identity, $action === 'autosaving');

		// folder list as Customheader
		if (!empty($this->sessionData['folder']))
		{
			$folders = implode('|',array_unique($this->sessionData['folder']));
			$mail->addHeader('X-Mailfolder', $folders);
		}
		$mail->addHeader('X-Mailidentity', $this->sessionData['mailidentity']);
		//$mail->addHeader('X-Stationery', $this->sessionData['stationeryID']);
		$mail->addHeader('X-Mailaccount', (int)$this->sessionData['mailaccount']);
		// decide where to save the message (default to draft folder, if we find nothing else)
		// if the current folder is in draft or template folder save it there
		// if it is called from printview then save it with the draft folder
		if (empty($savingDestination)) $savingDestination = $mail_bo->getDraftFolder();
		if (empty($this->sessionData['messageFolder']) && !empty($this->sessionData['mailbox']))
		{
			$this->sessionData['messageFolder'] = $this->sessionData['mailbox'];
		}
		if (!empty($this->sessionData['messageFolder']) && ($mail_bo->isDraftFolder($this->sessionData['messageFolder'])
			|| $mail_bo->isTemplateFolder($this->sessionData['messageFolder'])))
		{
			$savingDestination = $this->sessionData['messageFolder'];
			//error_log(__METHOD__.__LINE__.' SavingDestination:'.$savingDestination);
		}
		if (  !empty($_formData['printit']) && $_formData['printit'] == 0 ) $savingDestination = $mail_bo->getDraftFolder();

		// normaly Bcc is only added to recipients, but not as header visible to all recipients
		$mail->forceBccHeader();

		$mail_bo->openConnection();
		if ($mail_bo->folderExists($savingDestination,true)) {
			try
			{
				$messageUid = $mail_bo->appendMessage($savingDestination, $mail->getRaw(), null, $flags);
			}
			catch (egw_exception_wrong_userinput $e)
			{
				error_log(__METHOD__.__LINE__.lang("Save of message %1 failed. Could not save message to folder %2 due to: %3",__METHOD__,$savingDestination,$e->getMessage()));
				return false;
			}

		} else {
			error_log(__METHOD__.__LINE__."->".lang("folder")." ". $savingDestination." ".lang("does not exist on IMAP Server."));
			return false;
		}
		$mail_bo->closeConnection();
		return $messageUid;
	}

	function send($_formData)
	{
		$mail_bo	= $this->mail_bo;
		$mail 		= new egw_mailer($mail_bo->profileID);
		$messageIsDraft	=  false;

		$this->sessionData['mailaccount']	= $_formData['mailaccount'];
		$this->sessionData['to']	= self::resolveEmailAddressList($_formData['to']);
		$this->sessionData['cc']	= self::resolveEmailAddressList($_formData['cc']);
		$this->sessionData['bcc']	= self::resolveEmailAddressList($_formData['bcc']);
		$this->sessionData['folder']	= $_formData['folder'];
		$this->sessionData['replyto']	= $_formData['replyto'];
		$this->sessionData['subject']	= trim($_formData['subject']);
		$this->sessionData['body']	= $_formData['body'];
		$this->sessionData['priority']	= $_formData['priority'];
		$this->sessionData['mailidentity'] = $_formData['mailidentity'];
		//$this->sessionData['stationeryID'] = $_formData['stationeryID'];
		$this->sessionData['disposition'] = $_formData['disposition'];
		$this->sessionData['mimeType']	= $_formData['mimeType'];
		$this->sessionData['to_infolog'] = $_formData['to_infolog'];
		$this->sessionData['to_tracker'] = $_formData['to_tracker'];
		$this->sessionData['attachments']  = $_formData['attachments'];

		if (isset($_formData['lastDrafted']) && !empty($_formData['lastDrafted']))
		{
			$this->sessionData['lastDrafted'] = $_formData['lastDrafted'];
		}
		//error_log(__METHOD__.__LINE__.' Mode:'.$_formData['mode'].' PID:'.$_formData['processedmail_id']);
		if (isset($_formData['mode']) && !empty($_formData['mode']))
		{
			if ($_formData['mode']=='forward' && !empty($_formData['processedmail_id']))
			{
				$this->sessionData['forwardFlag']='forwarded';
				$_formData['processedmail_id'] = explode(',',$_formData['processedmail_id']);
				$this->sessionData['uid']=array();
				foreach ($_formData['processedmail_id'] as $k =>$rowid)
				{
					$fhA = mail_ui::splitRowID($rowid);
					$this->sessionData['uid'][] = $fhA['msgUID'];
					$this->sessionData['forwardedUID'][] = $fhA['msgUID'];
					if (!empty($fhA['folder'])) $this->sessionData['sourceFolder'] = $fhA['folder'];
				}
			}
			if ($_formData['mode']=='reply' && !empty($_formData['processedmail_id']))
			{
				$rhA = mail_ui::splitRowID($_formData['processedmail_id']);
				$this->sessionData['uid'] = $rhA['msgUID'];
				$this->sessionData['messageFolder'] = $rhA['folder'];
			}
			if ($_formData['mode']=='composefromdraft' && !empty($_formData['processedmail_id']))
			{
				$dhA = mail_ui::splitRowID($_formData['processedmail_id']);
				$this->sessionData['uid'] = $dhA['msgUID'];
				$this->sessionData['messageFolder'] = $dhA['folder'];
			}
		}
		// if the body is empty, maybe someone pasted something with scripts, into the message body
		// this should not happen anymore, unless you call send directly, since the check was introduced with the action command
		if(empty($this->sessionData['body']))
		{
			// this is to be found with the egw_unset_vars array for the _POST['body'] array
			$name='_POST';
			$key='body';
			#error_log($GLOBALS['egw_unset_vars'][$name.'['.$key.']']);
			if (isset($GLOBALS['egw_unset_vars'][$name.'['.$key.']']))
			{
				$this->sessionData['body'] = self::_getCleanHTML( $GLOBALS['egw_unset_vars'][$name.'['.$key.']']);
				$_formData['body']=$this->sessionData['body'];
			}
			#error_log($this->sessionData['body']);
		}
		if(empty($this->sessionData['to']) && empty($this->sessionData['cc']) &&
		   empty($this->sessionData['bcc']) && empty($this->sessionData['folder'])) {
		   	$messageIsDraft = true;
		}
		try
		{
			$identity = emailadmin_account::read_identity((int)$this->sessionData['mailidentity'],true);
		}
		catch (Exception $e)
		{
			$identity = array();
		}
		//error_log($this->sessionData['mailaccount']);
		//error_log(__METHOD__.__LINE__.':'.array2string($this->sessionData['mailidentity']).'->'.array2string($identity));
		// create the messages
		$this->createMessage($mail, $_formData, $identity);
		// remember the identity
		if ($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on') $fromAddress = $mail->FromName.($mail->FromName?' <':'').$mail->From.($mail->FromName?'>':'');
		#print "<pre>". $mail->getMessageHeader() ."</pre><hr><br>";
		#print "<pre>". $mail->getMessageBody() ."</pre><hr><br>";
		#exit;
		// check if there are folders to be used
		$folderToCheck = (array)$this->sessionData['folder'];
		$folder = array(); //for counting only
		$folderOnServerID = array();
		$folderOnMailAccount = array();
		foreach ($folderToCheck as $k => $f)
		{
			$fval=$f;
			$icServerID = $_formData['serverID'];//folders always assumed with serverID
			if (stripos($f,'::')!==false) list($icServerID,$fval) = explode('::',$f,2);
			if ($_formData['serverID']!=$_formData['mailaccount'])
			{
				if ($icServerID == $_formData['serverID'] )
				{
					$folder[$fval] = $fval;
					$folderOnServerID[] = $fval;
				}
				if ($icServerID == $_formData['mailaccount'])
				{
					$folder[$fval] = $fval;
					$folderOnMailAccount[] = $fval;
				}
			}
			else
			{
				if ($icServerID == $_formData['serverID'] )
				{
					$folder[$fval] = $fval;
					$folderOnServerID[] = $fval;
				}
			}
		}
		//error_log(__METHOD__.__LINE__.'#'.array2string($_formData['serverID']).'<serverID<->mailaccount>'.array2string($_formData['mailaccount']));
		// serverID ($_formData['serverID']) specifies where we originally came from.
		// mailaccount ($_formData['mailaccount']) specifies the mailaccount we send from and where the sent-copy should end up
		// serverID : is or may be needed to mark a mail as replied/forwarded or delete the original draft.
		// all other folders are tested against serverID that is carried with the foldername ID::Foldername; See above
		// (we work the folder from formData into folderOnMailAccount and folderOnServerID)
		// right now only folders from serverID or mailaccount should be selectable in compose form/dialog
		// we use the sentFolder settings of the choosen mailaccount
		// sentFolder is account specific
		$changeProfileOnSentFolderNeeded = false;
		if ($_formData['serverID']!=$_formData['mailaccount'])
		{
			$this->changeProfile($_formData['mailaccount']);
			//error_log(__METHOD__.__LINE__.'#'.$this->mail_bo->profileID.'<->'.$mail_bo->profileID.'#');
			$changeProfileOnSentFolderNeeded = true;
			// sentFolder is account specific
			$sentFolder = $this->mail_bo->getSentFolder();
			//error_log(__METHOD__.__LINE__.' SentFolder configured:'.$sentFolder.'#');
			if ($sentFolder&& $sentFolder!= 'none' && !$this->mail_bo->folderExists($sentFolder, true)) $sentFolder=false;
		}
		else
		{
			$sentFolder = $mail_bo->getSentFolder();
			//error_log(__METHOD__.__LINE__.' SentFolder configured:'.$sentFolder.'#');
			if ($sentFolder&& $sentFolder!= 'none' && !$mail_bo->folderExists($sentFolder, true)) $sentFolder=false;
		}
		//error_log(__METHOD__.__LINE__.' SentFolder configured:'.$sentFolder.'#');

		// we switch $this->mail_bo back to the account we used to work on
		if ($_formData['serverID']!=$_formData['mailaccount'])
		{
			$this->changeProfile($_formData['serverID']);
		}


		if(isset($sentFolder) && $sentFolder && $sentFolder != 'none' &&
			$this->mailPreferences['sendOptions'] != 'send_only' &&
			$messageIsDraft == false)
		{
			if ($sentFolder)
			{
				if ($_formData['serverID']!=$_formData['mailaccount'])
				{
					$folderOnMailAccount[] = $sentFolder;
				}
				else
				{
					$folderOnServerID[] = $sentFolder;
				}
				$folder[$sentFolder] = $sentFolder;
			}
			else
			{
				$this->errorInfo = lang("No (valid) Send Folder set in preferences");
			}
		}
		else
		{
			if (((!isset($sentFolder)||$sentFolder==false) && $this->mailPreferences['sendOptions'] != 'send_only') ||
				($this->mailPreferences['sendOptions'] != 'send_only' &&
				$sentFolder != 'none')) $this->errorInfo = lang("No Send Folder set in preferences");
		}
		// draftFolder is on Server we start from
		if($messageIsDraft == true) {
			$draftFolder = $mail_bo->getDraftFolder();
			if(!empty($draftFolder) && $mail_bo->folderExists($draftFolder,true)) {
				$this->sessionData['folder'] = array($draftFolder);
				$folderOnServerID[] = $draftFolder;
				$folder[$draftFolder] = $draftFolder;
			}
		}
		if ($folderOnServerID) $folderOnServerID = array_unique($folderOnServerID);
		if ($folderOnMailAccount) $folderOnMailAccount = array_unique($folderOnMailAccount);
		if (($this->mailPreferences['sendOptions'] != 'send_only' && $sentFolder != 'none') &&
			!( count($folder) > 0) &&
			!($_formData['to_infolog']=='on' || $_formData['to_tracker']=='on'))
		{
			$this->errorInfo = lang("Error: ").lang("No Folder destination supplied, and no folder to save message or other measure to store the mail (save to infolog/tracker) provided, but required.").($this->errorInfo?' '.$this->errorInfo:'');
			#error_log($this->errorInfo);
			return false;
		}

		// set a higher timeout for big messages
		@set_time_limit(120);
		//$mail->SMTPDebug = 10;
		//error_log("Folder:".count(array($this->sessionData['folder']))."To:".count((array)$this->sessionData['to'])."CC:". count((array)$this->sessionData['cc']) ."bcc:".count((array)$this->sessionData['bcc']));
		if(count((array)$this->sessionData['to']) > 0 || count((array)$this->sessionData['cc']) > 0 || count((array)$this->sessionData['bcc']) > 0) {
			try {
				$mail->send();
			}
			catch(Exception $e) {
				_egw_log_exception($e);
				$this->errorInfo = $e->getMessage();
				return false;
			}
		} else {
			if (count(array($this->sessionData['folder']))>0 && !empty($this->sessionData['folder'])) {
				//error_log(__METHOD__.__LINE__."Folders:".print_r($this->sessionData['folder'],true));
			} else {
				$this->errorInfo = lang("Error: ").lang("No Address TO/CC/BCC supplied, and no folder to save message to provided.");
				//error_log(__METHOD__.__LINE__.$this->errorInfo);
				return false;
			}
		}
		//error_log(__METHOD__.__LINE__."Mail Sent.!");
		//error_log(__METHOD__.__LINE__."Number of Folders to move copy the message to:".count($folder));
		//error_log(__METHOD__.__LINE__.array2string($folder));
		if ((count($folder) > 0) || (isset($this->sessionData['uid']) && isset($this->sessionData['messageFolder']))
            || (isset($this->sessionData['forwardFlag']) && isset($this->sessionData['sourceFolder']))) {
			$mail_bo = $this->mail_bo;
			$mail_bo->openConnection();
			//$mail_bo->reopen($this->sessionData['messageFolder']);
			#error_log("(re)opened Connection");
		}
		// if copying mail to folder, or saving mail to infolog, we need to gather the needed information
		if (count($folder) > 0 || $_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on') {
			//error_log(__METHOD__.__LINE__.array2string($this->sessionData['bcc']));

			// normaly Bcc is only added to recipients, but not as header visible to all recipients
			$mail->forceBccHeader();
		}
		// copying mail to folder
		if (count($folder) > 0)
		{
			foreach($folderOnServerID as $folderName) {
				if (is_array($folderName)) $folderName = array_shift($folderName); // should not happen at all
				//error_log(__METHOD__.__LINE__." attempt to save message to:".array2string($folderName));
				// if $_formData['serverID']!=$_formData['mailaccount'] skip copying to sentfolder on serverID
				// if($_formData['serverID']!=$_formData['mailaccount'] && $folderName==$sentFolder && $changeProfileOnSentFolderNeeded) continue;
				if ($mail_bo->folderExists($folderName,true)) {
					if($mail_bo->isSentFolder($folderName)) {
						$flags = '\\Seen';
					} elseif($mail_bo->isDraftFolder($folderName)) {
						$flags = '\\Draft';
					} else {
						$flags = '\\Seen';
					}
					#$mailHeader=explode('From:',$mail->getMessageHeader());
					#$mailHeader[0].$mail->AddrAppend("Bcc",$mailAddr).'From:'.$mailHeader[1],
					//error_log(__METHOD__.__LINE__." Cleared FolderTests; Save Message to:".array2string($folderName));
					//$mail_bo->reopen($folderName);
					try
					{
						//error_log(__METHOD__.__LINE__.array2string($folderName));
						$mail_bo->appendMessage($folderName, $mail->getRaw(), null, $flags);
					}
					catch (egw_exception_wrong_userinput $e)
					{
						error_log(__METHOD__.__LINE__.'->'.lang("Import of message %1 failed. Could not save message to folder %2 due to: %3",$this->sessionData['subject'],$folderName,$e->getMessage()));
					}
				}
				else
				{
					error_log(__METHOD__.__LINE__.'->'.lang("Import of message %1 failed. Destination Folder %2 does not exist.",$this->sessionData['subject'],$folderName));
				}
			}
			// if we choose to send from a differing profile
			if ($folderOnMailAccount)  $this->changeProfile($_formData['mailaccount']);
			foreach($folderOnMailAccount as $folderName) {
				if (is_array($folderName)) $folderName = array_shift($folderName); // should not happen at all
				//error_log(__METHOD__.__LINE__." attempt to save message to:".array2string($folderName));
				// if $_formData['serverID']!=$_formData['mailaccount'] skip copying to sentfolder on serverID
				// if($_formData['serverID']!=$_formData['mailaccount'] && $folderName==$sentFolder && $changeProfileOnSentFolderNeeded) continue;
				if ($this->mail_bo->folderExists($folderName,true)) {
					if($this->mail_bo->isSentFolder($folderName)) {
						$flags = '\\Seen';
					} elseif($this->mail_bo->isDraftFolder($folderName)) {
						$flags = '\\Draft';
					} else {
						$flags = '\\Seen';
					}
					#$mailHeader=explode('From:',$mail->getMessageHeader());
					#$mailHeader[0].$mail->AddrAppend("Bcc",$mailAddr).'From:'.$mailHeader[1],
					//error_log(__METHOD__.__LINE__." Cleared FolderTests; Save Message to:".array2string($folderName));
					//$mail_bo->reopen($folderName);
					try
					{
						//error_log(__METHOD__.__LINE__.array2string($folderName));
						$this->mail_bo->appendMessage($folderName, $mail->getRaw(), null, $flags);
					}
					catch (egw_exception_wrong_userinput $e)
					{
						error_log(__METHOD__.__LINE__.'->'.lang("Import of message %1 failed. Could not save message to folder %2 due to: %3",$this->sessionData['subject'],$folderName,$e->getMessage()));
					}
				}
				else
				{
					error_log(__METHOD__.__LINE__.'->'.lang("Import of message %1 failed. Destination Folder %2 does not exist.",$this->sessionData['subject'],$folderName));
				}
			}
			if ($folderOnMailAccount)  $this->changeProfile($_formData['serverID']);

			//$mail_bo->closeConnection();
		}
		// handle previous drafted versions of that mail
		$lastDrafted = false;
		if (isset($this->sessionData['lastDrafted']))
		{
			$lastDrafted=array();
			$dhA = mail_ui::splitRowID($this->sessionData['lastDrafted']);
			$lastDrafted['uid'] = $dhA['msgUID'];
			$lastDrafted['folder'] = $dhA['folder'];
			if (isset($lastDrafted['uid']) && !empty($lastDrafted['uid'])) $lastDrafted['uid']=trim($lastDrafted['uid']);
			// manually drafted, do not delete
			// will be handled later on IF mode was $_formData['mode']=='composefromdraft'
			if (isset($lastDrafted['uid']) && (empty($lastDrafted['uid']) || $lastDrafted['uid'] == $this->sessionData['uid'])) $lastDrafted=false;
			//error_log(__METHOD__.__LINE__.array2string($lastDrafted));
		}
		if ($lastDrafted && is_array($lastDrafted) && $mail_bo->isDraftFolder($lastDrafted['folder']))
		{
			try
			{
				$mail_bo->deleteMessages($lastDrafted['uid'],$lastDrafted['folder'],'remove_immediately');
			}
			catch (egw_exception $e)
			{
				//error_log(__METHOD__.__LINE__." ". str_replace('"',"'",$e->getMessage()));
				unset($e);
			}
		}
		unset($this->sessionData['lastDrafted']);

		//error_log("handling draft messages, flagging and such");
		if((isset($this->sessionData['uid']) && isset($this->sessionData['messageFolder']))
			|| (isset($this->sessionData['forwardFlag']) && isset($this->sessionData['sourceFolder']))) {
			// mark message as answered
			$mail_bo->openConnection();
			$mail_bo->reopen(($this->sessionData['messageFolder']?$this->sessionData['messageFolder']:$this->sessionData['sourceFolder']));
			// if the draft folder is a starting part of the messages folder, the draft message will be deleted after the send
			// unless your templatefolder is a subfolder of your draftfolder, and the message is in there
			if ($mail_bo->isDraftFolder($this->sessionData['messageFolder']) && !$mail_bo->isTemplateFolder($this->sessionData['messageFolder']))
			{
				//error_log(__METHOD__.__LINE__."#".$this->sessionData['uid'].'#'.$this->sessionData['messageFolder']);
				try // message may be deleted already, as it maybe done by autosave
				{
					if ($_formData['mode']=='composefromdraft') $mail_bo->deleteMessages(array($this->sessionData['uid']),$this->sessionData['messageFolder']);
				}
				catch (egw_exception $e)
				{
					//error_log(__METHOD__.__LINE__." ". str_replace('"',"'",$e->getMessage()));
					unset($e);
				}
			} else {
				$mail_bo->flagMessages("answered", $this->sessionData['uid'],($this->sessionData['messageFolder']?$this->sessionData['messageFolder']:$this->sessionData['sourceFolder']));
				//error_log(__METHOD__.__LINE__.array2string(array_keys($this->sessionData)).':'.array2string($this->sessionData['forwardedUID']).' F:'.$this->sessionData['sourceFolder']);
				if (array_key_exists('forwardFlag',$this->sessionData) && $this->sessionData['forwardFlag']=='forwarded')
				{
					try
					{
						//error_log(__METHOD__.__LINE__.':'.array2string($this->sessionData['forwardedUID']).' F:'.$this->sessionData['sourceFolder']);
						$mail_bo->flagMessages("forwarded", $this->sessionData['forwardedUID'],$this->sessionData['sourceFolder']);
					}
					catch (egw_exception $e)
					{
						//error_log(__METHOD__.__LINE__." ". str_replace('"',"'",$e->getMessage()));
						unset($e);
					}
				}
			}
			//$mail_bo->closeConnection();
		}
		if ($mail_bo) $mail_bo->closeConnection();
		//error_log("performing Infolog Stuff");
		//error_log(print_r($this->sessionData['to'],true));
		//error_log(print_r($this->sessionData['cc'],true));
		//error_log(print_r($this->sessionData['bcc'],true));
		if (is_array($this->sessionData['to']))
		{
			$mailaddresses['to'] = $this->sessionData['to'];
		}
		else
		{
			$mailaddresses = array();
		}
		if (is_array($this->sessionData['cc'])) $mailaddresses['cc'] = $this->sessionData['cc'];
		if (is_array($this->sessionData['bcc'])) $mailaddresses['bcc'] = $this->sessionData['bcc'];
		if (!empty($mailaddresses)) $mailaddresses['from'] = $GLOBALS['egw']->translation->decodeMailHeader($fromAddress);

		if ($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on' || $_formData['to_calendar'] == 'on' )
		{
			foreach(array('to_infolog','to_tracker','to_calendar') as $app_key)
			{
				if ($_formData[$app_key] == 'on')
				{
					$app_name = substr($app_key,3);
					// Get registered hook data of the app called for integration
					$hook = $GLOBALS['egw']->hooks->single(array('location'=> 'mail_import'),$app_name);

					// store mail / eml in temp. file to not have to download it from mail-server again
					$eml = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'mail_integrate');
					$eml_fp = fopen($eml, 'w');
					stream_copy_to_stream($mail->getRaw(), $eml_fp);
					fclose($eml_fp);

					// Open the app called for integration in a popup
					// and store the mail raw data as egw_data, in order to
					// be stored from registered app method later
					egw_framework::popup(egw::link('/index.php', array(
						'menuaction' => $hook['menuaction'],
						'egw_data' => egw_link::set_data(null,'mail_integration::integrate',array(
							$mailaddresses,
							$this->sessionData['subject'],
							$this->convertHTMLToText($this->sessionData['body']),
							$this->sessionData['attachments'],
							false, // date
							$eml,
							$_formData['serverID']),true),
						'app' => $app_name
					)),'_blank',$hook['popup']);
				}
			}
		}
		// only clean up temp-files, if we dont need them for mail_integration::integrate
		elseif(is_array($this->sessionData['attachments']))
		{
			foreach($this->sessionData['attachments'] as $value) {
				if (!empty($value['file']) && parse_url($value['file'],PHP_URL_SCHEME) != 'vfs') {	// happens when forwarding mails
					unlink($GLOBALS['egw_info']['server']['temp_dir'].'/'.$value['file']);
				}
			}
		}

		$this->sessionData = '';

		return true;
	}

	/**
	 * setDefaults, sets some defaults
	 *
	 * @param array $content
	 * @return array - the input, enriched with some not set attributes
	 */
	function setDefaults($content=array())
	{
		// if there's not already an identity selected for current account
		if (empty($content['mailidentity']))
		{
			// check if there a preference / previous selection of identity for current account
			if (!empty($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed']))
			{
				$sigPref = $GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'];
				if (!empty($sigPref[$this->mail_bo->profileID]) && $sigPref[$this->mail_bo->profileID]>0)
				{
					$content['mailidentity'] = $sigPref[$this->mail_bo->profileID];
				}
			}
			// if we have no preference search for first identity with non-empty signature
			if (empty($content['mailidentity']))
			{
				$default_identity = null;
				foreach(emailadmin_account::identities($this->mail_bo->profileID, true, 'params') as $identity)
				{
					if (!isset($default_identity)) $default_identity = $identity['ident_id'];
					if (!empty($identity['ident_signature']))
					{
						$content['mailidentity'] = $identity['ident_id'];
						break;
					}
				}
			}
			if (empty($content['mailidentity'])) $content['mailidentity'] = $default_identity;
		}
		if (!isset($content['mimeType']) || empty($content['mimeType']))
		{
			$content['mimeType'] = 'html';
			if (!empty($this->mailPreferences['composeOptions']) && $this->mailPreferences['composeOptions']=="text") $content['mimeType']  = 'plain';
		}
		return $content;

	}

	function stripSlashes($_string)
	{
		if (get_magic_quotes_gpc()) {
			return stripslashes($_string);
		} else {
			return $_string;
		}
	}
	/**
	 * Callback function to search mail folders
	 *
	 * @param int $_searchStringLength
	 * @param boolean $_returnList
	 * @param int $_mailaccountToSearch
	 * @param boolean $_noPrefixId = false, if set to true folders name does not get prefixed by account id
	 * @return type
	 */
	function ajax_searchFolder($_searchStringLength=2, $_returnList=false, $_mailaccountToSearch=null, $_noPrefixId=false) {
		//error_log(__METHOD__.__LINE__.':'.array2string($_REQUEST));
		static $useCacheIfPossible = null;
		if (is_null($useCacheIfPossible)) $useCacheIfPossible = true;
		$_searchString = trim($_REQUEST['query']);
		$results = array();
		$rememberServerID = $this->mail_bo->icServer->ImapServerId;
		if (is_null($_mailaccountToSearch) && !empty($_REQUEST['mailaccount'])) $_mailaccountToSearch = $_REQUEST['mailaccount'];
		if (empty($_mailaccountToSearch)) $_mailaccountToSearch = $this->mail_bo->icServer->ImapServerId;
		if ($this->mail_bo->icServer && $_mailaccountToSearch && $this->mail_bo->icServer->ImapServerId != $_mailaccountToSearch)
		{
			$this->changeProfile($_mailaccountToSearch);
		}
		if (strlen($_searchString)>=$_searchStringLength && isset($this->mail_bo->icServer))
		{
			//error_log(__METHOD__.__LINE__.':'.$this->mail_bo->icServer->ImapServerId);
			$this->mail_bo->openConnection($this->mail_bo->icServer->ImapServerId);
			//error_log(__METHOD__.__LINE__.array2string($_searchString).'<->'.$searchString);
			$folderObjects = $this->mail_bo->getFolderObjects(true,false,true,$useCacheIfPossible);
			if (count($folderObjects)<=1) {
				$useCacheIfPossible = false;
			}
			else
			{
				$useCacheIfPossible = true;
			}
			$searchString = translation::convert($_searchString, mail_bo::$displayCharset,'UTF7-IMAP');
			foreach ($folderObjects as $k =>$fA)
			{
				//error_log(__METHOD__.__LINE__.$_searchString.'/'.$searchString.' in '.$k.'->'.$fA->displayName);
				$f=false;
				$key = $_noPrefixId?$k:$_mailaccountToSearch.'::'.$k;
				if ($_searchStringLength<=0)
				{
					$f=true;
					$results[] = array('id'=>$key, 'label' => htmlspecialchars($fA->displayName));
				}
				if ($f==false && stripos($fA->displayName,$_searchString)!==false)
				{
					$f=true;
					$results[] = array('id'=>$key, 'label' => htmlspecialchars($fA->displayName));
				}
				if ($f==false && stripos($k,$searchString)!==false)
				{
					$results[] = array('id'=>$key, 'label' => htmlspecialchars($fA->displayName));
				}
			}
		}
		if ($this->mail_bo->icServer && $rememberServerID != $this->mail_bo->icServer->ImapServerId)
		{
			$this->changeProfile($rememberServerID);
		}
		//error_log(__METHOD__.__LINE__.' IcServer:'.$this->mail_bo->icServer->ImapServerId.':'.array2string($results));
		if ($_returnList)
		{
			foreach ((array)$results as $k => $_result)
			{
				$rL[$_result['id']] = $_result['label'];
			}
			return $rL;
		}
		// switch regular JSON response handling off
		egw_json_request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		//error_log(__METHOD__.__LINE__);
		echo json_encode($results);
		common::egw_exit();
	}

	public static function ajax_searchAddress($_searchStringLength=2) {
		//error_log(__METHOD__. "request from seachAddress " . $_REQUEST['query']);
		$_searchString = trim($_REQUEST['query']);
		$include_lists = (boolean)$_REQUEST['include_lists'];

		if ($GLOBALS['egw_info']['user']['apps']['addressbook'] && strlen($_searchString)>=$_searchStringLength)
		{
			//error_log(__METHOD__.__LINE__.array2string($_searchString));
			$showAccounts = empty($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']);
			$search = explode(' ', $_searchString);
			foreach ($search as $k => $v)
			{
				if (mb_strlen($v) < 3) unset($search[$k]);
			}
			$search_str = implode(' +', $search);	// tell contacts/so_sql to AND search patterns
			//error_log(__METHOD__.__LINE__.$_searchString);
			$filter = $showAccounts ? array() : array('account_id' => null);
			$filter['cols_to_search'] = array('n_prefix','n_given','n_family','org_name','email','email_home');
			$cols = array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home');
			$contacts = $GLOBALS['egw']->contacts->search($search_str, $cols, 'n_fn', '', '%', false, 'OR', array(0,100), $filter);
			// additionally search the accounts, if the contact storage is not the account storage
			if ($showAccounts && $GLOBALS['egw']->contacts->so_accounts)
			{
				$filter['owner'] = 0;
				$accounts = $GLOBALS['egw']->contacts->search($search_str, $cols, 'n_fn', '', '%', false,'OR', array(0,100), $filter);

				if ($contacts && $accounts)
				{
					$contacts = array_merge($contacts,$accounts);
					usort($contacts,function($a, $b)
					{
						return strcasecmp($a['n_fn'], $b['n_fn']);
					});
				}
				elseif($accounts)
				{
					$contacts =& $accounts;
				}
				unset($accounts);
			}
		}
		$results = array();
		if(is_array($contacts)) {
			foreach($contacts as $contact) {
				foreach(array($contact['email'],$contact['email_home']) as $email) {
					// avoid wrong addresses, if an rfc822 encoded address is in addressbook
					$email = preg_replace("/(^.*<)([a-zA-Z0-9_\-]+@[a-zA-Z0-9_\-\.]+)(.*)/",'$2',$email);
					if (method_exists($GLOBALS['egw']->contacts,'search'))
					{
						$contact['n_fn']='';
						if (!empty($contact['n_prefix'])) $contact['n_fn'] = $contact['n_prefix'];
						if (!empty($contact['n_given'])) $contact['n_fn'] .= ($contact['n_fn']?' ':'').$contact['n_given'];
						if (!empty($contact['n_family'])) $contact['n_fn'] .= ($contact['n_fn']?' ':'').$contact['n_family'];
						if (!empty($contact['org_name'])) $contact['n_fn'] .= ($contact['n_fn']?' ':'').'('.$contact['org_name'].')';
						$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
					}
					else
					{
						$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
					}
					$completeMailString = trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']) .' <'. trim($email) .'>';
					if(!empty($email) && in_array($completeMailString ,$results) === false) {
						$results[] = array(
							'id'=>$completeMailString,
							'label' => $completeMailString,
							// Add just name for nice display, with title for hover
							'name' => $contact['n_fn'],
							'title' => $email
						 );
					}
				}
			}
		}

		// Add groups
		$group_options = array('account_type' => 'groups');
		$groups = $GLOBALS['egw']->accounts->link_query($_searchString, $group_options);
		foreach($groups as $g_id => $name)
		{
			$group = $GLOBALS['egw']->accounts->read($g_id);
			if(!$group['account_email']) continue;
			$completeMailString = trim($name) .' <'. trim($group['account_email']) .'>';
			$results[] = array(
				'id' => $completeMailString,
				'label' => $completeMailString,
				'name'	=> $name,
				'title' => $group['account_email']
			);
		}

		// Add up to 5 matching mailing lists
		if($include_lists)
		{
			$lists = array_filter(
				$GLOBALS['egw']->contacts->get_lists(EGW_ACL_READ),
				function($element) use($_searchString) {
					return (stripos($element, $_searchString) !== false);
				}
			);
			$list_count = 0;
			foreach($lists as $key => $list_name)
			{
				$results[] = array(
					'id'	=> $key,
					'name'	=> $list_name,
					'label'	=> $list_name,
					'class' => 'mailinglist',
					'title' => lang('Mailinglist'),
				);
				if($list_count++ > 5) break;
			}
		}
		 // switch regular JSON response handling off
		egw_json_request::isJSONRequest(false);

		//error_log(__METHOD__.__LINE__.array2string($jsArray));
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		common::egw_exit();
	}

	/**
	 * Merge the selected contact ID into the document given in $_REQUEST['document']
	 * and send it.
	 *
	 * @param int $contact_id
	 */
	public function ajax_merge($contact_id)
	{
		$response = egw_json_response::get();
		$document_merge = new addressbook_merge();
		$this->mail_bo->openConnection();

		if(($error = $document_merge->check_document($_REQUEST['document'],'')))
		{
			$response->error($error);
			return;
		}

		// Merge does not work correctly (missing to) if current app is not addressbook
		//$GLOBALS['egw_info']['flags']['currentapp'] = 'addressbook';

		// Actually do the merge
		$folder = $merged_mail_id = null;
		$results = $this->mail_bo->importMessageToMergeAndSend(
			$document_merge, egw_vfs::PREFIX . $_REQUEST['document'],
			// Send an extra non-numeric ID to force actual send of document
			// instead of save as draft
			array((int)$contact_id, ''),
			$folder,$merged_mail_id
		);

		if($results['success'])
		{
			$response->data(implode(',',$results['success']));
		}
		if($results['failed'])
		{
			$response->error(implode(',',$results['failed']));
		}
	}

	/**
	 * Wrapper for etemplate_new::ajax_process_content to be able to identify send request to select different fpm pool
	 *
	 * @param string $etemplate_exec_id
	 * @param array $content
	 * @param boolean $no_validation
	 * @throws egw_exception_wrong_parameter
	 */
	static public function ajax_send($etemplate_exec_id, array $content, $no_validation)
	{
		// setting menuaction is required as it triggers different behavior eg. in egw_framework::window_close()
		$_GET['menuaction'] = 'etemplate_new::ajax_process_content';

		return etemplate_new::ajax_process_content($etemplate_exec_id, $content, $no_validation);
	}
}
