<?php
/**
 * EGroupware - Mail - interface class for compose mails in popup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2013 by Klaus Leithoff <kl-AT-stylite.de>
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
		'action'		=> True,
		'compose'		=> True,
		'composeFromDraft'	=> True,
		'getAttachment'		=> True,
		'fileSelector'		=> True,
		'forward'		=> True,
		'composeAsNew'         => True,
		'composeAsForward'=> True,
		'reply'			=> True,
		'replyAll'		=> True,
		'selectFolder'		=> True,
		'addAtachment'	=> True,
		'action'	=> True
	);

	var $destinations = array(
		'to' 		=> 'to',
		'cc'		=> 'cc',
		'bcc'		=> 'bcc',
		'replyto'	=> 'replyto',
		'folder'	=> 'folder'
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
	var $preferences;	// the prefenrences(emailserver, username, ...)
	var $preferencesArray;
	var $bopreferences;
	var $bosignatures;
	var $displayCharset;
	var $sessionData;

	/**
	 * Instance of Template class
	 *
	 * @var Template
	 */
	var $t;

	function mail_compose()
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
		$this->bosignatures	= new felamimail_bosignatures();
		$this->mail_bo	= mail_bo::getInstance(true,$profileID);

		$profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $this->mail_bo->profileID;
		$this->bopreferences =& $this->mail_bo->bopreferences;
		$this->preferences	=& $this->mail_bo->mailPreferences; // $this->bopreferences->getPreferences();
		// we should get away from this $this->preferences->preferences should hold the same info
		$this->preferencesArray =& $this->preferences->preferences; //$GLOBALS['egw_info']['user']['preferences']['felamimail'];
		//force the default for the forwarding -> asmail
		if (is_array($this->preferencesArray)) {
			if (!array_key_exists('message_forwarding',$this->preferencesArray)
				|| !isset($this->preferencesArray['message_forwarding'])
				|| empty($this->preferencesArray['message_forwarding'])) $this->preferencesArray['message_forwarding'] = 'asmail';
		} else {
			$this->preferencesArray['message_forwarding'] = 'asmail';
		}
		if (is_null(mail_bo::$mailConfig)) mail_bo::$mailConfig = config::read('mail');

		if (!isset($_POST['composeid']) && !isset($_GET['composeid']))
		{
			// create new compose session
			$this->composeID = $this->getComposeID();
			$this->setDefaults();
		}
		else
		{
			// reuse existing compose session
			if (isset($_POST['composeid']))
				$this->composeID = $_POST['composeid'];
			else
				$this->composeID = $_GET['composeid'];

			$this->restoreSessionData();
		}



		$this->mailPreferences  =& $this->mail_bo->mailPreferences;

	}

	function unhtmlentities ($string)
	{
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl = array_flip ($trans_tbl);
		return strtr ($string, $trans_tbl);
	}

	function action()
	{
		$formData['identity']	= (int)$_POST['identity'];

		foreach((array)$_POST['destination'] as $key => $destination) {
			if(!empty($_POST['address'][$key])) {
				if($destination == 'folder') {
					$formData[$destination][] = $GLOBALS['egw']->translation->convert($_POST['address'][$key], $this->charset, 'UTF7-IMAP');
				} else {
					$formData[$destination][] = $_POST['address'][$key];
				}
			}
		}

		$formData['subject'] 	= $this->stripSlashes($_POST['subject']);
		$formData['body'] 	= $this->stripSlashes($_POST['body']);
		// if the body is empty, maybe someone pasted something with scripts, into the message body
		if(empty($formData['body']))
		{
			// this is to be found with the egw_unset_vars array for the _POST['body'] array
			$name='_POST';
			$key='body';
			#error_log($GLOBALS['egw_unset_vars'][$name.'['.$key.']']);
			if (isset($GLOBALS['egw_unset_vars'][$name.'['.$key.']']))
			{
				$formData['body'] = bocompose::_getCleanHTML( $GLOBALS['egw_unset_vars'][$name.'['.$key.']']);
			}
		}
		$formData['priority'] 	= $this->stripSlashes($_POST['priority']);
		$formData['signatureID'] = (int)$_POST['signatureID'];
		$formData['stationeryID'] = $_POST['stationeryID'];
		$formData['mimeType']	= $this->stripSlashes($_POST['mimeType']);
		if ($formData['mimeType'] == 'html' && html::htmlarea_availible()===false)
		{
			$formData['mimeType'] = 'plain';
			$formData['body'] = $this->convertHTMLToText($formData['body']);
		}
		$formData['disposition'] = (bool)$_POST['disposition'];
		$formData['to_infolog'] = $_POST['to_infolog'];
		$formData['to_tracker'] = $_POST['to_tracker'];
		//$formData['mailbox']	= $_GET['mailbox'];
		if((bool)$_POST['printit'] == true) {
			$formData['printit'] = 1;
			$formData['isDraft'] = 1;
			// pint the composed message. therefore save it as draft and reopen it as plain printwindow
			$formData['subject'] = "[".lang('printview').":]".$formData['subject'];
			$messageUid = $this->saveAsDraft($formData,$destinationFolder);
			if (!$messageUid) {
				print "<script type=\"text/javascript\">alert('".lang("Error: Could not save Message as Draft")."');</script>";
				return;
			}
			$uidisplay   = CreateObject('felamimail.uidisplay');
			$uidisplay->printMessage($messageUid, $formData['printit'],$destinationFolder);
			//egw::link('/index.php',array('menuaction' => 'felamimail.uidisplay.printMessage','uid'=>$messageUid));
			return;
		}
		if((bool)$_POST['saveAsDraft'] == true) {
			$formData['isDraft'] = 1;
			// save as draft
			$folder = $this->mail_bo->getDraftFolder();
			$this->mail_bo->reopen($folder);
			$status = $this->mail_bo->getFolderStatus($folder);
			//error_log(__METHOD__.__LINE__.array2string(array('Folder'=>$folder,'Status'=>$status)));
			$uidNext = $status['uidnext']; // we may need that, if the server does not return messageUIDs of saved/appended messages
			$messageUid = $this->saveAsDraft($formData,$folder); // folder may change
			if (!$messageUid) {
				print "<script type=\"text/javascript\">alert('".lang("Error: Could not save Message as Draft")." ".lang("Trying to recover from session data")."');</script>";
				//try to reopen the mail from session data
				$this->compose('to',true);
				return;
			}
			// saving as draft, does not mean closing the message
			unset($_POST['composeid']);
			unset($_GET['composeid']);
			$uicompose   = CreateObject('felamimail.uicompose');
			$messageUid = ($messageUid===true ? $uidNext : $messageUid);
			if (!$uicompose->mail_bo->icServer->_connected) $uicompose->mail_bo->openConnection($uicompose->mail_bo->profileID);
			if ($uicompose->mail_bo->getMessageHeader($messageUid))
			{
				//error_log(__METHOD__.__LINE__.' (re)open drafted message with new UID: '.$messageUid.' in folder:'.$folder);
				$uicompose->bocompose->getDraftData($uicompose->mail_bo->icServer, $folder, $messageUid);
				$uicompose->compose('to',true);
				return;
			}
		} else {
			$cachedComposeID = egw_cache::getCache(egw_cache::SESSION,'email','composeIdCache'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60);
			egw_cache::setCache(egw_cache::SESSION,'email','composeIdCache'.trim($GLOBALS['egw_info']['user']['account_id']),$this->composeID,$expiration=60);
			//error_log(__METHOD__.__LINE__.' '.$formData['subject'].' '.$cachedComposeID.'<->'.$this->composeID);
			if (!empty($cachedComposeID) && $cachedComposeID == $this->composeID)
			{
				//already send
				print "<script type=\"text/javascript\">window.close();</script>";
				return;
			}
			if(!$this->send($formData)) {
				// reset the cached composeID, as something failed
				egw_cache::setCache(egw_cache::SESSION,'email','composeIdCache'.trim($GLOBALS['egw_info']['user']['account_id']),null,$expiration=60);
//					print "<script type=\"text/javascript\">alert('".lang("Error: Could not send Message.")." ".lang("Trying to recover from session data")."');</script>";
				$this->compose();
				return;
			}
		}

		#common::egw_exit();
		print "<script type=\"text/javascript\">window.close();</script>";
	}

	function composeAsForward($_focusElement='to')
	{
		if (isset($_GET['forwardmails']))
		{
			unset($_GET['forwardmails']);
			$replyID = $_GET['reply_id'];
			$replyIds = explode(',',$replyID);
			$icServer = $this->mail_bo->profileID;
			$folder = (isset($_GET['folder'])?base64_decode($_GET['folder']):base64_decode($_GET['mailbox']));
			//_debug_array(array('reply_id'=>$replyIds,'folder'=>$folder));
			if (!empty($folder) && !empty($replyID) ) {
				// this fill the session data with the values from the original email
				$buff = $this->preferencesArray['message_forwarding'];
				$this->preferencesArray['message_forwarding'] = 'asmail';
				foreach($replyIds as $key => $id)
				{
					$this->getForwardData($icServer, $folder, $id,NULL);
				}
				$this->preferencesArray['message_forwarding'] = $buff;
			}
		}
		$this->compose($_focusElement);
	}

	/**
	 * function compose
	 * 	this function is used to fill the compose dialog with the content provided by session data
	 *
	 * @var _focusElement		varchar subject, to, body supported
	 * @var suppressSigOnTop	boolean
	 * @var isReply				boolean
	 */
	function compose($_focusElement='to',$suppressSigOnTop=false, $isReply=false)
	{
		//error_log(__METHOD__.__LINE__.array2string($_REQUEST));
		if (isset($_GET['reply_id'])) $replyID = $_GET['reply_id'];
		// read the data from session
		// all values are empty for a new compose window
		$insertSigOnTop = false;
		$sessionData = $this->getSessionData();
		$alwaysAttachVCardAtCompose = false; // we use this to eliminate double attachments, if users VCard is already present/attached
		if ( isset($GLOBALS['egw_info']['apps']['stylite']) && (isset($this->preferencesArray['attachVCardAtCompose']) &&
			$this->preferencesArray['attachVCardAtCompose']))
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
					$sessionData['subject'] = lang($app).' #'.$res['id'].': ';
					foreach(array('subject','body','mimetype') as $name) {
						$sName = $name;
						if ($name=='mimetype')
						{
							$sName = 'mimeType';
							$sessionData[$sName] = $res[$name];
						}
						else
						{
							if ($res[$name]) $sessionData[$sName] .= (strlen($sessionData[$sName])>0 ? ' ':'') .$res[$name];
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
						$sessionData[$keyValuePair[0]] .= (strlen($sessionData[$keyValuePair[0]])>0 ? ' ':'') . $keyValuePair[1];
					}
				}
				$sessionData['to']=$mailtoArray[0];
				// if the mailto string is not htmlentity decoded the arguments are passed as simple requests
				foreach(array('cc','bcc','subject','body') as $name) {
					if ($_REQUEST[$name]) $sessionData[$name] .= (strlen($sessionData[$name])>0 ? ( $name == 'cc' || $name == 'bcc' ? ',' : ' ') : '') . $_REQUEST[$name];
				}
			}

			if ($_REQUEST['preset']['mailtocontactbyid']) {
				if ($GLOBALS['egw_info']['user']['apps']['addressbook']) {
					$addressbookprefs =& $GLOBALS['egw_info']['user']['preferences']['addressbook'];
					if (method_exists($GLOBALS['egw']->contacts,'search')) {

						$addressArray = split(',',$_REQUEST['preset']['mailtocontactbyid']);
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
						$sessionData['to']=$mailtoArray;
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
					$this->addAttachment($formData,($alwaysAttachVCardAtCompose?true:false));
				}
				$remember = array();
				if (isset($_REQUEST['preset']['mailto']) || (isset($_REQUEST['app']) && isset($_REQUEST['method']) && isset($_REQUEST['id'])))
				{
					foreach(array_keys($sessionData) as $k)
					{
						if (in_array($k,array('to','cc','bcc','subject','body','mimeType'))) $remember[$k] = $sessionData[$k];
					}
				}
				$sessionData = $this->getSessionData();
				if(!empty($remember)) $sessionData = array_merge($sessionData,$remember);
			}
			foreach(array('to','cc','bcc','subject','body') as $name)
			{
				if ($_REQUEST['preset'][$name]) $sessionData[$name] = $_REQUEST['preset'][$name];
			}
		}
		$etpl = new etemplate_new('mail.compose');
		$content = array();
		_debug_array($sessionData);
		// is the to address set already?
		if (!empty($_REQUEST['send_to']))
		{
			$sessionData['to'] = base64_decode($_REQUEST['send_to']);
			// first check if there is a questionmark or ampersand
			if (strpos($sessionData['to'],'?')!== false) list($sessionData['to'],$rest) = explode('?',$sessionData['to'],2);
			$sessionData['to'] = html_entity_decode($sessionData['to']);
			if (($at_pos = strpos($sessionData['to'],'@')) !== false)
			{
				if (($amp_pos = strpos(substr($sessionData['to'],$at_pos),'&')) !== false)
				{
					//list($email,$addoptions) = explode('&',$value,2);
					$email = substr($sessionData['to'],0,$amp_pos+$at_pos);
					$rest = substr($sessionData['to'], $amp_pos+$at_pos+1);
					//error_log(__METHOD__.__LINE__.$email.' '.$rest);
					$sessionData['to'] = $email;
				}
			}
			if (strpos($sessionData['to'],'%40')!== false) $sessionData['to'] = html::purify(str_replace('%40','@',$sessionData['to']));
			$rarr = array(html::purify($rest));
			if (isset($rest)&&!empty($rest) && strpos($rest,'&')!== false) $rarr = explode('&',$rest);
			//error_log(__METHOD__.__LINE__.$sessionData['to'].'->'.array2string($rarr));
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
			//error_log(__METHOD__.__LINE__.$sessionData['to'].'->'.array2string($karr));
			foreach(array('cc','bcc','subject','body') as $name)
			{
				if ($karr[$name]) $sessionData[$name] = $karr[$name];
			}
			if (!empty($_REQUEST['subject'])) $sessionData['subject'] = html::purify(trim(html_entity_decode($_REQUEST['subject'])));
		}

		//is the MimeType set/requested
		if (!empty($_REQUEST['mimeType']))
		{
			$sessionData['mimeType'] = $_REQUEST['mimeType'];
		}
		else
		{
			// try to enforce a mimeType on reply ( if type is not of the wanted type )
			if ($isReply)
			{
				if (!empty($this->preferencesArray['replyOptions']) && $this->preferencesArray['replyOptions']=="text" &&
					$sessionData['mimeType'] == 'html')
				{
					$sessionData['mimeType']  = 'plain';
					$sessionData['body'] = $this->convertHTMLToText(str_replace(array("\n\r","\n"),' ',$sessionData['body']));
				}
				if (!empty($this->preferencesArray['replyOptions']) && $this->preferencesArray['replyOptions']=="html" &&
					$sessionData['mimeType'] != 'html')
				{
					$sessionData['mimeType']  = 'html';
					$sessionData['body'] = "<pre>".$sessionData['body']."</pre>";
					// take care this assumption is made on the creation of the reply header in bocompose::getReplyData
					if (strpos($sessionData['body'],"<pre> \r\n \r\n---")===0) $sessionData['body'] = substr_replace($sessionData['body']," <br>\r\n<pre>---",0,strlen("<pre> \r\n \r\n---")-1);
				}
			}
		}
		if ($sessionData['mimeType'] == 'html' && html::htmlarea_availible()===false)
		{
			$sessionData['mimeType'] = 'plain';
			$sessionData['body'] = $this->convertHTMLToText($sessionData['body']);
		}
		// removal of possible script elements in HTML; getCleanHTML is the choice here, if not only for consistence
		// we use the preg of common_functions (slightly altered) to meet eGroupwares behavior on posted variables
		if ($sessionData['mimeType'] == 'html')
		{
			// this is now moved to egw_htmLawed (triggered by default config) which is called with ckeditor anyway
			//mail_bo::getCleanHTML($sessionData['body'],true);
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
		if (($suppressSigOnTop || $sessionData['isDraft']) && !empty($sessionData['signatureID'])) $presetSig = (int)$sessionData['signatureID'];
		if (($suppressSigOnTop || $sessionData['isDraft']) && !empty($sessionData['stationeryID'])) $presetStationery = $sessionData['stationeryID'];
		$presetId = NULL;
		if (($suppressSigOnTop || $sessionData['isDraft']) && !empty($sessionData['identity'])) $presetId = (int)$sessionData['identity'];

/*
		$this->t->set_file(array("composeForm" => "composeForm.tpl"));
		$this->t->set_block('composeForm','header','header');
		$this->t->set_block('composeForm','body_input');
		$this->t->set_block('composeForm','attachment','attachment');
		$this->t->set_block('composeForm','attachment_row','attachment_row');
		$this->t->set_block('composeForm','attachment_row_bold');
		$this->t->set_block('composeForm','destination_row');
		$this->t->set_block('composeForm','simple_text');

		$this->translate();
		// store the selected Signature
		$this->t->set_var("mySigID",($presetSig ? $presetSig : $sessionData['signatureID']));

		if ($GLOBALS['egw_info']['user']['apps']['addressbook']) {
			$this->t->set_var("link_addressbook",egw::link('/index.php',array(
				'menuaction' => 'addressbook.addressbook_ui.emailpopup',
			)));
		} else {
			$this->t->set_var("link_addressbook",'');
		}
		$this->t->set_var("focusElement",$_focusElement);

		$linkData = array
		(
			'menuaction'	=> 'felamimail.uicompose.selectFolder',
		);
		$this->t->set_var('folder_select_url',egw::link('/index.php',$linkData));

		$linkData = array
		(
			'menuaction'	=> 'felamimail.uicompose.fileSelector',
			'composeid'	=> $this->composeID
		);
		$this->t->set_var('file_selector_url',egw::link('/index.php',$linkData));

		$this->t->set_var('vfs_selector_url',egw::link('/index.php',array(
			'menuaction' => 'filemanager.filemanager_select.select',
			'mode' => 'open-multiple',
			'method' => 'felamimail.uicompose.vfsSelector',
			'id' => $this->composeID,
			'label' => lang('Attach'),
		)));
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$this->t->set_var('vfs_attach_button','
			<button class="menuButton" type="button" onclick="fm_compose_displayVfsSelector();" title="'.htmlspecialchars(lang('filemanager')).'">
				<img src="'.common::image('filemanager','navbar').'" height="18">
			</button>');
		}
		else
		{
			$this->t->set_var('vfs_attach_button','');
		}
		$linkData = array
		(
			'menuaction'	=> 'felamimail.uicompose.action',
			'composeid'	=> $this->composeID
		);
		$this->t->set_var("link_action",egw::link('/index.php',$linkData));
		$this->t->set_var('folder_name',$this->mail_bo->sessionData['mailbox']);
		$this->t->set_var('compose_id',$this->composeID);
		// the editorobject is needed all the time (since we use CKEDITOR
		//$editorObject = html::initCKEditor('400px','simple');
		$this->t->set_var('ckeditorConfig', egw_ckeditor_config::get_ckeditor_config('simple-withimage'));//$editorObject->jsEncode($editorObject->config));
		$this->t->set_var('refreshTimeOut', 3*60*1000); // 3 minutes till a compose messages will be saved as draft;

		// check for some error messages from last posting attempt
		$errorInfo = $this->getErrorInfo();
		if (isset($_REQUEST['preset']['msg'])) $errorInfo = html::purify($_REQUEST['preset']['msg']);
		if($errorInfo)
		{
			$this->t->set_var('errorInfo',"<font color=\"red\"><b>$errorInfo</b></font>");
		}
		else
		{
			$this->t->set_var('errorInfo','&nbsp;');
		}
*/
		// header
		$allIdentities = $this->mailPreferences->getIdentity();
		unset($allIdentities[0]);
		//_debug_array($allIdentities);
		if (is_null(mail_bo::$mailConfig)) mail_bo::$mailConfig = config::read('mail');
		// not set? -> use default, means full display of all available data
		if (!isset(mail_bo::$mailConfig['how2displayIdentities'])) mail_bo::$mailConfig['how2displayIdentities'] ='';
		$globalIds = 0;
		$defaultIds = array();
		foreach($allIdentities as $key => $singleIdentity) {
			if ($singleIdentity->id<0){ $globalIds++; }/*else{ unset($allIdentities[$key]);}*/
			// there could be up to 2 default IDS. the activeProfile and another on marking the desired Identity to choose
			if(!empty($singleIdentity->default) && $singleIdentity->default==1) $defaultIds[$singleIdentity->id] = $singleIdentity->id;
		}
		//error_log(__METHOD__.__LINE__.' Identities regarded/marked as default:'.array2string($defaultIds). ' MailProfileActive:'.$this->mail_bo->profileID);
		// if there are 2 defaultIDs, its most likely, that the user choose to set
		// the one not being the activeServerProfile to be his default Identity
		if (count($defaultIds)>1) unset($defaultIds[$this->mail_bo->profileID]);
		$defaultIdentity = 0;
		$identities = array();
		foreach($allIdentities as $key => $singleIdentity) {
			//$identities[$singleIdentity->id] = $singleIdentity->realName.' <'.$singleIdentity->emailAddress.'>';
			$iS = mail_bo::generateIdentityString($singleIdentity);
			if (mail_bo::$mailConfig['how2displayIdentities']=='' || count($allIdentities) ==1 || count($allIdentities) ==$globalIds)
			{
				$id_prepend ='';
			}
			else
			{
				$id_prepend = '('.$singleIdentity->id.') ';
			}
			//if ($singleIdentity->default) error_log(__METHOD__.__LINE__.':'.$presetId.'->'.$key.'('.$singleIdentity->id.')'.'#'.$iS.'#');
			if (array_search($id_prepend.$iS,$identities)===false) $identities[$singleIdentity->id] = $id_prepend.$iS;
			if(in_array($singleIdentity->id,$defaultIds) && $defaultIdentity==0)
			{
				//_debug_array($singleIdentity);
				$defaultIdentity = $singleIdentity->id;
				//$defaultIdentity = $key;
				$sessionData['signatureID'] = (!empty($singleIdentity->signature) ? $singleIdentity->signature : $sessionData['signatureID']);
			}
		}
		$selectFrom = html::select('identity', (!empty($presetId) ? $presetId : $defaultIdentity), $identities, true, "style='width:100%;' onchange='changeIdentity(this);'");
/*
		$this->t->set_var('select_from', $selectFrom);
		//error_log(__METHOD__.__LINE__.' DefaultIdentity:'.array2string($identities[($presetId ? $presetId : $defaultIdentity)]));
		// navbar(, kind of)
		$this->t->set_var('img_clear_left', common::image('felamimail','clear_left'));
		$this->t->set_var('img_fileopen', common::image('phpgwapi','fileopen'));
		$this->t->set_var('img_mail_send', common::image('felamimail','mail_send'));
		$this->t->set_var('img_attach_file', common::image('felamimail','attach'));
		$this->t->set_var('ajax-loader', common::image('felamimail','ajax-loader'));
		$this->t->set_var('img_fileexport', common::image('felamimail','fileexport'));
		// prepare print url/button
		$this->t->set_var('img_print_it', common::image('felamimail','print'));
		$this->t->set_var('lang_print_it', lang('print it'));
		$this->t->set_var('print_it', $printURL);
*/
		// from, to, cc, replyto
		$destinationRows = 0;
		foreach(array('to','cc','bcc','replyto','folder') as $destination) {
			foreach((array)$sessionData[$destination] as $key => $value) {
				if ($value=="NIL@NIL") continue;
				if ($destination=='replyto' && str_replace('"','',$value) == str_replace('"','',$identities[($presetId ? $presetId : $defaultIdentity)])) continue;
				//error_log(__METHOD__.__LINE__.array2string(array('key'=>$key,'value'=>$value)));
				$selectDestination = html::select('destination[]', $destination, $this->destinations, false, "style='width: 100%;' onchange='fm_compose_changeInputType(this)'");
				//$this->t->set_var('select_destination', $selectDestination);
				$value = htmlspecialchars_decode($value,ENT_COMPAT);
				$value = str_replace("\"\"",'"',$value);
				$address_array = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($value):$value), '');
				foreach((array)$address_array as $addressObject) {
					if ($addressObject->host == '.SYNTAX-ERROR.') continue;
					$address = imap_rfc822_write_address($addressObject->mailbox,$addressObject->host,$addressObject->personal);
					$address = mail_bo::htmlentities($address, $this->displayCharset);
					//$this->t->set_var('address', ($destination=='folder'?$value:$address));
					//$this->t->parse('destinationRows','destination_row',True);
					$destinationRows++;
				}
			}
		}
		while($destinationRows < 3) {
			// and always add one empty row
			$selectDestination = html::select('destination[]', 'to', $this->destinations, false, "style='width: 100%;' onchange='fm_compose_changeInputType(this)'");
/*
			$this->t->set_var('select_destination', $selectDestination);
			$this->t->set_var('address', '');
			$this->t->parse('destinationRows','destination_row',True);
*/
			$destinationRows++;
		}
		// and always add one empty row
		$selectDestination = html::select('destination[]', 'to', $this->destinations, false, "style='width: 100%;' onchange='fm_compose_changeInputType(this)'");
/*
		$this->t->set_var('select_destination', $selectDestination);
		$this->t->set_var('address', '');
		$this->t->parse('destinationRows','destination_row',True);
*/
		// handle subject
		$subject = mail_bo::htmlentities($sessionData['subject'],$this->displayCharset);
/*
		$this->t->set_var("subject",$subject);

		if ($GLOBALS['egw_info']['user']['apps']['addressbook']) {
			$this->t->set_var('addressbookButton','<button class="menuButton" type="button" onclick="addybook();" title="'.lang('addressbook').'">
                <img src="'.common::image('phpgwapi/templates/phpgw_website','users').'">
            </button>');
		} else {
			$this->t->set_var('addressbookButton','');
		}
		if ($GLOBALS['egw_info']['user']['apps']['infolog']) {
			$this->t->set_var('infologImage',html::image('felamimail','to_infolog',lang('Save as infolog'),'width="17px" height="17px" valign="middle"' ));
			$this->t->set_var('lang_save_as_infolog',lang('Save as infolog'));
			$this->t->set_var('infolog_checkbox','<input class="input_text" type="checkbox" id="to_infolog" name="to_infolog" />');
		} else {
			$this->t->set_var('infologImage','');
			$this->t->set_var('lang_save_as_infolog','');
			$this->t->set_var('infolog_checkbox','');
		}
		if ($GLOBALS['egw_info']['user']['apps']['tracker'])
		{
			$this->t->set_var('trackerImage',html::image('felamimail','to_tracker',lang('Save as tracker'),'width="17px" height="17px" valign="middle"' ));
			$this->t->set_var('lang_save_as_infolog',($GLOBALS['egw_info']['user']['apps']['infolog']?lang('Save:'):lang('Save as tracker')));
			$this->t->set_var('tracker_checkbox','<input class="input_text" type="checkbox" id="to_tracker" name="to_tracker" />');
		} else {
			$this->t->set_var('trackerImage','');
			$this->t->set_var('tracker_checkbox','');
		}
		$this->t->set_var('lang_no_recipient',lang('No recipient address given!'));
		$this->t->set_var('lang_no_subject',lang('No subject given!'));
		$this->t->set_var('lang_infolog_tracker_not_both',lang("You can either choose to save as infolog OR tracker, not both."));
		$this->t->pparse("out","header");
*/
		// prepare signatures, the selected sig may be used on top of the body
		require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.felamimail_bosignatures.inc.php');
		$boSignatures = new felamimail_bosignatures();
		$signatures = $boSignatures->getListOfSignatures();

		if (empty($sessionData['signatureID'])) {
			if ($signatureData = $boSignatures->getDefaultSignature()) {
				if (is_array($signatureData)) {
					$sessionData['signatureID'] = $signatureData['signatureid'];
				} else {
					$sessionData['signatureID'] =$signatureData;
				}
			}
		}

		$selectSignatures = array(
			'-2' => lang('no signature')
		);
		foreach($signatures as $signature) {
			$selectSignatures[$signature['fm_signatureid']] = lang('Signature').': '.$signature['fm_description'];
		}
		$disableRuler = false;
		$signature = $boSignatures->getSignature(($presetSig ? $presetSig : $sessionData['signatureID']));
		if ((isset($this->preferencesArray['disableRulerForSignatureSeparation']) &&
			$this->preferencesArray['disableRulerForSignatureSeparation']) ||
			empty($signature->fm_signature) || trim($this->convertHTMLToText($signature->fm_signature,true,true)) =='')
		{
			$disableRuler = true;
		}
		$font_span ='';
		if($sessionData['mimeType'] == 'html' /*&& trim($sessionData['body'])==''*/) {
			// User preferences for style
			$font = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font'];
			$font_size = egw_ckeditor_config::font_size_from_prefs();
			$font_span = '<span '.($font||$font_size?'style="':'').($font?'font-family:'.$font.'; ':'').($font_size?'font-size:'.$font_size.'; ':'').'">'.'&nbsp;'.'</span>';
			if (empty($font) && empty($font_size)) $font_span = '';
		}
		//remove possible html header stuff
		if (stripos($sessionData['body'],'<html><head></head><body>')!==false) $sessionData['body'] = str_ireplace(array('<html><head></head><body>','</body></html>'),array('',''),$sessionData['body']);
		//error_log(__METHOD__.__LINE__.array2string($this->preferencesArray));
		$blockElements = array('address','blockquote','center','del','dir','div','dl','fieldset','form','h1','h2','h3','h4','h5','h6','hr','ins','isindex','menu','noframes','noscript','ol','p','pre','table','ul');
		if (isset($this->preferencesArray['insertSignatureAtTopOfMessage']) &&
			$this->preferencesArray['insertSignatureAtTopOfMessage'] &&
			!(isset($_POST['mySigID']) && !empty($_POST['mySigID']) ) && !$suppressSigOnTop
		)
		{
			$insertSigOnTop = ($insertSigOnTop?$insertSigOnTop:true);
			$sigText = mail_bo::merge($signature->fm_signature,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
			if ($sessionData['mimeType'] == 'html')
			{
				$sigTextStartsWithBlockElement = ($disableRuler?false:true);
				foreach($blockElements as $e)
				{
					if ($sigTextStartsWithBlockElement) break;
					if (stripos(trim($sigText),'<'.$e)===0) $sigTextStartsWithBlockElement = true;
				}
			}
			if($sessionData['mimeType'] == 'html') {
				$before = (!empty($font_span) && !($insertSigOnTop === 'below')?$font_span:'&nbsp;').($disableRuler?''/*($sigTextStartsWithBlockElement?'':'<p style="margin:0px;"/>')*/:'<hr style="border:1px dotted silver; width:90%;">');
				$inbetween = '&nbsp;<br>';
			} else {
				$before = ($disableRuler ?"\r\n\r\n":"\r\n\r\n-- \r\n");
				$inbetween = "\r\n";
			}
			if ($sessionData['mimeType'] == 'html')
			{
				$sigText = ($sigTextStartsWithBlockElement?'':"<div>")."<!-- HTMLSIGBEGIN -->".$sigText."<!-- HTMLSIGEND -->".($sigTextStartsWithBlockElement?'':"</div>");
			}

			if ($insertSigOnTop === 'below')
			{
				$sessionData['body'] = $font_span.$sessionData['body'].$before.($sessionData['mimeType'] == 'html'?$sigText:$this->convertHTMLToText($sigText,true,true));
			}
			else
			{
				$sessionData['body'] = $before.($sessionData['mimeType'] == 'html'?$sigText:$this->convertHTMLToText($sigText,true,true)).$inbetween.$sessionData['body'];
			}
		}
		else
		{
			$sessionData['body'] = ($font_span?$font_span:'&nbsp;').$sessionData['body'];
		}
		//error_log(__METHOD__.__LINE__.$sessionData['body']);
		// prepare body
		// in a way, this tests if we are having real utf-8 (the displayCharset) by now; we should if charsets reported (or detected) are correct
		if (strtoupper($this->displayCharset) == 'UTF-8')
		{
			$test = @json_encode($sessionData['body']);
			//error_log(__METHOD__.__LINE__.' ->'.strlen($singleBodyPart['body']).' Error:'.json_last_error().'<- BodyPart:#'.$test.'#');
			//if (json_last_error() != JSON_ERROR_NONE && strlen($singleBodyPart['body'])>0)
			if ($test=="null" && strlen($sessionData['body'])>0)
			{
				// try to fix broken utf8
				$x = (function_exists('mb_convert_encoding')?mb_convert_encoding($sessionData['body'],'UTF-8','UTF-8'):(function_exists('iconv')?@iconv("UTF-8","UTF-8//IGNORE",$sessionData['body']):$sessionData['body']));
				$test = @json_encode($x);
				if ($test=="null" && strlen($sessionData['body'])>0)
				{
					// this should not be needed, unless something fails with charset detection/ wrong charset passed
					error_log(__METHOD__.__LINE__.' Charset problem detected; Charset Detected:'.mail_bo::detect_encoding($sessionData['body']));
					$sessionData['body'] = utf8_encode($sessionData['body']);
				}
				else
				{
					$sessionData['body'] = $x;
				}
			}
		}
		//error_log(__METHOD__.__LINE__.$sessionData['body']);
		if($sessionData['mimeType'] == 'html') {
			$mode = 'simple-withimage';
			//$mode ='advanced';// most helpful for debuging
			#if (isset($GLOBALS['egw_info']['server']['enabled_spellcheck'])) $mode = 'egw_simple_spellcheck';
			$style="border:0px; width:100%; height:500px;";
			// dont run purify, as we already did that (getCleanHTML).
/*
			$this->t->set_var('tinymce', html::fckEditorQuick('body', $mode, $sessionData['body'],'500px','100%',false,'0px',($_focusElement=='body'?true:false),($_focusElement!='body'?'parent.setToFocus("'.$_focusElement.'");':'')));
			$this->t->set_var('mimeType', 'html');
*/
			$ishtml=1;
		} else {
			$border="1px solid gray; margin:1px";
			// initalize the CKEditor Object to enable switching back and force
			$editor = html::fckEditorQuick('body', 'ascii', $sessionData['body'],'500px','99%',false,$border);
/*
			$this->t->set_var('tinymce', $editor); //html::fckEditorQuick('body', 'ascii', $sessionData['body'],'400px','99%'));
			$this->t->set_var('mimeType', 'text');
*/
			$ishtml=0;
		}


		$bostationery = new felamimail_bostationery();
		$selectStationeries = array(
			'0' => lang('no stationery')
		);
		$showStationaries = false;
		$validStationaries = $bostationery->get_valid_templates();
		if (is_array($validStationaries) && count($validStationaries)>0)
		{
			$showStationaries = true;
			$selectStationeries += $validStationaries;
		}
		// if ID of signature Select Box is set, we allow for changing the sig onChange of the signatueSelect
		$selectBoxSignature  = html::select('signatureID', ($presetSig ? $presetSig : $sessionData['signatureID']), $selectSignatures, true, ($insertSigOnTop?"id='signatureID'":"")." style='width: 35%;' onchange='fm_compose_changeInputType(this)'");
		$selectBoxStationery = html::select('stationeryID', ($presetStationery ? $presetStationery : 0), $selectStationeries, true, "style='width: 35%;'");
/*
		$this->t->set_var("select_signature", $selectBoxSignature);
		$this->t->set_var("select_stationery", ($showStationaries ? $selectBoxStationery:''));
		$this->t->set_var("lang_editormode",lang("Editor type"));
		if (html::htmlarea_availible()===false)
		{
			$this->t->set_var("toggle_editormode",'');
		}
		else
		{
			// IE seems to need onclick to realize there is a change
			$this->t->set_var("toggle_editormode", lang("Editor type").":&nbsp;<span><input name=\"_is_html\" value=\"".$ishtml."\" type=\"hidden\" /><input name=\"_editorselect\" onchange=\"fm_toggle_editor(this)\" onclick=\"fm_toggle_editor(this)\" ".($ishtml ? "checked=\"checked\"" : "")." id=\"_html\" value=\"html\" type=\"radio\"><label for=\"_html\">HTML</label><input name=\"_editorselect\" onchange=\"fm_toggle_editor(this)\" onclick=\"fm_toggle_editor(this)\" ".($ishtml ? "" : "checked=\"checked\"")." id=\"_plain\" value=\"plain\" type=\"radio\"><label for=\"_plain\">Plain text</label></span>");
		}
		$this->t->pparse("out","body_input");
*/
		// attachments
		if (is_array($sessionData['attachments']) && count($sessionData['attachments']) > 0)
		{
			$imgClearLeft	=  common::image('felamimail','clear_left');
			$tableRows = array();
			foreach((array)$sessionData['attachments'] as $id => $attachment) {
				$tempArray = array (
					'1' => $attachment['name'], '.1' => 'width="40%"',
					'2' => mime_magic::mime2label($attachment['type']),
					'3' => egw_vfs::hsize($attachment['size']), '.3' => "style='text-align:right;'",
					'4' => '&nbsp;', '.4' => 'width="10%"',
					'5' => "<img src='$imgClearLeft' onclick=\"fm_compose_deleteAttachmentRow(this,'".$this->composeID."','$id')\">",
				);
				$tableRows[] = $tempArray;
			}

			if(count($tableRows) > 0) {
				$table = html::table($tableRows, "style='width:100%'");
			}
//			$this->t->set_var('attachment_rows',$table);
		}
		else
		{
//			$this->t->set_var('attachment_rows','');
		}

//		$this->t->pparse("out","attachment");
$content['is_html'] = $this->sessionData['mimeType'] == 'html';
$content['mailtext'] = 'garbage';
		$etpl->exec('mail.mail_compose.compose',$content,$sel_options,$readonlys,$preserv,2);
	}

	function composeFromDraft() {
		$icServer = (int)$_GET['icServer'];
		$folder = (isset($_GET['folder'])?base64_decode($_GET['folder']):base64_decode($_GET['mailbox']));
		$replyID = $_GET['uid'];

		if (!empty($folder) && !empty($replyID) ) {
			// this fill the session data with the values from the original email
			$this->getDraftData($icServer, $folder, $replyID);
		}
		$this->compose('body',$suppressSigOnTop=true);
	}

	/**
	 * Callback for filemanagers select file dialog
	 *
	 * @param string $composeid
	 * @param string|array $files path of file(s) in vfs (no egw_vfs::PREFIX, just the path)
	 * @return string javascript output by the file select dialog, usually to close it
	 */
	function vfsSelector($composeid,$files)
	{
		foreach((array) $files as $path)
		{
			$formData = array(
				'name' => egw_vfs::basename($path),
				'type' => egw_vfs::mime_content_type($path),
				'file' => egw_vfs::PREFIX.$path,
				'size' => filesize(egw_vfs::PREFIX.$path),
			);
			$this->addAttachment($formData);
		}
		return 'window.close();';
	}

	function fileSelector()
	{
		if(is_array($_FILES["addFileName"])) {
			#phpinfo();
			//_debug_array($_FILES);
			$success=false;
			if (is_array($_FILES["addFileName"]['name']))
			{
				// multiple uploads supported by newer firefox (>3.6) and chrome (>4) versions,
				// upload array information is by key within the attribute (name, type, size, temp_name)
				foreach($_FILES["addFileName"]['name'] as $key => $filename)
				{
					if($_FILES['addFileName']['error'][$key] == $UPLOAD_ERR_OK) {
						$formData['name']	= $_FILES['addFileName']['name'][$key];
						$formData['type']	= $_FILES['addFileName']['type'][$key];
						$formData['file']	= $_FILES['addFileName']['tmp_name'][$key];
						$formData['size']	= $_FILES['addFileName']['size'][$key];
						$this->addAttachment($formData);
						$success = true;
					}
				}
			}
			else // should not happen as upload form name is defined as addFileName[]
			{
				if($_FILES['addFileName']['error'] == $UPLOAD_ERR_OK) {
					$formData['name']   = $_FILES['addFileName']['name'];
					$formData['type']   = $_FILES['addFileName']['type'];
					$formData['file']   = $_FILES['addFileName']['tmp_name'];
					$formData['size']   = $_FILES['addFileName']['size'];
					$this->addAttachment($formData);
					$success = true;
				}
			}
			if ($success == true)
			{
				print "<script type='text/javascript'>window.close();</script>";
			}
			else
			{
				print "<script type='text/javascript'>document.getElementById('fileSelectorDIV1').style.display = 'inline';document.getElementById('fileSelectorDIV2').style.display = 'none';</script>";
			}
		}

		// this call loads js and css for the treeobject
		html::tree(false,false,false,null,'foldertree','','',false,'/',null,false);
		egw_framework::validate_file('jscode','composeMessage','felamimail');

		#$uiwidgets		=& CreateObject('felamimail.uiwidgets');
/*
		$this->t->set_file(array("composeForm" => "composeForm.tpl"));
		$this->t->set_block('composeForm','fileSelector','fileSelector');

		$this->translate();

		$linkData = array
		(
			'menuaction'	=> 'felamimail.uicompose.fileSelector',
			'composeid'	=> $this->composeID
		);
		$this->t->set_var('file_selector_url', egw::link('/index.php',$linkData));

		$maxUploadSize = ini_get('upload_max_filesize');
		$this->t->set_var('max_uploadsize', $maxUploadSize);

		$this->t->set_var('ajax-loader', common::image('felamimail','ajax-loader'));

		$this->t->pparse("out","fileSelector");
*/
	}

	function forward() {
		$icServer = (int)$_GET['icServer'];
		$folder = base64_decode($_GET['folder']);
		$replyID = $_GET['reply_id'];
		$partID  = $_GET['part_id'];
		$mode = false;
		if (isset($_GET['mode']) && ($_GET['mode']=='forwardasattach'||$_GET['mode']=='forwardinline')) $mode  = ($_GET['mode']=='forwardinline'?'inline':'asattach');
		if (!empty($replyID))
		{
			// this fill the session data with the values from the original email
			$this->getForwardData($icServer, $folder, $replyID, $partID, $mode);
		}
		$handleAsReply = ($mode?$mode=='inline':$this->preferencesArray['message_forwarding'] == 'inline');
		$this->compose('to',false, $handleAsReply);
	}

	function getAttachment()
	{
		$bocompose  = CreateObject('felamimail.bocompose', $_GET['_composeID']);
		$attachment =  $bocompose->sessionData['attachments'][$_GET['attID']] ;
		if (!empty($attachment['folder']))
		{
			$is_winmail = $_GET['is_winmail'] ? $_GET['is_winmail'] : 0;
			$this->mailbox  = $attachment['folder'];
			$this->mail_bo->reopen($this->mailbox);
			#$attachment 	= $this->mail_bo->getAttachment($this->uid,$part);
			$attachmentData = $this->mail_bo->getAttachment($attachment['uid'],$attachment['partID'],$is_winmail);
			$this->mail_bo->closeConnection();
		}

		if (parse_url($attachment['file'],PHP_URL_SCHEME) == 'vfs')
		{
			egw_vfs::load_wrapper('vfs');
		}
		//error_log(print_r($attachmentData,true));
		header ("Content-Type: ".$attachment['type']."; name=\"". $this->mail_bo->decode_header($attachment['name']) ."\"");
		header ("Content-Disposition: inline; filename=\"". $this->mail_bo->decode_header($attachment['name']) ."\"");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Pragma: public");
		if (!empty($attachment['file']))
		{
			$fp = fopen($attachment['file'], 'rb');
			fpassthru($fp);
			fclose($fp);
		}
		else
		{
			echo $attachmentData['attachment'];
		}
		common::egw_exit();
		exit;

	}


	function selectFolder()
	{
		// this call loads js and css for the treeobject
		html::tree(false,false,false,null,'foldertree','','',false,'/',null,false);

		egw_framework::validate_file('jscode','composeMessage','felamimail');
		common::egw_header();

		$mail_bo		= $this->mail_bo;
		$uiwidgets		= CreateObject('felamimail.uiwidgets');
		$connectionStatus	= $mail_bo->openConnection($mail_bo->profileID);

		$folderObjects = $mail_bo->getFolderObjects(true,false);
		$folderTree = $uiwidgets->createHTMLFolder
		(
			$folderObjects,
			'INBOX',
			0,
			lang('IMAP Server'),
			$mailPreferences['username'].'@'.$mailPreferences['imapServerAddress'],
			'divFolderTree',
			false//,
			//true
		);
		print '<div id="divFolderTree" style="overflow:auto; width:320px; height:450px; margin-bottom:0px; padding-left:0px; padding-top:0px; z-index:100; border:1px solid Silver;"></div>';
		print $folderTree;
	}

	function composeAsNew() {
		$icServer = (int)$_GET['icServer'];
		$folder = (isset($_GET['folder'])?base64_decode($_GET['folder']):base64_decode($_GET['mailbox']));
		$replyID = $_GET['reply_id'];
		$partID  = $_GET['part_id'];
		if (!empty($folder) && !empty($replyID) ) {
			// this fill the session data with the values from the original email
			$this->getDraftData($icServer, $folder, $replyID, $partID);
		}
		$this->compose('body',true);
	}

	function reply() {
		$icServer = (int)$_GET['icServer'];
		$folder = base64_decode($_GET['folder']);
		$replyID = $_GET['reply_id'];
		$partID	 = $_GET['part_id'];
		if (!empty($folder) && !empty($replyID) ) {
			// this fill the session data with the values from the original email
			$this->getReplyData('single', $icServer, $folder, $replyID, $partID);
		}
		$this->compose('body',false,true);
	}

	function replyAll() {
		$icServer = (int)$_GET['icServer'];
		$folder = base64_decode($_GET['folder']);
		$replyID = $_GET['reply_id'];
		$partID	 = $_GET['part_id'];
		if (!empty($folder) && !empty($replyID) ) {
			// this fill the session data with the values from the original email
			$this->getReplyData('all', $icServer, $folder, $replyID, $partID);
		}
		$this->compose('body',false,true);
	}

	/**
	 * previous bocompose stuff
	 */


	/**
	 * adds uploaded files or files in eGW's temp directory as attachments
	 *
	 * It also stores the given data in the session
	 *
	 * @param array $_formData fields of the compose form (to,cc,bcc,reply_to,subject,body,priority,signature), plus data of the file (name,file,size,type)
	 */
	function addAttachment($_formData,$eliminateDoubleAttachments=false)
	{
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

		if ($eliminateDoubleAttachments == true)
			foreach ((array)$this->sessionData['attachments'] as $k =>$attach)
				if ($attach['name'] && $attach['name'] == $_formData['name'] &&
					strtolower($_formData['type'])== strtolower($attach['type']) &&
					stripos($_formData['file'],'vfs://') !== false) return;

		if ($attachfailed === false)
		{
			$buffer = array(
				'name'	=> $_formData['name'],
				'type'	=> $_formData['type'],
				'file'	=> $tmpFileName,
				'size'	=> $_formData['size']
			);
			// trying different ID-ing Method, as getRandomString seems to produce non Random String on certain systems.
			$attachmentID = md5(time().serialize($buffer));
			//error_log(__METHOD__." add Attachment with ID:".$attachmentID." (md5 of serialized array)");
			if (!is_array($this->sessionData['attachments'])) $this->sessionData['attachments']=array();
			$this->sessionData['attachments'][$attachmentID] = $buffer;
			unset($buffer);
		}
		else
		{
			error_log(__METHOD__.__LINE__.array2string($alert_msg));
		}

		$this->saveSessionData();
		//print"<pre>";
		//error_log(__METHOD__.__LINE__.print_r($this->sessionData,true));
		//print"</pre>";exit;
	}

	function addMessageAttachment($_uid, $_partID, $_folder, $_name, $_type, $_size)
	{
		$this->sessionData['attachments'][]=array (
			'uid'		=> $_uid,
			'partID'	=> $_partID,
			'name'		=> $_name,
			'type'		=> $_type,
			'size'		=> $_size,
			'folder'	=> $_folder
		);
		$this->saveSessionData();
	}

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

	function convertHTMLToTextTiny($_html)
	{
		print "<pre>"; print htmlspecialchars($_html); print "</pre>";
		// remove these tags and any spaces behind the tags
		$search = array('/<p.*?> */', '/<.?strong>/', '/<.?em>/', '/<.?u>/', '/<.?ul> */', '/<.?ol> */', '/<.?font.*?> */', '/<.?blockquote> */');
		$replace = '';
		$text = preg_replace($search, $replace, $_html);

		// convert these tags and any spaces behind the tags to line breaks
		$search = array('/<\/li> */', '/<br \/> */');
		$replace = "\r\n";
		$text = preg_replace($search, $replace, $text);

		// convert these tags and any spaces behind the tags to double line breaks
		$search = array('/&nbsp;<\/p> */', '/<\/p> */');
		$replace = "\r\n\r\n";
		$text = preg_replace($search, $replace, $text);

		// special replacements
		$search = array('/<li>/');
		$replace = array('  * ');

		$text = preg_replace($search, $replace, $text);

		$text = html_entity_decode($text, ENT_COMPAT, $this->displayCharset);

		print "<pre>"; print htmlspecialchars($text); print "</pre>"; exit;

		return $text;
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

		$this->setDefaults();

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

		// the array $userEMailAddresses was used for filtering out emailaddresses that are owned by the user, for draft data we should not do this
		//$userEMailAddresses = $this->preferences->getUserEMailAddresses();
		//error_log(__METHOD__.__LINE__.array2string($userEMailAddresses));

		// get message headers for specified message
		#$headers	= $mail_bo->getMessageHeader($_folder, $_uid);
		$headers	= $mail_bo->getMessageEnvelope($_uid, $_partID);
		$addHeadInfo = $mail_bo->getMessageHeader($_uid, $_partID);
		//error_log(__METHOD__.__LINE__.array2string($headers));
		if (!empty($addHeadInfo['X-MAILFOLDER'])) {
			foreach ( explode('|',$addHeadInfo['X-MAILFOLDER']) as $val ) {
				$this->sessionData['folder'][] = $val;
			}
		}
		if (!empty($addHeadInfo['X-SIGNATURE'])) {
			$this->sessionData['signatureID'] = $addHeadInfo['X-SIGNATURE'];
		}
		if (!empty($addHeadInfo['X-STATIONERY'])) {
			$this->sessionData['stationeryID'] = $addHeadInfo['X-STATIONERY'];
		}
		if (!empty($addHeadInfo['X-IDENTITY'])) {
			$this->sessionData['identity'] = $addHeadInfo['X-IDENTITY'];
		}
		// if the message is located within the draft folder, add it as last drafted version (for possible cleanup on abort))
		if ($mail_bo->isDraftFolder($_folder)) $this->sessionData['lastDrafted'] = array('uid'=>$_uid,'folder'=>$_folder);
		$this->sessionData['uid'] = $_uid;
		$this->sessionData['messageFolder'] = $_folder;
		$this->sessionData['isDraft'] = true;
		foreach((array)$headers['CC'] as $val) {
			if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
				continue;
			}

			//if($userEMailAddresses[$val['EMAIL']]) {
			//	continue;
			//}

			if(!$foundAddresses[$val['EMAIL']]) {
				$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
				$address = $this->mail_bo->decode_header($address,true);
				$this->sessionData['cc'][] = $address;
				$foundAddresses[$val['EMAIL']] = true;
			}
		}

		foreach((array)$headers['TO'] as $val) {
			if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
				continue;
			}

			//if($userEMailAddresses[$val['EMAIL']]) {
			//	continue;
			//}

			if(!$foundAddresses[$val['EMAIL']]) {
				$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
				$address = $this->mail_bo->decode_header($address,true);
				$this->sessionData['to'][] = $address;
				$foundAddresses[$val['EMAIL']] = true;
			}
		}

		foreach((array)$headers['REPLY_TO'] as $val) {
			if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
				continue;
			}

			//if($userEMailAddresses[$val['EMAIL']]) {
			//	continue;
			//}

			if(!$foundAddresses[$val['EMAIL']]) {
				$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
				$address = $this->mail_bo->decode_header($address,true);
				$this->sessionData['replyto'][] = $address;
				$foundAddresses[$val['EMAIL']] = true;
			}
		}

		foreach((array)$headers['BCC'] as $val) {
			if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
				continue;
			}

			//if($userEMailAddresses[$val['EMAIL']]) {
			//	continue;
			//}

			if(!$foundAddresses[$val['EMAIL']]) {
				$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
				$address = $this->mail_bo->decode_header($address,true);
				$this->sessionData['bcc'][] = $address;
				$foundAddresses[$val['EMAIL']] = true;
			}
		}

		$this->sessionData['subject']	= $mail_bo->decode_header($headers['SUBJECT']);
		// remove a printview tag if composing
		$searchfor = '/^\['.lang('printview').':\]/';
		$this->sessionData['subject'] = preg_replace($searchfor,'',$this->sessionData['subject']);
		$bodyParts = $mail_bo->getMessageBody($_uid, $this->preferencesArray['always_display'], $_partID);
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

		$this->saveSessionData();
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
			$modebuff = $this->preferencesArray['message_forwarding'];
			$this->preferencesArray['message_forwarding'] = $_mode;
		}
		if  ($this->preferencesArray['message_forwarding'] == 'inline') {
			$this->getReplyData('forward', $_icServer, $_folder, $_uid, $_partID);
		}
		$mail_bo    = $this->mail_bo;
		$mail_bo->openConnection();
		$mail_bo->reopen($_folder);

		// get message headers for specified message
		$headers	= $mail_bo->getMessageEnvelope($_uid, $_partID);

		//_debug_array($headers); exit;
		// check for Re: in subject header
		$this->sessionData['subject'] 	= "[FWD] " . $mail_bo->decode_header($headers['SUBJECT']);
		$this->sessionData['sourceFolder']=$_folder;
		$this->sessionData['forwardFlag']='forwarded';
		$this->sessionData['forwardedUID']=$_uid;
		if  ($this->preferencesArray['message_forwarding'] == 'asmail') {
			$this->sessionData['mimeType']  = $this->preferencesArray['composeOptions'];
			if($headers['SIZE'])
				$size				= $headers['SIZE'];
			else
				$size				= lang('unknown');

			$this->addMessageAttachment($_uid, $_partID, $_folder,
				$mail_bo->decode_header(($headers['SUBJECT']?$headers['SUBJECT']:lang('no subject'))),
				'MESSAGE/RFC822', $size);
		}
		else
		{
			unset($this->sessionData['in-reply-to']);
			unset($this->sessionData['to']);
			unset($this->sessionData['cc']);
			if($attachments = $mail_bo->getMessageAttachments($_uid,$_partID)) {
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
			$this->preferencesArray['message_forwarding'] = $modebuff;
		}
		$this->saveSessionData();
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

		$mail_bo    = $this->mail_bo;
		$mail_bo->openConnection();
		$mail_bo->reopen($_folder);

		$userEMailAddresses = $this->preferences->getUserEMailAddresses();

		// get message headers for specified message
		#print "AAAA: $_folder, $_uid, $_partID<br>";
		$headers	= $mail_bo->getMessageEnvelope($_uid, $_partID);
		#$headers	= $mail_bo->getMessageHeader($_uid, $_partID);
		$this->sessionData['uid'] = $_uid;
		$this->sessionData['messageFolder'] = $_folder;
		$this->sessionData['in-reply-to'] = $headers['MESSAGE_ID'];

		// check for Reply-To: header and use if available
		if(!empty($headers['REPLY_TO']) && ($headers['REPLY_TO'] != $headers['FROM'])) {
			foreach($headers['REPLY_TO'] as $val) {
				if($val['EMAIL'] == 'NIL') {
					continue;
				}

				if(!$foundAddresses[$val['EMAIL']]) {
					$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
					$address = $this->mail_bo->decode_header($address,true);
					$oldTo[] = $address;
					$foundAddresses[$val['EMAIL']] = true;
				}
			}
			$oldToAddress	= $headers['REPLY_TO'][0]['EMAIL'];
		} else {
			foreach($headers['FROM'] as $val) {
				if($val['EMAIL'] == 'NIL') {
					continue;
				}
				if(!$foundAddresses[$val['EMAIL']]) {
					$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
					$address = $this->mail_bo->decode_header($address,true);
					$oldTo[] = $address;
					$foundAddresses[$val['EMAIL']] = true;
				}
			}
			$oldToAddress	= $headers['REPLY_TO'][0]['EMAIL'];
		}

		if($_mode != 'all' || ($_mode == 'all' && !$userEMailAddresses[$oldToAddress]) ) {
			$this->sessionData['to'] = $oldTo;
		}

		if($_mode == 'all') {
			// reply to any address which is cc, but not to my self
			#if($headers->cc) {
				foreach($headers['CC'] as $val) {
					if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
						continue;
					}

					if($userEMailAddresses[$val['EMAIL']]) {
						continue;
					}

					if(!$foundAddresses[$val['EMAIL']]) {
						$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
						$address = $this->mail_bo->decode_header($address,true);
						$this->sessionData['cc'][] = $address;
						$foundAddresses[$val['EMAIL']] = true;
					}
				}
			#}

			// reply to any address which is to, but not to my self
			#if($headers->to) {
				foreach($headers['TO'] as $val) {
					if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
						continue;
					}

					if($userEMailAddresses[$val['EMAIL']]) {
						continue;
					}

					if(!$foundAddresses[$val['EMAIL']]) {
						$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
						$address = $this->mail_bo->decode_header($address,true);
						$this->sessionData['to'][] = $address;
						$foundAddresses[$val['EMAIL']] = true;
					}
				}
			#}

			#if($headers->from) {
				foreach($headers['FROM'] as $val) {
					if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
						continue;
					}

					if($userEMailAddresses[$val['EMAIL']]) {
						continue;
					}

					if(!$foundAddresses[$val['EMAIL']]) {
						$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
						$address = $this->mail_bo->decode_header($address,true);
						$this->sessionData['to'][] = $address;
						$foundAddresses[$val['EMAIL']] = true;
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
		//error_log(__METHOD__.__LINE__.'->'.array2string($this->preferencesArray['htmlOptions']));
		$bodyParts = $mail_bo->getMessageBody($_uid, ($this->preferencesArray['htmlOptions']?$this->preferencesArray['htmlOptions']:''), $_partID);
		//_debug_array($bodyParts);
		$styles = mail_bo::getStyles($bodyParts);

		$fromAddress = mail_bo::htmlspecialchars((($headers['FROM'][0]['PERSONAL_NAME'] != 'NIL') ? str_replace(array('<','>'),array('[',']'),$mail_bo->decode_header($headers['FROM'][0]['RFC822_EMAIL'],true)) : $mail_bo->decode_header($headers['FROM'][0]['EMAIL'],true)));

		$toAddressA = array();
		$toAddress = '';
		foreach ($headers['TO'] as $mailheader) {
			$toAddressA[] =  trim($mail_bo->decode_header((($mailheader['PERSONAL_NAME'] != 'NIL') ? $mailheader['RFC822_EMAIL'] : $mailheader['EMAIL']),true));
		}
		if (count($toAddressA)>0)
		{
			$toAddress = mail_bo::htmlspecialchars(implode(', ', str_replace(array('<','>'),array('[',']'),$toAddressA)));
			$toAddress = @htmlspecialchars(lang("to")).": ".$toAddress.($bodyParts['0']['mimeType'] == 'text/html'?"<br>":"\r\n");
		}
		$ccAddressA = array();
		$ccAddress = '';
		foreach ($headers['CC'] as $mailheader) {
			$ccAddressA[] =  trim($mail_bo->decode_header((($mailheader['PERSONAL_NAME'] != 'NIL') ? $mailheader['RFC822_EMAIL'] : $mailheader['EMAIL']),true));
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

		$this->saveSessionData();
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

	function getSessionData()
	{
		return $this->sessionData;
	}

	function removeAttachment($_attachmentID) {
		if (parse_url($this->sessionData['attachments'][$_attachmentID]['file'],PHP_URL_SCHEME) != 'vfs') {
			unlink($this->sessionData['attachments'][$_attachmentID]['file']);
		}
		unset($this->sessionData['attachments'][$_attachmentID]);
		$this->saveSessionData();
	}

	function restoreSessionData()
	{
		$this->sessionData = $GLOBALS['egw']->session->appsession('compose_session_data_'.$this->composeID, 'felamimail');
	}

	function saveSessionData()
	{
		$GLOBALS['egw']->session->appsession('compose_session_data_'.$this->composeID,'felamimail',$this->sessionData);
	}

	function createMessage(&$_mailObject, $_formData, $_identity, $_signature = false, $_convertLinks=false)
	{
		$mail_bo	= $this->mail_bo;
		$_mailObject->PluginDir = EGW_SERVER_ROOT."/phpgwapi/inc/";
		$activeMailProfile = $this->preferences->getIdentity($this->mail_bo->profileID, true);
		$_mailObject->IsSMTP();
		$_mailObject->CharSet	= $this->displayCharset;
		// you need to set the sender, if you work with different identities, since most smtp servers, dont allow
		// sending in the name of someone else
		if ($_identity->id != $activeMailProfile->id && strtolower($activeMailProfile->emailAddress) != strtolower($_identity->emailAddress)) error_log(__METHOD__.__LINE__.' Faking From/SenderInfo for '.$activeMailProfile->emailAddress.' with ID:'.$activeMailProfile->id.'. Identitiy to use for sending:'.array2string($_identity));
		$_mailObject->Sender  = ($_identity->id<0 && $activeMailProfile->id < 0 ? $_identity->emailAddress : $activeMailProfile->emailAddress);
		$_mailObject->From 	= $_identity->emailAddress;
		$_mailObject->FromName = $_mailObject->EncodeHeader(mail_bo::generateIdentityString($_identity,false));
		$_mailObject->Priority = $_formData['priority'];
		$_mailObject->Encoding = 'quoted-printable';
		$_mailObject->AddCustomHeader('X-Mailer: FeLaMiMail');
		if(isset($this->sessionData['in-reply-to'])) {
			$_mailObject->AddCustomHeader('In-Reply-To: '. $this->sessionData['in-reply-to']);
		}
		if($_formData['disposition']) {
			$_mailObject->AddCustomHeader('Disposition-Notification-To: '. $_identity->emailAddress);
		}
		if(!empty($_identity->organization) && (mail_bo::$mailConfig['how2displayIdentities'] == '' || mail_bo::$mailConfig['how2displayIdentities'] == 'orgNemail')) {
			#$_mailObject->AddCustomHeader('Organization: '. $mail_bo->encodeHeader($_identity->organization, 'q'));
			$_mailObject->AddCustomHeader('Organization: '. $_identity->organization);
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
		$signature = $_signature->fm_signature;

		if ((isset($this->preferencesArray['insertSignatureAtTopOfMessage']) && $this->preferencesArray['insertSignatureAtTopOfMessage']))
		{
			// note: if you use stationery ' s the insert signatures at the top does not apply here anymore, as the signature
			// is already part of the body, so the signature part of the template will not be applied.
			$signature = null; // note: no signature, no ruler!!!!
		}
		if ((isset($this->preferencesArray['disableRulerForSignatureSeparation']) &&
			$this->preferencesArray['disableRulerForSignatureSeparation']) ||
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
				if($this->sessionData['stationeryID']) {
					$bostationery = new felamimail_bostationery();
					$_mailObject->Body = $bostationery->render($this->sessionData['stationeryID'],$_formData['body'],$signature);
				} else {
					$_mailObject->Body = $_formData['body'] .
						($disableRuler ?'<br>':'<hr style="border:1px dotted silver; width:90%;">').
						$signature;
				}
				$_mailObject->AltBody = $this->convertHTMLToText($_formData['body'],true,true).
					($disableRuler ?"\r\n":"\r\n-- \r\n").
					$this->convertHTMLToText($signature,true,true);
				#print "<pre>$_mailObject->AltBody</pre>";
				#print htmlentities($_signature['signature']);
			} else {
				if($this->sessionData['stationeryID']) {
					$bostationery = new felamimail_bostationery();
					$_mailObject->Body = $bostationery->render($this->sessionData['stationeryID'],$_formData['body']);
				} else {
					$_mailObject->Body	= $_formData['body'];
				}
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
		if (is_array($this->sessionData) && isset($this->sessionData['attachments']))
		{
			$tnfattachments = null;
			foreach((array)$this->sessionData['attachments'] as $attachment) {
				if(is_array($attachment))
				{
					if (!empty($attachment['uid']) && !empty($attachment['folder'])) {
						$mail_bo->reopen($attachment['folder']);
						switch($attachment['type']) {
							case 'MESSAGE/RFC822':
								$rawHeader='';
								if (isset($attachment['partID'])) {
									$rawHeader      = $mail_bo->getMessageRawHeader($attachment['uid'], $attachment['partID']);
								}
								$rawBody        = $mail_bo->getMessageRawBody($attachment['uid'], $attachment['partID']);
								$_mailObject->AddStringAttachment($rawHeader.$rawBody, $_mailObject->EncodeHeader($attachment['name']), '7bit', 'message/rfc822');
								break;
							default:
								$attachmentData	= $mail_bo->getAttachment($attachment['uid'], $attachment['partID']);
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
		$this->sessionData['signatureID'] = $_formData['signatureID'];
		$this->sessionData['stationeryID'] = $_formData['stationeryID'];
		$this->sessionData['identity']  = $_formData['identity'];

		$identity = $this->preferences->getIdentity((int)$this->sessionData['identity'],true);

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
		$mail->AddCustomHeader('X-Signature: '.$this->sessionData['signatureID']);
		$mail->AddCustomHeader('X-Stationery: '.$this->sessionData['stationeryID']);
		$mail->AddCustomHeader('X-Identity: '.(int)$this->sessionData['identity']);
		// decide where to save the message (default to draft folder, if we find nothing else)
		// if the current folder is in draft or template folder save it there
		// if it is called from printview then save it with the draft folder
		//error_log(__METHOD__.__LINE__.array2string($this->preferences->ic_server));
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
			error_log("mail_bo::saveAsDraft->".lang("folder")." ". $savingDestination." ".lang("does not exist on IMAP Server."));
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

		$this->sessionData['identity']	= $_formData['identity'];
		$this->sessionData['to']	= $_formData['to'];
		$this->sessionData['cc']	= $_formData['cc'];
		$this->sessionData['bcc']	= $_formData['bcc'];
		$this->sessionData['folder']	= $_formData['folder'];
		$this->sessionData['replyto']	= $_formData['replyto'];
		$this->sessionData['subject']	= trim($_formData['subject']);
		$this->sessionData['body']	= $_formData['body'];
		$this->sessionData['priority']	= $_formData['priority'];
		$this->sessionData['signatureID'] = $_formData['signatureID'];
		$this->sessionData['stationeryID'] = $_formData['stationeryID'];
		$this->sessionData['disposition'] = $_formData['disposition'];
		$this->sessionData['mimeType']	= $_formData['mimeType'];
		$this->sessionData['to_infolog'] = $_formData['to_infolog'];
		$this->sessionData['to_tracker'] = $_formData['to_tracker'];
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
		#error_log(print_r($this->preferences,true));
		$identity = $this->preferences->getIdentity((int)$this->sessionData['identity'],true);
		$signature = $this->bosignatures->getSignature((int)$this->sessionData['signatureID']);
		//error_log($this->sessionData['identity']);
		//error_log(print_r($identity,true));
		// create the messages
		$this->createMessage($mail, $_formData, $identity, $signature, true);
		// remember the identity
		if ($_formData['to_infolog'] == 'on' || $_formData['to_tracker'] == 'on') $fromAddress = $mail->FromName.($mail->FromName?' <':'').$mail->From.($mail->FromName?'>':'');
		#print "<pre>". $mail->getMessageHeader() ."</pre><hr><br>";
		#print "<pre>". $mail->getMessageBody() ."</pre><hr><br>";
		#exit;

		$ogServer = $this->preferences->getOutgoingServer($this->mail_bo->profileID);
		#_debug_array($ogServer);
		$mail->Host 	= $ogServer->host;
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

		// check if there are folders to be used
		$folder = (array)$this->sessionData['folder'];
		$sentFolder = $this->mail_bo->getSentFolder();
		if(isset($sentFolder) && $sentFolder != 'none' &&
			$this->preferences->preferences['sendOptions'] != 'send_only' &&
			$messageIsDraft == false)
		{
			if ($this->mail_bo->folderExists($sentFolder, true))
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
			if ((!isset($sentFolder) && $this->preferences->preferences['sendOptions'] != 'send_only') ||
				($this->preferences->preferences['sendOptions'] != 'send_only' &&
				$sentFolder != 'none')) $this->errorInfo = lang("No Send Folder set in preferences");
		}
		if($messageIsDraft == true) {
			$draftFolder = $this->mail_bo->getDraftFolder();
			if(!empty($draftFolder) && $this->mail_bo->folderExists($draftFolder)) {
				$this->sessionData['folder'] = array($draftFolder);
				$folder[] = $draftFolder;
			}
		}
		$folder = array_unique($folder);
		if (($this->preferences->preferences['sendOptions'] != 'send_only' && $sentFolder != 'none') && !(count($folder) > 0) &&
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
		#error_log("Mail Sent.!");
		#error_log("Number of Folders to move copy the message to:".count($folder));
		if ((count($folder) > 0) || (isset($this->sessionData['uid']) && isset($this->sessionData['messageFolder']))
            || (isset($this->sessionData['forwardFlag']) && isset($this->sessionData['sourceFolder']))) {
			$mail_bo = $this->mail_bo;
			$mail_bo->openConnection();
			//$mail_bo->reopen($this->sessionData['messageFolder']);
			#error_log("(re)opened Connection");
		}
		// if copying mail to folder, or saving mail to infolog, we need to gather the needed information
		if (count($folder) > 0 || $_formData['to_infolog'] == 'on') {
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
				if($mail_bo->isSentFolder($folderName)) {
					$flags = '\\Seen';
				} elseif($mail_bo->isDraftFolder($folderName)) {
					$flags = '\\Draft';
				} else {
					$flags = '';
				}
				#$mailHeader=explode('From:',$mail->getMessageHeader());
				#$mailHeader[0].$mail->AddrAppend("Bcc",$mailAddr).'From:'.$mailHeader[1],
				if ($mail_bo->folderExists($folderName,true)) {
					try
					{
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
			//$mail_bo->closeConnection();
		}
		// handle previous drafted versions of that mail
		$lastDrafted = false;
		if (isset($this->sessionData['lastDrafted']))
		{
			$lastDrafted = $this->sessionData['lastDrafted'];
			if (isset($lastDrafted['uid']) && !empty($lastDrafted['uid'])) $lastDrafted['uid']=trim($lastDrafted['uid']);
			if (isset($lastDrafted['uid']) && (empty($lastDrafted['uid']) || $lastDrafted['uid'] == $this->sessionData['uid'])) $lastDrafted=false;
			//error_log(__METHOD__.__LINE__.array2string($lastDrafted));
		}
		if ($lastDrafted && is_array($lastDrafted) && $mail_bo->isDraftFolder($lastDrafted['folder'])) $mail_bo->deleteMessages((array)$lastDrafted['uid'],$lastDrafted['folder'],"remove_immediately");
		unset($this->sessionData['lastDrafted']);

		//error_log("handling draft messages, flagging and such");
		if((isset($this->sessionData['uid']) && isset($this->sessionData['messageFolder']))
			|| (isset($this->sessionData['forwardFlag']) && isset($this->sessionData['sourceFolder']))) {
			// mark message as answered
			$mail_bo->openConnection();
			$mail_bo->reopen($this->sessionData['messageFolder']);
			// if the draft folder is a starting part of the messages folder, the draft message will be deleted after the send
			// unless your templatefolder is a subfolder of your draftfolder, and the message is in there
			if ($mail_bo->isDraftFolder($this->sessionData['messageFolder']) && !$mail_bo->isTemplateFolder($this->sessionData['messageFolder']))
			{
				$mail_bo->deleteMessages(array($this->sessionData['uid']),$this->sessionData['messageFolder']);
			} else {
				$mail_bo->flagMessages("answered", array($this->sessionData['uid']));
				if (array_key_exists('forwardFlag',$this->sessionData) && $this->sessionData['forwardFlag']=='forwarded')
				{
					$mail_bo->flagMessages("forwarded", array($this->sessionData['forwardedUID']));
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
		$this->saveSessionData();

		return true;
	}

	function setDefaults()
	{
		require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.felamimail_bosignatures.inc.php');
		$boSignatures = new felamimail_bosignatures();

		if($signatureData = $boSignatures->getDefaultSignature()) {
			if (is_array($signatureData)) {
				$this->sessionData['signatureID'] = $signatureData['signatureid'];
			} else {
				$this->sessionData['signatureID'] = $signatureData;
			}
		} else {
			$this->sessionData['signatureID'] = -1;
		}
		// retrieve the signature accociated with the identity
		$accountData    = $this->bopreferences->getAccountData($this->preferences,'active');
		if ($accountData['identity']->signature) $this->sessionData['signatureID'] = $accountData['identity']->signature;
		// apply the current mailbox to the compose session data of the/a new email
		$appsessionData = $GLOBALS['egw']->session->appsession('session_data');
		$this->sessionData['mailbox'] = $appsessionData['mailbox'];

		$this->sessionData['mimeType'] = 'html';
		if (!empty($this->preferencesArray['composeOptions']) && $this->preferencesArray['composeOptions']=="text") $this->sessionData['mimeType']  = 'plain';

		$this->saveSessionData();
	}

	function stripSlashes($_string)
	{
		if (get_magic_quotes_gpc()) {
			return stripslashes($_string);
		} else {
			return $_string;
		}
	}

	function ajax_searchIdentities() {
	}

	function ajax_searchFolder() {
		static $useCacheIfPossible;
		if (is_null($useCacheIfPossible)) $useCacheIfPossible = true;
		$_searchString = trim($_REQUEST['query']);
		$results = array();
		if (strlen($_searchString)>=2 && isset($this->mail_bo->icServer))
		{
			//error_log(__METHOD__.__LINE__.':'.$this->mail_bo->icServer->ImapServerId);
			if (!($this->mail_bo->icServer->_connected == 1)) $this->mail_bo->openConnection($this->mail_bo->icServer->ImapServerId);
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
				error_log(__METHOD__.__LINE__.$_searchString.'/'.$searchString.' in '.$k.'->'.$fA->displayName);
				$f=false;
				if (stripos($fA->displayName,$_searchString)!==false)
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
		//$folderName = translation::convert(html_entity_decode($_folderName, ENT_QUOTES, $this->charset),'UTF7-IMAP', $this->charset);
		//$response->add("decodedFolder ='".$folderName."'");

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		common::egw_exit();
	}

	function ajax_searchAddress() {
		$_searchString = trim($_REQUEST['query']);
		if (strlen($_searchString)>=3 && $GLOBALS['egw_info']['user']['apps']['addressbook']) {
			//error_log(__METHOD__.__LINE__.array2string($_searchString));
			if (method_exists($GLOBALS['egw']->contacts,'search')) {
				// 1.3+
				$showAccounts = empty($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']);
				//error_log(__METHOD__.__LINE__.$_searchString);
				$seStAr = explode(' ',$_searchString);
				foreach ($seStAr as $k => $v) if (strlen($v)<3) unset($seStAr[$k]);
				$_searchString = trim(implode(' AND ',$seStAr));
				//error_log(__METHOD__.__LINE__.$_searchString);
				$filter = ($showAccounts?array():array('account_id' => null));
				$filter['cols_to_search']=array('n_prefix','n_given','n_family','org_name','email','email_home');
				$contacts = $GLOBALS['egw']->contacts->search(implode(' +',$seStAr),array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home'),'n_fn','','%',false,'OR',array(0,100),$filter);
				// additionally search the accounts, if the contact storage is not the account storage
				if ($showAccounts && $GLOBALS['egw']->contacts->so_accounts)
				{
					$accounts = $GLOBALS['egw']->contacts->search(array(
						'n_prefix'       => $_searchString,
						'n_given'       => $_searchString,
						'n_family'       => $_searchString,
						'org_name'       => $_searchString,
						'email'      => $_searchString,
						'email_home' => $_searchString,
					),array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home'),'n_fn','','%',false,'OR',array(0,100),array('owner' => 0));

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
			} else {
				// < 1.3
				$contacts = $GLOBALS['egw']->contacts->read(0,20,array(
					'fn' => 1,
					'email' => 1,
					'email_home' => 1,
				), $_searchString, 'tid=n', '', 'fn');
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
						$results[] = array('id'=>$completeMailString, 'label' => htmlspecialchars($completeMailString));
					}
					if ($i > 10) break;	// we check for # of results here, as we might have empty email addresses
				}
			}

		}
//error_log(__METHOD__.__LINE__.array2string($jsArray));
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results);
		common::egw_exit();
	}

}
