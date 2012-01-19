<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 * @version $Id: $
 */


/**
 * class import_csv for admin (groups)
 */
class admin_import_groups_csv implements importexport_iface_import_plugin  {

	private static $plugin_options = array(
		'fieldsep', 		// char
		'charset', 		// string
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
	 * actions which could be done to data entries
	 */
	protected static $actions = array( 'none', 'update', 'create', 'delete');

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
	 * @var bool
	 */
	private $dry_run = false;

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

		// dry run?
		$this->dry_run = isset( $_definition->plugin_options['dry_run'] ) ? $_definition->plugin_options['dry_run'] :  false;

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

		// Start counting successes
		$count = 0;
		$this->results = array();

		// Failures
		$this->errors = array();

		while ( $record = $import_csv->get_record() ) {
			$success = false;
			// don't import empty records
			if( count( array_unique( $record ) ) < 2 ) continue;

			importexport_import_csv::convert($record, admin_egw_group_record::$types, 'admin');

			if ( $_definition->plugin_options['conditions'] ) {
				foreach ( $_definition->plugin_options['conditions'] as $condition ) {
					switch ( $condition['type'] ) {
						// exists
						case 'exists' :
							$accounts = $GLOBALS['egw']->accounts->search(array(
								'type' => 'groups',
								'query' => $record[$condition['string']],
								'query_type' => $condition['string']
							));
							if ( is_array( $accounts ) && count( $accounts ) >= 1 ) {
								$account = current($accounts);
								// apply action to all contacts matching this exists condition
								$action = $condition['true'];
								foreach ( (array)$accounts as $account ) {
									// Read full account, and copy needed info for accounts->save()
									$account = $GLOBALS['egw']->accounts->read($account['account_id']);
									$record['account_id'] = $account['account_id'];
									$record['account_firstname'] = $account['account_firstname'];
									$record['account_lastname'] = $account['account_lastname'];
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
	 * @param array $_data contact data for the action
	 * @return bool success or not
	 */
	private function action ( $_action, $_data, $record_num = 0 ) {
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
			case 'create' :
				if(count($_data['account_members']) < 1) {
					$this->errors[$record_num] = lang('You must select at least one group member.');
					return false;
				}
				$command = new admin_cmd_edit_group($_action == 'create' ? false : $_data['account_lid'], $_data);
				if($this->dry_run) {
					$this->results[$_action]++;
					return true;
				}
				try {
					$command->run();
				} catch (Exception $e) {
					$this->errors[$record_num] = $e->getMessage();
					return false;
				}
				$this->results[$_action]++;
				return true;
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
		return lang('Group CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Creates / updates user groups from CSV file");
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
	* Maximum of one warning message per record, but you can concatenate them if you need to
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
