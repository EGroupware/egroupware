<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id: $
 */


/**
 * class import_csv for addressbook
 */
class addressbook_import_contacts_csv implements importexport_iface_import_plugin  {

	private static $plugin_options = array(
		'fieldsep', 		// char
		'charset', 			// string
		'contact_owner', 	// int
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

	/**
	 * actions wich could be done to data entries
	 */
	protected static $actions = array( 'none', 'update', 'insert', 'delete', );

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array( 'exists', 'greater', 'greater or equal', );

	/**
	 * @var definition
	 */
	private $definition;

	/**
	 * @var bocontacts
	 */
	private $bocontacts;

	/**
	* For figuring out if a contact has changed
	*/
	protected $tracking;

	/**
	 * @var bool
	 */
	private $dry_run = false;

	/**
	 * @var bool is current user admin?
	 */
	private $is_admin = false;

	/**
	 * @var int
	 */
	private $user = null;

	/**
	 * List of import warnings
	 */
	protected $warnings = array();

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

		// user, is admin ?
		$this->is_admin = isset( $GLOBALS['egw_info']['user']['apps']['admin'] ) && $GLOBALS['egw_info']['user']['apps']['admin'];
		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		// dry run?
		$this->dry_run = isset( $_definition->plugin_options['dry_run'] ) ? $_definition->plugin_options['dry_run'] :  false;

		// Needed for categories to work right
		$GLOBALS['egw_info']['flags']['currentapp'] = 'addressbook';

		// fetch the addressbook bo
		$this->bocontacts = new addressbook_bo();

		// Get the tracker for changes
		$this->tracking = new addressbook_tracking($this->bocontacts);

		// set FieldMapping.
		$import_csv->mapping = $_definition->plugin_options['field_mapping'];

		// set FieldConversion
		$import_csv->conversion = $_definition->plugin_options['field_conversion'];

		//check if file has a header lines
		if ( isset( $_definition->plugin_options['num_header_lines'] ) && $_definition->plugin_options['num_header_lines'] > 0) {
			$import_csv->skip_records($_definition->plugin_options['num_header_lines']);
		} elseif(isset($_definition->plugin_options['has_header_line']) && $_definition->plugin_options['has_header_line']) {
			// First method is preferred
			$import_csv->skip_records(1);
		}

		$_lookups = array();

		// set contact owner
		
		$contact_owner = isset( $_definition->plugin_options['contact_owner'] ) ?
			$_definition->plugin_options['contact_owner'] : $this->user;
		// Import into importer's personal addressbook
		if($contact_owner == 'personal')
		{
			$contact_owner = $this->user;
		}

		// Start counting successes
		$count = 0;
		$this->results = array();

		// Failures
		$this->errors = array();

		while ( $record = $import_csv->get_record() ) {
			$success = false;

			// don't import empty contacts
			if( count( array_unique( $record ) ) < 2 ) continue;

			importexport_import_csv::convert($record, addressbook_egw_record::$types, 'addressbook', $_lookups, $_definition->plugin_options['convert']);
			// Set owner, unless it's supposed to come from CSV file
			if($_definition->plugin_options['owner_from_csv']) {
				if($record['owner'] && !is_numeric($record['owner'])) {
					// Automatically handle text owner without explicit translation
					$new_owner = importexport_helper_functions::account_name2id($record['owner']);
					if($new_owner == '') {
						$this->errors[$import_csv->get_current_position()] = lang(
							'Unable to convert "%1" to account ID.  Using plugin setting (%2) for owner.',
							$record['owner'],
							common::grab_owner_name($contact_owner)
						);
						$record['owner'] = $contact_owner;
					} else {
						$record['owner'] = $new_owner;
					}
				}
			} else {
				$record['owner'] = $contact_owner;
			}

			// Also handle categories in their own field
			$more_categories = array();
			foreach($_definition->plugin_options['field_mapping'] as $number => $field_name) {
				if(!array_key_exists($field_name, $record) ||
					substr($field_name,0,3) != 'cat' || !$record[$field_name] || $field_name == 'cat_id') continue;
				list($cat, $cat_id) = explode('-', $field_name);
				if(is_numeric($record[$field_name]) && $record[$field_name] != 1) {
					// Column has a single category ID
					$more_categories[] = $record[$field_name];
				} elseif($record[$field_name] == '1' ||
					(!is_numeric($record[$field_name]) && strtolower($record[$field_name]) == strtolower(lang('Yes')))) {
					// Each category got its own column.  '1' is the database value, lang('yes') is the human value
					$more_categories[] = $cat_id;
				} else {
					// Text categories
					$more_categories = array_merge($more_categories, importexport_helper_functions::cat_name2id(is_array($record[$field_name]) ? $record[$field_name] : explode(',',$record[$field_name]), $cat_id));
				}
			}
			if(count($more_categories) > 0) $record['cat_id'] = array_merge(is_array($record['cat_id']) ? $record['cat_id'] : explode(',',$record['cat_id']), $more_categories);

			// Private set but missing causes hidden entries
			if(array_key_exists('private', $record) && (!isset($record['private']) || $record['private'] == '')) unset($record['private']);

			// Format birthday as backend requires - converter should give timestamp
			if($record['bday'] && is_numeric($record['bday']))
			{
				$time = new egw_time($record['bday']);
				$record['bday'] = $time->format('Y-m-d');
			}

			if ( $_definition->plugin_options['conditions'] ) {
				foreach ( $_definition->plugin_options['conditions'] as $condition ) {
					$contacts = array();
					switch ( $condition['type'] ) {
						// exists
						case 'exists' :
							if($record[$condition['string']]) {
								$searchcondition = array( $condition['string'] => $record[$condition['string']]);
								// if we use account_id for the condition, we need to set the owner for filtering, as this
								// enables addressbook_so to decide what backend is to be used
								if ($condition['string']=='account_id') $searchcondition['owner']=0;
								$contacts = $this->bocontacts->search(
									//array( $condition['string'] => $record[$condition['string']],),
									'',
									$_definition->plugin_options['update_cats'] == 'add' ? false : true,
									'', '', '', false, 'AND', false,
									$searchcondition
								);
							}
							if ( is_array( $contacts ) && count( array_keys( $contacts ) ) >= 1 ) {
								// apply action to all contacts matching this exists condition
								$action = $condition['true'];
								foreach ( (array)$contacts as $contact ) {
									$record['id'] = $contact['id'];
									if ( $_definition->plugin_options['update_cats'] == 'add' ) {
										if ( !is_array( $contact['cat_id'] ) ) $contact['cat_id'] = explode( ',', $contact['cat_id'] );
										if ( !is_array( $record['cat_id'] ) ) $record['cat_id'] = explode( ',', $record['cat_id'] );
										$record['cat_id'] = implode( ',', array_unique( array_merge( $record['cat_id'], $contact['cat_id'] ) ) );
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
					if ($action['stop']) break;
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
	 * @param array $_data contact data for the action
	 * @return bool success or not
	 */
	private function action ( $_action, $_data, $record_num = 0 ) {
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
				// Only update if there are changes
				$old = $this->bocontacts->read($_data['id']);
				// if we get countrycodes as countryname, try to translate them -> the rest should be handled by bo classes.
				foreach(array('adr_one_', 'adr_two_') as $c_prefix) {
					if (strlen(trim($_data[$c_prefix.'countryname']))==2) $_data[$c_prefix.'countryname'] = $GLOBALS['egw']->country->get_full_name(trim($_data[$c_prefix.'countryname']),$translated=true);
				}
				// Don't change a user account into a contact
				if($old['owner'] == 0) {
					unset($_data['owner']);
				} elseif(!$this->definition->plugin_options['change_owner']) {
					// Don't change addressbook of an existing contact
					unset($_data['owner']);
				}

				// Merge to deal with fields not in import record
				$_data = array_merge($old, $_data);
				$changed = $this->tracking->changed_fields($_data, $old);
				if(count($changed) == 0) {
					return true;
				} else {
					//error_log(__METHOD__.__LINE__.array2string($changed).' Old:'.$old['adr_one_countryname'].' ('.$old['adr_one_countrycode'].') New:'.$_data['adr_one_countryname'].' ('.$_data['adr_one_countryname'].')');
				}
				
				// Make sure n_fn gets updated
				unset($_data['n_fn']);

				// Fall through
			case 'insert' :
				if($_action == 'insert') {
					// Addressbook backend doesn't like inserting with ID specified, it screws up the owner & etag
					unset($_data['id']);
				}
				if(!isset($_data['org_name'])) {
					// org_name is a trigger to update n_fileas
					$_data['org_name'] = '';
				}
				if ( $this->dry_run ) {
					//print_r($_data);
					$this->results[$_action]++;
					return true;
				} else {
					$result = $this->bocontacts->save( $_data, $this->is_admin);
					if(!$result) {
						$this->errors[$record_num] = $this->bocontacts->error;
					} else {
						$this->results[$_action]++;
					}
					return $result;
				}
			default:
				throw new egw_exception('Unsupported action');
			
		}
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Addressbook CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports contacts into your Addressbook from a CSV File. CSV means 'Comma Seperated Values'. However in the options Tab you can also choose other seperators.");
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
        * Returns warnings that were encountered during importing
        * Maximum of one warning message per record, but you can append if you need to
        *
        * @return Array (
        *       record_# => warning message
        *       )
        */
        public function get_warnings() {
		return $this->warnings;
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
} // end of iface_export_plugin
?>
