<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id: $
 */

require_once(EGW_INCLUDE_ROOT. '/etemplate/inc/class.etemplate.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.export_csv.inc.php');
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.iface_export_plugin.inc.php');
require_once(EGW_INCLUDE_ROOT. '/addressbook/importexport/class.egw_addressbook_record.inc.php');
require_once(EGW_INCLUDE_ROOT. '/addressbook/inc/class.uicontacts.inc.php');

/**
 * export plugin of addressbook
 */
class export_contacts_csv implements iface_export_plugin {
	
	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, definition $_definition) {
		$options = $_definition->plugin_options;
		
		$uicontacts = new uicontacts();
		$selection = array();
		if ($options['selection'] == 'use_all') { 
			$query = $GLOBALS['egw']->session->appsession('index','addressbook');
			$query['num_rows'] = -1;	// all
			$uicontacts->get_rows($query,$selection,$readonlys,true);	// true = only return the id's
		}
		else {
			$selection = explode(',',$options['selection']);
		}
		
		$export_object = new export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);
		
		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $identifier) {
			$contact = new egw_addressbook_record($identifier);
			$export_object->export_record($contact);
			unset($contact);
		}		
	}
	
	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Addressbook CSV export');
	}
	
	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports contacts from your Addressbook into a CSV File. CSV means 'Comma Seperated Values'. However in the options Tab you can also choose other seperators.");
	}
	
	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix() {
		return 'csv';
	}
	
	/**
	 * return html for options.
	 * this way the plugin has all opertunities for options tab
	 * 
	 * @return string html
	 */
	public function get_options_etpl() {
		return 'addressbook.export_csv_options';
	}
	
	/**
	 * returns slectors of this plugin via xajax
	 *
	 */
	public function get_selectors_etpl() {
		return '<b>Selectors:</b>';
	}
}
