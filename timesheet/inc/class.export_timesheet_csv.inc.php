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
 * @version $Id: $
 */

require_once(EGW_INCLUDE_ROOT. '/etemplate/inc/class.etemplate.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.export_csv.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.iface_export_plugin.inc.php');
require_once(EGW_INCLUDE_ROOT. '/timesheet/inc/class.egw_timesheet_record.inc.php');
require_once(EGW_INCLUDE_ROOT. '/timesheet/inc/class.uitimesheet.inc.php');

/**
 * export plugin of addressbook
 */
class export_timesheet_csv implements iface_export_plugin {

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public static function export( $_stream, $_charset, definition $_definition) {
		$options = $_definition->options;

		$uitimesheet = new uitimesheet();
		$selection = array();

		$query = $GLOBALS['egw']->session->appsession('index',TIMESHEET_APP);
		$query['num_rows'] = -1;	// all

		$uitimesheet->get_rows($query,$selection,$readonlys,true);	// true = only return the id's

		$options['begin_with_fieldnames'] = true;
		$export_object = new export_csv($_stream, $charset, (array)$options);

		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $identifier) {
			$timesheetentry = new egw_timesheet_record($identifier);
			$export_object->export_record($timesheetentry);
			unset($timesheetentry);
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
		return lang("Exports entries from your Timesheet into a CSV File. CSV means 'Comma Seperated Values'. However in the options Tab you can also choose other seperators.");
	}

	/**
	 * returns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix() {
		return 'csv';
	}

	/**
	 * return html for options.
	 * this way the plugin has all opportunities for options tab
	 *
	 * @return string html
	 */
	public static function get_options_etpl() {
		return 'timesheet.export_csv_options';
	}

	/**
	 * returns slectors of this plugin via xajax
	 *
	 */
	public static function get_selectors_etpl() {
		return '<b>Selectors:</b>';
	}
}