<?php
	/***************************************************************************\
	* eGroupWare - FeLaMiMail                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class bocaching
	{
		var $public_functions = array
		(
			'updateImapStatus'	=> True,
			'action'	=> True
		);

		var $hostname;		// the hostname of the imap server
		var $accountname;	// the accountname, used to connect to this imap server
		var $foldername;	// folder name
		var $accountid;		// phpgw accountid
		var $messages;		// the number of messages in the mailbox
		var $recent;		// the number of recent messages in the mailbox
		var $unseen;		// the number of recent messages in the mailbox
		var $uidnext;		// the next uid to be used in the mailbox
		var $uidvalidity;	// the next uid to be used in the mailbox
		
		function bocaching($_hostname, $_accountname, $_foldername)
		{
			$this->hostname		= $_hostname;
			$this->accountname	= $_accountname;
			$this->foldername	= $_foldername;
			$this->accountid	= $GLOBALS['egw_info']['user']['account_id'];
			
			$this->socaching	=& CreateObject('felamimail.socaching',
							$this->hostname, $this->accountname, $this->foldername, $this->accountid);
			
			$status = $this->socaching->getImapStatus();
			if ($status != 0)
			{
				$this->messages		= $status['messages'];
				$this->recent 		= $status['recent'];
				$this->unseen 		= $status['unseen'];
				$this->uidnext 		= $status['uidnext'];
				$this->uidvalidity 	= $status['uidvalidity'];
			}
			else
			{
				$this->messages		= 0;
				$this->recent 		= 0;
				$this->unseen 		= 0;
				$this->uidnext 		= 0;
				$this->uidvalidity 	= 0;
			}
			
		}
		
		function addToCache($_data)
		{
			$search = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
			$replace = '';
			
			$data = preg_replace($search,$replace,$_data);
			
			$this->socaching->addToCache($data);
		}
		
		function clearCache($_folderName='')
		{
			$this->socaching->clearCache($_folderName);
		}
		
		function debug()
		{
			print "Hostname: ".$this->hostname."<br>";
			print "Messages: ".$this->messages."<br>";
			print "Unseen: ".$this->unseen."<br>";
			print "Uidnext: ".$this->uidnext."<br>";
			print "Uidvalidity: ".$this->uidvalidity."<br>";
		}
		
		function getHeaders($_firstMessage='', $_numberOfMessages='' ,$_sort='', $_filter='')
		{
			return $this->socaching->getHeaders($_firstMessage, $_numberOfMessages, $_sort, $_filter);
		}
		
		function getImapStatus()
		{
			$retValue = array
			(
				'messages'      => $this->messages,
				'recent'        => $this->recent,
				'unseen'        => $this->unseen,
				'uidnext'       => $this->uidnext,
				'uidvalidity'   => $this->uidvalidity
			);
			
			return $retValue;

		}
		
		// return the numbers of messages in cache currently
		// but use the use filter
		function getMessageCounter($_filter=FALSE)
		{
			return $this->socaching->getMessageCounter($_filter);
		}
		
		function getNextMessage($_uid, $_sort, $_filter)
		{
			return $this->socaching->getNextMessage($_uid, $_sort, $_filter);
		}
		
		function removeFromCache($_uid)
		{
			$this->socaching->removeFromCache($_uid);
		}		
		
		// expects the result from imap_status ($mbox, "{".$imapServerAddress.":$imapPort}$mailbox", SA_ALL);
		function updateImapStatus($_status)
		{
			// are we updating the first time
			#if ($this->uidnext == 0)
			#{
			#	$this->messages		= $_status->messages;
			#	$this->recent 		= $_status->recent;
			#	$this->unseen 		= $_status->unseen;
			#	$this->uidnext 		= $_status->uidnext;
			#	$this->uidvalidity 	= $_status->uidvalidity;
			#
			#	$this->socaching->updateImapStatus($_status,true);
			#}
			#else
			#{
				$this->messages		= $_status->messages;
				$this->recent 		= $_status->recent;
				$this->unseen 		= $_status->unseen;
				$this->uidnext 		= $_status->uidnext;
				$this->uidvalidity 	= $_status->uidvalidity;
			
				return $this->socaching->updateImapStatus($_status);
			#}
			
		}
		
	}

?>
