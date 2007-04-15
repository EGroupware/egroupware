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
	/* $Id: class.socaching.inc.php,v 1.21 2005/11/04 18:37:37 ralfbecker Exp $ */

	class sopreferences
	{
		var $accounts_table = 'egw_felamimail_accounts';
		var $signatures_table = 'egw_felamimail_signatures';
		/**
		 * Instance of the db-class
		 *
		 * @var egw_db
		 */
		var $db;
		
		function sopreferences()
		{
			$this->db = clone($GLOBALS['egw']->db);
			$this->db->set_app('felamimail');
		}
		
		function getAccountData($_accountID)
		{
			// no valid accountID
			if(($accountID = (int)$_accountID) < 1)
				return array();
				
			$retValue	= array();
			$where		= array('fm_owner' => $accountID);
			
			$this->db->select($this->accounts_table,'fm_id,fm_active,fm_realname,fm_organization,fm_emailaddress,fm_ic_hostname,fm_ic_port,fm_ic_username,fm_ic_password,fm_ic_encryption,fm_ic_validatecertificate,fm_ic_enable_sieve,fm_ic_sieve_server,fm_ic_sieve_port,fm_og_hostname,fm_og_port,fm_og_smtpauth,fm_og_username,fm_og_password',
				$where,__LINE__,__FILE__);
				
			while(($row = $this->db->row(true,'fm_'))) {
				$retValue[$row['id']] = $row;
			}

			return $retValue;
		}

		function getListOfSignatures($_accountID)
		{
			// no valid accountID
			if(($accountID = (int)$_accountID) < 1)
				return false;
				
			$retValue	= array();
			
			$where		= array('fm_accountid' => $accountID);
			
			$this->db->select($this->signatures_table,'fm_signatureid,fm_description,fm_defaultsignature',
				$where, __LINE__, __FILE__);
				
			while(($row = $this->db->row(true,'fm_'))) {
				$retValue[$row['signatureid']] = $row;
			}
			
			return $retValue;
		}

		function getSignature($_accountID, $_signatureID) 
		{
			// no valid accountID
			if(($accountID = (int)$_accountID) < 1)
				return false;
				
			$retValue	= array();
			
			$where		= array(
				'fm_accountid'		=> $accountID,
				'fm_signatureid'	=> $_signatureID
			);
			
			$this->db->select($this->signatures_table,'fm_signatureid,fm_description,fm_signature,fm_defaultsignature',
				$where, __LINE__, __FILE__);
				
			if(($row = $this->db->row(true,'fm_'))) {
				return $row;
			}

			return $retValue;
		}

		function getDefaultSignature($_accountID) 
		{
			// no valid accountID
			if(($accountID = (int)$_accountID) < 1)
				return false;
				
			$where		= array(
				'fm_accountid'		=> $accountID,
				'fm_defaultsignature'	=> true
			);
			
			$this->db->select($this->signatures_table,'fm_signatureid,fm_description,fm_signature,fm_defaultsignature',
				$where, __LINE__, __FILE__);
				
			if(($row = $this->db->row(true,'fm_'))) {
				return $row;
			}

			return false;
		}

		function deleteSignatures($_accountID, $_signatureIDs) 
		{
			// no valid accountID
			if(($accountID = (int)$_accountID) < 1)
				return false;
				
			foreach($_signatureIDs as $signatureID) {
			
				$where		= array(
					'fm_accountid'		=> $accountID,
					'fm_signatureid'	=> $signatureID
				);
			
				$this->db->delete($this->signatures_table, $where, __LINE__, __FILE__);
				}

			return true;
		}

		function saveAccountData($_accountID, $_icServer, $_ogServer, $_identity)
		{
			
			$data = array(
				'fm_active'			=> 0,
				'fm_realname'			=> $_identity->realName,
				'fm_organization'		=> $_identity->organization,
				'fm_emailaddress'		=> $_identity->emailAddress,
				'fm_ic_hostname'		=> $_icServer->host,
				'fm_ic_port'			=> $_icServer->port,
				'fm_ic_username'		=> $_icServer->username,
				'fm_ic_password'		=> $_icServer->password,
				'fm_ic_encryption'		=> $_icServer->encryption,
				'fm_ic_validatecertificate' 	=> (bool)$_icServer->validatecert,
				'fm_ic_enable_sieve' 		=> (bool)$_icServer->enableSieve,
				'fm_ic_sieve_server'		=> $_icServer->sieveHost,
				'fm_ic_sieve_port'		=> $_icServer->sievePort,
				'fm_og_hostname'		=> $_ogServer->host,
				'fm_og_port'			=> $_ogServer->port,
				'fm_og_smtpauth'		=> (bool)$_ogServer->smtpAuth,
				'fm_og_username'		=> $_ogServer->username,
				'fm_og_password'		=> $_ogServer->password,
			);
			$this->db->insert($this->accounts_table, $data, array(
				'fm_owner'			=> $_accountID,
			),__LINE__,__FILE__);	
		}
		
		function saveSignature($_accountID, $_signatureID, $_description, $_signature, $_isDefaultSignature) 
		{
			if($_isDefaultSignature == true) {
				$where = array(
					'fm_accountid'		=> $_accountID,
				);
				$data = array(
					'fm_defaultsignature'	=> false,
				);
				
				$this->db->update($this->signatures_table, $data, $where, __LINE__, __FILE__);
			}

			$data = array(
				'fm_accountid'		=> $_accountID,
				'fm_signature'		=> $_signature,
				'fm_description'	=> $_description,
				'fm_defaultsignature'	=> $_isDefaultSignature,
			);
			
			
			if($_signatureID == -1) {
				$this->db->insert($this->signatures_table, $data, '', __LINE__, __FILE__);
				
				return $this->db->get_last_insert_id($this->signatures_table,'fm_signatureid');
			} else {
				$where = array(
					'fm_accountid'		=> $_accountID,
					'fm_signatureid'	=> $_signatureID,
				);

				$this->db->update($this->signatures_table, $data, $where, __LINE__, __FILE__);
				
				return $_signatureID;
			}
		}

		function setProfileActive($_accountID, $_status)
		{
			$this->db->update($this->accounts_table,array(
				'fm_active'			=> $_status,
			),array(
				'fm_owner'			=> $_accountID,
			),__LINE__,__FILE__);	
		}
	}
?>
