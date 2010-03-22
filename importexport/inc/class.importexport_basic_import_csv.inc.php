<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 */


/**
 * A basic CSV import plugin.
 * 
 * You should extend this class to implement the various bits, but combined with the basic wizard 
 * should get you started on building a CSV plugin for an application fairly quickly.
 * 
 */
abstract class importexport_basic_import_csv implements importexport_iface_import_plugin  {

	protected static $plugin_options = array(
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
	 * Actions wich could be done to data entries
	 * If your plugin supports different actions, be sure to modify this array
	 */
	protected static $actions = array( 'none', 'update', 'insert', 'delete', );

	/**
	 * Conditions for actions
	 * If your plugin supports different conditions, be sure to modify this array
	 *
	 * @var array
	 */
	protected static $conditions = array( 'exists', 'greater', 'greater or equal', );

	/**
	 * This is the definition that will be used to deal with the CSV file
	 * @var definition
	 */
	protected $definition;

	/**
	 * @var bool
	 */
	protected $dry_run = false;

	/**
	 * @var bool is current user admin?
	 */
	protected $is_admin = false;

	/**
	 * @var int
	 */
	protected $user = null;

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
		$_definition->plugin_options['contact_owner'] = isset( $_definition->plugin_options['contact_owner'] ) ?
			$_definition->plugin_options['contact_owner'] : $this->user;

		// Start counting successes
		$count = 0;
		$this->results = array();

		// Failures
		$this->errors = array();

		while ( $record = $import_csv->get_record() ) {
			$success = false;

			// don't import empty records
			if( count( array_unique( $record ) ) < 2 ) continue;

			$record['owner'] = $this->_definition->plugin_options['contact_owner'];

			$success = $this->import_record($record, $import_csv);
			if($success) $count++;
		}
		return $count;
	}

	/**
	*	Import a single record
	*	
	*	You don't need to worry about mappings or translations, they've been done already.
	*	You do need to handle the conditions and the actions taken.
	*/
	protected abstract function import_record(&$record, &$import_csv);
	/* Example stub:
	{
		if ( $this->_definition->plugin_options['conditions'] ) {
			foreach ( $this->_definition->plugin_options['conditions'] as $condition ) {
				switch ( $condition['type'] ) {
					// exists
					case 'exists' :
						// Check for that record
						// Apply true action to any matching records found
							$action = $condition['true'];
							$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
						// Apply false action if no matching records found
							$action = $condition['false'];
							$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
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

		return $success;
	}
	*/

	/**
	 * perform the required action
	 * 
	 * Make sure you record any errors you encounter here:
	 * $this->errors[$record_num] = error message;
	 *
	 * @param int $_action one of $this->actions
	 * @param array $_data contact data for the action
	 * @param int $record_num Which record number is being dealt with.  Used for error messages.
	 * @return bool success or not
	 */
	protected abstract function action ( $_action, Array $_data, $record_num = 0 );

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Basic CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports information from a CSV file.  This is only a base class, and doesn't do anything on its own.");
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
