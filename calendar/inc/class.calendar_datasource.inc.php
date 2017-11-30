<?php
/**
 * DataSource for the Calendar
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005-16 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Acl;

include_once(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.datasource.inc.php');

/**
 * DataSource for the Calendar
 */
class calendar_datasource extends datasource
{
	/**
	 * Constructor
	 */
	function __construct()
	{
		if (false) parent::__construct();	// can not be called, but gives IDE warning

		$this->datasource('calendar');

		$this->valid = PM_PLANNED_START|PM_PLANNED_END|PM_PLANNED_TIME|PM_RESOURCES|PM_CAT_ID;
	}

	/**
	 * get an entry from the underlaying app (if not given) and convert it into a datasource array
	 *
	 * @param mixed $data_id id as used in the link-class for that app, or complete entry as array
	 * @return array/boolean array with the data supported by that source or false on error (eg. not found, not availible)
	 */
	function get($data_id)
	{
		// we use $cal as an already running instance is availible there
		if (!is_object($GLOBALS['calendar_bo']))
		{
			$GLOBALS['calendar_bo'] = new calendar_bo();
		}
		$cal = $GLOBALS['calendar_bo'];

		if (!is_array($data_id))
		{
			if (!(int) $data_id || !($data = $cal->read((int) $data_id)))
			{
				return false;
			}
		}
		else
		{
			$data =& $data_id;
		}
		$ds = array(
			'pe_title' => $cal->link_title($data),
			'pe_planned_start' => $cal->date2ts($data['start']),
			'pe_planned_end'   => $cal->date2ts($data['end']),
			'pe_resources'     => array(),
			'pe_details'       => $data['description'] ? nl2br($data['description']) : '',
		);
		// return first global category, as PM only supports one
		foreach($data['category'] ? explode(',', $data['category']) : array() as $cat_id)
		{
			if (Api\Categories::is_global($cat_id))
			{
				$ds['cat_id'] = $cat_id;
				break;
			}
		}
		// calculation of the time
		$ds['pe_planned_time'] = (int) (($ds['pe_planned_end'] - $ds['pe_planned_start'])/60);	// time is in minutes

		// if the event spans multiple days, we have to substract the nights (24h - daily working time specified in PM)
		if (($ds['pe_planned_time']/ 60 > 24) && date('Y-m-d',$ds['pe_planned_end']) != date('Y-m-d',$ds['pe_planned_start']))
		{
			$start = $end = null;
			foreach(array('start','end') as $name)
			{
				$arr = $cal->date2array($ds['pe_planned_'.$name]);
				$arr['hour'] = 12;
				$arr['minute'] = ${$name}['second'] = 0;
				unset($arr['raw']);
				$$name = $cal->date2ts($arr);
			}
			$nights = round(($end - $start) / DAY_s);

			if (!is_array($this->pm_config))
			{
				$this->pm_config = Api\Config::read('projectmanager');
				if (!$this->pm_config['hours_per_workday']) $this->pm_config['hours_per_workday'] = 8;
			}
			$ds['pe_planned_time'] -= $nights * 60 * (24 - $this->pm_config['hours_per_workday']);
		}
		foreach($data['participants'] as $uid => $status)
		{
			if ($status != 'R' && is_numeric($uid))	// only users for now
			{
				$ds['pe_resources'][] = $uid;
			}
		}
		// if we have multiple participants we have to multiply the time by the number of participants to get the total time
		$ds['pe_planned_time'] *= count($ds['pe_resources']);

		if($data['deleted'])
		{
			$ds['pe_status'] = 'deleted';
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

	/**
	 * Delete the datasource of a project element
	 *
	 * @param int $id
	 * @return boolean true on success, false on error
	 */
	function delete($id)
	{
		// dont delete entries which are linked to elements other than their project
		if (count(Link::get_links('calendar',$id)) > 1)
		{
			return false;
		}
		$bo = new calendar_boupdate();
		return $bo->delete($id);
	}

	/**
	 * Change the status of an entry according to the project status
	 *
	 * @param int $id
	 * @param string $status
	 * @return boolean true if status changed, false otherwise
	 */
	function change_status($id,$status)
	{
		$bo = new calendar_boupdate();
		if (($entry = $bo->read($id)) && (
				$bo->check_perms(Acl::EDIT,$entry)
		))
		{
			// Restore from deleted
			if ($status == 'active' && $entry['deleted'])
			{
				$entry['deleted'] = null;
				return (boolean)$bo->update($entry, true);
			}
		}
		return false;
	}
}
