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

/**
 * export resources to CSV
 */
class resources_export_csv implements importexport_iface_export_plugin {

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$bo = new resources_bo();
		$selection = array();
		if ($options['selection'] == 'selected') {
			// ui selection with checkbox 'selected'
			$query = egw_cache::getSession('resources', 'get_rows');
			$query['num_rows'] = -1;	// all
			unset($query['store_state']);
			$bo->get_rows($query,$selection,$readonlys);
		}
		elseif ( $options['selection'] == 'all' ) {
			$query = array(
				'num_rows'	=> -1,
			);	// all
			$bo->get_rows($query,$selection,$readonlys);
		} else {
			$selection = explode(',',$options['selection']);
		}

		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		// Check if we need to load the custom fields
		$need_custom = false;
		foreach(config::get_customfields('resources') as $field => $settings) {
			if($options['mapping']['#'.$field]) {
				$need_custom = true;
				break;
			}
		}
		$types = importexport_export_csv::$types;
		$types['select-bool'] = array('bookable');

		foreach ($selection as $record) {
			if(!is_array($record) || !$record['res_id']) continue;

			if($need_custom) {
				$record = $bo->read($record['res_id']);
			}
			$resource = new resources_egw_record();
			$resource->set_record($record);
			if($options['convert']) {
				importexport_export_csv::convert($resource, $types, 'resources');
			} else {
				// Implode arrays, so they don't say 'Array'
				foreach($resource->get_record_array() as $key => $value) {
					if(is_array($value)) $resource->$key = implode(',', $value);
				}
 			}

			$export_object->export_record($resource);
			unset($resource);
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Resources CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports a list of resources to a CSV File.");
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
	 * this way the plugin has all opportunities for options tab
	 *
	 */
	public function get_options_etpl() {
	}

	/**
	 * returns selectors information
	 *
	 */
	public function get_selectors_etpl() {
		return array(
			'name'	=> 'resources.export_csv_selectors'
		);
	}
}
