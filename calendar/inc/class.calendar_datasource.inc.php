<?php
/**
 * DataSource for the Calendar
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright (c) 2005-8 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
			if (categories::is_global($cat_id))
			{
				$ds['cat_id'] = $cat_id;
				break;
			}
		}
		// calculation of the time
		$ds['pe_planned_time'] = (int) (($ds['pe_planned_end'] - $ds['pe_planned_start'])/60);	// time is in minutes

		// if the event spans multiple days, we have to substract the nights (24h - daily working time specified in PM)
		if (date('Y-m-d',$ds['pe_planned_end']) != date('Y-m-d',$ds['pe_planned_start']))
		{
			foreach(array('start','end') as $name)
			{
				$$name = $cal->date2array($ds['pe_planned_'.$name]);
				${$name}['hour'] = 12;
				${$name}['minute'] = ${$name}['second'] = 0;
				unset(${$name}['raw']);
				$$name = $cal->date2ts($$name);
			}
			$nights = round(($end - $start) / DAY_s);

			if (!is_array($this->pm_config))
			{
				$c =& CreateObject('phpgwapi.config','projectmanager');
				$c->read_repository();
				$this->pm_config = $c->config_data;
				unset($c);
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
