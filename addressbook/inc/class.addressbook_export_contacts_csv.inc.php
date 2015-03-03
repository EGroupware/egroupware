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
 * @version $Id$
 */

/**
 * export plugin of addressbook
 */
class addressbook_export_contacts_csv implements importexport_iface_export_plugin {


	/**
	 * Constants used for exploding categories & multi-selectboxes into seperate fields
	 */
	const NO_EXPLODE = False;
	const MAIN_CATS = 'main_cats';	// Only the top-level categories get their own field
	const EACH_CAT = 'each_cat';	// Every category gets its own field
	const EXPLODE = 'explode';	// For [custom] multi-selects, each option gets its own field

	public function __construct()
	{
		$this->ui= new addressbook_ui();
		$this->get_selects();
	}

	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {

		$options = $_definition->plugin_options;
		$this->export_object = $export_object = new importexport_export_csv($_stream, (array)$options);

		$selection = array();

		// Addressbook defines its own export imits
		$limit_exception = bo_merge::is_export_limit_excepted();
		$export_limit = bo_merge::getExportLimit($app='addressbook');
		if (!$limit_exception) $export_object->export_limit = $export_limit; // we may not need that after all
		if($export_limit == 'no' && !$limit_exception) {
			return;
		}

		// Need to switch the app to get the same results
		$old_app = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw_info']['flags']['currentapp'] = 'addressbook';

		if ($options['selection'] == 'search') {
			// uicontacts selection with checkbox 'use_all'
			$query = $GLOBALS['egw']->session->appsession('index','addressbook');
			$query['num_rows'] = -1;	// all
			$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			$query['order'] = 'contact_id';
			if(!array_key_exists('filter',$query)) $query['filter'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->ui->get_rows($query,$selection,$readonlys, true);	// only return the ids
		}
		elseif ( $options['selection'] == 'all' ) {
			if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) {
				$col_filter['account_id'] = null;
			}
			$selection = ExecMethod2('addressbook.addressbook_bo.search', array(), true, '', '','',false,'AND',false,$col_filter);
			//$uicontacts->get_rows($query,$selection,$readonlys,true);
		}
		elseif ($options['selection'] == 'filter')
		{
			$fields = importexport_helper_functions::get_filter_fields($_definition->application, $this);
			$filter = $_definition->filter;
			$known_fields = $this->ui->get_fields('supported');
			$query = array();

			// Handle ranges
			foreach($filter as $field => $value)
			{
				if($field == 'cat_id')
				{
					$query['col_filter'][$field] = implode(',',$value);
					continue;
				}

				// Birthdays in addressbook are formatted Y-m-d
				if($field == 'bday')
				{
					if($value['from'])
					{
						$query['col_filter'][] = "contact_bday >= " . $GLOBALS['egw']->db->quote(date('Y-m-d', (int)$value['from']));
					}
					if($value['to'])
					{
						$query['col_filter'][] = "contact_bday <= " . $GLOBALS['egw']->db->quote(date('Y-m-d', (int)$value['to']));
					}
					continue;
				}
				// Custom fields & listed exceptions are not filtered with contact_ prefix
				if(strpos($field, '#') !== 0 && !in_array($field, array('tid','owner')))
				{
					$field = 'contact_'.$field;
				}
				$query['col_filter'][$field] = $value;
				if(!is_array($value) || (!$value['from'] && !$value['to'])) continue;

				// Ranges are inclusive, so should be provided that way (from 2 to 10 includes 2 and 10)
				if($value['from']) $query['col_filter'][] = "$field >= " . (int)$value['from'];
				if($value['to']) $query['col_filter'][] = "$field <= " . (int)$value['to'];
				unset($query['col_filter'][$field]);
			}
			$selection = ExecMethod2('addressbook.addressbook_bo.search', array(), true, '', '','',false,'AND',false,$query['col_filter']);
		}
		else
		{
			$selection = explode(',',$options['selection']);
		}
		if(!is_array($selection))
		{
			$selection = array();
		}
		$GLOBALS['egw_info']['flags']['currentapp'] = $old_app;

		if(bo_merge::hasExportLimit($export_limit) && !$limit_exception) {
			$selection = array_slice($selection, 0, $export_limit);
		}

		if($options['explode_multiselects']) {
			$customfields = config::get_customfields('addressbook');
			$additional_fields = array();
			$cat_obj = new categories('', 'addressbook');
			foreach($options['explode_multiselects'] as $field => $explode) {
				switch($explode['explode']) {
					case self::MAIN_CATS:
						$cats = $cat_obj->return_array('mains', 0, false,'','ASC','',true);
						foreach($cats as $settings) {
							$additional_fields[$field][$settings['id']] = array(
								'count' => 0,
								'label' => $settings['name'],
								'subs' => array(),
							);
							$subs = $cat_obj->return_sorted_array(0, False, '', 'ASC', 'cat_name', True, $settings['id']);
							foreach($subs as $sub) {
								$name = $sub['name'];
								$path = $sub;
								while($path['parent'] != $settings['id']) {
									$path = $cat_obj->read($path['parent']);
									$name = $path['name'] . '/' . $name;
								}
								$additional_fields[$field][$settings['id']]['subs'][$sub['id']] = $name;
							}
						}
						break;
					case self::EACH_CAT:
						$cats = $cat_obj->return_array('all', 0, false,'','ASC','',true);
						foreach($cats as $settings) {
							$name = $settings['name'];
							$path = $settings;
							while($path['level'] != 0) {
								$path = $cat_obj->read($path['parent']);
								$name = $path['name'] . '/' . $name;
							}
							$additional_fields[$field][$settings['id']] = array(
								'count' => 0,
								'label' => $name
							);
						}
						break;
					case self::EXPLODE:
						// Only works for custom fields
						$index = substr($field, 1);
						foreach($customfields[$index]['values'] as $key => $value) {
							$additional_fields[$field][$key] = array(
								'count' => 0,
								'label' => $customfields[$index]['label'] . ': ' . $value,
							);
						}
						break;
				}
			}

			// Check records to see if additional fields are actually used
			foreach ($selection as $_contact) {
				if(is_array($_contact) && array_key_exists('photo', $_contact)) {
					unset($_contact['photo']);
				}
				if(is_array($_contact) && count($_contact) == 1 && $_contact['id']) {
					$_contact = $_contact['id'];
				}
				if(is_array($_contact) && $_contact['id']) {
					$contact = new addressbook_egw_record();
					$contact->set_record($_contact);
				} else {
					$contact = new addressbook_egw_record($_contact);
				}
				foreach($additional_fields as $field => &$values) {
					if(!$contact->$field) continue;
					foreach($values as $value => &$settings) {
						if(!is_array($contact->$field)) {
							$contact->$field = explode(',', $contact->$field);
						}
						if(is_array($contact->$field) && in_array($value, $contact->$field)) {
							$settings['count']++;
						} elseif($contact->$field == $value) {
							$settings['count']++;
						} elseif($options['explode_multiselects'][$field]['explode'] == self::MAIN_CATS && array_intersect($contact->$field, array_keys($settings['subs']))) {
							$settings['count']++;
						}
					}
				}
			}

			unset($field);
			unset($value);
			unset($settings);

			// Add additional columns
			foreach($additional_fields as $field => $additional_values) {
				// Remove original
				unset($options['mapping'][$field]);
				// Add exploded
				$field_count = 0;
				foreach($additional_values as $value => $settings) {
					if($settings['count'] > 0) {
						$field_count += $settings['count'];
						$options['mapping'][$field.'-'.$value] = $settings['label'];
					}
				}
				if($field_count > 0) {
					// Set some options for converting
					$options['explode_multiselects'][$field]['values'] = $additional_values;
				} else {
					// Don't need this anymore
					unset($options['explode_multiselects'][$field]);
				}
			}
		}

		$export_object->set_mapping($options['mapping']);
		
		// Add in last/next event, if needed
		if($options['mapping']['last_date'] || $options['mapping']['next_date'])
		{
			$contact_ids = array();
			foreach($selection as $_contact)
			{
				if(is_array($_contact) && $_contact['id'])
				{
					$contact_ids[] = $_contact['id'];
				}
				else
				{
					$contact_ids[] = $contact;
				}
			}
			$events = $this->ui->read_calendar($contact_ids, false);
		}

		// $options['selection'] is array of identifiers as this plugin doesn't
		// support other selectors atm.
		foreach ($selection as $_contact) {
			if(is_array($_contact) && array_key_exists('photo', $_contact)) {
				unset($_contact['photo']);
			}
			if(is_array($_contact) && count($_contact) == 1 && $_contact['id']) {
				$_contact = $_contact['id'];
			}
			if(is_array($_contact) && $_contact['id']) {
				$contact = new addressbook_egw_record();
				$contact->set_record($_contact);
			} else {
				$contact = new addressbook_egw_record($_contact);
			}

			if($events && $events[$contact->id])
			{
				// NB: last_date and next_date are used instead of last_event & next_event
				// to avoid automatic conversion - we want to export link title, not date-time
				$contact->last_date = $events[$contact->id]['last_link']['title'];
				$contact->next_date = $events[$contact->id]['next_link']['title'];
			}
			// Some conversion
			$this->convert($contact, $options);
			if($options['convert']) {
				importexport_export_csv::convert($contact, addressbook_egw_record::$types, 'addressbook',$this->selects);
			} else {
				// Implode arrays, so they don't say 'Array'
				foreach($contact->get_record_array() as $key => $value) {
					if(is_array($value)) $contact->$key = implode(',', $value);
				}
			}

			$export_object->export_record($contact);
			unset($contact);
		}
		return $export_object;
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
			'name'		=> 'importexport.export_csv_selectors',
		);
	}

	/**
	* Convert some internal data to something with more meaning
	*
	* Dates, times, user IDs, category IDs
	*/
	public static function convert(addressbook_egw_record &$record, $options) {

		if ($record->tel_prefer) {
			$field = $record->tel_prefer;
			$record->tel_prefer = $record->$field;
		}

		if(!is_array($options['explode_multiselects']))
		{
			return;
		}
		foreach((array)$options['explode_multiselects'] as $field => $explode_settings) {
			if(!is_array($record->$field)) $record->$field = explode(',', $record->$field);
			foreach((array)$explode_settings['values'] as $value => $settings) {
				$field_name = "$field-$value";
				$record->$field_name = array();
				if(is_array($record->$field) && in_array($value, $record->$field) || $record->$field == $value) {
					if($explode_settings['explode'] != self::MAIN_CATS) {
						$record->$field_name = $options['convert'] ? lang('Yes') : true;
					} elseif($options['convert']) {
						// 3 part assign due to magic get method
						$record_value = $record->$field_name;
						$record_value[] = $settings['label'];
						$record->$field_name = $record_value;
					} else {
						$record->$field_name = $value;
					}
				}
				if($explode_settings['explode'] == self::MAIN_CATS && count(array_intersect($record->$field, array_keys($settings['subs'])))) {
					// 3 part assign due to magic get method
					$record_value = $record->$field_name;
					if(!is_array($record_value)) $record_value = array($record_value);
					foreach(array_intersect($record->$field, array_keys($settings['subs'])) as $sub_id) {
						$record_value[] = $options['convert'] ? $settings['subs'][$sub_id] : $sub_id;
					}
					$record->$field_name = $record_value;
				}
				if(is_array($record->$field_name)) $record->$field_name = implode(($options['convert'] ? ', ' : ','), $record->$field_name);
			}
		}
	}


	protected function get_selects()
	{
		$this->selects = array(
			'tid' => array('n' => 'Contact')
		);
		foreach($this->ui->content_types as $tid => $data)
		{
			$this->selects['tid'][$tid] = $data['name'];
		}
	}

	/**
	 * Adjust automatically generated filter fields
	 */
	public function get_filter_fields(Array &$filters)
    {
		unset($filters['last_event']);
		unset($filters['next_event']);
		foreach($filters as $field_name => &$settings)
		{
			if($this->selects[$field_name]) $settings['values'] = $this->selects[$field_name];
		}
		$filters['owner'] = array(
			'name'		=> 'owner',
			'label'		=> 'addressbook',
			'type'		=> 'select',
			'rows'		=> 5,
			'tags'		=> true,
			'values'	=> $this->ui->get_addressbooks(EGW_ACL_READ)
		);
	}
}
