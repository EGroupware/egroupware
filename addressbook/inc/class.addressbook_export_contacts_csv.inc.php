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

/**
 * export plugin of addressbook
 */
class addressbook_export_contacts_csv implements importexport_iface_export_plugin {

	// Used in conversions
	static $types = array(
                'select-account' => array('owner','creator','modifier'),
                'date-time' => array('modified','created','last_event','next_event'),
                'select-cat' => array('cat_id'),
        );

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$uicontacts = new addressbook_ui();
		$selection = array();
		if ($options['selection'] == 'use_all') {
			// uicontacts selection with checkbox 'use_all'
			$query = $GLOBALS['egw']->session->appsession('index','addressbook');
			$query['num_rows'] = -1;	// all
			$uicontacts->get_rows($query,$selection,$readonlys,true);	// true = only return the id's
		}
		elseif ( $options['selection'] == 'all_contacts' ) {
			$selection = ExecMethod('addressbook.addressbook_bo.search',array());
			//$uicontacts->get_rows($query,$selection,$readonlys,true);
		} else {
			$selection = explode(',',$options['selection']);
		}

		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $identifier) {
			$contact = new addressbook_egw_record($identifier);

			// Some conversion
			$this->convert($contact);
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
		return lang("Exports contacts from your Addressbook into a CSV File.");
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
		return 'addressbook.export_csv_selectors';
	}

	/**
	* Convert some internal data to something with more meaning
	* 
	* Dates, times, user IDs, category IDs
	*/
	public static function convert(addressbook_egw_record &$record) {
		$custom = config::get_customfields('addressbook');
		foreach($custom as $name => $c_field) {
			$name = '#' . $name;
			if($c_field['type'] == 'date') {
				self::$types['date-time'][] = $name;
			} elseif ($c_field['type'] == 'select-account')	{
				self::$types['select-account'][] = $name;
			}
		}
		foreach(self::$types['select-account'] as $name) {
			if ($record->$name) {
				$record->$name = $GLOBALS['egw']->common->grab_owner_name($record->$name);
			} elseif ($name == 'owner') {
				$record->$name = lang('Accounts');
			}
		}
		foreach(self::$types['date-time'] as $name) {
			//if ($record->$name) $record->$name = date('Y-m-d H:i:s',$record->$name);
			if ($record->$name) $record->$name = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'] . ' '.
				($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == '24' ? 'H' : 'h').':m:s',$record->$name); // User date format
		}
		if ($record->tel_prefer) {
			$field = $record->tel_prefer;
			$record->tel_prefer = $record->$field;
		}

		$cats = array();
		foreach(explode(',',$record->cat_id) as $n => $cat_id) {
			if ($cat_id) $cats[] = $GLOBALS['egw']->categories->id2name($cat_id);
		}

		$record->cat_id = implode(', ',$cats);
	}
}
