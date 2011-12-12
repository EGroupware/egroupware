<?php
/**
 * EGroupware EMailAdmin: Support for Cyrus IMAP (or other IMAP Server supporting Sieve)
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Lars Kneschke
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once 'Net/IMAP.php';

define('IMAP_NAMESPACE_PERSONAL', 'personal');
define('IMAP_NAMESPACE_OTHERS'	, 'others');
define('IMAP_NAMESPACE_SHARED'	, 'shared');
define('IMAP_NAMESPACE_ALL'	, 'all');

/**
 * This class holds all information about the imap connection.
 * This is the base class for all other imap classes.
 *
 * Also proxies Sieve calls to emailadmin_sieve (eg. it behaves like the former felamimail bosieve),
 * to allow IMAP plugins to also manage Sieve connection.
 */
class defaultimap extends Net_IMAP
{
	/**
	 * Capabilities of this class (pipe-separated): default, sieve, admin, logintypeemail
	 */
	const CAPABILITIES = 'default|sieve';
	
	/**
	 * ImapServerId
	 * 
	 * @var int
	 */
	var $ImapServerId;

	/**
	 * the password to be used for admin connections
	 *
	 * @var string
	 */
	var $adminPassword;
	
	/**
	 * the username to be used for admin connections
	 *
	 * @var string
	 */
	var $adminUsername;
	
	/**
	 * enable encryption
	 *
	 * @var bool
	 */
	var $encryption;
	
	/**
	 * the hostname/ip address of the imap server
	 *
	 * @var string
	 */
	var $host;
	
	/**
	 * the password for the user
	 *
	 * @var string
	 */
	var $password;
	
	/**
	 * the port of the imap server
	 *
	 * @var integer
	 */
	var $port = 143;

	/**
	 * the username
	 *
	 * @var string
	 */
	var $username;

	/**
	 * the domainname to be used for vmailmgr logins
	 *
	 * @var string
	 */
	var $domainName = false;

	/**
	 * validate ssl certificate
	 *
	 * @var bool
	 */
	var $validatecert;
	
	/**
	 * the mailbox delimiter
	 *
	 * @var string
	 */
	var $mailboxDelimiter = '/';

	/**
	 * the mailbox prefix. maybe used by uw-imap only?
	 *
	 * @var string
	 */
	var $mailboxPrefix = '~/mail';

	/**
	 * is the mbstring extension available
	 *
	 * @var unknown_type
	 */
	var $mbAvailable;
	
	/**
	 * Mailboxes which get automatic created for new accounts (INBOX == '')
	 *
	 * @var array
	 */
	var $imapLoginType;
	var $defaultDomain;
	
	
	/**
	 * disable internal conversion from/to ut7
	 * get's used by Net_IMAP
	 *
	 * @var array
	 */
	var $_useUTF_7 = false;

	/**
	 * a debug switch
	 */
	var $debug = false;
	
	/**
	 * Sieve available
	 * 
	 * @var boolean
	 */
	var $enableSieve = false;
	
	/**
	 * Hostname / IP of sieve host
	 * 
	 * @var string
	 */
	var $sieveHost;
	
	/**
	 * Port of Sieve service
	 * 
	 * @var int
	 */
	var $sievePort = 2000;

	/**
	 * the construtor
	 *
	 * @return void
	 */
	function __construct() 
	{
		if (function_exists('mb_convert_encoding')) {
			$this->mbAvailable = TRUE;
		}

		$this->restoreSessionData();
		
		// construtor for Net_IMAP stuff
		$this->Net_IMAPProtocol();
	}

	/**
	 * Magic method to re-connect with the imapserver, if the object get's restored from the session
	 */
	function __wakeup()
	{
		#$this->openConnection($this->isAdminConnection);   // we need to re-connect
	}
	
	/**
	 * adds a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function addAccount($_hookValues)
	{
		return true;
	}

	/**
	 * updates a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function updateAccount($_hookValues)
	{
		return true;
	}

	/**
	 * deletes a account on the imap server
	 *
	 * @param array $_hookValues
	 * @return bool true on success, false on failure
	 */
	function deleteAccount($_hookValues)
	{
		return true;
	}
	
	function disconnect()
	{
		//error_log(__METHOD__.function_backtrace());
		$retval = parent::disconnect();
		if( PEAR::isError($retval)) error_log(__METHOD__.$retval->message);
		$this->_connected = false;
	}
	
	/**
	 * converts a foldername from current system charset to UTF7
	 *
	 * @param string $_folderName
	 * @return string the encoded foldername
	 */
	function encodeFolderName($_folderName)
	{
		if($this->mbAvailable) {
			return mb_convert_encoding($_folderName, "UTF7-IMAP", $GLOBALS['egw']->translation->charset());
		}

		// if not
		// we can encode only from ISO 8859-1
		return imap_utf7_encode($_folderName);
	}
	
	/**
	 * returns the supported capabilities of the imap server
	 * return false if the imap server does not support capabilities
	 * 
	 * @return array the supported capabilites
	 */
	function getCapabilities() 
	{
		if(!is_array($this->sessionData['capabilities'][$this->host])) {
			return false;
		}
		
		return $this->sessionData['capabilities'][$this->host];
	}
	
	/**
	 * return the delimiter used by the current imap server
	 *
	 * @return string the delimimiter
	 */
	function getDelimiter() 
	{
		return isset($this->sessionData['delimiter'][$this->host]) ? $this->sessionData['delimiter'][$this->host] : $this->mailboxDelimiter;
	}
	
	/**
	 * Create transport string
	 *
	 * @return string the transportstring
	 */
	function _getTransportString() 
	{
		if($this->encryption == 2) {
			$connectionString = "tls://". $this->host;
		} elseif($this->encryption == 3) {
			$connectionString = "ssl://". $this->host;
		} else {
			// no tls
			$connectionString = $this->host;
		}
	
		return $connectionString;
	}

	/**
	 * Create the options array for SSL/TLS connections
	 *
	 * @return string the transportstring
	 */
	function _getTransportOptions() 
	{
		if($this->validatecert === false) {
			if($this->encryption == 2) {
				 return array(
					'tls' => array(
						'verify_peer' => false,
						'allow_self_signed' => true,
					)
				);
			} elseif($this->encryption == 3) {
				return array(
					'ssl' => array(
						'verify_peer' => false,
						'allow_self_signed' => true,
					)
				);
			}
		} else {
			if($this->encryption == 2) {
				return array(
					'tls' => array(
						'verify_peer' => true,
						'allow_self_signed' => false,
					)
				);
			} elseif($this->encryption == 3) {
				return array(
					'ssl' => array(
						'verify_peer' => true,
						'allow_self_signed' => false,
					)
				);
			}
		}
	
		return null;
	}

	/**
	 * get the effective Username for the Mailbox, as it is depending on the loginType
	 * @param string $_username
	 * @return string the effective username to be used to access the Mailbox
	 */
	function getMailBoxUserName($_username)
	{
		switch ($this->loginType)
		{
			case 'email':
				$_username = $_username;
				$accountID = $GLOBALS['egw']->accounts->name2id($_username);
				$accountemail = $GLOBALS['egw']->accounts->id2name($accountID,'account_email');
				//$accountemail = $GLOBALS['egw']->accounts->read($GLOBALS['egw']->accounts->name2id($_username,'account_email'));
				if (!empty($accountemail))
				{
					list($lusername,$domain) = explode('@',$accountemail,2);
					if (strtolower($domain) == strtolower($this->domainName) && !empty($lusername))
					{
						$_username = $lusername;
					}
				}
				break;
				
			case 'uidNumber':
				$_username = 'u'.$GLOBALS['egw']->accounts->name2id($_username);
				break;
		}
		return strtolower($_username);
	}

	/**
	 * Create mailbox string from given mailbox-name and user-name
	 *
	 * @param string $_folderName='' 
	 * @return string utf-7 encoded (done in getMailboxName)
	 */
	function getUserMailboxString($_username, $_folderName='') 
	{
		$nameSpaces = $this->getNameSpaces();

		if(!isset($nameSpaces['others'])) {
			return false;
		}
		
		$_username = $this->getMailBoxUserName($_username);
		if($this->loginType == 'vmailmgr' || $this->loginType == 'email' || $this->loginType == 'uidNumber') {
			$_username .= '@'. $this->domainName;
		}

		$mailboxString = $nameSpaces['others'][0]['name'] . $_username . (!empty($_folderName) ? $nameSpaces['others'][0]['delimiter'] . $_folderName : '');
		
		return $mailboxString;
	}
	/**
	 * get list of namespaces
	 *
	 * @return array array containing information about namespace
	 */
	function getNameSpaces() 
	{
		if(!$this->_connected) {
			return false;
		}
		static $nameSpace;
		if (is_null($nameSpace)) $nameSpace =& egw_cache::getSession('email','defaultimap_nameSpace');
		if (isset($nameSpace[$this->ImapServerId])) return $nameSpace[$this->ImapServerId];

		$retrieveDefault = false;
		if($this->hasCapability('NAMESPACE')) {
			$lNameSpace = $this->getNamespace();
			if( PEAR::isError($lNameSpace)) {
				//error_log("emailadmin::defaultimap->getNameSpaces: called from->".function_backtrace());
				if ($this->debug) error_log("emailadmin::defaultimap->getNameSpaces:".print_r($lNameSpace,true));
				$retrieveDefault = true;
			} else {
				$result = array();

				$result['personal']	= $lNameSpace['personal'];

				if(is_array($lNameSpace['others'])) {
					$result['others']	= $lNameSpace['others'];
				}
		
				if(is_array($lNameSpace['shared'])) {
					$result['shared']	= $lNameSpace['shared'];
				}
			}
		} 
		if (!$this->hasCapability('NAMESPACE') || $retrieveDefault) {
			$delimiter = $this->getHierarchyDelimiter();
			if( PEAR::isError($delimiter)) $delimiter = '/';

			$result['personal']     = array(
				0 => array(
					'name'		=> '',
					'delimiter'	=> $delimiter
				)
			);
		}
		$nameSpace[$this->ImapServerId] = $result;
		return $result;
	}
	
	/**
	 * returns the quota for given foldername
	 * gets quota for the current user only
	 *
	 * @param string $_folderName
	 * @return string the current quota for this folder
	 */
#	function getQuota($_folderName) 
#	{
#		if(!is_resource($this->mbox)) {
#			$this->openConnection();
#		}
#		
#		if(function_exists('imap_get_quotaroot') && $this->supportsCapability('QUOTA')) {
#			$quota = @imap_get_quotaroot($this->mbox, $this->encodeFolderName($_folderName));
#			if(is_array($quota) && isset($quota['STORAGE'])) {
#				return $quota['STORAGE'];
#			}
#		} 
#
#		return false;
#	}
	
	/**
	 * return the quota for another user
	 * used by admin connections only
	 *
	 * @param string $_username
	 * @return string the quota for specified user
	 */
	function getQuotaByUser($_username) 
	{
		$mailboxName = $this->getUserMailboxString($_username);
		//error_log(__METHOD__.$mailboxName);
		$storageQuota = $this->getStorageQuota($mailboxName); 
		//error_log(__METHOD__.$_username);
		//error_log(__METHOD__.$mailboxName);
		if ( PEAR::isError($storageQuota)) error_log(__METHOD__.$storageQuota->message);
		if(is_array($storageQuota) && isset($storageQuota['QMAX'])) {
			return (int)$storageQuota['QMAX'];
		}

		return false;
	}
	
	/**
	 * returns information about a user
	 * 
	 * Only a stub, as admin connection requires, which is only supported for Cyrus
	 *
	 * @param string $_username
	 * @return array userdata
	 */
	function getUserData($_username) 
	{
		return array();
	}
	
	/**
	 * opens a connection to a imap server
	 *
	 * @param bool $_adminConnection create admin connection if true
	 *
	 * @return resource the imap connection
	 */
	function openConnection($_adminConnection=false, $_timeout=20)
	{
		static $supportedAuthMethods;
		//error_log(__METHOD__.function_backtrace());
		//error_log(__METHOD__.__LINE__.($_adminConnection?' Adminconnection':' ').array2string($this));
		unset($this->_connectionErrorObject);
		
		if($_adminConnection) {
			$username	= $this->adminUsername;
			$password	= $this->adminPassword;
			$options	= '';
			$this->isAdminConnection = true;
		} else {
			$username	= $this->loginName;
			$password	= $this->password;
			$options	= $_options;
			$this->isAdminConnection = false;
		}
		
		$this->setStreamContextOptions($this->_getTransportOptions());
		$this->_timeout = $_timeout;
		if( PEAR::isError($status = parent::connect($this->_getTransportString(), $this->port, $this->encryption == 1)) ) {
			if ($this->debug) error_log(__METHOD__.__LINE__."Could not connect with ".$this->_getTransportString()." on Port ".$this->port." Encryption==1?".$this->encryption);
			if ($this->debug && !empty($status->message)) error_log(__METHOD__.__LINE__."Status connect:#".$status->message.'#');
			$this->_connectionErrorObject = (!empty($status->message) ? $status : new PEAR_Error("Could not connect with ".$this->_getTransportString()." on Port ".$this->port." Encryption==1?".$this->encryption));
			return (!empty($status->message) ? $status : false);
		}
		//$this->setTimeout($_timeout); // socket::connect sets the timeout on connection
		if(empty($username))
		{
			if ($this->debug) error_log(__METHOD__."No username supplied.".function_backtrace());
			if ($this->_connected) $this->disconnect(); // disconnect (if connected)
			$this->_connectionErrorObject = new PEAR_Error("No username supplied.");
			return false;
		}
		// we cache the supported AuthMethods during session, to be able to speed up login.
		if (is_null($supportedAuthMethods)) $supportedAuthMethods =& egw_cache::getSession('email','defaultimap_supportedAuthMethods');
		if (isset($supportedAuthMethods[$this->ImapServerId])) $this->supportedAuthMethods = $supportedAuthMethods[$this->ImapServerId];

		//error_log(__METHOD__.__LINE__.' ImapServerID:'.$this->ImapServerId.' '.array2string($this->supportedAuthMethods));
		if( PEAR::isError($status = parent::login($username, $password, (in_array('LOGIN', $this->supportedAuthMethods)?'LOGIN':true), !$this->isAdminConnection)) ) {
			if ($this->debug) error_log(__METHOD__."Could not log in with ->".$username.":".$password."<-");
			if ($this->debug && $status->message) error_log(__METHOD__."Status login:".array2string($status->message));
			//error_log(__METHOD__.'Called from:'.function_backtrace());
			$this->disconnect();
			$this->_connectionErrorObject = ($status->message ? $status : new PEAR_Error("Could not log in with the supplied credentials"));
			return ($status->message ? $status : false);
		}
		$supportedAuthMethods[$this->ImapServerId] = $this->supportedAuthMethods;
		//error_log(__METHOD__.__LINE__.' ImapServerID:'.$this->ImapServerId.' supported:'.array2string($this->supportedAuthMethods));
		//error_log(__METHOD__.__LINE__.' ImapServerID:'.$this->ImapServerId.' ServerMethods:'.array2string($this->_serverAuthMethods));
		return true;
	}		

	/**
	 * restore session variable
	 *
	 */
	function restoreSessionData() 
	{
		$this->sessionData = $GLOBALS['egw']->session->appsession('imap_session_data');
	}
	
	/**
	 * save session variable
	 *
	 */
	function saveSessionData() 
	{
		$GLOBALS['egw']->session->appsession('imap_session_data','',$this->sessionData);
	}
	
	/**
	 * set userdata
	 *
	 * @param string $_username username of the user
	 * @param int $_quota quota in bytes
	 * @return bool true on success, false on failure
	 */
	function setUserData($_username, $_quota) 
	{
		return true;
	}

	/**
	 * check if imap server supports given capability
	 *
	 * @param string $_capability the capability to check for
	 * @return bool true if capability is supported, false if not
	 */
	function supportsCapability($_capability) 
	{
		return $this->hasCapability($_capability);
	}
	
	/**
	 * Instance of emailadmin_sieve
	 * 
	 * @var emailadmin_sieve
	 */
	private $sieve;
	
	public $scriptName;
	public $error;
	
	//public $error;

	/**
	 * Proxy former felamimail bosieve methods to internal emailadmin_sieve instance
	 * 
	 * @param string $name
	 * @param array $params
	 */
	public function __call($name,array $params=null)
	{
		if ($this->debug) error_log(__METHOD__.'->'.$name.' with params:'.array2string($params));
		switch($name)
		{
			case 'installScript':
			case 'getScript':
			case 'setActive':
			case 'setEmailNotification':
			case 'getEmailNotification':
			case 'setRules':
			case 'getRules':
			case 'retrieveRules':
			case 'getVacation':
			case 'setVacation':
				if (is_null($this->sieve))
				{
					$this->sieve = new emailadmin_sieve($this);
					$this->scriptName =& $this->sieve->scriptName;
					$this->error =& $this->sieve->error;
				}
				$ret = call_user_func_array(array($this->sieve,$name),$params);
				//error_log(__CLASS__.'->'.$name.'('.array2string($params).') returns '.array2string($ret));
				return $ret;
		}
		throw new egw_exception_wrong_parameter("No method '$name' implemented!");
	}

	public function setVacationUser($_euser, $_scriptName, $_vacation)
	{
		if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.' User:'.array2string($_euser).' Scriptname:'.array2string($_scriptName).' VacationMessage:'.array2string($_vacation));
		if (is_null($this->sieve))
		{
			$this->sieve = new emailadmin_sieve();
			$this->scriptName =& $this->sieve->scriptName;
			$this->error =& $this->sieve->error;
			$this->sieve->icServer = $this;
		}
		return $this->sieve->setVacationUser($_euser, $_scriptName, $_vacation);
	}

	/**
	 * set the asyncjob for a timed vacation
	 *
	 * @param array $_vacation the vacation to set/unset
	 * @return  void
	 */
	function setAsyncJob ($_vacation, $_scriptName=null)
	{
		// setting up an async job to enable/disable the vacation message
		$async = new asyncservice();
		$user = (isset($_vacation['account_id'])&&!empty($_vacation['account_id'])?$_vacation['account_id']:$GLOBALS['egw_info']['user']['account_id']);
		$async_id = (isset($_vacation['id'])&&!empty($_vacation['id'])?$_vacation['id']:"felamimail-vacation-$user");
		$async->delete($async_id); // ="felamimail-vacation-$user");
		$_scriptName = (!empty($_scriptName)?$_scriptName:(isset($_vacation['scriptName'])&&!empty($_vacation['scriptName'])?$_vacation['scriptName']:'felamimail'));
		$end_date = $_vacation['end_date'] + 24*3600;   // end-date is inclusive, so we have to add 24h
		if ($_vacation['status'] == 'by_date' && time() < $end_date)
		{
			$time = time() < $_vacation['start_date'] ? $_vacation['start_date'] : $end_date;
			$async->set_timer($time,$async_id,'felamimail.bosieve.async_vacation',$_vacation+array('scriptName'=>$_scriptName),$user);
		}
 	}
}
