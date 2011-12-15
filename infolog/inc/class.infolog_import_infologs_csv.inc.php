<?php
/**
 * EGroupware - InfoLog CSV import
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */


/**
 * class import_csv for infolog
 */
class infolog_import_infologs_csv implements importexport_iface_import_plugin  {

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

	public static $special_fields = array(
		'projectmanager'  => 'Link to Projectmanager, use Project-ID, Title or @project_id(id_or_title)',
		'addressbook'     => 'Link to Addressbook, use nlast,nfirst[,org] or contact_id from addressbook',
		'link_1'      => '1. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_2'      => '2. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_3'      => '3. link: appname:appid the entry should be linked to, eg.: addressbook:123',
		'link_custom'	=> 'Link by custom field, use <appname>:<custom_field_name>:|[<field_index>]'
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
	private $boinfolog;

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

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		// dry run?
		$this->dry_run = isset( $_definition->plugin_options['dry_run'] ) ? $_definition->plugin_options['dry_run'] :  false;

		// fetch the infolog bo
		$this->boinfolog = new infolog_bo();

		// Get the tracker for changes
		$this->tracking = new infolog_tracking($this->boinfolog);

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
		$_definition->plugin_options['record_owner'] = $_definition->plugin_options['record_owner'] ?
			$_definition->plugin_options['record_owner'] : $this->user;
		$_definition->plugin_options['record_owner'] = $this->user;

		$_lookups = array(
			'info_type'	=>	$this->boinfolog->enums['types'],
			'info_status'	=>	$this->boinfolog->status['task']
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

			$lookups = $_lookups;
			if($record['info_type'] && $this->boinfolog->status[$record['info_type']])
			{
				$lookups['info_status'] = $this->boinfolog->status[$record['info_type']];
			}

			importexport_import_csv::convert($record, infolog_egw_record::$types, 'infolog', $lookups, $_definition->plugin_options['convert']);

			// Set default status for type, if not specified
			if(!$record['info_status'] && $record['info_type'])
			{
				$record['info_status'] = $this->boinfolog->status['defaults'][$record['info_type']];
			}

			// Set owner, unless it's supposed to come from CSV file
			if($_definition->plugin_options['owner_from_csv'])
			{
				if(!is_numeric($record['info_owner']))
				{
					$this->errors[$import_csv->get_current_position()] = lang(
						'Invalid owner ID: %1.  Might be a bad field translation.  Used %2 instead.',
						$record['info_owner'],
						$_definition->plugin_options['record_owner']
					);
					$record['info_owner'] = $_definition->plugin_options['record_owner'];
				}
			}
			else
			{
				$record['info_owner'] = $_definition->plugin_options['record_owner'];
			}
			if (!isset($record['info_owner'])) $record['info_owner'] = $GLOBALS['egw_info']['user']['account_id'];
			// Special values
			if ($record['addressbook'] && !is_numeric($record['addressbook']))
                        {
                                list($lastname,$firstname,$org_name) = explode(',',$record['addressbook']);
                                $record['addressbook'] = self::addr_id($lastname,$firstname,$org_name);
                        }
                        if ($record['projectmanager'] && !is_numeric($record['projectmanager']))
                        {
                                $record['projectmanager'] = self::project_id($record['projectmanager']);
                        }

			if ( $_definition->plugin_options['conditions'] )
			{
				foreach ( $_definition->plugin_options['conditions'] as $condition )
				{
					$results = array();
					switch ( $condition['type'] )
					{
						// exists
						case 'exists' :
							if($record[$condition['string']]) {
								$query['col_filter'] = array( $condition['string'] => $record[$condition['string']],);
								// Needed to query custom fields
								if($condition['string'][0] == '#') $query['custom_fields'] = true;
								$results = $this->boinfolog->search($query);
							}

							if ( is_array( $results ) && count( array_keys( $results )) >= 1) {
								// apply action to all records matching this exists condition
								$action = $condition['true'];
								foreach ( (array)$results as $contact ) {
									$record['info_id'] = $contact['info_id'];
									$record['info_owner'] = $contact['info_owner'];
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
					if ($action['last']) break;
				}
			}
			else
			{
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
		$result = true;
		switch ($_action) {
			case 'none' :
				return true;
			case 'update' :
				// Only update if there are changes
				$old = $this->boinfolog->read($_data['info_id']);

				if(!$this->definition->plugin_options['change_owner']) {
					// Don't change addressbook of an existing contact
					unset($_data['owner']);
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
					// Fake an XMLRPC call to avoid failing modification date check
					$GLOBALS['server']->last_method = '~fake it~';
					$result = $this->boinfolog->write( $_data, true, true);
					if(!$result) {
						$this->errors[$record_num] = lang('Permissions error - %1 could not %2',
							$GLOBALS['egw']->accounts->id2name($_data['info_owner']),
							lang($_action)
						);
					} else {
						$this->results[$_action]++;
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
		$info_link_id = $_data['info_link_id'];
		foreach(self::$special_fields as $field => $desc) {
			if(!$_data[$field]) continue;
			if(strpos($field, 'link') === 0) {
				list($app, $app_id) = explode(':', $_data[$field],2);

				// Linking to another entry based on matching custom fields
				if($field == 'link_custom')
				{
					$app_id = $this->link_by_cf($record_num, $app, $field, $app_id);
				}
			} else {
				$app = $field;
				$app_id = $_data[$field];
			}
			if ($app && $app_id) {
				$id = $_data['info_id'] ? $_data['info_id'] : (int)$result;
				//echo "<p>linking infolog:$id with $app:$app_id</p>\n";
				$link_id = egw_link::link('infolog',$id,$app,$app_id);
				if ($link_id && !$info_link_id)
				{
					$to_write = array(
						'info_id'      => $id,
						'info_link_id' => $link_id,
					);
					$this->boinfolog->write($to_write);
					$info_link_id = $link_id;
				}
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
		return lang('Infolog CSV import');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Imports entries into the infolog from a CSV File. CSV means 'Comma Seperated Values'. However in the options Tab you can also choose other seperators.");
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

	public static function project_id($num_or_title)
	{
		static $boprojects;

		if (!$num_or_title) return false;

		if (!is_object($boprojects))
		{
			$boprojects =& CreateObject('projectmanager.boprojectmanager');
		}
		if (($projects = $boprojects->search(array('pm_number' => $num_or_title))) ||
			($projects = $boprojects->search(array('pm_title'  => $num_or_title))))
		{
			return $projects[0]['pm_id'];
		}
		return false;
	}

	/**
	 * Get the primary key for an entry based on a custom field
	 * Returns key, so regular linking can take over
	 */
	protected function link_by_cf($record_num, $app, $fieldname, $value) 
	{
		$app_id = false;

		list($custom_field, $value) = explode(':',$value);
		// Find matching entry
		if($app && $custom_field && $value)
		{
			$cfs = config::get_customfields($app);
			if(!$cfs[$custom_field])
			{
				// Check for users specifing label instead of name
				foreach($cfs as $name => $settings)
				{
					if($settings['label'] == $custom_field)
					{
						$custom_field = $name;
						break;
					}
				}
			}
			if($custom_field[0] != '#') $custom_field = '#' . $custom_field;

			// Search
			if(egw_link::get_registry($app, 'query'))
			{
				$options = array('filter' => array($custom_field => $value));
				$result = egw_link::query($app, '', $options);
			
				// Only one allowed
				if(count($result) != 1)
				{
					$this->errors[$record_num] .= lang('Unable to link to %3 by custom field "%1".  %2 matches.', 
						$custom_field, count($result), lang($app));
					return false;
				}
				$app_id = key($result);
			}
		}
		return $app_id;
	}
}
?>
