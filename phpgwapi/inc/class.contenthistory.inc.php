<?php
/**
 * eGW API - content history class
 * 
 * @link http://www.egroupware.org
 * @author Lars Kneschke [lkneschke@linux-at-work.de]
 * @copyright Lars Kneschke 2005
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @version $Id$
 */

/**
 * class to maintain history of content
 *
 * This class contains all logic of the egw content history.
 */
class contenthistory
{
	/**
	 * Name of the content-history table
	 */
	const TABLE = 'egw_api_content_history';
	/**
	 * @var egw_db
	 */
	private $db;

	function __construct()
	{
		$this->db = $GLOBALS['egw']->db;
	}
	
	/**
	 * mark mapping as expired
	 *
	 * mark a mapping from externel to internal id as expired, when
	 * a egw entry gets deleted
	 *
	 * @param string $_appName the appname example: infolog_notes
	 * @param int $_id the internal egwapp content id
	 * @return bool 
	 */
	function expireMapping($_appName, $_id)
	{
		return !!$this->db->update('egw_contentmap',array (
				'map_expired'		=> 1,
			),array (
				'map_guid'		=> $GLOBALS['egw']->common->generate_uid($_appName, $_id),
			),__LINE__,__FILE__);
	}

	/**
	 * get the timestamp for action
	 *
	 * find which content changed since $_ts for application $_appName
	 *
	 * @param string$_appName the appname example: infolog_notes
	 * @param string $_action can be modify, add or delete
	 * @param string $_ts timestamp where to start searching from 
	 * @return array containing contentIds with changes
	 */
	function getHistory($_appName, $_action, $_ts)
	{
		$where = array('sync_appname' => $_appName);
		
		switch($_action)
		{
			case 'modify':
				$where[] = "sync_modified > '".$this->db->to_timestamp($_ts)."' AND sync_deleted IS NULL";
				break;
			case 'delete':
				$where[] = "sync_deleted > '".$this->db->to_timestamp($_ts)."'";
				break;
			case 'add':
				$where[] = "sync_added > '".$this->db->to_timestamp($_ts)."' AND sync_deleted IS NULL AND sync_modified IS NULL";
				break;
			default:
				// no valid $_action set
				return array();
		}
		$idList = array();
		foreach($this->db->select(self::TABLE,'sync_contentid',$where,__LINE__,__FILE__) as $row)
		{
			$idList[] = $row['sync_contentid'];
		}
		
		return $idList;
	}
	
	/**
	 * when got a entry last added/modified/deleted
	 *
	 * @param $_guid string the global uid of the entry
	 * @param $_action string can be add, delete or modify
	 * @return string the last timestamp
	 */
	function getTSforAction($_appName, $_id, $_action)
	{
		switch($_action)
		{
			case 'add':
				$col = 'sync_added';
				break;
			case 'delete':
				$col = 'sync_deleted';
				break;
			case 'modify':
				$col = 'sync_modified';
				break;
			default:
				return false;
		}
		$where = array (
			'sync_appname' => $_appName,
			'sync_contentid' => $_id,
		);

		if (($ts = $this->db->select(self::TABLE,$col,$where,__LINE__,__FILE__)->fetchSingle()))
		{
			$ts = $this->db->from_timestamp($ts);
		}
		return $ts;
	}
	
	/**
	 * update a timestamp for action
	 *
	 * @param string $_appName the appname example: infolog_notes
	 * @param int $_id the app internal content id
	 * @param string $_action can be modify, add or delete
	 * @param string $_ts timestamp where to start searching from 
	 * @return boolean returns allways true
	 */
	function updateTimeStamp($_appName, $_id, $_action, $_ts)
	{
		$newData = array (
			'sync_appname'		=> $_appName,
			'sync_contentid'	=> $_id,
			'sync_added'		=> $this->db->to_timestamp($_ts),
			'sync_changedby'	=> $GLOBALS['egw_info']['user']['account_id'],
		);

		switch($_action)
		{
			case 'add':
				$this->db->insert(self::TABLE,$newData,array(),__LINE__,__FILE__);
				break;
				
			case 'modify':
			case 'delete':
				// first check that this entry got ever added to database already
				$where = array (
					'sync_appname'		=> $_appName,
					'sync_contentid'	=> $_id,
				);

				if (!$this->db->select(self::TABLE,'sync_contentid',$where,__LINE__,__FILE__)->fetchSingle())
				{
					$this->db->insert(self::TABLE,$newData,array(),__LINE__,__FILE__);
				}

				// now update the time stamp
				$newData = array (
					'sync_changedby'	=> $GLOBALS['egw_info']['user']['account_id'],
					$_action == 'modify' ? 'sync_modified' : 'sync_deleted' => $_ts ,
				);
				$this->db->update(self::TABLE, $newData, $where,__LINE__,__FILE__);
				break;
		}
		return true;
	}
}
