<?php
	/***************************************************************************\
	* eGroupWare - EMailAdmin                                                   *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@egrouware.org]                      *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; version 2 of the License.                       *
	\***************************************************************************/
	/* $Id: class.bopreferences.inc.php,v 1.26 2005/11/28 18:00:18 lkneschke Exp $ */

	class ea_preferences
	{
		// users identities
		var $identities = array();
		
		// users incoming server(imap/pop3)
		var $ic_server = array();
		
		// users outgoing server(smtp)
		var $og_server = array();
		
		// users preferences
		var $preferences = array();
		
		// enable userdefined accounts
		var $userDefinedAccounts = false;
		
		// enable userdefined signatures
		var $ea_user_defined_signatures = false;
		
		function getIdentity($_id = -1)
		{
			if($_id != -1)
			{
				return $this->identities[$_id];
			}
			else
			{
				return $this->identities;
			}
		}
		
		function getIncomingServer($_id = -1)
		{
			if($_id != -1)
			{
				return $this->ic_server[$_id];
			}
			else
			{
				return $this->ic_server;
			}
		}
		
		function getOutgoingServer($_id = -1)
		{
			if($_id != -1)
			{
				return $this->og_server[$_id];
			}
			else
			{
				return $this->og_server;
			}
		}
		
		function getPreferences() {
			return $this->preferences;
		}
		
		function getUserEMailAddresses() {
			$identities = $this->getIdentity();

			if(count($identities) == 0) {
				return false;
			}
			
			$userEMailAdresses = array();
			
			foreach($identities as $identity) {
				$userEMailAdresses[$identity->emailAddress] = $identity->realName;
			}
			
			return $userEMailAdresses;
		}

		function setIdentity($_identityObject, $_id = -1)
		{
			if(($_identityObject instanceof ea_identity))
			{
				if($_id != -1)
				{
					$this->identities[$_id] = $_identityObject;
				}
				else
				{
					$this->identities[] = $_identityObject;
				}

				return true;
			}
			
			return false;
		}
		
		function setIncomingServer($_serverObject, $_id = -1)
		{
			if(($_serverObject instanceof defaultimap))
			{
				if($_id != -1)
				{
					$this->ic_server[$_id] = $_serverObject;
				}
				else
				{
					$this->ic_server[] = $_serverObject;
				}
				
				return true;
			}
			
			return false;
		}

		function setOutgoingServer($_serverObject, $_id = -1)
		{
			if(($_serverObject instanceof defaultsmtp))
			{
				if($_id != -1)
				{
					$this->og_server[$_id] = $_serverObject;
				}
				else
				{
					$this->og_server[] = $_serverObject;
				}
				
				return true;
			}
			
			return false;
		}

		function setPreferences($_preferences)
		{
			$this->preferences = $_preferences;
			
			return true;
		}
	}
?>
