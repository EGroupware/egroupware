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

	/**
	* the core logic of FeLaMiMail
	*
	* This class contains all logic of FeLaMiMail.
	* @package FeLaMiMail
	* @author Lars Kneschke
	* @version 1.35
	* @copyright Lars Kneschke 2002,2003,2004
	* @license http://opensource.org/licenses/gpl-license.php GPL
	*/
	class bofelamimail
	{
		var $public_functions = array
		(
			'flagMessages'		=> True
		);

		var $mbox;		// the mailbox identifier any function should use

		// define some constants
		// message types
		var $type = array("text", "multipart", "message", "application", "audio", "image", "video", "other");
		
		// message encodings
		var $encoding = array("7bit", "8bit", "binary", "base64", "quoted-printable", "other");
		
		// set to true, if php is compiled with multi byte string support
		var $mbAvailable = FALSE;

		// what type of mimeTypes do we want from the body(text/html, text/plain)
		var $htmlOptions;

		function bofelamimail($_displayCharset='iso-8859-1')
		{
			$this->restoreSessionData();
			
			$this->foldername	= $this->sessionData['mailbox'];
			$this->accountid	= $GLOBALS['phpgw_info']['user']['account_id'];
			
			$this->bopreferences	= CreateObject('felamimail.bopreferences');
			$this->sofelamimail	= CreateObject('felamimail.sofelamimail');
			$this->botranslation	= CreateObject('phpgwapi.translation');
			
			$this->mailPreferences	= $this->bopreferences->getPreferences();
			$this->imapBaseDir	= '';
			
			$this->displayCharset	= $_displayCharset;
			
			// set some defaults
			if(count($this->sessionData) == 0)
			{
				// this should be under user preferences
				// sessionData empty
				// no filter active
				$this->sessionData['activeFilter']	= "-1";
				// default mailbox INBOX
				$this->sessionData['mailbox']		= "INBOX";
				// default start message
				$this->sessionData['startMessage']	= 1;
				// default mailbox for preferences pages
				$this->sessionData['preferences']['mailbox']	= "INBOX";
				// default sorting
				$this->sessionData['sort']	= $this->mailPreferences['sortOrder'];
				$this->saveSessionData();
			}
			
			if (function_exists('mb_convert_encoding')) $this->mbAvailable = TRUE;
			
			$this->htmlOptions 	= $this->mailPreferences['htmlOptions'];
			
			$config = CreateObject('phpgwapi.config','felamimail');
			$config->read_repository();
			$this->profileID = $config->config_data['profileID'];
			
		}
		
		/**
		* hook to add account
		*
		* this function is a wrapper function for emailadmin
		*
		* @param _hookValues contains the hook values as array
		* @returns nothing
		*/
		function addAccount($_hookValues)
		{
			if($this->profileID > 0 && is_numeric($this->profileID))
			{
				ExecMethod('emailadmin.bo.addAccount',$_hookValues,3,$this->profileID);
			}
		}
		
		function adminMenu()
		{
 			if ($GLOBALS['phpgw_info']['server']['account_repository'] == "ldap")
			{
    		        	$data = Array
		        	(
					'description'   => 'email settings',
					'url'           => '/index.php',
					'extradata'     => 'menuaction=emailadmin.uiuserdata.editUserData'
				);
			
				//Do not modify below this line
				global $menuData;
			
				$menuData[] = $data;
			}
		}
		
		function appendMessage($_folderName, $_header, $_body, $_flags)
		{
			#print "<pre>$_header.$_body</pre>";
			$mailboxString = ExecMethod('emailadmin.bo.getMailboxString',$_folderName,3,$this->profileID);
			$header = str_replace("\n","\r\n",$_header);
			$body   = str_replace("\n","\r\n",$_body);
			$result = @imap_append($this->mbox, $mailboxString, "$header"."$body", $_flags);
			#print imap_last_error();
			return $result;
		}
		
		function closeConnection()
		{
			if(is_resource($this->mbox))
			{
				imap_close($this->mbox);
			}
		}
		
		// creates the mailbox string needed for the various imap functions
/*		function createMailboxString($_folderName='')
		{
			$mailboxString = sprintf("{%s:%s%s/notls}%s",
				$this->mailPreferences['imapServerAddress'],
				$this->mailPreferences['imapPort'],
				$this->mailPreferences['imapOptions'],
				$_folderName);

			return $this->encodeFolderName($mailboxString);
		}*/
		
		function compressFolder()
		{
			$prefs	= $this->bopreferences->getPreferences();

			$deleteOptions	= $prefs['deleteOptions'];
			$trashFolder	= $prefs['trash_folder'];
			
			if($this->sessionData['mailbox'] == $trashFolder && $deleteOptions == "move_to_trash")
			{
				// delete all messages in the trash folder
				$mailboxString = ExecMethod('emailadmin.bo.getMailboxString',$this->sessionData['mailbox'],3,$this->profileID);
				$status = imap_status ($this->mbox, $mailboxString, SA_ALL);
				$numberOfMessages = $status->messages;
				$msgList = "1:$numberOfMessages";
				imap_delete($this->mbox, $msgList);
				imap_expunge($this->mbox);
			}
			elseif($deleteOptions == "mark_as_deleted")
			{
				// delete all messages in the current folder which have the deleted flag set 
				imap_expunge($this->mbox);
			}
		}
		
		function decodeFolderName($_folderName)
		{
			if($this->mbAvailable)
			{
				return mb_convert_encoding( $_folderName, $this->displayCharset, "UTF7-IMAP");
			}
			
			// if not
			return @imap_utf7_decode($_folderName);
		}

		function decode_header($string)
		{
			#print "decode header: $string<br><br>";
			$newString = '';
			$elements=imap_mime_header_decode($string);
			for($i=0;$i<count($elements);$i++) 
			{
				#echo "Charset: {$elements[$i]->charset}<br>";
				#echo "Text: {$elements[$i]->text}<BR><BR>";
				if ($elements[$i]->charset == 'default')
					$elements[$i]->charset = 'iso-8859-1';
				$tempString = $this->botranslation->convert($elements[$i]->text,$elements[$i]->charset);
				$newString .= $tempString;
			}
			return $newString;
		}
		
		function deleteAccount($_hookValues)
		{
			if($this->profileID > 0 && is_numeric($this->profileID))
			{
				ExecMethod('emailadmin.bo.deleteAccount',$_hookValues,3,$this->profileID);
			}
		}
		
		function deleteMessages($_messageUID)
		{
			$caching = CreateObject('felamimail.bocaching',
					$this->mailPreferences['imapServerAddress'],
					$this->mailPreferences['username'],
					$this->sessionData['mailbox']);

			reset($_messageUID);
			while(list($key, $value) = each($_messageUID))
			{
				if(!empty($msglist)) $msglist .= ",";
				$msglist .= $value;
			}

			$prefs	= $this->bopreferences->getPreferences();

			$deleteOptions	= $prefs['deleteOptions'];
			$trashFolder	= $prefs['trash_folder'];

			if($this->sessionData['mailbox'] == $trashFolder && $deleteOptions == "move_to_trash")
			{
				$deleteOptions = "remove_immediately";
			}

			switch($deleteOptions)
			{
				case "move_to_trash":
					if(!empty($trashFolder))
					{
						if (imap_mail_move ($this->mbox, $msglist, $this->encodeFolderName($trashFolder), CP_UID))
						{
							imap_expunge($this->mbox);
							reset($_messageUID);
							while(list($key, $value) = each($_messageUID))
							{
								$caching->removeFromCache($value);
							}
						}
						else
						{
							print imap_last_error()."<br>";
						}
					}
					break;

				case "mark_as_deleted":
					imap_delete($this->mbox, $msglist, FT_UID);
					break;

				case "remove_immediately":
					imap_delete($this->mbox, $msglist, FT_UID);
					imap_expunge ($this->mbox);
					reset($_messageUID);
					while(list($key, $value) = each($_messageUID))
					{
						$caching->removeFromCache($value);
					}
					break;
			}
		}
		
		function encodeFolderName($_folderName)
		{
			if($this->mbAvailable)
			{
				return mb_convert_encoding( $_folderName, "UTF7-IMAP", $this->displayCharset );
			}
			
			// if not
			return imap_utf7_encode($_folderName);
		}

		function encodeHeader($_string, $_encoding='q')
		{
			switch($_encoding)
			{
				case "q":
					if(!preg_match("/[\x80-\xFF]/",$_string))
					{
						// nothing to quote, only 7 bit ascii
						return $_string;
					}
					
					$string = imap_8bit($_string);
					$stringParts = explode("=\r\n",$string);
					while(list($key,$value) = each($stringParts))
					{
						if(!empty($retString)) $retString .= " ";
						$value = str_replace(" ","_",$value);
						// imap_8bit does not convert "?"
						// it does not need, but it should
						$value = str_replace("?","=3F",$value);
						$retString .= "=?".strtoupper($this->displayCharset)."?Q?".$value."?=";
					}
					#exit;
					return $retString;
					break;
				default:
					return $_string;
			}
		}
		function flagMessages($_flag, $_messageUID)
		{
			reset($_messageUID);
			while(list($key, $value) = each($_messageUID))
			{
				if(!empty($msglist)) $msglist .= ",";
				$msglist .= $value;
			}

			switch($_flag)
			{
				case "flagged":
					$result = imap_setflag_full ($this->mbox, $msglist, "\\Flagged", ST_UID);
					break;
				case "read":
					$result = imap_setflag_full ($this->mbox, $msglist, "\\Seen", ST_UID);
					break;
				case "answered":
					$result = imap_setflag_full ($this->mbox, $msglist, "\\Answered", ST_UID);
					break;
				case "unflagged":
					$result = imap_clearflag_full ($this->mbox, $msglist, "\\Flagged", ST_UID);
					break;
				case "unread":
					$result = imap_clearflag_full ($this->mbox, $msglist, "\\Seen", ST_UID);
					$result = imap_clearflag_full ($this->mbox, $msglist, "\\Answered", ST_UID);
					break;
			}
			
			
			#print "Result: $result<br>";
		}
		
		// this function is based on a on "Building A PHP-Based Mail Client"
		// http://www.devshed.com
		// fetch a specific attachment from a message
		function getAttachment($_uid, $_partID)
		{
			// parse message structure
			$structure = imap_fetchstructure($this->mbox, $_uid, FT_UID);
			$sections = array();
			$this->parseMessage($sections, $structure, $_partID);
			
			#_debug_array($sections);
			
			$type 		= $sections[$_partID]["mimeType"];
			$encoding 	= $sections[$_partID]["encoding"];
			$filename 	= $sections[$_partID]["name"];
			
			$attachment = imap_fetchbody($this->mbox, $_uid, $_partID, FT_UID);
			
			switch ($encoding) 
			{
				case ENCBASE64:
					// use imap_base64 to decode
					$attachment = imap_base64($attachment);
					break;
				case ENCQUOTEDPRINTABLE:
					// use imap_qprint to decode
					$attachment = imap_qprint($attachment);
					break;
				case ENCOTHER:
					// not sure if this needs decoding at all
					break;
				default:
					// it is either not encoded or we don't know about it
			}
			
			return array(
				'type'	=> $type,
				'encoding'	=> $encoding,
				'filename'	=> $filename,
				'attachment'	=> $attachment
				);
		}
		
		function getEMailProfile()
		{
			$config = CreateObject('phpgwapi.config','felamimail');
			$config->read_repository();
			$felamimailConfig = $config->config_data;
			
			#_debug_array($felamimailConfig);
			
			if(!isset($felamimailConfig['profileID']))
			{
				return -1;
			}
			else
			{
				return intval($felamimailConfig['profileID']);
			}
		}

		function getFolderStatus($_folderName)
		{
			// now we have the keys as values
			$mailboxString = ExecMethod('emailadmin.bo.getMailboxString',$_folderName,3,$this->profileID);
			$subscribedFolders = $this->getFolderList(true);
			#print_r($subscribedFolders);
			#print $subscribedFolders[$_folderName]." - $_folderName<br>";
			if(isset($subscribedFolders[$_folderName]))
			{
				$retValue['subscribed']	= true;
			}
			else
			{
				$retValue['subscribed'] = false;
			}
			
			// get the current IMAP counters
			$folderStatus = imap_status($this->mbox,$mailboxString,SA_ALL);
			
			// merge a array and object to a array
			$retValue = array_merge($retValue,$folderStatus);
			
			return $retValue;
		}
		
		function getFolderList($_subscribedOnly=false)
		{
			$folders = array();
			if(!is_resource($this->mbox))
			{ 
				return $folders;
			}

			$mailboxString = ExecMethod('emailadmin.bo.getMailboxString',$this->imapBaseDir,3,$this->profileID);
			
		
			if($_subscribedOnly == 'true')
			{
				$list = imap_getsubscribed($this->mbox,$mailboxString,"*");
			}
			else
			{
				$list = imap_getmailboxes($this->mbox,$mailboxString,"*");
			}

			if(is_array($list))
			{
				#_debug_array($list);
				reset($list);
				$folders = array();
				while (list($key, $val) = each($list))
				{
					// remove the {host:port/imap/...} part
					$folderNameIMAP = $this->decodeFolderName(preg_replace("/{.*}/",'',$val->name));
					$folderParts = explode(".",$folderNameIMAP);
					reset($folderParts);
					$displayName = "";
					#print_r($folderParts);print"<br>";
					for($i=0; $i<count($folderParts); $i++)
					{
						if($i+1 == count($folderParts))
						{
							$displayName .= $folderParts[$i];
						}
						else
						{
							$displayName .= ". . ";
						}
					}
					$folders["$folderNameIMAP"] = $displayName;
				}
				#exit;
				ksort($folders,SORT_STRING);
				// return always the inbox
				$folders = array_merge(array('INBOX' => 'INBOX'),$folders);
				reset($folders);
				return $folders;
			}
			else
			{
				if($_subscribedOnly == 'true' && 
					is_array(imap_list($this->mbox,$mailboxString,'INBOX')))
				{
					$folders['INBOX'] = 'INBOX';
				}
				return $folders;
			}
		}
		
		function getHeaders($_startMessage, $_numberOfMessages, $_sort)
		{

			#printf ("this->bofelamimail->getHeaders start: %s<br>",date("H:i:s",mktime()));

			$caching = CreateObject('felamimail.bocaching',
					$this->mailPreferences['imapServerAddress'],
					$this->mailPreferences['username'],
					$this->sessionData['mailbox']);
			$bofilter = CreateObject('felamimail.bofilter');
			$transformdate = CreateObject('felamimail.transformdate');

			$mailboxString = ExecMethod('emailadmin.bo.getMailboxString',$this->sessionData['mailbox'],3,$this->profileID);
			$status = imap_status ($this->mbox, $mailboxString, SA_ALL);
			$cachedStatus = $caching->getImapStatus();

			// no data chached already?
			// get all message informations from the imap server for this folder
			if ($cachedStatus['uidnext'] == 0)
			{
				#print "nix gecached!!<br>";
				#print "current UIDnext :".$cachedStatus['uidnext']."<br>";
				#print "new UIDnext :".$status->uidnext."<br>";
				for($i=1; $i<=$status->messages; $i++)
				{
					@set_time_limit();
					$messageData['uid'] = imap_uid($this->mbox, $i);
					$header = imap_headerinfo($this->mbox, $i);
					// parse structure to see if attachments exist
					// display icon if so
					$structure = imap_fetchstructure($this->mbox, $i);
					$sections = array();
					$this->parseMessage($sections, $structure);
					
					$messageData['date']		= $header->udate;
					$messageData['subject']		= $header->subject;
					$messageData['to_name']		= $header->to[0]->personal;
					$messageData['to_address']	= $header->to[0]->mailbox."@".$header->to[0]->host;
					$messageData['sender_name']	= $header->from[0]->personal;
					$messageData['sender_address']	= $header->from[0]->mailbox."@".$header->from[0]->host;
					$messageData['size']		= $header->Size;
					
					$messageData['attachments']     = "false";
					foreach($sections as $key => $value)
					{
						if($value['type'] == 'attachment')
						{
							$messageData['attachments']	= "true";
							break;
						}
					}
					
					// maybe it's already in the database
					// lets remove it, sometimes the database gets out of sync
					$caching->removeFromCache($messageData['uid']);
					
					$caching->addToCache($messageData);
					
					unset($messageData);
				}

				$caching->updateImapStatus($status);
			}
			// update cache, but only add new emails
			elseif($status->uidnext != $cachedStatus['uidnext'])
			{
				#print "found new messages<br>";
				#print "new uidnext: ".$status->uidnext." old uidnext: ".$cachedStatus['uidnext']."<br>";
				$uidRange = $cachedStatus['uidnext'].":".$status->uidnext;
				#print "$uidRange<br>";
				$newHeaders = imap_fetch_overview($this->mbox,$uidRange,FT_UID);
				$countNewHeaders = count($newHeaders);
				for($i=0; $i<$countNewHeaders; $i++)
				{
					$messageData['uid'] = $newHeaders[$i]->uid;
					$header = imap_headerinfo($this->mbox, $newHeaders[$i]->msgno);
					// parse structure to see if attachments exist
					// display icon if so
					$structure = imap_fetchstructure($this->mbox, $newHeaders[$i]->msgno);
					$sections = array();
					$this->parseMessage($sections, $structure);
				
					$messageData['date'] 		= $header->udate;
					$messageData['subject'] 	= $header->subject;
					$messageData['to_name']		= $header->to[0]->personal;
					$messageData['to_address']	= $header->to[0]->mailbox."@".$header->to[0]->host;
					$messageData['sender_name'] 	= $header->from[0]->personal;
					$messageData['sender_address'] 	= $header->from[0]->mailbox."@".$header->from[0]->host;
					$messageData['size'] 		= $header->Size;

					$messageData['attachments']     = "false";
					foreach($sections as $key => $value)
					{
						if($value['type'] == 'attachment')
						{
							$messageData['attachments']	= "true";
							break;
						}
					}
					
					// maybe it's already in the database
					// lets remove it, sometimes the database gets out of sync
					$caching->removeFromCache($messageData['uid']);
					
					$caching->addToCache($messageData);
					
					unset($messageData);
				}
				$caching->updateImapStatus($status);
			}

			// now let's do some clean up
			// if we have more messages in the cache then in the imap box, some external 
			// imap client deleted some messages. It's better to erase the messages from the cache.
			$displayHeaders = $caching->getHeaders();
			if (count($displayHeaders) > $status->messages)
			{
				$messagesToRemove = count($displayHeaders) - $status->messages;
				reset($displayHeaders);
				for($i=0; $i<count($displayHeaders); $i++)
				{
					$header = imap_fetch_overview($this->mbox,$displayHeaders[$i]['uid'],FT_UID);
					if (count($header[0]) == 0)
					{
						$caching->removeFromCache($displayHeaders[$i]['uid']);
						$removedMessages++;
					}
					if ($removedMessages == $messagesToRemove) break;
				}
			}

			// now lets gets the important messages
			$filterList = $bofilter->getFilterList();
			$activeFilter = $bofilter->getActiveFilter();
			$filter = $filterList[$activeFilter];
			$displayHeaders = $caching->getHeaders($_startMessage, $_numberOfMessages, $_sort, $filter);

			$count=0;
			$countDisplayHeaders = count($displayHeaders);
			for ($i=0;$i<$countDisplayHeaders;$i++)
			{
				$header = imap_fetch_overview($this->mbox,$displayHeaders[$i]['uid'],FT_UID);
				#print $header[0]->date;print "<br>";
				#print_r($displayHeaders[$i]);print "<br>";
				#print_r($header);exit;

				#$rawHeader = imap_fetchheader($this->mbox,$displayHeaders[$i]['uid'],FT_UID);
				#$headers = $this->sofelamimail->fetchheader($rawHeader);
				
				$retValue['header'][$count]['subject'] 		= $this->decode_header($header[0]->subject);
				$retValue['header'][$count]['sender_name'] 	= $this->decode_header($displayHeaders[$i]['sender_name']);
				$retValue['header'][$count]['sender_address'] 	= $this->decode_header($displayHeaders[$i]['sender_address']);
				$retValue['header'][$count]['to_name'] 		= $this->decode_header($displayHeaders[$i]['to_name']);
				$retValue['header'][$count]['to_address'] 	= $this->decode_header($displayHeaders[$i]['to_address']);
				$retValue['header'][$count]['attachments']	= $displayHeaders[$i]['attachments'];
				$retValue['header'][$count]['size'] 		= $header[0]->size;

				$timestamp = $displayHeaders[$i]['date'];
				$timestamp7DaysAgo = 
					mktime(date("H"), date("i"), date("s"), date("m"), date("d")-7, date("Y"));
				$timestampNow = 
					mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"));
				// date from the future
				if($timestamp > $timestampNow)
				{
					$retValue['header'][$count]['date'] = date("Y-m-d",$timestamp);
				}
				// email from today, show only time
				elseif (date("Y-m-d") == date("Y-m-d",$timestamp))
				{
					$retValue['header'][$count]['date'] = date("H:i:s",$timestamp);
				}
				// email from the last 7 days, show only weekday
				elseif($timestamp7DaysAgo < $timestamp)
				{
					$retValue['header'][$count]['date'] = lang(date("l",$timestamp));
					#$retValue['header'][$count]['date'] = date("Y-m-d H:i:s",$timestamp7DaysAgo)." - ".date("Y-m-d",$timestamp);
					$retValue['header'][$count]['date'] = date("H:i:s",$timestamp)."(".lang(date("D",$timestamp)).")";
				}
				else
				{
					$retValue['header'][$count]['date'] = date("Y-m-d",$timestamp);
				}
				$retValue['header'][$count]['id'] = $header[0]->msgno;
				$retValue['header'][$count]['uid'] = $displayHeaders[$i]['uid'];
				$retValue['header'][$count]['recent'] = $header[0]->recent;
				$retValue['header'][$count]['flagged'] = $header[0]->flagged;
				$retValue['header'][$count]['answered'] = $header[0]->answered;
				$retValue['header'][$count]['deleted'] = $header[0]->deleted;
				$retValue['header'][$count]['seen'] = $header[0]->seen;
				$retValue['header'][$count]['draft'] = $header[0]->draft;
				
				$count++;
			}

			#printf ("this->bofelamimail->getHeaders done: %s<br>",date("H:i:s",mktime()));

			if(is_array($retValue['header']))
			{
				#_debug_array($retValue['header']);
				$retValue['info']['total']	= $caching->getMessageCounter($filter);
				$retValue['info']['first']	= $_startMessage;
				$retValue['info']['last']	= $_startMessage + $count - 1 ;
				return $retValue;
			}
			else
			{
				return 0;
			}
		}
		
		function getMailPreferences()
		{
			return $this->mailPreferences;
		}
		
		function getMessageAttachments($_uid, $_partID='')
		{
			$structure = imap_fetchstructure($this->mbox, $_uid, FT_UID);
			$sections = array();
			$this->parseMessage($sections, $structure, $_partID);
			#if(isset($sections['attachment']) && is_array($sections['attachment']))
			#{
			#	#_debug_array($structure['attachment']);
			#	return $sections['attachment'];
			#}
			
			$arrayData = array();
			if(count($sections) > 0)
			{
				foreach($sections as $key => $value)
				{
					if($value['type'] == 'attachment')
					{
						$arrayData[] = $value;
					}
				}
				if(count($arrayData) > 0)
				{
					return $arrayData;
				}
			}

			
			return false;

		}
		
		function getMessageBody($_uid, $_htmlOptions = '', $_partID)
		{
			if($_htmlOptions != '')
				$this->htmlOptions = $_htmlOptions; 

			$structure = imap_fetchstructure($this->mbox, $_uid, FT_UID);
			#_debug_array($structure);
			$sections = array();
			$this->parseMessage($sections, $structure, $_partID);
			#_debug_array($sections);
			
			foreach($sections as $key => $value)
			{
				if($value['type'] == 'body')
				{
					#_debug_array($value);
					// no mime message, only body available
					if($key == 0)
					{
						$newPart	= trim(imap_body($this->mbox, $_uid, FT_UID));
						$encoding	= $structure->encoding;
						
						// find mimetype
						if(strtolower($structure->subtype) == 'html')
						{
							$mimeType = 'text/html';
						}
						else
						{
							$mimeType = 'text/plain';
						}
						
						// find charset
						if($structure->ifparameters)
						{
							foreach($structure->parameters as $value)
							{
								$parameter[strtolower($value->attribute)] = 
									strtolower($value->value);
							}
							$charSet = $parameter['charset'];
						}
					}
					else
					{
						// select which part(text or html) to display from multipart/alternative
						#_debug_array($sections);
						if($sections[substr($key,0,-2)]['mimeType'] == "multipart/alternative")
						{
							switch($this->htmlOptions)
							{
								// prefer html part
								// don't display text part
								case 'always_display':
									if($value['mimeType'] == 'text/plain')
										continue 2;
									break;
									
								case 'only_if_no_text':
								default:
									if($value['mimeType'] == 'text/html')
										continue 2;
									break;
							}
						}
						// don't diplay html emails at all
						if($value['mimeType'] == 'text/html' && 
						$this->htmlOptions != 'always_display' &&
						$this->htmlOptions != 'only_if_no_text')
						{
							continue;
						}
						$newPart = imap_fetchbody($this->mbox, $_uid, $value["partID"], FT_UID);
						#if($newPart == '')
						#{
						#	#print "nothing<br>";
						#	// FIX ME
						#	// do this only if the parent sub type is multipart/mixed
						#	// and parent/parent is message/rfc
						#	$newPart = imap_fetchbody($this->mbox, $_uid, substr($value["partID"],0,-2), FT_UID);
						#	#$newPart = imap_fetchbody($this->mbox, $_uid, '2.2', FT_UID);
						#}
						$encoding	= $value['encoding'];
						$mimeType	= $value['mimeType'];
						$charSet	= $value['charset'];
					}
					
					// MS-Outlookbug workaround (don't break links)
					$newPart = preg_replace("!((http(s?)://)|((www|ftp)\.))(([^\n\t\r]+)([=](\r)?\n))+!i", 
							"$1$7", 
							$newPart);
					
					// decode the file ...
					switch ($encoding) 
					{
						case ENCBASE64:
							// use imap_base64 to decode
							$newPart = imap_base64($newPart);
							break;
						case ENCQUOTEDPRINTABLE:
							// use imap_qprint to decode
							#$newPart = imap_qprint($newPart);
							$newPart = quoted_printable_decode($newPart);
							break;
						case ENCOTHER:
							// not sure if this needs decoding at all
							break;
						default:
							// it is either not encoded or we don't know about it
							break;
					}
					
					$bodyPart[] = array('body'	=> $newPart,
							    'mimeType'	=> $mimeType,
							    'charSet'	=> $charSet);
				}
			}
			
			return $bodyPart;
			
		}


		function getMessageHeader($_uid, $_partID = '')
		{
			$msgno = imap_msgno($this->mbox, $_uid);
			if($_partID == '')
			{
			
				$retValue = imap_header($this->mbox, $msgno);
			}
			else
			{
				// do it the hard way
				// we need to fetch the headers of another part(message/rfcxxxx)
				$headersPart = imap_fetchbody($this->mbox, $_uid, $_partID.".0", FT_UID);
				$retValue = imap_rfc822_parse_headers($headersPart);
			}
			#_debug_array($retValue);
			return $retValue;
		}

		function getMessageRawBody($_uid, $_partID = '')
		{
			if($_partID != '')
			{
				$body = imap_fetchbody($this->mbox, $_uid, $_partID, FT_UID);
			}
			else
			{
				$header = imap_fetchheader($this->mbox, $_uid, FT_UID);
				$body = $header.imap_body($this->mbox, $_uid, FT_UID);
			}
			
			return $body;
		}

		function getMessageRawHeader($_uid, $_partID = '')
		{
			if(!$_partID == '')
			{
				return imap_fetchbody($this->mbox, $_uid, $_partID.'.0', FT_UID);
			}
			else
			{
				return imap_fetchheader($this->mbox, $_uid, FT_UID);
			}
		}

		function getMessageStructure($_uid)
		{
			return imap_fetchstructure($this->mbox, $_uid, FT_UID);
		}
		
		// return the qouta of the users INBOX
		function getQuotaRoot()
		{
			if(is_array($this->storageQuota))
			{
				return $this->storageQuota;
			}
			else
			{
				return false;
			}
		}
		
		function imap_createmailbox($_folderName, $_subscribe = False)
		{
			$mailboxString = ExecMethod('emailadmin.bo.getMailboxString',$_folderName,3,$this->profileID);
			
			$result = @imap_createmailbox($this->mbox,$mailboxString);
			
			if($_subscribe)
			{
				return @imap_subscribe($this->mbox,$mailboxString);
			}
			
			return $result;
		}
		
		function imap_deletemailbox($_folderName)
		{
			$mailboxString = ExecMethod('emailadmin.bo.getMailboxString',$_folderName,3,$this->profileID);
			
			@imap_unsubscribe ($this->mbox, $mailboxString);

			$result = imap_deletemailbox($this->mbox, $mailboxString);
			
			#print imap_last_error();
			
			return $result;
		}

		function imapGetQuota($_username)
		{
			$quota_value = @imap_get_quota($this->mbox, "user.".$_username);

			if(is_array($quota_value) && count($quota_value) > 0)
			{
				return array('limit' => $quota_value['limit']/1024);
			}
			else
			{
				return false;
			}
		}		
		
		function imap_get_quotaroot($_folderName)
		{
			return @imap_get_quotaroot($this->mbox, $_folderName);
		}
		
		function imap_renamemailbox($_oldMailboxName, $_newMailboxName)
		{
			if(strcasecmp("inbox",$_oldMailboxName) == 0 || strcasecmp("inbox",$_newMailboxName) == 0)
			{
				return False;
			}
			
			$oldMailboxName = ExecMethod('emailadmin.bo.getMailboxString',$_oldMailboxName,3,$this->profileID);
			
			$newMailboxName = ExecMethod('emailadmin.bo.getMailboxString',$_newMailboxName,3,$this->profileID);
			
			$result =  @imap_renamemailbox($this->mbox,$oldMailboxName, $newMailboxName);
			
			#print imap_last_error();
			
			return $result;
		}
		
		function imapSetQuota($_username, $_quotaLimit)
		{
			if(is_numeric($_quotaLimit) && $_quotaLimit >= 0)
			{
				// enable quota
				$quota_value = @imap_set_quota($this->mbox, "user.".$_username, $_quotaLimit*1024);
			}
			else
			{
				// disable quota
				$quota_value = @imap_set_quota($this->mbox, "user.".$_username, -1);
			}
		}
		
		function moveMessages($_foldername, $_messageUID)
		{
			$caching = CreateObject('felamimail.bocaching',
					$this->mailPreferences['imapServerAddress'],
					$this->mailPreferences['username'],
					$this->sessionData['mailbox']);
			$deleteOptions  = $GLOBALS['phpgw_info']["user"]["preferences"]["felamimail"]["deleteOptions"];

			reset($_messageUID);
			while(list($key, $value) = each($_messageUID))
			{
				if(!empty($msglist)) $msglist .= ",";
				$msglist .= $value;
			}
			#print $msglist."<br>";
			
			#print "destination folder($_folderName): ".$this->encodeFolderName($_foldername)."<br>";
			
			if (imap_mail_move ($this->mbox, $msglist, $this->encodeFolderName($_foldername), CP_UID))
			{
				#print "allet ok<br>";
				if($deleteOptions != "mark_as_deleted")
				{
					imap_expunge($this->mbox);
					reset($_messageUID);
					while(list($key, $value) = each($_messageUID))
					{
						$caching->removeFromCache($value);
					}
				}
			}
			else
			{
				print imap_last_error()."<br>";
			}
			
		}

		function openConnection($_folderName='', $_options=0, $_adminConnection=false)
		{
			if(!function_exists('imap_open'))
			{
				return lang('This PHP has no IMAP support compiled in!!');
			}
			
			if(!$this->mailPreferences['emailConfigValid'])
			{
				return lang('no valid emailprofile selected!!');
			}
			
			if($_folderName == '' && !$_adminConnection)
			{
				$_folderName = $this->sessionData['mailbox'];
			}
			
			if($_adminConnection)
			{
				$folderName	= '';
				$username	= $this->mailPreferences['imapAdminUsername'];
				$password	= $this->mailPreferences['imapAdminPW'];
				$options	= '';
			}
			else
			{
				$folderName	= $_folderName;
				$username	= $this->mailPreferences['username'];
				$password	= $this->mailPreferences['key'];
				$options	= $_options;
			}
			
			$mailboxString = ExecMethod('emailadmin.bo.getMailboxString',$_folderName,3,$this->profileID);

			if(!$this->mbox = @imap_open ($mailboxString, $username, $password, $options))
			{
				return imap_last_error();
			}
			else
			{
				// get the quota for this mailboxbox
				if (function_exists('imap_get_quotaroot') && !$_adminConnection)
				{
					$quota = @imap_get_quotaroot($this->mbox, $this->decodeFolderName($folderName));
					if(is_array($quota['STORAGE'])) 
					{
						$storage = $this->storageQuota = $quota['STORAGE'];
					}
				}
				#$_folderName = "user.lars.Asterisk";
				#print "$_folderName<br>";
				#imap_setacl($this->mbox, $_folderName, 'support', 'lrswipcda');
				#print "<pre>";
				#print_r(imap_getacl($this->mbox, $_folderName));
				#print "</pre>";
				return True;
			}
			
		}		

		function parseMessage(&$_sections, $_structure, $_wantedPartID = '', $_currentPartID='')
		{
			#print "w: $_wantedPartID, c: $_currentPartID<br>";
			#if($_currentPartID == '')
			#{
			#	 _debug_array($_structure);
			#	print "<hr><hr>";
			#}
			#_debug_array($_sections);
			#if ($_currentPartID == '') _debug_array($_structure);
			switch ($_structure->type)
			{
				case TYPETEXT:
					if(!preg_match("/^$_wantedPartID/i",$_currentPartID))
					{
						break;
					}
					$mime_type = "text";
					$data['encoding']	= $_structure->encoding;
					$data['size']		= $_structure->bytes;
					$data['partID']	= $_currentPartID;
					$data["mimeType"]	= $mime_type."/". strtolower($_structure->subtype);
					$data["name"]		= lang("unknown");
					for ($lcv = 0; $lcv < count($_structure->parameters); $lcv++)
					{
						$param = $_structure->parameters[$lcv];
						switch(strtolower($param->attribute))
						{
							case 'name':
								$data["name"] = $param->value;
								break;
							case 'charset':
								$data["charset"] = $param->value;
								break;
						}
						
					}
					
					// set this to zero, when we have a plaintext message
					// if partID[0] is set, we have no attachments
					if($_currentPartID == '') $_currentPartID = '0';
					
					if (strtolower($_structure->disposition) == "attachment" ||
						$data["name"] != lang("unknown"))
					{
						// treat it as attachment
						// must be a attachment
						$_sections[$_currentPartID]		= $data;
						$_sections[$_currentPartID]['type']	= 'attachment';
					}
					else
					{
						#print "found a body part $_currentPartID<br>";
						// must be a body part
						$_sections["$_currentPartID"]		= $data;
						$_sections["$_currentPartID"]['name']	= lang('body part')." $_currentPartID";
						$_sections[$_currentPartID]['type']	= 'body';
					}
					#print "<hr>";
					#_debug_array($retData);
					#print "<hr>";
					break;
					
				case TYPEMULTIPART:
					#print "found multipart<br>";
					$mimeType = 'multipart';
					// lets cycle trough all parts
					$_sections[$_currentPartID]['mimeType']	= $mimeType."/". strtolower($_structure->subtype);

					if($_currentPartID != '') $_currentPartID .= '.';

					#print $_sections[$_currentPartID]['mimeType']."<br>";
					for($i = 0; $i < count($_structure->parts); $i++)
					{
						$structureData = array();
						$this->parseMessage($_sections, $_structure->parts[$i], $_wantedPartID, $_currentPartID.($i+1));
					}
					break;
				
				case TYPEMESSAGE:
					#print "found message $_currentPartID<br>";
					#_debug_array($_structure);
					#print "<hr>";
					// handle it as attachment
					#print "$_wantedPartID : $_currentPartID<br>";
					if(($_wantedPartID < $_currentPartID) ||
						empty($_wantedPartID))
					{
						#print "add as attachment<br>";
						$mime_type = "message";
						$_sections[$_currentPartID]['encoding']	= $_structure->encoding;
						$_sections[$_currentPartID]['size']	= $_structure->bytes;
						$_sections[$_currentPartID]['partID']	= $_currentPartID;
						$_sections[$_currentPartID]["mimeType"]	= $mime_type."/". strtolower($_structure->subtype);
						$_sections[$_currentPartID]["name"]	= lang("unknown");
						$_sections[$_currentPartID]['type']	= 'attachment';
						if(!empty($_structure->description))
						{
							$_sections[$_currentPartID]["name"] = lang($_structure->description);
						}

						// has the structure dparameters ??
						if($_structure->ifdparameters)
						{
							foreach($_structure->dparameters as $key => $value)
							{
								switch(strtolower($value->attribute))
								{
									case 'filename':
										$_sections[$_currentPartID]["name"] = $value->value;
										break;
								}
							}
						}

						// has the structure parameters ??
						if($_structure->ifparameters)
						{
							foreach($_structure->parameters as $key => $value)
							{
								switch(strtolower($value->attribute))
								{
									case 'name':
										$_sections[$_currentPartID]["name"] = $value->value;
										break;
								}
							}
						}
	
						
					}
					// recurse in it
					else
					{
						#_debug_array($_structure);
						for($i = 0; $i < count($_structure->parts); $i++)
						{
						#	print "<b>dive into Message</b><bR>";
							if($_structure->parts[$i]->type != TYPEMULTIPART)
								$_currentPartID = $_currentPartID.'.'.($i+1);
							$this->parseMessage($_sections, $_structure->parts[$i], $_wantedPartID, $_currentPartID);
						#	$this->parseMessage($_sections, $_structure->parts[0], $_wantedPartID, $_currentPartID);
						#	print "<b>done diving</b><br>";
						}
					}
					break;
					
				case TYPEAPPLICATION:
					if(!preg_match("/^$_wantedPartID/i",$_currentPartID))
					{
						break;
					}
					$mime_type = "application";
					$_sections[$_currentPartID]['encoding']	= $_structure->encoding;
					$_sections[$_currentPartID]['size']	= $_structure->bytes;
					$_sections[$_currentPartID]['partID']	= $_currentPartID;
					$_sections[$_currentPartID]["mimeType"]	= $mime_type."/". strtolower($_structure->subtype);
					$_sections[$_currentPartID]["name"]	= lang("unknown");
					$_sections[$_currentPartID]['type']	= 'attachment';
					for ($lcv = 0; $lcv < count($_structure->dparameters); $lcv++)
					{
						$param = $_structure->dparameters[$lcv];
						switch(strtolower($param->attribute))
						{
							case 'filename':
								$_sections[$_currentPartID]["name"] = $param->value;
								break;
						}
					}
					
					for ($lcv = 0; $lcv < count($_structure->parameters); $lcv++)
					{
						$param = $_structure->parameters[$lcv];
						switch(strtolower($param->attribute))
						{
							case 'name':
								$_sections[$_currentPartID]["name"] = $param->value;
								break;
						}
					}
					
					break;
					
				case TYPEAUDIO:
					if(!preg_match("/^$_wantedPartID/i",$_currentPartID))
					{
						break;
					}
					$mime_type = "audio";
					$_sections[$_currentPartID]['encoding']	= $_structure->encoding;
					$_sections[$_currentPartID]['size']	= $_structure->bytes;
					$_sections[$_currentPartID]['partID']	= $_currentPartID;
					$_sections[$_currentPartID]["mimeType"]	= $mime_type."/". strtolower($_structure->subtype);
					$_sections[$_currentPartID]["name"]	= lang("unknown");
					$_sections[$_currentPartID]['type']	= 'attachment';
					for ($lcv = 0; $lcv < count($_structure->dparameters); $lcv++)
					{
						$param = $_structure->dparameters[$lcv];
						switch(strtolower($param->attribute))
						{
							case 'filename':
								$_sections[$_currentPartID]["name"] = $param->value;
								break;
						}
					}
					break;
					
				case TYPEIMAGE:
					if(!preg_match("/^$_wantedPartID/i",$_currentPartID))
					{
						break;
					}
					#print "found image $_currentPartID<br>";
					$mime_type = "image";
					$_sections[$_currentPartID]['encoding']	= $_structure->encoding;
					$_sections[$_currentPartID]['size']	= $_structure->bytes;
					$_sections[$_currentPartID]['partID']	= $_currentPartID;
					$_sections[$_currentPartID]["mimeType"]	= $mime_type."/". strtolower($_structure->subtype);
					$_sections[$_currentPartID]["name"]	= lang("unknown");
					$_sections[$_currentPartID]['type']	= 'attachment';
					for ($lcv = 0; $lcv < count($_structure->dparameters); $lcv++)
					{
						$param = $_structure->dparameters[$lcv];
						switch(strtolower($param->attribute))
						{
							case 'filename':
								$_sections[$_currentPartID]["name"] = $param->value;
								break;
						}
					}
					break;
					
				case TYPEVIDEO:
					if(!preg_match("/^$_wantedPartID/i",$_currentPartID))
					{
						break;
					}
					$mime_type = "video";
					$_sections[$_currentPartID]['encoding']	= $_structure->encoding;
					$_sections[$_currentPartID]['size']	= $_structure->bytes;
					$_sections[$_currentPartID]['partID']	= $_currentPartID;
					$_sections[$_currentPartID]["mimeType"]	= $mime_type."/". strtolower($_structure->subtype);
					$_sections[$_currentPartID]["name"]	= lang("unknown");
					$_sections[$_currentPartID]['type']	= 'attachment';
					for ($lcv = 0; $lcv < count($_structure->dparameters); $lcv++)
					{
						$param = $_structure->dparameters[$lcv];
						switch(strtolower($param->attribute))
						{
							case 'filename':
								$_sections[$_currentPartID]["name"] = $param->value;
								break;
						}
					}
					break;
					
				case TYPEMODEL:
					if(!preg_match("/^$_wantedPartID/i",$_currentPartID))
					{
						break;
					}
					$mime_type = "model";
					$_sections[$_currentPartID]['encoding']	= $_structure->encoding;
					$_sections[$_currentPartID]['size']	= $_structure->bytes;
					$_sections[$_currentPartID]['partID']	= $_currentPartID;
					$_sections[$_currentPartID]["mimeType"]	= $mime_type."/". strtolower($_structure->subtype);
					$_sections[$_currentPartID]["name"]	= lang("unknown");
					$_sections[$_currentPartID]['type']	= 'attachment';
					for ($lcv = 0; $lcv < count($_structure->dparameters); $lcv++)
					{
						$param = $_structure->dparameters[$lcv];
						switch(strtolower($param->attribute))
						{
							case 'filename':
								$_sections[$_currentPartID]["name"] = $param->value;
								break;
						}
					}
					break;
					
				default:
					break;
			}

			#if ($_currentPartID == '') _debug_array($_sections);
			
			#print "$_wantedPartID, $_currentPartID<br>";
			#if($_currentPartID >= $_wantedPartID)
			#{
			#	print "will add<br>";
			#	return $retData;
			#}
			
		}
		
		function restoreSessionData()
		{
			$this->sessionData = $GLOBALS['phpgw']->session->appsession('session_data');
		}
		
		function saveFilter($_formData)
		{
			if(!empty($_formData['from']))
				$data['from']	= $_formData['from'];
			if(!empty($_formData['to']))
				$data['to']	= $_formData['to'];
			if(!empty($_formData['subject']))
				$data['subject']= $_formData['subject'];
			if($_formData['filterActive'] == "true")
			{
				$data['filterActive']= "true";
			}

			$this->sessionData['filter'] = $data;
			$this->saveSessionData();
		}
		
		function saveSessionData()
		{
			$GLOBALS['phpgw']->session->appsession('session_data','',$this->sessionData);
		}
		
		function setEMailProfile($_profileID)
		{
			$config = CreateObject('phpgwapi.config','felamimail');
			$config->read_repository();
			$config->value('profileID',$_profileID);
			$config->save_repository();
		}
		
		function subscribe($_folderName, $_status)
		{
			#$this->mailPreferences['imapServerAddress']
			#$this->mailPreferences['imapPort'],
			
			$folderName = $this->encodeFolderName($_folderName);
			$folderName = "{".$this->mailPreferences['imapServerAddress'].":".$this->mailPreferences['imapPort']."}".$folderName;
			
			if($_status == 'unsubscribe')
			{
				return imap_unsubscribe($this->mbox,$folderName);
			}
			else
			{
				return imap_subscribe($this->mbox,$folderName);
			}
		}
		
		function toggleFilter()
		{
			if($this->sessionData['filter']['filterActive'] == 'true')
			{
				$this->sessionData['filter']['filterActive'] = 'false';
			}
			else
			{
				$this->sessionData['filter']['filterActive'] = 'true';
			}
			$this->saveSessionData();
		}

		function updateAccount($_hookValues)
		{
			if($this->profileID > 0 && is_numeric($this->profileID))
			{
				ExecMethod('emailadmin.bo.updateAccount',$_hookValues,3,$this->profileID);
			}
		}
		
		/* inspired by http://de2.php.net/wordwrap
		   desolate19 at hotmail dot com */
		function wordwrap($str, $cols, $cut)
		{
/*			
			// todo
			// think about multibyte charsets
			// think about links in html mode
			$len		= strlen($str);
			$tag		= 0;
			$lineLenght	= 0;
			
			for ($i = 0; $i < $len; $i++) 
			{
				$lineLenght++;
				$chr = substr($str,$i,1);
				if(ctype_cntrl($chr))
				{
					if(ord($chr) == 10)
						$lineLenght     = 0;
				}
				if ($chr == '<') {
					$tag++;
				} elseif ($chr == '>') {
					$tag--;
				} elseif ((!$tag) && (ctype_space($chr))) {
					$wordlen = 0;
				} elseif (!$tag) {
					$wordlen++;
				}

				if ((!$tag) && (!$wordlen) && $lineLenght > $cols) {
				//if ((!$tag) && ($wordlen) && (!($wordlen % $cols))) {
					#print "add cut<br>";
					$chr .= $cut;
					$lineLenght     = 0;
				}
				$result .= $chr;
			}
			return $result;
*/
			$lines = explode('\n', $str);
			$newStr = '';
			foreach($lines as $line)
			{
				// replace tabs by 8 space chars, or any tab only counts one char
				$line = str_replace("\t","        ",$line);
				$newStr .= wordwrap($line, $cols, $cut);
			}
			return $newStr;
		}
		
	}
?>
