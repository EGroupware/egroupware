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
		#function contenthistory()
		#{
		#}
		
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
			copyobj($GLOBALS['phpgw']->db,$db);
			
			$query = "select sync_guid from egw_api_content_history where sync_appname = '".$db->db_addslashes($_appName)."' and ";
			
			switch($_action)
			{
				case 'modify':
					$query .= "sync_modified > '".$db->to_timestamp($_ts)."' and sync_modified > sync_deleted";
					break;
				case 'delete':
					$query .= "sync_deleted > '".$db->to_timestamp($_ts)."'";
					break;
				case 'add':
					$query .= "sync_added > '".$db->to_timestamp($_ts)."' and sync_added > sync_deleted";
					break;
				default:
					// no valid $_action set
					return array();
					break;
			}
			
			$db->query($query, __LINE__, __FILE__);

			if($db->num_rows() > 0)
			{
				while($db->next_record())
				{
					$guidList[] = $db->f('sync_guid');
				}
				
				return $guidList;
			}
			else
			{
				return array();
			}
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
			copyobj($GLOBALS['phpgw']->db,$db);
			
			$where = array (
				'sync_guid'		=> $_guid,
			);

			$db->select('egw_api_content_history',array('sync_added','sync_modified','sync_deleted'),$where,__LINE__,__FILE__);
			if($db->num_rows() > 0)
			{
				$db->next_record();
				switch($_action)
				{
					case 'add':
						return $db->from_timestamp($db->f('sync_added'));
						break;
					case 'delete':
						return $db->from_timestamp($db->f('sync_deleted'));
						break;
					case 'modify':
						return $db->from_timestamp($db->f('sync_modified'));
						break;
					default:
						return false;
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
			copyobj($GLOBALS['phpgw']->db,$db);

			$_ts = $db->to_timestamp($_ts);

			switch($_action)
			{
				case 'add':
					$newData = array (
						'sync_appname'		=> $_appName,
						'sync_contentid'	=> $_id,
						'sync_added'		=> $_ts,
						'sync_guid'		=> $GLOBALS['phpgw']->common->generate_uid($_appName, $_id),
						'sync_changedby'	=> $GLOBALS['phpgw_info']['user']['account_id'],
					);
					$db->insert('egw_api_content_history',$newData,array(),__LINE__,__FILE__);
					break;
				case 'modify':
					$where = array (
						'sync_appname'		=> $_appName,
						'sync_contentid'	=> $_id,
						
					);

					// first check that this entry got ever added to database already
					$db->select('egw_api_content_history','sync_contentid',$where,__LINE__,__FILE__);
					if($db->num_rows() == 0)
					{
						$newData = array (
							'sync_appname'		=> $_appName,
							'sync_contentid'	=> $_id,
							'sync_added'		=> $_ts,
							'sync_guid'		=> $GLOBALS['phpgw']->common->generate_uid($_appName, $_id),
						);
						$db->insert('egw_api_content_history',$newData,array(),__LINE__,__FILE__);
					}
					
					// now update the time stamp
					$newData = array (
						'sync_appname'		=> $_appName,
						'sync_contentid'	=> $_id,
						'sync_modified'		=> $_ts,
						'sync_changedby'	=> $GLOBALS['phpgw_info']['user']['account_id'],
					);
					$db->update('egw_api_content_history', $newData, $where,__LINE__,__FILE__);
					break;
				case 'delete':
					$where = array (
						'sync_appname'		=> $_appName,
						'sync_contentid'	=> $_id,
						
					);

					// first check that this entry got ever added to database already
					$db->select('egw_api_content_history','sync_contentid',$where,__LINE__,__FILE__);
					if($db->num_rows() == 0)
					{
						$newData = array (
							'sync_appname'		=> $_appName,
							'sync_contentid'	=> $_id,
							'sync_added'		=> $_ts,
							'sync_guid'		=> $GLOBALS['phpgw']->common->generate_uid($_appName, $_id),
						);
						$db->insert('egw_api_content_history',$newData,array(),__LINE__,__FILE__);
					}
					
					// now update the time stamp
					$newData = array (
						'sync_appname'		=> $_appName,
						'sync_contentid'	=> $_id,
						'sync_deleted'		=> $_ts,
						'sync_changedby'	=> $GLOBALS['phpgw_info']['user']['account_id'],
					);
					$db->update('egw_api_content_history', $newData, $where,__LINE__,__FILE__);
					break;
			}
			
			return true;
		}
	}
?>
