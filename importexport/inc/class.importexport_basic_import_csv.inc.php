<?php
/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 */

use EGroupware\Api;
use EGroupware\Api\Link;

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
		'record_owner', 	// int
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
		'override_values'	// Array of values specified that override what's in the file
		//	'col_id' => array('value' => ...)
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
	protected static $conditions = array( 'exists', 'equal', 'less_than');

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
	 * If doing a dry_run, instead of altering the DB, store the records here
	 */
	protected $preview_records = array();

	/**
	 * @var bool is current user admin?
	 */
	protected $is_admin = false;

	/**
	 * Select box values for human conversion
	 */
	protected $lookups = array();

	/**
	 * @var int
	 */
	protected $user = null;

	/**
	 * Application field types (from egw_record)
	 */
	protected $types = array();

	/**
	 * Maximum number of errors or warnings before aborting
	 */
	const MAX_MESSAGES = 100;

	/**
	 * List of import errors
	 */
	protected $errors = array();

	/**
	 * List of actions, and how many times that action was taken
	 */
	protected $results = array();

	/**
	 * Special fields that are not mapped to an application field, but are processed
	 * using special rules.
	 */
	public static $special_fields = array(
		'contact' => array('label' => 'Link to Addressbook', 'title'=>'use nlast,nfirst[,org] or contact_id from addressbook, eg: Guy,Test,My organization'),
		'link_search' => array('label' => 'Link by search', 'title'=>'appname:search terms the entry should be linked to, links to the first match eg: addressbook:My organization'),
		'link_custom' => array('label' => 'Link by custom field', 'title'=>'use <appname>:<custom_field_name>:|[<field_index>] links to first <appname> entry where given custom field matches given row column'),
		'link_0' => array('label' => 'Link to ID', 'title'=>'appname:appid the entry should be linked to, eg.: addressbook:123'),
	);

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

		if(!$this->dry_run)
		{
			// This needs to scan the whole file, so it can take a while
			$record_count = $import_csv->get_num_of_records();
		}

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
		$this->warnings = array();
		$this->errors = array();

		// Record class name
		$app = $_definition->application;
		$record_class = isset(static::$record_class) ? static::$record_class : "{$app}_egw_record";
		$this->types = $record_class::$types;

		// Needed for categories to work right
        $GLOBALS['egw_info']['flags']['currentapp'] = $app;

		$this->init($_definition, $import_csv);

		while ( $record = $import_csv->get_record() ) {
			$success = false;

			// don't import empty records
			if( count( array_unique( $record ) ) < 2 ) continue;


			$warning = importexport_import_csv::convert($record, $this->types, $app, $this->lookups, $_definition->plugin_options['convert']);
			if($warning) $this->warnings[$import_csv->get_current_position()] = $warning;

			$egw_record = new $record_class();
			$egw_record->set_record($record);
			if($_definition->plugin_options['override_values'])
			{
				$this->set_overrides($_definition, $egw_record);
			}
			try
			{
				$success = $this->import_record($egw_record, $import_csv);
			}
			catch (Exception $e)
			{
				$this->errors[] = $e->getMessage();
				$success = false;
				if(!$this->dry_run)
				{
					importexport_import_ui::sendUpdate("", get_class($e), $e->getMessage());
				}
			}

			if($success)
			{
				$this->do_special_fields($egw_record, $import_csv);
			}
			if($success) $count++;

			// Add some more time
			if($success && $import_csv->get_current_position() > 0 && $import_csv->get_current_position() % 100 == 0)
			{
				set_time_limit(10);
			}

			// Send an update to client
			if(!$this->dry_run)
			{
				$complete = $record_count ? (int)(100 * ($import_csv->get_current_position() / $record_count)) : false;
				$this->sendUpdate($complete, $egw_record);
			}

			// Keep a few records for preview, but process the whole file
			if($this->dry_run && $import_csv->get_current_position() < $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'])
			{
				$this->preview_records[] = $egw_record;
			}
			if(count($this->warnings) > self::MAX_MESSAGES || count($this->errors) > self::MAX_MESSAGES)
			{
				$this->errors[] = 'Too many errors, aborted';
				break;
			}
		}
		return $count;
	}

	/**
	 * Stub to hook into import initialization - set lookups, etc.
	 * @param importexport_definition $definition
	 * @param importexport_import_csv|null $import_csv
	 */
	protected function init(importexport_definition $definition, importexport_import_csv $import_csv = null)
	{
	}

	/**
	*Import a single record
	*
	* You don't need to worry about mappings or translations, they've been done already.
	* You do need to handle the conditions and the actions taken.
	*
	* Updates the count of actions taken
	*
	* @return boolean success
	*/
	protected function import_record(importexport_iface_egw_record &$record, &$import_csv)
	{
		if ( $this->definition->plugin_options['conditions'] ) {
			foreach ( $this->definition->plugin_options['conditions'] as $condition ) {
				$result = false;
				switch ( $condition['type'] ) {
					// exists
					case 'exists' :
						// Check for that record
						$result = $this->exists($record, $condition, $matches);
						break;
					case 'equal':
						// Match on field
						$result = $this->equal($record, $condition, $matches);
						break;

				// not supported action
					default :
						die('condition / action not supported!!!');
				}
				if($result)
				{
					// Apply true action to any matching records found
					$action = $condition['true'];
					$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
				}
				else
				{
					// Apply false action if no matching records found
					$action = $condition['false'];
					$success = ($this->action(  $action['action'], $record, $import_csv->get_current_position() ));
				}
				if ($action['stop']) break;
			}
		} else {
			// unconditional insert
			$success = $this->action( 'insert', $record, $import_csv->get_current_position() );
		}

		return $success;
	}

	/**
	 * Search for matching records, based on the the given condition
	 *
	 * @param record
	 * @param condition array = array('string' => field name)
	 * @param matches - On return, will be filled with matching records
	 *
	 * @return boolean
	 */
	protected function exists(importexport_iface_egw_record &$record, Array &$condition, &$matches = array())
	{
	}

	/**
	 * Search for records where the selected field matches the given value
	 *
	 * @param record
	 * @param condition array = array('string' => field name, 'type' => Type of condition, 'op_2' => second operand)
	 *
	 * @return boolean
	 */
	protected function equal(importexport_iface_egw_record &$record, Array &$condition)
	{
		$field = $condition['string'];
		return $record->$field == $condition['op_2'];
	}

	/**
	 * Search for records where the selected field is less than the given value.
	 * PHP's concept of 'less than' is used.
	 *
	 * @param record
	 * @param condition array = array('string' => field name, 'type' => Type of condition, 'op_2' => second operand)
	 *
	 * @return boolean
	 */
	protected function less_than(importexport_iface_egw_record &$record, Array &$condition)
	{
		$field = $condition['string'];
		return $record->$field < $condition['op_2'];
	}
	/**
	 * perform the required action
	 *
	 * If a record identifier (ID) is generated for the record because of the action
	 * (eg: a new entry inserted) make sure to update the record with the identifier
	 *
	 * Make sure you record any errors you encounter here:
	 * $this->errors[$record_num] = error message;
	 *
	 * @param int $_action one of $this->actions
	 * @param importexport_iface_egw_record $record contact data for the action
	 * @param int $record_num Which record number is being dealt with.  Used for error messages.
	 * @return bool success or not
	 */
	protected abstract function action ( $_action, importexport_iface_egw_record &$record, $record_num = 0 );

	/**
	 * Handle setting specific values from the definition on every record
	 *
	 *
	 * @param importexport_definition $_definition
	 * @param importexport_iface_egw_record $record
	 */
	protected function set_overrides(importexport_definition &$_definition, importexport_iface_egw_record &$record)
	{
		$overrides = $_definition->plugin_options['override_values'];

		// Set the record field, with some type checks
		$set = function($record, $field, $value)
		{
			if(is_array($record->$field))
			{
				array_unshift($record->$field, $value['value']);
			}
			else
			{
				$record->$field = $value['value'];
			}
		};

		// Set category
		if($overrides['cat_id'] && $record::$types['select-cat'])
		{
			foreach($record::$types['select-cat'] as $field)
			{
				$set($record, $field, $overrides['cat_id']);
			}
		}
	}

	/**
	 * Handle special fields
	 *
	 * These include linking to other records, which requires a valid identifier,
	 * so must be performed after the action.
	 *
	 * @param importexport_iface_egw_record $record
	 */
	protected function do_special_fields(importexport_iface_egw_record &$record, &$import_csv)
	{
		$id = $record->get_identifier();


		foreach(self::$special_fields as $field => $desc) {
			if(!$record->$field) continue;

			// Warn if there's no ID unless it's a dry_run because there probably won't be an ID then
			if(!$this->dry_run && !$id)
			{
				$this->warnings[$import_csv->get_current_position()] .= "Unable to link, no identifier for record";
				return;
			}
			if(strpos($field, 'link') === 0) {
				list($app, $app_id) = explode(':', $record->$field,2);

				list($link, $type) = explode('_',$field);

				// Searching, take first result
				if($type == 'custom')
				{
					$app_id = $this->link_by_cf($record, $app, $app_id, $import_csv->get_current_position());
				}
				else if($type == 'search')
				{
					$result = Link::query($app, $app_id);
					do
					{
						$app_id = key($result);
						shift($result);
					} while($result && !$app_id);
				}
			} else if (in_array($field, array_keys($GLOBALS['egw_info']['apps']))) {
				$app = $field;
				$app_id = $record->$field;

				// Searching, take first result
				if(!is_numeric($app_id))
				{
					$result = Link::query($app, $app_id);
					do
					{
						$app_id = key($result);
						shift($result);
					} while($result && !$app_id);
				}
			}
			else if ($field == 'contact')
			{
				// Special search limited to family, given, org_name
				$app = 'addressbook';
				$app_id = self::addr_id($record->$field);
			}
			if (!$this->dry_run && $app && $app_id && ($app != $this->definition->application || $app_id != $id))
			{
				$link_id = Link::link($this->definition->application,$id,$app,$app_id);
			}
		}
	}

	/**
	 * Reads entries, and presents them back as they will be understood
	 * with no changes to the system.
	 *
	 * Uses information from the egw_record and the associated import wizard
	 * to parse, normalize and export a human-friendly version of the data as
	 * a HTML table.
	 *
	 * @param stream $stream
	 * @param importexport_definition $definition
	 * @return String HTML for preview
     */
	public function preview( $stream, importexport_definition $definition )
	{
		$this->import($stream, $definition);
		rewind($stream);

		// Set up result
		$rows = array('h1' => array(), 'f1' => array(), '.h1' => 'class=th');

		// Check for no results
		if(!count($this->preview_records) || !is_object($this->preview_records[0]))
		{
			return Api\Html::table($rows);
		}

		// Load labels for app
		$record_class = get_class($this->preview_records[0]);

		// Get labels from wizard, if possible
		$labels = array_combine($definition->plugin_options['field_mapping'], $definition->plugin_options['field_mapping']);

		$plugin = get_called_class();
		$wizard_name = $definition->application . '_wizard_' . str_replace($definition->application . '_', '', $plugin);
		try
		{
			$wizard = new $wizard_name;
			$fields = $wizard->get_import_fields();
			foreach($labels as $field => &$label)
			{
				if($fields[$field])
				{
					$label = $fields[$field];
				}
			}
		}
		catch (Exception $e)
		{
			Api\Translation::add_app($definition->application);
			foreach($labels as $field => &$label)
			{
				$label = lang($label);
			}
		}

		// Set up HTML
		$rows['h1'] = $labels;
		foreach($this->preview_records as $i => $row_data)
		{
			// Convert to human-friendly
			importexport_export_csv::convert($row_data,$record_class::$types,$definition->application,$this->lookups);
			$this->row_preview($row_data);
			$rows[] = $row_data->get_record_array();
		}
		$this->preview_records = array();

		return Api\Html::table($rows);
	}

	/**
	 * Allows an extending class to alter a row for preview
	 *
	 * @param egw_record $row_entry
	 */
	protected function row_preview(importexport_iface_egw_record &$row_entry)
	{
	}

	/**
	 * Search for contact, but only using family, given & org name fields.
	 *
	 * Returns the ID of the first match.
	 *
	 * @staticvar type $contacts
	 * @param string $n_family
	 * @param string $n_given
	 * @param string $org_name
	 * @return int|boolean Contact ID of first match, or false if none found
	 */
	public static function addr_id( $n_family,$n_given=null,$org_name=null, &$record=null) {

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
			$contacts = new Api\Contacts();
		}
		if (!is_null($org_name))	// org_name given?
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
			if(!$record || !$record->get_identifier())
			{
				return $addrs[0]['id'];
			}
			else
			{
				do
				{
					$id = key($addrs);
					array_shift($addrs);
				} while($addrs && !$id && $id == $record->get_identifier());
				return $id;
			}
		}
		return False;
	}

	/**
	 * Get the primary key for an entry based on a custom field
	 * Returns key, so regular linking can take over
	 *
	 * @param int $record_number Row number, used for errors
	 * @param string $app Target application name
	 * @param string $value CSV value in the form of custom_field_name:value
	 */
	protected function link_by_cf(importexport_iface_egw_record &$record, $app, $value,$record_num)
	{
		$app_id = false;

		list($custom_field, $value) = explode(':',$value);
		// Find matching entry
		if($app && $custom_field && $value)
		{
			$cfs = Api\Storage\Customfields::get($app);
			// Error if no custom fields, probably something wrong in definition
			if(!$cfs[$custom_field])
			{
				// Check for users specifing label instead of name
				foreach($cfs as $name => $settings)
				{
					if(strtolower($settings['label']) == strtolower($custom_field))
					{
						$custom_field = $name;
						break;
					}
				}
			}

			// Couldn't find field, give an error - something's wrong
			if(!$cfs[$custom_field] && !$cfs[substr($custom_field,1)]) {
				$this->errors[$record_num] .= lang('No custom field "%1" for %2.',
					$custom_field, lang($app));
				return false;
			}
			if($custom_field[0] != '#') $custom_field = '#' . $custom_field;
error_log("Searching for $custom_field = $value");
			// Search
			if(Link::get_registry($app, 'query'))
			{
				$options = array('filter' => array("$custom_field = " . $GLOBALS['egw']->db->quote($value)));
				$result = Link::query($app, '', $options);

				// Only one allowed
				if($record->get_identifier())
				{
					while(key($result) == $record->get_identifier())
					{
						array_shift($result);
					}
				}
				if(count($result) != 1)
				{
					$this->warnings[$record_num] .= ($this->warnings[$record_num] ? "\n" : '') .
						lang('Unable to link to %3 by custom field "%1": "%4".  %2 matches.',
						$custom_field, count($result), lang($app), $options['filter'][0]
					);
					return false;
				}
				$app_id = key($result);
			}
		}
		return $app_id;
	}

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
	public function get_options_etpl(importexport_definition &$definition=null)
	{
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

	/**
	 * Send some progress so the UI doesn't look frozen
	 *
	 * @param integer $complete 0-100 or false for indeterminate
	 * @param importexport_iface_egw_record $record
	 * @return void
	 * @throws Api\Json\Exception
	 */
	protected function sendUpdate($complete, $record)
	{
		$label = "";
		try
		{
			$label = $record->get_title() ?: "";
		}
		catch (Exception $e)
		{
		}
		importexport_import_ui::sendUpdate($complete, $label, substr(array2string($record->get_record_array()), 0, 120) . '...');
	}
} // end of iface_export_plugin
?>
