<?php
/**
 * EGroupware - Mail - worker class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013-2014 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Mail worker class
 *  -provides backend functionality for all classes in Mail
 *  -provides classes that may be used by other apps too
 *
 * @link https://github.com/horde/horde/blob/master/imp/lib/Contents.php
 */
class emailadmin_imapbase
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
	static $activeFolderCache;
	static $folderStatusCache;
	static $supportsORinQuery;

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
	 * Active mimeType
	 *
	 * @var string
	 */
	var $activeMimeType;

	/**
	 * Active incomming (IMAP) Server Object
	 *
	 * @var emailadmin_imap
	 */
	var $icServer;

	/**
	 * Active outgoing (smtp) Server Object
	 *
	 * @var emailadmin_smtp
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
	static $debugTimes = false; //true;

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
		// tidy eats away even some wanted whitespace, so we switch it off;
		// we used it for its compacting and beautifying capabilities, which resulted in better html for further processing
		'tidy'=>0,
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
	 * the folder will not be automatically created. This is controlled in emailadmin_imapbase->getFolderObjects
	 * so changing names here, must include a change of keywords there as well. Since these
	 * foldernames are subject to translation, keep that in mind too, if you change names here.
	 * lang('Drafts'), lang('Templates'), lang('Sent'), lang('Trash'), lang('Junk'), lang('Outbox')
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
	 * IDNA2 instance
	 *
	 * @var egw_idna
	 */
	static $idna2;

	/**
	 * Hold instances by profileID for getInstance() singleton
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Singleton for emailadmin_imapbase
	 *
	 * @param boolean $_restoreSession=true
	 * @param int $_profileID=0
	 * @param boolean $_validate=true - flag wether the profileid should be validated or not, if validation is true, you may receive a profile
	 *                                  not matching the input profileID, if we can not find a profile matching the given ID
	 * @param mixed boolean/object $_icServerObject - if object, return instance with object set as icServer
	 *												  immediately, if boolean === true use oldImapServer in constructor
	 * @param boolean $_reuseCache=null if null it is set to the value of $_restoreSession
	 * @return emailadmin_imapbase
	 */
	public static function getInstance($_restoreSession=true, &$_profileID=0, $_validate=true, $_oldImapServerObject=false, $_reuseCache=null)
	{
		//$_restoreSession=false;
		if (is_null($_reuseCache)) $_reuseCache = $_restoreSession;
		//error_log(__METHOD__.' ('.__LINE__.') '.' RestoreSession:'.$_restoreSession.' ProfileId:'.$_profileID.'/'.emailadmin_bo::getUserDefaultAccID().' for user:'.$GLOBALS['egw_info']['user']['account_lid'].' called from:'.function_backtrace());
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($_oldImapServerObject));
		if ($_oldImapServerObject instanceof emailadmin_imap)
		{
			if (!is_object(self::$instances[$_profileID]))
			{
				self::$instances[$_profileID] = new emailadmin_imapbase('utf-8',false,$_profileID,false,$_reuseCache);
			}
			self::$instances[$_profileID]->icServer = $_oldImapServerObject;
			self::$instances[$_profileID]->accountid= $_oldImapServerObject->ImapServerId;
			self::$instances[$_profileID]->profileID= $_oldImapServerObject->ImapServerId;
			self::$instances[$_profileID]->mailPreferences = $GLOBALS['egw_info']['user']['preferences']['mail'];
			self::$instances[$_profileID]->htmlOptions  = self::$instances[$_profileID]->mailPreferences['htmlOptions'];
			return self::$instances[$_profileID];
		}
		if ($_profileID == 0)
		{
			if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']) && !empty($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
			{
				$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
			}
			else
			{
				$profileID = emailadmin_bo::getUserDefaultAccID();
			}
			if ($profileID!=$_profileID) $_restoreSession==false;
			$_profileID=$profileID;
			if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' called with profileID==0 using '.$profileID.' instead->'.function_backtrace());
		}
		// no validation or restoreSession for old ImapServer Object, just fetch it and return it
		if ($_oldImapServerObject===true)
		{
			return new emailadmin_imapbase('utf-8',false,$_profileID,true,$_reuseCache);
		}
		if ($_profileID != 0 && $_validate)
		{
			$profileID = self::validateProfileID($_profileID);
			if ($profileID != $_profileID)
			{
				if (self::$debug)
				{
					error_log(__METHOD__.' ('.__LINE__.') '.' Validation of profile with ID:'.$_profileID.' failed. Using '.$profileID.' instead.');
					error_log(__METHOD__.' ('.__LINE__.') '.' # Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid']);
				}
				$_profileID = $profileID;
				//$GLOBALS['egw']->preferences->add('mail','ActiveProfileID',$_profileID,'user');
				// save prefs
				//$GLOBALS['egw']->preferences->save_repository(true);
			}
			//egw_cache::setSession('mail','activeProfileID',$_profileID);
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.' RestoreSession:'.$_restoreSession.' ProfileId:'.$_profileID.' called from:'.function_backtrace());
		if ($_profileID && (!isset(self::$instances[$_profileID]) || $_restoreSession===false))
		{
			self::$instances[$_profileID] = new emailadmin_imapbase('utf-8',$_restoreSession,$_profileID,false,$_reuseCache);
		}
		else
		{
			//refresh objects
			try
			{
				self::$instances[$_profileID]->icServer = emailadmin_account::read($_profileID)->imapServer();
				self::$instances[$_profileID]->ogServer = emailadmin_account::read($_profileID)->smtpServer();
				// TODO: merge mailprefs into userprefs, for easy treatment
				self::$instances[$_profileID]->mailPreferences = $GLOBALS['egw_info']['user']['preferences']['mail'];
				self::$instances[$_profileID]->htmlOptions  = self::$instances[$_profileID]->mailPreferences['htmlOptions'];
			} catch (egw_exception $e)
			{
				$newprofileID = emailadmin_bo::getUserDefaultAccID();
				// try loading the default profile for the user
				error_log(__METHOD__.' ('.__LINE__.') '." Loading the Profile for ProfileID ".$_profileID.' failed for icServer; '.$e->getMessage().' Trigger new instance for Default-Profile '.$newprofileID.'. called from:'.function_backtrace());
				if ($newprofileID)
				{
					self::$instances[$newprofileID] = new emailadmin_imapbase('utf-8',false,$newprofileID,false,$_reuseCache);
					$_profileID = $newprofileID;
				}
				else
				{
					throw $e;
				}
			}
			self::storeActiveProfileIDToPref(self::$instances[$_profileID]->icServer, $_profileID, $_validate );
		}
		self::$instances[$_profileID]->profileID = $_profileID;
		if (!isset(self::$instances[$_profileID]->idna2)) self::$instances[$_profileID]->idna2 = new egw_idna;
		//if ($_profileID==0); error_log(__METHOD__.' ('.__LINE__.') '.' RestoreSession:'.$_restoreSession.' ProfileId:'.$_profileID);
		if (is_null(self::$mailConfig)) self::$mailConfig = config::read('mail');
		return self::$instances[$_profileID];
	}

	/**
	 * store given ProfileID to Session and pref
	 *
	 * @param int $_profileID=0
	 * @param boolean $_testConnection=0
	 * @return mixed $_profileID or false on failed ConnectionTest
	 */
	public static function storeActiveProfileIDToPref($_icServerObject, $_profileID=0, $_testConnection=true)
	{
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']) && !empty($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
		{
			$oldProfileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		}
		else
		{
			$oldProfileID = emailadmin_bo::getUserDefaultAccID();
		}
		if ($_testConnection)
		{
			try
			{
				$mailbox = $_icServerObject->getCurrentMailbox();
			}
			catch (Exception $e)
			{
				if ($_profileID != emailadmin_bo::getUserDefaultAccID()) $_profileID = emailadmin_bo::getUserDefaultAccID();
				error_log(__METHOD__.__LINE__.' '.$e->getMessage());
				return false;
			}
		}
		if ($oldProfileID != $_profileID)
		{
			if ($oldProfileID && $_profileID==0) $_profileID = $oldProfileID;
			$GLOBALS['egw']->preferences->add('mail','ActiveProfileID',$_profileID,'user');
			// save prefs
			$GLOBALS['egw']->preferences->save_repository(true);
			$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $_profileID;
			egw_cache::setSession('mail','activeProfileID',$_profileID);
		}
		return $_profileID;
	}

	/**
	 * Validate given account acc_id to make sure account is valid for current user
	 *
	 * Validation checks:
	 * - non-empty imap-host
	 * - non-empty imap-username
	 *
	 * @param int $_acc_id=0
	 * @return int validated acc_id -> either acc_id given, or first valid one
	 */
	public static function validateProfileID($_acc_id=0)
	{
		if ($_acc_id)
		{
			try {
				$account = emailadmin_account::read($_acc_id);
				if ($account->is_imap())
				{
					return $_acc_id;
				}
				if (self::$debug) error_log(__METHOD__."($_acc_id) account NOT valid, no imap-host!");
			}
			catch (Exception $e) {
				unset($e);
				if (self::$debug) error_log(__METHOD__."($_acc_id) account NOT found!");
			}
		}
		// no account specified or specified account not found or not valid
		// --> search existing account for first valid one and return that
		foreach(emailadmin_account::search($only_current_user=true, 'acc_imap_host') as $acc_id => $imap_host)
		{
			if (!empty($imap_host) && ($account = emailadmin_account::read($acc_id)) && $account->is_imap())
			{
				if (self::$debug && $_acc_id) error_log(__METHOD__."($_acc_id) using $acc_id instead");
				return $acc_id;
			}
		}
		if (self::$debug) error_log(__METHOD__."($_acc_id) NO valid account found!");
		return 0;
	}


	/**
	 * Private constructor, use emailadmin_imapbase::getInstance() instead
	 *
	 * @param string $_displayCharset='utf-8'
	 * @param boolean $_restoreSession=true
	 * @param int $_profileID=0 if not nummeric, we assume we only want an empty class object
	 * @param boolean $_oldImapServerObject=false
	 * @param boolean $_reuseCache=null if null it is set to the value of $_restoreSession
	 */
	private function __construct($_displayCharset='utf-8',$_restoreSession=true, $_profileID=0, $_oldImapServerObject=false, $_reuseCache=null)
	{
		if (is_null($_reuseCache)) $_reuseCache = $_restoreSession;
		if (!empty($_displayCharset)) self::$displayCharset = $_displayCharset;
		// not nummeric, we assume we only want an empty class object
		if (!is_numeric($_profileID)) return true;
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
		}
		if (!$_reuseCache) $this->forcePrefReload($_profileID,!$_reuseCache);
		try
		{
			$this->profileID = self::validateProfileID($_profileID);
			$this->accountid	= $GLOBALS['egw_info']['user']['account_id'];

			//error_log(__METHOD__.' ('.__LINE__.') '." ProfileID ".$this->profileID.' called from:'.function_backtrace());
			$acc = emailadmin_account::read($this->profileID);
		}
		catch (Exception $e)
		{
			throw new egw_exception(__METHOD__." failed to instanciate emailadmin_imapbase for $_profileID / ".$this->profileID." with error:".$e->getMessage());;
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($acc->imapServer()));
		$this->icServer = ($_oldImapServerObject?$acc->oldImapServer():$acc->imapServer());
		$this->ogServer = $acc->smtpServer();
		// TODO: merge mailprefs into userprefs, for easy treatment
		$this->mailPreferences = $GLOBALS['egw_info']['user']['preferences']['mail'];
		$this->htmlOptions  = $this->mailPreferences['htmlOptions'];
		if (isset($this->icServer->ImapServerId) && !empty($this->icServer->ImapServerId))
		{
			$_profileID = $this->profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $this->icServer->ImapServerId;
		}

		if (is_null(self::$mailConfig)) self::$mailConfig = config::read('mail');
		if (!isset(self::$idna2)) self::$idna2 = new egw_idna;
	}

	/**
	 * forceEAProfileLoad
	 * used to force the load of a specific emailadmin profile; we assume administrative use only (as of now)
	 * @param int $_profile_id
	 * @return object instance of emailadmin_imapbase (by reference)
	 */
	public static function &forceEAProfileLoad($_profile_id)
	{
		self::unsetCachedObjects($_profile_id);
		$mail = emailadmin_imapbase::getInstance(false, $_profile_id,false);
		//_debug_array( $_profile_id);
		$mail->icServer = emailadmin_account::read($_profile_id)->imapServer();
		$mail->ogServer = emailadmin_account::read($_profile_id)->smtpServer();
		return $mail;
	}

	/**
	 * trigger the force of the reload of the SessionData by resetting the session to an empty array
	 * @param int $_profile_id
	 * @param boolean $_resetFolderObjects
	 */
	public static function forcePrefReload($_profile_id=null,$_resetFolderObjects=true)
	{
		// unset the mail_preferences session object, to force the reload/rebuild
		$GLOBALS['egw']->session->appsession('mail_preferences','mail',serialize(array()));
		$GLOBALS['egw']->session->appsession('session_data','emailadmin',serialize(array()));
		if ($_resetFolderObjects) emailadmin_imapbase::resetFolderObjectCache($_profile_id);
	}

	/**
	 * restore the SessionData
	 */
	function restoreSessionData()
	{
		$this->sessionData = array();//egw_cache::getCache(egw_cache::SESSION,'mail','session_data',$callback=null,$callback_params=array(),$expiration=60*60*1);
		self::$activeFolderCache = egw_cache::getCache(egw_cache::INSTANCE,'email','activeMailbox'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*10);
		if (!empty(self::$activeFolderCache[$this->profileID])) $this->sessionData['mailbox'] = self::$activeFolderCache[$this->profileID];
	}

	/**
	 * saveSessionData saves session data
	 */
	function saveSessionData()
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string(array_keys($this->sessionData)));
		if (!empty($this->sessionData['mailbox'])) self::$activeFolderCache[$this->profileID]=$this->sessionData['mailbox'];
		if (isset(self::$activeFolderCache) && is_array(self::$activeFolderCache))
		{
			egw_cache::setCache(egw_cache::INSTANCE,'email','activeMailbox'.trim($GLOBALS['egw_info']['user']['account_id']),self::$activeFolderCache, $expiration=60*60*10);
		}
	}

	/**
	 * unset certain CachedObjects for the given profile id, unsets the profile for default ID=0 as well
	 *
	 * 1) icServerIMAP_connectionError
	 * 2) icServerSIEVE_connectionError
	 * 3) INSTANCE OF MAIL_BO
	 * 4) HierarchyDelimiter
	 * 5) VacationNotice
	 *
	 * @param int $_profileID=null default profile of user as returned by getUserDefaultProfileID
	 * @return void
	 */
	static function unsetCachedObjects($_profileID=null)
	{
		if (is_null($_profileID)) $_profileID = emailadmin_account::get_default_acc_id();
		$account_id = $_profileID['account_id'];
		//error_log(__METHOD__.__LINE__.' called with ProfileID:'.array2string($_profileID).' from '.function_backtrace());
		if (!is_array($_profileID) && (is_numeric($_profileID) || !(stripos($_profileID,'tracker_')===false)))
		{
			self::resetConnectionErrorCache($_profileID);
			$rawHeadersCache = egw_cache::getCache(egw_cache::INSTANCE,'email','rawHeadersCache'.trim($account_id),$callback=null,$callback_params=array(),$expiration=60*60*1);
			if (isset($rawHeadersCache[$_profileID]))
			{
				unset($rawHeadersCache[$_profileID]);
				egw_cache::setCache(egw_cache::INSTANCE,'email','rawHeadersCache'.trim($account_id),$rawHeadersCache, $expiration=60*60*1);
			}
			$HierarchyDelimiterCache = egw_cache::getCache(egw_cache::INSTANCE,'email','HierarchyDelimiter'.trim($account_id),$callback=null,$callback_params=array(),$expiration=60*60*24*5);
			if (isset($HierarchyDelimiterCache[$_profileID]))
			{
				unset($HierarchyDelimiterCache[$_profileID]);
				egw_cache::setCache(egw_cache::INSTANCE,'email','HierarchyDelimiter'.trim($account_id),$HierarchyDelimiterCache, $expiration=60*60*24*5);
			}
			//reset folderObject cache, to trigger reload
			self::resetFolderObjectCache($_profileID);
			//reset counter of deleted messages per folder
			$eMailListContainsDeletedMessages = egw_cache::getCache(egw_cache::INSTANCE,'email','eMailListContainsDeletedMessages'.trim($account_id),$callback=null,$callback_params=array(),$expiration=60*60*1);
			if (isset($eMailListContainsDeletedMessages[$_profileID]))
			{
				unset($eMailListContainsDeletedMessages[$_profileID]);
				egw_cache::setCache(egw_cache::INSTANCE,'email','eMailListContainsDeletedMessages'.trim($account_id),$eMailListContainsDeletedMessages, $expiration=60*60*1);
			}
			$vacationCached = egw_cache::getCache(egw_cache::INSTANCE, 'email', 'vacationNotice'.trim($account_id),$callback=null,$callback_params=array(),$expiration=60*60*24*1);
			if (isset($vacationCached[$_profileID]))
			{
				unset($vacationCached[$_profileID]);
				egw_cache::setCache(egw_cache::INSTANCE,'email','vacationNotice'.trim($account_id),$vacationCached, $expiration=60*60*24*1);
			}

			if (isset(self::$instances[$_profileID])) unset(self::$instances[$_profileID]);
		}
		if (is_array($_profileID) && $_profileID['location'] == 'clear_cache')
		{
			// called via hook
			foreach($GLOBALS['egw']->accounts->search(array('type' => 'accounts','order' => 'account_lid')) as $account)
			{
				//error_log(__METHOD__.__LINE__.array2string($account));
				$account_id = $account['account_id'];
				$_profileID = null;
				self::resetConnectionErrorCache($_profileID,$account_id);
				self::resetFolderObjectCache($_profileID,$account_id);
				egw_cache::setCache(egw_cache::INSTANCE,'email','rawHeadersCache'.trim($account_id),array(), $expiration=60*60*1);
				egw_cache::setCache(egw_cache::INSTANCE,'email','HierarchyDelimiter'.trim($account_id),array(), $expiration=60*60*24*5);
				egw_cache::setCache(egw_cache::INSTANCE,'email','eMailListContainsDeletedMessages'.trim($account_id),array(), $expiration=60*60*1);
				egw_cache::setCache(egw_cache::INSTANCE,'email','vacationNotice'.trim($account_id),array(), $expiration=60*60*24*1);
			}
		}
	}

	/**
	 * resets the various cache objects where connection error Objects may be cached
	 *
	 * @param int $_ImapServerId the profileID to look for
	 * @param int $account_id the egw account to look for
	 */
	static function resetConnectionErrorCache($_ImapServerId=null,$account_id=null)
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.' for Profile:'.array2string($_ImapServerId) .' for user:'.trim($account_id));
		if (is_null($account_id)) $account_id = $GLOBALS['egw_info']['user']['account_id'];
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
			$isConError = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($account_id));
			if (isset($isConError[$_ImapServerId]))
			{
				unset($isConError[$_ImapServerId]);
			}
			$waitOnFailure = egw_cache::getCache(egw_cache::INSTANCE,'email','ActiveSyncWaitOnFailure'.trim($account_id),$callback=null,$callback_params=array(),$expiration=60*60*2);
			if (isset($waitOnFailure[$_ImapServerId]))
			{
				unset($waitOnFailure[$_ImapServerId]);
			}
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($account_id),$isConError,$expiration=60*15);
		egw_cache::setCache(egw_cache::INSTANCE,'email','ActiveSyncWaitOnFailure'.trim($account_id),$waitOnFailure,$expiration=60*60*2);
	}

	/**
	 * resets the various cache objects where Folder Objects may be cached
	 *
	 * @param int $_ImapServerId the profileID to look for
	 * @param int $account_id the egw account to look for
	 */
	static function resetFolderObjectCache($_ImapServerId=null,$account_id=null)
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.' called for Profile:'.$_ImapServerId.'->'.function_backtrace());
		if (is_null($account_id)) $account_id = $GLOBALS['egw_info']['user']['account_id'];
		if (is_null($_ImapServerId))
		{
			$folders2return = array();
			$folderInfo = array();
			$folderBasicInfo = array();
			$_specialUseFolders = array();
		}
		else
		{
			$folders2return = egw_cache::getCache(egw_cache::INSTANCE,'email','folderObjects'.trim($account_id),$callback=null,$callback_params=array(),$expiration=60*60*1);
			if (isset($folders2return[$_ImapServerId]))
			{
				unset($folders2return[$_ImapServerId]);
			}
			$folderInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($account_id),null,array(),$expiration=60*60*5);
			if (isset($folderInfo[$_ImapServerId]))
			{
				unset($folderInfo[$_ImapServerId]);
			}
			/*
			$lastFolderUsedForMove = egw_cache::getCache(egw_cache::INSTANCE,'email','lastFolderUsedForMove'.trim($account_id),null,array(),$expiration=60*60*1);
			if (isset($lastFolderUsedForMove[$_ImapServerId]))
			{
				unset($lastFolderUsedForMove[$_ImapServerId]);
			}
			*/
			$folderBasicInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($account_id),null,array(),$expiration=60*60*1);
			if (isset($folderBasicInfo[$_ImapServerId]))
			{
				unset($folderBasicInfo[$_ImapServerId]);
			}
			$_specialUseFolders = egw_cache::getCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($account_id),null,array(),$expiration=60*60*12);
			if (isset($_specialUseFolders[$_ImapServerId]))
			{
				unset($_specialUseFolders[$_ImapServerId]);
				self::$specialUseFolders=null;
			}
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','folderObjects'.trim($account_id),$folders2return, $expiration=60*60*1);
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($account_id),$folderInfo,$expiration=60*60*5);
		//egw_cache::setCache(egw_cache::INSTANCE,'email','lastFolderUsedForMove'.trim($account_id),$lastFolderUsedForMove,$expiration=60*60*1);
		egw_cache::setCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($account_id),$folderBasicInfo,$expiration=60*60*1);
		egw_cache::setCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($account_id),$_specialUseFolders,$expiration=60*60*12);
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
		//error_log(__METHOD__.' ('.__LINE__.') '." $_capability:".array2string($rv));
		return $rv;
	}

	/**
	 * getUserEMailAddresses - function to gather the emailadresses connected to the current mail-account
	 * @param string $_profileID the ID of the mailaccount to check for identities, if null current mail-account is used
	 * @return array - array(email=>realname)
	 */
	function getUserEMailAddresses($_profileID=null) {
		$acc = emailadmin_account::read((!empty($_profileID)?$_profileID:$this->profileID));
		//error_log(__METHOD__.' ('.__LINE__.') '.':'.array2string($acc));
		$identities = $acc->identities();

		$userEMailAdresses = array($acc['ident_email']=>$acc['ident_realname']);

		foreach($identities as $ik => $ident) {
			//error_log(__METHOD__.' ('.__LINE__.') '.':'.$ik.'->'.array2string($ident));
			$identity = emailadmin_account::read_identity($ik);
			if (!empty($identity['ident_email']) && !isset($userEMailAdresses[$identity['ident_email']])) $userEMailAdresses[$identity['ident_email']] = $identity['ident_realname'];
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($userEMailAdresses));
		return $userEMailAdresses;
	}

	/**
	 * getAllIdentities - function to gather the identities connected to the current user
	 * @param string/int $_accountToSearch; null; if set search accounts for user specified
	 * @return array - array(email=>realname)
	 */
	static function getAllIdentities($_accountToSearch=null) {
		$userEMailAdresses = array();
		foreach(emailadmin_account::search($only_current_user=($_accountToSearch?$_accountToSearch:true), $just_name=true) as $acc_id => $identity_name)
		{
			$acc = emailadmin_account::read($acc_id,($_accountToSearch?$_accountToSearch:null));
			$userEMailAdresses[$acc['ident_id']] = array('acc_id'=>$acc_id,'ident_id'=>$acc['ident_id'],'ident_email'=>$acc['ident_email'],'ident_org'=>$acc['ident_org'],'ident_realname'=>$acc['ident_realname'],'ident_signature'=>$acc['ident_signature'],'ident_name'=>$acc['ident_name']);
			$identities = $acc->identities($acc_id);

			foreach($identities as $ik => $ident) {
				//error_log(__METHOD__.' ('.__LINE__.') '.':'.$ik.'->'.array2string($ident));
				$identity = emailadmin_account::read_identity($ik);
				//error_log(__METHOD__.' ('.__LINE__.') '.':'.$ik.'->'.array2string($identity));
				if (!isset($userEMailAdresses[$identity['ident_id']])) $userEMailAdresses[$identity['ident_id']] = array('acc_id'=>$acc_id,'ident_id'=>$identity['ident_id'],'ident_email'=>$identity['ident_email'],'ident_org'=>$identity['ident_org'],'ident_realname'=>$identity['ident_realname'],'ident_signature'=>$identity['ident_signature'],'ident_name'=>$identity['ident_name']);
			}
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($userEMailAdresses));
		return $userEMailAdresses;
	}

	/**
	 * getAccountIdentities - function to gather the identities connected to the current mailaccount
	 * @param int  $acc_id to pass all conected identities back
	 * @return array - array(email=>realname)
	 */
	function getAccountIdentities($acc_id) {
		$userEMailAdresses = array();
		$acc = emailadmin_account::read($acc_id);
		$userEMailAdresses[$acc['ident_id']] = array('acc_id'=>$acc_id,'ident_id'=>$acc['ident_id'],'ident_email'=>$acc['ident_email'],'ident_org'=>$acc['ident_org'],'ident_realname'=>$acc['ident_realname'],'ident_signature'=>$acc['ident_signature'],'ident_name'=>$acc['ident_name']);
		$identities = $acc->identities();

		foreach($identities as $ik => $ident) {
			//error_log(__METHOD__.' ('.__LINE__.') '.':'.$ik.'->'.array2string($ident));
			$identity = emailadmin_account::read_identity($ik);
			//error_log(__METHOD__.' ('.__LINE__.') '.':'.$ik.'->'.array2string($identity));
			if (!isset($userEMailAdresses[$identity['ident_id']])) $userEMailAdresses[$identity['ident_id']] = array('ident_id'=>$identity['ident_id'],'ident_email'=>$identity['ident_email'],'ident_org'=>$identity['ident_org'],'ident_realname'=>$identity['ident_realname'],'ident_signature'=>$identity['ident_signature'],'ident_name'=>$identity['ident_name']);
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($userEMailAdresses));
		return $userEMailAdresses;
	}

	/**
	 * getDefaultIdentity - function to gather the default identitiy connected to the current mailaccount
	 * @return int - id of the identity
	 */
	function getDefaultIdentity() {
		// retrieve the signature accociated with the identity
		$id = $this->getIdentitiesWithAccounts($_accountData);
		$acc = emailadmin_account::read($this->profileID);
		$accountDataIT = ($_accountData[$this->profileID]?$acc->identities($this->profileID,false,'ident_id'):$acc->identities($_accountData[$id],false,'ident_id'));
		foreach($accountDataIT as $it => $accountData)
		{
			return $accountData;
		}
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
		$allAccountData = emailadmin_account::search($only_current_user=true, $just_name=false, $order_by=null);
		if ($allAccountData) {
			$rememberFirst=$selectedFound=null;
			foreach ($allAccountData as $tmpkey => $icServers)
			{
				if (is_null($rememberFirst)) $rememberFirst = $tmpkey;
				if ($tmpkey == $selectedID) $selectedFound=true;
				//error_log(__METHOD__.' ('.__LINE__.') '.' Key:'.$tmpkey.'->'.array2string($icServers->acc_imap_host));
				$host = $icServers->acc_imap_host;
				if (empty($host)) continue;
				$identities[$icServers->acc_id] = $icServers['ident_realname'].' '.$icServers['ident_org'].' <'.$icServers['ident_email'].'>';
				//error_log(__METHOD__.' ('.__LINE__.') '.' Key:'.$tmpkey.'->'.array2string($identities[$icServers->acc_id]));
			}
		}
		return ($selectedFound?$selectedID:$rememberFirst);
	}

	/**
	 * generateIdentityString
	 * construct the string representing an Identity passed by $identity
	 * @var array/object $identity, identity object that holds realname, organization, emailaddress and signatureid
	 * @var boolean $fullString full or false=NamePart only is returned
	 * @return string - constructed of identity object data as defined in mailConfig
	 */
	static function generateIdentityString($identity, $fullString=true)
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($identity));
		if (is_null(self::$mailConfig)) self::$mailConfig = config::read('mail');
		// not set? -> use default, means full display of all available data
		if (!isset(self::$mailConfig['how2displayIdentities'])) self::$mailConfig['how2displayIdentities']='';
		switch (self::$mailConfig['how2displayIdentities'])
		{
			case 'email';
				//$retData = str_replace('@',' ',$identity->emailAddress).($fullString===true?' <'.$identity->emailAddress.'>':'');
				$retData = $identity['ident_email'].($fullString===true?' <'.$identity['ident_email'].'>':'');
				break;
			case 'nameNemail';
				$retData = (!empty($identity['ident_realname'])?$identity['ident_realname']:substr_replace($identity['ident_email'],'',strpos($identity['ident_email'],'@'))).($fullString===true?' <'.$identity['ident_email'].'>':'');
				break;
			case 'orgNemail';
				$retData = (!empty($identity['ident_org'])?$identity['ident_org']:substr_replace($identity['ident_email'],'',0,strpos($identity['ident_email'],'@')+1)).($fullString===true?' <'.$identity['ident_email'].'>':'');
				break;
			default:
				$retData = $identity['ident_realname'].(!empty($identity['ident_org'])?' '.$identity['ident_org']:'').($fullString===true?' <'.$identity['ident_email'].'>':'');
		}
		return $retData;
	}

	/**
	 * closes a connection on the active Server ($this->icServer)
	 *
	 * @return void
	 */
	function closeConnection() {
		//if ($icServer->_connected) error_log(__METHOD__.' ('.__LINE__.') '.' disconnect from Server');
		//error_log(__METHOD__."() ".function_backtrace());
		$this->icServer->disconnect();
	}

	/**
	 * reopens a connection for the active Server ($this->icServer), and selects the folder given
	 *
	 * @param string $_foldername, folder to open/select
	 * @return void
	 */
	function reopen($_foldername)
	{
		if (self::$debugTimes) $starttime = microtime (true);

		//error_log(__METHOD__.' ('.__LINE__.') '."('$_foldername') ".function_backtrace());
		// TODO: trying to reduce traffic to the IMAP Server here, introduces problems with fetching the bodies of
		// eMails when not in "current-Folder" (folder that is selected by UI)
		static $folderOpened;
		//if (empty($folderOpened) || $folderOpened!=$_foldername)
		//{
			//error_log( __METHOD__.' ('.__LINE__.') '." $_foldername ".function_backtrace());
			//error_log(__METHOD__.' ('.__LINE__.') '.' Connected with icServer for Profile:'.$this->profileID.'?'.print_r($this->icServer->_connected,true));
			if ($this->folderIsSelectable($_foldername)) {
				$tretval = $this->icServer->openMailbox($_foldername);
			}
			$folderOpened = $_foldername;
		//}
		if (self::$debugTimes) self::logRunTimes($starttime,null,'Folder:'.$_foldername,__METHOD__.' ('.__LINE__.') ');
	}


	/**
	 * openConnection
	 *
	 * @param int $_icServerID
	 * @param boolean $_adminConnection
	 * @throws Horde_Imap_Client_Exception on connection error or authentication failure
	 * @throws InvalidArgumentException on missing credentials
	 */
	function openConnection($_icServerID=0, $_adminConnection=false)
	{
		//error_log( "-------------------------->open connection ".function_backtrace());
		//error_log(__METHOD__.' ('.__LINE__.') '.' ->'.array2string($this->icServer));
		if (self::$debugTimes) $starttime = microtime (true);
		$mailbox=null;
		try
		{
			if($this->folderExists($this->sessionData['mailbox'])) $mailbox = $this->sessionData['mailbox'];
			if (empty($mailbox)) $mailbox = $this->icServer->getCurrentMailbox();
/*
			if (isset(emailadmin_imap::$supports_keywords[$_icServerID]))
			{
				$this->icServer->openMailbox($mailbox);
			}
			else
			{
				$this->icServer->examineMailbox($mailbox);
			}
*/
			// the above should detect if there is a known information about supporting KEYWORDS
			// but does not work as expected :-(
			$this->icServer->examineMailbox($mailbox);
			//error_log(__METHOD__." using existing Connection ProfileID:".$_icServerID.' Status:'.print_r($this->icServer->_connected,true));
			//error_log(__METHOD__.' ('.__LINE__.') '."->open connection for Server with profileID:".$_icServerID.function_backtrace());

			//make sure we are working with the correct hierarchyDelimiter on the current connection, calling getHierarchyDelimiter with false to reset the cache
			$hD = $this->getHierarchyDelimiter(false);
			self::$specialUseFolders = $this->getSpecialUseFolders();
		}
		catch (Exception $e)
		{
			error_log(__METHOD__.' ('.__LINE__.') '."->open connection for Server with profileID:".$_icServerID." failed!".$e->getMessage());
			throw new egw_exception(__METHOD__." failed to ".__METHOD__." on Profile to $_icServerID :".$e->getMessage());
		}
		if (self::$debugTimes) self::logRunTimes($starttime,null,'ProfileID:'.$_icServerID,__METHOD__.' ('.__LINE__.') ');
	}

	/**
	 * getQuotaRoot
	 * return the qouta of the users INBOX
	 *
	 * @return mixed array/boolean
	 */
	function getQuotaRoot()
	{
		static $quota;
		if (isset($quota)) return $quota;
		if(!$this->icServer->hasCapability('QUOTA')) {
			$quota = false;
			return false;
		}
		$quota = $this->icServer->getStorageQuotaRoot('INBOX');
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($quota));
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
	 * 	Note: a IMAPServer may present several namespaces under each key;
	 *			so we return an array of namespacearrays for our needs
	 * @return array array(prefix_present=>mixed (bool/string) ,prefix=>string,delimiter=>string,type=>string (personal|others|shared))
	 */
	function _getNameSpaces()
	{
		static $nameSpace;
		$foldersNameSpace = array();
		$delimiter = $this->getHierarchyDelimiter();
		// TODO: cache by $this->icServer->ImapServerId
		if (is_null($nameSpace)) $nameSpace = $this->icServer->getNameSpaceArray();
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($nameSpace));
		if (is_array($nameSpace)) {
			foreach($nameSpace as $type => $singleNameSpaceArray)
			{
				foreach ($singleNameSpaceArray as $k => $singleNameSpace)
				{
					$prefix_present = false;
					$_foldersNameSpace = array();
					if($type == 'personal' && $singleNameSpace['name'] == '#mh/' && ($this->folderExists('Mail')||$this->folderExists('INBOX')))
					{
						$_foldersNameSpace['prefix_present'] = 'forced';
						// uw-imap server with mailbox prefix or dovecot maybe
						$_foldersNameSpace['prefix'] = ($this->folderExists('Mail')?'Mail':(!empty($singleNameSpace['name'])?$singleNameSpace['name']:''));
					}
					elseif($type == 'personal' && ($singleNameSpace['name'] == '#mh/') && $this->folderExists('mail'))
					{
						$_foldersNameSpace['prefix_present'] = 'forced';
						// uw-imap server with mailbox prefix or dovecot maybe
						$_foldersNameSpace['prefix'] = 'mail';
					} else {
						$_foldersNameSpace['prefix_present'] = !empty($singleNameSpace['name']);
						$_foldersNameSpace['prefix'] = $singleNameSpace['name'];
					}
					$_foldersNameSpace['delimiter'] = ($singleNameSpace['delimiter']?$singleNameSpace['delimiter']:$delimiter);
					$_foldersNameSpace['type'] = $type;
					$foldersNameSpace[] =$_foldersNameSpace;
				}
				//echo "############## $type->".print_r($foldersNameSpace[$type],true)." ###################<br>";
			}
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($foldersNameSpace));
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
		foreach($nameSpace as $k => $singleNameSpace)
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
			return $HierarchyDelimiter[$this->icServer->ImapServerId];
		}
		$HierarchyDelimiter[$this->icServer->ImapServerId] = '/';
		try
		{
			$HierarchyDelimiter[$this->icServer->ImapServerId] = $this->icServer->getDelimiter();
		}
		catch(Exception $e)
		{
			$HierarchyDelimiter[$this->icServer->ImapServerId] = '/';
		}
		return $HierarchyDelimiter[$this->icServer->ImapServerId];
	}

	/**
	 * getSpecialUseFolders
	 * @ToDo: could as well be static, when icServer is passed
	 * @return mixed null/array
	 */
	function getSpecialUseFolders()
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.':'.$this->icServer->ImapServerId.' Connected:'.$this->icServer->_connected);
		static $_specialUseFolders;
		if (is_null($_specialUseFolders)||empty($_specialUseFolders)) $_specialUseFolders = egw_cache::getCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*24*5);
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($this->icServer->acc_folder_trash));
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($this->icServer->acc_folder_sent));
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($this->icServer->acc_folder_draft));
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($this->icServer->acc_folder_template));
		self::$specialUseFolders = $_specialUseFolders[$this->icServer->ImapServerId];
		if (isset($_specialUseFolders[$this->icServer->ImapServerId]) && !empty($_specialUseFolders[$this->icServer->ImapServerId]))
			return $_specialUseFolders[$this->icServer->ImapServerId];
		$_specialUseFolders[$this->icServer->ImapServerId]=array();
		//if (!empty($this->icServer->acc_folder_trash) && !isset($_specialUseFolders[$this->icServer->ImapServerId][$this->icServer->acc_folder_trash]))
			$_specialUseFolders[$this->icServer->ImapServerId][$this->icServer->acc_folder_trash]='Trash';
		//if (!empty($this->icServer->acc_folder_draft) && !isset($_specialUseFolders[$this->icServer->ImapServerId][$this->icServer->acc_folder_draft]))
			$_specialUseFolders[$this->icServer->ImapServerId][$this->icServer->acc_folder_draft]='Drafts';
		//if (!empty($this->icServer->acc_folder_sent) && !isset($_specialUseFolders[$this->icServer->ImapServerId][$this->icServer->acc_folder_sent]))
			$_specialUseFolders[$this->icServer->ImapServerId][$this->icServer->acc_folder_sent]='Sent';
		//if (!empty($this->icServer->acc_folder_template) && !isset($_specialUseFolders[$this->icServer->ImapServerId][$this->icServer->acc_folder_template]))
			$_specialUseFolders[$this->icServer->ImapServerId][$this->icServer->acc_folder_template]='Templates';
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($_specialUseFolders));//.'<->'.array2string($this->icServer));
		self::$specialUseFolders = $_specialUseFolders[$this->icServer->ImapServerId];
		egw_cache::setCache(egw_cache::INSTANCE,'email','specialUseFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$_specialUseFolders, $expiration=60*60*24*5);
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
			if (!empty($folderStatus['attributes']) && stripos(array2string($folderStatus['attributes']),'noselect')!==false)
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
			//error_log(__METHOD__.' ('.__LINE__.') '.' Using cache for status on Server:'.$this->icServer->ImapServerId.' for folder:'.$folderName.'->'.array2string($folderStatus[$this->icServer->ImapServerId][$folderName]));
			return $folderStatus[$this->icServer->ImapServerId][$folderName];
		}
		try
		{
			$folderStatus[$this->icServer->ImapServerId][$folderName] = $this->icServer->getStatus($folderName);
		}
		catch (Exception $e)
		{
			throw new egw_exception(__METHOD__.' ('.__LINE__.') '." failed for $folderName with error:".$e->getMessage());
		}
		return $folderStatus[$this->icServer->ImapServerId][$folderName];
	}

	/**
	 * get IMAP folder status
	 *
	 * returns an array information about the imap folder, may be used as  wrapper to retrieve results from cache
	 *
	 * @param _folderName string the foldername
	 * @param ignoreStatusCache bool ignore the cache used for counters
	 * @param basicInfoOnly bool retrieve only names and stuff returned by getMailboxes
	 * @param fetchSubscribedInfo bool fetch Subscribed Info on folder
	 * @return array
	 */
	function getFolderStatus($_folderName,$ignoreStatusCache=false,$basicInfoOnly=false,$fetchSubscribedInfo=true)
	{
		if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '." called with:$_folderName,$ignoreStatusCache,$basicInfoOnly");
		if (!is_string($_folderName) || empty($_folderName)) // something is wrong. Do not proceed
		{
			return false;
		}
		static $folderInfoCache; // reduce traffic on single request
		static $folderBasicInfo;
		if (is_null($folderBasicInfo) || !isset($folderBasicInfo[$this->profileID]))
		{
			$folderBasicInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
			$folderInfoCache = $folderBasicInfo[$this->profileID];
		}
		else
		{
			$folderInfoCache = $folderBasicInfo[$this->profileID];
		}
		if (isset($folderInfoCache[$_folderName]) && $ignoreStatusCache==false && $basicInfoOnly) return $folderInfoCache[$_folderName];
		$retValue = array();
		$retValue['subscribed'] = false;
/*
		if(!$icServer = emailadmin_account::read($this->profileID)) {
			if (self::$debug) error_log(__METHOD__." no Server found for Folder:".$_folderName);
			return false;
		}
*/
		//error_log(__METHOD__.' ('.__LINE__.') '.$_folderName.' '.array2string(array_keys($folderInfoCache)));
		// does the folder exist???
		if (is_null($folderInfoCache) || !isset($folderInfoCache[$_folderName]))
		{
			$ret = $this->icServer->getMailboxes('', $_folderName, true);
			//error_log(__METHOD__.' ('.__LINE__.') '.$_folderName.' '.array2string($ret));
			if (is_array($ret))
			{
				$retkeys = array_keys($ret);
				if ($retkeys[0]==$_folderName) $folderInfoCache[$_folderName] = $ret[$retkeys[0]];
			}
			else
			{
				$folderInfoCache[$_folderName]=false;
			}
		}
		$folderInfo = $folderInfoCache[$_folderName];
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($folderInfo).'->'.function_backtrace());
		if(!$folderInfo|| !is_array($folderInfo)) {
			try
			{
				$folderInfo = $this->_getStatus($_folderName);
			}
			catch (Exception $e)
			{
				$folderInfo=null;
			}
			if (!is_array($folderInfo))
			{
				// no folder info, but there is a status returned for the folder: something is wrong, try to cope with it
				$folderInfo = is_array($folderInfo)?$folderInfo:array('HIERACHY_DELIMITER'=>$this->getHierarchyDelimiter(),
					'ATTRIBUTES' => '');
				if (empty($folderInfo['HIERACHY_DELIMITER']) || (isset($folderInfo['delimiter']) && empty($folderInfo['delimiter'])))
				{
					//error_log(__METHOD__.' ('.__LINE__.') '.array2string($folderInfo));
					$folderInfo['HIERACHY_DELIMITER'] = $this->getHierarchyDelimiter();
				}
			}
		}
		#if(!is_array($folderInfo)) {
		#	return false;
		#}
		$retValue['delimiter']		= ($folderInfo['HIERACHY_DELIMITER']?$folderInfo['HIERACHY_DELIMITER']:$folderInfo['delimiter']);
		$retValue['attributes']		= ($folderInfo['ATTRIBUTES']?$folderInfo['ATTRIBUTES']:$folderInfo['attributes']);
		$shortNameParts			= explode($retValue['delimiter'], $_folderName);
		$retValue['shortName']		= array_pop($shortNameParts);
		$retValue['displayName']	= $_folderName;
		$retValue['shortDisplayName']	= $retValue['shortName'];
		if(strtoupper($retValue['shortName']) == 'INBOX') {
			$retValue['displayName']	= lang('INBOX');
			$retValue['shortDisplayName']	= lang('INBOX');
		}
		// translate the automatic Folders (Sent, Drafts, ...) like the INBOX
		elseif (in_array($retValue['shortName'],self::$autoFolders))
		{
			$retValue['displayName'] = $retValue['shortDisplayName'] = lang($retValue['shortName']);
		}
		if ($folderInfo) $folderBasicInfo[$this->profileID][$_folderName]=$retValue;
		egw_cache::setCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderBasicInfo,$expiration=60*60*1);
		//error_log(__METHOD__.' ('.__LINE__.') '.' '.$_folderName.array2string($retValue['attributes']));
		if ($basicInfoOnly || (isset($retValue['attributes']) && stripos(array2string($retValue['attributes']),'noselect')!==false))
		{
			return $retValue;
		}
		// fetch all in one go for one request, instead of querying them one by one
		// cache it for a minute 60*60*1
		// this should reduce communication to the imap server
		static $subscribedFolders;
		if ($fetchSubscribedInfo && is_null($subscribedFolders)||empty($subscribedFolders[$this->profileID]))
		{
			$subscribedFolders = egw_cache::getCache(egw_cache::INSTANCE,'email','subscribedFolders'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
		}
		static $nameSpace;
		static $prefix;
		if (is_null($nameSpace) || empty($nameSpace[$this->profileID])) $nameSpace[$this->profileID] = $this->_getNameSpaces();
		if (!empty($nameSpace[$this->profileID]))
		{
			$nsNoPersonal=array();
			foreach($nameSpace[$this->profileID] as $k => $ns)
			{
				if ($ns['type']!='personal') $nsNoPersonal[]=$ns;
			}
			$nameSpace[$this->profileID]=$nsNoPersonal;
		}
		if (is_null($prefix) || empty($prefix[$this->profileID]) || empty($prefix[$this->profileID][$_folderName])) $prefix[$this->profileID][$_folderName] = $this->getFolderPrefixFromNamespace($nameSpace[$this->profileID], $_folderName);

		if ($fetchSubscribedInfo && is_null($subscribedFolders) || empty($subscribedFolders[$this->profileID]))
		{
			$subscribedFolders[$this->profileID] = $this->icServer->listSubscribedMailboxes();
			egw_cache::setCache(egw_cache::INSTANCE,'email','subscribedFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$subscribedFolders,$expiration=60*60*1);
		}

		if($fetchSubscribedInfo && is_array($subscribedFolders[$this->profileID]) && in_array($_folderName,$subscribedFolders[$this->profileID])) {
			$retValue['subscribed'] = true;
		}

		try
		{
			//$folderStatus = $this->_getStatus($_folderName,$ignoreStatusCache);
			$folderStatus = $this->getMailBoxCounters($_folderName,false);
			$retValue['messages']		= $folderStatus['MESSAGES'];
			$retValue['recent']		= $folderStatus['RECENT'];
			$retValue['uidnext']		= $folderStatus['UIDNEXT'];
			$retValue['uidvalidity']	= $folderStatus['UIDVALIDITY'];
			$retValue['unseen']		= $folderStatus['UNSEEN'];
			if (//$retValue['unseen']==0 &&
				(isset($this->mailPreferences['trustServersUnseenInfo']) && // some servers dont serve the UNSEEN information
				$this->mailPreferences['trustServersUnseenInfo']==false) ||
				(isset($this->mailPreferences['trustServersUnseenInfo']) &&
				$this->mailPreferences['trustServersUnseenInfo']==2 &&
				$prefix[$this->profileID][$_folderName] != '' && stripos($_folderName,$prefix[$this->profileID][$_folderName]) !== false)
			)
			{
				//error_log(__METHOD__." returned folderStatus for Folder $_folderName:".print_r($prefix,true).' TS:'.$this->mailPreferences['trustServersUnseenInfo']);
				// we filter for the combined status of unseen and undeleted, as this is what we show in list
				try
				{
					$sortResult = $this->getSortedList($_folderName, $_sort=0, $_reverse=1, $_filter=array('status'=>array('UNSEEN','UNDELETED')),$byUid=true,false);
					$retValue['unseen'] = $sortResult['count'];
				}
				catch (Exception $ee)
				{
					if (self::$debug) error_log(__METHOD__." could not fetch/calculate unseen counter for $_folderName Reason:'".$ee->getMessage()."' but requested.");
				}
			}
		}
		catch (Exception $e)
		{
			if (self::$debug) error_log(__METHOD__." returned folderStatus for Folder $_folderName:".print_r($e->getMessage(),true));
		}

		return $retValue;
	}

	/**
	 * getHeaders
	 *
	 * this function is a wrapper function for getSortedList and populates the resultList thereof with headerdata
	 *
	 * @param string $_folderName
	 * @param int $_startMessage
	 * @param int $_numberOfMessages number of messages to return
	 * @param array $_sort sort by criteria
	 * @param boolean $_reverse reverse sorting of the result array (may be switched, as it is passed to getSortedList by reference)
	 * @param array $_filter filter to apply to getSortedList
	 * @param mixed $_thisUIDOnly=null, if given fetch the headers of this uid only (either one, or array of uids)
	 * @param boolean $_cacheResult=true try touse the cache of getSortedList
	 * @return array result as array(header=>array,total=>int,first=>int,last=>int)
	 */
	function getHeaders($_folderName, $_startMessage, $_numberOfMessages, $_sort, $_reverse, $_filter, $_thisUIDOnly=null, $_cacheResult=true)
	{
		//self::$debug=true;
		if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.function_backtrace());
		if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '."$_folderName,$_startMessage, $_numberOfMessages, $_sort, $_reverse, ".array2string($_filter).", $_thisUIDOnly");
		$reverse = (bool)$_reverse;
		// get the list of messages to fetch
		$this->reopen($_folderName);
		//$currentFolder = $this->icServer->getCurrentMailbox();
		//if ($currentFolder != $_folderName); $this->icServer->openMailbox($_folderName);
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
			if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '."$_folderName, $_sort, $reverse, ".array2string($_filter).", $rByUid");
			if (self::$debug||self::$debugTimes) $starttime = microtime (true);
			//see this example below for a 12 week datefilter (since)
			//$_filter = array('status'=>array('UNDELETED'),'type'=>"SINCE",'string'=> date("d-M-Y", $starttime-(3600*24*7*12)));
			$_sortResult = $this->getSortedList($_folderName, $_sort, $reverse, $_filter, $rByUid, $_cacheResult);
			$sortResult = $_sortResult['match']->ids;
			//$modseq = $_sortResult['modseq'];
			//error_log(__METHOD__.' ('.__LINE__.') '.'Modsequence:'.$modseq);
			if (self::$debug||self::$debugTimes) self::logRunTimes($starttime,null,' call getSortedList for Folder:'.$_folderName.' Filter:'.array2string($_filter).' Ids:'.array2string($_thisUIDOnly),__METHOD__.' ('.__LINE__.') ');

			if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.array2string($sortResult));
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

			$total = $_sortResult['count'];
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
					//error_log(__METHOD__.' ('.__LINE__.') '.' Start:'.$_startMessage.' NumberOfMessages:'.$_numberOfMessages.' Total:'.$total);
					if ($_startMessage+$_numberOfMessages>$total)
					{
						$numberOfMessages = $total%$_numberOfMessages;
						//$numberOfMessages = abs($_startMessage-$total-1);
						if ($numberOfMessages>0 && $numberOfMessages<=$_numberOfMessages) $_numberOfMessages = $numberOfMessages;
						//error_log(__METHOD__.' ('.__LINE__.') '.' Start:'.$_startMessage.' NumberOfMessages:'.$_numberOfMessages.' Total:'.$total);
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
					if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' StartMessage:'.(-($_numberOfMessages+$startMessage)).', '.-$startMessage.' Number of Messages:'.count($sortResult));
					$sortResult = array_slice($sortResult, -($_numberOfMessages+$startMessage), -$startMessage);
				} else {
					if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' StartMessage:'.(-($_numberOfMessages+($_startMessage-1))).', AllTheRest, Number of Messages:'.count($sortResult));
					$sortResult = array_slice($sortResult, -($_numberOfMessages+($_startMessage-1)));
				}
				$sortResult = array_reverse($sortResult);
			} else {
				if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' StartMessage:'.($_startMessage-1).', '.$_numberOfMessages.' Number of Messages:'.count($sortResult));
				$sortResult = array_slice($sortResult, $_startMessage-1, $_numberOfMessages);
			}
			if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.array2string($sortResult));
		}
		else
		{
			$sortResult = (is_array($_thisUIDOnly) ? $_thisUIDOnly:(array)$_thisUIDOnly);
		}

		//$queryString = implode(',', $sortResult);
		// fetch the data for the selected messages
		if (self::$debug||self::$debugTimes) $starttime = microtime(true);
		try
		{
			$uidsToFetch = new Horde_Imap_Client_Ids();
			$uidsToFetch->add($sortResult);

			$fquery = new Horde_Imap_Client_Fetch_Query();

			// Pre-cache the headers we want, 'fetchHeaders' is a label into the cache
			$fquery->headers('fetchHeaders',array(
				'DISPOSITION-NOTIFICATION-TO','RETURN-RECEIPT-TO','X-CONFIRM-READING-TO',
				'DATE','SUBJECT','FROM','TO','CC',
				'X-PRIORITY'
			),array(
				// Cache headers, we'll look at them below
				'cache' => true,
				// Set peek so messages are not flagged as read
				'peek' => true
			));
			$fquery->size();
			$fquery->structure();
			$fquery->flags();
			//$fquery->imapDate();
			$headersNew = $this->icServer->fetch($_folderName, $fquery, array(
				'ids' => $uidsToFetch,
			));
			//error_log(__METHOD__.' ('.__LINE__.') '.array2string($headersNew->ids()));
		}
		catch (Exception $e)
		{
			$headersNew = array();
			$sortResult = array();
		}
		if (self::$debug||self::$debugTimes)
		{
			self::logRunTimes($starttime,null,'HordeFetch: for Folder:'.$_folderName.' Filter:'.array2string($_filter),__METHOD__.' ('.__LINE__.') ');
			if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' Query:'.$queryString.' Result:'.array2string($headersNew));
		}

		$count = 0;

		foreach((array)$sortResult as $uid) {
			$sortOrder[$uid] = $count++;
		}

		$count = 0;
		if (is_object($headersNew)) {
			if (self::$debug||self::$debugTimes) $starttime = microtime(true);
			foreach($headersNew->ids() as $id) {
				$_headerObject = $headersNew->get($id);
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($_headerObject));
				$headerObject = array();
				$uid = $headerObject['UID']= ($_headerObject->getUid()?$_headerObject->getUid():$id);
				$headerObject['MSG_NUM'] = $_headerObject->getSeq();
				$headerObject['SIZE'] = $_headerObject->getSize();
				$headerObject['INTERNALDATE'] = $_headerObject->getImapDate();

				// Get already cached headers, 'fetchHeaders' is a label matchimg above
				$headerForPrio = array_change_key_case($_headerObject->getHeaders('fetchHeaders',Horde_Imap_Client_Data_Fetch::HEADER_PARSE)->toArray(), CASE_UPPER);
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($headerForPrio));
				if ( isset($headerForPrio['DISPOSITION-NOTIFICATION-TO']) ) {
					$headerObject['DISPOSITION-NOTIFICATION-TO'] = self::decode_header(trim($headerForPrio['DISPOSITION-NOTIFICATION-TO']));
				} else if ( isset($headerForPrio['RETURN-RECEIPT-TO']) ) {
					$headerObject['DISPOSITION-NOTIFICATION-TO'] = self::decode_header(trim($headerForPrio['RETURN-RECEIPT-TO']));
				} else if ( isset($headerForPrio['X-CONFIRM-READING-TO']) ) {
					$headerObject['DISPOSITION-NOTIFICATION-TO'] = self::decode_header(trim($headerForPrio['X-CONFIRM-READING-TO']));
				} /*else $sent_not = "";*/
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($headerObject));
				$headerObject['DATE'] = $headerForPrio['DATE'];
				$headerObject['SUBJECT'] = (is_array($headerForPrio['SUBJECT'])?$headerForPrio['SUBJECT'][0]:$headerForPrio['SUBJECT']);
				$headerObject['FROM'] = (array)($headerForPrio['FROM']?$headerForPrio['FROM']:($headerForPrio['REPLY-TO']?$headerForPrio['REPLY-TO']:$headerForPrio['RETURN-PATH']));
				$headerObject['TO'] = (array)$headerForPrio['TO'];
				$headerObject['CC'] = (array)$headerForPrio['CC'];
				$headerObject['PRIORITY'] = $headerForPrio['X-PRIORITY'];
				foreach (array('FROM','TO','CC') as $_k => $key)
				{
					$address = array();
					foreach ($headerObject[$key] as $k => $ad)
					{
						if (stripos($ad,'@')===false)
						{
							$remember=$k;
						}
						else
						{
							$address[] = (!is_null($remember)?$headerObject[$key][$remember].' ':'').$ad;
							$remember=null;
						}
					}
					$headerObject[$key] = $address;

				}
				$headerObject['FLAGS'] = $_headerObject->getFlags();
				$mailStructureObject = $_headerObject->getStructure();
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($headerObject));
				//error_log(__METHOD__.' ('.__LINE__.') '.' MimeMap:'.array2string($mailStructureObject->contentTypeMap()));
				//foreach ($_headerObject->getStructure()->getParts() as $p => $part)
				$headerObject['ATTACHMENTS']=null;
				$skipParts=array();
				$messageMimeType='';
				foreach ($mailStructureObject->contentTypeMap() as $mime_id => $mime_type)
				{
					if ($mime_id==0 || $messageMimeType==='') $messageMimeType = $mime_type;
					$part = $mailStructureObject->getPart($mime_id);
					$partdisposition = $part->getDisposition();
					$partPrimaryType = $part->getPrimaryType();
					$cid = $part->getContentId();
					if (empty($partdisposition) && $partPrimaryType != 'multipart' && $partPrimaryType != 'text')
					{
						$partdisposition=($partPrimaryType == 'image'&&!empty($cid)?'inline':'attachment');
					}
					if ($mime_type=='message/rfc822')
					{
						//error_log(__METHOD__.' ('.__LINE__.') '.' Uid:'.$uid.'->'.$mime_id.':'.array2string($part->contentTypeMap()));
						foreach($part->contentTypeMap() as $sub_id => $sub_type) if ($sub_id != $mime_id) $skipParts[$sub_id] = $sub_type;
					}
					//error_log(__METHOD__.' ('.__LINE__.') '.' Uid:'.$uid.'->'.$mime_id.' Disp:'.$partdisposition.' Type:'.$partPrimaryType.' Skip:'.array2string($skipParts));
					if (array_key_exists($mime_id,$skipParts)) continue;
					if ($partdisposition=='attachment' ||
						($partdisposition=='inline'&&$partPrimaryType == 'image'&&empty($cid)) ||
						($partdisposition=='inline' && $partPrimaryType != 'image' && $partPrimaryType != 'multipart' && $partPrimaryType != 'text'))
					{
						$headerObject['ATTACHMENTS'][$mime_id]=$part->getAllDispositionParameters();
						$headerObject['ATTACHMENTS'][$mime_id]['mimeType']=$mime_type;
						$headerObject['ATTACHMENTS'][$mime_id]['uid']=$uid;
						$headerObject['ATTACHMENTS'][$mime_id]['cid'] = $cid;
						$headerObject['ATTACHMENTS'][$mime_id]['partID']=$mime_id;
						if (!isset($headerObject['ATTACHMENTS'][$mime_id]['name']))$headerObject['ATTACHMENTS'][$mime_id]['name']=$part->getName();
						//error_log(__METHOD__.' ('.__LINE__.') '.' PartDisposition:'.$mime_id.'->'.array2string($part->getName()));
						//error_log(__METHOD__.' ('.__LINE__.') '.' PartDisposition:'.$mime_id.'->'.array2string($part->getAllDispositionParameters()));
						//error_log(__METHOD__.' ('.__LINE__.') '.' Attachment:'.$mime_id.'->'.array2string($headerObject['ATTACHMENTS'][$mime_id]));
					}
				}
				//error_log(__METHOD__.' ('.__LINE__.') '.' FindBody (plain):'.array2string($mailStructureObject->findBody('plain')));
				//error_log(__METHOD__.' ('.__LINE__.') '.' FindBody (html):'.array2string($mailStructureObject->findBody('html')));
				//if($count == 0) error_log(__METHOD__.array2string($headerObject));
				if (empty($headerObject['UID'])) continue;
				//$uid = ($rByUid ? $headerObject['UID'] : $headerObject['MSG_NUM']);
				// make dates like "Mon, 23 Apr 2007 10:11:06 UT" working with strtotime
				if(substr($headerObject['DATE'],-2) === 'UT') {
					$headerObject['DATE'] .= 'C';
				}
				if(substr($headerObject['INTERNALDATE'],-2) === 'UT') {
					$headerObject['INTERNALDATE'] .= 'C';
				}
				//error_log(__METHOD__.' ('.__LINE__.') '.' '.$headerObject['SUBJECT'].'->'.$headerObject['DATE']);
				//error_log(__METHOD__.' ('.__LINE__.') '.' '.$this->decode_subject($headerObject['SUBJECT']).'->'.$headerObject['DATE']);
				if (isset($headerObject['ATTACHMENTS']) && count($headerObject['ATTACHMENTS'])) foreach ($headerObject['ATTACHMENTS'] as $pID =>$a) $retValue['header'][$sortOrder[$uid]]['attachments'][]=$a;
				$retValue['header'][$sortOrder[$uid]]['subject']	= $this->decode_subject($headerObject['SUBJECT']);
				$retValue['header'][$sortOrder[$uid]]['size'] 		= $headerObject['SIZE'];
				$retValue['header'][$sortOrder[$uid]]['date']		= self::_strtotime(($headerObject['DATE']&&!($headerObject['DATE']=='NIL')?$headerObject['DATE']:$headerObject['INTERNALDATE']),'ts',true);
				$retValue['header'][$sortOrder[$uid]]['internaldate']= self::_strtotime($headerObject['INTERNALDATE'],'ts',true);
				$retValue['header'][$sortOrder[$uid]]['mimetype']	= $messageMimeType;
				$retValue['header'][$sortOrder[$uid]]['id']		= $headerObject['MSG_NUM'];
				$retValue['header'][$sortOrder[$uid]]['uid']		= $headerObject['UID'];
				$retValue['header'][$sortOrder[$uid]]['priority']		= ($headerObject['PRIORITY']?$headerObject['PRIORITY']:3);
				if (isset($headerObject['DISPOSITION-NOTIFICATION-TO'])) $retValue['header'][$sortOrder[$uid]]['disposition-notification-to'] = $headerObject['DISPOSITION-NOTIFICATION-TO'];
				if (is_array($headerObject['FLAGS'])) {
					$retValue['header'][$sortOrder[$uid]] = array_merge($retValue['header'][$sortOrder[$uid]],self::prepareFlagsArray($headerObject));
				}
//error_log(__METHOD__.' ('.__LINE__.') '.$headerObject['SUBJECT'].'->'.array2string($_headerObject->getEnvelope()->__get('from')));
				if(is_array($headerObject['FROM']) && $headerObject['FROM'][0]) {
					$retValue['header'][$sortOrder[$uid]]['sender_address'] = self::decode_header($headerObject['FROM'][0]);
				}

				if(is_array($headerObject['TO']) && $headerObject['TO'][0]) {
					$retValue['header'][$sortOrder[$uid]]['to_address'] = self::decode_header($headerObject['TO'][0]);
					if (count($headerObject['TO'])>1)
					{
						$ki=0;
						foreach($headerObject['TO'] as $k => $add)
						{
							if ($k==0) continue;
							//error_log(__METHOD__.' ('.__LINE__.') '."-> $k:".array2string($add));
							$retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki] = self::decode_header($add);
							//error_log(__METHOD__.' ('.__LINE__.') '.array2string($retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki]));
							$ki++;
						}
					}
				}
				if(is_array($headerObject['CC']) && $headerObject['CC'][0]) {
					$ki=0;
					foreach($headerObject['CC'] as $k => $add)
					{
						//error_log(__METHOD__.' ('.__LINE__.') '."-> $k:".array2string($add));
						$retValue['header'][$sortOrder[$uid]]['cc_addresses'][$ki] = self::decode_header($add);
						//error_log(__METHOD__.' ('.__LINE__.') '.array2string($retValue['header'][$sortOrder[$uid]]['additional_to_addresses'][$ki]));
						$ki++;
					}
				}
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($retValue['header'][$sortOrder[$uid]]));

				$count++;
			}
			if (self::$debug||self::$debugTimes) self::logRunTimes($starttime,null,' fetching Headers and stuff for Folder:'.$_folderName,__METHOD__.' ('.__LINE__.') ');
			//self::$debug=false;
			// sort the messages to the requested displayorder
			if(is_array($retValue['header'])) {
				$countMessages = $total;
				if (isset($_filter['range'])) $countMessages = self::$folderStatusCache[$this->profileID][$_folderName]['messages'];
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
	 * static function prepareFlagsArray
	 * prepare headerObject to return some standardized array to tell which flags are set for a message
	 * @param array $headerObject  - array to process, a full return array from icServer->getSummary
	 * @return array array of flags
	 */
	static function prepareFlagsArray($headerObject)
	{
		if (is_array($headerObject['FLAGS'])) $headerFlags = array_map('strtolower',$headerObject['FLAGS']);
		$retValue = array();
		$retValue['recent']		= in_array('\\recent', $headerFlags);
		$retValue['flagged']	= in_array('\\flagged', $headerFlags);
		$retValue['answered']	= in_array('\\answered', $headerFlags);
		$retValue['forwarded']   = in_array('$forwarded', $headerFlags);
		$retValue['deleted']	= in_array('\\deleted', $headerFlags);
		$retValue['seen']		= in_array('\\seen', $headerFlags);
		$retValue['draft']		= in_array('\\draft', $headerFlags);
		$retValue['mdnsent']	= in_array('$mdnsent', $headerFlags)||in_array('mdnsent', $headerFlags);
		$retValue['mdnnotsent']	= in_array('$mdnnotsent', $headerFlags)||in_array('mdnnotsent', $headerFlags);
		$retValue['label1']   = in_array('$label1', $headerFlags);
		$retValue['label2']   = in_array('$label2', $headerFlags);
		$retValue['label3']   = in_array('$label3', $headerFlags);
		$retValue['label4']   = in_array('$label4', $headerFlags);
		$retValue['label5']   = in_array('$label5', $headerFlags);
		//error_log(__METHOD__.' ('.__LINE__.') '.$headerObject['SUBJECT'].':'.array2string($retValue));
		return $retValue;
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
		static $cachedFolderStatus;
		// in the past we needed examineMailbox to figure out if the server with the serverID support keywords
		// this information is filled/provided by examineMailbox; but caching within one request seems o.k.
		if (is_null($cachedFolderStatus) || !isset($cachedFolderStatus[$this->profileID][$_folderName]) )
		{
			$folderStatus = $cachedFolderStatus[$this->profileID][$_folderName] = $this->icServer->examineMailbox($_folderName);
		}
		else
		{
			$folderStatus = $cachedFolderStatus[$this->profileID][$_folderName];
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.' F:'.$_folderName.' S:'.array2string($folderStatus));
		//error_log(__METHOD__.' ('.__LINE__.') '.' Filter:'.array2string($_filter));
		$try2useCache = true;
		static $eMailListContainsDeletedMessages;
		if (is_null($eMailListContainsDeletedMessages)) $eMailListContainsDeletedMessages = egw_cache::getCache(egw_cache::INSTANCE,'email','eMailListContainsDeletedMessages'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
		// this indicates, that there is no Filter set, and the returned set/subset should not contain DELETED Messages, nor filtered for UNDELETED
		if ($setSession==true && ((strpos(array2string($_filter), 'UNDELETED') === false && strpos(array2string($_filter), 'DELETED') === false)))
		{
			if (self::$debugTimes) $starttime = microtime(true);
			if (is_null($eMailListContainsDeletedMessages) || empty($eMailListContainsDeletedMessages[$this->profileID]) || empty($eMailListContainsDeletedMessages[$this->profileID][$_folderName])) $eMailListContainsDeletedMessages = egw_cache::getCache(egw_cache::INSTANCE,'email','eMailListContainsDeletedMessages'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
			$deletedMessages = $this->getSortedList($_folderName, 0, $three=1, array('status'=>array('DELETED')),$five=true,false);
			//error_log(__METHOD__.' ('.__LINE__.') '.array2string($deletedMessages));
			$eMailListContainsDeletedMessages[$this->profileID][$_folderName] =$deletedMessages['count'];
			egw_cache::setCache(egw_cache::INSTANCE,'email','eMailListContainsDeletedMessages'.trim($GLOBALS['egw_info']['user']['account_id']),$eMailListContainsDeletedMessages, $expiration=60*60*1);
			if (self::$debugTimes) self::logRunTimes($starttime,null,'setting eMailListContainsDeletedMessages for Profile:'.$this->profileID.' Folder:'.$_folderName.' to '.$eMailListContainsDeletedMessages[$this->profileID][$_folderName],__METHOD__.' ('.__LINE__.') ');			//error_log(__METHOD__.' ('.__LINE__.') '.' Profile:'.$this->profileID.' Folder:'.$_folderName.' -> EXISTS/SessStat:'.array2string($folderStatus['MESSAGES']).'/'.self::$folderStatusCache[$this->profileID][$_folderName]['messages'].' ListContDelMsg/SessDeleted:'.$eMailListContainsDeletedMessages[$this->profileID][$_folderName].'/'.self::$folderStatusCache[$this->profileID][$_folderName]['deleted']);
		}
		$try2useCache = false;
		//self::$supportsORinQuery[$this->profileID]=true;
		if (is_null(self::$supportsORinQuery) || !isset(self::$supportsORinQuery[$this->profileID]))
		{
			self::$supportsORinQuery = egw_cache::getCache(egw_cache::INSTANCE,'email','supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*10);
			if (!isset(self::$supportsORinQuery[$this->profileID])) self::$supportsORinQuery[$this->profileID]=true;
		}
		$filter = $this->createIMAPFilter($_folderName, $_filter,self::$supportsORinQuery[$this->profileID]);
		//_debug_array($filter);
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($filter));
		if($this->icServer->hasCapability('SORT')) {
			if (self::$debug) error_log(__METHOD__." Mailserver has SORT Capability, SortBy: $_sort Reverse: $_reverse");
			$sortOrder = $this->_getSortString($_sort, $_reverse);
			if ($_reverse && in_array(Horde_Imap_Client::SORT_REVERSE,$sortOrder)) $_reverse=false; // as we reversed the result already
			if (self::$debug) error_log(__METHOD__." Mailserver runs SORT: SortBy: $sortOrder Filter: ".array2string($filter));
			try
			{
				$sortResult = $this->icServer->search($_folderName, $filter, array(
					'sort' => $sortOrder,));
			// if there is an Error, we assume that the server is not capable of sorting
			}
			catch(Exception $e)
			{
				$resultByUid = false;
				$sortOrder = array(Horde_Imap_Client::SORT_SEQUENCE);
				if ($_reverse) array_unshift($sortOrder,Horde_Imap_Client::SORT_REVERSE);
				try
				{
					$sortResult = $this->icServer->search($_folderName, $filter, array(
						'sort' => $sortOrder));
				}
				catch(Exception $e)
				{
					error_log(__METHOD__.'('.__LINE__.'):'.$e->getMessage());
					$sortResult = self::$folderStatusCache[$this->profileID][$_folderName]['sortResult'];
				}
			}
			if (self::$debug) error_log(__METHOD__.print_r($sortResult,true));
		} else {
			if (self::$debug) error_log(__METHOD__." Mailserver has NO SORT Capability");
			//$sortOrder = array(Horde_Imap_Client::SORT_SEQUENCE);
			//if ($_reverse) array_unshift($sortOrder,Horde_Imap_Client::SORT_REVERSE);
			try
			{
				$sortResult = $this->icServer->search($_folderName, $filter, array()/*array(
					'sort' => $sortOrder)*/);
			}
			catch(Exception $e)
			{
				//error_log(__METHOD__.'('.__LINE__.'):'.$e->getMessage());
				// possible error OR Query. But Horde gives no detailed Info :-(
				self::$supportsORinQuery[$this->profileID]=false;
				egw_cache::setCache(egw_cache::INSTANCE,'email','supportsORinQuery'.trim($GLOBALS['egw_info']['user']['account_id']),self::$supportsORinQuery,$expiration=60*60*10);
				if (self::$debug) error_log(__METHOD__.__LINE__." Mailserver seems to have NO OR Capability for Search:".$sortResult->message);
				$filter = $this->createIMAPFilter($_folderName, $_filter, self::$supportsORinQuery[$this->profileID]);
				try
				{
					$sortResult = $this->icServer->search($_folderName, $filter, array()/*array(
						'sort' => $sortOrder)*/);
				}
				catch(Exception $e)
				{
				}
			}
			if(is_array($sortResult['match'])) {
					// not sure that this is going so succeed as $sortResult['match'] is a hordeObject
					sort($sortResult['match'], SORT_NUMERIC);
			}
			if (self::$debug) error_log(__METHOD__." using Filter:".print_r($filter,true)." ->".print_r($sortResult,true));
		}
		if ($setSession)
		{
			self::$folderStatusCache[$this->profileID][$_folderName]['uidValidity'] = $folderStatus['UIDVALIDITY'];
			self::$folderStatusCache[$this->profileID][$_folderName]['messages']	= $folderStatus['MESSAGES'];
			self::$folderStatusCache[$this->profileID][$_folderName]['deleted']	= $eMailListContainsDeletedMessages[$this->profileID][$_folderName];
			self::$folderStatusCache[$this->profileID][$_folderName]['uidnext']	= $folderStatus['UIDNEXT'];
			self::$folderStatusCache[$this->profileID][$_folderName]['filter']	= $_filter;
			self::$folderStatusCache[$this->profileID][$_folderName]['sortResult'] = $sortResult;
			self::$folderStatusCache[$this->profileID][$_folderName]['sort']	= $_sort;
		}
		//_debug_array($sortResult['match']->ids);
		return $sortResult;
	}

	/**
	 * convert the sort value from the gui(integer) into a string
	 *
	 * @param mixed _sort the integer sort order / or a valid and handeled SORTSTRING (right now: UID/ARRIVAL/INTERNALDATE (->ARRIVAL))
	 * @param bool _reverse wether to add REVERSE to the Sort String or not
	 * @return the sort sequence for horde search
	 */
	function _getSortString($_sort, $_reverse=false)
	{
		$_reverse=false;
		if (is_numeric($_sort))
		{
			switch($_sort) {
				case 2:
					$retValue = array(Horde_Imap_Client::SORT_FROM);
					break;
				case 4:
					$retValue = array(Horde_Imap_Client::SORT_TO);
					break;
				case 3:
					$retValue = array(Horde_Imap_Client::SORT_SUBJECT);
					break;
				case 6:
					$retValue = array(Horde_Imap_Client::SORT_SIZE);
					break;
				case 0:
				default:
					$retValue = array(Horde_Imap_Client::SORT_DATE);
					//$retValue = 'ARRIVAL';
					break;
			}
		}
		else
		{
			switch(strtoupper($_sort)) {
				case 'FROMADDRESS':
					$retValue = array(Horde_Imap_Client::SORT_FROM);
					break;
				case 'TOADDRESS':
					$retValue = array(Horde_Imap_Client::SORT_TO);
					break;
				case 'SUBJECT':
					$retValue = array(Horde_Imap_Client::SORT_SUBJECT);
					break;
				case 'SIZE':
					$retValue = array(Horde_Imap_Client::SORT_SIZE);
					break;
				case 'ARRIVAL':
					$retValue = array(Horde_Imap_Client::SORT_ARRIVAL);
					break;
				case 'UID': // should be equivalent to INTERNALDATE, which is ARRIVAL, which should be highest (latest) uid should be newest date
				case 'INTERNALDATE':
					$retValue = array(Horde_Imap_Client::SORT_SEQUENCE);
					break;
				case 'DATE':
				default:
					$retValue = array(Horde_Imap_Client::SORT_DATE);
					break;
			}
		}
		if ($_reverse) array_unshift($retValue,Horde_Imap_Client::SORT_REVERSE);
		//error_log(__METHOD__.' ('.__LINE__.') '.' '.($_reverse?'REVERSE ':'').$_sort.'->'.$retValue);
		return $retValue;
	}

	/**
	 * this function creates an IMAP filter from the criterias given
	 *
	 * @param string $_folder used to determine the search to TO or FROM on QUICK Search wether it is a send-folder or not
	 * @param array $_criterias contains the search/filter criteria
	 * @param boolean $_supportsOrInQuery wether to use the OR Query on QuickSearch
	 * @return Horde_Imap_Client_Search_Query the IMAP filter
	 */
	function createIMAPFilter($_folder, $_criterias, $_supportsOrInQuery=true)
	{
		$imapFilter = new Horde_Imap_Client_Search_Query();

		//_debug_array($_criterias);
		if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' Criterias:'.(!is_array($_criterias)?" none -> returning $all":array2string($_criterias)));
		if((!is_array($_criterias) || $_criterias['status']=='any') && (!isset($_criterias['string']) || empty($_criterias['string']))) {
			$imapFilter->flag('DELETED', $set=false);
			return $imapFilter;
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.print_r($_criterias, true));
		$imapSearchFilter = new Horde_Imap_Client_Search_Query();
		$queryValid = false;
		if(!empty($_criterias['string'])) {
			$criteria = strtoupper($_criterias['type']);
			switch ($criteria) {
				case 'QUICK':
					$imapSearchFilter->headerText('SUBJECT', $_criterias['string'], $not=false);
					$imapFilter2 = new Horde_Imap_Client_Search_Query();
					if($this->isSentFolder($_folder)) {
						$imapFilter2->headerText('TO', $_criterias['string'], $not=false);
					} else {
						$imapFilter2->headerText('FROM', $_criterias['string'], $not=false);
					}
					if ($_supportsOrInQuery)
					{
						$imapSearchFilter->orSearch($imapFilter2);
					}
					else
					{
						$imapSearchFilter->andSearch($imapFilter2);
					}
					$queryValid = true;
					break;
				case 'FROM':
				case 'TO':
				case 'CC':
				case 'BCC':
				case 'SUBJECT':
					$imapSearchFilter->headerText($criteria, $_criterias['string'], $not=false);
					$queryValid = true;
					break;
				case 'BODY':
				case 'TEXT':
					$imapSearchFilter->text($_criterias['string'],($criteria=='BODY'?true:false), $not=false);
					$queryValid = true;
					break;
				case 'SINCE':
					$imapSearchFilter->dateSearch(new DateTime($_criterias['string']), Horde_Imap_Client_Search_Query::DATE_SINCE, $header=true, $not=false);
					$queryValid = true;
					break;
				case 'BEFORE':
					$imapSearchFilter->dateSearch(new DateTime($_criterias['string']), Horde_Imap_Client_Search_Query::DATE_BEFORE, $header=true, $not=false);
					$queryValid = true;
					break;
				case 'ON':
					$imapSearchFilter->dateSearch(new DateTime($_criterias['string']), Horde_Imap_Client_Search_Query::DATE_ON, $header=true, $not=false);
					$queryValid = true;
					break;
			}
		}
		if ($queryValid) $imapFilter->andSearch($imapSearchFilter);
		$statusQueryValid = false;
		foreach((array)$_criterias['status'] as $k => $criteria) {
			$imapStatusFilter = new Horde_Imap_Client_Search_Query();
			$criteria = strtoupper($criteria);
			switch ($criteria) {
				case 'ANSWERED':
				case 'DELETED':
				case 'FLAGGED':
				case 'RECENT':
				case 'SEEN':
					$imapStatusFilter->flag($criteria, $set=true);
					$queryValid = $statusQueryValid =true;
					break;
				case 'READ':
					$imapStatusFilter->flag('SEEN', $set=true);
					$queryValid = $statusQueryValid =true;
					break;
				case 'LABEL1':
				case 'KEYWORD1':
				case 'LABEL2':
				case 'KEYWORD2':
				case 'LABEL3':
				case 'KEYWORD3':
				case 'LABEL4':
				case 'KEYWORD4':
				case 'LABEL5':
				case 'KEYWORD5':
					$imapStatusFilter->flag(str_ireplace('KEYWORD','$LABEL',$criteria), $set=true);
					$queryValid = $statusQueryValid =true;
					break;
				case 'NEW':
					$imapStatusFilter->flag('RECENT', $set=true);
					$imapStatusFilter->flag('SEEN', $set=false);
					$queryValid = $statusQueryValid =true;
					break;
				case 'OLD':
					$imapStatusFilter->flag('RECENT', $set=false);
					$queryValid = $statusQueryValid =true;
					break;
// operate only on system flags
//        $systemflags = array(
//            'ANSWERED', 'DELETED', 'DRAFT', 'FLAGGED', 'RECENT', 'SEEN'
//        );
				case 'UNANSWERED':
					$imapStatusFilter->flag('ANSWERED', $set=false);
					$queryValid = $statusQueryValid =true;
					break;
				case 'UNDELETED':
					$imapFilter->flag('DELETED', $set=false);
					$queryValid = true;
					break;
				case 'UNFLAGGED':
					$imapStatusFilter->flag('FLAGGED', $set=false);
					$queryValid = $statusQueryValid =true;
					break;
				case 'UNREAD':
				case 'UNSEEN':
					$imapStatusFilter->flag('SEEN', $set=false);
					$queryValid = $statusQueryValid =true;
					break;
				case 'UNLABEL1':
				case 'UNKEYWORD1':
				case 'UNLABEL2':
				case 'UNKEYWORD2':
				case 'UNLABEL3':
				case 'UNKEYWORD3':
				case 'UNLABEL4':
				case 'UNKEYWORD4':
				case 'UNLABEL5':
				case 'UNKEYWORD5':
					$imapStatusFilter->flag(str_ireplace(array('UNKEYWORD','UNLABEL'),'$LABEL',$criteria), $set=false);
					$queryValid = $statusQueryValid =true;
					break;
				default:
					$statusQueryValid = false;
			}
			if ($statusQueryValid) $imapFilter->andSearch($imapStatusFilter);
			if ($statusQueryValid && !$queryValid) $queryValid=true;
		}
		if (isset($_criterias['range']) && !empty($_criterias['range']))
		{
			//$imapFilter .= $_criterias['range'].' ';
		}
		if (self::$debug)
		{
			$query_str = $imapFilter->build();
			error_log(__METHOD__.' ('.__LINE__.') '.' '.$query_str['query']);
		}
		if($queryValid==false) {
			$imapFilter->flag('DELETED', $set=false);
			return $imapFilter;
		} else {
			return $imapFilter;
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
			$test = @json_encode($_string);
			//error_log(__METHOD__.__LINE__.' ->'.strlen($singleBodyPart['body']).' Error:'.json_last_error().'<- BodyPart:#'.$test.'#');
			if (($test=="null" || $test === false || is_null($test)) && strlen($_string)>0)
			{
				// try to fix broken utf8
				$x = utf8_encode($_string);
				$test = @json_encode($x);
				if (($test=="null" || $test === false || is_null($test)) && strlen($_string)>0)
				{
					// this should not be needed, unless something fails with charset detection/ wrong charset passed
					$_string = (function_exists('mb_convert_encoding')?mb_convert_encoding($_string,'UTF-8','UTF-8'):(function_exists('iconv')?@iconv("UTF-8","UTF-8//IGNORE",$_string):$_string));
				}
				else
				{
					$_string = $x;
				}
			}

			if ($_tryIDNConversion===true && stripos($_string,'@')!==false)
			{
				$rfcAddr = imap_rfc822_parse_adrlist(str_replace(',','\,',$_string),'');
				if (!isset(self::$idna2)) self::$idna2 = new egw_idna;
				$stringA = array();
				//$_string = str_replace($rfcAddr[0]->host,self::$idna2->decode($rfcAddr[0]->host),$_string);
				foreach ((array)$rfcAddr as $_rfcAddr)
				{
					if ($_rfcAddr->host=='.SYNTAX-ERROR.')
					{
						$stringA = array();
						break; // skip idna conversion if we encounter an error here
					}
					$stringA[] = imap_rfc822_write_address($_rfcAddr->mailbox,self::$idna2->decode($_rfcAddr->host),$_rfcAddr->personal);
				}
				if (!empty($stringA)) $_string = implode(',',$stringA);
			}
			if ($_tryIDNConversion==='FORCE')
			{
				//error_log(__METHOD__.' ('.__LINE__.') '.'->'.$_string.'='.self::$idna2->decode($_string));
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
		// make sure its utf-8
		$test = @json_encode($_string);
		if (($test=="null" || $test === false || is_null($test)) && strlen($_string)>0)
		{
			$_string = utf8_encode($_string);
		}
		return $_string;

	}

	/**
	 * decodeEntityFolderName - remove html entities
	 * @param string _folderName the foldername
	 * @return string the converted string
	 */
	function decodeEntityFolderName($_folderName)
	{
		return html_entity_decode($_folderName, ENT_QUOTES, self::$displayCharset);
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
		if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '."->"."$_parent, $_folderName, $_subscribe");
		$parent		= $_parent;//$this->_encodeFolderName($_parent);
		$folderName	= $_folderName;//$this->_encodeFolderName($_folderName);

		if(empty($parent)) {
			$newFolderName = $folderName;
		} else {
			$HierarchyDelimiter = $this->getHierarchyDelimiter();
			$newFolderName = $parent . $HierarchyDelimiter . $folderName;
		}
		if (empty($newFolderName)) return false;
		if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.'->'.$newFolderName);
		if ($this->folderExists($newFolderName,true))
		{
			if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '." Folder $newFolderName already exists.");
			return $newFolderName;
		}
		try
		{
			$rv = $this->icServer->createMailbox($newFolderName);
		}
		catch (Exception $e)
		{
			error_log(__METHOD__.' ('.__LINE__.') '.' create Folder '.$newFolderName.'->'.$e->getMessage().' Namespace:'.array2string($this->icServer->getNameSpaces()).function_backtrace());
			return false;
		}
		try
		{
			$srv = $this->icServer->subscribeMailbox($newFolderName);
		}
		catch (Exception $e)
		{
			error_log(__METHOD__.' ('.__LINE__.') '.' subscribe to new folder '.$newFolderName.'->'.$e->getMessage());
			return false;
		}

		return $newFolderName;
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
		$oldFolderName	= $_oldFolderName;//$this->_encodeFolderName($_oldFolderName);
		$parent		= $_parent;//$this->_encodeFolderName($_parent);
		$folderName	= $_folderName;//$this->_encodeFolderName($_folderName);

		if(empty($parent)) {
			$newFolderName = $folderName;
		} else {
			$HierarchyDelimiter = $this->getHierarchyDelimiter();
			$newFolderName = $parent . $HierarchyDelimiter . $folderName;
		}
		if (self::$debug) error_log("create folder: $newFolderName");
		try
		{
			$rv = $this->icServer->renameMailbox($oldFolderName, $newFolderName);
		}
		catch (Exception $e)
		{
			throw new egw_exception(__METHOD__." failed for $oldFolderName (rename to: $newFolderName) with error:".$e->getMessage());;
		}
		// clear FolderExistsInfoCache
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderInfo,$expiration=60*60*5);

		return $newFolderName;

	}

	/**
	 * delete an existing folder
	 *
	 * @param string _folderName the name of the folder to be deleted
	 *
	 * @return bool true on success, PEAR Error on failure
	 */
	function deleteFolder($_folderName)
	{
		//$folderName = $this->_encodeFolderName($_folderName);
		try
		{
			$this->icServer->subscribeMailbox($_folderName,false);
			$this->icServer->deleteMailbox($_folderName);
		}
		catch (Exception $e)
		{
			throw new egw_exception("Deleting Folder $_foldername failed! Error:".$e->getMessage());;
		}
		// clear FolderExistsInfoCache
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderInfo,$expiration=60*60*5);

		return true;
	}

	/**
	 * fetchUnSubscribedFolders: get unsubscribed IMAP folder list
	 *
	 * returns an array of unsubscribed IMAP folder names.
	 *
	 * @return array with folder names. eg.: 1 => INBOX/TEST
	 */
	function fetchUnSubscribedFolders()
	{
		$unSubscribedMailboxes = $this->icServer->listUnSubscribedMailboxes();
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($unSubscribedMailboxes));
		return $unSubscribedMailboxes;
	}

	/**
	 * get IMAP folder objects
	 *
	 * returns an array of IMAP folder objects. Put INBOX folder in first
	 * position. Preserves the folder seperator for later use. The returned
	 * array is indexed using the foldername. Use cachedObjects when retrieving subscribedFolders
	 *
	 * @param boolean _subscribedOnly  get subscribed or all folders
	 * @param boolean _getCounters   get get messages counters
	 * @param boolean _alwaysGetDefaultFolders  this triggers to ignore the possible notavailableautofolders - preference
	 *			as activeSync needs all folders like sent, trash, drafts, templates and outbox - if not present devices may crash
	 *			-> autoFolders should be created if needed / accessed (if possible and configured)
	 * @param boolean _useCacheIfPossible  - if set to false cache will be ignored and reinitialized
	 *
	 * @return array with folder objects. eg.: INBOX => {inbox object}
	 */
	function getFolderObjects($_subscribedOnly=false, $_getCounters=false, $_alwaysGetDefaultFolders=false,$_useCacheIfPossible=true)
	{
		if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' ServerID:'.$this->icServer->ImapServerId.", subscribedOnly:$_subscribedOnly, getCounters:$_getCounters, alwaysGetDefaultFolders:$_alwaysGetDefaultFolders, _useCacheIfPossible:$_useCacheIfPossible");
		if (self::$debugTimes) $starttime = microtime (true);
		static $folders2return;
		//$_subscribedOnly=false;
		// always use static on single request if info is available;
		// so if you require subscribed/unsubscribed results on a single request you MUST
		// set $_useCacheIfPossible to false !
		if ($_useCacheIfPossible && isset($folders2return[$this->icServer->ImapServerId]) && !empty($folders2return[$this->icServer->ImapServerId]))
		{
			if (self::$debugTimes) self::logRunTimes($starttime,null,'using static',__METHOD__.' ('.__LINE__.') ');
			return $folders2return[$this->icServer->ImapServerId];
		}

		if ($_subscribedOnly && $_getCounters===false)
		{
			if (is_null($folders2return)) $folders2return = egw_cache::getCache(egw_cache::INSTANCE,'email','folderObjects'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
			if ($_useCacheIfPossible && isset($folders2return[$this->icServer->ImapServerId]) && !empty($folders2return[$this->icServer->ImapServerId]))
			{
				//error_log(__METHOD__.' ('.__LINE__.') '.' using Cached folderObjects'.array2string($folders2return[$this->icServer->ImapServerId]));
				if (self::$debugTimes) self::logRunTimes($starttime,null,'from Cache',__METHOD__.' ('.__LINE__.') ');
				return $folders2return[$this->icServer->ImapServerId];
			}
		}
		// use $folderBasicInfo for holding attributes and other basic folderinfo $folderBasicInfo[$this->icServer->ImapServerId]
		static $folderBasicInfo;
		if (is_null($folderBasicInfo)||!isset($folderBasicInfo[$this->icServer->ImapServerId])) $folderBasicInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*1);
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string(array_keys($folderBasicInfo[$this->icServer->ImapServerId])));
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
			$inboxData->counter = $this->getMailBoxCounters('INBOX');
		}
		// force unsubscribed by preference showAllFoldersInFolderPane
		if ($_subscribedOnly == true &&
			isset($this->mailPreferences['showAllFoldersInFolderPane']) &&
			$this->mailPreferences['showAllFoldersInFolderPane']==1)
		{
			$_subscribedOnly = false;
		}
		#$inboxData->attributes = 64;
		$inboxFolderObject = array('INBOX' => $inboxData);
		#_debug_array($folders);

		//$nameSpace = $this->icServer->getNameSpaces();
		$nameSpace = $this->_getNameSpaces();
		//error_log(__METHOD__.__LINE__.array2string($nameSpace));
		//_debug_array($nameSpace);
		//_debug_array($delimiter);
		if (is_array($nameSpace))
		{
			foreach($nameSpace as $k => $singleNameSpace) {
				$type = $singleNameSpace['type'];
				$prefix_present = $singleNameSpace['prefix_present'];
				// the following line (assumption that for the same namespace the delimiter should be equal) may be wrong
				$foldersNameSpace[$type]['delimiter']  = $singleNameSpace['delimiter'];

				if(is_array($singleNameSpace)) {
					// fetch and sort the subscribed folders
					// we alway fetch the subscribed, as this provides the only way to tell
					// if a folder is subscribed or not
					if ($_subscribedOnly == true);
					{
						try
						{
							$subscribedMailboxes = $this->icServer->listSubscribedMailboxes($singleNameSpace['prefix'],0,true);
							if (empty($subscribedMailboxes) && $type == 'shared')
							{
								$subscribedMailboxes = $this->icServer->listSubscribedMailboxes('',0,true);
							}
						}
						catch(Exception $e)
						{
							continue;
						}
						//echo "subscribedMailboxes";_debug_array($subscribedMailboxes);
						$subscribedFoldersPerNS = (!empty($subscribedMailboxes)?array_keys($subscribedMailboxes):array());
						//if (is_array($foldersNameSpace[$type]['subscribed'])) sort($foldersNameSpace[$type]['subscribed']);
						//_debug_array($foldersNameSpace);
						//error_log(__METHOD__.__LINE__.array2string($singleNameSpace).':#:'.array2string($subscribedFoldersPerNS));
						if (!empty($subscribedFoldersPerNS) && !empty($subscribedMailboxes))
						{
							//error_log(__METHOD__.' ('.__LINE__.') '." $type / subscribed:". array2string($subscribedMailboxes));
							foreach ($subscribedMailboxes as $k => $finfo)
							{
								//error_log(__METHOD__.__LINE__.$k.':#:'.array2string($finfo));
								$subscribedFoldersForCache[$this->icServer->ImapServerId][$k]=
								$folderBasicInfo[$this->icServer->ImapServerId][$k]=array(
									'MAILBOX'=>$finfo['MAILBOX'],
									'ATTRIBUTES'=>$finfo['ATTRIBUTES'],
									'delimiter'=>$finfo['delimiter'],//lowercase for some reason???
									'SUBSCRIBED'=>$mbx['SUBSCRIBED'],//seeded by getMailboxes
								);
								if (empty($foldersNameSpace[$type]['subscribed']) || !in_array($k,$foldersNameSpace[$type]['subscribed']))
								{
									$foldersNameSpace[$type]['subscribed'][] = $k;
								}
								if (empty($foldersNameSpace[$type]['all']) || !in_array($k,$foldersNameSpace[$type]['all']))
								{
									$foldersNameSpace[$type]['all'][] = $k;
								}
							}
						}
						//error_log(__METHOD__.' ('.__LINE__.') '.' '.$type.'->'.array2string($foldersNameSpace[$type]['subscribed']));
						if (!is_array($foldersNameSpace[$type]['all'])) $foldersNameSpace[$type]['all'] = array();
						if ($_subscribedOnly == true && !empty($foldersNameSpace[$type]['subscribed'])) {
							continue;
						}

					}
					// skip the checks here completely; we rely on Hordes code/results as for now
					// this improves speed tremendously for the !$_subscribedOnly - mode
					/*
					// only check for Folder in FolderMaintenance for Performance Reasons
					if(!$_subscribedOnly) {
						foreach ((array)$foldersNameSpace[$type]['subscribed'] as $folderName)
						{
							if ($singleNameSpace['prefix'] == $folderName || $singleNameSpace['prefix'] == $folderName.$singleNameSpace['delimiter']) continue;
							//echo __METHOD__."Checking $folderName for existence<br>";
							if (!$this->folderExists($folderName,true)) {
								//echo("eMail Folder $folderName failed to exist; should be unsubscribed; Trying ...");
								if ($this->subscribe($folderName, false))
								{
									$r = " success.";
								} else {
									$r = " failed.";
								}
								error_log(__METHOD__."-> $folderName in NS: $type failed to be here; should be unsubscribed....".$r);
							}
						}
					}
					*/

					// fetch and sort all folders
					//echo $type.'->'.$singleNameSpace['prefix'].'->'.($type=='shared'?0:2)."<br>";
					try
					{
						// calling with 2 lists all mailboxes on that level with fetches all
						// we switch to all, to avoid further calls for subsequent levels
						// that may produce problems, when encountering recursions probably
						// horde is handling that, so we do not; keep that in mind!
						//$allMailboxesExt = $this->icServer->getMailboxes($singleNameSpace['prefix'],2,true);
						$allMailboxesExt = $this->icServer->getMailboxes($singleNameSpace['prefix'],0,true);
					}
					catch (Exception $e)
					{
						error_log(__METHOD__.' ('.__LINE__.') '.' Failed to retrieve all Boxes:'.$e->getMessage());
						$allMailboxesExt = array();
					}
/*
					if (empty($allMailboxesExt) && $type == 'shared')
					{
						try
						{
							$allMailboxesExt = $this->icServer->getMailboxes('',0,true);
						}
						catch (Exception $e)
						{
							$allMailboxesExt = array();
						}
					}
					else
					{
						if ($prefix_present=='forced' && $type=='personal') // you cannot trust dovecots assumed prefix
						{
							try
							{
								$allMailboxesExtAll = $this->icServer->getMailboxes('',0,true);
							}
							catch (Exception $e)
							{
								$allMailboxesExtAll=array();
							}
							foreach ($allMailboxesExtAll as $kaMEA => $aMEA)
							{
								if (!in_array($aMEA,$allMailboxesExt)) $allMailboxesExt[] = $aMEA;
							}
						}
					}
					$allMailBoxesExtSorted = array();
*/
					if (!is_array($allMailboxesExt))
					{
						//error_log(__METHOD__.' ('.__LINE__.') '.' Expected Array but got:'.array2string($allMailboxesExt). 'Type:'.$type.' Prefix:'.$singleNameSpace['prefix']);
						continue;
						//$allMailboxesExt=array();
					}

					//error_log(__METHOD__.' ('.__LINE__.') '.' '.$type.'->'.array2string($allMailboxesExt));
					foreach ($allMailboxesExt as $mbx) {
						if (!isset($folderBasicInfo[$this->icServer->ImapServerId][$mbx['MAILBOX']]))
						{
							$folderBasicInfo[$this->icServer->ImapServerId][$mbx['MAILBOX']]=array(
								'MAILBOX'=>$mbx['MAILBOX'],
								'ATTRIBUTES'=>$mbx['ATTRIBUTES'],
								'delimiter'=>$mbx['delimiter'],//lowercase for some reason???
								'SUBSCRIBED'=>$mbx['SUBSCRIBED'],//seeded by getMailboxes
							);
							if ($mbx['SUBSCRIBED'] && !isset($subscribedFoldersForCache[$this->icServer->ImapServerId][$mbx['MAILBOX']]))
							{
								$subscribedFoldersForCache[$this->icServer->ImapServerId][$mbx['MAILBOX']] = $folderBasicInfo[$this->icServer->ImapServerId][$mbx['MAILBOX']];
							}
						}
						if ($mbx['SUBSCRIBED'] && (empty($foldersNameSpace[$type]['subscribed']) || !in_array($mbx['MAILBOX'],$foldersNameSpace[$type]['subscribed'])))
						{
							$foldersNameSpace[$type]['subscribed'][] = $mbx['MAILBOX'];
						}
						//echo __METHOD__;_debug_array($mbx);
						//error_log(__METHOD__.' ('.__LINE__.') '.array2string($mbx));
						if (isset($allMailBoxesExtSorted[$mbx['MAILBOX']])||
							isset($allMailBoxesExtSorted[$mbx['MAILBOX'].$foldersNameSpace[$type]['delimiter']])||
							(substr($mbx['MAILBOX'],-1)==$foldersNameSpace[$type]['delimiter'] && isset($allMailBoxesExtSorted[substr($mbx['MAILBOX'],0,-1)]))
						) continue;

						//echo '#'.$mbx['MAILBOX'].':'.array2string($mbx)."#<br>";
						$allMailBoxesExtSorted[$mbx['MAILBOX']] = $mbx;
					}
					if (is_array($allMailBoxesExtSorted)) ksort($allMailBoxesExtSorted);
					//_debug_array(array_keys($allMailBoxesExtSorted));
					$allMailboxes = array();
					foreach ((array)$allMailBoxesExtSorted as $mbx) {
						//echo $mbx['MAILBOX']."<br>";
						// this is not used when we are callinglistMailboxes with $restriction_search = 0
						// this lists all mailboxes so fetching mailboxes for each level would be obsolete
						/*
						if (in_array('\HasChildren',$mbx["ATTRIBUTES"]) || in_array('\Haschildren',$mbx["ATTRIBUTES"]) || in_array('\haschildren',$mbx["ATTRIBUTES"])) {
							unset($buff);
							if (!in_array($mbx['MAILBOX'],$allMailboxes)) $buff = self::getMailBoxesRecursive($mbx['MAILBOX'],$delimiter,$foldersNameSpace[$type]['prefix'],1);
							if( PEAR::isError($buff) ) {
								continue;
							}
							#_debug_array($buff);
							if (is_array($buff)) $allMailboxes = array_merge($allMailboxes,$buff);
						}
						*/
						if (!in_array($mbx['MAILBOX'],$allMailboxes)) $allMailboxes[] = $mbx['MAILBOX'];
						//echo "Result:";_debug_array($allMailboxes);
					}
					$foldersNameSpace[$type]['all'] = $allMailboxes;
					if (is_array($foldersNameSpace[$type]['all'])) sort($foldersNameSpace[$type]['all']);
				}
			}
/*
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
				if (!$_alwaysGetDefaultFolders && $this->mailPreferences['notavailableautofolders'] && !empty($this->mailPreferences['notavailableautofolders']))
				{
					$foldersToCheck = array_diff(self::$autoFolders,explode(',',$this->mailPreferences['notavailableautofolders']));
				} else {
					$foldersToCheck = self::$autoFolders;
				}
				//error_log(__METHOD__.' ('.__LINE__.') '." foldersToCheck:".array2string($foldersToCheck));
				//error_log(__METHOD__.' ('.__LINE__.') '." foldersToCheck:".array2string( $this->mailPreferences['sentFolder']));
				foreach($foldersToCheck as $personalFolderName) {
					$folderName = (!empty($personalPrefix) ? $folderPrefix.$personalFolderName : $personalFolderName);
					//error_log(__METHOD__.' ('.__LINE__.') '." foldersToCheck: $personalFolderName / $folderName");
					if(!is_array($foldersNameSpace['personal']['all']) || !in_array($folderName, $foldersNameSpace['personal']['all'])) {
						$createfolder = true;
						switch($personalFolderName)
						{
							case 'Drafts': // => Entwürfe
								$draftFolder = $this->getDraftFolder();
								if ($draftFolder && $draftFolder=='none')
									$createfolder=false;
								break;
							case 'Junk': //] => Spammails
								$junkFolder = $this->getJunkFolder();
								if ($junkFolder && $junkFolder=='none')
									$createfolder=false;
								break;
							case 'Sent': //] => Gesendet
								// ToDo: we may need more sophistcated checking here
								$sentFolder = $this->getSentFolder();
								if ($sentFolder && $sentFolder=='none')
									$createfolder=false;
								break;
							case 'Trash': //] => Papierkorb
								$trashFolder = $this->getTrashFolder();
								if ($trashFolder && $trashFolder=='none')
									$createfolder=false;
								break;
							case 'Templates': //] => Vorlagen
								$templateFolder = $this->getTemplateFolder();
								if ($templateFolder && $templateFolder=='none')
									$createfolder=false;
								break;
							case 'Outbox': // Nokia Outbox for activesync
								//if ($this->mailPreferences['outboxFolder'] && $this->mailPreferences['outboxFolder']=='none')
									$createfolder=false;
								if ($GLOBALS['egw_info']['user']['apps']['activesync']) $createfolder = true;
								break;
						}
						// check for the foldername as constructed with prefix (or not)
						if ($createfolder && $this->folderExists($folderName))
						{
							$createfolder = false;
						}
						// check for the folder as it comes (no prefix)
						if ($createfolder && $personalFolderName != $folderName && $this->folderExists($personalFolderName))
						{
							$createfolder = false;
							$folderName = $personalFolderName;
						}
						// check for the folder as it comes with INBOX prefixed
						$folderWithInboxPrefixed = $folderPrefixAsInbox.$personalFolderName;
						if ($createfolder && $folderWithInboxPrefixed != $folderName && $this->folderExists($folderWithInboxPrefixed))
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
*/
		}
		//subscribed folders may be used in getFolderStatus
		egw_cache::setCache(egw_cache::INSTANCE,'email','subscribedFolders'.trim($GLOBALS['egw_info']['user']['account_id']),$subscribedFoldersForCache,$expiration=60*60*1);
		//echo "<br>FolderNameSpace To Process:";_debug_array($foldersNameSpace);
		$autoFolderObjects = array();
		foreach( array('personal', 'others', 'shared') as $type) {
			if(isset($foldersNameSpace[$type])) {
				if($_subscribedOnly) {
					if( !empty($foldersNameSpace[$type]['subscribed']) ) $listOfFolders = $foldersNameSpace[$type]['subscribed'];
				} else {
					if( !empty($foldersNameSpace[$type]['all'])) $listOfFolders = $foldersNameSpace[$type]['all'];
				}
				foreach((array)$listOfFolders as $folderName) {
					//echo "<br>FolderToCheck:$folderName<br>";
					//error_log(__METHOD__.__LINE__.'#Delimiter:'.$delimiter.':#'.$folderName);
					if($_subscribedOnly && !(in_array($folderName, $foldersNameSpace[$type]['all'])||in_array($folderName.$foldersNameSpace[$type]['delimiter'], $foldersNameSpace[$type]['all']))) {
						#echo "$folderName failed to be here <br>";
						continue;
					}
					if (isset($folders[$folderName])) continue;
					if (isset($autoFolderObjects[$folderName])) continue;
					if (empty($delimiter)||$delimiter != $foldersNameSpace[$type]['delimiter']) $delimiter = $foldersNameSpace[$type]['delimiter'];
					$folderParts = explode($delimiter, $folderName);
					$shortName = array_pop($folderParts);

					$folderObject = new stdClass;
					$folderObject->delimiter	= $delimiter;
					$folderObject->folderName	= $folderName;
					$folderObject->shortFolderName	= $shortName;
					if(!$_subscribedOnly) {
						#echo $folderName."->".$type."<br>";
						#_debug_array($foldersNameSpace[$type]['subscribed']);
						$folderObject->subscribed = in_array($folderName, (array)$foldersNameSpace[$type]['subscribed']);
					}

					if($_getCounters == true) {
						//error_log(__METHOD__.' ('.__LINE__.') '.' getCounter forFolder:'.$folderName);
						$folderObject->counter = $this->getMailBoxCounters($folderName);
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
						$folderObject->displayName = $folderObject->folderName;
						$folderObject->shortDisplayName = $shortName;
					}
					//$folderName = $folderName;
					if (in_array($shortName,self::$autoFolders)&&self::searchValueInFolderObjects($shortName,$autoFolderObjects)===false) {
						$autoFolderObjects[$folderName] = $folderObject;
					} else {
						$folders[$folderName] = $folderObject;
					}
					//error_log(__METHOD__.' ('.__LINE__.') '.':'.$folderObject->folderName);
				}
			}
		}
		if (is_array($autoFolderObjects) && !empty($autoFolderObjects)) {
			uasort($autoFolderObjects,array($this,"sortByAutoFolderPos"));
		}
		if (is_array($folders)) uasort($folders,array($this,"sortByDisplayName"));
		//$folders2return = array_merge($autoFolderObjects,$folders);
		//_debug_array($folders2return); #exit;
		$folders2return[$this->icServer->ImapServerId] = array_merge((array)$inboxFolderObject,(array)$autoFolderObjects,(array)$folders);
		if (($_subscribedOnly && $_getCounters===false) ||
			($_subscribedOnly == false && $_getCounters===false &&
			isset($this->mailPreferences['showAllFoldersInFolderPane']) &&
			$this->mailPreferences['showAllFoldersInFolderPane']==1))
		{
			egw_cache::setCache(egw_cache::INSTANCE,'email','folderObjects'.trim($GLOBALS['egw_info']['user']['account_id']),$folders2return,$expiration=60*60*1);
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','folderBasicInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderBasicInfo,$expiration=60*60*1);
		if (self::$debugTimes) self::logRunTimes($starttime,null,function_backtrace(),__METHOD__.' ('.__LINE__.') ');
		return $folders2return[$this->icServer->ImapServerId];
	}

	/**
	 * search Value In FolderObjects
	 *
	 * Helper function to search for a specific value within the foldertree objects
	 * @param string $needle
	 * @param array $haystack, array of folderobjects
	 * @return MIXED false or key
	 */
	static function searchValueInFolderObjects($needle, $haystack)
	{
		$rv = false;
		foreach ($haystack as $k => $v)
		{
			foreach($v as $sk => $sv) if (trim($sv)==trim($needle)) return $k;
		}
		return $rv;
	}

	/**
	 * sortByDisplayName
	 *
	 * Helper function to sort folder-objects by displayname
	 * @param object $a
	 * @param object $b, array of folderobjects
	 * @return int expect values (0, 1 or -1)
	 */
	function sortByDisplayName($a,$b)
	{
		// 0, 1 und -1
		return strcasecmp($a->displayName,$b->displayName);
	}

	/**
	 * sortByAutoFolderPos
	 *
	 * Helper function to sort folder-objects by auto Folder Position
	 * @param object $a
	 * @param object $b, array of folderobjects
	 * @return int expect values (0, 1 or -1)
	 */
	function sortByAutoFolderPos($a,$b)
	{
		// 0, 1 und -1
		$pos1 = array_search(trim($a->shortFolderName),self::$autoFolders);
		$pos2 = array_search(trim($b->shortFolderName),self::$autoFolders);
		if ($pos1 == $pos2) return 0;
		return ($pos1 < $pos2) ? -1 : 1;
	}

	/**
	 * getMailBoxCounters
	 *
	 * function to retrieve the counters for a given folder
	 * @param string $folderName
	 * @param boolean $_returnObject return the counters as object rather than an array
	 * @return mixed false or array of counters array(MESSAGES,UNSEEN,RECENT,UIDNEXT,UIDVALIDITY) or object
	 */
	function getMailBoxCounters($folderName,$_returnObject=true)
	{
		try
		{
			$folderStatus = $this->icServer->getMailboxCounters($folderName);
			//error_log(__METHOD__.' ('.__LINE__.') '.$folderName.": FolderStatus:".array2string($folderStatus).function_backtrace());
		}
		catch (Exception $e)
		{
			if (self::$debug) error_log(__METHOD__." returned FolderStatus for Folder $folderName:".$e->getMessage());
			return false;
		}
		if(is_array($folderStatus)) {
			if ($_returnObject===false) return $folderStatus;
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

	/**
	 * getMailBoxesRecursive
	 *
	 * function to retrieve mailboxes recursively from given mailbox
	 * @param string $_mailbox
	 * @param string $delimiter
	 * @param string $prefix
	 * @param string $reclevel 0, counter to keep track of the current recursionlevel
	 * @return array of mailboxes
	 */
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
		$mbxkeys = array_keys($mbx);
		#_debug_array($mbx);
//error_log(__METHOD__.' ('.__LINE__.') '.' Delimiter:'.array2string($delimiter));
//error_log(__METHOD__.' ('.__LINE__.') '.array2string($mbx));
		// Example: Array([INBOX/GaGa] => Array([MAILBOX] => INBOX/GaGa[ATTRIBUTES] => Array([0] => \\unmarked)[delimiter] => /))
		if (is_array($mbx[$mbxkeys[0]]["ATTRIBUTES"]) && (in_array('\HasChildren',$mbx[$mbxkeys[0]]["ATTRIBUTES"]) || in_array('\Haschildren',$mbx[$mbxkeys[0]]["ATTRIBUTES"]) || in_array('\haschildren',$mbx[$mbxkeys[0]]["ATTRIBUTES"]))) {
			// if there are children fetch them
			//echo $mbx[$mbxkeys[0]]['MAILBOX']."<br>";
			unset($buff);
			$buff = $this->icServer->getMailboxes($mbx[$mbxkeys[0]]['MAILBOX'].($mbx[$mbxkeys[0]]['MAILBOX'] == $prefix ? '':$delimiter),2,false);
			//$buff = $this->icServer->getMailboxes($mbx[$mbxkeys[0]]['MAILBOX'],2,false);
			//_debug_array($buff);
			$allMailboxes = array();
			foreach ($buff as $mbxname) {
//error_log(__METHOD__.' ('.__LINE__.') '.array2string($mbxname));
				$mbxname = preg_replace('~'.($delimiter == '.' ? "\\".$delimiter:$delimiter).'+~s',$delimiter,$mbxname['MAILBOX']);
				#echo "About to recur in level $reclevel:".$mbxname."<br>";
				if ( $mbxname != $mbx[$mbxkeys[0]]['MAILBOX'] && $mbxname != $prefix  && $mbxname != $mbx[$mbxkeys[0]]['MAILBOX'].$delimiter)
				{
					$allMailboxes = array_merge($allMailboxes, self::getMailBoxesRecursive($mbxname, $delimiter, $prefix, $reclevel));
				}
			}
			if (!(in_array('\NoSelect',$mbx[$mbxkeys[0]]["ATTRIBUTES"]) || in_array('\Noselect',$mbx[$mbxkeys[0]]["ATTRIBUTES"]) || in_array('\noselect',$mbx[$mbxkeys[0]]["ATTRIBUTES"]))) $allMailboxes[] = $mbx[$mbxkeys[0]]['MAILBOX'];
			return $allMailboxes;
		} else {
			return array($_mailbox);
		}
	}

	/**
	 * _getSpecialUseFolder
	 * abstraction layer for getDraftFolder, getTemplateFolder, getTrashFolder and getSentFolder
	 * @param string $type the type to fetch (Drafts|Template|Trash|Sent)
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return mixed string or false
	 */
	function _getSpecialUseFolder($_type, $_checkexistance=TRUE)
	{
		static $types = array(
			'Drafts'=>array('prefName'=>'draftFolder','profileKey'=>'acc_folder_draft','autoFolderName'=>'Drafts'),
			'Template'=>array('prefName'=>'templateFolder','profileKey'=>'acc_folder_template','autoFolderName'=>'Templates'),
			'Trash'=>array('prefName'=>'trashFolder','profileKey'=>'acc_folder_trash','autoFolderName'=>'Trash'),
			'Sent'=>array('prefName'=>'sentFolder','profileKey'=>'acc_folder_sent','autoFolderName'=>'Sent'),
			'Junk'=>array('prefName'=>'junkFolder','profileKey'=>'acc_folder_junk','autoFolderName'=>'Junk'),
			'Outbox'=>array('prefName'=>'outboxFolder','profileKey'=>'acc_folder_outbox','autoFolderName'=>'Outbox'),
		);
		if (!isset($types[$_type]))
		{
			error_log(__METHOD__.' ('.__LINE__.') '.' '.$_type.' not supported for '.__METHOD__);
			return false;
		}
		if (is_null(self::$specialUseFolders) || empty(self::$specialUseFolders)) self::$specialUseFolders = $this->getSpecialUseFolders();

		//highest precedence
		try
		{
			$_folderName = $this->icServer->$types[$_type]['profileKey'];
		}
		catch (Exception $e)
		{
			// we know that outbox is not supported, but we use this here, as we autocreate expected SpecialUseFolders in this function
			if ($_type != 'Outbox') error_log(__METHOD__.' ('.__LINE__.') '.' Failed to retrieve Folder'.$_folderName." for ".array2string($types[$_type]).":".$e->getMessage());
			$_folderName = false;
		}
		// does the folder exist??? (is configured/preset, but non-existent)
		if ($_folderName && $_checkexistance && $_folderName !='none' && !$this->folderExists($_folderName,true)) {
			try
			{
				$this->createFolder('', $_folderName, true);
			}
			catch(Exception $e)
			{
				error_log(__METHOD__.' ('.__LINE__.') '.' Failed to create Folder '.$_folderName." for $_type:".$e->getMessage());
				$_folderName = false;
			}
		}
		// not sure yet if false is the correct behavior on none
		if ($_folderName =='none') return false;
		//no (valid) folder found yet; try specialUseFolders
		if (empty($_folderName) && is_array(self::$specialUseFolders) && ($f = array_search($_type,self::$specialUseFolders))) $_folderName = $f;
		//no specialUseFolder; try some Defaults
		if (empty($_folderName) && isset($types[$_type]))
		{
			$nameSpace = $this->_getNameSpaces();
			$prefix='';
			foreach ($nameSpace as $nSp)
			{
				if ($nSp['type']=='personal')
				{
					//error_log(__METHOD__.__LINE__.array2string($nSp));
					$prefix = $nSp['prefix'];
					break;
				}
			}
			if ($this->folderExists($prefix.$types[$_type]['autoFolderName'],true))
			{
				$_folderName = $prefix.$types[$_type]['autoFolderName'];
			}
			else
			{
				try
				{
					$this->createFolder('', $prefix.$types[$_type]['autoFolderName'], true);
					$_folderName = $prefix.$types[$_type]['autoFolderName'];
				}
				catch(Exception $e)
				{
					error_log(__METHOD__.' ('.__LINE__.') '.' Failed to create Folder '.$_folderName." for $_type:".$e->getMessage());
					$_folderName = false;
				}
			}
		}
		return $_folderName;
	}

	/**
	 * getDraftFolder wrapper for _getSpecialUseFolder Type Drafts
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return mixed string or false
	 */
	function getJunkFolder($_checkexistance=TRUE)
	{
		return $this->_getSpecialUseFolder('Junk', $_checkexistance);
	}

	/**
	 * getDraftFolder wrapper for _getSpecialUseFolder Type Drafts
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return mixed string or false
	 */
	function getDraftFolder($_checkexistance=TRUE)
	{
		return $this->_getSpecialUseFolder('Drafts', $_checkexistance);
	}

	/**
	 * getTemplateFolder wrapper for _getSpecialUseFolder Type Template
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return mixed string or false
	 */
	function getTemplateFolder($_checkexistance=TRUE)
	{
		return $this->_getSpecialUseFolder('Template', $_checkexistance);
	}

	/**
	 * getTrashFolder wrapper for _getSpecialUseFolder Type Trash
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return mixed string or false
	 */
	function getTrashFolder($_checkexistance=TRUE)
	{
		return $this->_getSpecialUseFolder('Trash', $_checkexistance);
	}

	/**
	 * getSentFolder wrapper for _getSpecialUseFolder Type Sent
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return mixed string or false
	 */
	function getSentFolder($_checkexistance=TRUE)
	{
		return $this->_getSpecialUseFolder('Sent', $_checkexistance);
	}

	/**
	 * getOutboxFolder wrapper for _getSpecialUseFolder Type Outbox
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return mixed string or false
	 */
	function getOutboxFolder($_checkexistance=TRUE)
	{
		return $this->_getSpecialUseFolder('Outbox', $_checkexistance);
	}

	/**
	 * isSentFolder is the given folder the sent folder or at least a subfolder of it
	 * @param string $_foldername, folder to perform the check on
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return boolean
	 */
	function isSentFolder($_folderName, $_checkexistance=TRUE)
	{
		$sentFolder = $this->getSentFolder($_checkexistance);
		if(empty($sentFolder)) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !$this->folderExists($_folderName)) {
			return false;
		}

		if(false !== stripos($_folderName, $sentFolder)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * checks if the Outbox folder exists and is part of the foldername to be checked
	 * @param string $_foldername, folder to perform the check on
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return boolean
	 */
	function isOutbox($_folderName, $_checkexistance=TRUE)
	{
		if (stripos($_folderName, 'Outbox')===false) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && $GLOBALS['egw_info']['user']['apps']['activesync'] && !$this->folderExists($_folderName)) {
			$outboxFolder = $this->getOutboxFolder($_checkexistance);
			if(false !== stripos($_folderName, $outboxFolder)) {
				return true;
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * isDraftFolder is the given folder the sent folder or at least a subfolder of it
	 * @param string $_foldername, folder to perform the check on
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return boolean
	 */
	function isDraftFolder($_folderName, $_checkexistance=TRUE)
	{
		$draftFolder = $this->getDraftFolder($_checkexistance);
		if(empty($draftFolder)) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !$this->folderExists($_folderName)) {
			return false;
		}

		if(false !== stripos($_folderName, $draftFolder)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * isTrashFolder is the given folder the sent folder or at least a subfolder of it
	 * @param string $_foldername, folder to perform the check on
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return boolean
	 */
	function isTrashFolder($_folderName, $_checkexistance=TRUE)
	{
		$trashFolder = $this->getTrashFolder($_checkexistance);
		if(empty($trashFolder)) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !$this->folderExists($_folderName)) {
			return false;
		}

		if(false !== stripos($_folderName, $trashFolder)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * isTemplateFolder is the given folder the sent folder or at least a subfolder of it
	 * @param string $_foldername, folder to perform the check on
	 * @param boolean $_checkexistance, trigger check for existance
	 * @return boolean
	 */
	function isTemplateFolder($_folderName, $_checkexistance=TRUE)
	{
		$templateFolder = $this->getTemplateFolder($_checkexistance);
		if(empty($templateFolder)) {
			return false;
		}
		// does the folder exist???
		if ($_checkexistance && !$this->folderExists($_folderName)) {
			return false;
		}

		if(false !== stripos($_folderName, $templateFolder)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * folderExists checks for existance of a given folder
	 * @param string $_foldername, folder to perform the check on
	 * @param boolean $_forceCheck, trigger check for existance on icServer
	 * @return mixed string or false
	 */
	function folderExists($_folder, $_forceCheck=false)
	{
		static $folderInfo;
		$forceCheck = $_forceCheck;
		if (empty($_folder))
		{
			// this error is more or less without significance, unless we force the check
			if ($_forceCheck===true) error_log(__METHOD__.' ('.__LINE__.') '.' Called with empty Folder:'.$_folder.function_backtrace());
			return false;
		}
		// reduce traffic within the Instance per User; Expire every 5 hours
		//error_log(__METHOD__.' ('.__LINE__.') '.' Called with Folder:'.$_folder.function_backtrace());
		if (is_null($folderInfo)) $folderInfo = egw_cache::getCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*5);
		//error_log(__METHOD__.' ('.__LINE__.') '.'Cached Info on Folder:'.$_folder.' for Profile:'.$this->profileID.($forceCheck?'(forcedCheck)':'').':'.array2string($folderInfo));
		if (!empty($folderInfo) && isset($folderInfo[$this->profileID]) && isset($folderInfo[$this->profileID][$_folder]) && $forceCheck===false)
		{
			//error_log(__METHOD__.' ('.__LINE__.') '.' Using cached Info on Folder:'.$_folder.' for Profile:'.$this->profileID);
			return $folderInfo[$this->profileID][$_folder];
		}
		else
		{
			if ($forceCheck === false)
			{
				//error_log(__METHOD__.' ('.__LINE__.') '.' No cached Info on Folder:'.$_folder.' for Profile:'.$this->profileID.' FolderExistsInfoCache:'.array2string($folderInfo[$this->profileID]));
				$forceCheck = true; // try to force the check, in case there is no connection, we may need that
			}
		}

		// does the folder exist???
		//error_log(__METHOD__."->Connected?".$this->icServer->_connected.", ".$_folder.", ".($forceCheck?' forceCheck activated':'dont check on server'));
		if ( $forceCheck || empty($folderInfo) || !isset($folderInfo[$this->profileID]) || !isset($folderInfo[$this->profileID][$_folder])) {
			//error_log(__METHOD__."->NotConnected and forceCheck with profile:".$this->profileID);
			//return false;
			//try to connect
		}
		try
		{
			$folderInfo[$this->profileID][$_folder] = $this->icServer->mailboxExist($_folder);
		}
		catch (Exception $e)
		{
			//error_log(__METHOD__.__LINE__.$e->getMessage());
			$folderInfo[$this->profileID][$_folder] = false;
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.' Folder Exists:'.$folderInfo[$this->profileID][$_folder].function_backtrace());

		if(!empty($folderInfo) && isset($folderInfo[$this->profileID][$_folder]) &&
			$folderInfo[$this->profileID][$_folder] !== true)
		{
			$folderInfo[$this->profileID][$_folder] = false; // set to false, whatever it was (to have a valid returnvalue for the static return)
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','icServerFolderExistsInfo'.trim($GLOBALS['egw_info']['user']['account_id']),$folderInfo,$expiration=60*60*5);
		return (!empty($folderInfo) && isset($folderInfo[$this->profileID][$_folder]) ? $folderInfo[$this->profileID][$_folder] : false);
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
		$deleteOptions	= $GLOBALS['egw_info']['user']['preferences']['mail']['deleteOptions'];
		$trashFolder	= $this->getTrashFolder();

		$this->icServer->openMailbox($folderName);

		if($folderName == $trashFolder && $deleteOptions == "move_to_trash") {
			$this->deleteMessages('all',$folderName,'remove_immediately');
		} else {
			$this->icServer->expunge($folderName);
		}
	}

	/**
	 * delete a Message
	 *
	 * @param mixed array/string _messageUID array of ids to flag, or 'all'
	 * @param string _folder foldername
	 * @param string _forceDeleteMethod - "no", or deleteMethod like 'move_to_trash',"mark_as_deleted","remove_immediately"
	 *
	 * @return bool true, as we do not handle return values yet
	 */
	function deleteMessages($_messageUID, $_folder=NULL, $_forceDeleteMethod='no')
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.'->'.array2string($_messageUID).','.array2string($_folder).', '.$_forceDeleteMethod);
		$msglist = '';
		$oldMailbox = '';
		if (is_null($_folder) || empty($_folder)) $_folder = $this->sessionData['mailbox'];
		if (empty($_messageUID))
		{
			if (self::$debug) error_log(__METHOD__." no messages Message(s): ".implode(',',$_messageUID));
			return false;
		}
		elseif ($_messageUID==='all')
		{
			$_messageUID= null;
		}
		else
		{
			$uidsToDelete = new Horde_Imap_Client_Ids();
			if (!(is_object($_messageUID) || is_array($_messageUID))) $_messageUID = (array)$_messageUID;
			$uidsToDelete->add($_messageUID);
		}
		$deleteOptions = $_forceDeleteMethod; // use forceDeleteMethod if not "no", or unknown method
		if ($_forceDeleteMethod === 'no' || !in_array($_forceDeleteMethod,array('move_to_trash',"mark_as_deleted","remove_immediately"))) $deleteOptions  = ($this->mailPreferences['deleteOptions']?$this->mailPreferences['deleteOptions']:"mark_as_deleted");
		//error_log(__METHOD__.' ('.__LINE__.') '.'->'.array2string($_messageUID).','.$_folder.'/'.$this->sessionData['mailbox'].' Option:'.$deleteOptions);
		$trashFolder    = $this->getTrashFolder();
		$draftFolder	= $this->getDraftFolder(); //$GLOBALS['egw_info']['user']['preferences']['mail']['draftFolder'];
		$templateFolder = $this->getTemplateFolder(); //$GLOBALS['egw_info']['user']['preferences']['mail']['templateFolder'];
		if(($_folder == $trashFolder && $deleteOptions == "move_to_trash") ||
		   ($_folder == $draftFolder)) {
			$deleteOptions = "remove_immediately";
		}
		if($this->icServer->getCurrentMailbox() != $_folder) {
			$oldMailbox = $this->icServer->getCurrentMailbox();
			$this->icServer->openMailbox($_folder);
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.'->'.array2string($_messageUID).','.$_folder.'/'.$this->sessionData['mailbox'].' Option:'.$deleteOptions);
		$updateCache = false;
		switch($deleteOptions) {
			case "move_to_trash":
				//error_log(__METHOD__.' ('.__LINE__.') ');
				$updateCache = true;
				if(!empty($trashFolder)); {
					if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.implode(' : ', $_messageUID));
					if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '."$trashFolder <= $_folder / ". $this->sessionData['mailbox']);
					// copy messages
					try
					{
						$retValue = $this->icServer->copy($_folder, $trashFolder, array('ids'=>$uidsToDelete,'move'=>true));
					}
					catch (Exception $e)
					{
						throw new egw_exception("Failed to move Messages (".array2string($uidsToDelete).") from Folder $_folder to $trashFolder Error:".$e->getMessage());
					}
				}
				break;

			case "mark_as_deleted":
				//error_log(__METHOD__.' ('.__LINE__.') ');
				// mark messages as deleted
				if (is_null($_messageUID)) $_messageUID='all';
				foreach((array)$_messageUID as $key =>$uid)
				{
					//flag messages, that are flagged for deletion as seen too
					$this->flagMessages('read', $uid, $_folder);
					$flags = $this->getFlags($uid);
					$this->flagMessages('delete', $uid, $_folder);
					//error_log(__METHOD__.' ('.__LINE__.') '.array2string($flags));
					if (strpos( array2string($flags),'Deleted')!==false) $undelete[] = $uid;
					unset($flags);
				}
				foreach((array)$undelete as $key =>$uid)
				{
					$this->flagMessages('undelete', $uid, $_folder);
				}
				break;

			case "remove_immediately":
				//error_log(__METHOD__.' ('.__LINE__.') ');
				$updateCache = true;
				if (is_null($_messageUID)) $_messageUID='all';
				if (is_object($_messageUID))
				{
					$this->flagMessages('delete', $_messageUID, $_folder);
				}
				else
				{
					foreach((array)$_messageUID as $key =>$uid)
					{
						//flag messages, that are flagged for deletion as seen too
						$this->flagMessages('delete', $uid, $_folder);
					}
				}
				// delete the messages finaly
				$this->icServer->expunge($_folder);
				break;
		}
		if($oldMailbox != '') {
			$this->icServer->openMailbox($oldMailbox);
		}

		return true;
	}

	/**
	 * get flags for a Message
	 *
	 * @param mixed string _messageUID array of id to retrieve the flags for
	 *
	 * @return null/array flags
	 */
	function getFlags ($_messageUID) {
		try
		{
			$uidsToFetch = new Horde_Imap_Client_Ids();
			if (!(is_object($_messageUID) || is_array($_messageUID))) $_messageUID = (array)$_messageUID;
			$uidsToFetch->add($_messageUID);
			$_folderName = $this->icServer->getCurrentMailbox();
			$fquery = new Horde_Imap_Client_Fetch_Query();
			$fquery->flags();
			$headersNew = $this->icServer->fetch($_folderName, $fquery, array(
				'ids' => $uidsToFetch,
			));
			if (is_object($headersNew)) {
				foreach($headersNew->ids() as $id) {
					$_headerObject = $headersNew->get($id);
					$flags = $_headerObject->getFlags();
				}
			}
		}
		catch (Exception $e)
		{
			error_log(__METHOD__.' ('.__LINE__.') '."Failed to fetch flags for ".array2string($_messageUID)." Error:".$e->getMessage());
			return null;
			//throw new egw_exception("Failed to fetch flags for ".array2string($_messageUID)" Error:".$e->getMessage());
		}
		return $flags;
	}

	/**
	 * get and parse the flags response for the Notifyflag for a Message
	 *
	 * @param string _messageUID array of id to retrieve the flags for
	 * @param array flags - to avoid additional server call
	 *
	 * @return null/boolean
	 */
	function getNotifyFlags ($_messageUID, $flags=null)
	{
		if (self::$debug) error_log(__METHOD__.$_messageUID.' Flags:'.array2string($flags));
		try
		{
			if($flags===null) $flags =  $this->getFlags($_messageUID);
		}
		catch (Exception $e)
		{
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
		//error_log(__METHOD__.' ('.__LINE__.') '.'->' .$_flag." ".array2string($_messageUID).",$_folder /".$this->sessionData['mailbox']);
		if (empty($_messageUID))
		{
			if (self::$debug) error_log(__METHOD__." no messages Message(s): ".implode(',',$_messageUID));
			return false;
		}
		$this->icServer->openMailbox(($_folder?$_folder:$this->sessionData['mailbox']));
		$folder = $this->icServer->getCurrentMailbox();
		if (is_array($_messageUID)&& count($_messageUID)>50)
		{
			$count = $this->getMailBoxCounters($folder,true);
			if ($count->messages == count($_messageUID)) $_messageUID='all';
		}

		if ($_messageUID==='all')
		{
			$messageUIDs = array('all');
		}
		else
		{
			if (!(is_object($_messageUID) || is_array($_messageUID))) $_messageUID = (array)$_messageUID;
			$messageUIDs = array_chunk($_messageUID,50,true);
		}
		try
		{
			foreach($messageUIDs as $k => $uids)
			{
				if ($uids==='all')
				{
					$uidsToModify=null;
				}
				else
				{
					$uidsToModify = new Horde_Imap_Client_Ids();
					$uidsToModify->add($uids);
				}
				switch($_flag) {
					case "delete":
						$ret = $this->icServer->store($folder, array('add'=>array('\\Deleted'), 'ids'=> $uidsToModify));
						break;
					case "undelete":
						$ret = $this->icServer->store($folder, array('remove'=>array('\\Deleted'), 'ids'=> $uidsToModify));
						break;
					case "flagged":
						$ret = $this->icServer->store($folder, array('add'=>array('\\Flagged'), 'ids'=> $uidsToModify));
						break;
					case "read":
					case "seen":
						$ret = $this->icServer->store($folder, array('add'=>array('\\Seen'), 'ids'=> $uidsToModify));
						break;
					case "forwarded":
						$ret = $this->icServer->store($folder, array('add'=>array('$Forwarded'), 'ids'=> $uidsToModify));
					case "answered":
						$ret = $this->icServer->store($folder, array('add'=>array('\\Answered'), 'ids'=> $uidsToModify));
						break;
					case "unflagged":
						$ret = $this->icServer->store($folder, array('remove'=>array('\\Flagged'), 'ids'=> $uidsToModify));
						break;
					case "unread":
					case "unseen":
						$ret = $this->icServer->store($folder, array('remove'=>array('\\Seen','\\Answered','$Forwarded'), 'ids'=> $uidsToModify));
						break;
					case "mdnsent":
						$ret = $this->icServer->store($folder, array('add'=>array('MDNSent'), 'ids'=> $uidsToModify));
						break;
					case "mdnnotsent":
						$ret = $this->icServer->store($folder, array('add'=>array('MDNnotSent'), 'ids'=> $uidsToModify));
						break;
					case "label1":
					case "labelone":
						$ret = $this->icServer->store($folder, array('add'=>array('$label1'), 'ids'=> $uidsToModify));
						break;
					case "unlabel1":
					case "unlabelone":
						$ret = $this->icServer->store($folder, array('remove'=>array('$label1'), 'ids'=> $uidsToModify));
						break;
					case "label2":
					case "labeltwo":
						$ret = $this->icServer->store($folder, array('add'=>array('$label2'), 'ids'=> $uidsToModify));
						break;
					case "unlabel2":
					case "unlabeltwo":
						$ret = $this->icServer->store($folder, array('remove'=>array('$label2'), 'ids'=> $uidsToModify));
						break;
					case "label3":
					case "labelthree":
						$ret = $this->icServer->store($folder, array('add'=>array('$label3'), 'ids'=> $uidsToModify));
						break;
					case "unlabel3":
					case "unlabelthree":
						$ret = $this->icServer->store($folder, array('remove'=>array('$label3'), 'ids'=> $uidsToModify));
						break;
					case "label4":
					case "labelfour":
						$ret = $this->icServer->store($folder, array('add'=>array('$label4'), 'ids'=> $uidsToModify));
						break;
					case "unlabel4":
					case "unlabelfour":
						$ret = $this->icServer->store($folder, array('remove'=>array('$label4'), 'ids'=> $uidsToModify));
						break;
					case "label5":
					case "labelfive":
						$ret = $this->icServer->store($folder, array('add'=>array('$label5'), 'ids'=> $uidsToModify));
						break;
					case "unlabel5":
					case "unlabelfive":
						$ret = $this->icServer->store($folder, array('remove'=>array('$label5'), 'ids'=> $uidsToModify));
						break;
					case "unlabel":
						$ret = $this->icServer->store($folder, array('remove'=>array('$label1'), 'ids'=> $uidsToModify));
						$ret = $this->icServer->store($folder, array('remove'=>array('$label2'), 'ids'=> $uidsToModify));
						$ret = $this->icServer->store($folder, array('remove'=>array('$label3'), 'ids'=> $uidsToModify));
						$ret = $this->icServer->store($folder, array('remove'=>array('$label4'), 'ids'=> $uidsToModify));
						$ret = $this->icServer->store($folder, array('remove'=>array('$label5'), 'ids'=> $uidsToModify));
						break;
				}
			}
		}
		catch(Exception $e)
		{
			error_log(__METHOD__.__LINE__.' Error, could not flag messages in folder '.$folder.' Reason:'.$e->getMessage());
		}
		self::$folderStatusCache[$this->profileID][(!empty($_folder)?$_folder: $this->sessionData['mailbox'])]['uidValidity'] = 0;

		//error_log(__METHOD__.' ('.__LINE__.') '.'->' .$_flag." ".array2string($_messageUID).",".($_folder?$_folder:$this->sessionData['mailbox']));
		return true; // as we do not catch/examine setFlags returnValue
	}

	/**
	 * move Message(s)
	 *
	 * @param string _foldername target folder
	 * @param mixed array/string _messageUID array of ids to flag, or 'all'
	 * @param boolean $deleteAfterMove - decides if a mail is moved (true) or copied (false)
	 * @param string $currentFolder
	 * @param boolean $returnUIDs - control wether or not the action called should return the new uids
	 *						caveat: not all servers do support that
	 * @param int $_targetProfileID - target profile ID, should only be handed over when target server is differen from source
	 *
	 * @return mixed/bool true,false or new uid
	 */
	function moveMessages($_foldername, $_messageUID, $deleteAfterMove=true, $currentFolder = Null, $returnUIDs = false, $_targetProfileID = Null)
	{
		$msglist = '';

		$deleteOptions  = $GLOBALS['egw_info']["user"]["preferences"]["mail"]["deleteOptions"];
		if (empty($_messageUID))
		{
			if (self::$debug) error_log(__METHOD__." no messages Message(s): ".implode(',',$_messageUID));
			return false;
		}
		elseif ($_messageUID==='all')
		{
			$uidsToMove= null;
		}
		else
		{
			$uidsToMove = new Horde_Imap_Client_Ids();
			if (!(is_object($_messageUID) || is_array($_messageUID))) $_messageUID = (array)$_messageUID;
			$uidsToMove->add($_messageUID);
		}
		$sourceFolder = (!empty($currentFolder)?$currentFolder: $this->sessionData['mailbox']);
		if (!is_null($_targetProfileID) && $_targetProfileID !== $this->icServer->ImapServerId)
		{
			$sourceFolder = $this->icServer->getMailbox($sourceFolder);
			$target = emailadmin_account::read($_targetProfileID)->imapServer();
			$foldername = $target->getMailbox($_foldername);
			//error_log(__METHOD__.' ('.__LINE__.') '.' Sourceserver:'.$this->icServer->ImapServerId.' TargetServer:'.$_targetProfileID.' TargetFolderObject:'.array2string($foldername));
			$this->icServer->openMailbox($sourceFolder);
			$uidsToFetch = new Horde_Imap_Client_Ids();
			$uidsToFetch->add($_messageUID);

			$fquery = new Horde_Imap_Client_Fetch_Query();
			$fquery->fullText(array('peek'=>true));
			$fquery->flags();
			$headersNew = $this->icServer->fetch($sourceFolder, $fquery, array(
				'ids' => $uidsToFetch,
			));
			if (is_object($headersNew)) {
				$c=0;
				$retUid = new Horde_Imap_Client_Ids();
				// make sure the target folder is open and ready
				$target->openMailbox($foldername);
				// we copy chunks of 5 to avoid too much memory and/or server stress
				foreach($headersNew as $id=>$_headerObject) {
					$c++;
					$flags = $_headerObject->getFlags(); //unseen status seems to be lost when retrieving the full message
					//error_log(__METHOD__.' ('.__LINE__.') '.array2string($flags));
					$body = $_headerObject->getFullMsg();
					$dataNflags[] = array('data'=>array(array('t'=>'text','v'=>"$body")), 'flags'=>$flags);
					if ($c==5)
					{
						$ret = $target->append($foldername,$dataNflags);
						$retUid->add($ret);
						unset($dataNflags);
						time_nanosleep(0,500000);// sleep 500 miliseconds
						$c=0;
					}
				}
				if (isset($dataNflags))
				{
					$ret = $target->append($foldername,$dataNflags);
					$retUid->add($ret);
					unset($dataNflags);
				}
				// make sure we are back to source
				$this->icServer->openMailbox($sourceFolder);
				if ($deleteAfterMove)
				{
					$this->deleteMessages($_messageUID, $sourceFolder, $_forceDeleteMethod='remove_immediately');
				}
			}
		}
		else
		{
			try
			{
				$retUid = $this->icServer->copy($sourceFolder, $_foldername, array('ids'=>$uidsToMove,'move'=>$deleteAfterMove));
			}
			catch (exception $e)
			{
				error_log(__METHOD__.' ('.__LINE__.') '."Copying to Folder $_foldername failed! Error:".$e->getMessage());
				throw new egw_exception("Copying to Folder $_foldername failed! Error:".$e->getMessage());
				return false;
			}
		}

		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($retUid));
		return ($returnUIDs ? $retUid : true);
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
	 * @param mixed $charset false or string -> Target charset, if false emailadmin_imapbase displayCharset will be used
	 * @return string
	 */
	static function htmlentities($_string, $_charset=false)
	{
		//setting the charset (if not given)
		if ($_charset===false) $_charset = self::$displayCharset;
		$_stringORG = $_string;
		$_string = @htmlentities($_string,ENT_QUOTES,$_charset, false);
		if (empty($_string) && !empty($_stringORG)) $_string = @htmlentities(translation::convert($_stringORG,translation::detect_encoding($_stringORG),$_charset),ENT_QUOTES | ENT_IGNORE,$_charset, false);
		return $_string;
	}

	/**
	 * htmlspecialchars
	 * helperfunction to cope with wrong encoding in strings;
	 * seems to be outdated and not needed any more for et2
	 * @param string $_string  input to be converted
	 * @param mixed $charset false or string -> Target charset, if false mail displayCharset will be used
	 * @return string
	 */
	static function htmlspecialchars($_string, $_charset=false)
	{
		return $_string;
/*
		//setting the charset (if not given)
		if ($_charset===false) $_charset = self::$displayCharset;
		$_stringORG = $_string;
		$_string = @htmlspecialchars($_string,ENT_QUOTES,$_charset, false);
		if (empty($_string) && !empty($_stringORG)) $_string = @htmlspecialchars(translation::convert($_stringORG,translation::detect_encoding($_stringORG),$_charset),ENT_QUOTES | ENT_IGNORE,$_charset, false);
		return $_string;
*/
	}

	/**
	 * clean a message from elements regarded as potentially harmful
	 * param string/reference $_html is the text to be processed
	 * param boolean $usepurify - obsolet, as we always use htmlLawed
	 * param boolean $cleanTags - use tidy (if available) to clean/balance tags
	 * return nothing
	 */
	static function getCleanHTML(&$_html, $usepurify = false, $cleanTags=true)
	{
		// remove CRLF and TAB as it is of no use in HTML.
		// but they matter in <pre>, so we rather don't
		//$_html = str_replace("\r\n",' ',$_html);
		//$_html = str_replace("\t",' ',$_html);
		//error_log($_html);
		//repair doubleencoded ampersands, and some stuff htmLawed stumbles upon with balancing switched on
		$_html = str_replace(array('&amp;amp;','<DIV><BR></DIV>',"<DIV>&nbsp;</DIV>",'<div>&nbsp;</div>','</td></font>','<br><td>','<tr></tr>','<o:p></o:p>','<o:p>','</o:p>'),
							 array('&amp;',    '<BR>',           '<BR>',             '<BR>',             '</font></td>','<td>',    '',         '',           '',  ''),$_html);
		//$_html = str_replace(array('&amp;amp;'),array('&amp;'),$_html);
		if (stripos($_html,'style')!==false) translation::replaceTagsCompletley($_html,'style'); // clean out empty or pagewide style definitions / left over tags
		if (stripos($_html,'head')!==false) translation::replaceTagsCompletley($_html,'head'); // Strip out stuff in head
		//if (stripos($_html,'![if')!==false && stripos($_html,'<![endif]>')!==false) translation::replaceTagsCompletley($_html,'!\[if','<!\[endif\]>',false); // Strip out stuff in ifs
		//if (stripos($_html,'!--[if')!==false && stripos($_html,'<![endif]-->')!==false) translation::replaceTagsCompletley($_html,'!--\[if','<!\[endif\]-->',false); // Strip out stuff in ifs
		//error_log(__METHOD__.' ('.__LINE__.') '.$_html);
		// force the use of kses, as it is still have the edge over purifier with some stuff
		$usepurify = true;
		if ($usepurify)
		{
			// we need a customized config, as we may allow external images, $GLOBALS['egw_info']['user']['preferences']['mail']['allowExternalIMGs']
			if (get_magic_quotes_gpc() === 1) $_html = stripslashes($_html);
			// Strip out doctype in head, as htmlLawed cannot handle it TODO: Consider extracting it and adding it afterwards
			if (stripos($_html,'!doctype')!==false) translation::replaceTagsCompletley($_html,'!doctype');
			if (stripos($_html,'?xml:namespace')!==false) translation::replaceTagsCompletley($_html,'\?xml:namespace','/>',false);
			if (stripos($_html,'?xml version')!==false) translation::replaceTagsCompletley($_html,'\?xml version','\?>',false);
			if (strpos($_html,'!CURSOR')!==false) translation::replaceTagsCompletley($_html,'!CURSOR');
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
			//error_log(__METHOD__.':'.__LINE__.':'.$_html);
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
					error_log(__METHOD__.' ('.__LINE__.') '.' ->'.$tidy->errorBuffer);
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
				//error_log(__METHOD__.' ('.__LINE__.') '.$_html);
				//@set_time_limit($to);
			}
		}
	}

	/**
	 * Header and Bodystructure stuff
	 */

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

	/**
	 * decodeMimePart - fetches the charset mimepart if it exists
	 * @param string $_mimeMessage - the message to be decoded
	 * @param string $_encoding - the encoding used BASE64 and QUOTED-PRINTABLE is supported
	 * @param string $_charset - not used
	 * @return string decoded mimePart
	 */
	function decodeMimePart($_mimeMessage, $_encoding, $_charset = '')
	{
		// decode the part
		if (self::$debug) error_log(__METHOD__."() with $_encoding and $_charset:".print_r($_mimeMessage,true));
		switch (strtoupper($_encoding))
		{
			case 'BASE64':
				// use imap_base64 to decode, not any longer, as it is strict, and fails if it encounters invalid chars
				return base64_decode($_mimeMessage);
				break;
			case 'QUOTED-PRINTABLE':
				// use imap_qprint to decode
				return quoted_printable_decode($_mimeMessage);
				break;
			case 'WEDONTKNOWTHEENCODING':
				// try base64
				$r = base64_decode($_mimeMessage);
				if (json_encode($r))
				{
					return $r;
				}
				//we do not know the encoding, so we do not decode
			default:
				// it is either not encoded or we don't know about it
				return $_mimeMessage;
				break;
		}
	}

	/**
	 * getMultipartAlternative
	 * get part of the message, if its stucture is indicating its of multipart alternative style
	 * a wrapper for multipartmixed
	 * @param string/int $_uid the messageuid,
	 * @param Horde_Mime_Part $_structure structure for parsing
	 * @param string $_htmlMode, how to display a message, html, plain text, ...
	 * @param boolean $_preserveSeen flag to preserve the seenflag by using body.peek
	 * @return array containing the desired part
	 */
	function getMultipartAlternative($_uid, Horde_Mime_Part $_structure, $_htmlMode, $_preserveSeen = false)
	{
		// a multipart/alternative has exactly 2 parts (text and html  OR  text and something else)
		// sometimes there are 3 parts, when there is an ics/ical attached/included-> we want to show that
		// as attachment AND as abstracted ical information (we use our notification style here).
		$partText = $partCalendar = $partHTML = null;
		if (self::$debug) _debug_array(array("METHOD"=>__METHOD__,"LINE"=>__LINE__,"STRUCTURE"=>$_structure));
		//error_log(__METHOD__.' ('.__LINE__.') ');
		$ignore_first_part = true;
		foreach($_structure->contentTypeMap() as $mime_id => $mime_type)
		{
			//error_log(__METHOD__."($_uid, ".$_structure->getMimeId().") $mime_id: $mime_type"." ignoreFirstPart:".$ignore_first_part);
			if (self::$debug) echo __METHOD__."($_uid, partID=".$_structure->getMimeId().") $mime_id: $mime_type<br>";

			if ($ignore_first_part)
			{
				$ignore_first_part = false;
				continue;	// ignore multipart/alternative itself
			}

			$mimePart = $_structure->getPart($mime_id);

			switch($mimePart->getPrimaryType())
			{
				case 'text':
					switch($mimePart->getSubType())
					{
						case 'calendar':	// only if there is no partText set already
							//if ($partText) break;
							if ($mimePart->getBytes() > 0) $partCalendar = $mimePart;
							break;
							// fall throught
						case 'plain':
							if ($mimePart->getBytes() > 0) $partText = $mimePart;
							break;

						case 'html':
							if ($mimePart->getBytes() > 0)  $partHTML = $mimePart;
							break;
					}
					break;

				case 'multipart':
					switch($mimePart->getSubType())
					{
						case 'related':
						case 'mixed':
							if (count($mimePart->getParts()) > 1)
							{
								// in a multipart alternative we treat the multipart/related as html part
								if (self::$debug) error_log(__METHOD__." process MULTIPART/".$mimePart->getSubType()." with array as subparts");
								$partHTML = $mimePart;
								break 3; // GET OUT OF LOOP, will be processed according to type
							}
							break;
						case 'alternative':
							if (count($mimePart->getParts()) > 1)
							{
								//cascading multipartAlternative structure, assuming only the first one is to be used
								return $this->getMultipartAlternative($_uid, $mimePart, $_htmlMode, $_preserveSeen);
							}
					}
			}
		}
		// seems we have a multipart/alternative; as we assume we are able to display events
		// the text/calendar part has higher precedence then text/plain
		if ($partCalendar && $partText) $partText = $partCalendar;

		switch($_htmlMode)
		{
			case 'html_only':
			case 'always_display':
				if ($partHTML)
				{
					switch($partHTML->getSubType())
					{
						case 'related':
							return $this->getMultipartRelated($_uid, $partHTML, $_htmlMode, $_preserveSeen);

						case 'mixed':
							return $this->getMultipartMixed($_uid, $partHTML, $_htmlMode, $_preserveSeen);

						default:
							return $this->getTextPart($_uid, $partHTML, $_htmlMode, $_preserveSeen);
					}
				}
				elseif ($partText && $_htmlMode=='always_display')
				{
					return $this->getTextPart($_uid, $partText, $_htmlMode, $_preserveSeen);
				}
				break;

			case 'only_if_no_text':
				if ($partText)
				{
					return $this->getTextPart($_uid, $partText, $_htmlMode, $_preserveSeen);
				}
				if ($partHTML)
				{
					if ($partHTML->getPrimaryType())
					{
						return $this->getMultipartRelated($_uid, $partHTML, $_htmlMode, $_preserveSeen);
					}
					return $this->getTextPart($_uid, $partHTML, 'always_display', $_preserveSeen);
				}
				break;

			default:
				if ($partText)
				{
					return $this->getTextPart($_uid, $partText, $_htmlMode, $_preserveSeen);
				}
				$bodyPart = array(
					'body'		=> lang("no plain text part found"),
					'mimeType'	=> 'text/plain',
					'charSet'	=> self::$displayCharset,
				);
				break;
		}
		return $bodyPart;
	}

	/**
	 * Get part of the message, if its stucture is indicating its of multipart mixed style
	 *
	 * @param int $_uid the messageuid,
	 * @param Horde_Mime_Part $_structure='', if given use structure for parsing
	 * @param string $_htmlMode, how to display a message, html, plain text, ...
	 * @param boolean $_preserveSeen flag to preserve the seenflag by using body.peek
	 * @return array containing the desired part
	 */
	function getMultipartMixed($_uid, Horde_Mime_Part $_structure, $_htmlMode, $_preserveSeen = false)
	{
		if (self::$debug) echo __METHOD__."$_uid, $_htmlMode<br>";
		$bodyPart = array();
		if (self::$debug) _debug_array($_structure);

		$ignore_first_part = true;
		$skipParts = array();
		foreach($_structure->contentTypeMap() as $mime_id => $mime_type)
		{
			//error_log(__METHOD__."($_uid, ".$_structure->getMimeId().") $mime_id: $mime_type");
			if (self::$debug) echo __METHOD__."($_uid, partID=".$_structure->getMimeId().") $mime_id: $mime_type<br>";
			if ($ignore_first_part)
			{
				$ignore_first_part = false;
				//error_log(__METHOD__."($_uid, ".$_structure->getMimeId().") SKIPPED FirstPart $mime_id: $mime_type");
				continue;	// ignore multipart/mixed itself
			}
			if (array_key_exists($mime_id,$skipParts))
			{
				//error_log(__METHOD__."($_uid, ".$_structure->getMimeId().") SKIPPED $mime_id: $mime_type");
				continue;
			}

			$part = $_structure->getPart($mime_id);

			switch($part->getPrimaryType())
			{
				case 'multipart':
					if ($part->getDisposition() == 'attachment') continue;
					switch($part->getSubType())
					{
						case 'alternative':
							return array($this->getMultipartAlternative($_uid, $part, $_htmlMode, $_preserveSeen));

						case 'mixed':
						case 'signed':
							$bodyPart = array_merge($bodyPart, $this->getMultipartMixed($_uid, $part, $_htmlMode, $_preserveSeen));
							break;

						case 'related':
							$bodyPart = array_merge($bodyPart, $this->getMultipartRelated($_uid, $part, $_htmlMode, $_preserveSeen));
							break;
					}
					break;

				case 'text':
					switch($part->getSubType())
					{
						case 'plain':
						case 'html':
						case 'calendar': // inline ics/ical files
							if($part->getDisposition() != 'attachment')
							{
								$bodyPart[] = $this->getTextPart($_uid, $part, $_htmlMode, $_preserveSeen);
							}
							//error_log(__METHOD__.' ('.__LINE__.') '.' ->'.$part->type."/".$part->subType.' -> BodyPart:'.array2string($bodyPart[count($bodyPart)-1]));
							break;
					}
					break;

				case 'message':
					//skip attachments
					if($part->getSubType() == 'delivery-status' && $part->getDisposition() != 'attachment')
					{
						$bodyPart[] = $this->getTextPart($_uid, $part, $_htmlMode, $_preserveSeen);
					}
					// do not descend into attached Messages
					if($part->getSubType() == 'rfc822' || $part->getDisposition() == 'attachment')
					{
						$skipParts[$mime_id.'.0'] = $mime_type;
						foreach($part->contentTypeMap() as $sub_id => $sub_type) $skipParts[$sub_id] = $sub_type;
						//error_log(__METHOD__.' ('.__LINE__.') '.' Uid:'.$_uid.' Part:'.$mime_id.':'.array2string($skipParts));
						//break 2;
					}
					break;

				default:
					// do nothing
					// the part is a attachment
			}
		}
		return $bodyPart;
	}

	/**
	 * getMultipartRelated
	 * get part of the message, if its stucture is indicating its of multipart related style
	 * a wrapper for multipartmixed
	 * @param string/int $_uid the messageuid,
	 * @param Horde_Mime_Part $_structure, if given use structure for parsing
	 * @param string $_htmlMode, how to display a message, html, plain text, ...
	 * @param boolean $_preserveSeen flag to preserve the seenflag by using body.peek
	 * @return array containing the desired part
	 */
	function getMultipartRelated($_uid, Horde_Mime_Part $_structure, $_htmlMode, $_preserveSeen = false)
	{
		return $this->getMultipartMixed($_uid, $_structure, $_htmlMode, $_preserveSeen);
	}

	/**
	 * Fetch a body part
	 *
	 * @param int $_uid
	 * @param string $_partID=null
	 * @param string $_folder=null
	 * @param boolean $_preserveSeen=false
	 * @param boolean $_stream=false true return a stream, false return string
	 * @param string &$_encoding=null on return: transfer encoding of returned part
	 * @return string|resource
	 */
	function getBodyPart($_uid, $_partID=null, $_folder=null, $_preserveSeen=false, $_stream=false, &$_encoding=null)
	{
		if (self::$debug) error_log( __METHOD__."($_uid, $_partID, $_folder, $_preserveSeen)");

		if (empty($_folder))
		{
			$_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($_folder).'/'.$this->icServer->getCurrentMailbox().'/'. $this->sessionData['mailbox']);
		// querying contents of body part
		$uidsToFetch = new Horde_Imap_Client_Ids();
		if (!(is_object($_uid) || is_array($_uid))) $_uid = (array)$_uid;
		$uidsToFetch->add($_uid);

		$fquery = new Horde_Imap_Client_Fetch_Query();
		$fquery->bodyPart($_partID, array(
			'peek' => $_preserveSeen,
			'decode' => true,	// try decode on server, does NOT neccessary work
		));

		$part = $this->icServer->fetch($_folder, $fquery, array(
			'ids' => $uidsToFetch,
		))->first();

		if (!$part) return null;

		$_encoding = $part->getBodyPartDecode($_partID);

		return $part->getBodyPart($_partID, $_stream);
	}

	/**
	 * Get Body from message
	 *
	 * @param int $_uid the messageuid
	 * @param Horde_Mime_Part $_structure=null, if given use structure for parsing
	 * @param string $_htmlMode how to display a message: 'html_only', 'always_display', 'only_if_no_text' or ''
	 * @param boolean $_preserveSeen flag to preserve the seenflag by using body.peek
	 * @param boolean $_stream=false true return a stream, false return string
	 * @return array containing the desired text part, mimeType and charset
	 */
	function getTextPart($_uid, Horde_Mime_Part $_structure, $_htmlMode='', $_preserveSeen=false, $_stream=false)
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.'->'.$_uid.':'.array2string($_structure).' '.function_backtrace());
		$bodyPart = array();
		if (self::$debug) _debug_array(array($_structure,function_backtrace()));

		if($_structure->getSubType() == 'html' && !in_array($_htmlMode, array('html_only', 'always_display', 'only_if_no_text')))
		{
			$bodyPart = array(
				'error'		=> 1,
				'body'		=> lang("displaying html messages is disabled"),
				'mimeType'	=> 'text/html',
				'charSet'	=> self::$displayCharset,
			);
		}
		elseif ($_structure->getSubType() == 'plain' && $_htmlMode == 'html_only')
		{
			$bodyPart = array(
				'error'		=> 1,
				'body'      => lang("displaying plain messages is disabled"),
				'mimeType'  => 'text/plain', // make sure we do not return mimeType text/html
				'charSet'   => self::$displayCharset,
			);
		}
		else
		{
			// some Servers append PropertyFile___ ; strip that here for display
			// RB: not sure what this is: preg_replace('/PropertyFile___$/','',$this->decodeMimePart($mimePartBody, $_structure->encoding, $this->getMimePartCharset($_structure))),
			$this->fetchPartContents($_uid, $_structure, $_stream, $_preserveSeen);

			$bodyPart = array(
				'body'		=> $_structure->getContents(array(
					'stream' => $_stream,
				)),
				'mimeType'  => $_structure->getType() == 'text/html' ? 'text/html' : 'text/plain',
				'charSet'	=> $_structure->getCharset(),
			);
/* RB: not sure this is still necessary
			if ($_structure->type == 'TEXT' && $_structure->subType == 'PLAIN' &&
				is_array($_structure->parameters) && isset($_structure->parameters['FORMAT']) &&
				trim(strtolower($_structure->parameters['FORMAT']))=='flowed'
			)
			{
				if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '." detected TEXT/PLAIN Format:flowed -> removing leading blank ('\r\n ') per line");
				$bodyPart['body'] = str_replace("\r\n ","\r\n", $bodyPart['body']);
			}
*/
			if ($_structure->getSubType() == 'calendar')
			{
				$bodyPart['body'] = $this->getEvent($_structure->getContents(), $_structure->getContentTypeParameter('METHOD'));
			}
		}
		return $bodyPart;
	}

	/**
	 * Return inline ical as html
	 *
	 * @param string $ical iCal data
	 * @param string $method iTip method eg. 'REPLY'
	 * @return string text to display instead
	 */
	function getEvent($ical, $method=null)
	{
		// we get an inline CALENDAR ical/ics, we display it using the calendar notification style
		$calobj = new calendar_ical;
		$calboupdate = new calendar_boupdate;
		// timezone stuff
		$tz_diff = $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'] - $this->common_prefs['tz_offset'];
		// form an event out of ical
		$events = $calobj->icaltoegw($ical);
		$event =& $events[0];
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
			if ($method == 'REPLY') $event['title'] = $oldevent[$eventid[0]]['title'];
		}
		// we prepare the message
		$details = $calboupdate->_get_event_details($event,$action,$event_arr);
		$details['olddate']=$olddate;
		//_debug_array($_structure);
		list($subject,$info) = $calboupdate->get_update_message($event, $method !='REPLY');
		$info = $GLOBALS['egw']->preferences->parse_notify($info,$details);

		// we set the bodyPart, we only show the event, we dont actually do anything, as we expect the user to
		// click on the attached ics to update his own eventstore
		$text = $subject;
		$text .= "\n".$info;
		$text .= "\n\n".lang('Event Details follow').":\n";
		foreach($event_arr as $key => $val)
		{
			if(strlen($details[$key])) {
				switch($key){
					case 'access':
					case 'priority':
					case 'link':
						break;
					default:
						$text .= sprintf("%-20s %s\n",$val['field'].':',$details[$key]);
						break;
				}
			}
		}
		return $text;
	}

	/**
	 * Get Body of message
	 *
	 * @param int $_uid the messageuid,
	 * @param string $_htmlOptions, how to display a message, html, plain text, ...
	 * @param string $_partID=null , the partID, may be omitted
	 * @param Horde_Mime_Part $_structure=null if given use structure for parsing
	 * @param boolean $_preserveSeen flag to preserve the seenflag by using body.peek
	 * @param string $_folder folder to work on
	 * @return array containing the message body, mimeType and charset
	 */
	function getMessageBody($_uid, $_htmlOptions='', $_partID=null, Horde_Mime_Part $_structure=null, $_preserveSeen = false, $_folder = '')
	{
		if (self::$debug) echo __METHOD__."$_uid, $_htmlOptions, $_partID<br>";
		if($_htmlOptions != '') {
			$this->htmlOptions = $_htmlOptions;
		}
		if (empty($_folder))
		{
			$_folder = $this->sessionData['mailbox'];
		}
		if (empty($this->sessionData['mailbox']) && !empty($_folder))
		{
			$this->sessionData['mailbox'] = $_folder;
		}

		if (!isset($_structure))
		{
			$_structure = $this->getStructure($_uid, $_partID, $_folder, $_preserveSeen);
		}
		if (!is_object($_structure))
		{
			return array(
				array(
					'error'		=> 1,
					'body'		=> 'Error: Could not fetch structure on mail:'.$_uid." as $_htmlOptions". 'for Mailprofile'.$this->icServer->ImapServerId.' User:'.$GLOBALS['egw_info']['user']['account_lid'],
					'mimeType'	=> 'text/plain',
					'charSet'	=> self::$displayCharset,
				)
			);
		}
		if (!empty($_partID))
		{
			$_structure->contentTypeMap();
			$_structure = $_structure->getPart($_partID);
			//_debug_array($_structure->getMimeId()); exit;
		}

		switch($_structure->getPrimaryType())
		{
			case 'application':
				return array(
					array(
						'body'		=> '',
						'mimeType'	=> 'text/plain',
						'charSet'	=> 'iso-8859-1',
					)
				);

			case 'multipart':
				switch($_structure->getSubType())
				{
					case 'alternative':
						$bodyParts = array($this->getMultipartAlternative($_uid, $_structure, $this->htmlOptions, $_preserveSeen));
						break;

					case 'nil': // multipart with no Alternative
					case 'mixed':
					case 'report':
					case 'signed':
						$bodyParts = $this->getMultipartMixed($_uid, $_structure, $this->htmlOptions, $_preserveSeen);
						break;

					case 'related':
						$bodyParts = $this->getMultipartRelated($_uid, $_structure, $this->htmlOptions, $_preserveSeen);
						break;
				}
				return self::normalizeBodyParts($bodyParts);

			case 'video':
			case 'audio': // some servers send audiofiles and imagesfiles directly, without any stuff surround it
			case 'image': // they are displayed as Attachment NOT INLINE
				return array(
					array(
						'body'      => '',
						'mimeType'  => $_structure->getSubType(),
					),
				);

			case 'text':
				$bodyPart = array();
				if ($_structure->getDisposition() != 'attachment')
				{
					switch($_structure->getSubType())
					{
						case 'calendar':
							// this is handeled in getTextPart
						case 'html':
						case 'plain':
						default:
							$bodyPart = array($this->getTextPart($_uid, $_structure, $this->htmlOptions, $_preserveSeen));
					}
				} else {
					// what if the structure->disposition is attachment ,...
				}
				return self::normalizeBodyParts($bodyPart);

			case 'attachment':
			case 'message':
				switch($_structure->getSubType())
				{
					case 'rfc822':
						$newStructure = $_structure->getParts();
						if (self::$debug) {echo __METHOD__." Message -> RFC -> NewStructure:"; _debug_array($newStructure[0]);}
						return self::normalizeBodyParts($this->getMessageBody($_uid, $_htmlOptions, $newStructure[0]->getMimeId(), $newStructure[0], $_preserveSeen, $_folder));
				}
				break;

			default:
				if (self::$debug) _debug_array($_structure);
				return array(
					array(
						'body'		=> lang('The mimeparser can not parse this message.').$_structure->getType(),
						'mimeType'	=> 'text/plain',
						'charSet'	=> self::$displayCharset,
					)
				);
		}
	}

	/**
	 * normalizeBodyParts - function to gather and normalize all body Information
	 * as we may recieve a bodyParts structure from within getMessageBody nested deeper than expected
	 * so this is used to normalize the output, so we are able to rely on our expectation
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

	/**
	 * getdisplayableBody - creates the bodypart of the email as textual representation
	 * @param object $mailClass the mailClass object to be used
	 * @param array $bodyParts  with the bodyparts
	 * @return string a preformatted string with the mails converted to text
	 */
	static function &getdisplayableBody(&$mailClass, $bodyParts, $preserveHTML = false)
	{
		for($i=0; $i<count($bodyParts); $i++)
		{
			if (!isset($bodyParts[$i]['body'])) {
				$bodyParts[$i]['body'] = self::getdisplayableBody($mailClass, $bodyParts[$i], $preserveHTML);
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

			if ($bodyParts[$i]['charSet']===false) $bodyParts[$i]['charSet'] = translation::detect_encoding($bodyParts[$i]['body']);
			// add line breaks to $bodyParts
			//error_log(__METHOD__.' ('.__LINE__.') '.' Charset:'.$bodyParts[$i]['charSet'].'->'.$bodyParts[$i]['body']);
			$newBody  = translation::convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
			//error_log(__METHOD__.' ('.__LINE__.') '.' MimeType:'.$bodyParts[$i]['mimeType'].'->'.$newBody);
			/*
			// in a way, this tests if we are having real utf-8 (the displayCharset) by now; we should if charsets reported (or detected) are correct
			if (strtoupper(self::$displayCharset) == 'UTF-8')
			{
				$test = json_encode($newBody);
				//error_log(__METHOD__.' ('.__LINE__.') '.'#'.$test.'# ->'.strlen($newBody).' Error:'.json_last_error());
				if (json_last_error() != JSON_ERROR_NONE && strlen($newBody)>0)
				{
					// this should not be needed, unless something fails with charset detection/ wrong charset passed
					error_log(__METHOD__.' ('.__LINE__.') '.' Charset Reported:'.$bodyParts[$i]['charSet'].' Carset Detected:'.translation::detect_encoding($bodyParts[$i]['body']));
					$newBody = utf8_encode($newBody);
				}
			}
			*/
			//error_log(__METHOD__.' ('.__LINE__.') '.' before purify:'.$newBody);
			$mailClass->activeMimeType = 'text/plain';
			if ($bodyParts[$i]['mimeType'] == 'text/html') {
				$mailClass->activeMimeType = $bodyParts[$i]['mimeType'];
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
						error_log(__METHOD__.' ('.__LINE__.') '.' ->'.$tidy->errorBuffer);
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
					// the next line should not be needed, but produces better results on HTML 2 Text conversion,
					// as we switched off HTMLaweds tidy functionality
					$newBody = str_replace(array('&amp;amp;','<DIV><BR></DIV>',"<DIV>&nbsp;</DIV>",'<div>&nbsp;</div>'),array('&amp;','<BR>','<BR>','<BR>'),$newBody);
					$newBody = $htmLawed->egw_htmLawed($newBody);
					if ($hasOther && $preserveHTML) $newBody = $matches[1]. $newBody. $matches[3];
				}
				//error_log(__METHOD__.' ('.__LINE__.') '.' after purify:'.$newBody);
				if ($preserveHTML==false) $newBody = translation::convertHTMLToText($newBody,self::$displayCharset,true,$stripalltags=true);
				//error_log(__METHOD__.' ('.__LINE__.') '.' after convertHTMLToText:'.$newBody);
				if ($preserveHTML==false) $newBody = nl2br($newBody); // we need this, as htmLawed removes \r\n
				$mailClass->getCleanHTML($newBody,false,$preserveHTML); // remove stuff we regard as unwanted
				if ($preserveHTML==false) $newBody = str_replace("<br />","\r\n",$newBody);
				//error_log(__METHOD__.' ('.__LINE__.') '.' after getClean:'.$newBody);
				$message .= $newBody;
				continue;
			}
			$newBody =self::htmlspecialchars($newBody);
			//error_log(__METHOD__.' ('.__LINE__.') '.' Body(after specialchars):'.$newBody);
			$newBody = strip_tags($newBody); //we need to fix broken tags (or just stuff like "<800 USD/p" )
			//error_log(__METHOD__.' ('.__LINE__.') '.' Body(after strip tags):'.$newBody);
			$newBody = htmlspecialchars_decode($newBody,ENT_QUOTES);
			//error_log(__METHOD__.' ('.__LINE__.') '.' Body (after hmlspc_decode):'.$newBody);
			$message .= $newBody;
			//continue;
		}
		return $message;
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
	 * getMessageEnvelope
	 * get parsed headers from message
	 * @param string/int $_uid the messageuid,
	 * @param string/int $_partID='' , the partID, may be omitted
	 * @param boolean $decode flag to do the decoding on the fly
	 * @param string $_folder folder to work on
	 * @param boolean $_useHeaderInsteadOfEnvelope - force getMessageHeader method to be used for fetching Envelope Information
	 * @return array the message header
	 */
	function getMessageEnvelope($_uid, $_partID = '',$decode=false, $_folder='', $_useHeaderInsteadOfEnvelope=false)
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.":$_uid,$_partID,$decode,$_folder".function_backtrace());
		if (empty($_folder)) $_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		//error_log(__METHOD__.' ('.__LINE__.') '.":$_uid,$_partID,$decode,$_folder");
		if((empty($_partID)||$_partID=='null')&&$_useHeaderInsteadOfEnvelope===false) {
			$uidsToFetch = new Horde_Imap_Client_Ids();
			if (!(is_object($_uid) || is_array($_uid))) $_uid = (array)$_uid;
			$uidsToFetch->add($_uid);

			$fquery = new Horde_Imap_Client_Fetch_Query();
			$envFields = new Horde_Mime_Headers();
			$fquery->envelope();
			$headersNew = $this->icServer->fetch($_folder, $fquery, array(
				'ids' => $uidsToFetch,
			));
			if (is_object($headersNew)) {
				foreach($headersNew as $id=>$_headerObject) {
					$env = $_headerObject->getEnvelope();
					//_debug_array($envFields->singleFields());
					foreach ($envFields->singleFields() as $e => $v)
					{
						switch ($v)
						{
							case 'to':
							case 'reply-to':
							case 'from':
							case 'cc':
							case 'bcc':
							case 'sender':
								//error_log(__METHOD__.' ('.__LINE__.') '.$v.'->'.array2string($env->$v->addresses));
								$envelope[$v]=$env->$v->addresses;
								$address = array();
								if (!is_array($envelope[$v])) break;
								foreach ($envelope[$v] as $k => $ad)
								{
									if (stripos($ad,'@')===false)
									{
										$remember=$k;
									}
									else
									{
										$address[] = (!is_null($remember)?$envelope[$v][$remember].' ':'').$ad;
										$remember=null;
									}
								}
								$envelope[$v] = $address;
								break;
							case 'date':
								$envelope[$v]=egw_time::to($env->$v);
								break;
							default:
								$envelope[$v]=$env->$v;
						}
					}
				}
			}
			$envelope = array_change_key_case($envelope,CASE_UPPER);
			//if ($decode) _debug_array($envelope);
			//error_log(__METHOD__.' ('.__LINE__.') '.array2string($envelope));
			return ($decode ? self::decode_header($envelope,true): $envelope);
		} else {

			$headers = $this->getMessageHeader($_uid, $_partID, true,true,$_folder);

			//error_log(__METHOD__.' ('.__LINE__.') '.':'.array2string($headers));
			//_debug_array($headers);
			$newData = array(
				'DATE'		=> $headers['DATE'],
				'SUBJECT'	=> ($decode ? self::decode_header($headers['SUBJECT']):$headers['SUBJECT']),
				'MESSAGE_ID'	=> $headers['MESSAGE-ID']
			);
			//_debug_array($newData);
			$recepientList = array('FROM', 'TO', 'CC', 'BCC', 'SENDER', 'REPLY-TO');
			foreach($recepientList as $recepientType) {
				if(isset($headers[$recepientType])) {
					if ($decode) $headers[$recepientType] =  self::decode_header($headers[$recepientType],true);
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
						$newData[$recepientType][] = ($addressData['RFC822_EMAIL']!='NIL'?$addressData['RFC822_EMAIL']:$addressData['EMAIL']);//$addressData;
					}
				} else {
					if($recepientType == 'SENDER' || $recepientType == 'REPLY-TO') {
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

	/**
	 * getMessageHeader
	 * get parsed headers from message
	 * @param string/int $_uid the messageuid,
	 * @param string/int $_partID='' , the partID, may be omitted
	 * @param boolean $decode flag to do the decoding on the fly
	 * @param boolean $preserveUnSeen flag to preserve the seen flag where applicable
	 * @param string $_folder folder to work on
	 * @return array the message header
	 */
	function getMessageHeader($_uid, $_partID = '',$decode=false, $preserveUnSeen=false, $_folder='')
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.':'.$_uid.', '.$_partID.', '.$decode.', '.$preserveUnSeen.', '.$_folder);
		if (empty($_folder)) $_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		$uidsToFetch = new Horde_Imap_Client_Ids();
		if (!(is_object($_uid) || is_array($_uid))) $_uid = (array)$_uid;
		$uidsToFetch->add($_uid);

		$fquery = new Horde_Imap_Client_Fetch_Query();
		if ($_partID != '')
		{
			$fquery->headerText(array('id'=>$_partID,'peek'=>$preserveUnSeen));
			$fquery->structure();
		}
		else
		{
			$fquery->headerText(array('peek'=>$preserveUnSeen));
		}
		$headersNew = $this->icServer->fetch($_folder, $fquery, array(
			'ids' => $uidsToFetch,
		));
		if (is_object($headersNew)) {
			foreach($headersNew as $id=>$_headerObject) {
				$retValue = $_headerObject->getHeaderText(0,Horde_Imap_Client_Data_Fetch::HEADER_PARSE)->toArray();
				if ($_partID != '')
				{
					$mailStructureObject = $_headerObject->getStructure();
					foreach ($mailStructureObject->contentTypeMap() as $mime_id => $mime_type)
					{
						if ($mime_id==$_partID)
						{
							//error_log(__METHOD__.' ('.__LINE__.') '."$mime_id == $_partID".array2string($_headerObject->getHeaderText($mime_id,Horde_Imap_Client_Data_Fetch::HEADER_PARSE)->toArray()));
							$retValue = $_headerObject->getHeaderText($mime_id,Horde_Imap_Client_Data_Fetch::HEADER_PARSE)->toArray();
							break;
						}
					}
				}
			}
		}
		$retValue = array_change_key_case($retValue,CASE_UPPER);
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($retValue));
		// if SUBJECT is an array, use thelast one, as we assume something with the unfolding for the subject did not work
		if (is_array($retValue['SUBJECT']))
		{
			$retValue['SUBJECT'] = $retValue['SUBJECT'][count($retValue['SUBJECT'])-1];
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.':'.array2string($decode ? self::decode_header($retValue,true):$retValue));
		return ($decode ? self::decode_header($retValue,true):$retValue);
	}

	/**
	 * getMessageRawHeader
	 * get messages raw header data
	 * @param string/int $_uid the messageuid,
	 * @param string/int $_partID='' , the partID, may be omitted
	 * @param string $_folder folder to work on
	 * @return string the message header
	 */
	function getMessageRawHeader($_uid, $_partID = '', $_folder = '')
	{
		static $rawHeaders;
		if (empty($_folder)) $_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		//error_log(__METHOD__.' ('.__LINE__.') '." Try Using Cache for raw Header $_uid, $_partID in Folder $_folder");

		if (is_null($rawHeaders)) $rawHeaders = egw_cache::getCache(egw_cache::INSTANCE,'email','rawHeadersCache'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*60*1);
		if (isset($rawHeaders[$this->icServer->ImapServerId][$_folder][$_uid][(empty($_partID)?'NIL':$_partID)]))
		{
			//error_log(__METHOD__.' ('.__LINE__.') '." Using Cache for raw Header $_uid, $_partID in Folder $_folder");
			return $rawHeaders[$this->icServer->ImapServerId][$_folder][$_uid][(empty($_partID)?'NIL':$_partID)];
		}
		$uidsToFetch = new Horde_Imap_Client_Ids();
		$uid = $_uid;
		if (!(is_object($_uid) || is_array($_uid))) $uid = (array)$_uid;
		$uidsToFetch->add($uid);

		$fquery = new Horde_Imap_Client_Fetch_Query();
		if ($_partID != '')
		{
			$fquery->headerText(array('id'=>$_partID,'peek'=>true));
			$fquery->structure();
		}
		else
		{
			$fquery->headerText(array('peek'=>true));
		}
		$headersNew = $this->icServer->fetch($_folder, $fquery, array(
			'ids' => $uidsToFetch,
		));
		if (is_object($headersNew)) {
			foreach($headersNew as $id=>$_headerObject) {
				$retValue = $_headerObject->getHeaderText();
				if ($_partID != '')
				{
					$mailStructureObject = $_headerObject->getStructure();
					foreach ($mailStructureObject->contentTypeMap() as $mime_id => $mime_type)
					{
						if ($mime_id==$_partID)
						{
							$retValue = $_headerObject->getHeaderText($mime_id);
						}
					}
				}
			}
		}
		$rawHeaders[$this->icServer->ImapServerId][$_folder][$_uid][(empty($_partID)?'NIL':$_partID)]=$retValue;
		egw_cache::setCache(egw_cache::INSTANCE,'email','rawHeadersCache'.trim($GLOBALS['egw_info']['user']['account_id']),$rawHeaders,$expiration=60*60*1);
		return $retValue;
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

			if ($singleBodyPart['charSet']===false) $singleBodyPart['charSet'] = translation::detect_encoding($singleBodyPart['body']);
			$singleBodyPart['body'] = translation::convert(
				$singleBodyPart['body'],
				strtolower($singleBodyPart['charSet'])
			);
			$ct = 0;

			if (stripos($singleBodyPart['body'],'<style')!==false)  $ct = preg_match_all('#<style(?:\s.*)?>(.+)</style>#isU', $singleBodyPart['body'], $newStyle);
			if ($ct>0)
			{
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($newStyle[0]));
				$style2buffer = implode('',$newStyle[0]);
			}
			if ($style2buffer && strtoupper(self::$displayCharset) == 'UTF-8')
			{
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($style2buffer));
				$test = json_encode($style2buffer);
				//error_log(__METHOD__.' ('.__LINE__.') '.'#'.$test.'# ->'.strlen($style2buffer).' Error:'.json_last_error());
				//if (json_last_error() != JSON_ERROR_NONE && strlen($style2buffer)>0)
				if ($test=="null" && strlen($style2buffer)>0)
				{
					// this should not be needed, unless something fails with charset detection/ wrong charset passed
					error_log(__METHOD__.' ('.__LINE__.') '.' Found Invalid sequence for utf-8 in CSS:'.$style2buffer.' Charset Reported:'.$singleBodyPart['charSet'].' Carset Detected:'.translation::detect_encoding($style2buffer));
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
		$css = preg_replace('/(javascript|expression|-moz-binding)/i','',$style);
		if (stripos($css,'script')!==false) translation::replaceTagsCompletley($css,'script'); // Strip out script that may be included
		// we need this, as styledefinitions are enclosed with curly brackets; and template stuff tries to replace everything between curly brackets that is having no horizontal whitespace
		// as the comments as <!-- styledefinition --> in stylesheet are outdated, and ck-editor does not understand it, we remove it
		$css = str_replace(array(':','<!--','-->'),array(': ','',''),$css);
		//error_log(__METHOD__.' ('.__LINE__.') '.$css);
		// TODO: we may have to strip urls and maybe comments and ifs
		return $css;
	}

	/**
	 * getMessageRawBody
	 * get the message raw body
	 * @param string/int $_uid the messageuid,
	 * @param string/int $_partID='' , the partID, may be omitted
	 * @param string $_folder folder to work on
	 * @return string the message body
	 */
	function getMessageRawBody($_uid, $_partID = '', $_folder='')
	{
		//TODO: caching einbauen static!
		static $rawBody;
		if (is_null($rawBody)) $rawBody = array();
		if (empty($_folder)) $_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		if (isset($rawBody[$this->icServer->ImapServerId][$_folder][$_uid][(empty($_partID)?'NIL':$_partID)]))
		{
			//error_log(__METHOD__.' ('.__LINE__.') '." Using Cache for raw Body $_uid, $_partID in Folder $_folder");
			return $rawBody[$this->icServer->ImapServerId][$_folder][$_uid][(empty($_partID)?'NIL':$_partID)];
		}

		$uidsToFetch = new Horde_Imap_Client_Ids();
		$uid = $_uid;
		if (!(is_object($_uid) || is_array($_uid))) $uid = (array)$_uid;
		$uidsToFetch->add($uid);

		$fquery = new Horde_Imap_Client_Fetch_Query();
		$fquery->fullText(array('peek'=>true));
		if ($_partID != '')
		{
			$fquery->structure();
			$fquery->bodyPart($_partID,array('peek'=>true));
		}
		$headersNew = $this->icServer->fetch($_folder, $fquery, array(
			'ids' => $uidsToFetch,
		));
		if (is_object($headersNew)) {
			foreach($headersNew as $id=>$_headerObject) {
				$body = $_headerObject->getFullMsg();
				if ($_partID != '')
				{
					$mailStructureObject = $_headerObject->getStructure();
					//_debug_array($mailStructureObject->contentTypeMap());
					foreach ($mailStructureObject->contentTypeMap() as $mime_id => $mime_type)
					{
						if ($mime_id==$_partID)
						{
							$body = $_headerObject->getBodyPart($mime_id);
						}
					}
				}
			}
		}
		//error_log(__METHOD__.' ('.__LINE__.') '."[$this->icServer->ImapServerId][$_folder][$_uid][".(empty($_partID)?'NIL':$_partID)."]");
		$rawBody[$this->icServer->ImapServerId][$_folder][$_uid][(empty($_partID)?'NIL':$_partID)] = $body;
		return $body;
	}

	/**
	 * Get structure of a mail or part of a mail
	 *
	 * @param int $_uid
	 * @param string $_partID=null
	 * @param string $_folder=null
	 * @param boolean $_preserveSeen=false flag to preserve the seenflag by using body.peek
	 * @param Horde_Imap_Client_Fetch_Query $fquery=null default query just structure
	 * @return Horde_Mime_Part
	 */
	function getStructure($_uid, $_partID=null, $_folder=null, $_preserveSeen=false)
	{
		if (self::$debug) error_log( __METHOD__.' ('.__LINE__.') '.":$_uid, $_partID");

		if (empty($_folder))
		{
			$_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());
		}
		$uidsToFetch = new Horde_Imap_Client_Ids();
		if (!(is_object($_uid) || is_array($_uid))) $_uid = (array)$_uid;
		$uidsToFetch->add($_uid);
		try
		{
			$_fquery = new Horde_Imap_Client_Fetch_Query();
	// not sure why Klaus add these, seem not necessary
	//		$fquery->envelope();
	//		$fquery->size();
			$_fquery->structure();
			if ($_partID) $_fquery->bodyPart($_partID, array('peek' => $_preserveSeen));

			$mail = $this->icServer->fetch($_folder, $_fquery, array(
				'ids' => $uidsToFetch,
			))->first();

			return $mail->getStructure();
		}
		catch (Exception $e)
		{
			error_log(__METHOD__.' ('.__LINE__.') '.' Could not fetch structure on mail:'.$_uid.' Serverprofile->'.$this->icServer->ImapServerId.' Message:'.$e->getMessage().' Stack:'.function_backtrace());
			return null;
		}
	}

	/**
	 * Parse the structure for attachments
	 *
	 * Returns not the attachments itself, but an array of information about the attachment
	 *
	 * @param int $_uid the messageuid,
	 * @param string $_partID=null , the partID, may be omitted
	 * @param Horde_Mime_Part $_structure=null if given use structure for parsing
	 * @param boolean $fetchEmbeddedImages=true,
	 * @param boolean $fetchTextCalendar=false,
	 * @param boolean $resolveTNEF=true
	 * @param string $_folder folder to work on
	 * @return array  an array of information about the attachment: array of array(name, size, mimeType, partID, encoding)
	 */
	function getMessageAttachments($_uid, $_partID=null, Horde_Mime_Part $_structure=null, $fetchEmbeddedImages=true, $fetchTextCalendar=false, $resolveTNEF=true, $_folder='')
	{
		if (self::$debug) error_log( __METHOD__.":$_uid, $_partID");
		if (empty($_folder)) $_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());

		if (!isset($_structure))
		{
			$_structure = $this->getStructure($_uid, $_partID,$_folder,true);
			//error_log(__METHOD__.' ('.__LINE__.') '.':'.print_r($_structure->contentTypeMap(),true));
		}
		if (!$_structure || !$_structure->contentTypeMap()) return array();
		if (!empty($_partID)) $_structure = $_structure->getPart($_partID);
		$skipParts = array();
		foreach($_structure->contentTypeMap() as $mime_id => $mime_type)
		{
			$part = $_structure->getPart($mime_id);
			//error_log(__METHOD__.' ('.__LINE__.') '.':'.' Uid:'.$uid.' Part:'.$_partID.'->'.array2string($part->getMimeId()));
			//error_log(__METHOD__.' ('.__LINE__.') '.':'.' Uid:'.$uid.' Part:'.$_partID.'->'.$part->getPrimaryType().'/'.$part->getSubType().'->'.$part->getDisposition());
			//error_log(__METHOD__.' ('.__LINE__.') '.':'.' Uid:'.$uid.' Part:'.$_partID.'->'.array2string($part->getAllDispositionParameters()));
			//error_log(__METHOD__.' ('.__LINE__.') '.':'.' Uid:'.$uid.' Part:'.$_partID.'->'.array2string($part->getAllContentTypeParameters()));
			$partDisposition = $part->getDisposition();
			$partPrimaryType = $part->getPrimaryType();
			// we only want to retrieve the attachments of the current mail, not those of possible
			// attached mails
			if ($mime_type=='message/rfc822' && $_partID!=$mime_id)
			{
				//error_log(__METHOD__.' ('.__LINE__.') '.' Uid:'.$uid.'->'.$mime_id.':'.array2string($part->contentTypeMap()));
				foreach($part->contentTypeMap() as $sub_id => $sub_type) if ($sub_id != $mime_id) $skipParts[$sub_id] = $sub_type;
			}
			//error_log(__METHOD__.' ('.__LINE__.') '.' Uid:'.$uid.' Part:'.$_partID.'->'.$mime_id.':'.array2string($skipParts));
			if (array_key_exists($mime_id,$skipParts)) continue;

			if ($partDisposition == 'attachment' ||
				(($partDisposition == 'inline' || empty($partDisposition)) && $partPrimaryType == 'image' && $part->getContentId()=='') ||
				(($partDisposition == 'inline' || empty($partDisposition)) && $partPrimaryType != 'image' && $partPrimaryType != 'text' && $partPrimaryType != 'multipart') ||
				($fetchEmbeddedImages && ($partDisposition == 'inline' || empty($partDisposition)) && $partPrimaryType == 'image') ||
				($fetchTextCalendar && $partPrimaryType == 'text' && $part->getSubType() == 'calendar'))
			{
				// if type is message/rfc822 and _partID is given, and MimeID equals partID
				// we attempt to fetch "ourselves"
				if ($_partID==$part->getMimeId() && $part->getPrimaryType()=='message') continue;
				$attachment = $part->getAllDispositionParameters();
				$attachment['mimeType'] = $mime_type;
				$attachment['uid'] = $_uid;
				$attachment['partID'] = $mime_id;
				if (!isset($attachment['name'])||empty($attachment['name'])) $attachment['name'] = $part->getName();
				if ($fetchTextCalendar)
				{
					//error_log(__METHOD__.' ('.__LINE__.') '.array2string($part->getAllContentTypeParameters()));
					$method = $part->getContentTypeParameter('method');
					if ($method) $attachment['method'] = $method;
					if (!isset($attachment['name'])) $attachment['name'] = 'event.ics';
				}
				$attachment['size'] = $part->getBytes();
				if (($cid = $part->getContentId())) $attachment['cid'] = $cid;
				if (empty($attachment['name'])) $attachment['name'] = (isset($attachment['cid'])&&!empty($attachment['cid'])?$attachment['cid']:lang("unknown").'_Uid'.$_uid.'_Part'.$mime_id).'.'.mime_magic::mime2ext($mime_type);
				$attachments[] = $attachment;
			}
		}
		return $attachments;
	}

	/**
	 * retrieve a attachment
	 *
	 * @param int _uid the uid of the message
	 * @param string _partID the id of the part, which holds the attachment
	 * @param int _winmail_nr winmail.dat attachment nr.
	 * @param boolean _returnPart flag to indicate if the attachment is to be returned as horde mime part object
	 * @param boolean _stream flag to indicate if the attachment is to be fetched or returned as filepointer
	 *
	 * @return array
	 */
	function getAttachment($_uid, $_partID, $_winmail_nr=0, $_returnPart=true, $_stream=false)
	{
		$_folder = ($this->sessionData['mailbox']? $this->sessionData['mailbox'] : $this->icServer->getCurrentMailbox());

		$uidsToFetch = new Horde_Imap_Client_Ids();
		if (!(is_object($_uid) || is_array($_uid))) $_uid = (array)$_uid;
		$uidsToFetch->add($_uid);

		$fquery = new Horde_Imap_Client_Fetch_Query();
		$fquery->structure();
		$fquery->bodyPart($_partID, array('peek'=>true));
		$headersNew = $this->icServer->fetch($_folder, $fquery, array(
			'ids' => $uidsToFetch,
		));
		if (is_object($headersNew)) {
			foreach($headersNew as $id=>$_headerObject) {
				$body = $_headerObject->getFullMsg();
				if ($_partID != '')
				{
					$mailStructureObject = $_headerObject->getStructure();
					$mailStructureObject->contentTypeMap();
					$part = $mailStructureObject->getPart($_partID);
					$partDisposition = ($part?$part->getDisposition():'failed');
					if ($partDisposition=='failed')
					{
						error_log(__METHOD__.'('.__LINE__.'):'.array2string($_uid).','.$_partID.' ID:'.$id.' HObject:'.array2string($_headerObject).' StructureObject:'.array2string($mailStructureObject->contentTypeMap()).'->'.function_backtrace());
					}
					// if $partDisposition is empty, we assume attachment, and hope that the function
					// itself is only triggered to fetch attachments
					if (empty($partDisposition)) $partDisposition='attachment';
					if ($part && ($partDisposition=='attachment' || $partDisposition=='inline' || ($part->getPrimaryType() == 'text' && $part->getSubType() == 'calendar')))
					{
						$headerObject['ATTACHMENTS'][$mime_id]=$part->getAllDispositionParameters();

						$structure_bytes = $part->getBytes();
						$structure_mime=$part->getType();
						$structure_partID=$part->getMimeId();
						$filename=$part->getName();
						$this->fetchPartContents($_uid, $part, $_stream, $_preserveSeen=true);
						if ($_returnPart) return $part;
					}
				}
			}
		}
		$ext = mime_magic::mime2ext($structure_mime);
		if ($ext && stripos($filename,'.')===false && stripos($filename,$ext)===false) $filename = trim($filename).'.'.$ext;
		$attachmentData = array(
			'type'		=> $structure_mime,
			'filename'	=> $filename,
			'attachment'	=> $part->getContents(array('stream'=>$_stream))
			);
/*
		// try guessing the mimetype, if we get the application/octet-stream
		if (strtolower($attachmentData['type']) == 'application/octet-stream') $attachmentData['type'] = mime_magic::filename2mime($attachmentData['filename']);
		# if the attachment holds a winmail number and is a winmail.dat then we have to handle that.
		if ( $filename == 'winmail.dat' && $_winmail_nr > 0 &&
			( $wmattach = $this->decode_winmail( $_uid, $_partID, $_winmail_nr ) ) )
		{
			$ext = mime_magic::mime2ext($wmattach['type']);
			if ($ext && stripos($wmattach['name'],'.')===false && stripos($wmattach['name'],$ext)===false) $wmattach['name'] = trim($wmattach['name']).'.'.$ext;
			$attachmentData = array(
				'type'       => $wmattach['type'],
				'filename'   => $wmattach['name'],
				'attachment' => $wmattach['attachment'],
			);
		}
*/
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
	 * @param boolean $_stream=null null do NOT fetch content, use fetchPartContents later
	 *	true:
	 * @return Horde_Mime_Part
	 */
	function getAttachmentByCID($_uid, $_cid, $_part, $_stream=null)
	{
		// some static variables to avoid fetching the same mail multiple times
		static $uid=null, $part=null, $structure=null;
		//error_log(__METHOD__.' ('.__LINE__.') '.":$_uid, $_cid, $_part");

		if(empty($_cid)) return false;

		if ($_uid != $uid || $_part != $part)
		{
			$structure = $this->getStructure($uid=$_uid, $part=$_part);
		}
		/** @var Horde_Mime_Part */
		$attachment = null;
		foreach($structure->contentTypeMap() as $mime_id => $mime_type)
		{
			$part = $structure->getPart($mime_id);

			if ($part->getPrimaryType() == 'image' &&
				(($cid = $part->getContentId()) &&
				// RB: seem a bit fague to search for inclusion in both ways
				(strpos($cid, $_cid) !== false || strpos($_cid, $cid) !== false)) ||
				(($name = $part->getName()) &&
				(strpos($name, $_cid) !== false || strpos($_cid, $name) !== false)))
			{
				// if we have a direct match, dont search any further
				if ($cid == $_cid)
				{
					$attachment = $part;
				}
				// everything else we only consider after we checked all
				if (!isset($attachment)) $attachment = $part;
				// do we want content fetched, can be done later, if not needed
				if (isset($_stream))
				{
					$this->fetchPartContents($_uid, $attachment, $_stream);
				}
				if (isset($attachment)) break;
			}
		}
		// set name as filename, if not set
		if ($attachment && !$attachment->getDispositionParameter('filename'))
		{
			$attachment->setDispositionParameter('filename', $attachment->getName());
		}
		// guess type, if not set
		if ($attachment && $attachment->getType() == 'application/octet-stream')
		{
			$attachment->setType(mime_magic::filename2mime($attachment->getDispositionParameter('filename')));
		}
		//error_log(__METHOD__."($_uid, '$_cid', '$_part') returning ".array2string($attachment));
		return $attachment;
	}

	/**
	 * Fetch and add contents to a part
	 *
	 * To get contents you use $part->getContents();
	 *
	 * @param int $_uid
	 * @param Horde_Mime_Part $part
	 * @param boolean $_stream=false true return a stream, false a string
	 * @param boolean $_preserveSeen flag to preserve the seenflag by using body.peek
	 * @return Horde_Mime_Part
	 */
	public function fetchPartContents($_uid, Horde_Mime_Part $part=null, $_stream=false, $_preserveSeen=false)
	{
		if (is_null($part)) return null;//new Horde_Mime_Part;
		$encoding = null;
		// we need to set content on structure to decode transfer encoding
		$part->setContents(
			$this->getBodyPart($_uid, $part->getMimeId(), null, $_preserveSeen, $_stream, $encoding),
			array('encoding' => $encoding));

		return $part;
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
		//error_log(__METHOD__.' ('.__LINE__.') '."$_folderName, $_header, $_body, $_flags");
		$header = ltrim(str_replace("\n","\r\n",$_header));
		$body   = str_replace("\n","\r\n",$_body);
		// the recent flag is the default enforced here ; as we assume the _flags is always set,
		// we default it to hordes default (Recent) (, other wise we should not pass the parameter
		// for flags at all)
		if (empty($_flags)) $_flags = '\\Recent';
		//if (!is_array($_flags) && stripos($_flags,',')!==false) $_flags=explode(',',$_flags);
		//if (!is_array($_flags)) $_flags = (array) $_flags;
		try
		{
			$dataNflags = array();
			$dataNflags[] = array('data'=>array(array('t'=>'text','v'=>"$header"."$body")), 'flags'=>array($_flags));
			$messageid = $this->icServer->append($_folderName,$dataNflags);
		}
		catch (Exception $e)
		{
			if (self::$debug) error_log("Could not append Message:".$e->getMessage());
			throw new egw_exception_wrong_userinput(lang("Could not append Message:".$e->getMessage));
			//return false;
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.' appended UID:'.$messageid);
		//$messageid = true; // for debug reasons only
		if ($messageid === true) // try to figure out the message uid
		{
			$list = $this->getHeaders($_folderName, $_startMessage=1, $_numberOfMessages=1, $_sort='INTERNALDATE', $_reverse=true, $_filter=array(),$_thisUIDOnly=null, $_cacheResult=false);
			if ($list)
			{
				if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' MessageUid:'.$messageid.' but found:'.array2string($list));
				$messageid = $list['header'][0]['uid'];
			}
		}
		return $messageid;
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
	 * @param object mailClass the mailClassobject to be used
	 * @param uid the uid of the email to be processed
	 * @param partid the partid of the email
	 * @param mailbox the mailbox, that holds the message
	 * @param preserveHTML flag to pass through to getdisplayableBody
	 * @param addHeaderSection flag to be able to supress headersection
	 * @param includeAttachments flag to be able to supress possible attachments
	 * @return array/bool with 'mailaddress'=>$mailaddress,
	 *				'subject'=>$subject,
	 *				'message'=>$message,
	 *				'attachments'=>$attachments,
	 *				'headers'=>$headers,; boolean false on failure
	 */
	static function get_mailcontent(&$mailClass,$uid,$partid='',$mailbox='', $preserveHTML = false, $addHeaderSection=true, $includeAttachments=true)
	{
			//echo __METHOD__." called for $uid,$partid <br>";
			$headers = $mailClass->getMessageHeader($uid,$partid,true,false,$mailbox);
			if (empty($headers)) return false;
			// dont force retrieval of the textpart, let mailClass preferences decide
			$bodyParts = $mailClass->getMessageBody($uid,($preserveHTML?'always_display':'only_if_no_text'),$partid,null,false,$mailbox);
			// if we do not want HTML but there is no TextRepresentation with the message itself, try converting
			if ( !$preserveHTML && $bodyParts[0]['mimeType']=='text/html')
			{
				foreach($bodyParts as $i => $part)
				{
					if ($bodyParts[$i]['mimeType']=='text/html')
					{
						$bodyParts[$i]['body'] = translation::convertHTMLToText($bodyParts[$i]['body'],$bodyParts[$i]['charSet'],true,$stripalltags=true);
						$bodyParts[$i]['mimeType']='text/plain';
					}
				}
			}
			//error_log(array2string($bodyParts));
			$attachments = $includeAttachments?$mailClass->getMessageAttachments($uid,$partid):array();

			if ($mailClass->isSentFolder($mailbox)) $mailaddress = $headers['TO'];
			elseif (isset($headers['FROM'])) $mailaddress = $headers['FROM'];
			elseif (isset($headers['SENDER'])) $mailaddress = $headers['SENDER'];
			if (isset($headers['CC'])) $mailaddress .= ','.$headers['CC'];
			//_debug_array(array($headers,$mailaddress));
			$subject = $headers['SUBJECT'];

			$message = self::getdisplayableBody($mailClass, $bodyParts, $preserveHTML);
			if ($preserveHTML && $mailClass->activeMimeType == 'text/plain') $message = '<pre>'.$message.'</pre>';
			$headdata = ($addHeaderSection ? self::createHeaderInfoSection($headers, '',$preserveHTML) : '');
			$message = $headdata.$message;
			//echo __METHOD__.'<br>';
			//_debug_array($attachments);
			if (is_array($attachments))
			{
				foreach ($attachments as $num => $attachment)
				{
					if ($attachment['mimeType'] == 'MESSAGE/RFC822')
					{
						//_debug_array($mailClass->getMessageHeader($uid, $attachment['partID']));
						//_debug_array($mailClass->getMessageBody($uid,'', $attachment['partID']));
						//_debug_array($mailClass->getMessageAttachments($uid, $attachment['partID']));
						$mailcontent = self::get_mailcontent($mailClass,$uid,$attachment['partID']);
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
						$attachments[$num] = array_merge($attachments[$num],$mailClass->getAttachment($uid, $attachment['partID'],0,false,false));
						if (empty($attachments[$num]['attachment'])&&$attachments[$num]['cid'])
						{
							$c = $mailClass->getAttachmentByCID($uid, $attachment['cid'], $attachment['partID'],true);
							$attachments[$num]['attachment'] = $c->getContents();
						}
						if (isset($attachments[$num]['charset'])) {
							if ($attachments[$num]['charset']===false) $attachments[$num]['charset'] = translation::detect_encoding($attachments[$num]['attachment']);
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
	 * @param string headline Text tom use for headline, if SUPPRESS, supress headline and footerline
	 * @param bool createHTML do it with HTML breaks
	 * @return string a preformatted string with the information of the header worked into it
	 */
	static function createHeaderInfoSection($header,$headline='', $createHTML = false)
	{
		$headdata = null;
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($header).function_backtrace());
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
			if (!empty($headline) && $headline != 'SUPPRESS') $headdata = "---------------------------- $headline ----------------------------".($createHTML?"<br />":"\n").$headdata;
			if (empty($headline)) $headdata = ($headline != 'SUPPRESS'?"--------------------------------------------------------".($createHTML?"<br />":"\n"):'').$headdata;
			$headdata .= ($headline != 'SUPPRESS'?"--------------------------------------------------------".($createHTML?"<br />":"\n"):'');
		}
		else
		{
			$headdata = ($headline != 'SUPPRESS'?"--------------------------------------------------------".($createHTML?"<br />":"\n"):'');
		}
		return $headdata;
	}

	/**
	 * adaptSubjectForImport - strips subject from unwanted Characters, and does some normalization
	 * to meet expectations
	 * @param string $subject string to process
	 * @return string
	 */
	static function adaptSubjectForImport($subject)
	{
		$subject = str_replace('$$','__',($subject?$subject:lang('(no subject)')));
		$subject = str_ireplace(array('[FWD]','[',']','{','}','<','>'),array('Fwd:',' ',' ',' ',' ',' ',' '),trim($subject));
		return $subject;
	}

	/**
	 * convertAddressArrayToString - converts an mail envelope Address Array To String
	 * @param array $rfcAddressArray  an addressarray as provided by mail retieved via egw_pear....
	 * @return string a comma separated string with the mailaddress(es) converted to text
	 */
	static function convertAddressArrayToString($rfcAddressArray, $createHTML = false)
	{
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($rfcAddressArray));
		$returnAddr ='';
		if (is_array($rfcAddressArray))
		{
			foreach((array)$rfcAddressArray as $addressData) {
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($addressData));
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
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($addressObject));
				if ($addressObject->host == '.SYNTAX-ERROR.') continue;
				//$mb =(string)$addressObject->mailbox;
				//$h = (string)$addressObject->host;
				//$p = (string)$addressObject->personal;
				$returnAddr .= (strlen($returnAddr)>0?',':'');
				//error_log(__METHOD__.' ('.__LINE__.') '.$p.' <'.$mb.'@'.$h.'>');
				$buff = imap_rfc822_write_address($addressObject->mailbox, self::$idna2->decode($addressObject->host), $addressObject->personal);
				$buff = str_replace(array('<','>'),array('[',']'),$buff);
				if ($createHTML) $buff = emailadmin_imapbase::htmlspecialchars($buff);
				//error_log(__METHOD__.' ('.__LINE__.') '.' Address: '.$returnAddr);
				$returnAddr .= $buff;
			}
		}
		else
		{
			// do not mess with strings, return them untouched /* ToDo: validate string as Address */
			$rfcAddressArray = self::decode_header($rfcAddressArray,true);
			$rfcAddressArray = str_replace(array('<','>'),array('[',']'),$rfcAddressArray);
			if (is_string($rfcAddressArray)) return ($createHTML ? emailadmin_imapbase::htmlspecialchars($rfcAddressArray) : $rfcAddressArray);
		}
		return $returnAddr;
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
		$rv = $mergeobj->merge_string($content,$ids,$err,$mimetype, array(), self::$displayCharset);
		if (empty($rv) && !empty($content) && !empty($err)) $rv = $content;
		if (!empty($err) && !empty($content) && !empty($ids)) error_log(__METHOD__.' ('.__LINE__.') '.' Merge failed for Ids:'.array2string($ids).' ContentType:'.$mimetype.' Content:'.$content.' Reason:'.array2string($err));
		return $rv;
	}

	/**
	 * Returns a string showing the size of the message/attachment
	 *
	 * @param integer $bytes
	 * @return string formatted string
	 */
	static function show_readable_size($bytes)
	{
		$bytes /= 1024;
		$type = 'k';

		if ($bytes / 1024 > 1)
		{
			$bytes /= 1024;
			$type = 'M';

			if ($bytes / 1024 > 1)
			{
				$bytes *= 10;
				settype($bytes, 'integer');
				$bytes /= 10;
				$bytes /= 1024;
				$type = 'G';
			}

		}

		if ($bytes < 10)
		{
			$bytes *= 10;
			settype($bytes, 'integer');
			$bytes /= 10;
		}
		else
			settype($bytes, 'integer');

		return $bytes . ' ' . $type ;
	}

	static function detect_qp(&$sting) {
		$needle = '/(=[0-9][A-F])|(=[A-F][0-9])|(=[A-F][A-F])|(=[0-9][0-9])/';
		return preg_match("$needle",$string);
	}

	/**
	 * logRunTimes
	 *	logs to the error log all parameters given; output only if self::$debugTimes is true
	 *
	 * @param int $_starttime starttime of the action measured based on microtime(true)
	 * @param int $_endtime endtime of the action measured, if not given microtime(true) is used
	 * @param string $_message message to output details or params, whatever seems neccesary
	 * @param string $_methodNline - Information where the log was taken
	 * @return void
	 */
	static function logRunTimes($_starttime,$_endtime=null,$_message='',$_methodNline='')
	{
		if (is_null($_endtime)) $_endtime = microtime(true);
		$usagetime = microtime(true) - $_starttime;
		if (self::$debugTimes) error_log($_methodNline.' took:'.number_format($usagetime,5).'(s) '.($_message?'Details:'.$_message:''));
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
					error_log(__METHOD__.' ('.__LINE__.') '.' Data:'.array2string($_formData));
					error_log(__METHOD__.' ('.__LINE__.') '.' Form reported Mimetype:'.$_formData['type'].' but seems to be:'.$sfxMimeType);
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
		$imageC = 0;
		preg_match_all("/(src|background)=\"(.*)\"/Ui", $_html2parse, $images);
		if(isset($images[2])) {
			foreach($images[2] as $i => $url) {
				//$isData = false;
				$basedir = '';
				$needTempFile = true;
				//error_log(__METHOD__.' ('.__LINE__.') '.$url);
				//error_log(__METHOD__.' ('.__LINE__.') '.$GLOBALS['egw_info']['server']['webserver_url']);
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($GLOBALS['egw_info']['user']));
				// do not change urls for absolute images (thanks to corvuscorax)
				if (!(substr($url,0,strlen('data:'))=='data:')) {
					//error_log(__METHOD__.' ('.__LINE__.') '.' -> '.$i.': '.array2string($images[$i]));
					$filename = basename($url);
					$directory = dirname($url);
					($directory == '.')?$directory='':'';
					$cid = 'cid:' . md5($filename);
					$ext = pathinfo($filename, PATHINFO_EXTENSION);
					$mimeType  = $_mailObject->_mime_types($ext);
					if ( strlen($directory) > 1 && substr($directory,-1) != '/') { $directory .= '/'; }
					$myUrl = $directory.$filename;
					if ($myUrl[0]=='/') // local path -> we only allow path's that are available via http/https (or vfs)
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
					//error_log(__METHOD__.' ('.__LINE__.') '.$basedir.$myUrl);
					if ($needTempFile) $data = file_get_contents($basedir.urldecode($myUrl));
				}
				if (substr($url,0,strlen('data:'))=='data:')
				{
					//error_log(__METHOD__.' ('.__LINE__.') '.' -> '.$i.': '.array2string($images[$i]));
					// we only support base64 encoded data
					$tmp = substr($url,strlen('data:'));
					list($mimeType,$data) = explode(';base64,',$tmp);
					list($what,$exactly) = explode('/',$mimeType);
					$needTempFile = true;
					$filename = ($what?$what:'data').$imageC++.'.'.$exactly;
					$cid = 'cid:' . md5($filename);
					$data = base64_decode($data);
					//$isData = true;
				}
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
					//error_log(__METHOD__.' ('.__LINE__.') '.' '.$url.' -> '.$basedir.$myUrl. ' TmpFile:'.$tmpfile);
					//error_log(__METHOD__.' ('.__LINE__.') '.' '.$url.' -> '.$mimeType. ' TmpFile:'.$attachment_file);
					if ( $_mailObject->AddEmbeddedImage($attachment_file, md5($filename), $filename, 'base64',$mimeType) )
					{
						//$_html2parse = preg_replace("/".$images[1][$i]."=\"".preg_quote($url, '/')."\"/Ui", $images[1][$i]."=\"".$cid."\"", $_html2parse);
						$_html2parse = str_replace($images[1][$i]."=\"".$url."\"", $images[1][$i]."=\"".$cid."\"", $_html2parse);
					}
				}
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
	function importMessageToMergeAndSend(bo_merge $bo_merge, $document, $SendAndMergeTocontacts, &$_folder, &$importID='')
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
			$tmpFileName = emailadmin_imapbase::checkFileBasics($_formData,$importID);
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
				//error_log(__METHOD__.' ('.__LINE__.') '.' Subject:'.$Subject);
				$Body = $mailObject->Body;
				//error_log(__METHOD__.' ('.__LINE__.') '.' Body:'.$Body);
				//error_log(__METHOD__.' ('.__LINE__.') '.' BodyContentType:'.$mailObject->BodyContentType);
				$AltBody = $mailObject->AltBody;
				//error_log(__METHOD__.' ('.__LINE__.') '.' AltBody:'.$AltBody);
				//error_log(__METHOD__.' ('.__LINE__.') '.array2string($mailObject->GetReplyTo()));
				// Fetch ReplyTo - Address if existing to check if we are to replace it
				$replyTo = $mailObject->GetReplyTo();
				if (isset($replyTo['replace@import.action']))
				{
					$mailObject->ClearReplyTos();
					$activeMailProfiles = $this->mail->getAccountIdentities($this->profileID);
					$activeMailProfile = array_shift($activeMailProfiles);

					$mailObject->AddReplyTo(self::$idna2->encode($activeMailProfile['ident_email']),emailadmin_imapbase::generateIdentityString($activeMailProfile,false));
				}
				foreach ($SendAndMergeTocontacts as $k => $val)
				{
					$mailObject->ErrorInfo = $errorInfo = '';
					//$mailObject->SMTPDebug = 5;
					$mailObject->set('error_count',0);
					$sendOK = $openComposeWindow = $openAsDraft = null;
					//error_log(__METHOD__.' ('.__LINE__.') '.' Id To Merge:'.$val);
					if (/*$GLOBALS['egw_info']['flags']['currentapp'] == 'addressbook' &&*/
						count($SendAndMergeTocontacts) > 1 &&
						is_numeric($val) || $GLOBALS['egw']->accounts->name2id($val)) // do the merge
					{

						//error_log(__METHOD__.' ('.__LINE__.') '.array2string($mailObject));
						$contact = $bo_merge->contacts->read($val);
						//error_log(__METHOD__.' ('.__LINE__.') '.' ID:'.$val.' Data:'.array2string($contact));
						$email = ($contact['email'] ? $contact['email'] : $contact['email_home']);
						$nfn = ($contact['n_fn'] ? $contact['n_fn'] : $contact['n_given'].' '.$contact['n_family']);
						$activeMailProfiles = $this->getAccountIdentities($this->profileID);
						$activeMailProfile = array_shift($activeMailProfiles);
						//error_log(__METHOD__.' ('.__LINE__.') '.array2string($activeMailProfile));
						$mailObject->From = $activeMailProfile['ident_email'];
						//$mailObject->From  = $_identity->emailAddress;
						$mailObject->FromName = $mailObject->EncodeHeader(self::generateIdentityString($activeMailProfile,false));

						$mailObject->MessageID = '';
						$mailObject->ClearAllRecipients();
						$mailObject->ClearCustomHeaders();
						$mailObject->AddAddress(self::$idna2->encode($email),$mailObject->EncodeHeader($nfn));
						$mailObject->Subject = $bo_merge->merge_string($Subject, $val, $e, 'text/plain', array(), self::$displayCharset);
						if (!empty($AltBody))
						{
							$mailObject->IsHTML(true);
						}
						elseif (empty($AltBody) && $mailObject->BodyContentType=='text/html')
						{
							$mailObject->IsHTML(true);
							$AltBody = translation::convertHTMLToText($Body,self::$displayCharset,false,$stripalltags=true);
						}
						else
						{
							$mailObject->IsHTML(false);
						}
						//error_log(__METHOD__.' ('.__LINE__.') '.' ContentType:'.$mailObject->BodyContentType);
						if (!empty($Body)) $mailObject->Body = $bo_merge->merge_string($Body, $val, $e, $mailObject->BodyContentType, array(), self::$displayCharset);
						//error_log(__METHOD__.' ('.__LINE__.') '.' Result:'.$mailObject->Body.' error:'.array2string($e));
						if (!empty($AltBody)) $mailObject->AltBody = $bo_merge->merge_string($AltBody, $val, $e, $mailObject->AltBodyContentType, array(), self::$displayCharset);

						$ogServer = emailadmin_account::read($this->profileID)->smtpServer();
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
						//error_log(__METHOD__.' ('.__LINE__.') '.array2string($mailObject));
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
									$errorInfo = 'Send Failed for '.$mailObject->Subject.' to '.$nfn.'<'.$email.'> Error:'.$mailObject->ErrorInfo;
								}
							}
							//error_log(__METHOD__.' ('.__LINE__.') '.array2string($errorInfo));
						}
					}
					elseif (!$k)	// 1. entry, further entries will fail for apps other then addressbook
					{
						$openAsDraft = true;
						$mailObject->MessageID = '';
						$mailObject->ClearAllRecipients();
						$mailObject->ClearCustomHeaders();
						if (/*$GLOBALS['egw_info']['flags']['currentapp'] == 'addressbook' &&*/
							is_numeric($val) || $GLOBALS['egw']->accounts->name2id($val)) // do the merge
						{
							$contact = $bo_merge->contacts->read($val);
							//error_log(__METHOD__.' ('.__LINE__.') '.array2string($contact));
							$email = ($contact['email'] ? $contact['email'] : $contact['email_home']);
							$nfn = ($contact['n_fn'] ? $contact['n_fn'] : $contact['n_given'].' '.$contact['n_family']);
							$mailObject->AddAddress(self::$idna2->encode($email),$mailObject->EncodeHeader($nfn));
						}
						$mailObject->Subject = $bo_merge->merge_string($Subject, $val, $e, 'text/plain', array(), self::$displayCharset);
						if (!empty($AltBody))
						{
							$mailObject->IsHTML(true);
						}
						elseif (empty($AltBody) && $mailObject->BodyContentType=='text/html')
						{
							$mailObject->IsHTML(true);
							$AltBody = translation::convertHTMLToText($Body,self::$displayCharset,false,$stripalltags=true);
						}
						else
						{
							$mailObject->IsHTML(false);
						}
						//error_log(__METHOD__.' ('.__LINE__.') '.' ContentType:'.$mailObject->BodyContentType);
						if (!empty($Body)) $mailObject->Body = $bo_merge->merge_string($Body, $val, $e, $mailObject->BodyContentType, array(), self::$displayCharset);
						//error_log(__METHOD__.' ('.__LINE__.') '.' Result:'.$mailObject->Body.' error:'.array2string($e));
						if (!empty($AltBody)) $mailObject->AltBody = $bo_merge->merge_string($AltBody, $val, $e, $mailObject->AltBodyContentType, array(), self::$displayCharset);
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
							if ($savefailed===false && $messageUid && is_null($sendOK))
							{
								$importID = $messageUid;
								$openComposeWindow = true;
							}
						}
						else
						{
							$savefailed = true;
							$alert_msg .= lang("Saving of message %1 failed. Destination Folder %2 does not exist.",$Subject,$_folder);
						}
						if ($sendOK)
						{
							$processStats['success'][$val] = 'Send succeeded to '.$nfn.'<'.$email.'>'.($savefailed?' but failed to store to Folder:'.$_folder:'');
						}
						else
						{
							if (!$openComposeWindow) $processStats['failed'][$val] = $errorInfo?$errorInfo:'Send failed to '.$nfn.'<'.$email.'> See error_log for details';
						}
					}
					if (!is_null($sendOK) && $sendOK===false && is_null($openComposeWindow))
					{
						$processStats['failed'][$val] = $errorInfo?$errorInfo:'Send failed to '.$nfn.'<'.$email.'> See error_log for details';
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
			//error_log(__METHOD__.' ('.__LINE__.') '.array2string($processStats));
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
	 * @param mixed string/object $message string containing the RawMessage / object Mail_mimeDecoded message (part))
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
			//error_log(__METHOD__.' ('.__LINE__.') '.$message);
			if (class_exists('Mail_mimeDecode',false)==false && (@include_once 'Mail/mimeDecode.php') === false) throw new egw_exception_assertion_failed(lang('Required PEAR class Mail/mimeDecode.php not found.'));
			if (is_string($message))
			{
				$mailDecode = new Mail_mimeDecode($message);
				$structure = $mailDecode->decode(array('include_bodies'=>true,'decode_bodies'=>true,'decode_headers'=>true));
			}
			if (is_object($message))
			{
				$structure = $message;
			}
			//error_log(__METHOD__.' ('.__LINE__.') '.array2string($structure));
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
			$myReplyTo = '';
			foreach((array)$structure->headers as $key => $val)
			{
				//error_log(__METHOD__.' ('.__LINE__.') '.$key.'->'.$val);
				foreach((array)$val as $i => $v)
				{
					if ($key!='content-type' && $key !='content-transfer-encoding' &&
						$key != 'message-id'  &&
						$key != 'subject' &&
						$key != 'from' &&
						$key != 'to' &&
						$key != 'cc' &&
						$key != 'bcc' &&
						$key != 'reply-to' &&
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
					case 'reply-to':
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
						if ($key=='reply-to')
						{
							$myReplyTo = ${$key};
							//break; // break early as we add that later
						}
						$Header .= $mailObject->TextLine(trim($mailObject->AddrAppend(ucfirst($key),${$key})));
						break;
					case 'content-transfer-encoding':
						$mailObject->Encoding = $val;
						break;
					case 'content-type':
						//error_log(__METHOD__.' ('.__LINE__.') '.' '.$key.'->'.$val);
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
					case 'disposition-notification-to':
					case 'organization':
						foreach((array)$val as $i => $v) $mailObject->AddCustomHeader($key.': '. $v);
						break;
				}
			}
			// handle reply-to, wich may be set, set the first one found
			if (!empty($myReplyTo))
			{
				$mailObject->ClearReplyTos();
				$mailObject->AddReplyTo($myReplyTo[0][0],$myReplyTo[0][1]);
			}

			$seemsToBePlainMessage = false;
			if (strtolower($structure->ctype_primary)=='text' && $structure->body)
			{
				$mailObject->IsHTML(strtolower($structure->ctype_secondary)=='html'?true:false);
				if (strtolower($structure->ctype_primary) == 'text' && strtolower($structure->ctype_secondary) == 'plain' &&
					is_array($structure->ctype_parameters) && isset($structure->ctype_parameters['format']) &&
					trim(strtolower($structure->ctype_parameters['format']))=='flowed'
				)
				{
					if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '." detected TEXT/PLAIN Format:flowed -> removing leading blank ('\r\n ') per line");
					$structure->body = str_replace("\r\n ","\r\n", $structure->body);
				}
				$mailObject->Body = $structure->body;
				$seemsToBePlainMessage = true;
			}
			$this->createBodyFromStructure($mailObject, $structure, $parenttype=null);
			$mailObject->SetMessageType();
			$mailObject->CreateHeader(); // this sets the boundary stufff
			//echo "Boundary:".$mailObject->FetchBoundary(1).'<br>';
			//$boundary ='';
			//if (isset($structure->ctype_parameters['boundary'])) $boundary = ' boundary="'.$mailObject->FetchBoundary(1).'";';
			if ($seemsToBePlainMessage && !empty($contenttypecalendar) && strtolower($mailObject->ContentType)=='text/plain')
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
		if (is_null($isHTML)) $isHTML = strtolower($structure->ctype_secondary)=='html'?true:false;
		if (is_null($attachmentnumber)) $attachmentnumber = 0;
		if ($structure->parts && strtolower($structure->ctype_primary)=='multipart')
		{
			if (is_null($alternatebodyneeded)) $alternatebodyneeded = false;
			foreach($structure->parts as $part)
			{
				//error_log(__METHOD__.' ('.__LINE__.') '.' Structure Content Type:'.$structure->ctype_primary.'/'.$structure->ctype_secondary.' Decoding:'.($decode?'on':'off'));
				//error_log(__METHOD__.' ('.__LINE__.') '.' '.$structure->ctype_primary.'/'.$structure->ctype_secondary.' => '.$part->ctype_primary.'/'.$part->ctype_secondary);
				//error_log(__METHOD__.' ('.__LINE__.') '.' Part:'.array2string($part));
				$partFetched = false;
				//echo __METHOD__.' ('.__LINE__.') '.$structure->ctype_primary.'/'.$structure->ctype_secondary.'<br>';
				if ($part->headers['content-transfer-encoding']) $mailObject->Encoding = $part->headers['content-transfer-encoding'];
				//$mailObject->IsHTML($part->ctype_secondary=='html'?true:false); // we do not set this here, as the default is text/plain
				if (isset($part->ctype_parameters['charset'])) $mailObject->CharSet = trim($part->ctype_parameters['charset']);
				if ((strtolower($structure->ctype_secondary)=='alternative'||
					 strtolower($structure->ctype_secondary)=='mixed' ||
					// strtolower($structure->ctype_secondary)=='related' || // may hold text/plain directly ?? I doubt it ??
					 strtolower($structure->ctype_secondary)=='signed') && strtolower($part->ctype_primary)=='text' && strtolower($part->ctype_secondary)=='plain' && $part->body)
				{
					//echo __METHOD__.' ('.__LINE__.') '.$part->ctype_primary.'/'.$part->ctype_secondary.'<br>';
					//error_log(__METHOD__.' ('.__LINE__.') '.$part->ctype_primary.'/'.$part->ctype_secondary.' already fetched Content is HTML='.$isHTML.' Body:'.$part->body);
					$bodyPart = $part->body;
					if ($decode) $bodyPart = $this->decodeMimePart($part->body,($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'WeDontKnowTheEncoding'));
/*
					if (strtolower($part->ctype_primary) == 'text' && strtolower($part->ctype_secondary) == 'plain' &&
						is_array($part->ctype_parameters) && isset($part->ctype_parameters['format']) &&
						trim(strtolower($part->ctype_parameters['format']))=='flowed'
					)
					{
						if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '." detected TEXT/PLAIN Format:flowed -> removing leading blank ('\r\n ') per line");
						$bodyPart = str_replace("\r\n ","\r\n", $bodyPart);
					}
*/
					$mailObject->Body = ($isHTML==false?$mailObject->Body:'').$bodyPart;
					$mailObject->AltBody .= $bodyPart;
					$partFetched = true;
				}
				if ((strtolower($structure->ctype_secondary)=='alternative'||
					 strtolower($structure->ctype_secondary)=='mixed' ||
					 strtolower($structure->ctype_secondary)=='related' || // may hold text/html directly
					 strtolower($structure->ctype_secondary)=='signed' ) &&
					strtolower($part->ctype_primary)=='text' && strtolower($part->ctype_secondary)=='html' && $part->body)
				{
					//echo __METHOD__.' ('.__LINE__.') '.$part->ctype_primary.'/'.$part->ctype_secondary.'<br>';
					//error_log(__METHOD__.' ('.__LINE__.') '.$part->ctype_primary.'/'.$part->ctype_secondary.' already fetched Content is HTML='.$isHTML.' Body:'.$part->body);
					$bodyPart = $part->body;
					if ($decode) $bodyPart = $this->decodeMimePart($part->body,($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'WeDontKnowTheEncoding'));
					$mailObject->IsHTML(true); // we need/want that here, because looping through all message parts may mess up the message body mimetype
					$mailObject->Body = ($isHTML?$mailObject->Body:'').$bodyPart;
					$alternatebodyneeded = true;
					$isHTML=true;
					$partFetched = true;
				}
				if ((strtolower($structure->ctype_secondary)=='alternative'||
					 strtolower($structure->ctype_secondary)=='mixed' ||
					 strtolower($structure->ctype_secondary)=='signed' ) &&
					strtolower($part->ctype_primary)=='text' && strtolower($part->ctype_secondary)=='calendar' && $part->body)
				{
					//error_log(__METHOD__.' ('.__LINE__.') '.$part->ctype_primary.'/'.$part->ctype_secondary.' BodyPart:'.array2string($part));
					$bodyPart = $part->body;
					if ($decode) $bodyPart = $this->decodeMimePart($part->body,($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'WeDontKnowTheEncoding'));
					$mailObject->AltExtended = $bodyPart;
					// "text/calendar; charset=utf-8; name=meeting.ics; method=REQUEST"
					// [ctype_parameters] => Array([charset] => utf-8[name] => meeting.ics[method] => REQUEST)
					$mailObject->AltExtendedContentType = $part->ctype_primary.'/'.$part->ctype_secondary.';'.
						($part->ctype_parameters['name']?' name='.$part->ctype_parameters['name'].';':'').
						($part->ctype_parameters['method']?' method='.$part->ctype_parameters['method'].'':'');
					$partFetched = true;
				}
				if ((strtolower($structure->ctype_secondary)=='mixed' ||
					 strtolower($structure->ctype_secondary)=='related' ||
					 strtolower($structure->ctype_secondary)=='alternative' ||
					 strtolower($structure->ctype_secondary)=='signed') && strtolower($part->ctype_primary)=='multipart')
				{
					//error_log( __METHOD__.' ('.__LINE__.') '." Recursion to fetch subparts:".$part->ctype_primary.'/'.$part->ctype_secondary);
					$this->createBodyFromStructure($mailObject, $part, $parenttype=null, $decode);
				}
				//error_log(__METHOD__.' ('.__LINE__.') '.$structure->ctype_primary.'/'.$structure->ctype_secondary.' => '.$part->ctype_primary.'/'.$part->ctype_secondary.' Part:'.array2string($part->body));
				if (($part->body||strtolower($part->ctype_primary.'/'.$part->ctype_secondary)=="message/rfc822") && ((strtolower($structure->ctype_secondary)=='mixed' && strtolower($part->ctype_primary)!='multipart') ||
					trim(strtolower($part->disposition)) == 'attachment' ||
					trim(strtolower($part->disposition)) == 'inline' ||
					isset($part->headers['content-id'])))
				{
					//error_log(__METHOD__.' ('.__LINE__.') '.$structure->ctype_secondary.'=>'.$part->ctype_primary.'/'.$part->ctype_secondary.'->'.array2string($part));
					$attachmentnumber++;
					$filename = trim(($part->ctype_parameters['name']?$part->ctype_parameters['name']:$part->d_parameters['filename']));
					if (strlen($filename)==0)
					{
						//error_log(__METHOD__.' ('.__LINE__.') '.$structure->ctype_secondary.'=>'.$part->ctype_primary.'/'.$part->ctype_secondary.'->'.array2string($part));
						foreach(array('content-type','content-disposition') as $k => $v)
						{
							foreach(array('filename','name') as $sk => $n)
							{
								if (stripos($part->headers[$v],$n)!== false)
								{
									$buff = explode($n,$part->headers[$v]);
									//error_log(__METHOD__.' ('.__LINE__.') '.array2string($buff));
									$namepart = array_pop($buff);
									//$disposition = array_pop($buff);
									//error_log(__METHOD__.' ('.__LINE__.') '.$namepart);
									$fp = strpos($namepart,'"');
									//error_log(__METHOD__.' ('.__LINE__.') '.' Start:'.$fp);
									if ($fp !== false)
									{
										$np = strpos($namepart,'"', $fp+1);
										//error_log(__METHOD__.' ('.__LINE__.') '.' End:'.$np);
										if ($np !== false)
										{
											$filename = trim(substr($namepart,$fp+1,$np-$fp-1));
											$filename = $mailObject->EncodeHeader($filename);
											if (!empty($filename))
											{
												if (strpos($part->disposition,';')!==false)
												{
													//chance is, disposition is broken too
													$dbuff = explode(';',$part->disposition);
													$part->disposition = trim($dbuff[0]);
												}
												break 2;
											}
										}
									}
								}
							}
						}
					}
					if (strlen($filename)==0) $filename = 'noname_'.$attachmentnumber;
					//error_log(__METHOD__.' ('.__LINE__.') '.' '.$filename);
					//echo $part->headers['content-transfer-encoding'].'#<br>';
					if ($decode) $part->body = $this->decodeMimePart($part->body,($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'WeDontKnowTheEncoding'));
					if ((trim(strtolower($part->disposition))=='attachment' || trim(strtolower($part->disposition)) == 'inline' || isset($part->headers['content-id'])) && $partFetched==false)
					{
						if (trim(strtolower($part->disposition)) == 'inline' || $part->headers['content-id'])
						{
							$part->headers['content-id'] = str_replace(array('<','>'),'',$part->headers['content-id']);
							$dirname = $this->accountid.'_'.$this->profileID.'_'.$this->sessionData['mailbox'].$part->headers['content-id'];
							if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' Dirname:'.$dirname);
							$dirname = md5($dirname);
							$dir = $GLOBALS['egw_info']['server']['temp_dir']."/fmail_import/$dirname";
							if (self::$debug) error_log(__METHOD__.' ('.__LINE__.') '.' Dir to save attachment to:'.$dir);
							if ( !file_exists( "$dir") )
							{
								@mkdir( $dir, 0700, true );
							}
							$rp = emailadmin_imapbase::getRandomString();
							file_put_contents( "$dir/$rp$filename", $part->body);

							$path = "$dir/$rp$filename";
							$mailObject->AddEmbeddedImage($path, $part->headers['content-id'], $filename, ($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'), $part->ctype_primary.'/'.$part->ctype_secondary);
						}
						else
						{
							//error_log(__METHOD__.' ('.__LINE__.') '.' Add String '.($part->disposition=='attachment'?'Attachment':'Part').' of type:'.$part->ctype_primary.'/'.$part->ctype_secondary.array2string($part->parts));
							if (strtolower($part->ctype_primary.'/'.$part->ctype_secondary)=="message/rfc822")
							{
								$localMailObject = new egw_mailer();
								$this->parseRawMessageIntoMailObject($localMailObject,$part->parts[0],$_Header,$_Body);
								//error_log(__METHOD__.__LINE__.$_Header.$localMailObject->LE.$localMailObject->LE.$_Body);
								$mailObject->AddStringAttachment($_Header.$localMailObject->LE.$localMailObject->LE.$_Body,
													 $filename,
													 ($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'),
													 $part->ctype_primary.'/'.$part->ctype_secondary
													);
							}
							else
							{
								$mailObject->AddStringAttachment($part->body, //($part->headers['content-transfer-encoding']?base64_decode($part->body):$part->body),
													 $filename,
													 ($part->headers['content-transfer-encoding']?$part->headers['content-transfer-encoding']:'base64'),
													 $part->ctype_primary.'/'.$part->ctype_secondary
													);
							}
						}
					}
					if (!(trim(strtolower($part->disposition))=='attachment' || trim(strtolower($part->disposition)) == 'inline' || isset($part->headers['content-id'])) && $partFetched==false)
					{
						//error_log(__METHOD__.' ('.__LINE__.') '.' Add String '.($part->disposition=='attachment'?'Attachment':'Part').' of type:'.$part->ctype_primary.'/'.$part->ctype_secondary.' Body:'.$part->body);
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

	function sendMDN($uid,$_folder)
	{
		try
		{
			$acc = emailadmin_account::read($this->profileID);
			//error_log(__METHOD__.__LINE__.array2string($acc));
			$identity = emailadmin_account::read_identity($acc['ident_id'],true);

			//$identity = emailadmin_account::read_identity($this->sessionData['mailaccount'],true);
		}
		catch (Exception $e)
		{
			$identity=array();
		}
		$headers = $this->getMessageHeader($uid,'',true,true,$_folder);
		$send = CreateObject('phpgwapi.send');
		$send->ClearAddresses();
		$send->ClearAttachments();
		$send->IsHTML(False);
		$send->IsSMTP();

		$array_to = explode(",",$headers['TO']);
		$send->From = $identity['ident_email'];
		$send->FromName = self::generateIdentityString($identity,false);

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
			$send->EncodeString(lang("Your message to  %1 was displayed." ,$send->From),"7bit").
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
		$body .= $send->EncodeString($this->getMessageRawHeader($uid,'',$_folder),'7bit')."\r\n";
		$body .= "--".$sep."--";


		$header = rtrim($send->CreateHeader())."\r\n"."Content-Type: multipart/report; report-type=disposition-notification;\r\n".
			"\tboundary=\"".$sep."\"\r\n\r\n";
		//error_log(__METHOD__.array2string($send));
		$rv = $send->SmtpSend($header,$body);
		//error_log(__METHOD__.'#'.array2string($rv).'#');
		return $rv;
	}

	/**
	 * Hook stuff
	 */

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
		error_log(__METHOD__.' ('.__LINE__.') '.' NOT DONE JET!');
		//$_profile_id=????
		//$icServer = emailadmin_account::read($_profile_id)->imapServer();
		//$ogServer = emailadmin_account::read($_profile_id)->smtpServer();

/*
		if(($icServer instanceof defaultimap)) {
			// if not connected, try opening an admin connection
			if (!$icServer->_connected) $this->openConnection($this->profileID,true);
			$icServer->addAccount($_hookValues);
			if ($icServer->_connected) $this->closeConnection(); // close connection afterwards
		}
		if(($ogServer instanceof emailadmin_smtp)) {
			$ogServer->addAccount($_hookValues);
		}
*/
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
		error_log(__METHOD__.' ('.__LINE__.') '.' NOT DONE JET!');
		//$_profile_id=????
		//$icServer = emailadmin_account::read($_profile_id)->imapServer();
		//$ogServer = emailadmin_account::read($_profile_id)->smtpServer();
/*
		if(($icServer instanceof defaultimap)) {
			//try to connect with admin rights, when not connected
			if (!$icServer->_connected) $this->openConnection($this->profileID,true);
			$icServer->deleteAccount($_hookValues);
			if ($icServer->_connected) $this->closeConnection(); // close connection
		}

		if(($ogServer instanceof emailadmin_smtp)) {
			$ogServer->deleteAccount($_hookValues);
		}
*/
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
		error_log(__METHOD__.' ('.__LINE__.') '.' NOT DONE JET!');
		//$_profile_id=????
		//$icServer = emailadmin_account::read($_profile_id)->imapServer();
		//$ogServer = emailadmin_account::read($_profile_id)->smtpServer();
/*
		if(($icServer instanceof defaultimap)) {
			$icServer->updateAccount($_hookValues);
		}

		if(($ogServer instanceof emailadmin_smtp)) {
			$ogServer->updateAccount($_hookValues);
		}
*/
	}
}
