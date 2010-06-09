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
		
		/**
		 * Switch on some error_log debug messages
		 *
		 * @var boolean
		 */
		var $debug = false;
	
		function bosieve($_icServer=null)
		{
			parent::Net_Sieve();
			
			$this->scriptName = (!empty($GLOBALS['egw_info']['user']['preferences']['felamimail']['sieveScriptName']) ? $GLOBALS['egw_info']['user']['preferences']['felamimail']['sieveScriptName'] : 'felamimail');

			$this->displayCharset	= $GLOBALS['egw']->translation->charset();
			
			if (!is_null($_icServer) && $this->_connect($_icServer) === 'die') {
				die('Sieve not activated');
			}
		}

		/**
		 * Open connection to the sieve server
		 *
		 * @param defaultimap $_icServer
		 * @param string $euser='' effictive user, if given the Cyrus admin account is used to login on behalf of $euser
		 * @return mixed 'die' = sieve not enabled, false=connect or login failure, true=success
		 */
		function _connect($_icServer,$euser='')
		{
			if(is_a($_icServer,'defaultimap') && $_icServer->enableSieve) {
				$sieveHost		= $_icServer->host;
				$sievePort		= $_icServer->sievePort;
				$useTLS			= $_icServer->encryption > 0;
				if ($euser) {
					$username		= $_icServer->adminUsername;
					$password		= $_icServer->adminPassword;
				} else {
					$username		= $_icServer->loginName;
					$password		= $_icServer->password;
				}
				$this->icServer = $_icServer;
			} else {
				return 'die';
			}

			if(PEAR::isError($this->error = $this->connect($sieveHost , $sievePort, null, $useTLS) ) ){
				if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.": error in connect($sieveHost,$sievePort): ".$this->error->getMessage());
				return false;
			}
			if(PEAR::isError($this->error = $this->login($username, $password, null, $euser) ) ){
				if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.": error in login($username,$password,null,$euser): ".$this->error->getMessage());
				return false;
			}
			return true;
		}
		
		function getRules($_scriptName) {
			return $this->rules;
		}

		function getVacation($_scriptName) {
			return $this->vacation;
		}
	
		function getEmailNotification($_scriptName) {
			return $this->emailNotification;
		}
	
		function setRules($_scriptName, $_rules) 
		{
			$script         =& CreateObject('felamimail.Script',$_scriptName);
			$script->debug = $this->debug;

			if($script->retrieveRules($this)) {
				$script->rules = $_rules;
				$script->updateScript($this);
				
				return true;
			} 

			return false;
		}

		function setVacation($_scriptName, $_vacation) 
		{
			if ($this->debug) error_log(__CLASS__.'::'.__METHOD__."($_scriptName,".print_r($_vacation,true).')');
			$script         =& CreateObject('felamimail.Script',$_scriptName);
			$script->debug = $this->debug;

			if($script->retrieveRules($this)) {
				$script->vacation = $_vacation;
				$script->updateScript($this);
				
				// setting up an async job to enable/disable the vacation message
				include_once(EGW_API_INC.'/class.asyncservice.inc.php');
				$async = new asyncservice();
				$user = $GLOBALS['egw_info']['user']['account_id'];
				$async->delete($async_id ="felamimail-vacation-$user");
				$end_date = $_vacation['end_date'] + 24*3600;	// end-date is inclusive, so we have to add 24h
				if ($_vacation['status'] == 'by_date' && time() < $end_date)
				{
					$time = time() < $_vacation['start_date'] ? $_vacation['start_date'] : $end_date;
					$async->set_timer($time,$async_id,'felamimail.bosieve.async_vacation',$_vacation+array('scriptName'=>$_scriptName),$user);
				}
				return true;
			}
			if ($this->debug) error_log(__CLASS__.'::'.__METHOD__."($_scriptName,".print_r($_vacation,true).') could not retrieve rules!');

			return false;
		}
		
		/**
		 * Callback for the async job to enable/disable the vacation message
		 *
		 * @param array $_vacation
		 */
		function async_vacation($_vacation)
		{
			if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.'('.print_r($_vacation,true).')');
			// unset the fm_preferences session object, to force the reload/rebuild
			$GLOBALS['egw']->session->appsession('fm_preferences','felamimail',serialize(array()));
			$GLOBALS['egw']->session->appsession('session_data','emailadmin',serialize(array()));

			$_restoreSession = false; // as in async, each call may be for a different user
			$bopreferences    = CreateObject('felamimail.bopreferences',$_restoreSession);
			$mailPreferences  = $bopreferences->getPreferences();
			$icServer = $mailPreferences->getIncomingServer(0);
			//error_log(__METHOD__.$icServer->loginName);	
			if ($this->_connect($icServer,$icServer->loginName) === true) {			
				$this->setVacation($_vacation['scriptName'],$_vacation);
				// we need to logout, so further vacation's get processed
				$error = $this->_cmdLogout();
				if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.' logout '.(PEAR::isError($error) ? 'failed: '.$ret->getMessage() : 'successful'));
			}
		}

		function setEmailNotification($_scriptName, $_emailNotification) {
	    	if ($_emailNotification['externalEmail'] == '' || !preg_match("/\@/",$_emailNotification['externalEmail'])) {
	    		$_emailNotification['status'] = 'off';
	    		$_emailNotification['externalEmail'] = '';
	    	}

	    	$script =& CreateObject('felamimail.Script',$_scriptName);
	    	if ($script->retrieveRules($this)) {
	    		$script->emailNotification = $_emailNotification;
	    		return $script->updateScript($this);
	    	}
	    	return false;
		}

		function retrieveRules($_scriptName) {
			$script         =& CreateObject('felamimail.Script',$_scriptName);
			
			if($script->retrieveRules($this)) {
				$this->rules = $script->rules;
				$this->vacation = $script->vacation;
				$this->emailNotification = $script->emailNotification; // Added email notifications	
				return true;
			} 
			
			return false;
		}
	}
?>
