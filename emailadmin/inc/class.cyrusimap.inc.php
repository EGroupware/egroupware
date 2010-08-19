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
	
include_once(EGW_SERVER_ROOT."/emailadmin/inc/class.defaultimap.inc.php");

/**
 * Manages connection to Cyrus IMAP server
 * 
 * Also proxies Sieve calls to emailadmin_sieve (eg. it behaves like the former felamimail bosieve),
 * to allow IMAP plugins to also manage Sieve connection.
 */
class cyrusimap extends defaultimap
{
	// mailbox delimiter
	var $mailboxDelimiter = '.';

	// mailbox prefix
	var $mailboxPrefix = '';

	var $enableCyrusAdmin = false;
	
	var $cyrusAdminUsername;
	
	var $cyrusAdminPassword;
	
	var $enableSieve = false;
	
	var $sieveHost;
	
	var $sievePort;
	
	function addAccount($_hookValues) 
	{
		return $this->updateAccount($_hookValues);
	}
	
	function deleteAccount($_hookValues)
	{
		if(!$this->enableCyrusAdmin) {
			return false;
		}

		if($this->_connected === true) {
			$this->disconnect();
		}
		
		// we need a admin connection
		if(!$this->openConnection(true)) {
			return false;
		}

		$username = $_hookValues['account_lid'];
	
		$mailboxName = $this->getUserMailboxString($username);

		// give the admin account the rights to delete this mailbox
		if(PEAR::isError($this->setACL($mailboxName, $this->adminUsername, 'lrswipcda'))) {
			$this->disconnect();
			return false;
		}

		if(PEAR::isError($this->deleteMailbox($mailboxName))) {
			$this->disconnect();
			return false;
		}
		
		$this->disconnect();

		return true;
	}

	/**
	 * Create mailbox string from given mailbox-name and user-name
	 * @param string $_username
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
		$mailboxString = $nameSpaces['others'][0]['name'] . strtolower($_username) . (!empty($_folderName) ? $nameSpaces['others'][0]['delimiter'] . $_folderName : '');
		
		if($this->loginType == 'vmailmgr' || $this->loginType == 'email') {
			$mailboxString .= '@'.$this->domainName;
		}

		return $mailboxString;
	}

	function setUserData($_username, $_quota) 
	{
		if(!$this->enableCyrusAdmin) {
			return false;
		}

		if($this->_connected === true) {
			$this->disconnect();
		}
	
		// create a admin connection
		if(!$this->openConnection(true)) {
			return false;
		}

		$mailboxName = $this->getUserMailboxString($_username);

		if((int)$_quota > 0) {
			// enable quota
			$quota_value = $this->setStorageQuota($mailboxName, (int)$_quota*1024);
		} else {
			// disable quota
			$quota_value = $this->setStorageQuota($mailboxName, -1);
		}

		$this->disconnect();

		return true;
		
	}

	function updateAccount($_hookValues) 
	{
		if(!$this->enableCyrusAdmin) { 
			return false;
		}
		#_debug_array($_hookValues);
		$username 	= $_hookValues['account_lid'];
		if(isset($_hookValues['new_passwd'])) {
			$userPassword	= $_hookValues['new_passwd'];
		}

		if($this->_connected === true) {
			$this->disconnect();
		}
		
		// we need a admin connection
		if(!$this->openConnection(true)) {
			return false;
		}

		// create the mailbox, with the account_lid, as it is passed from the hook values (gets transformed there if needed)
		$mailboxName = $this->getUserMailboxString($username, $mailboxName);
		// make sure we use the correct username here.
		$username = $this->getMailBoxUserName($username);
		$folderInfo = $this->getMailboxes('', $mailboxName, true);
		if(empty($folderInfo)) {
			if(!PEAR::isError($this->createMailbox($mailboxName))) {
				if(PEAR::isError($this->setACL($mailboxName, $username, "lrswipcda"))) {
					# log error message
				}
			}
		}
		$this->disconnect();
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
			case 'setVacationUser':
				if (is_null($this->sieve))
				{
					$this->sieve = new emailadmin_sieve($this);
					$this->scriptName =& $this->sieve->scriptName;
					$this->error =& $this->sieve->error;
				}
				$ret = call_user_func_array(array($this->sieve,$name),$params);
				error_log(__CLASS__.'->'.$name.'('.array2string($params).') returns '.array2string($ret));
				return $ret;
		}
		throw new egw_exception_wrong_parameter("No method '$name' implemented!");
	}
}
