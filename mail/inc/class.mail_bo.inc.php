<?php
/**
 * EGroupware - Mail - worker class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2013 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Mail worker class
 *  -provides backend functionality for all classes in Mail
 *  -provides classes that may be used by other apps too
 */
class mail_bo
{
	/**
	 * the current selected user profile
	 * @var int
	 */
	var $profileID = 0;

	/**
	 * the current display char set
	 * @var string
	 */
	static $displayCharset;

	/**
	 * Instance of bopreference
	 *
	 * @var bopreferences object
	 */
	var $bopreferences;

	/**
	 * Active preferences
	 *
	 * @var array
	 */
	var $mailPreferences;

	/**
	 * active html Options
	 *
	 * @var array
	 */
	var $htmlOptions;

	/**
	 * Active incomming (IMAP) Server Object
	 *
	 * @var object
	 */
	var $icServer;

	/**
	 * Active outgoing (smtp) Server Object
	 *
	 * @var object
	 */
	var $ogServer;

	/**
	 * errorMessage
	 *
	 * @var string $errorMessage
	 */
	var $errorMessage;

	/**
	 * switch to enable debug; sometimes debuging is quite handy, to see things. check with the error log to see results
	 * @var boolean
	 */
	static $debug = false; //true;

	/**
	 * static used to hold the mail Config values
	 * @array
	 */
	static $mailConfig;

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
				'make_tag_strict' => 3, // 3 is a new own config value, to indicate that transformation is to be performed, but don't transform font as size transformation of numeric sizes to keywords alters the intended result too much
				'keep_bad'=>2, //remove tags but keep element content (4 and 6 keep element content only if text (pcdata) is valid in parent element as per specs, this may lead to textloss if balance is switched on)
				'balance'=>1,//turn off tag-balancing (config['balance']=>0). That will not introduce any security risk; only standards-compliant tag nesting check/filtering will be turned off (basic tag-balance will remain; i.e., there won't be any unclosed tag, etc., after filtering)
				'direct_list_nest' => 1,
				'allow_for_inline' => array('table','div','li','p'),//block elements allowed for nesting when only inline is allowed; Example span does not allow block elements as table; table is the only element tested so far
				'tidy'=>1,
				'elements' => "* -script",
				'deny_attribute' => 'on*',
				'schemes'=>'href: file, ftp, http, https, mailto; src: cid, data, file, ftp, http, https; *:file, http, https, cid, src',
				'hook_tag' =>"hl_email_tag_transform",
			);

	/**
	 * static used define abbrevations for common access rights
	 *
	 * @array
	 */
	static $aclShortCuts = array('' => array('label'=>'none','title'=>'The user has no rights whatsoever.'),
						'lrs'		=> array('label'=>'readable','title'=>'Allows a user to read the contents of the mailbox.'),
						'lprs'		=> array('label'=>'post','title'=>'Allows a user to read the mailbox and post to it through the delivery system by sending mail to the submission address of the mailbox.'),
						'ilprs'		=> array('label'=>'append','title'=>'Allows a user to read the mailbox and append messages to it, either via IMAP or through the delivery system.'),
						'cdilprsw'	=> array('label'=>'write','title'=>'Allows a user to read the maibox, post to it, append messages to it, and delete messages or the mailbox itself. The only right not given is the right to change the ACL of the mailbox.'),
						'acdilprsw'	=> array('label'=>'all','title'=>'The user has all possible rights on the mailbox. This is usually granted to users only on the mailboxes they own.'),
						'custom'	=> array('label'=>'custom','title'=>'User defined combination of rights for the ACL'),
			);

	/**
	 * Folders that get automatic created AND get translated to the users language
	 * their creation is also controlled by users mailpreferences. if set to none / dont use folder
	 * the folder will not be automatically created. This is controlled in mail_bo->getFolderObjects
	 * so changing names here, must include a change of keywords there as well. Since these
	 * foldernames are subject to translation, keep that in mind too, if you change names here.
	 * ActiveSync:
	 *  Outbox is needed by Nokia Clients to be able to send Mails
	 * @var array
	 */
	static $autoFolders = array('Drafts', 'Templates', 'Sent', 'Trash', 'Junk', 'Outbox');

	/**
	 * Array to cache the specialUseFolders, if existing
	 * @var array
	 */
	static $specialUseFolders;

	/**
	 * var to hold IDNA2 object
	 * @var class object
	 */
	static $idna2;

	/**
	 * Hold instances by profileID for getInstance() singleton
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Singleton for mail_bo
	 *
	 * @param boolean $_restoreSession=true
	 * @param int $_profileID=0
	 * @param boolean $_validate=true - flag wether the profileid should be validated or not, if validation is true, you may receive a profile
	 *                                  not matching the input profileID, if we can not find a profile matching the given ID
	 * @return object instance of mail_bo
	 */
	public static function getInstance($_restoreSession=true, $_profileID=0, $_validate=true)
	{
		//error_log(__METHOD__.__LINE__.' RestoreSession:'.$_restoreSession.' ProfileId:'.$_profileID.' called from:'.function_backtrace());
		if ($_profileID == 0)
		{
			if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']) && !empty($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
			{
				$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
			}
			else
			{
				$profileID = emailadmin_bo::getUserDefaultProfileID();
			}
			if ($profileID!=$_profileID) $_restoreSession==false;
			$_profileID=$profileID;
			if (self::$debug) error_log(__METHOD__.__LINE__.' called with profileID==0 using '.$profileID.' instead->'.function_backtrace());
		}
		if ($_profileID != 0 && $_validate)
		{
			$profileID = self::validateProfileID($_restoreSession, $_profileID);
			if ($profileID != $_profileID)
			{
				if (self::$debug)
				{
					error_log(__METHOD__.__LINE__.' Validation of profile with ID:'.$_profileID.' failed. Using '.$profileID.' instead.');
					error_log(__METHOD__.__LINE__.' # Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid']);
				}
				$_profileID = $profileID;
				$GLOBALS['egw']->preferences->add('mail','ActiveProfileID',$_profileID,'user');
				// save prefs
				$GLOBALS['egw']->preferences->save_repository(true);
			}
			egw_cache::setSession('mail','activeProfileID',$_profileID);
		}
		//error_log(__METHOD__.__LINE__.' RestoreSession:'.$_restoreSession.' ProfileId:'.$_profileID.' called from:'.function_backtrace());
		if (!isset(self::$instances[$_profileID]) || $_restoreSession===false)
		{
			self::$instances[$_profileID] = new mail_bo('utf-8',$_restoreSession,$_profileID);
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
				self::$instances[$_profileID] = new mail_bo('utf-8',false,$_profileID);
			}
		}
		self::$instances[$_profileID]->profileID = $_profileID;
		if (!isset(self::$instances[$_profileID]->idna2)) self::$instances[$_profileID]->idna2 = new egw_idna;
		//if ($_profileID==0); error_log(__METHOD__.__LINE__.' RestoreSession:'.$_restoreSession.' ProfileId:'.$_profileID);
		if (is_null(self::$mailConfig)) self::$mailConfig = config::read('mail');
		return self::$instances[$_profileID];
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
		$mail = mail_bo::getInstance($_restoreSession, $_profileID, $validate=false); // we need an instance of mail_bo
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
	 * Private constructor, use mail_bo::getInstance() instead
	 *
	 * @param string $_displayCharset='utf-8'
	 * @param boolean $_restoreSession=true
	 * @param int $_profileID=0
	 */
	private function __construct($_displayCharset='utf-8',$_restoreSession=true, $_profileID=0)
	{
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

		$this->accountid	= $GLOBALS['egw_info']['user']['account_id'];

		$this->bopreferences	= CreateObject('mail.mail_bopreferences',$_restoreSession);

		$this->mailPreferences	= $this->bopreferences->getPreferences(true,$this->profileID);
		//error_log(__METHOD__.__LINE__." ProfileID ".$this->profileID.' called from:'.function_backtrace());
		if ($this->mailPreferences) {
			$this->icServer = $this->mailPreferences->getIncomingServer($this->profileID);
			if ($this->profileID != 0) $this->mailPreferences->setIncomingServer($this->icServer,0);
			$this->ogServer = $this->mailPreferences->getOutgoingServer($this->profileID);
			if ($this->profileID != 0) $this->mailPreferences->setOutgoingServer($this->ogServer,0);
			$this->htmlOptions  = $this->mailPreferences->preferences['htmlOptions'];
			if (isset($this->icServer->ImapServerId) && !empty($this->icServer->ImapServerId))
			{
				$_profileID = $this->profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $this->icServer->ImapServerId;
			}
		}

		if (is_null(self::$mailConfig)) self::$mailConfig = config::read('mail');
		if (!isset(self::$idna2)) self::$idna2 = new egw_idna;
	}

	/**
	 * forceEAProfileLoad
	 * used to force the load of a specific emailadmin profile; we assume administrative use only (as of now)
	 * @param int $_profile_id must be a value lower than 0 (emailadmin profile)
	 * @return object instance of mail_bo (by reference)
	 */
	public static function &forceEAProfileLoad($_profile_id)
	{
		$mail = mail_bo::getInstance(false, $_profile_id,false);
		//_debug_array( $_profile_id);
		$mail->mailPreferences = $mail->bopreferences->getPreferences(false,$_profile_id,'mail',$_profile_id);
		$mail->icServer = $mail->mailPreferences->getIncomingServer($_profile_id);
		$mail->ogServer = $mail->mailPreferences->getOutgoingServer($_profile_id);
		return $mail;
	}

	/**
	 * trigger the force of the reload of the SessionData by resetting the session to an empty array
	 */
	public static function forcePrefReload()
	{
		// unset the mail_preferences session object, to force the reload/rebuild
		$GLOBALS['egw']->session->appsession('mail_preferences','mail',serialize(array()));
		$GLOBALS['egw']->session->appsession('session_data','emailadmin',serialize(array()));
	}

	/**
	 * restore the SessionData
	 */
	function restoreSessionData()
	{
		$this->sessionData = $GLOBALS['egw']->session->appsession('session_data','mail');
		$this->sessionData['folderStatus'] = egw_cache::getCache(egw_cache::INSTANCE,'email','folderStatus'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
	}

	/**
	 * saveSessionData saves session data
	 */
	function saveSessionData()
	{
		if (isset($this->sessionData['folderStatus']) && is_array($this->sessionData['folderStatus']))
		{
			egw_cache::setCache(egw_cache::INSTANCE,'email','folderStatus'.trim($GLOBALS['egw_info']['user']['account_id']),$this->sessionData['folderStatus'], $expiration=60*60*1);
			unset($this->sessionData['folderStatus']);
		}
		$GLOBALS['egw']->session->appsession('session_data','mail',$this->sessionData);
	}

	/**
	 * resets the various cache objects where connection error Objects may be cached
	 *
	 * @param int $_ImapServerId the profileID to look for
	 */
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
			$waitOnFailure = array();
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
			$waitOnFailure = egw_cache::getCache(egw_cache::INSTANCE,'email','ActiveSyncWaitOnFailure'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*2);
			if (isset($waitOnFailure[$_ImapServerId]))
			{
				unset($waitOnFailure[$_ImapServerId]);
			}
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($account_id),$buff,$expiration=60*15);
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($account_id),$isConError,$expiration=60*15);
		egw_cache::setCache(egw_cache::INSTANCE,'email','ActiveSyncWaitOnFailure'.trim($GLOBALS['egw_info']['user']['account_id']),$waitOnFailure,$expiration=60*60*2);
	}

	/**
	 * resets the various cache objects where Folder Objects may be cached
	 *
	 * @param int $_ImapServerId the profileID to look for
	 */
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
			$lastFolderUsedForMove = egw_cache::getCache(egw_cache::INSTANCE,'email','lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
			if (isset($lastFolderUsedForMove[$_ImapServerId]))
			{
				unset($lastFolderUsedForMove[$_ImapServerId]);
			}
			$folderBasicInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
			if (isset($folderBasicInfo[$_ImapServerId]))
			{
				unset($folderBasicInfo[$_ImapServerId]);
			}
			$_specialUseFolders = egw_cache::getCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*12);
			if (isset($_specialUseFolders[$_ImapServerId]))
			{
				unset($_specialUseFolders[$_ImapServerId]);
				self::$specialUseFolders=null;
			}
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','folderObjects'.trim($GLOBALS['egw_info']['user']['account_id']),$folders2return, $expiration=60*60*1);
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderInfo,$expiration=60*5);
		egw_cache::setCache(egw_cache::INSTANCE,'email','lastFolderUsedForMove'.trim($GLOBALS['egw_info']['user']['account_id']),$lastFolderUsedForMove,$expiration=60*60*1);
		egw_cache::setCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderBasicInfo,$expiration=60*60*1);
		egw_cache::setCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$_specialUseFolders,$expiration=60*60*12);
	}

	/**
	 * checks if the imap server supports a given capability
	 *
	 * @param string $_capability the name of the capability to check for
	 * @return bool
	 */
	function hasCapability($_capability)
	{
		$rv = $this->icServer->hasCapability(strtoupper($_capability));
		//error_log(__METHOD__.__LINE__." $_capability:".array2string($rv));
		return $rv;
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

	/**
	 * reopens a connection for the active Server ($this->icServer), and selects the folder given
	 *
	 * @param string $_foldername, folder to open/select
	 * @return void
	 */
	function reopen($_foldername)
	{
		// TODO: trying to reduce traffic to the IMAP Server here, introduces problems with fetching the bodies of
		// eMails when not in "current-Folder" (folder that is selected by UI)
		static $folderOpened;
		//if (empty($folderOpened) || $folderOpened!=$_foldername)
		//{
			//error_log( "------------------------reopen- $_foldername <br>");
			//error_log(__METHOD__.__LINE__.' Connected with icServer for Profile:'.$this->profileID.'?'.print_r($this->icServer->_connected,true));
			if (!($this->icServer->_connected == 1)) {
				$tretval = $this->openConnection($this->profileID,false);
			}
			if ($this->icServer->_connected == 1 && $this->folderIsSelectable($_foldername)) {
				$tretval = $this->icServer->selectMailbox($_foldername);
			}
			$folderOpened = $_foldername;
		//}
	}


	/**
	 * openConnection
	 *
	 * @param int $_icServerID
	 * @param boolean $_adminConnection
	 * @return boolean true/false or PEAR_Error Object ((if available) on Failure)
	 */
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
			if (!empty($this->icServer->currentMailbox)) $tretval = $this->icServer->selectMailbox($this->icServer->currentMailbox);
			if ( PEAR::isError($tretval) ) $isError[$_icServerID] = $tretval->message;
			//error_log(__METHOD__." using existing Connection ProfileID:".$_icServerID.' Status:'.print_r($this->icServer->_connected,true));
		} else {
			//error_log(__METHOD__.__LINE__."->open connection for Server with profileID:".$_icServerID.function_backtrace());
			$timeout = mail_bo::getTimeOut();
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
			if (!PEAR::isError($tretval) && isset($this->sessionData['mailbox']) && !empty($this->sessionData['mailbox'])) $smretval = $this->icServer->selectMailbox($this->sessionData['mailbox']);//may fail silently
		}
		if ( PEAR::isError($tretval) ) egw_cache::setCache(egw_cache::INSTANCE,'email','icServerIMAP_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$isError,$expiration=60*15);
		//error_log(print_r($this->icServer->_connected,true));
		//make sure we are working with the correct hierarchyDelimiter on the current connection, calling getHierarchyDelimiter with false to reset the cache
		$hD = $this->getHierarchyDelimiter(false);
		self::$specialUseFolders = $this->getSpecialUseFolders();
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
		$timeout = $GLOBALS['egw_info']['user']['preferences']['mail']['connectionTimeout'];
		if (empty($timeout)) $timeout = ($_use=='SIEVE'?10:20); // this is the default value
		return $timeout;
	}

	/**
	 * _getNameSpaces, fetch the namespace from icServer
	 * @return array $nameSpace array(peronal=>array,others=>array, shared=>array)
	 */
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
					$foldersNameSpace[$type]['prefix'] = ($this->folderExists('Mail')?'Mail':(!empty($singleNameSpace[0]['name'])?$singleNameSpace[0]['name']:''));
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
		//error_log(__METHOD__.__LINE__.array2string($foldersNameSpace));
		return $foldersNameSpace;
	}

	/**
	 * getFolderPrefixFromNamespace, wrapper to extract the folder prefix from folder compared to given namespace array
	 * @var array $nameSpace
	 * @var string $_folderName
	 * @return string the prefix (may be an empty string)
	 */
	function getFolderPrefixFromNamespace($nameSpace, $folderName)
	{
		foreach($nameSpace as $type => $singleNameSpace)
		{
			//if (substr($singleNameSpace['prefix'],0,strlen($folderName))==$folderName) return $singleNameSpace['prefix'];
			if (substr($folderName,0,strlen($singleNameSpace['prefix']))==$singleNameSpace['prefix']) return $singleNameSpace['prefix'];
		}
		return "";
	}

	/**
	 * getHierarchyDelimiter
	 * @var boolean $_useCache
	 * @return string the hierarchyDelimiter
	 */
	function getHierarchyDelimiter($_useCache=true)
	{
		static $HierarchyDelimiter;
		if (is_null($HierarchyDelimiter)) $HierarchyDelimiter =& egw_cache::getSession('mail','HierarchyDelimiter');
		if ($_useCache===false) unset($HierarchyDelimiter[$this->icServer->ImapServerId]);
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

	/**
	 * getSpecialUseFolders
	 * @ToDo: could as well be static, when icServer is passed
	 * @return mixed null/array
	 */
	function getSpecialUseFolders()
	{
		//error_log(__METHOD__.__LINE__.':'.$this->icServer->ImapServerId.' Connected:'.$this->icServer->_connected);
		static $_specialUseFolders;
		if (is_null($_specialUseFolders)||empty($_specialUseFolders)) $_specialUseFolders = egw_cache::getCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*24*5);
		if (isset($_specialUseFolders[$this->icServer->ImapServerId]) &&!empty($_specialUseFolders[$this->icServer->ImapServerId]))
		{
			if(($this->icServer instanceof defaultimap))
			{
				//error_log(__METHOD__.__LINE__.array2string($specialUseFolders[$this->icServer->ImapServerId]));
				// array('Drafts', 'Templates', 'Sent', 'Trash', 'Junk', 'Outbox');
				if (empty($this->icServer->trashfolder) && ($f = array_search('Trash',(array)$_specialUseFolders[$this->icServer->ImapServerId]))) $this->icServer->trashfolder = $f;
				if (empty($this->icServer->draftfolder) && ($f = array_search('Drafts',(array)$_specialUseFolders[$this->icServer->ImapServerId]))) $this->icServer->draftfolder = $f;
				if (empty($this->icServer->sentfolder) && ($f = array_search('Sent',(array)$_specialUseFolders[$this->icServer->ImapServerId]))) $this->icServer->sentfolder = $f;
				if (empty($this->icServer->templatefolder) && ($f = array_search('Templates',(array)$_specialUseFolders[$this->icServer->ImapServerId]))) $this->icServer->templatefolder = $f;
			}
			//error_log(__METHOD__.__LINE__.array2string($_specialUseFolders[$this->icServer->ImapServerId]));
			self::$specialUseFolders = $_specialUseFolders[$this->icServer->ImapServerId]; // make sure this one is set on function call
			return $_specialUseFolders[$this->icServer->ImapServerId];
		}
		if(($this->icServer instanceof defaultimap) && $this->icServer->_connected)
		{
			//error_log(__METHOD__.__LINE__);
			if(($this->hasCapability('SPECIAL-USE')))
			{
				//error_log(__METHOD__.__LINE__);
				$ret = $this->icServer->getSpecialUseFolders();
				if (PEAR::isError($ret))
				{
					$_specialUseFolders[$this->icServer->ImapServerId]=array();
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
								if (in_array('\\'.$n,$f['ATTRIBUTES'])) $_specialUseFolders[$this->icServer->ImapServerId][$f['MAILBOX']] = $n;
							}
						}
					}
				}
				egw_cache::setCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$_specialUseFolders, $expiration=60*60*24*5);
			}
			//error_log(__METHOD__.__LINE__.array2string($_specialUseFolders[$this->icServer->ImapServerId]));
			if (empty($this->icServer->trashfolder) && ($f = array_search('Trash',(array)$_specialUseFolders[$this->icServer->ImapServerId]))) $this->icServer->trashfolder = $f;
			if (empty($this->icServer->draftfolder) && ($f = array_search('Drafts',(array)$_specialUseFolders[$this->icServer->ImapServerId]))) $this->icServer->draftfolder = $f;
			if (empty($this->icServer->sentfolder) && ($f = array_search('Sent',(array)$_specialUseFolders[$this->icServer->ImapServerId]))) $this->icServer->sentfolder = $f;
			if (empty($this->icServer->templatefolder) && ($f = array_search('Templates',(array)$_specialUseFolders[$this->icServer->ImapServerId]))) $this->icServer->templatefolder = $f;
		}
		self::$specialUseFolders = $_specialUseFolders[$this->icServer->ImapServerId]; // make sure this one is set on function call
		//error_log(__METHOD__.__LINE__.array2string($_specialUseFolders[$this->icServer->ImapServerId]));
		return $_specialUseFolders[$this->icServer->ImapServerId];
	}

	/**
	 * get IMAP folder status regarding NoSelect
	 *
	 * returns true or false regarding the noselect attribute
	 *
	 * @param foldertoselect string the foldername
	 *
	 * @return boolean
	 */
	function folderIsSelectable($folderToSelect)
	{
		$retval = true;
		if($folderToSelect && ($folderStatus = $this->getFolderStatus($folderToSelect,false,true))) {
			if ($folderStatus instanceof PEAR_Error) return false;
			if (stripos(array2string($folderStatus['attributes']),'noselect')!==false)
			{
				$retval = false;
			}
		}
		return $retval;
	}

	/**
	 * get IMAP folder status, wrapper to store results within a single request
	 *
	 * returns an array information about the imap folder
	 *
	 * @param folderName string the foldername
	 * @param ignoreStatusCache bool ignore the cache used for counters
	 *
	 * @return array
	 */
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

	/**
	 * get IMAP folder status
	 *
	 * returns an array information about the imap folder
	 *
	 * @param _folderName string the foldername
	 * @param ignoreStatusCache bool ignore the cache used for counters
	 * @param basicInfoOnly bool retrieve only names and stuff returned by getMailboxes
	 *
	 * @return array
	 */
	function getFolderStatus($_folderName,$ignoreStatusCache=false,$basicInfoOnly=false)
	{
		if (self::$debug) error_log(__METHOD__." called with:".$_folderName);
		if (!is_string($_folderName) || empty($_folderName)) // something is wrong. Do not proceed
		{
			return false;
		}
		static $folderInfoCache; // reduce traffic on single request
		static $folderBasicInfo;
		if (is_null($folderBasicInfo))
		{
			$folderBasicInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
			$folderInfoCache = $folderBasicInfo[$this->profileID];
		}
		if (isset($folderInfoCache[$_folderName]) && $ignoreStatusCache==false && $basicInfoOnly) return $folderInfoCache[$_folderName];
		$retValue = array();
		$retValue['subscribed'] = false;
		if(!$icServer = $this->mailPreferences->getIncomingServer($this->profileID)) {
			if (self::$debug) error_log(__METHOD__." no Server found for Folder:".$_folderName);
			return false;
		}

		// does the folder exist???
		if (is_null($folderInfoCache) || !isset($folderInfoCache[$_folderName])) $folderInfoCache[$_folderName] = $this->icServer->getMailboxes('', $_folderName, true);
		$folderInfo = $folderInfoCache[$_folderName];
		//error_log(__METHOD__.__LINE__.array2string($folderInfo).'->'.function_backtrace());
		if(($folderInfo instanceof PEAR_Error) || !is_array($folderInfo[0])) {
			if (self::$debug||$folderInfo instanceof PEAR_Error) error_log(__METHOD__." returned Info for folder $_folderName:".print_r($folderInfo->message,true));
			if ( ($folderInfo instanceof PEAR_Error) || PEAR::isError($r = $this->_getStatus($_folderName)) || $r == 0) return false;
			if (!is_array($folderInfo[0]))
			{
				// no folder info, but there is a status returned for the folder: something is wrong, try to cope with it
				$folderInfo = array(0 => array('HIERACHY_DELIMITER'=>$this->getHierarchyDelimiter(),
					'ATTRIBUTES' => ''));
			}
		}
		#if(!is_array($folderInfo[0])) {
		#	return false;
		#}

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
		if (!($folderInfo instanceof PEAR_Error)) $folderBasicInfo[$this->profileID][$_folderName]=$retValue;
		egw_cache::setCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderBasicInfo,$expiration=60*60*1);
		if ($basicInfoOnly || stripos(array2string($retValue['attributes']),'noselect')!==false)
		{
			return $retValue;
		}
		$subscribedFolders = $this->icServer->listsubscribedMailboxes('', $_folderName);
		if(is_array($subscribedFolders) && count($subscribedFolders) == 1) {
			$retValue['subscribed'] = true;
		}

		if ( PEAR::isError($folderStatus = $this->_getStatus($_folderName,$ignoreStatusCache)) ) {
			if (self::$debug) error_log(__METHOD__." returned folderStatus for Folder $_folderName:".print_r($folderStatus->message,true));
		} else {
			$nameSpace = $this->_getNameSpaces();
			if (isset($nameSpace['personal'])) unset($nameSpace['personal']);
			$prefix = $this->getFolderPrefixFromNamespace($nameSpace, $_folderName);
			$retValue['messages']		= $folderStatus['MESSAGES'];
			$retValue['recent']		= $folderStatus['RECENT'];
			$retValue['uidnext']		= $folderStatus['UIDNEXT'];
			$retValue['uidvalidity']	= $folderStatus['UIDVALIDITY'];
			$retValue['unseen']		= $folderStatus['UNSEEN'];
			if (//$retValue['unseen']==0 &&
				(isset($this->mailPreferences->preferences['trustServersUnseenInfo']) && // some servers dont serve the UNSEEN information
				$this->mailPreferences->preferences['trustServersUnseenInfo']==false) ||
				(isset($this->mailPreferences->preferences['trustServersUnseenInfo']) &&
				$this->mailPreferences->preferences['trustServersUnseenInfo']==2 &&
				$prefix != '' && stripos($_folderName,$prefix) !== false)
			)
			{
				//error_log(__METHOD__." returned folderStatus for Folder $_folderName:".print_r($prefix,true).' TS:'.$this->mailPreferences->preferences['trustServersUnseenInfo']);
				// we filter for the combined status of unseen and undeleted, as this is what we show in list
				$sortResult = $this->getSortedList($_folderName, $_sort=0, $_reverse=1, $_filter=array('status'=>array('UNSEEN','UNDELETED')),$byUid=true,false);
				$retValue['unseen'] = count($sortResult);
			}
		}

		return $retValue;
	}

	/**
	 * getHeaders
	 *
	 * this function is a wrapper function for getSortedList and populates the resultList thereof with headerdata
	 *
	 * @param string $_folderName,
	 * @param int $_startMessage,
	 * @param int $_numberOfMessages, number of messages to return
	 * @param array $_sort, sort by criteria
	 * @param boolean $_reverse, reverse sorting of the result array (may be switched, as it is passed to getSortedList by reference)
	 * @param array $_filter, filter to apply to getSortedList
	 * @param mixed $_thisUIDOnly=null, if given fetch the headers of this uid only (either one, or array of uids)
	 * @param boolean $_cacheResult=true try touse the cache of getSortedList
	 * @return array result as array(header=>array,total=>int,first=>int,last=>int)
	 */
	function getHeaders($_folderName, $_startMessage, $_numberOfMessages, $_sort, $_reverse, $_filter, $_thisUIDOnly=null, $_cacheResult=true)
	{
		//self::$debug=true;
		if (self::$debug) error_log(__METHOD__.__LINE__.function_backtrace());
		if (self::$debug) error_log(__METHOD__.__LINE__."$_folderName,$_startMessage, $_numberOfMessages, $_sort, $_reverse, ".array2string($_filter).", $_thisUIDOnly");
		$reverse = (bool)$_reverse;
		// get the list of messages to fetch
		if (self::$debug) $starttime = microtime (true);
		$this->reopen($_folderName);
		if (self::$debug)
		{
			$endtime = microtime(true) - $starttime;
			error_log(__METHOD__. " time used for reopen: ".$endtime.' for Folder:'.$_folderName);
		}
		//$currentFolder = $this->icServer->getCurrentMailbox();
		//if ($currentFolder != $_folderName); $this->icServer->selectMailbox($_folderName);
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
				if  ($_startMessage<=$total)
				{
					$startMessage = $_startMessage-1;
				}
				else
				{
					//error_log(__METHOD__.__LINE__.' Start:'.$_startMessage.' NumberOfMessages:'.$_numberOfMessages.' Total:'.$total);
					if ($_startMessage+$_numberOfMessages>$total)
					{
						$numberOfMessages = $total%$_numberOfMessages;
						//$numberOfMessages = abs($_startMessage-$total-1);
						if ($numberOfMessages>0 && $numberOfMessages<=$_numberOfMessages) $_numberOfMessages = $numberOfMessages;
						//error_log(__METHOD__.__LINE__.' Start:'.$_startMessage.' NumberOfMessages:'.$_numberOfMessages.' Total:'.$total);
					}
					$startMessage=($total-$_numberOfMessages)-1;
					//$retValue['info']['first'] = $startMessage;
					//$retValue['info']['last'] = $total;

				}
				if ($startMessage+$_numberOfMessages>$total)
				{
					$_numberOfMessages = $_numberOfMessages-($total-($startMessage+$_numberOfMessages));
					//$retValue['info']['first'] = $startMessage;
					//$retValue['info']['last'] = $total;
				}
				if($startMessage > 0) {
					if (self::$debug) error_log(__METHOD__.__LINE__.' StartMessage:'.(-($_numberOfMessages+$startMessage)).', '.-$startMessage.' Number of Messages:'.count($sortResult));
					$sortResult = array_slice($sortResult, -($_numberOfMessages+$startMessage), -$startMessage);
				} else {
					if (self::$debug) error_log(__METHOD__.__LINE__.' StartMessage:'.(-($_numberOfMessages+($_startMessage-1))).', AllTheRest, Number of Messages:'.count($sortResult));
					$sortResult = array_slice($sortResult, -($_numberOfMessages+($_startMessage-1)));
				}
				$sortResult = array_reverse($sortResult);
			} else {
				if (self::$debug) error_log(__METHOD__.__LINE__.' StartMessage:'.($_startMessage-1).', '.$_numberOfMessages.' Number of Messages:'.count($sortResult));
				$sortResult = array_slice($sortResult, $_startMessage-1, $_numberOfMessages);
			}
			if (self::$debug) error_log(__METHOD__.__LINE__.array2string($sortResult));
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
				$retValue['header'][$sortOrder[$uid]]['date']		= self::_strtotime(($headerObject['DATE']&&!($headerObject['DATE']=='NIL')?$headerObject['DATE']:$headerObject['INTERNALDATE']),'ts',true);
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
						$retValue['header'][$sortOrder[$uid]]['sender_address'] = self::decode_header($headerObject['FROM'][0]['EMAIL'],true);
					} else {
						$retValue['header'][$sortOrder[$uid]]['sender_address'] = self::decode_header($headerObject['FROM'][0]['MAILBOX_NAME'],true);
					}
					if($headerObject['FROM'][0]['PERSONAL_NAME'] != 'NIL') {
						$retValue['header'][$sortOrder[$uid]]['sender_name'] = self::decode_header($headerObject['FROM'][0]['PERSONAL_NAME']);
					}

				}

				if(is_array($headerObject['TO']) && is_array($headerObject['TO'][0])) {
					if($headerObject['TO'][0]['HOST_NAME'] != 'NIL') {
						$retValue['header'][$sortOrder[$uid]]['to_address'] = self::decode_header($headerObject['TO'][0]['EMAIL'],true);
					} else {
						$retValue['header'][$sortOrder[$uid]]['to_address'] = self::decode_header($headerObject['TO'][0]['MAILBOX_NAME'],true);
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
								$retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki]['address'] = self::decode_header($add['EMAIL'],true);
							}
							else
							{
								$retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki]['address'] = self::decode_header($add['MAILBOX_NAME'],true);
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
				$countMessages = $total;
				if (isset($_filter['range'])) $countMessages = $this->sessionData['folderStatus'][$this->profileID][$_folderName]['messages'];
				ksort($retValue['header']);
				$retValue['info']['total']	= $total;
				//if ($_startMessage>$total) $_startMessage = $total-($count-1);
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
	 * @return mixed bool/array false or array of ids
	 */
	function getSortedList($_folderName, $_sort, &$_reverse, $_filter, &$resultByUid=true, $setSession=true)
	{
		//ToDo: FilterSpecific Cache
		if(PEAR::isError($folderStatus = $this->icServer->examineMailbox($_folderName))) {
			//if (stripos($folderStatus->message,'not connected') !== false); error_log(__METHOD__.__LINE__.$folderStatus->message);
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

	/**
	 * convert the sort value from the gui(integer) into a string
	 *
	 * @param mixed _sort the integer sort order / or a valid and handeled SORTSTRING (right now: UID/ARRIVAL/INTERNALDATE (->ARRIVAL))
	 * @param bool _reverse wether to add REVERSE to the Sort String or not
	 * @return the ascii sort string
	 */
	function _getSortString($_sort, $_reverse=false)
	{
		$_reverse=false;
		if (is_numeric($_sort))
		{
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
		}
		else
		{
			switch($_sort) {
				case 'UID': // should be equivalent to INTERNALDATE, which is ARRIVAL, which should be highest (latest) uid should be newest date
				case 'ARRIVAL':
				case 'INTERNALDATE':
					$retValue = 'ARRIVAL';
					break;
				default:
					$retValue = 'DATE';
					break;
			}
		}
		//error_log(__METHOD__.__LINE__.' '.($_reverse?'REVERSE ':'').$_sort.'->'.$retValue);
		return ($_reverse?'REVERSE ':'').$retValue;
	}

	/**
	 * this function creates an IMAP filter from the criterias given
	 *
	 * @param string $_folder used to determine the search to TO or FROM on QUICK Search wether it is a send-folder or not
	 * @param array $_criterias contains the search/filter criteria
	 * @return string the IMAP filter
	 */
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
	 * decode header (or envelope information)
	 * if array given, note that only values will be converted
	 * @param  mixed $_string input to be converted, if array call decode_header recursively on each value
	 * @param  mixed/boolean $_tryIDNConversion (true/false AND FORCE): try IDN Conversion on domainparts of emailADRESSES
	 * @return mixed - based on the input type
	 */
	static function decode_header($_string, $_tryIDNConversion=false)
	{
		if (is_array($_string))
		{
			foreach($_string as $k=>$v)
			{
				$_string[$k] = self::decode_header($v, $_tryIDNConversion);
			}
			return $_string;
		}
		else
		{
			$_string = translation::decodeMailHeader($_string,self::$displayCharset);
			if ($_tryIDNConversion===true && stripos($_string,'@')!==false)
			{
				$rfcAddr = imap_rfc822_parse_adrlist($_string,'');
				if (!isset(self::$idna2)) self::$idna2 = new egw_idna;
				//$_string = str_replace($rfcAddr[0]->host,self::$idna2->decode($rfcAddr[0]->host),$_string);
				$_string = imap_rfc822_write_address($rfcAddr[0]->mailbox,self::$idna2->decode($rfcAddr[0]->host),$rfcAddr[0]->personal);
			}
			if ($_tryIDNConversion==='FORCE')
			{
				//error_log(__METHOD__.__LINE__.'->'.$_string.'='.self::$idna2->decode($_string));
				$_string = self::$idna2->decode($_string);
			}
			return $_string;
		}
	}

	/**
	 * decode subject
	 * if array given, note that only values will be converted
	 * @param  mixed $_string input to be converted, if array call decode_header recursively on each value
	 * @param  boolean $decode try decoding
	 * @return mixed - based on the input type
	 */
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
	 * convert a mailboxname from utf7-imap to displaycharset
	 *
	 * @param string _folderName the foldername
	 * @return string the converted string
	 */
	function encodeFolderName($_folderName)
	{
		return translation::convert($_folderName, 'UTF7-IMAP', self::$displayCharset);
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
			if(($ogServer instanceof emailadmin_smtp)) {
				$ogServer->addAccount($_hookValues);
			}
		}
	}

	/**
	 * hook to delete account
	 *
	 * this function is a wrapper function for emailadmin
	 *
	 * @param _hookValues contains the hook values as array
	 * @return nothing
	 */
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
			if(($ogServer instanceof emailadmin_smtp)) {
				$ogServer->deleteAccount($_hookValues);
			}
		}
	}

	/**
	 * hook to update account
	 *
	 * this function is a wrapper function for emailadmin
	 *
	 * @param _hookValues contains the hook values as array
	 * @return nothing
	 */
	function updateAccount($_hookValues)
	{
		if (is_object($this->mailPreferences)) $icServer = $this->mailPreferences->getIncomingServer(0);
		if(($icServer instanceof defaultimap)) {
			$icServer->updateAccount($_hookValues);
		}

		if (is_object($this->mailPreferences)) $ogServer = $this->mailPreferences->getOutgoingServer(0);
		if(($ogServer instanceof emailadmin_smtp)) {
			$ogServer->updateAccount($_hookValues);
		}
	}


}
