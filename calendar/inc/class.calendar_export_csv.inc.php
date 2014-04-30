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

	public function __construct() {
		translation::add_app('calendar');
		$this->bo = new calendar_bo();
		$this->get_selects();
	}

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$limit_exception = bo_merge::is_export_limit_excepted();
		if (!$limit_exception) $export_limit = bo_merge::getExportLimit('calendar');
		// Custom fields need to be specifically requested
		$cfs = array();
		foreach($options['mapping'] + (array)$_definition->filter as $key => $label) {
			if($key[0] == '#') $cfs[] = substr($key,1);
		}

		$query = array(
			'cfs'		=> $cfs, // Otherwise we shouldn't get any custom fields
			'num_rows'	=> -1,
			'csv_export'	=> true
		);
		switch($options['selection'])
		{
			case 'criteria':
				$query = array(
					'start' => $options['criteria']['start'],
					'end'   => strtotime('+1 day',$options['criteria']['end'])-1,
					'categories'	=> $options['categories'] ? $options['categories'] : $options['criteria']['categories'],
					//'enum_recuring' => false, // we want the recurring events enumerated for csv export
					'daywise'       => false,
					'users'         => $options['criteria']['owner'],
					'cfs'		=> $cfs // Otherwise we shouldn't get any custom fields
				);
				if(bo_merge::hasExportLimit($export_limit) && !$limit_exception) {
					$query['offset'] = 0;
					$query['num_rows'] = (int)$export_limit; // ! int of 'no' is 0
				}
				$events =& $this->bo->search($query);
				break;
			case 'search_results':
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
						case 'weekN':
							$query += $this->get_query_week($states);
							break;
						case 'day':
							$query += $this->get_query_day($states);
							break;
						default:
							// Let UI set the date ranges
							$ui = new calendar_uiviews($query);
							if(method_exists($ui, $states['view']))
							{
								ob_start();
								$ui->$states['view']();
								ob_end_clean();
							}
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
				break;
			case 'filter':
				$fields = importexport_helper_functions::get_filter_fields($_definition->application, $this);
				$filter = $_definition->filter;

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

			case 'all':
				$events = $this->bo->search($query + array(
					'offset' => 0,
					'order' => 'cal_start',
				),$sql_filter);
				break;
		}

		$export_object = new importexport_export_csv($_stream, (array)$options);
		if (!$limit_exception) $export_object->export_limit = $export_limit;
		$export_object->set_mapping($options['mapping']);
		$convert_fields = calendar_egw_record::$types;

		$recurrence = $this->bo->recur_types;

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
					importexport_export_csv::convert($record, $convert_fields, 'calendar', $this->selects);
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
		return $export_object;
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
		switch($states['view']) {
			case 'month':
				$query = $this->get_query_month($states);
				break;
			case 'week':
			case 'weekN':
				$query = $this->get_query_week($states);
				break;
			case 'day':
				$query = $this->get_query_day($states);
				break;
		}
		$start= new egw_time($query['start']);
		$end = new egw_time($query['end']);
		if ($states['view'] == 'listview')
		{
			$list = $GLOBALS['egw']->session->appsession('calendar_list','calendar');

			// Use UI to get dates
			$ui = new calendar_uilist();
			$list['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			$ui->get_rows($list,$rows,$readonlys);
			$start = $ui->first ? $ui->first : new egw_time($ui->date);
			$end = $ui->last;

			// Special handling
			if($list['filter'] == 'all') $start = $end = null;
			if($list['filter'] == 'before')
			{
				$end = $start;
				$start = null;
			}
			$ui = null;
		}
		elseif(!$end)
		{
			$end = '+1 ' . $states['view'];
			$end = strtotime($end, $start->format('ts'))-1;
		}
		$prefs = unserialize($GLOBALS['egw_info']['user']['preferences']['importexport'][$definition->definition_id]);
		$data = array(
			'name'		=> 'calendar.export_csv_select',
			'content'	=> array(
				'plugin_override' => true, // Plugin overrides preferences
				'selection'	=> $prefs['selection'] ? $prefs['selection'] : 'criteria',
				'criteria'	=> array(
					'start'		=> is_object($start) ? $start->format('ts') : $start,
					'end'		=> is_object($end) ? $end->format('ts') : $end,
					'owner'		=> $states['owner']
				)
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
		if ($states['view'] == 'week' && $days == 4)         // next 4 days view
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
			$query['end'] = strtotime($states['view'] == 'week' ? "+$days days" : "+{$ui->cal_prefs['multiple_weeks']} weeks",$query['start']) - 1;
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

	/**
	 * Get select options for use in filter
	 */
	protected function get_selects()
	{
		$this->selects['priority'] = Array(
			0 => lang('None'),
			1 => lang('Low'),
			2 => lang('Normal'),
			3 => lang('High')
		);
		$this->selects['filter'] = array(
			'default'     => lang('Not rejected'),
			'accepted'    => lang('Accepted'),
			'unknown'     => lang('Invitations'),
			'tentative'   => lang('Tentative'),
			'delegated'   => lang('Delegated'),
			'rejected'    => lang('Rejected'),
			'owner'       => lang('Owner too'),
			'all'         => lang('All incl. rejected'),
			'hideprivate' => lang('Hide private infos'),
			'showonlypublic' =>  lang('Hide private events'),
			'no-enum-groups' => lang('only group-events'),
			'not-unknown' => lang('No meeting requests'),
		);
	}

	/**
	 * Adjust automatically generated field filters
	 */
	public function get_filter_fields(Array &$filters)
	{

		// Calendar SO doesn't support filtering by column, so we have to remove pretty much everything
		unset($filters['recur_date']);

		// Add in the status filter at the beginning
		$filters = array_reverse($filters, true);
		$filters['filter'] = array(
			'type'	=> 'select',
			'name'	=> 'filter',
			'label'	=> lang('Filter'),
		);
		$filters = array_reverse($filters, true);

		foreach($filters as $field_name => &$settings)
		{
			// Can't filter on a custom field
			if(strpos($field_name, '#') === 0)
			{
				unset($filters[$field_name]);
				continue;
			}

			// Pass on select options
			if($this->selects[$field_name]) $settings['values'] = $this->selects[$field_name];
		}

	}
}
