<?php
/**************************************************************************\
* eGroupWare - Calendar's "new" SO-layer (storage-object)                  *
* http://www.egroupware.org                                                *
* Written and (c) 2004 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * Class to store all calendar data
 *
 * At the moment this class is used together with the "old" socalendar class.
 * As the calendar rewrite proceeds, the old class will be removed.
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only in server-time (please note, this is not the case with the socalendar*-classes used atm.)
 *
 * @package calendar
 * @author RalfBecker@outdoor-training.de
 * @license GPL
 */

class socal
{
	/**
	 * name of the main calendar table and prefix for all other calendar tables
	 */
	var $cal_table = 'phpgw_cal';
	var $extra_table,$holidays_table,$repeats_table,$user_table;
	
	/**
	 * internal copy of the global db-object
	 */
	var $db;
	/**
	 * Constructor of the socal class
	 */
	function socal()
	{
		foreach(array(
			//'old_so'    => 'calendar.socalendar',
			'async'    	=> 'phpgwapi.asyncservice',
		) as $my => $app_class)
		{
			list(,$class) = explode('.',$app_class);

			if (!is_object($GLOBALS['phpgw']->$class))
			{
				//echo "<p>calling CreateObject($app_class)</p>\n".str_repeat(' ',4096);
				$GLOBALS['phpgw']->$class = CreateObject($app_class);
			}
			$this->$my = &$GLOBALS['phpgw']->$class;
		}
		$this->db = $GLOBALS['phpgw']->db;
		$this->db->set_app('calendar');
		
		foreach(array('extra','holidays','repeats','user') as $name)
		{
			$vname = $name.'_table';
			$this->$vname = $this->cal_table.'_'.$name;
		}
	}
	
	/**
	 * reads one or more calendar entries
	 *
	 * All times (start, end and modified) are returned as timesstamps in servertime!
	 *
	 * @param $ids int/array id(s) of the entries to read
	 * @return array/boolean array with id => data pairs or false if entry not found
	 */
	function read($ids)
	{
		$this->db->select($this->cal_table,'*',array('cal_id'=>$ids),__LINE__,__FILE__);
		
		$events = false;
		$recur_ids = array();
		while (($row = $this->db->row(true,'cal_')))
		{
			if ($row['type'] == 'M') $recur_ids[] = $row['id'];
			unset($row['type']);

			$row['alarm'] = array();
			$events[$row['id']] = $row;
			
		}
		if (!$events) return false;
		
		// recur details
		if (count($recur_ids))
		{
			$this->db->select($this->repeats_table,'*',array('cal_id'=>$recur_ids),__LINE__,__FILE__);
			
			while(($row = $this->db->row(true)))
			{
				$row['recur_exception'] = $row['recur_exception'] ? explode(',',$row['recur_exception']) : array();
				$cal_id = $row['cal_id'];
				unset($row['cal_id']);
				$events[$cal_id] += $row;
			}
		}

		// participants
		$this->db->select($this->user_table,'*',array('cal_id'=>$ids),__LINE__,__FILE__);
		while (($row = $this->db->row(true)))
		{
			// if the type is not an ordinary user (eg. contact or resource) prefix the id with the type
			if ($row['cal_user_type'] && $row['cal_user_type'] != 'u')
			{
				$user_id = $row['cal_user_type'].$row['cal_user_id'];
			}
			else
			{
				$user_id = (int) $row['cal_user_id'];
			}
			$events[$row['cal_id']]['participants'][$user_id] = $row['cal_status'];
		}

		// custom fields
		$this->db->select($this->extra_table,'*',array('cal_id'=>$ids),__LINE__,__FILE__);
		while (($row = $this->db->row(true)))
		{
			$events[$row['cal_id']]['#'.$row['cal_extra_name']] = $row['cal_extra_value'];
		}
		
		// alarms, atm. we read all alarms in the system, as this can be done in a single query
		foreach((array)$this->async->read('cal'.(is_array($ids) ? '' : ':'.(int)$ids).':%') as $id => $job)
		{
			list(,$cal_id) = explode(':',$id);
			if (!isset($events[$cal_id])) continue;	// not needed
			
			$alarm         = $job['data'];	// text, enabled
			$alarm['id']   = $id;
			$alarm['time'] = $job['next'];

			$events[$cal_id]['alarm'][$id] = $alarm;
		}
		//echo "<p>socal::read(".print_r($ids,true).")=<pre>".print_r($events,true)."</pre>\n";
		return $events;
	}
}
