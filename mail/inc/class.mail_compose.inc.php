<?php
/**
 * EGroupware - Mail - interface class for compose mails in popup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2013-2016 by EGroupware GmbH <info-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;
use EGroupware\Api\Link;
use EGroupware\Api\Mail;
use EGroupware\Api\Vfs;

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
	 * Instance of Mail
	 *
	 * @var Mail
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
		$this->displayCharset   = Api\Translation::charset();

		$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		$this->mail_bo	= Mail::getInstance(true,$profileID);
		$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $this->mail_bo->profileID;

		$this->mailPreferences	=& $this->mail_bo->mailPreferences;
		//force the default for the forwarding -> asmail
		if (!is_array($this->mailPreferences) || empty($this->mailPreferences['message_forwarding']))
		{
			$this->mailPreferences['message_forwarding'] = 'asmail';
		}
		if (is_null(Mail::$mailConfig)) Mail::$mailConfig = Api\Config::read('mail');

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
			if (Mail::$debug) error_log(__METHOD__.__LINE__.'->'.$this->mail_bo->profileID.'<->'.$_icServerID);
			$this->mail_bo = Mail::getInstance(false,$_icServerID);
			if (Mail::$debug) error_log(__METHOD__.__LINE__.' Fetched IC Server:'.$this->mail_bo->profileID.':'.function_backtrace());
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
	static function getToolbarActions($content)
	{
		$group = 0;
		$actions = array(
			'send' => array(
				'caption' => 'Send',
				'icon'	=> 'mail_send',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose_submitAction',
				'hint' => 'Send',
				'shortcut' => array('ctrl' => true, 'keyCode' => 83, 'caption' => 'Ctrl + S'),
				'toolbarDefault' => true
			),
			'button[saveAsDraft]' => array(
				'caption' => 'Save',
				'icon' => 'apply',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.saveAsDraft',
				'hint' => 'Save as Draft',
				'toolbarDefault' => true
			),
			'button[saveAsDraftAndPrint]' => array(
				'caption' => 'Print',
				'icon' => 'print',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.saveAsDraft',
				'hint' => 'Save as Draft and Print'
			),
			'save2vfs' => array (
				'caption' => 'Save to filemanager',
				'icon' => 'filesave',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.compose_saveDraft2fm',
				'hint' => 'Save the drafted message as eml file into VFS'
			),
			'selectFromVFSForCompose' => array(
				'caption' => 'VFS',
				'icon' => 'filemanager/navbar',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose_triggerWidget',
				'hint' => 'Select file(s) from VFS',
				'toolbarDefault' => true
			),
			'uploadForCompose' => array(
				'caption' => 'Upload files...',
				'icon' => 'attach',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.compose_triggerWidget',
				'hint' => 'Select files to upload',
				'toolbarDefault' => true
			),
			'to_infolog' => array(
				'caption' => 'Infolog',
				'icon' => 'infolog/navbar',
				'group' => ++$group,
				'checkbox' => true,
				'hint' => 'check to save as infolog on send',
				'toolbarDefault' => true,
				'onExecute' => 'javaScript:app.mail.compose_setToggle'
			),
			'to_tracker' => array(
				'caption' => 'Tracker',
				'icon' => 'tracker/navbar',
				'group' => $group,
				'checkbox' => true,
				'hint' => 'check to save as tracker entry on send',
				'onExecute' => 'javaScript:app.mail.compose_setToggle',
				'mail_import' => Api\Hooks::single(array('location' => 'mail_import'),'tracker'),
			),
			'to_calendar' => array(
				'caption' => 'Calendar',
				'icon' => 'calendar/navbar',
				'group' => $group,
				'checkbox' => true,
				'hint' => 'check to save as calendar event on send',
				'onExecute' => 'javaScript:app.mail.compose_setToggle'
			),
			'disposition' => array(
				'caption' => 'Notification',
				'icon' => 'notification',
				'group' => ++$group,
				'checkbox' => true,
				'hint' => 'check to receive a notification when the message is read (note: not all clients support this and/or the receiver may not authorize the notification)',
				'onExecute' => 'javaScript:app.mail.compose_setToggle'
			),
			'prty' => array(
				'caption' => 'Priority',
				'group' => $group,
				'icon' => 'priority',
				'children' => array(),
				'hint' => 'Select the message priority tag',
			),
			'pgp' => array(
				'caption' => 'Encrypt',
				'icon' => 'lock',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.togglePgpEncrypt',
				'hint' => 'Send message PGP encrypted: requires keys from all recipients!',
				'checkbox' => true,
				'toolbarDefault' => true
			),

		);
		$acc_smime = Mail\Smime::get_acc_smime($content['mailaccount']);
		if ($acc_smime && !empty($acc_smime['acc_smime_password']))
		{
			$actions = array_merge($actions, array(
				'smime_sign' => array (
					'caption' => 'SMIME Sign',
					'icon' => 'smime_sign',
					'group' => ++$group,
					'onExecute' => 'javaScript:app.mail.compose_setToggle',
					'checkbox' => true,
					'hint' => 'Sign your message with smime certificate'
				),
				'smime_encrypt' => array (
					'caption' => 'SMIME Encryption',
					'icon' => 'smime_encrypt',
					'group' => $group,
					'onExecute' => 'javaScript:app.mail.compose_setToggle',
					'checkbox' => true,
					'hint' => 'Encrypt your message with smime certificate'
			)));
		}
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
					$actions['prty']['children'][$key]['icon'] = 'priority';
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
		if (Api\Header\UserAgent::mobile())
		{
			foreach (array_keys($actions) as $key)
			{
				if (!in_array($key, array('send','button[saveAsDraft]','uploadForCompose' ))) {
					$actions[$key]['toolbarDefault'] = false;
				}
			}
			unset($actions['pgp']);
		}
		if (!empty($GLOBALS['egw_info']['server']['disable_pgp_encryption'])) unset($actions['pgp']);
		// remove vfs actions if the user has no run access to filemanager
		if (empty($GLOBALS['egw_info']['user']['apps']['filemanager']))
		{
			unset($actions['save2vfs']);
			unset($actions['selectFromVFSForCompose']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['infolog']))
		{
			unset($actions['to_infolog']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['tracker']))
		{
			unset($actions['to_tracker']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['calendar']))
		{
			unset($actions['to_calendar']);
		}
		return $actions;
	}

	/**
	 * Compose dialog
	 *
	 * @var array $_content =null etemplate content array
	 * @var string $msg =null a possible message to be passed and displayed to the userinterface
	 * @var string $_focusElement ='to' subject, to, body supported
	 * @var boolean $suppressSigOnTop =false
	 * @var boolean $isReply =false
	 */
	function compose(array $_content=null,$msg=null, $_focusElement='to',$suppressSigOnTop=false, $isReply=false)
	{
		if ($msg) Framework::message($msg);

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
		$_contentHasSigID = $_content?array_key_exists('mailidentity',(array)$_content):false;
		$_contentHasMimeType = $_content? array_key_exists('mimeType',(array)$_content):false;

		// fetch appendix data which is an assistance input value consisiting of json data
		if (!empty($_content['appendix_data']))
		{
			$appendix_data = json_decode($_content['appendix_data'], true);
			$_content['appendix_data'] = '';
		}

		if (!empty($appendix_data['emails']))
		{
			try {
				if ($appendix_data['emails']['processedmail_id']) $_content['processedmail_id'] .= ','.$appendix_data['emails']['processedmail_id'];
				$attched_uids = $this->_get_uids_as_attachments($appendix_data['emails']['ids'], $_content['serverID']);
				if (is_array($attched_uids))
				{
					$_content['attachments'] = array_merge_recursive((array)$_content['attachments'], $attched_uids);
				}
			} catch (Exception $ex) {
				Framework::message($ex->getMessage(), 'error');
			}
			$suppressSigOnTop = true;
			unset($appendix_data);
		}

		if (isset($_GET['reply_id'])) $replyID = $_GET['reply_id'];
		if (empty($replyID) && isset($_GET['id'])) $replyID = $_GET['id'];

		// Process different places we can use as a start for composing an email
		$actionToProcess = 'compose';
		if(!empty($_GET['from']) && $replyID)
		{
			$_content = array_merge((array)$_content, $this->getComposeFrom(
				// Parameters needed for fetching appropriate data
				$replyID, $_GET['part_id'] ?? null, $_GET['from'] ?? null,
				// additionally these can be changed
				$_focusElement, $suppressSigOnTop, $isReply
			));
			if (Mail\Smime::get_acc_smime($this->mail_bo->profileID))
			{
				if (isset($_GET['smime_type'])) $smime_type = $_GET['smime_type'];
				// pre set smime_sign and smime_encrypt actions if the original
				// message is smime.
				$_content['smime_sign'] = $smime_type == (Mail\Smime::TYPE_SIGN ||
					$smime_type == Mail\Smime::TYPE_SIGN_ENCRYPT) ? 'on' : 'off';
				$_content['smime_encrypt'] = ($smime_type == Mail\Smime::TYPE_ENCRYPT) ? 'on' : 'off';
			}

			$actionToProcess = $_GET['from'];
			unset($_GET['from']);
			unset($_GET['reply_id']);
			unset($_GET['part_id']);
			unset($_GET['id']);
			unset($_GET['mode']);
			//error_log(__METHOD__.__LINE__.array2string($_content));
		}

		$composeCache = array();
		if (!empty($_content['composeID']))
		{
			$isFirstLoad = false;
			$composeCache = Api\Cache::getCache(Api\Cache::SESSION,'mail','composeCache'.trim($GLOBALS['egw_info']['user']['account_id']).'_'.$_content['composeID'],$callback=null,$callback_params=array(),$expiration=60*60*2);
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
		if (!empty($_content['selectFromVFSForCompose']))
		{
			$suppressSigOnTop = true;
			foreach ($_content['selectFromVFSForCompose'] as $i => $path)
			{
				$_content['uploadForCompose'][] = array(
					'name' => Vfs::basename($path),
					'type' => Vfs::mime_content_type($path),
					'file' => Vfs::PREFIX.$path,
					'size' => filesize(Vfs::PREFIX.$path),
				);
			}
			unset($_content['selectFromVFSForCompose']);
		}
		// check everything that was uploaded
		if (!empty($_content['uploadForCompose']))
		{
			$suppressSigOnTop = true;
			foreach ($_content['uploadForCompose'] as $i => &$upload)
			{
				if (!isset($upload['file'])) $upload['file'] = $upload['tmp_name'];
				try
				{
					$upload['file'] = $upload['tmp_name'] = Mail::checkFileBasics($upload,$this->composeID,false);
				}
				catch (Api\Exception\WrongUserinput $e)
				{
					Framework::message($e->getMessage(), 'error');
					unset($_content['uploadForCompose'][$i]);
					continue;
				}
				if (is_dir($upload['file']) && (!$_content['filemode'] || $_content['filemode'] == Vfs\Sharing::ATTACH))
				{
					$_content['filemode'] = Vfs\Sharing::READONLY;
					Framework::message(lang('Directories have to be shared.'), 'info');
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
		$activeFolderCache = Api\Cache::getCache(Api\Cache::INSTANCE,'email','activeMailbox'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*10);
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
		// make sure $acc is set/initialized properly with the current composeProfile, as $acc is used down there
		// at several locations and not necessary initialized before
		$acc = Mail\Account::read($composeProfile);
		$buttonClicked = false;
		if (!empty($_content['composeToolbar']) && $_content['composeToolbar'] === 'send')
		{
			$buttonClicked = $suppressSigOnTop = true;
			$sendOK = true;
			$_content['body'] = $_content['body'] ?? $_content['mail_'.($_content['mimeType'] == 'html'?'html':'plain').'text'] ?? null;
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

					//hook mail_compose_after_save
					Api\Hooks::process( array(
							'location' => 'mail_compose_after_save',
							'content' => $_content,
					));

					if (!$success)
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
				catch (Api\Exception\WrongUserinput $e)
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
				$workingFolder = $activeFolder['mailbox'];
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
					$workingFolder = $rhA['folder'];	// need folder to refresh eg. drafts folder
				}
				$response = Api\Json\Response::get();
				if ($activeProfile != $composeProfile)
				{
					// we need a message only, when account ids (composeProfile vs. activeProfile) differ
					$response->call('opener.egw_message',lang('Message send successfully.'));
				}
				elseif ($activeProfile == $composeProfile && ($workingFolder==$activeFolder['mailbox'] && $mode != 'compose') ||
					($this->mail_bo->isSentFolder($workingFolder) || $this->mail_bo->isDraftFolder($workingFolder)))
				{
					if ($this->mail_bo->isSentFolder($workingFolder)||$this->mail_bo->isDraftFolder($workingFolder))
					{
						// we may need a refresh when on sent folder or in drafts, as drafted messages will/should be deleted after succeeded send action
						$response->call('opener.egw_refresh',lang('Message send successfully.'),'mail');
					}
					// we only need to update the icon of the replied or forwarded mails --> 'update-in-place'
					else
					{
						//error_log(__METHOD__.__LINE__.array2string($idsForRefresh));
						$response->call('opener.egw_refresh',lang('Message send successfully.'),'mail',$idsForRefresh,'update-in-place');
					}
				}
				else
				{
					$response->call('opener.egw_message',lang('Message send successfully.'));
				}
				//egw_framework::refresh_opener(lang('Message send successfully.'),'mail');
				Framework::window_close();
			}
			if ($sendOK == false)
			{
				$response = Api\Json\Response::get();
				Framework::message(lang('Message send failed: %1',$message),'error');// maybe error is more appropriate
				$response->call('app.mail.clearIntevals');
			}
		}

		if ($activeProfile != $composeProfile) $this->changeProfile($activeProfile);
		$insertSigOnTop = false;
		$content = $_content ?? [];
		if ($_contentHasMimeType)
		{
			// mimeType is now a checkbox; convert it here to match expectations
			// ToDo: match Code to meet checkbox value
			$_content['mimetype'] = $content['mimeType'] = !empty($content['mimeType']) ? 'html' : 'plain';
		}
		// user might have switched desired mimetype, so we should convert
		if (!empty($content['is_html']) && $content['mimeType'] === 'plain')
		{
			//error_log(__METHOD__.__LINE__.$content['mail_htmltext']);
			$suppressSigOnTop = true;
			if (stripos($content['mail_htmltext'],'<pre>')!==false)
			{
				$contentArr = Api\Mail\Html::splithtmlByPRE($content['mail_htmltext']);
				if (is_array($contentArr))
				{
					foreach ($contentArr as $k =>&$elem)
					{
						if (stripos($elem,'<pre>')!==false) $elem = str_replace(array("\r\n","\n","\r"),array("<br>","<br>","<br>"),$elem);
					}
					$content['mail_htmltext'] = implode('',$contentArr);
				}
			}
			$content['mail_htmltext'] = $this->_getCleanHTML($content['mail_htmltext']);
			$content['mail_htmltext'] = Api\Mail\Html::convertHTMLToText($content['mail_htmltext'],$charset=false,false,true);

			$content['body'] = $content['mail_htmltext'];
			unset($content['mail_htmltext']);
			$content['is_html'] = false;
			$content['is_plain'] = true;
		}
		if (!empty($content['is_plain']) && $content['mimeType'] === 'html')
		{
			// the possible font span should only be applied on first load or on switch plain->html
			$isFirstLoad = "switchedplaintohtml";
			//error_log(__METHOD__.__LINE__.$content['mail_plaintext']);
			$suppressSigOnTop = true;
			$content['mail_plaintext'] = str_replace(['<',"\r\n","\n","\r"], ['&lt;',"<br>","<br>","<br>"], $content['mail_plaintext']);
			//$this->replaceEmailAdresses($content['mail_plaintext']);
			$content['body'] = $content['mail_plaintext'];
			unset($content['mail_plaintext']);
			$content['is_html'] = true;
			$content['is_plain'] = false;
		}

		$content['body'] = $content['body'] ?? $content['mail_'.($content['mimeType'] === 'html' ? 'html' : 'plain').'text'] ?? '';
		unset($_content['body'], $_content['mail_htmltext'], $_content['mail_plaintext']);
		$_currentMode = $_content['mimeType'] && $_content['mimeType'] !== 'plain' ? 'html' : 'plain';

		// we have to keep comments to be able to changing signatures
		// signature is wraped in "<!-- HTMLSIGBEGIN -->$signature<!-- HTMLSIGEND -->"
		Mail::$htmLawed_config['comment'] = 2;

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
				$acc = Mail\Account::read($_content['mailaccount']);
				//error_log(__METHOD__.__LINE__.array2string($acc));
				$Identities = Mail\Account::read_identity($acc['ident_id'],true);
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

			if ($_oldSig != $_signatureid)
			{
				if(Mail::$debug) error_log(__METHOD__.__LINE__.' old,new ->'.$_oldSig.','.$_signatureid.'#'.$content['body']);
				// prepare signatures, the selected sig may be used on top of the body
				try
				{
					$oldSignature = Mail\Account::read_identity($_oldSig,true);
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
					$signature = Mail\Account::read_identity($_signatureid,true);
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
					if(Mail::$debug) error_log(__METHOD__." Old signature:".$oldSigText);
				}

				//$oldSigText = Mail::merge($oldSigText,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
				//error_log(__METHOD__.'Old+:'.$oldSigText.'#');
				//$sigText = Mail::merge($sigText,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
				//error_log(__METHOD__.'new+:'.$sigText.'#');
				$_htmlConfig = Mail::$htmLawed_config;
				Mail::$htmLawed_config['transform_anchor'] = false;
				$oldSigTextCleaned = str_replace(array("\r", "\t", "<br />\n", ": "), array("", "", "<br />", ":"),
					$_currentMode == 'html' ? Api\Html::purify($oldSigText, null, array(), true) : $oldSigText);
				//error_log(__METHOD__.'Old(clean):'.$oldSigTextCleaned.'#');
				if ($_currentMode == 'html')
				{
					$content['body'] = str_replace("\n",'\n',$content['body']);	// dont know why, but \n screws up preg_replace
					$styles = Mail::getStyles(array(array('body'=>$content['body'])));
					if (stripos($content['body'],'style')!==false) Api\Mail\Html::replaceTagsCompletley($content['body'],'style',$endtag='',true); // clean out empty or pagewide style definitions / left over tags
				}
				$content['body'] = str_replace(array("\r", "\t", "<br />\n", ": "), array("", "", "<br />", ":"),
					$_currentMode == 'html' ? Api\Html::purify($content['body'], Mail::$htmLawed_config, array(), true) : $content['body']);
				Mail::$htmLawed_config = $_htmlConfig;
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
					if(Mail::$debug) error_log(__METHOD__." Old Signature failed to match:".$oldSigTextCleaned);
					if(Mail::$debug) error_log(__METHOD__." Compare content:".$content['body']);
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
		/*run the purify on compose body unconditional*/
		$content['body'] = str_replace(array("\r", "\t", "<br />\n"), array("", "", "<br />"),
		$_currentMode == 'html' ? Api\Html::purify($content['body'], Mail::$htmLawed_config, array(), true) : $content['body']);

		// do not double insert a signature on a server roundtrip
		if ($buttonClicked) $suppressSigOnTop = true;

		// On submit reads external_vcard widget's value and addes them as attachments.
		// this happens when we send vcards from addressbook to an opened compose
		// dialog.
		if (!empty($appendix_data['files']))
		{
			$_REQUEST['preset']['file'] = $appendix_data['files']['file'];
			$_REQUEST['preset']['type'] = $appendix_data['files']['type'];
			$_content['filemode'] = !empty($appendix_data['files']['filemode']) &&
						isset(Vfs\Sharing::$modes[$appendix_data['files']['filemode']]) ?
							$appendix_data['files']['filemode'] : Vfs\Sharing::ATTACH;
			$suppressSigOnTop = true;
			unset($_content['attachments']);
			$this->addPresetFiles($content, $insertSigOnTop, true);
		}

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
				$method = Link::get_registry($app,$mt);
				//error_log(__METHOD__.__LINE__.array2string($method));
				if ($method)
				{
					$res = ExecMethod($method,array($id,'html'));
					//error_log(__METHOD__.__LINE__.array2string($res));
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
							if ($name=='mimetype'&&$res[$name])
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
			if (!empty($_REQUEST['preset']))
			{
				$alreadyProcessed=array();
				//_debug_array($_REQUEST);
				if (!empty($_REQUEST['preset']['mailto'])) {
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
							$reqval = preg_replace('/__AMPERSAND__/i', "&", $reqval);
							$keyValuePair = explode('=',$reqval,2);
							$content[$keyValuePair[0]] .= (strlen($content[$keyValuePair[0]])>0 ? ' ':'') . $keyValuePair[1];
						}
					}
					$content['to']= preg_replace('/__AMPERSAND__/i', "&", $mailtoArray[0]);
					$alreadyProcessed['to']='to';
					// if the mailto string is not htmlentity decoded the arguments are passed as simple requests
					foreach(array('cc','bcc','subject','body') as $name) {
						$alreadyProcessed[$name]=$name;
						if ($_REQUEST[$name]) $content[$name] .= (strlen($content[$name])>0 ? ( $name == 'cc' || $name == 'bcc' ? ',' : ' ') : '') . $_REQUEST[$name];
					}
				}

				if (!empty($_REQUEST['preset']['mailtocontactbyid'])) {
					if ($GLOBALS['egw_info']['user']['apps']['addressbook']) {
						$contacts_obj = new Api\Contacts();
						$addressbookprefs =& $GLOBALS['egw_info']['user']['preferences']['addressbook'];
						if (method_exists($contacts_obj,'search')) {

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
								$showAccounts= $GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] !== '1';
								$filter = ($showAccounts?array():array('account_id' => null));
								$filter['cols_to_search']=array('n_fn','email','email_home');
								$contacts = $contacts_obj->search($_searchCond,array('n_fn','email','email_home'),'n_fn','','%',false,'OR',array(0,100),$filter);
								// additionally search the accounts, if the contact storage is not the account storage
								if ($showAccounts &&
									$GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
									$GLOBALS['egw_info']['server']['contact_repository'] == 'sql')
								{
									$accounts = $contacts_obj->search($_searchCond,array('n_fn','email','email_home'),'n_fn','','%',false,'OR',array(0,100),array('owner' => 0));

									if ($contacts && $accounts)
									{
										$contacts = array_merge($contacts,$accounts);
										usort($contacts, function($a, $b)
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
							$alreadyProcessed['to']='to';
							$content['to']=$mailtoArray;
						}
					}
				}

				if (!empty($_REQUEST['preset']['file']))
				{
					$content['filemode'] = !empty($_REQUEST['preset']['filemode']) &&
							(isset(Vfs\Sharing::$modes[$_REQUEST['preset']['filemode']]) || isset(Vfs\HiddenUploadSharing::$modes[$_REQUEST['preset']['filemode']])) ?
							$_REQUEST['preset']['filemode'] : Vfs\Sharing::ATTACH;

					$this->addPresetFiles($content, $insertSigOnTop, $alwaysAttachVCardAtCompose);
					$remember = array();
					if (isset($_REQUEST['preset']['mailto']) || (isset($_REQUEST['app']) && isset($_REQUEST['method']) && isset($_REQUEST['id'])))
					{
						foreach(array_keys($content) as $k)
						{
							if (in_array($k,array('to','cc','bcc','subject','body','mimeType'))&&isset($this->sessionData[$k]))
							{
								$alreadyProcessed[$k]=$k;
								$remember[$k] = $this->sessionData[$k];
							}
						}
					}
					if(!empty($remember)) $content = array_merge($content,$remember);
				}
				foreach(array('to','cc','bcc','subject','body','mimeType') as $name)
				{
					//always handle mimeType
					if ($name=='mimeType' && !empty($_REQUEST['preset'][$name]))
					{
						$_content[$name]=$content[$name]=$_REQUEST['preset'][$name];
					}
					//skip if already processed by "preset Routines"
					if ($alreadyProcessed[$name]) continue;
					//error_log(__METHOD__.__LINE__.':'.$name.'->'. $_REQUEST['preset'][$name]);
					if (!empty($_REQUEST['preset'][$name])) $content[$name] = $_REQUEST['preset'][$name];
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
				if (strpos($content['to'],'%40')!== false) $content['to'] = Api\Html::purify(str_replace('%40','@',$content['to']));
				$rarr = array(Api\Html::purify($rest));
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
				if (!empty($_REQUEST['subject'])) $content['subject'] = Api\Html::purify(trim(html_entity_decode($_REQUEST['subject'])));
			}
		}
		//error_log(__METHOD__.__LINE__.array2string($content));
		//is the MimeType set/requested
		if ($isFirstLoad && !empty($_REQUEST['mimeType']))
		{
			$_content['mimeType'] = $content['mimeType'];
			if (($_REQUEST['mimeType']=="text" ||$_REQUEST['mimeType']=="plain") && $content['mimeType'] == 'html')
			{
				$_content['mimeType'] = $content['mimeType']  = 'plain';
				$html = str_replace(array("\n\r","\n"),' ',$content['body']);
				$content['body'] = $this->convertHTMLToText($html);
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
					$html = str_replace(array("\n\r","\n"),' ',$content['body']);
					$content['body'] = $this->convertHTMLToText($html);
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
			$signature = Mail\Account::read_identity($content['mailidentity'] ? $content['mailidentity'] : $presetSig,true);
		}
		catch (Exception $e)
		{
			//PROBABLY NOT FOUND
			$signature=array();
		}
		if (!empty($this->mailPreferences['disableRulerForSignatureSeparation']) ||
			empty($signature['ident_signature']))
		{
			$disableRuler = true;
		}

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
			$sigText = Mail::merge($signature['ident_signature'],array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
			if ($content['mimeType'] == 'html')
			{
				$sigTextStartsWithBlockElement = !$disableRuler;
				foreach($blockElements as $e)
				{
					if ($sigTextStartsWithBlockElement) break;
					if (stripos(trim($sigText),'<'.$e)===0) $sigTextStartsWithBlockElement = true;
				}
			}
			if ($content['mimeType'] === 'html')
			{
				$start = "<br/>\n";
				$before = $disableRuler ? '' : '<hr class="ruler" style="border:1px dotted silver; width:100%;">';
				$inbetween = '';
			}
			else
			{
				$before = $disableRuler ? "\r\n" : "\r\n-- \r\n";
				$start = $inbetween = "\r\n";
			}
			if ($content['mimeType'] === 'html')
			{
				$sigText = ($sigTextStartsWithBlockElement?'':"<div>")."<!-- HTMLSIGBEGIN -->".$sigText."<!-- HTMLSIGEND -->".($sigTextStartsWithBlockElement?'':"</div>");
			}

			if ($insertSigOnTop === 'below')
			{
				$content['body'] = $start.$content['body'].$before.($content['mimeType'] == 'html'?$sigText:$this->convertHTMLToText($sigText,true,true));
			}
			else
			{
				$content['body'] = $start.$before.($content['mimeType'] == 'html'?$sigText:$this->convertHTMLToText($sigText,true,true)).$inbetween.$content['body'];
			}
		}
		// Skip this part if we're merging, it would add an extra line at the top
		else if (!$content['body'])
		{
			$content['body'] = ($isFirstLoad === "switchedplaintohtml"?"</span>":"");
		}
		//error_log(__METHOD__.__LINE__.$content['body']);

		// prepare body
		// in a way, this tests if we are having real utf-8 (the displayCharset) by now; we should if charsets reported (or detected) are correct
		$content['body'] = Api\Translation::convert_jsonsafe($content['body'],'utf-8');
		//error_log(__METHOD__.__LINE__.array2string($content));

		// get identities of all accounts as "$acc_id:$ident_id" => $identity
		$sel_options['mailaccount'] = $identities = array();
		foreach(Mail\Account::search(true,false) as $acc_id => $account)
		{
			// do NOT add SMTP only accounts as identities
			if (!$account->is_imap(false)) continue;

			foreach($account->identities($acc_id) as $ident_id => $identity)
			{
				$sel_options['mailaccount'][$acc_id.':'.$ident_id] = $identity;
				$identities[$ident_id] = $identity;
			}
			unset($account);
		}

		//$content['bcc'] = array('kl@egroupware.org','kl@leithoff.net');
		// address stuff like from, to, cc, replyto
		$destinationRows = 0;
		foreach(self::$destinations as $destination) {
			if (!empty($content[$destination]) && !is_array($content[$destination]))
			{
				$content[$destination] = (array)$content[$destination];
			}
			$addr_content = $content[strtolower($destination)] ?? [];
			// we clear the given address array and rebuild it
			unset($content[strtolower($destination)]);
			foreach($addr_content as $value) {
				if ($value === "NIL@NIL") continue;
				if ($destination === 'replyto' && str_replace('"','',$value) ===
					str_replace('"','',$identities[$this->mail_bo->getDefaultIdentity()]))
				{
					// preserve/restore the value to content.
					/** @noinspection UnsupportedStringOffsetOperationsInspection */
					$content[strtolower($destination)][]=$value;
					continue;
				}
				//error_log(__METHOD__.__LINE__.array2string(array('key'=>$key,'value'=>$value)));
				$value = str_replace("\"\"",'"', htmlspecialchars_decode($value, ENT_COMPAT));
				foreach(Mail::parseAddressList($value) as $addressObject) {
					if ($addressObject->host === '.SYNTAX-ERROR.') continue;
					$address = imap_rfc822_write_address($addressObject->mailbox,$addressObject->host,$addressObject->personal);
					//$address = Mail::htmlentities($address, $this->displayCharset);
					/** @noinspection UnsupportedStringOffsetOperationsInspection */
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
		$content['attachments'] = array_merge($content['attachments'] ?? [], $content['uploadForCompose'] ?? []);
		//if (is_array($content['attachments'])) foreach($content['attachments'] as $k => &$file) $file['delete['.$file['tmp_name'].']']=0;
		$content['no_griddata'] = empty($content['attachments']);
		$preserv['attachments'] = $content['attachments'];
		$content['expiration_blur'] = $GLOBALS['egw_info']['user']['apps']['stylite'] ? lang('Select a date') : lang('EPL only');

		//if (is_array($content['attachments']))error_log(__METHOD__.__LINE__.' Attachments:'.array2string($content['attachments']));
		// if no filemanager -> no vfsFileSelector
		if (empty($GLOBALS['egw_info']['user']['apps']['filemanager']))
		{
			$content['vfsNotAvailable'] = "mail_DisplayNone";
		}
		// if no infolog -> no save as infolog
		if (empty($GLOBALS['egw_info']['user']['apps']['infolog']))
		{
			$content['noInfologAvailable'] = "mail_DisplayNone";
		}
		// if no tracker -> no save as tracker
		if (empty($GLOBALS['egw_info']['user']['apps']['tracker']))
		{
			$content['noTrackerAvailable'] = "mail_DisplayNone";
		}
		if (empty($GLOBALS['egw_info']['user']['apps']['infolog']) && empty($GLOBALS['egw_info']['user']['apps']['tracker']))
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
		$sel_options['filemode'] = Vfs\Sharing::$modes;
		if (empty($content['priority'])) $content['priority']=3;
		//$GLOBALS['egw_info']['flags']['currentapp'] = 'mail';//should not be needed
		$etpl = new Etemplate('mail.compose');

		$etpl->setElementAttribute('composeToolbar', 'actions', self::getToolbarActions($content));
		if ($content['mimeType'] == 'html')
		{
			//mode="$cont[rtfEditorFeatures]" validation_rules="$cont[validation_rules]" base_href="$cont[upload_dir]"
			$_htmlConfig = Mail::$htmLawed_config;
			Mail::$htmLawed_config['comment'] = 2;
			Mail::$htmLawed_config['transform_anchor'] = false;
			$content['validation_rules']= json_encode(Mail::$htmLawed_config);
			$etpl->setElementAttribute('mail_htmltext','validation_rules',$content['validation_rules']);
			Mail::$htmLawed_config = $_htmlConfig;
		}

		if (!empty($content['composeID']))
		{
			$composeCache = $content;
			unset($composeCache['body']);
			unset($composeCache['mail_htmltext']);
			unset($composeCache['mail_plaintext']);
			Api\Cache::setCache(Api\Cache::SESSION,'mail','composeCache'.trim($GLOBALS['egw_info']['user']['account_id']).'_'.$this->composeID,$composeCache,$expiration=60*60*2);
		}
		if (empty($_content['serverID']))
		{
			$content['serverID'] = $this->mail_bo->profileID;
		}
		$preserv['serverID'] = $content['serverID'];
		$preserv['lastDrafted'] = $content['lastDrafted'] ?? null;
		$preserv['processedmail_id'] = $content['processedmail_id'] ?? null;
		$preserv['references'] = $content['references'] ?? null;
		$preserv['in-reply-to'] = $content['in-reply-to'] ?? null;
		// thread-topic is a proprietary microsoft header and deprecated with the current version
		// horde does not support the encoding of thread-topic, and probably will not no so in the future
		//$preserv['thread-topic'] = $content['thread-topic'];
		$preserv['thread-index'] = $content['thread-index'] ?? null;
		$preserv['list-id'] = $content['list-id'] ?? null;
		$preserv['mode'] = $content['mode'] ?? null;
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
		//Try to set the initial selected account to the first identity match found
		// which fixes the issue of prefered identity never get selected.
		if (!in_array($content['mailaccount'], array_keys($sel_options['mailaccount'])))
		{
			foreach ($sel_options['mailaccount'] as $ident => $value)
			{
				$idnt_acc_parts = explode(':', $ident);

				if ($content['mailidentity'] == $idnt_acc_parts[1])
				{
					$content['mailaccount'] = $ident;
					break;
				}
			}
		}
		// Resolve distribution list before send content to client
		foreach(array('to', 'cc', 'bcc', 'replyto')  as $f)
		{
			if (isset($content[$f]) && is_array($content[$f])) $content[$f]= self::resolveEmailAddressList ($content[$f]);
		}

		// set filemode icons for all attachments
		if(!empty($content['attachments']))
		{
			foreach($content['attachments'] as &$attach)
			{
				$attach['is_dir'] = !empty($attach['file']) && is_dir($attach['file']);
				$attach['filemode_icon'] = !empty($attach['file']) && !is_dir($attach['file']) && !empty($content['filemode']) &&
						($content['filemode'] == Vfs\Sharing::READONLY || $content['filemode'] == Vfs\Sharing::WRITABLE)
						? Vfs\Sharing::LINK : $content['filemode'] ?? '';
				$attach['filemode_title'] = lang(Vfs\Sharing::$modes[$attach['filemode_icon']]['label'] ?? '');
			}
			$content['attachmentsBlockTitle'] =  count($content['attachments']).' '.Lang('Attachments');
		}
		else
		{
			unset($content['attachments']);
		}

		if (isset($content['to'])) $content['to'] = self::resolveEmailAddressList($content['to']);
		$content['html_toolbar'] = empty(Mail::$mailConfig['html_toolbar']) ?
			implode(',', Etemplate\Widget\HtmlArea::$toolbar_default_list) : implode(',', Mail::$mailConfig['html_toolbar']);
		//error_log(__METHOD__.__LINE__.array2string($content));
		$etpl->exec('mail.mail_compose.compose',$content,$sel_options,array(),$preserv,2);
	}

	/**
	 * Add preset files like vcard as attachments into content array
	 *
	 * Preset attachments are read from $_REQUEST['preset']['file'] with
	 * optional ['type'] and ['name'].
	 *
	 * Attachments must either be in EGroupware Vfs or configured temp. directory!
	 *
	 * @param array $_content content
	 * @param string $_insertSigOnTop
	 * @param boolean $_eliminateDoubleAttachments
	 */
	function addPresetFiles (&$_content, &$_insertSigOnTop, $_eliminateDoubleAttachments)
	{
		// check if JSON was used
		if (!is_array($_REQUEST['preset']['file']) &&
			($_REQUEST['preset']['file'][0] === '[' && substr($_REQUEST['preset']['file'], -1) === ']' ||
			$_REQUEST['preset']['file'][0] === '{' && substr($_REQUEST['preset']['file'], -1) === '}') &&
			($files = json_decode($_REQUEST['preset']['file'], true)))
		{
			$types = !empty($_REQUEST['preset']['type']) ?
				json_decode($_REQUEST['preset']['type'], true) : array();
			$names = !empty($_REQUEST['preset']['name']) ?
				json_decode($_REQUEST['preset']['name'], true) : array();
		}
		else
		{
			$files = (array)$_REQUEST['preset']['file'];
			$types = !empty($_REQUEST['preset']['type']) ?
				(array)$_REQUEST['preset']['type'] : array();
			$names = !empty($_REQUEST['preset']['name']) ?
				(array)$_REQUEST['preset']['name'] : array();
		}

		foreach($files as $k => $path)
		{
			if (!empty($types[$k]) && stripos($types[$k],'text/calendar')!==false)
			{
				$_insertSigOnTop = 'below';
			}
			//error_log(__METHOD__.__LINE__.$path.'->'.array2string(parse_url($path,PHP_URL_SCHEME == 'vfs')));
			if (($scheme = parse_url($path,PHP_URL_SCHEME)) === 'vfs')
			{
				$type = Vfs::mime_content_type($path);
				// special handling for attaching vCard of iCal --> use their link-title as name
				if (substr($path,-7) != '/.entry' ||
					!(list($app,$id) = array_slice(explode('/',$path),-3)) ||
					!($name = Link::title($app, $id)))
				{
					$name = Vfs::decodePath(Vfs::basename($path));
				}
				else
				{
					$name .= '.'.Api\MimeMagic::mime2ext($type);
				}
				// use type specified by caller, if Vfs reports only default, or contains specified type (eg. "text/vcard; charset=utf-8")
				if (!empty($types[$k]) && ($type == 'application/octet-stream' || stripos($types[$k], $type) === 0))
				{
					$type = $types[$k];
				}
				$path = str_replace('+','%2B',$path);
				$formData = array(
					'name' => $name,
					'type' => $type,
					'file' => Vfs::decodePath($path),
					'size' => filesize(Vfs::decodePath($path)),
				);
				if ($formData['type'] == Vfs::DIR_MIME_TYPE && $_content['filemode'] == Vfs\Sharing::ATTACH)
				{
					$_content['filemode'] = Vfs\Sharing::READONLY;
					Framework::message(lang('Directories have to be shared.'), 'info');
				}
			}
			// do not allow to attache something from server filesystem outside configured temp_dir
			elseif (strpos(realpath(parse_url($path, PHP_URL_PATH)), realpath($GLOBALS['egw_info']['server']['temp_dir']).'/') !== 0)
			{
				error_log(__METHOD__."() Attaching '$path' outside configured temp. directory '{$GLOBALS['egw_info']['server']['temp_dir']}' denied!");
			}
			elseif(is_readable($path))
			{
				$formData = array(
					'name' => isset($names[$k]) ? $names[$k] : basename($path),
					'type' => isset($types[$k]) ? $types[$k] : (function_exists('mime_content_type') ? mime_content_type($path) : Api\MimeMagic::filename2mime($path)),
					'file' => $path,
					'size' => filesize($path),
				);
			}
			else
			{
				continue;
			}
			$this->addAttachment($formData,$_content, $_eliminateDoubleAttachments);
		}
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
				case 'composeasnew':
					$content = $this->getDraftData($icServer, $folder, $msgUID, $part_id);
					if ($from =='composefromdraft') $content['mode'] = 'composefromdraft';
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
					foreach ($replyIds as &$m_id)
					{
						//error_log(__METHOD__.__LINE__.' ID:'.$m_id.' Mode:'.$mode);
						$hA = mail_ui::splitRowID($m_id);
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
			$merge_class = preg_match('/^([a-z_-]+_merge)$/', $_REQUEST['merge']) ? $_REQUEST['merge'] : 'EGroupware\\Api\\Contacts\\Merge';
			$document_merge = new $merge_class();
			$this->mail_bo->openConnection();
			$merge_ids = $_REQUEST['preset']['mailtocontactbyid'] ? $_REQUEST['preset']['mailtocontactbyid'] : $mail_id;
			if (!is_array($merge_ids)) $merge_ids = explode(',',$merge_ids);
			try
			{
				$merged_mail_id = '';
				$folder = $this->mail_bo->getDraftFolder();
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
						$document_merge, Vfs::PREFIX . $_REQUEST['document'], $merge_ids, $folder, $merged_mail_id
					);

					// Open compose
					$merged_mail_id = trim($GLOBALS['egw_info']['user']['account_id']).mail_ui::$delimiter.
						$this->mail_bo->profileID.mail_ui::$delimiter.
						base64_encode($folder).mail_ui::$delimiter.$merged_mail_id;
					$content = $this->getComposeFrom($merged_mail_id, $part_id, 'composefromdraft', $_focusElement, $suppressSigOnTop, $isReply);
				}
			}
			catch (Api\Exception\WrongUserinput $e)
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
		Api\Mail\Html::replaceEmailAdresses($text);
		return 1;
	}

	function convertHTMLToText(&$_html,$sourceishtml = true, $stripcrl=false, $noRepEmailAddr = false)
	{
		$stripalltags = true;
		// third param is stripalltags, we may not need that, if the source is already in ascii
		if (!$sourceishtml) $stripalltags=false;
		return Api\Mail\Html::convertHTMLToText($_html,$this->displayCharset,$stripcrl,$stripalltags, $noRepEmailAddr);
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
		return Mail::getRandomString();
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
				Mail\Account::read_identity($addHeadInfo['X-MAILIDENTITY']);
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
				Mail\Account::read($addHeadInfo['X-MAILACCOUNT']);
				$this->sessionData['mailaccount'] = $addHeadInfo['X-MAILACCOUNT'];
			}
			catch (Exception $e)
			{
				unset($e);
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
			$rfcAddr=Mail::parseAddressList($val);
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
			$rfcAddr=Mail::parseAddressList($val);
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

		$fromAddr = Mail::parseAddressList($addHeadInfo['FROM'])[0];
		foreach((array)$headers['REPLY-TO'] as $val) {
			$rfcAddr=Mail::parseAddressList($val);
			$_rfcAddr = $rfcAddr[0];
			if (!$_rfcAddr->valid || ($_rfcAddr->mailbox == $fromAddr->mailbox && $_rfcAddr->host == $fromAddr->host)) continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (empty($_rfcAddr->mailbox) && empty($_rfcAddr->host)) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(empty($foundAddresses[$keyemail])) {
				$address = $this->mail_bo->decode_header($val,true);
				$this->sessionData['replyto'][] = $val;
				$foundAddresses[$keyemail] = true;
			}
		}

		foreach((array)$headers['BCC'] as $val) {
			$rfcAddr=Mail::parseAddressList($val);
			$_rfcAddr = $rfcAddr[0];
			if (!$_rfcAddr->valid) continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (empty($_rfcAddr->mailbox) && empty($_rfcAddr->host)) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(empty($foundAddresses[$keyemail])) {
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
		$bodyParts = $mail_bo->getMessageBody($_uid,'always_display', $_partID);
		//_debug_array($bodyParts);
		#$fromAddress = ($headers['FROM'][0]['PERSONAL_NAME'] != 'NIL') ? $headers['FROM'][0]['RFC822_EMAIL'] : $headers['FROM'][0]['EMAIL'];
		if($bodyParts['0']['mimeType'] == 'text/html') {
			$this->sessionData['mimeType'] 	= 'html';

			foreach($bodyParts as $i => &$bodyPart) {
				if($i>0) {
					$this->sessionData['body'] .= '<hr>';
				}
				if($bodyPart['mimeType'] == 'text/plain') {
					#$bodyParts[$i]['body'] = nl2br($bodyParts[$i]['body']);
					$bodyPart['body'] = "<pre>".$bodyPart['body']."</pre>";
				}
				if ($bodyPart['charSet']===false) $bodyPart['charSet'] = Mail::detect_encoding($bodyPart['body']);
				$bodyParts[$i]['body'] = Api\Translation::convert_jsonsafe($bodyPart['body'], $bodyPart['charSet']);
				#error_log( "GetDraftData (HTML) CharSet:".mb_detect_encoding($bodyPart['body'] . 'a' , strtoupper($bodyPart['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
				$this->sessionData['body'] .= ($i>0?"<br>":""). $bodyPart['body'] ;
			}
			$this->sessionData['body'] = mail_ui::resolve_inline_images($this->sessionData['body'], $_folder, $_uid, $_partID);

		} else {
			$this->sessionData['mimeType']	= 'plain';

			foreach($bodyParts as $i => &$bodyPart) {
				if($i>0) {
					$this->sessionData['body'] .= "<hr>";
				}
				if ($bodyPart['charSet']===false) $bodyPart['charSet'] = Mail::detect_encoding($bodyPart['body']);
				$bodyPart['body'] = Api\Translation::convert_jsonsafe($bodyPart['body'], $bodyPart['charSet']);
				#error_log( "GetDraftData (Plain) CharSet".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
				$this->sessionData['body'] .= ($i>0?"\r\n":""). $bodyPart['body'] ;
			}
			$this->sessionData['body'] = mail_ui::resolve_inline_images($this->sessionData['body'], $_folder, $_uid, $_partID,'plain');
		}

		if(($attachments = $mail_bo->getMessageAttachments($_uid,$_partID))) {
			foreach($attachments as $attachment) {
				//error_log(__METHOD__.__LINE__.array2string($attachment));
				$cid = $attachment['cid'];
				$match=null;
				preg_match("/cid:{$cid}/", $bodyParts['0']['body'], $match);
				//error_log(__METHOD__.__LINE__.'searching for cid:'."/cid:{$cid}/".'#'.$r.'#'.array2string($match));
				if (!$match || !$attachment['cid'])
				{
					$this->addMessageAttachment($_uid, $attachment['partID'],
						$_folder,
						$attachment['name'],
						$attachment['mimeType'],
						$attachment['size'],
						$attachment['is_winmail']);
				}
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
		$headers	= $mail_bo->getMessageEnvelope($_uid, $_partID,false,$_folder);
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
			try
			{
				if(($attachments = $mail_bo->getMessageAttachments($_uid,$_partID,null,true,false,false))) {
					//error_log(__METHOD__.__LINE__.':'.array2string($attachments));
					foreach($attachments as $attachment) {
						if (!($attachment['cid'] && preg_match("/image\//",$attachment['mimeType'])) || $attachment['disposition'] == 'attachment')
						{
							$this->addMessageAttachment($_uid, $attachment['partID'],
								$_folder,
								$attachment['name'],
								$attachment['mimeType'],
								$attachment['size']);
						}
					}
				}
			}
			catch (Mail\Smime\PassphraseMissing $e)
			{
				error_log(__METHOD__.'() Failed to forward because of smime '.$e->getMessage());
				Framework::message(lang('Forwarding of this message failed'.
						' because the content of this message seems to be encrypted'.
						' and can not be decrypted properly. If you still wish to'.
						' forward content of this encrypted message, you may try'.
						' to use forward as attachment instead.'),'error');
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
			$tmpFileName = Mail::checkFileBasics($_formData,$this->composeID,false);
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$attachfailed = true;
			$alert_msg = $e->getMessage();
			Framework::message($e->getMessage(), 'error');
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

	function addMessageAttachment($_uid, $_partID, $_folder, $_name, $_type, $_size, $_is_winmail= null)
	{
		$this->sessionData['attachments'][]=array (
			'uid'		=> $_uid,
			'partID'	=> $_partID,
			'name'		=> $_name,
			'type'		=> $_type,
			'size'		=> $_size,
			'folder'	=> $_folder,
			'winmailFlag' => $_is_winmail,
			'tmp_name'	=> mail_ui::generateRowID($this->mail_bo->profileID, $_folder, $_uid).'_'.(!empty($_partID)?$_partID:count($this->sessionData['attachments'] ?? [])+1),
		);
	}

	function getAttachment()
	{
		// read attachment data from etemplate request, use tmpname only to identify it
		if (($request = Etemplate\Request::read($_GET['etemplate_exec_id'])))
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
			Vfs::load_wrapper('vfs');
		}
		// attachment data in temp_dir, only use basename of given name, to not allow path traversal
		else
		{
			$attachment['tmp_name'] = $GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($attachment['tmp_name']);
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
				if (!empty($suffix)) $sfxMimeType = Api\MimeMagic::ext2mime($suffix);
				$attachment['type'] = $sfxMimeType;
				if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD') $attachment['type'] = strtoupper($sfxMimeType);
			}
			//error_log(__METHOD__.print_r($attachment,true));
			if (strtoupper($attachment['type']) == 'TEXT/CALENDAR' || strtoupper($attachment['type']) == 'TEXT/X-VCALENDAR')
			{
				//error_log(__METHOD__."about to call calendar_ical");
				$calendar_ical = new calendar_ical();
				$eventid = $calendar_ical->iCalSearch($attachment['attachment'],-1);
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
		$size = 0;
		Api\Header\Content::safe($attachment['attachment'], $attachment['name'], $attachment['type'], $size, true, $_GET['mode'] == "save");
		echo $attachment['attachment'];

		exit();
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

		// break reference into multiple lines if they're greater than 998 chars
		// and remove comma seperation. Fix error serer does not support binary
		// data due to long references.
		if (strlen($this->sessionData['references'])> 998)
		{
			$temp_refs = explode(',',$this->sessionData['references']);
			$this->sessionData['references'] = implode(" ",$temp_refs);
		}

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
		try {
			$bodyParts = $mail_bo->getMessageBody($_uid, ($this->mailPreferences['htmlOptions']?$this->mailPreferences['htmlOptions']:''), $_partID);
		}
		catch (Mail\Smime\PassphraseMissing $e)
		{
			$bodyParts = '';
			error_log(__METHOD__.'() Failed to reply because of smime '.$e->getMessage());
			Framework::message(lang('Replying to this message failed'.
				' because the content of this message seems to be encrypted'.
				' and can not be decrypted properly. If you still wish to include'.
				' content of this encrypted message, you may try to use forward as'.
				' attachment instead.'),'error');
		}
		//_debug_array($bodyParts);
		$styles = Mail::getStyles($bodyParts);

		$fromAddress = implode(', ', $headers['FROM']);

		$toAddressA = array();
		$toAddress = '';
		foreach ($headers['TO'] as $mailheader) {
			$toAddressA[] =  $mailheader;
		}
		if (count($toAddressA)>0)
		{
			$toAddress = implode(', ', $toAddressA);
			$toAddress = htmlspecialchars(lang("to").": ".$toAddress).($bodyParts['0']['mimeType'] == 'text/html'?"<br>":"\r\n");
		}
		$ccAddressA = array();
		$ccAddress = '';
		foreach ($headers['CC'] as $mailheader) {
			$ccAddressA[] =  $mailheader;
		}
		if (count($ccAddressA)>0)
		{
			$ccAddress = implode(', ', $ccAddressA);
			$ccAddress = htmlspecialchars(lang("cc").": ".$ccAddress).($bodyParts['0']['mimeType'] == 'text/html'?"<br>":"\r\n");
		}
		// create original message header in users preferred font and -size
		$this->sessionData['body']	= self::wrapBlockWithPreferredFont(
			htmlspecialchars(lang("from").": ".$fromAddress)."<br>".
			$toAddress.$ccAddress.
			htmlspecialchars(lang("date").": ".Mail::_strtotime($headers['DATE'],'r',true),ENT_QUOTES | ENT_IGNORE, Mail::$displayCharset, false),
			lang("original message"), 'originalMessage');

		if($bodyParts['0']['mimeType'] == 'text/html')
		{
			$this->sessionData['mimeType'] 	= 'html';
			if (!empty($styles)) $this->sessionData['body'] .= $styles;
			$this->sessionData['body']	.= '<blockquote type="cite">';

			foreach($bodyParts as $i => &$bodyPart)
			{
				if($i>0) {
					$this->sessionData['body'] .= '<hr>';
				}
				if($bodyPart['mimeType'] == 'text/plain') {
					#$bodyPart['body'] = nl2br($bodyPart['body'])."<br>";
					$bodyPart['body'] = "<pre>".$bodyPart['body']."</pre>";
				}
				if ($bodyPart['charSet']===false) $bodyPart['charSet'] = Mail::detect_encoding($bodyPart['body']);

				$_htmlConfig = Mail::$htmLawed_config;
				Mail::$htmLawed_config['comment'] = 2;
				Mail::$htmLawed_config['transform_anchor'] = false;
				$this->sessionData['body'] .= "<br>".self::_getCleanHTML(Api\Translation::convert_jsonsafe($bodyPart['body'], $bodyPart['charSet']));
				Mail::$htmLawed_config = $_htmlConfig;
				#error_log( "GetReplyData (HTML) CharSet:".mb_detect_encoding($bodyPart['body'] . 'a' , strtoupper($bodyPart['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
			}

			$this->sessionData['body']	.= '</blockquote><br>';
			$this->sessionData['body'] =  mail_ui::resolve_inline_images($this->sessionData['body'], $_folder, $_uid, $_partID, 'html');
		}
		else
		{
			// convert original message header to plain-text
            $this->sessionData['body'] = self::convertHTMLToText($this->sessionData['body'], true, false, true);

			$this->sessionData['mimeType']	= 'plain';
			foreach($bodyParts as $i => &$bodyPart)
			{
				if($i>0) {
					$this->sessionData['body'] .= "<hr>";
				}

				// add line breaks to $bodyParts
				$newBody2 = Api\Translation::convert_jsonsafe($bodyPart['body'],$bodyPart['charSet']);
				#error_log( "GetReplyData (Plain) CharSet:".mb_detect_encoding($bodyPart['body'] . 'a' , strtoupper($bodyPart['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
				$newBody = mail_ui::resolve_inline_images($newBody2, $_folder, $_uid, $_partID, 'plain');
				$this->sessionData['body'] .= "\r\n";
				$hasSignature = false;
				// create body new, with good line breaks and indention
				foreach(explode("\n",$newBody) as $value) {
					// the explode is removing the character
					//$value .= 'ee';

					// Try to remove signatures from qouted parts to avoid multiple
					// signatures problem in reply (rfc3676#section-4.3).
					if ($_mode != 'forward' && ($hasSignature || ($hasSignature = preg_match("/^--\s[\r\n]$/",$value))))
					{
						continue;
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

	/**
	 * Wrap html block in given tag with preferred font and -size set
	 *
	 * @param string $content
	 * @param string $legend
	 * @param ?string $class
	 * @return string
	 */
	static function wrapBlockWithPreferredFont($content, $legend, $class=null)
	{
		if (!empty($class)) $options = ' class="'.htmlspecialchars($class).'"';

		return Api\Html::fieldset($content, $legend, $options ?? '');
	}

	/**
	 * HTML cleanup
	 *
	 * @param type $_body message
	 * @param type $_useTidy = false, if true tidy extension will be loaded and tidy will try to clean body message
	 *			since the tidy causes segmentation fault ATM, we set the default to false.
	 * @return type
	 */
	static function _getCleanHTML($_body, $_useTidy = false)
	{
		static $nonDisplayAbleCharacters = array('[\016]','[\017]',
				'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
				'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

		if ($_useTidy && extension_loaded('tidy') )
		{
			$tidy = new tidy();
			$cleaned = $tidy->repairString($_body, Mail::$tidy_config,'utf8');
			// Found errors. Strip it all so there's some output
			if($tidy->getStatus() == 2)
			{
				error_log(__METHOD__.' ('.__LINE__.') '.' ->'.$tidy->errorBuffer);
			}
			else
			{
				$_body = $cleaned;
			}
		}

		Mail::getCleanHTML($_body);
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
	 * @param Api\Mailer $_mailObject
	 * @param array $_formData
	 * @param array $_identity
	 * @param boolean $_autosaving =false true: autosaving, false: save-as-draft or send
	 *
	 * @return array returns found inline images as attachment structure
	 */
	function createMessage(Api\Mailer $_mailObject, array $_formData, array $_identity, $_autosaving=false)
	{
		if (substr($_formData['body'], 0, 27) == '-----BEGIN PGP MESSAGE-----')
		{
			$_formData['mimeType'] = 'openpgp';
		}
		$mail_bo	= $this->mail_bo;
		$activeMailProfile = Mail\Account::read($this->mail_bo->profileID);

		// you need to set the sender, if you work with different identities, since most smtp servers, dont allow
		// sending in the name of someone else
		if ($_identity['ident_id'] != $activeMailProfile['ident_id'] && !empty($_identity['ident_email']) && strtolower($activeMailProfile['ident_email']) != strtolower($_identity['ident_email']))
		{
			error_log(__METHOD__.__LINE__.' Faking From/SenderInfo for '.$activeMailProfile['ident_email'].' with ID:'.$activeMailProfile['ident_id'].'. Identitiy to use for sending:'.array2string($_identity));
		}
		$email_From =  $_identity['ident_email'] ? $_identity['ident_email'] : $activeMailProfile['ident_email'];
		// Try to fix identity email with no domain part set
		$_mailObject->setFrom(Mail::fixInvalidAliasAddress(Api\Accounts::id2name($_identity['account_id'], 'account_email'), $email_From),
			mail_tree::getIdentityName($_identity, false));

		$_mailObject->addHeader('X-Priority', $_formData['priority']);
		$_mailObject->addHeader('X-Mailer', 'EGroupware-Mail');
		if(!empty($_formData['in-reply-to'])) {
			if (stripos($_formData['in-reply-to'],'<')===false) $_formData['in-reply-to']='<'.trim($_formData['in-reply-to']).'>';
			$_mailObject->addHeader('In-Reply-To', $_formData['in-reply-to']);
		}
		if(!empty($_formData['references'])) {
			if (stripos($_formData['references'],'<')===false)
			{
				$_formData['references']='<'.trim($_formData['references']).'>';
			}
			$_mailObject->addHeader('References', $_formData['references']);
		}

		if(!empty($_formData['thread-index'])) {
			$_mailObject->addHeader('Thread-Index', $_formData['thread-index']);
		}
		if(!empty($_formData['list-id'])) {
			$_mailObject->addHeader('List-Id', $_formData['list-id']);
		}
		if(isset($_formData['disposition']) && $_formData['disposition'] === 'on') {
			$_mailObject->addHeader('Disposition-Notification-To', $_identity['ident_email']);
		}

		// Expand any mailing lists
		foreach(array('to', 'cc', 'bcc', 'replyto')  as $field)
		{
			if ($field != 'replyto') $_formData[$field] = self::resolveEmailAddressList($_formData[$field]);

			if ($_formData[$field]) $_mailObject->addAddress($_formData[$field], '', $field);
		}

		$_mailObject->addHeader('Subject', $_formData['subject']);

		// this should never happen since we come from the edit dialog
		if (Mail::detect_qp($_formData['body'])) {
			$_formData['body'] = preg_replace('/=\r\n/', '', $_formData['body']);
			$_formData['body'] = quoted_printable_decode($_formData['body']);
		}
		$disableRuler = false;
		$signature = $_identity['ident_signature'];
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
		if ($_formData['attachments'] && $_formData['filemode'] != Vfs\Sharing::ATTACH && !$_autosaving)
		{
			$attachment_links = $this->_getAttachmentLinks($_formData['attachments'], $_formData['filemode'],
				// @TODO: $content['mimeType'] could type string/boolean. At the moment we can't strictly check them :(.
				// @TODO: This needs to be fixed in compose function to get the right type from the content.
				$_formData['mimeType'] == 'html',
				array_unique(array_merge((array)$_formData['to'], (array)$_formData['cc'], (array)$_formData['bcc'])),
				$_formData['expiration'], $_formData['password']);
		}
		switch ($_formData['mimeType'])
		{
			case 'html':
				$body = $_formData['body'];

				if (!empty($attachment_links))
				{
					// if we have a ruler, replace it with the attachment block
					static $ruler = '<hr class="ruler"';
					if (strpos($body, $ruler) !== false)
					{
						$body = preg_replace('#'.$ruler.'[^>]*>#', $attachment_links, $body);
					}
					// else place it before the signature
					elseif (strpos($body, '<!-- HTMLSIGBEGIN -->') !== false)
					{
						$body = str_replace('<!-- HTMLSIGBEGIN -->', $attachment_links.'<!-- HTMLSIGBEGIN -->', $body);
					}
					else
					{
						$body .= $attachment_links;
					}
				}
				$body = str_replace($ruler, '<hr', $body);  // remove id from ruler, to not replace in cited mails

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
				if (!$_autosaving) $inline_images = Mail::processURL2InlineImages($_mailObject, $body, $mail_bo);
				if (strpos($body,"<!-- HTMLSIGBEGIN -->")!==false)
				{
					$body = str_replace(array('<!-- HTMLSIGBEGIN -->','<!-- HTMLSIGEND -->'),'',$body);
				}
				$_mailObject->setHtmlBody($body, null, false);	// false = no automatic alternative, we called setBody()
				break;
			case 'openpgp':
				$_mailObject->setOpenPgpBody($_formData['body'].$attachment_links);
				break;
			default:
				$body = $this->convertHTMLToText($_formData['body'],false, false, true, true);

				if (!empty($attachment_links)) $body .= $attachment_links;

				#$_mailObject->Body = $_formData['body'];
				if(!empty($signature)) {
					$body .= ($disableRuler ?"\r\n":"\r\n-- \r\n").
						$this->convertHTMLToText($signature,true,true);
				}
				$_mailObject->setBody($body);
		}
		// add the attachments
		if (is_array($_formData) && isset($_formData['attachments']))
		{
			$connection_opened = false;
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
							$mail_bo->openConnection($mail_bo->profileID);
							$connection_opened = true;
						}
						$mail_bo->reopen($attachment['folder']);
						switch(strtoupper($attachment['type'])) {
							case 'MESSAGE/RFC':
							case 'MESSAGE/RFC822':
								$rawBody='';
								if (isset($attachment['partID'])) {
									$eml = $mail_bo->getAttachment($attachment['uid'],$attachment['partID'],0,false,true,$attachment['folder']);
									$rawBody=$eml['attachment'];
								} else {
									$rawBody        = $mail_bo->getMessageRawBody($attachment['uid'], $attachment['partID'],$attachment['folder']);
								}
								$_mailObject->addStringAttachment($rawBody, $attachment['name'], 'message/rfc822');
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
											break;
										}
									}
								}
								$_mailObject->addStringAttachment($attachmentData['attachment'], $attachment['name'], $attachment['type']);
								break;
						}
					}
					// attach files not for autosaving
					elseif ($_formData['filemode'] == Vfs\Sharing::ATTACH && !$_autosaving)
					{
						if (isset($attachment['file']) && parse_url($attachment['file'],PHP_URL_SCHEME) == 'vfs')
						{
							Vfs::load_wrapper('vfs');
							$tmp_path = $attachment['file'];
						}
						else	// non-vfs file has to be in temp_dir
						{
							$tmp_path = $GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($attachment['file']);
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
		return $inline_images ?? [];
	}

	/**
	 * Get html or text containing links to attachments
	 *
	 * We only care about file attachments, not forwarded messages or parts
	 *
	 * @param array $attachments
	 * @param string $filemode Vfs\Sharing::(ATTACH|LINK|READONL|WRITABLE)
	 * @param boolean $html
	 * @param array $recipients =array()
	 * @param string $expiration =null
	 * @param string $password =null
	 * @return string might be empty if no file attachments found
	 */
	protected function _getAttachmentLinks(array $attachments, $filemode, $html, $recipients=array(), $expiration=null, $password=null)
	{
		if ($filemode == Vfs\Sharing::ATTACH) return '';

		$links = array();
		foreach($attachments as $attachment)
		{
			$path = $attachment['file'];
			if (empty($path)) continue;	// we only care about file attachments, not forwarded messages or parts
			if (parse_url($attachment['file'],PHP_URL_SCHEME) != 'vfs')
			{
				$path = $GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($path);
			}
			// create share
			if ($filemode == Vfs\Sharing::WRITABLE || $expiration || $password)
			{
				$share = stylite_sharing::create($path, $filemode, $attachment['name'], $recipients, $expiration, $password);
			}
			else
			{
				$share = Vfs\Sharing::create('', $path, $filemode, $attachment['name'], $recipients);
			}
			$link = Vfs\Sharing::share2link($share);

			$name = Vfs::basename($attachment['name'] ? $attachment['name'] : $attachment['file']);

			if ($html)
			{
				$links[] = Api\Html::a_href($name, $link).' '.
					(is_dir($path) ? lang('Directory') : Vfs::hsize($attachment['size']));
			}
			else
			{
				$links[] = $name.' '.Vfs::hsize($attachment['size']).': '.
					(is_dir($path) ? lang('Directory') : $link);
			}
		}
		if (!$links)
		{
			return null;	// no file attachments found
		}
		elseif ($html)
		{
			return self::wrapBlockWithPreferredFont("<ul><li>".implode("</li>\n<li>", $links)."</li></ul>\n", lang('Download attachments'), 'attachmentLinks');
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
		$response = Api\Json\Response::get();
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
						if (!empty($content['processedmail_id']))
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
							catch (Api\Exception $e)
							{
								$msg = str_replace('"',"'",$e->getMessage());
								$success = false;
								error_log(__METHOD__.__LINE__.$msg);
							}
						} else {
							error_log(__METHOD__.__LINE__.': original message ('.$pMuid.') has attachments and lastDrafted ID ('.$duid.') equals the former');
						}
					} else {
						error_log(__METHOD__.__LINE__." No current draftID (".$draft_id."), or no lastDrafted Info (".$content['lastDrafted'].") or the former being equal:".array2string($content)."(, action=$action)");
					}
				} else {
					error_log(__METHOD__.__LINE__.' No headerdata found for messageUID='.$messageUid.' in Folder:'.$folder.':'.array2string($content)."(, action=$action)");
				}
			}
			else
			{
				throw new Api\Exception\WrongUserinput(lang("Error: Could not save Message as Draft"));
			}
		}
		catch (Api\Exception\WrongUserinput $e)
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
		static $contacts_obs = null;
		$addrFromList=array();
		foreach((array)$_emailAddressList as $ak => $address)
		{
			if(is_numeric($address) && $address > 0 || preg_match('/ <(\\d+)@lists.egroupware.org>$/', $address, $matches))
			{
				if (!isset($contacts_obs)) $contacts_obj = new Api\Contacts();
				// List was selected, expand to addresses
				unset($_emailAddressList[$ak]);
				foreach($contacts_obj->search('',array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home'),
					'','','',False,'AND',false,
					['list' => (int)($matches[1] ?? $address)]) as $email)
				{
					$addrFromList[] = $email['email'] ?: $email['email_home'];
				}
			}
		}
		return array_values(array_merge((array)$_emailAddressList, $addrFromList));
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
		$mail		= new Api\Mailer($this->mail_bo->profileID);

		// preserve the bcc and if possible the save to folder information
		$this->sessionData['folder']    = $_formData['folder'];
		$this->sessionData['bcc']   = $_formData['bcc'];
		$this->sessionData['mailidentity'] = $_formData['mailidentity'];
		//$this->sessionData['stationeryID'] = $_formData['stationeryID'];
		$this->sessionData['mailaccount']  = $_formData['mailaccount'];
		$this->sessionData['attachments']  = $_formData['attachments'];
		try
		{
			$acc = Mail\Account::read($this->sessionData['mailaccount']);
			//error_log(__METHOD__.__LINE__.array2string($acc));
			$identity = Mail\Account::read_identity($acc['ident_id'],true);
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
			catch (Api\Exception\WrongUserinput $e)
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
		$mail 		= new Api\Mailer($mail_bo->profileID);
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
		$this->sessionData['smime_sign']  = $_formData['smime_sign'];
		$this->sessionData['smime_encrypt']  = $_formData['smime_encrypt'];

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
			$identity = Mail\Account::read_identity((int)$this->sessionData['mailidentity'],true);
		}
		catch (Exception $e)
		{
			$identity = array();
		}
		//error_log($this->sessionData['mailaccount']);
		//error_log(__METHOD__.__LINE__.':'.array2string($this->sessionData['mailidentity']).'->'.array2string($identity));
		// create the messages and store inline images
		$inline_images = $this->createMessage($mail, $_formData, $identity);
		// remember the identity
		/** @noinspection MissingIssetImplementationInspection */
		if (!empty($mail->From) && ($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on')) $fromAddress = $mail->From;//$mail->FromName.($mail->FromName?' <':'').$mail->From.($mail->FromName?'>':'');
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
		// SMIME SIGN/ENCRYPTION
		if ($_formData['smime_sign'] == 'on' || $_formData['smime_encrypt'] == 'on' )
		{
			$recipients = array_merge($_formData['to'], (array) $_formData['cc'], (array) $_formData['bcc']);
			try	{
				if ($_formData['smime_sign'] == 'on')
				{
					if ($_formData['smime_passphrase'] != '') {
						Api\Cache::setSession(
							'mail',
							'smime_passphrase',
							$_formData['smime_passphrase'],
						(int)($GLOBALS['egw_info']['user']['preferences']['mail']['smime_pass_exp']??10) * 60
						);
					}
					$smime_success = $this->_encrypt(
						$mail,
						$_formData['smime_encrypt'] == 'on'? Mail\Smime::TYPE_SIGN_ENCRYPT: Mail\Smime::TYPE_SIGN,
						Mail::stripRFC822Addresses($recipients),
						$identity['ident_email'],
						$_formData['smime_passphrase']
					);
					if (!$smime_success)
					{
						$response = Api\Json\Response::get();
						$this->errorInfo = $_formData['smime_passphrase'] == ''?
								lang('You need to enter your S/MIME passphrase to send this message.'):
								lang('The entered passphrase is not correct! Please try again.');
						$response->call('app.mail.smimePassDialog', $this->errorInfo);
						return false;
					}
				}
				elseif ($_formData['smime_sign'] == 'off' && $_formData['smime_encrypt'] == 'on')
				{
					$smime_success =  $this->_encrypt(
						$mail,
						Mail\Smime::TYPE_ENCRYPT,
						Mail::stripRFC822Addresses($recipients),
						$identity['ident_email']
					);
				}
			}
			catch (Exception $ex)
			{
				$response = Api\Json\Response::get();
				$this->errorInfo = $ex->getMessage();
				return false;
			}
		}

		// set a higher timeout for big messages
		@set_time_limit(120);
		//$mail->SMTPDebug = 10;
		//error_log("Folder:".count(array($this->sessionData['folder']))."To:".count((array)$this->sessionData['to'])."CC:". count((array)$this->sessionData['cc']) ."bcc:".count((array)$this->sessionData['bcc']));
		if(count((array)$this->sessionData['to']) > 0 || count((array)$this->sessionData['cc']) > 0 || count((array)$this->sessionData['bcc']) > 0) {
			try {
				// do no close the session before sending, if we have to store the send text for infolog or other integration in the session
				if (!($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on' || $_formData['to_calendar'] == 'on' ))
				{
					$GLOBALS['egw']->session->commit_session();
				}
				$mail->send();
			}
			catch(Exception $e) {
				_egw_log_exception($e);
				//if( $e->details ) error_log(__METHOD__.__LINE__.array2string($e->details));
				$this->errorInfo = $e->getMessage().($e->details?'<br/>'.$e->details:'');
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
					catch (Api\Exception\WrongUserinput $e)
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
					catch (Api\Exception\WrongUserinput $e)
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
			if (isset($lastDrafted['uid']) && (empty($lastDrafted['uid']) || $lastDrafted['uid'] == ($this->sessionData['uid']??null))) $lastDrafted=false;
			//error_log(__METHOD__.__LINE__.array2string($lastDrafted));
		}
		if ($lastDrafted && is_array($lastDrafted) && $mail_bo->isDraftFolder($lastDrafted['folder']))
		{
			try
			{
				if ($this->sessionData['lastDrafted'] != ($this->sessionData['uid']??null) || !($_formData['mode']=='composefromdraft' &&
					($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on' || $_formData['to_calendar'] == 'on' )&&$this->sessionData['attachments']))
				{
					//error_log(__METHOD__.__LINE__."#".$lastDrafted['uid'].'#'.$lastDrafted['folder'].array2string($_formData));
					//error_log(__METHOD__.__LINE__."#".array2string($_formData));
					//error_log(__METHOD__.__LINE__."#".array2string($this->sessionData));
					$mail_bo->deleteMessages($lastDrafted['uid'],$lastDrafted['folder'],'remove_immediately');
				}
			}
			catch (Api\Exception $e)
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
			$mail_bo->reopen($this->sessionData['messageFolder'] ?? $this->sessionData['sourceFolder']);
			// if the draft folder is a starting part of the messages folder, the draft message will be deleted after the send
			// unless your templatefolder is a subfolder of your draftfolder, and the message is in there
			if (!empty($this->sessionData['messageFolder']) && $mail_bo->isDraftFolder($this->sessionData['messageFolder']) && !$mail_bo->isTemplateFolder($this->sessionData['messageFolder']))
			{
				try // message may be deleted already, as it maybe done by autosave
				{
					if ($_formData['mode']=='composefromdraft' &&
						!(($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on' || $_formData['to_calendar'] == 'on') && $this->sessionData['attachments']))
					{
						//error_log(__METHOD__.__LINE__."#".$this->sessionData['uid'].'#'.$this->sessionData['messageFolder']);
						$mail_bo->deleteMessages(array($this->sessionData['uid']),$this->sessionData['messageFolder'], 'remove_immediately');
					}
				}
				catch (Api\Exception $e)
				{
					//error_log(__METHOD__.__LINE__." ". str_replace('"',"'",$e->getMessage()));
					unset($e);
				}
			} else {
				$mail_bo->flagMessages("answered", $this->sessionData['uid'], $this->sessionData['messageFolder'] ?? $this->sessionData['sourceFolder']);
				//error_log(__METHOD__.__LINE__.array2string(array_keys($this->sessionData)).':'.array2string($this->sessionData['forwardedUID']).' F:'.$this->sessionData['sourceFolder']);
				if (array_key_exists('forwardFlag',$this->sessionData) && $this->sessionData['forwardFlag']=='forwarded')
				{
					try
					{
						//error_log(__METHOD__.__LINE__.':'.array2string($this->sessionData['forwardedUID']).' F:'.$this->sessionData['sourceFolder']);
						$mail_bo->flagMessages("forwarded", $this->sessionData['forwardedUID'],$this->sessionData['sourceFolder']);
					}
					catch (Api\Exception $e)
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
		if (!empty($mailaddresses) && !empty($fromAddress)) $mailaddresses['from'] = Mail\Html::decodeMailHeader($fromAddress);

		if ($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on' || $_formData['to_calendar'] == 'on' )
		{
			$this->sessionData['attachments'] = array_merge((array)$this->sessionData['attachments'], (array)$inline_images);

			foreach(array('to_infolog','to_tracker','to_calendar') as $app_key)
			{
				list(, $entryid) = explode(":", $_formData['to_integrate_ids'][0]) ?? null;
				if ($_formData[$app_key] == 'on')
				{
					$app_name = substr($app_key,3);
					// Get registered hook data of the app called for integration
					$hook = Api\Hooks::single(array('location'=> 'mail_import'),$app_name);

					// store mail / eml in temp. file to not have to download it from mail-server again
					$eml = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'mail_integrate');
					$eml_fp = fopen($eml, 'w');
					stream_copy_to_stream($mail->getRaw(), $eml_fp);
					fclose($eml_fp);
					$target = array(
						'menuaction' => $hook['menuaction'],
						'egw_data' => Link::set_data(null,'mail_integration::integrate',array(
							$mailaddresses,
							$this->sessionData['subject'],
							$this->convertHTMLToText($this->sessionData['body']),
							$this->sessionData['attachments'],
							false, // date
							$eml,
							$_formData['serverID']),true),
						'app' => $app_name
					);
					if ($entryid) $target['entry_id'] = $entryid;
					// Open the app called for integration in a popup
					// and store the mail raw data as egw_data, in order to
					// be stored from registered app method later
					Framework::popup(Egw::link('/index.php', $target),'_blank',$hook['popup']);
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
				foreach(Mail\Account::identities($this->mail_bo->profileID, true, 'params') as $identity)
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
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
			return stripslashes($_string);
		} else {
			return $_string;
		}
	}
	/**
	 * Callback function to search mail folders
	 *
	 * New et2-select(-*) widget sends query string and option array as first to parameters
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
		// new et2-select(-*) widget sends query string and option array as first to parameters
		if (!is_int($_searchStringLength)) $_searchStringLength = 2;
		if (!is_bool($_returnList)) $_returnList = false;
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
			$searchString = Api\Translation::convert($_searchString, Mail::$displayCharset,'UTF7-IMAP');
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
		Api\Json\Request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		//error_log(__METHOD__.__LINE__);
		echo json_encode($results);
		exit();
	}

	public static function ajax_searchAddress($_searchStringLength=2) {
		//error_log(__METHOD__. "request from seachAddress " . $_REQUEST['query']);
		if (!is_int($_searchStringLength)) $_searchStringLength = 2;
		$_searchString = trim($_REQUEST['query']);
		$include_lists = (boolean)$_REQUEST['include_lists'];

		$contacts_obj = new Api\Contacts();
		$results = array();
		$mailPrefs = $GLOBALS['egw_info']['user']['preferences']['mail'];
		$contactLabelPref = !is_array($mailPrefs['contactLabel']) && !empty($mailPrefs['contactLabel']) ?
			explode(',', $mailPrefs['contactLabel']) : $mailPrefs['contactLabel'];

		// Add some matching mailing lists, and some groups, limited by config
		if($include_lists)
		{
			$results += static::get_lists($_searchString, $contacts_obj);
		}

		if ($GLOBALS['egw_info']['user']['apps']['addressbook'] && strlen($_searchString)>=$_searchStringLength)
		{
			//error_log(__METHOD__.__LINE__.array2string($_searchString));
			$showAccounts = $GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] !== '1';
			$search = explode(' ', $_searchString);
			foreach ($search as $k => $v)
			{
				if (mb_strlen($v) < 3) unset($search[$k]);
			}
			$search_str = implode(' +', $search);	// tell contacts/so_sql to AND search patterns
			//error_log(__METHOD__.__LINE__.$_searchString);
			$filter = $showAccounts ? array() : array('account_id' => null);
			$filter['cols_to_search'] = array('n_prefix','n_given','n_family','org_name','email','email_home', 'contact_id');
			$cols = array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home', 'contact_id', 'etag');
			$contacts = $contacts_obj->search($search_str, $cols, 'n_fn', '', '%', false, 'OR', array(0,100), $filter);
			$cfs_type_email = Api\Storage\Customfields::get_email_cfs('addressbook');
			// additionally search the accounts, if the contact storage is not the account storage
			if ($showAccounts && $contacts_obj->so_accounts)
			{
				$filter['owner'] = 0;
				$accounts = $contacts_obj->search($search_str, $cols, 'n_fn', '', '%', false,'OR', array(0,100), $filter);

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

		if (is_array($contacts))
		{
			foreach($contacts as $contact)
			{
				$cf_emails = [];
				if ($cfs_type_email && ($cf_emails = $contacts_obj->read_customfields($contact['id'], $cfs_type_email)))
				{
					// cf_emails: [$contact['id'] => ['cf1'=>'email','cf2'=>'email2',...]]
					$cf_emails = array_values(reset($cf_emails));
				}
				foreach(array_merge(array($contact['email'],$contact['email_home']), $cf_emails) as $email)
				{
					// avoid wrong addresses, if an rfc822 encoded address is in addressbook
					//$email = preg_replace("/(^.*<)([a-zA-Z0-9_\-]+@[a-zA-Z0-9_\-\.]+)(.*)/",'$2',$email);
					$rfcAddr = Mail::parseAddressList($email);
					$_rfcAddr=$rfcAddr->first();
					if (!$_rfcAddr->valid)
					{
						continue; // skip address if we encounter an error here
					}
					$email = $_rfcAddr->mailbox.'@'.$_rfcAddr->host;

					if (method_exists($contacts_obj,'search'))
					{
						$contact['n_fn']='';
						if (!empty($contact['n_prefix']) && (empty($contactLabelPref) || in_array('n_prefix', $contactLabelPref))) $contact['n_fn'] = $contact['n_prefix'];
						if (!empty($contact['n_given']) && (empty($contactLabelPref) || in_array('n_given', $contactLabelPref))) $contact['n_fn'] .= ($contact['n_fn']?' ':'').$contact['n_given'];
						if (!empty($contact['n_family']) && (empty($contactLabelPref) || in_array('n_family', $contactLabelPref))) $contact['n_fn'] .= ($contact['n_fn']?' ':'').$contact['n_family'];
						if (!empty($contact['org_name']) && (empty($contactLabelPref) || in_array('org_name', $contactLabelPref))) $contact['n_fn'] .= ($contact['n_fn']?' ':'').'('.$contact['org_name'].')';
						$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
					}
					else
					{
						$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
					}
					$args = explode('@', trim($email));
					$args[] = trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']);
					$completeMailString = call_user_func_array('imap_rfc822_write_address', $args);
					if(!empty($email) && in_array($completeMailString ,$results) === false) {
						$result = array(
							'value' => $completeMailString,
							'label' => $completeMailString,
							// Add just name for nice display, with title for hover
							'name'  => $contact['n_fn'],
							'title' => $email,
							'lname' => $contact['n_family'],
							'fname' => $contact['n_given']
						);
						// TODO: Ralf find a cheap way to get this
						if($actual_picture)
						{
							$result['icon'] = Egw::link('/api/avatar.php', array(
								'contact_id' => $contact['id'],
								'etag'       => $contact['etag']
							));
						}
						$results[] = $result;
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
			$args = explode('@', trim($group['account_email']));
			$args[] = $name;
			$completeMailString = call_user_func_array('imap_rfc822_write_address', $args);
			$results[] = array(
				'id' => $completeMailString,
				'label' => $completeMailString,
				'name'	=> $name,
				'title' => $group['account_email']
			);
		}

		 // switch regular JSON response handling off
		Api\Json\Request::isJSONRequest(false);

		//error_log(__METHOD__.__LINE__.array2string($jsArray));
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		exit();
	}

	/**
	 * Get list of matching distribution lists when searching for email addresses
	 *
	 * The results are limited by config setting.  Default 10 each of group lists and normal lists
	 *
	 * @param String $_searchString
	 * @param Contacts $contacts_obj
	 * @return array
	 */
	protected static function get_lists($_searchString, &$contacts_obj)
	{
		$group_lists = array();
		$manual_lists = array();
		$lists = array_filter(
			$contacts_obj->get_lists(Acl::READ),
			function($element) use($_searchString) {
				return (stripos($element, $_searchString) !== false);
			}
		);

		foreach($lists as $key => $list_name)
		{
			$type = $key > 0 ? 'manual' : 'group';
			$list = array(
				'value'	=> $list_name.' <'.$key.'@lists.egroupware.org>',
				'label'	=> $list_name,
				'title' => lang('Mailinglist'),
				'icon' => Api\Image::find('api', 'email'),
			);
			${"${type}_lists"}[] = $list;
		}
		$config = Api\Config::read('mail');
		$limit = $config['address_list_limit'] ?: 10;
		$trim = function($list) use ($limit) {
			if(count($list) <= $limit) return $list;
			$list[$limit-1]['class'].= ' more_results';
			$list[$limit-1]['title'] .= '  (' . lang('%1 more', count($list) - $limit) . ')';
			return array_slice($list, 0, $limit);
		};
		return array_merge($trim($group_lists), $trim($manual_lists));
	}
	/**
	 * Merge the selected contact ID into the document given in $_REQUEST['document']
	 * and send it.
	 *
	 * @param int $contact_id
	 */
	public function ajax_merge($contact_id)
	{
		$response = Api\Json\Response::get();
		if(class_exists($_REQUEST['merge']) && is_subclass_of($_REQUEST['merge'], 'EGroupware\\Api\\Storage\\Merge'))
		{
			$document_merge = new $_REQUEST['merge']();
		}
		else
		{
			$document_merge = new Api\Contacts\Merge();
		}
		$this->mail_bo->openConnection();

		if(($error = $document_merge->check_document($_REQUEST['document'],'')))
		{
			$response->error($error);
			return;
		}

		// Actually do the merge
		$folder = $merged_mail_id = null;
		try
		{
			$results = $this->mail_bo->importMessageToMergeAndSend(
				$document_merge, Vfs::PREFIX . $_REQUEST['document'],
				// Send an extra non-numeric ID to force actual send of document
				// instead of save as draft
				array((int)$contact_id, ''),
				$folder,$merged_mail_id
			);

			// Also save as infolog
			if($merged_mail_id && $_REQUEST['to_app'] && isset($GLOBALS['egw_info']['user']['apps'][$_REQUEST['to_app']]))
			{
				$rowid = mail_ui::generateRowID($this->mail_bo->profileID, $folder, $merged_mail_id, true);
				$data = mail_integration::get_integrate_data($rowid);
				if($data && $_REQUEST['to_app'] == 'infolog')
				{
					$bo = new infolog_bo();
					$entry = $bo->import_mail($data['addresses'],$data['subject'],$data['message'],$data['attachments'],$data['date']);
					if($_REQUEST['info_type'] && isset($bo->enums['type'][$_REQUEST['info_type']]))
					{
						$entry['info_type'] = $_REQUEST['info_type'];
					}
					$bo->write($entry);
				}
			}
		}
		catch (Exception $e)
		{
			$contact = $document_merge->contacts->read((int)$contact_id);
			//error_log(__METHOD__.' ('.__LINE__.') '.' ID:'.$val.' Data:'.array2string($contact));
			$email = ($contact['email'] ? $contact['email'] : $contact['email_home']);
			$nfn = ($contact['n_fn'] ? $contact['n_fn'] : $contact['n_given'].' '.$contact['n_family']);
			$response->error(lang('Sending mail to "%1" failed', "$nfn <$email>").
				"\n".$e->getMessage()
			);
		}

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
	 * Method to do encryption on given mail object
	 *
	 * @param Api\Mailer $mail
	 * @param string $type encryption type
	 * @param array|string $recipients list of recipients
	 * @param string $sender email of sender
	 * @param string $passphrase = '', SMIME Private key passphrase
	 *
	 * @return boolean returns true if successful and false if passphrase required
	 * @throws Api\Exception\WrongUserinput if no certificate found
	 */
	protected function _encrypt($mail, $type, $recipients, $sender, $passphrase='')
	{
		$AB = new addressbook_bo();
		 // passphrase of sender private key
		$params['passphrase'] = $passphrase;

		try
		{
			$sender_cert = $AB->get_smime_keys($sender);
			if (!$sender_cert)	throw new Exception(lang("S/MIME Encryption failed because no certificate has been found for sender address: %1", $sender));
			$params['senderPubKey'] = $sender_cert[strtolower($sender)];

			if (isset($sender) && ($type == Mail\Smime::TYPE_SIGN || $type == Mail\Smime::TYPE_SIGN_ENCRYPT))
			{
				$acc_smime = Mail\Smime::get_acc_smime($this->mail_bo->profileID, $params['passphrase']);
				$params['senderPrivKey'] = $acc_smime['pkey'] ?? null;
				$params['extracerts'] = $acc_smime['extracerts'] ?? null;
			}

			if (isset($recipients) && ($type == Mail\Smime::TYPE_ENCRYPT || $type == Mail\Smime::TYPE_SIGN_ENCRYPT))
			{
				$params['recipientsCerts'] = $AB->get_smime_keys($recipients);
				foreach ($recipients as &$recipient)
				{
					if (!$params['recipientsCerts'][strtolower($recipient)]) $missingCerts []= $recipient;
				}
				if (is_array($missingCerts)) throw new Exception ('S/MIME Encryption failed because no certificate has been found for following addresses: '. implode ('|', $missingCerts));
			}

			return $mail->smimeEncrypt($type, $params);
		}
		catch(Api\Exception\WrongUserinput $e)
		{
			throw new $e;
		}
	}

	/**
	 * Builds attachments from provided UIDs and add them to sessionData
	 *
	 * @param string|array $_ids series of message ids
	 * @param int $_serverID compose current profileID
	 *
	 * @return array returns an array of attachments
	 *
	 * @throws Exception throws exception on cross account attempt
	 */
	function _get_uids_as_attachments ($_ids, $_serverID)
	{
		$ids = is_array($_ids) ? $_ids : explode(',', $_ids);
		if (is_array($ids) && $_serverID)
		{
			$parts = mail_ui::splitRowID($ids[0]);
			if ($_serverID != $parts['profileID'])
			{
				throw new Exception(lang('Cross account forward attachment is not allowed!'));
			}
		}
		foreach ($ids as &$id)
		{
			$parts = mail_ui::splitRowID($id);
			$mail_bo    = $this->mail_bo;
			$mail_bo->openConnection();
			$mail_bo->reopen($parts['folder']);
			$headers	= $mail_bo->getMessageEnvelope($parts['msgUID'], null,false,$parts['folder']);
			$this->addMessageAttachment($parts['msgUID'], null, $parts['folder'],
					$mail_bo->decode_header(($headers['SUBJECT']?$headers['SUBJECT']:lang('no subject'))).'.eml',
					'MESSAGE/RFC822', $headers['SIZE'] ? $headers['SIZE'] : lang('unknown'));
			$mail_bo->closeConnection();
		}
		return $this->sessionData['attachments'];
	}
}