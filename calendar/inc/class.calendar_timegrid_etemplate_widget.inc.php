<?php
/**
  * Egroupware
  * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
  * @link http://www.egroupware.org
  * @author Nathan Gray
  * @version $Id$
  */

use EGroupware\Api;
use EGroupware\Api\Etemplate;

 /**
  * Creates a grid with rows for the time, columns for (multiple) days containing events
  *
  * The associated javascript files are loaded by calendar/js/app.js using the
  * server-side include manager to get all the dependancies
  *
  * @author Nathan Gray
  */
 class calendar_timegrid_etemplate_widget extends Etemplate\Widget
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
		if(!is_array($value)) $value = array();

		foreach($value as &$events)
		{
			if(!is_array($events))
			{
				continue;
			}
			foreach($events as &$event)
			{
				if(!is_array($event)) continue;
				foreach(array('start','end') as $date)
				{
					$event[$date] = Api\DateTime::to($event[$date],'Y-m-d\TH:i:s\Z');
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
		Api\Json\Response::get()->data($holidays);
	}
 }