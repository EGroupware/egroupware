<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

/**
 * export CSV plugin of calendar
 */
class calendar_export_csv implements importexport_iface_export_plugin {

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;
		$this->bo = new calendar_bo();

		$limit_exception = bo_merge::is_export_limit_excepted();
		if (!$limit_exception) $export_limit = bo_merge::getExportLimit('calendar');
		// Custom fields need to be specifically requested
		$cfs = array();
		foreach($options['mapping'] as $key => $label) {
			if($key[0] == '#') $cfs[] = substr($key,1);
		}

		if($options['selection']['select'] == 'criteria') {
			$query = array(
				'start' => $options['selection']['start'],
				'end'   => $options['selection']['end'],
				'categories'	=> $options['categories'] ? $options['categories'] : $options['selection']['categories'],
				//'enum_recuring' => false, // we want the recurring events enumerated for csv export
				'daywise'       => false,
				'users'         => $options['selection']['owner'],
				'cfs'		=> $cfs // Otherwise we shouldn't get any custom fields
			);
			if(bo_merge::hasExportLimit($export_limit) && !$limit_exception) {
				$query['offset'] = 0;
				$query['num_rows'] = (int)$export_limit; // ! int of 'no' is 0
			}
			$events =& $this->bo->search($query);
		} elseif ($options['selection']['select'] == 'search_results') {
			$states = $GLOBALS['egw']->session->appsession('session_data','calendar');
			if($states['view'] == 'listview') {
				$query = $GLOBALS['egw']->session->appsession('calendar_list','calendar');
				$query['num_rows'] = -1;        // all
				$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
				$query['start'] = 0;
				$query['cfs'] = $cfs;

				if(bo_merge::hasExportLimit($export_limit) && !$limit_exception) {
					$query['num_rows'] = (int)$export_limit; // ! int of 'no' is 0
				}
				$ui = new calendar_uilist();
				$ui->get_rows($query, $events, $unused);
			} else {
				$query = $GLOBALS['egw']->session->appsession('session_data','calendar');
				$query['users'] = explode(',', $query['owner']);
				$query['num_rows'] = -1;
				if(bo_merge::hasExportLimit($export_limit) && !$limit_exception) {
					$query['num_rows'] = (int)$export_limit;  // ! int of 'no' is 0
				}

				$events = array();
				switch($states['view']) {
					case 'month':
						$query += $this->get_query_month($states);
						break;
					case 'week':
						$query += $this->get_query_week($states);
						break;
					case 'day':
						$query += $this->get_query_day($states);
						break;
					default:
						$ui = new calendar_uiviews($query);
						$query += array(
							'start' => is_array($ui->first) ? $this->bo->date2ts($ui->first) : $ui->first,
							'end' => is_array($ui->last) ? $this->bo->date2ts($ui->last) : $ui->last
						);

				}
				$boupdate = new calendar_boupdate();
				$events = $boupdate->search($query + array(
					'offset' => 0,
					'order' => 'cal_start',
				));
			}
		}

		$export_object = new importexport_export_csv($_stream, (array)$options);
		if (!$limit_exception) $export_object->export_limit = $export_limit;
		$export_object->set_mapping($options['mapping']);
		$convert_fields = calendar_egw_record::$types;

		$recurrence = $this->bo->recur_types;

		$lookups = array(
			'priority'	=> Array(
				0 => '',
				1 => lang('Low'),
				2 => lang('Normal'),
				3 => lang('High')
			),
		);
		$record = new calendar_egw_record();
		foreach ($events as $event) {
			// the condition below (2 lines) may only work on enum_recuring=false and using the iterator to test an recurring event on the given timerange
			// Get rid of yearly recurring events that don't belong
			//if($options['selection']['select'] == 'criteria' && ($event['start'] > $query['end'] || $event['end'] < $query['start'])) continue;
			// Add in participants
			if($options['mapping']['participants']) {
				$event['participants'] = implode(", ",$this->bo->participants($event,true));
			}
			if (is_array($event))
			{
				$record->set_record($event);
				if($options['mapping']['recurrence']) {
					$record->recurrence = $recurrence[$record->recur_type];
					if($record->recur_type != MCAL_RECUR_NONE) $record->recurrence .= ' / '. $record->recur_interval;
				}

				// Standard stuff
				if($options['convert']) {
					importexport_export_csv::convert($record, $convert_fields, 'calendar', $lookups);
				} else {
					// Implode arrays, so they don't say 'Array'
					foreach($record->get_record_array() as $key => $value) {
						if(is_array($value)) $record->$key = implode(',', $value);
					}
	 			}
				$export_object->export_record($record);
			}
		}
		unset($record);
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Calendar CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports events from your Calendar into a CSV File.");
	}

	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix() {
		return 'csv';
	}

	public static function get_mimetype() {
		return 'text/csv';
	}

	/**
	 * return html for options.
	 *
	 */
	public function get_options_etpl($definition = null) {
	}

	/**
	 * returns selectors of this plugin
	 *
	 */
	public function get_selectors_etpl($definition = null) {
		$states = $GLOBALS['egw']->session->appsession('session_data','calendar');
		$start= new egw_time($states['date']);
		if($states['view'] == 'week')
		{
			$days = isset($_GET['days']) ? $_GET['days'] : $GLOBALS['egw_info']['user']['preferences']['calendar']['days_in_weekview'];
			if ($days != 5) $days = 7;
			$end = "+$days days";
			$end = strtotime($end, $start->format('ts'))-1;
		}
		elseif ($states['view'] == 'listview')
		{
			$list = $GLOBALS['egw']->session->appsession('calendar_list','calendar');

			// Use UI to get dates
			$ui = new calendar_uilist();
			$list['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			$ui->get_rows($list);
			if($ui->first) $start = $ui->first;
			if($ui->last) $end = $ui->last;

			// Special handling
			if($list['filter'] == 'all') $start = $end = null;
			if($list['filter'] == 'before')
			{
				$end = $start;
				$start = null;
			}
			$ui = null;
		}
		else
		{
			$end = '+1 ' . $states['view'];
			$end = strtotime($end, $start->format('ts'))-1;
		}

		$prefs = unserialize($GLOBALS['egw_info']['user']['preferences']['importexport'][$definition->definition_id]);
		$data = array(
			'name'		=> 'calendar.export_csv_select',
			'content'	=> array(
				'plugin_override' => true, // Plugin overrides preferences
				'select'	=> $prefs['selection']['select'] ? $prefs['selection']['select'] : 'criteria',
				'start'		=> is_object($start) ? $start->format('ts') : $start,
				'end'		=> $end,
				'owner'		=> $states['owner']
			)
		);
		return $data;
	}

	/**
	 * Get additional query parameters used when in various views
	 * This stuff copied out of calendar_uiviews
	 */
	public static function get_query_month($states)
	{
		$timespan = array(
			'start' => mktime(0,0,0,$states['month'],1,$states['year']),
			'end' => mktime(0,0,0,$states['month']+1,1,$states['year'])-1
		);
		return $timespan;
	}

	public static function get_query_week($states)
	{
		$query = array();
		$days = $states['days'];
		$ui = new calendar_uiviews($states);
		if (!$days)
                {
                        $days = isset($_GET['days']) ? $_GET['days'] : $ui->cal_prefs['days_in_weekview'];
                        if ($days != 5) $days = 7;
                }
		if ($days == 4)         // next 4 days view
                {
                        $query['start'] = $this->bo->date2ts($states['date']);
                        $query['end'] = strtotime("+$days days",$query['start']) - 1;
                }
                else
                {
			$query['start'] = $ui->datetime->get_weekday_start($states['year'],$states['month'],$states['day']);
			if ($days == 5)         // no weekend-days
			{
				switch($ui->cal_prefs['weekdaystarts'])
				{
					case 'Saturday':
						$query['start'] = strtotime("+2 days",$query['start']);
						break;
					case 'Sunday':
						$query['start'] = strtotime("+1 day",$query['start']);
						break;
				}
			}
			$query['end'] = strtotime("+$days days",$query['start']) - 1;
		}
		return $query;
	}

	public static function get_query_day($states)
	{
		$query = array();
		$bo = new calendar_bo();
		$query['start'] = $bo->date2ts((string)$states['date']);
		$query['end'] = $query['start']+DAY_s-1;
		return $query;
	}
}
