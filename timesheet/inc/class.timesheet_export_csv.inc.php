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

	// Used in conversions
	static $types = array(
		'select-account' => array('ts_owner','ts_modifier'),
		'date-time' => array('ts_start', 'ts_modified'),
		'select-cat' => array('cat_id'),
		'links' => array('pl_id'),
		'select' => array('ts_status'),
	);

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$uitimesheet = new timesheet_ui();
		$selection = array();

		if($options['selection'] == 'selected') {
			$query = $GLOBALS['egw']->session->appsession('index',TIMESHEET_APP);
			$query['num_rows'] = -1;	// all records
			$uitimesheet->get_rows($query,$selection,$readonlys,true);	// true = only return the id's
		} elseif($options['selection'] == 'all') {
			$query = array('num_rows' => -1);
			$uitimesheet->get_rows($query,$selection,$readonlys,true);	// true = only return the id's
		} 


		$options['begin_with_fieldnames'] = true;
		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		$lookups = array(
			'ts_status'	=>	$uitimesheet->status_labels+array(lang('No status'))
		);
		foreach($lookups['ts_status'] as &$status) {
			$status = str_replace('&nbsp;','',$status); // Remove &nbsp;
		}

		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $identifier) {
			$record = new timesheet_egw_record($identifier);
			if($options['convert']) {
				importexport_export_csv::convert($record, self::$types, 'timesheet', $lookups);
			} else {
				// Implode arrays, so they don't say 'Array'
				foreach($record->get_record_array() as $key => $value) {
					if(is_array($value)) $record->$key = implode(',', $value);
				}
 			}
			$export_object->export_record($record);
			unset($record);
		}
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
			'name'	=> 'timesheet.export_csv_selectors',
			'content' => 'selected'
		);
	}
}
