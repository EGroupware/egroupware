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

	require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.sopreferences.inc.php');
	 
	class bopreferences extends sopreferences
	{
		var $public_functions = array
		(
			'getPreferences'	=> True,
		);
		
		// stores the users profile
		var $profileData;
		
		function bopreferences()
		{
			parent::sopreferences();
			$this->boemailadmin =& CreateObject('emailadmin.bo');
		}

		// get user defined accounts		
		function getAccountData(&$_profileData)
		{
			if(!is_a($_profileData, 'ea_preferences'))
				die(__FILE__.': '.__LINE__);
			$accountData = parent::getAccountData($GLOBALS['egw_info']['user']['account_id']);

			// currently we use only the first profile available
			$accountData = array_shift($accountData);
			#_debug_array($accountData);

			$icServer =& CreateObject('emailadmin.defaultimap');
			$icServer->encryption	= isset($accountData['ic_encryption']) ? $accountData['ic_encryption'] : 1;
			$icServer->host		= $accountData['ic_hostname'];
			$icServer->port 	= isset($accountData['ic_port']) ? $accountData['ic_port'] : 143;
			$icServer->validatecert	= isset($accountData['ic_validatecertificate']) ? (bool)$accountData['ic_validatecertificate'] : 1;
			$icServer->username 	= $accountData['ic_username'];
			$icServer->loginName 	= $accountData['ic_username'];
			$icServer->password	= $accountData['ic_password'];
			$icServer->enableSieve	= isset($accountData['ic_enable_sieve']) ? (bool)$accountData['ic_enable_sieve'] : 1;
			$icServer->sieveHost	= $accountData['ic_sieve_server'];
			$icServer->sievePort	= isset($accountData['ic_sieve_port']) ? $accountData['ic_sieve_port'] : 2000;

			$ogServer =& CreateObject('emailadmin.defaultsmtp');
			$ogServer->host		= $accountData['og_hostname'];
			$ogServer->port		= isset($accountData['og_port']) ? $accountData['og_port'] : 25;
			$ogServer->smtpAuth	= (bool)$accountData['og_smtpauth'];
			if($ogServer->smtpAuth) {
				$ogServer->username 	= $accountData['og_username'];
				$ogServer->password 	= $accountData['og_password'];
			}

			$identity =& CreateObject('emailadmin.ea_identity');
			$identity->emailAddress	= $accountData['emailaddress'];
			$identity->realName	= $accountData['realname'];
			$identity->default	= true;
			$identity->organization	= $accountData['organization'];

			$isActive = (bool)$accountData['active'];

			return array('icServer' => $icServer, 'ogServer' => $ogServer, 'identity' => $identity, 'active' => $isActive);
		}
		
		function getListOfSignatures() {
			$userPrefs = $GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig'];
			$signatures = parent::getListOfSignatures($GLOBALS['egw_info']['user']['account_id']);
			
			$GLOBALS['egw']->preferences->read_repository();			
			
			if(count($signatures) == 0 && 
				!isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig_copied']) &&
				!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig'])) {
				
				$this->saveSignature(-1, lang('default signature'), nl2br($GLOBALS['egw_info']['user']['preferences']['felamimail']['email_sig']));
				$signatures = parent::getListOfSignatures($GLOBALS['egw_info']['user']['account_id']);
				$GLOBALS['egw']->preferences->add('felamimail', 'email_sig_copied', true);
				$GLOBALS['egw']->preferences->save_repository();
			}
			
			return $signatures;
		}
		
		function getPreferences()
		{
			if(!is_a($this->profileData,'ea_preferences ')) {

				$imapServerTypes	= $this->boemailadmin->getIMAPServerTypes();
				$profileData		= $this->boemailadmin->getUserProfile('felamimail');

				if(!is_a($profileData, 'ea_preferences') || !is_a($profileData->ic_server[0], 'defaultimap')) {
					return false;
				}
				if($profileData->userDefinedAccounts) {
					// get user defined accounts
					$accountData = $this->getAccountData($profileData);
					
					if($accountData['active']) {
					
						// replace the global defined IMAP Server
						if(is_a($accountData['icServer'],'defaultimap'))
							$profileData->setIncomingServer($accountData['icServer'],0);
					
						// replace the global defined SMTP Server
						if(is_a($accountData['ogServer'],'defaultsmtp'))
							$profileData->setOutgoingServer($accountData['ogServer'],0);
					
						// replace the global defined identity
						if(is_a($accountData['identity'],'ea_identity'))
							$profileData->setIdentity($accountData['identity'],0);
					}
				}
				
				$GLOBALS['egw']->preferences->read_repository();
				$userPrefs = $GLOBALS['egw_info']['user']['preferences']['felamimail'];
				if(empty($userPrefs['deleteOptions']))
					$userPrefs['deleteOptions'] = 'mark_as_deleted';
				
				#$data['trash_folder']		= $userPrefs['felamimail']['trashFolder'];
				if (!empty($userPrefs['trash_folder'])) 
					$userPrefs['move_to_trash'] 	= True;
				if (!empty($userPrefs['sent_folder'])) 
					$userPrefs['move_to_sent'] 	= True;
				$userPrefs['signature']		= $userPrefs['email_sig'];
				
	 			unset($userPrefs['email_sig']);
 			
 				$profileData->setPreferences($userPrefs);

				#_debug_array($profileData);exit;
			
				$this->profileData = $profileData;
				
				#_debug_array($this->profileData);
			}

			return $this->profileData;
		}
		
		function getSignature($_signatureID) 
		{
			return parent::getSignature($GLOBALS['egw_info']['user']['account_id'], $_signatureID);
		}
		
		function getDefaultSignature() 
		{
			return parent::getDefaultSignature($GLOBALS['egw_info']['user']['account_id']);
		}
		
		function deleteSignatures($_signatureID) 
		{
			if(!is_array($_signatureID)) {
				return false;
			}
			return parent::deleteSignatures($GLOBALS['egw_info']['user']['account_id'], $_signatureID);
		}
		
		function saveAccountData($_icServer, $_ogServer, $_identity) 
		{
			if(!isset($_icServer->validatecert)) {
				$_icServer->validatecert = true;
			}
			
			if(isset($_icServer->host)) {
				$_icServer->sieveHost = $_icServer->host;
			}

			parent::saveAccountData($GLOBALS['egw_info']['user']['account_id'], $_icServer, $_ogServer, $_identity);
		}
		
		function saveSignature($_signatureID, $_description, $_signature, $_isDefaultSignature) 
		{
			return parent::saveSignature($GLOBALS['egw_info']['user']['account_id'], $_signatureID, $_description, $_signature, (bool)$_isDefaultSignature);
		}

		function setProfileActive($_status) 
		{
			parent::setProfileActive($GLOBALS['egw_info']['user']['account_id'], $_status);
		}
	}
?>