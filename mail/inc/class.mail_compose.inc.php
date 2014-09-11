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

include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php');

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
		if (!isset($GLOBALS['egw_info']['flags']['js_link_registry']))
		{
			//error_log(__METHOD__.__LINE__.' js_link_registry not set, force it:'.array2string($GLOBALS['egw_info']['flags']['js_link_registry']));
			$GLOBALS['egw_info']['flags']['js_link_registry']=true;
		}
		$this->displayCharset   = $GLOBALS['egw']->translation->charset();
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
				$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		$this->mail_bo	= mail_bo::getInstance(true,$profileID);

		$profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $this->mail_bo->profileID;
		$this->mailPreferences	=& $this->mail_bo->mailPreferences;
		//force the default for the forwarding -> asmail
		if (is_array($this->mailPreferences)) {
			if (!array_key_exists('message_forwarding',$this->mailPreferences)
				|| !isset($this->mailPreferences['message_forwarding'])
				|| empty($this->mailPreferences['message_forwarding'])) $this->mailPreferences['message_forwarding'] = 'asmail';
		} else {
			$this->mailPreferences['message_forwarding'] = 'asmail';
		}
		if (is_null(mail_bo::$mailConfig)) mail_bo::$mailConfig = config::read('mail');

		$this->mailPreferences  =& $this->mail_bo->mailPreferences;
	}

	/**
	 * changeProfile
	 *
	 * @param int $icServerID
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
	 * function compose
	 * 	this function is used to fill the compose dialog with the content provided by $_content
	 *
	 * @var _content				array the etemplate content array
	 * @var msg					string a possible message to be passed and displayed to the userinterface
	 * @var _focusElement		varchar subject, to, body supported
	 * @var suppressSigOnTop	boolean
	 * @var isReply				boolean
	 */
	function compose(array $_content=null,$msg=null, $_focusElement='to',$suppressSigOnTop=false, $isReply=false)
	{
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed']) && !empty($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed']))
		{
			$sigPref = $GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'];
		}
		else
		{
			$sigPref = array();
		}
		//error_log(__METHOD__.__LINE__.array2string($sigPref));
		//lang('compose'),lang('from') // needed to be found by translationtools
		//error_log(__METHOD__.__LINE__.array2string($_REQUEST).function_backtrace());
		//error_log(__METHOD__.__LINE__.array2string($_content).function_backtrace());
		$_contentHasSigID = array_key_exists('signatureid',(array)$_content);
		$_contentHasMimeType = array_key_exists('mimeType',(array)$_content);
		if (isset($_GET['reply_id'])) $replyID = $_GET['reply_id'];
		if (!$replyID && isset($_GET['id'])) $replyID = $_GET['id'];
		if (isset($_GET['part_id'])) $partID = $_GET['part_id'];

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
			if (is_array($_content['attachments']))
			{
				foreach ($_content['attachments'] as $i => &$upload)
				{
					if (is_numeric($upload['size'])) $upload['size'] = mail_bo::show_readable_size($upload['size']);
				}
			}
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
			$this->composeID = $_content['composeID'] = $this->getComposeID();
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
					$tmp_filename = mail_bo::checkFileBasics($upload,$this->composeID,false);
				}
				catch (egw_exception_wrong_userinput $e)
				{
					$attachfailed = true;
					$alert_msg = $e->getMessage();
				}
				$upload['file'] = $upload['tmp_name'] = $tmp_filename;
				$upload['size'] = mail_bo::show_readable_size($upload['size']);
			}
		}
		// check if someone did hit delete on the attachments list
		$keysToDelete = array();
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
				foreach($toDelete as $k =>$pressed)
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
			($_content['button']['send'] || $_content['button']['saveAsDraft']||$_content['button']['saveAsDraftAndPrint'])
		)
		{
			$this->changeProfile($_content['serverID']);
			$composeProfile = $this->mail_bo->profileID;
		}
		$buttonClicked = false;
		if ($_content['button']['send'])
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
					if (!empty($_content['signatureid']) && $_content['signatureid'] != $sigPref[$this->mail_bo->profileID])
					{
						$sigPref[$this->mail_bo->profileID]=$_content['signatureid'];
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
				egw_framework::message(lang('Message send failed: %1',$message),'error');// maybe error is more appropriate
			}
		}
		if ($_content['button']['saveAsDraft']||$_content['button']['saveAsDraftAndPrint'])
		{
			$buttonClicked = $suppressSigOnTop = true;
			$savedOK = true;
			try
			{
				$_content['isDraft'] = 1;
				$previouslyDrafted = $_content['lastDrafted'];
				// save as draft
				$folder = $this->mail_bo->getDraftFolder();
				$this->mail_bo->reopen($folder);
				$status = $this->mail_bo->getFolderStatus($folder);
				//error_log(__METHOD__.__LINE__.array2string(array('Folder'=>$folder,'Status'=>$status)));
				$uidNext = $status['uidnext']; // we may need that, if the server does not return messageUIDs of saved/appended messages
				$_content['body'] = ($_content['body'] ? $_content['body'] : $_content['mail_'.($_content['mimeType'] == 'html'?'html':'plain').'text']);
				$messageUid = $this->saveAsDraft($_content,$folder); // folder may change
				if (!$messageUid) {
					//try to reopen the mail from session data
					throw new egw_exception_wrong_userinput(lang("Error: Could not save Message as Draft")." ".lang("Trying to recover from session data"));
				}
				// saving as draft, does not mean closing the message
				$messageUid = ($messageUid===true ? $uidNext : $messageUid);
				//error_log(__METHOD__.__LINE__.' (re)open drafted message with new UID: '.$messageUid.'/'.gettype($messageUid).' in folder:'.$folder);
				if ($this->mail_bo->getMessageHeader($messageUid, '',false, false, $folder))
				{
					$draft_id = mail_ui::createRowID($folder, $messageUid);
					//error_log(__METHOD__.__LINE__.' (re)open drafted message with new UID: '.$draft_id.'/'.$previouslyDrafted.' in folder:'.$folder);
					if (isset($previouslyDrafted) && $previouslyDrafted!=$draft_id)
					{
						$dhA = mail_ui::splitRowID($previouslyDrafted);
						$duid = $dhA['msgUID'];
						$dmailbox = $dhA['folder'];
						try
						{
							//error_log(__METHOD__.__LINE__."->".print_r($duid,true).' folder:'.$dmailbox.' Method:'.'remove_immediately');
							$this->mail_bo->deleteMessages($duid,$dmailbox,'remove_immediately');
						}
						catch (egw_exception $e)
						{
							$error = str_replace('"',"'",$e->getMessage());
							error_log(__METHOD__.__LINE__.$error);
						}
					}
					$_content['lastDrafted'] = $draft_id;
					//$draftContent = $this->bocompose->getDraftData($this->mail_bo->icServer, $folder, $messageUid);
					//$this->compose($draftContent,null,'to',true);
					//return true;
				}
			}
			catch (egw_exception_wrong_userinput $e)
			{
				$error = str_replace('"',"'",$e->getMessage());
				error_log(__METHOD__.__LINE__.$error);
				$savedOK = false;
			}
			//error_log(__METHOD__.__LINE__.' :'.$draft_id.'->'.$savedOK);
			if ($savedOK)
			{
				egw_framework::message(lang('Message saved successfully.'),'mail');
				$response = egw_json_response::get();
				if (isset($previouslyDrafted) && $previouslyDrafted!=$draft_id) $response->call('opener.egw_refresh',lang('Message saved successfully.'),'mail',$previouslyDrafted,'delete');
				$response->call('opener.egw_refresh',lang('Message saved successfully.'),'mail',$draft_id,'add');
				if ($_content['button']['saveAsDraftAndPrint'])
				{
					$response->call('app.mail.mail_compose_print',"mail::" .$draft_id);
				}	
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
			$content['mail_htmltext'] = translation::convertHTMLToText($content['mail_htmltext'],$charset=false,$stripcrl=false,$stripalltags=true);

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
			(!empty($composeCache['signatureid']) && !empty($_content['signatureid']) && $_content['signatureid'] != $composeCache['signatureid'])
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
			$_oldSig = $composeCache['signatureid'];
			$_signatureid = ($newSig?$newSig:$_content['signatureid']);
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
				$oldSigText = str_replace(array("\r","\t","<br />\n",": "),array("","","<br />",":"),($_currentMode == 'html'?html::purify($oldSigText,$htmlConfig,array(),true):$oldSigText));
				//error_log(__METHOD__.'Old(clean):'.$oldSigText.'#');
				if ($_currentMode == 'html')
				{
					$content['body'] = str_replace("\n",'\n',$content['body']);	// dont know why, but \n screws up preg_replace
					$styles = mail_bo::getStyles(array(array('body'=>$content['body'])));
					if (stripos($content['body'],'style')!==false) translation::replaceTagsCompletley($content['body'],'style',$endtag='',$addbracesforendtag=true); // clean out empty or pagewide style definitions / left over tags
				}
				$content['body'] = str_replace(array("\r","\t","<br />\n",": "),array("","","<br />",":"),($_currentMode == 'html'?html::purify($content['body'],mail_bo::$htmLawed_config,array(),true):$content['body']));
				mail_bo::$htmLawed_config = $_htmlConfig;
				if ($_currentMode == 'html')
				{
					$content['body'] = preg_replace($reg='|'.preg_quote('<!-- HTMLSIGBEGIN -->','|').'.*'.preg_quote('<!-- HTMLSIGEND -->','|').'|u',
						$rep='<!-- HTMLSIGBEGIN -->'.$sigText.'<!-- HTMLSIGEND -->', $in=$content['body'], -1, $replaced);
					$content['body'] = str_replace(array('\n',"\xe2\x80\x93","\xe2\x80\x94","\xe2\x82\xac"),array("\n",'&ndash;','&mdash;','&euro;'),$content['body']);
					//error_log(__METHOD__."() preg_replace('$reg', '$rep', '$in', -1)='".$content['body']."', replaced=$replaced");
					if ($replaced)
					{
						$content['signatureid'] = $_content['signatureid'] = $presetSig = $_signatureid;
						$found = false; // this way we skip further replacement efforts
					}
					else
					{
						// try the old way
						$found = (strlen(trim($oldSigText))>0?strpos($content['body'],trim($oldSigText)):false);
					}
				}
				else
				{
					$found = (strlen(trim($oldSigText))>0?strpos($content['body'],trim($oldSigText)):false);
				}

				if ($found !== false && $_oldSig != -2 && !(empty($oldSigText) || trim($this->convertHTMLToText($oldSigText,true,true)) ==''))
				{
					//error_log(__METHOD__.'Old Content:'.$content['body'].'#');
					$_oldSigText = preg_quote($oldSigText,'~');
					//error_log(__METHOD__.'Old(masked):'.$_oldSigText.'#');
					$content['body'] = preg_replace('~'.$_oldSigText.'~mi',$sigText,$content['body'],1);
					//error_log(__METHOD__.'new Content:'.$content['body'].'#');
				}

				if ($_oldSig == -2 && (empty($oldSigText) || trim($this->convertHTMLToText($oldSigText,true,true)) ==''))
				{
					// if there is no sig selected, there is no way to replace a signature
				}

				if ($found === false)
				{
					if($this->_debug) error_log(__METHOD__." Old Signature failed to match:".$oldSigText);
					if($this->_debug) error_log(__METHOD__." Compare content:".$content['body']);
				}
				else
				{
					$content['signatureid'] = $_content['signatureid'] = $presetSig = $_signatureid;
				}
				if ($styles)
				{
					//error_log($styles);
					$content['body'] = $styles.$content['body'];
				}
	/*
				if ($_currentMode == 'html')
				{
					$_content = utf8_decode($_content);
				}

				$escaped = utf8_encode(str_replace(array("'", "\r", "\n"), array("\\'", "\\r", "\\n"), $_content));
				//error_log(__METHOD__.$escaped);
				if ($_currentMode == 'html')
				else
	*/
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
											$str = $GLOBALS['egw']->translation->convert(trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']) .' <'. trim($email) .'>', $this->charset, 'utf-8');
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
							if ($formData['type'] == egw_vfs::DIR_MIME_TYPE) continue;	// ignore directories
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
				foreach ($rarr as $ri => $rval)
				{
					//must contain =
					if (strpos($rval,'=')!== false)
					{
						$k = $v = '';
						list($k,$v) = explode('=',$rval,2);
						$karr[$k] = $v;
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
			$presetSig = (strtolower($_REQUEST['signature']) == 'no' ? -2 : -1);
		}
		if (($suppressSigOnTop || $content['isDraft']) && !empty($content['signatureid'])) $presetSig = (int)$content['signatureid'];
		//if (($suppressSigOnTop || $content['isDraft']) && !empty($content['stationeryID'])) $presetStationery = $content['stationeryID'];
		$presetId = NULL;
		if (($suppressSigOnTop || $content['isDraft']) && !empty($content['mailaccount'])) $presetId = (int)$content['mailaccount'];
		if (!empty($sigPref[$this->mail_bo->profileID]) && (empty($presetSig) || $presetSig==-1 || empty($content['signatureid']) || $content['signatureid']==-1)) $presetSig=$sigPref[$this->mail_bo->profileID];

/*

		$this->t->set_var("focusElement",$_focusElement);

*/

/*
		lang('No recipient address given!');
		lang('No subject given!');
		lang("You can either choose to save as infolog OR tracker, not both.");
*/
		// prepare signatures, the selected sig may be used on top of the body
		//identities and signature stuff
		$allIdentities = mail_bo::getAllIdentities();
		$selectedMailAccount = ($content['mailaccount']?$content['mailaccount']:$this->mail_bo->profileID);
		$acc = emailadmin_account::read($this->mail_bo->profileID);
		$selectSignatures = array(
			'-2' => lang('no signature')
		);
		//unset($allIdentities[0]);
		//_debug_array($allIdentities);
		if (is_null(mail_bo::$mailConfig)) mail_bo::$mailConfig = config::read('mail');
		// not set? -> use default, means full display of all available data
		if (!isset(mail_bo::$mailConfig['how2displayIdentities'])) mail_bo::$mailConfig['how2displayIdentities'] ='';
		$defaultIds = array();
		$identities = array();
		foreach($allIdentities as $key => $singleIdentity) {
			if (isset($identities[$singleIdentity['acc_id']])) continue; // only use the first
			$iS = mail_bo::generateIdentityString($singleIdentity);
			if (mail_bo::$mailConfig['how2displayIdentities']=='' || count($allIdentities) ==1)
			{
				$id_prepend ='';
			}
			else
			{
				$id_prepend = '('.$singleIdentity['ident_id'].') ';
			}
			if(empty($defaultIds)&& $singleIdentity['ident_id']==$acc['ident_id'])
			{
				$defaultIds[$singleIdentity['ident_id']] = $singleIdentity['ident_id'];
				$selectedSender = $singleIdentity['acc_id'];
			}
			//if ($singleIdentity->default) error_log(__METHOD__.__LINE__.':'.$presetId.'->'.$key.'('.$singleIdentity->id.')'.'#'.$iS.'#');
			if (array_search($id_prepend.$iS,$identities)===false)
			{
				$identities[$singleIdentity['acc_id']] = $id_prepend.$iS;
				$sel_options['mailaccount'][$singleIdentity['acc_id']] = $id_prepend.$iS;
			}
		}
		$sel_options['mailaccount'] = iterator_to_array(emailadmin_account::search());
		//error_log(__METHOD__.__LINE__.' Identities regarded/marked as default:'.array2string($defaultIds). ' MailProfileActive:'.$this->mail_bo->profileID);
		// if there are 2 defaultIDs, its most likely, that the user choose to set
		// the one not being the activeServerProfile to be his default Identity
		//if (count($defaultIds)>1) unset($defaultIds[$this->mail_bo->profileID]);
		$allSignatures = $this->mail_bo->getAccountIdentities($selectedMailAccount);
		$defaultIdentity = 0;
		foreach($allSignatures as $key => $singleIdentity) {
			//error_log(__METHOD__.__LINE__.array2string($singleIdentity));
			//$identities[$singleIdentity['ident_id']] = $singleIdentity['ident_realname'].' <'.$singleIdentity['ident_email'].'>';
			$iS = mail_bo::generateIdentityString($singleIdentity);
			if (mail_bo::$mailConfig['how2displayIdentities']=='' || count($allIdentities) ==1)
			{
				$id_prepend ='';
			}
			else
			{
				$id_prepend = '('.$singleIdentity['ident_id'].') ';
			}
			if (!empty($singleIdentity['ident_name']) && !in_array(lang('Signature').': '.$id_prepend.$singleIdentity['ident_name'],$selectSignatures))
			{
				$buff = $singleIdentity['ident_name'];
			}
			else
			{
				$buff = trim(substr(str_replace(array("\r\n","\r","\n","\t"),array(" "," "," "," "),translation::convertHTMLToText($singleIdentity['ident_signature'])),0,50));
			}
			$sigDesc = $buff?$buff:lang('none');

			if ($sigDesc == lang('none')) $sigDesc = (!empty($singleIdentity['ident_name'])?$singleIdentity['ident_name']:$singleIdentity['ident_realname'].($singleIdentity['ident_org']?' ('.$singleIdentity['ident_org'].')':''));
			$selectSignatures[$singleIdentity['ident_id']] = lang('Signature').': '.$id_prepend.$sigDesc;

			if(in_array($singleIdentity['ident_id'],$defaultIds) && $defaultIdentity==0)
			{
				//_debug_array($singleIdentity);
				$defaultIdentity = $singleIdentity['ident_id'];
				//$defaultIdentity = $key;
				if (empty($content['signatureid'])) $content['signatureid'] = (!empty($singleIdentity['ident_signature']) ? $singleIdentity['ident_id'] : $content['signatureid']);
			}
		}

		// fetch the signature, prepare the select box, ...
		if (empty($content['signatureid'])) {
			$content['signatureid'] = $acc['ident_id'];
		}

		$disableRuler = false;
		//_debug_array(($presetSig ? $presetSig : $content['signatureid']));
		try
		{
			$signature = emailadmin_account::read_identity(($presetSig ? $presetSig : $content['signatureid']),true);
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
			$font_part = '<span '.($font||$font_size?'style="':'').($font?'font-family:'.$font.'; ':'').($font_size?'font-size:'.$font_size.'; ':'').'">';
			$font_span = $font_part.'&nbsp;'.'</span>';
			if (empty($font) && empty($font_size)) $font_span = '';
		}
		// the font span should only be applied on first load or on switch plain->html and the absence of the font_part of the span
		if (!$isFirstLoad && !empty($font_span) && stripos($content['body'],$font_part)===false) $font_span = '';
		//remove possible html header stuff
		if (stripos($content['body'],'<html><head></head><body>')!==false) $content['body'] = str_ireplace(array('<html><head></head><body>','</body></html>'),array('',''),$content['body']);
		//error_log(__METHOD__.__LINE__.array2string($this->mailPreferences));
		$blockElements = array('address','blockquote','center','del','dir','div','dl','fieldset','form','h1','h2','h3','h4','h5','h6','hr','ins','isindex','menu','noframes','noscript','ol','p','pre','table','ul');
		if (isset($this->mailPreferences['insertSignatureAtTopOfMessage']) &&
			$this->mailPreferences['insertSignatureAtTopOfMessage'] &&
			!(isset($_POST['mySigID']) && !empty($_POST['mySigID']) ) && !$suppressSigOnTop
		)
		{
			$insertSigOnTop = ($insertSigOnTop?$insertSigOnTop:true);
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
				$before = (!empty($font_span) && !($insertSigOnTop === 'below')?$font_span:'&nbsp;').($disableRuler?''/*($sigTextStartsWithBlockElement?'':'<p style="margin:0px;"/>')*/:'<hr style="border:1px dotted silver; width:90%;">');
				$inbetween = '&nbsp;<br>';
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
				$content['body'] = $before.($content['mimeType'] == 'html'?$sigText:$this->convertHTMLToText($sigText,true,true)).$inbetween.$content['body'];
			}
		}
		else
		{
			$content['body'] = ($font_span?($isFirstLoad == "switchedplaintohtml"?$font_part:$font_span):/*($content['mimeType'] == 'html'?'&nbsp;':'')*/'').$content['body'].($isFirstLoad == "switchedplaintohtml"?"</span>":"");
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
		if($content['mimeType'] == 'html') {
			$ishtml=1;
		} else {
			$ishtml=0;
		}
		// signature stuff set earlier
		//_debug_array($selectSignatures);
		$sel_options['signatureid'] = $selectSignatures;
		$content['signatureid'] = ($presetSig ? $presetSig : $content['signatureid']);
		//_debug_array($sel_options['signatureid'][$content['signatureid']]);
		// end signature stuff

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
				if ($destination=='replyto' && str_replace('"','',$value) == str_replace('"','',$identities[($presetId ? $presetId : $defaultIdentity)]))
				{
					// preserve/restore the value to content.
					$content[strtolower($destination)][]=$value;
					continue;
				}
				//error_log(__METHOD__.__LINE__.array2string(array('key'=>$key,'value'=>$value)));
				$value = htmlspecialchars_decode($value,ENT_COMPAT);
				$value = str_replace("\"\"",'"',$value);
				$address_array = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($value):$value), '');
				//unset($content[strtolower($destination)]);
				foreach((array)$address_array as $addressObject) {
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
			if (!$_contentHasSigID && $content['signatureid'] && array_key_exists('signatureid',$_content)) unset($_content['signatureid']);
			$content = array_merge($content,$_content);

			if (!empty($content['folder'])) $sel_options['folder']=$this->ajax_searchFolder(0,true);
			$content['mailaccount'] = (empty($content['mailaccount'])?($selectedSender?$selectedSender:$this->mail_bo->profileID):$content['mailaccount']);
		}
		else
		{
			//error_log(__METHOD__.__LINE__.array2string(array($sel_options['mailaccount'],$selectedSender)));
			$content['mailaccount'] = ($selectedSender?$selectedSender:$this->mail_bo->profileID);
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
		if (!isset($content['priority']) || empty($content['priority'])) $content['priority']=3;
		//$GLOBALS['egw_info']['flags']['currentapp'] = 'mail';//should not be needed
		$etpl = new etemplate_new('mail.compose');

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
		$preserv['mode'] = $content['mode'];
		// convert it back to checkbox expectations
		if($content['mimeType'] == 'html') {
			$content['mimeType']=1;
		} else {
			$content['mimeType']=0;
		}

		//error_log(__METHOD__.__LINE__.array2string($content));
		$etpl->exec('mail.mail_compose.compose',$content,$sel_options,$readonlys,$preserv,2);
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
			$composeProfile = $this->mail_bo->profileID;
		}
		$icServer = $this->mail_bo->icServer;
		if (!empty($folder) && !empty($msgUID) )
		{
			// this fill the session data with the values from the original email
			switch($from)
			{
				case 'composeasnew':
				case 'composefromdraft':
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
					foreach ($replyIds as $k => $mail_id)
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
			preg_match('/^([a-z_-]+_merge)$/', $_REQUEST['merge'], $merge_class);
			$merge_class = $merge_class[1] ? $merge_class[1] : 'addressbook_merge';
			$document_merge = new $merge_class();
			$this->mail_bo->openConnection();
			$merge_ids = $_REQUEST['preset']['mailtocontactbyid'] ? $_REQUEST['preset']['mailtocontactbyid'] : $mail_id;
			$merge_ids = is_array($merge_ids) ? $merge_ids : explode(',',$merge_ids);
			try
			{
				$merged_mail_id = '';
				$folder = '';
				if($error = $document_merge->check_document($_REQUEST['document'],''))
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
		if(!empty($_addressObject->personal) && !empty($_addressObject->mailbox) && !empty($_addressObject->host)) {
			return sprintf('"%s" <%s@%s>', $this->mail_bo->decode_header($_addressObject->personal), $_addressObject->mailbox, $this->mail_bo->decode_header($_addressObject->host,'FORCE'));
		} elseif(!empty($_addressObject->mailbox) && !empty($_addressObject->host)) {
			return sprintf("%s@%s", $_addressObject->mailbox, $this->mail_bo->decode_header($_addressObject->host,'FORCE'));
		} else {
			return $this->mail_bo->decode_header($_addressObject->mailbox,true);
		}
	}

	// create a hopefully unique id, to keep track of different compose windows
	// if you do this, you are creating a new email
	function getComposeID()
	{
		$this->composeID = $this->getRandomString();

		return $this->composeID;
	}

	// $_mode can be:
	// single: for a reply to one address
	// all: for a reply to all
	function getDraftData($_icServer, $_folder, $_uid, $_partID=NULL)
	{
		$this->sessionData['to'] = array();

		$mail_bo = $this->mail_bo;
		$mail_bo->openConnection();
		$mail_bo->reopen($_folder);

		// get message headers for specified message
		#$headers	= $mail_bo->getMessageHeader($_folder, $_uid);
		$headers	= $mail_bo->getMessageEnvelope($_uid, $_partID);
//_debug_array($headers); exit;
		$addHeadInfo = $mail_bo->getMessageHeader($_uid, $_partID);
		//error_log(__METHOD__.__LINE__.array2string($headers));
		if (!empty($addHeadInfo['X-MAILFOLDER'])) {
			foreach ( explode('|',$addHeadInfo['X-MAILFOLDER']) as $val ) {
				if ($mail_bo->folderExists($val)) $this->sessionData['folder'][] = $val;
			}
		}
		if (!empty($addHeadInfo['X-SIGNATURE'])) {
			// with the new system it would be the identity
			try
			{
				$identity = emailadmin_account::read_identity($addHeadInfo['X-SIGNATURE']);
				$this->sessionData['signatureid'] = $addHeadInfo['X-SIGNATURE'];
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
		if (!empty($addHeadInfo['X-IDENTITY'])) {
			// with the new system it would the identity is the account id
			try
			{
				$acc = emailadmin_account::read($addHeadInfo['X-IDENTITY']);
				$this->sessionData['mailaccount'] = $addHeadInfo['X-IDENTITY'];
			}
			catch (Exception $e)
			{
				// fail silently
				$this->sessionData['mailaccount'] = $mail_bo->profileID;
			}
		}
		// if the message is located within the draft folder, add it as last drafted version (for possible cleanup on abort))
		if ($mail_bo->isDraftFolder($_folder)) $this->sessionData['lastDrafted'] = mail_ui::createRowID($_folder, $_uid);//array('uid'=>$_uid,'folder'=>$_folder);
		$this->sessionData['uid'] = $_uid;
		$this->sessionData['messageFolder'] = $_folder;
		$this->sessionData['isDraft'] = true;
		foreach((array)$headers['CC'] as $val) {
			$rfcAddr=imap_rfc822_parse_adrlist($val, '');
			$_rfcAddr = $rfcAddr[0];
			if ($_rfcAddr->host=='.SYNTAX-ERROR.') continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (empty($_rfcAddr->mailbox) && empty($_rfcAddr->host)) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(!$foundAddresses[$keyemail]) {
				$address = $val;
				$address = $this->mail_bo->decode_header($address,true);
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
			$rfcAddr=imap_rfc822_parse_adrlist($val, '');
			$_rfcAddr = $rfcAddr[0];
			if ($_rfcAddr->host=='.SYNTAX-ERROR.') continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (empty($_rfcAddr->mailbox) && empty($_rfcAddr->host)) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(!$foundAddresses[$keyemail]) {
				$address = $val;
				$address = $this->mail_bo->decode_header($address,true);
				$this->sessionData['to'][] = $val;
				$foundAddresses[$keyemail] = true;
			}
		}

		foreach((array)$headers['REPLY-TO'] as $val) {
			$rfcAddr=imap_rfc822_parse_adrlist($val, '');
			$_rfcAddr = $rfcAddr[0];
			if ($_rfcAddr->host=='.SYNTAX-ERROR.') continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (empty($_rfcAddr->mailbox) && empty($_rfcAddr->host)) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(!$foundAddresses[$keyemail]) {
				$address = $val;
				$address = $this->mail_bo->decode_header($address,true);
				$this->sessionData['replyto'][] = $val;
				$foundAddresses[$keyemail] = true;
			}
		}

		foreach((array)$headers['BCC'] as $val) {
			$rfcAddr=imap_rfc822_parse_adrlist($val, '');
			$_rfcAddr = $rfcAddr[0];
			if ($_rfcAddr->host=='.SYNTAX-ERROR.') continue;
			if($_rfcAddr->mailbox == 'undisclosed-recipients' || (empty($_rfcAddr->mailbox) && empty($_rfcAddr->host)) ) {
				continue;
			}
			$keyemail=$_rfcAddr->mailbox.'@'.$_rfcAddr->host;
			if(!$foundAddresses[$keyemail]) {
				$address = $val;
				$address = $this->mail_bo->decode_header($address,true);
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

		if($attachments = $mail_bo->getMessageAttachments($_uid,$_partID)) {
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
			if($attachments = $mail_bo->getMessageAttachments($_uid,$_partID)) {
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
		}
		//error_log(__METHOD__.__LINE__.array2string($tmpFileName));

		if ($eliminateDoubleAttachments == true)
			foreach ((array)$_content['attachments'] as $k =>$attach)
				if ($attach['name'] && $attach['name'] == $_formData['name'] &&
					strtolower($_formData['type'])== strtolower($attach['type']) &&
					stripos($_formData['file'],'vfs://') !== false) return;

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
		if(isset($_GET['filename'])) $attachment['filename']	= $_GET['filename'];
		if(isset($_GET['tmpname'])) $attachment['tmp_name']	= $_GET['tmpname'];
		if(isset($_GET['name'])) $attachment['name']	= $_GET['name'];
		//if(isset($_GET['size'])) $attachment['size']	= $_GET['size'];
		if(isset($_GET['type'])) $attachment['type']	= $_GET['type'];

		//error_log(__METHOD__.__LINE__.array2string($_GET));
		if (isset($attachment['filename']) && parse_url($attachment['filename'],PHP_URL_SCHEME) == 'vfs')
		{
			egw_vfs::load_wrapper('vfs');
		}
		$attachment['attachment'] = file_get_contents($attachment['tmp_name']);
		//error_log(__METHOD__.__LINE__.' FileSize:'.filesize($attachment['tmp_name']));
		if ($_GET['mode'] != "save")
		{
			if (strtoupper($attachment['type']) == 'TEXT/DIRECTORY')
			{
				$sfxMimeType = $attachment['type'];
				$buff = explode('.',$attachment['filename']);
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
		$filename = ($attachment['name']?$attachment['name']:($attachment['filename']?$attachment['filename']:$mailbox.'_uid'.$uid.'_part'.$part));
		html::content_header($filename,$attachment['type'],0,True,($_GET['mode'] == "save"));
		echo $attachment['attachment'];

		$GLOBALS['egw']->common->egw_exit();
		exit;
	}

	/**
	 * getRandomString - function to be used to fetch a random string and md5 encode that one
	 * @param none
	 * @return string - a random number which is md5 encoded
	 */
	function getRandomString() {
		return mail_bo::getRandomString();
	}

	/**
	 * testIfOneKeyInArrayDoesExistInString - function to be used to fetch a random string and md5 encode that one
	 * @param array arrayToTestAgainst to test its keys against haystack
	 * @param string haystack
	 * @return boolean
	 */
	function testIfOneKeyInArrayDoesExistInString($arrayToTestAgainst,$haystack) {
		foreach ($arrayToTestAgainst as $k => $v)
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
	 * getReplyData - function to gather the replyData and save it with the session, to be used then.
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

		$fromAddress = mail_bo::htmlspecialchars(implode(', ', str_replace(array('<','>'),array('[',']'),$headers['FROM'])));

		$toAddressA = array();
		$toAddress = '';
		foreach ($headers['TO'] as $mailheader) {
			$toAddressA[] =  $mailheader;
		}
		if (count($toAddressA)>0)
		{
			$toAddress = mail_bo::htmlspecialchars(implode(', ', str_replace(array('<','>'),array('[',']'),$toAddressA)));
			$toAddress = @htmlspecialchars(lang("to")).": ".$toAddress.($bodyParts['0']['mimeType'] == 'text/html'?"<br>":"\r\n");
		}
		$ccAddressA = array();
		$ccAddress = '';
		foreach ($headers['CC'] as $mailheader) {
			$ccAddressA[] =  $mailheader;
		}
		if (count($ccAddressA)>0)
		{
			$ccAddress = mail_bo::htmlspecialchars(implode(', ', str_replace(array('<','>'),array('[',']'),$ccAddressA)));
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

				$newBody        = explode("\n",$newBody);
				$this->sessionData['body'] .= "\r\n";
				// create body new, with good line breaks and indention
				foreach($newBody as $value) {
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
		$_body	= preg_replace($nonDisplayAbleCharacters, '', $_body);

		return $_body;
	}

	function createMessage(&$_mailObject, $_formData, $_identity, $_signature = false, $_convertLinks=false)
	{
		$mail_bo	= $this->mail_bo;
		$_mailObject->PluginDir = EGW_SERVER_ROOT."/phpgwapi/inc/";
		$activeMailProfile = emailadmin_account::read($this->mail_bo->profileID);
		$_mailObject->IsSMTP();
		$_mailObject->CharSet	= $this->displayCharset;
		// you need to set the sender, if you work with different identities, since most smtp servers, dont allow
		// sending in the name of someone else
		if ($_identity['ident_id'] != $activeMailProfile['ident_id'] && !empty($_identity['ident_email']) && strtolower($activeMailProfile['ident_email']) != strtolower($_identity['ident_email']))
		{
			error_log(__METHOD__.__LINE__.' Faking From/SenderInfo for '.$activeMailProfile['ident_email'].' with ID:'.$activeMailProfile['ident_id'].'. Identitiy to use for sending:'.array2string($_identity));
		}
		$_mailObject->Sender  = (!empty($_identity['ident_email'])? $_identity['ident_email'] : $activeMailProfile['ident_email']);
		if ($_signature && !empty($_signature['ident_email']) && $_identity['ident_email']!=$_signature['ident_email'])
		{
			error_log(__METHOD__.__LINE__.' Faking From for '.$activeMailProfile['ident_email'].' with ID:'.$activeMailProfile['ident_id'].'. Identitiy to use for sending:'.array2string($_signature));
			$_mailObject->From 	= $_signature['ident_email'];
			$_mailObject->FromName = $_mailObject->EncodeHeader(mail_bo::generateIdentityString($_signature,false));
		}
		else
		{
			$_mailObject->From 	= $_identity['ident_email'];
			$_mailObject->FromName = $_mailObject->EncodeHeader(mail_bo::generateIdentityString($_identity,false));
		}
		$_mailObject->Priority = $_formData['priority'];
		$_mailObject->Encoding = 'quoted-printable';
		$_mailObject->AddCustomHeader('X-Mailer: EGroupware-Mail');
		if(isset($_formData['in-reply-to']) && !empty($_formData['in-reply-to'])) {
			$_mailObject->AddCustomHeader('In-Reply-To: '. $_formData['in-reply-to']);
		}
		if(isset($_formData['references']) && !empty($_formData['references'])) {
			$_mailObject->AddCustomHeader('References: '. $_formData['references']);
		}
		if($_formData['disposition']) {
			$_mailObject->AddCustomHeader('Disposition-Notification-To: '. $_identity['ident_email']);
		}
		if(!empty($_identity->organization) && (mail_bo::$mailConfig['how2displayIdentities'] == '' || mail_bo::$mailConfig['how2displayIdentities'] == 'orgNemail')) {
			#$_mailObject->AddCustomHeader('Organization: '. $mail_bo->encodeHeader($_identity->organization, 'q'));
			$_mailObject->AddCustomHeader('Organization: '. $_identity['ident_org']);
		}

		// Expand any mailing lists
		foreach(array('to','cc','bcc') as $field)
		{
			foreach((array)$_formData[$field] as $field_key => $address)
			{
				if(is_int($address))
				{
					// List was selected, expand to addresses
					unset($_formData[$field][$field_key]);
					$list = $GLOBALS['egw']->contacts->search('',array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home'),'','','',False,'AND',false,array('list' =>(int)$address));
					// Just add email addresses, they'll be checked below
					foreach($list as $email)
					{
						$_formData[$field][] = $email['email'] ? $email['email'] : $email['email_home'];
					}
				}
			}
		}

		foreach((array)$_formData['to'] as $address) {
			$address_array	= imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address), '');
			foreach((array)$address_array as $addressObject) {
				if ($addressObject->host == '.SYNTAX-ERROR.') continue;
				$_emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
				$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$mail_bo->idna2->encode($addressObject->host) : '');
				#$emailName = $mail_bo->encodeHeader($addressObject->personal, 'q');
				#$_mailObject->AddAddress($emailAddress, $emailName);
				$_mailObject->AddAddress($emailAddress, str_replace(array('@'),' ',($addressObject->personal?$addressObject->personal:$_emailAddress)));
			}
		}

		foreach((array)$_formData['cc'] as $address) {
			$address_array	= imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
			foreach((array)$address_array as $addressObject) {
				if ($addressObject->host == '.SYNTAX-ERROR.') continue;
				$_emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
				$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$mail_bo->idna2->encode($addressObject->host) : '');
				#$emailName = $mail_bo->encodeHeader($addressObject->personal, 'q');
				#$_mailObject->AddCC($emailAddress, $emailName);
				$_mailObject->AddCC($emailAddress, str_replace(array('@'),' ',($addressObject->personal?$addressObject->personal:$_emailAddress)));
			}
		}

		foreach((array)$_formData['bcc'] as $address) {
			$address_array	= imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
			foreach((array)$address_array as $addressObject) {
			if ($addressObject->host == '.SYNTAX-ERROR.') continue;
				$_emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
				$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$mail_bo->idna2->encode($addressObject->host) : '');
				#$emailName = $mail_bo->encodeHeader($addressObject->personal, 'q');
				#$_mailObject->AddBCC($emailAddress, $emailName);
				$_mailObject->AddBCC($emailAddress, str_replace(array('@'),' ',($addressObject->personal?$addressObject->personal:$_emailAddress)));
			}
		}

		foreach((array)$_formData['replyto'] as $address) {
			$address_array  = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
			foreach((array)$address_array as $addressObject) {
				if ($addressObject->host == '.SYNTAX-ERROR.') continue;
				$_emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
				$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
				#$emailName = $mail_bo->encodeHeader($addressObject->personal, 'q');
				#$_mailObject->AddBCC($emailAddress, $emailName);
				$_mailObject->AddReplyto($emailAddress, str_replace(array('@'),' ',($addressObject->personal?$addressObject->personal:$_emailAddress)));
			}
		}

		//$_mailObject->WordWrap = 76; // as we break lines ourself, we will not need/use the buildin WordWrap
		#$_mailObject->Subject = $mail_bo->encodeHeader($_formData['subject'], 'q');
		$_mailObject->Subject = $_formData['subject'];
		#$realCharset = mb_detect_encoding($_formData['body'] . 'a' , strtoupper($this->displayCharset).',UTF-8, ISO-8859-1');
		#error_log("bocompose::createMessage:".$realCharset);
		// this should never happen since we come from the edit dialog
		if (mail_bo::detect_qp($_formData['body'])) {
			error_log("Error: bocompose::createMessage found QUOTED-PRINTABLE while Composing Message. Charset:$realCharset Message:".print_r($_formData['body'],true));
			$_formData['body'] = preg_replace('/=\r\n/', '', $_formData['body']);
			$_formData['body'] = quoted_printable_decode($_formData['body']);
		}
		$disableRuler = false;
		#if ($realCharset != $this->displayCharset) error_log("Error: bocompose::createMessage found Charset ($realCharset) differs from DisplayCharset (".$this->displayCharset.")");
		$signature = $_signature['ident_signature'];

		if ((isset($this->mailPreferences['insertSignatureAtTopOfMessage']) && $this->mailPreferences['insertSignatureAtTopOfMessage']))
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

		if($signature)
		{
			$signature = mail_bo::merge($signature,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
		}

		if($_formData['mimeType'] =='html') {
			$_mailObject->IsHTML(true);
			if(!empty($signature)) {
				#$_mailObject->Body    = array($_formData['body'], $_signature['signature']);
				$_mailObject->Body = $_formData['body'] .
					($disableRuler ?'<br>':'<hr style="border:1px dotted silver; width:90%;">').
					$signature;
				$_mailObject->AltBody = $this->convertHTMLToText($_formData['body'],true,true).
					($disableRuler ?"\r\n":"\r\n-- \r\n").
					$this->convertHTMLToText($signature,true,true);
				#print "<pre>$_mailObject->AltBody</pre>";
				#print htmlentities($_signature['signature']);
			} else {
				$_mailObject->Body	= $_formData['body'];
				$_mailObject->AltBody	= $this->convertHTMLToText($_formData['body'],true,true);
			}
			// convert URL Images to inline images - if possible
			if ($_convertLinks) mail_bo::processURL2InlineImages($_mailObject, $_mailObject->Body);
			if (strpos($_mailObject->Body,"<!-- HTMLSIGBEGIN -->")!==false)
			{
				$_mailObject->Body = str_replace(array('<!-- HTMLSIGBEGIN -->','<!-- HTMLSIGEND -->'),'',$_mailObject->Body);
			}
		} else {
			$_mailObject->IsHTML(false);
			$_mailObject->Body = $this->convertHTMLToText($_formData['body'],false);
			#$_mailObject->Body = $_formData['body'];
			if(!empty($signature)) {
				$_mailObject->Body .= ($disableRuler ?"\r\n":"\r\n-- \r\n").
					$this->convertHTMLToText($signature,true,true);
			}
		}

		// add the attachments
		$mail_bo->openConnection();
		if (is_array($_formData) && isset($_formData['attachments']))
		{
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
						$mail_bo->reopen($attachment['folder']);
						switch($attachment['type']) {
							case 'MESSAGE/RFC822':
								$rawHeader='';
								if (isset($attachment['partID'])) {
									$rawHeader      = $mail_bo->getMessageRawHeader($attachment['uid'], $attachment['partID'],$attachment['folder']);
								}
								$rawBody        = $mail_bo->getMessageRawBody($attachment['uid'], $attachment['partID'],$attachment['folder']);
								$_mailObject->AddStringAttachment($rawHeader.$rawBody, $_mailObject->EncodeHeader($attachment['name']), '7bit', 'message/rfc822');
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
								$_mailObject->AddStringAttachment($attachmentData['attachment'], $_mailObject->EncodeHeader($attachment['name']), 'base64', $attachment['type']);
								break;

						}
					} else {
						if (isset($attachment['file']) && parse_url($attachment['file'],PHP_URL_SCHEME) == 'vfs')
						{
							egw_vfs::load_wrapper('vfs');
						}
						if (isset($attachment['type']) && stripos($attachment['type'],"text/calendar; method=")!==false )
						{
							$_mailObject->AltExtended = file_get_contents($attachment['file']);
							$_mailObject->AltExtendedContentType = $attachment['type'];
						}
						else
						{
							$_mailObject->AddAttachment (
								$attachment['file'],
								$_mailObject->EncodeHeader($attachment['name']),
								(strtoupper($attachment['type'])=='MESSAGE/RFC822'?'7bit':'base64'),
								$attachment['type']
							);
						}
					}
				}
			}
		}
		$mail_bo->closeConnection();
	}
	
	function saveAsDraft($_formData, &$savingDestination='')
	{
		$mail_bo	= $this->mail_bo;
		$mail		= new egw_mailer();

		// preserve the bcc and if possible the save to folder information
		$this->sessionData['folder']    = $_formData['folder'];
		$this->sessionData['bcc']   = $_formData['bcc'];
		$this->sessionData['signatureid'] = $_formData['signatureid'];
		//$this->sessionData['stationeryID'] = $_formData['stationeryID'];
		$this->sessionData['mailaccount']  = $_formData['mailaccount'];
		$this->sessionData['attachments']  = $_formData['attachments'];
		try
		{
			$acc = emailadmin_account::read($this->sessionData['mailaccount']);
			//error_log(__METHOD__.__LINE__.array2string($acc));
			$identity = emailadmin_account::read_identity($acc['ident_id'],true);

			//$identity = emailadmin_account::read_identity($this->sessionData['mailaccount'],true);
		}
		catch (Exception $e)
		{
			$identity=array();
		}

		$flags = '\\Seen \\Draft';
		$BCCmail = '';

		$this->createMessage($mail, $_formData, $identity);

		foreach((array)$this->sessionData['bcc'] as $address) {
			$address_array  = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
			foreach((array)$address_array as $addressObject) {
				$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
				$mailAddr[] = array($emailAddress, $addressObject->personal);
			}
		}
		// folder list as Customheader
		if (!empty($this->sessionData['folder']))
		{
			$folders = implode('|',array_unique($this->sessionData['folder']));
			$mail->AddCustomHeader("X-Mailfolder: $folders");
		}
		$mail->AddCustomHeader('X-Signature: '.$this->sessionData['signatureid']);
		//$mail->AddCustomHeader('X-Stationery: '.$this->sessionData['stationeryID']);
		$mail->AddCustomHeader('X-Identity: '.(int)$this->sessionData['mailaccount']);
		// decide where to save the message (default to draft folder, if we find nothing else)
		// if the current folder is in draft or template folder save it there
		// if it is called from printview then save it with the draft folder
		$savingDestination = $mail_bo->getDraftFolder();
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

		if (count($mailAddr)>0) $BCCmail = $mail->AddrAppend("Bcc",$mailAddr);
		//error_log(__METHOD__.__LINE__.$BCCmail.$mail->getMessageHeader().$mail->getMessageBody());
		$mail_bo->openConnection();
		if ($mail_bo->folderExists($savingDestination,true)) {
			try
			{
				$messageUid = $mail_bo->appendMessage($savingDestination,
					$BCCmail.$mail->getMessageHeader(),
					$mail->getMessageBody(),
					$flags);
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
		$mail 		= new egw_mailer();
		$messageIsDraft	=  false;

		$this->sessionData['mailaccount']	= $_formData['mailaccount'];
		$this->sessionData['to']	= $_formData['to'];
		$this->sessionData['cc']	= $_formData['cc'];
		$this->sessionData['bcc']	= $_formData['bcc'];
		$this->sessionData['folder']	= $_formData['folder'];
		$this->sessionData['replyto']	= $_formData['replyto'];
		$this->sessionData['subject']	= trim($_formData['subject']);
		$this->sessionData['body']	= $_formData['body'];
		$this->sessionData['priority']	= $_formData['priority'];
		$this->sessionData['signatureid'] = $_formData['signatureid'];
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
			$acc = emailadmin_account::read($this->sessionData['mailaccount']);
			//error_log(__METHOD__.__LINE__.array2string($acc));
			$identity = emailadmin_account::read_identity($acc['ident_id'],true);

			//$identity = emailadmin_account::read_identity($this->sessionData['mailaccount'],true);
		}
		catch (Exception $e)
		{
			$identity=array();
		}
		try
		{
			$signature = emailadmin_account::read_identity((int)$this->sessionData['signatureid'],true);
		}
		catch (Exception $e)
		{
			$signature=array();
		}
		//error_log($this->sessionData['mailaccount']);
		//error_log(__METHOD__.__LINE__.print_r($identity,true));
		//error_log(__METHOD__.__LINE__.':'.array2string($this->sessionData['signatureid']).'->'.print_r($signature,true));
		// create the messages
		$this->createMessage($mail, $_formData, $identity, $signature, true);
		// remember the identity
		if ($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on') $fromAddress = $mail->FromName.($mail->FromName?' <':'').$mail->From.($mail->FromName?'>':'');
		#print "<pre>". $mail->getMessageHeader() ."</pre><hr><br>";
		#print "<pre>". $mail->getMessageBody() ."</pre><hr><br>";
		#exit;

		// we use the authentication data of the choosen mailaccount
		if ($_formData['serverID']!=$_formData['mailaccount'])
		{
			$this->changeProfile($_formData['mailaccount']);
		}
		// sentFolder is account specific
		$sentFolder = $this->mail_bo->getSentFolder();
		if (!$this->mail_bo->folderExists($sentFolder, true)) $sentFolder=false;
		// we do not fake the sender (anymore), we use the account settings for server and authentication of the choosen account
		$ogServer = $this->mail_bo->ogServer;
		//_debug_array($ogServer);
		$mail->Host = $ogServer->host;
		$mail->Port	= $ogServer->port;
		// SMTP Auth??
		if($ogServer->smtpAuth) {
			$mail->SMTPAuth	= true;
			// check if username contains a ; -> then a sender is specified (and probably needed)
			list($username,$senderadress) = explode(';', $ogServer->username,2);
			if (isset($senderadress) && !empty($senderadress)) $mail->Sender = $senderadress;
			$mail->Username = $username;
			$mail->Password	= $ogServer->password;
		}
		// we switch back from authentication data to the account we used to work on
		if ($_formData['serverID']!=$_formData['mailaccount'])
		{
			$this->changeProfile($_formData['serverID']);
		}

		// check if there are folders to be used
		$folderToCheck = (array)$this->sessionData['folder'];
		$folder = array();
		foreach ($folderToCheck as $k => $f)
		{
			if ($this->mail_bo->folderExists($f, true))
			{
				$folder[] = $f;
			}
		}
		if(isset($sentFolder) && $sentFolder && $sentFolder != 'none' &&
			$this->mailPreferences['sendOptions'] != 'send_only' &&
			$messageIsDraft == false)
		{
			if ($sentFolder)
			{
				$folder[] = $sentFolder;
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
		if($messageIsDraft == true) {
			$draftFolder = $this->mail_bo->getDraftFolder();
			if(!empty($draftFolder) && $this->mail_bo->folderExists($draftFolder,true)) {
				$this->sessionData['folder'] = array($draftFolder);
				$folder[] = $draftFolder;
			}
		}
		$folder = array_unique($folder);
		if (($this->mailPreferences['sendOptions'] != 'send_only' && $sentFolder != 'none') && !(count($folder) > 0) &&
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
				$mail->Send();
			}
			catch(phpmailerException $e) {
				$this->errorInfo = $e->getMessage();
				if ($mail->ErrorInfo) // use the complete mailer ErrorInfo, for full Information
				{
					if (stripos($mail->ErrorInfo, $this->errorInfo)===false)
					{
						$this->errorInfo = $mail->ErrorInfo.'<br>'.$this->errorInfo;
					}
					else
					{
						$this->errorInfo = $mail->ErrorInfo;
					}
				}
				error_log(__METHOD__.__LINE__.array2string($this->errorInfo));
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
			foreach((array)$this->sessionData['bcc'] as $address) {
				$address_array  = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
				foreach((array)$address_array as $addressObject) {
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					$mailAddr[] = array($emailAddress, $addressObject->personal);
				}
			}
			$BCCmail='';
			if (count($mailAddr)>0) $BCCmail = $mail->AddrAppend("Bcc",$mailAddr);
			$sentMailHeader = $BCCmail.$mail->getMessageHeader();
			$sentMailBody = $mail->getMessageBody();
		}
		// copying mail to folder
		if (count($folder) > 0)
		{
			foreach($folder as $folderName) {
				if (is_array($folderName)) $folderName = array_shift($folderName); // should not happen at all
				// if $_formData['serverID']!=$_formData['mailaccount'] skip copying to sentfolder on serverID
				if($mail_bo->isSentFolder($folderName) && $_formData['serverID']!=$_formData['mailaccount']) continue;
				if($mail_bo->isSentFolder($folderName)) {
					$flags = '\\Seen';
				} elseif($mail_bo->isDraftFolder($folderName)) {
					$flags = '\\Draft';
				} else {
					$flags = '\\Seen';
				}
				#$mailHeader=explode('From:',$mail->getMessageHeader());
				#$mailHeader[0].$mail->AddrAppend("Bcc",$mailAddr).'From:'.$mailHeader[1],
				//error_log(__METHOD__.__LINE__.array2string($folderName));
				//$mail_bo->reopen($folderName);
				if ($mail_bo->folderExists($folderName,true)) {
					try
					{
						//error_log(__METHOD__.__LINE__.array2string($folderName));
						$mail_bo->appendMessage($folderName,
								$sentMailHeader,
								$sentMailBody,
								$flags);
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
			if ($_formData['serverID']!=$_formData['mailaccount'])
			{
				// we assume the intention is to see the sent mail in the sentfolder
				// of that account, that is, if there is one at all, and our options
				// suggest it
				if(isset($sentFolder) && $sentFolder != 'none' &&
					$this->mailPreferences['sendOptions'] != 'send_only')
				{
					$this->changeProfile($_formData['mailaccount']);
					$sentFolder = $this->mail_bo->getSentFolder();
					try
					{
						$flags = '\\Seen';
						//error_log(__METHOD__.__LINE__.array2string($folderName));
						$this->mail_bo->appendMessage($sentFolder,
								$sentMailHeader,
								$sentMailBody,
								$flags);
					}
					catch (egw_exception_wrong_userinput $e)
					{
						error_log(__METHOD__.__LINE__.'->'.lang("Import of message %1 failed. Could not save message to folder %2 due to: %3",$this->sessionData['subject'],$folderName,$e->getMessage()));
					}

					$this->changeProfile($_formData['serverID']);
				}
			}

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
				$error = str_replace('"',"'",$e->getMessage());
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
				$mail_bo->deleteMessages(array($this->sessionData['uid']),$this->sessionData['messageFolder']);
			} else {
				$mail_bo->flagMessages("answered", $this->sessionData['uid'],($this->sessionData['messageFolder']?$this->sessionData['messageFolder']:$this->sessionData['sourceFolder']));
				//error_log(__METHOD__.__LINE__.array2string(array_keys($this->sessionData)).':'.array2string($this->sessionData['forwardedUID']).' F:'.$this->sessionData['sourceFolder']);
				if (array_key_exists('forwardFlag',$this->sessionData) && $this->sessionData['forwardFlag']=='forwarded')
				{
					//error_log(__METHOD__.__LINE__.':'.array2string($this->sessionData['forwardedUID']).' F:'.$this->sessionData['sourceFolder']);
					$mail_bo->flagMessages("forwarded", $this->sessionData['forwardedUID'],$this->sessionData['sourceFolder']);
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
		// attention: we dont return from infolog/tracker. You cannot check both. cleanups will be done there.
		if ($_formData['to_infolog'] == 'on') {
			$uiinfolog =& CreateObject('infolog.infolog_ui');
			$uiinfolog->import_mail(
				$mailaddresses,
				$this->sessionData['subject'],
				$this->convertHTMLToText($this->sessionData['body']),
				$this->sessionData['attachments'],
				false, // date
				$sentMailHeader, // raw SentMailHeader
				$sentMailBody // raw SentMailBody
			);
		}
		if ($_formData['to_tracker'] == 'on') {
			$uitracker =& CreateObject('tracker.tracker_ui');
			$uitracker->import_mail(
				$mailaddresses,
				$this->sessionData['subject'],
				$this->convertHTMLToText($this->sessionData['body']),
				$this->sessionData['attachments'],
				false, // date
				$sentMailHeader, // raw SentMailHeader
				$sentMailBody // raw SentMailBody
			);
		}
/*
		if ($_formData['to_calendar'] == 'on') {
			$uical =& CreateObject('calendar.calendar_uiforms');
			$uical->import_mail(
				$mailaddresses,
				$this->sessionData['subject'],
				$this->convertHTMLToText($this->sessionData['body']),
				$this->sessionData['attachments']
			);
		}
*/


		if(is_array($this->sessionData['attachments'])) {
			reset($this->sessionData['attachments']);
			while(list($key,$value) = @each($this->sessionData['attachments'])) {
				#print "$key: ".$value['file']."<br>";
				if (!empty($value['file']) && parse_url($value['file'],PHP_URL_SCHEME) != 'vfs') {	// happens when forwarding mails
					unlink($value['file']);
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
		// retrieve the signature accociated with the identity
		$id = $this->mail_bo->getDefaultIdentity();
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed']) && !empty($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed']))
		{
			$sigPref = $GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'];
			if (!empty($sigPref[$this->mail_bo->profileID]) && $sigPref[$this->mail_bo->profileID]>0)
			{
				try
				{
					$identity = emailadmin_account::read_identity($sigPref[$this->mail_bo->profileID]);
					$id = $sigPref[$this->mail_bo->profileID];
				}
				catch (Exception $e)
				{
					unset($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'][$this->mail_bo->profileID]);
				}
			}
		}

		if ((!isset($content['mailaccount']) || empty($content['mailaccount'])) && $id) $content['signatureid'] = $id;
		if (!isset($content['signatureid']) || empty($content['signatureid'])) $content['signatureid'] = $id;
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

	function ajax_searchFolder($_searchStringLength=2, $_returnList=false) {
		static $useCacheIfPossible;
		if (is_null($useCacheIfPossible)) $useCacheIfPossible = true;
		$_searchString = trim($_REQUEST['query']);
		$results = array();
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
				if ($_searchStringLength<=0)
				{
					$f=true;
					$results[] = array('id'=>$k, 'label' => htmlspecialchars($fA->displayName));
				}
				if ($f==false && stripos($fA->displayName,$_searchString)!==false)
				{
					$f=true;
					$results[] = array('id'=>$k, 'label' => htmlspecialchars($fA->displayName));
				}
				if ($f==false && stripos($k,$searchString)!==false)
				{
					$results[] = array('id'=>$k, 'label' => htmlspecialchars($fA->displayName));
				}
			}
		}
		//error_log(__METHOD__.__LINE__.' IcServer:'.$this->mail_bo->icServer->ImapServerId.':'.array2string($results));
		if ($_returnList)
		{
			foreach ((array)$results as $k => $_result) {$rL[$_result['id']]=$_result['label'];};
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
					if ($i > 10) break;	// we check for # of results here, as we might have empty email addresses
				}
			}
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

		if($error = $document_merge->check_document($_REQUEST['document'],''))
		{
			$response->error($error);
			return;
		}

		// Merge does not work correctly (missing to) if current app is not addressbook
		//$GLOBALS['egw_info']['flags']['currentapp'] = 'addressbook';

		// Actually do the merge
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
}
