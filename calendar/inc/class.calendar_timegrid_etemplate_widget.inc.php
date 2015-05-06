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