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
	
	include_once(PHPGW_SERVER_ROOT."/emailadmin/inc/class.defaultimap.inc.php");
	
	class cyrusimap extends defaultimap
	{
		#function cyrusimap()
		#{
		#}
		
		function addAccount($_hookValues)
		{
			#_debug_array($_hookValues);
			$username 	= $_hookValues['account_lid'];
			$userPassword	= $_hookValues['new_passwd'];
			
			#_debug_array($this->profileData);
			$imapAdminUsername	= $this->profileData['imapAdminUsername'];
			$imapAdminPW		= $this->profileData['imapAdminPW'];

			$folderNames = array(
				"user.$username",
				"user.$username.Trash",
				"user.$username.Sent"
			);
			
			// create the mailbox
			if($mbox = @imap_open ($this->getMailboxString(), $imapAdminUsername, $imapAdminPW))
			{
				// create the users folders
				foreach($folderNames as $mailBoxName)
				{
					if(imap_createmailbox($mbox,imap_utf7_encode("{".$this->profileData['imapServer']."}$mailBoxName")))
					{
						if(!imap_setacl($mbox, $mailBoxName, $username, "lrswipcd"))
						{
							# log error message
						}
					}
				}
				imap_close($mbox);
			}
			else
			{
				_debug_array(imap_errors());
				return false;
			}
			
			// subscribe to the folders
			if($mbox = @imap_open($this->getMailboxString(), $username, $userPassword))
			{
				imap_subscribe($mbox,$this->getMailboxString('INBOX'));
				imap_subscribe($mbox,$this->getMailboxString('INBOX.Sent'));
				imap_subscribe($mbox,$this->getMailboxString('INBOX.Trash'));
				imap_close($mbox);
			}
			else
			{
				# log error message
			}
		}
		
		function deleteAccount($_hookValues)
		{
			$username		= $_hookValues['account_lid'];
		
			$imapAdminUsername	= $this->profileData['imapAdminUsername'];
			$imapAdminPW		= $this->profileData['imapAdminPW'];

			if($mbox = @imap_open($this->getMailboxString(), $imapAdminUsername, $imapAdminPW))
			{
				$mailBoxName = "user.$username";
				// give the admin account the rights to delete this mailbox
				if(imap_setacl($mbox, $mailBoxName, $imapAdminUsername, "lrswipcda"))
				{
					if(imap_deletemailbox($mbox,
						imap_utf7_encode("{".$this->profileData['imapServer']."}$mailBoxName")))
					{
						return true;
					}
					else
					{
						// not able to delete mailbox
						return false;
					}
				}
				else
				{
					// not able to set acl
					return false;
				}
			}
			else
			{
				// imap open failed
				return false;
			}
		}

		function updateAccount($_hookValues)
		{
			#_debug_array($_hookValues);
			$username 	= $_hookValues['account_lid'];
			if(isset($_hookValues['new_passwd']))
				$userPassword	= $_hookValues['new_passwd'];
			
			#_debug_array($this->profileData);
			$imapAdminUsername	= $this->profileData['imapAdminUsername'];
			$imapAdminPW		= $this->profileData['imapAdminPW'];

			$folderNames = array(
				"user.$username",
				"user.$username.Trash",
				"user.$username.Sent"
			);
			
			// create the mailbox
			if($mbox = @imap_open ($this->getMailboxString(), $imapAdminUsername, $imapAdminPW))
			{
				// create the users folders
				foreach($folderNames as $mailBoxName)
				{
					if(imap_createmailbox($mbox,imap_utf7_encode("{".$this->profileData['imapServer']."}$mailBoxName")))
					{
						if(!imap_setacl($mbox, $mailBoxName, $username, "lrswipcd"))
						{
							# log error message
						}
					}
				}
				imap_close($mbox);
			}
			else
			{
				return false;
			}
			
			// we can only subscribe to the folders, if we have the users password
			if(isset($_hookValues['new_passwd']))
			{
				if($mbox = @imap_open($this->getMailboxString(), $username, $userPassword))
				{
					imap_subscribe($mbox,$this->getMailboxString('INBOX'));
					imap_subscribe($mbox,$this->getMailboxString('INBOX.Sent'));
					imap_subscribe($mbox,$this->getMailboxString('INBOX.Trash'));
					imap_close($mbox);
				}
				else
				{
					# log error message
				}
			}
		}
	}
?>
