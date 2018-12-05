<?php
/**
 * EGroupware: iCal export plugin of calendar
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;

/**
 * iCal export plugin of calendar
 */
class calendar_export_ical extends calendar_export_csv {

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;
		$this->bo = new calendar_bo();
		$boical = new calendar_ical();

		// Custom fields need to be specifically requested
		$cfs = array();

		$limit_exception = Api\Storage\Merge::is_export_limit_excepted();
		if (!$limit_exception) $export_limit = Api\Storage\Merge::getExportLimit('calendar');

		switch($options['selection'])
		{
			case 'criteria':
				$query = array(
					'start' => $options['criteria']['start'],
					'end'   => $options['criteria']['end'] ? strtotime('+1 day',$options['criteria']['end'])-1 : null,
					'categories'	=> $options['categories'],
					'daywise'       => false,
					'users'         => $options['criteria']['owner'],
					'cfs'		=> $cfs // Otherwise we shouldn't get any custom fields
				);
				if(Api\Storage\Merge::hasExportLimit($export_limit) && !$limit_exception) {
					$query['offset'] = 0;
					$query['num_rows'] = (int)$export_limit;  // ! int of 'no' is 0
				}
				$events =& $this->bo->search($query);
				break;

			case 'search_results':
				$states = $this->bo->cal_prefs['saved_states'];
				$query = Api\Cache::getSession('calendar', 'calendar_list');
				$query['num_rows'] = -1;        // all
				$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
				$query['start'] = 0;
				$query['cfs'] = $cfs;
				if(Api\Storage\Merge::hasExportLimit($export_limit) && !$limit_exception)
				{
					$query['num_rows'] = (int)$export_limit; // ! int of 'no' is 0
				}
				$ui = new calendar_uilist();
				if($states['view'] == 'listview')
				{
					$ui->get_rows($query, $events, $unused);
				}
				else
				{
					$query['filter'] = 'custom';
					$events = array();

					$ui->get_rows($query, $events, $unused);
				}
				// Filter out extra things like sel_options
				unset($events['sel_options']);
				break;
			case 'filter':
				$fields = importexport_helper_functions::get_filter_fields($_definition->application, $this);
				$filter = $_definition->filter;

				if(Api\Storage\Merge::hasExportLimit($export_limit) && !$limit_exception)
				{
					$query['num_rows'] = (int)$export_limit; // ! int of 'no' is 0
				}

				// Handle ranges
				foreach($filter as $field => $value)
				{
					if($field == 'filter' && $value)
					{
						$query['filter'] = $value;
						continue;
					}
					if(!is_array($value) || (!$value['from'] && !$value['to']))
					{
						$query['query']["cal_$field"] = $value;
						continue;
					}

					// Ranges are inclusive, so should be provided that way (from 2 to 10 includes 2 and 10)
					if($value['from']) $query['sql_filter'][] = "cal_$field >= " . (int)$value['from'];
					if($value['to']) $query['sql_filter'][] = "cal_$field <= " . (int)$value['to'];

				}
				if($query['sql_filter'] && is_array($query['sql_filter']))
				{
					// Set as an extra parameter
					$sql_filter = implode(' AND ',$query['sql_filter']);
				}
				// Fall through

			case 'all':
				$events = $this->bo->search($query + array(
					'offset' => 0,
					'order' => 'cal_start',
				),$sql_filter);
				break;
		}
		// compile list of unique cal_id's, as iCal should contain whole series, not recurrences
		// calendar_ical->exportVCal needs to read events again, to get them in server-time
		$ids = array();
		foreach($events as $event)
		{
			$id = is_array($event) ? $event['id'] : $event;
			if (($id = (int)$id)) $ids[$id] = $id;
		}

		$ical =& $boical->exportVCal($ids,'2.0','PUBLISH',false);
		fwrite($_stream, $ical);
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Calendar iCal export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports events from your Calendar in iCal format.");
	}

	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix() {
		return 'ics';
	}

	public static function get_mimetype() {
		return 'text/calendar';
	}

	/**
	 * Return array of settings for export dialog
	 *
	 * @param $definition Specific definition
	 *
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options	=> array,
	 * 		readonlys	=> array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl(importexport_definition &$definition = NULL)
	{
		return false;
	}

	/**
	 * returns selectors of this plugin
	 *
	 */
	public function get_selectors_etpl($definition = null) {
		$data = parent::get_selectors_etpl($definition);
		return $data;
	}
	/**
	 * Get the class name for the egw_record to use while exporting
	 *
	 * @return string;
	 */
	public static function get_egw_record_class()
	{
		return 'calendar_egw_record';
	}
}
