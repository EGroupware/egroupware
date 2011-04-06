<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id$
 */


/**
 * class import_csv for calendar
 */
class calendar_import_csv implements importexport_iface_import_plugin  {

	private static $plugin_options = array(
		'fieldsep', 		// char
		'charset',		// string
		'owner', 		// int
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
	protected static $actions = array( 'none', 'update', 'insert' );

	/**
	 * conditions for actions
	 *
	 * @var array
	 */
	protected static $conditions = array();

	/**
	 * @var definition
	 */
	private $definition;

	/**
	 * @var bo
	 */
	private $bo;

	/**
	* For figuring out if an entry has changed
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

		// fetch the addressbook bo
		$this->bo= new calendar_boupdate();

		// Get the tracker for changes
		$this->tracking = new calendar_tracking();

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

		// set eventOwner
		$_definition->plugin_options['owner'] = isset( $_definition->plugin_options['owner'] ) ?
			$_definition->plugin_options['owner'] : $this->user;

		// Start counting successes
		$count = 0;
		$this->results = array();

		// Failures
		$this->errors = array();

		// Used for participants
		$status_map = array_flip($this->bo->verbose_status);
		$role_map = array_flip($this->bo->roles);

		$lookups = array(
			'priority'	=> Array(
				0 => '',
				1 => lang('Low'),
				2 => lang('Normal'),
				3 => lang('High')
			),
		);

		while ( $record = $import_csv->get_record() ) {
			$success = false;

			// don't import empty records
			if( count( array_unique( $record ) ) < 2 ) continue;

			// Automatic conversions
			importexport_import_csv::convert($record, calendar_egw_record::$types, 'calendar', $lookups);

			// Set owner, unless it's supposed to come from CSV file
			if($_definition->plugin_options['owner_from_csv']) {
				if(!is_numeric($record['owner'])) {
					$this->errors[$import_csv->get_current_position()] = lang(
						'Invalid owner ID: %1.  Might be a bad field translation.  Used %2 instead.', 
						$record['owner'], 
						$_definition->plugin_options['owner']
					);
					$record['owner'] = $_definition->plugin_options['owner'];
				}
			} else {
				$record['owner'] = $_definition->plugin_options['owner'];
			}

			if ($record['participants'] && !is_array($record['participants'])) {
				// Importing participants in human friendly format
				preg_match_all('/(([^(]+?)( \(([0-9]+)\))? \((.+?)\) ([^,]+)),?/',$record['participants'],$participants);
				$record['participants'] = array();
				list($lines, $p, $names, $q, $quantity, $status, $role) = $participants;
				foreach($names as $key => $name) {
					$id = $GLOBALS['egw']->accounts->name2id($name, 'account_fullname');
					if(!$id) {
						$contacts = ExecMethod2('addressbook.addressbook_bo.search', $name,array('contact_id','account_id'),'org_name,n_family,n_given,cat_id,contact_email','','%',false,'OR',array(0,1));
						if($contacts) $id = $contacts[0]['account_id'] ? $contacts[0]['account_id'] : 'c'.$contacts[0]['id'];
					}
					if($id) {
						$record['participants'][$id] = calendar_so::combine_status(
							$status_map[lang($status[$key])] ? $status_map[lang($status[$key])] : $status[$key][0],
							$quantity[$key] ? $quantity[$key] : 1,
							$role_map[lang($role[$key])] ? $role_map[lang($role[$key])] : $role[$key]
						);
					}
				}
			}

			// Calendar doesn't actually support conditional importing
			if ( $_definition->plugin_options['conditions'] ) {
				foreach ( $_definition->plugin_options['conditions'] as $condition ) {
					$records = array();
					switch ( $condition['type'] ) {
						// exists
						case 'exists' :
							if($record[$condition['string']] && $condition['string'] == 'id') {
								$event = $this->bo->read($record[$condition['string']]);
								$records = array($event);
							}

							if ( is_array( $records ) && count( $records ) >= 1) {
								// apply action to all records matching this exists condition
								$action = $condition['true'];
								foreach ( (array)$records as $event ) {
									$record['id'] = $event['id'];
									if ( $_definition->plugin_options['update_cats'] == 'add' ) {
										if ( !is_array( $record['category'] ) ) $record['category'] = explode( ',', $record['category'] );
										$record['category'] = implode( ',', array_unique( array_merge( $record['category'], $event['category'] ) ) );
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
	 * @param array $_data record data for the action
	 * @return bool success or not
	 */
	private function action ( $_action, $_data, $record_num = 0 ) {
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
				// Only update if there are changes
				$old = $this->bo->read($_data['id']);

				// Don't change a user account into a record
				if(!$this->definition->plugin_options['change_owner']) {
					// Don't change owner of an existing record
					unset($_data['owner']);
				}

				// Merge to deal with fields not in import record
				$_data = array_merge($old, $_data);
				$changed = $this->tracking->changed_fields($_data, $old);
				if(count($changed) == 0) {
					return true;
				}
				// Fall through
			case 'insert' :
				if($_action == 'insert') {
					// Backend doesn't like inserting with ID specified, can overwrite existing
					unset($_data['id']);
				}
				// Make sure participants are set
				if(!$_data['participants']) {
					$user = $_data['owner'] ? $_data['owner'] : $this->user;
					$_data['participants'] = array(
						$user => 'U'
					);
				}
				if ( $this->dry_run ) {
					//print_r($_data);
					$this->results[$_action]++;
					return true;
				} else {
					$result = $this->bo->save( $_data, $this->is_admin);
					if(!$result) {
						$this->errors[$record_num] = lang('Unable to save');
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
		return lang('Calendar CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports events into your Calendar from a CSV File. CSV means 'Comma Seperated Values'. However in the options Tab you can also choose other seperators.");
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
} // end of iface_export_plugin
?>
