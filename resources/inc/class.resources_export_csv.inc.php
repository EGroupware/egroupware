<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * export resources to CSV
 */
class resources_export_csv implements importexport_iface_export_plugin
{

	public function __construct()
	{

		$this->bo = new resources_bo();
		$this->get_selects();
	}

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition)
	{
		$options = $_definition->plugin_options;

		$selection = array();
		if ($options['selection'] == 'search')
		{
			// ui selection with checkbox 'selected'
			$query = Api\Cache::getSession('resources', 'get_rows');
			$query['num_rows'] = -1;	// all
			unset($query['store_state']);
			$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			$this->bo->get_rows($query,$selection,$readonlys);
		}
		elseif ( $options['selection'] == 'all' || $options['selection'] == 'filter')
		{
			$query = array(
				'num_rows'	=> -1,
				'filter2'	=> -3,  // Accessories & resources
				'csv_export' => true,	// so get_rows method _can_ produce different content or not store state in the session
			);	// all

			if($options['selection'] == 'filter')
			{
				$filter = $_definition->filter;
				// Handle ranges
				foreach($filter as $field => $value)
				{
					if($field == 'cat_id')
					{
						$query['filter'] = $value;
						continue;
					}
					if($field == 'filter2')
					{
						$query['filter2'] = $value;
						continue;
					}
					$query['col_filter'][$field] = $value;
					if(!is_array($value) || (!$value['from'] && !$value['to'])) continue;

					// Ranges are inclusive, so should be provided that way (from 2 to 10 includes 2 and 10)
					if($value['from']) $query['col_filter'][] = "$field >= " . (int)$value['from'];
					if($value['to']) $query['col_filter'][] = "$field <= " . (int)$value['to'];
					unset($query['col_filter'][$field]);
				}
			}

			$this->bo->get_rows($query,$selection,$readonlys);
		}
		else
		{
			$selection = explode(',',$options['selection']);
		}

		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		// Check if we need to load the custom fields
		$need_custom = false;
		foreach(Api\Storage\Customfields::get('resources') as $field => $settings)
		{
			if($options['mapping']['#'.$field])
			{
				$need_custom = true;
				break;
			}
		}

		foreach ($selection as $record)
		{
			if(!is_array($record) || !$record['res_id']) continue;

			if($need_custom)
			{
				$record = $this->bo->read($record['res_id']);
			}
			$resource = new resources_egw_record();
			$resource->set_record($record);
			$resource->long_description = strip_tags($resource->long_description);
			if($options['convert'])
			{
				importexport_export_csv::convert($resource, resources_egw_record::$types, 'resources', $this->selects);
			}
			else
			{
				// Implode arrays, so they don't say 'Array'
				foreach($resource->get_record_array() as $key => $value)
				{
					if(is_array($value)) $resource->$key = implode(',', $value);
				}
 			}

			$export_object->export_record($resource);
			unset($resource);
		}
		return $export_object;
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name()
	{
		return lang('Resources CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description()
	{
		return lang("Exports a list of resources to a CSV File.");
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
	 * returns selectors information
	 *
	 */
	public function get_selectors_etpl()
	{
		return array(
			'name'	=> 'importexport.export_csv_selectors',
		);
	}

	/**
	 * Get selectbox values
	 */
	protected function get_selects()
    {
		$this->selects = array();
    }

	/**
	 * Customize automatically generated filter fields
	 */
	public function get_filter_fields(Array &$filters)
	{
		// In resources, not all categories are used
		$filters['cat_id']['type'] = 'select';
		$filters['cat_id']['name'] = 'filter';
		$filters['cat_id']['values']= (array)$this->bo->acl->get_cats(Acl::READ);

		// Add in resources / accessories
		$filters['filter2'] = array(
			'name' => 'filter2',
			'label' => 'Filter',
			'type' => 'select',
			'rows' => 5,
			'values' => resources_bo::$filter_options
		);
		foreach($filters as $field_name => &$settings)
		{
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
		return 'resources_egw_record';
	}
}
