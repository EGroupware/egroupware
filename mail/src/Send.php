<?php
/**
 * EGroupware Mail: send a mail directly (no interactive compose UI)
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail;

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Framework;
use EGroupware\Api\Link;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\AddressList;
use EGroupware\Api\Mail\BodyDecoding;

/**
 * Send a mail directly, no interactive compose UI involved - extracted 2026-09-08 from
 * Compose::send(), whose only real caller was already ApiHandler::send() (the REST API's
 * own send-mail endpoint, used only for accounts that aren't JMAP-native - a JMAP/Stalwart account
 * goes through ApiHandler::sendViaJmap() instead, never touching this class at all). Shares its
 * actual MIME-building (createMessage()/_getAttachmentLinks()/_encrypt()/etc.) with
 * Compose::saveAsDraft() via the ComposeMessageBuilder trait, rather than duplicating it or
 * depending on a full Compose instance.
 */
class Send
{
	use ComposeMessageBuilder;

	var $sessionData;

	/**
	 * Set after a failed send()/false return - the actual error message
	 *
	 * @var ?string
	 */
	var $errorInfo;

	function __construct(?int $_acc_id=null)
	{
		$this->initMailAccount($_acc_id);
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
			$tidy = new \tidy();
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

		BodyDecoding::getCleanHTML($_body);
		return preg_replace($nonDisplayAbleCharacters, '', $_body);
	}

	function send($_formData, ?int $_acc_id=null)
	{
		$mail_bo	= $this->mail_bo;
		$mail 		= new Api\Mailer($_acc_id ?: $mail_bo->profileID);
		$messageIsDraft	=  false;

		// it seems there are mail client ignoring / not displaying text behind the closing style-tag --> add a linebreak there
		if (strpos($_formData['body'], '</style><') !== false)
		{
			$_formData['body'] = str_replace('</style><', "</style>\n<", $_formData['body']);
		}

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
		$this->sessionData['to_infolog'] = $_formData['composeToolbar']['to_infolog'];
		$this->sessionData['to_tracker'] = $_formData['composeToolbar']['to_tracker'];
		$this->sessionData['to_calendar'] = $_formData['composeToolbar']['to_calendar'];
		$this->sessionData['attachments']  = $_formData['attachments'];
		$this->sessionData['smime_sign']  = $_formData['composeToolbar']['smime_sign'];
		$this->sessionData['smime_encrypt']  = $_formData['composeToolbar']['smime_encrypt'];

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
					$fhA = Mail::splitRowID($rowid);
					$this->sessionData['uid'][] = $fhA['msgUID'];
					$this->sessionData['forwardedUID'][] = $fhA['msgUID'];
					if (!empty($fhA['folder'])) $this->sessionData['sourceFolder'] = $fhA['folder'];
				}
			}
			if ($_formData['mode']=='reply' && !empty($_formData['processedmail_id']))
			{
				$rhA = Mail::splitRowID($_formData['processedmail_id']);
				$this->sessionData['uid'] = $rhA['msgUID'];
				$this->sessionData['messageFolder'] = $rhA['folder'];
			}
			if ($_formData['mode']=='composefromdraft' && !empty($_formData['processedmail_id']))
			{
				$dhA = Mail::splitRowID($_formData['processedmail_id']);
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
		catch (\Exception $e)
		{
			$identity = array();
		}
		//error_log($this->sessionData['mailaccount']);
		//error_log(__METHOD__.__LINE__.':'.array2string($this->sessionData['mailidentity']).'->'.array2string($identity));
		// create the messages and store inline images
		$inline_images = $this->createMessage($mail, $_formData, $identity);
		// remember the identity
		/** @noinspection MissingIssetImplementationInspection */
		if(!empty($mail->From) && ($_formData['composeToolbar']['to_infolog'] || $_formData['composeToolbar']['to_tracker']))
		{
			$fromAddress = $mail->From;
		}//$mail->FromName.($mail->FromName?' <':'').$mail->From.($mail->FromName?'>':'');
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
		if ($this->mailPreferences['sendOptions'] === 'send_only')
		{
			// no need to check for Sent folder
			$sentFolder = 'none';
		}
		elseif ($_formData['serverID']!=$_formData['mailaccount'])
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
			!($_formData['composeToolbar']['to_infolog'] || $_formData['composeToolbar']['to_tracker']))
		{
			$this->errorInfo = lang("Error: ").lang("No Folder destination supplied, and no folder to save message or other measure to store the mail (save to infolog/tracker) provided, but required.").($this->errorInfo?' '.$this->errorInfo:'');
			#error_log($this->errorInfo);
			return false;
		}
		// SMIME SIGN/ENCRYPTION
		//boolean flags for smime_sign and smime_encrypt are stored in sessionData
		if ($this->sessionData['smime_sign'] || $this->sessionData['smime_encrypt'])
		{
			$recipients = array_merge($_formData['to'], (array) $_formData['cc'], (array) $_formData['bcc']);
			try	{
				if ($this->sessionData['smime_sign'])
				{
					if (!empty($_formData['smime_passphrase']))
					{
						Api\Cache::setSession(
							'mail',
							'smime_passphrase',
							$_formData['smime_passphrase'],
						(int)($GLOBALS['egw_info']['user']['preferences']['mail']['smime_pass_exp']??10) * 60
						);
					}
					$smime_success = $this->_encrypt(
						$mail,
						$this->sessionData['smime_encrypt']? Mail\Smime::TYPE_SIGN_ENCRYPT: Mail\Smime::TYPE_SIGN,
						AddressList::stripRFC822Addresses($recipients),
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
				elseif (!$this->sessionData['smime_sign'] && $this->sessionData['smime_encrypt'])
				{
					$smime_success =  $this->_encrypt(
						$mail,
						Mail\Smime::TYPE_ENCRYPT,
						AddressList::stripRFC822Addresses($recipients),
						$identity['ident_email']
					);
				}
			}
			catch (\Exception $ex)
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
				if (empty($_formData['composeToolbar']['to_infolog']) &&
					empty($_formData['composeToolbar']['to_tracker']) &&
					empty($_formData['composeToolbar']['to_calendar']))
				{
					$GLOBALS['egw']->session->commit_session();
				}
				$mail->send();
			}
			catch(\Exception $e) {
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
		if(count($folder) > 0 || $_formData['composeToolbar']['to_infolog'] || $_formData['composeToolbar']['to_tracker']) {
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
			$dhA = Mail::splitRowID($this->sessionData['lastDrafted']);
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
						($_formData['composeToolbar']['to_infolog'] || $_formData['composeToolbar']['to_tracker'] || $_formData['composeToolbar']['to_calendar']) && $this->sessionData['attachments']))
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
						!(($_formData['composeToolbar']['to_infolog'] || $_formData['composeToolbar']['to_tracker'] || $_formData['composeToolbar']['to_calendar']) && $this->sessionData['attachments']))
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

		if($_formData['composeToolbar']['to_infolog'] || $_formData['composeToolbar']['to_tracker'] || $_formData['composeToolbar']['to_calendar'])
		{
			$this->sessionData['attachments'] = array_merge((array)$this->sessionData['attachments'], (array)$inline_images);

			foreach(array('to_infolog','to_tracker','to_calendar') as $app_key)
			{
				list(, $entryid) = explode(":", $_formData['to_integrate_ids'][0])+[null, null];
				if (!empty($_formData['composeToolbar'][$app_key]))
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
					// attachments come straight from client-submitted form-data - never trust
					// $value['file'] as a path component, or it becomes an arbitrary-file-delete
					unlink($GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($value['file']));
				}
			}
		}

		$this->sessionData = '';

		return true;
	}
}
