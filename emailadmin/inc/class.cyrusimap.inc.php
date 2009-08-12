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
	
	include_once(EGW_SERVER_ROOT."/emailadmin/inc/class.defaultimap.inc.php");
	
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

			$mailboxString = $nameSpaces['others'][0]['name'] . strtolower($_username) . (!empty($_folderName) ? $nameSpaces['others'][0]['delimiter'] . $_folderName : '');
			
			if($this->loginType == 'vmailmgr') {
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

			// create the mailbox
			$mailboxName = $this->getUserMailboxString($username, $mailboxName);
			$folderInfo = $this->getMailboxes('', $mailboxName, true);
			if(empty($folderInfo)) {
				if(!PEAR::isError($this->createMailbox($mailboxName))) {
					if(PEAR::isError($this->setACL($mailboxName, $username, "lrswipcda"))) {
						# log error message
					}
				}
			}
			$this->disconnect();

			# this part got moved to FeLaMiMail
			#// we can only subscribe to the folders, if we have the users password
			#if(isset($_hookValues['new_passwd'])) {
			#	// subscribe to the folders
			#	if($mbox = @imap_open($this->getMailboxString(), $username, $userPassword)) {
			#		foreach($this->createMailboxes as $mailboxName) {
			#			$mailboxName = 'INBOX' . ($mailboxName ? $this->getDelimiter() .$mailboxName : '');
			#			imap_subscribe($mbox,$this->getMailboxString($mailboxName));
			#		}
			#		imap_close($mbox);
			#	} else {
			#		# log error message
			#	}
			#}
		}
	}
?>
