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
	/* $Id: class.felamimail_signatures.inc.php,v 1.21 2005/11/04 18:37:37 ralfbecker Exp $ */

	class felamimail_signatures
	{
		var $tableName = 'egw_felamimail_signatures';
		
		var $fm_signatureid = NULL;
		
		var $fm_description = NULL;
		
		var $fm_signature = NULL;
		
		var $fm_defaultsignature = NULL;
		
		function felamimail_signatures($_signatureID = NULL) {
			$this->accountID = $GLOBALS['egw_info']['user']['account_id'];
			
			if($_signatureID !== NULL) {
				$this->read($_signatureID);
			}
		}
		
		function getDefaultSignature() {
			$db = clone($GLOBALS['egw']->db);
			$db->set_app('felamimail');

			$where = array(
				'fm_accountid'		=> $this->accountID,
				'fm_defaultsignature'	=> true
			);
			
			$db->select($this->tableName,'fm_signatureid,fm_description,fm_signature,fm_defaultsignature',
				$where, __LINE__, __FILE__);
				
			if(($row = $db->row(true))) {
				return $row['fm_signatureid'];
			}

			return false;
		}

		function read($_signatureID) {
			$db = clone($GLOBALS['egw']->db);
			$db->set_app('felamimail');
			
			$where = array(
				'fm_accountid'		=> $this->accountID,
				'fm_signatureid'	=> $_signatureID
			);
			
			$db->select($this->tableName,'fm_signatureid,fm_description,fm_signature,fm_defaultsignature',
				$where, __LINE__, __FILE__);
				
			if(($data = $db->row(true))) {
				$this->fm_signatureid	= $data['fm_signatureid'];
				$this->fm_description	= $data['fm_description'];
				$this->fm_signature	= $data['fm_signature'];
				$this->fm_defaultsignature = (bool)$data['fm_defaultsignature'];

				return TRUE;
			}

			return FALSE;
		}

		function delete($_signatureID = FALSE) {
			$db = clone($GLOBALS['egw']->db);
			$db->set_app('felamimail');

			if($_signatureID !== FALSE) {
				$signatureID = (int)$_signatureID;
			} else {
				$signatureID = (int)$this->fm_signatureid;
			}
			
			$where = array(
				'fm_accountid'		=> $this->accountID,
				'fm_signatureid'	=> $signatureID
			);
			
			$db->delete($this->tableName, $where, __LINE__, __FILE__);

			if ($db->affected_rows() === 0) {
				return false;
			}
			
			return true;
		}

		function save() {
			$db = clone($GLOBALS['egw']->db);
			$db->set_app('felamimail');

			// reset fm_defaultsignature in all other rows to false
			if($this->fm_defaultsignature === true) {
				$where = array(
					'fm_accountid'		=> $this->accountID,
				);
				$data = array(
					'fm_defaultsignature'	=> false,
				);
				
				$db->update($this->tableName, $data, $where, __LINE__, __FILE__);
			}

			$data = array(
				'fm_accountid'		=> $this->accountID,
				'fm_signature'		=> $this->fm_signature,
				'fm_description'	=> $this->fm_description,
				'fm_defaultsignature'	=> $this->fm_defaultsignature,
			);
			
			
			if($this->fm_signatureid === NULL) {
				$db->insert($this->tableName, $data, '', __LINE__, __FILE__);
				
				$this->fm_signatureid = $db->get_last_insert_id($this->tableName,'fm_signatureid');

				return TRUE;
			} else {
				$where = array(
					'fm_accountid'		=> $this->accountID,
					'fm_signatureid'	=> $this->fm_signatureid,
				);
				$db->update($this->tableName, $data, $where, __LINE__, __FILE__);
				
				return TRUE;
			}
		}
		
		function search() {
			$signatures = array();
			
			$db = clone($GLOBALS['egw']->db);
			$db->set_app('felamimail');
			
			$where = array(
				'fm_accountid'		=> $this->accountID
			);
			
			$db->select($this->tableName,'fm_signatureid,fm_description,fm_signature,fm_defaultsignature',
				$where, __LINE__, __FILE__);

			while ($data = $db->row(true)) {
				$signatureData = array(
					'fm_signatureid'	=> $data['fm_signatureid'],
					'fm_description'	=> $data['fm_description'],
					'fm_signature'		=> $data['fm_signature'],
					'fm_defaultsignature'	=> (bool)$data['fm_defaultsignature'],
				);
				$signatures[$data['fm_signatureid']] = $signatureData;
			}

			return $signatures;
		}
	}
?>
