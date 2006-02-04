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
 * DataSource for InfoLog
 *
 * The InfoLog datasource set's only real start- and endtimes, plus planned and used time and 
 * the responsible user as resources (not always the owner too!).
 * The read method of the extended datasource class sets the planned start- and endtime:
 *  - planned start from the end of a start constrain
 *  - planned end from the planned time and a start-time
 *  - planned start and end from the "real" values
 *
 * @package infolog
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class datasource_infolog extends datasource
{
	/**
	 * Constructor
	 */
	function datasource_infolog()
	{
		$this->datasource('infolog');
		
		$this->valid = PM_COMPLETION|PM_REAL_START|PM_REAL_END|PM_PLANNED_TIME|PM_USED_TIME|PM_RESOURCES;
	}
	
	/**
	 * get an entry from the underlaying app (if not given) and convert it into a datasource array
	 * 
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		// we use $GLOBALS['boinfolog'] as an already running instance might be availible there
		if (!is_object($GLOBALS['boinfolog']))
		{
			include_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.boinfolog.inc.php');
			$GLOBALS['boinfolog'] =& new boinfolog();
		}
		if (!is_array($data_id))
		{
			$data =& $GLOBALS['boinfolog']->read((int) $data_id);
			
			if (!is_array($data)) return false;
		}
		else
		{
			$data =& $data_id;
		}
		return array(
			'pe_title'        => $GLOBALS['boinfolog']->link_title($data),
			'pe_completion'   => $this->status2completion($data['info_status']).'%',
			'pe_real_start'   => $data['info_startdate'] ? $data['info_startdate'] : null,
			'pe_real_end'     => $data['info_enddate'] ? $data['info_enddate'] : null,
			'pe_planned_time' => $data['info_planned_time'],
			'pe_used_time'    => $data['info_used_time'],
			'pe_resources'    => count($data['info_responsible']) ? $data['info_responsible'] : array($data['info_owner']),
			'pe_details'      => $data['info_des'] ? nl2br($data['info_des']) : '',
			'pl_id'           => $data['pl_id'],
			'pe_unitprice'    => $data['info_price'],
			'pe_planned_quantity' => $data['info_planned_time'] / 60,
			'pe_planned_budget'   => $data['info_planned_time'] / 60 * $data['info_price'],
			'pe_used_quantity'    => $data['info_used_time'] / 60,
			'pe_used_budget'      => $data['info_used_time'] / 60 * $data['info_price'],
		);
	}
	
	/**
	 * converts InfoLog status into a percentage completion
	 *
	 * percentages are just used, done&billed give 100, ongoing&will-call give 50, rest (incl. all custome status) give 0
	 *
	 * @param string $status
	 * @return int completion in percent
	 */
	function status2completion($status)
	{
		if ((int) $status || substr($status,-1) == '%') return (int) $status;	// allready a percentage
		
		switch ($status)
		{
			case 'done':
			case 'billed':
				return 100;
				
			case 'will-call':
				return 50;

			case 'ongoing':
				return 10;
		}
		return 0;
	}
	
	/**
	 * Copy the datasource of a projectelement (InfoLog entry) and re-link it with project $target
	 *
	 * @param array $element source project element representing an InfoLog entry, $element['pe_app_id'] = info_id
	 * @param int $target target project id
	 * @param array $target_data=null data of target-project, atm not used by the infolog datasource
	 * @return array/boolean array(info_id,link_id) on success, false otherwise
	 */
	function copy($element,$target,$extra=null)
	{
		if (!is_object($GLOBALS['boinfolog']))
		{
			include_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.boinfolog.inc.php');
			$GLOBALS['boinfolog'] =& new boinfolog();
		}
		$info =& $GLOBALS['boinfolog']->read((int) $element['pe_app_id']);
		
		if (!is_array($info)) return false;
		
		// unsetting info_link_id and evtl. info_from
		if ($info['info_link_id'])
		{
			$GLOBALS['boinfolog']->link_id2from($info);		// unsets info_from and sets info_link_target
			unset($info['info_link_id']);
		}
		// we need to unset a view fields, to get a new entry
		foreach(array('info_id','info_owner','info_modified','info_modifierer') as $key)
		{
			unset($info[$key]);
		}
		if(!($info['info_id'] = $GLOBALS['boinfolog']->write($info))) return false;
		
		// link the new infolog against the project and setting info_link_id and evtl. info_from
		$info['info_link_id'] = $GLOBALS['boinfolog']->link->link('projectmanager',$target,'infolog',$info['info_id'],$element['pe_remark'],0,0,1);
		if (!$info['info_from'])
		{
			$info['info_from'] = $GLOBALS['boinfolog']->link->title('projectmanager',$target);
		}
		$GLOBALS['boinfolog']->write($info);
		
		// creating again all links, beside the one to the source-project
		foreach($GLOBALS['boinfolog']->link->get_links('infolog',$element['pe_app_id']) as $link)
		{
			if ($link['app'] == 'projectmanager' && $link['id'] == $element['pm_id'] ||		// ignoring the source project
				$link['app'] == $GLOBALS['boinfolog']->link->vfs_appname)					// ignoring files attachments for now
			{
				continue;
			}
			$GLOBALS['boinfolog']->link->link('infolog',$info['info_id'],$link['app'],$link['id'],$link['remark']);
		}
		return array($info['info_id'],$info['info_link_id']);
	}
}