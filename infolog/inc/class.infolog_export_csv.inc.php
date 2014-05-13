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


	public function __construct() {
		translation::add_app('infolog');
		$this->bo = new infolog_bo();
		$this->get_selects();
	}

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {
		$options = $_definition->plugin_options;

		$selection = array();
		$query = array();
		$cf_links = array();

		$this->export_object = new importexport_export_csv($_stream, (array)$options);
		$this->export_object->set_mapping($options['mapping']);

		$ids = array();
		switch($options['selection'])
		{
			case 'search':
				$query = array_merge((array)$GLOBALS['egw']->session->appsession('session_data','infolog'), $query);
				// Fall through
			case 'filter':
			case 'all':
				// do we need to query the cf's
				$query['custom_fields'] = false;
				foreach($options['mapping'] + (array)$_definition->filter as $field => $map) {
					if($field[0] == '#') {
						$query['custom_fields'] = true;
						$query['selectcols'] .= ",$field";

						if($GLOBALS['egw_info']['user']['apps'][$this->bo->customfields[substr($field,1)]['type']])
						{
							$cf_links[$field] = $this->bo->customfields[substr($field,1)]['type'];
						}
					}
				}
				if($options['selection'] == 'filter')
				{
					$fields = importexport_helper_functions::get_filter_fields($_definition->application, $this);
					$query['col_filter'] = $_definition->filter;

					// Backend expects a string
					if($query['col_filter']['info_responsible'])
					{
						$query['col_filter']['info_responsible'] = implode(',',$query['col_filter']['info_responsible']);
					}

					// Handle ranges
					foreach($query['col_filter'] as $field => $value)
					{
						if(!is_array($value) || (!$value['from'] && !$value['to'])) continue;

						// Ranges are inclusive, so should be provided that way (from 2 to 10 includes 2 and 10)
						if($value['from']) $query['col_filter'][] = "$field >= " . (int)$value['from'];
						if($value['to']) $query['col_filter'][] = "$field <= " . (int)$value['to'];
						unset($query['col_filter'][$field]);
					}
				}
				$query['num_rows'] = 500;
				$query['start'] = 0;
				do {
					$selection = $this->bo->search($query);
					$ids = array_keys($selection);

					// Pre-load any cfs that are links
					$cf_preload = array();
					foreach($cf_links as $field => $app) {
						foreach($selection as &$row) {
							if($row[$field]) $cf_preload[$app][] = $row[$field];
						}
						if($cf_preload[$app]){
							 $selects[$field] = egw_link::titles($app, $cf_preload[$app]);
							//error_log('Preload ' . $field . '['.$app . ']: ' . implode(',',$cf_preload[$app]));
						}
					}

					$this->export_records($this->export_object, $options, $selection, $ids);
					$query['start'] += $query['num_rows'];
				} while($query['start'] < $query['total']);

				return $this->export_object;
				break;
			default:
				$ids = $selection = explode(',',$options['selection']);
				$this->export_records($this->export_object, $options, $selection, $ids);
				break;
		}
		return $this->export_object;
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
				$this->selects['pl_id'] = ExecMethod('projectmanager.projectmanager_pricelist_bo.pricelist',$selection[$id]['pm_id']);
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
				$this->selects['info_status'] = $this->bo->get_status($record->info_type);
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
	 * Suggest a file name for the downloaded file
	 * No suffix
	 */
	public function get_filename()
	{
		if(is_object($this->export_object) && $this->export_object->get_num_of_records() == 1)
		{
			return $this->export_object->record->get_title();
		}
		return false;
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
		);
	}

	protected function get_selects()
	{
		$this->selects['info_type'] = $this->bo->enums['type'];
		$this->selects['info_priority'] = $this->bo->enums['priority'];
		$this->selects['pl_id'] = ExecMethod('projectmanager.projectmanager_pricelist_bo.pricelist',false);
		$this->selects['info_status'] = $this->bo->get_status();
	}

	public function get_filter_fields(Array &$filters)
	{
		foreach($filters as $field_name => &$settings)
		{
			if($this->selects[$field_name]) $settings['values'] = $this->selects[$field_name];
			
			// Infolog can't handle ranges in custom fields due to the way searching is done.
			if(strpos($field_name, '#') === 0 && strpos($settings['type'],'date') === 0) unset($filters[$field_name]);
		}
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
