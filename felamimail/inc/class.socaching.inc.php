<?php
	/***************************************************************************\
	* phpGroupWare - FeLaMiMail                                                 *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.phpgroupware.org                                               *
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
		var $cache_table = 'phpgw_felamimail_cache';
		var $folder_table = 'phpgw_felamimail_folderstatus';
		var $hostname,$accountname,$foldername,$accountid,$host_account_folder;	// set by the constructor
		
		function socaching($_hostname, $_accountname, $_foldername, $_accountid)
		{
			$this->hostname		= $_hostname;
			$this->accountname	= $_accountname;
			$this->foldername	= $_foldername;
			$this->accountid	= $_accountid;
			
			$this->host_account_folder = array(
				'accountid'	=> $this->accountid,
				'hostname'	=> $this->hostname,
				'foldername'	=> $this->foldername,
				'accountname'	=> $this->accountname,
			);
			
			$this->db = $GLOBALS['phpgw']->db;
			$this->db->set_app('felamimail');
		}
		
		function addToCache($_data)
		{
			// we need to truncate the to_address field, as it can be easyly longer then the
			// allowed size of atm. 120 chars, DB's other then mysql, give an SQL error
			if (!$this->to_address_size)
			{
				$table_def = $this->db->get_table_definitions('',$this->cache_table);
				$this->to_address_size = $table_def['fd']['to_address']['precision'];
				unset($table_def);
			}
			$this->db->insert($this->cache_table,array_merge($this->host_account_folder,array(
				'uid'				=> $_data['uid'],
				'date'			=> $_data['date'],
				'subject'			=> $_data['subject'],
				'sender_name'		=> $_data['sender_name'],
				'sender_address'	=> $_data['sender_address'],
				'to_name'			=> $_data['to_name'],
				'to_address'		=> substr($_data['to_address'],0,$this->to_address_size),
				'size'			=> $_data['size'],
				'attachments'		=> $_data['attachments'],
			)),False,__LINE__,__FILE__);	
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
							$filter .= "(sender_name LIKE $value OR sender_address LIKE $value) ";
							break;
						case 'to':
							$filter .= "(to_name LIKE $value OR to_address LIKE $value) ";
							break;
						case 'subject':
							$filter .= "subject LIKE $value ";
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
				
			$this->db->select($this->cache_table,'uid,date,subject,sender_name,sender_address,to_name,to_address,size,attachments',
				$where,__LINE__,__FILE__,$_firstMessage,$this->getSortSQL($_sort),False,$_numberOfMessages);
				
			while($this->db->next_record())
			{
				$retValue[] = array(
					'uid'			=> $this->db->f('uid'),
					'sender_name'	=> $this->db->f('sender_name'), 
					'sender_address'=> $this->db->f('sender_address'), 
					'to_name'		=> $this->db->f('to_name'), 
					'to_address'	=> $this->db->f('to_address'),
					'attachments'	=> $this->db->f('attachments'),
					'date'			=> $this->db->f('date')
				);
			}
			return $retValue;
		}
		
		/**
		 * get folder status
		 * @return array/int array with the currently cached infos or 0 if nothing cached for this folder so far
		 */
		function getImapStatus()
		{
			$this->db->select($this->folder_table,'messages,recent,unseen,uidnext,uidvalidity',
				$this->host_account_folder,__LINE__,__FILE__);

			if ($this->db->next_record())
			{
				return array(
					'messages'		=> $this->db->f('messages'),
					'recent'		=> $this->db->f('recent'),
					'unseen'		=> $this->db->f('unseen'),
					'uidnext'		=> $this->db->f('uidnext'),
					'uidvalidity'	=> $this->db->f('uidvalidity')
				);
			}
			return 0;
		}
		
		/**
		 * Numbers of messages in cache currently, by using the given filter
		 * @param array $filter see getFilterSQL
		 * @return int 
		 */
		function getMessageCounter($_filter)
		{
			$where = $this->host_account_folder;
			$filter = $this->getFilterSQL($_filter);
			if ($filter) $where[] = $filter;
			
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
				
			$this->db->select($this->cache_table,'uid',
				$where,__LINE__,__FILE__,FALSE,$this->getSortSQL($_sort));

			while($this->db->next_record())
			{
				// we found the current message
				if($this->db->f('uid') == $_uid)
				{
					// jump to the next messages
					if($this->db->next_record())
					{
						$retValue['next'] = $this->db->f('uid');
					}
					// we are done
					if($retValue) return $retValue;
					
					// we should never get here
					return false;
				}
				else
				{
					// we found (maybe!) the previous message
					$retValue['previous'] = $this->db->f('uid');
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
					$sort = "ORDER BY date DESC";
					break;
				case "1":
					$sort = "ORDER BY date ASC";
					break;
				case "2":
					$sort = "ORDER BY sender_address DESC";
					break;
				case "3":
					$sort = "ORDER BY sender_address ASC";
					break;
				case "4":
					$sort = "ORDER BY subject DESC";
					break;
				case "5":
					$sort = "ORDER BY subject ASC";
					break;
				case "6":
					$sort = "ORDER BY size DESC";
					break;
				case "7":
					$sort = "ORDER BY size ASC";
					break;
				default:
					$sort = "ORDER BY date DESC";
			}
			return $sort;
		}
		
		function removeFromCache($_uid)
		{
			$this->db->delete($this->cache_table,array_merge($this->host_account_folder,array(
					'uid'			=> $_uid,
				)),__LINE__,__FILE__);
		}
		
		function updateImapStatus($_status, $firstUpdate)
		{
			$this->db->insert($this->folder_table,array(
					'messages'	=> $_status->messages,
					'recent'		=> $_status->recent,
					'unseen'		=> $_status->unseen,
					'uidnext'		=> $_status->uidnext,
					'uidvalidity'	=> $_status->uidvalidity,
				),$this->host_account_folder,__LINE__,__FILE__);
		}
	}
?>