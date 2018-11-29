<?php
/**
 * EGroupware - Export plugin for ACL settings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2017
 * @version $Id$
 */

use EGroupware\Api\Hooks;
use EGroupware\Api\Etemplate\Widget\Select;
/**
 * Export ACL plugin
 */
class admin_export_acl_csv implements importexport_iface_export_plugin
{
	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition)
	{
		$options = $_definition->plugin_options;


		$selection = array();
		$query = array(
			'filter'  => 'other',
			'filter2' => $_definition->filter['acl_appname'] ? $_definition->filter['acl_appname'] : '',
			'acl_rights' => Hooks::process(array(
				'location' => 'acl_rights'
			))
		);
		if($_definition->filter['acl_location'])
		{
			$query['col_filter']['acl_location'] = $_definition->filter['acl_location'];
		}

		// ACL queries only go by one account at a time, so we collect for all
		if($_definition->filter['acl_account'])
		{
			$accounts = array_flip($_definition->filter['acl_account']);
		}
		else
		{
			$account_query = array(
				'type'     => 'both',
				'active'   => $_definition->filter['active']
			);
			$accounts = $GLOBALS['egw']->accounts->search($account_query);
		}
		foreach($accounts as $account_id => $account_data)
		{
			$this->get_account_acl($account_id, $options['mapping']['acl_run'], $query, $selection);
		}

		$options['begin_with_fieldnames'] = true;
		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		$lookups = array(
			'acl_appname' => Select::app_options('installed')
		);
		if($selection['sel_options'])
		{
			$lookups += $selection['sel_options'];
			unset($selection['sel_options']);
		}
		$selection = array_map("unserialize", array_unique(array_map("serialize", $selection)));

		// $_record is an array, that's what search() returns
		foreach ($selection as $_record)
		{
			$record = new admin_egw_acl_record($_record);
			// Add in field for all ACLs
			$all_acls = array();
			foreach($record->get_record_array() as $key => $value)
			{
				if(strpos($key, '_') === FALSE && !in_array($key, array('id')))
				{
					$all_acls[] = $value;
				}
			}

			if($options['convert'])
			{
				importexport_export_csv::convert($record, admin_egw_acl_record::$types, 'admin', $lookups);
			}
			else
			{
				// Implode arrays, so they don't say 'Array'
				foreach($record->get_record_array() as $key => $value)
				{
					if(is_array($value)) $record->$key = implode(',', $value);
				}
 			}
			$record->all_acls = implode(',', $all_acls);

			$export_object->export_record($record);
			unset($record);
		}
		return $export_object;
	}

	/**
	 * Get the acl data for one account & format it as needed
	 *
	 * @param int $account_id
	 * @param boolean $run_rights Include run rights
	 * @param array $query
	 * @param array $selection
	 */
	protected function get_account_acl($account_id, $run_rights, $query, &$selection)
	{
		$query['account_id'] = $account_id;
		$query['filter'] = 'other';
		$account_acl = array();
		admin_acl::get_rows($query, $account_acl);
		$selection = array_merge($selection, $account_acl);

		$run = array();
		if($run_rights)
		{
			$query['filter'] = 'run';
			admin_acl::get_rows($query, $run);
			foreach($run as &$run_row)
			{
				$run_row['acl_run'] = true;
				unset($run_row['acl_location']);
				unset($run_row['acl1']);
			}
			$selection = array_merge($selection, $run);
		}

	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name()
	{
		return lang('ACL CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description()
	{
		return lang("Exports permission settings into a CSV File. ");
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
			'name'	=> 'importexport.export_csv_selectors',
			'content' => array(
				'selection' => 'all',
				'no_search' => true
			),
			'sel_options' => array(
				'acl_appname' => Select::app_options('installed')
			),
			'readonlys' => array(
			//	'search' => true
			)
			//'preserv' => array('no_error_for_all'),
		);
	}
	/**
	 * Get the class name for the egw_record to use while exporting
	 *
	 * @return string;
	 */
	public static function get_egw_record_class()
	{
		return 'admin_egw_acl_record';
	}
}
