<?php
	/***************************************************************************\
	* eGroupWare - content history class                                        *
	* http://www.linux-at-work.de                                               *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License.                *
	\***************************************************************************/
	/* $Id$ */

	/**
	* class to maintain history of content
	*
	* This class contains all logic of the egw content history.
	* @package phpgwapi
	* @author Lars Kneschke
	* @version 1.35
	* @copyright Lars Kneschke 2005
	* @license http://opensource.org/licenses/gpl-license.php GPL
	*/
	class contenthistory
	{
		/**
		 * @var db-object $this->db
		 */
		var $db;

		function contenthistory()
		{
			$this->db = clone($GLOBALS['egw']->db);
			$this->db->set_app('phpgwapi');
			$this->table = 'egw_api_content_history';
		}
		
		/**
		* get the timestamp for action
		*
		* find which content changed since $_ts for application $_appName
		*
		* @param $_appName string the appname example: infolog_notes
		* @param $_action string can be modify, add or delete
		* @param $_ts string timestamp where to start searching from 
		* @return array containing the global UID's
		*/
		function getHistory($_appName, $_action, $_ts)
		{
			switch($_action)
			{
				case 'modify':
					$query = "sync_modified > '".$this->db->to_timestamp($_ts)."' AND sync_modified > sync_deleted";
					break;
					
				case 'delete':
					$query = "sync_deleted > '".$this->db->to_timestamp($_ts)."'";
					break;
					
				case 'add':
					$query = "sync_added > '".$this->db->to_timestamp($_ts)."' AND sync_added > sync_deleted";
					break;
					
				default:
					// no valid $_action set
					return array();
			}
			
			$this->db->select($this->table,array(
				'sync_appname' => $_appName,
				$query,				
			), __LINE__, __FILE__);

			$guidList = array();
			while($this->db->next_record())
			{
				$guidList[] = $this->db->f('sync_guid');
			}
			return $guidList;
		}
		
		/**
		* when got a entry last added/modified/deleted
		*
		* @param $_guid string the global uid of the entry
		* @param $_action string can be add, delete or modify
		* @return string the last timestamp
		*/
		function getTSforAction($_guid, $_action)
		{
			$where = array (
				'sync_guid'		=> $_guid,
			);

			$this->db->select($this->table,array('sync_added','sync_modified','sync_deleted'),$where,__LINE__,__FILE__);
			if($this->db->next_record())
			{
				switch($_action)
				{
					case 'add':
						return $this->db->from_timestamp($this->db->f('sync_added'));

					case 'delete':
						return $this->db->from_timestamp($this->db->f('sync_deleted'));

					case 'modify':
						return $this->db->from_timestamp($this->db->f('sync_modified'));
				}
			}
			
			return false;
		}
		
		/**
		* update a timestamp for action
		*
		* @param $_appName string the appname example: infolog_notes
		* @param $_id int the app internal content id
		* @param $_action string can be modify, add or delete
		* @param $_ts string timestamp where to start searching from 
		* @return boolean returns allways true
		*/
		function updateTimeStamp($_appName, $_id, $_action, $_ts)
		{
			$_ts = $this->db->to_timestamp($_ts);

			$newData = array (
				'sync_appname'		=> $_appName,
				'sync_contentid'	=> $_id,
				'sync_added'		=> $_ts,
				'sync_guid'			=> $GLOBALS['egw']->common->generate_uid($_appName, $_id),
				'sync_changedby'	=> $GLOBALS['egw_info']['user']['account_id'],
			);
			switch($_action)
			{
				case 'add':
					$this->db->insert($this->table,$newData,array(),__LINE__,__FILE__);
					break;
					
				case 'modify':
				case 'delete':
					// first check that this entry got ever added to database already
					$where = array (
						'sync_appname'		=> $_appName,
						'sync_contentid'	=> $_id,
					);
					$this->db->select($this->table,'sync_contentid',$where,__LINE__,__FILE__);
					if(!$this->db->next_record())
					{
						$this->db->insert($this->table,$newData,array(),__LINE__,__FILE__);
					}
					
					// now update the time stamp
					$newData = array (
						'sync_changedby'	=> $GLOBALS['egw_info']['user']['account_id'],
						$_action == 'modify' ? 'sync_modified' : 'sync_deleted' => $_ts,
					);
					$this->db->update($this->table, $newData, $where,__LINE__,__FILE__);
					break;
			}
			
			return true;
		}
	}
