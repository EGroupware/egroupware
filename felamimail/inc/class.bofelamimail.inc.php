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
			'flagMessages'		=> True,
		);

		var $mbox;		// the mailbox identifier any function should use
		static $debug = false; //true; // sometimes debuging is quite handy, to see things. check with the error log to see results
		// define some constants
		// message types
		var $type = array("text", "multipart", "message", "application", "audio", "image", "video", "other");

		// message encodings
		var $encoding = array("7bit", "8bit", "binary", "base64", "quoted-printable", "other");
		static $displayCharset;
		var $bopreferences;
		var $mailPreferences;
		// set to true, if php is compiled with multi byte string support
		var $mbAvailable = FALSE;

		// what type of mimeTypes do we want from the body(text/html, text/plain)
		var $htmlOptions;

		var $sessionData;

		// the current selected user profile
		var $profileID = 0;

		/**
		 * Folders that get automatic created AND get translated to the users language
		 * their creation is also controlled by users mailpreferences. if set to none / dont use folder
		 * the folder will not be automatically created. This is controlled in bofelamimail->getFolderObjects
		 * so changing names here, must include a change of keywords there as well. Since these
		 * foldernames are subject to translation, keep that in mind too, if you change names here.
		 * @var array
		 */
		static $autoFolders = array('Drafts', 'Templates', 'Sent', 'Trash', 'Junk');

		/**
		* Autoload classes from emailadmin, 'til they get autoloading conform names
		*
		* @param string $class
		*/
		static function autoload($class)
		{
			if (file_exists($file=EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$class.'.inc.php'))
			{
				include_once($file);
				//error_log(__METHOD__."($class) included $file");
			}
			elseif (file_exists($file=EGW_INCLUDE_ROOT.'/felamimail/inc/class.'.$class.'.inc.php'))
			{
				include_once($file);
			}
			else
			{
				#error_log(__METHOD__."($class) failed!");
			}
		}

		function bofelamimail($_displayCharset='iso-8859-1',$_restoreSession=true)
		{
			if ($_restoreSession) 
			{
				//error_log(__METHOD__." Session restore ".function_backtrace());
				$this->restoreSessionData();
			}
			else
			{
				$this->restoreSessionData();
				$lv_mailbox = $this->sessionData['mailbox'];
				$firstMessage = $this->sessionData['previewMessage'];
				$this->sessionData = array();
				$this->forcePrefReload();
			}

			// FIXME: this->foldername seems to be unused
			//$this->foldername	= $this->sessionData['mailbox'];
			$this->accountid	= $GLOBALS['egw_info']['user']['account_id'];

			$this->bopreferences	= CreateObject('felamimail.bopreferences',$_restoreSession);
			//$this->sofelamimail	= new sofelamimail;

			$this->mailPreferences	= $this->bopreferences->getPreferences();
			if ($this->mailPreferences) {
				$this->icServer = $this->mailPreferences->getIncomingServer(0);
				$this->ogServer = $this->mailPreferences->getOutgoingServer(0);
				$this->htmlOptions  = $this->mailPreferences->preferences['htmlOptions'];
			}
			#_debug_array($this->mailPreferences->preferences);
			$this->imapBaseDir	= '';

			self::$displayCharset	= $_displayCharset;
			if(function_exists(mb_decode_mimeheader)) {
				mb_internal_encoding(self::$displayCharset);
			}

			// set some defaults
			if(empty($this->sessionData)) 
			{
				// this should be under user preferences
				// sessionData empty
				// no filter active
				$this->sessionData['activeFilter']	= "-1";
				// default mailbox INBOX
				$this->sessionData['mailbox']		= (($lv_mailbox && self::folderExists($lv_mailbox,true)) ? $lv_mailbox : "INBOX");
				$this->sessionData['previewMessage'] = ($firstMessage >0 ? $firstMessage : 0); 
				// default start message
				$this->sessionData['startMessage']	= 1;
				// default mailbox for preferences pages
				$this->sessionData['preferences']['mailbox']	= "INBOX";

				$this->sessionData['messageFilter'] = array(
					'string'	=> '',
					'type'		=> 'quick',
					'status'	=> 'any',
				);

				// default sorting
				switch($GLOBALS['egw_info']['user']['preferences']['felamimail']['sortOrder']) {
					case 1:
						$this->sessionData['sort'] = SORTDATE;
						$this->sessionData['sortReverse'] = false;
						break;
					case 2:
						$this->sessionData['sort'] = SORTFROM;
						$this->sessionData['sortReverse'] = true;
						break;
					case 3:
						$this->sessionData['sort'] = SORTFROM;
						$this->sessionData['sortReverse'] = false;
						break;
					case 4:
						$this->sessionData['sort'] = SORTSUBJECT;
						$this->sessionData['sortReverse'] = true;
						break;
					case 5:
						$this->sessionData['sort'] = SORTSUBJECT;
						$this->sessionData['sortReverse'] = false;
						break;
					case 6:
						$this->sessionData['sort'] = SORTSIZE;
						$this->sessionData['sortReverse'] = true;
						break;
					case 7:
						$this->sessionData['sort'] = SORTSIZE;
						$this->sessionData['sortReverse'] = false;
						break;
					default:
						$this->sessionData['sort'] = SORTDATE;
						$this->sessionData['sortReverse'] = true;
						break;
				}
				$this->saveSessionData();
			}

			if (function_exists('mb_convert_encoding')) {
				$this->mbAvailable = TRUE;
			}

		}

		function forcePrefReload()
		{
			// unset the fm_preferences session object, to force the reload/rebuild
			$GLOBALS['egw']->session->appsession('fm_preferences','felamimail',serialize(array()));
			$GLOBALS['egw']->session->appsession('session_data','emailadmin',serialize(array()));
		}

		function setACL($_folderName, $_accountName, $_acl)
		{
			if ( PEAR::isError($this->icServer->setACL($_folderName, $_accountName, $_acl)) ) {
				return false;
			}

			return TRUE;
		}

		function deleteACL($_folderName, $_accountName)
		{
			if ( PEAR::isError($this->icServer->deleteACL($_folderName, $_accountName)) ) {
				return false;
			}

			return TRUE;
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
			if ($this->mailPreferences) {
				$icServer = $this->mailPreferences->getIncomingServer(0);
				if(is_a($icServer,'defaultimap')) {
					$icServer->addAccount($_hookValues);
				}

				$ogServer = $this->mailPreferences->getOutgoingServer(0);
				if(is_a($ogServer,'defaultsmtp')) {
					$ogServer->addAccount($_hookValues);
				}
			}
		}

		function adminMenu()
		{
 			if ($GLOBALS['egw_info']['server']['account_repository'] == "ldap")
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

		/**
		* save a message in folder
		*
		* @todo set flags again
		*
		* @param string _folderName the foldername
		* @param string _header the header of the message
		* @param string _body the body of the message
		* @param string _flags the imap flags to set for the saved message
		*
		* @returns the id of the message appended or false
		*/
		function appendMessage($_folderName, $_header, $_body, $_flags)
		{
			$header = ltrim(str_replace("\n","\r\n",$_header));
			$body   = str_replace("\n","\r\n",$_body);
			$messageid = $this->icServer->appendMessage("$header"."$body", $_folderName, $_flags);
			if ( PEAR::isError($messageid)) {
				if (self::$debug) error_log("Could not append Message:".print_r($messageid->message,true));
				return false;
			}
			return $messageid;
		}

		function closeConnection() {
			$this->icServer->disconnect();
		}

		/**
		* remove any messages which are marked as deleted or
		* remove any messages from the trashfolder
		*
		* @param string _folderName the foldername
		* @returns nothing
		*/
		function compressFolder($_folderName = false)
		{
			$folderName	= ($_folderName ? $_folderName : $this->sessionData['mailbox']);
			$deleteOptions	= $GLOBALS['egw_info']['user']['preferences']['felamimail']['deleteOptions'];
			$trashFolder	= $this->mailPreferences->preferences['trashFolder']; //$GLOBALS['egw_info']['user']['preferences']['felamimail']['trashFolder'];

			$this->icServer->selectMailbox($folderName);

			if($folderName == $trashFolder && $deleteOptions == "move_to_trash") {
				$this->icServer->deleteMessages('1:*');
				$this->icServer->expunge();
			} else {
				$this->icServer->expunge();
			}
		}

		/**
		* create a new folder under given parent folder
		*
		* @param string _parent the parent foldername
		* @param string _folderName the new foldername
		* @param bool _subscribe subscribe to the new folder
		*
		* @returns mixed name of the newly created folder or false on error
		*/
		function createFolder($_parent, $_folderName, $_subscribe=false)
		{
			$parent		= $this->_encodeFolderName($_parent);
			$folderName	= $this->_encodeFolderName($_folderName);

			if(empty($parent)) {
				$newFolderName = $folderName;
			} else {
				$HierarchyDelimiter = $this->getHierarchyDelimiter();
				$newFolderName = $parent . $HierarchyDelimiter . $folderName;
			}

			if ( PEAR::isError($this->icServer->createMailbox($newFolderName) ) ) {
				return false;
			}

			if ( PEAR::isError($this->icServer->subscribeMailbox($newFolderName) ) ) {
				return false;
			}

			return $newFolderName;

		}

		function createIMAPFilter($_folder, $_criterias)
		{
			if(!is_array($_criterias)) {
				return 'ALL';
			}
		#	error_log(print_r($_criterias, true));
			$imapFilter = '';

			#foreach($_criterias as $criteria => $parameter) {
			if(!empty($_criterias['string'])) {
				$criteria = strtoupper($_criterias['type']);
				switch ($criteria) {
					case 'QUICK':
						if($this->isSentFolder($_folder)) {
							$imapFilter .= 'OR SUBJECT "'. $_criterias['string'] .'" TO "'. $_criterias['string'] .'" ';
						} else {
							$imapFilter .= 'OR SUBJECT "'. $_criterias['string'] .'" FROM "'. $_criterias['string'] .'" ';
						}
						break;
					case 'BCC':
					case 'BODY':
					case 'CC':
					case 'FROM':
					case 'KEYWORD':
					case 'SUBJECT':
					case 'TEXT':
					case 'TO':
						$imapFilter .= $criteria .' "'. $_criterias['string'] .'" ';
						break;
				}
			}

			#foreach($_criterias as $criteria => $parameter) {
				$criteria = strtoupper($_criterias['status']);
				switch ($criteria) {
					case 'ANSWERED':
					case 'DELETED':
					case 'FLAGGED':
					case 'NEW':
					case 'OLD':
					case 'RECENT':
					case 'SEEN':
					case 'UNANSWERED':
					case 'UNDELETED':
					case 'UNFLAGGED':
					case 'UNSEEN':
						$imapFilter .= $criteria .' ';
						break;

					case 'BEFORE':
					case 'ON':
					case 'SINCE':
						$imapFilter .= $criteria .' '. $_criterias['string'].' ';
						break;
				}
			#}
		#	error_log("Filter: $imapFilter");
			if($imapFilter == '') {
				return 'ALL';
			} else {
				return trim($imapFilter);
				#return 'CHARSET '. strtoupper(self::$displayCharset) .' '. trim($imapFilter);
			}
		}

		/**
		* convert a mailboxname from displaycharset to urf7-imap
		*
		* @param string _folderName the foldername
		*
		* @returns string the converted foldername
		*/
		function decodeFolderName($_folderName)
		{
			return $GLOBALS['egw']->translation->convert($_folderName, self::$displayCharset, 'UTF7-IMAP');
		}

		function decodeMimePart($_mimeMessage, $_encoding, $_charset = '')
		{
			// decode the part
			if (self::$debug) error_log("bofelamimail::decodeMimePart with $_encoding and $_charset:".print_r($_mimeMessage,true));
			switch ($_encoding)
			{
				case 'BASE64':
					// use imap_base64 to decode, not any longer, as it is strict, and fails if it encounters invalid chars
					return base64_decode($_mimeMessage); //imap_base64($_mimeMessage);
					break;
				case 'QUOTED-PRINTABLE':
					// use imap_qprint to decode
					return quoted_printable_decode($_mimeMessage);
					break;
				default:
					// it is either not encoded or we don't know about it
					return $_mimeMessage;
					break;
			}
		}

		/**
		 * decode header (or envelope information
		 * if array given, note that only values will be converted 
		 * @param $_string mixed input to be converted, if array call decode_header recursively on each value
		 * @returns mixed - based on the input type
		 */
		static function decode_header($_string)
		{
			if (is_array($_string))
			{
				foreach($_string as $k=>$v)
				{
					$_string[$k] = self::decode_header($v);
				}
				return $_string;
			}
			else
			{
				return $GLOBALS['egw']->translation->decodeMailHeader($_string,self::$displayCharset);
			}
		}

		function decode_subject($_string,$decode=true)
		{
			#$string = $_string;
			if($_string=='NIL')
			{
				return 'No Subject';
			}
			if ($decode) $_string = self::decode_header($_string);
			return $_string;

		}

		/**
		 * decodes winmail.dat attachments
		 *
		 * @param int $_uid
		 * @param string $_partID
		 * @param int $_filenumber
		 * @return array
		 */
		function decode_winmail( $_uid, $_partID, $_filenumber=0 )
		{
			$attachment = $this->getAttachment( $_uid, $_partID );
			$dirname = $this->accountid.'_'.$this->profileID.'_'.$this->sessionData['mailbox'].'_'.$_uid.'_'.$_partID;
			if (self::$debug) error_log(__METHOD__.__LINE__.' Dirname:'.$dirname);
			$dirname = md5($dirname);
			$dir = $GLOBALS['egw_info']['server']['temp_dir']."/fmail_winmail/$dirname";
			$mime = CreateObject('phpgwapi.mime_magic');
			if ( $attachment['type'] == 'APPLICATION/MS-TNEF' && $attachment['filename'] == 'winmail.dat' )
			{
				// decode winmail.dat
				if ( !file_exists( "$dir/winmail.dat" ) )
				{
					@mkdir( $dir, 0700, true );
					file_put_contents( "$dir/winmail.dat", $attachment['attachment'] );
				}
				if (file_exists('/usr/bin/tnef'))
				{
					exec( "cd $dir && /usr/bin/tnef --save-body --overwrite -C $dir -f ./winmail.dat" );
				}
				elseif (exec("which tnef")) // use tnef if exsting, as it gives better results..
				{
					exec( "cd $dir && tnef --save-body --overwrite -C $dir -f ./winmail.dat" );
				} 
				elseif (exec("which ytnef"))
				{
					exec( "cd $dir && ytnef -f . winmail.dat" );
				}
				// list contents
				$files = scandir( $dir );
				foreach ( $files as $num => $file )
				{
					if ( filetype( "$dir/$file" ) != 'file' || $file == 'winmail.dat' ) continue;
					if ( $_filenumber > 0 && $_filenumber != $num ) continue;
					$type = $mime->filename2mime($file);
					$attachments[] = array(
						'is_winmail' => $num,
						'name' => self::decode_header($file),
						'size' => filesize( "$dir/$file"),
						'partID' => $_partID,
						'mimeType' => $type,
						'type' => $type,
						'attachment' => $_filenumber > 0 ? file_get_contents("$dir/$file") : '',
					);
					unlink($dir."/".$file);
				}
				if (file_exists($dir."/winmail.dat")) unlink($dir."/winmail.dat");
				if (file_exists($dir)) @rmdir($dir);
				return $_filenumber > 0 ? $attachments[0] : $attachments;
			}
			return false;
		}

		function deleteAccount($_hookValues)
		{
			if ($this->mailPreferences) {
				$icServer = $this->mailPreferences->getIncomingServer(0);
				if(is_a($icServer,'defaultimap')) {
					$icServer->deleteAccount($_hookValues);
				}

				$ogServer = $this->mailPreferences->getOutgoingServer(0);
				if(is_a($ogServer,'defaultsmtp')) {
					$ogServer->deleteAccount($_hookValues);
				}
			}
		}

		/**
		* delete a existing folder
		*
		* @param string _folderName the name of the folder to be deleted
		*
		* @returns bool true on success, false on failure
		*/
		function deleteFolder($_folderName)
		{
			$folderName = $this->_encodeFolderName($_folderName);

			$this->icServer->unsubscribeMailbox($folderName);
			if ( PEAR::isError($this->icServer->deleteMailbox($folderName)) ) {
				return false;
			}

			return true;
		}

		function deleteMessages($_messageUID, $_folder=NULL)
		{
			$msglist = '';
			$oldMailbox = '';
			if (is_null($_folder) || empty($_folder)) $_folder = $this->sessionData['mailbox'];
			if(!is_array($_messageUID) || count($_messageUID) === 0) 
			{
				if ($_messageUID=='all')
				{
					$_messageUID= null;
				}
				else
				{
					if (self::$debug) error_log(__METHOD__." no messages Message(s): ".implode(',',$_messageUID));
					return false;					
				}
			}

			$deleteOptions  = $this->mailPreferences->preferences['deleteOptions'];
			$trashFolder    = $this->mailPreferences->preferences['trashFolder'];
			$draftFolder	= $this->mailPreferences->preferences['draftFolder']; //$GLOBALS['egw_info']['user']['preferences']['felamimail']['draftFolder'];
			$templateFolder = $this->mailPreferences->preferences['templateFolder']; //$GLOBALS['egw_info']['user']['preferences']['felamimail']['templateFolder'];

			if(($this->sessionData['mailbox'] == $trashFolder && $deleteOptions == "move_to_trash") ||
			   ($this->sessionData['mailbox'] == $draftFolder)) {
				$deleteOptions = "remove_immediately";
			}
			if($this->icServer->getCurrentMailbox() != $_folder) {
				$oldMailbox = $this->icServer->getCurrentMailbox();
				$this->icServer->selectMailbox($_folder);
			}

			switch($deleteOptions) {
				case "move_to_trash":
					if(!empty($trashFolder)) {
						if (self::$debug) error_log(implode(' : ', $_messageUID));
						if (self::$debug) error_log("$trashFolder <= ". $this->sessionData['mailbox']);
						// copy messages
						if ( PEAR::isError($this->icServer->copyMessages($trashFolder, $_messageUID, $_folder, true)) ) {
							if (self::$debug) error_log(__METHOD__." failed to copy Message(s) to $trashFolder: ".implode(',',$_messageUID));
							return false;
						}
						// mark messages as deleted
						if ( PEAR::isError($this->icServer->deleteMessages($_messageUID, true))) {
							if (self::$debug) error_log(__METHOD__." failed to delete Message(s): ".implode(',',$_messageUID));
							return false;
						}
						// delete the messages finaly
						$this->icServer->expunge();
					}
					break;

				case "mark_as_deleted":
					// mark messages as deleted
					if ( PEAR::isError($this->icServer->deleteMessages($_messageUID, true))) {
						if (self::$debug) error_log(__METHOD__." failed to mark as deleted for Message(s): ".implode(',',$_messageUID));
						return false;
					}
					break;

				case "remove_immediately":
					// mark messages as deleted
					if ( PEAR::isError($this->icServer->deleteMessages($_messageUID, true))) {
						if (self::$debug) error_log(__METHOD__." failed to remove immediately Message(s): ".implode(',',$_messageUID));
						return false;
					}
					// delete the messages finaly
					$this->icServer->expunge();
					break;
			}

			if($oldMailbox != '') {
				$this->icServer->selectMailbox($oldMailbox);
			}

			return true;
		}

		/**
		* convert a mailboxname from utf7-imap to displaycharset
		*
		* @param string _folderName the foldername
		*
		* @returns string the converted string
		*/
		function encodeFolderName($_folderName)
		{
			return $GLOBALS['egw']->translation->convert($_folderName, 'UTF7-IMAP', self::$displayCharset);
		}

#		function encodeHeader($_string, $_encoding='q')
#		{
#			switch($_encoding) {
#				case "q":
#					if(!preg_match("/[\x80-\xFF]/",$_string)) {
#						// nothing to quote, only 7 bit ascii
#						return $_string;
#					}
#
#					$string = imap_8bit($_string);
#					$stringParts = explode("=\r\n",$string);
#					while(list($key,$value) = each($stringParts)) {
#						if(!empty($retString)) $retString .= " ";
#						$value = str_replace(" ","_",$value);
#						// imap_8bit does not convert "?"
#						// it does not need, but it should
#						$value = str_replace("?","=3F",$value);
#						$retString .= "=?".strtoupper(self::$displayCharset). "?Q?". $value. "?=";
#					}
#					#exit;
#					return $retString;
#					break;
#				default:
#					return $_string;
#			}
#		}
		function getFlags ($_messageUID) {
			$flags =  $this->icServer->getFlags($_messageUID, true);
			if (PEAR::isError($flags)) {
				return null;
			}
			return $flags;
		}

		function getNotifyFlags ($_messageUID) {
			$flags =  $this->icServer->getFlags($_messageUID, true);
			if (PEAR::isError($flags)) {
				return null;
				}
			if ( in_array('MDNSent',$flags[0]) )
				return true;

			if ( in_array('MDNnotSent',$flags[0]) )
				return false;

			return null;
		}

		function flagMessages($_flag, $_messageUID)
		{
			#error_log("felamimail::bocompose::flagMessages");
			if(!is_array($_messageUID)) {
				#return false;
				if ($_messageUID=='all')
				{
					//all is an allowed value to be passed
				}
				else
				{
					$_messageUID=array($_messageUID);
				}
			}

			$this->icServer->selectMailbox($this->sessionData['mailbox']);

			switch($_flag) {
				case "flagged":
					$this->icServer->setFlags($_messageUID, '\\Flagged', 'add', true);
					break;
				case "read":
					$this->icServer->setFlags($_messageUID, '\\Seen', 'add', true);
					break;
				case "forwarded":
					$this->icServer->setFlags($_messageUID, '$Forwarded', 'add', true);
				case "answered":
					$this->icServer->setFlags($_messageUID, '\\Answered', 'add', true);
					break;
				case "unflagged":
					$this->icServer->setFlags($_messageUID, '\\Flagged', 'remove', true);
					break;
				case "unread":
					$this->icServer->setFlags($_messageUID, '\\Seen', 'remove', true);
					$this->icServer->setFlags($_messageUID, '\\Answered', 'remove', true);
					$this->icServer->setFlags($_messageUID, '$Forwarded', 'remove', true);
					break;
				case "mdnsent":
					$this->icServer->setFlags($_messageUID, 'MDNSent', 'add', true);
					break;
				case "mdnnotsent":
					$this->icServer->setFlags($_messageUID, 'MDNnotSent', 'add', true);
					break;
			}

			$this->sessionData['folderStatus'][$this->profileID][$this->sessionData['mailbox']]['uidValidity'] = 0;
			$this->saveSessionData();
		}

		function _getSubStructure($_structure, $_partID)
		{
			$tempID = '';
			$structure = $_structure;
			if (empty($_partID)) $_partID=1;
			$imapPartIDs = explode('.',$_partID);
			#error_log(print_r($structure,true));
			#error_log(print_r($_partID,true));

			if($_partID != 1) {
				foreach($imapPartIDs as $imapPartID) {
					if(!empty($tempID)) {
						$tempID .= '.';
					}
					$tempID .= $imapPartID;
					#error_log(print_r( "TEMPID: $tempID<br>",true));
					//_debug_array($structure);
					if($structure->subParts[$tempID]->type == 'MESSAGE' && $structure->subParts[$tempID]->subType == 'RFC822' &&
					   count($structure->subParts[$tempID]->subParts) == 1 &&
					   $structure->subParts[$tempID]->subParts[$tempID]->type == 'MULTIPART' &&
					   ($structure->subParts[$tempID]->subParts[$tempID]->subType == 'MIXED' ||
					    $structure->subParts[$tempID]->subParts[$tempID]->subType == 'ALTERNATIVE' ||
					    $structure->subParts[$tempID]->subParts[$tempID]->subType == 'RELATED' ||
					    $structure->subParts[$tempID]->subParts[$tempID]->subType == 'REPORT'))
					{
						$structure = $structure->subParts[$tempID]->subParts[$tempID];
					} else {
						$structure = $structure->subParts[$tempID];
					}
				}
			}

			if($structure->partID != $_partID) {
				foreach($imapPartIDs as $imapPartID) {
					if(!empty($tempID)) {
						$tempID .= '.';
					}
					$tempID .= $imapPartID;
					//print "TEMPID: $tempID<br>";
					//_debug_array($structure);
					if($structure->subParts[$tempID]->type == 'MESSAGE' && $structure->subParts[$tempID]->subType == 'RFC822' &&
					   count($structure->subParts[$tempID]->subParts) == 1 &&
					   $structure->subParts[$tempID]->subParts[$tempID]->type == 'MULTIPART' &&
					   ($structure->subParts[$tempID]->subParts[$tempID]->subType == 'MIXED' ||
					    $structure->subParts[$tempID]->subParts[$tempID]->subType == 'ALTERNATIVE' ||
					    $structure->subParts[$tempID]->subParts[$tempID]->subType == 'RELATED' ||
					    $structure->subParts[$tempID]->subParts[$tempID]->subType == 'REPORT')) {
						$structure = $structure->subParts[$tempID]->subParts[$tempID];
					} else {
						$structure = $structure->subParts[$tempID];
					}
				}
				if($structure->partID != $_partID) {
					error_log("bofelamimail::_getSubStructure(". __LINE__ .") partID's don't match");
					return false;
				}
			}

			return $structure;
		}

		/*
		* strip tags out of the message completely with their content
		* param $_body is the text to be processed
		* param $tag is the tagname which is to be removed. Note, that only the name of the tag is to be passed to the function
		*            without the enclosing brackets
		* param $endtag can be different from tag  but should be used only, if begin and endtag are known to be different e.g.: <!-- -->
		*/
		static function replaceTagsCompletley(&$_body,$tag,$endtag='',$addbracesforendtag=true)
		{
			translation::replaceTagsCompletley($_body,$tag,$endtag,$addbracesforendtag);
		}

		static function getCleanHTML(&$_html, $usepurify = false)
		{
			// remove CRLF and TAB as it is of no use in HTML.
			// but they matter in <pre>, so we rather don't
			//$_html = str_replace("\r\n",' ',$_html);
			//$_html = str_replace("\t",' ',$_html);
			//error_log($_html);
			self::replaceTagsCompletley($_html,'style'); // clean out empty or pagewide style definitions / left over tags
			self::replaceTagsCompletley($_html,'head'); // Strip out stuff in head	
			self::replaceTagsCompletley($_html,'!\[if','<!\[endif\]>',false); // Strip out stuff in ifs	
			self::replaceTagsCompletley($_html,'!--\[if','<!\[endif\]-->',false); // Strip out stuff in ifs	
			//error_log($_html);
			// force the use of kses, as it is still have the edge over purifier with some stuff
			$usepurify = false;
			if ($usepurify)
			{
				// we may need a customized config, as we may allow external images, $GLOBALS['egw_info']['user']['preferences']['felamimail']['allowExternalIMGs']

				$config = html::purifyCreateDefaultConfig();
				
				$config->set('Core.Encoding', (self::$displayCharset?self::$displayCharset:'UTF-8'));
				// maybe the two following lines are useful for caching???
				$config->set('HTML.DefinitionID', 'felamimail');
				$config->set('HTML.DefinitionRev', 1);
				// doctype and tidylevel
	 			$config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
				$config->set('HTML.TidyLevel', 'light');
				// EnableID is needed for anchor tags
				$config->set('Attr.EnableID',true);
				// actual allowed tags and attributes
				$config->set('URI.AllowedSchemes', array('http'=>true, 'https'=>true, 'ftp'=>true, 'file'=>true, 'mailto' => true, 'cid'=>true));
				$config->set('AutoFormat.RemoveEmpty', true);
				$config->set('HTML.Allowed', 'br,p[align],b,i,u,s,em,pre,tt,strong,strike,center,div[align],hr[class|style],'.
							'font[size|color],'.
							'ul[type],ol[type|start],li,'.
							'h1,h2,h3,'.
							'span[class|style],'.
							'table[class|border|cellpadding|cellspacing|width|style|align|bgcolor|align],'.
							'tbody,thead,tfoot,colgroup,'.
							'col[width|span],'.
							'blockquote[class|cite|dir],'.
							'tr[class|style|align|bgcolor|align|valign],'.
							'td[class|colspan|rowspan|width|style|align|bgcolor|align|valign|nowrap],'.
							'th[class|colspan|rowspan|width|style|align|bgcolor|align|valign|nowrap],'.
							'a[href|target|name|title],'.
							'img[src|alt|title]');
				$DisableExternalResources = true;
				if ($GLOBALS['egw_info']['user']['preferences']['felamimail']['allowExternalIMGs']) $DisableExternalResources = false;
				$config->set('URI.DisableExternalResources',$DisableExternalResources);
				$config->set('Core.RemoveInvalidImg', false);
				//$config->set('Attr.DefaultInvalidImage', 'Image removed by htmlpurify');
				$config->set('Core.HiddenElements', array('script','style','head')); // strips script, style, head copletely

				$config->set('Cache.SerializerPath', ($GLOBALS['egw_info']['server']['temp_dir']?$GLOBALS['egw_info']['server']['temp_dir']:sys_get_temp_dir()));
				//$config->set('HTML.MaxImgLength',null);
				$config->set('Cache.DefinitionImpl', null); // remove this later!
				//$purifier = new HTMLPurifier($config);
				//$_html = $purifier->purify( $_html );
				if (get_magic_quotes_gpc() === 1) $_html = stripslashes($_html);
				$_html = html::purify($_html,$config);
	            // no scripts allowed
	            // clean out comments , should not be needed as purify should do the job.
				$search = array(
					'@url\(http:\/\/[^\)].*?\)@si',  // url calls e.g. in style definitions
					'@<!--[\s\S]*?[ \t\n\r]*-->@',         // Strip multi-line comments including CDATA 
	            );
	            //$_html = preg_replace($search,"",$_html);
	            // remove non printable chars
	            $_html = preg_replace('/([\000-\012])/','',$_html);
				//error_log($_html);
			}
			else
			{
				//echo $_html;exit;
				$kses = new kses();
				$kses->AddProtocol('cid');
				// since check protocoll is called for every value associated to an attribute we have to add color and background-color to the valid protocolls
				$kses->AddProtocol('color');
				$kses->AddProtocol('font-size');
				$kses->AddProtocol('background-color');
				#$kses->AddHTML('html', array(
				#		'xmlns' => array(),
				#		'lang' => array(),
				#	)
				#);
				#$kses->AddHTML('head');
				#$kses->AddHTML('body', array(
				#		'class' => array(),
				#		'id' => array(),
				#	)
				#);
				#$kses->AddHTML('meta', array(
				#		'http-equiv' => array(),
				#		'content' => array(),
				#	)
				#);
				#$kses->AddHTML('link',array(
				#		'rel' => array(), // ="stylesheet"
				#		'type' => array(), //="text/css"
				#		'href' => array(),
				#		'media' => array(),
				#	)
				#);
				$kses->AddHTML(
					'p', array(
						'align'	=> array('minlen' =>   1, 'maxlen' =>  10)
					)
				);
				$kses->AddHTML("tbody");
				$kses->AddHTML("thead");
				$kses->AddHTML("tt");
				$kses->AddHTML("br");
				$kses->AddHTML("b");
				$kses->AddHTML("u");
				$kses->AddHTML("s");
				$kses->AddHTML("i");
				$kses->AddHTML('em');
				$kses->AddHTML("strong");
				$kses->AddHTML("strike");
				$kses->AddHTML("center");
				$kses->AddHTML(
					"font",array(
						"color"	=> array('maxlen' => 20),
						"size"=>array('maxlen'=>2)
					)
				);
				$kses->AddHTML(
					"hr",array(
						"class"		=> array('maxlen' => 20),
						"style"		=> array('minlen' => 1),
					)
				);
				$kses->AddHTML(
					"div",array(
				#		'class' => array(),
						'align' => array('maxlen' => 10)
					)
				);
				$kses->AddHTML("ul");
				$kses->AddHTML(
					"ol",array(
						"type"	=> array('maxlen' => 20)
					)
				);
				$kses->AddHTML("li");
				$kses->AddHTML("h1");
				$kses->AddHTML("h2");
				$kses->AddHTML("h3");
				$kses->AddHTML(
					"style",array(
						"type"	=> array('maxlen' => 20),
						"color"	=> array('maxlen' => 20),
						"background-color" => array('maxlen' => 20),
						"background" => array('maxlen' => 5),
					)
				);

				$kses->AddHTML("select");
				$kses->AddHTML(
					"option",array(
						"value" => array('maxlen' => 45),
						"selected" => array()
					)
				);

				$kses->AddHTML(
					"a", array(
						"href" 		=> array('maxlen' => 348, 'minlen' => 10),
						"name" 		=> array('minlen' => 2),
						'target'	=> array('maxlen' => 10)
					)
				);

				$kses->AddHTML(
					"pre", array(
						"wrap" => array('maxlen' => 10)
					)
				);

				//      Allows 'td' tag with colspan|rowspan|class|style|width|nowrap attributes,
				//              colspan has minval of   2       and maxval of 5
				//              rowspan has minval of   3       and maxval of 6
				//              class   has minlen of   1 char  and maxlen of   10 chars
				//              style   has minlen of   5 chars and maxlen of 100 chars
				//              width   has maxval of 100
				//              nowrap  is valueless
				$kses->AddHTML(
					"table",array(
						"class"   => array("minlen" =>   1, 'maxlen' =>  20),
						"border"   => array("minlen" =>   1, 'maxlen' =>  10),
						"cellpadding"   => array("minlen" =>   0, 'maxlen' =>  10),
						"cellspacing"   => array("minlen" =>   0, 'maxlen' =>  10),
						"width"   => array("maxlen" => 5),
						"style"   => array('minlen' =>  5, 'maxlen' => 100),
						"bgcolor"   => array('maxlen' =>  10),
						"align"   => array('maxlen' =>  10),
						"valign"   => array('maxlen' =>  10),
						"bordercolor"   => array('maxlen' =>  10)
					)
				);
				$kses->AddHTML(
					"tr",array(
						"colspan"	=> array('minval' =>   2, 'maxval' =>   5),
						"rowspan"	=> array('minval' =>   3, 'maxval' =>   6),
						"class"		=> array("minlen" =>   1, 'maxlen' =>  20),
						"width"		=> array("maxlen" => 5),
						"style"		=> array('minlen' =>  5, 'maxlen' => 100),
						"align"		=> array('maxlen' =>  10),
						'bgcolor'	=> array('maxlen' => 10),
						"valign"	=> array('maxlen' =>  10),
						"nowrap"	=> array('valueless' => 'y')
					)
				);
				$kses->AddHTML(
					"td",array(
						"colspan" => array('minval' =>   2, 'maxval' =>   5),
						"rowspan" => array('minval' =>   3, 'maxval' =>   6),
						"class"   => array("minlen" =>   1, 'maxlen' =>  20),
						"width"   => array("maxlen" => 5),
						"style"   => array('minlen' =>  5, 'maxlen' => 100),
						"align"   => array('maxlen' =>  10),
						'bgcolor' => array('maxlen' => 10),
						"valign"   => array('maxlen' =>  10),
						"nowrap"  => array('valueless' => 'y')
					)
				);
				$kses->AddHTML(
					"th",array(
						"colspan" => array('minval' =>   2, 'maxval' =>   5),
						"rowspan" => array('minval' =>   3, 'maxval' =>   6),
						"class"   => array("minlen" =>   1, 'maxlen' =>  20),
						"width"   => array("maxlen" => 5),
						"style"   => array('minlen' =>  5, 'maxlen' => 100),
						"align"   => array('maxlen' =>  10),
						"valign"   => array('maxlen' =>  10),
						"nowrap"  => array('valueless' => 'y')
					)
				);
				$kses->AddHTML(
					"span",array(
						"class"   => array("minlen" =>   1, 'maxlen' =>  20),
						"style"	  => array('minlen' =>  5, 'maxlen' => 100)
					)
				);
				$kses->AddHTML(
					"blockquote",array(
						"class"	=> array("minlen" =>   1, 'maxlen' =>  20),
						"style"	=> array("minlen" =>   1),
						"cite"	=> array('maxlen' => 30),
						"type"	=> array('maxlen' => 10),
						"dir"	=> array("minlen" =>   1, 'maxlen' =>  10)
					)
				);
				$kses->AddHTML(
					'img',array(
						"src"		=> array("minlen" =>   4, 'maxlen' =>  384, $GLOBALS['egw_info']['user']['preferences']['felamimail']['allowExternalIMGs'] ? '' : 'match' => '/^cid:.*/'),
						"align"		=> array("minlen" =>   1),
						"border"	=> array('maxlen' => 30),
						"width"		=> array("minlen" =>   1, 'maxlen' =>  3),
						"height"	=> array("minlen" =>   1, 'maxlen' =>  3),
					)
				);

				// no scripts allowed
				// clean out comments
				$search = array(
					'@<!--[\s\S]*?[ \t\n\r]*-->@',         // Strip multi-line comments including CDATA 
					'@url\(http:\/\/[^\)].*?\)@si',  // url calls e.g. in style definitions
				);
				//error_log(__METHOD__.$_html);
				$_html = preg_replace($search,"",$_html);
				// do the kses clean out first, to avoid general problems with content later on
				$_html = $kses->Parse($_html);
				// remove non printable chars
				$_html = preg_replace('/([\000-\012])/','',$_html);
				//error_log($_html);
			}
		}

		/**
		* replace emailaddresses enclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
		* always returns 1
		*/
		static function replaceEmailAdresses(&$text)
		{
			return translation::replaceEmailAdresses($text);
		}

		static function convertHTMLToText($_html,$stripcrl=false,$stripalltags=true)
		{
			return translation::convertHTMLToText($_html,self::$displayCharset,$stripcrl,$stripalltags);
		}

		/**
		* retrieve a attachment
		*
		* @param int _uid the uid of the message
		* @param string _partID the id of the part, which holds the attachment
		* @param int _winmail_nr winmail.dat attachment nr.
		*
		* @returns array
		*/
		function getAttachment($_uid, $_partID, $_winmail_nr=0)
		{
			// parse message structure
			$structure = $this->icServer->getStructure($_uid, true);
			if($_partID != '') {
				$structure = $this->_getSubStructure($structure, $_partID);
			}
			$filename = self::getFileNameFromStructure($structure);
			$attachment = $this->icServer->getBodyPart($_uid, $_partID, true);

			if (PEAR::isError($attachment))
			{
				error_log(__METHOD__.__LINE__.' failed:'.$attachment->message);
				return array('type' => 'text/plain',
							 'filename' => 'error.txt',
							 'attachment' =>__METHOD__.' failed:'.$attachment->message
						);
			}

			switch ($structure->encoding) {
				case 'BASE64':
					// use imap_base64 to decode
					$attachment = imap_base64($attachment);
					break;
				case 'QUOTED-PRINTABLE':
					// use imap_qprint to decode
					#$attachment = imap_qprint($attachment);
					$attachment = quoted_printable_decode($attachment);
					break;
				default:
					// it is either not encoded or we don't know about it
			}

			$attachmentData = array(
				'type'		=> $structure->type .'/'. $structure->subType,
				'filename'	=> $filename,
				'attachment'	=> $attachment
				);
			// try guessing the mimetype, if we get the application/octet-stream
			if (strtolower($attachmentData['type']) == 'application/octet-stream') $attachmentData['type'] = mime_magic::filename2mime($attachmentData['filename']);
			# if the attachment holds a winmail number and is a winmail.dat then we have to handle that.
			if ( $filename == 'winmail.dat' && $_winmail_nr > 0 &&
				( $wmattach = $this->decode_winmail( $_uid, $_partID, $_winmail_nr ) ) )
			{
				$attachmentData = array(
					'type'       => $wmattach['type'],
					'filename'   => $wmattach['name'],
					'attachment' => $wmattach['attachment'],
 				);
			}
			return $attachmentData;
		}

		// this function is based on a on "Building A PHP-Based Mail Client"
		// http://www.devshed.com
		// fetch a specific attachment from a message
		function getAttachmentByCID($_uid, $_cid, $_part)
		{
			// some static variables to avoid fetching the same mail multiple times
			static $uid,$part,$attachments,$structure;

			if ($_uid != $uid || $_part != $part)
			{
				$attachments = $this->getMessageAttachments($uid=$_uid, $part=$_part);
				$structure = null;
			}
			$partID = false;
			//list($cidname,$cidrest) = explode('@',$_cid,2);
			//error_log("getAttachmentByCID:$_uid ($cidname -> $cidrest), $_cid, $_part");
			foreach($attachments as $attachment)
			{
				//error_log(print_r($attachment,true));
				if(isset($attachment['cid']) && (strpos($attachment['cid'], $_cid) !== false || strpos($_cid, $attachment['cid']) !== false))
				{
					$partID = $attachment['partID'];
					break;
				}
			}
			if ($partID == false)
			{
				foreach($attachments as $attachment)
				{
					// if the former did not match try matching the cid to the name of the attachment
					if(isset($attachment['cid']) && isset($attachment['name']) && (strpos($attachment['name'], $_cid) !== false || strpos($_cid, $attachment['name']) !== false))
					{
						$partID = $attachment['partID'];
						break;
					}
				}
			}
			if ($partID == false)
			{
				foreach($attachments as $attachment)
				{
					// if the former did not match try matching the cid to the name of the attachment, AND there is no mathing attachment with cid
					if(isset($attachment['name']) && (strpos($attachment['name'], $_cid) !== false || strpos($_cid, $attachment['name']) !== false))
					{
						$partID = $attachment['partID'];
						break;
					}
				}
			}

			//error_log( "Cid:$_cid PARTID:$partID<bR>"); #exit;

			if($partID == false) {
				return false;
			}

			// parse message structure
			$structure = $this->icServer->getStructure($_uid, true);
			$structure = $this->_getSubStructure($structure, $partID);
			$filename = self::getFileNameFromStructure($structure);
			$attachment = $this->icServer->getBodyPart($_uid, $partID, true);

			if (PEAR::isError($attachment))
			{
				error_log(__METHOD__.__LINE__.' failed:'.$attachment->message);
				return array('type' => 'text/plain',
							 'filename' => 'error.txt',
							 'attachment' =>__METHOD__.' failed:'.$attachment->message
						);
			}

			switch ($structure->encoding) {
				case 'BASE64':
					// use imap_base64 to decode
					$attachment = imap_base64($attachment);
					break;
				case 'QUOTED-PRINTABLE':
					// use imap_qprint to decode
					#$attachment = imap_qprint($attachment);
					$attachment = quoted_printable_decode($attachment);
					break;
				default:
					// it is either not encoded or we don't know about it
			}

			$attachmentData = array(
				'type'		=> $structure->type .'/'. $structure->subType,
				'filename'	=> $filename,
				'attachment'	=> $attachment
			);
			// try guessing the mimetype, if we get the application/octet-stream
			if (strtolower($attachmentData['type']) == 'application/octet-stream') $attachmentData['type'] = mime_magic::filename2mime($attachmentData['filename']);
 
			return $attachmentData;
		}

		function getEMailProfile()
		{
			$config = CreateObject('phpgwapi.config','felamimail');
			$config->read_repository();
			$felamimailConfig = $config->config_data;

			#_debug_array($felamimailConfig);

			if(!isset($felamimailConfig['profileID'])){
				return -1;
			} else {
				return intval($felamimailConfig['profileID']);
			}
		}

		function getErrorMessage()
		{
			return $this->icServer->_connectionErrorObject->message;
		}

		/**
		* get IMAP folder status
		*
		* returns an array information about the imap folder
		*
		* @param _folderName string the foldername
		*
		* @returns array
		*/
		function getFolderStatus($_folderName)
		{
			if (self::$debug) error_log(__METHOD__." called with:".$_folderName);
			$retValue = array();
			$retValue['subscribed'] = false;
			if(!$icServer = $this->mailPreferences->getIncomingServer(0)) {
				return false;
			}

			// does the folder exist???
			$folderInfo = $this->icServer->getMailboxes('', $_folderName, true);
			if(is_a($folderInfo, 'PEAR_Error') || !is_array($folderInfo[0])) {
				if (self::$debug) error_log(__METHOD__." returned Info for folder $_folderName:".print_r($folderInfo->message,true));
				return false;
			}
			#if(!is_array($folderInfo[0])) {
			#	return false;
			#}

			$subscribedFolders = $this->icServer->listsubscribedMailboxes('', $_folderName);
			if(is_array($subscribedFolders) && count($subscribedFolders) == 1) {
				$retValue['subscribed'] = true;
			}

			$retValue['delimiter']		= $folderInfo[0]['HIERACHY_DELIMITER'];
			$retValue['attributes']		= $folderInfo[0]['ATTRIBUTES'];
			$shortNameParts			= explode($retValue['delimiter'], $_folderName);
			$retValue['shortName']		= array_pop($shortNameParts);
			$retValue['displayName']	= $this->encodeFolderName($_folderName);
			$retValue['shortDisplayName']	= $this->encodeFolderName($retValue['shortName']);
			if(strtoupper($retValue['shortName']) == 'INBOX') {
				$retValue['displayName']	= lang('INBOX');
				$retValue['shortDisplayName']	= lang('INBOX');
			}
			// translate the automatic Folders (Sent, Drafts, ...) like the INBOX
			elseif (in_array($retValue['shortName'],self::$autoFolders))
			{
				$retValue['displayName'] = $retValue['shortDisplayName'] = lang($retValue['shortName']);
			}

			if ( PEAR::isError($folderStatus = $this->icServer->getStatus($_folderName)) ) {
			/*if ($folderStatus = $this->bofelamimail->getMailBoxCounters($_folderName)) {
				$retValue['messages']	=	$folderStatus->messages;
				$retValue['recent']		=	$folderStatus->recent;
				$retValue['uidnext']	=	$folderStatus->uidnext;
				$retValue['unseen']		=	$folderStatus->unseen;
				$retValue['uidvalidity']=	$folderStatus->uidvalidity;
			*/
				//_debug_array($folderStatus);
				if (self::$debug) error_log(__METHOD__." returned folderStatus for Folder $_folderName:".print_r($folderStatus->message,true));
			} else {
				$retValue['messages']		= $folderStatus['MESSAGES'];
				$retValue['recent']		= $folderStatus['RECENT'];
				$retValue['uidnext']		= $folderStatus['UIDNEXT'];
				$retValue['uidvalidity']	= $folderStatus['UIDVALIDITY'];
				$retValue['unseen']		= $folderStatus['UNSEEN'];
			}

			return $retValue;
		}

		/**
		* get IMAP folder objects
		*
		* returns an array of IMAP folder objects. Put INBOX folder in first
		* position. Preserves the folder seperator for later use. The returned
		* array is indexed using the foldername.
		*
		* @param _subscribedOnly boolean get subscribed or all folders
		* @param _getCounters    boolean get get messages counters
		*
		* @returns array with folder objects. eg.: INBOX => {inbox object}
		*/
		function getFolderObjects($_subscribedOnly=false, $_getCounters=false)
		{
			$isUWIMAP = false;

			$delimiter = $this->getHierarchyDelimiter();

			$inboxData = new stdClass;
			$inboxData->name 		= 'INBOX';
			$inboxData->folderName		= 'INBOX';
			$inboxData->displayName		= lang('INBOX');
			$inboxData->delimiter 		= $delimiter;
			$inboxData->shortFolderName	= 'INBOX';
			$inboxData->shortDisplayName	= lang('INBOX');
			$inboxData->subscribed = true;
			if($_getCounters == true) {
				/*
				$folderStatus = $this->icServer->getStatus('INBOX');

				$status =  new stdClass;
				$status->messages	= $folderStatus['MESSAGES'];
				$status->unseen		= $folderStatus['UNSEEN'];
				$status->recent		= $folderStatus['RECENT'];

				$inboxData->counter	= $status;
				*/
				$inboxData->counter = self::getMailBoxCounters('INBOX');
			}
			// force unsubscribed by preference showAllFoldersInFolderPane
			if ($_subscribedOnly == true && 
				isset($this->mailPreferences->preferences['showAllFoldersInFolderPane']) && 
				$this->mailPreferences->preferences['showAllFoldersInFolderPane']==1)
			{
				$_subscribedOnly = false;
			} 
			#$inboxData->attributes = 64;
			$inboxFolderObject = array('INBOX' => $inboxData);
			#_debug_array($folders);

			$nameSpace = $this->icServer->getNameSpaces();
			#_debug_array($nameSpace);
			#_debug_array($delimiter);
			if(isset($nameSpace['#mh/'])) {
				// removed the uwimap code
				// but we need to reintroduce him later
				// uw imap does not return the attribute of a folder, when requesting subscribed folders only
				// dovecot has the same problem too
			} else {
				if (is_array($nameSpace)) {
				  foreach($nameSpace as $type => $singleNameSpace) {
					if($type == 'personal' && ($singleNameSpace[2]['name'] == '#mh/' || count($nameSpace) == 1) && $this->icServer->mailboxExist('Mail')) {
						// uw-imap server with mailbox prefix or dovecot maybe
						$foldersNameSpace[$type]['prefix'] = 'Mail';
					} elseif($type == 'personal' && ($singleNameSpace[2]['name'] == '#mh/' || count($nameSpace) == 1) && $this->icServer->mailboxExist('mail')) {
						// uw-imap server with mailbox prefix or dovecot maybe
						$foldersNameSpace[$type]['prefix'] = 'mail';
					} else {
						$foldersNameSpace[$type]['prefix'] = $singleNameSpace[0]['name'];
					}
					#echo "############## ".print_r($singleNameSpace,true)." ###################<br>";
					$foldersNameSpace[$type]['delimiter'] = $delimiter;

					if(is_array($singleNameSpace[0])) {
						// fetch and sort the subscribed folders
						$subscribedMailboxes = $this->icServer->listsubscribedMailboxes($foldersNameSpace[$type]['prefix']);
						if (empty($subscribedMailboxes) && $type == 'shared') $subscribedMailboxes = $this->icServer->listsubscribedMailboxes('',0);

						#echo "subscribedMailboxes";_debug_array($subscribedMailboxes);
						if( PEAR::isError($subscribedMailboxes) ) {
							continue;
						}
						$foldersNameSpace[$type]['subscribed'] = $subscribedMailboxes;
						if (is_array($foldersNameSpace[$type]['subscribed'])) sort($foldersNameSpace[$type]['subscribed']);
						#_debug_array($foldersNameSpace);
						if ($_subscribedOnly == true) {
							$foldersNameSpace[$type]['all'] = (is_array($foldersNameSpace[$type]['subscribed']) ? $foldersNameSpace[$type]['subscribed'] :array());
							continue;
						}
						// only check for Folder in FolderMaintenance for Performance Reasons
						if(!$_subscribedOnly) {
							foreach ((array)$foldersNameSpace[$type]['subscribed'] as $folderName)
							{
								//echo __METHOD__."Checking $folderName for existence<br>";
								if (!self::folderExists($folderName)) {
									echo("eMail Folder $folderName failed to exist; should be unsubscribed; Trying ...");
									error_log(__METHOD__."-> $folderName failed to be here; should be unsubscribed");
									if (self::subscribe($folderName, false))
									{
										echo " success."."<br>" ;
									} else {
										echo " failed."."<br>";
									}
								}
							}
						}

						// fetch and sort all folders
						#echo $type.'->'.$foldersNameSpace[$type]['prefix'].'->'.($type=='shared'?0:2)."<br>";
						$allMailboxesExt = $this->icServer->getMailboxes($foldersNameSpace[$type]['prefix'],2,true);
						if (empty($allMailboxesExt) && $type == 'shared')  $allMailboxesExt = $this->icServer->getMailboxes('',0,true);
						if( PEAR::isError($allMailboxesExt) ) {
							#echo __METHOD__;_debug_array($allMailboxesExt);
							continue;
						}
						foreach ($allMailboxesExt as $mbx) {
							#echo __METHOD__;_debug_array($mbx);
							$allMailBoxesExtSorted[$mbx['MAILBOX']] = $mbx;
						}
						if (is_array($allMailBoxesExtSorted)) ksort($allMailBoxesExtSorted);
						#_debug_array($allMailBoxesExtSorted);
						$allMailboxes = array();
						foreach ((array)$allMailBoxesExtSorted as $mbx) {
							#echo $mbx['MAILBOX']."<br>";
							if (in_array('\HasChildren',$mbx["ATTRIBUTES"]) || in_array('\Haschildren',$mbx["ATTRIBUTES"])) {
								unset($buff);
								//$buff = $this->icServer->getMailboxes($mbx['MAILBOX'].$delimiter,0,false);
								if (!in_array($mbx['MAILBOX'],$allMailboxes)) $buff = self::getMailBoxesRecursive($mbx['MAILBOX'],$delimiter,$foldersNameSpace[$type]['prefix'],1);
								if( PEAR::isError($buff) ) {
									continue;
								}
								#_debug_array($buff);
								if (is_array($buff)) $allMailboxes = array_merge($allMailboxes,$buff);
							}
							if (!in_array($mbx['MAILBOX'],$allMailboxes)) $allMailboxes[] = $mbx['MAILBOX'];
							#echo "Result:";_debug_array($allMailboxes);
						}
						$foldersNameSpace[$type]['all'] = $allMailboxes;
						if (is_array($foldersNameSpace[$type]['all'])) sort($foldersNameSpace[$type]['all']);
				    }
				  }
				}
				// check for autocreated folders
				if(isset($foldersNameSpace['personal']['prefix'])) {
					$personalPrefix = $foldersNameSpace['personal']['prefix'];
					$personalDelimiter = $foldersNameSpace['personal']['delimiter'];
					if(!empty($personalPrefix)) {
						if(substr($personalPrefix, -1) != $personalDelimiter) {
							$folderPrefix = $personalPrefix . $personalDelimiter;
						} else {
							$folderPrefix = $personalPrefix;
						}
					}
					if ($this->mailPreferences->preferences['notavailableautofolders'] && !empty($this->mailPreferences->preferences['notavailableautofolders']))
					{
						$foldersToCheck = array_diff(self::$autoFolders,explode(',',$this->mailPreferences->preferences['notavailableautofolders']));
					} else {
						$foldersToCheck = self::$autoFolders;
					}
					#echo "foldersToCheck:";_debug_array($foldersToCheck);
					foreach($foldersToCheck as $personalFolderName) {
						$folderName = (!empty($personalPrefix)) ? $folderPrefix.$personalFolderName : $personalFolderName;
						if(!is_array($foldersNameSpace['personal']['all']) || !in_array($folderName, $foldersNameSpace['personal']['all'])) {
							$createfolder = true;
							switch($folderName)
							{
								case 'Drafts': // => Entwürfe
									if ($this->mailPreferences->preferences['draftFolder'] && $this->mailPreferences->preferences['draftFolder']=='none')
										$createfolder=false;
									break;
								case 'Junk': //] => Spammails
									if ($this->mailPreferences->preferences['junkFolder'] && $this->mailPreferences->preferences['junkFolder']=='none')
										$createfolder=false;
									break;
								case 'Sent': //] => Gesendet
									if ($this->mailPreferences->preferences['sentFolder'] && $this->mailPreferences->preferences['sentFolder']=='none')
										$createfolder=false;
									break;
								case 'Trash': //] => Papierkorb
									if ($this->mailPreferences->preferences['trashFolder'] && $this->mailPreferences->preferences['trashFolder']=='none')
										$createfolder=false;
									break;
								case 'Templates': //] => Vorlagen
									if ($this->mailPreferences->preferences['templateFolder'] && $this->mailPreferences->preferences['templateFolder']=='none')
										$createfolder=false;
									break;
							}
							if($createfolder === true && $this->createFolder('', $folderName, true)) {
								$foldersNameSpace['personal']['all'][] = $folderName;
								$foldersNameSpace['personal']['subscribed'][] = $folderName;
							} else {
								#print "FOLDERNAME failed: $folderName<br>";
							}
						}
					}
				}
			}
			#echo "<br>FolderNameSpace To Process:";_debug_array($foldersNameSpace);
			foreach( array('personal', 'others', 'shared') as $type) {
				if(isset($foldersNameSpace[$type])) {
					if($_subscribedOnly) {
						if( !PEAR::isError($foldersNameSpace[$type]['subscribed']) ) $listOfFolders = $foldersNameSpace[$type]['subscribed'];
					} else {
						if( !PEAR::isError($foldersNameSpace[$type]['all'])) $listOfFolders = $foldersNameSpace[$type]['all'];
					}
					foreach((array)$listOfFolders as $folderName) {
						//echo "<br>FolderToCheck:$folderName<br>";
						if($_subscribedOnly && !in_array($folderName, $foldersNameSpace[$type]['all'])) {
							#echo "$folderName failed to be here <br>";
							continue;
						}
						$folderParts = explode($delimiter, $folderName);
						$shortName = array_pop($folderParts);

						$folderObject = new stdClass;
						$folderObject->delimiter	= $delimiter;
						$folderObject->folderName	= $folderName;
						$folderObject->shortFolderName	= $shortName;
						if(!$_subscribedOnly) {
							#echo $folderName."->".$type."<br>";
							#_debug_array($foldersNameSpace[$type]['subscribed']);
							$folderObject->subscribed = in_array($folderName, $foldersNameSpace[$type]['subscribed']);
						}

						if($_getCounters == true) {
							/*
							$folderStatus = $this->icServer->getStatus($folderName);
							#echo "<br> FolderStatus:";_debug_array($folderStatus);
							if(is_array($folderStatus)) {
								$status =  new stdClass;
								$status->messages	= $folderStatus['MESSAGES'];
								$status->unseen		= $folderStatus['UNSEEN'];
								$status->recent 	= $folderStatus['RECENT'];

								$folderObject->counter = $status;
							}
							*/
							$folderObject->counter = $this->bofelamimail->getMailBoxCounters($folderName);
						}

						if(strtoupper($folderName) == 'INBOX') {
							$folderName = 'INBOX';
							$folderObject->folderName	= 'INBOX';
							$folderObject->shortFolderName	= 'INBOX';
							$folderObject->displayName	= lang('INBOX');
							$folderObject->shortDisplayName = lang('INBOX');
							$folderObject->subscribed	= true;

						// translate the automatic Folders (Sent, Drafts, ...) like the INBOX
						} elseif (in_array($shortName,self::$autoFolders)) {
							$tmpfolderparts = explode($delimiter,$folderObject->folderName);
							array_pop($tmpfolderparts);
							$folderObject->displayName = implode($delimiter,$tmpfolderparts).$delimiter.lang($shortName);
							$folderObject->shortDisplayName = lang($shortName);
							unset($tmpfolderparts);
						} else {
							$folderObject->displayName = $this->encodeFolderName($folderObject->folderName);
							$folderObject->shortDisplayName = $this->encodeFolderName($shortName);
						}
						$folderName = $folderName;
						if (in_array($shortName,self::$autoFolders)) {
							$autoFolderObjects[$folderName] = $folderObject;
						} else {
							$folders[$folderName] = $folderObject;
						}
					}
				}
			}
			if (is_array($autoFolderObjects)) {
				uasort($autoFolderObjects,array($this,"sortByAutoFolderPos"));
			}
			if (is_array($folders)) uasort($folders,array($this,"sortByDisplayName"));
			//$folders2return = array_merge($autoFolderObjects,$folders);
			//_debug_array($folders2return); #exit;
			return array_merge($inboxFolderObject,(array)$autoFolderObjects,(array)$folders);
		}

		function sortByDisplayName($a,$b)
		{
			// 0, 1 und -1
			return strcasecmp($a->displayName,$b->displayName);
		}

		function sortByAutoFolderPos($a,$b)
		{
			// 0, 1 und -1
			$pos1 = array_search($a->shortFolderName,self::$autoFolders);
			$pos2 = array_search($b->shortFolderName,self::$autoFolders);
			if ($pos1 == $pos2) return 0;
			return ($pos1 < $pos2) ? -1 : 1;
		}

		function getMailBoxCounters($folderName)
		{
			$folderStatus = $this->icServer->getStatus($folderName);
			#echo "<br> FolderStatus:";_debug_array($folderStatus);
			if ( PEAR::isError($folderStatus)) {
				if (self::$debug) error_log(__METHOD__." returned FolderStatus for Folder $folderName:".print_r($folderStatus->message,true));
				return false;
			}
			if(is_array($folderStatus)) {
				$status =  new stdClass;
				$status->messages   = $folderStatus['MESSAGES'];
				$status->unseen     = $folderStatus['UNSEEN'];
				$status->recent     = $folderStatus['RECENT'];
				$status->uidnext        = $folderStatus['UIDNEXT'];
				$status->uidvalidity    = $folderStatus['UIDVALIDITY'];

				return $status;
			}
			return false;
		}

		function getMailBoxesRecursive($_mailbox, $delimiter, $prefix, $reclevel=0)
		{
			#echo __METHOD__." retrieve SubFolders for $_mailbox$delimiter <br>";
			$maxreclevel=25;
			if ($reclevel > $maxreclevel) {
				error_log( __METHOD__." Recursion Level Exeeded ($reclevel) while looking up $_mailbox$delimiter ");
				return array();
			}
			$reclevel++;
			// clean up douple delimiters
			$_mailbox = preg_replace('~'.($delimiter == '.' ? "\\".$delimiter:$delimiter).'+~s',$delimiter,$_mailbox);
			//get that mailbox in question
			$mbx = $this->icServer->getMailboxes($_mailbox,1,true);
			#_debug_array($mbx);
			if (is_array($mbx[0]["ATTRIBUTES"]) && (in_array('\HasChildren',$mbx[0]["ATTRIBUTES"]) || in_array('\Haschildren',$mbx[0]["ATTRIBUTES"]))) {
				// if there are children fetch them
				#echo $mbx[0]['MAILBOX']."<br>";
				unset($buff);
				$buff = $this->icServer->getMailboxes($mbx[0]['MAILBOX'].($mbx[0]['MAILBOX'] == $prefix ? '':$delimiter),2,false);
				//$buff = $this->icServer->getMailboxes($mbx[0]['MAILBOX'],2,false);
				#_debug_array($buff);
				if( PEAR::isError($buff) ) {
					if (self::$debug) error_log(__METHOD__." Error while retrieving Mailboxes for:".$mbx[0]['MAILBOX'].$delimiter.".");
					return array();
				} else {
					$allMailboxes = array();
					foreach ($buff as $mbxname) {
						$mbxname = preg_replace('~'.($delimiter == '.' ? "\\".$delimiter:$delimiter).'+~s',$delimiter,$mbxname);
						#echo "About to recur in level $reclevel:".$mbxname."<br>";
						if ( $mbxname != $mbx[0]['MAILBOX'] && $mbxname != $prefix) $allMailboxes = array_merge($allMailboxes, self::getMailBoxesRecursive($mbxname, $delimiter, $prefix, $reclevel));
					}
					if (!(in_array('\NoSelect',$mbx[0]["ATTRIBUTES"]) || in_array('\Noselect',$mbx[0]["ATTRIBUTES"]))) $allMailboxes[] = $mbx[0]['MAILBOX'];
					return $allMailboxes;
				}
			} else {
				return array($_mailbox);
			}
		}

		function getMimePartCharset($_mimePartObject)
		{
			$charSet = 'iso-8859-1';

			if(is_array($_mimePartObject->parameters)) {
				if(isset($_mimePartObject->parameters['CHARSET'])) {
					$charSet = $_mimePartObject->parameters['CHARSET'];
				}
			}

			return $charSet;
		}

		function getMultipartAlternative($_uid, $_structure, $_htmlMode)
		{
			// a multipart/alternative has exactly 2 parts (text and html  OR  text and something else)

			$partText = false;
			$partHTML = false;
			if (self::$debug) _debug_array(array("METHOD"=>__METHOD__,"LINE"=>__LINE__,"STRUCTURE"=>$_structure));
			foreach($_structure as $mimePart) {
				if($mimePart->type == 'TEXT' && $mimePart->subType == 'PLAIN' && $mimePart->bytes > 0) {
					$partText = $mimePart;
				} elseif($mimePart->type == 'TEXT' && $mimePart->subType == 'HTML' && $mimePart->bytes > 0) {
					$partHTML = $mimePart;
				} elseif ($mimePart->type == 'MULTIPART' && $mimePart->subType == 'RELATED' && is_array($mimePart->subParts)) {
					// in a multipart alternative we treat the multipart/related as html part
					#$partHTML = array($mimePart);
					error_log(__METHOD__." process MULTIPART/RELATED with array as subparts");
					$partHTML = $mimePart;
				} elseif ($mimePart->type == 'MULTIPART' && $mimePart->subType == 'ALTERNATIVE' && is_array($mimePart->subParts)) {
					//cascading multipartAlternative structure, assuming only the first one is to be used
					return $this->getMultipartAlternative($_uid,$mimePart->subParts,$_htmlMode);
				}
			}

			switch($_htmlMode) {
				case 'always_display':
					if(is_object($partHTML)) {
						if($partHTML->subType == 'RELATED') {
							return $this->getMultipartRelated($_uid, $partHTML, 'always_display');
						} else {
							return $this->getTextPart($_uid, $partHTML, 'always_display');
						}
					} elseif(is_object($partText)) {
						return $this->getTextPart($_uid, $partText);
					}

					break;
				case 'only_if_no_text':
					if(is_object($partText)) {
						return $this->getTextPart($_uid, $partText);
					} elseif(is_object($partHTML)) {
						if($partHTML->type) {
							return $this->getMultipartRelated($_uid, $partHTML, $_htmlMode);
						} else {
							return $this->getTextPart($_uid, $partHTML, 'always_display');
						}
					}

					break;

				default:
					if(is_object($partText)) {
						return $this->getTextPart($_uid, $partText);
					} else {
						$bodyPart = array(
							'body'		=> lang("no plain text part found"),
							'mimeType'	=> 'text/plain',
							'charSet'	=> self::$displayCharset,
						);
					}

					break;
			}

			return $bodyPart;
		}

		function getMultipartMixed($_uid, $_structure, $_htmlMode)
		{
			if (self::$debug) echo __METHOD__."$_uid, $_htmlMode<br>";
			$bodyPart = array();
			if (self::$debug) _debug_array($_structure); 
			if (!is_array($_structure)) $_structure = array($_structure);
			foreach($_structure as $part) {
				if (self::$debug) echo $part->type."/".$part->subType."<br>";
				switch($part->type) {
					case 'MULTIPART':
						switch($part->subType) {
							case 'ALTERNATIVE':
								$bodyPart[] = $this->getMultipartAlternative($_uid, $part->subParts, $_htmlMode);
								break;

							case 'MIXED':
							case 'SIGNED':
								$bodyPart = array_merge($bodyPart, $this->getMultipartMixed($_uid, $part->subParts, $_htmlMode));
								break;

							case 'RELATED':
								$bodyPart = array_merge($bodyPart, $this->getMultipartRelated($_uid, $part->subParts, $_htmlMode));
								break;
						}
						break;

					case 'TEXT':
						switch($part->subType) {
							case 'PLAIN':
							case 'HTML':
								if($part->disposition != 'ATTACHMENT') {
									$bodyPart[] = $this->getTextPart($_uid, $part, $_htmlMode);
								}
								break;
						}
						break;

					case 'MESSAGE':
						if($part->subType == 'delivery-status') {
							$bodyPart[] = $this->getTextPart($_uid, $part);
						}
						break;

					default:
						// do nothing
						// the part is a attachment
						#$bodyPart[] = $this->getMessageBody($_uid, $_htmlMode, $part->partID, $part);
						#if (!($part->type == 'TEXT' && ($part->subType == 'PLAIN' || $part->subType == 'HTML'))) {
						#	$bodyPart[] = $this->getMessageAttachments($_uid, $part->partID, $part);
						#}
				}
			}

			return $bodyPart;
		}

		function getMultipartRelated($_uid, $_structure, $_htmlMode)
		{
			return $this->getMultipartMixed($_uid, $_structure, $_htmlMode);
		}

		function getTextPart($_uid, $_structure, $_htmlMode = '')
		{
			$bodyPart = array();
			#_debug_array($_structure);
			$partID = $_structure->partID;
			$mimePartBody = $this->icServer->getBodyPart($_uid, $partID, true);
			if (PEAR::isError($mimePartBody))
			{
				error_log(__METHOD__.__LINE__.' failed:'.$mimePartBody->message);
				return false;
			}
			//_debug_array($mimePartBody);
			//_debug_array(preg_replace('/PropertyFile___$/','',$this->decodeMimePart($mimePartBody, $_structure->encoding)));
			if($_structure->subType == 'HTML' && $_htmlMode != 'always_display'  && $_htmlMode != 'only_if_no_text') {
				$bodyPart = array(
					'body'		=> lang("displaying html messages is disabled"),
					'mimeType'	=> 'text/html',
					'charSet'	=> self::$displayCharset,
				);
			} else {
				// some Servers append PropertyFile___ ; strip that here for display
				$bodyPart = array(
					'body'		=> preg_replace('/PropertyFile___$/','',$this->decodeMimePart($mimePartBody, $_structure->encoding, $this->getMimePartCharset($_structure))),
					'mimeType'	=> ($_structure->type == 'TEXT' && $_structure->subType == 'HTML') ? 'text/html' : 'text/plain',
					'charSet'	=> $this->getMimePartCharset($_structure),
				);
			}
			//_debug_array($bodyPart);
			return $bodyPart;
		}

		function getNameSpace($_icServer)
		{
			$this->icServer->getNameSpaces();
		}

		function getHierarchyDelimiter()
		{
			$HierarchyDelimiter = '/';
			if(is_a($this->icServer,'defaultimap')) 
			{
				$HierarchyDelimiter = $this->icServer->getHierarchyDelimiter();
				if (PEAR::isError($HierarchyDelimiter)) $HierarchyDelimiter = '/';
			}
			return $HierarchyDelimiter;
		}

		/**
		* fetches a sorted list of messages from the imap server
		* private function
		*
		* @todo implement sort based on Net_IMAP
		* @param string $_folderName the name of the folder in which the messages get searched
		* @param integer $_sort the primary sort key
		* @param bool $_reverse sort the messages ascending or descending
		* @param array $_filter the search filter
		* @return bool
		*/
		function getSortedList($_folderName, $_sort, $_reverse, $_filter)
		{
			if(PEAR::isError($folderStatus = $this->icServer->examineMailbox($_folderName))) {
				return false;
			}
			if(is_array($this->sessionData['folderStatus'][0][$_folderName]) &&
				$this->sessionData['folderStatus'][0][$_folderName]['uidValidity']	=== $folderStatus['UIDVALIDITY'] &&
				$this->sessionData['folderStatus'][0][$_folderName]['messages']	=== $folderStatus['EXISTS'] &&
				$this->sessionData['folderStatus'][0][$_folderName]['uidnext']	=== $folderStatus['UIDNEXT'] &&
				$this->sessionData['folderStatus'][0][$_folderName]['filter']	=== $_filter &&
				$this->sessionData['folderStatus'][0][$_folderName]['sort']	=== $_sort &&
				!empty($this->sessionData['folderStatus'][0][$_folderName]['sortResult'])
			) {
				if (self::$debug) error_log(__METHOD__." USE CACHE");
				$sortResult = $this->sessionData['folderStatus'][0][$_folderName]['sortResult'];

			} else {
				if (self::$debug) error_log(__METHOD__." USE NO CACHE");
				$filter = $this->createIMAPFilter($_folderName, $_filter);
				if($this->icServer->hasCapability('SORT')) {
					if (self::$debug) error_log(__METHOD__." Mailserver has SORT Capability");
					$sortOrder = $this->_getSortString($_sort);
					if (!empty(self::$displayCharset)) {
						$sortResult = $this->icServer->sort($sortOrder, strtoupper( self::$displayCharset ), $filter, true);
					}
					if (PEAR::isError($sortResult) || empty(self::$displayCharset)) {
						$sortResult = $this->icServer->sort($sortOrder, 'US-ASCII', $filter, true);
						// if there is an PEAR Error, we assume that the server is not capable of sorting
						if (PEAR::isError($sortResult)) {
							$advFilter = 'CHARSET '. strtoupper(self::$displayCharset) .' '.$filter;
							if (PEAR::isError($sortResult)) 
							{
								$sortResult = $this->icServer->search($filter, false);
								if (PEAR::isError($sortResult))
								{
									$sortResult = $this->sessionData['folderStatus'][0][$_folderName]['sortResult'];
								}
							}
						}
					}
					if (self::$debug) error_log(__METHOD__.print_r($sortResult,true));
				} else {
					if (self::$debug) error_log(__METHOD__." Mailserver has NO SORT Capability");
					$advFilter = 'CHARSET '. strtoupper(self::$displayCharset) .' '.$filter;
					$sortResult = $this->icServer->search($advFilter, true);
					if (PEAR::isError($sortResult)) $sortResult = $this->icServer->search($filter, true);
					if(is_array($sortResult)) {
							sort($sortResult, SORT_NUMERIC);
					}
					if (self::$debug) error_log(__METHOD__." using Filter:".print_r($filter,true)." ->".print_r($sortResult,true));
				}

				$this->sessionData['folderStatus'][0][$_folderName]['uidValidity'] = $folderStatus['UIDVALIDITY'];
				$this->sessionData['folderStatus'][0][$_folderName]['messages']	= $folderStatus['EXISTS'];
				$this->sessionData['folderStatus'][0][$_folderName]['uidnext']	= $folderStatus['UIDNEXT'];
				$this->sessionData['folderStatus'][0][$_folderName]['filter']	= $_filter;
				$this->sessionData['folderStatus'][0][$_folderName]['sortResult'] = $sortResult;
				$this->sessionData['folderStatus'][0][$_folderName]['sort']	= $_sort;
			}
			$this->sessionData['folderStatus'][0][$_folderName]['reverse'] 	= $_reverse;
			$this->saveSessionData();

			return $sortResult;
		}

		function getMessageEnvelope($_uid, $_partID = '',$decode=false)
		{
			if($_partID == '') {
				if( PEAR::isError($envelope = $this->icServer->getEnvelope('', $_uid, true)) ) {
					return false;
				}

				return ($decode ? self::decode_header($envelope[0]): $envelope[0]);
			} else {
				if( PEAR::isError($headers = $this->icServer->getParsedHeaders($_uid, true, $_partID, true)) ) {
					return false;
				}

				#_debug_array($headers);
				$newData = array(
					'DATE'		=> $headers['DATE'],
					'SUBJECT'	=> ($decode ? self::decode_header($headers['SUBJECT']):$headers['SUBJECT']),
					'MESSAGE_ID'	=> $headers['MESSAGE-ID']
				);

				$recepientList = array('FROM', 'TO', 'CC', 'BCC', 'SENDER', 'REPLY_TO');
				foreach($recepientList as $recepientType) {
					if(isset($headers[$recepientType])) {
						if ($decode) $headers[$recepientType] =  self::decode_header($headers[$recepientType]);
						$addresses = imap_rfc822_parse_adrlist($headers[$recepientType], '');
						foreach($addresses as $singleAddress) {
							$addressData = array(
								'PERSONAL_NAME'		=> $singleAddress->personal ? $singleAddress->personal : 'NIL',
								'AT_DOMAIN_LIST'	=> $singleAddress->adl ? $singleAddress->adl : 'NIL',
								'MAILBOX_NAME'		=> $singleAddress->mailbox ? $singleAddress->mailbox : 'NIL',
								'HOST_NAME'		=> $singleAddress->host ? $singleAddress->host : 'NIL',
								'EMAIL'			=> $singleAddress->host ? $singleAddress->mailbox.'@'.$singleAddress->host : $singleAddress->mailbox,
							);
							if($addressData['PERSONAL_NAME'] != 'NIL') {
								$addressData['RFC822_EMAIL'] = imap_rfc822_write_address($singleAddress->mailbox, $singleAddress->host, $singleAddress->personal);
							} else {
								$addressData['RFC822_EMAIL'] = 'NIL';
							}
							$newData[$recepientType][] = $addressData;
						}
					} else {
						if($recepientType == 'SENDER' || $recepientType == 'REPLY_TO') {
							$newData[$recepientType] = $newData['FROM'];
						} else {
							$newData[$recepientType] = array();
						}
					}
				}
				#_debug_array($newData);

				return $newData;
			}
		}

		function getHeaders($_folderName, $_startMessage, $_numberOfMessages, $_sort, $_reverse, $_filter, $_thisUIDOnly=null)
		{
			$reverse = (bool)$_reverse;
			// get the list of messages to fetch
			$this->reopen($_folderName);
			//$this->icServer->selectMailbox($_folderName);

			#print "<pre>";
			#$this->icServer->setDebug(true);
			if ($_thisUIDOnly === null)
			{
				$sortResult = $this->getSortedList($_folderName, $_sort, $_reverse, $_filter);
				#$this->icServer->setDebug(false);
				#print "</pre>";
				// nothing found
				if(!is_array($sortResult) || empty($sortResult)) {
					$retValue = array();
					$retValue['info']['total']	= 0;
					$retValue['info']['first']	= 0;
					$retValue['info']['last']	= 0;
					return $retValue;
				}

				$total = count($sortResult);
				#_debug_array($sortResult);
				#_debug_array(array_slice($sortResult, -5, -2));
				#error_log("REVERSE: $reverse");
				if($reverse === true) {
					$startMessage = $_startMessage-1;
					if($startMessage > 0) {
						$sortResult = array_slice($sortResult, -($_numberOfMessages+$startMessage), -$startMessage);
					} else {
						$sortResult = array_slice($sortResult, -($_numberOfMessages+($_startMessage-1)));
					}
					$sortResult = array_reverse($sortResult);
				} else {
					$sortResult = array_slice($sortResult, $_startMessage-1, $_numberOfMessages);
				}
			}
			else
			{
				$sortResult = (is_array($_thisUIDOnly) ? $_thisUIDOnly:(array)$_thisUIDOnly);
			}

			$queryString = implode(',', $sortResult);
			// fetch the data for the selected messages
			$headersNew = $this->icServer->getSummary($queryString, true);

			$count = 0;

			foreach((array)$sortResult as $uid) {
				$sortOrder[$uid] = $count++;
			}

			$count = 0;
			if (is_array($headersNew)) {
				foreach((array)$headersNew as $headerObject) {
					//if($count == 0) error_log(__METHOD__.array2string($headerObject));
					if (empty($headerObject['UID'])) continue;
					$uid = $headerObject['UID'];
					// make dates like "Mon, 23 Apr 2007 10:11:06 UT" working with strtotime
					if(substr($headerObject['DATE'],-2) === 'UT') {
						$headerObject['DATE'] .= 'C';
					}

					$retValue['header'][$sortOrder[$uid]]['subject']	= $this->decode_subject($headerObject['SUBJECT']);
					$retValue['header'][$sortOrder[$uid]]['size'] 		= $headerObject['SIZE'];
					$retValue['header'][$sortOrder[$uid]]['date']		= self::_strtotime($headerObject['DATE'],'ts',true);
					$retValue['header'][$sortOrder[$uid]]['mimetype']	= $headerObject['MIMETYPE'];
					$retValue['header'][$sortOrder[$uid]]['id']		= $headerObject['MSG_NUM'];
					$retValue['header'][$sortOrder[$uid]]['uid']		= $headerObject['UID'];
					$retValue['header'][$sortOrder[$uid]]['priority']		= ($headerObject['PRIORITY']?$headerObject['PRIORITY']:3);
					if (is_array($headerObject['FLAGS'])) {
						$retValue['header'][$sortOrder[$uid]]['recent']		= in_array('\\Recent', $headerObject['FLAGS']);
						$retValue['header'][$sortOrder[$uid]]['flagged']	= in_array('\\Flagged', $headerObject['FLAGS']);
						$retValue['header'][$sortOrder[$uid]]['answered']	= in_array('\\Answered', $headerObject['FLAGS']);
						$retValue['header'][$sortOrder[$uid]]['forwarded']   = in_array('$Forwarded', $headerObject['FLAGS']);
						$retValue['header'][$sortOrder[$uid]]['deleted']	= in_array('\\Deleted', $headerObject['FLAGS']);
						$retValue['header'][$sortOrder[$uid]]['seen']		= in_array('\\Seen', $headerObject['FLAGS']);
						$retValue['header'][$sortOrder[$uid]]['draft']		= in_array('\\Draft', $headerObject['FLAGS']);
						$retValue['header'][$sortOrder[$uid]]['mdnsent']	= in_array('MDNSent', $headerObject['FLAGS']);
						$retValue['header'][$sortOrder[$uid]]['mdnnotsent']	= in_array('MDNnotSent', $headerObject['FLAGS']);
					}
					if(is_array($headerObject['FROM']) && is_array($headerObject['FROM'][0])) {
						if($headerObject['FROM'][0]['HOST_NAME'] != 'NIL') {
							$retValue['header'][$sortOrder[$uid]]['sender_address'] = self::decode_header($headerObject['FROM'][0]['EMAIL']);
						} else {
							$retValue['header'][$sortOrder[$uid]]['sender_address'] = self::decode_header($headerObject['FROM'][0]['MAILBOX_NAME']);
						}
						if($headerObject['FROM'][0]['PERSONAL_NAME'] != 'NIL') {
							$retValue['header'][$sortOrder[$uid]]['sender_name'] = self::decode_header($headerObject['FROM'][0]['PERSONAL_NAME']);
						}

					}

					if(is_array($headerObject['TO']) && is_array($headerObject['TO'][0])) {
						if($headerObject['TO'][0]['HOST_NAME'] != 'NIL') {
							$retValue['header'][$sortOrder[$uid]]['to_address'] = self::decode_header($headerObject['TO'][0]['EMAIL']);
						} else {
							$retValue['header'][$sortOrder[$uid]]['to_address'] = self::decode_header($headerObject['TO'][0]['MAILBOX_NAME']);
						}
						if($headerObject['TO'][0]['PERSONAL_NAME'] != 'NIL') {
							$retValue['header'][$sortOrder[$uid]]['to_name'] = self::decode_header($headerObject['TO'][0]['PERSONAL_NAME']);
						}

					}

					$count++;
				}

				// sort the messages to the requested displayorder
				if(is_array($retValue['header'])) {
					ksort($retValue['header']);
					$retValue['info']['total']	= $total;
					$retValue['info']['first']	= $_startMessage;
					$retValue['info']['last']	= $_startMessage + $count - 1 ;
					return $retValue;
				} else {
					$retValue = array();
					$retValue['info']['total']	= 0;
					$retValue['info']['first']	= 0;
					$retValue['info']['last']	= 0;
					return $retValue;
				}
			} else {
				error_log("bofelamimail::getHeaders -> retrieval of Message Details failed: ".print_r($headersNew,TRUE));
				$retValue = array();
				$retValue['info']['total']  = 0;
				$retValue['info']['first']  = 0;
				$retValue['info']['last']   = 0;
				return $retValue;
			}
		}

		function getNextMessage($_foldername, $_id)
		{
			#_debug_array($this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult']);
			#_debug_array($this->sessionData['folderStatus'][$this->profileID]);
			#print "ID: $_id<br>";
			$position=false;
			if (is_array($this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'])) {
				$position = array_search($_id, $this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult']);
			}
			#print "POS: $position<br>";

			if($position !== false) {
				$retValue = array();

				if($this->sessionData['folderStatus'][$this->profileID][$_foldername]['reverse'] == true) {
					#print "is reverse<br>";
					if(isset($this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'][$position-1])) {
						$retValue['next'] = $this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'][$position-1];
					}
					if(isset($this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'][$position+1])) {
						$retValue['previous'] = $this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'][$position+1];
					}
				} else {
					#print "is not reverse";
					if(isset($this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'][$position-1])) {
						$retValue['previous'] = $this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'][$position-1];
					}
					if(isset($this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'][$position+1])) {
						$retValue['next'] = $this->sessionData['folderStatus'][$this->profileID][$_foldername]['sortResult'][$position+1];
					}
				}

				return $retValue;
			}

			return false;
		}

		function getIMAPACL($_folderName, $user='')
		{
			if(($this->hasCapability('ACL'))) {
				if ( PEAR::isError($acl = $this->icServer->getACL($_folderName)) ) {
					return false;
				}

				if ($user=='') {
					return $acl;
				}

				foreach ($acl as $i => $userACL) {
					if ($userACL['USER'] == $user) {
						return $userACL['RIGHTS'];
					}
				}

				return '';
			}

			return false;
		}

		/**
		* checks if the imap server supports a given capability
		*
		* @param string $_capability the name of the capability to check for
		* @return bool
		*/
		function hasCapability($_capability)
		{
			return $this->icServer->hasCapability(strtoupper($_capability));
		}

		function getMailPreferences()
		{
			return $this->mailPreferences;
		}

		function getMessageAttachments($_uid, $_partID='', $_structure='', $resolveTNEF=true)
		{
			if (self::$debug) echo __METHOD__."$_uid, $_partID<br>";

			if(is_object($_structure)) {
				$structure = $_structure;
			} else {
				$structure = $this->icServer->getStructure($_uid, true);

				if($_partID != '' && $_partID !=0) {
					$structure = $this->_getSubStructure($structure, $_partID);
				}
			}

			if (self::$debug) _debug_array($structure);
			$attachments = array();
			// this kind of messages contain only the attachment and no body
			if($structure->type == 'APPLICATION' || $structure->type == 'AUDIO' || $structure->type == 'IMAGE') 
			{
				$newAttachment = array();
				$newAttachment['name']		= self::getFileNameFromStructure($structure);
				$newAttachment['size']		= $structure->bytes;
				$newAttachment['mimeType']	= $structure->type .'/'. $structure->subType;
				$newAttachment['partID']	= $structure->partID;
				$newAttachment['encoding']      = $structure->encoding;
				// try guessing the mimetype, if we get the application/octet-stream
				if (strtolower($newAttachment['mimeType']) == 'application/octet-stream') $newAttachment['mimeType'] = mime_magic::filename2mime($newAttachment['name']);
 
				if(isset($structure->cid)) {
					$newAttachment['cid']	= $structure->cid;
				}
				# if the new attachment is a winmail.dat, we have to decode that first
				if ( $resolveTNEF && $newAttachment['name'] == 'winmail.dat' &&
					( $wmattachments = $this->decode_winmail( $_uid, $newAttachment['partID'] ) ) )
				{
					$attachments = array_merge( $attachments, $wmattachments );
				} else {
					$attachments[] = $newAttachment;
				}
				//$attachments[] = $newAttachment;

				#return $attachments;
			}

			// this kind of message can have no attachments
			if(($structure->type == 'TEXT' && !($structure->disposition == 'INLINE' && $structure->dparameters['FILENAME'])) ||
			   ($structure->type == 'MULTIPART' && $structure->subType == 'ALTERNATIVE' && !is_array($structure->subParts)) ||
			   !is_array($structure->subParts)) 
			{
				if (count($attachments) == 0) return array();
			}

			#$attachments = array();

			foreach($structure->subParts as $subPart) {
				// skip all non attachment parts
				if(($subPart->type == 'TEXT' && ($subPart->subType == 'PLAIN' || $subPart->subType == 'HTML') && ($subPart->disposition != 'ATTACHMENT' &&
					!($subPart->disposition == 'INLINE' && $subPart->dparameters['FILENAME']))) ||
					($subPart->type == 'MULTIPART' && $subPart->subType == 'ALTERNATIVE') ||
					($subPart->type == 'MULTIPART' && $subPart->subType == 'APPLEFILE') ||
					($subPart->type == 'MESSAGE' && $subPart->subType == 'delivery-status'))
				{
					if ($subPart->type == 'MULTIPART' && $subPart->subType == 'ALTERNATIVE')
					{
						$attachments = array_merge($this->getMessageAttachments($_uid, '', $subPart, $resolveTNEF), $attachments);
					}
					if (!($subPart->type=='TEXT' && $subPart->disposition =='INLINE' && $subPart->filename)) continue;
				}

			   	// fetch the subparts for this part
				if($subPart->type == 'MULTIPART' &&
				   ($subPart->subType == 'RELATED' ||
					$subPart->subType == 'MIXED' ||
					$subPart->subType == 'SIGNED' ||
					$subPart->subType == 'APPLEDOUBLE'))
				{
				   	$attachments = array_merge($this->getMessageAttachments($_uid, '', $subPart, $resolveTNEF), $attachments);
				} else {
					$newAttachment = array();
					$newAttachment['name']		= self::getFileNameFromStructure($subPart);
					$newAttachment['size']		= $subPart->bytes;
					$newAttachment['mimeType']	= $subPart->type .'/'. $subPart->subType;
					$newAttachment['partID']	= $subPart->partID;
					$newAttachment['encoding']	= $subPart->encoding;
					// try guessing the mimetype, if we get the application/octet-stream
					if (strtolower($newAttachment['mimeType']) == 'application/octet-stream') $newAttachment['mimeType'] = mime_magic::filename2mime($newAttachment['name']);
 
					if(isset($subPart->cid)) {
						$newAttachment['cid']	= $subPart->cid;
					}
					# if the new attachment is a winmail.dat, we have to decode that first
					if ( $resolveTNEF && $newAttachment['name'] == 'winmail.dat' &&
						( $wmattachments = $this->decode_winmail( $_uid, $newAttachment['partID'] ) ) )
					{
						$attachments = array_merge( $attachments, $wmattachments );
					} else {
						$attachments[] = $newAttachment;
					}
					//$attachments[] = $newAttachment;
				}
			}

		   	//_debug_array($attachments); exit;
			return $attachments;

		}

		static function getFileNameFromStructure(&$structure)
		{
			static $namecounter;
			if (is_null($namecounter)) $namecounter = 0;

			if(isset($structure->parameters['NAME'])) {
				return self::decode_header($structure->parameters['NAME']);
			} elseif(isset($structure->dparameters['FILENAME'])) {
				return self::decode_header($structure->dparameters['FILENAME']);
			} elseif(isset($structure->dparameters['FILENAME*'])) {
				return self::decode_header($structure->dparameters['FILENAME*']);
			} elseif ( isset($structure->filename) && !empty($structure->filename) && $structure->filename != 'NIL') {
				return self::decode_header($structure->filename);
			} else {
				$namecounter++;
				return lang("unknown").$namecounter.($structure->subType ? ".".$structure->subType : "");
			}
		}

		function getMessageBody($_uid, $_htmlOptions='', $_partID='', $_structure = '')
		{
			if (self::$debug) echo __METHOD__."$_uid, $_htmlOptions, $_partID<br>";
			if($_htmlOptions != '') {
				$this->htmlOptions = $_htmlOptions;
			}
			if(is_object($_structure)) {
				$structure = $_structure;
			} else {
				$structure = $this->icServer->getStructure($_uid, true);
				if($_partID != '') {
					$structure = $this->_getSubStructure($structure, $_partID);
				}
			}
			if (self::$debug) _debug_array($structure);
			switch($structure->type) {
				case 'APPLICATION':
					return array(
						array(
							'body'		=> '',
							'mimeType'	=> 'text/plain',
							'charSet'	=> 'iso-8859-1',
						)
					);
					break;
				case 'MULTIPART':
					switch($structure->subType) {
						case 'ALTERNATIVE':
							$bodyParts = array($this->getMultipartAlternative($_uid, $structure->subParts, $this->htmlOptions));

							break;

						case 'MIXED':
						case 'REPORT':
						case 'SIGNED':
							$bodyParts = $this->getMultipartMixed($_uid, $structure->subParts, $this->htmlOptions);
							break;

						case 'RELATED':
							$bodyParts = $this->getMultipartRelated($_uid, $structure->subParts, $this->htmlOptions);
							break;
					}
					return self::normalizeBodyParts($bodyParts);
					break;
				case 'AUDIO': // some servers send audiofiles and imagesfiles directly, without any stuff surround it
				case 'IMAGE': // they are displayed as Attachment NOT INLINE
					return array(
						array(
							'body'      => '',
							'mimeType'  => $structure->subType,
						),
					);
					break;
				case 'TEXT':
					$bodyPart = array();
					if ( $structure->disposition != 'ATTACHMENT') {
						switch($structure->subType) {
							case 'CALENDAR':
								// maybe opening an egw_calendar object, ... in the future
								#$calobj =& CreateObject('calendar.calendar_ical');
								#$bodyPart = $this->getTextPart($_uid, $structure, $this->htmlOptions);
								#$calobj->importVCal($bodyPart['body']);
								#break;
							case 'HTML':
							case 'PLAIN':
							default:
								$bodyPart = array($this->getTextPart($_uid, $structure, $this->htmlOptions));
						}
					} else {
						// what if the structure->disposition is attachment ,...
					}
					return self::normalizeBodyParts($bodyPart);
					break;
				case 'MESSAGE':
					switch($structure->subType) {
						case 'RFC822':
							$newStructure = array_shift($structure->subParts);
							return self::normalizeBodyParts($this->getMessageBody($_uid, $_htmlOptions, $newStructure->partID, $newStructure));
							break;
					}
					break;
				default:
					return array(
						array(
							'body'		=> lang('The mimeparser can not parse this message.'),
							'mimeType'	=> 'text/plain',
							'charSet'	=> 'iso-8859-1',
						)
					);
					break;
			}
		}

		/**
		 * normalizeBodyParts - function to gather and normalize all body Information
		 * @param _bodyParts - Body Array
		 * @returns array - a normalized Bodyarray
		 */
		static function normalizeBodyParts($_bodyParts) 
		{
			if (is_array($_bodyParts))
			{
				foreach($_bodyParts as $singleBodyPart) 
				{
					if (!isset($singleBodyPart['body'])) {
						$buff = self::normalizeBodyParts($singleBodyPart);
						foreach ($buff as $val)	$body2return[] = $val;
						continue;
					}
					$body2return[] = $singleBodyPart;
				}
			}
			else
			{
				$body2return = $_bodyParts;
			}
			return $body2return;
		}

		function getMessageHeader($_uid, $_partID = '',$decode=false)
		{
			$retValue = $this->icServer->getParsedHeaders($_uid, true, $_partID, true);

			return ($decode ? self::decode_header($retValue):$retValue);
		}

		function getMessageRawBody($_uid, $_partID = '')
		{
			if($_partID != '') {
				$body = $this->icServer->getBody($_uid, true);
			} else {
				$body = $this->icServer->getBodyPart($_uid, $_partID, true);
			}
			if (PEAR::isError($body))
			{
				error_log(__METHOD__.__LINE__.' failed:'.$body->message);
				return false;
			}

			return $body;
		}

		function getMessageRawHeader($_uid, $_partID = '')
		{
			$retValue = $this->icServer->getRawHeaders($_uid, $_partID, true);

			return $retValue;
		}

		// return the qouta of the users INBOX
		function getQuotaRoot()
		{
			if(!$this->icServer->hasCapability('QUOTA')) {
				return false;
			}

			$quota = $this->icServer->getStorageQuotaRoot('INBOX');
			if(is_array($quota)) {
				return array(
					'usage'	=> $quota['USED'],
					'limit'	=> $quota['QMAX'],
				);
			} else {
				return false;
			}
		}

	#	function imapGetQuota($_username)
	#	{
	#		$quota_value = @imap_get_quota($this->mbox, "user.".$_username);
	#
	#		if(is_array($quota_value) && count($quota_value) > 0)
	#		{
	#			return array('limit' => $quota_value['limit']/1024);
	#		}
	#		else
	#		{
	#			return false;
	#		}
	#	}

	#	function imap_get_quotaroot($_folderName)
	#	{
	#		return @imap_get_quotaroot($this->mbox, $_folderName);
	#	}

	#	function imapSetQuota($_username, $_quotaLimit)
	#	{
	#		if(is_numeric($_quotaLimit) && $_quotaLimit >= 0)
	#		{
	#			// enable quota
	#			$quota_value = @imap_set_quota($this->mbox, "user.".$_username, $_quotaLimit*1024);
	#		}
	#		else
	#		{
	#			// disable quota
	#			$quota_value = @imap_set_quota($this->mbox, "user.".$_username, -1);
	#		}
	#	}

		function isSentFolder($_folderName)
		{
			if(empty($this->mailPreferences->preferences['sentFolder'])) {
				return false;
			}
			// does the folder exist???
			if (!self::folderExists($_folderName)) {
				return false;
			}

			if(false !== stripos($_folderName, $this->mailPreferences->preferences['sentFolder'])) {
				return true;
			} else {
				return false;
			}
		}

		function isDraftFolder($_folderName)
		{
			if(empty($this->mailPreferences->preferences['draftFolder'])) {
				return false;
			}
			// does the folder exist???
			if (!self::folderExists($_folderName)) {
				return false;
			}

			if(false !== stripos($_folderName, $this->mailPreferences->preferences['draftFolder'])) {
				return true;
			} else {
				return false;
			}
		}

		function isTemplateFolder($_folderName)
		{
			if(empty($this->mailPreferences->preferences['templateFolder'])) {
				return false;
			}
			// does the folder exist???
			if (!self::folderExists($_folderName)) {
				return false;
			}

			if(false !== stripos($_folderName, $this->mailPreferences->preferences['templateFolder'])) {
				return true;
			} else {
				return false;
			}
		}

		function folderExists($_folder, $forceCheck=false)
		{
			static $folderInfo;
			if (empty($_folder))
			{
				error_log(__METHOD__.__LINE__.' Called with empty Folder:'.$_folder.function_backtrace());
				return false;
			}
			// reduce traffic within on request
			//error_log(__METHOD__.__LINE__.' Called with Folder:'.$_folder.function_backtrace());
			if (isset($folderInfo[$_folder])) return $folderInfo[$_folder];

			// does the folder exist???
			//error_log(__METHOD__."->Connected?".$this->icServer->_connected.", ".$_folder.", ".$forceCheck);
			if ((!($this->icServer->_connected == 1)) && $forceCheck) {
				//error_log(__METHOD__."->NotConnected and forceCheck");
				//return false;
				//try to connect
				if (!$this->icServer->_connected) $this->openConnection();
			}
			if(is_a($this->icServer,'defaultimap')) $folderInfo[$_folder] = $this->icServer->mailboxExist($_folder);
			//error_log(__METHOD__.__LINE__.' Folder Exists:'.$folderInfo[$_folder]);

			if(is_a($folderInfo[$_folder], 'PEAR_Error') || $folderInfo[$_folder] !== true)
			{
				return false;
			} else {
				return true;
			}
		}

		function moveMessages($_foldername, $_messageUID, $deleteAfterMove=true)
		{
			$msglist = '';

			$deleteOptions  = $GLOBALS['egw_info']["user"]["preferences"]["felamimail"]["deleteOptions"];

			if ( PEAR::isError($this->icServer->copyMessages($_foldername, $_messageUID, $this->sessionData['mailbox'], true)) ) {
				return false;
			}
			// mark messages as deleted
			if ($deleteAfterMove === true)
			{
				if ( PEAR::isError($this->icServer->deleteMessages($_messageUID, true))) 
				{
					return false;
				}

				if($deleteOptions != "mark_as_deleted") 
				{
					// delete the messages finaly
					$this->icServer->expunge();
				}
			}
			return true;
		}

		function openConnection($_icServerID=0, $_adminConnection=false)
		{
			if (!is_object($this->mailPreferences)) 
			{
				error_log(__METHOD__." No Object for MailPreferences found.". function_backtrace());
				$this->errorMessage .= lang('No valid data to create MailProfile!!');
				return false;
			}
			if(!$this->icServer = $this->mailPreferences->getIncomingServer((int)$_icServerID)) {
				$this->errorMessage .= lang('No active IMAP server found!!');
				return false;
			}
			if ($this->icServer && empty($this->icServer->host)) {
				$errormessage = lang('No IMAP server host configured!!');
				if ($GLOBALS['egw_info']['user']['apps']['emailadmin']) {
					$errormessage .= "<br>".lang("Configure a valid IMAP Server in emailadmin for the profile you are using.");
				} else {
					$errormessage .= "<br>".lang('Please ask the administrator to correct the emailadmin IMAP Server Settings for you.');
				}
				$this->icServer->_connectionErrorObject->message .= $this->errorMessage .= $errormessage;
				return false;
			}
			#error_log( "---------------------------open connection <br>");
			#error_log(print_r($this->icServer,true));
			if ($this->icServer->_connected == 1) {
				$tretval = $this->icServer->selectMailbox($this->icServer->currentMailbox);
				#error_log(__METHOD__." using existing Connection ".print_r($this->icServer->_connected,true));
			} else {
				$tretval = $this->icServer->openConnection($_adminConnection);
				#error_log(__METHOD__." open new Connection ".print_r($this->icServer->_connected,true));
			}
			#error_log(print_r($this->icServer->_connected,true));
			return $tretval;
		}

		/**
		* rename a folder
		*
		* @param string _oldFolderName the old foldername
		* @param string _parent the parent foldername
		* @param string _folderName the new foldername
		*
		* @returns mixed name of the newly created folder or false on error
		*/
		function renameFolder($_oldFolderName, $_parent, $_folderName)
		{
			$oldFolderName	= $this->_encodeFolderName($_oldFolderName);
			$parent		= $this->_encodeFolderName($_parent);
			$folderName	= $this->_encodeFolderName($_folderName);

			if(empty($parent)) {
				$newFolderName = $folderName;
			} else {
				$HierarchyDelimiter = $this->getHierarchyDelimiter();
				$newFolderName = $parent . $HierarchyDelimiter . $folderName;
			}
			if (self::$debug) error_log("create folder: $newFolderName");
			$rv = $this->icServer->renameMailbox($oldFolderName, $newFolderName);
			if ( PEAR::isError($rv) ) {
				if (self::$debug) error_log(__METHOD__." failed for $oldFolderName, $newFolderName with error: ".print_r($rv->message,true));
				return false;
			}

			return $newFolderName;

		}

		function reopen($_foldername)
		{
			#error_log( "------------------------reopen-<br>");
			#error_log(print_r($this->icServer->_connected,true));
			if ($this->icServer->_connected == 1) {
				$tretval = $this->icServer->selectMailbox($_foldername);
			} else {
				$tretval = $this->icServer->openConnection(false);
				$tretval = $this->icServer->selectMailbox($_foldername);
			}
		}

		function restoreSessionData()
		{
			$GLOBALS['egw_info']['flags']['autoload'] = array(__CLASS__,'autoload');

			$this->sessionData = $GLOBALS['egw']->session->appsession('session_data','felamimail');
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
			$GLOBALS['egw']->session->appsession('session_data','felamimail',$this->sessionData);
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
			if (self::$debug) error_log("bofelamimail::".($_status?"":"un")."subscribe:".$_folderName);
			if($_status === true) {
				if ( PEAR::isError($this->icServer->subscribeMailbox($_folderName))) {
					error_log("bofelamimail::".($_status?"":"un")."subscribe:".$_folderName." failed");
					return false;
				}
			} else {
				if ( PEAR::isError($this->icServer->unsubscribeMailbox($_folderName))) {
					error_log("bofelamimail::".($_status?"":"un")."subscribe:".$_folderName." failed");
					return false;
				}
			}

			return true;
		}

		function toggleFilter()
		{
			if($this->sessionData['filter']['filterActive'] == 'true') {
				$this->sessionData['filter']['filterActive'] = 'false';
			} else {
				$this->sessionData['filter']['filterActive'] = 'true';
			}
			$this->saveSessionData();
		}

		function updateAccount($_hookValues)
		{
			if (is_object($this->mailPreferences)) $icServer = $this->mailPreferences->getIncomingServer(0);
			if(is_a($icServer,'defaultimap')) {
				$icServer->updateAccount($_hookValues);
			}

			if (is_object($this->mailPreferences)) $ogServer = $this->mailPreferences->getOutgoingServer(0);
			if(is_a($ogServer,'defaultsmtp')) {
				$ogServer->updateAccount($_hookValues);
			}
		}

		function updateSingleACL($_folderName, $_accountName, $_aclType, $_aclStatus)
		{
			$userACL = $this->getIMAPACL($_folderName, $_accountName);

			if($_aclStatus == 'true') {
				if(strpos($userACL, $_aclType) === false) {
					$userACL .= $_aclType;
					$this->setACL($_folderName, $_accountName, $userACL);
				}
			} elseif($_aclStatus == 'false') {
				if(strpos($userACL, $_aclType) !== false) {
					$userACL = str_replace($_aclType,'',$userACL);
					$this->setACL($_folderName, $_accountName, $userACL);
				}
			}

			return $userACL;
		}

		static function wordwrap($str, $cols, $cut, $dontbreaklinesstartingwith=false)
		{
			$lines = explode("\n", $str);
			$newStr = '';
			foreach($lines as $line)
			{
				// replace tabs by 8 space chars, or any tab only counts one char
				//$line = str_replace("\t","        ",$line);
				//$newStr .= wordwrap($line, $cols, $cut);
				$allowedLength = $cols-strlen($cut);
				if (strlen($line) > $allowedLength &&
					($dontbreaklinesstartingwith==false ||
					 ($dontbreaklinesstartingwith &&
					  strlen($dontbreaklinesstartingwith)>=1 &&
					  substr($line,0,strlen($dontbreaklinesstartingwith)) != $dontbreaklinesstartingwith
					 )
					)
				) {
					$s=explode(" ", $line);
					$line = "";
					$linecnt = 0;
					foreach ($s as $k=>$v) {
						$cnt = strlen($v);
						// only break long words within the wordboundaries,
						// but it may destroy links, so we check for href and dont it if we find one
						if($cnt > $allowedLength && stripos($v,'href=')===false && stripos($v,'onclick=')===false) 
						{
							$v=wordwrap($v, $allowedLength, $cut, true);
						}
						// the rest should be broken at the start of the new word that exceeds the limit
						if ($linecnt+$cnt > $allowedLength) {
							$v=$cut.$v;
							#$linecnt = 0;
							$linecnt =strlen($v)-strlen($cut);
						} else {
							$linecnt += $cnt;
						}
						if (strlen($v)) $line .= (strlen($line) ? " " : "").$v;
					}
				}
				$newStr .= $line . "\n";
			}
			return $newStr;
		}

		/**
		* convert the foldername from display charset to UTF-7
		*
		* @param string _parent the parent foldername
		* @returns ISO-8859-1 / UTF7-IMAP encoded string
		*/
		function _encodeFolderName($_folderName) {
			return $GLOBALS['egw']->translation->convert($_folderName, self::$displayCharset, 'ISO-8859-1');
			#return $GLOBALS['egw']->translation->convert($_folderName, self::$displayCharset, 'UTF7-IMAP');
		}

		/**
		* convert the foldername from UTF-7 to display charset
		*
		* @param string _parent the parent foldername
		* @returns ISO-8859-1 / self::$displayCharset encoded string
		*/
		function _decodeFolderName($_folderName) {
			return $GLOBALS['egw']->translation->convert($_folderName, self::$displayCharset, 'ISO-8859-1');
			#return $GLOBALS['egw']->translation->convert($_folderName, 'UTF7-IMAP', self::$displayCharset);
		}

		/**
		* convert the sort value from the gui(integer) into a string
		*
		* @param int _sort the integer sort order
		* @returns the ascii sort string
		*/
		function _getSortString($_sort)
		{
			switch($_sort) {
				case 2:
					$retValue = 'FROM';
					break;
				case 3:
					$retValue = 'SUBJECT';
					break;
				case 6:
					$retValue = 'SIZE';
					break;
				case 0:
				default:
					$retValue = 'DATE';
					break;
			}

			return $retValue;
		}

		function sendMDN($uid) {
			$identities = $this->mailPreferences->getIdentity();
			$headers = $this->getMessageHeader($uid);
			$send = CreateObject('phpgwapi.send');
			$send->ClearAddresses();
			$send->ClearAttachments();
			$send->IsHTML(False);
			$send->IsSMTP();

			$array_to = explode(",",$headers['TO']);
			foreach($identities as  $identity) {
				if ( preg_match('/\b'.$identity->emailAddress.'\b/',$headers['TO']) ) {
					$send->From = $identity->emailAddress;
					$send->FromName = $identity->realName;
					error_log(__METHOD__.__LINE__.' using identity for send from:'.$send->From.' to match header information:'.$headers['TO']);
					break;
				}
				if($identity->default) {
					$send->From = $identity->emailAddress;
					$send->FromName = $identity->realName;
				}
			}

			if (isset($headers['DISPOSITION-NOTIFICATION-TO'])) {
				$toAddr = $headers['DISPOSITION-NOTIFICATION-TO'];
			} else if ( isset($headers['RETURN-RECEIPT-TO']) ) {
				$toAddr = $headers['RETURN-RECEIPT-TO'];
			} else if ( isset($headers['X-CONFIRM-READING-TO']) ) {
				$toAddr = $headers['X-CONFIRM-READING-TO'];
			} else return false;
			$singleAddress = imap_rfc822_parse_adrlist($toAddr,'');
			if (self::$debug) error_log(__METHOD__.__LINE__.' To Address:'.$singleAddress[0]->mailbox."@".$singleAddress[0]->host.", ".$singleAddress[0]->personal);
			$send->AddAddress($singleAddress[0]->mailbox."@".$singleAddress[0]->host, $singleAddress[0]->personal);	
			$send->AddCustomHeader('References: '.$headers['MESSAGE-ID']);
			$send->Subject = $send->encode_subject( lang('Read')." : ".$headers['SUBJECT'] );

			$sep = "-----------mdn".$uniq_id = md5(uniqid(time()));

			$body = "--".$sep."\r\n".
				"Content-Type: text/plain; charset=ISO-8859-1\r\n".
				"Content-Transfer-Encoding: 7bit\r\n\r\n".
				$send->EncodeString(lang("Your message to %1 was displayed." ,$send->From),"7bit").
				"\r\n";

			$body .= "--".$sep."\r\n".
				"Content-Type: message/disposition-notification; name=\"MDNPart2.txt\"\r\n" .
				"Content-Disposition: inline\r\n".
				"Content-Transfer-Encoding: 7bit\r\n\r\n";
			$body.= $send->EncodeString("Reporting-UA: eGroupWare\r\n" .
						   "Final-Recipient: rfc822;".$send->From."\r\n" .
						   "Original-Message-ID: ".$headers['MESSAGE-ID']."\r\n".
						   "Disposition: manual-action/MDN-sent-manually; displayed",'7bit')."\r\n";

			$body .= "--".$sep."\r\n".
				"Content-Type: text/rfc822-headers; name=\"MDNPart3.txt\"\r\n" .
				"Content-Transfer-Encoding: 7bit\r\n" .
				"Content-Disposition: inline\r\n\r\n";
			$body .= $send->EncodeString($this->getMessageRawHeader($uid),'7bit')."\r\n";
			$body .= "--".$sep."--";


			$header = rtrim($send->CreateHeader())."\r\n"."Content-Type: multipart/report; report-type=disposition-notification;\r\n".
				"\tboundary=\"".$sep."\"\r\n\r\n";
			return $send->SmtpSend($header,$body);
		}

		/**
		 * Merges a given content with contact data
		 *
		 * @param string $content
		 * @param array $ids array with contact id(s)
		 * @param string &$err error-message on error
		 * @return string/boolean merged content or false on error
		 */
		function merge($content,$ids)
		{
			$contacts = new addressbook_bo();
			$mergeobj = new addressbook_merge();

			foreach ($ids as $id)
			{
				// generate replacements
				if (!($replacements = $mergeobj->contact_replacements($id)))
				{
					$err = lang('Contact not found!');
					#return false;
					continue;
				}
				if (strpos($content,'$$user/') !== null && ($user = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')))
				{
					$replacements += $mergeobj->contact_replacements($user,'user');
				}
				$replacements['$$date$$'] = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],time()+$this->contacts->tz_offset_s);

				$content = str_replace(array_keys($replacements),array_values($replacements),$content);


				$this->replacements = $replacements;
				if (strpos($content,'$$IF'))
				{	//Example use to use: $$IF n_prefix~Herr~Sehr geehrter~Sehr geehrte$$
					$content = preg_replace_callback('/\$\$IF ([0-9a-z_-]+)~(.*)~(.*)~(.*)\$\$/imU',Array($this,'replace_callback'),$content);
				}

			}
			return $content;
		}

		/**
		 * Tests if string contains 8bit symbols.
		 *
		 * If charset is not set, function defaults to default_charset.
		 * $default_charset global must be set correctly if $charset is
		 * not used.
		 * @param string $string tested string
		 * @param string $charset charset used in a string
		 * @return bool true if 8bit symbols are detected
		 */
		static function is8bit(&$string,$charset='') {

		    if ($charset=='') $charset= self::$displayCharset;

			/**
			* Don't use \240 in ranges. Sometimes RH 7.2 doesn't like it.
			* Don't use \200-\237 for iso-8859-x charsets. This ranges
			* stores control symbols in those charsets.
			* Use preg_match instead of ereg in order to avoid problems
			* with mbstring overloading
			*/
			if (preg_match("/^iso-8859/i",$charset)) {
				$needle='/\240|[\241-\377]/';
			} else {
				$needle='/[\200-\237]|\240|[\241-\377]/';
			}
			return preg_match("$needle",$string);
		}

		/**
		 * htmlspecialchars 
		 * helperfunction to cope with wrong encoding in strings
		 * @param string $_string  input to be converted
		 * @param mixed $charset false or string -> Target charset, if false bofelamimail displayCharset will be used
		 * @return string 
		 */
		static function htmlspecialchars($_string, $_charset=false)
		{
			//setting the charset (if not given)
			if ($_charset===false) $_charset = bofelamimail::$displayCharset;
			$_stringORG = $_string;
			$_string = @htmlspecialchars($_string,ENT_QUOTES,$_charset, false);
			if (empty($_string) && !empty($_stringORG)) $_string = @htmlspecialchars($GLOBALS['egw']->translation->convert($_stringORG,bofelamimail::detect_encoding($_stringORG),$_charset),ENT_QUOTES | ENT_IGNORE,$_charset, false);
			return $_string;
		}

		/**
		 * htmlentities
		 * helperfunction to cope with wrong encoding in strings
		 * @param string $_string  input to be converted
		 * @param mixed $charset false or string -> Target charset, if false bofelamimail displayCharset will be used
		 * @return string 
		 */
		static function htmlentities($_string, $_charset=false)
		{
			//setting the charset (if not given)
			if ($_charset===false) $_charset = bofelamimail::$displayCharset;
			$_stringORG = $_string;
			$_string = @htmlentities($_string,ENT_QUOTES,$_charset, false);
			if (empty($_string) && !empty($_stringORG)) $_string = @htmlentities($GLOBALS['egw']->translation->convert($_stringORG,bofelamimail::detect_encoding($_stringORG),$_charset),ENT_QUOTES | ENT_IGNORE,$_charset, false);
			return $_string;
		}

		/**
		 * detect_encoding - try to detect the encoding
		 *    only to be used if the string in question has no structure that determines his encoding
		 * @param string - to be evaluated
		 * @returns mixed string/boolean (encoding or false
		 */
		static function detect_encoding($string) { 
			static $list = array('utf-8', 'iso-8859-1', 'windows-1251'); // list may be extended
			if (function_exists('iconv'))
			{ 
				foreach ($list as $item) {
				$sample = iconv($item, $item, $string);
				if (md5($sample) == md5($string))
					return $item;
				}
			}
			return false; // we may choose to return iso-8859-1 as default at some point
		}

		static function detect_qp(&$sting) {
			$needle = '/(=[0-9][A-F])|(=[A-F][0-9])|(=[A-F][A-F])|(=[0-9][0-9])/';
			return preg_match("$needle",$string);
		}
		/**
		 * Helper function to handle wrong or unrecognized timezones
		 * returns the date as it is parseable by strtotime, or current timestamp if everything failes
		 * @param string date to be parsed/formatted
		 * @param string format string, if none is passed, use the users common dateformat supplemented by the time hour:minute:second
		 * @return string returns the date as it is parseable by strtotime, or current timestamp if everything failes
		 */
		static function _strtotime($date='',$format=NULL,$convert2usertime=false)
		{
			if ($format==NULL) $format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'].' '.($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s');
			$date2return = ($convert2usertime ? egw_time::server2user($date,$format) : egw_time::to($date,$format));
			if ($date2return==null)
			{
				$dtarr = explode(' ',$date);
				$test = null;
				while ($test===null && count($dtarr)>=1) 
				{
					array_pop($dtarr);
					$test= ($convert2usertime ? egw_time::server2user(implode(' ',$dtarr),$format): egw_time::to(implode(' ',$dtarr),$format));
					if ($test) $date2return = $test; 
				}
				if ($test===null) $date2return = egw_time::to('now',$format);
			}
			return $date2return;
		}

		/**
		 * checkFileBasics
		 *	check if formdata meets basic restrictions (in tmp dir, or vfs, mimetype, etc.)
		 *
		 * @param array $_formData passed by reference Array with information of name, type, file and size, mimetype may be adapted
		 * @param string $IDtoAddToFileName id to enrich the returned tmpfilename
		 * @param string $reqMimeType /(default message/rfc822, if set to false, mimetype check will not be performed
		 * @return mixed $fullPathtoFile or exception
		 */
		static function checkFileBasics(&$_formData, $IDtoAddToFileName='', $reqMimeType='message/rfc822')
		{
			//error_log(__METHOD__.__FILE__.array2string($_formData).' Id:'.$IDtoAddToFileName.' ReqMimeType:'.$reqMimeType);
			$importfailed = $tmpFileName = false;
			if ($_formData['size'] != 0 && (is_uploaded_file($_formData['file']) || 
				realpath(dirname($_formData['file'])) == realpath($GLOBALS['egw_info']['server']['temp_dir']) ||
				parse_url($_formData['file'],PHP_URL_SCHEME) == 'vfs'))
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
				if ($reqMimeType == 'message/rfc822') 
				{
					$mime_type_default = 'message/rfc';
				}
				else
				{
					$mime_type_default = $reqMimeType;
				}
				if (trim($_formData['type']) == '')
				{
					$_formData['type'] = 'application/octet-stream';
				}
				// if reqMimeType is set to false do not test for that
				if ($reqMimeType)
				{
					// so if PHP did not pass any file_type info, then substitute the rfc default value
					if (substr(strtolower(trim($_formData['type'])),0,strlen($mime_type_default)) != $mime_type_default)
					{
						// maybe its application/octet-stream -> this may mean that we could not determine the type
						// so we check for the suffix too
						$buff = explode('.',$_formData['name']);
						$suffix = '';
						if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
						if (!(strtolower(trim($_formData['type'])) == "application/octet-stream" && mime_magic::ext2mime($suffix)== $reqMimeType))
						{
							//error_log("Message rejected, no message/rfc. Is:".$_formData['type']);
							$importfailed = true;
							$alert_msg .= lang("File rejected, no %2. Is:%1",$_formData['type'],$reqMimeType);
						}
						if ((strtolower(trim($_formData['type'])) != $reqMimeType && mime_magic::ext2mime($suffix)== $reqMimeType))
						{
							$_formData['type'] = mime_magic::ext2mime($suffix);
						}
					}
				}
				// as FreeBSD seems to have problems with the generated temp names we append some more random stuff
				$randomString = chr(rand(65,90)).chr(rand(48,57)).chr(rand(65,90)).chr(rand(48,57)).chr(rand(65,90));
				$tmpFileName = $GLOBALS['egw_info']['server']['temp_dir'].
					SEP.
					$GLOBALS['egw_info']['user']['account_id'].
					trim($IDtoAddToFileName).basename($_formData['file']).'_'.$randomString;
				
				if (parse_url($_formData['file'],PHP_URL_SCHEME) == 'vfs')
				{
					$tmpFileName = $_formData['file'];	// no need to store it somewhere
				}
				elseif (is_uploaded_file($_formData['file']))
				{
					move_uploaded_file($_formData['file'],$tmpFileName);	// requirement for safe_mode!
				}
				else
				{
					rename($_formData['file'],$tmpFileName);
				}
			} else {
				//error_log("Import of message ".$_formData['file']." failes to meet basic restrictions");
				$importfailed = true;
				$alert_msg .= lang("Processing of file %1 failed. Failed to meet basic restrictions.",$_formData['name']);
			}
			if ($importfailed == true)
			{
				throw new egw_exception_wrong_userinput($alert_msg);
			}
			else
			{
				if (parse_url($tmpFileName,PHP_URL_SCHEME) == 'vfs')
				{
					egw_vfs::load_wrapper('vfs');
				}
				return $tmpFileName;
			}			
		}

		/**
		 * getRandomString - function to be used to fetch a random string and md5 encode that one
		 * @param none
		 * @return string - a random number which is md5 encoded
		 */
		static function getRandomString() {
			mt_srand((float) microtime() * 1000000);
			return md5(mt_rand (100000, 999999));
		}

		/**
		 * functions to allow access to mails through other apps to fetch content
		 * used in infolog, tracker
		 */

		/**
		 * get_mailcontent - fetches the actual mailcontent, and returns it as well defined array
		 * @param bofelamimail the bofelamimailobject to be used
		 * @param uid the uid of the email to be processed
		 * @param partid the partid of the email
		 * @param mailbox the mailbox, that holds the message
		 * @returns array with 'mailaddress'=>$mailaddress,
		 *				'subject'=>$subject,
		 *				'message'=>$message,
		 *				'attachments'=>$attachments,
		 *				'headers'=>$headers,
		 */
		static function get_mailcontent(&$bofelamimail,$uid,$partid='',$mailbox='')
		{
				//echo __METHOD__." called for $uid,$partid <br>";
				$headers = $bofelamimail->getMessageHeader($uid,$partid,true);
				// dont force retrieval of the textpart, let felamimail preferences decide
				$bodyParts = $bofelamimail->getMessageBody($uid,'',$partid);
				$attachments = $bofelamimail->getMessageAttachments($uid,$partid);

				if ($bofelamimail->isSentFolder($mailbox)) $mailaddress = $headers['TO'];
				elseif (isset($headers['FROM'])) $mailaddress = $headers['FROM'];
				elseif (isset($headers['SENDER'])) $mailaddress = $headers['SENDER'];
				if (isset($headers['CC'])) $mailaddress .= ','.$headers['CC'];
				//_debug_array($headers);
				$subject = $headers['SUBJECT'];

				$message = self::getdisplayableBody($bofelamimail, $bodyParts);
				$headdata = self::createHeaderInfoSection($headers);
				$message = $headdata.$message;
				//echo __METHOD__.'<br>';
				//_debug_array($attachments);
				if (is_array($attachments))
				{
					foreach ($attachments as $num => $attachment)
					{
						if ($attachment['mimeType'] == 'MESSAGE/RFC822')
						{
							//_debug_array($bofelamimail->getMessageHeader($uid, $attachment['partID']));
							//_debug_array($bofelamimail->getMessageBody($uid,'', $attachment['partID']));
							//_debug_array($bofelamimail->getMessageAttachments($uid, $attachment['partID']));
							$mailcontent = self::get_mailcontent($bofelamimail,$uid,$attachment['partID']);
							$headdata ='';
							if ($mailcontent['headers'])
							{
								$headdata = self::createHeaderInfoSection($mailcontent['headers']);
							}
							if ($mailcontent['message'])
							{
								$tempname =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
								$attachedMessages[] = array(
									'type' => 'TEXT/PLAIN',
									'name' => $mailcontent['subject'].'.txt',
									'tmp_name' => $tempname,
								);
								$tmpfile = fopen($tempname,'w');
								fwrite($tmpfile,$headdata.$mailcontent['message']);
								fclose($tmpfile);
							}
							foreach($mailcontent['attachments'] as $tmpattach => $tmpval)
							{
								$attachedMessages[] = $tmpval;
							}
							unset($attachments[$num]);
						}
						else
						{
							$attachments[$num] = array_merge($attachments[$num],$bofelamimail->getAttachment($uid, $attachment['partID']));
							if (isset($attachments[$num]['charset'])) {
								if ($attachments[$num]['charset']===false) $attachments[$num]['charset'] = self::detect_encoding($attachments[$num]['attachment']);
								$GLOBALS['egw']->translation->convert($attachments[$num]['attachment'],$attachments[$num]['charset']);
							}
							$attachments[$num]['type'] = $attachments[$num]['mimeType'];
							$attachments[$num]['tmp_name'] = tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
							$tmpfile = fopen($attachments[$num]['tmp_name'],'w');
							fwrite($tmpfile,$attachments[$num]['attachment']);
							fclose($tmpfile);
							unset($attachments[$num]['attachment']);
						}
					}
					if (is_array($attachedMessages)) $attachments = array_merge($attachments,$attachedMessages);
				}
				return array(
						'mailaddress'=>$mailaddress,
						'subject'=>$subject,
						'message'=>$message,
						'attachments'=>$attachments,
						'headers'=>$headers,
						);
		}

		/**
		 * createHeaderInfoSection - creates a textual headersection from headerobject
		 * @params header headerarray may contain SUBJECT,FROM,SENDER,TO,CC,BCC,DATE,PRIORITY,IMPORTANCE
		 * @returns string a preformatted string with the information of the header worked into it
		 */
		static function createHeaderInfoSection($header,$headline='')
		{
			$headdata = null;
			if ($header['SUBJECT']) $headdata = lang('subject').': '.$header['SUBJECT']."\n";
			if ($header['FROM']) $headdata .= lang('from').': '.$header['FROM']."\n";
			if ($header['SENDER']) $headdata .= lang('sender').': '.$header['SENDER']."\n";
			if ($header['TO']) $headdata .= lang('to').': '.$header['TO']."\n";
			if ($header['CC']) $headdata .= lang('cc').': '.$header['CC']."\n";
			if ($header['BCC']) $headdata .= lang('bcc').': '.$header['BCC']."\n";
			if ($header['DATE']) $headdata .= lang('date').': '.$header['DATE']."\n";
			if ($header['PRIORITY'] && $header['PRIORITY'] != 'normal') $headdata .= lang('priority').': '.$header['PRIORITY']."\n";
			if ($header['IMPORTANCE'] && $header['IMPORTANCE'] !='normal') $headdata .= lang('importance').': '.$header['IMPORTANCE']."\n";
			//if ($mailcontent['headers']['ORGANIZATION']) $headdata .= lang('organization').': '.$mailcontent['headers']['ORGANIZATION']."\
			if (!empty($headdata)) 
			{
				if (!empty($headline)) $headdata = "---------------------------- $headline ----------------------------\n".$headdata;
				if (empty($headline)) $headdata = "--------------------------------------------------------\n".$headdata;
				$headdata .= "--------------------------------------------------------\n";
			}
			else
			{
				$headdata = "--------------------------------------------------------\n";
			}
			return $headdata;
		}

		/**
		 * getdisplayableBody - creates the bodypart of the email as textual representation
		 * @param bofelamimail the bofelamimailobject to be used
		 * @params bodyPorts array with the bodyparts
		 * @returns string a preformatted string with the mails converted to text
		 */
		static function &getdisplayableBody(&$bofelamimail, $bodyParts)
		{
			for($i=0; $i<count($bodyParts); $i++)
			{
				if (!isset($bodyParts[$i]['body'])) {
					$bodyParts[$i]['body'] = self::getdisplayableBody($bofelamimail, $bodyParts[$i]);
					$message .= $bodyParts[$i]['body'];
					continue;
				}
				if ($bodyParts[$i]['charSet']===false) $bodyParts[$i]['charSet'] = self::detect_encoding($bodyParts[$i]['body']);
				// add line breaks to $bodyParts
				$newBody  = $GLOBALS['egw']->translation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);

				if ($bodyParts[$i]['mimeType'] == 'text/html') {
					// convert HTML to text, as we dont want HTML in infologs
					$newBody = html::purify($newBody);
					$newBody = $bofelamimail->convertHTMLToText($newBody,true);
					$bofelamimail->getCleanHTML($newBody); // new Body passed by reference
					$message .= $newBody;
					continue;
				}
				$newBody = strip_tags($newBody);
				$newBody  = explode("\n",$newBody);
				// create it new, with good line breaks
				reset($newBody);
				while(list($key,$value) = @each($newBody))
				{
					if (trim($value) != '') {
						#if ($value != "\r") $value .= "\n";
					} else {
						// if you want to strip all empty lines uncomment the following
						#continue;
					}
					$message .= $bofelamimail->wordwrap($value,75,"\n");
				}
			}
			return $message;
		}
	}
