<?php
/**
 * EGroupware - Export plugin for users
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
class admin_export_users_csv implements importexport_iface_export_plugin
{
	protected $lookups = array(
		'account_status'	=> array('A' => 'Active', '' => 'Disabled', 'D' => 'Disabled'),
	);

	public function __construct() {
		foreach($this->lookups as $select => &$options)
		{
			foreach($options as $key => &$label)
			{
				if(is_string($label))
				{
					$label = lang($label);
				}
			}
		}
	}
	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition)
	{
		$options = $_definition->plugin_options;

		$query = array(
			'type'	=>	'accounts',
		);
		if($options['selection']['group_id'])
		{
			$query['type'] = (int)$options['selection']['group_id'];
		}
		$selection = $GLOBALS['egw']->accounts->search($query);

		$options['begin_with_fieldnames'] = true;
		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		// $_record is an array, that's what search() returns
		foreach ($selection as $_record)
		{
			$record = new admin_egw_user_record($_record);
			if($options['convert'])
			{
				$never_expires = ($record->account_expires == -1);
				importexport_export_csv::convert($record, admin_egw_user_record::$types, 'admin', $this->lookups);
				if($never_expires) $record->account_expires = 'never';  // Has to be 'never' for admin_cmd_edit_user to parse it
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
			unset($record);
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name()
	{
		return lang('User CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description()
	{
		return lang("Exports users into a CSV File. ");
	}

	/**
	 * returns file suffix for exported file
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
	 * returns slectors of this plugin via xajax
	 *
	 */
	public function get_selectors_etpl()
	{
		return array(
			'name'	=> 'admin.export_users_csv_selectors',
			'preserv' => array('no_error_for_all'),
		);
	}
	/**
	 * Get the class name for the egw_record to use while exporting
	 *
	 * @return string;
	 */
	public static function get_egw_record_class()
	{
		return 'admin_egw_user_record';
	}

	public function get_filter_fields(&$fields)
	{
		$fields['account_primary_group']['account_type'] = 'groups';
		$fields['account_groups']['account_type'] = 'groups';

		$fields['account_status']['values'] = $this->lookups['account_status'];
	}
}
