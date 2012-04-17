<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */

/**
 * export plugin of infolog
 */
class infolog_export_csv implements importexport_iface_export_plugin {


	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$bo = new infolog_bo();
		$selection = array();
		$query = array();
		$cf_links = array();

		if(!$this->selects)
		{
			$this->selects['info_type'] = $bo->enums['type'];
			$this->selects['info_priority'] = $bo->enums['priority'];
		}

		$export_object = new importexport_export_csv($_stream, (array)$options);
		$export_object->set_mapping($options['mapping']);

		// do we need to query the cf's
		foreach($options['mapping'] as $field => $map) {
			if($field[0] == '#') {
				$query['custom_fields'][] = $field;

				if($GLOBALS['egw_info']['user']['apps'][$bo->customfields[substr($field,1)]['type']])
				{
					$cf_links[$field] = $bo->customfields[substr($field,1)]['type'];
				}
			}
		}

		$ids = array();
		switch($options['selection'])
		{
			case 'search':
				$query = array_merge($GLOBALS['egw']->session->appsession('session_data','infolog'), $query);
				// Fall through
			case 'all':
				$query['num_rows'] = 500;
				$query['start'] = 0;
				do {
					$selection = $bo->search($query);
					$ids = array_keys($selection);

					// Pre-load any cfs that are links
					$cf_preload = array();
					foreach($cf_links as $field => $app) {
						foreach($selection as &$row) {
							if($row[$field]) $cf_preload[$app][] = $row[$field];
						}
						if($cf_preload[$app]){
							 $selects[$field] = egw_link::titles($app, $cf_preload[$app]);
							error_log('Preload ' . $field . '['.$app . ']: ' . implode(',',$cf_preload[$app]));
						}
					}

					$this->export_records($export_object, $options, $selection, $ids);
					$query['start'] += $query['num_rows'];
				} while($query['start'] < $query['total']);

				return $export_object;
				break;
			default:
				$ids = $selection = explode(',',$options['selection']);
				$this->export_records($export_object, $options, $selection, $ids);
				break;
		}
		return $export_object;
	}

	protected function export_records(&$export_object, $options, &$selection, $ids = array())
	{
		// Pre-load links all at once
		if($ids && $options['mapping']['info_link_id'])
		{
			$links = egw_link::get_links_multiple('infolog', $ids, true, '!'.egw_link::VFS_APPNAME);
			foreach($links as $id => $link) {
				if(!is_array($selection[$id])) break;
				$selection[$id]['info_link_id'] = $link;
				if($options['convert']) $selection[$id]['info_link_id'] = egw_link::title($link['app'], $link['id']);
			}
		}
		// If exporting PM fields, pre-load them all at once
		if($ids && ($options['mapping']['pm_id'] || $options['mapping']['project']))
		{
			$projects = egw_link::get_links_multiple('infolog', $ids, true, 'projectmanager');
			foreach($projects as $id => $links)
			{
				if(!is_array($selection[$id])) break;
				$selection[$id]['pm_id'] = current($links);
				$selection[$id]['project'] = egw_link::title('projectmanager', $selection[$id]['pm_id']);
			}
		}

		foreach ($selection as $_identifier) {
			if(!is_array($_identifier)) {
				$record = new infolog_egw_record($_identifier);
				if($link = $links[$record->info_id]) $record->info_link_id = $options['convert'] ? egw_link::title($link['app'], $link['id']) : $link;
				if($project = $projects[$record->info_id])
				{
					$record->pm_id = current($project);
					$record->project = egw_link::title('projectmanager', $record->pm_id);
				}
			} else {
				$record = new infolog_egw_record();
				$record->set_record($_identifier);
			}
			// Some conversion
			if($options['convert']) {
				$this->selects['info_status'] = $bo->status[$record->info_type];
				importexport_export_csv::convert($record, infolog_egw_record::$types, 'infolog', $this->selects);
				$this->convert($record);

				// Force 0 times to ''
				foreach(array('info_planned_time', 'info_used_time', 'info_replanned_time') as $field)
				{
					if($record->$field == 0) $record->$field = '';
				}
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
		return lang('Infolog CSV export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports Infolog entries into a CSV File.");
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
	}

	/**
	 * returns slectors of this plugin via xajax
	 *
	 */
	public function get_selectors_etpl() {
		return array(
			'name'	=> 'infolog.export_csv_selectors',
			'content'	=> 'search'
		);
	}

	/**
	* Convert some internal data to something with more meaning
	*
	* This is for something specific to Infolog, in addition to the normal conversions.
	*/
	public static function convert(infolog_egw_record &$record) {
		// Stub, for now
	}
}
