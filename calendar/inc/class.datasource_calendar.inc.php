<?php
/**************************************************************************\
* eGroupWare - ProjectManager - DataSource for InfoLog                     *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

/**
 * DataSource for the Calendar
 *
 * @package calendar
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class datasource_calendar extends datasource
{
	/**
	 * Constructor
	 */
	function datasource_calendar()
	{
		$this->datasource('calendar');
		
		$this->valid = PM_PLANNED_START|PM_PLANNED_END|PM_PLANNED_TIME|PM_RESOURCES;
	}
	
	/**
	 * get an entry from the underlaying app (if not given) and convert it into a datasource array
	 * 
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		// we use $GLOBALS['bocal'] as an already running instance is availible there
		if (!is_object($GLOBALS['bocal']))
		{
			include_once(EGW_INCLUDE_ROOT.'/calendar/inc/class.bocal.inc.php');
			$GLOBALS['bocal'] =& new bocal();
		}
		if (!is_array($data_id))
		{
			if (!(int) $data_id || !($data = $GLOBALS['bocal']->read((int) $data_id)))
			{
				return false;
			}
		}
		else
		{
			$data =& $data_id;
		}
		$ds = array(
			'pe_title' => $GLOBALS['bocal']->link_title($data),
			'pe_planned_start' => $GLOBALS['bocal']->date2ts($data['start']),
			'pe_planned_end'   => $GLOBALS['bocal']->date2ts($data['end']),
			'pe_resources'     => array(),
			'pe_details'       => $data['description'] ? nl2br($data['description']) : '',
		);
		$ds['pe_planned_time'] = (int) (($ds['pe_planned_end'] - $ds['pe_planned_start'])/60);	// time is in minutes

		foreach($data['participants'] as $uid => $status)
		{
			if ($status != 'R' && is_numeric($uid))	// only users for now
			{
				$ds['pe_resources'][] = $uid;
			}
		}
/*
		// ToDO: this does not change automatically after the event is over, 
		// maybe we need a flag for that in egw_pm_elements
		if ($data['end']['raw'] <= time()+$GLOBALS['egw']->datetime->tz_offset)
		{
			$ds['pe_used_time'] = $ds['pe_planned_time'];
		}
*/
		if ($this->debug)
		{
			echo "datasource_calendar($data_id) data="; _debug_array($data);
			echo "datasource="; _debug_array($ds);
		}
		return $ds;
	}
}
