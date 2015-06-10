<?php

 /*
  * Egroupware
  * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
  * @link http://www.egroupware.org
  * @author Nathan Gray
  * @version $Id$
  */

 /**
  * Creates a grid with rows for the time, columns for (multiple) days containing events
  *
  * The associated javascript files are loaded by calendar/js/app.js using the
  * server-side include manager to get all the dependancies
  *
  * @author Nathan Gray
  */
 class calendar_timegrid_etemplate_widget extends etemplate_widget
 {

	 /**
	 * Set up what we know on the server side.
	 *
	 * Sending a first chunk of rows
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& self::get_array(self::$request->content, $form_name, true);

		error_log(__METHOD__ . "($cname,".array2string($expand));
		error_log(array2string($value));

		foreach($value as $day => &$events)
		{
			foreach($events as &$event)
			{
				if(!is_array($event)) continue;
				foreach(array('start','end') as $date)
				{
					$event[$date] = egw_time::to($event[$date],'Y-m-d\TH:i:s\Z');
				}
			}
		}
	}

	/**
	 * Ajax callback to fetch the holidays for a given year.
	 * @param type $year
	 */
	public static function ajax_get_holidays($year)
	{
		$cal_bo = new calendar_bo();
		$holidays = $cal_bo->read_holidays((int)$year);
		egw_json_response::get()->data($holidays);
	}
 }