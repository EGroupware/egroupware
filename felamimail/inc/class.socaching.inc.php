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

	class socaching
	{
		var $public_functions = array
		(
			'addAtachment'	=> True,
			'action'	=> True
		);
		var $cache_table = 'egw_felamimail_cache';
		var $folder_table = 'egw_felamimail_folderstatus';
		var $hostname,$accountname,$foldername,$accountid,$host_account_folder;	// set by the constructor
		
		function socaching($_hostname, $_accountname, $_foldername, $_accountid)
		{
			$this->hostname		= $_hostname;
			$this->accountname	= $_accountname;
			$this->foldername	= $_foldername;
			$this->accountid	= $_accountid;
			
			$this->host_account_folder = array(
				'fmail_accountid'	=> $this->accountid,
				'fmail_hostname'	=> $this->hostname,
				'fmail_foldername'	=> $this->foldername,
				'fmail_accountname'	=> $this->accountname,
			);
			
			$this->db = clone($GLOBALS['egw']->db);
			$this->db->set_app('felamimail');
		}
		
		function addToCache($_data)
		{
			// we need to truncate the to_address field, as it can be easyly longer then the
			// allowed size of atm. 120 chars, DB's other then mysql, give an SQL error
			if (!$this->to_address_size)
			{
				$table_def = $this->db->get_table_definitions('',$this->cache_table);
				$this->to_address_size = $table_def['fd']['fmail_to_address']['precision'];
				unset($table_def);
			}
			$this->db->insert($this->cache_table,array(
				'fmail_date'			=> $_data['date'],
				'fmail_subject'			=> $_data['subject'],
				'fmail_sender_name'		=> $_data['sender_name'],
				'fmail_sender_address'		=> $_data['sender_address'],
				'fmail_to_name'			=> $_data['to_name'],
				'fmail_to_address'		=> substr($_data['to_address'],0,$this->to_address_size),
				'fmail_size'			=> $_data['size'],
				'fmail_attachments'		=> $_data['attachments'],
			),array_merge($this->host_account_folder,array(
				'fmail_uid'				=> $_data['uid'],
			)),__LINE__,__FILE__);	
		}
		
		function clearCache($_folderName='')
		{
			$where = $this->host_account_folder;
			if(!empty($_folderName)) 
				$where['fmail_foldername'] = $_folderName;
			
			$this->db->delete($this->cache_table,$where,__LINE__, __FILE__);
		}
		
		/**
		 * create sql from the filter array
		 *
		 * @param array $_filter values/searchpattern for 'from', 'to' or 'subject'
		 * @return string SQL to be AND'ed into a query
		 */
		function getFilterSQL($_filter)
		{
			$filter = '';

			if(is_array($_filter))
			{
				foreach($_filter as $key => $value)
				{
					$value = $this->db->quote('%'.$value.'%');

					if($filter != '') $filter .= 'OR ';

					switch($key)
					{
						case 'from':
							$filter .= "(fmail_sender_name LIKE $value OR fmail_sender_address LIKE $value) ";
							break;
						case 'to':
							$filter .= "(fmail_to_name LIKE $value OR fmail_to_address LIKE $value) ";
							break;
						case 'subject':
							$filter .= "fmail_subject LIKE $value ";
							break;
					}
				}
				if($filter != '') $filter = "($filter) ";
			}
			return $filter;
		}
		
		function getHeaders($_firstMessage='', $_numberOfMessages='', $_sort='', $_filter='')
		{
			$where = $this->host_account_folder;
			$filter = $this->getFilterSQL($_filter);
			if ($filter) $where[] = $filter;
			$foundSomething = false;
				
			$this->db->select($this->cache_table,'fmail_uid,fmail_date,fmail_subject,fmail_sender_name,fmail_sender_address,fmail_to_name,fmail_to_address,fmail_size,fmail_attachments',
				$where,__LINE__,__FILE__,$_firstMessage?$_firstMessage-1:False,$this->getSortSQL($_sort),False,$_numberOfMessages);
				
			while($this->db->next_record()) {
				$foundSomething = true;
				$retValue[$this->db->f('fmail_uid')] = array(
					'uid'				=> $this->db->f('fmail_uid'),
					'sender_name'	=> $this->db->f('fmail_sender_name'), 
					'subject'			=> $this->db->f('fmail_subject'), 
					'size'			=> $this->db->f('fmail_size'), 
					'sender_address'	=> $this->db->f('fmail_sender_address'), 
					'to_name'		=> $this->db->f('fmail_to_name'), 
					'to_address'		=> $this->db->f('fmail_to_address'),
					'attachments'		=> $this->db->f('fmail_attachments'),
					'date'			=> $this->db->f('fmail_date')
				);
			}
			
			if($foundSomething) {
				return $retValue;
			}

			return false;
		}
		
		/**
		 * get folder status
		 * @return array/int array with the currently cached infos or 0 if nothing cached for this folder so far
		 */
		function getImapStatus()
		{
			$this->db->select($this->folder_table,'fmail_messages,fmail_recent,fmail_unseen,fmail_uidnext,fmail_uidvalidity',
				$this->host_account_folder,__LINE__,__FILE__);

			if ($this->db->next_record())
			{
				return array(
					'messages'	=> $this->db->f('fmail_messages'),
					'recent'	=> $this->db->f('fmail_recent'),
					'unseen'	=> $this->db->f('fmail_unseen'),
					'uidnext'	=> $this->db->f('fmail_uidnext'),
					'uidvalidity'	=> $this->db->f('fmail_uidvalidity')
				);
			}
			return false;
		}
		
		/**
		 * Numbers of messages in cache currently, by using the given filter
		 * @param array $filter see getFilterSQL
		 * @return int 
		 */
		function getMessageCounter($_filter)
		{
			$where = $this->host_account_folder;
			if($_filter)
			{
				$filter = $this->getFilterSQL($_filter);
				if(!empty($filter)) {
					$where[] = $filter;
				}
			}
			
			$this->db->select($this->cache_table,'count(*)',$where,__LINE__,__FILE__);
			
			return $this->db->next_record() ? $this->db->f(0) : 0;
		}
		
		/**
		 * get the next message
		 */
		function getNextMessage($_uid, $_sort='', $_filter='')
		{
			$where = $this->host_account_folder;
			$filter = $this->getFilterSQL($_filter);
			if ($filter) $where[] = $filter;
				
			$this->db->select($this->cache_table,'fmail_uid',
				$where,__LINE__,__FILE__,FALSE,$this->getSortSQL($_sort));

			while($this->db->next_record())
			{
				// we found the current message
				if($this->db->f('fmail_uid') == $_uid)
				{
					// jump to the next messages
					if($this->db->next_record())
					{
						$retValue['next'] = $this->db->f('fmail_uid');
					}
					// we are done
					if($retValue) return $retValue;
					
					// we should never get here
					return false;
				}
				else
				{
					// we found (maybe!) the previous message
					$retValue['previous'] = $this->db->f('fmail_uid');
				}
			}
			
			// we should never get here
			return false;
		}
		
		function getSortSQL($_sort)
		{
			switch($_sort)
			{
				case "0":
					$sort = "ORDER BY fmail_date DESC";
					break;
				case "1":
					$sort = "ORDER BY fmail_date ASC";
					break;
				case "2":
					$sort = "ORDER BY fmail_sender_address DESC";
					break;
				case "3":
					$sort = "ORDER BY fmail_sender_address ASC";
					break;
				case "4":
					$sort = "ORDER BY fmail_subject DESC";
					break;
				case "5":
					$sort = "ORDER BY fmail_subject ASC";
					break;
				case "6":
					$sort = "ORDER BY fmail_size DESC";
					break;
				case "7":
					$sort = "ORDER BY fmail_size ASC";
					break;
				default:
					$sort = "ORDER BY fmail_date DESC";
			}
			return $sort;
		}
		
		function removeFromCache($_uid)
		{
			$this->db->delete($this->cache_table,array_merge($this->host_account_folder,array(
					'fmail_uid'			=> $_uid,
				)),__LINE__,__FILE__);
		}
		
		function updateImapStatus($_status)
		{
			$data = array(
					'fmail_messages'	=> $_status->messages,
					'fmail_recent'		=> $_status->recent,
					'fmail_unseen'		=> $_status->unseen,
					'fmail_uidnext'		=> $_status->uidnext,
					'fmail_uidvalidity'	=> $_status->uidvalidity,
				);
			$this->db->insert($this->folder_table,$data,$this->host_account_folder,__LINE__,__FILE__);
		}
	}
?>
