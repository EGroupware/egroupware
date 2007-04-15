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

	class defaultpop
	{
		var $profileData;
		
		function defaultpop($_profileData)
		{
			$this->profileData = $_profileData;
		}
		
		function addAccount($_hookValues)
		{
			return true;
		}
		
		function deleteAccount($_hookValues)
		{
			return true;
		}
		
		function encodeFolderName($_folderName)
		{
			if($this->mbAvailable)
			{
				return mb_convert_encoding( $_folderName, "UTF7-IMAP", "ISO_8859-1" );
			}
			
			// if not
			return imap_utf7_encode($_folderName);
		}

		function getMailboxString($_folderName='')
		{
			if($this->profileData['imapTLSEncryption'] == 'yes' &&
				 $this->profileData['imapTLSAuthentication'] == 'yes')
			{
				if(empty($this->profileData['imapPort']))
					$port = '995';
				else
					$port = $this->profileData['imapPort'];
					
				$mailboxString = sprintf("{%s:%s/pop3/ssl}%s",
					$this->profileData['imapServer'],
					$port,
					$_folderName);
			}
			// don't check cert
			elseif($this->profileData['imapTLSEncryption'] == 'yes')
			{
				if(empty($this->profileData['imapPort']))
					$port = '995';
				else
					$port = $this->profileData['imapPort'];
					
				$mailboxString = sprintf("{%s:%s/pop3/ssl/novalidate-cert}%s",
					$this->profileData['imapServer'],
					$port,
					$_folderName);
			}
			// no tls
			else
			{
				if(empty($this->profileData['imapPort']))
					$port = '110';
				else
					$port = $this->profileData['imapPort'];
					
				$mailboxString = sprintf("{%s:%s/pop3}%s",
					$this->profileData['imapServer'],
					$port,
					$_folderName);
			}

			return $this->encodeFolderName($mailboxString);
		}

		function updateAccount($_hookValues)
		{
			return true;
		}
	}
?>
