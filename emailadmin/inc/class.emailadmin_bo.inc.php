<?php
/**
 * EGroupware EMailAdmin: Business logic
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Lars Kneschke
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Business logic
 */
class emailadmin_bo
{
	/**
	 * Name of app the table is registered
	 */
	const APP = 'emailadmin';

	static $sessionData = array();
	#var $userSessionData;
	var $LDAPData;

	//var $SMTPServerType = array();		// holds a list of config options
	static $SMTPServerType = array(
		'emailadmin_smtp' 	=> array(
			'description'	=> 'standard SMTP-Server',
			'classname'	=> 'emailadmin_smtp'
		),
	);
	//var $IMAPServerType = array();		// holds a list of config options
	static $IMAPServerType = array(
		'defaultimap' 	=> array(
			'description'	=> 'standard IMAP server',
			'protocol'	=> 'imap',
			'classname'	=> 'defaultimap'
		)
	);

	var $imapClass;				// holds the imap/pop3 class
	var $smtpClass;				// holds the smtp class
	var $tracking;				// holds the tracking object

	/**
	 * @var emailadmin_so
	 */
	var $soemailadmin;

	function __construct($_profileID=false,$_restoreSesssion=true)
	{
		//error_log(__METHOD__.function_backtrace());
		if (!is_object($GLOBALS['emailadmin_bo']))
		{
			$GLOBALS['emailadmin_bo'] = $this;
		}
		//init with all servertypes and translate the standard entry description
		self::$SMTPServerType = self::getSMTPServerTypes();
		self::$IMAPServerType = self::getIMAPServerTypes();
		self::$SMTPServerType['emailadmin_smtp']['description'] = lang('standard SMTP-Server');
		self::$IMAPServerType['defaultimap']['description'] = lang('standard IMAP Server');
		if ($_restoreSesssion) // &&  !(is_array(self::$sessionData) && (count(self::$sessionData)>0))  )
		{
			$this->restoreSessionData();
		}
		if ($_restoreSesssion===false) // && (is_array(self::$sessionData) && (count(self::$sessionData)>0))  )
		{
			// make sure session data will be created new
			self::$sessionData = array();
			self::saveSessionData();
		}
		#_debug_array(self::$sessionData);
	}

	function getAccountEmailAddress($_accountName, $_profileID)
	{
		$profileData	= $this->getProfile($_profileID);

		#$smtpClass	= self::$SMTPServerType[$profileData['smtpType']]['classname'];
		if ($profileData['smtpType']=='defaultsmtp') $profileData['smtpType']='emailadmin_smtp';
		$smtpClass	= CreateObject('emailadmin.'.self::$SMTPServerType[$profileData['smtpType']]['classname']);

		#return empty($smtpClass) ? False : ExecMethod("emailadmin.$smtpClass.getAccountEmailAddress",$_accountName,3,$profileData);
		return is_object($smtpClass) ?  $smtpClass->getAccountEmailAddress($_accountName) : False;
	}

	function getMailboxString($_folderName)
	{
		if (is_object($this->imapClass))
		{
			return ExecMethod("emailadmin.".$this->imapClass.".getMailboxString",$_folderName,3,$this->profileData);
			return $this->imapClass->getMailboxString($_folderName);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get a list of supported SMTP servers
	 *
	 * Calls hook "smtp_server_types" to allow applications to supply own server-types
	 *
	 * @return array classname => label pairs
	 * @deprecated use emailadmin_base::getSMTPServerTypes()
	 */
	static public function getSMTPServerTypes($extended=true)
	{
		return emailadmin_base::getSMTPServerTypes($extended);
	}

	/**
	 * Get a list of supported IMAP servers
	 *
	 * Calls hook "imap_server_types" to allow applications to supply own server-types
	 *
	 * @param boolean $extended=true
	 * @return array classname => label pairs
	 * @deprecated use emailadmin_base::getIMAPServerTypes()
	 */
	static public function getIMAPServerTypes($extended=true)
	{
		return emailadmin_base::getIMAPServerTypes($extended);
	}

	/**
	 * Query user data from incomming (IMAP) and outgoing (SMTP) mail-server
	 *
	 * @param int $_accountID
	 * @return array
	 */
	function getUserData($_accountID)
	{
		return false;
	}

	function restoreSessionData()
	{
		$GLOBALS['egw_info']['flags']['autoload'] = array(__CLASS__,'autoload');

		//echo function_backtrace()."<br>";
		//unserializing the sessiondata, since they are serialized for objects sake
		self::$sessionData = (array) unserialize($GLOBALS['egw']->session->appsession('session_data','emailadmin'));
	}

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
			}
			elseif (strpos($class,'activesync')===0)
			{
				//temporary solution/hack to fix the false loading of activesync stuff, even as we may not need it for ui
				//but trying to load it blocks the mail app
				//error_log(__METHOD__.__LINE__.' '.$class);
				include_once(EGW_INCLUDE_ROOT.'/activesync/backend/egw.php');
			}

		}
	}

	function saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy)
	{
		if (is_object($this->smtpClass))
		{
			#$smtpClass = CreateObject('emailadmin.'.$this->smtpClass,$this->profileID);
			#$smtpClass->saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy);
			$this->smtpClass->saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy);
		}

	}

	/**
	 * called by the validation hook in setup
	 *
	 * @param array $settings following keys: mail_server, mail_server_type {IMAP|IMAPS|POP-3|POP-3S},
	 *	mail_login_type {standard|vmailmgr}, mail_suffix (domain), smtp_server, smtp_port, smtp_auth_user, smtp_auth_passwd
	 */
	function setDefaultProfile($settings)
	{
		return false;
	}

	function saveSessionData()
	{
		// serializing the session data, for the sake of objects
		if (is_object($GLOBALS['egw']->session))	// otherwise setup(-cli) fails
		{
			$GLOBALS['egw']->session->appsession('session_data','emailadmin',serialize(self::$sessionData));
		}
		#$GLOBALS['egw']->session->appsession('user_session_data','',$this->userSessionData);
	}

	function updateAccount($_hookValues) {
		if (is_object($this->imapClass)) {
			#ExecMethod("emailadmin.".$this->imapClass.".updateAccount",$_hookValues,3,$this->profileData);
			$this->imapClass->updateAccount($_hookValues);
		}

		if (is_object($this->smtpClass)) {
			#ExecMethod("emailadmin.".$this->smtpClass.".updateAccount",$_hookValues,3,$this->profileData);
			$this->smtpClass->updateAccount($_hookValues);
		}
		self::$sessionData = array();
		$this->saveSessionData();
	}

	/**
	 * Get ID of default new account profile
	 *
	 * @return int
	 * @deprecated use emailadmin_account::get_default_acc_id()
	 */
	static function getDefaultAccID()
	{
		return emailadmin_account::get_default_acc_id();
	}

	/**
	 * Get ID of User specific default new account profile
	 *
	 * @return int
	 * @deprecated use emailadmin_account::get_default_acc_id()
	 */
	static function getUserDefaultAccID()
	{
		return emailadmin_account::get_default_acc_id();
	}
}
