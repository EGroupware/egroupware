<?php
	/***************************************************************************\
	* EGroupWare - EMailAdmin                                                   *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	require_once 'Net/IMAP.php';

	define('IMAP_NAMESPACE_PERSONAL', 'personal');
	define('IMAP_NAMESPACE_OTHERS'	, 'others');
	define('IMAP_NAMESPACE_SHARED'	, 'shared');
	define('IMAP_NAMESPACE_ALL'	, 'all');

	/**
	 * This class holds all information about the imap connection.
	 * This is the base class for all other imap classes.
	 *
	 */
	class defaultimap extends Net_IMAP
	{
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
		 * the construtor
		 *
		 * @return void
		 */
		function defaultimap() 
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
			if ($this->loginType == 'email')
			{
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
			if($this->loginType == 'vmailmgr' || $this->loginType == 'email') {
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
			$retrieveDefault = false;
			if($this->hasCapability('NAMESPACE')) {
				$nameSpace = $this->getNamespace();
				if( PEAR::isError($nameSpace)) {
					if ($this->debug) error_log("emailadmin::defaultimap->getNameSpaces:".print_r($nameSpace,true));
					$retrieveDefault = true;
				} else {
					$result = array();

					$result['personal']	= $nameSpace['personal'];

					if(is_array($nameSpace['others'])) {
						$result['others']	= $nameSpace['others'];
					}
			
					if(is_array($nameSpace['shared'])) {
						$result['shared']	= $nameSpace['shared'];
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
		 * opens a connection to a imap server
		 *
		 * @param bool $_adminConnection create admin connection if true
		 *
		 * @return resource the imap connection
		 */
		function openConnection($_adminConnection=false) 
		{
			//error_log(__METHOD__.function_backtrace());
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
			$this->setTimeout(20);
			if( PEAR::isError($status = parent::connect($this->_getTransportString(), $this->port, $this->encryption == 1)) ) {
				error_log(__METHOD__."Could not connect");
				error_log(__METHOD__.$status->message);
				$this->_connectionErrorObject = $status;
				return false;
			}
			if( PEAR::isError($status = parent::login($username, $password, 'LOGIN', !$this->isAdminConnection)) ) {
				error_log(__METHOD__."Could not log in");
				error_log(__METHOD__.$status->message);
				$this->_connectionErrorObject = $status;
				return false;
			}
	
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
	}
?>
