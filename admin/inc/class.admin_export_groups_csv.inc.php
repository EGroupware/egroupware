<?php
/**
 * eGroupWare - Export plugin for groups
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

/**
 * Export users plugin
 */
class admin_export_groups_csv implements importexport_iface_export_plugin {

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$selection = array();

		$query = array(
			'type'	=>	'groups',
		);
		$selection = $GLOBALS['egw']->accounts->search($query);

		$options['begin_with_fieldnames'] = true;
		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		$lookups = array(
			'account_status'	=> array('A' => lang('Enabled'), '' => lang('Disabled'), 'D' => lang('Disabled')),
		);

		// $_record is an array, that's what search() returns
		foreach ($selection as $_record) {
			$record = new admin_egw_group_record($_record);
			if($options['convert']) {
				importexport_export_csv::convert($record, admin_egw_group_record::$types, 'admin', $lookups);
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
		return lang('Group CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports groups into a CSV File. ");
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
			'preserv' => array('no_error_for_no_selection'),
		);
	}
}
