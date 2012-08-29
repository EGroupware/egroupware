<?php
/**
 * EGroupware - FeLaMiMail - worker class
 *
 * @link http://www.egroupware.org
 * @package felamimail
 * @author Lars Kneschke [lkneschke@linux-at-work.de]
 * @author Klaus Leithoff [kl@stylite.de]
 * @version 1.9.002
 * @copyright (c) 2002,2003,2004 by Lars Kneschke
 * @copyright (c) 2009-10 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * FeLaMiMail worker class
 *  -provides backend functionality for all classes in FeLaMiMail
 *  -provides classes that may be used by other apps too
 */
class felamimail_bo
{
	var $public_functions = array
	(
		'flagMessages'		=> True,
	);

	static $debug = false; //true; // sometimes debuging is quite handy, to see things. check with the error log to see results

	// define some constants
	// message types
	var $type = array("text", "multipart", "message", "application", "audio", "image", "video", "other");

	/**
	 * static used to configure tidy - if tidy is loadable, this config is used with tidy to straighten out html, instead of using purifiers tidy mode
	 *
	 * @array
	 */
	static $tidy_config = array('clean'=>true,'output-html'=>true,'join-classes'=>true,'join-styles'=>true,'show-body-only'=>"auto",'word-2000'=>true,'wrap'=>0);

	/**
	 * static used to configure htmLawed, for use with emails
	 *
	 * @array
	 */
	static $htmLawed_config = array('comment'=>1, //remove comments
				'keep_bad'=>6,
				'balance'=>1,//turn off tag-balancing (config['balance']=>0). That will not introduce any security risk; only standards-compliant tag nesting check/filtering will be turned off (basic tag-balance will remain; i.e., there won't be any unclosed tag, etc., after filtering)
				'tidy'=>1,
				'elements' => "* -script",
				'schemes'=>'href: file, ftp, http, https, mailto; src: cid, data, file, ftp, http, https; *:file, http, https',
				'hook_tag' =>"hl_email_tag_transform",
			);

	/**
	 * errorMessage
	 *
	 * @var string $errorMessage
	 */
	var $errorMessage;

	// message encodings
	var $encoding = array("7bit", "8bit", "binary", "base64", "quoted-printable", "other");
	static $displayCharset;

	/**
	 * Instance of bopreference
	 *
	 * @var bopreferences
	 */
	var $bopreferences;

	/**
	 * Active preferences
	 *
	 * @var array
	 */
	var $mailPreferences;
	// set to true, if php is compiled with multi byte string support
	var $mbAvailable = FALSE;

	// what type of mimeTypes do we want from the body(text/html, text/plain)
	var $htmlOptions;

	/**
	 * Active mimeType
	 *
	 * @var string
	 */
	var $activeMimeType;

	var $sessionData;

	// the current selected user profile
	var $profileID = 0;

	/**
	 * Folders that get automatic created AND get translated to the users language
	 * their creation is also controlled by users mailpreferences. if set to none / dont use folder
	 * the folder will not be automatically created. This is controlled in bofelamimail->getFolderObjects
	 * so changing names here, must include a change of keywords there as well. Since these
	 * foldernames are subject to translation, keep that in mind too, if you change names here.
	 * ActiveSync:
	 *  Outbox is needed by Nokia Clients to be able to send Mails
	 * @var array
	 */
	static $autoFolders = array('Drafts', 'Templates', 'Sent', 'Trash', 'Junk', 'Outbox');

	/**
	* Autoload classes from emailadmin, 'til they get autoloading conform names
	*
	* @param string $class
	*/
	static function autoload($class)
	{
		if (strlen($class)<100)
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
	}

	/**
	 * Hold instances by profileID for getInstance() singleton
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Singleton for felamimail_bo
	 *
	 * @param boolean $_restoreSession=true
	 * @param int $_profileID=0
	 * @param boolean $_validate=true - flag wether the profileid should be validated or not, if validation is true, you may receive a profile
	 *                                  not matching the input profileID, if we can not find a profile matching the given ID
	 * @return object instance of felamimail_bo
	 */
	public static function getInstance($_restoreSession=true, $_profileID=0, $_validate=true)
	{
		if ($_profileID != 0 && $_validate)
		{
			$profileID = self::validateProfileID($_restoreSession, $_profileID);
			if ($profileID != $_profileID)
			{
				error_log(__METHOD__.__LINE__.' Validation of profile with ID:'.$_profileID.' failed. Using '.$profileID.' instead.');
				error_log(__METHOD__.__LINE__.' # Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid']);
				$_profileID = $profileID;
				$GLOBALS['egw']->preferences->add('felamimail','ActiveProfileID',$_profileID,'user');
				// save prefs
				$GLOBALS['egw']->preferences->save_repository(true);
				egw_cache::setSession('felamimail','activeProfileID',$_profileID);
			}
		}
		//error_log(__METHOD__.__LINE__.' RestoreSession:'.$_restoreSession.' ProfileId:'.$_profileID.' called from:'.function_backtrace());
		if (!isset(self::$instances[$_profileID]))
		{
			self::$instances[$_profileID] = new felamimail_bo('utf-8',$_restoreSession,$_profileID);
		}
		else
		{
			// make sure the prefs are up to date for the profile to load
			$loadfailed = false;
			self::$instances[$_profileID]->mailPreferences	= self::$instances[$_profileID]->bopreferences->getPreferences(true,$_profileID);
			//error_log(__METHOD__.__LINE__." ReRead the Prefs for ProfileID ".$_profileID.' called from:'.function_backtrace());
			if (self::$instances[$_profileID]->mailPreferences) {
				self::$instances[$_profileID]->icServer = self::$instances[$_profileID]->mailPreferences->getIncomingServer($_profileID);
				// if we do not get an icServer object, session restore failed on bopreferences->getPreferences
				if (!self::$instances[$_profileID]->icServer) $loadfailed=true;
				if ($_profileID != 0) self::$instances[$_profileID]->mailPreferences->setIncomingServer(self::$instances[$_profileID]->icServer,0);
				self::$instances[$_profileID]->ogServer = self::$instances[$_profileID]->mailPreferences->getOutgoingServer($_profileID);
				if ($_profileID != 0) self::$instances[$_profileID]->mailPreferences->setOutgoingServer(self::$instances[$_profileID]->ogServer,0);
				self::$instances[$_profileID]->htmlOptions  = self::$instances[$_profileID]->mailPreferences->preferences['htmlOptions'];
			}
			else
			{
				$loadfailed=true;
			}
			if ($loadfailed)
			{
				error_log(__METHOD__.__LINE__." ReRead of the Prefs for ProfileID ".$_profileID.' failed for icServer; trigger new instance. called from:'.function_backtrace());
				// restore session seems to provide an incomplete session
				self::$instances[$_profileID] = new felamimail_bo('utf-8',false,$_profileID);
			}
		}
		self::$instances[$_profileID]->profileID = $_profileID;
		//error_log(__METHOD__.__LINE__.' RestoreSession:'.$_restoreSession.' ProfileId:'.$_profileID);
		return self::$instances[$_profileID];
	}

	/**
	 * unset the private static instances by profileID
	 *
	 * @param int $_profileID=0
	 * @return void
	 */
	public static function unsetInstance($_profileID=0)
	{
		if (isset(self::$instances[$_profileID])) unset(self::$instances[$_profileID]);
	}

	/**
	 * validate the given profileId to make sure it is valid for the active user
	 *
	 * @param boolean $_restoreSession=true - needed to pass on to getInstance
	 * @param int $_profileID=0
	 * @return int validated profileID -> either the profileID given, or a valid one
	 */
	public static function validateProfileID($_restoreSession=true, $_profileID=0)
	{
		$identities = array();
		$mail = felamimail_bo::getInstance($_restoreSession, $_profileID, $validate=false); // we need an instance of felamimail_bo
		$selectedID = $mail->getIdentitiesWithAccounts($identities);
		if (is_object($mail->mailPreferences)) $activeIdentity =& $mail->mailPreferences->getIdentity($_profileID, true);
		// if you use user defined accounts you may want to access the profile defined with the emailadmin available to the user
		// as we validate the profile in question and may need to return an emailadminprofile, we fetch this one all the time
		$boemailadmin = new emailadmin_bo();
		$defaultProfile = $boemailadmin->getUserProfile() ;
		//error_log(__METHOD__.__LINE__.array2string($defaultProfile));
		$identitys =& $defaultProfile->identities;
		$icServers =& $defaultProfile->ic_server;
		foreach ($identitys as $tmpkey => $identity)
		{
			if (empty($icServers[$tmpkey]->host)) continue;
			$identities[$identity->id] = $identity->realName.' '.$identity->organization.' <'.$identity->emailAddress.'>';
		}

		//error_log(__METHOD__.__LINE__.array2string($identities));
		if (array_key_exists($_profileID,$identities))
		{
			// everything seems to be in order self::$profileID REMAINS UNCHANGED
		}
		else
		{
			if (array_key_exists($selectedID,$identities))
			{
				$_profileID = $selectedID;
			}
			else
			{
				foreach (array_keys((array)$identities) as $k => $ident)
				{
					//error_log(__METHOD__.__LINE__.' Testing Identity with ID:'.$ident.' for being provided by emailadmin.');
					if ($ident <0) $_profileID = $ident;
				}
				if (self::$debug) error_log(__METHOD__.__LINE__.' Profile Selected (after trying to fetch DefaultProfile):'.array2string($_profileID));
				if (!array_key_exists($_profileID,$identities))
				{
					// everything failed, try first profile found
					$keys = array_keys((array)$identities);
					if (count($keys)>0) $_profileID = array_shift($keys);
					else $_profileID = 0;
				}
			}
		}
		if (self::$debug) error_log(__METHOD__.'::'.__LINE__.' ProfileSelected:'.$_profileID.' -> '.$identities[$_profileID]);
		return $_profileID;
	}

	/**
	 * Private constructor, use felamimail_bo::getInstance() instead
	 *
	 * @param string $_displayCharset='utf-8'
	 * @param boolean $_restoreSession=true
	 * @param int $_profileID=0
	 */
	private function __construct($_displayCharset='utf-8',$_restoreSession=true, $_profileID=0)
	{
		$this->profileID = $_profileID;
		if ($_restoreSession)
		{
			//error_log(__METHOD__." Session restore ".function_backtrace());
			$this->restoreSessionData();
			$lv_mailbox = $this->sessionData['mailbox'];
			$firstMessage = $this->sessionData['previewMessage'];
		}
		else
		{
			$this->restoreSessionData();
			$lv_mailbox = $this->sessionData['mailbox'];
			$firstMessage = $this->sessionData['previewMessage'];
			$this->sessionData = array();
			$this->forcePrefReload();
		}
		//error_log(array2string(array($firstMessage,$lv_mailbox)));
		// FIXME: this->foldername seems to be unused
		//$this->foldername	= $this->sessionData['mailbox'];
		$this->accountid	= $GLOBALS['egw_info']['user']['account_id'];

		$this->bopreferences	= CreateObject('felamimail.bopreferences',$_restoreSession);

		$this->mailPreferences	= $this->bopreferences->getPreferences(true,$this->profileID);
		//error_log(__METHOD__.__LINE__." ProfileID ".$this->profileID.' called from:'.function_backtrace());
		if ($this->mailPreferences) {
			$this->icServer = $this->mailPreferences->getIncomingServer($this->profileID);
			if ($this->profileID != 0) $this->mailPreferences->setIncomingServer($this->icServer,0);
			$this->ogServer = $this->mailPreferences->getOutgoingServer($this->profileID);
			if ($this->profileID != 0) $this->mailPreferences->setOutgoingServer($this->ogServer,0);
			$this->htmlOptions  = $this->mailPreferences->preferences['htmlOptions'];
		}
		//_debug_array($this->mailPreferences->preferences);
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
			// store active profileID
			$this->sessionData['profileID']	= $_profileID;
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

	public static function forcePrefReload()
	{
		// unset the fm_preferences session object, to force the reload/rebuild
		$GLOBALS['egw']->session->appsession('fm_preferences','felamimail',serialize(array()));
		$GLOBALS['egw']->session->appsession('session_data','emailadmin',serialize(array()));
	}

	function _getNameSpaces()
	{
		static $nameSpace;
		$foldersNameSpace = array();
		$delimiter = $this->getHierarchyDelimiter();
		// TODO: cache by $this->icServer->ImapServerId
		if (is_null($nameSpace)) $nameSpace = $this->icServer->getNameSpaces();
		if (is_array($nameSpace)) {
			foreach($nameSpace as $type => $singleNameSpace) {
				$prefix_present = false;
				if($type == 'personal' && ($singleNameSpace[2]['name'] == '#mh/' || count($nameSpace) == 1) && ($this->folderExists('Mail')||$this->folderExists('INBOX')))
				{
					$foldersNameSpace[$type]['prefix_present'] = 'forced';
					// uw-imap server with mailbox prefix or dovecot maybe
					$foldersNameSpace[$type]['prefix'] = ($this->folderExists('Mail')?'Mail':'');
				}
				elseif($type == 'personal' && ($singleNameSpace[2]['name'] == '#mh/' || count($nameSpace) == 1) && $this->folderExists('mail'))
				{
					$foldersNameSpace[$type]['prefix_present'] = 'forced';
					// uw-imap server with mailbox prefix or dovecot maybe
					$foldersNameSpace[$type]['prefix'] = 'mail';
				} else {
					$foldersNameSpace[$type]['prefix_present'] = true;
					$foldersNameSpace[$type]['prefix'] = $singleNameSpace[0]['name'];
				}
				$foldersNameSpace[$type]['delimiter'] = $delimiter;
				//echo "############## $type->".print_r($foldersNameSpace[$type],true)." ###################<br>";
			}
		}
		return $foldersNameSpace;
	}

	function getFolderPrefixFromNamespace($nameSpace, $folderName)
	{
		foreach($nameSpace as $type => $singleNameSpace)
		{
			if (substr($singleNameSpace['prefix'],0,strlen($folderName))==$folderName) return $singleNameSpace['prefix'];
		}
		return "";
	}

	function setACL($_folderName, $_accountName, $_acl, $_recursive=false)
	{
		//$_recursive=true;
		//error_log(__METHOD__.__LINE__.'-> called with:'."$_folderName, $_accountName, $_acl, $_recursive");
		if ( PEAR::isError($this->icServer->setACL($_folderName, $_accountName, $_acl)) ) {
			return false;
		}
		if ($_recursive)
		{
			$delimiter = $this->getHierarchyDelimiter();
			$nameSpace = $this->_getNameSpaces();
			$prefix = $this->getFolderPrefixFromNamespace($nameSpace, $_folderName);
			//error_log(__METHOD__.__LINE__.'->'."$_folderName, $delimiter, $prefix");

			$subFolders = $this->getMailBoxesRecursive($_folderName, $delimiter, $prefix);
			//error_log(__METHOD__.__LINE__.' Fetched Subfolders->'.array2string($subFolders));
			foreach ($subFolders as $k => $folder)
			{
				// we do not monitor failure or success on subfolders
				if ($folder <> $_folderName) $this->icServer->setACL($folder, $_accountName, $_acl);
			}
		}
		return TRUE;
	}

	function deleteACL($_folderName, $_accountName, $_recursive=false)
	{
		//$_recursive=true;
		//error_log(__METHOD__.__LINE__." calledv with: $_folderName, $_accountName, $_recursive");
		if ( PEAR::isError($this->icServer->deleteACL($_folderName, $_accountName)) ) {
			return false;
		}
		if ($_recursive)
		{
			$delimiter = $this->getHierarchyDelimiter();
			$nameSpace = $this->_getNameSpaces();
			$prefix = $this->getFolderPrefixFromNamespace($nameSpace, $_folderName);
			//error_log(__METHOD__.__LINE__.'->'."$_folderName, $delimiter, $prefix");

			$subFolders = $this->getMailBoxesRecursive($_folderName, $delimiter, $prefix);
			//error_log(__METHOD__.__LINE__.' Fetched Subfolders->'.array2string($subFolders));
			foreach ($subFolders as $k => $folder)
			{
				// we do not monitor failure or success on subfolders
				if ($folder <> $_folderName) $this->icServer->deleteACL($folder, $_accountName);
			}
		}
		return TRUE;
	}

	/**
	* hook to add account
	*
	* this function is a wrapper function for emailadmin
	*
	* @param _hookValues contains the hook values as array
	* @return nothing
	*/
	function addAccount($_hookValues)
	{
		if ($this->mailPreferences) {
			$icServer = $this->mailPreferences->getIncomingServer($this->profileID);
			if(($icServer instanceof defaultimap)) {
				// if not connected, try opening an admin connection
				if (!$icServer->_connected) $this->openConnection($this->profileID,true);
				$icServer->addAccount($_hookValues);
				if ($icServer->_connected) $this->closeConnection(); // close connection afterwards
			}

			$ogServer = $this->mailPreferences->getOutgoingServer($this->profileID);
			if(($ogServer instanceof defaultsmtp)) {
				$ogServer->addAccount($_hookValues);
			}
		}
	}

	/**
	* save a message in folder
	*	throws exception on failure
	* @todo set flags again
	*
	* @param string _folderName the foldername
	* @param string _header the header of the message
	* @param string _body the body of the message
	* @param string _flags the imap flags to set for the saved message
	*
	* @return the id of the message appended or exception
	*/
	function appendMessage($_folderName, $_header, $_body, $_flags)
	{
		$header = ltrim(str_replace("\n","\r\n",$_header));
		$body   = str_replace("\n","\r\n",$_body);
		$messageid = $this->icServer->appendMessage("$header"."$body", $_folderName, $_flags);
		if ( PEAR::isError($messageid)) {
			if (self::$debug) error_log("Could not append Message:".print_r($messageid->message,true));
			throw new egw_exception_wrong_userinput(lang("Could not append Message:".array2string($messageid->message)));
			//return false;
		}
		if ($messageid === true) // try to figure out the message uid
		{
			$list = $this->getHeaders($_folderName, $_startMessage=1, $_numberOfMessages=1, $_sort=0, $_reverse=true, $_filter=array());
			if ($list)
			{
				if (self::$debug) error_log(__METHOD__.__LINE__.' MessageUid:'.$messageid.' but found:'.array2string($list));
				$messageid = $list['header'][0]['uid'];
			}
		}
		return $messageid;
	}

	function closeConnection() {
		//if ($icServer->_connected) error_log(__METHOD__.__LINE__.' disconnect from Server');
		if ($icServer->_connected) $this->icServer->disconnect();
	}

	/**
	* remove any messages which are marked as deleted or
	* remove any messages from the trashfolder
	*
	* @param string _folderName the foldername
	* @return nothing
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
	* @return mixed name of the newly created folder or false on error
	*/
	function createFolder($_parent, $_folderName, $_subscribe=false)
	{
		if (self::$debug) error_log(__METHOD__.__LINE__."->"."$_parent, $_folderName, $_subscribe");
		$parent		= $this->_encodeFolderName($_parent);
		$folderName	= $this->_encodeFolderName($_folderName);

		if(empty($parent)) {
			$newFolderName = $folderName;
		} else {
			$HierarchyDelimiter = $this->getHierarchyDelimiter();
			$newFolderName = $parent . $HierarchyDelimiter . $folderName;
		}
		if (self::$debug) error_log(__METHOD__.__LINE__.'->'.$newFolderName);
		if (self::folderExists($newFolderName,true))
		{
			error_log(__METHOD__.__LINE__." Folder $newFolderName already exists.");
			return $newFolderName;
		}
		$rv = $this->icServer->createMailbox($newFolderName);
		if ( PEAR::isError($rv ) ) {
			error_log(__METHOD__.__LINE__.' create Folder '.$newFolderName.'->'.$rv->message.' Namespace:'.array2string($this->icServer->getNameSpaces()));
			return false;
		}
		$srv = $this->icServer->subscribeMailbox($newFolderName);
		if ( PEAR::isError($srv ) ) {
			error_log(__METHOD__.__LINE__.' subscribe to new folder '.$newFolderName.'->'.$srv->message);
			return false;
		}

		return $newFolderName;

	}

	function createIMAPFilter($_folder, $_criterias)
	{
		$all = 'ALL UNDELETED'; //'ALL'
		//_debug_array($_criterias);
		if (self::$debug) error_log(__METHOD__.__LINE__.' Criterias:'.(!is_array($_criterias)?" none -> returning $all":array2string($_criterias)));
		if(!is_array($_criterias)) {
			return $all;
		}
		#error_log(print_r($_criterias, true));
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
				case 'SINCE':
				case 'BEFORE':
				case 'ON':
					$imapFilter .= $criteria .' '. $_criterias['string'].' ';
					break;
			}
		}

		foreach((array)$_criterias['status'] as $k => $criteria) {
			$criteria = strtoupper($criteria);
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
				case 'KEYWORD1':
				case 'KEYWORD2':
				case 'KEYWORD3':
				case 'KEYWORD4':
				case 'KEYWORD5':
					$imapFilter .= "KEYWORD ".'$label'.substr(trim($criteria),strlen('KEYWORD')).' ';
					break;
			}
		}
		if (isset($_criterias['range']) && !empty($_criterias['range']))
		{
			$imapFilter .= $_criterias['range'].' ';
		}
		if (self::$debug) error_log(__METHOD__.__LINE__." Filter: ".($imapFilter?$imapFilter:$all));
		if($imapFilter == '') {
			return $all;

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
	* @return string the converted foldername
	*/
	function decodeFolderName($_folderName)
	{
		return translation::convert($_folderName, self::$displayCharset, 'UTF7-IMAP');
	}

	function decodeMimePart($_mimeMessage, $_encoding, $_charset = '')
	{
		// decode the part
		if (self::$debug) error_log(__METHOD__."() with $_encoding and $_charset:".print_r($_mimeMessage,true));
		switch (strtoupper($_encoding))
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
	 * decode header (or envelope information)
	 * if array given, note that only values will be converted
	 * @param  mixed $_string input to be converted, if array call decode_header recursively on each value
	 * @return mixed - based on the input type
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
			return translation::decodeMailHeader($_string,self::$displayCharset);
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
		if (self::$debug) error_log(__METHOD__.__LINE__.' Dir to save winmail.dat to:'.$dir);
		$mime = CreateObject('phpgwapi.mime_magic');
		if ( $attachment['type'] == 'APPLICATION/MS-TNEF' && $attachment['filename'] == 'winmail.dat' )
		{
			// decode winmail.dat
			if ( !file_exists( "$dir/winmail.dat" ) )
			{
				@mkdir( $dir, 0700, true );
				file_put_contents( "$dir/winmail.dat", $attachment['attachment'] );

			}
			//if (self::$debug) unset($attachment['attachment']);
			if (self::$debug) error_log(__METHOD__.__LINE__." Saving Attachment to $dir. ->".array2string($attachment));
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
			$icServer = $this->mailPreferences->getIncomingServer($this->profileID);
			if(($icServer instanceof defaultimap)) {
				//try to connect with admin rights, when not connected
				if (!$icServer->_connected) $this->openConnection($this->profileID,true);
				$icServer->deleteAccount($_hookValues);
				if ($icServer->_connected) $this->closeConnection(); // close connection
			}

			$ogServer = $this->mailPreferences->getOutgoingServer($this->profileID);
			if(($ogServer instanceof defaultsmtp)) {
				$ogServer->deleteAccount($_hookValues);
			}
		}
	}

	/**
	* delete a existing folder
	*
	* @param string _folderName the name of the folder to be deleted
	*
	* @return bool true on success, false on failure
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

	function deleteMessages($_messageUID, $_folder=NULL, $_forceDeleteMethod='no')
	{
		//error_log(__METHOD__.__LINE__.'->'.array2string($_messageUID).','.$_folder);
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
		$deleteOptions = $_forceDeleteMethod; // use forceDeleteMethod if not "no", or unknown method
		if ($_forceDeleteMethod === 'no' || !in_array($_forceDeleteMethod,array('move_to_trash',"mark_as_deleted","remove_immediately"))) $deleteOptions  = $this->mailPreferences->preferences['deleteOptions'];
		//error_log(__METHOD__.__LINE__.'->'.array2string($_messageUID).','.$_folder.'/'.$this->sessionData['mailbox'].' Option:'.$deleteOptions);
		$trashFolder    = $this->mailPreferences->preferences['trashFolder'];
		$draftFolder	= $this->mailPreferences->preferences['draftFolder']; //$GLOBALS['egw_info']['user']['preferences']['felamimail']['draftFolder'];
		$templateFolder = $this->mailPreferences->preferences['templateFolder']; //$GLOBALS['egw_info']['user']['preferences']['felamimail']['templateFolder'];
		if(($_folder == $trashFolder && $deleteOptions == "move_to_trash") ||
		   ($_folder == $draftFolder)) {
			$deleteOptions = "remove_immediately";
		}
		if($this->icServer->getCurrentMailbox() != $_folder) {
			$oldMailbox = $this->icServer->getCurrentMailbox();
			$this->icServer->selectMailbox($_folder);
		}
		$updateCache = false;
		switch($deleteOptions) {
			case "move_to_trash":
				$updateCache = true;
				if(!empty($trashFolder)) {
					if (self::$debug) error_log(implode(' : ', $_messageUID));
					if (self::$debug) error_log("$trashFolder <= ". $this->sessionData['mailbox']);
					// copy messages
					$retValue = $this->icServer->copyMessages($trashFolder, $_messageUID, $_folder, true);
					if ( PEAR::isError($retValue) ) {
						if (self::$debug) error_log(__METHOD__." failed to copy Message(s) to $trashFolder: ".implode(',',$_messageUID));
						throw new egw_exception("failed to copy Message(s) to $trashFolder: ".implode(',',$_messageUID).' due to:'.array2string($retValue->message));
						return false;
					}
					// mark messages as deleted
					$retValue = $this->icServer->deleteMessages($_messageUID, true);
					if ( PEAR::isError($retValue)) {
						if (self::$debug) error_log(__METHOD__." failed to delete Message(s): ".implode(',',$_messageUID).' due to:'.$retValue->message);
						throw new egw_exception("failed to delete Message(s): ".implode(',',$_messageUID).' due to:'.array2string($retValue->message));
						return false;
					}
					// delete the messages finaly
					$rv = $this->icServer->expunge();
					if ( PEAR::isError($rv)) error_log(__METHOD__." failed to expunge Message(s) from Folder: ".$_folder.' due to:'.$rv->message);
				}
				break;

			case "mark_as_deleted":
				// mark messages as deleted
				foreach((array)$_messageUID as $key =>$uid)
				{
					//flag messages, that are flagged for deletion as seen too
					$this->flagMessages('read', $uid, $_folder);
					$flags = $this->getFlags($uid);
					//error_log(__METHOD__.__LINE__.array2string($flags));
					if (strpos( array2string($flags),'Deleted')!==false) $undelete[] = $uid;
					unset($flags);
				}
				$retValue = PEAR::isError($this->icServer->deleteMessages($_messageUID, true));
				foreach((array)$undelete as $key =>$uid)
				{
					$this->flagMessages('undelete', $uid, $_folder);
				}
				if ( PEAR::isError($retValue)) {
					if (self::$debug) error_log(__METHOD__." failed to mark as deleted for Message(s): ".implode(',',$_messageUID));
					throw new egw_exception("failed to mark as deleted for Message(s): ".implode(',',$_messageUID).' due to:'.array2string($retValue->message));
					return false;
				}
				break;

			case "remove_immediately":
				$updateCache = true;
				// mark messages as deleted
				$retValue = $this->icServer->deleteMessages($_messageUID, true);
				if ( PEAR::isError($retValue)) {
					if (self::$debug) error_log(__METHOD__." failed to remove immediately Message(s): ".implode(',',$_messageUID));
					throw new egw_exception("failed to remove immediately Message(s): ".implode(',',$_messageUID).' due to:'.array2string($retValue->message));
					return false;
				}
				// delete the messages finaly
				$this->icServer->expunge();
				break;
		}
		if ($updateCache)
		{
			$structure = egw_cache::getCache(egw_cache::INSTANCE,'email','structureCache'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
			$cachemodified = false;
			foreach ((array)$_messageUID as $k => $_uid)
			{
				if (isset($structure[$this->icServer->ImapServerId][$_folder][$_uid]) || $_uid=='all')
				{
					$cachemodified = true;
					if ($_uid=='all')
						unset($structure[$this->icServer->ImapServerId][$_folder]);
					else
						unset($structure[$this->icServer->ImapServerId][$_folder][$_uid]);
				}
			}
			if ($cachemodified) egw_cache::setCache(egw_cache::INSTANCE,'email','structureCache'.trim($GLOBALS['egw_info']['user']['account_id']),$structure,$expiration=60*60*1);
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
	* @return string the converted string
	*/
	function encodeFolderName($_folderName)
	{
		return translation::convert($_folderName, 'UTF7-IMAP', self::$displayCharset);
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
			error_log(__METHOD__.__LINE__.'Failed to retrieve Flags for Messages with UID(s)'.array2string($_messageUID).' Server returned:'.$flags->message);
			return null;
		}
		return $flags;
	}

	function getNotifyFlags ($_messageUID, $flags=null)
	{
		if($flags===null) $flags =  $this->icServer->getFlags($_messageUID, true);
		if (self::$debug) error_log(__METHOD__.$_messageUID.' Flags:'.array2string($flags));
		if (PEAR::isError($flags)) {
			return null;
			}
		if ( stripos( array2string($flags),'MDNSent')!==false)
			return true;

		if ( stripos( array2string($flags),'MDNnotSent')!==false)
			return false;

		return null;
	}

	/**
	 * flag a Message
	 *
	 * @param string _flag (readable name)
	 * @param mixed array/string _messageUID array of ids to flag, or 'all'
	 * @param string _folder foldername
	 *
	 * @todo handle handle icserver->setFlags returnValue
	 *
	 * @return bool true, as we do not handle icserver->setFlags returnValue
	 */
	function flagMessages($_flag, $_messageUID,$_folder=NULL)
	{
		//error_log(__METHOD__.__LINE__.'->' .$_flag." ".array2string($_messageUID).",$_folder /".$this->sessionData['mailbox']);
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

		$this->icServer->selectMailbox(($_folder?$_folder:$this->sessionData['mailbox']));

		switch($_flag) {
			case "undelete":
				$ret = $this->icServer->setFlags($_messageUID, '\\Deleted', 'remove', true);
				break;
			case "flagged":
				$ret = $this->icServer->setFlags($_messageUID, '\\Flagged', 'add', true);
				break;
			case "read":
				$ret = $this->icServer->setFlags($_messageUID, '\\Seen', 'add', true);
				break;
			case "forwarded":
				$ret = $this->icServer->setFlags($_messageUID, '$Forwarded', 'add', true);
			case "answered":
				$ret = $this->icServer->setFlags($_messageUID, '\\Answered', 'add', true);
				break;
			case "unflagged":
				$ret = $this->icServer->setFlags($_messageUID, '\\Flagged', 'remove', true);
				break;
			case "unread":
				$ret = $this->icServer->setFlags($_messageUID, '\\Seen', 'remove', true);
				$ret = $this->icServer->setFlags($_messageUID, '\\Answered', 'remove', true);
				$ret = $this->icServer->setFlags($_messageUID, '$Forwarded', 'remove', true);
				break;
			case "mdnsent":
				$ret = $this->icServer->setFlags($_messageUID, 'MDNSent', 'add', true);
				break;
			case "mdnnotsent":
				$ret = $this->icServer->setFlags($_messageUID, 'MDNnotSent', 'add', true);
				break;
			case "label1":
				$ret = $this->icServer->setFlags($_messageUID, '$label1', 'add', true);
				break;
			case "unlabel1":
				$this->icServer->setFlags($_messageUID, '$label1', 'remove', true);
				break;
			case "label2":
				$ret = $this->icServer->setFlags($_messageUID, '$label2', 'add', true);
				break;
			case "unlabel2":
				$ret = $this->icServer->setFlags($_messageUID, '$label2', 'remove', true);
				break;
			case "label3":
				$ret = $this->icServer->setFlags($_messageUID, '$label3', 'add', true);
				break;
			case "unlabel3":
				$ret = $this->icServer->setFlags($_messageUID, '$label3', 'remove', true);
				break;
			case "label4":
				$ret = $this->icServer->setFlags($_messageUID, '$label4', 'add', true);
				break;
			case "unlabel4":
				$ret = $this->icServer->setFlags($_messageUID, '$label4', 'remove', true);
				break;
			case "label5":
				$ret = $this->icServer->setFlags($_messageUID, '$label5', 'add', true);
				break;
			case "unlabel5":
				$ret = $this->icServer->setFlags($_messageUID, '$label5', 'remove', true);
				break;

		}
		if (PEAR::isError($ret))
		{
			if (stripos($ret->message,'Too long argument'))
			{
				$c = count($_messageUID);
				$h =ceil($c/2);
				error_log(__METHOD__.__LINE__.$ret->message." $c messages given for flagging. Trying with chunks of $h");
				$this->flagMessages($_flag, array_slice($_messageUID,0,$h),($_folder?$_folder:$this->sessionData['mailbox']));
				$this->flagMessages($_flag, array_slice($_messageUID,$h),($_folder?$_folder:$this->sessionData['mailbox']));
			}
		}

		$this->sessionData['folderStatus'][$this->profileID][$this->sessionData['mailbox']]['uidValidity'] = 0;
		$this->saveSessionData();
		//error_log(__METHOD__.__LINE__.'->' .$_flag." ".array2string($_messageUID).",".($_folder?$_folder:$this->sessionData['mailbox']));
		return true; // as we do not catch/examine setFlags returnValue
	}

	function _getStatus($folderName,$ignoreStatusCache=false)
	{
		static $folderStatus;
		if (!$ignoreStatusCache && isset($folderStatus[$this->icServer->ImapServerId][$folderName]))
		{
			//error_log(__METHOD__.__LINE__.' Using cache for status on Server:'.$this->icServer->ImapServerId.' for folder:'.$folderName.'->'.array2string($folderStatus[$this->icServer->ImapServerId][$folderName]));
			return $folderStatus[$this->icServer->ImapServerId][$folderName];
		}
		$folderStatus[$this->icServer->ImapServerId][$folderName] = $this->icServer->getStatus($folderName);
		return $folderStatus[$this->icServer->ImapServerId][$folderName];
	}

	function _getStructure($_uid, $byUid=true, $_ignoreCache=false, $_folder = '')
	{
		static $structure;
		if (empty($_folder)) $_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		//error_log(__METHOD__.__LINE__.'User:'.trim($GLOBALS['egw_info']['user']['account_id'])." UID: $_uid, ".$this->icServer->ImapServerId.','.$_folder);
		if (is_null($structure)) $structure = egw_cache::getCache(egw_cache::INSTANCE,'email','structureCache'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
		if (isset($structure[$this->icServer->ImapServerId]) && !empty($structure[$this->icServer->ImapServerId]) &&
			isset($structure[$this->icServer->ImapServerId][$_folder]) && !empty($structure[$this->icServer->ImapServerId][$_folder]) &&
			isset($structure[$this->icServer->ImapServerId][$_folder][$_uid]) && !empty($structure[$this->icServer->ImapServerId][$_folder][$_uid]))
		{
			if ($_ignoreCache===false)
			{
				//error_log(__METHOD__.__LINE__.' Using cache for structure on Server:'.$this->icServer->ImapServerId.' for uid:'.$_uid." in Folder:".$_folder.'->'.array2string($structure[$this->icServer->ImapServerId][$_folder][$_uid]));
				return $structure[$this->icServer->ImapServerId][$_folder][$_uid];
			}
		}
		$structure[$this->icServer->ImapServerId][$_folder][$_uid] = $this->icServer->getStructure($_uid, $byUid);
		egw_cache::setCache(egw_cache::INSTANCE,'email','structureCache'.trim($GLOBALS['egw_info']['user']['account_id']),$structure,$expiration=60*60*1);
		//error_log(__METHOD__.__LINE__.' Using query for structure on Server:'.$this->icServer->ImapServerId.' for uid:'.$_uid." in Folder:".$_folder.'->'.array2string($structure[$this->icServer->ImapServerId][$_folder][$_uid]));
		return $structure[$this->icServer->ImapServerId][$_folder][$_uid];
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
				error_log(__METHOD__."(". __LINE__ .") partID's don't match");
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

	static function getCleanHTML(&$_html, $usepurify = false, $cleanTags=true)
	{
		// remove CRLF and TAB as it is of no use in HTML.
		// but they matter in <pre>, so we rather don't
		//$_html = str_replace("\r\n",' ',$_html);
		//$_html = str_replace("\t",' ',$_html);
		//error_log($_html);
		//repair doubleencoded ampersands, and some stuff htmLawed stumbles upon with balancing switched on
		$_html = str_replace(array('&amp;amp;','<DIV><BR></DIV>',"<DIV>&nbsp;</DIV>",'<div>&nbsp;</div>'),array('&amp;','<BR>','<BR>','<BR>'),$_html);
		//$_html = str_replace(array('&amp;amp;'),array('&amp;'),$_html);
		if (stripos($_html,'style')!==false) self::replaceTagsCompletley($_html,'style'); // clean out empty or pagewide style definitions / left over tags
		if (stripos($_html,'head')!==false) self::replaceTagsCompletley($_html,'head'); // Strip out stuff in head
		//if (stripos($_html,'![if')!==false && stripos($_html,'<![endif]>')!==false) self::replaceTagsCompletley($_html,'!\[if','<!\[endif\]>',false); // Strip out stuff in ifs
		if (stripos($_html,'!--[if')!==false && stripos($_html,'<![endif]-->')!==false) self::replaceTagsCompletley($_html,'!--\[if','<!\[endif\]-->',false); // Strip out stuff in ifs
		//error_log($_html);
		// force the use of kses, as it is still have the edge over purifier with some stuff
		$usepurify = true;
		if ($usepurify)
		{
			// we need a customized config, as we may allow external images, $GLOBALS['egw_info']['user']['preferences']['felamimail']['allowExternalIMGs']
			if (get_magic_quotes_gpc() === 1) $_html = stripslashes($_html);
			// Strip out doctype in head, as htmlLawed cannot handle it TODO: Consider extracting it and adding it afterwards
			if (stripos($_html,'!doctype')!==false) self::replaceTagsCompletley($_html,'!doctype');
			if (stripos($_html,'?xml:namespace')!==false) self::replaceTagsCompletley($_html,'\?xml:namespace','/>',false);
			if (stripos($_html,'?xml version')!==false) self::replaceTagsCompletley($_html,'\?xml version','\?>',false);
			if (strpos($_html,'!CURSOR')!==false) self::replaceTagsCompletley($_html,'!CURSOR');
			// htmLawed filter only the 'body'
			//preg_match('`(<htm.+?<body[^>]*>)(.+?)(</body>.*?</html>)`ims', $_html, $matches);
			//if ($matches[2])
			//{
			//	$hasOther = true;
			//	$_html = $matches[2];
			//}
			// purify got switched to htmLawed
			// some testcode to test purifying / htmlawed
			//$_html = "<BLOCKQUOTE>hi <div> there </div> kram <br> </blockquote>".$_html;
			$_html = html::purify($_html,self::$htmLawed_config,array(),true);
			//if ($hasOther) $_html = $matches[1]. $_html. $matches[3];
			// clean out comments , should not be needed as purify should do the job.
			$search = array(
				'@url\(http:\/\/[^\)].*?\)@si',  // url calls e.g. in style definitions
				'@<!--[\s\S]*?[ \t\n\r]*-->@',         // Strip multi-line comments including CDATA
			);
			$_html = preg_replace($search,"",$_html);
			// remove non printable chars
            $_html = preg_replace('/([\000-\012])/','',$_html);
			//error_log($_html);
		}
		// using purify above should have tidied the tags already sufficiently
		if ($usepurify == false && $cleanTags==true)
		{
			if (extension_loaded('tidy'))
			{
				$tidy = new tidy();
				$cleaned = $tidy->repairString($_html, self::$tidy_config,'utf8');
				// Found errors. Strip it all so there's some output
				if($tidy->getStatus() == 2)
				{
					error_log(__METHOD__.__LINE__.' ->'.$tidy->errorBuffer);
				}
				else
				{
					$_html = $cleaned;
				}
			}
			else
			{
				//$to = ini_get('max_execution_time');
				//@set_time_limit(10);
				$htmLawed = new egw_htmLawed();
				$_html = $htmLawed->egw_htmLawed($_html);
				//error_log(__METHOD__.__LINE__.$_html);
				//@set_time_limit($to);
			}
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
	 * @return array
	 */
	function getAttachment($_uid, $_partID, $_winmail_nr=0)
	{
		// parse message structure
		$structure = $this->_getStructure($_uid, true);
		if($_partID != '') {
			$structure = $this->_getSubStructure($structure, $_partID);
		}
		$filename = $this->getFileNameFromStructure($structure, $_uid, $structure->partID);
		$attachment = $this->icServer->getBodyPart($_uid, $_partID, true, true);
		if (PEAR::isError($attachment))
		{
			error_log(__METHOD__.__LINE__.' failed:'.$attachment->message);
			return array('type' => 'text/plain',
						 'filename' => 'error.txt',
						 'attachment' =>__METHOD__.' failed:'.$attachment->message
					);
		}

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
		if ($structure->type === 'TEXT' && isset($structure->parameters['CHARSET']) && stripos('UTF-16',$structure->parameters['CHARSET'])!==false)
		{
			$attachment = translation::convert($attachment,$structure->parameters['CHARSET'],self::$displayCharset);
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

	/**
	 * Fetch a specific attachment from a message by it's cid
	 *
	 * this function is based on a on "Building A PHP-Based Mail Client"
	 * http://www.devshed.com
	 *
	 * @param string|int $_uid
	 * @param string $_cid
	 * @param string $_part
	 * @return array with values for keys 'type', 'filename' and 'attachment'
	 */
	function getAttachmentByCID($_uid, $_cid, $_part)
	{
		// some static variables to avoid fetching the same mail multiple times
		static $uid,$part,$attachments,$structure;
		//error_log("getAttachmentByCID:$_uid, $_cid, $_part");

		if(empty($_cid)) return false;

		if ($_uid != $uid || $_part != $part)
		{
			$attachments = $this->getMessageAttachments($uid=$_uid, $part=$_part);
			$structure = null;
		}
		$partID = false;
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
		if (is_null($structure))
		{
			$structure = $this->_getStructure($_uid, true);
		}
		$part_structure = $this->_getSubStructure($structure, $partID);
		$filename = $this->getFileNameFromStructure($part_structure, $_uid, $_uid, $part_structure->partID);
		$attachment = $this->icServer->getBodyPart($_uid, $partID, true);
		if (PEAR::isError($attachment))
		{
			error_log(__METHOD__.__LINE__.' failed:'.$attachment->message);
			return array('type' => 'text/plain',
						 'filename' => 'error.txt',
						 'attachment' =>__METHOD__.' failed:'.$attachment->message
					);
		}

		if (PEAR::isError($attachment))
		{
			error_log(__METHOD__.__LINE__.' failed:'.$attachment->message);
			return array('type' => 'text/plain',
						 'filename' => 'error.txt',
						 'attachment' =>__METHOD__.' failed:'.$attachment->message
					);
		}

		switch ($part_structure->encoding) {
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
			'type'		=> $part_structure->type .'/'. $part_structure->subType,
			'filename'	=> $filename,
			'attachment'	=> $attachment
		);
		// try guessing the mimetype, if we get the application/octet-stream
		if (strtolower($attachmentData['type']) == 'application/octet-stream') $attachmentData['type'] = mime_magic::filename2mime($attachmentData['filename']);

		return $attachmentData;
	}

	/**
	 * getIdentitiesWithAccounts
	 *
	 * @param array reference to pass all identities back
	 * @return the default Identity (active) or 0
	 */
	function getIdentitiesWithAccounts(&$identities)
	{
		// account select box
		$selectedID = $this->profileID;
		if($this->mailPreferences->userDefinedAccounts) $allAccountData = $this->bopreferences->getAllAccountData($this->mailPreferences);

		if ($allAccountData) {
			foreach ($allAccountData as $tmpkey => $accountData)
			{
				$identity =& $accountData['identity'];
				$icServer =& $accountData['icServer'];
				//_debug_array($identity);
				//_debug_array($icServer);
				if (empty($icServer->host)) continue;
				$identities[$identity->id]=$identity->realName.' '.$identity->organization.' <'.$identity->emailAddress.'>';
				if (!empty($identity->default)) $selectedID = $identity->id;
			}
		}

		return $selectedID;
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
		$rv =$this->icServer->_connectionErrorObject->message;
		if (empty($rv)) $rv =$this->errorMessage;
		return $rv;
	}

	/**
	* get IMAP folder status
	*
	* returns an array information about the imap folder
	*
	* @param _folderName string the foldername
	*
	* @return array
	*/
	function getFolderStatus($_folderName,$ignoreStatusCache=false)
	{
		if (self::$debug) error_log(__METHOD__." called with:".$_folderName);
		$retValue = array();
		$retValue['subscribed'] = false;
		if(!$icServer = $this->mailPreferences->getIncomingServer($this->profileID)) {
			if (self::$debug) error_log(__METHOD__." no Server found for Folder:".$_folderName);
			return false;
		}

		// does the folder exist???
		$folderInfo = $this->icServer->getMailboxes('', $_folderName, true);

		if(($folderInfo instanceof PEAR_Error) || !is_array($folderInfo[0])) {
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

		if ( PEAR::isError($folderStatus = $this->_getStatus($_folderName,$ignoreStatusCache)) ) {
			if (self::$debug) error_log(__METHOD__." returned folderStatus for Folder $_folderName:".print_r($folderStatus->message,true));
		} else {
			$retValue['messages']		= $folderStatus['MESSAGES'];
			$retValue['recent']		= $folderStatus['RECENT'];
			$retValue['uidnext']		= $folderStatus['UIDNEXT'];
			$retValue['uidvalidity']	= $folderStatus['UIDVALIDITY'];
			$retValue['unseen']		= $folderStatus['UNSEEN'];
			if (//$retValue['unseen']==0 &&
				isset($this->mailPreferences->preferences['trustServersUnseenInfo']) && // some servers dont serve the UNSEEN information
				$this->mailPreferences->preferences['trustServersUnseenInfo']==false)
			{
				// we filter for the combined status of unseen and undeleted, as this is what we show in list
				$sortResult = $this->getSortedList($_folderName, $_sort=0, $_reverse=1, $_filter=array('status'=>array('UNSEEN','UNDELETED')),$byUid=true,false);
				$retValue['unseen'] = count($sortResult);
			}
		}

		return $retValue;
	}

	static function resetConnectionErrorCache($_ImapServerId=null)
	{
		//error_log(__METHOD__.__LINE__.' for Profile:'.array2string($_ImapServerId) .' for user:'.trim($GLOBALS['egw_info']['user']['account_id']));
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		if (is_array($_ImapServerId))
		{
			// called via hook
			$account_id = $_ImapServerId['account_id'];
			unset($_ImapServerId);
			$_ImapServerId = null;
		}
		if (is_null($_ImapServerId))
		{
			$buff = array();
			$isConError = array();
		}
		else
		{
			$buff = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($account_id));
			if (isset($buff[$_ImapServerId]))
			{
				unset($buff[$_ImapServerId]);
			}
			$isConError = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($account_id));
			if (isset($isConError[$_ImapServerId]))
			{
				unset($isConError[$_ImapServerId]);
			}
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($account_id),$buff,$expiration=60*15);
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($account_id),$isConError,$expiration=60*15);
	}

	static function resetFolderObjectCache($_ImapServerId=null)
	{
		//error_log(__METHOD__.__LINE__.' called for Profile:'.$_ImapServerId.'->'.function_backtrace());
		if (is_null($_ImapServerId))
		{
			$folders2return = array();
			$folderInfo = array();
		}
		else
		{
			$folders2return = egw_cache::getCache(egw_cache::INSTANCE,'email','folderObjects'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
			if (isset($folders2return[$_ImapServerId]))
			{
				unset($folders2return[$_ImapServerId]);
			}
			$folderInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*5);
			if (isset($folderInfo[$_ImapServerId]))
			{
				unset($folderInfo[$_ImapServerId]);
			}
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','folderObjects'.trim($GLOBALS['egw_info']['user']['account_id']),$folders2return, $expiration=60*60*1);
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderInfo,$expiration=60*5);
	}

	/**
	* get IMAP folder objects
	*
	* returns an array of IMAP folder objects. Put INBOX folder in first
	* position. Preserves the folder seperator for later use. The returned
	* array is indexed using the foldername. Use cachedObjects when retrieving subscribedFolders
	*
	* @param _subscribedOnly boolean get subscribed or all folders
	* @param _getCounters    boolean get get messages counters
	* @param _alwaysGetDefaultFolders boolean this triggers to ignore the possible notavailableautofolders - preference
	*			as activeSync needs all folders like sent, trash, drafts, templates and outbox - if not present devices may crash
	*
	* @return array with folder objects. eg.: INBOX => {inbox object}
	*/
	function getFolderObjects($_subscribedOnly=false, $_getCounters=false, $_alwaysGetDefaultFolders=false)
	{
		if (self::$debug) error_log(__METHOD__.__LINE__.' '."subscribedOnly:$_subscribedOnly, getCounters:$_getCounters, alwaysGetDefaultFolders:$_alwaysGetDefaultFolders");
		static $folders2return;
		if ($_subscribedOnly && $_getCounters===false)
		{
			if (is_null($folders2return)) $folders2return = egw_cache::getCache(egw_cache::INSTANCE,'email','folderObjects'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
			if (isset($folders2return[$this->icServer->ImapServerId]) && !empty($folders2return[$this->icServer->ImapServerId]))
			{
				//error_log(__METHOD__.__LINE__.' using Cached folderObjects');
				return $folders2return[$this->icServer->ImapServerId];
			}
		}
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

		//$nameSpace = $this->icServer->getNameSpaces();
		$nameSpace = $this->_getNameSpaces();
		//_debug_array($nameSpace);
		//_debug_array($delimiter);
		if(isset($nameSpace['#mh/'])) {
			// removed the uwimap code
			// but we need to reintroduce him later
			// uw imap does not return the attribute of a folder, when requesting subscribed folders only
			// dovecot has the same problem too
		} else {
			if (is_array($nameSpace)) {
			  foreach($nameSpace as $type => $singleNameSpace) {
				$prefix_present = $nameSpace[$type]['prefix_present'];
				$foldersNameSpace[$type] = $nameSpace[$type];

				if(is_array($singleNameSpace)) {
					// fetch and sort the subscribed folders
					$subscribedMailboxes = $this->icServer->listsubscribedMailboxes($foldersNameSpace[$type]['prefix']);
					if (empty($subscribedMailboxes) && $type == 'shared')
					{
						$subscribedMailboxes = $this->icServer->listsubscribedMailboxes('',0);
					}
					else
					{
						if ($prefix_present=='forced') // you cannot trust dovecots assumed prefix
						{
							$subscribedMailboxesAll = $this->icServer->listsubscribedMailboxes('',0);
							if( PEAR::isError($subscribedMailboxesAll) ) continue;
							foreach ($subscribedMailboxesAll as $ksMA => $sMA) if (!in_array($sMA,$subscribedMailboxes)) $subscribedMailboxes[] = $sMA;
						}
					}

					//echo "subscribedMailboxes";_debug_array($subscribedMailboxes);
					if( PEAR::isError($subscribedMailboxes) ) {
						continue;
					}
					$foldersNameSpace[$type]['subscribed'] = $subscribedMailboxes;
					if (is_array($foldersNameSpace[$type]['subscribed'])) sort($foldersNameSpace[$type]['subscribed']);
					//_debug_array($foldersNameSpace);
					if ($_subscribedOnly == true) {
						$foldersNameSpace[$type]['all'] = (is_array($foldersNameSpace[$type]['subscribed']) ? $foldersNameSpace[$type]['subscribed'] :array());
						continue;
					}
					// only check for Folder in FolderMaintenance for Performance Reasons
					if(!$_subscribedOnly) {
						foreach ((array)$foldersNameSpace[$type]['subscribed'] as $folderName)
						{
							if ($foldersNameSpace[$type]['prefix'] == $folderName || $foldersNameSpace[$type]['prefix'] == $folderName.$foldersNameSpace[$type]['delimiter']) continue;
							//echo __METHOD__."Checking $folderName for existence<br>";
							if (!self::folderExists($folderName,true)) {
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
					//echo $type.'->'.$foldersNameSpace[$type]['prefix'].'->'.($type=='shared'?0:2)."<br>";
					$allMailboxesExt = $this->icServer->getMailboxes($foldersNameSpace[$type]['prefix'],2,true);
					if( PEAR::isError($allMailboxesExt) )
					{
						error_log(__METHOD__.__LINE__.' Failed to retrieve all Boxes:'.$allMailboxesExt->message);
						$allMailboxesExt = array();
					}
					if (empty($allMailboxesExt) && $type == 'shared')
					{
						$allMailboxesExt = $this->icServer->getMailboxes('',0,true);
					}
					else
					{
						if ($prefix_present=='forced') // you cannot trust dovecots assumed prefix
						{
							$allMailboxesExtAll = $this->icServer->getMailboxes('',0,true);
							foreach ($allMailboxesExtAll as $kaMEA => $aMEA)
							{
								if( PEAR::isError($aMEA) ) continue;
								if (!in_array($aMEA,$allMailboxesExt)) $allMailboxesExt[] = $aMEA;
							}
						}
					}
					if( PEAR::isError($allMailboxesExt) ) {
						#echo __METHOD__;_debug_array($allMailboxesExt);
						continue;
					}
					$allMailBoxesExtSorted = array();
					foreach ((array)$allMailboxesExt as $mbx) {
						//echo __METHOD__;_debug_array($mbx);
						//error_log(__METHOD__.__LINE__.array2string($mbx));
						if (isset($allMailBoxesExtSorted[$mbx['MAILBOX']])||
							isset($allMailBoxesExtSorted[$mbx['MAILBOX'].$foldersNameSpace[$type]['delimiter']])||
							(substr($mbx['MAILBOX'],-1)==$foldersNameSpace[$type]['delimiter'] && isset($allMailBoxesExtSorted[substr($mbx['MAILBOX'],0,-1)]))
						) continue;

						//echo '#'.$mbx['MAILBOX'].':'.array2string($mbx)."#<br>";
						$allMailBoxesExtSorted[$mbx['MAILBOX']] = $mbx;
					}
					if (is_array($allMailBoxesExtSorted)) ksort($allMailBoxesExtSorted);
					//_debug_array($allMailBoxesExtSorted);
					$allMailboxes = array();
					foreach ((array)$allMailBoxesExtSorted as $mbx) {
						//echo $mbx['MAILBOX']."<br>";
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
						//echo "Result:";_debug_array($allMailboxes);
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
				else
				{
					if(substr($personalPrefix, -1) != $personalDelimiter) {
						$folderPrefixAsInbox = 'INBOX' . $personalDelimiter;
					} else {
						$folderPrefixAsInbox = 'INBOX';
					}
				}
				if (!$_alwaysGetDefaultFolders && $this->mailPreferences->preferences['notavailableautofolders'] && !empty($this->mailPreferences->preferences['notavailableautofolders']))
				{
					$foldersToCheck = array_diff(self::$autoFolders,explode(',',$this->mailPreferences->preferences['notavailableautofolders']));
				} else {
					$foldersToCheck = self::$autoFolders;
				}
				//error_log(__METHOD__.__LINE__." foldersToCheck:".array2string($foldersToCheck));
				//error_log(__METHOD__.__LINE__." foldersToCheck:".array2string( $this->mailPreferences->preferences['sentFolder']));
				foreach($foldersToCheck as $personalFolderName) {
					$folderName = (!empty($personalPrefix) ? $folderPrefix.$personalFolderName : $personalFolderName);
					//error_log(__METHOD__.__LINE__." foldersToCheck: $personalFolderName / $folderName");
					if(!is_array($foldersNameSpace['personal']['all']) || !in_array($folderName, $foldersNameSpace['personal']['all'])) {
						$createfolder = true;
						switch($personalFolderName)
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
								// ToDo: we may need more sophistcated checking here
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
							case 'Outbox': // Nokia Outbox for activesync
								//if ($this->mailPreferences->preferences['outboxFolder'] && $this->mailPreferences->preferences['outboxFolder']=='none')
									$createfolder=false;
								if ($GLOBALS['egw_info']['user']['apps']['activesync']) $createfolder = true;
								break;
						}
						// check for the foldername as constructed with prefix (or not)
						if ($createfolder && self::folderExists($folderName))
						{
							$createfolder = false;
						}
						// check for the folder as it comes (no prefix)
						if ($createfolder && $personalFolderName != $folderName && self::folderExists($personalFolderName))
						{
							$createfolder = false;
							$folderName = $personalFolderName;
						}
						// check for the folder as it comes with INBOX prefixed
						$folderWithInboxPrefixed = $folderPrefixAsInbox.$personalFolderName;
						if ($createfolder && $folderWithInboxPrefixed != $folderName && self::folderExists($folderWithInboxPrefixed))
						{
							$createfolder = false;
							$folderName = $folderWithInboxPrefixed;
						}
						// now proceed with the folderName that may be altered in the progress of testing for existence
						if ($createfolder === false && $_alwaysGetDefaultFolders)
						{
							if (!in_array($folderName,$foldersNameSpace['personal']['all'])) $foldersNameSpace['personal']['all'][] = $folderName;
							if (!in_array($folderName,$foldersNameSpace['personal']['subscribed'])) $foldersNameSpace['personal']['subscribed'][] = $folderName;
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
		//echo "<br>FolderNameSpace To Process:";_debug_array($foldersNameSpace);
		$autoFolderObjects = array();
		foreach( array('personal', 'others', 'shared') as $type) {
			if(isset($foldersNameSpace[$type])) {
				if($_subscribedOnly) {
					if( !PEAR::isError($foldersNameSpace[$type]['subscribed']) ) $listOfFolders = $foldersNameSpace[$type]['subscribed'];
				} else {
					if( !PEAR::isError($foldersNameSpace[$type]['all'])) $listOfFolders = $foldersNameSpace[$type]['all'];
				}
				foreach((array)$listOfFolders as $folderName) {
					//echo "<br>FolderToCheck:$folderName<br>";
					if($_subscribedOnly && !(in_array($folderName, $foldersNameSpace[$type]['all'])||in_array($folderName.$foldersNameSpace[$type]['delimiter'], $foldersNameSpace[$type]['all']))) {
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
					//$folderName = $folderName;
					if (in_array($shortName,self::$autoFolders)&&self::searchValueInFolderObjects($shortName,$autoFolderObjects)===false) {
						$autoFolderObjects[$folderName] = $folderObject;
					} else {
						$folders[$folderName] = $folderObject;
					}
				}
			}
		}
		if (is_array($autoFolderObjects) && !empty($autoFolderObjects)) {
			uasort($autoFolderObjects,array($this,"sortByAutoFolderPos"));
		}
		if (is_array($folders)) uasort($folders,array($this,"sortByDisplayName"));
		//$folders2return = array_merge($autoFolderObjects,$folders);
		//_debug_array($folders2return); #exit;
		$folders2return[$this->icServer->ImapServerId] = array_merge($inboxFolderObject,$autoFolderObjects,(array)$folders);
		if ($_subscribedOnly && $_getCounters===false) egw_cache::setCache(egw_cache::INSTANCE,'email','folderObjects'.trim($GLOBALS['egw_info']['user']['account_id']),$folders2return,$expiration=60*60*1);
		return $folders2return[$this->icServer->ImapServerId];
	}

	static function searchValueInFolderObjects($needle, $haystack)
	{
		$rv = false;
		foreach ($haystack as $k => $v)
		{
			foreach($v as $sk => $sv) if (trim($sv)==trim($needle)) return $k;
		}
		return $rv;
	}

	function sortByDisplayName($a,$b)
	{
		// 0, 1 und -1
		return strcasecmp($a->displayName,$b->displayName);
	}

	function sortByAutoFolderPos($a,$b)
	{
		// 0, 1 und -1
		$pos1 = array_search(trim($a->shortFolderName),self::$autoFolders);
		$pos2 = array_search(trim($b->shortFolderName),self::$autoFolders);
		if ($pos1 == $pos2) return 0;
		return ($pos1 < $pos2) ? -1 : 1;
	}

	function getMailBoxCounters($folderName)
	{
		$folderStatus = $this->_getStatus($folderName);
		//error_log(__METHOD__.__LINE__." FolderStatus:".array2string($folderStatus));
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
		// clean up double delimiters
		$_mailbox = preg_replace('~'.($delimiter == '.' ? "\\".$delimiter:$delimiter).'+~s',$delimiter,$_mailbox);
		//get that mailbox in question
		$mbx = $this->icServer->getMailboxes($_mailbox,1,true);
		#_debug_array($mbx);
		if (is_array($mbx[0]["ATTRIBUTES"]) && (in_array('\HasChildren',$mbx[0]["ATTRIBUTES"]) || in_array('\Haschildren',$mbx[0]["ATTRIBUTES"]))) {
			// if there are children fetch them
			//echo $mbx[0]['MAILBOX']."<br>";
			unset($buff);
			$buff = $this->icServer->getMailboxes($mbx[0]['MAILBOX'].($mbx[0]['MAILBOX'] == $prefix ? '':$delimiter),2,false);
			//$buff = $this->icServer->getMailboxes($mbx[0]['MAILBOX'],2,false);
			//_debug_array($buff);
			if( PEAR::isError($buff) ) {
				if (self::$debug) error_log(__METHOD__." Error while retrieving Mailboxes for:".$mbx[0]['MAILBOX'].$delimiter.".");
				return array();
			} else {
				$allMailboxes = array();
				foreach ($buff as $mbxname) {
					$mbxname = preg_replace('~'.($delimiter == '.' ? "\\".$delimiter:$delimiter).'+~s',$delimiter,$mbxname);
					#echo "About to recur in level $reclevel:".$mbxname."<br>";
					if ( $mbxname != $mbx[0]['MAILBOX'] && $mbxname != $prefix  && $mbxname != $mbx[0]['MAILBOX'].$delimiter) $allMailboxes = array_merge($allMailboxes, self::getMailBoxesRecursive($mbxname, $delimiter, $prefix, $reclevel));
				}
				if (!(in_array('\NoSelect',$mbx[0]["ATTRIBUTES"]) || in_array('\Noselect',$mbx[0]["ATTRIBUTES"]))) $allMailboxes[] = $mbx[0]['MAILBOX'];
				return $allMailboxes;
			}
		} else {
			return array($_mailbox);
		}
	}

	/**
	 * getMimePartCharset - fetches the charset mimepart if it exists
	 * @param $_mimePartObject structure object
	 * @return mixed mimepart or false if no CHARSET is found, the missing charset has to be handled somewhere else,
	 *		as we cannot safely assume any charset as we did earlier
	 */
	function getMimePartCharset($_mimePartObject)
	{
		//$charSet = 'iso-8859-1';//self::$displayCharset; //'iso-8859-1'; // self::displayCharset seems to be asmarter fallback than iso-8859-1
		$CharsetFound=false;
		//echo "#".$_mimePartObject->encoding.'#<br>';
		if(is_array($_mimePartObject->parameters)) {
			if(isset($_mimePartObject->parameters['CHARSET'])) {
				$charSet = $_mimePartObject->parameters['CHARSET'];
				$CharsetFound=true;
			}
		}
		// this one is dirty, but until I find something that does the trick of detecting the encoding, ....
		//if ($CharsetFound == false && $_mimePartObject->encoding == "QUOTED-PRINTABLE") $charSet = 'iso-8859-1'; //assume quoted-printable to be ISO
		//if ($CharsetFound == false && $_mimePartObject->encoding == "BASE64") $charSet = 'utf-8'; // assume BASE64 to be UTF8
		return ($CharsetFound ? $charSet : $CharsetFound);
	}

	function getMultipartAlternative($_uid, $_structure, $_htmlMode, $_preserveSeen = false)
	{
		// a multipart/alternative has exactly 2 parts (text and html  OR  text and something else)
		// sometimes there are 3 parts, when there is an ics/ical attached/included-> we want to show that
		// as attachment AND as abstracted ical information (we use our notification style here).
		$partText = false;
		$partHTML = false;
		if (self::$debug) _debug_array(array("METHOD"=>__METHOD__,"LINE"=>__LINE__,"STRUCTURE"=>$_structure));
		foreach($_structure as $mimePart) {
			if($mimePart->type == 'TEXT' && ($mimePart->subType == 'PLAIN' || $mimePart->subType == 'CALENDAR') && $mimePart->bytes > 0) {
				if ($mimePart->subType == 'CALENDAR' && $partText === false) $partText = $mimePart; // only if there is no partText set already
				if ($mimePart->subType == 'PLAIN') $partText = $mimePart;
			} elseif($mimePart->type == 'TEXT' && $mimePart->subType == 'HTML' && $mimePart->bytes > 0) {
				$partHTML = $mimePart;
			} elseif ($mimePart->type == 'MULTIPART' && $mimePart->subType == 'RELATED' && is_array($mimePart->subParts)) {
				// in a multipart alternative we treat the multipart/related as html part
				#$partHTML = array($mimePart);
				if (self::$debug) error_log(__METHOD__." process MULTIPART/RELATED with array as subparts");
				$partHTML = $mimePart;
			} elseif ($mimePart->type == 'MULTIPART' && $mimePart->subType == 'ALTERNATIVE' && is_array($mimePart->subParts)) {
				//cascading multipartAlternative structure, assuming only the first one is to be used
				return $this->getMultipartAlternative($_uid,$mimePart->subParts,$_htmlMode, $_preserveSeen);
			}
		}
		//error_log(__METHOD__.__LINE__.$_htmlMode);
		switch($_htmlMode) {
			case 'html_only':
			case 'always_display':
				if(is_object($partHTML)) {
					if($partHTML->subType == 'RELATED') {
						return $this->getMultipartRelated($_uid, $partHTML, $_htmlMode, $_preserveSeen);
					} else {
						return $this->getTextPart($_uid, $partHTML, $_htmlMode, $_preserveSeen);
					}
				} elseif(is_object($partText) && $_htmlMode=='always_display') {
					return $this->getTextPart($_uid, $partText, $_htmlMode, $_preserveSeen);
				}

				break;
			case 'only_if_no_text':
				if(is_object($partText)) {
					return $this->getTextPart($_uid, $partText, $_htmlMode, $_preserveSeen);
				} elseif(is_object($partHTML)) {
					if($partHTML->type) {
						return $this->getMultipartRelated($_uid, $partHTML, $_htmlMode, $_preserveSeen);
					} else {
						return $this->getTextPart($_uid, $partHTML, 'always_display', $_preserveSeen);
					}
				}

				break;

			default:
				if(is_object($partText)) {
					return $this->getTextPart($_uid, $partText, $_htmlMode, $_preserveSeen);
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

	function getMultipartMixed($_uid, $_structure, $_htmlMode, $_preserveSeen = false)
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
							$bodyPart[] = $this->getMultipartAlternative($_uid, $part->subParts, $_htmlMode, $_preserveSeen);
							break;

						case 'MIXED':
						case 'SIGNED':
							$bodyPart = array_merge($bodyPart, $this->getMultipartMixed($_uid, $part->subParts, $_htmlMode, $_preserveSeen));
							break;

						case 'RELATED':
							$bodyPart = array_merge($bodyPart, $this->getMultipartRelated($_uid, $part->subParts, $_htmlMode, $_preserveSeen));
							break;
					}
					break;

				case 'TEXT':
					switch($part->subType) {
						case 'PLAIN':
						case 'HTML':
						case 'CALENDAR': // inline ics/ical files
							if($part->disposition != 'ATTACHMENT') {
								$bodyPart[] = $this->getTextPart($_uid, $part, $_htmlMode, $_preserveSeen);
							}
							//error_log(__METHOD__.__LINE__.' ->'.$part->type."/".$part->subType.' -> BodyPart:'.array2string($bodyPart[count($bodyPart)-1]));
							break;
					}
					break;

				case 'MESSAGE':
					if($part->subType == 'delivery-status') {
						$bodyPart[] = $this->getTextPart($_uid, $part, $_htmlMode, $_preserveSeen);
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

	function getMultipartRelated($_uid, $_structure, $_htmlMode, $_preserveSeen = false)
	{
		return $this->getMultipartMixed($_uid, $_structure, $_htmlMode, $_preserveSeen);
	}

	function getTextPart($_uid, $_structure, $_htmlMode = '', $_preserveSeen = false)
	{
		//error_log(__METHOD__.__LINE__.'->'.$_uid.':'.array2string($_structure).' '.function_backtrace());
		$bodyPart = array();
		if (self::$debug) _debug_array(array($_structure,function_backtrace()));
		$partID = $_structure->partID;
		$mimePartBody = $this->icServer->getBodyPart($_uid, $partID, true, $_preserveSeen);
		if (PEAR::isError($mimePartBody))
		{
			error_log(__METHOD__.__LINE__.' failed:'.$mimePartBody->message);
			return false;
		}
		//_debug_array($mimePartBody);
		//error_log(__METHOD__.__LINE__.' UID:'.$_uid.' PartID:'.$partID.' HTMLMode:'.$_htmlMode.' ->'.array2string($_structure).array2string($mimePartBody));
		if (empty($mimePartBody)) return array(
				'body'		=> '',
				'mimeType'  => ($_structure->type == 'TEXT' && $_structure->subType == 'HTML') ? 'text/html' : 'text/plain',
				'charSet'   => self::$displayCharset,
			);
		//_debug_array(preg_replace('/PropertyFile___$/','',$this->decodeMimePart($mimePartBody, $_structure->encoding)));
		if($_structure->subType == 'HTML' && $_htmlMode!= 'html_only' && $_htmlMode != 'always_display'  && $_htmlMode != 'only_if_no_text') {
			$bodyPart = array(
				'error'		=> 1,
				'body'		=> lang("displaying html messages is disabled"),
				'mimeType'	=> 'text/html',
				'charSet'	=> self::$displayCharset,
			);
		} elseif ($_structure->subType == 'PLAIN' && $_htmlMode == 'html_only') {
			$bodyPart = array(
				'error'		=> 1,
				'body'      => lang("displaying plain messages is disabled"),
				'mimeType'  => 'text/plain', // make sure we do not return mimeType text/html
				'charSet'   => self::$displayCharset,
			);
		} else {
			// some Servers append PropertyFile___ ; strip that here for display
			$bodyPart = array(
				'body'		=> preg_replace('/PropertyFile___$/','',$this->decodeMimePart($mimePartBody, $_structure->encoding, $this->getMimePartCharset($_structure))),
				'mimeType'	=> ($_structure->type == 'TEXT' && $_structure->subType == 'HTML') ? 'text/html' : 'text/plain',
				'charSet'	=> $this->getMimePartCharset($_structure),
			);
			if ($_structure->subType == 'CALENDAR')
			{
				// we get an inline CALENDAR ical/ics, we display it using the calendar notification style
				$calobj = new calendar_ical;
				$calboupdate = new calendar_boupdate;
				// timezone stuff
				$tz_diff = $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'] - $this->common_prefs['tz_offset'];
				// form an event out of ical
				$event = $calobj->icaltoegw($bodyPart['body']);
				$event= $event[0];
				// preset the olddate
				$olddate = $calboupdate->format_date($event['start']+$tz_diff);
				// search egw, if we can find it
				$eventid = $calobj->find_event(array('uid'=>$event['uid']));
				if ((int)$eventid[0]>0)
				{
					// we found an event, we use the first one
					$oldevent = $calobj->read($eventid);
					// we set the olddate, to comply with the possible merge params for the notification message
					if($oldevent != False && $oldevent[$eventid[0]]['start']!=$event[$eventid[0]]['start']) {
						$olddate = $calboupdate->format_date($oldevent[$eventid[0]]['start']+$tz_diff);
					}
					// we merge the changes and the original event
					$event = array_merge($oldevent[$eventid[0]],$event);
					// for some strange reason, the title of the old event is not replaced with the new title
					// if you klick on the ics and import it into egw, so we dont show the title here.
					// so if it is a mere reply, we dont use the new title (more detailed info/work needed here)
					if ($_structure->parameters['METHOD']=='REPLY') $event['title'] = $oldevent[$eventid[0]]['title'];
				}
				// we prepare the message
				$details = $calboupdate->_get_event_details($event,$action,$event_arr);
				$details['olddate']=$olddate;
				//_debug_array($_structure);
				list($subject,$info) = $calboupdate->get_update_message($event,($_structure->parameters['METHOD']=='REPLY'?false:true));
				$info = $GLOBALS['egw']->preferences->parse_notify($info,$details);
				// we set the bodyPart, we only show the event, we dont actually do anything, as we expect the user to
				// click on the attached ics to update his own eventstore
				$bodyPart['body'] = $subject;
				$bodyPart['body'] .= "\n".$info;
				$bodyPart['body'] .= "\n\n".lang('Event Details follow').":\n";
				foreach($event_arr as $key => $val)
				{
					if(strlen($details[$key])) {
						switch($key){
					 		case 'access':
							case 'priority':
							case 'link':
								break;
							default:
								$bodyPart['body'] .= sprintf("%-20s %s\n",$val['field'].':',$details[$key]);
								break;
					 	}
					}
				}
			}
		}
		//_debug_array($bodyPart);
		return $bodyPart;
	}

	function getHierarchyDelimiter()
	{
		static $HierarchyDelimiter;
		if (is_null($HierarchyDelimiter)) $HierarchyDelimiter =& egw_cache::getSession('felamimail','HierarchyDelimiter');
		if (isset($HierarchyDelimiter[$this->icServer->ImapServerId])&&!empty($HierarchyDelimiter[$this->icServer->ImapServerId]))
		{
			$this->icServer->mailboxDelimiter = $HierarchyDelimiter[$this->icServer->ImapServerId];
			return $HierarchyDelimiter[$this->icServer->ImapServerId];
		}
		$HierarchyDelimiter[$this->icServer->ImapServerId] = '/';
		if(($this->icServer instanceof defaultimap))
		{
			$HierarchyDelimiter[$this->icServer->ImapServerId] = $this->icServer->getHierarchyDelimiter();
			if (PEAR::isError($HierarchyDelimiter[$this->icServer->ImapServerId])) $HierarchyDelimiter[$this->icServer->ImapServerId] = '/';
		}
		$this->icServer->mailboxDelimiter = $HierarchyDelimiter[$this->icServer->ImapServerId];
		return $HierarchyDelimiter[$this->icServer->ImapServerId];
	}


	function getSpecialUseFolders()
	{
		static $specialUseFolders;
		if (is_null($specialUseFolders)) $specialUseFolders = egw_cache::getCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*24*5);
		if (isset($specialUseFolders[$this->icServer->ImapServerId]))
		{
			return $specialUseFolders[$this->icServer->ImapServerId];
		}
		if(($this->icServer instanceof defaultimap))
		{
			if(($this->hasCapability('SPECIAL-USE')))
			{
				$ret = $this->icServer->getSpecialUseFolders();
				if (PEAR::isError($ret))
				{
					$specialUseFolders[$this->icServer->ImapServerId]=array();
				}
				else
				{
					foreach ($ret as $k => $f)
					{
						if (isset($f['ATTRIBUTES']) && !empty($f['ATTRIBUTES']) &&
							!in_array('\\NonExistent',$f['ATTRIBUTES']))
						{
							foreach (self::$autoFolders as $i => $n) // array('Drafts', 'Templates', 'Sent', 'Trash', 'Junk', 'Outbox');
							{
								if (in_array('\\'.$n,$f['ATTRIBUTES'])) $specialUseFolders[$this->icServer->ImapServerId][$f['MAILBOX']] = $n;
							}
						}
					}
				}
				egw_cache::setCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$specialUseFolders, $expiration=60*60*24*5);
			}
		}
		return $specialUseFolders[$this->icServer->ImapServerId];
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
	* @param bool $resultByUid if set to true, the result is to be returned by uid, if the server does not reply
	* 			on a query for uids, the result may be returned by IDs only, this will be indicated by this param
	* @param bool $setSession if set to true the session will be populated with the result of the query
	* @return bool
	*/
	function getSortedList($_folderName, $_sort, &$_reverse, $_filter, &$resultByUid=true, $setSession=true)
	{
		//ToDo: FilterSpecific Cache
		if(PEAR::isError($folderStatus = $this->icServer->examineMailbox($_folderName))) {
			return false;
		}
		//error_log(__METHOD__.__LINE__.array2string($folderStatus));
		//error_log(__METHOD__.__LINE__.' Filter:'.array2string($_filter));
		$try2useCache = true;
		static $eMailListContainsDeletedMessages;
		if (is_null($eMailListContainsDeletedMessages)) $eMailListContainsDeletedMessages = egw_cache::getCache(egw_cache::INSTANCE,'email','eMailListContainsDeletedMessages'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
		// this indicates, that there is no Filter set, and the returned set/subset should not contain DELETED Messages, nor filtered for UNDELETED
		if ($setSession==true && ((strpos(array2string($_filter), 'UNDELETED') === false && strpos(array2string($_filter), 'DELETED') === false)))
		{
			//$starttime = microtime(true);
			//$deletedMessages = $this->getSortedList($_folderName, $_sort=0, $_reverse=1, $_filter=array('status'=>array('DELETED')),$byUid=true,false);
			$deletedMessages = $this->getSortedList($_folderName, 0, $three=1, array('status'=>array('DELETED')),$five=true,false);
			//error_log(__METHOD__.__LINE__.array2string($deletedMessages));
			$eMailListContainsDeletedMessages[$this->profileID][$_folderName] = count($deletedMessages);
			egw_cache::setCache(egw_cache::INSTANCE,'email','eMailListContainsDeletedMessages'.trim($GLOBALS['egw_info']['user']['account_id']),$eMailListContainsDeletedMessages, $expiration=60*60*1);
			//$endtime = microtime(true);
			//$r = ($endtime-$starttime);
			//error_log(__METHOD__.__LINE__.' Profile:'.$this->profileID.' Folder:'.$_folderName.' -> EXISTS/SessStat:'.array2string($folderStatus['EXISTS']).'/'.$this->sessionData['folderStatus'][$this->profileID][$_folderName]['messages'].' ListContDelMsg/SessDeleted:'.$eMailListContainsDeletedMessages[$this->profileID][$_folderName].'/'.$this->sessionData['folderStatus'][$this->profileID][$_folderName]['deleted']);
			//error_log(__METHOD__.__LINE__.' Took:'.$r.'(s) setting eMailListContainsDeletedMessages for Profile:'.$this->profileID.' Folder:'.$_folderName.' to '.$eMailListContainsDeletedMessages[$this->profileID][$_folderName]);
		}
		if (self::$debug)
		{
			error_log(__METHOD__.__LINE__.' Profile:'.$this->profileID.' Folder:'.$_folderName.' -> EXISTS/SessStat:'.array2string($folderStatus['EXISTS']).'/'.$this->sessionData['folderStatus'][$this->profileID][$_folderName]['messages'].' ListContDelMsg/SessDeleted:'.$eMailListContainsDeletedMessages[$this->profileID][$_folderName].'/'.$this->sessionData['folderStatus'][$this->profileID][$_folderName]['deleted']);
			error_log(__METHOD__.__LINE__.' CachedFolderStatus:'.array2string($this->sessionData['folderStatus'][$this->profileID][$_folderName]));
		}
		if($try2useCache && (is_array($this->sessionData['folderStatus'][$this->profileID][$_folderName]) &&
			$this->sessionData['folderStatus'][$this->profileID][$_folderName]['uidValidity']	=== $folderStatus['UIDVALIDITY'] &&
			$this->sessionData['folderStatus'][$this->profileID][$_folderName]['messages']	== $folderStatus['EXISTS'] &&
			$this->sessionData['folderStatus'][$this->profileID][$_folderName]['deleted']	== $eMailListContainsDeletedMessages[$this->profileID][$_folderName] &&
			$this->sessionData['folderStatus'][$this->profileID][$_folderName]['uidnext']	=== $folderStatus['UIDNEXT'] &&
			$this->sessionData['folderStatus'][$this->profileID][$_folderName]['filter']	=== $_filter &&
			$this->sessionData['folderStatus'][$this->profileID][$_folderName]['sort']	=== $_sort &&
			//$this->sessionData['folderStatus'][0][$_folderName]['reverse'] === $_reverse &&
			!empty($this->sessionData['folderStatus'][$this->profileID][$_folderName]['sortResult']))
		) {
			if (self::$debug) error_log(__METHOD__." USE CACHE for Profile:". $this->profileID." Folder:".$_folderName.'->'.($setSession?'setSession':'checkrun').' Filter:'.array2string($_filter).function_backtrace());
			$sortResult = $this->sessionData['folderStatus'][$this->profileID][$_folderName]['sortResult'];

		} else {
			$try2useCache = false;
			//error_log(__METHOD__." USE NO CACHE for Profile:". $this->profileID." Folder:".$_folderName.'->'.($setSession?'setSession':'checkrun'));
			if (self::$debug) error_log(__METHOD__." USE NO CACHE for Profile:". $this->profileID." Folder:".$_folderName." Filter:".array2string($_filter).function_backtrace());
			$filter = $this->createIMAPFilter($_folderName, $_filter);
			//_debug_array($filter);

			if($this->icServer->hasCapability('SORT')) {
				if (self::$debug) error_log(__METHOD__." Mailserver has SORT Capability, SortBy: $_sort Reverse: $_reverse");
				$sortOrder = $this->_getSortString($_sort, $_reverse);
				if ($_reverse && strpos($sortOrder,'REVERSE')!==false) $_reverse=false; // as we reversed the result already
				if (self::$debug) error_log(__METHOD__." Mailserver runs SORT: SortBy: $sortOrder Filter: $filter");
				if (!empty(self::$displayCharset)) {
					$sortResult = $this->icServer->sort($sortOrder, strtoupper( self::$displayCharset ), $filter, $resultByUid);
				}
				if (PEAR::isError($sortResult) || empty(self::$displayCharset)) {
					$sortResult = $this->icServer->sort($sortOrder, 'US-ASCII', $filter, $resultByUid);
					// if there is an PEAR Error, we assume that the server is not capable of sorting
					if (PEAR::isError($sortResult)) {
						$advFilter = 'CHARSET '. strtoupper(self::$displayCharset) .' '.$filter;
						if (PEAR::isError($sortResult))
						{
							$resultByUid = false;
							$sortResult = $this->icServer->search($filter, $resultByUid);
							if (PEAR::isError($sortResult))
							{
								$sortResult = $this->sessionData['folderStatus'][$this->profileID][$_folderName]['sortResult'];
							}
						}
					}
				}
				if (self::$debug) error_log(__METHOD__.print_r($sortResult,true));
			} else {
				if (self::$debug) error_log(__METHOD__." Mailserver has NO SORT Capability");
				$advFilter = 'CHARSET '. strtoupper(self::$displayCharset) .' '.$filter;
				$sortResult = $this->icServer->search($advFilter, $resultByUid);
				if (PEAR::isError($sortResult))
				{
					$sortResult = $this->icServer->search($filter, $resultByUid);
					if (PEAR::isError($sortResult))
					{
						// some servers are not replying on a search for uids, so try this one
						$resultByUid = false;
						$sortResult = $this->icServer->search('*', $resultByUid);
						if (PEAR::isError($sortResult))
						{
							error_log(__METHOD__.__LINE__.' PEAR_Error:'.array2string($sortResult->message));
							$sortResult = null;
						}
					}
				}
				if(is_array($sortResult)) {
						sort($sortResult, SORT_NUMERIC);
				}
				if (self::$debug) error_log(__METHOD__." using Filter:".print_r($filter,true)." ->".print_r($sortResult,true));
			}
			if ($setSession)
			{
				$this->sessionData['folderStatus'][$this->profileID][$_folderName]['uidValidity'] = $folderStatus['UIDVALIDITY'];
				$this->sessionData['folderStatus'][$this->profileID][$_folderName]['messages']	= $folderStatus['EXISTS'];
				$this->sessionData['folderStatus'][$this->profileID][$_folderName]['uidnext']	= $folderStatus['UIDNEXT'];
				$this->sessionData['folderStatus'][$this->profileID][$_folderName]['filter']	= $_filter;
				$this->sessionData['folderStatus'][$this->profileID][$_folderName]['sortResult'] = $sortResult;
				$this->sessionData['folderStatus'][$this->profileID][$_folderName]['sort']	= $_sort;
			}
		}
		if ($setSession)
		{
			// this indicates, that there should be no UNDELETED Messages in the returned set/subset
			if (((strpos(array2string($_filter), 'UNDELETED') === false && strpos(array2string($_filter), 'DELETED') === false)))
			{
				if ($try2useCache == false) $this->sessionData['folderStatus'][$this->profileID][$_folderName]['deleted'] = $eMailListContainsDeletedMessages[$this->profileID][$_folderName];
			}
			$this->sessionData['folderStatus'][$this->profileID][$_folderName]['reverse'] 	= $_reverse;
			$this->saveSessionData();
		}
		return $sortResult;
	}

	function getMessageEnvelope($_uid, $_partID = '',$decode=false)
	{
		if($_partID == '') {
			if( PEAR::isError($envelope = $this->icServer->getEnvelope('', $_uid, true)) ) {
				return false;
			}
			//if ($decode) _debug_array($envelope[0]);
			return ($decode ? self::decode_header($envelope[0]): $envelope[0]);
		} else {
			if( PEAR::isError($headers = $this->icServer->getParsedHeaders($_uid, true, $_partID, true)) ) {
				return false;
			}
			//_debug_array($headers);
			$newData = array(
				'DATE'		=> $headers['DATE'],
				'SUBJECT'	=> ($decode ? self::decode_header($headers['SUBJECT']):$headers['SUBJECT']),
				'MESSAGE_ID'	=> $headers['MESSAGE-ID']
			);
			//_debug_array($newData);
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
			//if ($decode) _debug_array($newData);

			return $newData;
		}
	}

	function getHeaders($_folderName, $_startMessage, $_numberOfMessages, $_sort, $_reverse, $_filter, $_thisUIDOnly=null, $_cacheResult=true)
	{
		//self::$debug=true;
		if (self::$debug) error_log(__METHOD__.__LINE__.function_backtrace());
		if (self::$debug) error_log(__METHOD__.__LINE__."$_folderName,$_startMessage, $_numberOfMessages, $_sort, $reverse, ".array2string($_filter).", $_thisUIDOnly");
		$reverse = (bool)$_reverse;
		// get the list of messages to fetch
		if (self::$debug) $starttime = microtime (true);
		$this->reopen($_folderName);
		if (self::$debug)
		{
			$endtime = microtime(true) - $starttime;
			error_log(__METHOD__. " time used for reopen: ".$endtime.' for Folder:'.$_folderName);
		}
		//$this->icServer->selectMailbox($_folderName);
		$rByUid = true; // try searching by uid. this var will be passed by reference to getSortedList, and may be set to false, if UID retrieval fails
		#print "<pre>";
		#$this->icServer->setDebug(true);
		if ($_thisUIDOnly === null)
		{
			if (($_startMessage || $_numberOfMessages) && !isset($_filter['range']))
			{
				// this will not work we must calculate the range we want to retieve as e.g.: 0:20 retirieves the first 20 mails and sorts them
				// if sort capability is applied to the range fetched, not sort first and fetch the range afterwards
				$start = $_startMessage-1;
				$end = $_startMessage-1+$_numberOfMessages;
				//$_filter['range'] ="$start:$end";
				//$_filter['range'] ="$_startMessage:*";
			}
			if (self::$debug) error_log(__METHOD__.__LINE__."$_folderName, $_sort, $reverse, ".array2string($_filter).", $rByUid");
			if (self::$debug) $starttime = microtime (true);
			$sortResult = $this->getSortedList($_folderName, $_sort, $reverse, $_filter, $rByUid, $_cacheResult);
			if (self::$debug)
			{
				$endtime = microtime(true) - $starttime;
				error_log(__METHOD__. " time used for getSortedList: ".$endtime.' for Folder:'.$_folderName.' Filter:'.array2string($_filter).' Ids:'.array2string($_thisUIDOnly));
			}
			if (self::$debug) error_log(__METHOD__.__LINE__.array2string($sortResult));
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
			//error_log("REVERSE: $reverse");
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
		if (self::$debug) $starttime = microtime(true);
		$headersNew = $this->icServer->getSummary($queryString, $rByUid);
		if (PEAR::isError($headersNew) && empty($queryString))
		{
			$headersNew = array();
			$sortResult = array();
		}
		if ($headersNew == null && empty($_thisUIDOnly)) // -> if we request uids, do not try to look for messages with ids
		{
			if (self::$debug) error_log(__METHOD__.__LINE__."Uid->$queryString, ByUID? $rByUid");
			// message retrieval via uid failed try one by one via message number
			$rByUid = false;
			foreach($sortResult as $k => $v)
			{
				if (self::$debug) error_log(__METHOD__.__LINE__.' Query:'.$v.':*');
				$rv = $this->icServer->getSummary($v.':*', $rByUid);
				$headersNew[] = $rv[0];
			}
		}
		if (self::$debug)
		{
			$endtime = microtime(true) - $starttime;
			error_log(__METHOD__. " time used for getSummary: ".$endtime.' for Folder:'.$_folderName.' Filter:'.array2string($_filter));
			error_log(__METHOD__.__LINE__.' Query:'.$queryString.' Result:'.array2string($headersNew));
		}

		$count = 0;

		foreach((array)$sortResult as $uid) {
			$sortOrder[$uid] = $count++;
		}

		$count = 0;
		if (is_array($headersNew)) {
			if (self::$debug) $starttime = microtime(true);
			foreach((array)$headersNew as $headerObject) {
				//if($count == 0) error_log(__METHOD__.array2string($headerObject));
				if (empty($headerObject['UID'])) continue;
				$uid = ($rByUid ? $headerObject['UID'] : $headerObject['MSG_NUM']);
				// make dates like "Mon, 23 Apr 2007 10:11:06 UT" working with strtotime
				if(substr($headerObject['DATE'],-2) === 'UT') {
					$headerObject['DATE'] .= 'C';
				}
				if(substr($headerObject['INTERNALDATE'],-2) === 'UT') {
					$headerObject['INTERNALDATE'] .= 'C';
				}
				//error_log(__METHOD__.__LINE__.' '.$headerObject['SUBJECT'].'->'.$headerObject['DATE']);
				//error_log(__METHOD__.__LINE__.' '.$this->decode_subject($headerObject['SUBJECT']).'->'.$headerObject['DATE']);
				$retValue['header'][$sortOrder[$uid]]['subject']	= $this->decode_subject($headerObject['SUBJECT']);
				$retValue['header'][$sortOrder[$uid]]['size'] 		= $headerObject['SIZE'];
				$retValue['header'][$sortOrder[$uid]]['date']		= self::_strtotime($headerObject['DATE'],'ts',true);
				$retValue['header'][$sortOrder[$uid]]['internaldate']= self::_strtotime($headerObject['INTERNALDATE'],'ts',true);
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
					if (is_array($headerObject['FLAGS'])) $headerFlags = array_map('strtolower',$headerObject['FLAGS']);
					if (!empty($headerFlags))
					{
						$retValue['header'][$sortOrder[$uid]]['label1']   = in_array('$label1', $headerFlags);
						$retValue['header'][$sortOrder[$uid]]['label2']   = in_array('$label2', $headerFlags);
						$retValue['header'][$sortOrder[$uid]]['label3']   = in_array('$label3', $headerFlags);
						$retValue['header'][$sortOrder[$uid]]['label4']   = in_array('$label4', $headerFlags);
						$retValue['header'][$sortOrder[$uid]]['label5']   = in_array('$label5', $headerFlags);
					}
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
					if (count($headerObject['TO'])>1)
					{
						$ki=0;
						foreach($headerObject['TO'] as $k => $add)
						{
							if ($k==0) continue;
							//error_log(__METHOD__.__LINE__."-> $k:".array2string($add));
							if($add['HOST_NAME'] != 'NIL')
							{
								$retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki]['address'] = self::decode_header($add['EMAIL']);
							}
							else
							{
								$retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki]['address'] = self::decode_header($add['MAILBOX_NAME']);
							}
							if($headerObject['TO'][$k]['PERSONAL_NAME'] != 'NIL')
							{
								$retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki]['name'] = self::decode_header($add['PERSONAL_NAME']);
							}
							//error_log(__METHOD__.__LINE__.array2string($retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki]));
							$ki++;
						}
					}
				}

				$count++;
			}
			if (self::$debug)
			{
				$endtime = microtime(true) - $starttime;
				error_log(__METHOD__. " time used for the rest: ".$endtime.' for Folder:'.$_folderName);
			}
			//self::$debug=false;
			// sort the messages to the requested displayorder
			if(is_array($retValue['header'])) {
				$countMessages = false;
				if (isset($_filter['range'])) $countMessages = $this->sessionData['folderStatus'][$this->profileID][$_folderName]['messages'];
				ksort($retValue['header']);
				$retValue['info']['total']	= $countMessages ? $countMessages : $total;
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
			if ($headersNew == null && empty($_thisUIDOnly)) error_log(__METHOD__." -> retrieval of Message Details to Query $queryString failed: ".print_r($headersNew,TRUE));
			$retValue = array();
			$retValue['info']['total']  = 0;
			$retValue['info']['first']  = 0;
			$retValue['info']['last']   = 0;
			return $retValue;
		}
	}

	function getNextMessage($_foldername, $_id)
	{
		if (empty($this->sessionData['folderStatus'])) $this->restoreSessionData();
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

	function getMessageAttachments($_uid, $_partID='', $_structure='', $fetchEmbeddedImages=true, $fetchTextCalendar=false, $resolveTNEF=true)
	{
		if (self::$debug) echo __METHOD__."$_uid, $_partID<br>";

		if(is_object($_structure)) {
			$structure = $_structure;
		} else {
			$structure = $this->_getStructure($_uid, true);

			if($_partID != '' && $_partID !=0) {
				$structure = $this->_getSubStructure($structure, $_partID);
			}
		}
		if (self::$debug) _debug_array($structure);
		$attachments = array();
		// this kind of messages contain only the attachment and no body
		if($structure->type == 'APPLICATION' || $structure->type == 'AUDIO' || $structure->type == 'VIDEO' || $structure->type == 'IMAGE' || ($structure->type == 'TEXT' && $structure->disposition == 'ATTACHMENT') )
		{
			$newAttachment = array();
			$newAttachment['name']		= $this->getFileNameFromStructure($structure,$_uid,$structure->partID);
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
			}
			elseif ( $resolveTNEF===false && $newAttachment['name'] == 'winmail.dat' )
			{
				$attachments[] = $newAttachment;
			} else {
				if ( ($fetchEmbeddedImages && isset($newAttachment['cid']) && strlen($newAttachment['cid'])>0) ||
					!isset($newAttachment['cid']) ||
					empty($newAttachment['cid'])) $attachments[] = $newAttachment;
			}
			//$attachments[] = $newAttachment;

			#return $attachments;
		}
		// outlook sometimes sends a TEXT/CALENDAR;REQUEST as plain ics, nothing more.
		if ($structure->type == 'TEXT' && $structure->subType == 'CALENDAR' &&
			isset($structure->parameters['METHOD'] ) && strtoupper($structure->parameters['METHOD']) == 'REQUEST')
		{
			$newAttachment = array();
			$newAttachment['name']      = 'event.ics';
			$newAttachment['size']      = $structure->bytes;
			$newAttachment['mimeType']  = $structure->type .'/'. $structure->subType;//.';'.$structure->parameters['METHOD'];
			$newAttachment['partID']    = $structure->partID;
			$newAttachment['encoding']  = $structure->encoding;
			$newAttachment['method']    = $structure->parameters['METHOD'];
			$newAttachment['charset']   = $structure->parameters['CHARSET'];
			$attachments[] = $newAttachment;
		}
		// this kind of message can have no attachments
		if(($structure->type == 'TEXT' && !($structure->disposition == 'INLINE' && $structure->dparameters['FILENAME'])) ||
		   ($structure->type == 'MULTIPART' && $structure->subType == 'ALTERNATIVE' && !is_array($structure->subParts)) ||
		   !is_array($structure->subParts))
		{
			if (count($attachments) == 0) return array();
		}

		#$attachments = array();

		foreach((array)$structure->subParts as $subPart) {
			// skip all non attachment parts
			if(($subPart->type == 'TEXT' && ($subPart->subType == 'PLAIN' || $subPart->subType == 'HTML') && ($subPart->disposition != 'ATTACHMENT' &&
				!($subPart->disposition == 'INLINE' && $subPart->dparameters['FILENAME']))) ||
				($subPart->type == 'MULTIPART' && $subPart->subType == 'ALTERNATIVE') ||
				($subPart->type == 'MULTIPART' && $subPart->subType == 'APPLEFILE') ||
				($subPart->type == 'MESSAGE' && $subPart->subType == 'delivery-status'))
			{
				if ($subPart->type == 'MULTIPART' && $subPart->subType == 'ALTERNATIVE')
				{
					$attachments = array_merge($this->getMessageAttachments($_uid, '', $subPart, $fetchEmbeddedImages, $fetchTextCalendar, $resolveTNEF), $attachments);
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
			   	$attachments = array_merge($this->getMessageAttachments($_uid, '', $subPart, $fetchEmbeddedImages,$fetchTextCalendar, $resolveTNEF), $attachments);
			} else {
				if (!$fetchTextCalendar && $subPart->type == 'TEXT' &&
					$subPart->subType == 'CALENDAR' &&
					$subPart->parameters['METHOD'] &&
					$subPart->disposition !='ATTACHMENT') continue;
				$newAttachment = array();
				$newAttachment['name']		= $this->getFileNameFromStructure($subPart,$_uid,$subPart->partID);
				$newAttachment['size']		= $subPart->bytes;
				$newAttachment['mimeType']	= $subPart->type .'/'. $subPart->subType;
				$newAttachment['partID']	= $subPart->partID;
				$newAttachment['encoding']	= $subPart->encoding;
				$newAttachment['method']    = $subPart->parameters['METHOD'];
				$newAttachment['charset']   = $subPart->parameters['CHARSET'];
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
				}
				elseif ( $resolveTNEF===false && $newAttachment['name'] == 'winmail.dat' )
				{
					$attachments[] = $newAttachment;
				} else {
					if ( ($fetchEmbeddedImages && isset($newAttachment['cid']) && strlen($newAttachment['cid'])>0) ||
						!isset($newAttachment['cid']) ||
						empty($newAttachment['cid']) || $newAttachment['cid'] == 'NIL') $attachments[] = $newAttachment;
				}
				//$attachments[] = $newAttachment;
			}
		}

	   	//_debug_array($attachments); exit;
		return $attachments;

	}

	function getFileNameFromStructure(&$structure, $_uid = false, $partID = false)
	{
		static $namecounter;
		if (is_null($namecounter)) $namecounter = 0;

		//if ( $_uid && $partID) error_log(__METHOD__.__LINE__.array2string($structure).' Uid:'.$_uid.' PartID:'.$partID.' -> '.array2string($this->icServer->getParsedHeaders($_uid, true, $partID, true)));
		if(isset($structure->parameters['NAME'])) {
			if (is_array($structure->parameters['NAME'])) $structure->parameters['NAME'] = implode(' ',$structure->parameters['NAME']);
			return rawurldecode(self::decode_header($structure->parameters['NAME']));
		} elseif(isset($structure->dparameters['FILENAME'])) {
			return rawurldecode(self::decode_header($structure->dparameters['FILENAME']));
		} elseif(isset($structure->dparameters['FILENAME*'])) {
			return rawurldecode(self::decode_header($structure->dparameters['FILENAME*']));
		} elseif ( isset($structure->filename) && !empty($structure->filename) && $structure->filename != 'NIL') {
			return rawurldecode(self::decode_header($structure->filename));
		} else {
			if ( $_uid && $partID)
			{
				$headers = $this->icServer->getParsedHeaders($_uid, true, $partID, true);
				if ($headers)
				{
					if (!PEAR::isError($headers))
					{
						// simple parsing of the headers array for a usable name
						//error_log( __METHOD__.__LINE__.array2string($headers));
						foreach(array('CONTENT-TYPE','CONTENT-DISPOSITION') as $k => $v)
						{
							$headers[$v] = rawurldecode(self::decode_header($headers[$v]));
							foreach(array('filename','name') as $sk => $n)
							{
								if (stripos($headers[$v],$n)!== false)
								{
									$buff = explode($n,$headers[$v]);
									//error_log(__METHOD__.__LINE__.array2string($buff));
									$namepart = array_pop($buff);
									//error_log(__METHOD__.__LINE__.$namepart);
									$fp = strpos($namepart,'"');
									//error_log(__METHOD__.__LINE__.' Start:'.$fp);
									if ($fp !== false)
									{
										$np = strpos($namepart,'"', $fp+1);
										//error_log(__METHOD__.__LINE__.' End:'.$np);
										if ($np !== false)
										{
											$name = trim(substr($namepart,$fp+1,$np-$fp-1));
											if (!empty($name)) return $name;
										}
									}
								}
							}
						}
					}
				}
			}
			$namecounter++;
			return lang("unknown").$namecounter.($structure->subType ? ".".$structure->subType : "");
		}
	}

	function getMessageBody($_uid, $_htmlOptions='', $_partID='', $_structure = '', $_preserveSeen = false, $_folder = '')
	{
		if (self::$debug) echo __METHOD__."$_uid, $_htmlOptions, $_partID<br>";
		if($_htmlOptions != '') {
			$this->htmlOptions = $_htmlOptions;
		}
		if(is_object($_structure)) {
			$structure = $_structure;
		} else {
			$this->icServer->_cmd_counter = rand($this->icServer->_cmd_counter+1,$this->icServer->_cmd_counter+100);
			$structure = $this->_getStructure($_uid, true, false, $_folder);
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
						$bodyParts = array($this->getMultipartAlternative($_uid, $structure->subParts, $this->htmlOptions, $_preserveSeen));

						break;

					case 'MIXED':
					case 'REPORT':
					case 'SIGNED':
						$bodyParts = $this->getMultipartMixed($_uid, $structure->subParts, $this->htmlOptions, $_preserveSeen);
						break;

					case 'RELATED':
						$bodyParts = $this->getMultipartRelated($_uid, $structure->subParts, $this->htmlOptions, $_preserveSeen);
						break;
				}
				return self::normalizeBodyParts($bodyParts);
				break;
			case 'VIDEO':
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
							// this is handeled in getTextPart
						case 'HTML':
						case 'PLAIN':
						default:
							$bodyPart = array($this->getTextPart($_uid, $structure, $this->htmlOptions, $_preserveSeen));
					}
				} else {
					// what if the structure->disposition is attachment ,...
				}
				return self::normalizeBodyParts($bodyPart);
				break;
			case 'ATTACHMENT':
			case 'MESSAGE':
				switch($structure->subType) {
					case 'RFC822':
						$newStructure = array_shift($structure->subParts);
						if (self::$debug) {echo __METHOD__." Message -> RFC -> NewStructure:"; _debug_array($newStructure);}
						return self::normalizeBodyParts($this->getMessageBody($_uid, $_htmlOptions, $newStructure->partID, $newStructure));
						break;
				}
				break;
			default:
				if (self::$debug) _debug_array($structure);
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
	 * @return array - a normalized Bodyarray
	 */
	static function normalizeBodyParts($_bodyParts)
	{
		if (is_array($_bodyParts))
		{
			foreach($_bodyParts as $singleBodyPart)
			{
				if (!isset($singleBodyPart['body'])) {
					$buff = self::normalizeBodyParts($singleBodyPart);
					foreach ((array)$buff as $val)	$body2return[] = $val;
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
		if (PEAR::isError($retValue))
		{
			error_log(__METHOD__.__LINE__.array2string($retValue->message));
			$retValue = null;
		}
		return ($decode ? self::decode_header($retValue):$retValue);
	}

	function getMessageRawBody($_uid, $_partID = '')
	{
		//TODO: caching einbauen static!
		static $rawBody;
		$_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		if (isset($rawBody[$_folder][$_uid][($_partID==''?'NIL':$_partID)]))
		{
			error_log(__METHOD__.__LINE__." Using Cache for raw Body $_uid, $_partID in Folder $_folder");
			return $rawBody[$this->icServer->ImapServerId][$_folder][$_uid][($_partID==''?'NIL':$_partID)];
		}
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
		$rawBody[$this->icServer->ImapServerId][$_folder][$_uid][($_partID==''?'NIL':$_partID)] = $body;
		return $body;
	}

	function getMessageRawHeader($_uid, $_partID = '')
	{
		static $rawHeaders;
		$_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		//error_log(__METHOD__.__LINE__." Try Using Cache for raw Header $_uid, $_partID in Folder $_folder");

		if (is_null($rawHeaders)) $rawHeaders = egw_cache::getCache(egw_cache::INSTANCE,'email','rawHeadersCache'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
		if (isset($rawHeaders[$this->icServer->ImapServerId][$_folder][$_uid][($_partID==''?'NIL':$_partID)]))
		{
			//error_log(__METHOD__.__LINE__." Using Cache for raw Header $_uid, $_partID in Folder $_folder");
			return $rawHeaders[$this->icServer->ImapServerId][$_folder][$_uid][($_partID==''?'NIL':$_partID)];
		}

		$retValue = $this->icServer->getRawHeaders($_uid, $_partID, true);
		if (PEAR::isError($retValue))
		{
			error_log(__METHOD__.__LINE__.array2string($retValue->message));
			$retValue = "Could not retrieve RawHeaders in ".__METHOD__.__LINE__." PEAR::Error:".array2string($retValue->message);
		}
		$rawHeaders[$this->icServer->ImapServerId][$_folder][$_uid][($_partID==''?'NIL':$_partID)]=$retValue;
		egw_cache::setCache(egw_cache::INSTANCE,'email','rawHeadersCache'.trim($GLOBALS['egw_info']['user']['account_id']),$rawHeaders,$expiration=60*60*1);
		return $retValue;
	}

	// return the qouta of the users INBOX
	function getQuotaRoot()
	{
		static $quota;
		if (isset($quota)) return $quota;
		if(!$this->icServer->hasCapability('QUOTA')) {
			$quota = false;
			return false;
		}
		$quota = $this->icServer->getStorageQuotaRoot('INBOX');
		//error_log(__METHOD__.__LINE__.array2string($quota));
		if(is_array($quota)) {
			$quota = array(
				'usage'	=> $quota['USED'],
				'limit'	=> $quota['QMAX'],
			);
		} else {
			$quota = false;
		}
		return $quota;
	}

	function getDraftFolder($_checkexistance=TRUE)
	{
		if(empty($this->mailPreferences->preferences['draftFolder'])) {
			return false;
		}
		$_folderName = $this->mailPreferences->preferences['draftFolder'];
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}
		return $_folderName;
	}

	function getTemplateFolder($_checkexistance=TRUE)
	{
		if(empty($this->mailPreferences->preferences['templateFolder'])) {
			return false;
		}
		$_folderName = $this->mailPreferences->preferences['templateFolder'];
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}
		return $_folderName;
	}

	function getTrashFolder($_checkexistance=TRUE)
	{
		if(empty($this->mailPreferences->preferences['trashFolder'])) {
			return false;
		}
		$_folderName = $this->mailPreferences->preferences['trashFolder'];
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}
		return $_folderName;
	}

	function getSentFolder($_checkexistance=TRUE)
	{
		if(empty($this->mailPreferences->preferences['sentFolder'])) {
			return false;
		}
		$_folderName = $this->mailPreferences->preferences['sentFolder'];
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}
		return $_folderName;
	}

	function isSentFolder($_folderName, $_checkexistance=TRUE)
	{
		if(empty($this->mailPreferences->preferences['sentFolder'])) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}

		if(false !== stripos($_folderName, $this->mailPreferences->preferences['sentFolder'])) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * checks if the Outbox folder exists and is port of the foldername to be checked
	 */
	function isOutbox($_folderName, $_checkexistance=TRUE)
	{
		if (stripos($_folderName, 'Outbox')===false) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}
		return true;
	}

	function isDraftFolder($_folderName, $_checkexistance=TRUE)
	{
		if(empty($this->mailPreferences->preferences['draftFolder'])) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}

		if(false !== stripos($_folderName, $this->mailPreferences->preferences['draftFolder'])) {
			return true;
		} else {
			return false;
		}
	}

	function isTrashFolder($_folderName, $_checkexistance=TRUE)
	{
		if(empty($this->mailPreferences->preferences['trashFolder'])) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}

		if(false !== stripos($_folderName, $this->mailPreferences->preferences['trashFolder'])) {
			return true;
		} else {
			return false;
		}
	}

	function isTemplateFolder($_folderName, $_checkexistance=TRUE)
	{
		if(empty($this->mailPreferences->preferences['templateFolder'])) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !self::folderExists($_folderName)) {
			return false;
		}

		if(false !== stripos($_folderName, $this->mailPreferences->preferences['templateFolder'])) {
			return true;
		} else {
			return false;
		}
	}

	function folderExists($_folder, $_forceCheck=false)
	{
		static $folderInfo;
		$forceCheck = $_forceCheck;
		if (empty($_folder))
		{
			error_log(__METHOD__.__LINE__.' Called with empty Folder:'.$_folder.function_backtrace());
			return false;
		}
		// reduce traffic within the Instance per User; Expire every 5 Minutes
		//error_log(__METHOD__.__LINE__.' Called with Folder:'.$_folder.function_backtrace());
		if (is_null($folderInfo)) $folderInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*5);
		//error_log(__METHOD__.__LINE__.'Cached Info on Folder:'.$_folder.' for Profile:'.$this->profileID.($forceCheck?'(forcedCheck)':'').':'.array2string($folderInfo));
		if (!empty($folderInfo) && isset($folderInfo[$this->profileID]) && isset($folderInfo[$this->profileID][$_folder]) && $forceCheck===false)
		{
			//error_log(__METHOD__.__LINE__.' Using cached Info on Folder:'.$_folder.' for Profile:'.$this->profileID);
			return $folderInfo[$this->profileID][$_folder];
		}
		else
		{
			if ($forceCheck === false)
			{
				//error_log(__METHOD__.__LINE__.' No cached Info on Folder:'.$_folder.' for Profile:'.$this->profileID.' FolderExistsInfoCache:'.array2string($folderInfo[$this->profileID]));
				$forceCheck = true; // try to force the check, in case there is no connection, we may need that
			}
		}

		// does the folder exist???
		//error_log(__METHOD__."->Connected?".$this->icServer->_connected.", ".$_folder.", ".($forceCheck?' forceCheck activated':'dont check on server'));
		if ((!($this->icServer->_connected == 1)) && $forceCheck || empty($folderInfo) || !isset($folderInfo[$this->profileID]) || !isset($folderInfo[$this->profileID][$_folder])) {
			//error_log(__METHOD__."->NotConnected and forceCheck with profile:".$this->profileID);
			//return false;
			//try to connect
			if (!$this->icServer->_connected) $this->openConnection($this->profileID,false);
		}
		if(($this->icServer instanceof defaultimap)) $folderInfo[$this->profileID][$_folder] = $this->icServer->mailboxExist($_folder);
		//error_log(__METHOD__.__LINE__.' Folder Exists:'.$folderInfo[$this->profileID][$_folder].function_backtrace());

		if(!empty($folderInfo) && isset($folderInfo[$this->profileID][$_folder]) &&
			($folderInfo[$this->profileID][$_folder] instanceof PEAR_Error) || $folderInfo[$this->profileID][$_folder] !== true)
		{
			$folderInfo[$this->profileID][$_folder] = false; // set to false, whatever it was (to have a valid returnvalue for the static return)
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderInfo,$expiration=60*5);
		return (!empty($folderInfo) && isset($folderInfo[$this->profileID][$_folder]) ? $folderInfo[$this->profileID][$_folder] : false);
	}

	/**
	 * getFolderType - checks and returns the foldertype for a given nfolder
	 * @param string $mailbox
	 * @return int the folder Type 0 for Standard, 1 for Sent, 2 for draft, 3 for template
	 */
	function getFolderType($mailbox)
	{
		$sentFolderFlag =$this->isSentFolder($mailbox);
		$folderType = 0;
		if($sentFolderFlag ||
			false !== in_array($mailbox,explode(',',$GLOBALS['egw_info']['user']['preferences']['felamimail']['messages_showassent_0'])))
		{
			$folderType = 1;
			$sentFolderFlag=1;
		} elseif($this->isDraftFolder($mailbox)) {
			$folderType = 2;
		} elseif($this->isTemplateFolder($mailbox)) {
			$folderType = 3;
		}
		return $folderType;
	}

	function moveMessages($_foldername, $_messageUID, $deleteAfterMove=true, $currentFolder = Null, $returnUIDs = false)
	{
		$msglist = '';

		$deleteOptions  = $GLOBALS['egw_info']["user"]["preferences"]["felamimail"]["deleteOptions"];
		$retUid = $this->icServer->copyMessages($_foldername, $_messageUID, (!empty($currentFolder)?$currentFolder: $this->sessionData['mailbox']), true, $returnUIDs);
		if ( PEAR::isError($retUid) ) {
			error_log(__METHOD__.__LINE__."Copying to Folder $_foldername failed! PEAR::Error:".array2string($retUid->message));
			throw new egw_exception("Copying to Folder $_foldername failed! PEAR::Error:".array2string($retUid->message));
			return false;
		}
		if ($deleteAfterMove === true)
		{
			$retValue = $this->icServer->deleteMessages($_messageUID, true);
			if ( PEAR::isError($retValue))
			{
				error_log(__METHOD__.__LINE__."Delete After Move PEAR::Error:".array2string($retValue->message));
				throw new egw_exception("Delete After Move PEAR::Error:".array2string($retValue->message));
				return false;
			}

			if($deleteOptions != "mark_as_deleted")
			{
				$structure = egw_cache::getCache(egw_cache::INSTANCE,'email','structureCache'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
				$cachemodified = false;
				foreach ((array)$_messageUID as $k => $_uid)
				{
					if (isset($structure[$this->icServer->ImapServerId][(!empty($currentFolder)?$currentFolder: $this->sessionData['mailbox'])][$_uid]))
					{
						$cachemodified = true;
						unset($structure[$this->icServer->ImapServerId][(!empty($currentFolder)?$currentFolder: $this->sessionData['mailbox'])][$_uid]);
					}
				}
				if ($cachemodified) egw_cache::setCache(egw_cache::INSTANCE,'email','structureCache'.trim($GLOBALS['egw_info']['user']['account_id']),$structure,$expiration=60*60*1);

				// delete the messages finaly
				$this->icServer->expunge();
			}
		}
		//error_log(__METHOD__.__LINE__.array2string($retUid));
		return ($returnUIDs ? $retUid : true);
	}

	function openConnection($_icServerID=0, $_adminConnection=false)
	{
		static $isError;
		//error_log(__METHOD__.__LINE__.'->'.$_icServerID.' called from '.function_backtrace());
		if (is_null($isError)) $isError = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*5);
		if ( isset($isError[$_icServerID]) || (($this->icServer instanceof defaultimap) && PEAR::isError($this->icServer->_connectionErrorObject)))
		{
			if (trim($isError[$_icServerID])==',' || trim($this->icServer->_connectionErrorObject->message) == ',')
			{
				//error_log(__METHOD__.__LINE__.' Connection seemed to have failed in the past, no real reason given, try to recover on our own.');
				emailadmin_bo::unsetCachedObjects($_icServerID);
			}
			else
			{
				//error_log(__METHOD__.__LINE__.' failed for Reason:'.$isError[$_icServerID]);
				$this->errorMessage = ($isError[$_icServerID]?$isError[$_icServerID]:$this->icServer->_connectionErrorObject->message);
				return false;
			}
		}
		if (!is_object($this->mailPreferences))
		{
			if (self::$debug) error_log(__METHOD__." No Object for MailPreferences found.". function_backtrace());
			$this->errorMessage .= lang('No valid data to create MailProfile!!');
			$isError[$_icServerID] = (($this->icServer instanceof defaultimap)?new PEAR_Error($this->errorMessage):$this->errorMessage);
			egw_cache::setCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$isError,$expiration=60*15);
			return false;
		}
		if(!$this->icServer = $this->mailPreferences->getIncomingServer((int)$_icServerID)) {
			$this->errorMessage .= lang('No active IMAP server found!!');
			$isError[$_icServerID] = $this->errorMessage;
			egw_cache::setCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$isError,$expiration=60*15);
			return false;
		}
		//error_log(__METHOD__.__LINE__.'->'.array2string($this->icServer->ImapServerId));
		if ($this->icServer && empty($this->icServer->host)) {
			$errormessage = lang('No IMAP server host configured!!');
			if ($GLOBALS['egw_info']['user']['apps']['emailadmin']) {
				$errormessage .= "<br>".lang("Configure a valid IMAP Server in emailadmin for the profile you are using.");
			} else {
				$errormessage .= "<br>".lang('Please ask the administrator to correct the emailadmin IMAP Server Settings for you.');
			}
			$this->icServer->_connectionErrorObject->message .= $this->errorMessage .= $errormessage;
			$isError[$_icServerID] = $this->errorMessage;
			egw_cache::setCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$isError,$expiration=60*15);
			return false;
		}
		//error_log( "-------------------------->open connection ".function_backtrace());
		//error_log(__METHOD__.__LINE__.' ->'.array2string($this->icServer));
		if ($this->icServer->_connected == 1) {
			$tretval = $this->icServer->selectMailbox($this->icServer->currentMailbox);
			if ( PEAR::isError($tretval) ) $isError[$_icServerID] = $tretval->message;
			//error_log(__METHOD__." using existing Connection ProfileID:".$_icServerID.' Status:'.print_r($this->icServer->_connected,true));
		} else {
			//error_log( "-------------------------->open connection for Server with profileID:".$_icServerID.function_backtrace());
			$timeout = felamimail_bo::getTimeOut();
			$tretval = $this->icServer->openConnection($_adminConnection,$timeout);
			if ( PEAR::isError($tretval) || $tretval===false)
			{
				$isError[$_icServerID] = ($tretval?$tretval->message:$this->icServer->_connectionErrorObject->message);
				if (self::$debug)
				{
					error_log(__METHOD__.__LINE__." # failed to open new Connection ProfileID:".$_icServerID.' Status:'.print_r($this->icServer->_connected,true).' Message:'.$isError[$_icServerID].' called from '.function_backtrace());
					error_log(__METHOD__.__LINE__.' # Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid']);
				}
			}
		}
		if ( PEAR::isError($tretval) ) egw_cache::setCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$isError,$expiration=60*15);
		//error_log(print_r($this->icServer->_connected,true));
		$hD = $this->getHierarchyDelimiter();
		$sUF = $this->getSpecialUseFolders();
		//error_log(__METHOD__.__LINE__.array2string($sUF));

		return $tretval;
	}

	/**
	 * getTimeOut
	 *
	 * @param string _use decide if the use is for IMAP or SIEVE, by now only the default differs
	 *
	 * @return int - timeout (either set or default 20/10)
	 */
	static function getTimeOut($_use='IMAP')
	{
		$timeout = $GLOBALS['egw_info']['user']['preferences']['felamimail']['connectionTimeout'];
		if (empty($timeout)) $timeout = ($_use=='SIEVE'?10:20); // this is the default value
		return $timeout;
	}

	/**
	 * rename a folder
	 *
	 * @param string _oldFolderName the old foldername
	 * @param string _parent the parent foldername
	 * @param string _folderName the new foldername
	 *
	 * @return mixed name of the newly created folder or false on error
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
		// TODO: trying to reduce traffic to the IMAP Server here, introduces problems with fetching the bodies of
		// eMails when not in "current-Folder" (folder that is selected by UI)
		//static $folderOpened;
		//if (empty($folderOpened) || $folderOpened!=$_foldername)
		//{
			//error_log( "------------------------reopen-<br>");
			//error_log(__METHOD__.__LINE__.' Connected with icServer for Profile:'.$this->profileID.'?'.print_r($this->icServer->_connected,true));
			if ($this->icServer->_connected == 1) {
				$tretval = $this->icServer->selectMailbox($_foldername);
			} else {
				$tretval = $this->icServer->openConnection(false);
				$tretval = $this->icServer->selectMailbox($_foldername);
			}
			$folderOpened = $_foldername;
		//}
	}

	function restoreSessionData()
	{
		$GLOBALS['egw_info']['flags']['autoload'] = array(__CLASS__,'autoload');

		$this->sessionData = $GLOBALS['egw']->session->appsession('session_data','felamimail');
		$this->sessionData['folderStatus'] = egw_cache::getCache(egw_cache::INSTANCE,'email','folderStatus'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
	}

	function saveSessionData()
	{
		if (isset($this->sessionData['folderStatus']) && is_array($this->sessionData['folderStatus']))
		{
			egw_cache::setCache(egw_cache::INSTANCE,'email','folderStatus'.trim($GLOBALS['egw_info']['user']['account_id']),$this->sessionData['folderStatus'], $expiration=60*60*1);
			unset($this->sessionData['folderStatus']);
		}
		$GLOBALS['egw']->session->appsession('session_data','felamimail',$this->sessionData);
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

	function setEMailProfile($_profileID)
	{
		$config = CreateObject('phpgwapi.config','felamimail');
		$config->read_repository();
		$config->value('profileID',$_profileID);
		$config->save_repository();
	}

	function subscribe($_folderName, $_status)
	{
		if (self::$debug) error_log("felamimail_bo::".($_status?"":"un")."subscribe:".$_folderName);
		if($_status === true) {
			$rv = $this->icServer->subscribeMailbox($_folderName);
			if ( PEAR::isError($rv)) {
				error_log("felamimail_bo::".($_status?"":"un")."subscribe:".$_folderName." failed:".$rv->message);
				return false;
			}
		} else {
			$rv = $this->icServer->unsubscribeMailbox($_folderName);
			if ( PEAR::isError($rv)) {
				error_log("felamimail_bo::".($_status?"":"un")."subscribe:".$_folderName." failed:".$rv->message);
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
		if(($icServer instanceof defaultimap)) {
			$icServer->updateAccount($_hookValues);
		}

		if (is_object($this->mailPreferences)) $ogServer = $this->mailPreferences->getOutgoingServer(0);
		if(($ogServer instanceof defaultsmtp)) {
			$ogServer->updateAccount($_hookValues);
		}
	}

	function updateSingleACL($_folderName, $_accountName, $_aclType, $_aclStatus,$_recursive=false)
	{
		//error_log(__METHOD__.__LINE__." $_folderName, $_accountName, $_aclType, $_aclStatus,$_recursive");
		$userACL = $this->getIMAPACL($_folderName, $_accountName);

		if($_aclStatus == 'true' || $_aclStatus == 1) {
			if(strpos($userACL, $_aclType) === false) {
				$userACL .= $_aclType;
				$this->setACL($_folderName, $_accountName, $userACL, $_recursive);
			}
		} elseif($_aclStatus == 'false' || empty($_aclStatus)) {
			if(strpos($userACL, $_aclType) !== false) {
				$userACL = str_replace($_aclType,'',$userACL);
				$this->setACL($_folderName, $_accountName, $userACL, $_recursive);
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
			   )
			{
				$s=explode(" ", $line);
				$line = "";
				$linecnt = 0;
				foreach ($s as $k=>$v) {
					$cnt = strlen($v);
					// only break long words within the wordboundaries,
					// but it may destroy links, so we check for href and dont do it if we find one
					// we check for any html within the word, because we do not want to break html by accident
					if($cnt > $allowedLength && stripos($v,'href=')===false && stripos($v,'onclick=')===false && $cnt == strlen(html_entity_decode($v)))
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
	* @return ISO-8859-1 / UTF7-IMAP encoded string
	*/
	function _encodeFolderName($_folderName) {
		return translation::convert($_folderName, self::$displayCharset, 'ISO-8859-1');
		#return translation::convert($_folderName, self::$displayCharset, 'UTF7-IMAP');
	}

	/**
	* convert the foldername from UTF-7 to display charset
	*
	* @param string _parent the parent foldername
	* @return ISO-8859-1 / self::$displayCharset encoded string
	*/
	function _decodeFolderName($_folderName) {
		return translation::convert($_folderName, self::$displayCharset, 'ISO-8859-1');
		#return translation::convert($_folderName, 'UTF7-IMAP', self::$displayCharset);
	}

	/**
	* convert the sort value from the gui(integer) into a string
	*
	* @param int _sort the integer sort order
	* @return the ascii sort string
	*/
	function _getSortString($_sort, $_reverse=false)
	{
		$_reverse=false;
		switch($_sort) {
			case 2:
				$retValue = 'FROM';
				break;
			case 4:
				$retValue = 'TO';
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
				//$retValue = 'ARRIVAL';
				break;
		}

		return ($_reverse?'REVERSE ':'').$retValue;
	}

	function sendMDN($uid)
	{
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
				error_log(__METHOD__.__LINE__.' using identity for send from:'.$send->From.' to match header information:'.$headers['TO'].' ProfileID:'.$this->profileID.' Folder:'.$this->sessionData['mailbox']);
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
		//error_log(__METHOD__.array2string($send));
		$rv = $send->SmtpSend($header,$body);
		//error_log(__METHOD__.'#'.array2string($rv).'#');
		return $rv;
	}

	/**
	 * Merges a given content with contact data
	 *
	 * @param string $content
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @return string/boolean merged content or false on error
	 */
	function merge($content,$ids,$mimetype='')
	{
		$contacts = new addressbook_bo();
		$mergeobj = new addressbook_merge();

		if (empty($mimetype)) $mimetype = (strlen(strip_tags($content)) == strlen($content) ?'text/plain':'text/html');
		$rv = $mergeobj->merge_string($content,$ids,$err,$mimetype);
		if (empty($rv) && !empty($content) && !empty($err)) $rv = $content;
		if (!empty($err) && !empty($content) && !empty($ids)) error_log(__METHOD__.__LINE__.' Merge failed for Ids:'.array2string($ids).' ContentType:'.$mimetype.' Content:'.$content.' Reason:'.array2string($err));
		return $rv;
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
		if ($_charset===false) $_charset = self::$displayCharset;
		$_stringORG = $_string;
		$_string = @htmlspecialchars($_string,ENT_QUOTES,$_charset, false);
		if (empty($_string) && !empty($_stringORG)) $_string = @htmlspecialchars(translation::convert($_stringORG,self::detect_encoding($_stringORG),$_charset),ENT_QUOTES | ENT_IGNORE,$_charset, false);
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
		if ($_charset===false) $_charset = self::$displayCharset;
		$_stringORG = $_string;
		$_string = @htmlentities($_string,ENT_QUOTES,$_charset, false);
		if (empty($_string) && !empty($_stringORG)) $_string = @htmlentities(translation::convert($_stringORG,self::detect_encoding($_stringORG),$_charset),ENT_QUOTES | ENT_IGNORE,$_charset, false);
		return $_string;
	}

	/**
	 * detect_encoding - try to detect the encoding
	 *    only to be used if the string in question has no structure that determines his encoding
	 * @param string - to be evaluated
	 * @return mixed string/boolean (encoding or false
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
			// check the mimetype by extension. as browsers seem to report crap
			// maybe its application/octet-stream -> this may mean that we could not determine the type
			// so we check for the suffix too
			// trust vfs mime-types, trust the mimetype if it contains a method
			if ((substr($_formData['file'],0,6) !== 'vfs://' || $_formData['type'] == 'application/octet-stream') && stripos($_formData['type'],'method=')===false)
			{
				$buff = explode('.',$_formData['name']);
				$suffix = '';
				if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
				if (!empty($suffix)) $sfxMimeType = mime_magic::ext2mime($suffix);
				if (!empty($suffix) && !empty($sfxMimeType) &&
					(strlen(trim($_formData['type']))==0 || (strtolower(trim($_formData['type'])) != $sfxMimeType)))
				{
					error_log(__METHOD__.__LINE__.' Data:'.array2string($_formData));
					error_log(__METHOD__.__LINE__.' Form reported Mimetype:'.$_formData['type'].' but seems to be:'.$sfxMimeType);
					$_formData['type'] = $sfxMimeType;
				}
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
					if (!(strtolower(trim($_formData['type'])) == "application/octet-stream" && $sfxMimeType == $reqMimeType))
					{
						//error_log("Message rejected, no message/rfc. Is:".$_formData['type']);
						$importfailed = true;
						$alert_msg .= lang("File rejected, no %2. Is:%1",$_formData['type'],$reqMimeType);
					}
					if ((strtolower(trim($_formData['type'])) != $reqMimeType && $sfxMimeType == $reqMimeType))
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
	 * @param object bofelamimail the bofelamimailobject to be used
	 * @param uid the uid of the email to be processed
	 * @param partid the partid of the email
	 * @param mailbox the mailbox, that holds the message
	 * @param preserveHTML flag to pass through to getdisplayableBody
	 * @return array with 'mailaddress'=>$mailaddress,
	 *				'subject'=>$subject,
	 *				'message'=>$message,
	 *				'attachments'=>$attachments,
	 *				'headers'=>$headers,
	 */
	static function get_mailcontent(&$bofelamimail,$uid,$partid='',$mailbox='', $preserveHTML = false)
	{
			//echo __METHOD__." called for $uid,$partid <br>";
			$headers = $bofelamimail->getMessageHeader($uid,$partid,true);
			// dont force retrieval of the textpart, let felamimail preferences decide
			$bodyParts = $bofelamimail->getMessageBody($uid,($preserveHTML?'always_display':'only_if_no_text'),$partid);
			//error_log(array2string($bodyParts));
			$attachments = $bofelamimail->getMessageAttachments($uid,$partid);

			if ($bofelamimail->isSentFolder($mailbox)) $mailaddress = $headers['TO'];
			elseif (isset($headers['FROM'])) $mailaddress = $headers['FROM'];
			elseif (isset($headers['SENDER'])) $mailaddress = $headers['SENDER'];
			if (isset($headers['CC'])) $mailaddress .= ','.$headers['CC'];
			//_debug_array($headers);
			$subject = $headers['SUBJECT'];

			$message = self::getdisplayableBody($bofelamimail, $bodyParts, $preserveHTML);
			if ($preserveHTML && $bofelamimail->activeMimeType == 'text/plain') $message = '<pre>'.$message.'</pre>';
			$headdata = self::createHeaderInfoSection($headers, '',$preserveHTML);
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
							$headdata = self::createHeaderInfoSection($mailcontent['headers'],'',$preserveHTML);
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
							translation::convert($attachments[$num]['attachment'],$attachments[$num]['charset']);
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
	 * @param array header headerarray may contain SUBJECT,FROM,SENDER,TO,CC,BCC,DATE,PRIORITY,IMPORTANCE
	 * @param string headline Text tom use for headline
	 * @param bool createHTML do it with HTML breaks
	 * @return string a preformatted string with the information of the header worked into it
	 */
	static function createHeaderInfoSection($header,$headline='', $createHTML = false)
	{
		$headdata = null;
		if ($header['SUBJECT']) $headdata = lang('subject').': '.$header['SUBJECT'].($createHTML?"<br />":"\n");
		if ($header['FROM']) $headdata .= lang('from').': '.self::convertAddressArrayToString($header['FROM'], $createHTML).($createHTML?"<br />":"\n");
		if ($header['SENDER']) $headdata .= lang('sender').': '.self::convertAddressArrayToString($header['SENDER'], $createHTML).($createHTML?"<br />":"\n");
		if ($header['TO']) $headdata .= lang('to').': '.self::convertAddressArrayToString($header['TO'], $createHTML).($createHTML?"<br />":"\n");
		if ($header['CC']) $headdata .= lang('cc').': '.self::convertAddressArrayToString($header['CC'], $createHTML).($createHTML?"<br />":"\n");
		if ($header['BCC']) $headdata .= lang('bcc').': '.self::convertAddressArrayToString($header['BCC'], $createHTML).($createHTML?"<br />":"\n");
		if ($header['DATE']) $headdata .= lang('date').': '.$header['DATE'].($createHTML?"<br />":"\n");
		if ($header['PRIORITY'] && $header['PRIORITY'] != 'normal') $headdata .= lang('priority').': '.$header['PRIORITY'].($createHTML?"<br />":"\n");
		if ($header['IMPORTANCE'] && $header['IMPORTANCE'] !='normal') $headdata .= lang('importance').': '.$header['IMPORTANCE'].($createHTML?"<br />":"\n");
		//if ($mailcontent['headers']['ORGANIZATION']) $headdata .= lang('organization').': '.$mailcontent['headers']['ORGANIZATION']."\
		if (!empty($headdata))
		{
			if (!empty($headline)) $headdata = "---------------------------- $headline ----------------------------".($createHTML?"<br />":"\n").$headdata;
			if (empty($headline)) $headdata = "--------------------------------------------------------".($createHTML?"<br />":"\n").$headdata;
			$headdata .= "--------------------------------------------------------".($createHTML?"<br />":"\n");
		}
		else
		{
			$headdata = "--------------------------------------------------------".($createHTML?"<br />":"\n");
		}
		return $headdata;
	}

	/**
	 * convertAddressArrayToString - converts an felamimail envelope Address Array To String
	 * @param array $rfcAddressArray  an addressarray as provided by felamimail retieved via egw_pear....
	 * @return string a comma separated string with the mailaddress(es) converted to text
	 */
	static function convertAddressArrayToString($rfcAddressArray, $createHTML = false)
	{
		//error_log(__METHOD__.__LINE__.array2string($rfcAddressArray));
		$returnAddr ='';
		if (is_array($rfcAddressArray))
		{
			foreach((array)$rfcAddressArray as $addressData) {
				//error_log(__METHOD__.__LINE__.array2string($addressData));
				if($addressData['MAILBOX_NAME'] == 'NIL') {
					continue;
				}
				if(strtolower($addressData['MAILBOX_NAME']) == 'undisclosed-recipients') {
					continue;
				}
				if ($addressData['RFC822_EMAIL'])
				{
					$addressObjectA = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($addressData['RFC822_EMAIL']):$addressData['RFC822_EMAIL']),'');
				}
				else
				{
					$emailaddress = ($addressData['PERSONAL_NAME']?$addressData['PERSONAL_NAME'].' <'.$addressData['EMAIL'].'>':$addressData['EMAIL']);
					$addressObjectA = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($emailaddress):$emailaddress),'');
				}
				$addressObject = $addressObjectA[0];
				//error_log(__METHOD__.__LINE__.array2string($addressObject));
				if ($addressObject->host == '.SYNTAX-ERROR.') continue;
				//$mb =(string)$addressObject->mailbox;
				//$h = (string)$addressObject->host;
				//$p = (string)$addressObject->personal;
				$returnAddr .= (strlen($returnAddr)>0?',':'');
				//error_log(__METHOD__.__LINE__.$p.' <'.$mb.'@'.$h.'>');
				$buff = imap_rfc822_write_address($addressObject->mailbox, $addressObject->host, $addressObject->personal);
				$buff = str_replace(array('<','>'),array('[',']'),$buff);
				if ($createHTML) $buff = felamimail_bo::htmlspecialchars($buff);
				//error_log(__METHOD__.__LINE__.' Address: '.$returnAddr);
				$returnAddr .= $buff;
			}
		}
		else
		{
			// do not mess with strings, return them untouched /* ToDo: validate string as Address */
			$rfcAddressArray = str_replace(array('<','>'),array('[',']'),$rfcAddressArray);
			if (is_string($rfcAddressArray)) return ($createHTML ? felamimail_bo::htmlspecialchars($rfcAddressArray) : $rfcAddressArray);
		}
		return $returnAddr;
	}

	/**
	 * getStyles - extracts the styles from the given bodyparts
	 * @param array $bodyParts  with the bodyparts
	 * @return string a preformatted string with the mails converted to text
	 */
	static function &getStyles($_bodyParts)
	{
		$style = '';
		if (empty($_bodyParts)) return "";
		foreach((array)$_bodyParts as $singleBodyPart) {
			if (!isset($singleBodyPart['body'])) {
				$singleBodyPart['body'] = self::getStyles($singleBodyPart);
				$style .= $singleBodyPart['body'];
				continue;
			}
			if ($singleBodyPart['charSet']===false) $singleBodyPart['charSet'] = felamimail_bo::detect_encoding($singleBodyPart['body']);
			$singleBodyPart['body'] = $GLOBALS['egw']->translation->convert(
				$singleBodyPart['body'],
				strtolower($singleBodyPart['charSet'])
			);
			$ct = 0;
			if (stripos($singleBodyPart['body'],'<style')!==false)  $ct = preg_match_all('#<style(?:\s.*)?>(.+)</style>#isU', $singleBodyPart['body'], $newStyle);
			if ($ct>0)
			{
				//error_log(__METHOD__.__LINE__.array2string($newStyle[0]));
				$style2buffer = implode('',$newStyle[0]);
			}
			if ($style2buffer && strtoupper(self::$displayCharset) == 'UTF-8')
			{
				//error_log(__METHOD__.__LINE__.array2string($style2buffer));
				$test = json_encode($style2buffer);
				//error_log(__METHOD__.__LINE__.'#'.$test.'# ->'.strlen($style2buffer).' Error:'.json_last_error());
				//if (json_last_error() != JSON_ERROR_NONE && strlen($style2buffer)>0)
				if ($test=="null" && strlen($style2buffer)>0)
				{
					// this should not be needed, unless something fails with charset detection/ wrong charset passed
					error_log(__METHOD__.__LINE__.' Found Invalid sequence for utf-8 in CSS:'.$style2buffer.' Charset Reported:'.$singleBodyPart['charSet'].' Carset Detected:'.felamimail_bo::detect_encoding($style2buffer));
					$style2buffer = utf8_encode($style2buffer);
				}
			}
			$style .= $style2buffer;
		}
	    // clean out comments and stuff
		$search = array(
			'@url\(http:\/\/[^\)].*?\)@si',  // url calls e.g. in style definitions
//			'@<!--[\s\S]*?[ \t\n\r]*-->@',   // Strip multi-line comments including CDATA
//			'@<!--[\s\S]*?[ \t\n\r]*--@',    // Strip broken multi-line comments including CDATA
		);
		$style = preg_replace($search,"",$style);

		// CSS Security
		// http://code.google.com/p/browsersec/wiki/Part1#Cascading_stylesheets
		$css = preg_replace('/(javascript|expession|-moz-binding)/i','',$style);
		if (stripos($css,'script')!==false) felamimail_bo::replaceTagsCompletley($css,'script'); // Strip out script that may be included
		// we need this, as styledefinitions are enclosed with curly brackets; and template stuuff tries to replace everything between curly brackets that is having no horizontal whitespace
		$css = str_replace(':',': ',$css);
		//error_log(__METHOD__.__LINE__.$css);
		// TODO: we may have to strip urls and maybe comments and ifs
		return $css;
	}

	/**
	 * getdisplayableBody - creates the bodypart of the email as textual representation
	 * @param object $bofelamimail the bofelamimailobject to be used
	 * @param array $bodyParts  with the bodyparts
	 * @return string a preformatted string with the mails converted to text
	 */
	static function &getdisplayableBody(&$bofelamimail, $bodyParts, $preserveHTML = false)
	{
		for($i=0; $i<count($bodyParts); $i++)
		{
			if (!isset($bodyParts[$i]['body'])) {
				$bodyParts[$i]['body'] = self::getdisplayableBody($bofelamimail, $bodyParts[$i], $preserveHTML);
				$message .= empty($bodyParts[$i]['body'])?'':$bodyParts[$i]['body'];
				continue;
			}
			if (isset($bodyParts[$i]['error'])) continue;
			if (empty($bodyParts[$i]['body'])) continue;
			// some characterreplacements, as they fail to translate
			$sar = array(
				'@(\x84|\x93|\x94)@',
				'@(\x96|\x97|\x1a)@',
				'@(\x82|\x91|\x92)@',
				'@(\x85)@',
				'@(\x86)@',
				'@(\x99)@',
				'@(\xae)@',
			);
			$rar = array(
				'"',
				'-',
				'\'',
				'...',
				'&',
				'(TM)',
				'(R)',
			);

			if(($bodyParts[$i]['mimeType'] == 'text/html' || $bodyParts[$i]['mimeType'] == 'text/plain') &&
				strtoupper($bodyParts[$i]['charSet']) != 'UTF-8')
			{
				$bodyParts[$i]['body'] = preg_replace($sar,$rar,$bodyParts[$i]['body']);
			}

			if ($bodyParts[$i]['charSet']===false) $bodyParts[$i]['charSet'] = self::detect_encoding($bodyParts[$i]['body']);
			// add line breaks to $bodyParts
			//error_log(__METHOD__.__LINE__.' Charset:'.$bodyParts[$i]['charSet'].'->'.$bodyParts[$i]['body']);
			$newBody  = translation::convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
			//error_log(__METHOD__.__LINE__.' MimeType:'.$bodyParts[$i]['mimeType'].'->'.$newBody);
			/*
			// in a way, this tests if we are having real utf-8 (the displayCharset) by now; we should if charsets reported (or detected) are correct
			if (strtoupper(self::$displayCharset) == 'UTF-8')
			{
				$test = json_encode($newBody);
				//error_log(__METHOD__.__LINE__.'#'.$test.'# ->'.strlen($newBody).' Error:'.json_last_error());
				if (json_last_error() != JSON_ERROR_NONE && strlen($newBody)>0)
				{
					// this should not be needed, unless something fails with charset detection/ wrong charset passed
					error_log(__METHOD__.__LINE__.' Charset Reported:'.$bodyParts[$i]['charSet'].' Carset Detected:'.felamimail_bo::detect_encoding($bodyParts[$i]['body']));
					$newBody = utf8_encode($newBody);
				}
			}
			*/
			//error_log(__METHOD__.__LINE__.' before purify:'.$newBody);
			$bofelamimail->activeMimeType = 'text/plain';
			if ($bodyParts[$i]['mimeType'] == 'text/html') {
				$bofelamimail->activeMimeType = $bodyParts[$i]['mimeType'];
				// as translation::convert reduces \r\n to \n and purifier eats \n -> peplace it with a single space
				$newBody = str_replace("\n"," ",$newBody);
				// convert HTML to text, as we dont want HTML in infologs
				if (extension_loaded('tidy'))
				{
					$tidy = new tidy();
					$cleaned = $tidy->repairString($newBody, self::$tidy_config,'utf8');
					// Found errors. Strip it all so there's some output
					if($tidy->getStatus() == 2)
					{
						error_log(__METHOD__.__LINE__.' ->'.$tidy->errorBuffer);
					}
					else
					{
						$newBody = $cleaned;
					}
					if (!$preserveHTML)
					{
						// filter only the 'body', as we only want that part, if we throw away the html
						preg_match('`(<htm.+?<body[^>]*>)(.+?)(</body>.*?</html>)`ims', $newBody, $matches);
						if ($matches[2])
						{
							$hasOther = true;
							$newBody = $matches[2];
						}
					}
				}
				else
				{
					// htmLawed filter only the 'body'
					preg_match('`(<htm.+?<body[^>]*>)(.+?)(</body>.*?</html>)`ims', $newBody, $matches);
					if ($matches[2])
					{
						$hasOther = true;
						$newBody = $matches[2];
					}
					$htmLawed = new egw_htmLawed();
					// the next line should not be needed
					$newBody = str_replace(array('&amp;amp;','<DIV><BR></DIV>',"<DIV>&nbsp;</DIV>",'<div>&nbsp;</div>'),array('&amp;','<BR>','<BR>','<BR>'),$newBody);
					$newBody = $htmLawed->egw_htmLawed($newBody);
					if ($hasOther && $preserveHTML) $newBody = $matches[1]. $newBody. $matches[3];
				}
				//error_log(__METHOD__.__LINE__.' after purify:'.$newBody);
				if ($preserveHTML==false) $newBody = $bofelamimail->convertHTMLToText($newBody,true);
				//error_log(__METHOD__.__LINE__.' after convertHTMLToText:'.$newBody);
				if ($preserveHTML==false) $newBody = nl2br($newBody); // we need this, as htmLawed removes \r\n
				$bofelamimail->getCleanHTML($newBody,false,$preserveHTML); // remove stuff we regard as unwanted
				if ($preserveHTML==false) $newBody = str_replace("<br />","\r\n",$newBody);
				//error_log(__METHOD__.__LINE__.' after getClean:'.$newBody);
				$message .= $newBody;
				continue;
			}
			$newBody =self::htmlspecialchars($newBody);
			//error_log(__METHOD__.__LINE__.' Body(after specialchars):'.$newBody);
			$newBody = strip_tags($newBody); //we need to fix broken tags (or just stuff like "<800 USD/p" )
			//error_log(__METHOD__.__LINE__.' Body(after strip tags):'.$newBody);
			$newBody = htmlspecialchars_decode($newBody,ENT_QUOTES);
			//error_log(__METHOD__.__LINE__.' Body (after hmlspc_decode):'.$newBody);
			$message .= $newBody;
			//continue;
		}
		return $message;
	}

	/**
	 * processURL2InlineImages - parses a html text for images, and adds them as inline attachment
	 * we do not use the functionality of the phpmailer here, as phpmailers functionality requires
	 * files to be present within the filesystem, which we do not require as we make this happen
	 * (we load the file, and store it temporarily for the use of attaching it to the file send
	 * @param object $_mailObject instance of the egw_mailer/phpmailer Object to be used
	 * @param string $_html2parse the html to parse and to be altered, if conditions meet
	 * @return void
	 */
	static function processURL2InlineImages(&$_mailObject, &$_html2parse)
	{
		preg_match_all("/(src|background)=\"(.*)\"/Ui", $_html2parse, $images);
		if(isset($images[2])) {
			foreach($images[2] as $i => $url) {
				$basedir = '';
				$needTempFile = true;
				//error_log(__METHOD__.__LINE__.$url);
				//error_log(__METHOD__.__LINE__.$GLOBALS['egw_info']['server']['webserver_url']);
				//error_log(__METHOD__.__LINE__.array2string($GLOBALS['egw_info']['user']));
				// do not change urls for absolute images (thanks to corvuscorax)
				//if (!preg_match('#^[A-z]+://#',$url)) {
					//error_log(__METHOD__.__LINE__.' -> '.$i.': '.array2string($images));
					$filename = basename($url);
					$directory = dirname($url);
					($directory == '.')?$directory='':'';
					$cid = 'cid:' . md5($filename);
					$ext = pathinfo($filename, PATHINFO_EXTENSION);
					$mimeType  = $_mailObject->_mime_types($ext);
					if ( strlen($directory) > 1 && substr($directory,-1) != '/') { $directory .= '/'; }
					$myUrl = $directory.$filename;
					if ($myUrl[0]=='/') // local path -> we only path's that are available via http/https (or vfs)
					{
						$basedir = ($_SERVER['HTTPS']?'https://':'http://'.$_SERVER['HTTP_HOST']);
					}
					// use vfs instead of url containing webdav.php
					// ToDo: we should test if the webdav url is of our own scope, as we cannot handle foreign
					// webdav.php urls as vfs
					if (strpos($myUrl,'webdav.php') !== false) // we have a webdav link, so we build a vfs/sqlfs link of it.
					{
						egw_vfs::load_wrapper('vfs');
						list($garbage,$vfspart) = explode('webdav.php',$myUrl,2);
						$myUrl = $vfspart;
						$basedir = 'vfs://default';
						$needTempFile = false;
					}
					if ( strlen($basedir) > 1 && substr($basedir,-1) != '/' && $myUrl[0]!='/') { $basedir .= '/'; }
					//error_log(__METHOD__.__LINE__.$basedir.$myUrl);
					if ($needTempFile) $data = file_get_contents($basedir.urldecode($myUrl));
					if ($data || $needTempFile === false)
					{
						if ($needTempFile)
						{
							$attachment_file =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
							$tmpfile = fopen($attachment_file,'w');
							fwrite($tmpfile,$data);
							fclose($tmpfile);
						}
						else
						{
							$attachment_file = $basedir.urldecode($myUrl);
						}
						//error_log(__METHOD__.__LINE__.' '.$url.' -> '.$basedir.$myUrl. ' TmpFile:'.$tmpfile);
						if ( $_mailObject->AddEmbeddedImage($attachment_file, md5($filename), $filename, 'base64',$mimeType) ) {
							$_html2parse = preg_replace("/".$images[1][$i]."=\"".preg_quote($url, '/')."\"/Ui", $images[1][$i]."=\"".$cid."\"", $_html2parse);
						}
					}
				//}
			}
		}
	}

	/**
	 * importMessageToMergeAndSend
	 *
	 * @param object &bo_merge bo_merge object
	 * @param string $document the full filename
	 * @param array $SendAndMergeTocontacts array of contact ids
	 * @param string $_folder (passed by reference) will set the folder used. must be set with a folder, but will hold modifications if
	 *					folder is modified
	 * @param string $importID ID for the imported message, used by attachments to identify them unambiguously
	 * @return mixed array of messages with success and failed messages or exception
	 */
	function importMessageToMergeAndSend(&$bo_merge, $document, $SendAndMergeTocontacts, &$_folder, $importID='')
	{
		$importfailed = false;
		$processStats = array('success'=>array(),'failed'=>array());
		if (empty($SendAndMergeTocontacts))
		{
			$importfailed = true;
			$alert_msg .= lang("Import of message %1 failed. No Contacts to merge and send to specified.",$_formData['name']);
		}

		// check if formdata meets basic restrictions (in tmp dir, or vfs, mimetype, etc.)
		/* as the file is provided by bo_merge, we do not check
		try
		{
			$tmpFileName = felamimail_bo::checkFileBasics($_formData,$importID);
		}
		catch (egw_exception_wrong_userinput $e)
		{
			$importfailed = true;
			$alert_msg .= $e->getMessage();
		}
		*/
		$tmpFileName = $document;
		// -----------------------------------------------------------------------
		if ($importfailed === false)
		{
			$mailObject = new egw_mailer();
			try
			{
				$this->parseFileIntoMailObject($mailObject,$tmpFileName,$Header,$Body);
			}
			catch (egw_exception_assertion_failed $e)
			{
				$importfailed = true;
				$alert_msg .= $e->getMessage();
			}

			//_debug_array($Body);
			$this->openConnection();
			if (empty($_folder))
			{
				$_folder = $this->getSentFolder();
			}
			$delimiter = $this->getHierarchyDelimiter();
			if($_folder=='INBOX'.$delimiter) $_folder='INBOX';
			if ($importfailed === false)
			{
				$Subject = $mailObject->Subject;
				//error_log(__METHOD__.__LINE__.' Subject:'.$Subject);
				$Body = $mailObject->Body;
				//error_log(__METHOD__.__LINE__.' Body:'.$Body);
				//error_log(__METHOD__.__LINE__.' BodyContentType:'.$mailObject->BodyContentType);
				$AltBody = $mailObject->AltBody;
				//error_log(__METHOD__.__LINE__.' AltBody:'.$AltBody);
				foreach ($SendAndMergeTocontacts as $k => $val)
				{
					$sendOK = $openComposeWindow = $openAsDraft = false;
					//error_log(__METHOD__.__LINE__.' Id To Merge:'.$val);
					if ($GLOBALS['egw_info']['flags']['currentapp'] == 'addressbook' &&
						count($SendAndMergeTocontacts) > 1 &&
						is_numeric($val) || $GLOBALS['egw']->accounts->name2id($val)) // do the merge
					{

						//error_log(__METHOD__.__LINE__.array2string($mailObject));
						$contact = $bo_merge->contacts->read($val);
						//error_log(__METHOD__.__LINE__.' ID:'.$val.' Data:'.array2string($contact));
						$email = ($contact['email'] ? $contact['email'] : $contact['email_home']);
						$nfn = ($contact['n_fn'] ? $contact['n_fn'] : $contact['n_given'].' '.$contact['n_family']);
						$activeMailProfile = $this->mailPreferences->getIdentity($this->profileID, true);
						//error_log(__METHOD__.__LINE__.array2string($activeMailProfile));
						$mailObject->From = $activeMailProfile->emailAddress;
						//$mailObject->From  = $_identity->emailAddress;
						$mailObject->FromName = $mailObject->EncodeHeader($activeMailProfile->realName);

						$mailObject->MessageID = '';
						$mailObject->ClearAllRecipients();
						$mailObject->ClearCustomHeaders();
						$mailObject->AddAddress($email,$mailObject->EncodeHeader($nfn));
						$mailObject->Subject = $bo_merge->merge_string($Subject, $val, $e, 'text/plain');
						if (!empty($AltBody))
						{
							$mailObject->IsHTML(true);
						}
						elseif (empty($AltBody) && $mailObject->BodyContentType=='text/html')
						{
							$mailObject->IsHTML(true);
							$AltBody = self::convertHTMLToText($Body,false,$stripalltags=true);
						}
						else
						{
							$mailObject->IsHTML(false);
						}
						//error_log(__METHOD__.__LINE__.' ContentType:'.$mailObject->BodyContentType);
						if (!empty($Body)) $mailObject->Body = $bo_merge->merge_string($Body, $val, $e, $mailObject->BodyContentType);
						//error_log(__METHOD__.__LINE__.' Result:'.$mailObject->Body.' error:'.array2string($e));
						if (!empty($AltBody)) $mailObject->AltBody = $bo_merge->merge_string($AltBody, $val, $e, $mailObject->AltBodyContentType);

						$ogServer = $this->mailPreferences->getOutgoingServer($this->profileID);
						#_debug_array($ogServer);
						$mailObject->Host     = $ogServer->host;
						$mailObject->Port = $ogServer->port;
						// SMTP Auth??
						if($ogServer->smtpAuth) {
							$mailObject->SMTPAuth = true;
							// check if username contains a ; -> then a sender is specified (and probably needed)
							list($username,$senderadress) = explode(';', $ogServer->username,2);
							if (isset($senderadress) && !empty($senderadress)) $mailObject->Sender = $senderadress;
							$mailObject->Username = $username;
							$mailObject->Password = $ogServer->password;
						}
						//error_log(__METHOD__.__LINE__.array2string($mailObject));
						// set a higher timeout for big messages
						@set_time_limit(120);
						$sendOK = true;
						try {
							$mailObject->Send();
						}
						catch(phpmailerException $e) {
							$sendOK = false;
							$errorInfo = $e->getMessage();
							if ($mailObject->ErrorInfo) // use the complete mailer ErrorInfo, for full Information
							{
								if (stripos($mailObject->ErrorInfo, $errorInfo)===false)
								{
									$errorInfo = 'Send Failed for '.$mailObject->Subject.' to '.$nfn.'<'.$email.'> Error:'.$mailObject->ErrorInfo.'<br>'.$errorInfo;
								}
								else
								{
									$errorInfo = $mailObject->ErrorInfo;
								}
							}
							error_log(__METHOD__.__LINE__.array2string($errorInfo));
						}
					}
					elseif (!$k)	// 1. entry, further entries will fail for apps other then addressbook
					{
						$openAsDraft = true;
						$mailObject->MessageID = '';
						$mailObject->ClearAllRecipients();
						$mailObject->ClearCustomHeaders();
						if ($GLOBALS['egw_info']['flags']['currentapp'] == 'addressbook' &&
							is_numeric($val) || $GLOBALS['egw']->accounts->name2id($val)) // do the merge
						{
							$contact = $bo_merge->contacts->read($val);
							//error_log(__METHOD__.__LINE__.array2string($contact));
							$email = ($contact['email'] ? $contact['email'] : $contact['email_home']);
							$nfn = ($contact['n_fn'] ? $contact['n_fn'] : $contact['n_given'].' '.$contact['n_family']);
							$mailObject->AddAddress($email,$mailObject->EncodeHeader($nfn));
						}
						$mailObject->Subject = $bo_merge->merge_string($Subject, $val, $e, 'text/plain');
						if (!empty($AltBody))
						{
							$mailObject->IsHTML(true);
						}
						elseif (empty($AltBody) && $mailObject->BodyContentType=='text/html')
						{
							$mailObject->IsHTML(true);
							$AltBody = self::convertHTMLToText($Body,false,$stripalltags=true);
						}
						else
						{
							$mailObject->IsHTML(false);
						}
						//error_log(__METHOD__.__LINE__.' ContentType:'.$mailObject->BodyContentType);
						if (!empty($Body)) $mailObject->Body = $bo_merge->merge_string($Body, $val, $e, $mailObject->BodyContentType);
						//error_log(__METHOD__.__LINE__.' Result:'.$mailObject->Body.' error:'.array2string($e));
						if (!empty($AltBody)) $mailObject->AltBody = $bo_merge->merge_string($AltBody, $val, $e, $mailObject->AltBodyContentType);
						$_folder = $this->getDraftFolder();
					}
					if ($sendOK || $openAsDraft)
					{
						$BCCmail = '';
						if ($this->folderExists($_folder,true))
						{
						    if($this->isSentFolder($_folder))
							{
						        $flags = '\\Seen';
						    } elseif($this->isDraftFolder($_folder)) {
						        $flags = '\\Draft';
						    } else {
						        $flags = '';
						    }
							unset($mailObject->sentHeader);
							unset($mailObject->sentBody);
							$savefailed = false;
							try
							{
								$messageUid =$this->appendMessage($_folder,
									$BCCmail.$mailObject->getMessageHeader(),
									$mailObject->getMessageBody(),
									$flags);
							}
							catch (egw_exception_wrong_userinput $e)
							{
								$savefailed = true;
								$alert_msg .= lang("Save of message %1 failed. Could not save message to folder %2 due to: %3",$Subject,$_folder,$e->getMessage());
							}
							// no send, save successful, and message_uid present
							if ($savefailed===false && $messageUid && $sendOK===false)
							{
								$openComposeWindow = true;
								list($fm_width,$fm_height) = explode('x',egw_link::get_registry('felamimail','view_popup'));
								$linkData = array
								(
									'menuaction'    => 'felamimail.uicompose.composeFromDraft',
									'uid'		=> $messageUid,
									'folder'    => base64_encode($_folder),
									'icServer'	=> $this->profileID,
									'method'	=> 'importMessageToMergeAndSend',
								);
								$composeUrl = $GLOBALS['egw']->link('/index.php',$linkData);
								//error_log(__METHOD__.__LINE__.' ComposeURL:'.$composeUrl);
								$GLOBALS['egw_info']['flags']['java_script_thirst'] .= '<script language="JavaScript">'.
									//"egw_openWindowCentered('$composeUrl','composeAsDraft_".$messageUid."',".$fm_width.",".$fm_height.");".
									"window.open('$composeUrl','_blank','dependent=yes,width=".$fm_width.",height=".$fm_height.",toolbar=no,scrollbars=no,status=no');".
									"</script>";
								$processStats['success'][] = lang("Saving of message %1 succeeded. Check Folder %2.",$Subject,$_folder);
							}
						}
						else
						{
							$savefailed = true;
							$alert_msg .= lang("Saving of message %1 failed. Destination Folder %2 does not exist.",$Subject,$_folder);
						}
						if ($sendOK)
						{
							$processStats['success'][] = 'Send succeeded to '.$nfn.'<'.$email.'>'.($savefailed?' but failed to store to Folder:'.$_folder:'');
						}
						else
						{
							if (!$openComposeWindow) $processStats['failed'][] = 'Send failed to '.$nfn.'<'.$email.'> See error_log for details';
						}
					}
				}
			}
			unset($mailObject);
		}
		// set the url to open when refreshing
		if ($importfailed == true)
		{
			throw new egw_exception_wrong_userinput($alert_msg);
		}
		else
		{
			return $processStats;
		}
	}

	/**
	 * functions to allow the parsing of message/rfc files
	 * used in felamimail to import mails, or parsev a message from file enrich it with addressdata (merge) and send it right away.
	 */

	/**
	 * parseFileIntoMailObject - parses a message/rfc mail from file to the mailobject and returns the header and body via reference
	 *   throws egw_exception_assertion_failed when the required Pear Class is not found/loadable
	 * @param object $mailObject instance of the SMTP Mailer Object
	 * @param string $tmpFileName string that points/leads to the file to be imported
	 * @param string &$Header  reference used to return the imported Mailheader
	 * @param string &$Body reference to return the imported Body
	 * @return void Mailheader and body is returned via Reference in $Header $Body
	 */
	function parseFileIntoMailObject($mailObject,$tmpFileName,&$Header,&$Body)
	{
			$message = file_get_contents($tmpFileName);
			try
			{
				return $this->parseRawMessageIntoMailObject($mailObject,$message,$Header,$Body);
			}
			catch (egw_exception_assertion_failed $e)
			{	// not sure that this is needed to pass on exeptions
				throw new egw_exception_assertion_failed($e->getMessage());
			}
	}

	/**
	 * parseRawMessageIntoMailObject - parses a message/rfc mail from file to the mailobject and returns the header and body via reference
	 *   throws egw_exception_assertion_failed when the required Pear Class is not found/loadable
	 * @param object $mailObject instance of the SMTP Mailer Object
	 * @param string $message string containing the RawMessage
	 * @param string &$Header  reference used to return the imported Mailheader
	 * @param string &$Body reference to return the imported Body
	 * @return void Mailheader and body is returned via Reference in $Header $Body
	 */
	function parseRawMessageIntoMailObject($mailObject,$message,&$Header,&$Body)
	{
			/**
			 * pear/Mail_mimeDecode requires package "pear/Mail_Mime" (version >= 1.4.0, excluded versions: 1.4.0)
			 * ./pear upgrade Mail_Mime
			 * ./pear install Mail_mimeDecode
			 */
			//echo '<pre>'.$message.'</pre>';
			//error_log(__METHOD__.__LINE__.$message);
			if (class_exists('Mail_mimeDecode',false)==false && (@include_once 'Mail/mimeDecode.php') === false) throw new egw_exception_assertion_failed(lang('Required PEAR class Mail/mimeDecode.php not found.'));
			$mailDecode = new Mail_mimeDecode($message);
			$structure = $mailDecode->decode(array('include_bodies'=>true,'decode_bodies'=>true,'decode_headers'=>true));
			//error_log(__METHOD__.__LINE__.array2string($structure));
			//_debug_array($structure);
			//exit;
			// now create a message to view, save it in Drafts and open it
			$mailObject->PluginDir = EGW_SERVER_ROOT."/phpgwapi/inc/";
			$mailObject->IsSMTP();
			$mailObject->CharSet = self::$displayCharset; // some default, may be altered by BodyImport
			if (isset($structure->ctype_parameters['charset'])) $mailObject->CharSet = trim($structure->ctype_parameters['charset']);
			$mailObject->Encoding = 'quoted-printable'; // some default, may be altered by BodyImport
/*
			$mailObject->AddAddress($emailAddress, $addressObject->personal);
			$mailObject->AddCC($emailAddress, $addressObject->personal);
			$mailObject->AddBCC($emailAddress, $addressObject->personal);
			$mailObject->AddReplyto($emailAddress, $addressObject->personal);
*/
			$result ='';
			$contenttypecalendar = '';
			foreach((array)$structure->headers as $key => $val)
			{
				//error_log(__METHOD__.__LINE__.$key.'->'.$val);
				foreach((array)$val as $i => $v)
				{
					if ($key!='content-type' && $key !='content-transfer-encoding' &&
						$key != 'message-id'  &&
						$key != 'subject' &&
						$key != 'from' &&
						$key != 'to' &&
						$key != 'cc' &&
						$key != 'bcc' &&
						$key != 'x-priority') // the omitted values to that will be set at the end
					{
						$Header .= $mailObject->HeaderLine($key, trim($v));
					}
				}
				switch ($key)
				{
					case 'x-priority':
						$mailObject->Priority = $val;
						break;
					case 'message-id':
						$mailObject->MessageID  = $val; // ToDo: maybe we want to regenerate the message id all the time
						break;
					case 'sender':
						$mailObject->Sender  = $val;
						break;
					case 'to':
					case 'cc':
					case 'bcc':
					case 'from':
						$address_array  = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($val):$val),'');
						$i = 0;
						foreach((array)$address_array as $addressObject)
						{
							$mb = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
							$pName = $addressObject->personal;
							if ($key=='from')
							{
								$mailObject->From = $mb;
								$mailObject->FromName = $pName;
							}
							${$key}[$i] = array($mb,$pName);
							$i++;
						}
						$Header .= $mailObject->TextLine(trim($mailObject->AddrAppend($key,${$key})));
						break;
					case 'content-transfer-encoding':
						$mailObject->Encoding = $val;
						break;
					case 'content-type':
						//error_log(__METHOD__.__LINE__.' '.$key.'->'.$val);
						if (stripos($val,'calendar')) $contenttypecalendar = $val;
						break;
					case 'subject':
						$mailObject->Subject = $mailObject->EncodeHeader($mailObject->SecureHeader($val));
						$Header .= $mailObject->HeaderLine('Subject',$mailObject->Subject);
						break;
					default:
						// stuff like X- ...
						//$mailObject->AddCustomHeader('X-Mailer: FeLaMiMail');
						if (!strtolower(substr($key,0,2))=='x-') break;
					//case 'priority': // priority is a cusom header field
					//	$mailObject->Priority = $val;
					//	break;
					case 'disposition-notification-To':
					case 'organization':
						foreach((array)$val as $i => $v) $mailObject->AddCustomHeader($key.': '. $v);
						break;
				}
			}
			$seemsToBePlainMessage = false;
			if ($structure->ctype_primary=='text' && $structure->body)
			{
				$mailObject->IsHTML($structure->ctype_secondary=='html'?true:false);
				$mailObject->Body = $structure->body;
				$seemsToBePlainMessage = true;
			}
			$this->createBodyFromStructure($mailObject, $structure, $parenttype=null);
			$mailObject->SetMessageType();
			$mailObject->CreateHeader(); // this sets the boundary stufff
			//echo "Boundary:".$mailObject->FetchBoundary(1).'<br>';
			//$boundary ='';
			//if (isset($structure->ctype_parameters['boundary'])) $boundary = ' boundary="'.$mailObject->FetchBoundary(1).'";';
			if ($seemsToBePlainMessage && !empty($contenttypecalendar) && $mailObject->ContentType=='text/plain')
			{
				$Header .= $mailObject->HeaderLine('Content-Transfer-Encoding', $mailObject->Encoding);
				$Header .= $mailObject->HeaderLine('Content-type', $contenttypecalendar);
			}
			else
			{
				$Header .= $mailObject->GetMailMIME();
			}
			$Body = $mailObject->getMessageBody(); // this is a method of the egw_mailer/phpmailer class
			//_debug_array($Header);
			//_debug_array($Body);
			//_debug_array($mailObject);
			//exit;
	}

	/**
	 * createBodyFromStructure - fetches/creates the bodypart of the email as textual representation
	 *   is called recursively to be able to fetch the stuctureparts of the mail parsed from Mail/mimeDecode
	 * @param object $mailObject instance of the SMTP Mailer Object
	 * @param array $structure array that represents structure and content of a mail parsed from Mail/mimeDecode
	 * @param string $parenttype type of the parent node
	 * @return void Parsed Information is passed to the mailObject to be processed there
	 */
	function createBodyFromStructure($mailObject, $structure, $parenttype=null, $decode=false)
	{
		static $attachmentnumber;
		static $isHTML;
		static $alternatebodyneeded;
		if (is_null($isHTML)) $isHTML = $structure->ctype_secondary=='html'?true:false;
		if (is_null($attachmentnumber)) $attachmentnumber = 0;
		if ($structure->parts && $structure->ctype_primary=='multipart')
		{
			if (is_null($alternatebodyneeded)) $alternatebodyneeded = false;
			foreach($structure->parts as $part)
			{
				//error_log(__METHOD__.__LINE__.' Structure Content Type:'.$structure->ctype_primary.'/'.$structure->ctype_secondary.' Decoding:'.($decode?'on':'off'));
				//error_log(__METHOD__.__LINE__.' '.$structure->ctype_primary.'/'.$structure->ctype_secondary.' => '.$part->ctype_primary.'/'.$part->ctype_secondary);
				//error_log(__METHOD__.__LINE__.' Part:'.array2string($part));
				$partFetched = false;
				//echo __METHOD__.__LINE__.$structure->ctype_primary.'/'.$structure->ctype_secondary.'<br>';
				if ($part->headers['content-transfer-encoding']) $mailObject->Encoding = $part->headers['content-transfer-encoding'];
				//$mailObject->IsHTML($part->ctype_secondary=='html'?true:false); // we do not set this here, as the default is text/plain
				if (isset($part->ctype_parameters['charset'])) $mailObject->CharSet = trim($part->ctype_parameters['charset']);
				if (($structure->ctype_secondary=='alternative'||
					 $structure->ctype_secondary=='mixed' ||
					 $structure->ctype_secondary=='signed') && $part->ctype_primary=='text' && $part->ctype_secondary=='plain' && $part->body)
				{
					//echo __METHOD__.__LINE__.$part->ctype_primary.'/'.$part->ctype_secondary.'<br>';
					//error_log(__METHOD__.__LINE__.$part->ctype_primary.'/'.$part->ctype_secondary.' already fetched Content is HTML='.$isHTML.' Body:'.$part->body);
					$bodyPart = $part->body;
					if ($decode) $bodyPart = $this->decodeMimePart($part->body,($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'));
					$mailObject->Body = ($isHTML==false?$mailObject->Body:'').$bodyPart;
					$mailObject->AltBody .= $bodyPart;
					$partFetched = true;
				}
				if (($structure->ctype_secondary=='alternative'||
					 $structure->ctype_secondary=='mixed' ||
					 $structure->ctype_secondary=='signed' ) &&
					$part->ctype_primary=='text' && $part->ctype_secondary=='html' && $part->body)
				{
					//echo __METHOD__.__LINE__.$part->ctype_primary.'/'.$part->ctype_secondary.'<br>';
					//error_log(__METHOD__.__LINE__.$part->ctype_primary.'/'.$part->ctype_secondary.' already fetched Content is HTML='.$isHTML.' Body:'.$part->body);
					$bodyPart = $part->body;
					if ($decode) $bodyPart = $this->decodeMimePart($part->body,($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'));
					$mailObject->IsHTML(true); // we need/want that here, because looping through all message parts may mess up the message body mimetype
					$mailObject->Body = ($isHTML?$mailObject->Body:'').$bodyPart;
					$alternatebodyneeded = true;
					$isHTML=true;
					$partFetched = true;
				}
				if (($structure->ctype_secondary=='alternative'||
					 $structure->ctype_secondary=='mixed' ||
					 $structure->ctype_secondary=='signed' ) &&
					$part->ctype_primary=='text' && $part->ctype_secondary=='calendar' && $part->body)
				{
					//error_log(__METHOD__.__LINE__.$part->ctype_primary.'/'.$part->ctype_secondary.' BodyPart:'.array2string($part));
					$bodyPart = $part->body;
					if ($decode) $bodyPart = $this->decodeMimePart($part->body,($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'));
					$mailObject->AltExtended = $bodyPart;
					// "text/calendar; charset=utf-8; name=meeting.ics; method=REQUEST"
					// [ctype_parameters] => Array([charset] => utf-8[name] => meeting.ics[method] => REQUEST)
					$mailObject->AltExtendedContentType = $part->ctype_primary.'/'.$part->ctype_secondary.';'.
						($part->ctype_parameters['name']?' name='.$part->ctype_parameters['name'].';':'').
						($part->ctype_parameters['method']?' method='.$part->ctype_parameters['method'].'':'');
					$partFetched = true;
				}
				if (($structure->ctype_secondary=='mixed' ||
					 $structure->ctype_secondary=='related' ||
					 $structure->ctype_secondary=='alternative' ||
					 $structure->ctype_secondary=='signed') && $part->ctype_primary=='multipart')
				{
					//error_log( __METHOD__.__LINE__." Recursion to fetch subparts:".$part->ctype_primary.'/'.$part->ctype_secondary);
					$this->createBodyFromStructure($mailObject, $part, $parenttype=null, $decode);
				}
				//error_log(__METHOD__.__LINE__.$structure->ctype_primary.'/'.$structure->ctype_secondary.' => '.$part->ctype_primary.'/'.$part->ctype_secondary.' Part:'.array2string($part));
				if ($part->body && (($structure->ctype_secondary=='mixed' && $part->ctype_primary!='multipart') ||
					trim($part->disposition) == 'attachment' ||
					trim($part->disposition) == 'inline'))
				{
					//error_log(__METHOD__.__LINE__.$structure->ctype_secondary.'=>'.$part->ctype_primary.'/'.$part->ctype_secondary.'->'.array2string($part));
					$attachmentnumber++;
					$filename = trim(($part->ctype_parameters['name']?$part->ctype_parameters['name']:$part->d_parameters['filename']));
					if (strlen($filename)==0) $filename = 'noname_'.$attachmentnumber;
					//echo $part->headers['content-transfer-encoding'].'#<br>';
					if ($decode) $part->body = $this->decodeMimePart($part->body,($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'));
					if ((trim($part->disposition)=='attachment' || trim($part->disposition) == 'inline') && $partFetched==false)
					{
						//error_log(__METHOD__.__LINE__.' Add String '.($part->disposition=='attachment'?'Attachment':'Part').' of type:'.$part->ctype_primary.'/'.$part->ctype_secondary);
						$mailObject->AddStringAttachment($part->body, //($part->headers['content-transfer-encoding']?base64_decode($part->body):$part->body),
													 $filename,
													 ($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'),
													 $part->ctype_primary.'/'.$part->ctype_secondary
													);
					}
					if (!(trim($part->disposition)=='attachment' || trim($part->disposition) == 'inline') && $partFetched==false)
					{
						//error_log(__METHOD__.__LINE__.' Add String '.($part->disposition=='attachment'?'Attachment':'Part').' of type:'.$part->ctype_primary.'/'.$part->ctype_secondary.' Body:'.$part->body);
						$mailObject->AddStringPart($part->body, //($part->headers['content-transfer-encoding']?base64_decode($part->body):$part->body),
													 $filename,
													 ($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'),
													 $part->ctype_primary.'/'.$part->ctype_secondary
													);
					}
				}
			}
			if ($alternatebodyneeded == false) $mailObject->AltBody = '';
		}
	}
}
