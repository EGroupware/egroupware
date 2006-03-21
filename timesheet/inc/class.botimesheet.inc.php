<?php
/**************************************************************************\
* eGroupWare - TimeSheet: business object                                  *
* http://www.eGroupWare.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* -------------------------------------------------------                  *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

if (!defined('TIMESHEET_APP'))
{
	define('TIMESHEET_APP','timesheet');
}

/**
 * Business object of the TimeSheet
 *
 * Uses eTemplate's so_sql as storage object (Table: egw_timesheet).
 *
 * @package timesheet
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class botimesheet extends so_sql
{
	/**
	 * @var array $config timesheets config data
	 */
	var $config = array();
	/**
	 * @var array $timestamps timestaps that need to be adjusted to user-time on reading or saving
	 */
	var $timestamps = array(
		'ts_start','ts_modified'
	);
	/**
	 * @var int $tz_offset_s offset in secconds between user and server-time,
	 *	it need to be add to a server-time to get the user-time or substracted from a user-time to get the server-time
	 */
	var $tz_offset_s;
	/**
	 * @var int $now actual user-time as timestamp
	 */
	var $now;
	/**
	 * @var int $today start of today in user-time
	 */
	var $today;
	/**
	 * @var array $date_filters filter for search limiting the date-range
	 */
	var $date_filters = array(	// Start: year,month,day,week, End: year,month,day,week
		'Today'       => array(0,0,0,0,  0,0,1,0),
		'Yesterday'   => array(0,0,-1,0, 0,0,0,0),
		'This week'   => array(0,0,0,0,  0,0,0,1),
		'Last week'   => array(0,0,0,-1, 0,0,0,0),
		'This month'  => array(0,0,0,0,  0,1,0,0),
		'Last month'  => array(0,-1,0,0, 0,0,0,0),
		'2 month ago' => array(0,-2,0,0, 0,-1,0,0),
		'This year'   => array(0,0,0,0,  1,0,0,0),
		'Last year'   => array(-1,0,0,0, 0,0,0,0),
		'2 years ago' => array(-2,0,0,0, -1,0,0,0),
		'3 years ago' => array(-3,0,0,0, -2,0,0,0),
	);
	/**
	 * @var object $link reference to the (bo)link class instanciated at $GLOBALS['egw']->link
	 */
	var $link;
	/**
	 * @var array $grants
	 */	
	var $grants;
	/**
	 * @var array $summary array sums of the last search in keys duration and price
	 */
	var $summary;

	function botimesheet()
	{
		$this->so_sql(TIMESHEET_APP,'egw_timesheet');

		$config =& CreateObject('phpgwapi.config',TIMESHEET_APP);
		$config->read_repository();
		$this->config =& $config->config_data;
		unset($config);

		if (!is_object($GLOBALS['egw']->datetime))
		{
			$GLOBALS['egw']->datetime =& CreateObject('phpgwapi.datetime');
		}
		$this->tz_offset_s = $GLOBALS['egw']->datetime->tz_offset;
		$this->now = time() + $this->tz_offset_s;	// time() is server-time and we need a user-time
		$this->today = mktime(0,0,0,date('m',$this->now),date('d',$this->now),date('Y',$this->now));

		// save us in $GLOBALS['botimesheet'] for ExecMethod used in hooks
		if (!is_object($GLOBALS['botimesheet']))
		{
			$GLOBALS['botimesheet'] =& $this;
		}
		// instanciation of link-class has to be after making us globaly availible, as it calls us to get the search_link
		if (!is_object($GLOBALS['egw']->link))
		{
			$GLOBALS['egw']->link =& CreateObject('phpgwapi.bolink');
		}
		$this->link =& $GLOBALS['egw']->link;
		
		$this->grants = $GLOBALS['egw']->acl->get_grants(TIMESHEET_APP);
	}
	
	/**
	 * get list of specified grants as uid => Username pairs
	 *
	 * @param int $required=EGW_ACL_READ
	 * @return array with uid => Username pairs
	 */
	function grant_list($required=EGW_ACL_READ)
	{
		$result = array();
		foreach($this->grants as $uid => $grant)
		{
			if ($grant & $required)
			{
				$result[$uid] = $GLOBALS['egw']->common->grab_owner_name($uid);
			}
		}
		natcasesort($result);

		return $result;
	}
	
	/**
	 * checks if the user has enough rights for a certain operation
	 *
	 * Rights are given via owner grants or role based acl
	 *
	 * @param int $required EGW_ACL_READ, EGW_ACL_WRITE, EGW_ACL_ADD, EGW_ACL_DELETE, EGW_ACL_BUDGET, EGW_ACL_EDIT_BUDGET
	 * @param array/int $data=null project or project-id to use, default the project in $this->data
	 * @return boolean true if the rights are ok, false if not
	 */
	function check_acl($required,$data=null)
	{
		if (!$data)
		{
			$data =& $this->data;
		}
		if (!is_array($data))
		{
			$save_data = $this->data;
			$data = $this->read($data,true);
			$this->data = $save_data;
		}
		$rights = $this->grants[$data['ts_owner']];
		
		return $data && !!($rights & $required);
	}
	
	function date_filter($name)
	{
		if (!isset($this->date_filters[$name]))
		{
			return false;
		}
		$year  = (int) date('Y',$this->today);
		$month = (int) date('m',$this->today);
		$day   = (int) date('d',$this->today);

		list($syear,$smonth,$sday,$sweek,$eyear,$emonth,$eday,$eweek) = $this->date_filters[$name];
		
		if ($syear || $eyear)
		{
			$start = mktime(0,0,0,1,1,$syear+$year);
			$end   = mktime(0,0,0,1,1,$eyear+$year);
		}
		elseif ($smonth || $emonth)
		{
			$start = mktime(0,0,0,$smonth+$month,1,$year);
			$end   = mktime(0,0,0,$emonth+$month,1,$year);
		}
		elseif ($sday || $eday)
		{
			$start = mktime(0,0,0,$month,$sday+$day,$year);
			$end   = mktime(0,0,0,$month,$eday+$day,$year);
		}
		elseif ($sweek || $eweek)
		{
			$wday = (int) date('w',$this->today); // 0=sun, ..., 6=sat
			switch($GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'])
			{
				case 'Sunday':
					$weekstart = $this->today - $wday * 24*60*60;
					break;
				case 'Saturday':
					$weekstart = $this->today - (6-$wday) * 24*60*60;
					break;
				case 'Moday':
				default:
					$weekstart = $this->today - ($wday ? $wday-1 : 6) * 24*60*60;
					break;
			}
			$start = $weekstart + $sweek*7*24*60*60;
			$end   = $weekstart + $eweek*7*24*60*60;
			// todo	
		}
		//echo "<p align='right'>date_filter($name) today=".date('l, Y-m-d H:i',$this->today)." ==> ".date('l, Y-m-d H:i:s',$start)." <= date < ".date('l, Y-m-d H:i:s',$end)."</p>\n"; 
		// convert start + end from user to servertime
		$start -= $this->tz_offset_s;
		$end   -= $this->tz_offset_s;

		return "($start <= ts_start AND ts_start < $end)";
	}

	/**
	 * search the timesheet
	 *
	 * reimplemented to limit result to users we have grants from
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @param boolean $only_summary=false If true only return the sums as array with keys duration and price, default false
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false,$only_summary=false)
	{
		if (!$extra_cols) $extra_cols = 'ts_quantity*ts_unitprice AS ts_total';

		if (!isset($filter['ts_owner']) || !count($filter['ts_owner']))
		{
			$filter['ts_owner'] = array_keys($this->grants);
		}
		else
		{
			if (!is_array($filter['ts_owner'])) $filter['ts_owner'] = array($filter['ts_owner']);
			
			foreach($filter['ts_owner'] as $key => $owner)
			{
				if (!isset($this->grants[$owner]))
				{
					unset($filter['ts_owner'][$key]);
				}
			}
		}
		if (!count($filter['ts_owner']))
		{
			$this->total = 0;
			$this->summary = array();
			return array();
		}
		$this->summary = parent::search($criteria,'SUM(ts_duration) AS duration,SUM(ts_quantity*ts_unitprice) AS price',
			'','',$wildcard,$empty,$op,false,$filter,$join);
		$this->summary = $this->summary[0];
		
		if ($only_summary) return $this->summary;

		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}

	/**
	 * read a timesheet entry
	 *
	 * @param int $ts_id
	 * @param boolean $ignore_acl=false should the acl be checked
	 * @return array/boolean array with timesheet entry or false if no rights
	 */
	function read($ts_id,$ignore_acl=false)
	{
		if (!(int)$ts_id || !$ignore_acl && !$this->check_acl(EGW_ACL_READ,$ts_id) ||
			$this->data['ts_id'] != (int)$ts_id && !parent::read((int)$ts_id))
		{
			return false;	// no read rights, or entry not found
		}
		return $this->data;
	}
	
	/**
	 * saves a timesheet entry
	 *
	 * reimplemented to notify the link-class
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param boolean $touch_modified=true should modification date+user be set, default yes
	 * @param boolean $ignore_acl=false should the acl be checked, returns true if no edit-rigts
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null,$touch_modified=true,$ignore_acl=false)
	{
		if ($keys) $this->data_merge($keys);
		
		if (!$ignore_acl && $this->data['ts_id'] && !$this->check_acl(EGW_ACL_EDIT))
		{
			return true;
		}
		if ($touch_modified)
		{
			$this->data['ts_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['ts_modified'] = $this->now;
		}
		if (!($err = parent::save()))
		{
			// notify the link-class about the update, as other apps may be subscribt to it
			$this->link->notify_update(TIMESHEET_APP,$this->data['ts_id'],$this->data);
		}
		return $err;
	}
	
	/**
	 * deletes a timesheet entry identified by $keys or the loaded one, reimplemented to notify the link class (unlink)
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @param boolean $ignore_acl=false should the acl be checked, returns false if no delete-rigts
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null,$ignore_acl=false)
	{
		if (!is_array($keys) && (int) $keys)
		{
			$keys = array('ts_id' => (int) $keys);
		}
		$ts_id = is_null($keys) ? $this->data['ts_id'] : $keys['ts_id'];
		
		if (!$this->check_acl(EGW_ACL_DELETE,$ts_id))
		{
			return false;
		}
		if (($ret = parent::delete($keys)) && $ts_id)
		{
			// delete all links to timesheet entry $ts_id
			$this->link->unlink(0,TIMESHEET_APP,$ts_id);
		}
		return $ret;
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * reimplemented to adjust the timezone of the timestamps (adding $this->tz_offset_s to get user-time)
	 * Please note, we do NOT call the method of the parent so_sql !!!
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array with changed data
	 */
	function db2data($data=null)
	{
		if (!is_array($data))
		{
			$data = &$this->data;
		}
		foreach($this->timestamps as $name)
		{
			if (isset($data[$name]) && $data[$name]) $data[$name] += $this->tz_offset_s;
		}
		return $data;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * reimplemented to adjust the timezone of the timestamps (subtraction $this->tz_offset_s to get server-time)
	 * Please note, we do NOT call the method of the parent so_sql !!!
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array with changed data
	 */
	function data2db($data=null)
	{
		if ($intern = !is_array($data))
		{
			$data = &$this->data;
		}
		foreach($this->timestamps as $name)
		{
			if (isset($data[$name]) && $data[$name]) $data[$name] -= $this->tz_offset_s;
		}
		return $data;
	}
	
	/**
	 * Get the time- and pricesum for the given timesheet entries
	 *
	 * @param array $ids array of timesheet id's
	 * @return array with keys time and price
	 */
	function sum($ids)
	{
		return $this->search(array('ts_id'=>$ids),true,'','','',false,'AND',false,null,'',false,true);
	}
	
	/**
	 * get title for an timesheet entry identified by $entry
	 * 
	 * Is called as hook to participate in the linking
	 *
	 * @param int/array $entry int ts_id or array with timesheet entry
	 * @param string the title
	 */
	function link_title( $entry )
	{
		if (!is_array($entry))
		{
			$entry = $this->read( $entry );
		}
		if (!$entry)
		{
			return False;
		}
		$format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
		if (date('H:i',$entry['ts_start']) != '00:00')	// dont show 00:00 time, as it means date only
		{
			$format .= ' '.($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i');
		}
		return date($format,$entry['ts_start']).': '.$entry['ts_title'];
	}

	/**
	 * query timesheet for entries matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @return array with ts_id - title pairs of the matching entries
	 */
	function link_query( $pattern )
	{
		$criteria = array();
		foreach(array('ts_project','ts_title','ts_description') as $col)
		{
			$criteria[$col] = $pattern;
		}
		$result = array();
		foreach((array) $this->search($criteria,false,'','','%',false,'OR') as $ts )
		{
			$result[$ts['ts_id']] = $this->link_title($ts);
		}
		return $result;
	}

	/**
	 * Hook called by link-class to include timesheet in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	function search_link($location)
	{
		return array(
			'query' => TIMESHEET_APP.'.botimesheet.link_query',
			'title' => TIMESHEET_APP.'.botimesheet.link_title',
			'view'  => array(
				'menuaction' => TIMESHEET_APP.'.uitimesheet.view',
			),
			'view_id' => 'ts_id',
			'view_popup'  => '600x400',			
			'add' => array(
				'menuaction' => TIMESHEET_APP.'.uitimesheet.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',		
			'add_popup'  => '600x400',			
		);
	}
}