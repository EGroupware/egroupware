<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package timesheet
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */


/**
 * class import_csv for timesheet
 */
class timesheet_import_csv implements importexport_iface_import_plugin  {

	private static $plugin_options = array(
		'fieldsep', 		// char
		'charset', 			// string
		'update_cats', 			// string {override|add} overides record
								// with cat(s) from csv OR add the cat from
								// csv file to exeisting cat(s) of record
		'num_header_lines', // int number of header lines
		'field_conversion', // array( $csv_col_num => conversion)
		'field_mapping',	// array( $csv_col_num => adb_filed)
		'conditions',		/* => array containing condition arrays:
				'type' => exists, // exists
				'string' => '#kundennummer',
				'true' => array(
					'action' => update,
					'last' => true,
				),
				'false' => array(
					'action' => insert,
					'last' => true,
				),*/

	);

	public static $special_fields = array(
		'addressbook'     => 'Link to Addressbook, use nlast,nfirst[,org] or contact_id from addressbook',
		'link_1'      => '1. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_2'      => '2. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_3'      => '3. link: appname:appid the entry should be linked to, eg.: addressbook:123',
	);

	/**
	 * actions wich could be done to data entries
	 */
	protected static $actions = array( 'none', 'update', 'insert', 'delete', );

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array( 'exists' );

	/**
	 * @var definition
	 */
	private $definition;

	/**
	 * @var business object
	 */
	private $bo;

	/**
	* For figuring out if a record has changed
	*/
	protected $tracking;

	/**
	 * @var bool
	 */
	private $dry_run = false;

	/**
	 * @var int
	 */
	private $user = null;

	/**
	 * List of import errors
	 */
	protected $errors = array();

	/**
         * List of actions, and how many times that action was taken
         */
        protected $results = array();

	/**
	 * imports entries according to given definition object.
	 * @param resource $_stream
	 * @param string $_charset
	 * @param definition $_definition
	 */
	public function import( $_stream, importexport_definition $_definition ) {
		$import_csv = new importexport_import_csv( $_stream, array(
			'fieldsep' => $_definition->plugin_options['fieldsep'],
			'charset' => $_definition->plugin_options['charset'],
		));

		$this->definition = $_definition;

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		// dry run?
		$this->dry_run = isset( $_definition->plugin_options['dry_run'] ) ? $_definition->plugin_options['dry_run'] :  false;

		// fetch the bo
		$this->bo = new timesheet_bo();

		// Get the tracker for changes
		$this->tracking = new timesheet_tracking($this->bo);

		// set FieldMapping.
		$import_csv->mapping = $_definition->plugin_options['field_mapping'];

		// set FieldConversion
		$import_csv->conversion = $_definition->plugin_options['field_conversion'];

		// Add extra conversions
		$import_csv->conversion_class = $this;

		//check if file has a header lines
		if ( isset( $_definition->plugin_options['num_header_lines'] ) && $_definition->plugin_options['num_header_lines'] > 0) {
			$import_csv->skip_records($_definition->plugin_options['num_header_lines']);
		} elseif(isset($_definition->plugin_options['has_header_line']) && $_definition->plugin_options['has_header_line']) {
			// First method is preferred
			$import_csv->skip_records(1);
		}

		// set Owner
		$_definition->plugin_options['record_owner'] = isset( $_definition->plugin_options['record_owner'] ) ?
			$_definition->plugin_options['record_owner'] : $this->user;

		// Used to try to automatically match names to account IDs
		$addressbook = new addressbook_so();

		// For converting human-friendly lookups
		$categories = new categories('timesheet');
		$lookups = array(
			'ts_status'	=> $this->bo->status_labels,
			'cat_id'	=> $categories->return_sorted_array(0,False,'','','',true)
		);

		// Start counting successes
		$count = 0;
		$this->results = array();

		// Failures
		$this->errors = array();

		while ( $record = $import_csv->get_record() ) {
			$success = false;

			// don't import empty records
			if( count( array_unique( $record ) ) < 2 ) continue;

			importexport_import_csv::convert($record, timesheet_egw_record::$types, 'timesheet', $lookups);

			// Date / time
			$record['ts_start'] = strtotime($record['ts_start']);

			// Automatically handle text categories without explicit translation
			foreach(array('ts_status','cat_id') as $field) {
				if(!is_numeric($record[$field])) {
					$translate_key = 'translate'.(substr($field,0,2) == 'ts' ? substr($field,2) : '_cat_id');
					if($key = array_search($record[$field], $lookups[$field])) {
						$record[$field] = $key;
					} elseif(array_key_exists($translate_key, $_definition->plugin_options)) {
						$t_field = $_definition->plugin_options[$translate_key];
						switch ($t_field) {
							case '':
							case '0':
								// Skip that field
								unset($record[$field]);
								break;
							case '~skip~':
								continue 2;
							default:
								if(strpos($t_field, 'add') === 0) {
									// Check for a parent
									list($name, $parent_name) = explode('~',$t_field);
									if($parent_name) {
										$parent = importexport_helper_functions::cat_name2id($parent_name);
									}

									if($field == 'cat_id') {
										$record[$field] = importexport_helper_functions::cat_name2id($record[$field], $parent);
									} elseif ($field == 'ts_status') {
										end($this->bo->status_labels);
										$id = key($this->bo->status_labels)+1;
										$this->bo->status_labels[$id] = $record[$field];
										$this->bo->status_labels_config[$id] = array(
											'name'   => $record[$field],
											'parent' => $parent,
											'admin'  => false
										);
										config::save_value('status_labels',$this->bo->status_labels_config,TIMESHEET_APP);
										$lookups[$field][$id] = $name;
										$record[$field] = $id;
									}
								} elseif($key = array_search($t_field, $lookups[$field])) {
									$record[$field] = $key;
								} else {
									$record[$field] = $t_field;
								}
								break;
						}
					}
				}
			}

			// Set creator, unless it's supposed to come from CSV file
			if($_definition->plugin_options['owner_from_csv'] && $record['ts_owner']) {
				if(!is_numeric($record['ts_owner'])) {
					// Automatically handle text owner without explicit translation
					$new_owner = importexport_helper_functions::account_name2id($record['ts_owner']);
					if($new_owner == '') {
						$this->errors[$import_csv->get_current_position()] = lang(
							'Unable to convert "%1" to account ID.  Using plugin setting (%2) for %3.',
							$record['ts_owner'],
							common::grab_owner_name($_definition->plugin_options['record_owner']),
							lang($this->bo->field2label['ts_owner'])
						);
						$record['ts_owner'] = $_definition->plugin_options['record_owner'];
					} else {
						$record['ts_owner'] = $new_owner;
					}
				}
			} elseif ($_definition->plugin_options['record_owner']) {
				$record['ts_owner'] = $_definition->plugin_options['record_owner'];
			}

			// Check account IDs
			foreach(array('ts_modifier') as $field) {
				if($record[$field] && !is_numeric($record[$field])) {
					// Try an automatic conversion
					$account_id = importexport_helper_functions::account_name2id($record[$field]);
					if($account_id && strtoupper(common::grab_owner_name($account_id)) == strtoupper($record[$field])) {
						$record[$field] = $account_id;
					} else {
						$this->errors[$import_csv->get_current_position()] = lang(
							'Unable to convert "%1" to account ID.  Using plugin setting (%2) for %3.',
							$record[$field],
							common::grab_owner_name($_definition->plugin_options['record_owner']),
							$this->bo->field2label[$field] ? lang($this->bo->field2label[$field]) : $field
						);
					}
				}
			}

			// Lookups - from human friendly to integer
			foreach(array_keys($lookups) as $field) {
				if(!is_numeric($record[$field]) && $key = array_search($record[$field], $lookups[$field])) {
					$record[$field] = $key;
				}
			}

			// Special values
			if ($record['addressbook'] && !is_numeric($record['addressbook']))
			{
				list($lastname,$firstname,$org_name) = explode(',',$record['addressbook']);
				$record['addressbook'] = self::addr_id($lastname,$firstname,$org_name);
			}

			if ( $_definition->plugin_options['conditions'] ) {
				foreach ( $_definition->plugin_options['conditions'] as $condition ) {
					$results = array();
					switch ( $condition['type'] ) {
						// exists
						case 'exists' :
							if($record[$condition['string']]) {
								$results = $this->bo->search(array($condition['string'] => $record[$condition['string']]));
							}

							if ( is_array( $results ) && count( array_keys( $results )) >= 1 ) {
								// apply action to all records matching this exists condition
								$action = $condition['true'];
								foreach ( (array)$results as $result ) {
									$record['ts_id'] = $result['ts_id'];
									if ( $_definition->plugin_options['update_cats'] == 'add' ) {
										if ( !is_array( $result['cat_id'] ) ) $result['cat_id'] = explode( ',', $result['cat_id'] );
										if ( !is_array( $record['cat_id'] ) ) $record['cat_id'] = explode( ',', $record['cat_id'] );
										$record['cat_id'] = implode( ',', array_unique( array_merge( $record['cat_id'], $result['cat_id'] ) ) );
									}
									$success = $this->action(  $action['action'], $record, $import_csv->get_current_position() );
								}
							} else {
								$action = $condition['false'];
								$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
							}
							break;

						// not supported action
						default :
							die('condition / action not supported!!!');
							break;
					}
					if ($action['last']) break;
				}
			} else {
				// unconditional insert
				$success = $this->action( 'insert', $record, $import_csv->get_current_position() );
			}
			if($success) $count++;
		}
		return $count;
	}

	/**
	 * perform the required action
	 *
	 * @param int $_action one of $this->actions
	 * @param array $_data tracker data for the action
	 * @return bool success or not
	 */
	private function action ( $_action, $_data, $record_num = 0 ) {
		$result = true;
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
				// Only update if there are changes
				$old = $this->bo->read($_data['ts_id']);

				if(!$this->definition->plugin_options['change_owner']) {
					// Don't change creator of an existing ticket
					unset($_data['ts_owner']);
				}

				// Merge to deal with fields not in import record
				$_data = array_merge($old, $_data);
				$changed = $this->tracking->changed_fields($_data, $old);
				if(count($changed) == 0 && !$this->definition->plugin_options['update_timestamp']) {
					break;
				}
				
				// Fall through
			case 'insert' :
				if ( $this->dry_run ) {
					//print_r($_data);
					$this->results[$_action]++;
					break;
				} else {
					$result = $this->bo->save( $_data);
					if($result) {
						$this->errors[$record_num] = lang('Permissions error - %1 could not %2',
							$GLOBALS['egw']->accounts->id2name($_data['owner']),
							lang($_action)
						) . ' ' . $result;
					} else {
						$this->results[$_action]++;
						$result = $this->bo->data['ts_id'];
					}
					break;
				}
			default:
				throw new egw_exception('Unsupported action');
		}

		// Process some additional fields
		if(!is_numeric($result)) {
			return $result;
		}
		$_link_id = false;
		foreach(self::$special_fields as $field => $desc) {
			if(!$_data[$field]) continue;

			// Links
			if(strpos('link', $field) === 0) {
				list($app, $id) = explode(':', $_data[$field]);
			} else {
				$app = $field;
				$id = $_data[$field];
			}
			if ($app && $app_id) {
				$link_id = egw_link::link('timesheet',$id,$app,$app_id);
			}
		}
		return $result;
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Timesheet CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports entries into the timesheet from a CSV File. ");
	}

	/**
	 * retruns file suffix(s) plugin can handle (e.g. csv)
	 *
	 * @return string suffix (comma seperated)
	 */
	public static function get_filesuffix() {
		return 'csv';
	}

	/**
	 * return etemplate components for options.
	 * @abstract We can't deal with etemplate objects here, as an uietemplate
	 * objects itself are scipt orientated and not "dialog objects"
	 *
	 * @return array (
	 * 		name 		=> string,
	 * 		content		=> array,
	 * 		sel_options => array,
	 * 		preserv		=> array,
	 * )
	 */
	public function get_options_etpl() {
		// lets do it!
	}

	/**
	 * returns etemplate name for slectors of this plugin
	 *
	 * @return string etemplate name
	 */
	public function get_selectors_etpl() {
		// lets do it!
	}

	/**
        * Returns errors that were encountered during importing
        * Maximum of one error message per record, but you can append if you need to
        *
        * @return Array (
        *       record_# => error message
        *       )
        */
        public function get_errors() {
		return $this->errors;
	}

	/**
        * Returns a list of actions taken, and the number of records for that action.
        * Actions are things like 'insert', 'update', 'delete', and may be different for each plugin.
        *
        * @return Array (
        *       action => record count
        * )
        */
        public function get_results() {
                return $this->results;
        }
	// end of iface_export_plugin

	// Extra conversion functions - must be static
	public static function addr_id( $n_family,$n_given=null,$org_name=null ) {

		// find in Addressbook, at least n_family AND (n_given OR org_name) have to match
		static $contacts;
		if (is_null($n_given) && is_null($org_name))
		{
			// Maybe all in one
			list($n_family, $n_given, $org_name) = explode(',', $n_family);
		}
		$n_family = trim($n_family);
		if(!is_null($n_given)) $n_given = trim($n_given);
		if (!is_object($contacts))
		{
			$contacts =& CreateObject('phpgwapi.contacts');
		}
		if (!is_null($org_name))        // org_name given?
		{
			$org_name = trim($org_name);
			$addrs = $contacts->read( 0,0,array('id'),'',"n_family=$n_family,n_given=$n_given,org_name=$org_name" );
			if (!count($addrs))
			{
				$addrs = $contacts->read( 0,0,array('id'),'',"n_family=$n_family,org_name=$org_name",'','n_family,org_name');
			}
		}
		if (!is_null($n_given) && (is_null($org_name) || !count($addrs)))       // first name given and no result so far
		{
			$addrs = $contacts->search(array('n_family' => $n_family, 'n_given' => $n_given));
		}
		if (is_null($n_given) && is_null($org_name))    // just one name given, check against fn (= full name)
		{
			$addrs = $contacts->read( 0,0,array('id'),'',"n_fn=$n_family",'','n_fn' );
		}
		if (count($addrs))
		{
			return $addrs[0]['id'];
		}
		return False;
	}
}
?>
