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
	* Free Software Foundation; version 2 of the License.                       *
	\***************************************************************************/
	/* $Id: class.uisieve.inc.php,v 1.24 2005/11/30 08:29:45 ralfbecker Exp $ */

	#include_once(EGW_SERVER_ROOT. '/felamimail/inc/Sieve.php');
	include_once('Net/Sieve.php');

	class bosieve extends Net_Sieve {
		/**
		* @var object $icServer object containing the information about the imapserver
		*/
		var $icServer;
	
		/**
		* @var object $icServer object containing the information about the imapserver
		*/
		var $scriptName;

		/**
		* @var object $error the last PEAR error object
		*/
		var $error;
	
		function bosieve($_icServer) 
		{
			parent::Net_Sieve();
			
			$this->scriptName = (!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['sieveScriptName']) ? $GLOBALS['egw_info']['user']['preferences']['felamimail']['sieveScriptName'] : 'felamimail');

			$this->displayCharset	= $GLOBALS['egw']->translation->charset();

			if(is_a($_icServer,'defaultimap') && $_icServer->enableSieve) {
				$sieveHost		= $_icServer->host;
				$sievePort		= $_icServer->sievePort;
				$username		= $_icServer->loginName;
				$password		= $_icServer->password;
				
				$this->icServer = $_icServer;
			} else {
				die('Sieve not activated');
			}

			if(PEAR::isError($this->error = $this->connect($sieveHost , $sievePort) ) ){
				return false;
			}

			if(PEAR::isError($this->error = $this->login($username, $password) ) ){
				return false;
			}
		}
		
		function getRules($_scriptName) {
			return $this->rules;
		}

		function getVacation($_scriptName) {
			return $this->vacation;
		}
		
		function setRules($_scriptName, $_rules) 
		{
			$script         =& CreateObject('felamimail.Script',$_scriptName);

			if($script->retrieveRules($this)) {
				$script->rules = $_rules;
				$script->updateScript($this);
				
				return true;
			} 

			return false;
		}

		function setVacation($_scriptName, $_vacation) 
		{
			$script         =& CreateObject('felamimail.Script',$_scriptName);

			if($script->retrieveRules($this)) {
				$script->vacation = $_vacation;
				$script->updateScript($this);
				
				return true;
			} 

			return false;
		}

		function retrieveRules($_scriptName) {
			$script         =& CreateObject('felamimail.Script',$_scriptName);
			
			if($script->retrieveRules($this)) {
				$this->rules = $script->rules;
				$this->vacation = $script->vacation;
				
				return true;
			} 
			
			return false;
		}
	}
?>
