<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package timesheet
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Knut Moeller <k.moeller@metaways.de>
 * @copyright Knut Moeller <k.moeller@metaways.de>
 * @version $Id$
 */

/**
 * export plugin of addressbook
 */
class timesheet_export_csv implements importexport_iface_export_plugin {

	public function __construct()
	{
		$this->ui = new timesheet_ui();
		$this->get_selects();
	}

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$this->ui = new timesheet_ui();
		$selection = array();

		if($options['selection'] == 'search') {
			$query = $GLOBALS['egw']->session->appsession('index',TIMESHEET_APP);
			$query['num_rows'] = -1;	// all records
			$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			$this->ui->get_rows($query,$selection,$readonlys,true);	// true = only return the id's
		} elseif($options['selection'] == 'all') {
			$query = array(
				'num_rows' => -1,
				'csv_export' => true,	// so get_rows method _can_ produce different content or not store state in the session
			);
			$this->ui->get_rows($query,$selection,$readonlys,true);	// true = only return the id's
		}
		else if($options['selection'] == 'filter')
		{
			$fields = importexport_helper_functions::get_filter_fields($_definition->application, $this);
			$filter = $_definition->filter;
			$query = array(
				'num_rows' => -1,
				'csv_export' => true,	// so get_rows method _can_ produce different content or not store state in the session
				'col_filter' => array()
			);

			// Handle ranges
			foreach($filter as $field => $value)
			{
				if($field == 'cat_id')
				{
					$query['cat_id'] = $value;
					continue;
				}
				$query['col_filter'][$field] = $value;
				if(!is_array($value) || (!$value['from'] && !$value['to'])) continue;

				// Ranges are inclusive, so should be provided that way (from 2 to 10 includes 2 and 10)
				if($value['from']) $query['col_filter'][] = "$field >= " . (int)$value['from'];
				if($value['to']) $query['col_filter'][] = "$field <= " . (int)$value['to'];
				unset($query['col_filter'][$field]);
			}
			$this->ui->get_rows($query,$selection,$readonlys,true);	// true = only return the id's
		}


		$options['begin_with_fieldnames'] = true;
		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $identifier) {
			$record = new timesheet_egw_record($identifier);
			if($options['convert']) {
				importexport_export_csv::convert($record, timesheet_egw_record::$types, 'timesheet', $this->selects);
			} else {
				// Implode arrays, so they don't say 'Array'
				foreach($record->get_record_array() as $key => $value) {
					if(is_array($value)) $record->$key = implode(',', $value);
				}
 			}
			$export_object->export_record($record);
			unset($record);
		}
		return $export_object;
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Timesheet CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports entries from your Timesheet into a CSV File. ");
	}

	/**
	 * returns file suffix for exported file
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
	 * this way the plugin has all opportunities for options tab
	 *
	 * @return string html
	 */
	public function get_options_etpl() {
		return false;
	}

	/**
	 * returns slectors of this plugin via xajax
	 *
	 */
	public function get_selectors_etpl() {
		return array(
			'name'	=> 'importexport.export_csv_selectors',
		);
	}

	public function get_selects()
	{
		$this->selects = array(
			'ts_status'	=>	$this->ui->status_labels+array(lang('No status'))
		);
		foreach($this->selects['ts_status'] as &$status) {
			$status = str_replace('&nbsp;','',$status); // Remove &nbsp;
		}

	}
	/**
	 * Adjust the automatically generated filter fields
	 */
	public function get_filter_fields(Array &$filters)
	{
		foreach($filters as $field_name => &$settings)
		{
			if($this->selects[$field_name]) $settings['values'] = $this->selects[$field_name];
		}
	}

}
