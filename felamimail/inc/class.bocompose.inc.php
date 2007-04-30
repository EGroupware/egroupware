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
	* Free Software Foundation; version 2 of the License.                       *
	\***************************************************************************/
	/* $Id$ */

	class bocompose
	{
		var $public_functions = array
		(
			'addAtachment'	=> True,
			'action'	=> True
		);
		
		var $attachments;	// Array of attachments
		var $preferences;	// the prefenrences(emailserver, username, ...)

		function bocompose($_composeID = '', $_charSet = 'iso-8859-1')
		{
			$this->displayCharset	= strtolower($_charSet);
			$this->bopreferences	=& CreateObject('felamimail.bopreferences');
			$this->bofelamimail	=& CreateObject('felamimail.bofelamimail',$_charSet);
			$this->preferences	= $this->bopreferences->getPreferences();
			$this->botranslation	=& CreateObject('phpgwapi.translation');
			$this->preferencesArray =& $GLOBALS['egw_info']['user']['preferences']['felamimail'];

			if (!empty($_composeID))
			{
				$this->composeID = $_composeID;
				$this->restoreSessionData();
			}
			else	// new email
			{
				$this->setDefaults();
			}
		}
		
		/**
		 * adds uploaded files or files in eGW's temp directory as attachments
		 *
		 * It also stores the given data in the session
		 *
		 * @param array $_formData fields of the compose form (to,cc,bcc,reply_to,subject,body,priority,signature), plus data of the file (name,file,size,type)
		 */
		function addAttachment($_formData)
		{
			// to gard against exploits the file must be either uploaded or be in the temp_dir
			if ($_formData['size'] != 0 && (is_uploaded_file($_formData['file']) || 
				realpath(dirname($_formData['file'])) == realpath($GLOBALS['egw_info']['server']['temp_dir'])))
			{
				// ensure existance of eGW temp dir
				// note: this is different from apache temp dir, 
				// and different from any other temp file location set in php.ini
				if (!file_exists($GLOBALS['egw_info']['server']['temp_dir']))
				{
					@mkdir($GLOBALS['egw_info']['server']['temp_dir'],0700);
				}
				
				// if we were NOT able to create this temp directory, then make an ERROR report
				if (!file_exists($GLOBALS['egw_info']['server']['temp_dir']))
				{
					$alert_msg .= 'Error:'.'<br>'
						.'Server is unable to access phpgw tmp directory'.'<br>'
						.$GLOBALS['egw_info']['server']['temp_dir'].'<br>'
						.'Please check your configuration'.'<br>'
						.'<br>';
				}
				
				// sometimes PHP is very clue-less about MIME types, and gives NO file_type
				// rfc default for unknown MIME type is:
				$mime_type_default = 'application/octet-stream';
				// so if PHP did not pass any file_type info, then substitute the rfc default value
				if (trim($_formData['type']) == '')
				{
					$_formData['type'] = $mime_type_default;
				}
				
				$tmpFileName = $GLOBALS['egw_info']['server']['temp_dir'].
					SEP.
					$GLOBALS['egw_info']['user']['account_id'].
					$this->composeID.
					basename($_formData['file']);
				
				if (is_uploaded_file($_formData['file']))
				{
					move_uploaded_file($_formData['file'],$tmpFileName);	// requirement for safe_mode!
				}
				else
				{
					rename($_formData['file'],$tmpFileName);
				}
				$attachmentID = $this->getRandomString();

				$this->sessionData['attachments'][$attachmentID]=array
				(
					'name'	=> $_formData['name'],
					'type'	=> $_formData['type'],
					'file'	=> $tmpFileName,
					'size'	=> $_formData['size']
				);
			}

			$this->saveSessionData();
			#print"<pre>";print_r($this->sessionData);print"</pre>";exit;
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
		
		function convertHTMLToText($_html) 
		{
			#print $_html;
			#print '<hr>';
			#print "<pre>"; print htmlspecialchars($_html); print "</pre>";
			#print "<hr>";
			// remove these tags and any spaces behind the tags
			$search = array('/<div.*?>/', '/<\/div>\r\n/', '/<\/div>/', '/<p.*?> */', '/<br \/>/', '/<\/li>/', '/<.?strong> /', '/<.?strong>/', '/<.?em>/', '/<.?u>/', '/<.?ul> */', '/<.?ol> */', '/<.?font.*?> */');
			$replace = '';
			$text = preg_replace($search, $replace, $_html);
			
			// convert these tags and any spaces behind the tags to double line breaks
			$search = array('/&nbsp;<\/p> */', '/<\/p> */');
			$replace = "\r\n\r\n";
			$text = preg_replace($search, $replace, $text);
			
			// special replacements
			$search = array('/<li>/');
			$replace = array('  * ');
			$text = preg_replace($search, $replace, $text);

			$search = array('/<h[1-9]>/', '/<\/h[1-9]>/');
			$replace = array('* ', '');
			$text = preg_replace($search, $replace, $text);

			$search = array('/<hr.*>/');
			$replace = array("\r\n--------------------------------\r\n");
			$text = preg_replace($search, $replace, $text);
			
			$text = html_entity_decode($text, ENT_COMPAT, $this->displayCharset);

			$indent = 0;
			$indentString = '';
			$asciiText = '';

			$quoteParts = preg_split('/<blockquote type="cite">/', $text, -1, PREG_SPLIT_OFFSET_CAPTURE);

			foreach($quoteParts as $quotePart) {
				if($quotePart[1] > 0) {
					$indent++;
					$indentString .= '>';
				}
				$quoteParts2 = preg_split('/<\/blockquote>/', $quotePart[0], -1, PREG_SPLIT_OFFSET_CAPTURE);
				
				foreach($quoteParts2 as $quotePart2) {
					if($quotePart2[1] > 0) {
						$indent--;
						$indentString = substr($indentString, 0, $indent);
					}

					$quoteParts3 = preg_split('/\r\n/', $quotePart2[0]);

					foreach($quoteParts3 as $quotePart3) {
						$quotePart3 = wordwrap($quotePart3, 75, "\r\n$indentString");
						$asciiText .= $indentString . $quotePart3 . "\r\n";
					}
				}
			}

			return $asciiText;
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
				return sprintf('"%s" <%s@%s>', $this->bofelamimail->decode_header($_addressObject->personal), $_addressObject->mailbox, $_addressObject->host);
			} elseif(!empty($_addressObject->mailbox) && !empty($_addressObject->host)) {
				return sprintf("%s@%s", $_addressObject->mailbox, $_addressObject->host);
			} else {
				return $_addressObject->mailbox;
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
		function getDraftData($_icServer, $_folder, $_uid) 
		{
			$this->sessionData['to'] = array();
			
			$bofelamimail =& CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$bofelamimail->openConnection();
			$bofelamimail->reopen($_folder);
			
			$userEMailAddresses = $this->preferences->getUserEMailAddresses();

			// get message headers for specified message
			#$headers	= $bofelamimail->getMessageHeader($_folder, $_uid);
			$headers	= $bofelamimail->getMessageEnvelope($_uid);

			$this->sessionData['uid'] = $_uid;
			$this->sessionData['messageFolder'] = $_folder;
			$this->sessionData['isDraft'] = true;

			foreach($headers['CC'] as $val) {
				if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
					continue;
				}

				if($userEMailAddresses[$val['EMAIL']]) {
					continue;
				}
						
				if(!$foundAddresses[$val['EMAIL']]) {
					$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
					$address = $this->bofelamimail->decode_header($address);
					$this->sessionData['cc'][] = $address;
					$foundAddresses[$val['EMAIL']] = true;
				}
			}
			
			foreach($headers['TO'] as $val) {
				if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
					continue;
				}

				if($userEMailAddresses[$val['EMAIL']]) {
					continue;
				}
						
				if(!$foundAddresses[$val['EMAIL']]) {
					$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
					$address = $this->bofelamimail->decode_header($address);
					$this->sessionData['to'][] = $address;
					$foundAddresses[$val['EMAIL']] = true;
				}
			}

			$this->sessionData['subject']	= $bofelamimail->decode_header($headers['SUBJECT']);

			$bodyParts = $bofelamimail->getMessageBody($_uid, $this->preferencesArray['always_display'], $_partID);
			#_debug_array($bodyParts);

			#$fromAddress = ($headers['FROM'][0]['PERSONAL_NAME'] != 'NIL') ? $headers['FROM'][0]['RFC822_EMAIL'] : $headers['FROM'][0]['EMAIL'];
			if($bodyParts['0']['mimeType'] == 'text/html') {
				$this->sessionData['mimeType'] 	= 'html';

				for($i=0; $i<count($bodyParts); $i++) {
					if($i>0) {
						$this->sessionData['body'] .= '<hr>';
					}
					if($bodyParts[$i]['mimeType'] == 'text/plain') {
						$bodyParts[$i]['body'] = nl2br($bodyParts[$i]['body']);
					}
					$this->sessionData['body'] .= $this->botranslation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
				}

			} else {
				$this->sessionData['mimeType']	= 'plain';
			
				for($i=0; $i<count($bodyParts); $i++) {
					if($i>0) {
						$this->sessionData['body'] .= "<hr>";
					}

					// add line breaks to $bodyParts
					$this->sessionData['body'] .= $this->botranslation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
				}
			}

			if($attachments = $bofelamimail->getMessageAttachments($_uid)) {
				foreach($attachments as $attachment) {
					$this->addMessageAttachment($_uid, $attachment['partID'], 
						$_folder, 
						$attachment['name'],
						$attachment['mimeType'],
						$attachment['size']);
				}
			}
			$bofelamimail->closeConnection();
			
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
		
		function getForwardData($_icServer, $_folder, $_uid, $_partID) 
		{
			$bofelamimail    =& CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$bofelamimail->openConnection();
			$bofelamimail->reopen($_folder);

			// get message headers for specified message
			$headers	= $bofelamimail->getMessageEnvelope($_uid, $_partID);

			#_debug_array($headers); exit;
			// check for Re: in subject header
			$this->sessionData['subject'] 	= "[FWD] " . $bofelamimail->decode_header($headers['SUBJECT']);
			$this->sessionData['mimeType']  = 'html';
			if($headers['SIZE'])
				$size				= $headers['SIZE'];
			else
				$size				= lang('unknown');

			$this->addMessageAttachment($_uid, $_partID, $_folder,
				$bofelamimail->decode_header($headers['SUBJECT']),
				'MESSAGE/RFC822', $size);
			
			$bofelamimail->closeConnection();
			
			$this->saveSessionData();
		}

		function getRandomString() {
			mt_srand((float) microtime() * 1000000);
			return md5(mt_rand (100000, 999999));
		}
		
		// $_mode can be:
		// single: for a reply to one address
		// all: for a reply to all
		function getReplyData($_mode, $_icServer, $_folder, $_uid, $_partID) 
		{
			$foundAddresses = array();
			
			$bofelamimail    =& CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$bofelamimail->openConnection();
			$bofelamimail->reopen($_folder);
			
			$userEMailAddresses = $this->preferences->getUserEMailAddresses();

			// get message headers for specified message
			#print "AAAA: $_folder, $_uid, $_partID<br>";
			$headers	= $bofelamimail->getMessageEnvelope($_uid, $_partID);
			#$headers	= $bofelamimail->getMessageHeader($_uid, $_partID);
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
						$address = $this->bofelamimail->decode_header($address);
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
						$address = $this->bofelamimail->decode_header($address);
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
							$address = $this->bofelamimail->decode_header($address);
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
							$address = $this->bofelamimail->decode_header($address);
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
							$address = $this->bofelamimail->decode_header($address);
							$this->sessionData['to'][] = $address;
							$foundAddresses[$val['EMAIL']] = true;
						}
					}
				#}
			}
			
			// check for Re: in subject header
			if(strtolower(substr(trim($bofelamimail->decode_header($headers['SUBJECT'])), 0, 3)) == "re:") {
				$this->sessionData['subject'] = $bofelamimail->decode_header($headers['SUBJECT']);
			} else {
				$this->sessionData['subject'] = "Re: " . $bofelamimail->decode_header($headers['SUBJECT']);
			}

			#_debug_array($headers);
			$bodyParts = $bofelamimail->getMessageBody($_uid, $this->preferencesArray['always_display'], $_partID);
			#_debug_array($bodyParts);

			$fromAddress = ($headers['FROM'][0]['PERSONAL_NAME'] != 'NIL') ? $headers['FROM'][0]['RFC822_EMAIL'] : $headers['FROM'][0]['EMAIL'];
			if($bodyParts['0']['mimeType'] == 'text/html') {
				$this->sessionData['body']	= @htmlspecialchars($bofelamimail->decode_header($fromAddress), ENT_QUOTES) . " ".lang("wrote").":" .'<br>';
				$this->sessionData['mimeType'] 	= 'html';
				$this->sessionData['body']	.= '<blockquote type="cite">';

				for($i=0; $i<count($bodyParts); $i++) {
					if($i>0) {
						$this->sessionData['body'] .= '<hr>';
					}
					if($bodyParts[$i]['mimeType'] == 'text/plain') {
						$bodyParts[$i]['body'] = nl2br($bodyParts[$i]['body']);
					}
					$this->sessionData['body'] .= $this->botranslation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
				}

				$this->sessionData['body']	.= '</blockquote><br>';
			} else {
				$this->sessionData['body']	= @htmlspecialchars($bofelamimail->decode_header($fromAddress), ENT_QUOTES) . " ".lang("wrote").":\r\n";
				$this->sessionData['mimeType']	= 'plain';
			
				for($i=0; $i<count($bodyParts); $i++) {
					if($i>0) {
						$this->sessionData['body'] .= "<hr>";
					}

					// add line breaks to $bodyParts
					$newBody	= $this->botranslation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
					$newBody        = explode("\n",$newBody);

					// create body new, with good line breaks and indention
					foreach($newBody as $value) {
						// the explode is removing the character
						$value .= "\n";
						
						$numberOfChars = strspn(trim($value), ">");
						$appendString = str_repeat('>', $numberOfChars + 1);
						
						$bodyAppend = $this->bofelamimail->wordwrap($value, 80, "\r\n$appendString ");
						
						if($bodyAppend[0] == '>') {
							$bodyAppend = '>'. $bodyAppend;
						} else {
							$bodyAppend = '> '. $bodyAppend;
						}
						
						$this->sessionData['body'] .= $bodyAppend;
					}
				}
			}
			
			$bofelamimail->closeConnection();
			
			$this->saveSessionData();
		}
		
		function getSessionData()
		{
			return $this->sessionData;
		}

		// get the user name, will will use for the FROM field
		function getUserName()
		{
			$retData = sprintf("%s <%s>",$this->preferences['realname'],$this->preferences['emailAddress']);
			return $retData;
		}
		
		function removeAttachment($_attachmentID) {
			unlink($this->sessionData['attachments'][$_attachmentID]['file']);
			unset($this->sessionData['attachments'][$_attachmentID]);

			$this->saveSessionData();
		}
		
		function restoreSessionData() 
		{
			$this->sessionData = $GLOBALS['egw']->session->appsession('compose_session_data_'.$this->composeID);
		}
		
		function saveSessionData() 
		{
			$GLOBALS['egw']->session->appsession('compose_session_data_'.$this->composeID,'',$this->sessionData);
		}

		function createMessage(&$_mailObject, $_formData, $_identity, $_signature = false) 
		{
			$bofelamimail	=& CreateObject('felamimail.bofelamimail',$this->displayCharset);

			$userLang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
			$langFile = EGW_SERVER_ROOT."/phpgwapi/setup/phpmailer.lang-$userLang.php";
			if(file_exists($langFile)) {
				$_mailObject->SetLanguage($userLang, EGW_SERVER_ROOT."/phpgwapi/setup/");
			} else {
				$_mailObject->SetLanguage("en", EGW_SERVER_ROOT."/phpgwapi/setup/");
			}
			$_mailObject->PluginDir = EGW_SERVER_ROOT."/phpgwapi/inc/";

			$_mailObject->IsSMTP();
			$_mailObject->CharSet	= $this->displayCharset;
			$_mailObject->From 	= $_identity->emailAddress;
			$_mailObject->FromName = $_mailObject->EncodeHeader($_identity->realName);
			$_mailObject->Priority = $_formData['priority'];
			$_mailObject->Encoding = 'quoted-printable';
			$_mailObject->AddCustomHeader('X-Mailer: FeLaMiMail');
			if(isset($this->sessionData['in-reply-to'])) {
				$_mailObject->AddCustomHeader('In-Reply-To: '. $this->sessionData['in-reply-to']);
			}
			if($_formData['disposition']) {
				$_mailObject->AddCustomHeader('Disposition-Notification-To: '. $_identity->emailAddress);
			}
			if(!empty($_identity->organization)) {
				#$_mailObject->AddCustomHeader('Organization: '. $bofelamimail->encodeHeader($_identity->organization, 'q'));
				$_mailObject->AddCustomHeader('Organization: '. $_identity->organization);
			}

			foreach((array)$_formData['to'] as $address) {
				$address_array	= imap_rfc822_parse_adrlist($address, '');
				foreach((array)$address_array as $addressObject) {
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					#$emailName = $bofelamimail->encodeHeader($addressObject->personal, 'q');
					#$_mailObject->AddAddress($emailAddress, $emailName);
					$_mailObject->AddAddress($emailAddress, $addressObject->personal);
				}
			}

			foreach((array)$_formData['cc'] as $address) {
				$address_array	= imap_rfc822_parse_adrlist($address,'');
				foreach((array)$address_array as $addressObject) {
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					#$emailName = $bofelamimail->encodeHeader($addressObject->personal, 'q');
					#$_mailObject->AddCC($emailAddress, $emailName);
					$_mailObject->AddCC($emailAddress, $addressObject->personal);
				}
			}
			
			foreach((array)$_formData['bcc'] as $address) {
				$address_array	= imap_rfc822_parse_adrlist($address,'');
				foreach((array)$address_array as $addressObject) {
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					#$emailName = $bofelamimail->encodeHeader($addressObject->personal, 'q');
					#$_mailObject->AddBCC($emailAddress, $emailName);
					$_mailObject->AddBCC($emailAddress, $addressObject->personal);
				}
			}
			
			if (!empty($_formData['reply_to'])) {
				$address_array	= imap_rfc822_parse_adrlist($this->sessionData['reply_to'],'');
				if(count($address_array)>0) {
					$emailAddress = $address_array[0]->mailbox."@".$address_array[0]->host;
					#$emailName = $bofelamimail->encodeHeader($address_array[0]->personal, 'q');
					#$_mailObject->AddReplyTo($emailAddress, $emailName);
					$_mailObject->AddReplyTo($emailAddress, $address_array[0]->personal);
				}
			}
			
			$_mailObject->WordWrap = 76;
			#$_mailObject->Subject = $bofelamimail->encodeHeader($_formData['subject'], 'q');
			$_mailObject->Subject = $_formData['subject'];
			if($_formData['mimeType'] =='html') {
				$_mailObject->IsHTML(true);
				if(!empty($_signature['signature'])) {
					#$_mailObject->Body    = array($_formData['body'], $_signature['signature']);
					$_mailObject->Body    = $_formData['body'] .'<hr style="border:dotted 1px silver; width:90%; border:dotted 1px silver;">'. $_signature['signature'];
					$_mailObject->AltBody = $this->convertHTMLToText($_formData['body']).
						"\r\n--\r\n". 
						$this->convertHTMLToText($_signature['signature']);
					#print "<pre>$_mailObject->AltBody</pre>";
					#print htmlentities($_signature['signature']);
				} else {
					$_mailObject->Body	= $_formData['body'];
					$_mailObject->AltBody	= $this->convertHTMLToText($_formData['body']);
				}
			} else {
				$_mailObject->IsHTML(false);
				$_mailObject->Body = $this->convertHTMLToText($_formData['body']);
				if(!empty($_signature['signature'])) {
					$_mailObject->Body .= "\r\n--\r\n". $this->convertHTMLToText($_signature['signature']);
				}
			}
			
		#	if (!empty($_formData['signature'])) {
		#		$_mailObject->Body	.= "\r\n-- \r\n";
		#		$_mailObject->Body	.= $_formData['signature'];
		#	}

			// add the attachments
			foreach((array)$this->sessionData['attachments'] as $attachment) {
				if(!empty($attachment['uid']) && !empty($attachment['folder'])) {
					switch($attachment['type']) {
						case 'MESSAGE/RFC822':
							$bofelamimail->openConnection();
							$bofelamimail->reopen($attachment['folder']);
							$rawBody	= $bofelamimail->getMessageRawBody($attachment['uid'], $attachment['partID']);
							$bofelamimail->closeConnection();

							$_mailObject->AddStringAttachment($rawBody, $attachment['name'], '7bit', 'message/rfc822');
			
							break;
							
						default:
							$bofelamimail->openConnection();
							$bofelamimail->reopen($attachment['folder']);
							$attachmentData	= $bofelamimail->getAttachment($attachment['uid'], $attachment['partID']);
							$bofelamimail->closeConnection();

							$_mailObject->AddStringAttachment($attachmentData['attachment'], $attachment['name'], 'base64', $attachment['type']);
			
							break;
							
					}
				} else {
					$_mailObject->AddAttachment (
						$attachment['file'],
						$attachment['name'],
						'base64',
						$attachment['type']
					);
				}
			}
		}

		function saveAsDraft($_formData)
		{
			$bofelamimail	=& CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$mail		=& CreateObject('phpgwapi.phpmailer');
			$identity	= $this->preferences->getIdentity((int)$this->sessionData['identity']);
			$flags = '\\Seen \\Draft';
			
			$this->createMessage($mail, $_formData, $identity);
			
			$bofelamimail->openConnection();
			$bofelamimail->appendMessage($this->preferencesArray['draftFolder'],
				$mail->getMessageHeader(),
				$mail->getMessageBody(),
				$flags);
			$bofelamimail->closeConnection();
		}

		function send($_formData)
		{
			$bofelamimail	=& CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$mail 		=& CreateObject('phpgwapi.phpmailer');
			$messageIsDraft	=  false;

			
			$this->sessionData['identity']	= $_formData['identity'];
			$this->sessionData['to']	= $_formData['to'];
			$this->sessionData['cc']	= $_formData['cc'];
			$this->sessionData['bcc']	= $_formData['bcc'];
			$this->sessionData['folder']	= $_formData['folder'];
			$this->sessionData['reply_to']	= trim($_formData['reply_to']);
			$this->sessionData['subject']	= trim($_formData['subject']);
			$this->sessionData['body']	= $_formData['body'];
			$this->sessionData['priority']	= $_formData['priority'];
			$this->sessionData['signatureID'] = $_formData['signatureID'];
			$this->sessionData['disposition'] = $_formData['disposition'];
			$this->sessionData['mimeType']	= $_formData['mimeType'];
			$this->sessionData['to_infolog'] = $_formData['to_infolog'];
			
			if(empty($this->sessionData['to']) && empty($this->sessionData['cc']) && 
			   empty($this->sessionData['bcc']) && empty($this->sessionData['folder'])) {
			   	$messageIsDraft = true;
			}

			$identity = $this->preferences->getIdentity((int)$this->sessionData['identity']);
			$signature = $this->bopreferences->getSignature((int)$this->sessionData['signatureID']);
			
			// create the messages
			$this->createMessage($mail, $_formData, $identity, $signature);
			#print "<pre>". $mail->getMessageHeader() ."</pre><hr><br>";
			#print "<pre>". $mail->getMessageBody() ."</pre><hr><br>";
			#exit;
			                                                                

			$ogServer = $this->preferences->getOutgoingServer(0);

			$mail->Host 	= $ogServer->host;
			$mail->Port	= $ogServer->port;

			// SMTP Auth??
			if($ogServer->smtpAuth) {
				$mail->SMTPAuth	= true;
				$mail->Username	= $ogServer->username;
				$mail->Password	= $ogServer->password;
			}

			// set a higher timeout for big messages
			@set_time_limit(120);
			#$mail->SMTPDebug = 10;
			if(count((array)$this->sessionData['to']) > 0 || count((array)$this->sessionData['cc']) > 0 || count((array)$this->sessionData['bcc']) > 0) {
				if(!$mail->Send()) {
					$this->errorInfo = $mail->ErrorInfo;
					return false;
				}
			}

			$folder = (array)$this->sessionData['folder'];
			if(isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['sentFolder']) && 
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['sentFolder'] != 'none' &&
				$messageIsDraft == false) {
				$folder[] = $GLOBALS['egw_info']['user']['preferences']['felamimail']['sentFolder'];
			}
			if($messageIsDraft == true) {
			   	if(!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['draftFolder'])) {
				   	$this->sessionData['folder'] = array($GLOBALS['egw_info']['user']['preferences']['felamimail']['draftFolder']);
			   	}
			}
			$folder = array_unique($folder);

			if (count($folder) > 0) {
				$bofelamimail =& CreateObject('felamimail.bofelamimail');
				$bofelamimail->openConnection();
				foreach($folder as $folderName) {
					if($folderName == $GLOBALS['egw_info']['user']['preferences']['felamimail']['sentFolder']) {
						$flags = '\\Seen';
					} elseif($folderName == $GLOBALS['egw_info']['user']['preferences']['felamimail']['draftFolder']) {
						$flags = '\\Draft';
					} else {
						$flags = '';
					}
					$bofelamimail->appendMessage($folderName,
								$mail->getMessageHeader(),
								$mail->getMessageBody(),
								$flags);
				}
				$bofelamimail->closeConnection();
			}

			if(isset($this->sessionData['uid']) && isset($this->sessionData['messageFolder'])) {
				// mark message as answered
				$bofelamimail =& CreateObject('felamimail.bofelamimail');
				$bofelamimail->openConnection();
				$bofelamimail->reopen($this->sessionData['messageFolder']);
				if($this->sessionData['messageFolder'] == $GLOBALS['egw_info']['user']['preferences']['felamimail']['draftFolder']) {
					$bofelamimail->deleteMessages(array($this->sessionData['uid']));
				} else {
					$bofelamimail->flagMessages("answered", array($this->sessionData['uid']));
				}
				$bofelamimail->closeConnection();
			}

			// attension: we dont return from infolog. cleanups will be done there.
			if ($_formData['to_infolog'] == 'on') {
				$uiinfolog =& CreateObject('infolog.uiinfolog');
				$uiinfolog->import_mail(
					$this->sessionData['to'],
					$this->sessionData['subject'],
					$this->convertHTMLToText($this->sessionData['body']),
					$this->sessionData['attachments']
				);
			}
			
			if(is_array($this->sessionData['attachments'])) {
				reset($this->sessionData['attachments']);
				while(list($key,$value) = @each($this->sessionData['attachments'])) {
					#print "$key: ".$value['file']."<br>";
					if (!empty($value['file'])) {	// happens when forwarding mails
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
			if($signatureData = $this->bopreferences->getDefaultSignature()) {
				$this->sessionData['signatureID'] = $signatureData['signatureid'];
			} else {
				$this->sessionData['signatureID'] = -1;
			}
			#$this->sessionData['signature']	= $GLOBALS['egw']->preferences->parse_notify(
			#	$GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig']
			#);
			$this->sessionData['mimeType']	= 'html';
			
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
}
