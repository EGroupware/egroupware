<?php
/**
 * EGroupware EMailAdmin: Support for Cyrus IMAP
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
 */
class cyrusimap extends defaultimap
{
	/**
	 * Capabilities of this class (pipe-separated): default, sieve, admin, logintypeemail
	 */
	const CAPABILITIES = 'default|sieve|admin|logintypeemail';
	
	// mailbox delimiter
	var $mailboxDelimiter = '.';

	// mailbox prefix
	var $mailboxPrefix = '';

	var $enableCyrusAdmin = false;
	
	var $cyrusAdminUsername;
	
	var $cyrusAdminPassword;
	
	/**
	 * Updates an account
	 * 
	 * @param array $_hookValues only value for key 'account_lid' and 'new_passwd' is used
	 */
	function addAccount($_hookValues) 
	{
		return $this->updateAccount($_hookValues);
	}
	
	/**
	 * Delete an account
	 * 
	 * @param array $_hookValues only value for key 'account_lid' is used
	 */
	function deleteAccount($_hookValues)
	{
		// some precausion to really delete just _one_ account
		if (strpos($_hookValues['account_lid'],'%') !== false ||
			strpos($_hookValues['account_lid'],'*') !== false)
		{
			return false;
		}
		return !!$this->deleteUsers($_hookValues['account_lid']);
	}

	/**
	 * Delete multiple (user-)mailboxes via a wildcard, eg. '%' for whole domain
	 * 
	 * Domain is the configured domain and it uses the Cyrus admin user
	 * 
	 * @return string $username='%' username containing wildcards, default '%' for all users of a domain
	 * @return int|boolean number of deleted mailboxes on success or false on error
	 */
	function deleteUsers($username='%')
	{
		if(!$this->enableCyrusAdmin || empty($username)) {
			return false;
		}

		// we need a admin connection
		if($this->_connected === true && !$this->isAdminConnection) {
			$this->disconnect();
			if(!$this->openConnection(true)) {
				return false;
			}
		}
		$mailboxName = $this->getUserMailboxString($username);
		list($reference,$restriction) = explode($username,$mailboxName,2);
		$mboxes = $this->getMailboxes($reference,$username.$restriction);
		//error_log(__METHOD__."('$username') getMailboxes('$reference','$username$restriction') = ".array2string($mboxes));
		
		foreach($mboxes as $mbox) {
			// give the admin account the rights to delete this mailbox
			if(PEAR::isError($this->setACL($mbox, $this->adminUsername, 'lrswipcda'))) {
				$this->disconnect();
				return false;
			}
	
			if(PEAR::isError($this->deleteMailbox($mbox))) {
				$this->disconnect();
				return false;
			}
		}
		$this->disconnect();

		return count($mboxes);
	}

	/**
	 * returns information about a user
	 * currently only supported information is the current quota
	 *
	 * @param string $_username
	 * @return array userdata
	 */
	function getUserData($_username) 
	{
		if($this->_connected === true) {
			//error_log(__METHOD__."try to disconnect");
			$this->disconnect();
		}

		$this->openConnection(true);
		$userData = array();

		if($quota = $this->getQuotaByUser($_username)) {
			$userData['quotaLimit'] = $quota / 1024;
		}
		
		$this->disconnect();
		
		return $userData;
	}
	
	/**
	 * Set information about a user
	 * currently only supported information is the current quota
	 * 
	 * @param string $_username
	 * @param int $_quota
	 */
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

	/**
	 * Updates an account
	 * 
	 * @param array $_hookValues only value for key 'account_lid' and 'new_passwd' is used
	 */
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
}
