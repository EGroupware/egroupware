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
		$config = config::read('phpgwapi');

		// Custom fields need to be specifically requested
		$cfs = array();
		foreach($options['mapping'] as $key => $label) {
			if($key[0] == '#') $cfs[] = substr($key,1);
		}

		if($options['select'] == 'criteria') {
			$query = array(
				'start' => $options['selection']['start'],
				'end'   => $options['selection']['end'],
				'categories'	=> $options['categories'] ? $options['categories'] : $options['selection']['categories'],
				'enum_recuring' => false,
				'daywise'       => false,
				'users'         => $options['selection']['owner'],
				'cfs'		=> $cfs // Otherwise we shouldn't get any custom fields
			);
			if($config['export_limit']) {
				$query['offset'] = 0;
				$query['num_rows'] = (int)$config['export_limit'];
			}
			$events =& $this->bo->search($query);
		} elseif ($options['select'] = 'search_results') {
			$query = $GLOBALS['egw']->session->appsession('calendar_list','calendar');
			$query['num_rows'] = -1;        // all
			$query['start'] = 0;
			$query['cfs'] = $cfs;

			if($config['export_limit']) {
				$query['num_rows'] = (int)$config['export_limit'];
			}
			$ui = new calendar_uilist();
			$ui->get_rows($query, $events);
		}

		$export_object = new importexport_export_csv($_stream, (array)$options);
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
			// Get rid of yearly recurring events that don't belong
			if($options['selection']['select'] == 'criteria' && ($event['start'] > $query['end'] || $event['end'] < $query['start'])) continue;

			// Add in participants
			if($options['mapping']['participants']) {
				$event['participants'] = implode(", ",$this->bo->participants($event,true));
			}

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
	public function get_options_etpl() {
	}

	/**
	 * returns selectors of this plugin
	 *
	 */
	public function get_selectors_etpl() {
		$states = $GLOBALS['egw']->session->appsession('session_data','calendar');
		
		$start= new egw_time($states['date']);
		if($states['view'] == 'week') {
			$days = isset($_GET['days']) ? $_GET['days'] : $GLOBALS['egw_info']['user']['preferences']['calendar']['days_in_weekview'];
			if ($days != 5) $days = 7;
			$end = "+$days days";
		} else {
			$end = '+1 ' . $states['view'];
		}

		return array(
			'name'		=> 'calendar.export_csv_select',
			'content'	=> array(
				'plugin_override' => true, // Plugin overrides preferences
				'start'		=> $start->format('ts'),
				'end'		=> strtotime($end, $start->format('ts'))-1,
				'owner'		=> $states['owner']
			)
		);
	}
}
