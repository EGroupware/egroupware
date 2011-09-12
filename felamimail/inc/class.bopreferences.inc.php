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
		var $sessionData;
		var $boemailadmin;

		function bopreferences($_restoreSession = true)
		{
			//error_log(__METHOD__." called ".print_r($_restoreSession,true).function_backtrace());
			parent::sopreferences();
			$this->boemailadmin = new emailadmin_bo(-1,$_restoreSession);
			if ($_restoreSession && !(is_array($this->sessionData) && (count($this->sessionData)>0))  ) $this->restoreSessionData();
			if ($_restoreSession===false && (is_array($this->sessionData) && (count($this->sessionData)>0))  ) 
			{
				//error_log(__METHOD__." Unset Session ".function_backtrace());
				//make sure session data will be reset
				$this->sessionData = array();
				$this->profileData = array();
				self::saveSessionData();
			}
			//error_log(__METHOD__.print_r($this->sessionData,true));
			if (isset($this->sessionData['profileData']) && ($this->sessionData['profileData'] instanceof ea_preferences)) {
				$this->profileData = $this->sessionData['profileData'];
			}
		}

		function restoreSessionData()
		{
			//error_log(__METHOD__." Session restore ".function_backtrace());
			// set an own autoload function, search emailadmin for missing classes
			$GLOBALS['egw_info']['flags']['autoload'] = array(__CLASS__,'autoload');

			$this->sessionData = (array) unserialize($GLOBALS['egw']->session->appsession('fm_preferences','felamimail'));
		}

		/**
		 * Autoload classes from emailadmin, 'til they get autoloading conform names
		 *
		 * @param string $class
		 */
		static function autoload($class)
		{
			if (file_exists($file=EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$class.'.inc.php'))
			{
				include_once($file);
				//error_log(__METHOD__."($class) included $file");
			}
			elseif (file_exists($file=EGW_INCLUDE_ROOT.'/felamimail/inc/class.'.$class.'.inc.php'))
			{
				include_once($file);
			}
			else
			{
				#error_log(__METHOD__."($class) failed!");
			}
		}

		function saveSessionData()
		{
			$GLOBALS['egw']->session->appsession('fm_preferences','felamimail',serialize($this->sessionData));
		}
		// get the first active user defined account
		function getAccountData(&$_profileData, $_accountID=NULL)
		{
			#echo "<p>backtrace: ".function_backtrace()."</p>\n";
			if(!($_profileData instanceof ea_preferences))
				die(__FILE__.': '.__LINE__);
			$accountData = parent::getAccountData($GLOBALS['egw_info']['user']['account_id'],$_accountID);

			// currently we use only the first profile available
			$accountData = array_shift($accountData);
			#_debug_array($accountData);

			$icServer = CreateObject('emailadmin.defaultimap');
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
			if ($accountData['ic_folderstoshowinhome']) $icServer->folderstoshowinhome	= $accountData['ic_folderstoshowinhome'];
			if ($accountData['ic_trashfolder']) $icServer->trashfolder = $accountData['ic_trashfolder'];
			if ($accountData['ic_sentfolder']) $icServer->sentfolder = $accountData['ic_sentfolder'];
			if ($accountData['ic_draftfolder']) $icServer->draftfolder = $accountData['ic_draftfolder'];
			if ($accountData['ic_templatefolder']) $icServer->templatefolder = $accountData['ic_templatefolder'];

			$ogServer = CreateObject('emailadmin.defaultsmtp');
			$ogServer->host		= $accountData['og_hostname'];
			$ogServer->port		= isset($accountData['og_port']) ? $accountData['og_port'] : 25;
			$ogServer->smtpAuth	= (bool)$accountData['og_smtpauth'];
			if($ogServer->smtpAuth) {
				$ogServer->username 	= $accountData['og_username'];
				$ogServer->password 	= $accountData['og_password'];
			}

			$identity = CreateObject('emailadmin.ea_identity');
			$identity->emailAddress	= $accountData['emailaddress'];
			$identity->realName	= $accountData['realname'];
			//$identity->default	= true;
			$identity->default = (bool)$accountData['active'];
			$identity->organization	= $accountData['organization'];
			$identity->signature = $accountData['signatureid'];
			$identity->id  = $accountData['id'];

			$isActive = (bool)$accountData['active'];

			return array('icServer' => $icServer, 'ogServer' => $ogServer, 'identity' => $identity, 'active' => $isActive);
		}

		function getAllAccountData(&$_profileData)
		{
			if(!($_profileData instanceof ea_preferences))
				die(__FILE__.': '.__LINE__);
			$AllAccountData = parent::getAccountData($GLOBALS['egw_info']['user']['account_id'],'all');
			#_debug_array($accountData);
			foreach ($AllAccountData as $key => $accountData)
			{
				$icServer = CreateObject('emailadmin.defaultimap');
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
				if ($accountData['ic_folderstoshowinhome']) $icServer->folderstoshowinhome = $accountData['ic_folderstoshowinhome'];
				if ($accountData['ic_trashfolder']) $icServer->trashfolder = $accountData['ic_trashfolder'];
				if ($accountData['ic_sentfolder']) $icServer->sentfolder = $accountData['ic_sentfolder'];
				if ($accountData['ic_draftfolder']) $icServer->draftfolder = $accountData['ic_draftfolder'];
				if ($accountData['ic_templatefolder']) $icServer->templatefolder = $accountData['ic_templatefolder'];

				$ogServer = CreateObject('emailadmin.defaultsmtp');
				$ogServer->host		= $accountData['og_hostname'];
				$ogServer->port		= isset($accountData['og_port']) ? $accountData['og_port'] : 25;
				$ogServer->smtpAuth	= (bool)$accountData['og_smtpauth'];
				if($ogServer->smtpAuth) {
					$ogServer->username 	= $accountData['og_username'];
					$ogServer->password 	= $accountData['og_password'];
				}

				$identity = CreateObject('emailadmin.ea_identity');
				$identity->emailAddress	= $accountData['emailaddress'];
				$identity->realName	= $accountData['realname'];
				//$identity->default	= true;
				$identity->default = (bool)$accountData['active'];
				$identity->organization	= $accountData['organization'];
				$identity->signature = $accountData['signatureid'];
				$identity->id  = $accountData['id'];
				$isActive = (bool)$accountData['active'];
				$out[] = array('icServer' => $icServer, 'ogServer' => $ogServer, 'identity' => $identity, 'active' => $isActive);
			}
			return $out;
		}

		function getUserDefinedIdentities()
		{
			$profileData        = $this->boemailadmin->getUserProfile('felamimail');
			if(!($profileData instanceof ea_preferences) || !($profileData->ic_server[0] instanceof defaultimap)) {
				return false;
			}
			if($profileData->userDefinedAccounts || $profileData->userDefinedIdentities) {
				// get user defined accounts
				$allAccountData = $this->getAllAccountData($profileData);
				if ($allAccountData) {
					foreach ($allAccountData as $tmpkey => $accountData)
					{
						$accountArray[] = $accountData['identity'];
					}
					return $accountArray;
				}
			}
			return array();
		}

		function getPreferences($getUserDefinedProfiles=true)
		{
			if (isset($this->sessionData['profileData']) && ($this->sessionData['profileData'] instanceof ea_preferences)) {
				$this->profileData = $this->sessionData['profileData'];
			}
			if(!($this->profileData instanceof ea_preferences)) {
				$imapServerTypes	= $this->boemailadmin->getIMAPServerTypes();
				$profileData		= $this->boemailadmin->getUserProfile('felamimail');

				if(!($profileData instanceof ea_preferences) || !($profileData->ic_server[0] instanceof defaultimap)) {
					return false;
				}
				if($profileData->userDefinedAccounts && $GLOBALS['egw_info']['user']['apps']['felamimail'] && $getUserDefinedProfiles) {
					// get user defined accounts
					$accountData = $this->getAccountData($profileData);

					if($accountData['active']) {

						// replace the global defined IMAP Server
						if(($accountData['icServer'] instanceof defaultimap))
							$profileData->setIncomingServer($accountData['icServer'],0);

						// replace the global defined SMTP Server
						if(($accountData['ogServer'] instanceof defaultsmtp))
							$profileData->setOutgoingServer($accountData['ogServer'],0);

						// replace the global defined identity
						if(($accountData['identity'] instanceof ea_identity)) {
							//_debug_array($profileData);
							$rememberIdentities = $profileData->identities;
							$profileData->setIdentity($accountData['identity'],0);
							$rememberID = $accountData['identity']->id;
						}
					}
				}
				if($profileData->userDefinedIdentities && $GLOBALS['egw_info']['user']['apps']['felamimail']) 
				{
					$allUserIdentities = $this->getUserDefinedIdentities();
					if (is_array($allUserIdentities)) 
					{
						$i=count($allUserIdentities);
						$y=-1;
						foreach ($allUserIdentities as $tmpkey => $id)
						{
							if ($id->id != $rememberID) 
							{
								$profileData->setIdentity($id,$i);
								$i++;
							}
							else
							{	
								foreach ($rememberIdentities as $adkey => $ident)
								{
									$profileData->setIdentity($ident,$i);
									$profileData->identities[$i]->default = false;
									$profileData->identities[$i]->id = $y;
									$i++;
									$y--;
								}
							}
						}
					}
				}

				$GLOBALS['egw']->preferences->read_repository();
				$userPrefs = $GLOBALS['egw_info']['user']['preferences']['felamimail'];
				# echo "<p>backtrace: ".function_backtrace()."</p>\n";
				if (is_array($profileData->ic_server[0]->folderstoshowinhome) && !empty($profileData->ic_server[0]->folderstoshowinhome[0])) {
					$userPrefs['mainscreen_showfolders'] = implode(',',$profileData->ic_server[0]->folderstoshowinhome);
				}
				if (!empty($profileData->ic_server[0]->sentfolder)) $userPrefs['sentFolder'] = $profileData->ic_server[0]->sentfolder;
				if (!empty($profileData->ic_server[0]->trashfolder)) $userPrefs['trashFolder'] = $profileData->ic_server[0]->trashfolder;
				if (!empty($profileData->ic_server[0]->draftfolder)) $userPrefs['draftFolder'] = $profileData->ic_server[0]->draftfolder;
				if (!empty($profileData->ic_server[0]->templatefolder)) $userPrefs['templateFolder'] = $profileData->ic_server[0]->templatefolder;
				if(empty($userPrefs['deleteOptions']))
					$userPrefs['deleteOptions'] = 'mark_as_deleted';

				if (!empty($userPrefs['trash_folder']))
					$userPrefs['move_to_trash'] 	= True;
				if (!empty($userPrefs['sent_folder']))
					$userPrefs['move_to_sent'] 	= True;

				$userPrefs['signature']		= $userPrefs['email_sig'];

	 			unset($userPrefs['email_sig']);

 				$profileData->setPreferences($userPrefs);

				#_debug_array($profileData);#exit;

				$this->sessionData['profileData'] = $this->profileData = $profileData;
				$this->saveSessionData();
				#_debug_array($this->profileData);
			}
			return $this->profileData;
		}

		function ggetSignature($_signatureID, $_unparsed = false)
		{
			if($_signatureID == -1) {
				$profileData = $this->boemailadmin->getUserProfile('felamimail');

				$systemSignatureIsDefaultSignature = !parent::getDefaultSignature($GLOBALS['egw_info']['user']['account_id']);

				$systemSignature = array(
					'signatureid'		=> -1,
					'description'		=> 'eGroupWare '. lang('default signature'),
					'signature'		=> ($_unparsed === true ? $profileData->ea_default_signature : $GLOBALS['egw']->preferences->parse_notify($profileData->ea_default_signature)),
					'defaultsignature'	=> $systemSignatureIsDefaultSignature,
				);

				return $systemSignature;

			} else {
				require_once('class.felamimail_signatures.inc.php');
				$signature = new felamimail_signatures($_signatureID);
				if($_unparsed === false) {
					$signature->fm_signature = $GLOBALS['egw']->preferences->parse_notify($signature->fm_signature);
				}
				return $signature;
			}
		}

		function ggetDefaultSignature()
		{
			return parent::getDefaultSignature($GLOBALS['egw_info']['user']['account_id']);
		}

		function ddeleteSignatures($_signatureID)
		{
			if(!is_array($_signatureID)) {
				return false;
			}
			return parent::deleteSignatures($GLOBALS['egw_info']['user']['account_id'], $_signatureID);
		}

		function saveAccountData($_icServer, $_ogServer, $_identity)
		{
			if(is_object($_icServer) && !isset($_icServer->validatecert)) {
				$_icServer->validatecert = true;
			}
			if(isset($_icServer->host)) {
				$_icServer->sieveHost = $_icServer->host;
			}
			$this->sessionData = array();
			$this->saveSessionData();
			return parent::saveAccountData($GLOBALS['egw_info']['user']['account_id'], $_icServer, $_ogServer, $_identity);
		}

		function deleteAccountData($_identity)
		{
			if (is_array($_identity)) {
				foreach ($_identity as $tmpkey => $id)
				{
					if ($id->id) {
						$identity[] = $id->id;
					} else {
						$identity[] = $id;
					}
				}
			} else {
				$identity = $_identity;
			}
			$this->sessionData = array();
			$this->saveSessionData();
			parent::deleteAccountData($GLOBALS['egw_info']['user']['account_id'], $identity);
		}

		function ssaveSignature($_signatureID, $_description, $_signature, $_isDefaultSignature)
		{
			return parent::saveSignature($GLOBALS['egw_info']['user']['account_id'], $_signatureID, $_description, $_signature, (bool)$_isDefaultSignature);
		}

		function setProfileActive($_status, $_identity=NULL)
		{
			$this->sessionData = array();
			$this->saveSessionData();
			parent::setProfileActive($GLOBALS['egw_info']['user']['account_id'], $_status, $_identity);
		}
	}
?>
