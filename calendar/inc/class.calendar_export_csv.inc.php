<?php
/**
 * EGroupware calendar export
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
 * export CSV plugin of calendar
 */
class calendar_export_csv implements importexport_iface_export_plugin {

	public function __construct() {
		Api\Translation::add_app('calendar');
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

		$events = $this->get_events($_definition, $options);

		$export_object = new importexport_export_csv($_stream, (array)$options);
		if (!$limit_exception)
		{
			$export_object->export_limit = $export_limit;
		}
		$export_object->set_mapping($options['mapping']);
		$convert_fields = calendar_egw_record::$types;

		$record = new calendar_egw_record();
		foreach ($events as $event)
		{
			// the condition below (2 lines) may only work on enum_recuring=false and using the iterator to test an recurring event on the given timerange
			// Get rid of yearly recurring events that don't belong
			//if($options['selection']['select'] == 'criteria' && ($event['start'] > $query['end'] || $event['end'] < $query['start'])) continue;
			// Add in participants
			if($options['mapping']['participants'])
			{
				if(is_array($event['participants']))
				{
					$event['participants'] = implode(", ",$this->bo->participants($event,true));
				}
				else
				{
					// Getting results from list already has participants formatted
					$event['participants'] = str_replace("\n", ' ', $event['participants']);
				}
			}
			if (is_array($event))
			{
				$record->set_record($event);
				if($options['mapping']['recurrence'])
				{
					$rrule = calendar_rrule::event2rrule($event);
					$record->recurrence = $rrule->__toString();
				}

				// Standard stuff
				if($options['convert'])
				{
					importexport_export_csv::convert($record, $convert_fields, 'calendar', $this->selects);
				}
				else
				{
					// Implode arrays, so they don't say 'Array'
					foreach($record->get_record_array() as $key => $value)
					{
						if(is_array($value)) $record->$key = implode(',', $value);
					}
	 			}
				$export_object->export_record($record);
			}
		}
		unset($record);
		return $export_object;
	}

	protected function get_events(importexport_definition $_definition, $options = array())
	{
		$limit_exception = Api\Storage\Merge::is_export_limit_excepted();
		if (!$limit_exception) $export_limit = Api\Storage\Merge::getExportLimit('calendar');

		// Custom fields need to be specifically requested
		$cfs = array();
		foreach((array)$options['mapping'] + (array)$_definition->filter as $key => $label) {
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
					'categories'	=> $options['categories'] ? $options['categories'] : $options['criteria']['categories'],
					//'enum_recuring' => false, // we want the recurring events enumerated for csv export
					'daywise'       => false,
					'users'         => $options['criteria']['owner'],
					'cfs'		=> $cfs // Otherwise we shouldn't get any custom fields
				);
				if($options['criteria']['start'])
				{
					$query['start'] = $options['criteria']['start'];
				}
				if($options['criteria']['end'])
				{
					$query['end'] = strtotime('+1 day',$options['criteria']['end'])-1;
				}
				if(Api\Storage\Merge::hasExportLimit($export_limit) && !$limit_exception) {
					$query['offset'] = 0;
					$query['num_rows'] = (int)$export_limit; // ! int of 'no' is 0
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

				// Handle ranges
				foreach($filter as $field => $value)
				{
					if($field == 'status_filter' && $value)
					{
						$query['filter'] = $value;
						continue;
					}
					else if($field == 'users' && $value)
					{
						// No cal_ prefix here
						$query['users'] = $value;
					}
					else if(!is_array($value) || (!$value['from'] && !$value['to']))
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
		return $events;
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name()
	{
		return lang('Calendar CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description()
	{
		return lang("Exports events from your Calendar into a CSV File.");
	}

	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix()
	{
		return 'csv';
	}

	public static function get_mimetype()
	{
		return 'text/csv';
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
	public function get_selectors_etpl($definition = null)
	{
		$states = $this->bo->cal_prefs['saved_states'];
		$list = Api\Cache::getSession('calendar', 'calendar_list');

		$start= new Api\DateTime($list['startdate']);
		$end = new Api\DateTime($list['enddate']);

		if ($states['view'] == 'listview')
		{
			$list = Api\Cache::getSession('calendar', 'calendar_list');

			// Use UI to get dates
			$ui = new calendar_uilist();
			$list['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			$ui->get_rows($list,$rows,$readonlys);
			$start = $ui->first ? $ui->first : new Api\DateTime($ui->date);
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
		else if(!$end)
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
		$this->selects['status_filter'] = array(
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


		$states = $this->bo->cal_prefs['saved_states'];
	}

	/**
	 * Adjust automatically generated field filters
	 */
	public function get_filter_fields(Array &$filters)
	{

		// Calendar SO doesn't support filtering by column, so we have to remove pretty much everything
		unset($filters['recur_date']);

		// Add in the  participant & status filters at the beginning
		$filters = array_reverse($filters, true);
		$filters['status_filter'] = array(
			'type'	=> 'select',
			'name'	=> 'status_filter',
			'label'	=> lang('Filter'),
			'multiple' => false
		);
		$filters['users'] = array(
			'name' => 'users',
			'label' => lang('Participant'),
			'type' => 'calendar-owner',
		) +  $filters['owner'];
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
