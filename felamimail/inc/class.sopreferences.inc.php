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
		
		// allowed keywords for _identity are either the fm_id, all or active
		// an fm_id retrieves the account with the specified fm_id of the given user
		// all 		retrieves ALL Accounts of a given user
		// active 	retrieves all active accounts of a given user
		function getAccountData($_accountID, $_identity = NULL)
		{
			// no valid accountID
			if(($accountID = (int)$_accountID) < 1)
				return array();
				
			$retValue	= array();
			$where		= array('fm_owner' => $accountID);
			if (!empty($_identity) && $_identity != 'active' && $_identity != 'all') $where['fm_id'] = $_identity;
			if ($_identity == 'active' || empty($_identity)) $where['fm_active'] = true;
			$this->db->select($this->accounts_table,'fm_id,fm_active,fm_realname,fm_organization,fm_emailaddress,fm_signatureid,'.
				'fm_ic_hostname,fm_ic_port,fm_ic_username,fm_ic_password,fm_ic_encryption,fm_ic_validatecertificate,'.
				'fm_ic_enable_sieve,fm_ic_sieve_server,fm_ic_sieve_port,'.
				'fm_ic_folderstoshowinhome, fm_ic_trashfolder, fm_ic_sentfolder, fm_ic_draftfolder, fm_ic_templatefolder,'.
				'fm_og_hostname,fm_og_port,fm_og_smtpauth,fm_og_username,fm_og_password',
				$where,__LINE__,__FILE__);
				
			while(($row = $this->db->row(true,'fm_'))) {
				foreach(array('active','ic_validatecertificate','ic_enable_sieve','og_smtpauth','ic_folderstoshowinhome') as $name)
				{
					if ($name == 'ic_folderstoshowinhome') {
						$row[$name] = unserialize($row[$name]);
					} else {
						$row[$name] = $this->db->from_bool($row[$name]);
					}
				}
				$retValue[$row['id']] = $row;
			}
			return $retValue;
		}

		function saveAccountData($_accountID, $_icServer, $_ogServer, $_identity)
		{
			
			$data = array(
				'fm_active'			=> false,
				'fm_owner'			=> $_accountID,
				'fm_realname'			=> $_identity->realName,
				'fm_organization'		=> $_identity->organization,
				'fm_emailaddress'		=> $_identity->emailAddress,
				'fm_signatureid'		=> $_identity->signature,
			);
			if (is_object($_icServer)) {
				$data = array_merge($data,array(
					'fm_ic_hostname'		=> $_icServer->host,
					'fm_ic_port'			=> $_icServer->port,
					'fm_ic_username'		=> $_icServer->username,
					'fm_ic_password'		=> $_icServer->password,
					'fm_ic_encryption'		=> $_icServer->encryption,
					'fm_ic_validatecertificate' 	=> (bool)$_icServer->validatecert,
					'fm_ic_enable_sieve' 		=> (bool)$_icServer->enableSieve,
					'fm_ic_sieve_server'		=> $_icServer->sieveHost,
					'fm_ic_sieve_port'		=> $_icServer->sievePort,
					'fm_ic_folderstoshowinhome'	=> serialize($_icServer->folderstoshowinhome),
					'fm_ic_trashfolder'	=> $_icServer->trashfolder,
					'fm_ic_sentfolder'	=> $_icServer->sentfolder,
					'fm_ic_draftfolder'	=> $_icServer->draftfolder,
					'fm_ic_templatefolder'	=> $_icServer->templatefolder,
				));
			}
			if (is_object($_ogServer)) {
				$data = array_merge($data,array(
					'fm_og_hostname'		=> $_ogServer->host,
					'fm_og_port'			=> $_ogServer->port,
					'fm_og_smtpauth'		=> (bool)$_ogServer->smtpAuth,
					'fm_og_username'		=> $_ogServer->username,
					'fm_og_password'		=> $_ogServer->password,
				));
			}
			$where = array(
                'fm_owner'          => $_accountID,
            );
			#_debug_array($data);
			if (!empty($_identity->id)) $where['fm_id'] = $_identity->id;
			if ($_identity->id == 'new') 
			{
				$this->db->insert($this->accounts_table, $data, NULL,__LINE__,__FILE__);
				return  $this->db->get_last_insert_id($this->accounts_table, 'fm_id');
			} else {
				$this->db->update($this->accounts_table, $data, $where,__LINE__,__FILE__);	
				return $_identity->id;
			}	
		}
		
		function deleteAccountData($_accountID, $_identity)
		{
			$where = array(
				'fm_owner'          => $_accountID,
			);
			if (is_array($_identity) && count($_identity)>1) $where[] = "fm_id in (".implode(',',$_identity).")";
			if (is_array($_identity) && count($_identity)==1) $where['fm_id'] = $_identity[0];
			if (!empty($_identity->id) && !is_array($_identity)) $where['fm_id'] = $_identity->id;
			$this->db->delete($this->accounts_table, $where  ,__LINE__,__FILE__);
		}
	
		function setProfileActive($_accountID, $_status, $_identity)
		{
			$where = array(
                'fm_owner'          => $_accountID,
            );
			if (!empty($_identity)) $where['fm_id'] = $_identity;
			$this->db->update($this->accounts_table,array(
				'fm_active'			=> (bool)$_status,
			), $where,__LINE__,__FILE__);	
		}
	}
?>
